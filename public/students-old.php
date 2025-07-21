<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole(['admin', 'training_partner']);

$database = new Database();
$db = $database->getConnection();

$pageTitle = 'Student Management';
$currentUser = $auth->getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'bulk_action') {
        try {
            $studentIds = $_POST['student_ids'] ?? [];
            $bulkAction = $_POST['bulk_action'] ?? '';
            
            if (empty($studentIds)) {
                throw new Exception("No students selected");
            }
            
            $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
            
            switch ($bulkAction) {
                case 'activate':
                    $stmt = $db->prepare("UPDATE students SET status = 'active' WHERE id IN ($placeholders)");
                    $stmt->execute($studentIds);
                    $success = count($studentIds) . " students activated successfully!";
                    break;
                    
                case 'deactivate':
                    $stmt = $db->prepare("UPDATE students SET status = 'inactive' WHERE id IN ($placeholders)");
                    $stmt->execute($studentIds);
                    $success = count($studentIds) . " students deactivated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $db->prepare("DELETE FROM students WHERE id IN ($placeholders)");
                    $stmt->execute($studentIds);
                    $success = count($studentIds) . " students deleted successfully!";
                    break;
                    
                default:
                    throw new Exception("Invalid bulk action");
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'add_student') {
        try {
            // Validate required fields
            $required_fields = ['aadhaar', 'name', 'father_name', 'dob', 'gender', 'phone'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            // Check if Aadhaar already exists
            $stmt = $db->prepare("SELECT id FROM students WHERE aadhaar = ?");
            $stmt->execute([$_POST['aadhaar']]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Student with this Aadhaar number already exists");
            }
            
            // Generate enrollment number
            $course_id = $_POST['course_id'] ?? null;
            $enrollment_number = generateEnrollmentNumber($db, $course_id);
            
            // Handle file uploads
            $photo_path = '';
            $aadhaar_doc_path = '';
            $education_doc_path = '';
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $photo_path = uploadFile($_FILES['photo'], 'photos', ['jpg', 'jpeg', 'png']);
            }
            
            if (isset($_FILES['aadhaar_doc']) && $_FILES['aadhaar_doc']['error'] === 0) {
                $aadhaar_doc_path = uploadFile($_FILES['aadhaar_doc'], 'documents', ['jpg', 'jpeg', 'pdf']);
            }
            
            if (isset($_FILES['education_doc']) && $_FILES['education_doc']['error'] === 0) {
                $education_doc_path = uploadFile($_FILES['education_doc'], 'documents', ['jpg', 'jpeg', 'pdf']);
            }
            
            // Get training center for training partner
            $training_center_id = null;
            if ($currentUser['role'] === 'training_partner') {
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE user_id = ?");
                $stmt->execute([$currentUser['id']]);
                $center = $stmt->fetch(PDO::FETCH_ASSOC);
                $training_center_id = $center['id'] ?? null;
            } else {
                $training_center_id = $_POST['training_center_id'] ?? null;
            }
            
            // Insert student
            $stmt = $db->prepare("
                INSERT INTO students (
                    enrollment_number, aadhaar, name, father_name, dob, gender, phone, email, 
                    address, education, religion, marital_status, photo_path, aadhaar_doc_path, 
                    education_doc_path, course_id, training_center_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $enrollment_number,
                $_POST['aadhaar'],
                $_POST['name'],
                $_POST['father_name'],
                $_POST['dob'],
                $_POST['gender'],
                $_POST['phone'],
                $_POST['email'] ?? null,
                $_POST['address'] ?? null,
                $_POST['education'] ?? null,
                $_POST['religion'] ?? null,
                $_POST['marital_status'] ?? null,
                $photo_path,
                $aadhaar_doc_path,
                $education_doc_path,
                $course_id,
                $training_center_id
            ]);
            
            $student_id = $db->lastInsertId();
            
            // Create user account for student
            $student_password = password_hash('softpro@123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, role, name, phone) 
                VALUES (?, ?, ?, 'student', ?, ?)
            ");
            $stmt->execute([
                $_POST['phone'], // username is phone number
                $_POST['email'] ?? $_POST['phone'] . '@student.com',
                $student_password,
                $_POST['name'],
                $_POST['phone']
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Update student with user_id
            $stmt = $db->prepare("UPDATE students SET user_id = ? WHERE id = ?");
            $stmt->execute([$user_id, $student_id]);
            
            // Add to batch if selected
            if (!empty($_POST['batch_id'])) {
                $stmt = $db->prepare("INSERT INTO student_batches (student_id, batch_id) VALUES (?, ?)");
                $stmt->execute([$student_id, $_POST['batch_id']]);
            }
            
            // Record registration fee
            $stmt = $db->prepare("
                INSERT INTO fee_payments (student_id, amount, payment_type, payment_method, payment_date, status) 
                VALUES (?, 100, 'registration', 'cash', CURDATE(), 'approved')
            ");
            $stmt->execute([$student_id]);
            
            $success = "Student registered successfully! Enrollment Number: $enrollment_number";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'edit_student') {
        try {
            $student_id = $_POST['student_id'];
            
            // Handle file uploads
            $update_fields = [];
            $update_values = [];
            
            $fields = ['name', 'father_name', 'dob', 'gender', 'phone', 'email', 'address', 'education', 'religion', 'marital_status'];
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $update_fields[] = "$field = ?";
                    $update_values[] = $_POST[$field];
                }
            }
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $photo_path = uploadFile($_FILES['photo'], 'photos', ['jpg', 'jpeg', 'png']);
                $update_fields[] = "photo_path = ?";
                $update_values[] = $photo_path;
            }
            
            if (isset($_FILES['aadhaar_doc']) && $_FILES['aadhaar_doc']['error'] === 0) {
                $aadhaar_doc_path = uploadFile($_FILES['aadhaar_doc'], 'documents', ['jpg', 'jpeg', 'pdf']);
                $update_fields[] = "aadhaar_doc_path = ?";
                $update_values[] = $aadhaar_doc_path;
            }
            
            if (isset($_FILES['education_doc']) && $_FILES['education_doc']['error'] === 0) {
                $education_doc_path = uploadFile($_FILES['education_doc'], 'documents', ['jpg', 'jpeg', 'pdf']);
                $update_fields[] = "education_doc_path = ?";
                $update_values[] = $education_doc_path;
            }
            
            if (!empty($update_fields)) {
                $update_values[] = $student_id;
                $stmt = $db->prepare("UPDATE students SET " . implode(', ', $update_fields) . " WHERE id = ?");
                $stmt->execute($update_values);
            }
            
            $success = "Student updated successfully!";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'delete_student') {
        try {
            $student_id = $_POST['student_id'];
            
            // Delete student and related records
            $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            
            $success = "Student deleted successfully!";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="students_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Get students for export (without pagination)
    $export_query = "
        SELECT s.enrollment_number, s.name, s.father_name, s.aadhaar, s.phone, s.email, 
               s.dob, s.gender, s.address, s.education, s.religion, s.marital_status,
               c.name as course_name, tc.name as center_name, s.status, s.created_at
        FROM students s 
        LEFT JOIN courses c ON s.course_id = c.id 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        $where_clause
        ORDER BY s.created_at DESC
    ";
    
    $stmt = $db->prepare($export_query);
    $stmt->execute($where_values);
    $export_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output Excel content
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Enrollment Number</th>';
    echo '<th>Name</th>';
    echo '<th>Father\'s Name</th>';
    echo '<th>Aadhaar</th>';
    echo '<th>Phone</th>';
    echo '<th>Email</th>';
    echo '<th>Date of Birth</th>';
    echo '<th>Gender</th>';
    echo '<th>Address</th>';
    echo '<th>Education</th>';
    echo '<th>Religion</th>';
    echo '<th>Marital Status</th>';
    echo '<th>Course</th>';
    echo '<th>Training Center</th>';
    echo '<th>Status</th>';
    echo '<th>Registration Date</th>';
    echo '</tr>';
    
    foreach ($export_students as $student) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($student['enrollment_number']) . '</td>';
        echo '<td>' . htmlspecialchars($student['name']) . '</td>';
        echo '<td>' . htmlspecialchars($student['father_name']) . '</td>';
        echo '<td>' . htmlspecialchars($student['aadhaar']) . '</td>';
        echo '<td>' . htmlspecialchars($student['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($student['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['dob']) . '</td>';
        echo '<td>' . htmlspecialchars($student['gender']) . '</td>';
        echo '<td>' . htmlspecialchars($student['address'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['education'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['religion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['marital_status'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['course_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['center_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['status']) . '</td>';
        echo '<td>' . date('d-m-Y', strtotime($student['created_at'])) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

$where_conditions = [];
$where_values = [];

if ($currentUser['role'] === 'training_partner') {
    $stmt = $db->prepare("SELECT id FROM training_centers WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $center = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($center) {
        $where_conditions[] = "s.training_center_id = ?";
        $where_values[] = $center['id'];
    }
}

// Apply filters
if (!empty($_GET['course_id'])) {
    $where_conditions[] = "s.course_id = ?";
    $where_values[] = $_GET['course_id'];
}

if (!empty($_GET['training_center_id']) && $currentUser['role'] === 'admin') {
    $where_conditions[] = "s.training_center_id = ?";
    $where_values[] = $_GET['training_center_id'];
}

if (!empty($_GET['status'])) {
    $where_conditions[] = "s.status = ?";
    $where_values[] = $_GET['status'];
}

if (!empty($_GET['search'])) {
    $where_conditions[] = "(s.name LIKE ? OR s.enrollment_number LIKE ? OR s.phone LIKE ?)";
    $search_term = '%' . $_GET['search'] . '%';
    $where_values[] = $search_term;
    $where_values[] = $search_term;
    $where_values[] = $search_term;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM students s $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($where_values);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get students
$query = "
    SELECT s.*, c.name as course_name, tc.name as center_name,
           (SELECT COUNT(*) FROM fee_payments fp WHERE fp.student_id = s.id AND fp.status = 'approved') as payments_count
    FROM students s 
    LEFT JOIN courses c ON s.course_id = c.id 
    LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
    $where_clause
    ORDER BY s.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($where_values);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses for filters and forms
$stmt = $db->prepare("SELECT * FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training centers for admin
$training_centers = [];
if ($currentUser['role'] === 'admin') {
    $stmt = $db->prepare("SELECT * FROM training_centers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $training_centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get batches for enrollment
$batches = [];
if ($currentUser['role'] === 'admin') {
    $stmt = $db->prepare("SELECT b.*, c.name as course_name FROM batches b LEFT JOIN courses c ON b.course_id = c.id WHERE b.status IN ('upcoming', 'ongoing') ORDER BY b.start_date");
} else {
    $stmt = $db->prepare("SELECT b.*, c.name as course_name FROM batches b LEFT JOIN courses c ON b.course_id = c.id JOIN training_centers tc ON b.training_center_id = tc.id WHERE tc.user_id = ? AND b.status IN ('upcoming', 'ongoing') ORDER BY b.start_date");
    $stmt->execute([$currentUser['id']]);
}
$stmt->execute();
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function generateEnrollmentNumber($db, $course_id = null) {
    $year = date('Y');
    $month = date('m');
    
    $prefix = 'ST' . $year . $month;
    
    if ($course_id) {
        $stmt = $db->prepare("SELECT code FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($course) {
            $prefix .= strtoupper(substr($course['code'], 0, 3));
        }
    }
    
    // Get next sequence number
    $stmt = $db->prepare("SELECT COUNT(*) + 1 as next_seq FROM students WHERE enrollment_number LIKE ?");
    $stmt->execute([$prefix . '%']);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC)['next_seq'];
    
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function uploadFile($file, $folder, $allowed_extensions) {
    $upload_dir = "../uploads/$folder/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception("Invalid file type. Allowed: " . implode(', ', $allowed_extensions));
    }
    
    if ($file['size'] > 2048 * 1024) { // 2MB limit
        throw new Exception("File size too large. Maximum 2MB allowed.");
    }
    
    $filename = uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception("Failed to upload file");
    }
    
    return "uploads/$folder/$filename";
}

include '../includes/layout.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-users me-2"></i>Student Management</h2>
            <p class="text-muted">Manage student registrations and information</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-user-plus me-2"></i>Add Student
            </button>
            <button class="btn btn-outline-primary" onclick="exportStudents()">
                <i class="fas fa-download me-2"></i>Export
            </button>
        </div>
    </div>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Name, Enrollment No, Phone">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo ($_GET['course_id'] ?? '') == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($currentUser['role'] === 'admin'): ?>
                <div class="col-md-3">
                    <label class="form-label">Training Center</label>
                    <select class="form-select" name="training_center_id">
                        <option value="">All Centers</option>
                        <?php foreach ($training_centers as $center): ?>
                        <option value="<?php echo $center['id']; ?>" <?php echo ($_GET['training_center_id'] ?? '') == $center['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($center['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($_GET['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($_GET['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="completed" <?php echo ($_GET['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Students Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Students (<?php echo number_format($total_records); ?>)</h5>
            <div class="btn-group">
                <button class="btn btn-outline-secondary btn-sm" onclick="bulkAction('activate')">
                    <i class="fas fa-check me-1"></i>Activate Selected
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="bulkAction('deactivate')">
                    <i class="fas fa-times me-1"></i>Deactivate Selected
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="bulkAction('delete')">
                    <i class="fas fa-trash me-1"></i>Delete Selected
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($students)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Photo</th>
                            <th>Enrollment No.</th>
                            <th>Name</th>
                            <th>Father's Name</th>
                            <th>Phone</th>
                            <th>Course</th>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <th>Training Center</th>
                            <?php endif; ?>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>"></td>
                            <td>
                                <?php if ($student['photo_path']): ?>
                                    <img src="<?php echo htmlspecialchars($student['photo_path']); ?>" alt="Photo" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['enrollment_number']); ?></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['phone']); ?></td>
                            <td><?php echo htmlspecialchars($student['course_name'] ?? 'Not Assigned'); ?></td>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <td><?php echo htmlspecialchars($student['center_name'] ?? 'Not Assigned'); ?></td>
                            <?php endif; ?>
                            <td><?php echo date('d-m-Y', strtotime($student['created_at'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : ($student['status'] === 'completed' ? 'info' : 'secondary'); ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewStudent(<?php echo $student['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="editStudent(<?php echo $student['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteStudent(<?php echo $student['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No students found</h5>
                <p class="text-muted">Click "Add Student" to register your first student</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addStudentForm">
                <input type="hidden" name="action" value="add_student">
                
                <div class="modal-body">
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-lg-8">
                            <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Aadhaar Number *</label>
                                    <input type="text" class="form-control" name="aadhaar" required maxlength="12" pattern="[0-9]{12}">
                                    <div class="form-text">12-digit Aadhaar number</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Father's Name *</label>
                                    <input type="text" class="form-control" name="father_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" name="dob" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Gender *</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" name="phone" required pattern="[0-9]{10}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Religion</label>
                                    <input type="text" class="form-control" name="religion">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Marital Status</label>
                                    <select class="form-select" name="marital_status">
                                        <option value="">Select Status</option>
                                        <option value="single">Single</option>
                                        <option value="married">Married</option>
                                        <option value="divorced">Divorced</option>
                                        <option value="widowed">Widowed</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Education</label>
                                <input type="text" class="form-control" name="education" placeholder="e.g., 12th Pass, Graduate">
                            </div>
                        </div>
                        
                        <!-- Course and Batch Assignment -->
                        <div class="col-lg-4">
                            <h6 class="border-bottom pb-2 mb-3">Course & Batch</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Course</label>
                                <select class="form-select" name="course_id" id="courseSelect">
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <div class="mb-3">
                                <label class="form-label">Training Center</label>
                                <select class="form-select" name="training_center_id">
                                    <option value="">Select Center</option>
                                    <?php foreach ($training_centers as $center): ?>
                                    <option value="<?php echo $center['id']; ?>"><?php echo htmlspecialchars($center['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Assign to Batch (Optional)</label>
                                <select class="form-select" name="batch_id">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>">
                                        <?php echo htmlspecialchars($batch['name'] . ' - ' . $batch['course_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- File Uploads -->
                            <h6 class="border-bottom pb-2 mb-3 mt-4">Documents</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Student Photo</label>
                                <div class="file-upload-area" onclick="document.getElementById('photoInput').click()">
                                    <i class="fas fa-camera fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Click to upload photo</p>
                                    <small class="text-muted">JPG, JPEG, PNG (Max 2MB)</small>
                                </div>
                                <input type="file" id="photoInput" name="photo" accept="image/*" style="display: none;">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Aadhaar Document</label>
                                <div class="file-upload-area" onclick="document.getElementById('aadhaarInput').click()">
                                    <i class="fas fa-id-card fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Click to upload Aadhaar</p>
                                    <small class="text-muted">JPG, JPEG, PDF (Max 2MB)</small>
                                </div>
                                <input type="file" id="aadhaarInput" name="aadhaar_doc" accept=".jpg,.jpeg,.pdf" style="display: none;">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Education Document</label>
                                <div class="file-upload-area" onclick="document.getElementById('educationInput').click()">
                                    <i class="fas fa-graduation-cap fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Click to upload education certificate</p>
                                    <small class="text-muted">JPG, JPEG, PDF (Max 2MB)</small>
                                </div>
                                <input type="file" id="educationInput" name="education_doc" accept=".jpg,.jpeg,.pdf" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Register Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="studentDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Form validation
document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    if (!validateForm('addStudentForm')) {
        e.preventDefault();
        showToast('Please fill in all required fields', 'error');
    }
});

// File upload previews
document.getElementById('photoInput').addEventListener('change', function() {
    if (this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.createElement('img');
            preview.src = e.target.result;
            preview.style.maxWidth = '100px';
            preview.style.maxHeight = '100px';
            preview.className = 'img-thumbnail mt-2';
            
            const container = document.getElementById('photoInput').previousElementSibling;
            const existingPreview = container.querySelector('img');
            if (existingPreview) {
                existingPreview.remove();
            }
            container.appendChild(preview);
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// View student details
function viewStudent(studentId) {
    fetch(`api/students.php?action=get_student&id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStudentDetails(data.student);
                new bootstrap.Modal(document.getElementById('viewStudentModal')).show();
            } else {
                showToast('Error loading student details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading student details', 'error');
        });
}

function displayStudentDetails(student) {
    const content = document.getElementById('studentDetailsContent');
    content.innerHTML = `
        <div class="row">
            <div class="col-md-3 text-center">
                ${student.photo_path ? 
                    `<img src="${student.photo_path}" alt="Student Photo" class="img-fluid rounded mb-3" style="max-width: 200px;">` :
                    `<div class="bg-secondary rounded d-flex align-items-center justify-content-center mb-3" style="width: 200px; height: 250px; margin: 0 auto;">
                        <i class="fas fa-user fa-4x text-white"></i>
                    </div>`
                }
            </div>
            <div class="col-md-9">
                <h4>${student.name}</h4>
                <p class="text-muted">Enrollment No: ${student.enrollment_number}</p>
                
                <div class="row">
                    <div class="col-sm-6"><strong>Aadhaar:</strong> ${student.aadhaar}</div>
                    <div class="col-sm-6"><strong>Father's Name:</strong> ${student.father_name}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-6"><strong>DOB:</strong> ${formatDate(student.dob)}</div>
                    <div class="col-sm-6"><strong>Gender:</strong> ${student.gender}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-6"><strong>Phone:</strong> ${student.phone}</div>
                    <div class="col-sm-6"><strong>Email:</strong> ${student.email || 'Not provided'}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-6"><strong>Course:</strong> ${student.course_name || 'Not assigned'}</div>
                    <div class="col-sm-6"><strong>Training Center:</strong> ${student.center_name || 'Not assigned'}</div>
                </div>
                
                ${student.address ? `<div class="mt-3"><strong>Address:</strong><br>${student.address}</div>` : ''}
                
                <div class="mt-3">
                    <strong>Documents:</strong>
                    <div class="d-flex gap-2 mt-2">
                        ${student.aadhaar_doc_path ? `<a href="${student.aadhaar_doc_path}" target="_blank" class="btn btn-sm btn-outline-primary">View Aadhaar</a>` : ''}
                        ${student.education_doc_path ? `<a href="${student.education_doc_path}" target="_blank" class="btn btn-sm btn-outline-primary">View Education</a>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Helper function to format date
function formatDate(dateString) {
    if (!dateString) return 'Not provided';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB');
}

// Edit student
function editStudent(studentId) {
    // Implementation for edit student modal
    showToast('Edit functionality will be implemented', 'info');
}

// Delete student
function deleteStudent(studentId) {
    if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_student');
        formData.append('student_id', studentId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            showToast('Student deleted successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting student', 'error');
        });
    }
}

// Bulk actions
function bulkAction(action) {
    const selectedStudents = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
    
    if (selectedStudents.length === 0) {
        showToast('Please select at least one student', 'warning');
        return;
    }
    
    const actionText = action === 'delete' ? 'delete' : action;
    if (confirm(`Are you sure you want to ${actionText} ${selectedStudents.length} student(s)?`)) {
        const formData = new FormData();
        formData.append('action', 'bulk_action');
        formData.append('bulk_action', action);
        selectedStudents.forEach(id => formData.append('student_ids[]', id));
        
        fetch('students.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            showToast(`Bulk ${action} completed successfully`, 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .catch(error => {
            console.error('Error:', error);
            showToast(`Error performing bulk ${action}`, 'error');
        });
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Export students
function exportStudents() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'excel');
    window.open(currentUrl.toString(), '_blank');
}

// Auto-generate enrollment number when course is selected
document.getElementById('courseSelect').addEventListener('change', function() {
    // This would typically fetch a preview of the enrollment number
    // Implementation depends on the specific enrollment number format
});
</script>

<?php include '../includes/layout.php'; ?>

<?php
// Students Management - v2.0
session_start();
require_once '../config/database-v2.php';

$currentUser = ['role' => 'admin', 'name' => 'Administrator'];
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getV2Connection();
        
        if ($action === 'add') {
            // Add new student
            $full_name = trim($_POST['full_name'] ?? '');
            $father_name = trim($_POST['father_name'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $aadhar_number = trim($_POST['aadhar_number'] ?? '');
            $qualification = $_POST['qualification'] ?? '';
            $category = $_POST['category'] ?? '';
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $training_center_id = intval($_POST['training_center_id'] ?? 0);
            
            // Convert DD-MM-YYYY to YYYY-MM-DD for database
            if ($date_of_birth && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date_of_birth, $matches)) {
                $date_of_birth = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }
            
            // Split full name into first and last name
            $nameParts = explode(' ', $full_name, 2);
            $first_name = $nameParts[0];
            $last_name = isset($nameParts[1]) ? $nameParts[1] : '';
            
            if ($full_name && $phone && $training_center_id) {
                // Generate enrollment number
                $enrollment_number = 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("
                    INSERT INTO students (
                        enrollment_number, first_name, last_name, father_name, date_of_birth, gender,
                        email, phone, aadhar_number, qualification, category, address_line1,
                        state, pincode, training_center_id, admission_date, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'enrolled', NOW())
                ");
                
                $stmt->execute([
                    $enrollment_number, $first_name, $last_name, $father_name, $date_of_birth, $gender,
                    $email, $phone, $aadhar_number, $qualification, $category, $address_line1,
                    $state, $pincode, $training_center_id
                ]);
                
                $message = "Student added successfully! Enrollment Number: $enrollment_number";
                $messageType = "success";
            } else {
                $message = "Please fill all required fields.";
                $messageType = "error";
            }
        }
        
        if ($action === 'edit') {
            // Edit student
            $id = intval($_POST['id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $father_name = trim($_POST['father_name'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $aadhar_number = trim($_POST['aadhar_number'] ?? '');
            $qualification = $_POST['qualification'] ?? '';
            $category = $_POST['category'] ?? '';
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $training_center_id = intval($_POST['training_center_id'] ?? 0);
            $status = $_POST['status'] ?? 'enrolled';
            
            // Convert DD-MM-YYYY to YYYY-MM-DD for database
            if ($date_of_birth && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date_of_birth, $matches)) {
                $date_of_birth = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }
            
            // Split full name into first and last name
            $nameParts = explode(' ', $full_name, 2);
            $first_name = $nameParts[0];
            $last_name = isset($nameParts[1]) ? $nameParts[1] : '';
            
            if ($id && $full_name && $phone) {
                $stmt = $conn->prepare("
                    UPDATE students SET 
                        first_name = ?, last_name = ?, father_name = ?, date_of_birth = ?, gender = ?,
                        email = ?, phone = ?, aadhar_number = ?, qualification = ?, category = ?,
                        address_line1 = ?, state = ?, pincode = ?, training_center_id = ?,
                        status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $first_name, $last_name, $father_name, $date_of_birth, $gender,
                    $email, $phone, $aadhar_number, $qualification, $category,
                    $address_line1, $state, $pincode, $training_center_id, $status, $id
                ]);
                
                $message = "Student updated successfully!";
                $messageType = "success";
            }
        }
        
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("UPDATE students SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = "Student deleted successfully!";
                $messageType = "success";
            }
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$center_filter = $_GET['center'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build search query
$whereConditions = ["s.status != 'deleted'"];
$params = [];

if ($search) {
    $whereConditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.enrollment_number LIKE ? OR s.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($center_filter) {
    $whereConditions[] = "s.training_center_id = ?";
    $params[] = $center_filter;
}

if ($status_filter) {
    $whereConditions[] = "s.status = ?";
    $params[] = $status_filter;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    $conn = getV2Connection();
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM students s WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalStudents = $countStmt->fetchColumn();
    $totalPages = ceil($totalStudents / $limit);
    
    // Get students with pagination
    $sql = "
        SELECT s.*, tc.center_name 
        FROM students s 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        WHERE $whereClause 
        ORDER BY s.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    // Get training centers for dropdowns
    $centerStmt = $conn->query("SELECT id, center_name FROM training_centers WHERE status = 'active' ORDER BY center_name");
    $trainingCenters = $centerStmt->fetchAll();
    
} catch (Exception $e) {
    $students = [];
    $trainingCenters = [];
    if (empty($message)) {
        $message = "Error loading data: " . $e->getMessage();
        $messageType = "error";
    }
}

// States array
$states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat',
    'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh',
    'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh',
    'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu and Kashmir', 'Ladakh'
];

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">Students Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard-v2.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Students</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary btn-rounded" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-plus me-2"></i>Add Student
        </button>
    </div>
</div>

<!-- Content Body -->
<div class="content-body">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-primary">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h4><?= $totalStudents ?></h4>
                <p class="text-muted mb-0">Total Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4><?= count(array_filter($students, fn($s) => $s['status'] === 'active')) ?></h4>
                <p class="text-muted mb-0">Active Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <h4><?= count(array_filter($students, fn($s) => $s['status'] === 'enrolled')) ?></h4>
                <p class="text-muted mb-0">New Enrollments</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-info">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h4><?= count(array_filter($students, fn($s) => $s['status'] === 'completed')) ?></h4>
                <p class="text-muted mb-0">Completed</p>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Students</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Name, Enrollment No, Phone...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Training Center</label>
                    <select class="form-select" name="center">
                        <option value="">All Centers</option>
                        <?php foreach ($trainingCenters as $center): ?>
                            <option value="<?= $center['id'] ?>" <?= $center_filter == $center['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($center['center_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="enrolled" <?= $status_filter === 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="dropped" <?= $status_filter === 'dropped' ? 'selected' : '' ?>>Dropped</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-users me-2"></i>Students List</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Student Details</th>
                            <th>Contact</th>
                            <th>Training Center</th>
                            <th>Admission Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No students found. Add your first student to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <div class="bg-gradient-primary rounded-circle d-flex align-items-center justify-content-center text-white" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($student['enrollment_number']) ?></small>
                                            <br>
                                            <small class="text-muted">F/O: <?= htmlspecialchars($student['father_name']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($student['phone']) ?>
                                            <?php if ($student['email']): ?>
                                                <br><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($student['email']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($student['center_name'] ?? 'Not Assigned') ?></td>
                                    <td><?= $student['admission_date'] ? date('d M Y', strtotime($student['admission_date'])) : '-' ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'enrolled' => 'info',
                                            'active' => 'success',
                                            'completed' => 'primary',
                                            'dropped' => 'danger',
                                            'suspended' => 'warning'
                                        ];
                                        $statusColor = $statusColors[$student['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <?= ucfirst($student['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editStudent(<?= htmlspecialchars(json_encode($student)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteStudent(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&center=<?= urlencode($center_filter) ?>&status=<?= urlencode($status_filter) ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&center=<?= urlencode($center_filter) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&center=<?= urlencode($center_filter) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Showing <?= min($totalStudents, $offset + 1) ?> to <?= min($totalStudents, $offset + $limit) ?> of <?= $totalStudents ?> students
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Father's Name</label>
                                <input type="text" class="form-control" name="father_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date of Birth (DD-MM-YYYY)</label>
                                <input type="text" class="form-control" name="date_of_birth" 
                                       pattern="^([0-2][0-9]|(3)[0-1])(\/|-)(((0)[0-9])|((1)[0-2]))(\/|-)(\d{4})$"
                                       placeholder="DD-MM-YYYY">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone" pattern="[0-9]{10}" 
                                       placeholder="10-digit number" maxlength="10" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Aadhar Number</label>
                                <input type="text" class="form-control" name="aadhar_number" pattern="[0-9]{12}" 
                                       placeholder="12-digit Aadhar number" maxlength="12">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Qualification</label>
                                <select class="form-select" name="qualification">
                                    <option value="">Select Qualification</option>
                                    <option value="10th">10th Pass</option>
                                    <option value="12th">12th Pass</option>
                                    <option value="iti">ITI</option>
                                    <option value="diploma">Diploma</option>
                                    <option value="graduate">Graduate</option>
                                    <option value="postgraduate">Post Graduate</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <option value="">Select Category</option>
                                    <option value="general">General</option>
                                    <option value="obc">OBC</option>
                                    <option value="sc">SC</option>
                                    <option value="st">ST</option>
                                    <option value="ews">EWS</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Training Center *</label>
                                <select class="form-select" name="training_center_id" required>
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $center): ?>
                                        <option value="<?= $center['id'] ?>"><?= htmlspecialchars($center['center_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <h6 class="mt-3 mb-3">Address Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address_line1" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <select class="form-select" name="state">
                                    <option value="">Select State</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?= $state ?>"><?= $state ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">PIN Code</label>
                                <input type="text" class="form-control" name="pincode" 
                                       pattern="[0-9]{6}" placeholder="6-digit PIN" maxlength="6">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="editStudentForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Same form fields as add modal with id attributes for editing -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Father's Name</label>
                                <input type="text" class="form-control" name="father_name" id="edit_father_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date of Birth (DD-MM-YYYY)</label>
                                <input type="text" class="form-control" name="date_of_birth" id="edit_date_of_birth"
                                       pattern="^([0-2][0-9]|(3)[0-1])(\/|-)(((0)[0-9])|((1)[0-2]))(\/|-)(\d{4})$"
                                       placeholder="DD-MM-YYYY">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" id="edit_gender">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone" pattern="[0-9]{10}" 
                                       placeholder="10-digit number" maxlength="10" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Training Center *</label>
                                <select class="form-select" name="training_center_id" id="edit_training_center_id" required>
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $center): ?>
                                        <option value="<?= $center['id'] ?>"><?= htmlspecialchars($center['center_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" id="edit_category">
                                    <option value="">Select Category</option>
                                    <option value="general">General</option>
                                    <option value="obc">OBC</option>
                                    <option value="sc">SC</option>
                                    <option value="st">ST</option>
                                    <option value="ews">EWS</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="enrolled">Enrolled</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="dropped">Dropped</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editStudent(student) {
    document.getElementById('edit_id').value = student.id;
    // Combine first_name and last_name for full_name
    const fullName = (student.first_name || '') + (student.last_name ? ' ' + student.last_name : '');
    document.getElementById('edit_full_name').value = fullName;
    document.getElementById('edit_father_name').value = student.father_name || '';
    
    // Convert database date (YYYY-MM-DD) to DD-MM-YYYY format for display
    if (student.date_of_birth) {
        const dateParts = student.date_of_birth.split('-');
        if (dateParts.length === 3) {
            const formattedDate = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
            document.getElementById('edit_date_of_birth').value = formattedDate;
        }
    } else {
        document.getElementById('edit_date_of_birth').value = '';
    }
    
    document.getElementById('edit_gender').value = student.gender || '';
    document.getElementById('edit_phone').value = student.phone;
    document.getElementById('edit_email').value = student.email || '';
    document.getElementById('edit_training_center_id').value = student.training_center_id;
    document.getElementById('edit_category').value = student.category || '';
    document.getElementById('edit_status').value = student.status;
    
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

function deleteStudent(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Phone validation for students
document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Students Management', 'students', $content);
?>

<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user data
$user = getCurrentUser();
$userRole = getCurrentUserRole();
$userName = getCurrentUserName();

// Check if user has permission to access students
if (!in_array($userRole, ['admin', 'training_partner'])) {
    header('Location: unauthorized.php');
    exit();
}

// Initialize database connection
$db = getConnection();
if (!$db) {
    die('Database connection failed');
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Add new student
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $course_id = $_POST['course_id'] ?? '';
        $batch_id = $_POST['batch_id'] ?? '';
        $training_center_id = $_POST['training_center_id'] ?? '';
        
        if ($name && $email && $phone && $course_id) {
            try {
                // Set training center based on user role
                if ($userRole === 'training_partner') {
                    $training_center_id = $user['id'];
                }
                
                $stmt = $db->prepare("
                    INSERT INTO students (name, email, phone, address, course_id, batch_id, training_center_id, password, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                // Default password for new students
                $defaultPassword = password_hash('student123', PASSWORD_DEFAULT);
                
                $stmt->execute([
                    $name, $email, $phone, $address, 
                    $course_id, $batch_id, $training_center_id, $defaultPassword
                ]);
                
                $message = 'Student added successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding student: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Please fill all required fields.';
            $messageType = 'warning';
        }
    }
    
    if ($action === 'edit') {
        // Edit student
        $student_id = $_POST['student_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $course_id = $_POST['course_id'] ?? '';
        $batch_id = $_POST['batch_id'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if ($student_id && $name && $email && $phone) {
            try {
                $stmt = $db->prepare("
                    UPDATE students 
                    SET name = ?, email = ?, phone = ?, address = ?, course_id = ?, batch_id = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $phone, $address, $course_id, $batch_id, $status, $student_id]);
                
                $message = 'Student updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating student: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
    
    if ($action === 'delete') {
        // Delete student
        $student_id = $_POST['student_id'] ?? '';
        
        if ($student_id) {
            try {
                $stmt = $db->prepare("UPDATE students SET status = 'deleted' WHERE id = ?");
                $stmt->execute([$student_id]);
                
                $message = 'Student deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting student: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Fetch students based on user role
$studentsQuery = "
    SELECT s.*, c.name as course_name, b.batch_name, tc.center_name 
    FROM students s 
    LEFT JOIN courses c ON s.course_id = c.id 
    LEFT JOIN batches b ON s.batch_id = b.id 
    LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
    WHERE s.status != 'deleted'
";

if ($userRole === 'training_partner') {
    $studentsQuery .= " AND s.training_center_id = ?";
    $stmt = $db->prepare($studentsQuery . " ORDER BY s.created_at DESC");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->prepare($studentsQuery . " ORDER BY s.created_at DESC");
    $stmt->execute();
}

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses for dropdown
$stmt = $db->prepare("SELECT * FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch batches for dropdown
$batchesQuery = "SELECT b.*, c.name as course_name FROM batches b JOIN courses c ON b.course_id = c.id WHERE b.status = 'active'";
if ($userRole === 'training_partner') {
    $batchesQuery .= " AND b.training_center_id = ?";
    $stmt = $db->prepare($batchesQuery . " ORDER BY b.batch_name");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->prepare($batchesQuery . " ORDER BY b.batch_name");
    $stmt->execute();
}
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch training centers for admin
$trainingCenters = [];
if ($userRole === 'admin') {
    $stmt = $db->prepare("SELECT * FROM training_centers WHERE status = 'active' ORDER BY center_name");
    $stmt->execute();
    $trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        .main-content {
            padding: 2rem;
        }
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            margin: 0.1rem;
            border-radius: 5px;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="p-3">
                        <h4 class="text-white mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>SMS
                        </h4>
                        <small class="text-light opacity-75">Student Management</small>
                    </div>
                    <hr class="text-light">
                    <nav class="nav flex-column px-3">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        
                        <?php if ($userRole === 'admin'): ?>
                        <a class="nav-link" href="training-centers.php">
                            <i class="fas fa-building me-2"></i>Training Centers
                        </a>
                        <a class="nav-link" href="masters.php">
                            <i class="fas fa-cogs me-2"></i>Masters
                        </a>
                        <?php endif; ?>
                        
                        <a class="nav-link active" href="students.php">
                            <i class="fas fa-users me-2"></i>Students
                        </a>
                        <a class="nav-link" href="batches.php">
                            <i class="fas fa-layer-group me-2"></i>Batches
                        </a>
                        <a class="nav-link" href="assessments.php">
                            <i class="fas fa-clipboard-check me-2"></i>Assessments
                        </a>
                        <a class="nav-link" href="fees.php">
                            <i class="fas fa-money-bill me-2"></i>Fees
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        
                        <hr class="text-light">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h2 class="mb-1">
                                    <i class="fas fa-users me-2"></i>Students Management
                                </h2>
                                <p class="text-muted mb-0">Manage student enrollments and information</p>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                    <i class="fas fa-plus me-1"></i>Add Student
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Messages -->
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Students Table -->
                    <div class="content-card">
                        <div class="table-responsive">
                            <table class="table table-hover" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Course</th>
                                        <th>Batch</th>
                                        <?php if ($userRole === 'admin'): ?>
                                        <th>Training Center</th>
                                        <?php endif; ?>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $student['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 14px;">
                                                    <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                </div>
                                                <?php echo htmlspecialchars($student['name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($student['course_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($student['batch_name'] ?? 'Not Assigned'); ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                        <td><?php echo htmlspecialchars($student['center_name'] ?? 'Not Assigned'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : ($student['status'] === 'inactive' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-action" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="add_name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="add_email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_phone" class="form-label">Phone *</label>
                                <input type="text" class="form-control" id="add_phone" name="phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_course" class="form-label">Course *</label>
                                <select class="form-select" id="add_course" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_batch" class="form-label">Batch</label>
                                <select class="form-select" id="add_batch" name="batch_id">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['batch_name'] . ' - ' . $batch['course_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($userRole === 'admin'): ?>
                            <div class="col-md-6 mb-3">
                                <label for="add_training_center" class="form-label">Training Center</label>
                                <select class="form-select" id="add_training_center" name="training_center_id">
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $tc): ?>
                                    <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['center_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-12 mb-3">
                                <label for="add_address" class="form-label">Address</label>
                                <textarea class="form-control" id="add_address" name="address" rows="3"></textarea>
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
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_phone" class="form-label">Phone *</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_course" class="form-label">Course *</label>
                                <select class="form-select" id="edit_course" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_batch" class="form-label">Batch</label>
                                <select class="form-select" id="edit_batch" name="batch_id">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['batch_name'] . ' - ' . $batch['course_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="graduated">Graduated</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_address" class="form-label">Address</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete student <strong id="delete_student_name"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="student_id" id="delete_student_id">
                        <button type="submit" class="btn btn-danger">Delete Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#studentsTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        function editStudent(student) {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_name').value = student.name;
            document.getElementById('edit_email').value = student.email;
            document.getElementById('edit_phone').value = student.phone;
            document.getElementById('edit_address').value = student.address || '';
            document.getElementById('edit_course').value = student.course_id || '';
            document.getElementById('edit_batch').value = student.batch_id || '';
            document.getElementById('edit_status').value = student.status;
            
            new bootstrap.Modal(document.getElementById('editStudentModal')).show();
        }

        function deleteStudent(id, name) {
            document.getElementById('delete_student_id').value = id;
            document.getElementById('delete_student_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>

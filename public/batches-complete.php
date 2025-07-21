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

// Check if user has permission to access batches
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
        // Add new batch
        $batch_name = trim($_POST['batch_name'] ?? '');
        $course_id = $_POST['course_id'] ?? '';
        $training_center_id = $_POST['training_center_id'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $max_students = $_POST['max_students'] ?? 30;
        
        if ($batch_name && $course_id && $start_date && $end_date) {
            try {
                // Set training center based on user role
                if ($userRole === 'training_partner') {
                    $training_center_id = $user['id'];
                }
                
                $stmt = $db->prepare("
                    INSERT INTO batches (batch_name, course_id, training_center_id, start_date, end_date, max_students, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'upcoming')
                ");
                
                $stmt->execute([
                    $batch_name, $course_id, $training_center_id, 
                    $start_date, $end_date, $max_students
                ]);
                
                $message = 'Batch added successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding batch: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Please fill all required fields.';
            $messageType = 'warning';
        }
    }
    
    if ($action === 'edit') {
        // Edit batch
        $batch_id = $_POST['batch_id'] ?? '';
        $batch_name = trim($_POST['batch_name'] ?? '');
        $course_id = $_POST['course_id'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $max_students = $_POST['max_students'] ?? 30;
        $status = $_POST['status'] ?? 'upcoming';
        
        if ($batch_id && $batch_name && $course_id && $start_date && $end_date) {
            try {
                $stmt = $db->prepare("
                    UPDATE batches 
                    SET batch_name = ?, course_id = ?, start_date = ?, end_date = ?, max_students = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$batch_name, $course_id, $start_date, $end_date, $max_students, $status, $batch_id]);
                
                $message = 'Batch updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating batch: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
    
    if ($action === 'delete') {
        // Delete batch
        $batch_id = $_POST['batch_id'] ?? '';
        
        if ($batch_id) {
            try {
                $stmt = $db->prepare("UPDATE batches SET status = 'deleted' WHERE id = ?");
                $stmt->execute([$batch_id]);
                
                $message = 'Batch deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting batch: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Fetch batches based on user role
$batchesQuery = "
    SELECT b.*, c.name as course_name, tc.center_name,
           (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.id AND s.status = 'active') as enrolled_students
    FROM batches b 
    LEFT JOIN courses c ON b.course_id = c.id 
    LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
    WHERE b.status != 'deleted'
";

if ($userRole === 'training_partner') {
    $batchesQuery .= " AND b.training_center_id = ?";
    $stmt = $db->prepare($batchesQuery . " ORDER BY b.created_at DESC");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->prepare($batchesQuery . " ORDER BY b.created_at DESC");
    $stmt->execute();
}

$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses for dropdown
$stmt = $db->prepare("SELECT * FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Batches Management - Student Management System</title>
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
                        
                        <a class="nav-link" href="students.php">
                            <i class="fas fa-users me-2"></i>Students
                        </a>
                        <a class="nav-link active" href="batches.php">
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
                                    <i class="fas fa-layer-group me-2"></i>Batches Management
                                </h2>
                                <p class="text-muted mb-0">Manage training batches and schedules</p>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                                    <i class="fas fa-plus me-1"></i>Add Batch
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

                    <!-- Batches Table -->
                    <div class="content-card">
                        <div class="table-responsive">
                            <table class="table table-hover" id="batchesTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Batch Name</th>
                                        <th>Course</th>
                                        <?php if ($userRole === 'admin'): ?>
                                        <th>Training Center</th>
                                        <?php endif; ?>
                                        <th>Duration</th>
                                        <th>Students</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch): ?>
                                    <tr>
                                        <td><?php echo $batch['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 14px;">
                                                    <?php echo strtoupper(substr($batch['batch_name'], 0, 1)); ?>
                                                </div>
                                                <strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($batch['course_name'] ?? 'Not Assigned'); ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                        <td><?php echo htmlspecialchars($batch['center_name'] ?? 'Not Assigned'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <small>
                                                <?php echo date('d M Y', strtotime($batch['start_date'])); ?><br>
                                                to <?php echo date('d M Y', strtotime($batch['end_date'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $batch['enrolled_students']; ?>/<?php echo $batch['max_students']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusColors = [
                                                'upcoming' => 'warning',
                                                'ongoing' => 'success',
                                                'completed' => 'primary',
                                                'cancelled' => 'danger'
                                            ];
                                            $statusColor = $statusColors[$batch['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $statusColor; ?>">
                                                <?php echo ucfirst($batch['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-action" onclick="editBatch(<?php echo htmlspecialchars(json_encode($batch)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteBatch(<?php echo $batch['id']; ?>, '<?php echo htmlspecialchars($batch['batch_name']); ?>')">
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

    <!-- Add Batch Modal -->
    <div class="modal fade" id="addBatchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Batch
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_batch_name" class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" id="add_batch_name" name="batch_name" required>
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
                            <?php if ($userRole === 'admin'): ?>
                            <div class="col-md-12 mb-3">
                                <label for="add_training_center" class="form-label">Training Center</label>
                                <select class="form-select" id="add_training_center" name="training_center_id">
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $tc): ?>
                                    <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['center_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-6 mb-3">
                                <label for="add_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="add_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="add_end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_max_students" class="form-label">Maximum Students</label>
                                <input type="number" class="form-control" id="add_max_students" name="max_students" value="30" min="1" max="100">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Batch Modal -->
    <div class="modal fade" id="editBatchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Batch
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="batch_id" id="edit_batch_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_batch_name" class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" id="edit_batch_name" name="batch_name" required>
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
                                <label for="edit_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_max_students" class="form-label">Maximum Students</label>
                                <input type="number" class="form-control" id="edit_max_students" name="max_students" min="1" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="upcoming">Upcoming</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Batch</button>
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
                    <p>Are you sure you want to delete batch <strong id="delete_batch_name"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="batch_id" id="delete_batch_id">
                        <button type="submit" class="btn btn-danger">Delete Batch</button>
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
            $('#batchesTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        function editBatch(batch) {
            document.getElementById('edit_batch_id').value = batch.id;
            document.getElementById('edit_batch_name').value = batch.batch_name;
            document.getElementById('edit_course').value = batch.course_id || '';
            document.getElementById('edit_start_date').value = batch.start_date;
            document.getElementById('edit_end_date').value = batch.end_date;
            document.getElementById('edit_max_students').value = batch.max_students;
            document.getElementById('edit_status').value = batch.status;
            
            new bootstrap.Modal(document.getElementById('editBatchModal')).show();
        }

        function deleteBatch(id, name) {
            document.getElementById('delete_batch_id').value = id;
            document.getElementById('delete_batch_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>

<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include required files
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get user data
$user = getCurrentUser();
$userName = getCurrentUserName();

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
        // Add new training center
        $center_name = trim($_POST['center_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        
        if ($center_name && $contact_person && $email && $phone) {
            try {
                $password = password_hash('demo123', PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO training_centers 
                    (center_name, contact_person, email, phone, address, city, state, pincode, password, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                $stmt->execute([
                    $center_name, $contact_person, $email, $phone, 
                    $address, $city, $state, $pincode, $password
                ]);
                
                $message = 'Training center added successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding training center: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Please fill all required fields.';
            $messageType = 'warning';
        }
    }
    
    if ($action === 'edit') {
        // Edit training center
        $tc_id = $_POST['tc_id'] ?? '';
        $center_name = trim($_POST['center_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if ($tc_id && $center_name && $contact_person && $email && $phone) {
            try {
                $stmt = $db->prepare("
                    UPDATE training_centers 
                    SET center_name = ?, contact_person = ?, email = ?, phone = ?, 
                        address = ?, city = ?, state = ?, pincode = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $center_name, $contact_person, $email, $phone, 
                    $address, $city, $state, $pincode, $status, $tc_id
                ]);
                
                $message = 'Training center updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating training center: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
    
    if ($action === 'delete') {
        // Delete training center
        $tc_id = $_POST['tc_id'] ?? '';
        
        if ($tc_id) {
            try {
                $stmt = $db->prepare("UPDATE training_centers SET status = 'deleted' WHERE id = ?");
                $stmt->execute([$tc_id]);
                
                $message = 'Training center deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting training center: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Fetch training centers
$stmt = $db->prepare("SELECT * FROM training_centers WHERE status != 'deleted' ORDER BY created_at DESC");
$stmt->execute();
$trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Centers - Student Management System</title>
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
                        <a class="nav-link active" href="training-centers.php">
                            <i class="fas fa-building me-2"></i>Training Centers
                        </a>
                        <a class="nav-link" href="masters.php">
                            <i class="fas fa-cogs me-2"></i>Masters
                        </a>
                        <a class="nav-link" href="students.php">
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
                                    <i class="fas fa-building me-2"></i>Training Centers
                                </h2>
                                <p class="text-muted mb-0">Manage training centers and partners</p>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                                    <i class="fas fa-plus me-1"></i>Add Training Center
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

                    <!-- Training Centers Table -->
                    <div class="content-card">
                        <div class="table-responsive">
                            <table class="table table-hover" id="centersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Center Name</th>
                                        <th>Contact Person</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trainingCenters as $center): ?>
                                    <tr>
                                        <td><?php echo $center['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 14px;">
                                                    <?php echo strtoupper(substr($center['center_name'], 0, 1)); ?>
                                                </div>
                                                <strong><?php echo htmlspecialchars($center['center_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($center['contact_person']); ?></td>
                                        <td><?php echo htmlspecialchars($center['email']); ?></td>
                                        <td><?php echo htmlspecialchars($center['phone']); ?></td>
                                        <td>
                                            <?php 
                                            $location = [];
                                            if ($center['city']) $location[] = $center['city'];
                                            if ($center['state']) $location[] = $center['state'];
                                            echo htmlspecialchars(implode(', ', $location) ?: 'Not specified');
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $center['status'] === 'active' ? 'success' : ($center['status'] === 'inactive' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($center['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-action" onclick="editCenter(<?php echo htmlspecialchars(json_encode($center)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteCenter(<?php echo $center['id']; ?>, '<?php echo htmlspecialchars($center['center_name']); ?>')">
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

    <!-- Add Center Modal -->
    <div class="modal fade" id="addCenterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-building me-2"></i>Add New Training Center
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_center_name" class="form-label">Center Name *</label>
                                <input type="text" class="form-control" id="add_center_name" name="center_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_contact_person" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="add_contact_person" name="contact_person" required>
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
                                <label for="add_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="add_city" name="city">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_state" class="form-label">State</label>
                                <input type="text" class="form-control" id="add_state" name="state">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_pincode" class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="add_pincode" name="pincode">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="add_address" class="form-label">Address</label>
                                <textarea class="form-control" id="add_address" name="address" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Default login password will be "demo123"
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Training Center</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Center Modal -->
    <div class="modal fade" id="editCenterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Training Center
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tc_id" id="edit_tc_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_center_name" class="form-label">Center Name *</label>
                                <input type="text" class="form-control" id="edit_center_name" name="center_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_contact_person" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person" required>
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
                                <label for="edit_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="edit_city" name="city">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_state" class="form-label">State</label>
                                <input type="text" class="form-control" id="edit_state" name="state">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_pincode" class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="edit_pincode" name="pincode">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
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
                        <button type="submit" class="btn btn-primary">Update Training Center</button>
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
                    <p>Are you sure you want to delete training center <strong id="delete_center_name"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="tc_id" id="delete_tc_id">
                        <button type="submit" class="btn btn-danger">Delete Training Center</button>
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
            $('#centersTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        function editCenter(center) {
            document.getElementById('edit_tc_id').value = center.id;
            document.getElementById('edit_center_name').value = center.center_name;
            document.getElementById('edit_contact_person').value = center.contact_person;
            document.getElementById('edit_email').value = center.email;
            document.getElementById('edit_phone').value = center.phone;
            document.getElementById('edit_address').value = center.address || '';
            document.getElementById('edit_city').value = center.city || '';
            document.getElementById('edit_state').value = center.state || '';
            document.getElementById('edit_pincode').value = center.pincode || '';
            document.getElementById('edit_status').value = center.status;
            
            new bootstrap.Modal(document.getElementById('editCenterModal')).show();
        }

        function deleteCenter(id, name) {
            document.getElementById('delete_tc_id').value = id;
            document.getElementById('delete_center_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>

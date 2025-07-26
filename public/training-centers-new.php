<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user = $_SESSION['user'];
$userRole = $user['role'];

// Check if user has permission
if ($userRole !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create':
                // Generate TC ID
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM training_centers");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                $tcId = 'TC' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
                
                // Validate required fields
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($name) || empty($email) || empty($phone) || empty($password)) {
                    throw new Exception('All fields are required');
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                if (strlen($phone) !== 10 || !ctype_digit($phone)) {
                    throw new Exception('Phone number must be exactly 10 digits');
                }
                
                if (strlen($password) < 6) {
                    throw new Exception('Password must be at least 6 characters');
                }
                
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists');
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert training center
                $stmt = $db->prepare("
                    INSERT INTO training_centers (tc_id, name, email, phone, address, password, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$tcId, $name, $email, $phone, $address, $hashedPassword]);
                
                $trainingCenterId = $db->lastInsertId();
                
                // Create user account for training center
                $username = 'tc' . str_pad($trainingCenterId, 3, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password, role, name, phone, training_center_id) 
                    VALUES (?, ?, ?, 'training_partner', ?, ?, ?)
                ");
                $stmt->execute([$username, $email, $hashedPassword, $name, $phone, $trainingCenterId]);
                
                echo json_encode(['success' => true, 'message' => 'Training center created successfully', 'tc_id' => $tcId]);
                break;
                
            case 'update':
                $id = $_POST['id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $status = $_POST['status'] ?? 'active';
                
                if (empty($name) || empty($email) || empty($phone)) {
                    throw new Exception('Name, email, and phone are required');
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                if (strlen($phone) !== 10 || !ctype_digit($phone)) {
                    throw new Exception('Phone number must be exactly 10 digits');
                }
                
                // Check if email already exists for other training centers
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists');
                }
                
                // Update training center
                $stmt = $db->prepare("
                    UPDATE training_centers 
                    SET name = ?, email = ?, phone = ?, address = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $phone, $address, $status, $id]);
                
                // Update user account
                $stmt = $db->prepare("
                    UPDATE users 
                    SET email = ?, name = ?, phone = ? 
                    WHERE training_center_id = ? AND role = 'training_partner'
                ");
                $stmt->execute([$email, $name, $phone, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Training center updated successfully']);
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                
                // Check if training center has students or batches
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE training_center_id = ?");
                $stmt->execute([$id]);
                $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM batches WHERE training_center_id = ?");
                $stmt->execute([$id]);
                $batchCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($studentCount > 0 || $batchCount > 0) {
                    throw new Exception('Cannot delete training center with existing students or batches');
                }
                
                // Delete user account first
                $stmt = $db->prepare("DELETE FROM users WHERE training_center_id = ? AND role = 'training_partner'");
                $stmt->execute([$id]);
                
                // Delete training center
                $stmt = $db->prepare("DELETE FROM training_centers WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Training center deleted successfully']);
                break;
                
            case 'reset_password':
                $id = $_POST['id'] ?? 0;
                $newPassword = $_POST['new_password'] ?? '';
                
                if (strlen($newPassword) < 6) {
                    throw new Exception('Password must be at least 6 characters');
                }
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update training center password
                $stmt = $db->prepare("UPDATE training_centers SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $id]);
                
                // Update user password
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE training_center_id = ? AND role = 'training_partner'");
                $stmt->execute([$hashedPassword, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Fetch training centers
$stmt = $db->prepare("
    SELECT tc.*, 
           (SELECT COUNT(*) FROM students WHERE training_center_id = tc.id) as student_count,
           (SELECT COUNT(*) FROM batches WHERE training_center_id = tc.id) as batch_count
    FROM training_centers tc
    ORDER BY tc.created_at DESC
");
$stmt->execute();
$trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/layout.php';
renderHeader('Training Centers');
?>

<div class="container-fluid">
    <div class="row">
        <?php renderSidebar($userRole); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-building me-2"></i>Training Centers
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTrainingCenterModal">
                        <i class="fas fa-plus me-1"></i>Add Training Center
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Training Centers
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($trainingCenters); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-building fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Centers
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count(array_filter($trainingCenters, function($tc) { return $tc['status'] === 'active'; })); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Total Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo array_sum(array_column($trainingCenters, 'student_count')); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Total Batches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo array_sum(array_column($trainingCenters, 'batch_count')); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-layer-group fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Training Centers Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Training Centers List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="trainingCentersTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>TC ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Students</th>
                                    <th>Batches</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trainingCenters as $tc): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($tc['tc_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($tc['name']); ?></td>
                                    <td><?php echo htmlspecialchars($tc['email']); ?></td>
                                    <td><?php echo htmlspecialchars($tc['phone']); ?></td>
                                    <td><span class="badge bg-info"><?php echo $tc['student_count']; ?></span></td>
                                    <td><span class="badge bg-secondary"><?php echo $tc['batch_count']; ?></span></td>
                                    <td>
                                        <?php if ($tc['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($tc['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editTrainingCenter(<?php echo $tc['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $tc['id']; ?>)">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteTrainingCenter(<?php echo $tc['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Training Center Modal -->
<div class="modal fade" id="addTrainingCenterModal" tabindex="-1" aria-labelledby="addTrainingCenterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTrainingCenterModalLabel">Add Training Center</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addTrainingCenterForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Training Center Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" pattern="[0-9]{10}" maxlength="10" required>
                                <div class="form-text">Enter 10-digit mobile number</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Training Center</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Training Center Modal -->
<div class="modal fade" id="editTrainingCenterModal" tabindex="-1" aria-labelledby="editTrainingCenterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTrainingCenterModalLabel">Edit Training Center</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editTrainingCenterForm">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Training Center Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_phone" class="form-label">Phone *</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" pattern="[0-9]{10}" maxlength="10" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="resetPasswordForm">
                <input type="hidden" id="reset_id" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#trainingCentersTable').DataTable({
        "pageLength": 25,
        "order": [[ 7, "desc" ]], // Sort by created date
        "columnDefs": [
            { "orderable": false, "targets": 8 } // Disable sorting on Actions column
        ]
    });
    
    // Add Training Center Form
    $('#addTrainingCenterForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'create');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message + '\nTC ID: ' + response.tc_id);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while creating the training center.');
            }
        });
    });
    
    // Edit Training Center Form
    $('#editTrainingCenterForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'update');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while updating the training center.');
            }
        });
    });
    
    // Reset Password Form
    $('#resetPasswordForm').on('submit', function(e) {
        e.preventDefault();
        
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== confirmPassword) {
            alert('Passwords do not match');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'reset_password');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $('#resetPasswordModal').modal('hide');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while resetting the password.');
            }
        });
    });
    
    // Phone number validation
    $('#phone, #edit_phone').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

function editTrainingCenter(id) {
    // Find the training center data
    const trainingCenters = <?php echo json_encode($trainingCenters); ?>;
    const tc = trainingCenters.find(t => t.id == id);
    
    if (tc) {
        $('#edit_id').val(tc.id);
        $('#edit_name').val(tc.name);
        $('#edit_email').val(tc.email);
        $('#edit_phone').val(tc.phone);
        $('#edit_address').val(tc.address);
        $('#edit_status').val(tc.status);
        $('#editTrainingCenterModal').modal('show');
    }
}

function resetPassword(id) {
    $('#reset_id').val(id);
    $('#resetPasswordModal').modal('show');
}

function deleteTrainingCenter(id) {
    if (confirm('Are you sure you want to delete this training center? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while deleting the training center.');
            }
        });
    }
}
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.text-xs {
    font-size: 0.7rem;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.table th {
    border-top: none;
}
</style>

</body>
</html>

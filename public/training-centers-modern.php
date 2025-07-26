<?php
/**
 * Modern Training Centers Management System
 * Features: Full CRUD, auto-generated TC ID, password management, comprehensive interface
 */
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login-new.php');
    exit();
}

// Get user data
$user = getCurrentUser();
$userRole = getCurrentUserRole();

// Check permissions (only admin can manage training centers)
if ($userRole !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();
if (!$db) {
    die('Database connection failed');
}

$message = '';
$messageType = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_center':
                // Validate required fields
                $name = trim($_POST['name'] ?? '');
                $contact_person = trim($_POST['contact_person'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $pincode = trim($_POST['pincode'] ?? '');

                // Validation
                if (empty($name) || empty($contact_person) || empty($email) || 
                    empty($phone) || empty($address) || empty($city) || 
                    empty($state) || empty($pincode)) {
                    throw new Exception('Please fill all required fields.');
                }

                // Email validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Please enter a valid email address.');
                }

                // Phone validation
                if (!preg_match('/^[0-9]{10}$/', $phone)) {
                    throw new Exception('Phone number must be exactly 10 digits.');
                }

                // Pincode validation
                if (!preg_match('/^[0-9]{6}$/', $pincode)) {
                    throw new Exception('Pincode must be exactly 6 digits.');
                }

                // Check for duplicate email
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception('Training center with this email already exists.');
                }

                // Generate TC ID
                $year = date('Y');
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM training_centers WHERE YEAR(created_at) = ?");
                $stmt->execute([$year]);
                $count = $stmt->fetchColumn() + 1;
                $tc_id = 'TC' . $year . str_pad($count, 3, '0', STR_PAD_LEFT);

                // Check if TC ID exists
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE tc_id = ?");
                $stmt->execute([$tc_id]);
                if ($stmt->fetch()) {
                    $tc_id = 'TC' . $year . str_pad($count + rand(1, 100), 3, '0', STR_PAD_LEFT);
                }

                // Generate default password
                $defaultPassword = 'tc' . strtolower(str_replace(' ', '', $name)) . '123';
                $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

                // Insert training center
                $stmt = $db->prepare("
                    INSERT INTO training_centers (
                        tc_id, name, contact_person, email, phone, address, 
                        city, state, pincode, password, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");

                $stmt->execute([
                    $tc_id, $name, $contact_person, $email, $phone, 
                    $address, $city, $state, $pincode, $hashedPassword
                ]);

                echo json_encode([
                    'success' => true, 
                    'message' => 'Training center added successfully!',
                    'tc_id' => $tc_id,
                    'password' => $defaultPassword
                ]);
                exit();

            case 'update_center':
                $center_id = $_POST['center_id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $contact_person = trim($_POST['contact_person'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $pincode = trim($_POST['pincode'] ?? '');
                $status = $_POST['status'] ?? 'active';

                // Validation
                if (!$center_id || empty($name) || empty($contact_person) || 
                    empty($email) || empty($phone) || empty($address) || 
                    empty($city) || empty($state) || empty($pincode)) {
                    throw new Exception('Please fill all required fields.');
                }

                // Email validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Please enter a valid email address.');
                }

                // Phone validation
                if (!preg_match('/^[0-9]{10}$/', $phone)) {
                    throw new Exception('Phone number must be exactly 10 digits.');
                }

                // Check for duplicate email (excluding current center)
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE email = ? AND id != ?");
                $stmt->execute([$email, $center_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Training center with this email already exists.');
                }

                // Update training center
                $stmt = $db->prepare("
                    UPDATE training_centers SET 
                        name = ?, contact_person = ?, email = ?, phone = ?, 
                        address = ?, city = ?, state = ?, pincode = ?, 
                        status = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name, $contact_person, $email, $phone, 
                    $address, $city, $state, $pincode, $status, $center_id
                ]);

                echo json_encode([
                    'success' => true, 
                    'message' => 'Training center updated successfully!'
                ]);
                exit();

            case 'delete_center':
                $center_id = $_POST['center_id'] ?? 0;
                
                if (!$center_id) {
                    throw new Exception('Invalid training center ID.');
                }

                // Check if center has students
                $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE training_center_id = ?");
                $stmt->execute([$center_id]);
                $studentCount = $stmt->fetchColumn();

                if ($studentCount > 0) {
                    throw new Exception('Cannot delete training center. It has ' . $studentCount . ' students enrolled.');
                }

                // Soft delete (update status to deleted)
                $stmt = $db->prepare("UPDATE training_centers SET status = 'deleted', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$center_id]);

                echo json_encode([
                    'success' => true, 
                    'message' => 'Training center deleted successfully!'
                ]);
                exit();

            case 'reset_password':
                $center_id = $_POST['center_id'] ?? 0;
                
                if (!$center_id) {
                    throw new Exception('Invalid training center ID.');
                }

                // Get center details
                $stmt = $db->prepare("SELECT name FROM training_centers WHERE id = ?");
                $stmt->execute([$center_id]);
                $center = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$center) {
                    throw new Exception('Training center not found.');
                }

                // Generate new password
                $newPassword = 'tc' . strtolower(str_replace(' ', '', $center['name'])) . rand(100, 999);
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update password
                $stmt = $db->prepare("UPDATE training_centers SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $center_id]);

                echo json_encode([
                    'success' => true, 
                    'message' => 'Password reset successfully!',
                    'new_password' => $newPassword
                ]);
                exit();

            case 'get_center_details':
                $center_id = $_POST['center_id'] ?? 0;
                
                if (!$center_id) {
                    throw new Exception('Invalid training center ID.');
                }

                $stmt = $db->prepare("
                    SELECT tc.*, 
                           (SELECT COUNT(*) FROM students s WHERE s.training_center_id = tc.id) as student_count,
                           (SELECT COUNT(*) FROM batches b WHERE b.training_center_id = tc.id) as batch_count,
                           (SELECT COUNT(*) FROM courses c WHERE c.training_center_id = tc.id) as course_count
                    FROM training_centers tc 
                    WHERE tc.id = ?
                ");
                $stmt->execute([$center_id]);
                $center = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$center) {
                    throw new Exception('Training center not found.');
                }

                echo json_encode([
                    'success' => true, 
                    'center' => $center
                ]);
                exit();

            case 'get_center_statistics':
                $center_id = $_POST['center_id'] ?? 0;
                
                $stmt = $db->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM students WHERE training_center_id = ?) as total_students,
                        (SELECT COUNT(*) FROM students WHERE training_center_id = ? AND status = 'active') as active_students,
                        (SELECT COUNT(*) FROM batches WHERE training_center_id = ?) as total_batches,
                        (SELECT COUNT(*) FROM batches WHERE training_center_id = ? AND status = 'ongoing') as ongoing_batches
                ");
                $stmt->execute([$center_id, $center_id, $center_id, $center_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true, 
                    'statistics' => $stats
                ]);
                exit();

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Fetch training centers
$centersQuery = "
    SELECT tc.*, 
           (SELECT COUNT(*) FROM students s WHERE s.training_center_id = tc.id AND s.status != 'deleted') as student_count,
           (SELECT COUNT(*) FROM batches b WHERE b.training_center_id = tc.id AND b.status != 'deleted') as batch_count
    FROM training_centers tc 
    WHERE tc.status != 'deleted'
    ORDER BY tc.created_at DESC
";

$stmt = $db->prepare($centersQuery);
$stmt->execute();
$centers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$totalCenters = count($centers);
$activeCenters = count(array_filter($centers, function($c) { return $c['status'] === 'active'; }));
$totalStudents = array_sum(array_column($centers, 'student_count'));
$totalBatches = array_sum(array_column($centers, 'batch_count'));

// Get states for dropdown
$states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 
    'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 
    'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 
    'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 
    'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal'
];

// Include the new layout
require_once '../includes/layout-new.php';
renderHeader('Training Centers Management - Student Management System');
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-building"></i>
                    Training Centers Management
                </h1>
                <p class="page-subtitle">Manage training center registrations, credentials, and information</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                    <i class="fas fa-plus me-2"></i>Add Training Center
                </button>
                <button type="button" class="btn btn-success" onclick="exportCenters()">
                    <i class="fas fa-download me-2"></i>Export Data
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo $totalCenters; ?></div>
                        <div class="stats-label">Total Centers</div>
                    </div>
                    <div class="stats-icon primary">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo $activeCenters; ?></div>
                        <div class="stats-label">Active Centers</div>
                    </div>
                    <div class="stats-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card warning">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo $totalStudents; ?></div>
                        <div class="stats-label">Total Students</div>
                    </div>
                    <div class="stats-icon warning">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card info">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo $totalBatches; ?></div>
                        <div class="stats-label">Total Batches</div>
                    </div>
                    <div class="stats-icon info">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Training Centers Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Training Centers List
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="centersTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>TC ID</th>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Students</th>
                            <th>Batches</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($centers as $index => $center): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($center['tc_id']); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="center-avatar me-2" style="width: 35px; height: 35px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?php echo strtoupper(substr($center['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($center['name']); ?></div>
                                        <small class="text-muted">Reg: <?php echo date('M Y', strtotime($center['created_at'])); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($center['contact_person']); ?></td>
                            <td><?php echo htmlspecialchars($center['email']); ?></td>
                            <td><?php echo htmlspecialchars($center['phone']); ?></td>
                            <td>
                                <div>
                                    <div><?php echo htmlspecialchars($center['city']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($center['state']); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $center['student_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $center['batch_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($center['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><?php echo ucfirst($center['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewCenter(<?php echo $center['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="editCenter(<?php echo $center['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $center['id']; ?>)">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCenter(<?php echo $center['id']; ?>)">
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
</div>

<!-- Add Training Center Modal -->
<div class="modal fade" id="addCenterModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary-gradient text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Training Center
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addCenterForm">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">Basic Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="name" class="form-label">Center Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="contact_person" class="form-label">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="phone" name="phone" maxlength="10" pattern="[0-9]{10}" required>
                            <div class="form-text">Enter exactly 10 digits</div>
                        </div>

                        <!-- Address Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Address Information</h6>
                        </div>
                        
                        <div class="col-12">
                            <label for="address" class="form-label">Street Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="city" name="city" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                            <select class="form-select" id="state" name="state" required>
                                <option value="">Select State</option>
                                <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state; ?>"><?php echo $state; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="pincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pincode" name="pincode" maxlength="6" pattern="[0-9]{6}" required>
                            <div class="form-text">Enter exactly 6 digits</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addCenter()">
                    <i class="fas fa-save me-2"></i>Add Training Center
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Training Center Modal -->
<div class="modal fade" id="editCenterModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success-gradient text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Training Center
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editCenterForm">
                    <input type="hidden" id="edit_center_id" name="center_id">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="text-success border-bottom pb-2">Basic Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Center Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_contact_person" class="form-label">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_contact_person" name="contact_person" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_phone" name="phone" maxlength="10" pattern="[0-9]{10}" required>
                        </div>

                        <!-- Address Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-success border-bottom pb-2">Address Information</h6>
                        </div>
                        
                        <div class="col-12">
                            <label for="edit_address" class="form-label">Street Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_address" name="address" rows="3" required></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_city" class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_city" name="city" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_state" class="form-label">State <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_state" name="state" required>
                                <option value="">Select State</option>
                                <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state; ?>"><?php echo $state; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_pincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_pincode" name="pincode" maxlength="6" pattern="[0-9]{6}" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="updateCenter()">
                    <i class="fas fa-save me-2"></i>Update Training Center
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Training Center Modal -->
<div class="modal fade" id="viewCenterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info-gradient text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Training Center Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewCenterContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Render Sidebar -->
<?php renderSidebar('training_centers'); ?>

<script>
// Document ready
$(document).ready(function() {
    // Initialize DataTable
    $('#centersTable').DataTable({
        order: [[0, 'asc']],
        columnDefs: [
            { targets: [10], orderable: false } // Actions column
        ]
    });

    // Phone number validation
    $('#phone, #edit_phone').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });

    // Pincode validation
    $('#pincode, #edit_pincode').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 6) {
            this.value = this.value.slice(0, 6);
        }
    });
});

// Add training center function
function addCenter() {
    const form = document.getElementById('addCenterForm');
    if (!validateForm(form)) {
        showToast('Please fill all required fields correctly.', 'danger');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'add_center');

    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message + ' TC ID: ' + response.tc_id + ', Password: ' + response.password, 'success');
                $('#addCenterModal').modal('hide');
                form.reset();
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast(response.message, 'danger');
            }
        },
        error: function() {
            showToast('An error occurred. Please try again.', 'danger');
        }
    });
}

// View training center function
function viewCenter(centerId) {
    $.post('', {
        action: 'get_center_details',
        center_id: centerId
    }, function(response) {
        if (response.success) {
            const center = response.center;
            let html = `
                <div class="row g-3">
                    <div class="col-12">
                        <div class="d-flex align-items-center mb-3">
                            <div class="center-avatar me-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">
                                ${center.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h5 class="mb-1">${center.name}</h5>
                                <p class="text-muted mb-0">TC ID: ${center.tc_id}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary">Contact Information</h6>
                        <ul class="list-unstyled">
                            <li><strong>Contact Person:</strong> ${center.contact_person}</li>
                            <li><strong>Email:</strong> ${center.email}</li>
                            <li><strong>Phone:</strong> ${center.phone}</li>
                        </ul>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary">Address</h6>
                        <address>
                            ${center.address}<br>
                            ${center.city}, ${center.state}<br>
                            PIN: ${center.pincode}
                        </address>
                    </div>
                    
                    <div class="col-12">
                        <h6 class="text-primary">Statistics</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <h4 class="text-primary mb-0">${center.student_count}</h4>
                                    <small>Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <h4 class="text-success mb-0">${center.batch_count}</h4>
                                    <small>Batches</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <h4 class="text-info mb-0">${center.course_count || 0}</h4>
                                    <small>Courses</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <span class="badge bg-${center.status === 'active' ? 'success' : 'warning'} p-2">
                                        ${center.status.charAt(0).toUpperCase() + center.status.slice(1)}
                                    </span>
                                    <div><small>Status</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <h6 class="text-primary">Registration Details</h6>
                        <ul class="list-unstyled">
                            <li><strong>Registered:</strong> ${new Date(center.created_at).toLocaleDateString()}</li>
                            <li><strong>Last Updated:</strong> ${center.updated_at ? new Date(center.updated_at).toLocaleDateString() : 'Never'}</li>
                        </ul>
                    </div>
                </div>
            `;
            
            $('#viewCenterContent').html(html);
            $('#viewCenterModal').modal('show');
        } else {
            showToast(response.message, 'danger');
        }
    }, 'json');
}

// Edit training center function
function editCenter(centerId) {
    $.post('', {
        action: 'get_center_details',
        center_id: centerId
    }, function(response) {
        if (response.success) {
            const center = response.center;
            
            $('#edit_center_id').val(center.id);
            $('#edit_name').val(center.name);
            $('#edit_contact_person').val(center.contact_person);
            $('#edit_email').val(center.email);
            $('#edit_phone').val(center.phone);
            $('#edit_address').val(center.address);
            $('#edit_city').val(center.city);
            $('#edit_state').val(center.state);
            $('#edit_pincode').val(center.pincode);
            $('#edit_status').val(center.status);
            
            $('#editCenterModal').modal('show');
        } else {
            showToast(response.message, 'danger');
        }
    }, 'json');
}

// Update training center function
function updateCenter() {
    const form = document.getElementById('editCenterForm');
    if (!validateForm(form)) {
        showToast('Please fill all required fields correctly.', 'danger');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'update_center');

    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                $('#editCenterModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.message, 'danger');
            }
        },
        error: function() {
            showToast('An error occurred. Please try again.', 'danger');
        }
    });
}

// Reset password function
function resetPassword(centerId) {
    if (confirm('Are you sure you want to reset the password for this training center?')) {
        $.post('', {
            action: 'reset_password',
            center_id: centerId
        }, function(response) {
            if (response.success) {
                showToast(response.message + ' New Password: ' + response.new_password, 'success');
            } else {
                showToast(response.message, 'danger');
            }
        }, 'json');
    }
}

// Delete training center function
function deleteCenter(centerId) {
    if (confirm('Are you sure you want to delete this training center? This action cannot be undone.')) {
        $.post('', {
            action: 'delete_center',
            center_id: centerId
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.message, 'danger');
            }
        }, 'json');
    }
}

// Export centers function
function exportCenters() {
    showToast('Export feature - Coming soon!', 'info');
}

console.log('Training centers management loaded successfully');
</script>

<?php renderFooter(); ?>

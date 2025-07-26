<?php
// Training Centers Management - v2.0
session_start();
require_once '../config/database-v2.php';

// For now, we'll simulate admin access. Later you can implement proper authentication
$currentUser = ['role' => 'admin', 'name' => 'Administrator'];

// Start output buffering to capture content
ob_start();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getV2Connection();
        
        if ($action === 'add') {
            // Add new training center
            $center_name = trim($_POST['center_name'] ?? '');
            $center_code = trim($_POST['center_code'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $spoc_name = trim($_POST['spoc_name'] ?? '');
            $spoc_phone = trim($_POST['spoc_phone'] ?? '');
            $spoc_email = trim($_POST['spoc_email'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 100);
            
            if ($center_name && $center_code && $email && $spoc_name) {
                $stmt = $conn->prepare("
                    INSERT INTO training_centers (
                        center_name, center_code, address, city, state, pincode,
                        phone, email, spoc_name, spoc_phone, spoc_email, capacity,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                $stmt->execute([
                    $center_name, $center_code, $address, $city, $state, $pincode,
                    $phone, $email, $spoc_name, $spoc_phone, $spoc_email, $capacity
                ]);
                
                $message = "Training center added successfully!";
                $messageType = "success";
            } else {
                $message = "Please fill all required fields.";
                $messageType = "error";
            }
        }
        
        if ($action === 'edit') {
            // Edit training center
            $id = intval($_POST['id'] ?? 0);
            $center_name = trim($_POST['center_name'] ?? '');
            $center_code = trim($_POST['center_code'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $spoc_name = trim($_POST['spoc_name'] ?? '');
            $spoc_phone = trim($_POST['spoc_phone'] ?? '');
            $spoc_email = trim($_POST['spoc_email'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 100);
            $status = $_POST['status'] ?? 'active';
            
            if ($id && $center_name && $center_code && $email && $spoc_name) {
                $stmt = $conn->prepare("
                    UPDATE training_centers SET 
                        center_name = ?, center_code = ?, address = ?, city = ?, state = ?, 
                        pincode = ?, phone = ?, email = ?, spoc_name = ?, spoc_phone = ?, 
                        spoc_email = ?, capacity = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $center_name, $center_code, $address, $city, $state, $pincode,
                    $phone, $email, $spoc_name, $spoc_phone, $spoc_email, $capacity, $status, $id
                ]);
                
                $message = "Training center updated successfully!";
                $messageType = "success";
            } else {
                $message = "Please fill all required fields.";
                $messageType = "error";
            }
        }
        
        if ($action === 'delete') {
            // Soft delete training center
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("UPDATE training_centers SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = "Training center deleted successfully!";
                $messageType = "success";
            }
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all training centers
try {
    $conn = getV2Connection();
    $stmt = $conn->query("
        SELECT * FROM training_centers 
        WHERE status != 'deleted' 
        ORDER BY center_name ASC
    ");
    $trainingCenters = $stmt->fetchAll();
} catch (Exception $e) {
    $trainingCenters = [];
    if (empty($message)) {
        $message = "Error loading training centers: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get states for dropdown
$states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat',
    'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh',
    'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh',
    'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu and Kashmir', 'Ladakh'
];
?>

<!-- Training Centers Management Content -->
<div class="container-fluid">
            margin: 0.25rem 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border: 1px solid rgba(0,0,0,0.125);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        .table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
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
                        <h4><i class="fas fa-graduation-cap"></i> SMIS v2.0</h4>
                        <p class="mb-0">Student Management Information System</p>
                    </div>
                    <nav class="nav flex-column px-3">
                        <a class="nav-link" href="dashboard-v2.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="training-centers.php">
                            <i class="fas fa-building"></i> Training Centers
                        </a>
                        <a class="nav-link" href="students-v2.php">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                        <a class="nav-link" href="batches-v2.php">
                            <i class="fas fa-users"></i> Batches
                        </a>
                        <a class="nav-link" href="courses-v2.php">
                            <i class="fas fa-book"></i> Courses
                        </a>
                        <a class="nav-link" href="fees-v2.php">
                            <i class="fas fa-money-bill"></i> Fees
                        </a>
                        <a class="nav-link" href="reports-v2.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        <hr>
                        <a class="nav-link" href="../public/setup-v2-schema-part1.php">
                            <i class="fas fa-database"></i> Setup v2.0 DB
                        </a>
                        <a class="nav-link" href="../public/check-v2-database.php">
                            <i class="fas fa-check-circle"></i> Check v2.0 DB
                        </a>
                        <hr>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="bg-white p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Training Centers Management</h5>
                                <small class="text-muted">Manage training center information and status</small>
                            </div>
                            <div>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                                    <i class="fas fa-plus"></i> Add Training Center
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-primary"><?= count($trainingCenters) ?></h3>
                                        <p class="mb-0">Total Centers</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-success"><?= count(array_filter($trainingCenters, fn($c) => $c['status'] === 'active')) ?></h3>
                                        <p class="mb-0">Active Centers</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-warning"><?= count(array_filter($trainingCenters, fn($c) => $c['status'] === 'pending_approval')) ?></h3>
                                        <p class="mb-0">Pending</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-info"><?= array_sum(array_column($trainingCenters, 'capacity')) ?></h3>
                                        <p class="mb-0">Total Capacity</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Training Centers Table -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-building"></i> Training Centers List</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Center Code</th>
                                                <th>Center Name</th>
                                                <th>Location</th>
                                                <th>SPOC</th>
                                                <th>Contact</th>
                                                <th>Capacity</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($trainingCenters)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">No training centers found. Add your first training center to get started.</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($trainingCenters as $center): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($center['center_code']) ?></strong></td>
                                                        <td><?= htmlspecialchars($center['center_name']) ?></td>
                                                        <td>
                                                            <?= htmlspecialchars($center['city']) ?>, <?= htmlspecialchars($center['state']) ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($center['pincode']) ?></small>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($center['spoc_name']) ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($center['spoc_email']) ?></small>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($center['phone']) ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($center['spoc_phone']) ?></small>
                                                        </td>
                                                        <td><span class="badge bg-info"><?= $center['capacity'] ?></span></td>
                                                        <td>
                                                            <?php
                                                            $statusColors = [
                                                                'active' => 'success',
                                                                'inactive' => 'secondary',
                                                                'suspended' => 'danger',
                                                                'pending_approval' => 'warning'
                                                            ];
                                                            $statusColor = $statusColors[$center['status']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?= $statusColor ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $center['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editCenter(<?= htmlspecialchars(json_encode($center)) ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteCenter(<?= $center['id'] ?>, '<?= htmlspecialchars($center['center_name']) ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Training Center</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Name *</label>
                                    <input type="text" class="form-control" name="center_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Code *</label>
                                    <input type="text" class="form-control" name="center_code" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">State</label>
                                    <select class="form-control" name="state">
                                        <option value="">Select State</option>
                                        <?php foreach ($states as $state): ?>
                                            <option value="<?= $state ?>"><?= $state ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Pincode</label>
                                    <input type="text" class="form-control" name="pincode">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" pattern="[0-9]{10}" 
                                           placeholder="10-digit phone number" maxlength="10"
                                           title="Please enter a valid 10-digit phone number">
                                    <div class="invalid-feedback">
                                        Please enter a valid 10-digit phone number.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6>SPOC (Single Point of Contact) Details</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">SPOC Name *</label>
                                    <input type="text" class="form-control" name="spoc_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">SPOC Phone</label>
                                    <input type="tel" class="form-control" name="spoc_phone" pattern="[0-9]{10}" 
                                           placeholder="10-digit phone number" maxlength="10"
                                           title="Please enter a valid 10-digit phone number">
                                    <div class="invalid-feedback">
                                        Please enter a valid 10-digit phone number.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">SPOC Email</label>
                                    <input type="email" class="form-control" name="spoc_email">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" value="100" min="1">
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
                <form method="POST" id="editCenterForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Training Center</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Name *</label>
                                    <input type="text" class="form-control" name="center_name" id="edit_center_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Code *</label>
                                    <input type="text" class="form-control" name="center_code" id="edit_center_code" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" id="edit_city">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">State</label>
                                    <select class="form-control" name="state" id="edit_state">
                                        <option value="">Select State</option>
                                        <?php foreach ($states as $state): ?>
                                            <option value="<?= $state ?>"><?= $state ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Pincode</label>
                                    <input type="text" class="form-control" name="pincode" id="edit_pincode">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" id="edit_phone" pattern="[0-9]{10}" 
                                           placeholder="10-digit phone number" maxlength="10"
                                           title="Please enter a valid 10-digit phone number">
                                    <div class="invalid-feedback">
                                        Please enter a valid 10-digit phone number.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6>SPOC Details</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">SPOC Name *</label>
                                    <input type="text" class="form-control" name="spoc_name" id="edit_spoc_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">SPOC Phone</label>
                                    <input type="tel" class="form-control" name="spoc_phone" id="edit_spoc_phone" pattern="[0-9]{10}" 
                                           placeholder="10-digit phone number" maxlength="10"
                                           title="Please enter a valid 10-digit phone number">
                                    <div class="invalid-feedback">
                                        Please enter a valid 10-digit phone number.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">SPOC Email</label>
                                    <input type="email" class="form-control" name="spoc_email" id="edit_spoc_email">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status" id="edit_status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="pending_approval">Pending Approval</option>
                                    </select>
                                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number validation
        function validatePhone(input) {
            const phoneRegex = /^[0-9]{10}$/;
            const value = input.value.replace(/\D/g, ''); // Remove non-digits
            
            if (value.length > 10) {
                input.value = value.slice(0, 10);
            } else {
                input.value = value;
            }
            
            if (value.length === 10 && phoneRegex.test(value)) {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                return true;
            } else if (value.length > 0) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                return false;
            } else {
                input.classList.remove('is-valid', 'is-invalid');
                return true; // Empty is valid for optional fields
            }
        }

        // Add event listeners to phone inputs
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    validatePhone(this);
                });
                
                input.addEventListener('keypress', function(e) {
                    // Only allow numbers
                    if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes(e.key)) {
                        e.preventDefault();
                    }
                });
            });

            // Form validation before submission
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const phoneInputs = form.querySelectorAll('input[type="tel"]');
                    let isValid = true;
                    
                    phoneInputs.forEach(function(input) {
                        if (input.value && !validatePhone(input)) {
                            isValid = false;
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please enter valid 10-digit phone numbers.');
                    }
                });
            });
        });

        function editCenter(center) {
            document.getElementById('edit_id').value = center.id;
            document.getElementById('edit_center_name').value = center.center_name;
            document.getElementById('edit_center_code').value = center.center_code;
            document.getElementById('edit_address').value = center.address || '';
            document.getElementById('edit_city').value = center.city || '';
            document.getElementById('edit_state').value = center.state || '';
            document.getElementById('edit_pincode').value = center.pincode || '';
            document.getElementById('edit_phone').value = center.phone || '';
            document.getElementById('edit_email').value = center.email;
            document.getElementById('edit_spoc_name').value = center.spoc_name;
            document.getElementById('edit_spoc_phone').value = center.spoc_phone || '';
            document.getElementById('edit_spoc_email').value = center.spoc_email || '';
            document.getElementById('edit_capacity').value = center.capacity;
            document.getElementById('edit_status').value = center.status;
            
            new bootstrap.Modal(document.getElementById('editCenterModal')).show();
        }

        function deleteCenter(id, name) {
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
    </script>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Training Centers Management', 'training_centers', $content);
?>

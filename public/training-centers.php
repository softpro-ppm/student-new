<?php
// Training Centers Management - v2.0 - Clean Layout Version
session_start();
require_once '../config/database.php';

// Start output buffering to capture content
ob_start();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getConnection();
        
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

// Get all training centers and summary
try {
    $conn = getConnection();
    $stmt = $conn->query("
        SELECT * FROM training_centers 
        WHERE status != 'deleted' 
        ORDER BY center_name ASC
    ");
    $trainingCenters = $stmt->fetchAll();
    
    // Get summary statistics
    $summary = [
        'total_centers' => count($trainingCenters),
        'active_centers' => count(array_filter($trainingCenters, fn($c) => $c['status'] === 'active')),
        'pending_centers' => count(array_filter($trainingCenters, fn($c) => $c['status'] === 'pending')),
        'total_capacity' => array_sum(array_column($trainingCenters, 'capacity'))
    ];
    
} catch (Exception $e) {
    $trainingCenters = [];
    $summary = ['total_centers' => 0, 'active_centers' => 0, 'pending_centers' => 0, 'total_capacity' => 0];
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
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center">
                        <div class="stat-icon bg-gradient-primary me-3">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 text-primary"><?= $summary['total_centers'] ?></h3>
                            <small class="text-muted">Total Centers</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center">
                        <div class="stat-icon bg-gradient-success me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 text-success"><?= $summary['active_centers'] ?></h3>
                            <small class="text-muted">Active Centers</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center">
                        <div class="stat-icon bg-gradient-warning me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 text-warning"><?= $summary['pending_centers'] ?></h3>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center">
                        <div class="stat-icon bg-gradient-info me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 text-info"><?= $summary['total_capacity'] ?></h3>
                            <small class="text-muted">Total Capacity</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Training Centers List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-list"></i> Training Centers List</h6>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                <i class="fas fa-plus"></i> Add Training Center
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($trainingCenters)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-building text-muted" style="font-size: 4rem;"></i>
                    <h5 class="text-muted mt-3">No Training Centers Found</h5>
                    <p class="text-muted">Get started by adding your first training center.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                        <i class="fas fa-plus"></i> Add Training Center
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
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
                            <?php foreach ($trainingCenters as $center): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($center['center_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($center['center_name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($center['city'] . ', ' . $center['state']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($center['pincode']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($center['spoc_name']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($center['spoc_email']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($center['phone']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($center['spoc_phone']) ?></small>
                                    </td>
                                    <td><span class="badge bg-info"><?= $center['capacity'] ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= $center['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($center['status']) ?>
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
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Center Modal -->
<div class="modal fade" id="addCenterModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
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
                                <select class="form-select" name="state">
                                    <option value="">Select State</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?= $state ?>"><?= $state ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">PIN Code</label>
                                <input type="text" class="form-control" name="pincode" pattern="[0-9]{6}" maxlength="6">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                    </div>
                    <h6 class="mt-3 mb-3">SPOC Details</h6>
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
                                <input type="tel" class="form-control" name="spoc_phone">
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
<div class="modal fade" id="editCenterModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Training Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Same form fields as add modal with id attributes for editing -->
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
                                <select class="form-select" name="state" id="edit_state">
                                    <option value="">Select State</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?= $state ?>"><?= $state ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">PIN Code</label>
                                <input type="text" class="form-control" name="pincode" id="edit_pincode" pattern="[0-9]{6}" maxlength="6">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                        </div>
                    </div>
                    <h6 class="mt-3 mb-3">SPOC Details</h6>
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
                                <input type="tel" class="form-control" name="spoc_phone" id="edit_spoc_phone">
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
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending">Pending</option>
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

<style>
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.card {
    min-height: 120px;
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.row {
    margin-left: -15px;
    margin-right: -15px;
}

.row > .col-md-3 {
    padding-left: 15px;
    padding-right: 15px;
}
</style>

<script>
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

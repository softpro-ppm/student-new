<?php
// Simple Training Centers Management (fallback version)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

$currentUser = $auth->getCurrentUser();
$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_center':
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address'] ?? '');
                $contactPerson = trim($_POST['contact_person'] ?? '');
                
                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }
                
                // Check if code or email already exists
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE code = ? OR email = ?");
                $stmt->execute([$code, $email]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Training center code or email already exists");
                }
                
                // Insert training center
                $stmt = $db->prepare("INSERT INTO training_centers (name, code, email, phone, address, contact_person) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $code, $email, $phone, $address, $contactPerson]);
                
                $successMessage = 'Training center added successfully!';
                break;
                
            case 'edit_center':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address'] ?? '');
                $contactPerson = trim($_POST['contact_person'] ?? '');
                $status = $_POST['status'];
                
                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }
                
                // Check if code or email already exists (excluding current record)
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE (code = ? OR email = ?) AND id != ?");
                $stmt->execute([$code, $email, $id]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Training center code or email already exists");
                }
                
                // Update training center
                $stmt = $db->prepare("UPDATE training_centers SET name = ?, code = ?, email = ?, phone = ?, address = ?, contact_person = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $code, $email, $phone, $address, $contactPerson, $status, $id]);
                
                $successMessage = 'Training center updated successfully!';
                break;
                
            case 'delete_center':
                $id = intval($_POST['id']);
                
                // Delete training center
                $stmt = $db->prepare("DELETE FROM training_centers WHERE id = ?");
                $stmt->execute([$id]);
                
                $successMessage = 'Training center deleted successfully!';
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get training centers
$stmt = $db->prepare("SELECT * FROM training_centers ORDER BY name");
$stmt->execute();
$trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Centers - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 56px; /* Account for fixed navbar */
        }
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            z-index: 1000;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar .nav-link {
            color: #333;
            border-radius: 0.25rem;
            margin: 0.125rem 0.5rem;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .sidebar .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .center-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .center-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php renderHeader(); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php renderSidebar('training-centers'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-building me-2 text-primary"></i>Training Centers
                    </h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                        <i class="fas fa-plus me-2"></i>Add Training Center
                    </button>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Training Centers Grid -->
                <div class="row">
                    <?php foreach ($trainingCenters as $center): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card center-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title text-primary"><?= htmlspecialchars($center['name']) ?></h5>
                                        <span class="badge bg-<?= $center['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($center['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">Code:</small>
                                        <span class="badge bg-info"><?= htmlspecialchars($center['code']) ?></span>
                                    </div>
                                    
                                    <?php if ($center['email']): ?>
                                        <p class="card-text">
                                            <i class="fas fa-envelope text-muted me-2"></i>
                                            <small><?= htmlspecialchars($center['email']) ?></small>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($center['phone']): ?>
                                        <p class="card-text">
                                            <i class="fas fa-phone text-muted me-2"></i>
                                            <small><?= htmlspecialchars($center['phone']) ?></small>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($center['contact_person']): ?>
                                        <p class="card-text">
                                            <i class="fas fa-user text-muted me-2"></i>
                                            <small><?= htmlspecialchars($center['contact_person']) ?></small>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($center['address']): ?>
                                        <p class="card-text">
                                            <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                            <small><?= htmlspecialchars(substr($center['address'], 0, 50)) ?>...</small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="editCenter(<?= htmlspecialchars(json_encode($center)) ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" 
                                                onclick="deleteCenter(<?= $center['id'] ?>, '<?= htmlspecialchars($center['name']) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($trainingCenters)): ?>
                        <div class="col-12">
                            <div class="card center-card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Training Centers Found</h5>
                                    <p class="text-muted">Add your first training center to get started.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                                        <i class="fas fa-plus me-2"></i>Add Training Center
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Center Modal -->
    <div class="modal fade" id="addCenterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_center">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Training Center</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Center Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Center Code *</label>
                            <input type="text" class="form-control" name="code" required maxlength="10" style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Center</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Center Modal -->
    <div class="modal fade" id="editCenterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_center">
                    <input type="hidden" name="id" id="editCenterId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Training Center</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Center Name *</label>
                            <input type="text" class="form-control" name="name" id="editCenterName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Center Code *</label>
                            <input type="text" class="form-control" name="code" id="editCenterCode" required maxlength="10" style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="editCenterEmail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="editCenterPhone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" id="editCenterContactPerson">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="editCenterAddress" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editCenterStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Center</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCenter(center) {
            document.getElementById('editCenterId').value = center.id;
            document.getElementById('editCenterName').value = center.name;
            document.getElementById('editCenterCode').value = center.code;
            document.getElementById('editCenterEmail').value = center.email || '';
            document.getElementById('editCenterPhone').value = center.phone || '';
            document.getElementById('editCenterContactPerson').value = center.contact_person || '';
            document.getElementById('editCenterAddress').value = center.address || '';
            document.getElementById('editCenterStatus').value = center.status;
            
            new bootstrap.Modal(document.getElementById('editCenterModal')).show();
        }

        function deleteCenter(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_center">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

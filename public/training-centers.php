<?php
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
                $password = 'softpro@123'; // Default password
                
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
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $db->beginTransaction();
                
                // Insert training center
                $stmt = $db->prepare("INSERT INTO training_centers (name, code, email, phone, address, contact_person, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $code, $email, $phone, $address, $contactPerson, $hashedPassword]);
                $centerId = $db->lastInsertId();
                
                // Create user account for training center
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, name, phone, training_center_id) VALUES (?, ?, ?, 'training_partner', ?, ?, ?)");
                $stmt->execute([$email, $email, $hashedPassword, $name, $phone, $centerId]);
                
                $db->commit();
                
                $successMessage = 'Training center added successfully! Default password: softpro@123';
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
                
                $db->beginTransaction();
                
                // Update training center
                $stmt = $db->prepare("UPDATE training_centers SET name = ?, code = ?, email = ?, phone = ?, address = ?, contact_person = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $code, $email, $phone, $address, $contactPerson, $status, $id]);
                
                // Update user account
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, name = ?, phone = ?, status = ? WHERE training_center_id = ?");
                $stmt->execute([$email, $email, $name, $phone, $status, $id]);
                
                $db->commit();
                
                $successMessage = 'Training center updated successfully!';
                break;
                
            case 'delete_center':
                $id = intval($_POST['id']);
                
                // Check if center has students
                $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE training_center_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cannot delete training center with existing students");
                }
                
                $db->beginTransaction();
                
                // Delete user account
                $stmt = $db->prepare("DELETE FROM users WHERE training_center_id = ?");
                $stmt->execute([$id]);
                
                // Delete training center
                $stmt = $db->prepare("DELETE FROM training_centers WHERE id = ?");
                $stmt->execute([$id]);
                
                $db->commit();
                
                $successMessage = 'Training center deleted successfully!';
                break;
                
            case 'reset_password':
                $id = intval($_POST['id']);
                $newPassword = 'softpro@123';
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $db->beginTransaction();
                
                // Update training center password
                $stmt = $db->prepare("UPDATE training_centers SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $id]);
                
                // Update user password
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE training_center_id = ?");
                $stmt->execute([$hashedPassword, $id]);
                
                $db->commit();
                
                $successMessage = 'Password reset successfully! New password: softpro@123';
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

// Get training centers with statistics
$stmt = $db->prepare("
    SELECT tc.*, 
           COUNT(DISTINCT s.id) as total_students,
           COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_students,
           COALESCE(SUM(f.amount), 0) as total_fees_collected,
           COUNT(DISTINCT b.id) as total_batches
    FROM training_centers tc
    LEFT JOIN students s ON tc.id = s.training_center_id
    LEFT JOIN fees f ON s.id = f.student_id AND f.status = 'paid'
    LEFT JOIN batches b ON tc.id IN (
        SELECT DISTINCT s2.training_center_id 
        FROM students s2 
        WHERE s2.batch_id = b.id
    )
    GROUP BY tc.id
    ORDER BY tc.created_at DESC
");
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
        .center-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        .center-card:hover {
            transform: translateY(-5px);
        }
        .center-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
        .center-body {
            padding: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 10px;
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .center-grid .col-md-4 {
            margin-bottom: 20px;
        }
        .summary-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
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
                        <i class="fas fa-building me-2 text-primary"></i>Training Centers Management
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

                <!-- Summary Statistics -->
                <div class="summary-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h3><?= count($trainingCenters) ?></h3>
                            <p class="mb-0">Total Training Centers</p>
                        </div>
                        <div class="col-md-3">
                            <h3><?= count(array_filter($trainingCenters, function($tc) { return $tc['status'] === 'active'; })) ?></h3>
                            <p class="mb-0">Active Centers</p>
                        </div>
                        <div class="col-md-3">
                            <h3><?= array_sum(array_column($trainingCenters, 'total_students')) ?></h3>
                            <p class="mb-0">Total Students</p>
                        </div>
                        <div class="col-md-3">
                            <h3>₹<?= number_format(array_sum(array_column($trainingCenters, 'total_fees_collected')), 2) ?></h3>
                            <p class="mb-0">Total Fees Collected</p>
                        </div>
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Training Centers</h4>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="viewMode" id="cardView" checked onclick="toggleView('card')">
                        <label class="btn btn-outline-primary" for="cardView">
                            <i class="fas fa-th-large me-2"></i>Card View
                        </label>
                        <input type="radio" class="btn-check" name="viewMode" id="tableView" onclick="toggleView('table')">
                        <label class="btn btn-outline-primary" for="tableView">
                            <i class="fas fa-table me-2"></i>Table View
                        </label>
                    </div>
                </div>

                <!-- Card View -->
                <div id="cardViewContainer" class="center-grid">
                    <div class="row">
                        <?php foreach ($trainingCenters as $center): ?>
                            <div class="col-md-4">
                                <div class="center-card">
                                    <div class="center-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1"><?= htmlspecialchars($center['name']) ?></h5>
                                                <p class="mb-0">
                                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($center['code']) ?></span>
                                                </p>
                                            </div>
                                            <span class="status-badge bg-<?= $center['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($center['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="center-body">
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <div class="stat-card">
                                                    <h4 class="text-primary mb-1"><?= $center['total_students'] ?></h4>
                                                    <small>Total Students</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stat-card">
                                                    <h4 class="text-success mb-1"><?= $center['active_students'] ?></h4>
                                                    <small>Active Students</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <p class="mb-1"><i class="fas fa-envelope text-muted me-2"></i><?= htmlspecialchars($center['email']) ?></p>
                                            <p class="mb-1"><i class="fas fa-phone text-muted me-2"></i><?= htmlspecialchars($center['phone']) ?></p>
                                            <?php if ($center['contact_person']): ?>
                                                <p class="mb-1"><i class="fas fa-user text-muted me-2"></i><?= htmlspecialchars($center['contact_person']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="stat-card">
                                                    <h5 class="text-warning mb-1">₹<?= number_format($center['total_fees_collected'], 2) ?></h5>
                                                    <small>Fees Collected</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editCenter(<?= htmlspecialchars(json_encode($center)) ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="resetPassword(<?= $center['id'] ?>, '<?= htmlspecialchars($center['name']) ?>')">
                                                <i class="fas fa-key"></i> Reset Password
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteCenter(<?= $center['id'] ?>, '<?= htmlspecialchars($center['name']) ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Table View -->
                <div id="tableViewContainer" style="display: none;">
                    <div class="card center-card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Code</th>
                                            <th>Contact</th>
                                            <th>Students</th>
                                            <th>Fees Collected</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trainingCenters as $center): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($center['name']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($center['contact_person'] ?? 'N/A') ?></small>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-info"><?= htmlspecialchars($center['code']) ?></span></td>
                                                <td>
                                                    <div>
                                                        <small><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($center['email']) ?></small><br>
                                                        <small><i class="fas fa-phone me-1"></i><?= htmlspecialchars($center['phone']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?= $center['active_students'] ?></span> / 
                                                    <span class="badge bg-secondary"><?= $center['total_students'] ?></span>
                                                </td>
                                                <td>₹<?= number_format($center['total_fees_collected'], 2) ?></td>
                                                <td>
                                                    <span class="status-badge bg-<?= $center['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst($center['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="editCenter(<?= htmlspecialchars(json_encode($center)) ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="resetPassword(<?= $center['id'] ?>, '<?= htmlspecialchars($center['name']) ?>')">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteCenter(<?= $center['id'] ?>, '<?= htmlspecialchars($center['name']) ?>')">
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
            </main>
        </div>
    </div>

    <!-- Add Training Center Modal -->
    <div class="modal fade" id="addCenterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_center">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Training Center</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Code *</label>
                                    <input type="text" class="form-control" name="code" required maxlength="10" style="text-transform: uppercase;">
                                    <div class="form-text">Unique identifier (e.g., TC001, HYD001)</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                    <div class="form-text">Will be used as login username</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone *</label>
                                    <input type="tel" class="form-control" name="phone" required pattern="[0-9]{10}" maxlength="10">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" name="contact_person">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Default Login Credentials:</strong><br>
                            Username: Email address<br>
                            Password: softpro@123
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

    <!-- Edit Training Center Modal -->
    <div class="modal fade" id="editCenterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editCenterForm">
                    <input type="hidden" name="action" value="edit_center">
                    <input type="hidden" name="id" id="editCenterId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Training Center</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Name *</label>
                                    <input type="text" class="form-control" name="name" id="editCenterName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Code *</label>
                                    <input type="text" class="form-control" name="code" id="editCenterCode" required maxlength="10" style="text-transform: uppercase;">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="editCenterEmail" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone *</label>
                                    <input type="tel" class="form-control" name="phone" id="editCenterPhone" required pattern="[0-9]{10}" maxlength="10">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" name="contact_person" id="editCenterContactPerson">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="editCenterStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="editCenterAddress" rows="3"></textarea>
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
        // Edit training center function
        function editCenter(center) {
            document.getElementById('editCenterId').value = center.id;
            document.getElementById('editCenterName').value = center.name;
            document.getElementById('editCenterCode').value = center.code;
            document.getElementById('editCenterEmail').value = center.email;
            document.getElementById('editCenterPhone').value = center.phone;
            document.getElementById('editCenterContactPerson').value = center.contact_person || '';
            document.getElementById('editCenterAddress').value = center.address || '';
            document.getElementById('editCenterStatus').value = center.status;
            
            new bootstrap.Modal(document.getElementById('editCenterModal')).show();
        }

        // Delete training center function
        function deleteCenter(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone and will also delete the associated user account.`)) {
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

        // Reset password function
        function resetPassword(id, name) {
            if (confirm(`Reset password for "${name}" to default (softpro@123)?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Toggle view function
        function toggleView(viewType) {
            const cardView = document.getElementById('cardViewContainer');
            const tableView = document.getElementById('tableViewContainer');
            
            if (viewType === 'card') {
                cardView.style.display = 'block';
                tableView.style.display = 'none';
            } else {
                cardView.style.display = 'none';
                tableView.style.display = 'block';
            }
        }

        // Phone number validation
        document.querySelectorAll('input[type="tel"]').forEach(function(input) {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });
        });

        // Auto-uppercase center codes
        document.querySelectorAll('input[name="code"]').forEach(function(input) {
            input.addEventListener('input', function(e) {
                this.value = this.value.toUpperCase();
            });
        });
    </script>
</body>
</html>

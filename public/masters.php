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
            case 'add_sector':
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $description = trim($_POST['description'] ?? '');
                
                // Check if code already exists
                $stmt = $db->prepare("SELECT id FROM sectors WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Sector code already exists");
                }
                
                $stmt = $db->prepare("INSERT INTO sectors (name, code, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $description]);
                
                $successMessage = 'Sector added successfully!';
                break;
                
            case 'edit_sector':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'];
                
                // Check if code already exists (excluding current record)
                $stmt = $db->prepare("SELECT id FROM sectors WHERE code = ? AND id != ?");
                $stmt->execute([$code, $id]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Sector code already exists");
                }
                
                $stmt = $db->prepare("UPDATE sectors SET name = ?, code = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $code, $description, $status, $id]);
                
                $successMessage = 'Sector updated successfully!';
                break;
                
            case 'delete_sector':
                $id = intval($_POST['id']);
                
                // Check if sector has courses
                $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE sector_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cannot delete sector with existing courses");
                }
                
                $stmt = $db->prepare("DELETE FROM sectors WHERE id = ?");
                $stmt->execute([$id]);
                
                $successMessage = 'Sector deleted successfully!';
                break;
                
            case 'add_course':
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $sectorId = intval($_POST['sector_id']);
                $durationMonths = intval($_POST['duration_months']);
                $feeAmount = floatval($_POST['fee_amount']);
                $description = trim($_POST['description'] ?? '');
                
                // Check if code already exists
                $stmt = $db->prepare("SELECT id FROM courses WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Course code already exists");
                }
                
                $stmt = $db->prepare("INSERT INTO courses (name, code, sector_id, duration_months, fee_amount, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $code, $sectorId, $durationMonths, $feeAmount, $description]);
                
                $successMessage = 'Course added successfully!';
                break;
                
            case 'edit_course':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $sectorId = intval($_POST['sector_id']);
                $durationMonths = intval($_POST['duration_months']);
                $feeAmount = floatval($_POST['fee_amount']);
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'];
                
                // Check if code already exists (excluding current record)
                $stmt = $db->prepare("SELECT id FROM courses WHERE code = ? AND id != ?");
                $stmt->execute([$code, $id]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Course code already exists");
                }
                
                $stmt = $db->prepare("UPDATE courses SET name = ?, code = ?, sector_id = ?, duration_months = ?, fee_amount = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $code, $sectorId, $durationMonths, $feeAmount, $description, $status, $id]);
                
                $successMessage = 'Course updated successfully!';
                break;
                
            case 'delete_course':
                $id = intval($_POST['id']);
                
                // Check if course has students or batches
                $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE course_id = ?");
                $stmt->execute([$id]);
                $studentCount = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM batches WHERE course_id = ?");
                $stmt->execute([$id]);
                $batchCount = $stmt->fetchColumn();
                
                if ($studentCount > 0 || $batchCount > 0) {
                    throw new Exception("Cannot delete course with existing students or batches");
                }
                
                $stmt = $db->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                
                $successMessage = 'Course deleted successfully!';
                break;
                
            case 'update_settings':
                $settings = $_POST['settings'] ?? [];
                
                $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                foreach ($settings as $key => $value) {
                    $stmt->execute([$value, $key]);
                }
                
                $successMessage = 'Settings updated successfully!';
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get active tab
$activeTab = $_GET['tab'] ?? 'sectors';

// Get sectors
$stmt = $db->prepare("SELECT * FROM sectors ORDER BY name");
$stmt->execute();
$sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses with sector names
$stmt = $db->prepare("
    SELECT c.*, s.name as sector_name 
    FROM courses c 
    LEFT JOIN sectors s ON c.sector_id = s.id 
    ORDER BY c.name
");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get settings
$stmt = $db->prepare("SELECT * FROM settings ORDER BY setting_key");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

include '../includes/layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masters - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .master-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .master-card:hover {
            transform: translateY(-5px);
        }
        .nav-pills .nav-link {
            border-radius: 25px;
            padding: 12px 25px;
            margin: 0 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .setting-group {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-actions {
            white-space: nowrap;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php renderHeader(); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php renderSidebar('masters'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cogs me-2 text-primary"></i>Masters Management
                    </h1>
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

                <!-- Navigation Pills -->
                <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'sectors' ? 'active' : '' ?>" 
                                id="pills-sectors-tab" data-bs-toggle="pill" 
                                data-bs-target="#pills-sectors" type="button" role="tab">
                            <i class="fas fa-industry me-2"></i>Sectors
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'courses' ? 'active' : '' ?>" 
                                id="pills-courses-tab" data-bs-toggle="pill" 
                                data-bs-target="#pills-courses" type="button" role="tab">
                            <i class="fas fa-book me-2"></i>Courses
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>" 
                                id="pills-settings-tab" data-bs-toggle="pill" 
                                data-bs-target="#pills-settings" type="button" role="tab">
                            <i class="fas fa-sliders-h me-2"></i>System Settings
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="pills-tabContent">
                    <!-- Sectors Tab -->
                    <div class="tab-pane fade <?= $activeTab === 'sectors' ? 'show active' : '' ?>" 
                         id="pills-sectors" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4><i class="fas fa-industry me-2"></i>Sectors Management</h4>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectorModal">
                                <i class="fas fa-plus me-2"></i>Add Sector
                            </button>
                        </div>
                        
                        <div class="card master-card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Code</th>
                                                <th>Description</th>
                                                <th>Courses</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sectors as $sector): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($sector['name']) ?></strong></td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($sector['code']) ?></span></td>
                                                    <td><?= htmlspecialchars(substr($sector['description'] ?? '', 0, 50)) ?>...</td>
                                                    <td>
                                                        <?php
                                                        $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE sector_id = ?");
                                                        $stmt->execute([$sector['id']]);
                                                        $courseCount = $stmt->fetchColumn();
                                                        ?>
                                                        <span class="badge bg-secondary"><?= $courseCount ?> courses</span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge bg-<?= $sector['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <?= ucfirst($sector['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="table-actions">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editSector(<?= htmlspecialchars(json_encode($sector)) ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteSector(<?= $sector['id'] ?>, '<?= htmlspecialchars($sector['name']) ?>')">
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

                    <!-- Courses Tab -->
                    <div class="tab-pane fade <?= $activeTab === 'courses' ? 'show active' : '' ?>" 
                         id="pills-courses" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4><i class="fas fa-book me-2"></i>Courses Management</h4>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                <i class="fas fa-plus me-2"></i>Add Course
                            </button>
                        </div>
                        
                        <div class="card master-card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Code</th>
                                                <th>Sector</th>
                                                <th>Duration</th>
                                                <th>Fee Amount</th>
                                                <th>Students</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($courses as $course): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($course['name']) ?></strong></td>
                                                    <td><span class="badge bg-primary"><?= htmlspecialchars($course['code']) ?></span></td>
                                                    <td><?= htmlspecialchars($course['sector_name'] ?? 'N/A') ?></td>
                                                    <td><?= $course['duration_months'] ?> months</td>
                                                    <td>₹<?= number_format($course['fee_amount'], 2) ?></td>
                                                    <td>
                                                        <?php
                                                        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE course_id = ?");
                                                        $stmt->execute([$course['id']]);
                                                        $studentCount = $stmt->fetchColumn();
                                                        ?>
                                                        <span class="badge bg-secondary"><?= $studentCount ?> students</span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge bg-<?= $course['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <?= ucfirst($course['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="table-actions">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editCourse(<?= htmlspecialchars(json_encode($course)) ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteCourse(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>')">
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

                    <!-- Settings Tab -->
                    <div class="tab-pane fade <?= $activeTab === 'settings' ? 'show active' : '' ?>" 
                         id="pills-settings" role="tabpanel">
                        <h4><i class="fas fa-sliders-h me-2"></i>System Settings</h4>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            
                            <!-- General Settings -->
                            <div class="setting-group">
                                <h5 class="mb-3"><i class="fas fa-cog me-2"></i>General Settings</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Site Name</label>
                                        <input type="text" class="form-control" name="settings[site_name]" 
                                               value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Academic Year</label>
                                        <input type="text" class="form-control" name="settings[academic_year]" 
                                               value="<?= htmlspecialchars($settings['academic_year'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Registration Fee (₹)</label>
                                        <input type="number" class="form-control" name="settings[registration_fee]" 
                                               value="<?= htmlspecialchars($settings['registration_fee'] ?? '') ?>" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Currency</label>
                                        <select class="form-select" name="settings[currency]">
                                            <option value="INR" <?= ($settings['currency'] ?? '') === 'INR' ? 'selected' : '' ?>>INR (₹)</option>
                                            <option value="USD" <?= ($settings['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                            <option value="EUR" <?= ($settings['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Assessment Settings -->
                            <div class="setting-group">
                                <h5 class="mb-3"><i class="fas fa-clipboard-list me-2"></i>Assessment Settings</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Default Passing Marks (%)</label>
                                        <input type="number" class="form-control" name="settings[assessment_passing_marks]" 
                                               value="<?= htmlspecialchars($settings['assessment_passing_marks'] ?? '70') ?>" min="1" max="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Certificate Template Path</label>
                                        <input type="text" class="form-control" name="settings[certificate_template_path]" 
                                               value="<?= htmlspecialchars($settings['certificate_template_path'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Communication Settings -->
                            <div class="setting-group">
                                <h5 class="mb-3"><i class="fas fa-envelope me-2"></i>Communication Settings</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">WhatsApp API URL</label>
                                        <input type="url" class="form-control" name="settings[whatsapp_api_url]" 
                                               value="<?= htmlspecialchars($settings['whatsapp_api_url'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="settings[email_smtp_host]" 
                                               value="<?= htmlspecialchars($settings['email_smtp_host'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" name="settings[email_smtp_port]" 
                                               value="<?= htmlspecialchars($settings['email_smtp_port'] ?? '587') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" name="settings[email_smtp_username]" 
                                               value="<?= htmlspecialchars($settings['email_smtp_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" name="settings[email_smtp_password]" 
                                               value="<?= htmlspecialchars($settings['email_smtp_password'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Sector Modal -->
    <div class="modal fade" id="addSectorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_sector">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Sector</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sector Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sector Code *</label>
                            <input type="text" class="form-control" name="code" required maxlength="10" style="text-transform: uppercase;">
                            <div class="form-text">Unique identifier for the sector (e.g., IT001, HC001)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Sector</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Sector Modal -->
    <div class="modal fade" id="editSectorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editSectorForm">
                    <input type="hidden" name="action" value="edit_sector">
                    <input type="hidden" name="id" id="editSectorId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Sector</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sector Name *</label>
                            <input type="text" class="form-control" name="name" id="editSectorName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sector Code *</label>
                            <input type="text" class="form-control" name="code" id="editSectorCode" required maxlength="10" style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editSectorDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editSectorStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Sector</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_course">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Course Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Course Code *</label>
                                    <input type="text" class="form-control" name="code" required maxlength="10" style="text-transform: uppercase;">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sector *</label>
                                    <select class="form-select" name="sector_id" required>
                                        <option value="">Select Sector</option>
                                        <?php foreach ($sectors as $sector): ?>
                                            <?php if ($sector['status'] === 'active'): ?>
                                                <option value="<?= $sector['id'] ?>"><?= htmlspecialchars($sector['name']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Duration (Months) *</label>
                                    <input type="number" class="form-control" name="duration_months" required min="1" max="60">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Fee Amount (₹) *</label>
                                    <input type="number" class="form-control" name="fee_amount" required min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editCourseForm">
                    <input type="hidden" name="action" value="edit_course">
                    <input type="hidden" name="id" id="editCourseId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Course Name *</label>
                                    <input type="text" class="form-control" name="name" id="editCourseName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Course Code *</label>
                                    <input type="text" class="form-control" name="code" id="editCourseCode" required maxlength="10" style="text-transform: uppercase;">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sector *</label>
                                    <select class="form-select" name="sector_id" id="editCourseSectorId" required>
                                        <option value="">Select Sector</option>
                                        <?php foreach ($sectors as $sector): ?>
                                            <option value="<?= $sector['id'] ?>"><?= htmlspecialchars($sector['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Duration (Months) *</label>
                                    <input type="number" class="form-control" name="duration_months" id="editCourseDuration" required min="1" max="60">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Fee Amount (₹) *</label>
                                    <input type="number" class="form-control" name="fee_amount" id="editCourseFeeAmount" required min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="editCourseStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editCourseDescription" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit sector function
        function editSector(sector) {
            document.getElementById('editSectorId').value = sector.id;
            document.getElementById('editSectorName').value = sector.name;
            document.getElementById('editSectorCode').value = sector.code;
            document.getElementById('editSectorDescription').value = sector.description || '';
            document.getElementById('editSectorStatus').value = sector.status;
            
            new bootstrap.Modal(document.getElementById('editSectorModal')).show();
        }

        // Delete sector function
        function deleteSector(id, name) {
            if (confirm(`Are you sure you want to delete the sector "${name}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_sector">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Edit course function
        function editCourse(course) {
            document.getElementById('editCourseId').value = course.id;
            document.getElementById('editCourseName').value = course.name;
            document.getElementById('editCourseCode').value = course.code;
            document.getElementById('editCourseSectorId').value = course.sector_id;
            document.getElementById('editCourseDuration').value = course.duration_months;
            document.getElementById('editCourseFeeAmount').value = course.fee_amount;
            document.getElementById('editCourseDescription').value = course.description || '';
            document.getElementById('editCourseStatus').value = course.status;
            
            new bootstrap.Modal(document.getElementById('editCourseModal')).show();
        }

        // Delete course function
        function deleteCourse(id, name) {
            if (confirm(`Are you sure you want to delete the course "${name}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Tab switching with URL update
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (e) {
                const tabId = e.target.getAttribute('data-bs-target').replace('#pills-', '');
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.replaceState(null, '', url);
            });
        });
    </script>
</body>
</html>

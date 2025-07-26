<?php
// Batches Management - v2.0
session_start();
require_once '../config/database-v2.php';

$currentUser = ['role' => 'admin', 'name' => 'Administrator'];
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getV2Connection();
        
        if ($action === 'add') {
            // Add new batch
            $batch_name = trim($_POST['batch_name'] ?? '');
            $course_id = intval($_POST['course_id'] ?? 0);
            $training_center_id = intval($_POST['training_center_id'] ?? 0);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $max_capacity = intval($_POST['max_capacity'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            
            if ($batch_name && $course_id && $training_center_id && $start_date && $end_date) {
                // Generate batch code
                $batch_code = 'B' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("
                    INSERT INTO batches (
                        batch_code, batch_name, course_id, training_center_id, start_date, 
                        end_date, max_capacity, description, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', NOW())
                ");
                
                $stmt->execute([
                    $batch_code, $batch_name, $course_id, $training_center_id, 
                    $start_date, $end_date, $max_capacity, $description
                ]);
                
                $message = "Batch added successfully! Batch Code: $batch_code";
                $messageType = "success";
            } else {
                $message = "Please fill all required fields.";
                $messageType = "error";
            }
        }
        
        if ($action === 'edit') {
            // Edit batch
            $id = intval($_POST['id'] ?? 0);
            $batch_name = trim($_POST['batch_name'] ?? '');
            $course_id = intval($_POST['course_id'] ?? 0);
            $training_center_id = intval($_POST['training_center_id'] ?? 0);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $max_capacity = intval($_POST['max_capacity'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'upcoming';
            
            if ($id && $batch_name && $course_id && $training_center_id) {
                $stmt = $conn->prepare("
                    UPDATE batches SET 
                        batch_name = ?, course_id = ?, training_center_id = ?, start_date = ?, 
                        end_date = ?, max_capacity = ?, description = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $batch_name, $course_id, $training_center_id, $start_date, 
                    $end_date, $max_capacity, $description, $status, $id
                ]);
                
                $message = "Batch updated successfully!";
                $messageType = "success";
            }
        }
        
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                // Check if batch has students
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM student_batches WHERE batch_id = ?");
                $checkStmt->execute([$id]);
                $studentCount = $checkStmt->fetchColumn();
                
                if ($studentCount > 0) {
                    $message = "Cannot delete batch with assigned students. Please remove students first.";
                    $messageType = "error";
                } else {
                    $stmt = $conn->prepare("UPDATE batches SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $message = "Batch deleted successfully!";
                    $messageType = "success";
                }
            }
        }
        
        if ($action === 'assign_student') {
            $batch_id = intval($_POST['batch_id'] ?? 0);
            $student_id = intval($_POST['student_id'] ?? 0);
            
            if ($batch_id && $student_id) {
                // Check if student is already assigned to this batch
                $checkStmt = $conn->prepare("SELECT id FROM student_batches WHERE student_id = ? AND batch_id = ?");
                $checkStmt->execute([$student_id, $batch_id]);
                
                if ($checkStmt->fetchColumn()) {
                    $message = "Student is already assigned to this batch.";
                    $messageType = "error";
                } else {
                    // Check batch capacity
                    $capacityStmt = $conn->prepare("
                        SELECT b.max_capacity, COUNT(sb.id) as current_count 
                        FROM batches b 
                        LEFT JOIN student_batches sb ON b.id = sb.batch_id 
                        WHERE b.id = ? GROUP BY b.id
                    ");
                    $capacityStmt->execute([$batch_id]);
                    $capacityData = $capacityStmt->fetch();
                    
                    if ($capacityData && $capacityData['current_count'] >= $capacityData['max_capacity']) {
                        $message = "Batch is at full capacity.";
                        $messageType = "error";
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO student_batches (student_id, batch_id, enrollment_date, status) 
                            VALUES (?, ?, CURDATE(), 'active')
                        ");
                        $stmt->execute([$student_id, $batch_id]);
                        
                        $message = "Student assigned to batch successfully!";
                        $messageType = "success";
                    }
                }
            }
        }
        
        if ($action === 'remove_student') {
            $batch_id = intval($_POST['batch_id'] ?? 0);
            $student_id = intval($_POST['student_id'] ?? 0);
            
            if ($batch_id && $student_id) {
                $stmt = $conn->prepare("UPDATE student_batches SET status = 'removed' WHERE student_id = ? AND batch_id = ?");
                $stmt->execute([$student_id, $batch_id]);
                
                $message = "Student removed from batch successfully!";
                $messageType = "success";
            }
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$center_filter = $_GET['center'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build search query
$whereConditions = ["b.status != 'deleted'"];
$params = [];

if ($search) {
    $whereConditions[] = "(b.batch_name LIKE ? OR b.batch_code LIKE ? OR c.course_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($center_filter) {
    $whereConditions[] = "b.training_center_id = ?";
    $params[] = $center_filter;
}

if ($status_filter) {
    $whereConditions[] = "b.status = ?";
    $params[] = $status_filter;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    $conn = getV2Connection();
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) 
        FROM batches b 
        LEFT JOIN courses c ON b.course_id = c.id 
        WHERE $whereClause
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalBatches = $countStmt->fetchColumn();
    $totalPages = ceil($totalBatches / $limit);
    
    // Get batches with pagination
    $sql = "
        SELECT b.*, c.course_name, tc.center_name,
               COUNT(sb.id) as student_count
        FROM batches b 
        LEFT JOIN courses c ON b.course_id = c.id 
        LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
        LEFT JOIN student_batches sb ON b.id = sb.batch_id AND sb.status = 'active'
        WHERE $whereClause 
        GROUP BY b.id
        ORDER BY b.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll();
    
    // Get courses and training centers for dropdowns
    $courseStmt = $conn->query("SELECT id, course_name FROM courses WHERE status = 'active' ORDER BY course_name");
    $courses = $courseStmt->fetchAll();
    
    $centerStmt = $conn->query("SELECT id, center_name FROM training_centers WHERE status = 'active' ORDER BY center_name");
    $trainingCenters = $centerStmt->fetchAll();
    
    // Get unassigned students for assignment modal
    $unassignedStmt = $conn->query("
        SELECT s.id, s.first_name, s.last_name, s.enrollment_number 
        FROM students s 
        WHERE s.status = 'enrolled' 
        AND s.id NOT IN (
            SELECT sb.student_id FROM student_batches sb WHERE sb.status = 'active'
        )
        ORDER BY s.first_name, s.last_name
    ");
    $unassignedStudents = $unassignedStmt->fetchAll();
    
} catch (Exception $e) {
    $batches = [];
    $courses = [];
    $trainingCenters = [];
    $unassignedStudents = [];
    if (empty($message)) {
        $message = "Error loading data: " . $e->getMessage();
        $messageType = "error";
    }
}

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">Batches Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard-v2.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Batches</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary btn-rounded" data-bs-toggle="modal" data-bs-target="#addBatchModal">
            <i class="fas fa-plus me-2"></i>Add Batch
        </button>
    </div>
</div>

<!-- Content Body -->
<div class="content-body">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-primary">
                    <i class="fas fa-layer-group"></i>
                </div>
                <h4><?= $totalBatches ?></h4>
                <p class="text-muted mb-0">Total Batches</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-success">
                    <i class="fas fa-play-circle"></i>
                </div>
                <h4><?= count(array_filter($batches, fn($b) => $b['status'] === 'active')) ?></h4>
                <p class="text-muted mb-0">Active Batches</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <h4><?= count(array_filter($batches, fn($b) => $b['status'] === 'upcoming')) ?></h4>
                <p class="text-muted mb-0">Upcoming Batches</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-info">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4><?= count(array_filter($batches, fn($b) => $b['status'] === 'completed')) ?></h4>
                <p class="text-muted mb-0">Completed</p>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Batches</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Batch name, code, course...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Training Center</label>
                    <select class="form-select" name="center">
                        <option value="">All Centers</option>
                        <?php foreach ($trainingCenters as $center): ?>
                            <option value="<?= $center['id'] ?>" <?= $center_filter == $center['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($center['center_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Batches Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Batches List</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Batch Details</th>
                            <th>Course</th>
                            <th>Training Center</th>
                            <th>Duration</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No batches found. Create your first batch to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($batch['batch_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($batch['batch_code']) ?></small>
                                            <?php if ($batch['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($batch['description'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($batch['course_name'] ?? 'Not Assigned') ?></td>
                                    <td><?= htmlspecialchars($batch['center_name'] ?? 'Not Assigned') ?></td>
                                    <td>
                                        <div>
                                            <small><strong>Start:</strong> <?= $batch['start_date'] ? date('d M Y', strtotime($batch['start_date'])) : '-' ?></small>
                                            <br>
                                            <small><strong>End:</strong> <?= $batch['end_date'] ? date('d M Y', strtotime($batch['end_date'])) : '-' ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2"><?= $batch['student_count'] ?>/<?= $batch['max_capacity'] ?></span>
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <?php 
                                                $percentage = $batch['max_capacity'] > 0 ? ($batch['student_count'] / $batch['max_capacity']) * 100 : 0;
                                                $progressColor = $percentage >= 100 ? 'danger' : ($percentage >= 80 ? 'warning' : 'success');
                                                ?>
                                                <div class="progress-bar bg-<?= $progressColor ?>" style="width: <?= min(100, $percentage) ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'upcoming' => 'info',
                                            'active' => 'success',
                                            'completed' => 'primary',
                                            'cancelled' => 'danger',
                                            'suspended' => 'warning'
                                        ];
                                        $statusColor = $statusColors[$batch['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <?= ucfirst($batch['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="showStudentAssignment(<?= $batch['id'] ?>, '<?= htmlspecialchars($batch['batch_name']) ?>')">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="viewBatchStudents(<?= $batch['id'] ?>, '<?= htmlspecialchars($batch['batch_name']) ?>')">
                                                <i class="fas fa-users"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editBatch(<?= htmlspecialchars(json_encode($batch)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteBatch(<?= $batch['id'] ?>, '<?= htmlspecialchars($batch['batch_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&center=<?= urlencode($center_filter) ?>&status=<?= urlencode($status_filter) ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&center=<?= urlencode($center_filter) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&center=<?= urlencode($center_filter) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Showing <?= min($totalBatches, $offset + 1) ?> to <?= min($totalBatches, $offset + $limit) ?> of <?= $totalBatches ?> batches
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" name="batch_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Course *</label>
                                <select class="form-select" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Training Center *</label>
                                <select class="form-select" name="training_center_id" required>
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $center): ?>
                                        <option value="<?= $center['id'] ?>"><?= htmlspecialchars($center['center_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Max Capacity *</label>
                                <input type="number" class="form-control" name="max_capacity" min="1" max="100" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date *</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Batch description..."></textarea>
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
<div class="modal fade" id="editBatchModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editBatchForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" name="batch_name" id="edit_batch_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Course *</label>
                                <select class="form-select" name="course_id" id="edit_course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Training Center *</label>
                                <select class="form-select" name="training_center_id" id="edit_training_center_id" required>
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $center): ?>
                                        <option value="<?= $center['id'] ?>"><?= htmlspecialchars($center['center_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Max Capacity *</label>
                                <input type="number" class="form-control" name="max_capacity" id="edit_max_capacity" min="1" max="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="upcoming">Upcoming</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date *</label>
                                <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
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

<!-- Assign Students Modal -->
<div class="modal fade" id="assignStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="assign_student">
                <input type="hidden" name="batch_id" id="assign_batch_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignModalTitle">Assign Students to Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Student</label>
                        <select class="form-select" name="student_id" required>
                            <option value="">Choose a student...</option>
                            <?php foreach ($unassignedStudents as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['enrollment_number'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBatch(batch) {
    document.getElementById('edit_id').value = batch.id;
    document.getElementById('edit_batch_name').value = batch.batch_name;
    document.getElementById('edit_course_id').value = batch.course_id;
    document.getElementById('edit_training_center_id').value = batch.training_center_id;
    document.getElementById('edit_max_capacity').value = batch.max_capacity;
    document.getElementById('edit_status').value = batch.status;
    document.getElementById('edit_start_date').value = batch.start_date;
    document.getElementById('edit_end_date').value = batch.end_date;
    document.getElementById('edit_description').value = batch.description || '';
    
    new bootstrap.Modal(document.getElementById('editBatchModal')).show();
}

function deleteBatch(id, name) {
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

function showStudentAssignment(batchId, batchName) {
    document.getElementById('assign_batch_id').value = batchId;
    document.getElementById('assignModalTitle').textContent = `Assign Students to "${batchName}"`;
    new bootstrap.Modal(document.getElementById('assignStudentModal')).show();
}

function viewBatchStudents(batchId, batchName) {
    // This would open a new page or modal to show all students in the batch
    window.open(`batch-students.php?batch_id=${batchId}`, '_blank');
}

// Date validation
document.addEventListener('DOMContentLoaded', function() {
    const startDateInputs = document.querySelectorAll('input[name="start_date"]');
    const endDateInputs = document.querySelectorAll('input[name="end_date"]');
    
    startDateInputs.forEach(function(startInput) {
        startInput.addEventListener('change', function() {
            const endInput = this.closest('form').querySelector('input[name="end_date"]');
            if (endInput && this.value) {
                endInput.min = this.value;
                if (endInput.value && endInput.value < this.value) {
                    endInput.value = this.value;
                }
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Batches Management', 'batches', $content);
?>

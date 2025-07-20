<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole(['admin', 'training_partner']);

$database = new Database();
$db = $database->getConnection();

$pageTitle = 'Batch Management';
$currentUser = $auth->getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_batch') {
        try {
            $name = $_POST['name'];
            $course_id = $_POST['course_id'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $timings = $_POST['timings'];
            $max_students = intval($_POST['max_students'] ?? 30);
            
            // Get training center for training partner
            $training_center_id = null;
            if ($currentUser['role'] === 'training_partner') {
                $stmt = $db->prepare("SELECT id FROM training_centers WHERE user_id = ?");
                $stmt->execute([$currentUser['id']]);
                $center = $stmt->fetch(PDO::FETCH_ASSOC);
                $training_center_id = $center['id'] ?? null;
            } else {
                $training_center_id = $_POST['training_center_id'] ?? null;
            }
            
            // Determine batch status based on dates
            $today = date('Y-m-d');
            $status = 'upcoming';
            if ($start_date <= $today && $end_date >= $today) {
                $status = 'ongoing';
            } elseif ($end_date < $today) {
                $status = 'completed';
            }
            
            // Insert batch
            $stmt = $db->prepare("
                INSERT INTO batches (name, course_id, training_center_id, start_date, end_date, timings, max_students, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $course_id, $training_center_id, $start_date, $end_date, $timings, $max_students, $status]);
            
            $success = "Batch created successfully!";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'edit_batch') {
        try {
            $batch_id = $_POST['batch_id'];
            $name = $_POST['name'];
            $course_id = $_POST['course_id'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $timings = $_POST['timings'];
            $max_students = intval($_POST['max_students'] ?? 30);
            $status = $_POST['status'];
            
            $training_center_id = null;
            if ($currentUser['role'] === 'admin' && isset($_POST['training_center_id'])) {
                $training_center_id = $_POST['training_center_id'];
            }
            
            // Update batch
            if ($currentUser['role'] === 'admin') {
                $stmt = $db->prepare("
                    UPDATE batches 
                    SET name = ?, course_id = ?, training_center_id = ?, start_date = ?, end_date = ?, timings = ?, max_students = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $course_id, $training_center_id, $start_date, $end_date, $timings, $max_students, $status, $batch_id]);
            } else {
                $stmt = $db->prepare("
                    UPDATE batches b
                    JOIN training_centers tc ON b.training_center_id = tc.id
                    SET b.name = ?, b.course_id = ?, b.start_date = ?, b.end_date = ?, b.timings = ?, b.max_students = ?, b.status = ?
                    WHERE b.id = ? AND tc.user_id = ?
                ");
                $stmt->execute([$name, $course_id, $start_date, $end_date, $timings, $max_students, $status, $batch_id, $currentUser['id']]);
            }
            
            $success = "Batch updated successfully!";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'delete_batch') {
        try {
            $batch_id = $_POST['batch_id'];
            
            // Check if batch has students
            $stmt = $db->prepare("SELECT COUNT(*) as student_count FROM student_batches WHERE batch_id = ?");
            $stmt->execute([$batch_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
            
            if ($count > 0) {
                throw new Exception("Cannot delete batch with enrolled students. Please remove students first.");
            }
            
            // Delete batch
            if ($currentUser['role'] === 'admin') {
                $stmt = $db->prepare("DELETE FROM batches WHERE id = ?");
                $stmt->execute([$batch_id]);
            } else {
                $stmt = $db->prepare("
                    DELETE b FROM batches b
                    JOIN training_centers tc ON b.training_center_id = tc.id
                    WHERE b.id = ? AND tc.user_id = ?
                ");
                $stmt->execute([$batch_id, $currentUser['id']]);
            }
            
            $success = "Batch deleted successfully!";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'add_students_to_batch') {
        try {
            $batch_id = $_POST['batch_id'];
            $student_ids = $_POST['student_ids'] ?? [];
            
            if (empty($student_ids)) {
                throw new Exception("Please select at least one student");
            }
            
            // Check batch capacity
            $stmt = $db->prepare("
                SELECT b.max_students, COUNT(sb.id) as current_count 
                FROM batches b 
                LEFT JOIN student_batches sb ON b.id = sb.batch_id AND sb.status = 'active'
                WHERE b.id = ? 
                GROUP BY b.id, b.max_students
            ");
            $stmt->execute([$batch_id]);
            $batch_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $available_slots = $batch_info['max_students'] - $batch_info['current_count'];
            
            if (count($student_ids) > $available_slots) {
                throw new Exception("Only $available_slots slots available in this batch");
            }
            
            // Add students to batch
            $stmt = $db->prepare("INSERT INTO student_batches (student_id, batch_id) VALUES (?, ?)");
            
            foreach ($student_ids as $student_id) {
                // Check if student is already in this batch
                $checkStmt = $db->prepare("SELECT id FROM student_batches WHERE student_id = ? AND batch_id = ? AND status = 'active'");
                $checkStmt->execute([$student_id, $batch_id]);
                
                if ($checkStmt->rowCount() == 0) {
                    $stmt->execute([$student_id, $batch_id]);
                }
            }
            
            $success = count($student_ids) . " student(s) added to batch successfully!";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'remove_student_from_batch') {
        try {
            $student_batch_id = $_POST['student_batch_id'];
            
            $stmt = $db->prepare("UPDATE student_batches SET status = 'dropped' WHERE id = ?");
            $stmt->execute([$student_batch_id]);
            
            $success = "Student removed from batch successfully!";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get batches with filters
$where_conditions = [];
$where_values = [];

if ($currentUser['role'] === 'training_partner') {
    $stmt = $db->prepare("SELECT id FROM training_centers WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $center = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($center) {
        $where_conditions[] = "b.training_center_id = ?";
        $where_values[] = $center['id'];
    }
}

// Apply filters
if (!empty($_GET['course_id'])) {
    $where_conditions[] = "b.course_id = ?";
    $where_values[] = $_GET['course_id'];
}

if (!empty($_GET['training_center_id']) && $currentUser['role'] === 'admin') {
    $where_conditions[] = "b.training_center_id = ?";
    $where_values[] = $_GET['training_center_id'];
}

if (!empty($_GET['status'])) {
    $where_conditions[] = "b.status = ?";
    $where_values[] = $_GET['status'];
}

if (!empty($_GET['search'])) {
    $where_conditions[] = "b.name LIKE ?";
    $where_values[] = '%' . $_GET['search'] . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM batches b $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($where_values);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get batches
$query = "
    SELECT b.*, c.name as course_name, c.duration_months, tc.name as center_name,
           COUNT(sb.id) as student_count
    FROM batches b 
    LEFT JOIN courses c ON b.course_id = c.id 
    LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
    LEFT JOIN student_batches sb ON b.id = sb.batch_id AND sb.status = 'active'
    $where_clause
    GROUP BY b.id
    ORDER BY b.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($where_values);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses for filters and forms
$stmt = $db->prepare("SELECT * FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training centers for admin
$training_centers = [];
if ($currentUser['role'] === 'admin') {
    $stmt = $db->prepare("SELECT * FROM training_centers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $training_centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_batches,
        COUNT(CASE WHEN status = 'upcoming' THEN 1 END) as upcoming_batches,
        COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing_batches,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_batches
    FROM batches b 
    $where_clause
";

$stmt = $db->prepare($stats_query);
$stmt->execute($where_values);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/layout.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-chalkboard-teacher me-2"></i>Batch Management</h2>
            <p class="text-muted">Manage training batches and student enrollment</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                <i class="fas fa-plus me-2"></i>Create Batch
            </button>
            <button class="btn btn-outline-primary" onclick="exportBatches()">
                <i class="fas fa-download me-2"></i>Export
            </button>
        </div>
    </div>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-primary"><?php echo number_format($stats['total_batches']); ?></div>
                            <div class="stat-label">Total Batches</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard-teacher fa-2x text-primary opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-warning"><?php echo number_format($stats['upcoming_batches']); ?></div>
                            <div class="stat-label">Upcoming</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-plus fa-2x text-warning opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-success"><?php echo number_format($stats['ongoing_batches']); ?></div>
                            <div class="stat-label">Ongoing</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-play-circle fa-2x text-success opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-info"><?php echo number_format($stats['completed_batches']); ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle fa-2x text-info opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Batch name">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo ($_GET['course_id'] ?? '') == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($currentUser['role'] === 'admin'): ?>
                <div class="col-md-3">
                    <label class="form-label">Training Center</label>
                    <select class="form-select" name="training_center_id">
                        <option value="">All Centers</option>
                        <?php foreach ($training_centers as $center): ?>
                        <option value="<?php echo $center['id']; ?>" <?php echo ($_GET['training_center_id'] ?? '') == $center['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($center['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php echo ($_GET['status'] ?? '') == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="ongoing" <?php echo ($_GET['status'] ?? '') == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo ($_GET['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Batches Grid -->
    <div class="row">
        <?php if (!empty($batches)): ?>
        <?php foreach ($batches as $batch): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($batch['name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($batch['course_name']); ?></small>
                    </div>
                    <span class="badge bg-<?php 
                        echo $batch['status'] === 'ongoing' ? 'success' : 
                            ($batch['status'] === 'upcoming' ? 'warning' : 'info'); 
                    ?>">
                        <?php echo ucfirst($batch['status']); ?>
                    </span>
                </div>
                
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Start Date</small>
                            <div><?php echo date('d-m-Y', strtotime($batch['start_date'])); ?></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">End Date</small>
                            <div><?php echo date('d-m-Y', strtotime($batch['end_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <small class="text-muted">Timings</small>
                            <div><?php echo htmlspecialchars($batch['timings']); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="mb-3">
                        <small class="text-muted">Training Center</small>
                        <div><?php echo htmlspecialchars($batch['center_name'] ?? 'Not assigned'); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Students</small>
                            <div class="d-flex align-items-center">
                                <span class="me-2"><?php echo $batch['student_count']; ?>/<?php echo $batch['max_students']; ?></span>
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar" style="width: <?php echo ($batch['student_count'] / $batch['max_students']) * 100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Duration</small>
                            <div><?php echo $batch['duration_months']; ?> months</div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="btn-group w-100">
                        <button class="btn btn-outline-primary btn-sm" onclick="viewBatchStudents(<?php echo $batch['id']; ?>)" title="View Students">
                            <i class="fas fa-users"></i>
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="addStudentsToBatch(<?php echo $batch['id']; ?>)" title="Add Students">
                            <i class="fas fa-user-plus"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="editBatch(<?php echo $batch['id']; ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteBatch(<?php echo $batch['id']; ?>)" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="col-12">
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No batches found</h5>
                <p class="text-muted">Click "Create Batch" to add your first batch</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addBatchForm">
                <input type="hidden" name="action" value="add_batch">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Name *</label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., Web Development Batch 1">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course *</label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Training Center</label>
                            <select class="form-select" name="training_center_id">
                                <option value="">Select Training Center</option>
                                <?php foreach ($training_centers as $center): ?>
                                <option value="<?php echo $center['id']; ?>"><?php echo htmlspecialchars($center['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximum Students</label>
                            <input type="number" class="form-control" name="max_students" value="30" min="1" max="100">
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Maximum Students</label>
                            <input type="number" class="form-control" name="max_students" value="30" min="1" max="100">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Timings *</label>
                        <input type="text" class="form-control" name="timings" required placeholder="e.g., 9:00 AM - 12:00 PM">
                        <div class="form-text">Specify the daily class timings</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Batch Students Modal -->
<div class="modal fade" id="viewStudentsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Batch Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="batchStudentsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Add Students to Batch Modal -->
<div class="modal fade" id="addStudentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Students to Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addStudentsForm">
                <input type="hidden" name="action" value="add_students_to_batch">
                <input type="hidden" name="batch_id" id="addStudentsBatchId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Search Students</label>
                        <input type="text" class="form-control" id="studentSearch" placeholder="Search by name or enrollment number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available Students</label>
                        <div id="availableStudentsList" style="max-height: 400px; overflow-y: auto;">
                            <!-- Students list will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add Selected Students
                    </button>
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
                <h5 class="modal-title">Edit Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editBatchForm">
                <input type="hidden" name="action" value="edit_batch">
                <input type="hidden" name="batch_id" id="editBatchId">
                
                <div class="modal-body" id="editBatchContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('addBatchForm').addEventListener('submit', function(e) {
    if (!validateForm('addBatchForm')) {
        e.preventDefault();
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    // Validate dates
    const startDate = new Date(this.start_date.value);
    const endDate = new Date(this.end_date.value);
    
    if (startDate >= endDate) {
        e.preventDefault();
        showToast('End date must be after start date', 'error');
        return;
    }
});

// View batch students
function viewBatchStudents(batchId) {
    fetch(`api/batches.php?action=get_batch_students&id=${batchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayBatchStudents(data.students, data.batch);
                new bootstrap.Modal(document.getElementById('viewStudentsModal')).show();
            } else {
                showToast('Error loading batch students', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading batch students', 'error');
        });
}

function displayBatchStudents(students, batch) {
    const content = document.getElementById('batchStudentsContent');
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>${batch.name}</h6>
                <p class="text-muted">${batch.course_name}</p>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge bg-primary">${students.length}/${batch.max_students} Students</span>
            </div>
        </div>
    `;
    
    if (students.length > 0) {
        html += `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Enrollment No.</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Enrollment Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        students.forEach(student => {
            html += `
                <tr>
                    <td>${student.enrollment_number}</td>
                    <td>${student.name}</td>
                    <td>${student.phone}</td>
                    <td>${formatDate(student.enrollment_date)}</td>
                    <td>
                        <span class="badge bg-${student.status === 'active' ? 'success' : 'secondary'}">
                            ${student.status}
                        </span>
                    </td>
                    <td>
                        ${student.status === 'active' ? 
                            `<button class="btn btn-sm btn-outline-danger" onclick="removeStudentFromBatch(${student.student_batch_id})">
                                <i class="fas fa-times"></i>
                            </button>` : ''
                        }
                    </td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    } else {
        html += `
            <div class="text-center py-4">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <p class="text-muted">No students enrolled in this batch</p>
            </div>
        `;
    }
    
    content.innerHTML = html;
}

// Add students to batch
function addStudentsToBatch(batchId) {
    document.getElementById('addStudentsBatchId').value = batchId;
    
    fetch(`api/batches.php?action=get_available_students&batch_id=${batchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAvailableStudents(data.students);
                new bootstrap.Modal(document.getElementById('addStudentsModal')).show();
            } else {
                showToast('Error loading available students', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading available students', 'error');
        });
}

function displayAvailableStudents(students) {
    const container = document.getElementById('availableStudentsList');
    
    if (students.length > 0) {
        let html = '';
        students.forEach(student => {
            html += `
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="student_ids[]" value="${student.id}" id="student${student.id}">
                    <label class="form-check-label" for="student${student.id}">
                        <strong>${student.name}</strong> (${student.enrollment_number})
                        <br><small class="text-muted">${student.course_name || 'No course assigned'} • ${student.phone}</small>
                    </label>
                </div>
            `;
        });
        container.innerHTML = html;
    } else {
        container.innerHTML = '<p class="text-muted">No available students found</p>';
    }
}

// Search students
document.getElementById('studentSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const checkboxes = document.querySelectorAll('#availableStudentsList .form-check');
    
    checkboxes.forEach(checkbox => {
        const label = checkbox.querySelector('label').textContent.toLowerCase();
        checkbox.style.display = label.includes(searchTerm) ? 'block' : 'none';
    });
});

// Edit batch
function editBatch(batchId) {
    fetch(`api/batches.php?action=get_batch&id=${batchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateEditForm(data.batch);
                document.getElementById('editBatchId').value = batchId;
                new bootstrap.Modal(document.getElementById('editBatchModal')).show();
            } else {
                showToast('Error loading batch details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading batch details', 'error');
        });
}

function populateEditForm(batch) {
    const content = document.getElementById('editBatchContent');
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Batch Name *</label>
                <input type="text" class="form-control" name="name" value="${batch.name}" required>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Course *</label>
                <select class="form-select" name="course_id" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" ${batch.course_id == <?php echo $course['id']; ?> ? 'selected' : ''}>
                        <?php echo htmlspecialchars($course['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Start Date *</label>
                <input type="date" class="form-control" name="start_date" value="${batch.start_date}" required>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">End Date *</label>
                <input type="date" class="form-control" name="end_date" value="${batch.end_date}" required>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Timings *</label>
                <input type="text" class="form-control" name="timings" value="${batch.timings}" required>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Maximum Students</label>
                <input type="number" class="form-control" name="max_students" value="${batch.max_students}" min="1" max="100">
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="upcoming" ${batch.status === 'upcoming' ? 'selected' : ''}>Upcoming</option>
                    <option value="ongoing" ${batch.status === 'ongoing' ? 'selected' : ''}>Ongoing</option>
                    <option value="completed" ${batch.status === 'completed' ? 'selected' : ''}>Completed</option>
                </select>
            </div>
        </div>
    `;
}

// Delete batch
function deleteBatch(batchId) {
    if (confirm('Are you sure you want to delete this batch? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_batch');
        formData.append('batch_id', batchId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            showToast('Batch deleted successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting batch', 'error');
        });
    }
}

// Remove student from batch
function removeStudentFromBatch(studentBatchId) {
    if (confirm('Are you sure you want to remove this student from the batch?')) {
        const formData = new FormData();
        formData.append('action', 'remove_student_from_batch');
        formData.append('student_batch_id', studentBatchId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            showToast('Student removed from batch successfully', 'success');
            // Refresh the students modal
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('viewStudentsModal'));
                if (modal) {
                    modal.hide();
                }
            }, 1500);
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error removing student', 'error');
        });
    }
}

// Export batches
function exportBatches() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'excel');
    window.open(currentUrl.toString(), '_blank');
}

// Auto-update batch status based on dates
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.querySelector('[name="start_date"]');
    const endDateInput = document.querySelector('[name="end_date"]');
    
    if (startDateInput && endDateInput) {
        function updateBatchStatus() {
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let status = 'upcoming';
            if (startDate <= today && endDate >= today) {
                status = 'ongoing';
            } else if (endDate < today) {
                status = 'completed';
            }
            
            // Update status display or selection if needed
        }
        
        startDateInput.addEventListener('change', updateBatchStatus);
        endDateInput.addEventListener('change', updateBatchStatus);
    }
});
</script>

<?php include '../includes/layout.php'; ?>

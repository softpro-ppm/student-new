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

$user = getCurrentUser();
$userRole = getCurrentUserRole();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create':
                if ($userRole !== 'admin' && $userRole !== 'training_partner') {
                    throw new Exception('Insufficient permissions');
                }
                
                $name = trim($_POST['name'] ?? '');
                $course_id = $_POST['course_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $start_time = $_POST['start_time'] ?? '';
                $end_time = $_POST['end_time'] ?? '';
                $status = $_POST['status'] ?? 'planned';
                
                if (empty($name) || !$course_id || empty($start_date)) {
                    throw new Exception('Batch name, course, and start date are required');
                }
                
                // Set training center based on user role
                if ($userRole === 'training_partner') {
                    $stmt = $db->prepare("SELECT training_center_id FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $training_center_id = $result['training_center_id'] ?? 0;
                }
                
                if (!$training_center_id) {
                    throw new Exception('Training center is required');
                }
                
                // Validate dates
                if ($end_date && $end_date <= $start_date) {
                    throw new Exception('End date must be after start date');
                }
                
                // Validate time
                if ($start_time && $end_time && $end_time <= $start_time) {
                    throw new Exception('End time must be after start time');
                }
                
                // Insert batch
                $stmt = $db->prepare("
                    INSERT INTO batches (name, course_id, training_center_id, start_date, end_date, 
                                       start_time, end_time, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name, $course_id, $training_center_id, $start_date, 
                    $end_date ?: null, $start_time ?: null, $end_time ?: null, $status
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Batch created successfully']);
                break;
                
            case 'update':
                if ($userRole !== 'admin' && $userRole !== 'training_partner') {
                    throw new Exception('Insufficient permissions');
                }
                
                $id = $_POST['id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $course_id = $_POST['course_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $start_time = $_POST['start_time'] ?? '';
                $end_time = $_POST['end_time'] ?? '';
                $status = $_POST['status'] ?? 'planned';
                
                if (empty($name) || !$course_id || empty($start_date)) {
                    throw new Exception('Batch name, course, and start date are required');
                }
                
                // Set training center based on user role
                if ($userRole === 'training_partner') {
                    $stmt = $db->prepare("SELECT training_center_id FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $training_center_id = $result['training_center_id'] ?? 0;
                }
                
                // Update batch
                $stmt = $db->prepare("
                    UPDATE batches 
                    SET name = ?, course_id = ?, training_center_id = ?, start_date = ?, 
                        end_date = ?, start_time = ?, end_time = ?, status = ?,
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $course_id, $training_center_id, $start_date, 
                    $end_date ?: null, $start_time ?: null, $end_time ?: null, $status, $id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Batch updated successfully']);
                break;
                
            case 'delete':
                if ($userRole !== 'admin') {
                    throw new Exception('Only administrators can delete batches');
                }
                
                $id = $_POST['id'] ?? 0;
                
                // Check if batch has students
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE batch_id = ?");
                $stmt->execute([$id]);
                $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($studentCount > 0) {
                    throw new Exception('Cannot delete batch with enrolled students');
                }
                
                // Delete batch
                $stmt = $db->prepare("DELETE FROM batches WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Fetch data for dropdowns
$stmt = $db->prepare("SELECT id, name FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id, name FROM training_centers WHERE status = 'active' ORDER BY name");
$stmt->execute();
$trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch batches based on user role
$whereClause = '';
$params = [];

if ($userRole === 'training_partner') {
    // Get batches for current user's training center
    $stmt = $db->prepare("SELECT training_center_id FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userTrainingCenterId = $result['training_center_id'] ?? 0;
    
    if ($userTrainingCenterId) {
        $whereClause = 'WHERE b.training_center_id = ?';
        $params[] = $userTrainingCenterId;
    }
}

$stmt = $db->prepare("
    SELECT b.*, c.name as course_name, tc.name as training_center_name,
           (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as student_count
    FROM batches b
    LEFT JOIN courses c ON b.course_id = c.id
    LEFT JOIN training_centers tc ON b.training_center_id = tc.id
    $whereClause
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/layout.php';
renderHeader('Batches');
?>

<div class="container-fluid">
    <div class="row">
        <?php renderSidebar($userRole); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-layer-group me-2"></i>Batches
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                        <i class="fas fa-plus me-1"></i>Add Batch
                    </button>
                    <?php endif; ?>
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
                                        Total Batches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($batches); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-layer-group fa-2x text-gray-300"></i>
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
                                        Ongoing Batches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count(array_filter($batches, function($b) { return $b['status'] === 'ongoing'; })); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-play-circle fa-2x text-gray-300"></i>
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
                                        Planned Batches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count(array_filter($batches, function($b) { return $b['status'] === 'planned'; })); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-plus fa-2x text-gray-300"></i>
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
                                        Total Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo array_sum(array_column($batches, 'student_count')); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Batches Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Batches List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="batchesTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Batch Name</th>
                                    <th>Course</th>
                                    <?php if ($userRole === 'admin'): ?>
                                    <th>Training Center</th>
                                    <?php endif; ?>
                                    <th>Duration</th>
                                    <th>Timing</th>
                                    <th>Students</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($batch['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($batch['course_name'] ?? 'N/A'); ?></td>
                                    <?php if ($userRole === 'admin'): ?>
                                    <td><?php echo htmlspecialchars($batch['training_center_name'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($batch['start_date']): ?>
                                            <?php echo date('M d, Y', strtotime($batch['start_date'])); ?>
                                            <?php if ($batch['end_date']): ?>
                                                <br><small class="text-muted">to <?php echo date('M d, Y', strtotime($batch['end_date'])); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($batch['start_time'] && $batch['end_time']): ?>
                                            <?php echo date('g:i A', strtotime($batch['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($batch['end_time'])); ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo $batch['student_count']; ?></span></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($batch['status']) {
                                            case 'planned': $statusClass = 'bg-warning'; break;
                                            case 'ongoing': $statusClass = 'bg-success'; break;
                                            case 'completed': $statusClass = 'bg-info'; break;
                                            case 'cancelled': $statusClass = 'bg-danger'; break;
                                            default: $statusClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($batch['status']); ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($batch['created_at'])); ?></td>
                                    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="viewBatch(<?php echo $batch['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBatch(<?php echo $batch['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($userRole === 'admin'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBatch(<?php echo $batch['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php endif; ?>
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

<?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1" aria-labelledby="addBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBatchModalLabel">Add Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addBatchForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Course *</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php if ($userRole === 'admin'): ?>
                    <div class="mb-3">
                        <label for="training_center_id" class="form-label">Training Center *</label>
                        <select class="form-select" id="training_center_id" name="training_center_id" required>
                            <option value="">Select Training Center</option>
                            <?php foreach ($trainingCenters as $tc): ?>
                            <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="planned">Planned</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Batch Modal -->
<div class="modal fade" id="editBatchModal" tabindex="-1" aria-labelledby="editBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBatchModalLabel">Edit Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editBatchForm">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_course_id" class="form-label">Course *</label>
                                <select class="form-select" id="edit_course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php if ($userRole === 'admin'): ?>
                    <div class="mb-3">
                        <label for="edit_training_center_id" class="form-label">Training Center *</label>
                        <select class="form-select" id="edit_training_center_id" name="training_center_id" required>
                            <option value="">Select Training Center</option>
                            <?php foreach ($trainingCenters as $tc): ?>
                            <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="planned">Planned</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
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

<!-- View Batch Modal -->
<div class="modal fade" id="viewBatchModal" tabindex="-1" aria-labelledby="viewBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewBatchModalLabel">Batch Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewBatchContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#batchesTable').DataTable({
        "pageLength": 25,
        "order": [[ <?php echo ($userRole === 'admin') ? '7' : '6'; ?>, "desc" ]], // Sort by created date
        "columnDefs": [
            { "orderable": false, "targets": -1 } // Disable sorting on Actions column
        ]
    });
    
    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
    // Add Batch Form
    $('#addBatchForm').on('submit', function(e) {
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
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while creating the batch.');
            }
        });
    });
    
    // Edit Batch Form
    $('#editBatchForm').on('submit', function(e) {
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
                alert('An error occurred while updating the batch.');
            }
        });
    });
    <?php endif; ?>
});

<?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
function viewBatch(id) {
    const batches = <?php echo json_encode($batches); ?>;
    const batch = batches.find(b => b.id == id);
    
    if (batch) {
        const content = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Batch Information</h6>
                    <p><strong>Batch Name:</strong> ${batch.name}</p>
                    <p><strong>Course:</strong> ${batch.course_name || 'N/A'}</p>
                    <p><strong>Training Center:</strong> ${batch.training_center_name || 'N/A'}</p>
                    <p><strong>Status:</strong> <span class="badge bg-${batch.status === 'ongoing' ? 'success' : batch.status === 'completed' ? 'info' : batch.status === 'cancelled' ? 'danger' : 'warning'}">${batch.status ? batch.status.charAt(0).toUpperCase() + batch.status.slice(1) : 'N/A'}</span></p>
                    <p><strong>Total Students:</strong> ${batch.student_count}</p>
                </div>
                <div class="col-md-6">
                    <h6>Schedule Information</h6>
                    <p><strong>Start Date:</strong> ${batch.start_date ? new Date(batch.start_date).toLocaleDateString() : 'N/A'}</p>
                    <p><strong>End Date:</strong> ${batch.end_date ? new Date(batch.end_date).toLocaleDateString() : 'N/A'}</p>
                    <p><strong>Start Time:</strong> ${batch.start_time ? new Date('2000-01-01T' + batch.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'N/A'}</p>
                    <p><strong>End Time:</strong> ${batch.end_time ? new Date('2000-01-01T' + batch.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'N/A'}</p>
                    <p><strong>Created:</strong> ${new Date(batch.created_at).toLocaleDateString()}</p>
                </div>
            </div>
        `;
        
        $('#viewBatchContent').html(content);
        $('#viewBatchModal').modal('show');
    }
}

function editBatch(id) {
    const batches = <?php echo json_encode($batches); ?>;
    const batch = batches.find(b => b.id == id);
    
    if (batch) {
        $('#edit_id').val(batch.id);
        $('#edit_name').val(batch.name);
        $('#edit_course_id').val(batch.course_id);
        $('#edit_training_center_id').val(batch.training_center_id);
        $('#edit_start_date').val(batch.start_date);
        $('#edit_end_date').val(batch.end_date);
        $('#edit_start_time').val(batch.start_time);
        $('#edit_end_time').val(batch.end_time);
        $('#edit_status').val(batch.status);
        $('#editBatchModal').modal('show');
    }
}

function deleteBatch(id) {
    if (confirm('Are you sure you want to delete this batch? This action cannot be undone.')) {
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
                alert('An error occurred while deleting the batch.');
            }
        });
    }
}
<?php endif; ?>
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

<?php renderFooter(); ?>

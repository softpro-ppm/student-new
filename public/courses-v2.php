<?php
// Courses Management - v2.0
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
            // Add new course
            $course_name = trim($_POST['course_name'] ?? '');
            $sector_id = intval($_POST['sector_id'] ?? 0);
            $course_fee = floatval($_POST['course_fee'] ?? 0);
            $registration_fee = floatval($_POST['registration_fee'] ?? 0);
            $duration_hours = intval($_POST['duration_hours'] ?? 0);
            $course_duration = intval($_POST['course_duration'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $qualification_required = trim($_POST['qualification_required'] ?? '');
            
            if ($course_name && $sector_id && $course_fee > 0) {
                // Generate course code
                $course_code = 'C' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("
                    INSERT INTO courses (
                        course_code, course_name, sector_id, course_fee, registration_fee, 
                        duration_hours, course_duration, description, qualification_required, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                $stmt->execute([
                    $course_code, $course_name, $sector_id, $course_fee, $registration_fee,
                    $duration_hours, $course_duration, $description, $qualification_required
                ]);
                
                $message = "Course added successfully! Course Code: $course_code";
                $messageType = "success";
            } else {
                $message = "Please fill all required fields.";
                $messageType = "error";
            }
        }
        
        if ($action === 'edit') {
            // Edit course
            $id = intval($_POST['id'] ?? 0);
            $course_name = trim($_POST['course_name'] ?? '');
            $sector_id = intval($_POST['sector_id'] ?? 0);
            $course_fee = floatval($_POST['course_fee'] ?? 0);
            $registration_fee = floatval($_POST['registration_fee'] ?? 0);
            $duration_hours = intval($_POST['duration_hours'] ?? 0);
            $course_duration = intval($_POST['course_duration'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $qualification_required = trim($_POST['qualification_required'] ?? '');
            $status = $_POST['status'] ?? 'active';
            
            if ($id && $course_name && $sector_id && $course_fee > 0) {
                $stmt = $conn->prepare("
                    UPDATE courses SET 
                        course_name = ?, sector_id = ?, course_fee = ?, registration_fee = ?, 
                        duration_hours = ?, course_duration = ?, description = ?, 
                        qualification_required = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $course_name, $sector_id, $course_fee, $registration_fee, 
                    $duration_hours, $course_duration, $description, 
                    $qualification_required, $status, $id
                ]);
                
                $message = "Course updated successfully!";
                $messageType = "success";
            }
        }
        
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                // Check if course has batches
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM batches WHERE course_id = ? AND status != 'deleted'");
                $checkStmt->execute([$id]);
                $batchCount = $checkStmt->fetchColumn();
                
                if ($batchCount > 0) {
                    $message = "Cannot delete course with active batches. Please remove batches first.";
                    $messageType = "error";
                } else {
                    $stmt = $conn->prepare("UPDATE courses SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $message = "Course deleted successfully!";
                    $messageType = "success";
                }
            }
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$sector_filter = $_GET['sector'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build search query
$whereConditions = ["c.status != 'deleted'"];
$params = [];

if ($search) {
    $whereConditions[] = "(c.course_name LIKE ? OR c.job_role LIKE ? OR c.course_code LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($sector_filter) {
    $whereConditions[] = "c.sector_id = ?";
    $params[] = $sector_filter;
}

if ($status_filter) {
    $whereConditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    $conn = getV2Connection();
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) 
        FROM courses c 
        LEFT JOIN sectors s ON c.sector_id = s.id 
        WHERE $whereClause
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalCourses = $countStmt->fetchColumn();
    $totalPages = ceil($totalCourses / $limit);
    
    // Get courses with pagination
    $sql = "
        SELECT c.*, s.sector_name,
               COUNT(b.id) as batch_count
        FROM courses c 
        LEFT JOIN sectors s ON c.sector_id = s.id 
        LEFT JOIN batches b ON c.id = b.course_id AND b.status != 'deleted'
        WHERE $whereClause 
        GROUP BY c.id
        ORDER BY c.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
    
    // Get sectors for dropdown
    $sectorStmt = $conn->query("SELECT id, sector_name FROM sectors WHERE status = 'active' ORDER BY sector_name");
    $sectors = $sectorStmt->fetchAll();
    
} catch (Exception $e) {
    $courses = [];
    $sectors = [];
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
            <h4 class="mb-0">Courses Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard-v2.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Courses</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary btn-rounded" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="fas fa-plus me-2"></i>Add Course
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
                    <i class="fas fa-book"></i>
                </div>
                <h4><?= $totalCourses ?></h4>
                <p class="text-muted mb-0">Total Courses</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4><?= count(array_filter($courses, fn($c) => $c['status'] === 'active')) ?></h4>
                <p class="text-muted mb-0">Active Courses</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-info">
                    <i class="fas fa-layer-group"></i>
                </div>
                <h4><?= array_sum(array_column($courses, 'batch_count')) ?></h4>
                <p class="text-muted mb-0">Total Batches</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-warning">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <h4>₹<?= number_format(array_sum(array_column($courses, 'course_fee'))) ?></h4>
                <p class="text-muted mb-0">Total Fee Value</p>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Search Courses</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Course name, job role, code...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sector</label>
                    <select class="form-select" name="sector">
                        <option value="">All Sectors</option>
                        <?php foreach ($sectors as $sector): ?>
                            <option value="<?= $sector['id'] ?>" <?= $sector_filter == $sector['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sector['sector_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
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

    <!-- Courses Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-book me-2"></i>Courses List</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Course Details</th>
                            <th>Sector</th>
                            <th>Duration</th>
                            <th>Fees</th>
                            <th>Batches</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($courses)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No courses found. Create your first course to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($course['course_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($course['course_code']) ?></small>
                                            <?php if ($course['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($course['description'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($course['sector_name'] ?? 'Not Assigned') ?></td>
                                    <td>
                                        <div>
                                            <?php if ($course['course_duration']): ?>
                                                <span class="badge bg-info text-dark">
                                                    <i class="fas fa-calendar me-1"></i><?= $course['course_duration'] ?> days
                                                </span>
                                                <br>
                                            <?php endif; ?>
                                            <?php if ($course['duration_hours']): ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-clock me-1"></i><?= $course['duration_hours'] ?> hrs
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-success">Course: ₹<?= number_format($course['course_fee']) ?></strong>
                                            <?php if ($course['registration_fee']): ?>
                                                <br><small class="text-muted">Registration: ₹<?= number_format($course['registration_fee']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($course['batch_count'] > 0): ?>
                                            <span class="badge bg-primary"><?= $course['batch_count'] ?> Batches</span>
                                        <?php else: ?>
                                            <span class="text-muted">No Batches</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'active' => 'success',
                                            'inactive' => 'danger',
                                            'draft' => 'warning'
                                        ];
                                        $statusColor = $statusColors[$course['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <?= ucfirst($course['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editCourse(<?= htmlspecialchars(json_encode($course)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteCourse(<?= $course['id'] ?>, '<?= htmlspecialchars($course['course_name']) ?>')">
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
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sector=<?= urlencode($sector_filter) ?>&status=<?= urlencode($status_filter) ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&sector=<?= urlencode($sector_filter) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sector=<?= urlencode($sector_filter) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Showing <?= min($totalCourses, $offset + 1) ?> to <?= min($totalCourses, $offset + $limit) ?> of <?= $totalCourses ?> courses
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sector *</label>
                                <div class="input-group">
                                    <select class="form-select" name="sector_id" required>
                                        <option value="">Select Sector</option>
                                        <?php foreach ($sectors as $sector): ?>
                                            <option value="<?= $sector['id'] ?>"><?= htmlspecialchars($sector['sector_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" onclick="addNewSector()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Course Name *</label>
                                <input type="text" class="form-control" name="course_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Course Fee (₹) *</label>
                                <input type="number" class="form-control" name="course_fee" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Registration Fee (₹)</label>
                                <input type="number" class="form-control" name="registration_fee" min="0" step="0.01" value="500">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Course Duration (Days)</label>
                                <input type="number" class="form-control" name="course_duration" min="1" max="365" placeholder="e.g., 90">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Duration (Hours)</label>
                                <input type="number" class="form-control" name="duration_hours" min="1" max="1000" placeholder="e.g., 400">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qualification Required</label>
                        <input type="text" class="form-control" name="qualification_required" 
                               placeholder="e.g., 10th Pass, ITI, Graduate">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Description</label>
                        <textarea class="form-control" name="description" rows="3" 
                                  placeholder="Brief description of the course..."></textarea>
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
<div class="modal fade" id="editCourseModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editCourseForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sector *</label>
                                <div class="input-group">
                                    <select class="form-select" name="sector_id" id="edit_sector_id" required>
                                        <option value="">Select Sector</option>
                                        <?php foreach ($sectors as $sector): ?>
                                            <option value="<?= $sector['id'] ?>"><?= htmlspecialchars($sector['sector_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" onclick="addNewSector()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Course Name *</label>
                                <input type="text" class="form-control" name="course_name" id="edit_course_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Course Fee (₹) *</label>
                                <input type="number" class="form-control" name="course_fee" id="edit_course_fee" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Registration Fee (₹)</label>
                                <input type="number" class="form-control" name="registration_fee" id="edit_registration_fee" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Duration (Days)</label>
                                <input type="number" class="form-control" name="course_duration" id="edit_course_duration" min="1" max="365">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Duration (Hours)</label>
                                <input type="number" class="form-control" name="duration_hours" id="edit_duration_hours" min="1" max="1000">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qualification Required</label>
                        <input type="text" class="form-control" name="qualification_required" id="edit_qualification_required"
                               placeholder="e.g., 10th Pass, ITI, Graduate">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3" 
                                  placeholder="Brief description of the course..."></textarea>
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

<script>
function editCourse(course) {
    document.getElementById('edit_id').value = course.id;
    document.getElementById('edit_course_name').value = course.course_name;
    document.getElementById('edit_sector_id').value = course.sector_id;
    document.getElementById('edit_course_fee').value = course.course_fee;
    document.getElementById('edit_registration_fee').value = course.registration_fee || '';
    document.getElementById('edit_duration_hours').value = course.duration_hours || '';
    document.getElementById('edit_course_duration').value = course.course_duration || '';
    document.getElementById('edit_qualification_required').value = course.qualification_required || '';
    document.getElementById('edit_description').value = course.description || '';
    document.getElementById('edit_status').value = course.status;
    
    new bootstrap.Modal(document.getElementById('editCourseModal')).show();
}

function addNewSector() {
    const sectorName = prompt('Enter new sector name:');
    if (sectorName && sectorName.trim()) {
        // In a real implementation, this would make an AJAX call to add the sector
        alert('Feature to add new sectors will be implemented. For now, please contact administrator.');
    }
}

function deleteCourse(id, name) {
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

// Course fee formatting
document.addEventListener('DOMContentLoaded', function() {
    const feeInputs = document.querySelectorAll('input[name="course_fee"]');
    feeInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            // Remove any non-numeric characters except decimal point
            this.value = this.value.replace(/[^0-9.]/g, '');
            
            // Ensure only one decimal point
            const parts = this.value.split('.');
            if (parts.length > 2) {
                this.value = parts[0] + '.' + parts.slice(1).join('');
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Courses Management', 'courses', $content);
?>

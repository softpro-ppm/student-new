<?php
/**
 * Enhanced Students Management Page
 * Features: Modern UI, AJAX operations, bulk actions, advanced filtering
 */

// Security and error handling
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include required files
require_once '../includes/auth.php';
require_once '../config/database-simple.php';

// Security checks
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$userRole = getCurrentUserRole();
$userName = getCurrentUserName();

// Permission check
if (!in_array($userRole, ['admin', 'training_partner'])) {
    header('Location: unauthorized.php');
    exit();
}

// Page configuration
$pageTitle = 'Students Management';
$pageDescription = 'Manage student records, enrollments, and information';
$pageHeader = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Students']
];

// Initialize database connection
try {
    $db = getConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Students page database error: " . $e->getMessage());
    die('System temporarily unavailable. Please try again later.');
}

$message = '';
$messageType = '';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['ajax']) {
            case 'get_students':
                $whereClause = '';
                $params = [];
                
                // Filter by training center for training partners
                if ($userRole === 'training_partner') {
                    $whereClause = ' WHERE s.training_center_id = ?';
                    $params[] = $user['id'];
                }
                
                // Additional filters
                if (!empty($_GET['status'])) {
                    $whereClause .= ($whereClause ? ' AND' : ' WHERE') . ' s.status = ?';
                    $params[] = $_GET['status'];
                }
                
                if (!empty($_GET['batch_id'])) {
                    $whereClause .= ($whereClause ? ' AND' : ' WHERE') . ' s.batch_id = ?';
                    $params[] = $_GET['batch_id'];
                }
                
                $query = "
                    SELECT s.*, 
                           b.batch_name, 
                           c.name as course_name,
                           tc.center_name as training_center_name
                    FROM students s
                    LEFT JOIN batches b ON s.batch_id = b.id
                    LEFT JOIN courses c ON b.course_id = c.id
                    LEFT JOIN training_centers tc ON s.training_center_id = tc.id
                    {$whereClause}
                    ORDER BY s.created_at DESC
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $students]);
                break;
                
            case 'get_student':
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("
                    SELECT s.*, 
                           b.batch_name, 
                           c.name as course_name,
                           tc.center_name as training_center_name
                    FROM students s
                    LEFT JOIN batches b ON s.batch_id = b.id
                    LEFT JOIN courses c ON b.course_id = c.id
                    LEFT JOIN training_centers tc ON s.training_center_id = tc.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    echo json_encode(['success' => true, 'data' => $student]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                }
                break;
                
            case 'get_batches':
                $training_center_id = $_GET['training_center_id'] ?? '';
                $whereClause = '';
                $params = [];
                
                if ($userRole === 'training_partner') {
                    $whereClause = ' WHERE b.training_center_id = ?';
                    $params[] = $user['id'];
                } elseif ($training_center_id) {
                    $whereClause = ' WHERE b.training_center_id = ?';
                    $params[] = $training_center_id;
                }
                
                $query = "
                    SELECT b.id, b.batch_name, c.name as course_name
                    FROM batches b
                    LEFT JOIN courses c ON b.course_id = c.id
                    {$whereClause}
                    ORDER BY b.batch_name
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $batches]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    try {
        $action = $_POST['action'] ?? '';
        
        // CSRF protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid security token');
        }
        
        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $batch_id = $_POST['batch_id'] ?? null;
                $training_center_id = $_POST['training_center_id'] ?? null;
                
                // Validation
                if (empty($name) || empty($phone)) {
                    throw new Exception('Name and phone are required');
                }
                
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address');
                }
                
                // Check for duplicate phone
                $stmt = $db->prepare("SELECT id FROM students WHERE phone = ?");
                $stmt->execute([$phone]);
                if ($stmt->fetch()) {
                    throw new Exception('Phone number already exists');
                }
                
                // Set training center for training partners
                if ($userRole === 'training_partner') {
                    $training_center_id = $user['id'];
                }
                
                // Generate password
                $password = 'softpro@123'; // Default password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO students (name, email, phone, address, batch_id, training_center_id, password, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                $stmt->execute([$name, $email, $phone, $address, $batch_id, $training_center_id, $hashedPassword]);
                
                $message = 'Student added successfully';
                $messageType = 'success';
                break;
                
            case 'edit':
                $id = $_POST['id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $batch_id = $_POST['batch_id'] ?? null;
                $status = $_POST['status'] ?? 'active';
                
                // Validation
                if (empty($name) || empty($phone)) {
                    throw new Exception('Name and phone are required');
                }
                
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address');
                }
                
                // Check for duplicate phone (excluding current student)
                $stmt = $db->prepare("SELECT id FROM students WHERE phone = ? AND id != ?");
                $stmt->execute([$phone, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Phone number already exists');
                }
                
                $stmt = $db->prepare("
                    UPDATE students 
                    SET name = ?, email = ?, phone = ?, address = ?, batch_id = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([$name, $email, $phone, $address, $batch_id, $status, $id]);
                
                $message = 'Student updated successfully';
                $messageType = 'success';
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                
                // Soft delete
                $stmt = $db->prepare("UPDATE students SET status = 'deleted', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = 'Student deleted successfully';
                $messageType = 'success';
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $selected_ids = $_POST['selected_ids'] ?? [];
                
                if (empty($selected_ids)) {
                    throw new Exception('No students selected');
                }
                
                $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
                
                switch ($bulk_action) {
                    case 'activate':
                        $stmt = $db->prepare("UPDATE students SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($selected_ids);
                        $message = 'Students activated successfully';
                        break;
                        
                    case 'deactivate':
                        $stmt = $db->prepare("UPDATE students SET status = 'inactive' WHERE id IN ($placeholders)");
                        $stmt->execute($selected_ids);
                        $message = 'Students deactivated successfully';
                        break;
                        
                    case 'delete':
                        $stmt = $db->prepare("UPDATE students SET status = 'deleted' WHERE id IN ($placeholders)");
                        $stmt->execute($selected_ids);
                        $message = 'Students deleted successfully';
                        break;
                }
                
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Get training centers for dropdown
$trainingCenters = [];
try {
    if ($userRole === 'admin') {
        $stmt = $db->prepare("SELECT id, center_name FROM training_centers WHERE status = 'active' ORDER BY center_name");
        $stmt->execute();
        $trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching training centers: " . $e->getMessage());
}

// Get courses for dropdown
$courses = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM courses WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
}

// Additional CSS
$additionalCSS = "
.student-card {
    transition: transform 0.2s ease;
}

.student-card:hover {
    transform: translateY(-2px);
}

.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.bulk-actions {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: none;
}

.table-responsive {
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
    .btn-toolbar {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-toolbar .btn-group {
        width: 100%;
    }
    
    .btn-toolbar .btn {
        font-size: 0.875rem;
    }
}
";

// Include layout header
include '../includes/layout-enhanced.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Alerts -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Action Bar -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="btn-toolbar" role="toolbar">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="exportStudents()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload me-2"></i>Import
                    </button>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary" onclick="refreshStudents()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="d-flex gap-2">
                <select class="form-select" id="statusFilter" onchange="filterStudents()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <?php if ($userRole === 'admin'): ?>
                <select class="form-select" id="centerFilter" onchange="filterStudents()">
                    <option value="">All Centers</option>
                    <?php foreach ($trainingCenters as $center): ?>
                    <option value="<?php echo $center['id']; ?>"><?php echo htmlspecialchars($center['center_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bulk Actions Bar -->
    <div id="bulkActions" class="bulk-actions">
        <div class="row align-items-center">
            <div class="col-md-6">
                <span id="selectedCount">0</span> students selected
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-success" onclick="bulkAction('activate')">
                        <i class="fas fa-check me-1"></i>Activate
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" onclick="bulkAction('deactivate')">
                        <i class="fas fa-pause me-1"></i>Deactivate
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction('delete')">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Students Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="studentsTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Batch</th>
                            <?php if ($userRole === 'admin'): ?>
                            <th>Training Center</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Joined</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStudentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       pattern="[0-9]{10}" title="Please enter 10-digit phone number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if ($userRole === 'admin'): ?>
                            <div class="mb-3">
                                <label for="training_center_id" class="form-label">Training Center</label>
                                <select class="form-select" id="training_center_id" name="training_center_id" onchange="loadBatches()">
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $center): ?>
                                    <option value="<?php echo $center['id']; ?>"><?php echo htmlspecialchars($center['center_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="batch_id" class="form-label">Batch</label>
                                <select class="form-select" id="batch_id" name="batch_id">
                                    <option value="">Select Batch</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="1"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Default login credentials will be: Phone Number / softpro@123
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" 
                                       pattern="[0-9]{10}" title="Please enter 10-digit phone number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_batch_id" class="form-label">Batch</label>
                                <select class="form-select" id="edit_batch_id" name="batch_id">
                                    <option value="">Select Batch</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_address" class="form-label">Address</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="1"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Page scripts
$pageScripts = ['https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js'];

// Additional JavaScript
$additionalJS = "
let studentsTable;
let selectedStudents = [];

document.addEventListener('DOMContentLoaded', function() {
    loadStudents();
    loadBatches();
    
    // Initialize forms
    document.getElementById('addStudentForm').addEventListener('submit', handleAddStudent);
    document.getElementById('editStudentForm').addEventListener('submit', handleEditStudent);
});

function loadStudents() {
    const params = new URLSearchParams({
        ajax: 'get_students',
        status: document.getElementById('statusFilter').value,
        " . ($userRole === 'admin' ? "center_id: document.getElementById('centerFilter').value" : "") . "
    });
    
    fetch('?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderStudentsTable(data.data);
            } else {
                showToast('Error loading students: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading students', 'danger');
        });
}

function renderStudentsTable(students) {
    const tbody = document.getElementById('studentsTableBody');
    tbody.innerHTML = '';
    
    students.forEach(student => {
        const row = createStudentRow(student);
        tbody.appendChild(row);
    });
    
    // Reinitialize DataTable if it exists
    if (studentsTable) {
        studentsTable.destroy();
    }
    
    studentsTable = initDataTable('#studentsTable', {
        order: [[6, 'desc']], // Sort by joined date
        columnDefs: [
            { orderable: false, targets: [0, 7] } // Disable sorting for checkbox and actions
        ]
    });
}

function createStudentRow(student) {
    const row = document.createElement('tr');
    
    const statusBadgeClass = {
        'active': 'bg-success',
        'inactive': 'bg-warning',
        'deleted': 'bg-danger'
    }[student.status] || 'bg-secondary';
    
    row.innerHTML = \`
        <td>
            <input type=\"checkbox\" class=\"student-checkbox\" value=\"\${student.id}\" onchange=\"updateSelection()\">
        </td>
        <td>
            <div class=\"d-flex align-items-center\">
                <div class=\"avatar-circle bg-primary text-white me-2\" style=\"width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;\">
                    \${student.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div class=\"fw-semibold\">\${escapeHtml(student.name)}</div>
                    <small class=\"text-muted\">ID: \${student.id}</small>
                </div>
            </div>
        </td>
        <td>
            <div>\${escapeHtml(student.phone)}</div>
            \${student.email ? \`<small class=\"text-muted\">\${escapeHtml(student.email)}</small>\` : ''}
        </td>
        <td>
            \${student.batch_name ? \`
                <div>\${escapeHtml(student.batch_name)}</div>
                <small class=\"text-muted\">\${escapeHtml(student.course_name || '')}</small>
            \` : '<span class=\"text-muted\">Not Assigned</span>'}
        </td>
        " . ($userRole === 'admin' ? "\`
        <td>
            \${student.training_center_name ? escapeHtml(student.training_center_name) : '<span class=\"text-muted\">Not Assigned</span>'}
        </td>
        \`" : "") . "
        <td>
            <span class=\"badge \${statusBadgeClass} status-badge\">\${student.status}</span>
        </td>
        <td>
            \${formatDate(student.created_at)}
        </td>
        <td>
            <div class=\"btn-group btn-group-sm\">
                <button type=\"button\" class=\"btn btn-outline-primary\" onclick=\"editStudent(\${student.id})\" title=\"Edit\">
                    <i class=\"fas fa-edit\"></i>
                </button>
                <button type=\"button\" class=\"btn btn-outline-info\" onclick=\"viewStudent(\${student.id})\" title=\"View\">
                    <i class=\"fas fa-eye\"></i>
                </button>
                <button type=\"button\" class=\"btn btn-outline-danger\" onclick=\"deleteStudent(\${student.id})\" title=\"Delete\">
                    <i class=\"fas fa-trash\"></i>
                </button>
            </div>
        </td>
    \`;
    
    return row;
}

function loadBatches(trainingCenterId = null) {
    const params = new URLSearchParams({ ajax: 'get_batches' });
    if (trainingCenterId) {
        params.append('training_center_id', trainingCenterId);
    }
    
    fetch('?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateBatchDropdowns(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading batches:', error);
        });
}

function updateBatchDropdowns(batches) {
    const selects = ['batch_id', 'edit_batch_id'];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            const currentValue = select.value;
            select.innerHTML = '<option value=\"\">Select Batch</option>';
            
            batches.forEach(batch => {
                const option = document.createElement('option');
                option.value = batch.id;
                option.textContent = \`\${batch.batch_name} (\${batch.course_name})\`;
                if (batch.id == currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }
    });
}

function filterStudents() {
    loadStudents();
}

function refreshStudents() {
    showLoading('Refreshing students...');
    loadStudents();
    hideLoading();
    showToast('Students refreshed', 'success');
}

function editStudent(id) {
    fetch(\`?ajax=get_student&id=\${id}\`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.data;
                document.getElementById('edit_id').value = student.id;
                document.getElementById('edit_name').value = student.name;
                document.getElementById('edit_phone').value = student.phone;
                document.getElementById('edit_email').value = student.email || '';
                document.getElementById('edit_status').value = student.status;
                document.getElementById('edit_address').value = student.address || '';
                
                // Load batches and set selected batch
                loadBatches().then(() => {
                    if (student.batch_id) {
                        document.getElementById('edit_batch_id').value = student.batch_id;
                    }
                });
                
                new bootstrap.Modal(document.getElementById('editStudentModal')).show();
            } else {
                showToast('Error loading student details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading student details', 'danger');
        });
}

function viewStudent(id) {
    // Implement student details view
    showToast('Student details view coming soon', 'info');
}

function deleteStudent(id) {
    confirmAction(
        'Delete Student',
        'Are you sure you want to delete this student? This action cannot be undone.',
        'warning'
    ).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('csrf_token', window.csrfToken);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                showToast('Student deleted successfully', 'success');
                loadStudents();
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting student', 'danger');
            });
        }
    });
}

function handleAddStudent(event) {
    event.preventDefault();
    
    if (!validateForm(event.target)) {
        return;
    }
    
    const formData = new FormData(event.target);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
        event.target.reset();
        loadStudents();
        showToast('Student added successfully', 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding student', 'danger');
    });
}

function handleEditStudent(event) {
    event.preventDefault();
    
    if (!validateForm(event.target)) {
        return;
    }
    
    const formData = new FormData(event.target);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('editStudentModal')).hide();
        loadStudents();
        showToast('Student updated successfully', 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating student', 'danger');
    });
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelection();
}

function updateSelection() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    selectedStudents = Array.from(checkboxes).map(cb => cb.value);
    
    const count = selectedStudents.length;
    document.getElementById('selectedCount').textContent = count;
    
    const bulkActions = document.getElementById('bulkActions');
    if (count > 0) {
        bulkActions.style.display = 'block';
    } else {
        bulkActions.style.display = 'none';
    }
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.student-checkbox');
    const selectAll = document.getElementById('selectAll');
    
    if (count === 0) {
        selectAll.indeterminate = false;
        selectAll.checked = false;
    } else if (count === allCheckboxes.length) {
        selectAll.indeterminate = false;
        selectAll.checked = true;
    } else {
        selectAll.indeterminate = true;
        selectAll.checked = false;
    }
}

function bulkAction(action) {
    if (selectedStudents.length === 0) {
        showToast('No students selected', 'warning');
        return;
    }
    
    const actionMessages = {
        activate: 'activate selected students',
        deactivate: 'deactivate selected students',
        delete: 'delete selected students'
    };
    
    confirmAction(
        'Bulk Action',
        \`Are you sure you want to \${actionMessages[action]}?\`,
        'warning'
    ).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'bulk_action');
            formData.append('bulk_action', action);
            formData.append('csrf_token', window.csrfToken);
            
            selectedStudents.forEach(id => {
                formData.append('selected_ids[]', id);
            });
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                loadStudents();
                document.getElementById('selectAll').checked = false;
                updateSelection();
                showToast(\`Students \${action}d successfully\`, 'success');
            })
            .catch(error => {
                console.error('Error:', error);
                showToast(\`Error performing bulk \${action}\`, 'danger');
            });
        }
    });
}

function exportStudents() {
    // Get current table data
    const tableData = [];
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    
    // Add header
    tableData.push(['Name', 'Phone', 'Email', 'Batch', 'Training Center', 'Status', 'Joined']);
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 1) {
            const rowData = [
                cells[1].textContent.trim().split('\\n')[0], // Name
                cells[2].textContent.trim().split('\\n')[0], // Phone
                cells[2].textContent.trim().split('\\n')[1] || '', // Email
                cells[3].textContent.trim(), // Batch
                " . ($userRole === 'admin' ? "cells[4].textContent.trim(), // Training Center" : "'',") . "
                cells[" . ($userRole === 'admin' ? '5' : '4') . "].textContent.trim(), // Status
                cells[" . ($userRole === 'admin' ? '6' : '5') . "].textContent.trim()  // Joined
            ];
            tableData.push(rowData);
        }
    });
    
    // Create and download Excel file
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(tableData);
    XLSX.utils.book_append_sheet(wb, ws, 'Students');
    XLSX.writeFile(wb, \`students_export_\${new Date().toISOString().split('T')[0]}.xlsx\`);
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
";

// Include layout footer
include '../includes/layout-enhanced-footer.php';
?>

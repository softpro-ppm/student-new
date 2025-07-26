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

$user = $_SESSION['user'];
$userRole = $user['role'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_fee':
                if ($userRole !== 'admin' && $userRole !== 'training_partner') {
                    throw new Exception('Insufficient permissions');
                }
                
                $student_id = $_POST['student_id'] ?? 0;
                $amount = $_POST['amount'] ?? 0;
                $fee_type = $_POST['fee_type'] ?? 'course';
                $due_date = $_POST['due_date'] ?? null;
                $notes = $_POST['notes'] ?? '';
                
                if (!$student_id || !$amount) {
                    throw new Exception('Student and amount are required');
                }
                
                if ($amount <= 0) {
                    throw new Exception('Amount must be greater than zero');
                }
                
                // Insert fee record
                $stmt = $db->prepare("
                    INSERT INTO fees (student_id, amount, fee_type, due_date, notes, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$student_id, $amount, $fee_type, $due_date, $notes]);
                
                echo json_encode(['success' => true, 'message' => 'Fee record added successfully']);
                break;
                
            case 'mark_paid':
                if ($userRole !== 'admin' && $userRole !== 'training_partner') {
                    throw new Exception('Insufficient permissions');
                }
                
                $fee_id = $_POST['fee_id'] ?? 0;
                $receipt_number = $_POST['receipt_number'] ?? '';
                $paid_date = $_POST['paid_date'] ?? date('Y-m-d');
                
                if (!$fee_id) {
                    throw new Exception('Fee ID is required');
                }
                
                $stmt = $db->prepare("
                    UPDATE fees 
                    SET status = 'paid', paid_date = ?, receipt_number = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$paid_date, $receipt_number, $fee_id]);
                
                echo json_encode(['success' => true, 'message' => 'Fee marked as paid']);
                break;
                
            case 'approve_fee':
                if ($userRole !== 'admin') {
                    throw new Exception('Only administrators can approve fees');
                }
                
                $fee_id = $_POST['fee_id'] ?? 0;
                
                $stmt = $db->prepare("
                    UPDATE fees 
                    SET status = 'approved', approved_by = ?, approved_date = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id'], $fee_id]);
                
                echo json_encode(['success' => true, 'message' => 'Fee approved successfully']);
                break;
                
            case 'reject_fee':
                if ($userRole !== 'admin') {
                    throw new Exception('Only administrators can reject fees');
                }
                
                $fee_id = $_POST['fee_id'] ?? 0;
                $reason = $_POST['reason'] ?? '';
                
                $stmt = $db->prepare("
                    UPDATE fees 
                    SET status = 'rejected', notes = ?, approved_by = ?, approved_date = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$reason, $user['id'], $fee_id]);
                
                echo json_encode(['success' => true, 'message' => 'Fee rejected successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Fetch data based on user role
$whereClause = '';
$params = [];

if ($userRole === 'training_partner') {
    // Get fees for students in this training center
    $stmt = $db->prepare("SELECT training_center_id FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $trainingCenterId = $result['training_center_id'] ?? 0;
    
    if ($trainingCenterId) {
        $whereClause = 'WHERE s.training_center_id = ?';
        $params[] = $trainingCenterId;
    }
} elseif ($userRole === 'student') {
    // Get fees for this student only
    $stmt = $db->prepare("SELECT id FROM students WHERE email = ? OR phone = ?");
    $stmt->execute([$user['email'], $user['username']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentId = $result['id'] ?? 0;
    
    if ($studentId) {
        $whereClause = 'WHERE f.student_id = ?';
        $params[] = $studentId;
    }
}

// Fetch fees
$stmt = $db->prepare("
    SELECT f.*, s.name as student_name, s.enrollment_number, 
           c.name as course_name, tc.center_name as training_center_name,
           u.name as approved_by_name
    FROM fees f
    JOIN students s ON f.student_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN training_centers tc ON s.training_center_id = tc.id
    LEFT JOIN users u ON f.approved_by = u.id
    $whereClause
    ORDER BY f.created_at DESC
");
$stmt->execute($params);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get students for dropdown (if admin or training partner)
$students = [];
if ($userRole === 'admin' || $userRole === 'training_partner') {
    $studentWhereClause = '';
    $studentParams = [];
    
    if ($userRole === 'training_partner' && $trainingCenterId) {
        $studentWhereClause = 'WHERE training_center_id = ?';
        $studentParams[] = $trainingCenterId;
    }
    
    $stmt = $db->prepare("
        SELECT id, name, enrollment_number 
        FROM students 
        $studentWhereClause 
        ORDER BY name
    ");
    $stmt->execute($studentParams);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate statistics
$totalFees = array_sum(array_column($fees, 'amount'));
$paidFees = array_sum(array_map(function($fee) {
    return $fee['status'] === 'paid' || $fee['status'] === 'approved' ? $fee['amount'] : 0;
}, $fees));
$pendingFees = array_sum(array_map(function($fee) {
    return $fee['status'] === 'pending' ? $fee['amount'] : 0;
}, $fees));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees Management - Student Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
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
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 500;
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
                        <h4 class="text-white mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>SMS
                        </h4>
                        <small class="text-light opacity-75">Student Management</small>
                    </div>
                    <hr class="text-light">
                    <nav class="nav flex-column px-3">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        
                        <?php if ($userRole === 'admin'): ?>
                        <a class="nav-link" href="training-centers.php">
                            <i class="fas fa-building me-2"></i>Training Centers
                        </a>
                        <a class="nav-link" href="masters.php">
                            <i class="fas fa-cogs me-2"></i>Masters
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                        <a class="nav-link" href="students.php">
                            <i class="fas fa-users me-2"></i>Students
                        </a>
                        <a class="nav-link" href="batches.php">
                            <i class="fas fa-layer-group me-2"></i>Batches
                        </a>
                        <a class="nav-link" href="assessments.php">
                            <i class="fas fa-clipboard-check me-2"></i>Assessments
                        </a>
                        <a class="nav-link active" href="fees.php">
                            <i class="fas fa-money-bill me-2"></i>Fees
                        </a>
                        <?php endif; ?>
                        
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        
                        <hr class="text-light">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h2 class="mb-1"><i class="fas fa-money-bill me-2 text-primary"></i>Fees Management</h2>
                                <p class="text-muted mb-0">Manage student fee records and payments</p>
                            </div>
                            <div class="col-auto">
                                <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                                    <i class="fas fa-plus me-1"></i>Add Fee Record
                                </button>
                                <?php endif; ?>
                            </div>
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
                                Total Fees
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($totalFees, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calculator fa-2x text-gray-300"></i>
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
                                Paid Fees
                            </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($paidFees, 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                        Pending Fees
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($pendingFees, 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                        Collection Rate
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $totalFees > 0 ? number_format(($paidFees / $totalFees) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fees Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Fee Records</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="feesTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <?php if ($userRole !== 'student'): ?>
                                    <th>Student</th>
                                    <?php endif; ?>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Paid Date</th>
                                    <th>Receipt No.</th>
                                    <th>Created</th>
                                    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <?php if ($userRole !== 'student'): ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fee['student_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($fee['enrollment_number']); ?></small>
                                    </td>
                                    <?php endif; ?>
                                    <td><strong>₹<?php echo number_format($fee['amount'], 2); ?></strong></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($fee['fee_type']); ?></span></td>
                                    <td>
                                        <?php if ($fee['due_date']): ?>
                                            <?php 
                                            $dueDate = new DateTime($fee['due_date']);
                                            $today = new DateTime();
                                            $isOverdue = $dueDate < $today && $fee['status'] === 'pending';
                                            ?>
                                            <span class="<?php echo $isOverdue ? 'text-danger' : ''; ?>">
                                                <?php echo $dueDate->format('M d, Y'); ?>
                                                <?php if ($isOverdue): ?>
                                                    <i class="fas fa-exclamation-triangle ms-1"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($fee['status']) {
                                            case 'paid': $statusClass = 'bg-success'; break;
                                            case 'approved': $statusClass = 'bg-info'; break;
                                            case 'pending': $statusClass = 'bg-warning'; break;
                                            case 'rejected': $statusClass = 'bg-danger'; break;
                                            default: $statusClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($fee['status']); ?></span>
                                    </td>
                                    <td><?php echo $fee['paid_date'] ? date('M d, Y', strtotime($fee['paid_date'])) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($fee['receipt_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($fee['created_at'])); ?></td>
                                    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($fee['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" onclick="markPaid(<?php echo $fee['id']; ?>)">
                                                    <i class="fas fa-check"></i> Mark Paid
                                                </button>
                                                <?php if ($userRole === 'admin'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="approveFee(<?php echo $fee['id']; ?>)">
                                                    <i class="fas fa-thumbs-up"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="rejectFee(<?php echo $fee['id']; ?>)">
                                                    <i class="fas fa-thumbs-down"></i> Reject
                                                </button>
                                                <?php endif; ?>
                                            <?php elseif ($fee['status'] === 'paid' && $userRole === 'admin'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="approveFee(<?php echo $fee['id']; ?>)">
                                                    <i class="fas fa-thumbs-up"></i> Approve
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

<?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
<!-- Add Fee Modal -->
<div class="modal fade" id="addFeeModal" tabindex="-1" aria-labelledby="addFeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFeeModalLabel">Add Fee Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addFeeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student *</label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['name'] . ' (' . $student['enrollment_number'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fee_type" class="form-label">Fee Type</label>
                                <select class="form-select" id="fee_type" name="fee_type">
                                    <option value="registration">Registration</option>
                                    <option value="course" selected>Course</option>
                                    <option value="exam">Exam</option>
                                    <option value="emi">EMI</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Fee Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1" aria-labelledby="markPaidModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markPaidModalLabel">Mark Fee as Paid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="markPaidForm">
                <input type="hidden" id="paid_fee_id" name="fee_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="paid_date" class="form-label">Paid Date *</label>
                        <input type="date" class="form-control" id="paid_date" name="paid_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="receipt_number" class="form-label">Receipt Number</label>
                        <input type="text" class="form-control" id="receipt_number" name="receipt_number" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

            </div> <!-- End main-content -->
        </div> <!-- End col-md-9 col-lg-10 -->
    </div> <!-- End row -->
</div> <!-- End container-fluid -->

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#feesTable').DataTable({
        "pageLength": 25,
        "order": [[ <?php echo $userRole !== 'student' ? '7' : '6'; ?>, "desc" ]], // Sort by created date
        "columnDefs": [
            { "orderable": false, "targets": -1 } // Disable sorting on Actions column
        ]
    });
    
    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
    // Add Fee Form
    $('#addFeeForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add_fee');
        
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
                alert('An error occurred while adding the fee record.');
            }
        });
    });
    
    // Mark Paid Form
    $('#markPaidForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'mark_paid');
        
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
                alert('An error occurred while marking the fee as paid.');
            }
        });
    });
    <?php endif; ?>
});

<?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
function markPaid(feeId) {
    $('#paid_fee_id').val(feeId);
    $('#markPaidModal').modal('show');
}

function approveFee(feeId) {
    if (confirm('Are you sure you want to approve this fee payment?')) {
        const formData = new FormData();
        formData.append('action', 'approve_fee');
        formData.append('fee_id', feeId);
        
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
                alert('An error occurred while approving the fee.');
            }
        });
    }
}

function rejectFee(feeId) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason !== null && reason.trim() !== '') {
        const formData = new FormData();
        formData.append('action', 'reject_fee');
        formData.append('fee_id', feeId);
        formData.append('reason', reason);
        
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
                alert('An error occurred while rejecting the fee.');
            }
        });
    }
}
<?php endif; ?>
</script>

</body>
</html>

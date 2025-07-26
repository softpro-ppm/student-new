<?php
// Fees Management - v2.0
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
        
        if ($action === 'add_payment') {
            // Add new payment
            $student_id = intval($_POST['student_id'] ?? 0);
            $payment_type = $_POST['payment_type'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_mode = $_POST['payment_mode'] ?? '';
            $transaction_id = trim($_POST['transaction_id'] ?? '');
            $payment_date = $_POST['payment_date'] ?? '';
            $remarks = trim($_POST['remarks'] ?? '');
            
            if ($student_id && $payment_type && $amount > 0 && $payment_mode && $payment_date) {
                // Generate receipt number
                $receipt_number = 'RCP' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("
                    INSERT INTO fee_payments (
                        student_id, receipt_number, payment_type, amount, payment_mode,
                        transaction_id, payment_date, remarks, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
                ");
                
                $stmt->execute([
                    $student_id, $receipt_number, $payment_type, $amount, $payment_mode,
                    $transaction_id, $payment_date, $remarks
                ]);
                
                $message = "Payment recorded successfully! Receipt Number: $receipt_number";
                $messageType = "success";
            } else {
                $message = "Please fill all required fields.";
                $messageType = "error";
            }
        }
        
        if ($action === 'edit_payment') {
            // Edit payment
            $id = intval($_POST['id'] ?? 0);
            $payment_type = $_POST['payment_type'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_mode = $_POST['payment_mode'] ?? '';
            $transaction_id = trim($_POST['transaction_id'] ?? '');
            $payment_date = $_POST['payment_date'] ?? '';
            $remarks = trim($_POST['remarks'] ?? '');
            $status = $_POST['status'] ?? 'completed';
            
            if ($id && $payment_type && $amount > 0 && $payment_mode) {
                $stmt = $conn->prepare("
                    UPDATE fee_payments SET 
                        payment_type = ?, amount = ?, payment_mode = ?, transaction_id = ?, 
                        payment_date = ?, remarks = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $payment_type, $amount, $payment_mode, $transaction_id, 
                    $payment_date, $remarks, $status, $id
                ]);
                
                $message = "Payment updated successfully!";
                $messageType = "success";
            }
        }
        
        if ($action === 'delete_payment') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("UPDATE fee_payments SET status = 'cancelled', deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = "Payment cancelled successfully!";
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
$payment_type_filter = $_GET['payment_type'] ?? '';
$payment_mode_filter = $_GET['payment_mode'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    $conn = getV2Connection();
    
    // Get fee breakdown summary
    $summaryQuery = "
        SELECT 
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN sb.batch_id IS NOT NULL THEN c.course_fee ELSE 0 END) as total_course_fees,
            SUM(fp.amount) as total_collected,
            SUM(CASE WHEN sb.batch_id IS NOT NULL THEN c.course_fee ELSE 0 END) - COALESCE(SUM(fp.amount), 0) as total_pending
        FROM students s
        LEFT JOIN batch_students sb ON s.id = sb.student_id AND sb.status = 'active'
        LEFT JOIN batches b ON sb.batch_id = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN fee_payments fp ON s.id = fp.student_id AND fp.status = 'completed'
        WHERE s.status != 'deleted'
    ";
    $summaryStmt = $conn->query($summaryQuery);
    $summary = $summaryStmt->fetch();
    
    // Build search query for payments
    $whereConditions = ["fp.status != 'cancelled'"];
    $params = [];
    
    if ($search) {
        $whereConditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.enrollment_number LIKE ? OR fp.receipt_number LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($payment_type_filter) {
        $whereConditions[] = "fp.payment_type = ?";
        $params[] = $payment_type_filter;
    }
    
    if ($payment_mode_filter) {
        $whereConditions[] = "fp.payment_mode = ?";
        $params[] = $payment_mode_filter;
    }
    
    if ($date_from) {
        $whereConditions[] = "fp.payment_date >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $whereConditions[] = "fp.payment_date <= ?";
        $params[] = $date_to;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) 
        FROM fee_payments fp 
        LEFT JOIN students s ON fp.student_id = s.id 
        WHERE $whereClause
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalPayments = $countStmt->fetchColumn();
    $totalPages = ceil($totalPayments / $limit);
    
    // Get payments with pagination
    $sql = "
        SELECT fp.*, s.first_name, s.last_name, s.enrollment_number,
               c.course_name, c.course_fee
        FROM fee_payments fp 
        LEFT JOIN students s ON fp.student_id = s.id 
        LEFT JOIN batch_students sb ON s.id = sb.student_id AND sb.status = 'active'
        LEFT JOIN batches b ON sb.batch_id = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        WHERE $whereClause 
        ORDER BY fp.payment_date DESC, fp.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Get students for dropdown
    $studentStmt = $conn->query("
        SELECT s.id, s.first_name, s.last_name, s.enrollment_number,
               c.course_fee, c.course_name
        FROM students s 
        LEFT JOIN batch_students sb ON s.id = sb.student_id AND sb.status = 'active'
        LEFT JOIN batches b ON sb.batch_id = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        WHERE s.status != 'deleted' 
        ORDER BY s.first_name, s.last_name
    ");
    $students = $studentStmt->fetchAll();
    
    // Get fee breakdown for students
    $feeBreakdownQuery = "
        SELECT 
            s.id, s.first_name, s.last_name, s.enrollment_number,
            c.course_fee,
            COALESCE(SUM(CASE WHEN fp.payment_type = 'registration' THEN fp.amount ELSE 0 END), 0) as registration_paid,
            COALESCE(SUM(CASE WHEN fp.payment_type = 'course' THEN fp.amount ELSE 0 END), 0) as course_paid,
            COALESCE(SUM(fp.amount), 0) as total_paid
        FROM students s
        LEFT JOIN batch_students sb ON s.id = sb.student_id AND sb.status = 'active'
        LEFT JOIN batches b ON sb.batch_id = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN fee_payments fp ON s.id = fp.student_id AND fp.status = 'completed'
        WHERE s.status != 'deleted'
        GROUP BY s.id
        ORDER BY s.first_name, s.last_name
        LIMIT 20
    ";
    $feeBreakdownStmt = $conn->query($feeBreakdownQuery);
    $feeBreakdown = $feeBreakdownStmt->fetchAll();
    
} catch (Exception $e) {
    $payments = [];
    $students = [];
    $feeBreakdown = [];
    $summary = ['total_students' => 0, 'total_course_fees' => 0, 'total_collected' => 0, 'total_pending' => 0];
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
            <h4 class="mb-0">Fees Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard-v2.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Fees</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-rounded" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="fas fa-plus me-2"></i>Add Payment
            </button>
            <button class="btn btn-info btn-rounded" onclick="showFeeBreakdown()">
                <i class="fas fa-list me-2"></i>Fee Breakdown
            </button>
        </div>
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

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-primary">
                    <i class="fas fa-users"></i>
                </div>
                <h4><?= $summary['total_students'] ?></h4>
                <p class="text-muted mb-0">Total Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-info">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <h4>₹<?= number_format($summary['total_course_fees']) ?></h4>
                <p class="text-muted mb-0">Total Course Fees</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4>₹<?= number_format($summary['total_collected']) ?></h4>
                <p class="text-muted mb-0">Total Collected</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <h4>₹<?= number_format($summary['total_pending']) ?></h4>
                <p class="text-muted mb-0">Pending Dues</p>
            </div>
        </div>
    </div>

    <!-- Fee Breakdown Section -->
    <div class="card mb-4" id="feeBreakdownCard" style="display: none;">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Student-wise Fee Breakdown</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student Details</th>
                            <th>Course Fee</th>
                            <th>Registration Fee</th>
                            <th>Total Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeBreakdown as $student): ?>
                            <?php 
                            $registrationFee = 500; // Fixed registration fee
                            $totalFee = ($student['course_fee'] ?? 0) + $registrationFee;
                            $balance = $totalFee - $student['total_paid'];
                            ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($student['enrollment_number']) ?></small>
                                    </div>
                                </td>
                                <td>₹<?= number_format($student['course_fee'] ?? 0) ?></td>
                                <td>
                                    <div>
                                        ₹<?= number_format($registrationFee) ?>
                                        <br>
                                        <small class="text-success">Paid: ₹<?= number_format($student['registration_paid']) ?></small>
                                    </div>
                                </td>
                                <td>₹<?= number_format($student['total_paid']) ?></td>
                                <td>
                                    <strong class="<?= $balance <= 0 ? 'text-success' : 'text-danger' ?>">
                                        ₹<?= number_format($balance) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($balance <= 0): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($student['total_paid'] > 0): ?>
                                        <span class="badge bg-warning">Partial</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search Payments</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Student name, receipt...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Type</label>
                    <select class="form-select" name="payment_type">
                        <option value="">All Types</option>
                        <option value="registration" <?= $payment_type_filter === 'registration' ? 'selected' : '' ?>>Registration</option>
                        <option value="course" <?= $payment_type_filter === 'course' ? 'selected' : '' ?>>Course Fee</option>
                        <option value="exam" <?= $payment_type_filter === 'exam' ? 'selected' : '' ?>>Exam Fee</option>
                        <option value="certificate" <?= $payment_type_filter === 'certificate' ? 'selected' : '' ?>>Certificate</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Mode</label>
                    <select class="form-select" name="payment_mode">
                        <option value="">All Modes</option>
                        <option value="cash" <?= $payment_mode_filter === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="online" <?= $payment_mode_filter === 'online' ? 'selected' : '' ?>>Online</option>
                        <option value="upi" <?= $payment_mode_filter === 'upi' ? 'selected' : '' ?>>UPI</option>
                        <option value="cheque" <?= $payment_mode_filter === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                        <option value="dd" <?= $payment_mode_filter === 'dd' ? 'selected' : '' ?>>DD</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payment History</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Student Details</th>
                            <th>Payment Type</th>
                            <th>Amount</th>
                            <th>Payment Mode</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No payments found. Record your first payment to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($payment['receipt_number']) ?></strong>
                                        <?php if ($payment['transaction_id']): ?>
                                            <br><small class="text-muted">TXN: <?= htmlspecialchars($payment['transaction_id']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($payment['enrollment_number']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?= ucfirst($payment['payment_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-success">₹<?= number_format($payment['amount']) ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $modeColors = [
                                            'cash' => 'success',
                                            'online' => 'primary',
                                            'upi' => 'info',
                                            'cheque' => 'warning',
                                            'dd' => 'secondary'
                                        ];
                                        $modeColor = $modeColors[$payment['payment_mode']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $modeColor ?>">
                                            <?= strtoupper($payment['payment_mode']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M Y', strtotime($payment['payment_date'])) ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'completed' => 'success',
                                            'pending' => 'warning',
                                            'cancelled' => 'danger'
                                        ];
                                        $statusColor = $statusColors[$payment['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <?= ucfirst($payment['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editPayment(<?= htmlspecialchars(json_encode($payment)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="printReceipt(<?= $payment['id'] ?>)">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="cancelPayment(<?= $payment['id'] ?>, '<?= htmlspecialchars($payment['receipt_number']) ?>')">
                                                <i class="fas fa-times"></i>
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
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&payment_type=<?= urlencode($payment_type_filter) ?>&payment_mode=<?= urlencode($payment_mode_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&payment_type=<?= urlencode($payment_type_filter) ?>&payment_mode=<?= urlencode($payment_mode_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&payment_type=<?= urlencode($payment_type_filter) ?>&payment_mode=<?= urlencode($payment_mode_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Showing <?= min($totalPayments, $offset + 1) ?> to <?= min($totalPayments, $offset + $limit) ?> of <?= $totalPayments ?> payments
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add_payment">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Student *</label>
                                <select class="form-select" name="student_id" required onchange="updateCourseInfo(this)">
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['id'] ?>" data-course-fee="<?= $student['course_fee'] ?>" data-course="<?= htmlspecialchars($student['course_name']) ?>">
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['enrollment_number'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Type *</label>
                                <select class="form-select" name="payment_type" required onchange="updateAmount(this)">
                                    <option value="">Select Type</option>
                                    <option value="registration" data-amount="500">Registration Fee (₹500)</option>
                                    <option value="course" data-amount="">Course Fee</option>
                                    <option value="exam" data-amount="200">Exam Fee (₹200)</option>
                                    <option value="certificate" data-amount="100">Certificate Fee (₹100)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Amount (₹) *</label>
                                <input type="number" class="form-control" name="amount" id="payment_amount" min="1" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Payment Mode *</label>
                                <select class="form-select" name="payment_mode" required>
                                    <option value="">Select Mode</option>
                                    <option value="cash">Cash</option>
                                    <option value="online">Online Transfer</option>
                                    <option value="upi">UPI</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="dd">Demand Draft</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Payment Date *</label>
                                <input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction ID / Reference</label>
                        <input type="text" class="form-control" name="transaction_id" placeholder="For digital payments">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editPaymentForm">
                <input type="hidden" name="action" value="edit_payment">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Type *</label>
                                <select class="form-select" name="payment_type" id="edit_payment_type" required>
                                    <option value="registration">Registration Fee</option>
                                    <option value="course">Course Fee</option>
                                    <option value="exam">Exam Fee</option>
                                    <option value="certificate">Certificate Fee</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Amount (₹) *</label>
                                <input type="number" class="form-control" name="amount" id="edit_amount" min="1" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Payment Mode *</label>
                                <select class="form-select" name="payment_mode" id="edit_payment_mode" required>
                                    <option value="cash">Cash</option>
                                    <option value="online">Online Transfer</option>
                                    <option value="upi">UPI</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="dd">Demand Draft</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Payment Date *</label>
                                <input type="date" class="form-control" name="payment_date" id="edit_payment_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction ID / Reference</label>
                        <input type="text" class="form-control" name="transaction_id" id="edit_transaction_id">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" id="edit_remarks" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateCourseInfo(select) {
    const option = select.selectedOptions[0];
    if (option) {
        const courseFee = option.getAttribute('data-course-fee');
        // Store course fee for later use
        select.setAttribute('data-selected-course-fee', courseFee);
    }
}

function updateAmount(select) {
    const paymentType = select.value;
    const amountInput = document.getElementById('payment_amount');
    const studentSelect = document.querySelector('select[name="student_id"]');
    
    if (paymentType === 'registration') {
        amountInput.value = 500;
    } else if (paymentType === 'course' && studentSelect) {
        const courseFee = studentSelect.getAttribute('data-selected-course-fee');
        if (courseFee) {
            amountInput.value = courseFee;
        }
    } else if (paymentType === 'exam') {
        amountInput.value = 200;
    } else if (paymentType === 'certificate') {
        amountInput.value = 100;
    } else {
        amountInput.value = '';
    }
}

function editPayment(payment) {
    document.getElementById('edit_id').value = payment.id;
    document.getElementById('edit_payment_type').value = payment.payment_type;
    document.getElementById('edit_amount').value = payment.amount;
    document.getElementById('edit_payment_mode').value = payment.payment_mode;
    document.getElementById('edit_payment_date').value = payment.payment_date;
    document.getElementById('edit_transaction_id').value = payment.transaction_id || '';
    document.getElementById('edit_remarks').value = payment.remarks || '';
    document.getElementById('edit_status').value = payment.status;
    
    new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
}

function cancelPayment(id, receiptNumber) {
    if (confirm(`Are you sure you want to cancel payment "${receiptNumber}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_payment">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function printReceipt(paymentId) {
    window.open(`print-receipt.php?payment_id=${paymentId}`, '_blank');
}

function showFeeBreakdown() {
    const card = document.getElementById('feeBreakdownCard');
    if (card.style.display === 'none') {
        card.style.display = 'block';
    } else {
        card.style.display = 'none';
    }
}

// Set max date to today
document.addEventListener('DOMContentLoaded', function() {
    const dateInputs = document.querySelectorAll('input[name="payment_date"]');
    const today = new Date().toISOString().split('T')[0];
    dateInputs.forEach(function(input) {
        input.max = today;
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Fees Management', 'fees', $content);
?>

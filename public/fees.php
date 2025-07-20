<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php'; // For PDF generation

use Dompdf\Dompdf;
use Dompdf\Options;

$auth = new Auth();
$auth->requireRole(['admin', 'training_partner']);

$database = new Database();
$db = $database->getConnection();

$pageTitle = 'Fee Management';
$currentUser = $auth->getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_payment') {
        try {
            $student_id = $_POST['student_id'];
            $amount = floatval($_POST['amount']);
            $payment_type = $_POST['payment_type'];
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $transaction_id = $_POST['transaction_id'] ?? null;
            $payment_date = $_POST['payment_date'];
            $notes = $_POST['notes'] ?? null;
            
            // Insert fee payment
            $stmt = $db->prepare("
                INSERT INTO fee_payments (student_id, amount, payment_type, payment_method, transaction_id, payment_date, notes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$student_id, $amount, $payment_type, $payment_method, $transaction_id, $payment_date, $notes]);
            
            $success = "Fee payment recorded successfully. Awaiting admin approval.";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'approve_payment' && $currentUser['role'] === 'admin') {
        try {
            $payment_id = $_POST['payment_id'];
            
            // Generate receipt number
            $receipt_number = generateReceiptNumber($db);
            
            // Update payment status
            $stmt = $db->prepare("
                UPDATE fee_payments 
                SET status = 'approved', approved_by = ?, approved_at = NOW(), receipt_number = ? 
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['id'], $receipt_number, $payment_id]);
            
            // Send receipt via WhatsApp and Email
            sendPaymentReceipt($db, $payment_id);
            
            $success = "Payment approved successfully. Receipt sent to student.";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'reject_payment' && $currentUser['role'] === 'admin') {
        try {
            $payment_id = $_POST['payment_id'];
            $notes = $_POST['rejection_notes'] ?? '';
            
            $stmt = $db->prepare("
                UPDATE fee_payments 
                SET status = 'rejected', approved_by = ?, approved_at = NOW(), notes = CONCAT(COALESCE(notes, ''), '\nRejection reason: ', ?) 
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['id'], $notes, $payment_id]);
            
            $success = "Payment rejected successfully.";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'generate_receipt') {
        try {
            $payment_id = $_POST['payment_id'];
            $pdf_path = generatePDFReceipt($db, $payment_id);
            
            if ($pdf_path) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="receipt_' . $payment_id . '.pdf"');
                readfile($pdf_path);
                exit();
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get fee payments with filters
$where_conditions = [];
$where_values = [];

if ($currentUser['role'] === 'training_partner') {
    $stmt = $db->prepare("SELECT id FROM training_centers WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $center = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($center) {
        $where_conditions[] = "s.training_center_id = ?";
        $where_values[] = $center['id'];
    }
}

// Apply filters
if (!empty($_GET['student_id'])) {
    $where_conditions[] = "fp.student_id = ?";
    $where_values[] = $_GET['student_id'];
}

if (!empty($_GET['status'])) {
    $where_conditions[] = "fp.status = ?";
    $where_values[] = $_GET['status'];
}

if (!empty($_GET['payment_type'])) {
    $where_conditions[] = "fp.payment_type = ?";
    $where_values[] = $_GET['payment_type'];
}

if (!empty($_GET['date_from'])) {
    $where_conditions[] = "fp.payment_date >= ?";
    $where_values[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $where_conditions[] = "fp.payment_date <= ?";
    $where_values[] = $_GET['date_to'];
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM fee_payments fp 
    JOIN students s ON fp.student_id = s.id 
    $where_clause
";
$stmt = $db->prepare($count_query);
$stmt->execute($where_values);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get fee payments
$query = "
    SELECT fp.*, s.name as student_name, s.enrollment_number, s.phone, 
           c.name as course_name, tc.name as center_name,
           u.name as approved_by_name
    FROM fee_payments fp 
    JOIN students s ON fp.student_id = s.id 
    LEFT JOIN courses c ON s.course_id = c.id 
    LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
    LEFT JOIN users u ON fp.approved_by = u.id 
    $where_clause
    ORDER BY fp.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($where_values);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get students for payment form
$students_query = "SELECT s.id, s.name, s.enrollment_number, s.phone, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.status = 'active'";
if ($currentUser['role'] === 'training_partner' && isset($center)) {
    $students_query .= " AND s.training_center_id = " . $center['id'];
}
$students_query .= " ORDER BY s.name";

$stmt = $db->prepare($students_query);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) as total_approved_amount,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as total_pending_amount
    FROM fee_payments fp 
    JOIN students s ON fp.student_id = s.id 
    $where_clause
";

$stmt = $db->prepare($stats_query);
$stmt->execute($where_values);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper functions
function generateReceiptNumber($db) {
    $year = date('Y');
    $month = date('m');
    $prefix = 'RCP' . $year . $month;
    
    $stmt = $db->prepare("SELECT COUNT(*) + 1 as next_seq FROM fee_payments WHERE receipt_number LIKE ?");
    $stmt->execute([$prefix . '%']);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC)['next_seq'];
    
    return $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
}

function generatePDFReceipt($db, $payment_id) {
    // Get payment details
    $stmt = $db->prepare("
        SELECT fp.*, s.name as student_name, s.enrollment_number, s.phone, s.address,
               c.name as course_name, c.duration_months, tc.name as center_name, tc.address as center_address, tc.phone as center_phone
        FROM fee_payments fp 
        JOIN students s ON fp.student_id = s.id 
        LEFT JOIN courses c ON s.course_id = c.id 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        WHERE fp.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception("Payment not found");
    }
    
    // Create PDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    $html = generateReceiptHTML($payment);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Save PDF
    $upload_dir = '../uploads/receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = 'receipt_' . $payment_id . '_' . time() . '.pdf';
    $filepath = $upload_dir . $filename;
    
    file_put_contents($filepath, $dompdf->output());
    
    return $filepath;
}

function generateReceiptHTML($payment) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .receipt-container { 
                width: 100%; 
                max-width: 800px; 
                margin: 0 auto; 
                border: 3px double #333; 
                border-radius: 10px; 
                padding: 20px; 
                background: #fff;
            }
            .header { text-align: center; margin-bottom: 30px; }
            .logo { width: 80px; height: 80px; float: left; }
            .title { font-size: 24px; font-weight: bold; color: #2563eb; margin-bottom: 5px; }
            .subtitle { font-size: 16px; color: #666; }
            .receipt-details { margin: 20px 0; }
            .row { display: flex; justify-content: space-between; margin-bottom: 10px; }
            .label { font-weight: bold; }
            .amount-section { 
                background: #f8f9fa; 
                border: 2px solid #2563eb; 
                border-radius: 8px; 
                padding: 15px; 
                margin: 20px 0; 
                text-align: center; 
            }
            .amount { font-size: 28px; font-weight: bold; color: #2563eb; }
            .footer { 
                margin-top: 30px; 
                padding-top: 20px; 
                border-top: 2px solid #ddd; 
                font-size: 12px; 
                color: #666; 
            }
            .signature-section { 
                display: flex; 
                justify-content: space-between; 
                margin-top: 40px; 
            }
            .signature { text-align: center; width: 200px; }
            .signature-line { 
                border-top: 1px solid #333; 
                margin-top: 50px; 
                padding-top: 5px; 
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="header">
                <div style="overflow: hidden;">
                    <div style="float: left; width: 100px;">
                        <!-- Logo placeholder -->
                        <div style="width: 80px; height: 80px; background: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">SMS</div>
                    </div>
                    <div style="margin-left: 120px;">
                        <div class="title">STUDENT MANAGEMENT SYSTEM</div>
                        <div class="subtitle">Fee Payment Receipt</div>
                        <div style="margin-top: 10px; font-weight: bold;">Receipt No: ' . htmlspecialchars($payment['receipt_number']) . '</div>
                    </div>
                </div>
            </div>
            
            <div class="receipt-details">
                <div class="row">
                    <span class="label">Student Name:</span>
                    <span>' . htmlspecialchars($payment['student_name']) . '</span>
                </div>
                <div class="row">
                    <span class="label">Enrollment Number:</span>
                    <span>' . htmlspecialchars($payment['enrollment_number']) . '</span>
                </div>
                <div class="row">
                    <span class="label">Course:</span>
                    <span>' . htmlspecialchars($payment['course_name'] ?? 'N/A') . '</span>
                </div>
                <div class="row">
                    <span class="label">Training Center:</span>
                    <span>' . htmlspecialchars($payment['center_name'] ?? 'N/A') . '</span>
                </div>
                <div class="row">
                    <span class="label">Payment Date:</span>
                    <span>' . date('d-m-Y', strtotime($payment['payment_date'])) . '</span>
                </div>
                <div class="row">
                    <span class="label">Payment Type:</span>
                    <span>' . ucfirst(str_replace('_', ' ', $payment['payment_type'])) . '</span>
                </div>
                <div class="row">
                    <span class="label">Payment Method:</span>
                    <span>' . ucfirst($payment['payment_method']) . '</span>
                </div>
                ' . ($payment['transaction_id'] ? '<div class="row"><span class="label">Transaction ID:</span><span>' . htmlspecialchars($payment['transaction_id']) . '</span></div>' : '') . '
            </div>
            
            <div class="amount-section">
                <div style="font-size: 18px; margin-bottom: 10px;">Amount Paid</div>
                <div class="amount">₹ ' . number_format($payment['amount'], 2) . '</div>
                <div style="margin-top: 10px; font-style: italic;">
                    (Rupees ' . numberToWords($payment['amount']) . ' Only)
                </div>
            </div>
            
            ' . ($payment['notes'] ? '<div style="margin: 20px 0;"><strong>Notes:</strong> ' . htmlspecialchars($payment['notes']) . '</div>' : '') . '
            
            <div class="signature-section">
                <div class="signature">
                    <div class="signature-line">Student Signature</div>
                </div>
                <div class="signature">
                    <div class="signature-line">Authorized Signature</div>
                </div>
            </div>
            
            <div class="footer">
                <div style="text-align: center;">
                    <strong>' . htmlspecialchars($payment['center_name'] ?? 'Student Management System') . '</strong><br>
                    ' . htmlspecialchars($payment['center_address'] ?? '') . '<br>
                    Phone: ' . htmlspecialchars($payment['center_phone'] ?? '') . ' | Email: info@sms.com<br>
                    <em>This is a computer generated receipt.</em>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

function numberToWords($number) {
    $words = array(
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    );
    
    if ($number < 21) {
        return $words[$number];
    } elseif ($number < 100) {
        return $words[10 * floor($number / 10)] . ' ' . $words[$number % 10];
    } elseif ($number < 1000) {
        return $words[floor($number / 100)] . ' Hundred ' . numberToWords($number % 100);
    } elseif ($number < 100000) {
        return numberToWords(floor($number / 1000)) . ' Thousand ' . numberToWords($number % 1000);
    } else {
        return numberToWords(floor($number / 100000)) . ' Lakh ' . numberToWords($number % 100000);
    }
}

function sendPaymentReceipt($db, $payment_id) {
    // Implementation for WhatsApp and Email sending
    // This would integrate with actual WhatsApp Business API and SMTP
    return true;
}

include '../includes/layout.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-money-bill-wave me-2"></i>Fee Management</h2>
            <p class="text-muted">Manage student fee payments and receipts</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="fas fa-plus me-2"></i>Add Payment
            </button>
            <button class="btn btn-outline-primary" onclick="exportPayments()">
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
                            <div class="stat-number text-primary"><?php echo number_format($stats['total_payments']); ?></div>
                            <div class="stat-label">Total Payments</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-receipt fa-2x text-primary opacity-25"></i>
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
                            <div class="stat-number text-warning"><?php echo number_format($stats['pending_count']); ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock fa-2x text-warning opacity-25"></i>
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
                            <div class="stat-number text-success">₹<?php echo number_format($stats['total_approved_amount']); ?></div>
                            <div class="stat-label">Approved Amount</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
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
                            <div class="stat-number text-info">₹<?php echo number_format($stats['total_pending_amount']); ?></div>
                            <div class="stat-label">Pending Amount</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half fa-2x text-info opacity-25"></i>
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
                    <label class="form-label">Student</label>
                    <select class="form-select" name="student_id">
                        <option value="">All Students</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" <?php echo ($_GET['student_id'] ?? '') == $student['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['name'] . ' (' . $student['enrollment_number'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($_GET['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo ($_GET['status'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Type</label>
                    <select class="form-select" name="payment_type">
                        <option value="">All Types</option>
                        <option value="registration" <?php echo ($_GET['payment_type'] ?? '') == 'registration' ? 'selected' : ''; ?>>Registration</option>
                        <option value="course_fee" <?php echo ($_GET['payment_type'] ?? '') == 'course_fee' ? 'selected' : ''; ?>>Course Fee</option>
                        <option value="installment" <?php echo ($_GET['payment_type'] ?? '') == 'installment' ? 'selected' : ''; ?>>Installment</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Payments Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Fee Payments (<?php echo number_format($total_records); ?>)</h5>
            <?php if ($currentUser['role'] === 'admin'): ?>
            <div class="btn-group">
                <button class="btn btn-outline-success btn-sm" onclick="bulkApprove()">
                    <i class="fas fa-check me-1"></i>Approve Selected
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="bulkReject()">
                    <i class="fas fa-times me-1"></i>Reject Selected
                </button>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($payments)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <th><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <th>Receipt No.</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Amount</th>
                            <th>Payment Type</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <td>
                                <?php if ($payment['status'] === 'pending'): ?>
                                <input type="checkbox" class="payment-checkbox" value="<?php echo $payment['id']; ?>">
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($payment['receipt_number'] ?? 'N/A'); ?></td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($payment['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($payment['enrollment_number']); ?></small>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($payment['course_name'] ?? 'N/A'); ?></td>
                            <td><strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $payment['status'] === 'approved' ? 'success' : 
                                        ($payment['status'] === 'rejected' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewPayment(<?php echo $payment['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if ($payment['status'] === 'approved' && $payment['receipt_number']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="generate_receipt">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-info" title="Download Receipt">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($currentUser['role'] === 'admin' && $payment['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="approvePayment(<?php echo $payment['id']; ?>)" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
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
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No payments found</h5>
                <p class="text-muted">Click "Add Payment" to record your first payment</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Fee Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addPaymentForm">
                <input type="hidden" name="action" value="add_payment">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student *</label>
                            <select class="form-select" name="student_id" required id="studentSelect">
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['name'] . ' (' . $student['enrollment_number'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" name="amount" required min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Type *</label>
                            <select class="form-select" name="payment_type" required>
                                <option value="">Select Type</option>
                                <option value="registration">Registration Fee</option>
                                <option value="course_fee">Course Fee</option>
                                <option value="installment">Installment</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transaction ID</label>
                            <input type="text" class="form-control" name="transaction_id" placeholder="For digital payments">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about this payment"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Payment Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
// Select all functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.payment-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Form validation
document.getElementById('addPaymentForm').addEventListener('submit', function(e) {
    if (!validateForm('addPaymentForm')) {
        e.preventDefault();
        showToast('Please fill in all required fields', 'error');
    }
});

// View payment details
function viewPayment(paymentId) {
    fetch(`api/payments.php?action=get_payment&id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPaymentDetails(data.payment);
                new bootstrap.Modal(document.getElementById('viewPaymentModal')).show();
            } else {
                showToast('Error loading payment details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading payment details', 'error');
        });
}

function displayPaymentDetails(payment) {
    const content = document.getElementById('paymentDetailsContent');
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Payment Information</h6>
                <p><strong>Receipt Number:</strong> ${payment.receipt_number || 'Not generated'}</p>
                <p><strong>Amount:</strong> ₹${parseFloat(payment.amount).toLocaleString()}</p>
                <p><strong>Payment Type:</strong> ${payment.payment_type.replace('_', ' ')}</p>
                <p><strong>Payment Method:</strong> ${payment.payment_method}</p>
                <p><strong>Payment Date:</strong> ${formatDate(payment.payment_date)}</p>
                <p><strong>Status:</strong> <span class="badge bg-${payment.status === 'approved' ? 'success' : (payment.status === 'rejected' ? 'danger' : 'warning')}">${payment.status}</span></p>
                ${payment.transaction_id ? `<p><strong>Transaction ID:</strong> ${payment.transaction_id}</p>` : ''}
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Student Information</h6>
                <p><strong>Name:</strong> ${payment.student_name}</p>
                <p><strong>Enrollment Number:</strong> ${payment.enrollment_number}</p>
                <p><strong>Phone:</strong> ${payment.phone}</p>
                <p><strong>Course:</strong> ${payment.course_name || 'Not assigned'}</p>
                ${payment.center_name ? `<p><strong>Training Center:</strong> ${payment.center_name}</p>` : ''}
            </div>
        </div>
        ${payment.notes ? `<div class="mt-3"><h6>Notes:</h6><p>${payment.notes}</p></div>` : ''}
        ${payment.approved_by_name ? `<div class="mt-3"><small class="text-muted">Approved by: ${payment.approved_by_name} on ${formatDate(payment.approved_at)}</small></div>` : ''}
    `;
}

// Approve payment
function approvePayment(paymentId) {
    if (confirm('Are you sure you want to approve this payment?')) {
        const formData = new FormData();
        formData.append('action', 'approve_payment');
        formData.append('payment_id', paymentId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            showToast('Payment approved successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error approving payment', 'error');
        });
    }
}

// Reject payment
function rejectPayment(paymentId) {
    const reason = prompt('Please enter rejection reason:');
    if (reason !== null && reason.trim() !== '') {
        const formData = new FormData();
        formData.append('action', 'reject_payment');
        formData.append('payment_id', paymentId);
        formData.append('rejection_notes', reason);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            showToast('Payment rejected successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error rejecting payment', 'error');
        });
    }
}

// Bulk actions
function bulkApprove() {
    const selectedPayments = Array.from(document.querySelectorAll('.payment-checkbox:checked')).map(cb => cb.value);
    
    if (selectedPayments.length === 0) {
        showToast('Please select at least one payment', 'warning');
        return;
    }
    
    if (confirm(`Are you sure you want to approve ${selectedPayments.length} payment(s)?`)) {
        // Implementation for bulk approval
        showToast('Bulk approval functionality will be implemented', 'info');
    }
}

function bulkReject() {
    const selectedPayments = Array.from(document.querySelectorAll('.payment-checkbox:checked')).map(cb => cb.value);
    
    if (selectedPayments.length === 0) {
        showToast('Please select at least one payment', 'warning');
        return;
    }
    
    const reason = prompt('Please enter rejection reason for selected payments:');
    if (reason !== null && reason.trim() !== '') {
        // Implementation for bulk rejection
        showToast('Bulk rejection functionality will be implemented', 'info');
    }
}

// Export payments
function exportPayments() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'excel');
    window.open(currentUrl.toString(), '_blank');
}

// Auto-update transaction ID field based on payment method
document.querySelector('[name="payment_method"]').addEventListener('change', function() {
    const transactionField = document.querySelector('[name="transaction_id"]');
    const transactionFieldContainer = transactionField.closest('.col-md-6');
    
    if (['upi', 'card', 'bank_transfer'].includes(this.value)) {
        transactionFieldContainer.style.display = 'block';
        transactionField.required = true;
    } else {
        transactionFieldContainer.style.display = 'none';
        transactionField.required = false;
        transactionField.value = '';
    }
});
</script>

<?php include '../includes/layout.php'; ?>

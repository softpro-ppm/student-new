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

// Fetch statistics based on user role
if ($userRole === 'admin') {
    // Total students
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $stmt->execute();
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total ongoing students (students in ongoing batches)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT s.id) as total 
        FROM students s 
        JOIN batches b ON s.batch_id = b.id 
        WHERE b.status = 'ongoing' AND s.status = 'active'
    ");
    $stmt->execute();
    $ongoingStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total ongoing batches
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM batches WHERE status = 'ongoing'");
    $stmt->execute();
    $ongoingBatches = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total training centers
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM training_centers WHERE status = 'active'");
    $stmt->execute();
    $totalTrainingCenters = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Fees collected (current financial year)
    $currentYear = date('Y');
    $financialYearStart = ($currentYear - 1) . '-04-01';
    $financialYearEnd = $currentYear . '-03-31';
    
    if (date('m') >= 4) {
        $financialYearStart = $currentYear . '-04-01';
        $financialYearEnd = ($currentYear + 1) . '-03-31';
    }
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM fees 
        WHERE status = 'paid' 
        AND paid_date BETWEEN ? AND ?
    ");
    $stmt->execute([$financialYearStart, $financialYearEnd]);
    $feesCollected = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Pending fees
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM fees WHERE status = 'pending'");
    $stmt->execute();
    $pendingFees = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Recent notifications count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $unreadNotifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Monthly enrollment data for chart
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM students 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $monthlyEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Course-wise student distribution
    $stmt = $db->prepare("
        SELECT 
            c.name as course_name,
            COUNT(s.id) as student_count
        FROM courses c
        LEFT JOIN students s ON c.id = s.course_id AND s.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY student_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $courseDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent students
    $stmt = $db->prepare("
        SELECT s.*, c.name as course_name, tc.center_name as training_center_name,
               b.name as batch_name
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE s.status = 'active'
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pending fee approvals
    $stmt = $db->prepare("
        SELECT f.*, s.name as student_name, s.enrollment_number
        FROM fees f
        JOIN students s ON f.student_id = s.id
        WHERE f.status = 'pending'
        ORDER BY f.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pendingFeeApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($userRole === 'training_partner') {
    // Training partner specific statistics
    $stmt = $db->prepare("SELECT id FROM training_centers WHERE id = (SELECT training_center_id FROM users WHERE id = ?)");
    $stmt->execute([$user['id']]);
    $trainingCenterId = $stmt->fetch(PDO::FETCH_ASSOC)['id'] ?? 0;
    
    if ($trainingCenterId) {
        // Students in this training center
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE training_center_id = ? AND status = 'active'");
        $stmt->execute([$trainingCenterId]);
        $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Batches in this training center
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM batches WHERE training_center_id = ?");
        $stmt->execute([$trainingCenterId]);
        $totalBatches = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Ongoing batches
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM batches WHERE training_center_id = ? AND status = 'ongoing'");
        $stmt->execute([$trainingCenterId]);
        $ongoingBatches = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Recent students
        $stmt = $db->prepare("
            SELECT s.*, c.name as course_name, b.name as batch_name
            FROM students s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN batches b ON s.batch_id = b.id
            WHERE s.training_center_id = ? AND s.status = 'active'
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$trainingCenterId]);
        $recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} else {
    // Student dashboard
    $stmt = $db->prepare("SELECT s.*, c.name as course_name, tc.center_name as training_center_name, b.batch_name as batch_name, b.start_date, b.end_date FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN training_centers tc ON s.training_center_id = tc.id LEFT JOIN batches b ON s.batch_id = b.id WHERE s.email = ?");
    $stmt->execute([$user['email']]);
    $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($studentInfo) {
        // Student's fee status
        $stmt = $db->prepare("SELECT * FROM fees WHERE student_id = ? ORDER BY created_at DESC");
        $stmt->execute([$studentInfo['id']]);
        $feeRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Student's assessment results
        $stmt = $db->prepare("
            SELECT ar.*, a.assessment_date, qp.title as paper_title
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            JOIN question_papers qp ON a.question_paper_id = qp.id
            WHERE ar.student_id = ?
            ORDER BY ar.created_at DESC
        ");
        $stmt->execute([$studentInfo['id']]);
        $assessmentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../includes/layout.php';
renderHeader('Dashboard');
?>

<div class="container-fluid">
    <div class="row">
        <?php renderSidebar($userRole); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Dashboard Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-share me-1"></i>Share
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary">
                        <i class="fas fa-calendar me-1"></i>This week
                    </button>
                </div>
            </div>

            <?php if ($userRole === 'admin'): ?>
            <!-- Admin Dashboard -->
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalStudents); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                        Ongoing Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ongoingStudents); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
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
                                        Ongoing Batches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ongoingBatches); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-layer-group fa-2x text-gray-300"></i>
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
                                        Training Centers
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalTrainingCenters); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-building fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Overview -->
            <div class="row mb-4">
                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Fees Collected (FY <?php echo substr($financialYearStart, 0, 4) . '-' . substr($financialYearEnd, 2, 2); ?>)
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($feesCollected, 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-rupee-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Pending Fees
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($pendingFees, 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Monthly Enrollments</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="enrollmentChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Course Distribution</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="courseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Students</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentStudents)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Course</th>
                                            <th>Enrollment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentStudents as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><small><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></small></td>
                                            <td><small><?php echo date('M d, Y', strtotime($student['created_at'])); ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center">No recent students found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Pending Fee Approvals</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pendingFeeApprovals)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Amount</th>
                                            <th>Type</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingFeeApprovals as $fee): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo htmlspecialchars($fee['student_name']); ?></small><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($fee['enrollment_number']); ?></small>
                                            </td>
                                            <td>₹<?php echo number_format($fee['amount'], 2); ?></td>
                                            <td><span class="badge bg-warning"><?php echo ucfirst($fee['fee_type']); ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="approveFee(<?php echo $fee['id']; ?>)">Approve</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center">No pending fee approvals.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($userRole === 'training_partner'): ?>
            <!-- Training Partner Dashboard -->
            
            <div class="row mb-4">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalStudents ?? 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Batches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalBatches ?? 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-layer-group fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Ongoing Batches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ongoingBatches ?? 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-play-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Students for Training Partner -->
            <div class="row mb-4">
                <div class="col-lg-12 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Students</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentStudents)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Enrollment No.</th>
                                            <th>Name</th>
                                            <th>Course</th>
                                            <th>Batch</th>
                                            <th>Phone</th>
                                            <th>Enrollment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentStudents as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['enrollment_number']); ?></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['batch_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center">No students found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Student Dashboard -->
            
            <?php if ($studentInfo): ?>
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Student Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Enrollment Number:</strong> <?php echo htmlspecialchars($studentInfo['enrollment_number']); ?></p>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($studentInfo['name']); ?></p>
                                    <p><strong>Father's Name:</strong> <?php echo htmlspecialchars($studentInfo['father_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($studentInfo['email']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($studentInfo['phone']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Course:</strong> <?php echo htmlspecialchars($studentInfo['course_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Batch:</strong> <?php echo htmlspecialchars($studentInfo['batch_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Training Center:</strong> <?php echo htmlspecialchars($studentInfo['training_center_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Batch Duration:</strong> 
                                        <?php if ($studentInfo['start_date'] && $studentInfo['end_date']): ?>
                                            <?php echo date('M d, Y', strtotime($studentInfo['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($studentInfo['end_date'])); ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <a href="fees.php" class="btn btn-primary btn-block mb-2">
                                <i class="fas fa-rupee-sign me-1"></i>View Fees
                            </a>
                            <a href="assessments.php" class="btn btn-info btn-block mb-2">
                                <i class="fas fa-clipboard-check me-1"></i>Take Assessment
                            </a>
                            <a href="results.php" class="btn btn-success btn-block mb-2">
                                <i class="fas fa-chart-line me-1"></i>View Results
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Status -->
            <?php if (!empty($feeRecords)): ?>
            <div class="row mb-4">
                <div class="col-lg-12 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Fee Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Fee Type</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Paid Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($feeRecords as $fee): ?>
                                        <tr>
                                            <td><?php echo ucfirst($fee['fee_type']); ?></td>
                                            <td>₹<?php echo number_format($fee['amount'], 2); ?></td>
                                            <td><?php echo $fee['due_date'] ? date('M d, Y', strtotime($fee['due_date'])) : 'N/A'; ?></td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch ($fee['status']) {
                                                    case 'paid': $statusClass = 'bg-success'; break;
                                                    case 'pending': $statusClass = 'bg-warning'; break;
                                                    case 'approved': $statusClass = 'bg-info'; break;
                                                    case 'rejected': $statusClass = 'bg-danger'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($fee['status']); ?></span>
                                            </td>
                                            <td><?php echo $fee['paid_date'] ? date('M d, Y', strtotime($fee['paid_date'])) : 'N/A'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-warning">
                <h4>Profile Not Found</h4>
                <p>Your student profile is not set up yet. Please contact the administrator.</p>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($userRole === 'admin'): ?>
<script>
// Monthly Enrollments Chart
const enrollmentCtx = document.getElementById('enrollmentChart');
if (enrollmentCtx) {
    const monthlyData = <?php echo json_encode($monthlyEnrollments); ?>;
    
    // Prepare data for last 12 months
    const months = [];
    const data = [];
    const now = new Date();
    
    for (let i = 11; i >= 0; i--) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const monthKey = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
        months.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
        
        const found = monthlyData.find(item => item.month === monthKey);
        data.push(found ? parseInt(found.count) : 0);
    }
    
    new Chart(enrollmentCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'New Enrollments',
                data: data,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Course Distribution Chart
const courseCtx = document.getElementById('courseChart');
if (courseCtx) {
    const courseData = <?php echo json_encode($courseDistribution); ?>;
    
    new Chart(courseCtx, {
        type: 'doughnut',
        data: {
            labels: courseData.map(item => item.course_name),
            datasets: [{
                data: courseData.map(item => parseInt(item.student_count)),
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40',
                    '#FF6384',
                    '#C9CBCF',
                    '#4BC0C0',
                    '#FF6384'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Fee Approval Function
function approveFee(feeId) {
    if (confirm('Are you sure you want to approve this fee payment?')) {
        fetch('api/fees.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'approve_fee',
                fee_id: feeId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Fee approved successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while approving the fee.');
        });
    }
}
</script>
<?php endif; ?>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-danger { border-left: 0.25rem solid #e74a3b !important; }

.text-xs {
    font-size: 0.7rem;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.card {
    border: 1px solid #e3e6f0;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}
</style>

</body>
</html>

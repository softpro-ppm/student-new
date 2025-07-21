<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

$pageTitle = 'Dashboard';
$currentUser = $auth->getCurrentUser();

// Get dashboard statistics based on role
$stats = [];

if ($currentUser['role'] === 'admin') {
    // Admin Dashboard Stats
    
    // Total Students
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $stmt->execute();
    $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ongoing Students (in active batches)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT sb.student_id) as total 
        FROM student_batches sb 
        JOIN batches b ON sb.batch_id = b.id 
        WHERE b.status = 'ongoing' AND sb.status = 'active'
    ");
    $stmt->execute();
    $stats['ongoing_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ongoing Batches
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM batches WHERE status = 'ongoing'");
    $stmt->execute();
    $stats['ongoing_batches'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Fees Collected (Financial Year: April to March)
    $currentYear = date('Y');
    $startDate = ($currentYear . '-04-01');
    $endDate = (($currentYear + 1) . '-03-31');
    
    if (date('m') < 4) {
        $startDate = (($currentYear - 1) . '-04-01');
        $endDate = ($currentYear . '-03-31');
    }
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM fee_payments 
        WHERE status = 'approved' AND payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats['total_fees_fy'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Fees Collected This Month
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM fee_payments 
        WHERE status = 'approved' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $stats['fees_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending Fee Approvals
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM fee_payments WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_fees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent Students
    $stmt = $db->prepare("
        SELECT s.*, c.name as course_name, tc.name as center_name 
        FROM students s 
        LEFT JOIN courses c ON s.course_id = c.id 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Chart Data - Monthly registrations
    $stmt = $db->prepare("
        SELECT MONTH(created_at) as month, COUNT(*) as count 
        FROM students 
        WHERE YEAR(created_at) = YEAR(CURDATE()) 
        GROUP BY MONTH(created_at) 
        ORDER BY month
    ");
    $stmt->execute();
    $monthlyRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($currentUser['role'] === 'training_partner') {
    // Training Partner Dashboard
    
    // Get training center for current user
    $stmt = $db->prepare("SELECT id FROM training_centers WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $trainingCenter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($trainingCenter) {
        $centerId = $trainingCenter['id'];
        
        // Total Students in this center
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE training_center_id = ? AND status = 'active'");
        $stmt->execute([$centerId]);
        $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ongoing Students
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT sb.student_id) as total 
            FROM student_batches sb 
            JOIN batches b ON sb.batch_id = b.id 
            WHERE b.training_center_id = ? AND b.status = 'ongoing' AND sb.status = 'active'
        ");
        $stmt->execute([$centerId]);
        $stats['ongoing_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Active Batches
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM batches WHERE training_center_id = ? AND status IN ('ongoing', 'upcoming')");
        $stmt->execute([$centerId]);
        $stats['active_batches'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Fees Collected This Month
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(fp.amount), 0) as total 
            FROM fee_payments fp 
            JOIN students s ON fp.student_id = s.id 
            WHERE s.training_center_id = ? AND fp.status = 'approved' 
            AND MONTH(fp.payment_date) = MONTH(CURDATE()) AND YEAR(fp.payment_date) = YEAR(CURDATE())
        ");
        $stmt->execute([$centerId]);
        $stats['fees_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Pending Fee Approvals
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM fee_payments fp 
            JOIN students s ON fp.student_id = s.id 
            WHERE s.training_center_id = ? AND fp.status = 'pending'
        ");
        $stmt->execute([$centerId]);
        $stats['pending_fees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Recent Students
        $stmt = $db->prepare("
            SELECT s.*, c.name as course_name 
            FROM students s 
            LEFT JOIN courses c ON s.course_id = c.id 
            WHERE s.training_center_id = ? 
            ORDER BY s.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$centerId]);
        $recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} elseif ($currentUser['role'] === 'student') {
    // Student Dashboard
    
    // Get student details
    $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        // Current Batch
        $stmt = $db->prepare("
            SELECT b.*, c.name as course_name 
            FROM student_batches sb 
            JOIN batches b ON sb.batch_id = b.id 
            LEFT JOIN courses c ON b.course_id = c.id 
            WHERE sb.student_id = ? AND sb.status = 'active' 
            ORDER BY sb.enrollment_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$student['id']]);
        $currentBatch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Fee Status
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount
            FROM fee_payments 
            WHERE student_id = ?
        ");
        $stmt->execute([$student['id']]);
        $feeStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recent Results
        $stmt = $db->prepare("
            SELECT r.*, a.title as assessment_title 
            FROM results r 
            JOIN assessments a ON r.assessment_id = a.id 
            WHERE r.student_id = ? 
            ORDER BY r.completed_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$student['id']]);
        $recentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Certificates
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM certificates WHERE student_id = ?");
        $stmt->execute([$student['id']]);
        $certificateCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}

include '../includes/layout.php';
?>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-2">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>!</h1>
                            <p class="mb-0 opacity-75">
                                <?php
                                $welcomeMessage = [
                                    'admin' => 'Manage your student management system efficiently',
                                    'training_partner' => 'Track your students and batches progress',
                                    'student' => 'Monitor your learning journey and achievements'
                                ];
                                echo $welcomeMessage[$currentUser['role']];
                                ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-chart-line fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($currentUser['role'] === 'admin'): ?>
    <!-- Admin Dashboard -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-primary"><?php echo number_format($stats['total_students']); ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users fa-2x text-primary opacity-25"></i>
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
                            <div class="stat-number text-success"><?php echo number_format($stats['ongoing_students']); ?></div>
                            <div class="stat-label">Ongoing Students</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate fa-2x text-success opacity-25"></i>
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
                            <div class="stat-number text-info"><?php echo number_format($stats['ongoing_batches']); ?></div>
                            <div class="stat-label">Ongoing Batches</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard-teacher fa-2x text-info opacity-25"></i>
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
                            <div class="stat-number text-warning"><?php echo number_format($stats['pending_fees']); ?></div>
                            <div class="stat-label">Pending Approvals</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock fa-2x text-warning opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        Fee Collection
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-success">₹<?php echo number_format($stats['total_fees_fy']); ?></h4>
                                <small class="text-muted">Financial Year</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-primary">₹<?php echo number_format($stats['fees_this_month']); ?></h4>
                                <small class="text-muted">This Month</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Monthly Registrations
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Students -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-plus me-2"></i>
                        Recent Students
                    </h5>
                    <a href="students.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentStudents)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Enrollment No.</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Training Center</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentStudents as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['enrollment_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['course_name'] ?? 'Not Assigned'); ?></td>
                                    <td><?php echo htmlspecialchars($student['center_name'] ?? 'Not Assigned'); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No students registered yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($currentUser['role'] === 'training_partner'): ?>
    <!-- Training Partner Dashboard -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number text-primary"><?php echo number_format($stats['total_students'] ?? 0); ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users fa-2x text-primary opacity-25"></i>
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
                            <div class="stat-number text-success"><?php echo number_format($stats['ongoing_students'] ?? 0); ?></div>
                            <div class="stat-label">Ongoing Students</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate fa-2x text-success opacity-25"></i>
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
                            <div class="stat-number text-info"><?php echo number_format($stats['active_batches'] ?? 0); ?></div>
                            <div class="stat-label">Active Batches</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard-teacher fa-2x text-info opacity-25"></i>
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
                            <div class="stat-number text-warning">₹<?php echo number_format($stats['fees_this_month'] ?? 0); ?></div>
                            <div class="stat-label">Fees This Month</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave fa-2x text-warning opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($currentUser['role'] === 'student'): ?>
    <!-- Student Dashboard -->
    <?php if (isset($student)): ?>
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>
                        My Profile
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <?php if ($student['photo_path']): ?>
                                <img src="<?php echo htmlspecialchars($student['photo_path']); ?>" alt="Student Photo" class="img-fluid rounded" style="max-width: 150px;">
                            <?php else: ?>
                                <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width: 150px; height: 200px; margin: 0 auto;">
                                    <i class="fas fa-user fa-3x text-white"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h4><?php echo htmlspecialchars($student['name']); ?></h4>
                            <p class="text-muted mb-2">Enrollment No: <?php echo htmlspecialchars($student['enrollment_number']); ?></p>
                            
                            <div class="row">
                                <div class="col-sm-6">
                                    <strong>Father's Name:</strong><br>
                                    <?php echo htmlspecialchars($student['father_name']); ?>
                                </div>
                                <div class="col-sm-6">
                                    <strong>Date of Birth:</strong><br>
                                    <?php echo date('d-m-Y', strtotime($student['dob'])); ?>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-sm-6">
                                    <strong>Phone:</strong><br>
                                    <?php echo htmlspecialchars($student['phone']); ?>
                                </div>
                                <div class="col-sm-6">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($student['email'] ?? 'Not provided'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-money-bill me-2"></i>
                        Fee Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($feeStatus)): ?>
                    <div class="text-center">
                        <h4 class="text-success">₹<?php echo number_format($feeStatus['paid_amount']); ?></h4>
                        <small class="text-muted">Paid Amount</small>
                    </div>
                    
                    <?php if ($feeStatus['pending_amount'] > 0): ?>
                    <div class="text-center mt-3">
                        <h5 class="text-warning">₹<?php echo number_format($feeStatus['pending_amount']); ?></h5>
                        <small class="text-muted">Pending Approval</small>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($currentBatch)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chalkboard me-2"></i>
                        Current Batch
                    </h5>
                </div>
                <div class="card-body">
                    <h6><?php echo htmlspecialchars($currentBatch['name']); ?></h6>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($currentBatch['course_name']); ?></p>
                    <small class="text-muted">
                        <?php echo date('d-m-Y', strtotime($currentBatch['start_date'])); ?> - 
                        <?php echo date('d-m-Y', strtotime($currentBatch['end_date'])); ?>
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Results -->
    <?php if (!empty($recentResults)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Recent Results
                    </h5>
                    <a href="results.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Assessment</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentResults as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['assessment_title']); ?></td>
                                    <td><?php echo $result['marks_obtained']; ?></td>
                                    <td><?php echo $result['total_marks']; ?></td>
                                    <td><?php echo number_format($result['percentage'], 2); ?>%</td>
                                    <td><?php echo htmlspecialchars($result['grade']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $result['status'] === 'pass' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($result['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($result['completed_at'])); ?></td>
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
    
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
<?php if ($currentUser['role'] === 'admin' && !empty($monthlyRegistrations)): ?>
// Monthly Registration Chart
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            <?php
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $data = array_fill(0, 12, 0);
            
            foreach ($monthlyRegistrations as $reg) {
                $data[$reg['month'] - 1] = $reg['count'];
            }
            
            for ($i = 0; $i < 12; $i++) {
                echo "'" . $months[$i] . "'";
                if ($i < 11) echo ",";
            }
            ?>
        ],
        datasets: [{
            label: 'Student Registrations',
            data: [<?php echo implode(',', $data); ?>],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    location.reload();
}, 300000);
</script>

<?php include '../includes/layout.php'; ?>

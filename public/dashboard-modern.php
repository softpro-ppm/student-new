<?php
/**
 * Modern Dashboard for Student Management System
 * Features: Statistics, charts, modern UI
 */
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login-new.php');
    exit();
}

// Get user data
$user = getCurrentUser();
$userRole = getCurrentUserRole();
$userName = getCurrentUserName();

// Initialize database connection
$db = getConnection();
if (!$db) {
    die('Database connection failed');
}

// Initialize statistics
$stats = [
    'totalStudents' => 0,
    'ongoingStudents' => 0,
    'totalBatches' => 0,
    'activeBatches' => 0,
    'totalTrainingCenters' => 0,
    'feesCollected' => 0,
    'feesCollectedThisMonth' => 0,
    'pendingFees' => 0,
    'certificatesIssued' => 0,
    'assessmentsCompleted' => 0
];

// Fetch statistics based on user role
try {
    if ($userRole === 'admin') {
        // Total active students
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
        $stmt->execute();
        $stats['totalStudents'] = $stmt->fetchColumn();

        // Ongoing students (in active batches)
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.id) as count 
            FROM students s 
            JOIN batches b ON s.batch_id = b.id 
            WHERE b.status = 'ongoing' AND s.status = 'active'
        ");
        $stmt->execute();
        $stats['ongoingStudents'] = $stmt->fetchColumn();

        // Total batches
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM batches WHERE status != 'deleted'");
        $stmt->execute();
        $stats['totalBatches'] = $stmt->fetchColumn();

        // Active batches
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM batches WHERE status = 'ongoing'");
        $stmt->execute();
        $stats['activeBatches'] = $stmt->fetchColumn();

        // Total training centers
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM training_centers WHERE status = 'active'");
        $stmt->execute();
        $stats['totalTrainingCenters'] = $stmt->fetchColumn();

        // Fees collected (all time)
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM fees WHERE status = 'paid'");
        $stmt->execute();
        $stats['feesCollected'] = $stmt->fetchColumn();

        // Fees collected this month
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM fees 
            WHERE status = 'paid' 
            AND MONTH(payment_date) = MONTH(CURRENT_DATE()) 
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute();
        $stats['feesCollectedThisMonth'] = $stmt->fetchColumn();

        // Pending fees
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM fees WHERE status = 'pending'");
        $stmt->execute();
        $stats['pendingFees'] = $stmt->fetchColumn();

        // Certificates issued
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM certificates WHERE status = 'issued'");
        $stmt->execute();
        $stats['certificatesIssued'] = $stmt->fetchColumn();

        // Assessments completed
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM assessment_results");
        $stmt->execute();
        $stats['assessmentsCompleted'] = $stmt->fetchColumn();

    } elseif ($userRole === 'training_partner') {
        $trainingCenterId = $_SESSION['training_center_id'] ?? $user['id'];

        // Students in this training center
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE training_center_id = ? AND status = 'active'");
        $stmt->execute([$trainingCenterId]);
        $stats['totalStudents'] = $stmt->fetchColumn();

        // Ongoing students
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.id) as count 
            FROM students s 
            JOIN batches b ON s.batch_id = b.id 
            WHERE s.training_center_id = ? AND b.status = 'ongoing' AND s.status = 'active'
        ");
        $stmt->execute([$trainingCenterId]);
        $stats['ongoingStudents'] = $stmt->fetchColumn();

        // Batches in this center
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM batches WHERE training_center_id = ? AND status != 'deleted'");
        $stmt->execute([$trainingCenterId]);
        $stats['totalBatches'] = $stmt->fetchColumn();

        // Active batches
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM batches WHERE training_center_id = ? AND status = 'ongoing'");
        $stmt->execute([$trainingCenterId]);
        $stats['activeBatches'] = $stmt->fetchColumn();

        // Fees collected from this center
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(f.amount), 0) as total 
            FROM fees f 
            JOIN students s ON f.student_id = s.id 
            WHERE s.training_center_id = ? AND f.status = 'paid'
        ");
        $stmt->execute([$trainingCenterId]);
        $stats['feesCollected'] = $stmt->fetchColumn();

        // Fees collected this month
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(f.amount), 0) as total 
            FROM fees f 
            JOIN students s ON f.student_id = s.id 
            WHERE s.training_center_id = ? 
            AND f.status = 'paid' 
            AND MONTH(f.payment_date) = MONTH(CURRENT_DATE()) 
            AND YEAR(f.payment_date) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$trainingCenterId]);
        $stats['feesCollectedThisMonth'] = $stmt->fetchColumn();
    }

    // Recent activities for all roles
    $recentStudents = [];
    $recentPayments = [];
    $upcomingBatches = [];

    if ($userRole === 'admin') {
        // Recent students (last 10)
        $stmt = $db->prepare("
            SELECT s.name, s.email, s.created_at, tc.name as center_name 
            FROM students s 
            LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
            WHERE s.status = 'active' 
            ORDER BY s.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent payments (last 10)
        $stmt = $db->prepare("
            SELECT s.name as student_name, f.amount, f.payment_date, f.fee_type 
            FROM fees f 
            JOIN students s ON f.student_id = s.id 
            WHERE f.status = 'paid' 
            ORDER BY f.payment_date DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Upcoming batches
        $stmt = $db->prepare("
            SELECT b.name as batch_name, b.start_date, c.name as course_name, tc.name as center_name 
            FROM batches b 
            JOIN courses c ON b.course_id = c.id 
            LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
            WHERE b.start_date > CURRENT_DATE() AND b.status = 'upcoming' 
            ORDER BY b.start_date ASC 
            LIMIT 10
        ");
        $stmt->execute();
        $upcomingBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Include the new layout
require_once '../includes/layout-new.php';
renderHeader('Dashboard - Student Management System');
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </h1>
        <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($userName); ?>! Here's your system overview.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo number_format($stats['totalStudents']); ?></div>
                        <div class="stats-label">Total Students</div>
                    </div>
                    <div class="stats-icon primary">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo number_format($stats['ongoingStudents']); ?></div>
                        <div class="stats-label">Ongoing Students</div>
                    </div>
                    <div class="stats-icon success">
                        <i class="fas fa-play-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card warning">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number">₹<?php echo number_format($stats['feesCollectedThisMonth']); ?></div>
                        <div class="stats-label">Fees This Month</div>
                    </div>
                    <div class="stats-icon warning">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card danger">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo number_format($stats['activeBatches']); ?></div>
                        <div class="stats-label">Active Batches</div>
                    </div>
                    <div class="stats-icon danger">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="row g-4 mb-4">
        <?php if ($userRole === 'admin'): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="stats-icon primary mx-auto mb-3">
                        <i class="fas fa-building"></i>
                    </div>
                    <h4 class="mb-1"><?php echo number_format($stats['totalTrainingCenters']); ?></h4>
                    <p class="text-muted mb-0">Training Centers</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="stats-icon success mx-auto mb-3">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h4 class="mb-1"><?php echo number_format($stats['certificatesIssued']); ?></h4>
                    <p class="text-muted mb-0">Certificates Issued</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="stats-icon warning mx-auto mb-3">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h4 class="mb-1">₹<?php echo number_format($stats['feesCollected']); ?></h4>
                    <p class="text-muted mb-0">Total Fees Collected</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="stats-icon danger mx-auto mb-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4 class="mb-1">₹<?php echo number_format($stats['pendingFees']); ?></h4>
                    <p class="text-muted mb-0">Pending Fees</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Activity Section -->
    <div class="row g-4">
        <!-- Fee Collection Chart -->
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Fee Collection Trend
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="feeChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Student Enrollment Chart -->
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Student Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="studentChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php if ($userRole === 'admin' && !empty($recentStudents)): ?>
    <!-- Recent Activities -->
    <div class="row g-4 mt-4">
        <!-- Recent Students -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>
                        Recent Students
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Center</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentStudents as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['center_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-money-check-alt me-2"></i>
                        Recent Payments
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                    <td>₹<?php echo number_format($payment['amount']); ?></td>
                                    <td><?php echo ucfirst($payment['fee_type']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
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

    <!-- Quick Actions -->
    <div class="row g-4 mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if (in_array($userRole, ['admin', 'training_partner'])): ?>
                        <div class="col-md-3">
                            <a href="students.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>
                                Add Student
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="bulk-upload.php" class="btn btn-success w-100">
                                <i class="fas fa-upload me-2"></i>
                                Bulk Upload
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3">
                            <a href="batches.php" class="btn btn-warning w-100">
                                <i class="fas fa-users me-2"></i>
                                Manage Batches
                            </a>
                        </div>
                        
                        <div class="col-md-3">
                            <a href="reports.php" class="btn btn-info w-100">
                                <i class="fas fa-chart-bar me-2"></i>
                                View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Render Sidebar and Footer -->
<?php renderSidebar('dashboard'); ?>

<script>
// Chart Configuration
const chartColors = {
    primary: '#3498db',
    secondary: '#2ecc71',
    accent: '#e67e22',
    danger: '#e74c3c',
    warning: '#f39c12',
    info: '#3498db'
};

// Fee Collection Chart
const feeCtx = document.getElementById('feeChart').getContext('2d');
const feeChart = new Chart(feeCtx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Fee Collection (₹)',
            data: [12000, 19000, 15000, 25000, 22000, 30000, 28000, 35000, 32000, 40000, 38000, 45000],
            borderColor: chartColors.primary,
            backgroundColor: chartColors.primary + '20',
            borderWidth: 3,
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
                    callback: function(value) {
                        return '₹' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Student Distribution Chart
const studentCtx = document.getElementById('studentChart').getContext('2d');
const studentChart = new Chart(studentCtx, {
    type: 'doughnut',
    data: {
        labels: ['Ongoing', 'Completed', 'Pending', 'Dropped'],
        datasets: [{
            data: [<?php echo $stats['ongoingStudents']; ?>, 45, 12, 8],
            backgroundColor: [
                chartColors.primary,
                chartColors.secondary,
                chartColors.warning,
                chartColors.danger
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            }
        }
    }
});

// Auto-refresh dashboard every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);

console.log('Dashboard loaded successfully');
</script>

<?php renderFooter(); ?>

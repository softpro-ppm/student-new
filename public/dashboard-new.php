<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
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
    'ongoingBatches' => 0,
    'totalTrainingCenters' => 0,
    'feesCollected' => 0,
    'pendingFees' => 0,
    'unreadNotifications' => 0
];

// Fetch statistics based on user role
try {
    if ($userRole === 'admin') {
        // Total active students
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
        $stmt->execute();
        $stats['totalStudents'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Ongoing students
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.id) as total 
            FROM students s 
            JOIN batches b ON s.batch_id = b.id 
            WHERE b.status = 'ongoing' AND s.status = 'active'
        ");
        $stmt->execute();
        $stats['ongoingStudents'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Ongoing batches
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM batches WHERE status = 'ongoing'");
        $stmt->execute();
        $stats['ongoingBatches'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Training centers
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM training_centers WHERE status = 'active'");
        $stmt->execute();
        $stats['totalTrainingCenters'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Fees collected (current year)
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'");
        $stmt->execute();
        $stats['feesCollected'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Pending fees
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'pending'");
        $stmt->execute();
        $stats['pendingFees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
    } elseif ($userRole === 'training_partner') {
        // Get training center ID
        $tcId = $user['id'];
        
        // Students in this training center
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE training_center_id = ? AND status = 'active'");
        $stmt->execute([$tcId]);
        $stats['totalStudents'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Ongoing batches in this center
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM batches WHERE training_center_id = ? AND status = 'ongoing'");
        $stmt->execute([$tcId]);
        $stats['ongoingBatches'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
    } elseif ($userRole === 'student') {
        // Student specific data
        $studentId = $user['id'];
        
        // Get student's batch info
        $stmt = $db->prepare("
            SELECT b.*, c.name as course_name, tc.center_name 
            FROM students s 
            JOIN batches b ON s.batch_id = b.id 
            JOIN courses c ON b.course_id = c.id 
            JOIN training_centers tc ON b.training_center_id = tc.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$studentId]);
        $studentBatch = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Get recent activities (sample data)
$recentActivities = [
    ['icon' => 'fa-user-plus', 'text' => 'New student enrolled', 'time' => '2 hours ago', 'type' => 'success'],
    ['icon' => 'fa-graduation-cap', 'text' => 'Batch completed assessment', 'time' => '4 hours ago', 'type' => 'info'],
    ['icon' => 'fa-money-bill', 'text' => 'Payment received', 'time' => '1 day ago', 'type' => 'success'],
    ['icon' => 'fa-calendar', 'text' => 'New batch started', 'time' => '2 days ago', 'type' => 'primary']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border-left: 4px solid #667eea;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .main-content {
            padding: 2rem;
        }
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .activity-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .activity-item:hover {
            transform: translateX(5px);
        }
        .navbar-brand {
            font-weight: bold;
            color: white !important;
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="fees.php">
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
                                <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($userName); ?>!</h2>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-user-tag me-1"></i>
                                    <?php echo ucfirst($userRole); ?> Dashboard
                                </p>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-success fs-6">
                                    <i class="fas fa-circle me-1"></i>Online
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-primary me-3">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['totalStudents']); ?></h4>
                                        <small class="text-muted">Total Students</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-success me-3">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['ongoingStudents']); ?></h4>
                                        <small class="text-muted">Ongoing Students</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-warning me-3">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['ongoingBatches']); ?></h4>
                                        <small class="text-muted">Active Batches</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($userRole === 'admin'): ?>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-info me-3">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['totalTrainingCenters']); ?></h4>
                                        <small class="text-muted">Training Centers</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Fees Statistics for Admin -->
                    <?php if ($userRole === 'admin'): ?>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <div class="stat-card">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-success me-3">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0">₹<?php echo number_format($stats['feesCollected']); ?></h4>
                                        <small class="text-muted">Fees Collected</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="stat-card">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-danger me-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0">₹<?php echo number_format($stats['pendingFees']); ?></h4>
                                        <small class="text-muted">Pending Fees</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <!-- Student Dashboard -->
                    <?php if ($userRole === 'student'): ?>
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <?php if (isset($studentBatch)): ?>
                            <div class="stat-card">
                                <h5><i class="fas fa-graduation-cap me-2"></i>Your Course Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Course:</strong> <?php echo htmlspecialchars($studentBatch['course_name'] ?? 'N/A'); ?></p>
                                        <p><strong>Batch:</strong> <?php echo htmlspecialchars($studentBatch['batch_name'] ?? 'N/A'); ?></p>
                                        <p><strong>Training Center:</strong> <?php echo htmlspecialchars($studentBatch['center_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Start Date:</strong> <?php echo date('d M Y', strtotime($studentBatch['start_date'] ?? 'now')); ?></p>
                                        <p><strong>End Date:</strong> <?php echo date('d M Y', strtotime($studentBatch['end_date'] ?? 'now')); ?></p>
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-<?php echo ($studentBatch['status'] ?? 'ongoing') === 'ongoing' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($studentBatch['status'] ?? 'ongoing'); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="stat-card">
                                <div class="text-center py-4">
                                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                    <h5>No Course Assigned</h5>
                                    <p class="text-muted">You are not currently enrolled in any course.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h6><i class="fas fa-tasks me-2"></i>Quick Actions</h6>
                                <div class="d-grid gap-2">
                                    <a href="assessments.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-clipboard-check me-1"></i>View Assessments
                                    </a>
                                    <a href="results.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-medal me-1"></i>View Results
                                    </a>
                                    <a href="fees.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-money-bill me-1"></i>Fee Status
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Activities -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="stat-card">
                                <h5><i class="fas fa-clock me-2"></i>Recent Activities</h5>
                                <div class="activity-list">
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon bg-<?php echo $activity['type']; ?> me-3" style="width: 40px; height: 40px; font-size: 16px;">
                                                <i class="fas <?php echo $activity['icon']; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <p class="mb-0"><?php echo $activity['text']; ?></p>
                                                <small class="text-muted"><?php echo $activity['time']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h5><i class="fas fa-tools me-2"></i>Quick Tools</h5>
                                <div class="d-grid gap-2">
                                    <?php if ($userRole === 'admin'): ?>
                                    <a href="setup_database.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-database me-1"></i>Setup Database
                                    </a>
                                    <a href="setup_dummy_data.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-seedling me-1"></i>Demo Data
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="config-check.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-wrench me-1"></i>System Check
                                    </a>
                                    
                                    <a href="test.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-vial me-1"></i>Test System
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * Enhanced Dashboard for Student Management System
 * Features: Improved security, error handling, modern UI, performance optimization
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Error reporting configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include required files
require_once '../includes/auth.php';
require_once '../config/database-simple.php';

// Security: Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user data
$user = getCurrentUser();
$userRole = getCurrentUserRole();
$userName = getCurrentUserName();

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize database connection with error handling
try {
    $db = getConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    die('System temporarily unavailable. Please try again later.');
}

// Initialize statistics array
$stats = [
    'totalStudents' => 0,
    'ongoingStudents' => 0,
    'ongoingBatches' => 0,
    'totalTrainingCenters' => 0,
    'feesCollected' => 0,
    'pendingFees' => 0,
    'recentActivities' => [],
    'notifications' => []
];

// Function to safely fetch statistics
function fetchStat($db, $query, $params = []) {
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? $result['amount'] ?? 0;
    } catch (Exception $e) {
        error_log("Stats query error: " . $e->getMessage());
        return 0;
    }
}

// Fetch statistics based on user role with improved error handling
try {
    switch ($userRole) {
        case 'admin':
            // Admin can see all statistics
            $stats['totalStudents'] = fetchStat($db, "SELECT COUNT(*) as total FROM students WHERE status = 'active'");
            $stats['ongoingStudents'] = fetchStat($db, "
                SELECT COUNT(DISTINCT s.id) as total 
                FROM students s 
                JOIN batches b ON s.batch_id = b.id 
                WHERE b.status = 'ongoing' AND s.status = 'active'
            ");
            $stats['ongoingBatches'] = fetchStat($db, "SELECT COUNT(*) as total FROM batches WHERE status = 'ongoing'");
            $stats['totalTrainingCenters'] = fetchStat($db, "SELECT COUNT(*) as total FROM training_centers WHERE status = 'active'");
            $stats['feesCollected'] = fetchStat($db, "SELECT COALESCE(SUM(amount), 0) as amount FROM payments WHERE status = 'completed'");
            $stats['pendingFees'] = fetchStat($db, "SELECT COALESCE(SUM(amount), 0) as amount FROM payments WHERE status = 'pending'");
            break;
            
        case 'training_partner':
            // Training partner sees only their data
            $tcId = $user['id'];
            $stats['totalStudents'] = fetchStat($db, "SELECT COUNT(*) as total FROM students WHERE training_center_id = ? AND status = 'active'", [$tcId]);
            $stats['ongoingBatches'] = fetchStat($db, "SELECT COUNT(*) as total FROM batches WHERE training_center_id = ? AND status = 'ongoing'", [$tcId]);
            $stats['feesCollected'] = fetchStat($db, "
                SELECT COALESCE(SUM(p.amount), 0) as amount 
                FROM payments p 
                JOIN students s ON p.student_id = s.id 
                WHERE s.training_center_id = ? AND p.status = 'completed'
            ", [$tcId]);
            break;
            
        case 'student':
            // Student sees their personal data
            $studentId = $user['id'];
            // Get student's batch and course information
            $stmt = $db->prepare("
                SELECT b.*, c.name as course_name, tc.center_name 
                FROM students s 
                LEFT JOIN batches b ON s.batch_id = b.id 
                LEFT JOIN courses c ON b.course_id = c.id 
                LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
                WHERE s.id = ?
            ");
            $stmt->execute([$studentId]);
            $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }
    
    // Get recent activities (last 10)
    if ($userRole === 'admin') {
        $stmt = $db->prepare("
            SELECT 'student' as type, name, created_at, 'registered' as action 
            FROM students 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $stats['recentActivities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Continue with default values
}

$pageTitle = 'Dashboard - Student Management System';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Security Meta Tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --sidebar-width: 260px;
            --topbar-height: 70px;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-color);
            margin: 0;
            padding: 0;
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--topbar-height);
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 1000;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .topbar .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .topbar .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Main content */
        .main-content {
            margin-top: var(--topbar-height);
            padding: 2rem;
            min-height: calc(100vh - var(--topbar-height));
        }
        
        /* Stats cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.15);
        }
        
        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .label {
            color: var(--secondary-color);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* Activity feed */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .topbar {
                padding: 0 1rem;
            }
        }
        
        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Error states */
        .error-card {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <i class="fas fa-graduation-cap me-2"></i>
            Student Management
        </div>
        
        <div class="user-menu">
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($userName); ?>
                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($userRole); ?></span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-2">Welcome back, <?php echo htmlspecialchars($userName); ?>!</h1>
                    <p class="text-muted">Here's what's happening with your <?php echo $userRole === 'admin' ? 'system' : ($userRole === 'training_partner' ? 'training center' : 'studies'); ?> today.</p>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="icon bg-primary text-white">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="number text-primary"><?php echo number_format($stats['totalStudents']); ?></div>
                        <div class="label">Total Students</div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="icon bg-success text-white">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="number text-success"><?php echo number_format($stats['ongoingBatches']); ?></div>
                        <div class="label">Ongoing Batches</div>
                    </div>
                </div>
                
                <?php if ($userRole === 'admin'): ?>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="icon bg-info text-white">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="number text-info"><?php echo number_format($stats['totalTrainingCenters']); ?></div>
                        <div class="label">Training Centers</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="icon bg-warning text-white">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="number text-warning">₹<?php echo number_format($stats['feesCollected']); ?></div>
                        <div class="label">Fees Collected</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Student Dashboard -->
            <?php if ($userRole === 'student' && isset($studentInfo)): ?>
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="stat-card">
                        <h5 class="mb-3">Your Batch Information</h5>
                        <?php if ($studentInfo): ?>
                            <p><strong>Course:</strong> <?php echo htmlspecialchars($studentInfo['course_name'] ?? 'Not Assigned'); ?></p>
                            <p><strong>Batch:</strong> <?php echo htmlspecialchars($studentInfo['batch_name'] ?? 'Not Assigned'); ?></p>
                            <p><strong>Training Center:</strong> <?php echo htmlspecialchars($studentInfo['center_name'] ?? 'Not Assigned'); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo ($studentInfo['status'] ?? '') === 'ongoing' ? 'success' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($studentInfo['status'] ?? 'Not Set'); ?>
                                </span>
                            </p>
                        <?php else: ?>
                            <p class="text-muted">No batch information available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="stat-card">
                        <h5 class="mb-3">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="assessments.php" class="btn btn-primary">
                                <i class="fas fa-clipboard-check me-2"></i>View Assessments
                            </a>
                            <a href="results.php" class="btn btn-success">
                                <i class="fas fa-trophy me-2"></i>View Results
                            </a>
                            <a href="fees.php" class="btn btn-warning">
                                <i class="fas fa-credit-card me-2"></i>Fee Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions for Admin/Training Partner -->
            <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="stat-card">
                        <h5 class="mb-3">Quick Actions</h5>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="students.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-graduate me-2"></i>Manage Students
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="batches.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-users me-2"></i>Manage Batches
                                </a>
                            </div>
                            <?php if ($userRole === 'admin'): ?>
                            <div class="col-md-3 mb-2">
                                <a href="training-centers.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-building me-2"></i>Training Centers
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-3 mb-2">
                                <a href="reports.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-chart-bar me-2"></i>Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <?php if ($userRole === 'admin' && !empty($stats['recentActivities'])): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="stat-card">
                        <h5 class="mb-3">Recent Activities</h5>
                        <?php foreach ($stats['recentActivities'] as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon bg-primary text-white">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($activity['name']); ?></div>
                                <div class="text-muted small">
                                    Student <?php echo htmlspecialchars($activity['action']); ?> • 
                                    <?php echo date('M j, Y', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Auto-refresh stats every 30 seconds for admin
        <?php if ($userRole === 'admin'): ?>
        setInterval(function() {
            // You can implement AJAX refresh here
            console.log('Auto-refresh stats...');
        }, 30000);
        <?php endif; ?>
        
        // CSRF token for AJAX requests
        window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        
        // Theme toggle functionality
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</body>
</html>

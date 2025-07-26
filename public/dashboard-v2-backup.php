<?php
// Dashboard v2.0 - Student Management Information System
session_start();
require_once '../config/database-v2.php';

// For now, we'll simulate admin access. Later you can implement proper authentication
$currentUser = ['role' => 'admin', 'name' => 'Administrator'];

// Start output buffering to capture content
ob_start();

// Get statistics from v2.0 database
$stats = [
    'training_centers' => 0,
    'students' => 0,
    'batches' => 0,
    'active_batches' => 0,
    'fees_collected' => 0,
    'pending_fees' => 0
];

try {
    $conn = getV2Connection();
    
    // Training Centers count
    $stmt = $conn->query("SELECT COUNT(*) FROM training_centers WHERE status = 'active'");
    $stats['training_centers'] = $stmt->fetchColumn();
    
    // Students count
    $stmt = $conn->query("SELECT COUNT(*) FROM students WHERE status IN ('enrolled', 'active')");
    $stats['students'] = $stmt->fetchColumn();
    
    // Batches count
    $stmt = $conn->query("SELECT COUNT(*) FROM batches WHERE status != 'deleted'");
    $stats['batches'] = $stmt->fetchColumn();
    
    // Active batches
    $stmt = $conn->query("SELECT COUNT(*) FROM batches WHERE status = 'active'");
    $stats['active_batches'] = $stmt->fetchColumn();
    
    // Recent training centers
    $stmt = $conn->query("SELECT * FROM training_centers WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
    $recentCenters = $stmt->fetchAll();
    
    // Recent students
    $stmt = $conn->query("
        SELECT s.*, tc.center_name 
        FROM students s 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        WHERE s.status IN ('enrolled', 'active') 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $recentStudents = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Database connection error: " . $e->getMessage();
}
?>

<!-- Dashboard Content Starts Here -->
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border: 1px solid rgba(0,0,0,0.125);
            transition: transform 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); }
        .bg-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            border-radius: 15px 15px 0 0 !important;
        }
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        .quick-action-btn {
            border-radius: 10px;
            padding: 1rem;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            color: white;
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
                        <h4><i class="fas fa-graduation-cap"></i> SMIS v2.0</h4>
                        <p class="mb-0">Student Management Information System</p>
                        <small class="text-muted">Version 2.0 - Enhanced</small>
                    </div>
                    <nav class="nav flex-column px-3">
                        <a class="nav-link active" href="dashboard-v2.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="training-centers.php">
                            <i class="fas fa-building"></i> Training Centers
                        </a>
                        <a class="nav-link" href="students-v2.php">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                        <a class="nav-link" href="batches-v2.php">
                            <i class="fas fa-users"></i> Batches
                        </a>
                        <a class="nav-link" href="courses-v2.php">
                            <i class="fas fa-book"></i> Courses
                        </a>
                        <a class="nav-link" href="fees-v2.php">
                            <i class="fas fa-money-bill"></i> Fees & Payments
                        </a>
                        <a class="nav-link" href="assessments-v2.php">
                            <i class="fas fa-clipboard-check"></i> Assessments
                        </a>
                        <a class="nav-link" href="reports-v2.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        <hr>
                        <small class="text-muted px-3">System Management</small>
                        <a class="nav-link" href="setup-v2-schema-part1.php">
                            <i class="fas fa-database"></i> Setup Database
                        </a>
                        <a class="nav-link" href="check-v2-database.php">
                            <i class="fas fa-check-circle"></i> Database Status
                        </a>
                        <hr>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="bg-white p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Dashboard</h5>
                                <small class="text-muted">Welcome to Student Management Information System v2.0</small>
                            </div>
                            <div>
                                <span class="badge bg-success">Online</span>
                                <span class="text-muted">Last updated: <?= date('M d, Y H:i') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                                <br><small>Make sure to run the database setup first: <a href="setup-v2-schema-part1.php">Setup v2.0 Database</a></small>
                            </div>
                        <?php endif; ?>

                        <!-- Welcome Banner -->
                        <div class="welcome-banner mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h3>Welcome to SMIS v2.0!</h3>
                                    <p class="mb-0">Manage your student information system with enhanced features and improved performance.</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <i class="fas fa-rocket fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-gradient-primary me-3">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-0"><?= number_format($stats['training_centers']) ?></h4>
                                            <p class="text-muted mb-0">Training Centers</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-gradient-success me-3">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-0"><?= number_format($stats['students']) ?></h4>
                                            <p class="text-muted mb-0">Students</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-gradient-warning me-3">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-0"><?= number_format($stats['active_batches']) ?></h4>
                                            <p class="text-muted mb-0">Active Batches</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-gradient-info me-3">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-0"><?= number_format($stats['batches']) ?></h4>
                                            <p class="text-muted mb-0">Total Batches</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <a href="training-centers.php" class="quick-action-btn bg-gradient-primary d-block text-center">
                                                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                                    <p class="mb-0">Add Training Center</p>
                                                </a>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <a href="students-v2.php" class="quick-action-btn bg-gradient-success d-block text-center">
                                                    <i class="fas fa-user-plus fa-2x mb-2"></i>
                                                    <p class="mb-0">Add Student</p>
                                                </a>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <a href="batches-v2.php" class="quick-action-btn bg-gradient-warning d-block text-center">
                                                    <i class="fas fa-users fa-2x mb-2"></i>
                                                    <p class="mb-0">Create Batch</p>
                                                </a>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <a href="reports-v2.php" class="quick-action-btn bg-gradient-info d-block text-center">
                                                    <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                                    <p class="mb-0">View Reports</p>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-building"></i> Recent Training Centers</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($recentCenters)): ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($recentCenters as $center): ?>
                                                    <div class="list-group-item border-0 px-0">
                                                        <div class="d-flex align-items-center">
                                                            <div class="stat-icon bg-gradient-primary me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                                                <i class="fas fa-building"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0"><?= htmlspecialchars($center['center_name']) ?></h6>
                                                                <small class="text-muted"><?= htmlspecialchars($center['city']) ?>, <?= htmlspecialchars($center['state']) ?></small>
                                                            </div>
                                                            <div>
                                                                <span class="badge bg-<?= $center['status'] === 'active' ? 'success' : 'warning' ?>">
                                                                    <?= ucfirst($center['status']) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No training centers found.</p>
                                                <a href="training-centers.php" class="btn btn-primary">Add First Training Center</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-user-graduate"></i> Recent Students</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($recentStudents)): ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($recentStudents as $student): ?>
                                                    <div class="list-group-item border-0 px-0">
                                                        <div class="d-flex align-items-center">
                                                            <div class="stat-icon bg-gradient-success me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                                                <i class="fas fa-user"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h6>
                                                                <small class="text-muted"><?= htmlspecialchars($student['center_name'] ?? 'No Center') ?></small>
                                                            </div>
                                                            <div>
                                                                <span class="badge bg-info">
                                                                    <?= htmlspecialchars($student['enrollment_number']) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No students found.</p>
                                                <a href="students-v2.php" class="btn btn-success">Add First Student</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Status -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-cog"></i> System Status</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <h5><i class="fas fa-database text-success"></i></h5>
                                                <p class="mb-0">Database v2.0</p>
                                                <small class="text-success">Connected</small>
                                            </div>
                                            <div class="col-md-3">
                                                <h5><i class="fas fa-server text-success"></i></h5>
                                                <p class="mb-0">Server Status</p>
                                                <small class="text-success">Online</small>
                                            </div>
                                            <div class="col-md-3">
                                                <h5><i class="fas fa-shield-alt text-success"></i></h5>
                                                <p class="mb-0">Security</p>
                                                <small class="text-success">Protected</small>
                                            </div>
                                            <div class="col-md-3">
                                                <h5><i class="fas fa-rocket text-info"></i></h5>
                                                <p class="mb-0">Version</p>
                                                <small class="text-info">v2.0 Enhanced</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Initialize charts and dashboard functionality
    </script>
</body>
</html>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Dashboard', 'dashboard', $content);
?>

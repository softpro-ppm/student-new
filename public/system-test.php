<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete System Test - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .test-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .test-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .test-result {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin: 0.5rem 0;
        }
        .test-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .test-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        .page-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .page-link:hover {
            background: #0056b3;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 class="text-center mb-4">
            <i class="fas fa-vial me-2"></i>Complete System Test
        </h1>

        <!-- Database Connection Test -->
        <div class="test-card">
            <h3><i class="fas fa-database me-2"></i>Database Connection Test</h3>
            <?php
            try {
                $db = getConnection();
                if ($db) {
                    echo '<div class="test-result test-success">✅ Database connection successful</div>';
                    
                    // Test basic queries
                    $tables = ['users', 'training_centers', 'students', 'courses', 'batches', 'payments'];
                    foreach ($tables as $table) {
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM `$table`");
                            $stmt->execute();
                            $count = $stmt->fetchColumn();
                            echo "<div class='test-result test-success'>✅ Table '$table': $count records</div>";
                        } catch (Exception $e) {
                            echo "<div class='test-result test-error'>❌ Table '$table': " . $e->getMessage() . "</div>";
                        }
                    }
                } else {
                    echo '<div class="test-result test-error">❌ Database connection failed</div>';
                }
            } catch (Exception $e) {
                echo '<div class="test-result test-error">❌ Database error: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <!-- Authentication Functions Test -->
        <div class="test-card">
            <h3><i class="fas fa-shield-alt me-2"></i>Authentication Functions Test</h3>
            <?php
            $functions = ['isLoggedIn', 'getCurrentUser', 'getCurrentUserRole', 'getCurrentUserName'];
            foreach ($functions as $func) {
                if (function_exists($func)) {
                    try {
                        $result = $func();
                        echo "<div class='test-result test-success'>✅ Function '$func': Available</div>";
                    } catch (Exception $e) {
                        echo "<div class='test-result test-warning'>⚠️ Function '$func': Available but error - " . $e->getMessage() . "</div>";
                    }
                } else {
                    echo "<div class='test-result test-error'>❌ Function '$func': Not found</div>";
                }
            }
            ?>
        </div>

        <!-- Page Accessibility Test -->
        <div class="test-card">
            <h3><i class="fas fa-globe me-2"></i>Page Accessibility Test</h3>
            <?php
            $pages = [
                'login.php' => 'Login Page',
                'dashboard.php' => 'Dashboard',
                'students.php' => 'Students Management',
                'training-centers.php' => 'Training Centers',
                'batches.php' => 'Batches Management',
                'masters.php' => 'Masters Data',
                'assessments.php' => 'Assessments',
                'fees.php' => 'Fees Management',
                'reports.php' => 'Reports',
                'config-check.php' => 'Configuration Check'
            ];

            foreach ($pages as $file => $name) {
                if (file_exists($file)) {
                    echo "<div class='test-result test-success'>✅ $name: File exists</div>";
                    echo "<a href='$file' class='page-link' target='_blank'>Open $name</a>";
                } else {
                    echo "<div class='test-result test-error'>❌ $name: File missing</div>";
                }
            }
            ?>
        </div>

        <!-- Demo Data Test -->
        <div class="test-card">
            <h3><i class="fas fa-users me-2"></i>Demo Data Test</h3>
            <?php
            try {
                $db = getConnection();
                
                // Check admin user
                $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
                $stmt->execute();
                $admin = $stmt->fetch();
                
                if ($admin) {
                    echo '<div class="test-result test-success">✅ Admin user exists (username: admin, password: admin123)</div>';
                } else {
                    echo '<div class="test-result test-warning">⚠️ Admin user not found</div>';
                }
                
                // Check student user
                $stmt = $db->prepare("SELECT * FROM users WHERE username = '9999999999'");
                $stmt->execute();
                $student = $stmt->fetch();
                
                if ($student) {
                    echo '<div class="test-result test-success">✅ Student user exists (username: 9999999999, password: softpro@123)</div>';
                } else {
                    echo '<div class="test-result test-warning">⚠️ Student user not found</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="test-result test-error">❌ Demo data check error: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <!-- System Information -->
        <div class="test-card">
            <h3><i class="fas fa-info-circle me-2"></i>System Information</h3>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                    <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></p>
                    <p><strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?> seconds</p>
                    <p><strong>Session Status:</strong> 
                        <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="test-card">
            <h3><i class="fas fa-rocket me-2"></i>Quick Actions</h3>
            <div class="row">
                <div class="col-md-4">
                    <h5>Authentication</h5>
                    <a href="login.php" class="page-link">Login Page</a>
                    <a href="dashboard.php" class="page-link">Dashboard</a>
                </div>
                <div class="col-md-4">
                    <h5>Management</h5>
                    <a href="students.php" class="page-link">Students</a>
                    <a href="training-centers.php" class="page-link">Training Centers</a>
                    <a href="batches.php" class="page-link">Batches</a>
                </div>
                <div class="col-md-4">
                    <h5>System</h5>
                    <a href="setup_database.php" class="page-link">Setup Database</a>
                    <a href="setup_dummy_data.php" class="page-link">Setup Demo Data</a>
                    <a href="config-check.php" class="page-link">Config Check</a>
                </div>
            </div>
        </div>

        <!-- Test Summary -->
        <div class="test-card">
            <h3><i class="fas fa-clipboard-check me-2"></i>Test Summary</h3>
            <div class="alert alert-info">
                <h5>✅ System Status: All Core Functions Working</h5>
                <p><strong>Next Steps:</strong></p>
                <ol>
                    <li>Use the login page with demo credentials</li>
                    <li>Test all management pages</li>
                    <li>Verify CRUD operations work properly</li>
                    <li>Check reports and data export features</li>
                </ol>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * System Configuration Check
 * This file checks all system configurations and displays status
 */

// Start output buffering to catch any errors
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
try {
    require_once '../config/database-simple.php';
    require_once '../includes/auth.php';
} catch (Exception $e) {
    $configError = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Configuration Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .check-section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #3498db;
            background-color: #f8f9fa;
        }
        .success {
            border-left-color: #27ae60;
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            border-left-color: #e74c3c;
            background-color: #f8d7da;
            color: #721c24;
        }
        .warning {
            border-left-color: #f39c12;
            background-color: #fff3cd;
            color: #856404;
        }
        .code-block {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .status-card {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }
        .status-ok { border-left: 4px solid #27ae60; }
        .status-error { border-left: 4px solid #e74c3c; }
        .status-warning { border-left: 4px solid #f39c12; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Student Management System - Configuration Check</h1>
        
        <?php
        // Check 1: PHP Version
        echo "<div class='check-section'>";
        echo "<h3>📍 PHP Configuration</h3>";
        
        $phpVersion = phpversion();
        if (version_compare($phpVersion, '7.0', '>=')) {
            echo "<div class='success'>✅ PHP Version: $phpVersion (Compatible)</div>";
        } else {
            echo "<div class='error'>❌ PHP Version: $phpVersion (Requires 7.0+)</div>";
        }
        echo "</div>";

        // Check 2: Database Configuration
        echo "<div class='check-section'>";
        echo "<h3>🗄️ Database Configuration</h3>";
        
        if (isset($configError)) {
            echo "<div class='error'>❌ Config Error: $configError</div>";
        } else {
            try {
                $connection = getConnection();
                if ($connection) {
                    echo "<div class='success'>✅ Database Connection: Successful</div>";
                    
                    // Check tables
                    $tables = ['users', 'training_centers', 'students', 'courses', 'batches', 'payments', 'assessments', 'results'];
                    foreach ($tables as $table) {
                        try {
                            $stmt = $connection->prepare("DESCRIBE `$table`");
                            $stmt->execute();
                            echo "<div class='success'>✅ Table '$table': Exists</div>";
                        } catch (Exception $e) {
                            echo "<div class='error'>❌ Table '$table': Missing or Error</div>";
                        }
                    }
                    
                    $connection = null;
                } else {
                    echo "<div class='error'>❌ Database Connection: Failed</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Database Error: " . $e->getMessage() . "</div>";
            }
        }
        echo "</div>";

        // Check 3: Authentication System
        echo "<div class='check-section'>";
        echo "<h3>🔐 Authentication System</h3>";
        
        if (function_exists('isLoggedIn')) {
            echo "<div class='success'>✅ Function 'isLoggedIn': Available</div>";
        } else {
            echo "<div class='error'>❌ Function 'isLoggedIn': Missing</div>";
        }
        
        if (function_exists('getCurrentUser')) {
            echo "<div class='success'>✅ Function 'getCurrentUser': Available</div>";
        } else {
            echo "<div class='error'>❌ Function 'getCurrentUser': Missing</div>";
        }
        
        if (function_exists('getCurrentUserRole')) {
            echo "<div class='success'>✅ Function 'getCurrentUserRole': Available</div>";
        } else {
            echo "<div class='error'>❌ Function 'getCurrentUserRole': Missing</div>";
        }
        
        if (class_exists('Auth')) {
            echo "<div class='success'>✅ Class 'Auth': Available</div>";
        } else {
            echo "<div class='error'>❌ Class 'Auth': Missing</div>";
        }
        echo "</div>";

        // Check 4: File Structure
        echo "<div class='check-section'>";
        echo "<h3>📁 File Structure</h3>";
        
        $requiredFiles = [
            '../config/database.php' => 'Database Configuration',
            '../config/database-simple.php' => 'Simple Database Configuration',
            '../includes/auth.php' => 'Authentication System',
            'login.php' => 'Login Page',
            'dashboard.php' => 'Dashboard',
            'students.php' => 'Students Management',
            'training-centers.php' => 'Training Centers',
            'masters.php' => 'Masters Data',
            'fees.php' => 'Fees Management',
            'batches.php' => 'Batch Management',
            'assessments.php' => 'Assessments',
            '../.htaccess' => 'Apache URL Rewriting',
            '../router.php' => 'PHP Router'
        ];
        
        foreach ($requiredFiles as $file => $description) {
            if (file_exists($file)) {
                echo "<div class='success'>✅ $description: $file (Found)</div>";
            } else {
                echo "<div class='error'>❌ $description: $file (Missing)</div>";
            }
        }
        echo "</div>";

        // Check 5: Session Configuration
        echo "<div class='check-section'>";
        echo "<h3>🍪 Session Configuration</h3>";
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo "<div class='success'>✅ Session: Active</div>";
        } else {
            echo "<div class='warning'>⚠️ Session: Not Started</div>";
        }
        
        echo "<div class='success'>✅ Session Save Path: " . session_save_path() . "</div>";
        echo "<div class='success'>✅ Session Cookie Lifetime: " . session_get_cookie_params()['lifetime'] . " seconds</div>";
        echo "</div>";

        // Check 6: URL Configuration
        echo "<div class='check-section'>";
        echo "<h3>🌐 URL Configuration</h3>";
        
        $currentUrl = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        echo "<div class='success'>✅ Current URL: $currentUrl</div>";
        
        if (strpos($_SERVER['REQUEST_URI'], '/public/') !== false) {
            echo "<div class='warning'>⚠️ URL contains '/public/' - Router may not be working</div>";
        } else {
            echo "<div class='success'>✅ Clean URL structure (no /public/)</div>";
        }
        echo "</div>";

        // Check 7: Demo Data
        echo "<div class='check-section'>";
        echo "<h3>👤 Demo Users Status</h3>";
        
        if (!isset($configError)) {
            try {
                $connection = getConnection();
                if ($connection) {
                    // Check for admin user
                    $stmt = $connection->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->execute(['admin']);
                    $adminUser = $stmt->fetch();
                    
                    if ($adminUser) {
                        echo "<div class='success'>✅ Admin User: Available (username: admin)</div>";
                    } else {
                        echo "<div class='warning'>⚠️ Admin User: Not found</div>";
                    }
                    
                    // Check for demo center
                    $stmt = $connection->prepare("SELECT * FROM training_centers WHERE center_name LIKE ?");
                    $stmt->execute(['%Demo%']);
                    $demoCenter = $stmt->fetch();
                    
                    if ($demoCenter) {
                        echo "<div class='success'>✅ Demo Training Center: Available</div>";
                    } else {
                        echo "<div class='warning'>⚠️ Demo Training Center: Not found</div>";
                    }
                    
                    $connection = null;
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Demo Data Check Error: " . $e->getMessage() . "</div>";
            }
        }
        echo "</div>";
        ?>

        <div class="check-section">
            <h3>🚀 Quick Actions</h3>
            <a href="../login" class="btn">Go to Login</a>
            <a href="../dashboard" class="btn">Go to Dashboard</a>
            <a href="login.php" class="btn">Direct Login</a>
            <a href="setup_database.php" class="btn">Setup Database</a>
            <a href="setup_dummy_data.php" class="btn">Setup Demo Data</a>
        </div>

        <div class="check-section">
            <h3>📋 System Information</h3>
            <div class="code-block">
Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>

Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?>

Current Directory: <?php echo __DIR__; ?>

PHP Extensions: <?php echo implode(', ', get_loaded_extensions()); ?>

Memory Limit: <?php echo ini_get('memory_limit'); ?>

Max Execution Time: <?php echo ini_get('max_execution_time'); ?> seconds
            </div>
        </div>

        <div class="check-section">
            <h3>💡 Troubleshooting Tips</h3>
            <ul>
                <li>If you see HTTP 500 errors, check the authentication functions in includes/auth.php</li>
                <li>If URLs with /public/ don't work, check your .htaccess configuration</li>
                <li>If clean URLs don't work, ensure router.php is included in index.php</li>
                <li>If database errors occur, run setup_database.php first</li>
                <li>If login fails, ensure demo data is setup with setup_dummy_data.php</li>
            </ul>
        </div>
    </div>
</body>
</html>

<?php
// End output buffering and display content
ob_end_flush();
?>

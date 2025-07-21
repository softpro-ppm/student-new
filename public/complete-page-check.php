<?php
session_start();
require_once '../includes/auth.php';

// Auto-login for testing
if (!isLoggedIn()) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_type'] = 'admin';
    $_SESSION['user_name'] = 'System Admin';
}

echo "<h1>Complete Page Checker</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.page-test { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
.success { background: #d4edda; border-color: #c3e6cb; }
.error { background: #f8d7da; border-color: #f5c6cb; }
.warning { background: #fff3cd; border-color: #ffeaa7; }
.info { background: #d1ecf1; border-color: #bee5eb; }
iframe { width: 100%; height: 400px; border: 1px solid #ccc; margin-top: 10px; }
</style>";

// List of all main pages to test
$pages = [
    'dashboard.php' => 'Dashboard',
    'students.php' => 'Students Management',
    'training-centers.php' => 'Training Centers',
    'batches.php' => 'Batches Management',
    'masters.php' => 'Masters Data',
    'assessments.php' => 'Assessments',
    'fees.php' => 'Fees Management',
    'reports.php' => 'Reports'
];

echo "<div class='info page-test'>";
echo "<h3>Page Status Overview</h3>";
echo "<p>Testing all main system pages for functionality and content...</p>";
echo "</div>";

foreach ($pages as $page => $title) {
    echo "<div class='page-test'>";
    echo "<h3>$title ($page)</h3>";
    
    // Check if file exists
    if (file_exists($page)) {
        echo "<span style='color: green;'>✓ File exists</span> | ";
        
        // Check file size
        $size = filesize($page);
        echo "<span style='color: blue;'>Size: " . number_format($size) . " bytes</span> | ";
        
        if ($size < 1000) {
            echo "<span style='color: orange;'>⚠ Small file size</span> | ";
        }
        
        // Check for basic PHP syntax errors
        $output = [];
        $return_var = 0;
        exec("php -l \"$page\" 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "<span style='color: green;'>✓ Syntax OK</span> | ";
        } else {
            echo "<span style='color: red;'>✗ Syntax Error</span> | ";
        }
        
        // Quick content check
        $content = file_get_contents($page);
        if (strpos($content, 'renderSidebar') !== false) {
            echo "<span style='color: green;'>✓ Has Sidebar</span> | ";
        } else {
            echo "<span style='color: orange;'>⚠ No Sidebar</span> | ";
        }
        
        if (strpos($content, 'renderHeader') !== false || strpos($content, 'layout.php') !== false) {
            echo "<span style='color: green;'>✓ Has Layout</span> | ";
        } else {
            echo "<span style='color: orange;'>⚠ No Layout</span> | ";
        }
        
        if (strpos($content, 'getConnection') !== false || strpos($content, 'Database') !== false) {
            echo "<span style='color: green;'>✓ Has DB Connection</span>";
        } else {
            echo "<span style='color: red;'>✗ No DB Connection</span>";
        }
        
        echo "<br><br>";
        echo "<a href='$page' target='_blank' style='background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Open Page</a>";
        
        // Try to load the page in an iframe for visual check
        echo "<iframe src='$page' loading='lazy'></iframe>";
        
    } else {
        echo "<span style='color: red;'>✗ File does not exist</span>";
    }
    
    echo "</div>";
}

// Additional system checks
echo "<div class='info page-test'>";
echo "<h3>System Health Checks</h3>";

// Check includes
$includes = ['../includes/auth.php', '../includes/layout.php', '../config/database-simple.php'];
foreach ($includes as $include) {
    if (file_exists($include)) {
        echo "<span style='color: green;'>✓ $include exists</span><br>";
    } else {
        echo "<span style='color: red;'>✗ $include missing</span><br>";
    }
}

echo "<hr>";

// Check authentication functions
try {
    require_once '../includes/auth.php';
    $user = getCurrentUser();
    echo "<span style='color: green;'>✓ Auth functions working</span><br>";
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Auth error: " . $e->getMessage() . "</span><br>";
}

// Check database connection
try {
    // Use getConnection() function which should already be available from auth.php
    $db = getConnection();
    if ($db) {
        echo "<span style='color: green;'>✓ Database connection working</span><br>";
    } else {
        echo "<span style='color: red;'>✗ Database connection failed</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Database error: " . $e->getMessage() . "</span><br>";
}

echo "</div>";

echo "<div class='page-test'>";
echo "<h3>Quick Actions</h3>";
echo "<a href='system-diagnostic.php' target='_blank'>System Diagnostic</a> | ";
echo "<a href='fix-database-schema.php' target='_blank'>Fix Database Schema</a> | ";
echo "<a href='test-columns-quick.php' target='_blank'>Test Columns</a>";
echo "</div>";
?>

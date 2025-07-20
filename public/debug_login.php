<?php
// Simple debug script to check if login works
echo "<h1>Login Debug</h1>";

// Test include paths
$includeAuth = __DIR__ . '/../includes/auth.php';
$includeDb = __DIR__ . '/../config/database.php';

echo "<p>Auth path: " . $includeAuth . " - " . (file_exists($includeAuth) ? "EXISTS" : "NOT FOUND") . "</p>";
echo "<p>DB path: " . $includeDb . " - " . (file_exists($includeDb) ? "EXISTS" : "NOT FOUND") . "</p>";

if (file_exists($includeDb)) {
    require_once $includeDb;
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p style='color: green;'>✓ Database connected successfully</p>";
        
        // Test login directly
        if (file_exists($includeAuth)) {
            session_start();
            require_once $includeAuth;
            
            $auth = new Auth();
            
            // Test admin login
            $result = $auth->login('admin', 'admin123');
            echo "<p>Admin login test: " . ($result['success'] ? "SUCCESS" : "FAILED - " . $result['message']) . "</p>";
            
            // Test student login
            $result2 = $auth->login('9999999999', 'softpro@123');
            echo "<p>Student login test: " . ($result2['success'] ? "SUCCESS" : "FAILED - " . $result2['message']) . "</p>";
            
        } else {
            echo "<p style='color: red;'>✗ Auth file not found</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Database file not found</p>";
}

echo "<br><a href='login.php'>← Back to Login</a>";
?>

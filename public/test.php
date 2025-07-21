<?php
// Simple test file to check database connection and functions
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

echo "<!DOCTYPE html><html><head><title>System Test</title></head><body>";
echo "<h1>Student Management System - Connection Test</h1>";

// Test database connection
echo "<h2>Database Connection Test:</h2>";
try {
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        
        // Test if users table exists
        $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Users table exists!</p>";
            
            // Check if admin user exists
            $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ Admin user exists!</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ Admin user not found!</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Users table not found!</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Database connection failed!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test auth functions
echo "<h2>Auth Functions Test:</h2>";
try {
    if (function_exists('isLoggedIn')) {
        echo "<p style='color: green;'>✅ isLoggedIn() function exists!</p>";
        $loginStatus = isLoggedIn() ? 'Yes' : 'No';
        echo "<p>Current login status: " . $loginStatus . "</p>";
    } else {
        echo "<p style='color: red;'>❌ isLoggedIn() function not found!</p>";
    }
    
    if (function_exists('getCurrentUser')) {
        echo "<p style='color: green;'>✅ getCurrentUser() function exists!</p>";
    } else {
        echo "<p style='color: red;'>❌ getCurrentUser() function not found!</p>";
    }
    
    if (function_exists('getCurrentUserRole')) {
        echo "<p style='color: green;'>✅ getCurrentUserRole() function exists!</p>";
    } else {
        echo "<p style='color: red;'>❌ getCurrentUserRole() function not found!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Auth functions error: " . $e->getMessage() . "</p>";
}

echo "<h2>Session Information:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p><a href='login.php'>Go to Login Page</a></p>";
echo "</body></html>";
?>

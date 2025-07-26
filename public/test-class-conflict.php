<?php
// Test for Database class conflicts
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Class Conflict Test</h1>";

echo "<p>Testing database.php inclusion...</p>";

try {
    require_once '../config/database.php';
    echo "<p style='color: green;'>✓ database.php loaded successfully</p>";

    if (class_exists('Database')) {
        echo "<p style='color: green;'>✓ Database class exists</p>";
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            echo "<p style='color: green;'>✓ Database connection successful</p>";
        } else {
            echo "<p style='color: red;'>✗ Database connection failed</p>";
        }

    } else {
        echo "<p style='color: red;'>✗ Database class not found in database.php</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php'>Test Login</a> | <a href='config-check.php'>Full System Check</a></p>";
?>

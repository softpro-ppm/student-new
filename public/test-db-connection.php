<?php
// Simple Database Connection Test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

try {
    require_once '../config/database.php';
    
    echo "<p>✓ database-simple.php loaded successfully</p>";
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p>✓ Database connection successful!</p>";
        
        // Test basic query
        $stmt = $db->query("SELECT DATABASE() as current_db");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✓ Connected to database: " . $result['current_db'] . "</p>";
        
        // Check if tables exist
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p>✓ Found " . count($tables) . " tables in database:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        if (count($tables) == 0) {
            echo "<p style='color: orange;'>⚠ No tables found. Run <a href='setup_database.php'>setup_database.php</a> to create tables.</p>";
        } else {
            echo "<p style='color: green;'>✓ Database appears to be set up correctly!</p>";
            echo "<p><a href='login.php'>Go to Login Page</a> | <a href='config-check.php'>Full System Check</a></p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>

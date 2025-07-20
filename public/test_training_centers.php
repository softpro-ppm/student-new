<?php
// Test script to check training-centers.php syntax and basic functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Training Centers Test</h1>";

// Test file includes
$includeAuth = __DIR__ . '/../includes/auth.php';
$includeDb = __DIR__ . '/../config/database.php';

echo "<p>Auth path: " . $includeAuth . " - " . (file_exists($includeAuth) ? "EXISTS" : "NOT FOUND") . "</p>";
echo "<p>DB path: " . $includeDb . " - " . (file_exists($includeDb) ? "EXISTS" : "NOT FOUND") . "</p>";

if (file_exists($includeDb) && file_exists($includeAuth)) {
    try {
        require_once $includeDb;
        echo "<p style='color: green;'>✓ Database config loaded</p>";
        
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            echo "<p style='color: green;'>✓ Database connected</p>";
            
            // Check if training_centers table exists
            $stmt = $db->prepare("SHOW TABLES LIKE 'training_centers'");
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ training_centers table exists</p>";
                
                // Show table structure
                $stmt = $db->prepare("DESCRIBE training_centers");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h3>Training Centers Table Structure:</h3>";
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Field</th><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Null</th><th style='padding: 8px;'>Key</th></tr>";
                foreach ($columns as $column) {
                    echo "<tr>";
                    echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Field']) . "</td>";
                    echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Type']) . "</td>";
                    echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Null']) . "</td>";
                    echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Key']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Test basic query
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM training_centers");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p>Current training centers count: " . $count['count'] . "</p>";
                
            } else {
                echo "<p style='color: red;'>✗ training_centers table does not exist</p>";
            }
            
            // Test auth loading (without role check)
            session_start();
            require_once $includeAuth;
            echo "<p style='color: green;'>✓ Auth class loaded</p>";
            
            echo "<p style='color: green;'>✓ All basic components working</p>";
            
        } else {
            echo "<p style='color: red;'>✗ Database connection failed</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        echo "<pre>Stack trace: " . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<p style='color: red;'>✗ Required files not found</p>";
}

echo "<br><br>";
echo "<p><a href='training-centers.php'>← Try Training Centers Page</a></p>";
echo "<p><a href='migrate_db.php'>← Run Database Migration</a></p>";
echo "<p><a href='login.php'>← Back to Login</a></p>";
?>

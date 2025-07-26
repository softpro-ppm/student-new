<?php
// Check results table structure
require_once '../config/database.php';

try {
    $db = getConnection();
    
    // Check results table structure
    $stmt = $db->prepare("DESCRIBE results");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Results Table Structure:</h2>\n";
    foreach ($columns as $column) {
        echo "<p>{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']} - {$column['Default']} - {$column['Extra']}</p>\n";
    }
    
    // Check if status column exists
    $hasStatus = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            $hasStatus = true;
            break;
        }
    }
    
    if (!$hasStatus) {
        echo "<p>Status column missing. Adding it now...</p>\n";
        $alterQuery = "ALTER TABLE results ADD COLUMN status ENUM('pass', 'fail') NOT NULL DEFAULT 'fail'";
        $db->exec($alterQuery);
        echo "<p>✓ Status column added successfully</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}
?>

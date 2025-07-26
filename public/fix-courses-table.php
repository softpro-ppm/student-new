<?php
// Check courses table structure
require_once '../config/database.php';

try {
    $db = getConnection();
    
    // Check courses table structure
    $stmt = $db->prepare("DESCRIBE courses");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Courses Table Structure:</h2>\n";
    foreach ($columns as $column) {
        echo "<p>{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']} - {$column['Default']} - {$column['Extra']}</p>\n";
    }
    
    // Check if sector_id column exists
    $hasSectorId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'sector_id') {
            $hasSectorId = true;
            break;
        }
    }
    
    if (!$hasSectorId) {
        echo "<p>sector_id column missing. Adding it now...</p>\n";
        $alterQuery = "ALTER TABLE courses ADD COLUMN sector_id INT NULL";
        $db->exec($alterQuery);
        echo "<p>✓ sector_id column added successfully</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}
?>

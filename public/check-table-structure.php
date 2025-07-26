<?php
require_once '../config/database.php';

try {
    $db = getConnection();
    
    echo "STUDENTS TABLE STRUCTURE:\n";
    echo "========================\n";
    $stmt = $db->query('DESCRIBE students');
    foreach($stmt->fetchAll() as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
    
    echo "\nFEES TABLE STRUCTURE:\n";
    echo "====================\n";
    $stmt = $db->query('DESCRIBE fees');
    foreach($stmt->fetchAll() as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
    
    echo "\nCHECKING FOR TRAINING_CENTERS TABLE:\n";
    echo "====================================\n";
    try {
        $stmt = $db->query('SHOW TABLES LIKE "training_centers"');
        if ($stmt->rowCount() > 0) {
            echo "✓ training_centers table exists\n";
            $stmt = $db->query('DESCRIBE training_centers');
            foreach($stmt->fetchAll() as $col) {
                echo $col['Field'] . ' - ' . $col['Type'] . "\n";
            }
        } else {
            echo "✗ training_centers table does not exist\n";
        }
    } catch (Exception $e) {
        echo "✗ Error checking training_centers: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

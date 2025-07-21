<?php
require_once '../config/database.php';

try {
    $db = getConnection();
    
    echo "<h2>Database Schema Check</h2>";
    
    // Check batches table
    echo "<h3>Batches Table:</h3>";
    $stmt = $db->query("DESCRIBE batches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "</pre>";
    
    // Check training_centers table
    echo "<h3>Training Centers Table:</h3>";
    $stmt = $db->query("DESCRIBE training_centers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "</pre>";
    
    // Check students table
    echo "<h3>Students Table:</h3>";
    $stmt = $db->query("DESCRIBE students");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "</pre>";
    
    // Check courses table
    echo "<h3>Courses Table:</h3>";
    $stmt = $db->query("DESCRIBE courses");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

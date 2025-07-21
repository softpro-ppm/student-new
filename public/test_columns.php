<?php
require_once '../config/database.php';

try {
    $db = getConnection();
    
    echo "<h2>Schema Analysis</h2>";
    
    // Test actual column names in batches table
    echo "<h3>Batches Table Columns:</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM batches");
    $batchColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($batchColumns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
    
    // Test actual column names in training_centers table
    echo "<h3>Training Centers Table Columns:</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM training_centers");
    $tcColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tcColumns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
    
    // Test working query
    echo "<h3>Test Query:</h3>";
    $testQuery = "SELECT 
        b.id as batch_id,
        b.name as batch_name,
        tc.id as tc_id,
        tc.name as center_name
    FROM batches b 
    LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
    LIMIT 3";
    
    $stmt = $db->prepare($testQuery);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($results);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

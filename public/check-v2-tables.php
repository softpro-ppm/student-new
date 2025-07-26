<?php
require_once '../config/database-v2.php';

try {
    $conn = getV2Connection();
    $stmt = $conn->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables in student_management_v2:</h3>";
    if (empty($tables)) {
        echo "<p>No tables found in the database.</p>";
    } else {
        echo "<ul>";
        foreach($tables as $table) {
            echo "<li>" . $table . "</li>";
        }
        echo "</ul>";
    }
    
    // Also check for the specific missing tables
    $missingTables = ['student_batches', 'fee_payments', 'courses'];
    echo "<h3>Missing tables status:</h3>";
    foreach($missingTables as $table) {
        $exists = in_array($table, $tables);
        echo "<p><strong>$table:</strong> " . ($exists ? "EXISTS" : "MISSING") . "</p>";
    }
    
} catch(Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

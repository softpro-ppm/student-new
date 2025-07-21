<?php
require_once '../config/database.php';

echo "<h2>Column Structure Analysis</h2>";
echo "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    $db = getConnection();
    
    // Check batches table structure
    echo "<h3>Batches Table Structure:</h3>";
    $stmt = $db->query("DESCRIBE batches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
    
    // Test sample data from batches
    echo "<h3>Sample Batches Data:</h3>";
    $stmt = $db->query("SELECT * FROM batches LIMIT 3");
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($batches) {
        echo "<table><tr>";
        foreach (array_keys($batches[0]) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";
        foreach ($batches as $batch) {
            echo "<tr>";
            foreach ($batch as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No batches data found.";
    }
    
    // Check training_centers table structure
    echo "<h3>Training Centers Table Structure:</h3>";
    $stmt = $db->query("DESCRIBE training_centers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
    
    // Test sample data from training_centers  
    echo "<h3>Sample Training Centers Data:</h3>";
    $stmt = $db->query("SELECT * FROM training_centers LIMIT 3");
    $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($centers) {
        echo "<table><tr>";
        foreach (array_keys($centers[0]) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";
        foreach ($centers as $center) {
            echo "<tr>";
            foreach ($center as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No training centers data found.";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

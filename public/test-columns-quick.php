<?php
require_once '../config/database.php';

$db = getConnection();

echo "<h2>Testing Column Names</h2>";

try {
    // Test if batch_name column exists
    echo "<h3>Testing batches.batch_name:</h3>";
    $stmt = $db->query("SELECT batch_name FROM batches LIMIT 1");
    $result = $stmt->fetch();
    echo "✓ batches.batch_name exists<br>";
} catch (Exception $e) {
    echo "✗ batches.batch_name error: " . $e->getMessage() . "<br>";
    
    // Try with 'name' column
    try {
        echo "<h3>Testing batches.name:</h3>";
        $stmt = $db->query("SELECT name FROM batches LIMIT 1");
        $result = $stmt->fetch();
        echo "✓ batches.name exists<br>";
    } catch (Exception $e2) {
        echo "✗ batches.name error: " . $e2->getMessage() . "<br>";
    }
}

try {
    // Test if center_name column exists
    echo "<h3>Testing training_centers.center_name:</h3>";
    $stmt = $db->query("SELECT center_name FROM training_centers LIMIT 1");
    $result = $stmt->fetch();
    echo "✓ training_centers.center_name exists<br>";
} catch (Exception $e) {
    echo "✗ training_centers.center_name error: " . $e->getMessage() . "<br>";
    
    // Try with 'name' column
    try {
        echo "<h3>Testing training_centers.name:</h3>";
        $stmt = $db->query("SELECT name FROM training_centers LIMIT 1");
        $result = $stmt->fetch();
        echo "✓ training_centers.name exists<br>";
    } catch (Exception $e2) {
        echo "✗ training_centers.name error: " . $e2->getMessage() . "<br>";
    }
}

// Show all columns
echo "<h3>All Batches Columns:</h3>";
$stmt = $db->query("SHOW COLUMNS FROM batches");
while ($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . "<br>";
}

echo "<h3>All Training Centers Columns:</h3>";
$stmt = $db->query("SHOW COLUMNS FROM training_centers");
while ($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . "<br>";
}
?>

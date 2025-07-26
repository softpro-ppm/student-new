<?php
require_once '../config/database-v1.php';

echo "<h2>Testing v1.0 Database Connection</h2>";

try {
    $db = getOldConnection();
    echo "✅ Database connection successful<br><br>";
    
    // Test query to get training centers
    $stmt = $db->query("SELECT * FROM tbltrainingcenter LIMIT 5");
    $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Training Centers in v1.0 Database:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>SPOC</th><th>Email</th><th>Contact</th></tr>";
    
    foreach ($centers as $center) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($center['TrainingcenterId']) . "</td>";
        echo "<td>" . htmlspecialchars($center['trainingcentername']) . "</td>";
        echo "<td>" . htmlspecialchars($center['spocname']) . "</td>";
        echo "<td>" . htmlspecialchars($center['spocemailaddress']) . "</td>";
        echo "<td>" . htmlspecialchars($center['spoccontact']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p>✅ v1.0 database is working correctly!</p>";
    echo "<p><a href='training-centers.php'>Go to Training Centers Management</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

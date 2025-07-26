<?php
require_once '../config/database-v2.php';

echo "<h2>🔧 Adding Missing Columns to Courses Table</h2>";

try {
    $conn = getV2Connection();
    
    // Check existing columns
    $columns = $conn->query("DESCRIBE courses")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Current columns: " . implode(', ', $columns) . "</p>";
    
    // Add missing columns
    $alterQueries = [];
    
    if (!in_array('registration_fee', $columns)) {
        $alterQueries[] = "ALTER TABLE courses ADD COLUMN registration_fee DECIMAL(10,2) DEFAULT 500 AFTER course_fee";
    }
    
    if (!in_array('course_duration', $columns)) {
        $alterQueries[] = "ALTER TABLE courses ADD COLUMN course_duration INT DEFAULT 90 AFTER duration_hours";
    }
    
    // Remove job_role column if it exists
    if (in_array('job_role', $columns)) {
        $alterQueries[] = "ALTER TABLE courses DROP COLUMN job_role";
    }
    
    if (in_array('job_role_id', $columns)) {
        $alterQueries[] = "ALTER TABLE courses DROP COLUMN job_role_id";
    }
    
    foreach ($alterQueries as $query) {
        try {
            $conn->exec($query);
            echo "✅ Executed: " . htmlspecialchars($query) . "<br>";
        } catch (PDOException $e) {
            echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }
    
    echo "<p><strong>Courses table structure updated successfully!</strong></p>";
    echo "<a href='courses-v2.php'>Go to Courses Page</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

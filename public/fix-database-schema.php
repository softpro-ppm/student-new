<?php
require_once '../config/database.php';

echo "<h2>Database Schema Fix</h2>";
echo "<style>
.success { color: green; }
.error { color: red; }
.info { color: blue; }
</style>";

try {
    $db = getConnection();
    
    // Check and fix batches table
    echo "<h3>Checking Batches Table...</h3>";
    
    $stmt = $db->query("SHOW COLUMNS FROM batches");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('batch_name', $columns)) {
        if (in_array('name', $columns)) {
            echo "<div class='info'>Adding batch_name column as alias for name...</div>";
            try {
                $db->exec("ALTER TABLE batches ADD COLUMN batch_name VARCHAR(255) AFTER name");
                $db->exec("UPDATE batches SET batch_name = name WHERE batch_name IS NULL");
                echo "<div class='success'>✓ Added batch_name column</div>";
            } catch (Exception $e) {
                echo "<div class='error'>✗ Error adding batch_name: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='error'>✗ Neither batch_name nor name column exists!</div>";
        }
    } else {
        echo "<div class='success'>✓ batch_name column already exists</div>";
    }
    
    // Check and fix training_centers table
    echo "<h3>Checking Training Centers Table...</h3>";
    
    $stmt = $db->query("SHOW COLUMNS FROM training_centers");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('center_name', $columns)) {
        if (in_array('name', $columns)) {
            echo "<div class='info'>Adding center_name column as alias for name...</div>";
            try {
                $db->exec("ALTER TABLE training_centers ADD COLUMN center_name VARCHAR(255) AFTER name");
                $db->exec("UPDATE training_centers SET center_name = name WHERE center_name IS NULL");
                echo "<div class='success'>✓ Added center_name column</div>";
            } catch (Exception $e) {
                echo "<div class='error'>✗ Error adding center_name: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='error'>✗ Neither center_name nor name column exists!</div>";
        }
    } else {
        echo "<div class='success'>✓ center_name column already exists</div>";
    }
    
    // Test the queries now
    echo "<h3>Testing Fixed Queries...</h3>";
    
    try {
        $studentsQuery = "
            SELECT s.*, c.name as course_name, b.batch_name, tc.center_name 
            FROM students s 
            LEFT JOIN courses c ON s.course_id = c.id 
            LEFT JOIN batches b ON s.batch_id = b.id 
            LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
            WHERE s.status != 'deleted'
            LIMIT 1
        ";
        $stmt = $db->prepare($studentsQuery);
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<div class='success'>✓ Students query working</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Students query error: " . $e->getMessage() . "</div>";
    }
    
    try {
        $batchesQuery = "
            SELECT b.*, c.name as course_name, tc.center_name,
                   (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.id AND s.status = 'active') as enrolled_students
            FROM batches b 
            LEFT JOIN courses c ON b.course_id = c.id 
            LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
            WHERE b.status != 'deleted'
            LIMIT 1
        ";
        $stmt = $db->prepare($batchesQuery);
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<div class='success'>✓ Batches query working</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Batches query error: " . $e->getMessage() . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Database connection error: " . $e->getMessage() . "</div>";
}

echo "<h3>Quick Links to Test:</h3>";
echo "<a href='students.php' target='_blank'>Test Students Page</a> | ";
echo "<a href='batches.php' target='_blank'>Test Batches Page</a> | ";
echo "<a href='system-diagnostic.php' target='_blank'>Run Diagnostics</a>";
?>

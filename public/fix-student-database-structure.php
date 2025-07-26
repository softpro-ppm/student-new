<?php
/**
 * Fix students table structure - Remove city column from database schema
 * This script removes the city column from the students table as it's no longer used in v2.0
 */

require_once '../config/database.php';

try {
    echo "<h2>🔧 Fixing Students Table Structure</h2>\n";
    
    // First check if city column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM students LIKE 'city'");
    if ($checkColumn->rowCount() > 0) {
        echo "✅ City column found in students table, removing...\n";
        
        // Drop the city column
        $conn->exec("ALTER TABLE students DROP COLUMN city");
        echo "✅ City column removed successfully!\n";
    } else {
        echo "ℹ️ City column already removed from students table.\n";
    }
    
    // Verify table structure
    echo "\n<h3>📋 Current Students Table Structure:</h3>\n";
    $columns = $conn->query("SHOW COLUMNS FROM students");
    while ($column = $columns->fetch()) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n✅ Students table structure update completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

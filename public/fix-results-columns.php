<?php
// Add missing columns to results table
require_once '../config/database.php';

try {
    $db = getConnection();
    
    // Check results table structure
    $stmt = $db->prepare("DESCRIBE results");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnNames = array_column($columns, 'Field');
    
    // Add missing columns
    if (!in_array('total_marks', $columnNames)) {
        echo "Adding 'total_marks' column...\n";
        $db->exec("ALTER TABLE results ADD COLUMN total_marks INT DEFAULT 100");
        echo "✓ 'total_marks' column added\n";
    } else {
        echo "✓ 'total_marks' column already exists\n";
    }
    
    if (!in_array('attempt_number', $columnNames)) {
        echo "Adding 'attempt_number' column...\n";
        $db->exec("ALTER TABLE results ADD COLUMN attempt_number INT DEFAULT 1");
        echo "✓ 'attempt_number' column added\n";
    } else {
        echo "✓ 'attempt_number' column already exists\n";
    }
    
    if (!in_array('completed_at', $columnNames)) {
        echo "Adding 'completed_at' column...\n";
        $db->exec("ALTER TABLE results ADD COLUMN completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✓ 'completed_at' column added\n";
    } else {
        echo "✓ 'completed_at' column already exists\n";
    }
    
    // Update marks_obtained to INT if it's DECIMAL
    $marksColumn = null;
    foreach ($columns as $column) {
        if ($column['Field'] === 'marks_obtained') {
            $marksColumn = $column;
            break;
        }
    }
    
    if ($marksColumn && strpos($marksColumn['Type'], 'decimal') !== false) {
        echo "Converting 'marks_obtained' to INT...\n";
        $db->exec("ALTER TABLE results MODIFY COLUMN marks_obtained INT NULL");
        echo "✓ 'marks_obtained' converted to INT\n";
    }
    
    echo "\n✓ Results table is ready!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

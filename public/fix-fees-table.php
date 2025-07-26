<?php
// Fix fees table structure
require_once '../config/database.php';

try {
    $db = getConnection();
    
    // Check fees table structure
    $stmt = $db->prepare("DESCRIBE fees");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current fees table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    $columnNames = array_column($columns, 'Field');
    
    // Add missing columns
    if (!in_array('fee_type', $columnNames)) {
        echo "\nAdding 'fee_type' column...\n";
        $db->exec("ALTER TABLE fees ADD COLUMN fee_type ENUM('admission', 'course', 'exam', 'certificate', 'other') DEFAULT 'course'");
        echo "✓ 'fee_type' column added\n";
    }
    
    if (!in_array('due_date', $columnNames)) {
        echo "Adding 'due_date' column...\n";
        $db->exec("ALTER TABLE fees ADD COLUMN due_date DATE NULL");
        echo "✓ 'due_date' column added\n";
    }
    
    if (!in_array('paid_date', $columnNames)) {
        echo "Adding 'paid_date' column...\n";
        $db->exec("ALTER TABLE fees ADD COLUMN paid_date DATE NULL");
        echo "✓ 'paid_date' column added\n";
    }
    
    if (!in_array('payment_method', $columnNames)) {
        echo "Adding 'payment_method' column...\n";
        $db->exec("ALTER TABLE fees ADD COLUMN payment_method VARCHAR(50) NULL");
        echo "✓ 'payment_method' column added\n";
    }
    
    if (!in_array('transaction_id', $columnNames)) {
        echo "Adding 'transaction_id' column...\n";
        $db->exec("ALTER TABLE fees ADD COLUMN transaction_id VARCHAR(100) NULL");
        echo "✓ 'transaction_id' column added\n";
    }
    
    if (!in_array('notes', $columnNames)) {
        echo "Adding 'notes' column...\n";
        $db->exec("ALTER TABLE fees ADD COLUMN notes TEXT NULL");
        echo "✓ 'notes' column added\n";
    }
    
    if (!in_array('approved_by', $columnNames)) {
        echo "Adding 'approved_by' column...\n";
        $db->exec("ALTER TABLE fees ADD COLUMN approved_by INT NULL");
        echo "✓ 'approved_by' column added\n";
    }
    
    echo "\n✓ Fees table structure fixed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

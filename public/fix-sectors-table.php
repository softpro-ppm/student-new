<?php
// Fix sectors table structure
require_once '../config/database.php';

try {
    $db = getConnection();
    
    // Check sectors table structure
    $stmt = $db->prepare("DESCRIBE sectors");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current sectors table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    $columnNames = array_column($columns, 'Field');
    
    // Add missing columns
    if (!in_array('code', $columnNames)) {
        echo "\nAdding 'code' column...\n";
        $db->exec("ALTER TABLE sectors ADD COLUMN code VARCHAR(50) UNIQUE NULL");
        echo "✓ 'code' column added\n";
    }
    
    if (!in_array('description', $columnNames)) {
        echo "Adding 'description' column...\n";
        $db->exec("ALTER TABLE sectors ADD COLUMN description TEXT NULL");
        echo "✓ 'description' column added\n";
    }
    
    if (!in_array('status', $columnNames)) {
        echo "Adding 'status' column...\n";
        $db->exec("ALTER TABLE sectors ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
        echo "✓ 'status' column added\n";
    }
    
    // Update existing sectors with codes if they don't have them
    $stmt = $db->prepare("SELECT id, name FROM sectors WHERE code IS NULL OR code = ''");
    $stmt->execute();
    $sectorsWithoutCode = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sectorsWithoutCode)) {
        echo "\nUpdating sectors without codes...\n";
        foreach ($sectorsWithoutCode as $sector) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sector['name']), 0, 3));
            if (strlen($code) < 2) {
                $code = 'SEC' . $sector['id'];
            }
            
            $updateStmt = $db->prepare("UPDATE sectors SET code = ? WHERE id = ?");
            $updateStmt->execute([$code, $sector['id']]);
            echo "- Updated sector '{$sector['name']}' with code '$code'\n";
        }
    }
    
    echo "\n✓ Sectors table structure fixed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

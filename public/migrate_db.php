<?php
// Database migration script to fix training-centers issues
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed!');
}

echo "<h1>Database Migration for Training Centers</h1>";

try {
    // Check if training_center_id column exists in users table
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'training_center_id'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "<p>Adding training_center_id column to users table...</p>";
        $db->exec("ALTER TABLE users ADD COLUMN training_center_id INT DEFAULT NULL");
        echo "<p style='color: green;'>✓ Added training_center_id column to users table</p>";
    } else {
        echo "<p style='color: blue;'>ℹ training_center_id column already exists in users table</p>";
    }
    
    // Check if password column exists in training_centers table
    $stmt = $db->prepare("SHOW COLUMNS FROM training_centers LIKE 'password'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "<p>Adding password column to training_centers table...</p>";
        $db->exec("ALTER TABLE training_centers ADD COLUMN password VARCHAR(255) DEFAULT NULL");
        echo "<p style='color: green;'>✓ Added password column to training_centers table</p>";
    } else {
        echo "<p style='color: blue;'>ℹ password column already exists in training_centers table</p>";
    }
    
    // Check if updated_at column exists in training_centers table
    $stmt = $db->prepare("SHOW COLUMNS FROM training_centers LIKE 'updated_at'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "<p>Adding updated_at column to training_centers table...</p>";
        $db->exec("ALTER TABLE training_centers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "<p style='color: green;'>✓ Added updated_at column to training_centers table</p>";
    } else {
        echo "<p style='color: blue;'>ℹ updated_at column already exists in training_centers table</p>";
    }
    
    echo "<h2>Current Table Structures:</h2>";
    
    // Show users table structure
    echo "<h3>Users Table:</h3>";
    $stmt = $db->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Field</th><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Null</th><th style='padding: 8px;'>Key</th><th style='padding: 8px;'>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show training_centers table structure
    echo "<h3>Training Centers Table:</h3>";
    $stmt = $db->prepare("DESCRIBE training_centers");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Field</th><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Null</th><th style='padding: 8px;'>Key</th><th style='padding: 8px;'>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green; font-weight: bold;'>Migration completed successfully!</p>";
    echo "<p><a href='training-centers.php'>← Go to Training Centers</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Migration Error: " . $e->getMessage() . "</p>";
}
?>

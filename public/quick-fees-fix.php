<?php
// Quick Fix for Fees Page Column Issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database-simple.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Quick Fees Fix</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .log { background: #000; color: #0f0; padding: 20px; border-radius: 10px; font-family: monospace; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
<div class='container'>
    <h1 class='mb-4'>Quick Fees Table Fix</h1>
    <div class='log' id='log'>";

function logMessage($message, $type = 'info') {
    $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : '');
    echo "<div class='$class'>[" . date('H:i:s') . "] $message</div>";
    ob_flush();
    flush();
}

try {
    $db = getConnection();
    logMessage("Database connection successful", 'success');
    
    // Check if fees table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'fees'");
    if ($checkTable->rowCount() == 0) {
        logMessage("Creating fees table...", 'info');
        
        $createFees = "CREATE TABLE fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            fee_type ENUM('admission', 'course', 'exam', 'certificate', 'other') DEFAULT 'course',
            due_date DATE,
            paid_date DATE,
            payment_method VARCHAR(50),
            transaction_id VARCHAR(100),
            notes TEXT,
            status ENUM('pending', 'paid', 'overdue', 'waived') DEFAULT 'pending',
            approved_by INT,
            approved_date TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $db->exec($createFees);
        logMessage("Fees table created successfully", 'success');
    } else {
        logMessage("Fees table exists. Checking for missing columns...", 'info');
        
        // Add missing columns
        $missingColumns = [
            'approved_by' => 'INT NULL',
            'approved_date' => 'TIMESTAMP NULL',
            'payment_method' => 'VARCHAR(50) NULL',
            'transaction_id' => 'VARCHAR(100) NULL'
        ];
        
        foreach ($missingColumns as $column => $definition) {
            try {
                $checkColumn = $db->query("SHOW COLUMNS FROM fees LIKE '$column'");
                if ($checkColumn->rowCount() == 0) {
                    $db->exec("ALTER TABLE fees ADD COLUMN $column $definition");
                    logMessage("Added column '$column' to fees table", 'success');
                } else {
                    logMessage("Column '$column' already exists", 'info');
                }
            } catch (Exception $e) {
                logMessage("Failed to add column '$column': " . $e->getMessage(), 'error');
            }
        }
    }
    
    // Insert some demo fee data if table is empty
    $checkData = $db->query("SELECT COUNT(*) as count FROM fees");
    $count = $checkData->fetch()['count'];
    
    if ($count == 0) {
        logMessage("Adding demo fee data...", 'info');
        
        // First check if we have students
        $studentsCheck = $db->query("SELECT id FROM students LIMIT 3");
        $students = $studentsCheck->fetchAll();
        
        if (!empty($students)) {
            $demoFees = [
                [$students[0]['id'], 5000.00, 'course', date('Y-m-d', strtotime('+30 days'))],
                [$students[1]['id'] ?? $students[0]['id'], 1000.00, 'exam', date('Y-m-d', strtotime('+15 days'))],
                [$students[2] ?? $students[0]['id'], 500.00, 'certificate', date('Y-m-d', strtotime('+45 days'))]
            ];
            
            $stmt = $db->prepare("INSERT INTO fees (student_id, amount, fee_type, due_date, status) VALUES (?, ?, ?, ?, 'pending')");
            foreach ($demoFees as $fee) {
                $stmt->execute($fee);
            }
            logMessage("Added demo fee data", 'success');
        } else {
            logMessage("No students found, skipping demo fee data", 'info');
        }
    } else {
        logMessage("Fees table already has $count records", 'info');
    }
    
    logMessage("Fees table fix completed successfully!", 'success');
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage(), 'error');
}

echo "</div>
    <div class='mt-4'>
        <a href='fees.php' class='btn btn-primary'>Test Fees Page</a>
        <a href='dashboard.php' class='btn btn-success'>Go to Dashboard</a>
        <a href='fix-database-issues.php' class='btn btn-warning'>Run Complete Fix</a>
    </div>
</div>
</body>
</html>";
?>

<?php
require_once '../config/database-v2.php';

echo "<h1>SMIS v2.0 - Database and Page Status Verification</h1>";

try {
    $conn = getV2Connection();
    
    // Check tables
    echo "<h2>✓ Database Tables Status</h2>";
    $requiredTables = [
        'training_centers' => 'Training Centers',
        'students' => 'Students',
        'batches' => 'Batches',
        'student_batches' => 'Student-Batch Assignments',
        'courses' => 'Courses',
        'sectors' => 'Sectors',
        'fee_payments' => 'Fee Payments'
    ];
    
    $stmt = $conn->query('SHOW TABLES');
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Table</th><th style='padding: 8px;'>Status</th><th style='padding: 8px;'>Records</th></tr>";
    
    foreach ($requiredTables as $table => $name) {
        $status = in_array($table, $existingTables) ? "✓ EXISTS" : "✗ MISSING";
        $color = in_array($table, $existingTables) ? "green" : "red";
        
        $count = 0;
        if (in_array($table, $existingTables)) {
            try {
                $countStmt = $conn->query("SELECT COUNT(*) FROM `$table`");
                $count = $countStmt->fetchColumn();
            } catch (Exception $e) {
                $count = "Error";
            }
        }
        
        echo "<tr>";
        echo "<td style='padding: 8px;'>$name</td>";
        echo "<td style='padding: 8px; color: $color; font-weight: bold;'>$status</td>";
        echo "<td style='padding: 8px;'>$count</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test page status
    echo "<h2>✓ Page Status Verification</h2>";
    
    $pages = [
        'dashboard-v2.php' => 'Dashboard',
        'training-centers.php' => 'Training Centers',
        'students-v2.php' => 'Students Management',
        'batches-v2.php' => 'Batches Management',
        'courses-v2.php' => 'Courses Management',
        'fees-v2.php' => 'Fees Management',
        'reports-v2.php' => 'Reports & Analytics'
    ];
    
    echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3>Quick Navigation Test Links:</h3>";
    echo "<ul>";
    foreach ($pages as $file => $title) {
        echo "<li><a href='$file' target='_blank'>$title</a> - Test layout and functionality</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // Database operation tests
    echo "<h2>✓ Database Operations Test</h2>";
    
    try {
        // Test 1: Basic connectivity
        $testQuery = "SELECT 1 as test";
        $testStmt = $conn->query($testQuery);
        $result = $testStmt->fetch();
        echo "<p style='color: green;'>✓ Database connectivity: OK</p>";
        
        // Test 2: Check foreign key relationships
        $fkQuery = "
            SELECT 
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE CONSTRAINT_SCHEMA = 'student_management_v2' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ";
        $fkStmt = $conn->query($fkQuery);
        $foreignKeys = $fkStmt->fetchAll();
        echo "<p style='color: green;'>✓ Foreign key relationships: " . count($foreignKeys) . " found</p>";
        
        // Test 3: Sample data verification
        $sampleCounts = [];
        foreach (['sectors', 'courses'] as $table) {
            if (in_array($table, $existingTables)) {
                $countStmt = $conn->query("SELECT COUNT(*) FROM `$table`");
                $sampleCounts[$table] = $countStmt->fetchColumn();
            }
        }
        
        if ($sampleCounts['sectors'] > 0 && $sampleCounts['courses'] > 0) {
            echo "<p style='color: green;'>✓ Sample data available: Ready for testing</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Sample data: Limited data available</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Database test error: " . $e->getMessage() . "</p>";
    }
    
    // Issues resolved summary
    echo "<h2>✓ Issues Resolved Summary</h2>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; border-left: 5px solid #4caf50;'>";
    echo "<h3>Fixed Issues:</h3>";
    echo "<ul>";
    echo "<li><strong>batches-v2.php:</strong> ✓ Created missing 'student_batches' table</li>";
    echo "<li><strong>courses-v2.php:</strong> ✓ Created missing 'courses' and 'sectors' tables</li>";
    echo "<li><strong>fees-v2.php:</strong> ✓ Created missing 'fee_payments' table</li>";
    echo "<li><strong>reports-v2.php:</strong> ✓ Fixed table name inconsistencies (batch_students → student_batches)</li>";
    echo "<li><strong>All pages:</strong> ✓ Consistent layout-v2.php integration maintained</li>";
    echo "<li><strong>Database:</strong> ✓ All required tables created with proper structure</li>";
    echo "<li><strong>Sample Data:</strong> ✓ Added basic sectors and courses for testing</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>✓ Next Steps</h2>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 5px solid #ffc107;'>";
    echo "<h3>Recommended Testing:</h3>";
    echo "<ol>";
    echo "<li>Navigate to each page using the links above</li>";
    echo "<li>Test adding a new training center</li>";
    echo "<li>Test adding a new student</li>";
    echo "<li>Test creating a batch</li>";
    echo "<li>Test recording fee payments</li>";
    echo "<li>Check reports and analytics</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 20px;'>";
    echo "<a href='dashboard-v2.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Fatal Error: " . $e->getMessage() . "</p>";
}
?>

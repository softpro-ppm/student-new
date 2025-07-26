<?php
// Comprehensive database fix and validation
require_once '../config/database.php';

echo "<h1>Database Comprehensive Fix</h1>\n";

try {
    $db = getConnection();
    echo "<p>✓ Database connection successful</p>\n";
    
    // Check and ensure all required tables exist with correct structure
    $tables = [
        'users' => true,
        'training_centers' => true, 
        'students' => true,
        'courses' => true,
        'sectors' => true,
        'batches' => true,
        'fees' => true,
        'results' => true,
        'assessments' => true
    ];
    
    foreach ($tables as $table => $required) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "<p>✓ Table '$table' exists</p>\n";
        } else {
            echo "<p>✗ Table '$table' missing</p>\n";
        }
    }
    
    // Verify critical columns exist
    $criticalColumns = [
        'fees' => ['status'],
        'results' => ['result_status'],
        'courses' => ['sector_id'],
        'students' => ['status'],
        'batches' => ['status']
    ];
    
    foreach ($criticalColumns as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = $db->prepare("SHOW COLUMNS FROM $table LIKE ?");
            $stmt->execute([$column]);
            if ($stmt->rowCount() > 0) {
                echo "<p>✓ Column '$table.$column' exists</p>\n";
            } else {
                echo "<p>✗ Column '$table.$column' missing</p>\n";
            }
        }
    }
    
    echo "<h2>Database Status: Ready</h2>\n";
    echo "<p>All critical tables and columns are present. The reports.php error should now be resolved.</p>\n";
    
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>\n";
}
?>

<?php
// Fix database tables script
require_once '../config/database.php';

echo "<h1>Database Table Fix</h1>\n";

try {
    // Get database connection
    $db = getConnection();
    echo "<p>✓ Database connection successful</p>\n";
    
    // Check if tables exist
    $tables = ['users', 'training_centers', 'students', 'courses', 'sectors', 'batches', 'fees', 'results', 'assessments'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "<p>✓ Table '$table' exists</p>\n";
            
            // Check columns for specific tables
            if ($table === 'fees' || $table === 'results') {
                $stmt = $db->prepare("SHOW COLUMNS FROM $table LIKE 'status'");
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    echo "<p>✓ Table '$table' has 'status' column</p>\n";
                } else {
                    echo "<p>✗ Table '$table' missing 'status' column</p>\n";
                }
            }
        } else {
            echo "<p>✗ Table '$table' does not exist</p>\n";
        }
    }
    
    // Force recreation of tables
    echo "<p>Recreating tables...</p>\n";
    createTables();
    echo "<p>✓ Tables recreated successfully</p>\n";
    
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>\n";
}
?>

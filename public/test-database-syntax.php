<?php
// Simple syntax check for database.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Testing database.php syntax...\n";

try {
    include_once '../config/database.php';
    echo "✅ Database.php syntax is correct!\n";
    
    // Test connection
    $db = getConnection();
    if ($db) {
        echo "✅ Database connection successful!\n";
    } else {
        echo "❌ Database connection failed\n";
    }
    
} catch (ParseError $e) {
    echo "❌ Parse Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>

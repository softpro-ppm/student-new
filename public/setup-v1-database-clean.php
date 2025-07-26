<?php
require_once '../config/database-v1.php';

function importOldDatabaseClean() {
    try {
        $conn = getOldConnection();
        echo "Connected to the old database successfully.<br>";

        // First, check if database is already populated
        $result = $conn->query("SHOW TABLES");
        $tables = $result->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "Database already contains " . count($tables) . " tables.<br>";
            echo "Tables found: " . implode(", ", $tables) . "<br><br>";
            
            // Ask user if they want to proceed
            echo "<form method='post'>";
            echo "<p>Do you want to:</p>";
            echo "<input type='radio' name='action' value='skip' checked> Skip import (database already exists)<br>";
            echo "<input type='radio' name='action' value='recreate'> Drop and recreate database<br>";
            echo "<input type='radio' name='action' value='force'> Force import anyway<br><br>";
            echo "<input type='submit' value='Proceed'>";
            echo "</form>";
            
            if (!isset($_POST['action'])) {
                return;
            }
            
            $action = $_POST['action'];
            
            if ($action === 'skip') {
                echo "Skipping import. Database is ready to use.<br>";
                testDatabaseConnection();
                return;
            } elseif ($action === 'recreate') {
                echo "Dropping and recreating database...<br>";
                $conn->exec("DROP DATABASE IF EXISTS u820431346_smis");
                $conn->exec("CREATE DATABASE u820431346_smis");
                $conn->exec("USE u820431346_smis");
            }
        }

        $sqlFile = '../u820431346_smis.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found: " . $sqlFile);
        }

        echo "Starting database import...<br>";
        
        // Use MySQL command line for better compatibility
        $mysqlPath = 'mysql'; // Adjust if needed
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'u820431346_smis';
        
        // Build command
        $command = "$mysqlPath -h $host -u $username";
        if (!empty($password)) {
            $command .= " -p$password";
        }
        $command .= " $database < \"$sqlFile\"";
        
        // Execute command
        $output = [];
        $returnVar = 0;
        exec($command . " 2>&1", $output, $returnVar);
        
        if ($returnVar === 0) {
            echo "Database imported successfully using MySQL command line.<br>";
        } else {
            echo "MySQL command failed. Falling back to PHP method...<br>";
            fallbackPHPImport($conn, $sqlFile);
        }
        
        testDatabaseConnection();

    } catch (Exception $e) {
        echo "Error importing old database: " . $e->getMessage() . "<br>";
    }
}

function fallbackPHPImport($conn, $sqlFile) {
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = splitSQLStatements($sql);
    
    $successCount = 0;
    $errorCount = 0;
    $ignoredErrors = ['1050', '1062', '1075']; // Table exists, duplicate entry, auto increment issues
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $conn->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            if (in_array($errorCode, $ignoredErrors)) {
                // Ignore expected errors
                continue;
            }
            
            $errorCount++;
            echo "Error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Fallback import completed. Success: $successCount, Errors: $errorCount<br>";
}

function splitSQLStatements($sql) {
    // Remove comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    $statements = [];
    $currentStatement = '';
    $delimiter = ';';
    
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Handle DELIMITER statements
        if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
            $delimiter = trim($matches[1]);
            continue;
        }
        
        if (empty($line)) continue;
        
        $currentStatement .= $line . "\n";
        
        // Check if statement ends with current delimiter
        if (substr(rtrim($line), -strlen($delimiter)) === $delimiter) {
            $currentStatement = substr($currentStatement, 0, -strlen($delimiter) - 1);
            $currentStatement = trim($currentStatement);
            
            if (!empty($currentStatement)) {
                $statements[] = $currentStatement;
            }
            
            $currentStatement = '';
            
            if ($delimiter !== ';') {
                $delimiter = ';';
            }
        }
    }
    
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    return $statements;
}

function testDatabaseConnection() {
    try {
        $conn = getOldConnection();
        
        // Test some basic queries
        echo "<h3>Database Connection Test:</h3>";
        
        // Show tables
        $result = $conn->query("SHOW TABLES");
        $tables = $result->fetchAll(PDO::FETCH_COLUMN);
        echo "Total tables: " . count($tables) . "<br>";
        
        // Test some key tables
        $keyTables = ['admin', 'tblcandidate', 'tblbatch', 'payment'];
        foreach ($keyTables as $table) {
            if (in_array($table, $tables)) {
                $result = $conn->query("SELECT COUNT(*) FROM $table");
                $count = $result->fetchColumn();
                echo "Table '$table': $count records<br>";
            } else {
                echo "Table '$table': NOT FOUND<br>";
            }
        }
        
        echo "<br><strong>✅ Old database (v1.0) is ready for use!</strong><br>";
        echo "You can now proceed with planning the v2.0 upgrade.<br>";
        
    } catch (Exception $e) {
        echo "❌ Database test failed: " . $e->getMessage() . "<br>";
    }
}

importOldDatabaseClean();
?>

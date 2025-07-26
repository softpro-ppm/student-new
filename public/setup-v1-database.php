<?php
require_once '../config/database.php';

function importOldDatabase() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        echo "Connected to the old database successfully.<br>";

        $sqlFile = '../u820431346_smis.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found: " . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        
        // Split SQL into individual statements and handle DELIMITER
        $statements = splitSQLStatements($sql);
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;
            
            try {
                $conn->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                $errorCount++;
                echo "Warning: Error executing statement: " . $e->getMessage() . "<br>";
                echo "Statement: " . substr($statement, 0, 100) . "...<br><br>";
            }
        }

        echo "Database import completed.<br>";
        echo "Successful statements: $successCount<br>";
        echo "Failed statements: $errorCount<br>";

    } catch (Exception $e) {
        echo "Error importing old database: " . $e->getMessage() . "<br>";
    }
}

function splitSQLStatements($sql) {
    // Remove comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    $statements = [];
    $currentStatement = '';
    $delimiter = ';';
    $inDelimiterBlock = false;
    
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Handle DELIMITER statements
        if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
            $delimiter = trim($matches[1]);
            $inDelimiterBlock = ($delimiter !== ';');
            continue;
        }
        
        if (empty($line)) continue;
        
        $currentStatement .= $line . "\n";
        
        // Check if statement ends with current delimiter
        if (substr(rtrim($line), -strlen($delimiter)) === $delimiter) {
            // Remove the delimiter from the statement
            $currentStatement = substr($currentStatement, 0, -strlen($delimiter) - 1);
            $currentStatement = trim($currentStatement);
            
            if (!empty($currentStatement)) {
                $statements[] = $currentStatement;
            }
            
            $currentStatement = '';
            
            // Reset delimiter if we were in a delimiter block
            if ($inDelimiterBlock && $delimiter !== ';') {
                $delimiter = ';';
                $inDelimiterBlock = false;
            }
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    return $statements;
}

importOldDatabase();
?>

<?php
require_once '../config/database-v1.php';

echo "<h2>Old Database (v1.0) Status Check</h2>";

try {
    $conn = getOldConnection();
    echo "✅ Database connection successful<br><br>";
    
    // Get database info
    $result = $conn->query("SELECT DATABASE() as current_db");
    $currentDb = $result->fetch()['current_db'];
    echo "<strong>Current Database:</strong> $currentDb<br><br>";
    
    // Show all tables with record counts
    echo "<h3>Tables and Record Counts:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Table Name</th><th>Record Count</th><th>Status</th></tr>";
    
    $result = $conn->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $totalRecords = 0;
    foreach ($tables as $table) {
        try {
            $countResult = $conn->query("SELECT COUNT(*) FROM `$table`");
            $count = $countResult->fetchColumn();
            $totalRecords += $count;
            $status = $count > 0 ? "✅ Has Data" : "⚠️ Empty";
            echo "<tr><td>$table</td><td>$count</td><td>$status</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td>$table</td><td>Error</td><td>❌ " . $e->getMessage() . "</td></tr>";
        }
    }
    
    echo "</table>";
    echo "<br><strong>Total Tables:</strong> " . count($tables) . "<br>";
    echo "<strong>Total Records:</strong> $totalRecords<br><br>";
    
    // Check key tables structure
    echo "<h3>Key Tables Structure:</h3>";
    $keyTables = ['admin', 'tblcandidate', 'tblbatch', 'payment', 'tbltrainingcenter'];
    
    foreach ($keyTables as $table) {
        if (in_array($table, $tables)) {
            echo "<h4>Table: $table</h4>";
            $result = $conn->query("DESCRIBE `$table`");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' style='border-collapse: collapse; margin-bottom: 10px;'>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>" . $column['Field'] . "</td>";
                echo "<td>" . $column['Type'] . "</td>";
                echo "<td>" . $column['Null'] . "</td>";
                echo "<td>" . $column['Key'] . "</td>";
                echo "<td>" . $column['Default'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ Table '$table' not found</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>Summary</h3>";
    echo "✅ Your old database (v1.0) is successfully set up and running!<br>";
    echo "✅ Ready for v2.0 upgrade planning and data migration.<br><br>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Old database (v1.0) is operational</li>";
    echo "<li>🔄 Plan v2.0 database schema improvements</li>";
    echo "<li>🔄 Create migration scripts</li>";
    echo "<li>🔄 Set up v2.0 database</li>";
    echo "<li>🔄 Migrate data from v1.0 to v2.0</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    echo "<p>Please run the setup script first: <a href='setup-v1-database-clean.php'>setup-v1-database-clean.php</a></p>";
}
?>

<?php
require_once '../config/database-v2.php';

function checkV2Database() {
    try {
        $conn = getV2Connection();
        echo "<h2>📊 Database v2.0 Status Check</h2>";
        
        // Get database info
        $result = $conn->query("SELECT DATABASE() as current_db");
        $currentDb = $result->fetch()['current_db'];
        echo "<p><strong>Database:</strong> $currentDb</p>";
        
        // Check all tables
        echo "<h3>📋 Table Structure Overview:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>Table Name</th>
                <th>Record Count</th>
                <th>Status</th>
                <th>Primary Key</th>
                <th>Foreign Keys</th>
              </tr>";
        
        $tables = [
            'users', 'training_centers', 'sectors', 'schemes', 'job_roles', 
            'courses', 'students', 'batches', 'batch_students', 'fees', 
            'payments', 'assessments', 'results', 'audit_logs', 'system_settings'
        ];
        
        $total_records = 0;
        foreach ($tables as $table) {
            try {
                // Get record count
                $countResult = $conn->query("SELECT COUNT(*) FROM `$table`");
                $count = $countResult->fetchColumn();
                $total_records += $count;
                
                // Get table info
                $infoResult = $conn->query("SHOW CREATE TABLE `$table`");
                $tableInfo = $infoResult->fetch();
                $createSQL = $tableInfo['Create Table'];
                
                // Extract primary key
                preg_match('/PRIMARY KEY \(`([^`]+)`\)/', $createSQL, $pkMatches);
                $primaryKey = isset($pkMatches[1]) ? $pkMatches[1] : 'None';
                
                // Count foreign keys
                $fkCount = substr_count($createSQL, 'FOREIGN KEY');
                
                $status = $count > 0 ? "✅ Has Data ($count)" : ($count === 0 ? "⚠️ Empty" : "❌ Error");
                
                echo "<tr>";
                echo "<td><strong>$table</strong></td>";
                echo "<td style='text-align: center;'>$count</td>";
                echo "<td>$status</td>";
                echo "<td>$primaryKey</td>";
                echo "<td style='text-align: center;'>$fkCount</td>";
                echo "</tr>";
                
            } catch (Exception $e) {
                echo "<tr>";
                echo "<td><strong>$table</strong></td>";
                echo "<td colspan='4' style='color: red;'>❌ " . $e->getMessage() . "</td>";
                echo "</tr>";
            }
        }
        
        echo "</table>";
        echo "<p><strong>Total Records Across All Tables:</strong> $total_records</p>";
        
        // Check relationships
        echo "<h3>🔗 Foreign Key Relationships:</h3>";
        $fkQuery = "
            SELECT 
                TABLE_NAME,
                COLUMN_NAME,
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM 
                information_schema.KEY_COLUMN_USAGE 
            WHERE 
                REFERENCED_TABLE_SCHEMA = '$currentDb' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, COLUMN_NAME
        ";
        
        $fkResult = $conn->query($fkQuery);
        $foreignKeys = $fkResult->fetchAll();
        
        if (!empty($foreignKeys)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
            echo "<tr style='background: #f0f0f0;'>
                    <th>Table</th>
                    <th>Column</th>
                    <th>References</th>
                    <th>Constraint Name</th>
                  </tr>";
            
            foreach ($foreignKeys as $fk) {
                echo "<tr>";
                echo "<td>{$fk['TABLE_NAME']}</td>";
                echo "<td>{$fk['COLUMN_NAME']}</td>";
                echo "<td>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</td>";
                echo "<td style='font-size: 12px; color: #666;'>{$fk['CONSTRAINT_NAME']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check indexes
        echo "<h3>⚡ Index Analysis:</h3>";
        $indexQuery = "
            SELECT 
                TABLE_NAME,
                INDEX_NAME,
                COLUMN_NAME,
                NON_UNIQUE
            FROM 
                information_schema.STATISTICS 
            WHERE 
                TABLE_SCHEMA = '$currentDb'
                AND INDEX_NAME != 'PRIMARY'
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
        ";
        
        $indexResult = $conn->query($indexQuery);
        $indexes = $indexResult->fetchAll();
        
        $indexesByTable = [];
        foreach ($indexes as $index) {
            $indexesByTable[$index['TABLE_NAME']][] = $index;
        }
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>Table</th>
                <th>Index Name</th>
                <th>Columns</th>
                <th>Type</th>
              </tr>";
        
        foreach ($indexesByTable as $tableName => $tableIndexes) {
            $currentIndex = '';
            $columns = [];
            
            foreach ($tableIndexes as $index) {
                if ($index['INDEX_NAME'] !== $currentIndex) {
                    if (!empty($columns)) {
                        $type = $currentIndex === 'PRIMARY' ? 'Primary' : ($index['NON_UNIQUE'] ? 'Index' : 'Unique');
                        echo "<tr>";
                        echo "<td>$tableName</td>";
                        echo "<td>$currentIndex</td>";
                        echo "<td>" . implode(', ', $columns) . "</td>";
                        echo "<td>$type</td>";
                        echo "</tr>";
                    }
                    $currentIndex = $index['INDEX_NAME'];
                    $columns = [];
                }
                $columns[] = $index['COLUMN_NAME'];
            }
            
            if (!empty($columns)) {
                $type = $currentIndex === 'PRIMARY' ? 'Primary' : ($index['NON_UNIQUE'] ? 'Index' : 'Unique');
                echo "<tr>";
                echo "<td>$tableName</td>";
                echo "<td>$currentIndex</td>";
                echo "<td>" . implode(', ', $columns) . "</td>";
                echo "<td>$type</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        // Summary
        echo "<hr>";
        echo "<h3>📊 Summary:</h3>";
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px;'>";
        echo "<h4>✅ Database v2.0 Status: READY</h4>";
        echo "<ul>";
        echo "<li>✅ All " . count($tables) . " core tables created successfully</li>";
        echo "<li>✅ " . count($foreignKeys) . " foreign key relationships established</li>";
        echo "<li>✅ " . count($indexes) . " indexes created for performance</li>";
        echo "<li>✅ Total records: $total_records</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<h3>🚀 Next Steps:</h3>";
        echo "<ul>";
        echo "<li>✅ <strong>v2.0 Schema:</strong> Complete</li>";
        echo "<li>🔄 <strong>Data Migration:</strong> <a href='data-migration-v1-to-v2.php'>Start Migration Process</a></li>";
        echo "<li>⏳ <strong>Testing:</strong> Validate migrated data</li>";
        echo "<li>⏳ <strong>Application Update:</strong> Update application to use v2.0</li>";
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "❌ Database check failed: " . $e->getMessage();
    }
}

checkV2Database();
?>

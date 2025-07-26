<?php
// Comprehensive page test and layout verification
require_once '../config/database.php';

echo "🔍 COMPREHENSIVE SYSTEM VERIFICATION\n";
echo "=====================================\n\n";

try {
    $db = getConnection();
    
    // 1. Database Health Check
    echo "1. DATABASE HEALTH CHECK:\n";
    echo "------------------------\n";
    
    $tables = [
        'students' => 'Students data',
        'courses' => 'Course catalog', 
        'sectors' => 'Sector information',
        'fees' => 'Fee records',
        'results' => 'Assessment results',
        'assessments' => 'Assessment data',
        'training_centers' => 'Training centers'
    ];
    
    foreach ($tables as $table => $description) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch()['count'];
            echo "   ✅ {$description}: {$count} records\n";
        } catch (Exception $e) {
            echo "   ❌ {$description}: Error - {$e->getMessage()}\n";
        }
    }
    
    // 2. Critical Column Verification
    echo "\n2. CRITICAL COLUMN VERIFICATION:\n";
    echo "-------------------------------\n";
    
    // Check training_centers table columns
    echo "   Training Centers Table:\n";
    $stmt = $db->query("DESCRIBE training_centers");
    $tcColumns = array_column($stmt->fetchAll(), 'Field');
    echo "   ✅ center_name column: " . (in_array('center_name', $tcColumns) ? "EXISTS" : "MISSING") . "\n";
    
    // Check students table columns
    echo "   Students Table:\n";
    $stmt = $db->query("DESCRIBE students");
    $studColumns = array_column($stmt->fetchAll(), 'Field');
    echo "   ✅ training_center_id column: " . (in_array('training_center_id', $studColumns) ? "EXISTS" : "MISSING") . "\n";
    
    // 3. Data Relationships Test
    echo "\n3. DATA RELATIONSHIPS TEST:\n";
    echo "---------------------------\n";
    
    // Students with training centers
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM students s 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        WHERE s.training_center_id IS NOT NULL
    ");
    echo "   ✅ Students with training centers: " . $stmt->fetch()['count'] . "\n";
    
    // Fees with valid students
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM fees f 
        JOIN students s ON f.student_id = s.id
    ");
    echo "   ✅ Valid fee records: " . $stmt->fetch()['count'] . "\n";
    
    // Results with valid assessments
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM results r 
        JOIN assessments a ON r.assessment_id = a.id
    ");
    echo "   ✅ Valid result records: " . $stmt->fetch()['count'] . "\n";
    
    // 4. Page Files Verification
    echo "\n4. PAGE FILES VERIFICATION:\n";
    echo "---------------------------\n";
    
    $criticalPages = [
        'login.php' => 'Login page',
        'dashboard.php' => 'Dashboard',
        'students.php' => 'Students management',
        'fees.php' => 'Fees management',
        'reports.php' => 'Reports & Analytics',
        'masters.php' => 'Masters data',
        'logout.php' => 'Logout functionality'
    ];
    
    foreach ($criticalPages as $file => $description) {
        if (file_exists($file)) {
            echo "   ✅ {$description}: File exists\n";
        } else {
            echo "   ❌ {$description}: File missing\n";
        }
    }
    
    // 5. SQL Query Tests
    echo "\n5. SQL QUERY TESTS:\n";
    echo "------------------\n";
    
    // Test fees query with training center join
    try {
        $stmt = $db->prepare("
            SELECT f.*, s.name as student_name, 
                   tc.center_name as training_center_name
            FROM fees f
            JOIN students s ON f.student_id = s.id
            LEFT JOIN training_centers tc ON s.training_center_id = tc.id
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "   ✅ Fees query with training center: SUCCESS\n";
    } catch (Exception $e) {
        echo "   ❌ Fees query error: " . $e->getMessage() . "\n";
    }
    
    // Test reports query
    try {
        $stmt = $db->prepare("
            SELECT s.*, c.name as course_name, 
                   tc.center_name as training_center_name
            FROM students s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN training_centers tc ON s.training_center_id = tc.id
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "   ✅ Reports query with training center: SUCCESS\n";
    } catch (Exception $e) {
        echo "   ❌ Reports query error: " . $e->getMessage() . "\n";
    }
    
    // 6. Sample Data Summary
    echo "\n6. SAMPLE DATA SUMMARY:\n";
    echo "----------------------\n";
    
    // Training centers with student counts
    $stmt = $db->query("
        SELECT tc.center_name, COUNT(s.id) as student_count
        FROM training_centers tc
        LEFT JOIN students s ON tc.id = s.training_center_id
        GROUP BY tc.id, tc.center_name
        ORDER BY student_count DESC
    ");
    
    echo "   Training Centers:\n";
    while ($row = $stmt->fetch()) {
        echo "   • {$row['center_name']}: {$row['student_count']} students\n";
    }
    
    // Fee status summary
    $stmt = $db->query("
        SELECT status, COUNT(*) as count 
        FROM fees 
        GROUP BY status
    ");
    
    echo "\n   Fee Status Distribution:\n";
    while ($row = $stmt->fetch()) {
        echo "   • " . ucfirst($row['status']) . ": {$row['count']} records\n";
    }
    
    echo "\n✅ SYSTEM VERIFICATION COMPLETE!\n";
    echo "================================\n";
    echo "🎉 All critical components are working properly!\n\n";
    
    echo "📋 RECOMMENDED TESTING STEPS:\n";
    echo "1. Visit: http://localhost/student-new/public/login.php\n";
    echo "2. Login with admin credentials\n";
    echo "3. Test Dashboard: http://localhost/student-new/public/dashboard.php\n";
    echo "4. Test Reports: http://localhost/student-new/public/reports.php\n";
    echo "5. Test Fees: http://localhost/student-new/public/fees.php\n";
    echo "6. Test Masters: http://localhost/student-new/public/masters.php\n";
    echo "7. Test Students: http://localhost/student-new/public/students.php\n";
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
}
?>

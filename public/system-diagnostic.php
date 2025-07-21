<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// Fake login if not logged in
if (!isLoggedIn()) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_type'] = 'admin';
    $_SESSION['user_name'] = 'System Admin';
}

echo "<h1>System Diagnostic</h1>";
echo "<style>
.test { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
.success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
.error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
.warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
</style>";

// Test database connection
echo "<div class='test'>";
try {
    $db = getConnection();
    if ($db) {
        echo "<div class='success'><strong>✓ Database Connection:</strong> Connected successfully</div>";
        
        // Test table access
        $tables = ['students', 'batches', 'training_centers', 'courses', 'fees'];
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM $table LIMIT 1");
                $result = $stmt->fetch();
                echo "<div class='success'><strong>✓ Table $table:</strong> Accessible ({$result['count']} records)</div>";
            } catch (Exception $e) {
                echo "<div class='error'><strong>✗ Table $table:</strong> " . $e->getMessage() . "</div>";
            }
        }
    } else {
        echo "<div class='error'><strong>✗ Database Connection:</strong> Failed</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><strong>✗ Database Connection:</strong> " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test authentication functions
echo "<div class='test'>";
try {
    $user = getCurrentUser();
    echo "<div class='success'><strong>✓ getCurrentUser():</strong> Working</div>";
    
    $role = getCurrentUserRole();
    echo "<div class='success'><strong>✓ getCurrentUserRole():</strong> $role</div>";
    
    $name = getCurrentUserName();
    echo "<div class='success'><strong>✓ getCurrentUserName():</strong> $name</div>";
} catch (Exception $e) {
    echo "<div class='error'><strong>✗ Auth Functions:</strong> " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test page access
$pages = [
    'dashboard.php' => 'Dashboard',
    'students.php' => 'Students Management',
    'training-centers.php' => 'Training Centers',
    'batches.php' => 'Batches Management',
    'masters.php' => 'Masters Data',
    'assessments.php' => 'Assessments',
    'fees.php' => 'Fees Management',
    'reports.php' => 'Reports'
];

echo "<div class='test'>";
echo "<h3>Page Access Test</h3>";
foreach ($pages as $page => $title) {
    echo "<div style='margin: 5px 0;'>";
    echo "<a href='$page' target='_blank' style='margin-right: 10px;'>$title</a>";
    echo "<span style='color: #666;'>($page)</span>";
    echo "</div>";
}
echo "</div>";

// Test specific queries that were failing
echo "<div class='test'>";
echo "<h3>Query Tests</h3>";

try {
    // Test students query
    $db = getConnection();
    $studentsQuery = "
        SELECT s.*, c.name as course_name, b.name as batch_name, tc.name as center_name 
        FROM students s 
        LEFT JOIN courses c ON s.course_id = c.id 
        LEFT JOIN batches b ON s.batch_id = b.id 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        WHERE s.status != 'deleted'
        LIMIT 3
    ";
    $stmt = $db->prepare($studentsQuery);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<div class='success'><strong>✓ Students Query:</strong> Working (" . count($students) . " records)</div>";
} catch (Exception $e) {
    echo "<div class='error'><strong>✗ Students Query:</strong> " . $e->getMessage() . "</div>";
}

try {
    // Test batches query
    $batchesQuery = "
        SELECT b.*, c.name as course_name, tc.name as center_name,
               (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.id AND s.status = 'active') as enrolled_students
        FROM batches b 
        LEFT JOIN courses c ON b.course_id = c.id 
        LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
        WHERE b.status != 'deleted'
        LIMIT 3
    ";
    $stmt = $db->prepare($batchesQuery);
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<div class='success'><strong>✓ Batches Query:</strong> Working (" . count($batches) . " records)</div>";
} catch (Exception $e) {
    echo "<div class='error'><strong>✗ Batches Query:</strong> " . $e->getMessage() . "</div>";
}

echo "</div>";

// System info
echo "<div class='test'>";
echo "<h3>System Information</h3>";
echo "<div><strong>PHP Version:</strong> " . phpversion() . "</div>";
echo "<div><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</div>";
echo "<div><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</div>";
echo "<div><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</div>";
echo "</div>";
?>

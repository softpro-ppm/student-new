<?php
/**
 * Quick Database Status Checker
 * Checks if all required tables exist and provides quick actions
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$_SESSION['skip_table_creation'] = true;

require_once '../config/database-simple.php';

$status = [
    'connection' => false,
    'tables' => [],
    'missing_tables' => [],
    'demo_data' => []
];

try {
    $db = getConnection();
    $status['connection'] = true;
    
    // Required tables
    $requiredTables = [
        'users', 'training_centers', 'students', 'courses', 'batches', 
        'fees', 'payments', 'assessments', 'results', 'certificates',
        'notifications', 'settings', 'sectors'
    ];
    
    foreach ($requiredTables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $status['tables'][] = $table;
        } else {
            $status['missing_tables'][] = $table;
        }
    }
    
    // Check demo data
    if (in_array('users', $status['tables'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        $status['demo_data']['admin'] = $stmt->fetchColumn() > 0;
    }
    
    if (in_array('training_centers', $status['tables'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM training_centers WHERE email = 'demo@center.com'");
        $stmt->execute();
        $status['demo_data']['demo_center'] = $stmt->fetchColumn() > 0;
    }
    
    if (in_array('students', $status['tables'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE phone = '9999999999'");
        $stmt->execute();
        $status['demo_data']['demo_student'] = $stmt->fetchColumn() > 0;
    }
    
} catch (Exception $e) {
    $status['connection'] = false;
    $status['error'] = $e->getMessage();
}

$isComplete = $status['connection'] && empty($status['missing_tables']);

header('Content-Type: application/json');
echo json_encode([
    'status' => $isComplete ? 'complete' : 'incomplete',
    'connection' => $status['connection'],
    'tables_exist' => count($status['tables']),
    'tables_missing' => count($status['missing_tables']),
    'missing_tables' => $status['missing_tables'],
    'demo_data' => $status['demo_data'],
    'error' => $status['error'] ?? null
]);
?>

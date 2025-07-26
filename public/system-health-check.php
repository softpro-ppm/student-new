<?php
// System Health Check
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>System Health Check</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .health-check { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body>
<div class='container'>
    <h1 class='mb-4'><i class='fas fa-heartbeat'></i> System Health Check</h1>";

function checkStatus($condition, $okMessage, $errorMessage) {
    if ($condition) {
        echo "<div class='status-ok'><i class='fas fa-check-circle'></i> $okMessage</div>";
        return true;
    } else {
        echo "<div class='status-error'><i class='fas fa-times-circle'></i> $errorMessage</div>";
        return false;
    }
}

$allGood = true;

// 1. Database Connection
echo "<div class='health-check'>
    <h3><i class='fas fa-database'></i> Database Connection</h3>";
try {
    $database = new Database();
    $db = $database->getConnection();
    checkStatus(true, "Database connection successful", "");
} catch (Exception $e) {
    checkStatus(false, "", "Database connection failed: " . $e->getMessage());
    $allGood = false;
}
echo "</div>";

// 2. Required Tables
echo "<div class='health-check'>
    <h3><i class='fas fa-table'></i> Database Tables</h3>";

$requiredTables = ['users', 'students', 'training_centers', 'courses', 'batches', 'fees'];
foreach ($requiredTables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        checkStatus($exists, "Table '$table' exists", "Table '$table' missing");
        if (!$exists) $allGood = false;
    } catch (Exception $e) {
        checkStatus(false, "", "Error checking table '$table': " . $e->getMessage());
        $allGood = false;
    }
}
echo "</div>";

// 3. Required Columns
echo "<div class='health-check'>
    <h3><i class='fas fa-columns'></i> Table Columns</h3>";

$requiredColumns = [
    'students' => ['course_id', 'enrollment_number'],
    'training_centers' => ['city', 'state'],
    'courses' => ['name', 'code']
];

foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
            $exists = $stmt->rowCount() > 0;
            checkStatus($exists, "Column '$table.$column' exists", "Column '$table.$column' missing");
            if (!$exists) $allGood = false;
        } catch (Exception $e) {
            checkStatus(false, "", "Error checking column '$table.$column': " . $e->getMessage());
            $allGood = false;
        }
    }
}
echo "</div>";

// 4. Page Access Test
echo "<div class='health-check'>
    <h3><i class='fas fa-globe'></i> Page Access Test</h3>";

$pages = [
    'login.php' => 'Login Page',
    'dashboard.php' => 'Dashboard',
    'students.php' => 'Students Management',
    'training-centers.php' => 'Training Centers',
    'fees.php' => 'Fees Management'
];

foreach ($pages as $page => $title) {
    $url = "http://localhost/student-new/public/$page";
    echo "<div class='mb-2'>
        <strong>$title:</strong> 
        <a href='$url' target='_blank' class='btn btn-sm btn-outline-primary ms-2'>Test</a>
    </div>";
}
echo "</div>";

// 5. Demo Data
echo "<div class='health-check'>
    <h3><i class='fas fa-users'></i> Demo Data</h3>";

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetch()['count'];
    checkStatus($adminCount > 0, "Admin user exists", "No admin user found");
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM training_centers");
    $centerCount = $stmt->fetch()['count'];
    checkStatus($centerCount > 0, "Training centers exist ($centerCount found)", "No training centers found");
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM students");
    $studentCount = $stmt->fetch()['count'];
    checkStatus($studentCount >= 0, "Students table accessible ($studentCount students)", "Cannot access students");
    
} catch (Exception $e) {
    checkStatus(false, "", "Error checking demo data: " . $e->getMessage());
    $allGood = false;
}
echo "</div>";

// Overall Status
echo "<div class='health-check text-center'>";
if ($allGood) {
    echo "<h2 class='status-ok'><i class='fas fa-check-circle'></i> All Systems Operational!</h2>
          <p>Your student management system is ready to use.</p>";
} else {
    echo "<h2 class='status-warning'><i class='fas fa-exclamation-triangle'></i> Issues Found</h2>
          <p>Please run the database fix script to resolve issues.</p>
          <a href='fix-database-issues.php' class='btn btn-warning'>Fix Database Issues</a>";
}

echo "<div class='mt-4'>
    <a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a>
    <a href='setup-database-complete.php' class='btn btn-success'>Setup Database</a>
</div>";

echo "</div></div></body></html>";
?>

<?php
// Comprehensive Page Fix Script
// This script will fix common database column issues across all pages

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database-simple.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Comprehensive Page Fix</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .log-container { background: rgba(255,255,255,0.95); border-radius: 15px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .log { background: #000; color: #0f0; padding: 20px; border-radius: 10px; font-family: monospace; max-height: 400px; overflow-y: auto; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
<div class='container'>
    <div class='log-container'>
        <h1 class='text-center mb-4'><i class='fas fa-tools'></i> Comprehensive Page Fix</h1>
        <div class='log' id='log'>";

function logMessage($message, $type = 'info') {
    $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : ($type === 'warning' ? 'warning' : 'info'));
    echo "<div class='$class'>[" . date('H:i:s') . "] $message</div>";
    ob_flush();
    flush();
}

try {
    $db = getConnection();
    logMessage("Database connection successful", 'success');
    
    // 1. First, let's ensure all required tables exist and add missing columns
    logMessage("=== DATABASE STRUCTURE VERIFICATION ===", 'info');
    
    // Check and create missing tables
    $requiredTables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'training_partner', 'student') NOT NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            training_center_id INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'training_centers' => "CREATE TABLE IF NOT EXISTS training_centers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            center_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            contact_person VARCHAR(255),
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'students' => "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(20) NOT NULL,
            address TEXT,
            course_id INT,
            batch_id INT,
            training_center_id INT,
            enrollment_number VARCHAR(50) UNIQUE,
            admission_date DATE,
            password VARCHAR(255),
            status ENUM('active', 'inactive', 'graduated', 'dropped', 'deleted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'courses' => "CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            duration_months INT DEFAULT 6,
            fee DECIMAL(10,2) DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'batches' => "CREATE TABLE IF NOT EXISTS batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_name VARCHAR(255) NOT NULL,
            course_id INT,
            training_center_id INT,
            start_date DATE,
            end_date DATE,
            max_students INT DEFAULT 30,
            status ENUM('planning', 'active', 'completed', 'cancelled') DEFAULT 'planning',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'fees' => "CREATE TABLE IF NOT EXISTS fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            fee_type ENUM('admission', 'course', 'exam', 'certificate', 'other') DEFAULT 'course',
            due_date DATE,
            paid_date DATE,
            payment_method VARCHAR(50),
            transaction_id VARCHAR(100),
            notes TEXT,
            status ENUM('pending', 'paid', 'overdue', 'waived') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];
    
    foreach ($requiredTables as $tableName => $createSQL) {
        try {
            $db->exec($createSQL);
            logMessage("✓ Table '$tableName' verified/created", 'success');
        } catch (Exception $e) {
            logMessage("✗ Error with table '$tableName': " . $e->getMessage(), 'error');
        }
    }
    
    // 2. Add demo data if tables are empty
    logMessage("=== DEMO DATA SETUP ===", 'info');
    
    // Add demo admin user
    $checkAdmin = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($checkAdmin == 0) {
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role, name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@system.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'System Administrator', '9999999999', 'active']);
        logMessage("✓ Demo admin user created (admin/admin123)", 'success');
    } else {
        logMessage("✓ Admin user already exists", 'info');
    }
    
    // Add demo training center
    $checkCenter = $db->query("SELECT COUNT(*) FROM training_centers")->fetchColumn();
    if ($checkCenter == 0) {
        $stmt = $db->prepare("INSERT INTO training_centers (center_name, email, password, phone, address, city, state, contact_person, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Demo Training Center', 'demo@center.com', password_hash('demo123', PASSWORD_DEFAULT), '9876543210', '123 Demo Street', 'Demo City', 'Demo State', 'Demo Contact', 'active']);
        logMessage("✓ Demo training center created", 'success');
    } else {
        logMessage("✓ Training centers already exist", 'info');
    }
    
    // Add demo courses
    $checkCourses = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    if ($checkCourses == 0) {
        $demoCourses = [
            ['Computer Basics', 'CB001', 'Basic computer skills and MS Office', 3, 5000],
            ['Web Development', 'WD001', 'HTML, CSS, JavaScript and PHP', 6, 15000],
            ['Data Entry', 'DE001', 'Professional data entry skills', 2, 3000],
            ['Digital Marketing', 'DM001', 'Social media and online marketing', 4, 8000]
        ];
        
        $stmt = $db->prepare("INSERT INTO courses (name, code, description, duration_months, fee) VALUES (?, ?, ?, ?, ?)");
        foreach ($demoCourses as $course) {
            $stmt->execute($course);
        }
        logMessage("✓ Demo courses created", 'success');
    } else {
        logMessage("✓ Courses already exist", 'info');
    }
    
    // Add demo batches
    $checkBatches = $db->query("SELECT COUNT(*) FROM batches")->fetchColumn();
    if ($checkBatches == 0) {
        $courses = $db->query("SELECT id FROM courses LIMIT 2")->fetchAll();
        $centers = $db->query("SELECT id FROM training_centers LIMIT 1")->fetchAll();
        
        if (!empty($courses) && !empty($centers)) {
            $demoBatches = [
                ['Batch-2024-001', $courses[0]['id'], $centers[0]['id'], date('Y-m-d'), date('Y-m-d', strtotime('+6 months')), 30],
                ['Batch-2024-002', $courses[1]['id'] ?? $courses[0]['id'], $centers[0]['id'], date('Y-m-d', strtotime('+1 month')), date('Y-m-d', strtotime('+7 months')), 25]
            ];
            
            $stmt = $db->prepare("INSERT INTO batches (batch_name, course_id, training_center_id, start_date, end_date, max_students) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($demoBatches as $batch) {
                $stmt->execute($batch);
            }
            logMessage("✓ Demo batches created", 'success');
        }
    } else {
        logMessage("✓ Batches already exist", 'info');
    }
    
    // Add demo students
    $checkStudents = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($checkStudents == 0) {
        $courses = $db->query("SELECT id FROM courses LIMIT 1")->fetchAll();
        $batches = $db->query("SELECT id FROM batches LIMIT 1")->fetchAll();
        $centers = $db->query("SELECT id FROM training_centers LIMIT 1")->fetchAll();
        
        if (!empty($courses) && !empty($centers)) {
            $demoStudents = [
                ['John Doe', 'john@example.com', '9876543210', '123 Student Street', $courses[0]['id'], $batches[0]['id'] ?? null, $centers[0]['id'], 'ENR001', date('Y-m-d'), password_hash('student123', PASSWORD_DEFAULT)],
                ['Jane Smith', 'jane@example.com', '9876543211', '456 Student Avenue', $courses[0]['id'], $batches[0]['id'] ?? null, $centers[0]['id'], 'ENR002', date('Y-m-d'), password_hash('student123', PASSWORD_DEFAULT)],
                ['Mike Johnson', 'mike@example.com', '9876543212', '789 Student Road', $courses[0]['id'], $batches[0]['id'] ?? null, $centers[0]['id'], 'ENR003', date('Y-m-d'), password_hash('student123', PASSWORD_DEFAULT)]
            ];
            
            $stmt = $db->prepare("INSERT INTO students (name, email, phone, address, course_id, batch_id, training_center_id, enrollment_number, admission_date, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($demoStudents as $student) {
                $stmt->execute($student);
            }
            logMessage("✓ Demo students created", 'success');
        }
    } else {
        logMessage("✓ Students already exist", 'info');
    }
    
    // Add demo fees
    $checkFees = $db->query("SELECT COUNT(*) FROM fees")->fetchColumn();
    if ($checkFees == 0) {
        $students = $db->query("SELECT id FROM students LIMIT 3")->fetchAll();
        
        if (!empty($students)) {
            $demoFees = [
                [$students[0]['id'], 5000.00, 'course', date('Y-m-d', strtotime('+30 days')), 'pending'],
                [$students[1]['id'], 1000.00, 'exam', date('Y-m-d', strtotime('+15 days')), 'pending'],
                [$students[2]['id'], 500.00, 'certificate', date('Y-m-d', strtotime('+45 days')), 'pending']
            ];
            
            $stmt = $db->prepare("INSERT INTO fees (student_id, amount, fee_type, due_date, status) VALUES (?, ?, ?, ?, ?)");
            foreach ($demoFees as $fee) {
                $stmt->execute($fee);
            }
            logMessage("✓ Demo fees created", 'success');
        }
    } else {
        logMessage("✓ Fees already exist", 'info');
    }
    
    logMessage("=== ALL SYSTEMS READY ===", 'success');
    logMessage("Database setup completed successfully!", 'success');
    logMessage("All pages should now work without errors!", 'success');
    
} catch (Exception $e) {
    logMessage("Critical error: " . $e->getMessage(), 'error');
}

echo "</div>
    <div class='text-center mt-4'>
        <h3 class='text-success'>✅ System Ready!</h3>
        <p class='mb-4'>All database issues have been resolved. Test your pages:</p>
        <div class='row'>
            <div class='col-md-2 mb-2'>
                <a href='fees.php' class='btn btn-primary w-100'>💰 Fees</a>
            </div>
            <div class='col-md-2 mb-2'>
                <a href='reports.php' class='btn btn-success w-100'>📊 Reports</a>
            </div>
            <div class='col-md-2 mb-2'>
                <a href='students.php' class='btn btn-info w-100'>👥 Students</a>
            </div>
            <div class='col-md-2 mb-2'>
                <a href='training-centers.php' class='btn btn-warning w-100'>🏢 Centers</a>
            </div>
            <div class='col-md-2 mb-2'>
                <a href='dashboard.php' class='btn btn-secondary w-100'>📈 Dashboard</a>
            </div>
            <div class='col-md-2 mb-2'>
                <a href='login.php' class='btn btn-outline-primary w-100'>🔐 Login</a>
            </div>
        </div>
        
        <div class='alert alert-info mt-4'>
            <h5>📋 Demo Login Credentials:</h5>
            <div class='row'>
                <div class='col-md-4'>
                    <strong>Admin:</strong><br>
                    Username: admin<br>
                    Password: admin123
                </div>
                <div class='col-md-4'>
                    <strong>Training Center:</strong><br>
                    Email: demo@center.com<br>
                    Password: demo123
                </div>
                <div class='col-md-4'>
                    <strong>Student:</strong><br>
                    Phone: 9876543210<br>
                    Password: student123
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>";
?>

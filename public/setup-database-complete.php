<?php
/**
 * Complete Database Setup Script for Student Management System
 * This script creates all necessary tables with proper structure and relationships
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database-simple.php';

// Prevent table creation during normal operations
$_SESSION['skip_table_creation'] = true;

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - Student Management System</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; }
        .setup-container { max-width: 800px; margin: 2rem auto; }
        .status-success { color: #198754; }
        .status-error { color: #dc3545; }
        .status-warning { color: #fd7e14; }
    </style>
</head>
<body>
<div class='container setup-container'>
    <div class='card shadow'>
        <div class='card-header bg-primary text-white'>
            <h3 class='mb-0'><i class='fas fa-database me-2'></i>Database Setup</h3>
        </div>
        <div class='card-body'>";

try {
    $db = getConnection();
    echo "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i>Database connection successful</div>";
    
    // Define all table creation queries
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'training_partner') DEFAULT 'training_partner',
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(15),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'courses' => "CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            duration_months INT DEFAULT 6,
            fee DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'sectors' => "CREATE TABLE IF NOT EXISTS sectors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'training_centers' => "CREATE TABLE IF NOT EXISTS training_centers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            center_name VARCHAR(200) NOT NULL,
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            phone VARCHAR(15),
            address TEXT,
            contact_person VARCHAR(100),
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            registration_number VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'batches' => "CREATE TABLE IF NOT EXISTS batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_name VARCHAR(100) NOT NULL,
            course_id INT,
            training_center_id INT,
            start_date DATE,
            end_date DATE,
            max_students INT DEFAULT 30,
            current_students INT DEFAULT 0,
            status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_course_id (course_id),
            INDEX idx_training_center_id (training_center_id)
        )",
        
        'students' => "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(15) UNIQUE NOT NULL,
            password VARCHAR(255),
            address TEXT,
            date_of_birth DATE,
            gender ENUM('male', 'female', 'other'),
            batch_id INT,
            training_center_id INT,
            enrollment_date DATE DEFAULT (CURDATE()),
            status ENUM('active', 'inactive', 'completed', 'dropped', 'deleted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_batch_id (batch_id),
            INDEX idx_training_center_id (training_center_id),
            INDEX idx_phone (phone)
        )",
        
        'fees' => "CREATE TABLE IF NOT EXISTS fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            due_date DATE,
            description VARCHAR(255),
            status ENUM('pending', 'paid', 'overdue', 'waived') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_id (student_id)
        )",
        
        'payments' => "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            fee_id INT,
            amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('cash', 'card', 'bank_transfer', 'upi', 'cheque') DEFAULT 'cash',
            transaction_id VARCHAR(100),
            payment_date DATE DEFAULT (CURDATE()),
            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_id (student_id),
            INDEX idx_fee_id (fee_id)
        )",
        
        'assessments' => "CREATE TABLE IF NOT EXISTS assessments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            course_id INT,
            batch_id INT,
            assessment_type ENUM('theory', 'practical', 'project', 'viva') DEFAULT 'theory',
            max_marks INT DEFAULT 100,
            passing_marks INT DEFAULT 40,
            assessment_date DATE,
            duration_minutes INT DEFAULT 60,
            status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_course_id (course_id),
            INDEX idx_batch_id (batch_id)
        )",
        
        'results' => "CREATE TABLE IF NOT EXISTS results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            assessment_id INT NOT NULL,
            marks_obtained DECIMAL(5,2),
            grade VARCHAR(10),
            percentage DECIMAL(5,2),
            result_status ENUM('pass', 'fail', 'absent', 'pending') DEFAULT 'pending',
            remarks TEXT,
            evaluated_by INT,
            evaluated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_id (student_id),
            INDEX idx_assessment_id (assessment_id),
            UNIQUE KEY unique_student_assessment (student_id, assessment_id)
        )",
        
        'certificates' => "CREATE TABLE IF NOT EXISTS certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            certificate_number VARCHAR(50) UNIQUE NOT NULL,
            issue_date DATE DEFAULT (CURDATE()),
            grade VARCHAR(10),
            percentage DECIMAL(5,2),
            status ENUM('issued', 'revoked', 'pending') DEFAULT 'issued',
            file_path VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_id (student_id),
            INDEX idx_course_id (course_id),
            INDEX idx_certificate_number (certificate_number)
        )",
        
        'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            user_type ENUM('admin', 'training_partner', 'student') NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id_type (user_id, user_type),
            INDEX idx_created_at (created_at)
        )",
        
        'settings' => "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'bulk_uploads' => "CREATE TABLE IF NOT EXISTS bulk_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            upload_type ENUM('students', 'fees', 'results') NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500),
            total_records INT DEFAULT 0,
            processed_records INT DEFAULT 0,
            failed_records INT DEFAULT 0,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            error_log TEXT,
            uploaded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_uploaded_by (uploaded_by)
        )",
        
        'question_papers' => "CREATE TABLE IF NOT EXISTS question_papers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            course_id INT,
            subject VARCHAR(100),
            duration_minutes INT DEFAULT 60,
            total_marks INT DEFAULT 100,
            instructions TEXT,
            status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_course_id (course_id),
            INDEX idx_created_by (created_by)
        )",
        
        'password_resets' => "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type ENUM('admin', 'training_partner', 'student') NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id_type (user_id, user_type),
            INDEX idx_token (token)
        )"
    ];
    
    $created = 0;
    $errors = 0;
    
    echo "<h5 class='mt-4'>Creating Tables:</h5>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm'>";
    echo "<thead><tr><th>Table</th><th>Status</th><th>Details</th></tr></thead><tbody>";
    
    foreach ($tables as $tableName => $query) {
        echo "<tr>";
        echo "<td><strong>$tableName</strong></td>";
        
        try {
            $db->exec($query);
            echo "<td><span class='status-success'><i class='fas fa-check-circle me-1'></i>Created</span></td>";
            echo "<td class='text-muted'>Table created successfully</td>";
            $created++;
        } catch (PDOException $e) {
            echo "<td><span class='status-error'><i class='fas fa-times-circle me-1'></i>Error</span></td>";
            echo "<td class='text-danger'>" . htmlspecialchars($e->getMessage()) . "</td>";
            $errors++;
        }
        
        echo "</tr>";
    }
    
    echo "</tbody></table></div>";
    
    // Insert default data
    echo "<h5 class='mt-4'>Inserting Default Data:</h5>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm'>";
    echo "<thead><tr><th>Data Type</th><th>Status</th><th>Details</th></tr></thead><tbody>";
    
    // Default admin user
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        $adminExists = $stmt->fetchColumn() > 0;
        
        if (!$adminExists) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role, name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@system.com', $adminPassword, 'admin', 'System Administrator', '9999999999', 'active']);
            echo "<tr><td><strong>Admin User</strong></td><td><span class='status-success'><i class='fas fa-check-circle me-1'></i>Created</span></td><td>Username: admin, Password: admin123</td></tr>";
        } else {
            echo "<tr><td><strong>Admin User</strong></td><td><span class='status-warning'><i class='fas fa-info-circle me-1'></i>Exists</span></td><td>Admin user already exists</td></tr>";
        }
    } catch (Exception $e) {
        echo "<tr><td><strong>Admin User</strong></td><td><span class='status-error'><i class='fas fa-times-circle me-1'></i>Error</span></td><td>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    
    // Default demo training center
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM training_centers WHERE email = 'demo@center.com'");
        $stmt->execute();
        $centerExists = $stmt->fetchColumn() > 0;
        
        if (!$centerExists) {
            $centerPassword = password_hash('demo123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO training_centers (center_name, email, password, phone, address, contact_person, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Demo Training Center', 'demo@center.com', $centerPassword, '9876543210', '123 Demo Street, Demo City', 'Demo Contact Person', 'active']);
            echo "<tr><td><strong>Demo Training Center</strong></td><td><span class='status-success'><i class='fas fa-check-circle me-1'></i>Created</span></td><td>Email: demo@center.com, Password: demo123</td></tr>";
        } else {
            echo "<tr><td><strong>Demo Training Center</strong></td><td><span class='status-warning'><i class='fas fa-info-circle me-1'></i>Exists</span></td><td>Demo center already exists</td></tr>";
        }
    } catch (Exception $e) {
        echo "<tr><td><strong>Demo Training Center</strong></td><td><span class='status-error'><i class='fas fa-times-circle me-1'></i>Error</span></td><td>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    
    // Default course
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE name = 'Web Development'");
        $stmt->execute();
        $courseExists = $stmt->fetchColumn() > 0;
        
        if (!$courseExists) {
            $stmt = $db->prepare("INSERT INTO courses (name, description, duration_months, fee, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Web Development', 'Complete web development course covering HTML, CSS, JavaScript, PHP, and MySQL', 6, 15000.00, 'active']);
            echo "<tr><td><strong>Default Course</strong></td><td><span class='status-success'><i class='fas fa-check-circle me-1'></i>Created</span></td><td>Web Development course added</td></tr>";
        } else {
            echo "<tr><td><strong>Default Course</strong></td><td><span class='status-warning'><i class='fas fa-info-circle me-1'></i>Exists</span></td><td>Course already exists</td></tr>";
        }
    } catch (Exception $e) {
        echo "<tr><td><strong>Default Course</strong></td><td><span class='status-error'><i class='fas fa-times-circle me-1'></i>Error</span></td><td>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    
    // Demo student
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE phone = '9999999999'");
        $stmt->execute();
        $studentExists = $stmt->fetchColumn() > 0;
        
        if (!$studentExists) {
            $studentPassword = password_hash('softpro@123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO students (name, email, phone, password, address, training_center_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            // Get training center ID
            $stmt2 = $db->prepare("SELECT id FROM training_centers WHERE email = 'demo@center.com'");
            $stmt2->execute();
            $centerId = $stmt2->fetchColumn();
            
            $stmt->execute(['Demo Student', 'demo@student.com', '9999999999', $studentPassword, '456 Student Lane, Student City', $centerId, 'active']);
            echo "<tr><td><strong>Demo Student</strong></td><td><span class='status-success'><i class='fas fa-check-circle me-1'></i>Created</span></td><td>Phone: 9999999999, Password: softpro@123</td></tr>";
        } else {
            echo "<tr><td><strong>Demo Student</strong></td><td><span class='status-warning'><i class='fas fa-info-circle me-1'></i>Exists</span></td><td>Demo student already exists</td></tr>";
        }
    } catch (Exception $e) {
        echo "<tr><td><strong>Demo Student</strong></td><td><span class='status-error'><i class='fas fa-times-circle me-1'></i>Error</span></td><td>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    
    // Default settings
    try {
        $settings = [
            ['system_name', 'Student Management System', 'Application name'],
            ['system_version', '2.0', 'Current system version'],
            ['default_currency', 'INR', 'Default currency for fees'],
            ['session_timeout', '3600', 'Session timeout in seconds'],
            ['max_file_size', '10', 'Maximum file upload size in MB']
        ];
        
        foreach ($settings as $setting) {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute($setting);
        }
        echo "<tr><td><strong>System Settings</strong></td><td><span class='status-success'><i class='fas fa-check-circle me-1'></i>Created</span></td><td>Default settings configured</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td><strong>System Settings</strong></td><td><span class='status-error'><i class='fas fa-times-circle me-1'></i>Error</span></td><td>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    
    echo "</tbody></table></div>";
    
    // Summary
    echo "<div class='alert alert-info mt-4'>";
    echo "<h5><i class='fas fa-info-circle me-2'></i>Setup Summary</h5>";
    echo "<ul class='mb-0'>";
    echo "<li>Tables Created: <strong>$created</strong></li>";
    echo "<li>Errors: <strong>$errors</strong></li>";
    echo "<li>Database: <strong>" . ($errors == 0 ? "Ready" : "Partially Ready") . "</strong></li>";
    echo "</ul>";
    echo "</div>";
    
    if ($errors == 0) {
        echo "<div class='alert alert-success'>";
        echo "<h5><i class='fas fa-check-circle me-2'></i>Setup Complete!</h5>";
        echo "<p class='mb-2'>Your Student Management System database is now ready. You can use these credentials to login:</p>";
        echo "<ul class='mb-0'>";
        echo "<li><strong>Admin:</strong> admin / admin123</li>";
        echo "<li><strong>Training Center:</strong> demo@center.com / demo123</li>";
        echo "<li><strong>Student:</strong> 9999999999 / softpro@123</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='text-center mt-4'>";
        echo "<a href='login.php' class='btn btn-primary btn-lg'>";
        echo "<i class='fas fa-sign-in-alt me-2'></i>Go to Login";
        echo "</a>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>";
        echo "<h5><i class='fas fa-exclamation-triangle me-2'></i>Setup Completed with Errors</h5>";
        echo "<p>Some tables could not be created. Please check the error messages above and ensure your database user has proper permissions.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5><i class='fas fa-times-circle me-2'></i>Database Connection Failed</h5>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in <code>config/database-simple.php</code></p>";
    echo "</div>";
}

unset($_SESSION['skip_table_creation']);

echo "        </div>
    </div>
</div>
</body>
</html>";
?>

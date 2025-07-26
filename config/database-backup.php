<?php
// Database Configuration - Enhanced version with function protection
// Only declare if not already declared to avoid redeclaration errors
if (!function_exists('getConnection')) {
    function getConnection() {
        $host = 'localhost';
        $db_name = 'student';
        $username = 'root';
        $password = '';

        try {
            $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
            $conn->exec("set names utf8");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Connection error. Please check configuration.");
        }
    }
}

// Database class wrapper - only declare if not already declared
if (!class_exists('Database')) {
    class Database {
        private $host = 'localhost';
        private $db_name = 'student';
        private $username = 'root';
        private $password = '';

        private $conn;

        public function getConnection() {
            $this->conn = null;
            try {
                $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
                $this->conn->exec("set names utf8");
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Initialize database tables and demo users
                $this->initializeDemoData();
                
            } catch(PDOException $exception) {
                error_log("Database connection error: " . $exception->getMessage());
                echo "Connection error. Please check configuration.";
            }
            return $this->conn;
        }
    
    private function initializeDemoData() {
        try {
            // Check if admin user exists
            $checkAdmin = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
            $checkAdmin->execute();
            
            if ($checkAdmin->fetchColumn() == 0) {
                $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $insertAdmin = $this->conn->prepare("INSERT INTO users (username, email, password, role, name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertAdmin->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 'System Administrator', '9999999999', 'active']);
            }
            
            // Check if student user exists
            $checkStudent = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE username = '9999999999'");
            $checkStudent->execute();
            
            if ($checkStudent->fetchColumn() == 0) {
                $studentPassword = password_hash('softpro@123', PASSWORD_DEFAULT);
                $insertStudent = $this->conn->prepare("INSERT INTO users (username, email, password, role, name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertStudent->execute(['9999999999', 'student@example.com', $studentPassword, 'student', 'Demo Student', '9999999999', 'active']);
            }
            
        } catch(PDOException $e) {
            error_log("Demo data initialization error: " . $e->getMessage());
        }
    }
}

// Create database tables if they don't exist
function createTables() {
    $database = new Database();
    $db = $database->getConnection();
    
    // Users table
    $createUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'training_partner', 'student') NOT NULL,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        training_center_id INT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (training_center_id) REFERENCES training_centers(id) ON DELETE SET NULL
    )";
    
    // Training Centers table
    $createTrainingCenters = "CREATE TABLE IF NOT EXISTS training_centers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        address TEXT,
        phone VARCHAR(20),
        email VARCHAR(255),
        contact_person VARCHAR(255),
        password VARCHAR(255),
        user_id INT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    // Sectors table
    $createSectors = "CREATE TABLE IF NOT EXISTS sectors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    // Courses table
    $createCourses = "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        sector_id INT,
        duration_months INT,
        fee_amount DECIMAL(10,2),
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE
    )";
    
    // Students table
    $createStudents = "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        enrollment_number VARCHAR(50) UNIQUE NOT NULL,
        aadhaar VARCHAR(12) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        father_name VARCHAR(255) NOT NULL,
        dob DATE NOT NULL,
        gender ENUM('male', 'female', 'other') NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255),
        address TEXT,
        education VARCHAR(255),
        religion VARCHAR(100),
        marital_status ENUM('single', 'married', 'divorced', 'widowed'),
        photo_path VARCHAR(500),
        aadhaar_doc_path VARCHAR(500),
        education_doc_path VARCHAR(500),
        course_id INT,
        training_center_id INT,
        user_id INT,
        registration_fee_paid ENUM('yes', 'no') DEFAULT 'no',
        status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id),
        FOREIGN KEY (training_center_id) REFERENCES training_centers(id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    // Batches table
    $createBatches = "CREATE TABLE IF NOT EXISTS batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        course_id INT,
        training_center_id INT,
        start_date DATE,
        end_date DATE,
        timings VARCHAR(255),
        max_students INT DEFAULT 30,
        status ENUM('upcoming', 'ongoing', 'completed') DEFAULT 'upcoming',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id),
        FOREIGN KEY (training_center_id) REFERENCES training_centers(id)
    )";
    
    // Student Batches table
    $createStudentBatches = "CREATE TABLE IF NOT EXISTS student_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        batch_id INT,
        enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
    )";
    
    // Fee Payments table
    $createFeePayments = "CREATE TABLE IF NOT EXISTS fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        amount DECIMAL(10,2) NOT NULL,
        payment_type ENUM('registration', 'course_fee', 'installment') NOT NULL,
        payment_method VARCHAR(100),
        transaction_id VARCHAR(255),
        payment_date DATE,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        approved_by INT,
        approved_at TIMESTAMP NULL,
        receipt_number VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )";
    
    // Assessments table
    $createAssessments = "CREATE TABLE IF NOT EXISTS assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        course_id INT,
        description TEXT,
        time_limit INT DEFAULT 60,
        total_marks INT DEFAULT 100,
        passing_marks INT DEFAULT 70,
        max_attempts INT DEFAULT 3,
        status ENUM('draft', 'active', 'closed') DEFAULT 'draft',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    
    // Assessment Questions table
    $createAssessmentQuestions = "CREATE TABLE IF NOT EXISTS assessment_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assessment_id INT,
        question TEXT NOT NULL,
        type ENUM('multiple_choice', 'true_false', 'text') NOT NULL,
        options JSON,
        correct_answer TEXT NOT NULL,
        marks INT DEFAULT 5,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    
    // Student Assessments table (for tracking individual attempts)
    $createStudentAssessments = "CREATE TABLE IF NOT EXISTS student_assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        assessment_id INT,
        token VARCHAR(255) UNIQUE NOT NULL,
        status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        score INT DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0,
        result_status ENUM('passed', 'failed') NULL,
        time_spent INT DEFAULT 0,
        attempts INT DEFAULT 0,
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
        UNIQUE KEY unique_student_assessment (student_id, assessment_id)
    )";
    
    // Assessment Results table (detailed question-wise results)
    $createAssessmentResults = "CREATE TABLE IF NOT EXISTS assessment_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_assessment_id INT,
        question_id INT,
        student_answer TEXT,
        correct_answer TEXT,
        is_correct BOOLEAN DEFAULT FALSE,
        marks_obtained INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_assessment_id) REFERENCES student_assessments(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES assessment_questions(id) ON DELETE CASCADE
    )";
    
    // Results table (legacy - keeping for compatibility)
    $createResults = "CREATE TABLE IF NOT EXISTS results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        assessment_id INT,
        marks_obtained INT,
        total_marks INT,
        percentage DECIMAL(5,2),
        grade VARCHAR(10),
        status ENUM('pass', 'fail') NOT NULL,
        attempt_number INT DEFAULT 1,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
    )";
    
    // Certificates table
    $createCertificates = "CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        result_id INT,
        certificate_number VARCHAR(100) UNIQUE NOT NULL,
        issued_date DATE,
        certificate_path VARCHAR(500),
        qr_code_path VARCHAR(500),
        status ENUM('generated', 'issued') DEFAULT 'generated',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (result_id) REFERENCES results(id) ON DELETE CASCADE
    )";
    
    // Settings table
    $createSettings = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    // Notifications table
    $createNotifications = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        type VARCHAR(50),
        is_read ENUM('yes', 'no') DEFAULT 'no',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    try {
        $db->exec($createUsers);
        $db->exec($createTrainingCenters);
        $db->exec($createSectors);
        $db->exec($createCourses);
        $db->exec($createStudents);
        $db->exec($createBatches);
        $db->exec($createStudentBatches);
        $db->exec($createFeePayments);
        $db->exec($createAssessments);
        $db->exec($createAssessmentQuestions);
        $db->exec($createStudentAssessments);
        $db->exec($createAssessmentResults);
        $db->exec($createResults);
        $db->exec($createCertificates);
        $db->exec($createSettings);
        $db->exec($createNotifications);
        
        // Insert default admin user
        $checkAdmin = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $checkAdmin->execute();
        
        if ($checkAdmin->fetchColumn() == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $insertAdmin = $db->prepare("INSERT INTO users (username, email, password, role, name, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $insertAdmin->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 'System Administrator', '9999999999']);
        }
        
        // Insert default sectors
        $checkSectors = $db->prepare("SELECT COUNT(*) FROM sectors");
        $checkSectors->execute();
        
        if ($checkSectors->fetchColumn() == 0) {
            $sectors = [
                ['IT/ITES', 'IT001', 'Information Technology and IT Enabled Services'],
                ['Healthcare', 'HC001', 'Healthcare and Medical Services'],
                ['Automotive', 'AU001', 'Automotive and Transportation'],
                ['Retail', 'RT001', 'Retail and Customer Service'],
                ['Banking', 'BK001', 'Banking and Financial Services']
            ];
            
            $insertSector = $db->prepare("INSERT INTO sectors (name, code, description) VALUES (?, ?, ?)");
            foreach ($sectors as $sector) {
                $insertSector->execute($sector);
            }
        }
        
        // Insert default courses
        $checkCourses = $db->prepare("SELECT COUNT(*) FROM courses");
        $checkCourses->execute();
        
        if ($checkCourses->fetchColumn() == 0) {
            // Get sector IDs
            $getSectors = $db->prepare("SELECT id, code FROM sectors");
            $getSectors->execute();
            $sectors = $getSectors->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $courses = [
                ['Web Development', 'WD001', $sectors['IT001'] ?? 1, 6, 15000.00, 'Full Stack Web Development with HTML, CSS, JavaScript, PHP'],
                ['Digital Marketing', 'DM001', $sectors['IT001'] ?? 1, 3, 8000.00, 'Complete Digital Marketing and Social Media Marketing'],
                ['Data Entry Operator', 'DE001', $sectors['IT001'] ?? 1, 2, 5000.00, 'Computer Data Entry and MS Office'],
                ['Nursing Assistant', 'NA001', $sectors['HC001'] ?? 2, 12, 25000.00, 'Healthcare and Patient Care Assistant'],
                ['Automotive Technician', 'AT001', $sectors['AU001'] ?? 3, 8, 18000.00, 'Vehicle Maintenance and Repair'],
                ['Retail Sales Associate', 'RS001', $sectors['RT001'] ?? 4, 4, 10000.00, 'Customer Service and Sales'],
                ['Banking Operations', 'BO001', $sectors['BK001'] ?? 5, 6, 12000.00, 'Banking Procedures and Customer Relations']
            ];
            
            $insertCourse = $db->prepare("INSERT INTO courses (name, code, sector_id, duration_months, fee_amount, description) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($courses as $course) {
                $insertCourse->execute($course);
            }
        }
        
        // Insert default settings
        $checkSettings = $db->prepare("SELECT COUNT(*) FROM settings");
        $checkSettings->execute();
        
        if ($checkSettings->fetchColumn() == 0) {
            $settings = [
                ['site_name', 'Student Management System'],
                ['registration_fee', '100'],
                ['currency', 'INR'],
                ['academic_year', '2024-25'],
                ['whatsapp_api_url', ''],
                ['email_smtp_host', ''],
                ['email_smtp_port', '587'],
                ['email_smtp_username', ''],
                ['email_smtp_password', ''],
                ['certificate_template_path', ''],
                ['assessment_passing_marks', '70']
            ];
            
            $insertSetting = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($settings as $setting) {
                $insertSetting->execute($setting);
            }
        }
        
        return true;
    } catch(PDOException $exception) {
        echo "Table creation error: " . $exception->getMessage();
        return false;
    }
}

// Initialize database
createTables();

// Note: getConnection() function is declared at the top of this file
}
?>

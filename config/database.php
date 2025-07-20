<?php
// Database Configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'student_management';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
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
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
        user_id INT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
        batch_id INT,
        course_id INT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        total_marks INT DEFAULT 100,
        passing_marks INT DEFAULT 60,
        duration_minutes INT DEFAULT 60,
        status ENUM('draft', 'active', 'completed') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (batch_id) REFERENCES batches(id),
        FOREIGN KEY (course_id) REFERENCES courses(id)
    )";
    
    // Assessment Questions table
    $createAssessmentQuestions = "CREATE TABLE IF NOT EXISTS assessment_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assessment_id INT,
        question TEXT NOT NULL,
        option_a VARCHAR(500),
        option_b VARCHAR(500),
        option_c VARCHAR(500),
        option_d VARCHAR(500),
        correct_answer ENUM('a', 'b', 'c', 'd') NOT NULL,
        marks INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
    )";
    
    // Results table
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
        
        return true;
    } catch(PDOException $exception) {
        echo "Table creation error: " . $exception->getMessage();
        return false;
    }
}

// Initialize database
createTables();
?>

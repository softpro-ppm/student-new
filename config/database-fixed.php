<?php
// Database Configuration - Fixed version
// Simplified and error-free database configuration

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
}

// Create database tables if they don't exist
if (!function_exists('createTables')) {
    function createTables() {
        try {
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createUsers);
            
            // Training Centers table
            $createTrainingCenters = "CREATE TABLE IF NOT EXISTS training_centers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                center_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                phone VARCHAR(20),
                address TEXT,
                city VARCHAR(100),
                state VARCHAR(100),
                pincode VARCHAR(10),
                contact_person VARCHAR(255),
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createTrainingCenters);
            
            // Students table
            $createStudents = "CREATE TABLE IF NOT EXISTS students (
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
            )";
            $db->exec($createStudents);
            
            // Courses table
            $createCourses = "CREATE TABLE IF NOT EXISTS courses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(50) UNIQUE NOT NULL,
                description TEXT,
                duration_months INT DEFAULT 6,
                fee DECIMAL(10,2) DEFAULT 0,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createCourses);
            
            // Batches table
            $createBatches = "CREATE TABLE IF NOT EXISTS batches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                batch_name VARCHAR(255) NOT NULL,
                course_id INT,
                training_center_id INT,
                start_date DATE,
                end_date DATE,
                max_students INT DEFAULT 30,
                current_students INT DEFAULT 0,
                instructor_name VARCHAR(255),
                status ENUM('planning', 'active', 'completed', 'cancelled') DEFAULT 'planning',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createBatches);
            
            // Fees table
            $createFees = "CREATE TABLE IF NOT EXISTS fees (
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
                approved_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createFees);
            
            return true;
            
        } catch(PDOException $exception) {
            echo "Table creation error: " . $exception->getMessage();
            return false;
        }
    }
}

// Initialize database
createTables();
?>

<?php
// --- Environment-specific Database Configuration ---

// Check the server host to determine if it's a live or local environment.
$is_live = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1');

if ($is_live) {
    // --- Live Server Database Credentials ---
    // IMPORTANT: Replace these placeholders with your actual live database details.
    $db_host = 'localhost';
    $db_name = 'u820431346_smis_new';
    $db_username = 'u820431346_smis_new';
    $db_password = '8b#U+Jp@R';
} else {
    // --- Local Server Database Credentials ---
    $db_host = 'localhost';
    $db_name = 'u820431346_smis'; // As per your SQL file
    $db_username = 'root';
    $db_password = '';
}

// --- Database Connection Functions ---

// Only declare if not already declared to avoid redeclaration errors
if (!function_exists('getConnection')) {
    /**
     * Establishes a PDO database connection using environment-specific credentials.
     * @return PDO The database connection object.
     * @throws Exception If the connection fails.
     */
    function getConnection() {
        global $db_host, $db_name, $db_username, $db_password;

        try {
            $conn = new PDO("mysql:host=" . $db_host . ";dbname=" . $db_name, $db_username, $db_password);
            $conn->exec("set names utf8");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch(PDOException $exception) {
            // Log the detailed error message for debugging
            error_log("Database connection error: " . $exception->getMessage());
            // Provide a user-friendly error message
            throw new Exception("Database connection failed. Please check the configuration.");
        }
    }
}

// Database class wrapper - only declare if not already declared
if (!class_exists('Database')) {
    /**
     * A wrapper class for database operations, supporting environment-specific connections.
     */
    class Database {
        private $host;
        private $db_name;
        private $username;
        private $password;
        private $conn;

        public function __construct() {
            global $db_host, $db_name, $db_username, $db_password;
            $this->host = $db_host;
            $this->db_name = $db_name;
            $this->username = $db_username;
            $this->password = $db_password;
        }

        /**
         * Gets the database connection, initializing it if necessary.
         * @return PDO|null The database connection object or null on failure.
         */
        public function getConnection() {
            if ($this->conn === null) {
                try {
                    $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
                    $this->conn->exec("set names utf8");
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch(PDOException $exception) {
                    error_log("Database connection error: " . $exception->getMessage());
                    // Return null or handle the error as needed
                    return null;
                }
            }
            return $this->conn;
        }
    }
}

// --- Database Schema Initialization ---

// Create database tables if they don't exist
if (!function_exists('createTables')) {
    /**
     * Creates necessary database tables if they do not already exist.
     * This function should be called once during application setup.
     */
    function createTables() {
        try {
            $db = getConnection();
            
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
                sector_id INT,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createCourses);
            
            // Sectors table
            $createSectors = "CREATE TABLE IF NOT EXISTS sectors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(50) UNIQUE NOT NULL,
                description TEXT,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createSectors);
            
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
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            )";
            $db->exec($createResults);
            
            // Assessments table
            $createAssessments = "CREATE TABLE IF NOT EXISTS assessments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                course_id INT,
                total_marks INT DEFAULT 100,
                passing_marks INT DEFAULT 40,
                duration_minutes INT DEFAULT 60,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
            )";
            $db->exec($createAssessments);
            
            return true;
            
        } catch(PDOException $exception) {
            // In a real application, you would log this error
            // For this example, we'll just return false
            return false;
        }
    }
}

// --- Initial Setup ---

// Run table creation logic only if the 'setup_done' flag is not set
if (!file_exists(__DIR__ . '/.setup_done')) {
    if (createTables()) {
        // Create a flag file to indicate that setup is complete
        file_put_contents(__DIR__ . '/.setup_done', 'Setup completed on ' . date('Y-m-d H:i:s'));
    }
}
?>
<?php
require_once '../config/database-v2.php';

function createMissingTables() {
    try {
        $conn = getV2Connection();
        echo "<h2>Creating Missing Tables for v2.0</h2>";
        
        // 1. Create student_batches table (alias for batch_students)
        $sql_student_batches = "
        CREATE TABLE IF NOT EXISTS student_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            batch_id INT NOT NULL,
            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completion_date TIMESTAMP NULL,
            status ENUM('enrolled', 'active', 'completed', 'dropped', 'removed', 'transferred') DEFAULT 'enrolled',
            attendance_percentage DECIMAL(5,2) DEFAULT 0,
            final_grade VARCHAR(10),
            certificate_issued BOOLEAN DEFAULT FALSE,
            certificate_number VARCHAR(100),
            remarks TEXT,
            
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_student_batch (student_id, batch_id),
            INDEX idx_student (student_id),
            INDEX idx_batch (batch_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 2. Create fee_payments table (for fees module)
        $sql_fee_payments = "
        CREATE TABLE IF NOT EXISTS fee_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            receipt_number VARCHAR(100) UNIQUE NOT NULL,
            payment_type ENUM('registration', 'course', 'examination', 'certificate', 'library', 'other') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_mode ENUM('cash', 'online', 'bank_transfer', 'cheque', 'dd', 'card', 'upi') NOT NULL,
            transaction_id VARCHAR(200),
            payment_date DATE NOT NULL,
            cleared_date DATE,
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'completed',
            remarks TEXT,
            receipt_path VARCHAR(500),
            
            processed_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_student (student_id),
            INDEX idx_receipt_number (receipt_number),
            INDEX idx_payment_date (payment_date),
            INDEX idx_status (status),
            INDEX idx_payment_type (payment_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 3. Create courses table if not exists
        $sql_courses = "
        CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_name VARCHAR(255) NOT NULL,
            course_code VARCHAR(50) UNIQUE NOT NULL,
            sector_id INT NULL,
            description TEXT,
            duration_hours INT DEFAULT 0,
            course_duration INT DEFAULT 0,
            course_fee DECIMAL(10,2) DEFAULT 0,
            registration_fee DECIMAL(10,2) DEFAULT 0,
            qualification_required VARCHAR(255),
            
            status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
            
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_course_code (course_code),
            INDEX idx_sector (sector_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 4. Create sectors table if not exists
        $sql_sectors = "
        CREATE TABLE IF NOT EXISTS sectors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sector_name VARCHAR(255) NOT NULL,
            sector_code VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            ministry VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_sector_code (sector_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 5. Create batches table if not exists
        $sql_batches = "
        CREATE TABLE IF NOT EXISTS batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_name VARCHAR(255) NOT NULL,
            batch_code VARCHAR(50) UNIQUE NOT NULL,
            course_id INT NOT NULL,
            training_center_id INT NOT NULL,
            instructor_user_id INT NULL,
            
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            registration_start_date DATE,
            registration_end_date DATE,
            
            max_capacity INT DEFAULT 30,
            current_capacity INT DEFAULT 0,
            description TEXT,
            
            status ENUM('upcoming', 'active', 'completed', 'cancelled', 'suspended') DEFAULT 'upcoming',
            
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_batch_code (batch_code),
            INDEX idx_course (course_id),
            INDEX idx_training_center (training_center_id),
            INDEX idx_start_date (start_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // Execute table creation
        $tables = [
            'student_batches' => $sql_student_batches,
            'fee_payments' => $sql_fee_payments,
            'courses' => $sql_courses,
            'sectors' => $sql_sectors,
            'batches' => $sql_batches
        ];
        
        $created = 0;
        $errors = 0;
        
        foreach ($tables as $tableName => $sql) {
            try {
                $conn->exec($sql);
                echo "<p style='color: green;'>✓ Table '$tableName' created successfully</p>";
                $created++;
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ Error creating table '$tableName': " . $e->getMessage() . "</p>";
                $errors++;
            }
        }
        
        // Add some sample data
        echo "<h3>Adding Sample Data</h3>";
        
        // Sample sector
        try {
            $stmt = $conn->prepare("INSERT IGNORE INTO sectors (sector_name, sector_code, description) VALUES (?, ?, ?)");
            $stmt->execute(['Information Technology', 'IT', 'Information Technology and Computing']);
            $stmt->execute(['Healthcare', 'HEALTH', 'Healthcare and Life Sciences']);
            echo "<p style='color: green;'>✓ Sample sectors added</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ Sectors may already exist: " . $e->getMessage() . "</p>";
        }
        
        // Sample course
        try {
            $stmt = $conn->prepare("INSERT IGNORE INTO courses (course_name, course_code, sector_id, course_fee, registration_fee, duration_hours) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Web Development', 'WEB001', 1, 5000.00, 500.00, 120]);
            $stmt->execute(['Data Entry Operator', 'DEO001', 1, 3000.00, 300.00, 80]);
            echo "<p style='color: green;'>✓ Sample courses added</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ Courses may already exist: " . $e->getMessage() . "</p>";
        }
        
        echo "<hr>";
        echo "<p><strong>Summary:</strong></p>";
        echo "<p>Tables created: $created</p>";
        echo "<p>Errors: $errors</p>";
        
        if ($errors === 0) {
            echo "<p style='color: green; font-weight: bold;'>✓ All missing tables created successfully!</p>";
            echo "<p><a href='batches-v2.php'>Test Batches Page</a> | ";
            echo "<a href='courses-v2.php'>Test Courses Page</a> | ";
            echo "<a href='fees-v2.php'>Test Fees Page</a> | ";
            echo "<a href='reports-v2.php'>Test Reports Page</a></p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>⚠ Some tables could not be created. Check the errors above.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Fatal Error: " . $e->getMessage() . "</p>";
    }
}

// Execute the function
createMissingTables();
?>

<?php
require_once '../config/database-v2.php';

function createV2SchemaPart2() {
    try {
        $conn = getV2Connection();
        echo "<h2>Creating Database v2.0 Schema - Phase 2</h2>";
        
        $executed = 0;
        $errors = 0;
        
        // 7. STUDENTS TABLE (Enhanced from tblcandidate)
        $sql_students = "
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NULL,
            enrollment_number VARCHAR(100) UNIQUE NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            father_name VARCHAR(100),
            mother_name VARCHAR(100),
            date_of_birth DATE NOT NULL,
            gender ENUM('male', 'female', 'other') NOT NULL,
            email VARCHAR(255) UNIQUE,
            phone VARCHAR(20) NOT NULL,
            alternate_phone VARCHAR(20),
            aadhar_number VARCHAR(12) UNIQUE,
            qualification VARCHAR(100),
            marital_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT 'single',
            religion VARCHAR(50),
            category ENUM('general', 'obc', 'sc', 'st', 'ews') DEFAULT 'general',
            
            -- Address Information
            address_line1 TEXT NOT NULL,
            address_line2 TEXT,
            village VARCHAR(100),
            mandal VARCHAR(100),
            district VARCHAR(100) NOT NULL,
            state VARCHAR(100) NOT NULL,
            pincode VARCHAR(10) NOT NULL,
            
            -- Documents
            photo_path VARCHAR(500),
            aadhar_document_path VARCHAR(500),
            qualification_document_path VARCHAR(500),
            other_documents JSON,
            
            -- Training Information
            training_center_id INT NOT NULL,
            current_batch_id INT NULL,
            admission_date DATE,
            expected_completion_date DATE,
            
            -- Status and Tracking
            status ENUM('enrolled', 'active', 'completed', 'dropped', 'suspended', 'transferred') DEFAULT 'enrolled',
            notes TEXT,
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (training_center_id) REFERENCES training_centers(id) ON DELETE RESTRICT,
            
            INDEX idx_enrollment (enrollment_number),
            INDEX idx_phone (phone),
            INDEX idx_aadhar (aadhar_number),
            INDEX idx_training_center (training_center_id),
            INDEX idx_status (status),
            INDEX idx_admission_date (admission_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 8. BATCHES TABLE
        $sql_batches = "
        CREATE TABLE IF NOT EXISTS batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_name VARCHAR(255) NOT NULL,
            batch_code VARCHAR(50) UNIQUE NOT NULL,
            course_id INT NOT NULL,
            training_center_id INT NOT NULL,
            instructor_user_id INT NULL,
            
            -- Batch Details
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            registration_start_date DATE,
            registration_end_date DATE,
            
            -- Capacity Management
            max_students INT DEFAULT 30,
            current_students INT DEFAULT 0,
            waiting_list_count INT DEFAULT 0,
            
            -- Schedule
            class_schedule JSON,
            assessment_schedule JSON,
            
            -- Status
            status ENUM('planning', 'registration_open', 'registration_closed', 'active', 'completed', 'cancelled', 'suspended') DEFAULT 'planning',
            
            -- Additional Info
            venue_details TEXT,
            special_instructions TEXT,
            notes TEXT,
            
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
            FOREIGN KEY (training_center_id) REFERENCES training_centers(id) ON DELETE RESTRICT,
            FOREIGN KEY (instructor_user_id) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_batch_code (batch_code),
            INDEX idx_course (course_id),
            INDEX idx_training_center (training_center_id),
            INDEX idx_start_date (start_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 9. BATCH_STUDENTS TABLE (Many-to-Many relationship)
        $sql_batch_students = "
        CREATE TABLE IF NOT EXISTS batch_students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_id INT NOT NULL,
            student_id INT NOT NULL,
            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completion_date TIMESTAMP NULL,
            status ENUM('enrolled', 'active', 'completed', 'dropped', 'transferred') DEFAULT 'enrolled',
            attendance_percentage DECIMAL(5,2) DEFAULT 0,
            final_grade VARCHAR(10),
            certificate_issued BOOLEAN DEFAULT FALSE,
            certificate_number VARCHAR(100),
            remarks TEXT,
            
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_batch_student (batch_id, student_id),
            INDEX idx_batch (batch_id),
            INDEX idx_student (student_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 10. FEES TABLE (Enhanced fee management)
        $sql_fees = "
        CREATE TABLE IF NOT EXISTS fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            batch_id INT NULL,
            course_id INT NOT NULL,
            
            -- Fee Details
            fee_type ENUM('registration', 'course', 'examination', 'certificate', 'library', 'hostel', 'other') NOT NULL,
            fee_name VARCHAR(255) NOT NULL,
            base_amount DECIMAL(10,2) NOT NULL,
            discount_amount DECIMAL(10,2) DEFAULT 0,
            tax_amount DECIMAL(10,2) DEFAULT 0,
            final_amount DECIMAL(10,2) NOT NULL,
            
            -- Payment Schedule
            due_date DATE NOT NULL,
            installment_number INT DEFAULT 1,
            total_installments INT DEFAULT 1,
            
            -- Status
            status ENUM('pending', 'partial', 'paid', 'overdue', 'waived', 'cancelled') DEFAULT 'pending',
            
            -- References
            invoice_id INT NULL,
            waiver_reason TEXT,
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
            
            INDEX idx_student (student_id),
            INDEX idx_batch (batch_id),
            INDEX idx_fee_type (fee_type),
            INDEX idx_due_date (due_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // Execute table creation
        $tables = [
            'students' => $sql_students,
            'batches' => $sql_batches,
            'batch_students' => $sql_batch_students,
            'fees' => $sql_fees
        ];
        
        foreach ($tables as $table_name => $sql) {
            try {
                $conn->exec($sql);
                echo "✅ Created table: $table_name<br>";
                $executed++;
            } catch (PDOException $e) {
                echo "❌ Error creating $table_name: " . $e->getMessage() . "<br>";
                $errors++;
            }
        }
        
        echo "<br><strong>Phase 2 Complete:</strong> $executed tables created, $errors errors<br>";
        echo "<a href='setup-v2-schema-part3.php'>Continue to Phase 3 →</a>";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

createV2SchemaPart2();
?>

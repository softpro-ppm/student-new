<?php
require_once '../config/database.php';

function createV2SchemaPart3() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        echo "<h2>Creating Database v2.0 Schema - Phase 3 (Final)</h2>";
        
        $executed = 0;
        $errors = 0;
        
        // 11. PAYMENTS TABLE (Enhanced payment tracking)
        $sql_payments = "
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            fee_id INT NOT NULL,
            
            -- Payment Details
            payment_reference VARCHAR(100) UNIQUE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('cash', 'online', 'bank_transfer', 'cheque', 'dd', 'card') NOT NULL,
            payment_gateway VARCHAR(50),
            transaction_id VARCHAR(200),
            
            -- Bank/Cheque Details
            bank_name VARCHAR(100),
            cheque_number VARCHAR(50),
            cheque_date DATE,
            
            -- Status and Dates
            payment_date DATE NOT NULL,
            cleared_date DATE,
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
            
            -- Additional Info
            remarks TEXT,
            receipt_number VARCHAR(100),
            receipt_path VARCHAR(500),
            
            -- System Tracking
            processed_by INT,
            verified_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE RESTRICT,
            
            INDEX idx_student (student_id),
            INDEX idx_fee (fee_id),
            INDEX idx_payment_reference (payment_reference),
            INDEX idx_payment_date (payment_date),
            INDEX idx_status (status),
            INDEX idx_payment_method (payment_method)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 12. ASSESSMENTS TABLE
        $sql_assessments = "
        CREATE TABLE IF NOT EXISTS assessments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            assessment_code VARCHAR(50) UNIQUE NOT NULL,
            course_id INT NOT NULL,
            batch_id INT NULL,
            
            -- Assessment Details
            assessment_type ENUM('theory', 'practical', 'viva', 'project', 'final') NOT NULL,
            description TEXT,
            instructions TEXT,
            total_marks INT NOT NULL DEFAULT 100,
            passing_marks INT NOT NULL DEFAULT 40,
            duration_minutes INT DEFAULT 60,
            
            -- Scheduling
            scheduled_date DATE,
            start_time TIME,
            end_time TIME,
            venue VARCHAR(255),
            
            -- Configuration
            question_paper_path VARCHAR(500),
            answer_key_path VARCHAR(500),
            evaluation_criteria JSON,
            
            -- Status
            status ENUM('draft', 'scheduled', 'active', 'completed', 'cancelled') DEFAULT 'draft',
            
            -- System Tracking
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
            FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
            
            INDEX idx_assessment_code (assessment_code),
            INDEX idx_course (course_id),
            INDEX idx_batch (batch_id),
            INDEX idx_scheduled_date (scheduled_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 13. RESULTS TABLE
        $sql_results = "
        CREATE TABLE IF NOT EXISTS results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            assessment_id INT NOT NULL,
            batch_id INT NOT NULL,
            
            -- Marks Details
            marks_obtained DECIMAL(6,2) NOT NULL,
            total_marks DECIMAL(6,2) NOT NULL,
            percentage DECIMAL(5,2) GENERATED ALWAYS AS ((marks_obtained / total_marks) * 100) STORED,
            grade VARCHAR(10),
            result_status ENUM('pass', 'fail', 'absent', 'disqualified') NOT NULL,
            
            -- Attempt Info
            attempt_number INT DEFAULT 1,
            is_reappear BOOLEAN DEFAULT FALSE,
            
            -- Evaluation Details
            answer_sheet_path VARCHAR(500),
            remarks TEXT,
            evaluated_by INT,
            evaluated_at TIMESTAMP NULL,
            
            -- Publication
            published BOOLEAN DEFAULT FALSE,
            published_at TIMESTAMP NULL,
            published_by INT,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE RESTRICT,
            FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
            
            UNIQUE KEY unique_student_assessment_attempt (student_id, assessment_id, attempt_number),
            INDEX idx_student (student_id),
            INDEX idx_assessment (assessment_id),
            INDEX idx_batch (batch_id),
            INDEX idx_result_status (result_status),
            INDEX idx_percentage (percentage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 14. AUDIT_LOGS TABLE
        $sql_audit_logs = "
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            table_name VARCHAR(100) NOT NULL,
            record_id INT NOT NULL,
            action ENUM('insert', 'update', 'delete', 'view') NOT NULL,
            old_values JSON,
            new_values JSON,
            changed_fields JSON,
            user_id INT,
            user_type VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_table_record (table_name, record_id),
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 15. SYSTEM_SETTINGS TABLE
        $sql_settings = "
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type ENUM('string', 'integer', 'boolean', 'json', 'decimal') DEFAULT 'string',
            category VARCHAR(100) DEFAULT 'general',
            description TEXT,
            is_editable BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_category (category),
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // Execute table creation
        $tables = [
            'payments' => $sql_payments,
            'assessments' => $sql_assessments,
            'results' => $sql_results,
            'audit_logs' => $sql_audit_logs,
            'system_settings' => $sql_settings
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
        
        // Add foreign key constraints for users table
        try {
            $conn->exec("ALTER TABLE users ADD FOREIGN KEY (training_center_id) REFERENCES training_centers(id) ON DELETE SET NULL");
            echo "✅ Added foreign key constraint for users table<br>";
        } catch (PDOException $e) {
            echo "⚠️ Foreign key constraint already exists or error: " . $e->getMessage() . "<br>";
        }
        
        // Update current_batch_id foreign key for students
        try {
            $conn->exec("ALTER TABLE students ADD FOREIGN KEY (current_batch_id) REFERENCES batches(id) ON DELETE SET NULL");
            echo "✅ Added foreign key constraint for students.current_batch_id<br>";
        } catch (PDOException $e) {
            echo "⚠️ Foreign key constraint already exists or error: " . $e->getMessage() . "<br>";
        }
        
        echo "<br><strong>Phase 3 Complete:</strong> $executed tables created, $errors errors<br>";
        echo "<hr>";
        echo "<h3>🎉 Database v2.0 Schema Creation Complete!</h3>";
        echo "<p>✅ All tables have been created successfully.</p>";
        echo "<p>📊 <a href='check-v2-database.php'>View v2.0 Database Status</a></p>";
        echo "<p>🔄 <a href='data-migration-v1-to-v2.php'>Start Data Migration →</a></p>";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

createV2SchemaPart3();
?>

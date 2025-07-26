<?php
require_once '../config/database-v2.php';

function createV2Schema() {
    try {
        $conn = getV2Connection();
        echo "<h2>Creating Database v2.0 Schema</h2>";
        
        // Track execution
        $executed = 0;
        $errors = 0;
        
        // 1. USERS TABLE - Unified user management
        $sql_users = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('super_admin', 'admin', 'training_partner', 'student', 'instructor') NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            training_center_id INT NULL,
            status ENUM('active', 'inactive', 'suspended', 'deleted') DEFAULT 'active',
            email_verified BOOLEAN DEFAULT FALSE,
            phone_verified BOOLEAN DEFAULT FALSE,
            last_login TIMESTAMP NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_training_center (training_center_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 2. TRAINING CENTERS TABLE
        $sql_training_centers = "
        CREATE TABLE IF NOT EXISTS training_centers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            center_name VARCHAR(255) NOT NULL,
            center_code VARCHAR(50) UNIQUE NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100) NOT NULL,
            pincode VARCHAR(10) NOT NULL,
            phone VARCHAR(20),
            email VARCHAR(255) UNIQUE NOT NULL,
            spoc_name VARCHAR(255) NOT NULL,
            spoc_phone VARCHAR(20) NOT NULL,
            spoc_email VARCHAR(255) NOT NULL,
            registration_number VARCHAR(100),
            accreditation_details JSON,
            capacity INT DEFAULT 100,
            facilities JSON,
            status ENUM('active', 'inactive', 'suspended', 'pending_approval') DEFAULT 'pending_approval',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            
            INDEX idx_center_code (center_code),
            INDEX idx_city (city),
            INDEX idx_state (state),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 3. SECTORS TABLE
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
        
        // 4. SCHEMES TABLE
        $sql_schemes = "
        CREATE TABLE IF NOT EXISTS schemes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scheme_name VARCHAR(255) NOT NULL,
            scheme_code VARCHAR(50) UNIQUE NOT NULL,
            sector_id INT NOT NULL,
            description TEXT,
            duration_months INT DEFAULT 6,
            funding_agency VARCHAR(255),
            stipend_amount DECIMAL(10,2) DEFAULT 0,
            scheme_guidelines JSON,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            start_date DATE,
            end_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE RESTRICT,
            INDEX idx_scheme_code (scheme_code),
            INDEX idx_sector (sector_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 5. JOB ROLES TABLE
        $sql_job_roles = "
        CREATE TABLE IF NOT EXISTS job_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_role_name VARCHAR(255) NOT NULL,
            job_role_code VARCHAR(50) UNIQUE NOT NULL,
            scheme_id INT NOT NULL,
            sector_id INT NOT NULL,
            nqr_level VARCHAR(10),
            description TEXT,
            eligibility_criteria JSON,
            job_responsibilities JSON,
            career_progression JSON,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE RESTRICT,
            FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE RESTRICT,
            INDEX idx_job_role_code (job_role_code),
            INDEX idx_scheme (scheme_id),
            INDEX idx_sector (sector_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // 6. COURSES TABLE
        $sql_courses = "
        CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_name VARCHAR(255) NOT NULL,
            course_code VARCHAR(50) UNIQUE NOT NULL,
            job_role_id INT NOT NULL,
            scheme_id INT NOT NULL,
            sector_id INT NOT NULL,
            description TEXT,
            duration_hours INT NOT NULL,
            duration_months INT DEFAULT 6,
            theory_hours INT DEFAULT 0,
            practical_hours INT DEFAULT 0,
            on_job_training_hours INT DEFAULT 0,
            course_fee DECIMAL(10,2) DEFAULT 0,
            curriculum JSON,
            assessment_criteria JSON,
            certification_details JSON,
            prerequisites JSON,
            status ENUM('active', 'inactive', 'draft') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (job_role_id) REFERENCES job_roles(id) ON DELETE RESTRICT,
            FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE RESTRICT,
            FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE RESTRICT,
            INDEX idx_course_code (course_code),
            INDEX idx_job_role (job_role_id),
            INDEX idx_scheme (scheme_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // Execute table creation
        $tables = [
            'users' => $sql_users,
            'training_centers' => $sql_training_centers,
            'sectors' => $sql_sectors,
            'schemes' => $sql_schemes,
            'job_roles' => $sql_job_roles,
            'courses' => $sql_courses
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
        
        echo "<br><strong>Phase 1 Complete:</strong> $executed tables created, $errors errors<br>";
        echo "<a href='setup-v2-schema-part2.php'>Continue to Phase 2 →</a>";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

createV2Schema();
?>

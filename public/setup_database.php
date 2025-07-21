<?php
// Complete Database Schema and Setup
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed!');
}

echo "<h1>Student Management System - Complete Database Setup</h1>";

try {
    // Drop and recreate all tables with proper structure
    $dropTables = [
        'assessment_results', 'assessments', 'question_papers', 'certificates', 
        'notifications', 'results', 'fees', 'student_batches', 'students', 
        'batches', 'courses', 'sectors', 'training_centers', 'users', 'settings'
    ];
    
    echo "<h2>Dropping existing tables...</h2>";
    foreach ($dropTables as $table) {
        try {
            $db->exec("DROP TABLE IF EXISTS $table");
            echo "<p>✓ Dropped table: $table</p>";
        } catch (PDOException $e) {
            echo "<p>⚠ Could not drop $table: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>Creating new tables...</h2>";
    
    // Users table
    $createUsers = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'training_partner', 'student') NOT NULL,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        training_center_id INT,
        avatar VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($createUsers);
    echo "<p>✓ Created users table</p>";
    
    // Training Centers table
    $createTrainingCenters = "CREATE TABLE training_centers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tc_id VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        password VARCHAR(255) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($createTrainingCenters);
    echo "<p>✓ Created training_centers table</p>";
    
    // Sectors table
    $createSectors = "CREATE TABLE sectors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($createSectors);
    echo "<p>✓ Created sectors table</p>";
    
    // Courses table
    $createCourses = "CREATE TABLE courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        sector_id INT,
        duration_months INT NOT NULL,
        fee_amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE SET NULL
    )";
    $db->exec($createCourses);
    echo "<p>✓ Created courses table</p>";
    
    // Batches table
    $createBatches = "CREATE TABLE batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        course_id INT,
        training_center_id INT,
        start_date DATE,
        end_date DATE,
        start_time TIME,
        end_time TIME,
        status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (training_center_id) REFERENCES training_centers(id) ON DELETE CASCADE
    )";
    $db->exec($createBatches);
    echo "<p>✓ Created batches table</p>";
    
    // Students table
    $createStudents = "CREATE TABLE students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        enrollment_number VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        father_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(10) NOT NULL,
        aadhaar VARCHAR(12) UNIQUE NOT NULL,
        dob DATE,
        gender ENUM('male', 'female', 'other') NOT NULL,
        education ENUM('Below SSC', 'SSC', 'Intermediate', 'Graduation', 'Post Graduation', 'B.Tech', 'Diploma') NOT NULL,
        marital_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT 'single',
        course_id INT,
        batch_id INT,
        training_center_id INT,
        photo VARCHAR(255),
        aadhaar_doc VARCHAR(255),
        education_doc VARCHAR(255),
        status ENUM('active', 'inactive', 'completed', 'dropped') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
        FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
        FOREIGN KEY (training_center_id) REFERENCES training_centers(id) ON DELETE SET NULL
    )";
    $db->exec($createStudents);
    echo "<p>✓ Created students table</p>";
    
    // Fees table
    $createFees = "CREATE TABLE fees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        fee_type ENUM('registration', 'course', 'exam', 'emi', 'other') DEFAULT 'course',
        status ENUM('pending', 'paid', 'approved', 'rejected') DEFAULT 'pending',
        due_date DATE,
        paid_date DATE,
        approved_by INT,
        approved_date DATE,
        receipt_number VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $db->exec($createFees);
    echo "<p>✓ Created fees table</p>";
    
    // Question Papers table
    $createQuestionPapers = "CREATE TABLE question_papers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        course_id INT,
        total_questions INT DEFAULT 0,
        duration_minutes INT DEFAULT 60,
        passing_marks INT DEFAULT 70,
        questions JSON,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )";
    $db->exec($createQuestionPapers);
    echo "<p>✓ Created question_papers table</p>";
    
    // Assessments table
    $createAssessments = "CREATE TABLE assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT NOT NULL,
        question_paper_id INT NOT NULL,
        assessment_date DATE,
        instructions TEXT,
        status ENUM('scheduled', 'ongoing', 'completed') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
        FOREIGN KEY (question_paper_id) REFERENCES question_papers(id) ON DELETE CASCADE
    )";
    $db->exec($createAssessments);
    echo "<p>✓ Created assessments table</p>";
    
    // Assessment Results table
    $createAssessmentResults = "CREATE TABLE assessment_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assessment_id INT NOT NULL,
        student_id INT NOT NULL,
        answers JSON,
        score INT DEFAULT 0,
        total_marks INT DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0,
        result ENUM('pass', 'fail') DEFAULT 'fail',
        attempt_number INT DEFAULT 1,
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )";
    $db->exec($createAssessmentResults);
    echo "<p>✓ Created assessment_results table</p>";
    
    // Results table
    $createResults = "CREATE TABLE results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        assessment_result_id INT,
        final_score DECIMAL(5,2) NOT NULL,
        grade ENUM('A+', 'A', 'B+', 'B', 'C', 'F') NOT NULL,
        result ENUM('pass', 'fail') NOT NULL,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (assessment_result_id) REFERENCES assessment_results(id) ON DELETE SET NULL
    )";
    $db->exec($createResults);
    echo "<p>✓ Created results table</p>";
    
    // Certificates table
    $createCertificates = "CREATE TABLE certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        result_id INT,
        certificate_number VARCHAR(50) UNIQUE NOT NULL,
        certificate_path VARCHAR(255),
        qr_code_path VARCHAR(255),
        issued_date DATE NOT NULL,
        status ENUM('generated', 'issued', 'revoked') DEFAULT 'generated',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (result_id) REFERENCES results(id) ON DELETE SET NULL
    )";
    $db->exec($createCertificates);
    echo "<p>✓ Created certificates table</p>";
    
    // Settings table
    $createSettings = "CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'number', 'boolean', 'file', 'json') DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($createSettings);
    echo "<p>✓ Created settings table</p>";
    
    // Notifications table
    $createNotifications = "CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($createNotifications);
    echo "<p>✓ Created notifications table</p>";
    
    echo "<h2>Adding foreign key constraints...</h2>";
    
    // Add foreign key for users.training_center_id
    $db->exec("ALTER TABLE users ADD FOREIGN KEY (training_center_id) REFERENCES training_centers(id) ON DELETE SET NULL");
    echo "<p>✓ Added foreign key for users.training_center_id</p>";
    
    echo "<h2>Inserting default data...</h2>";
    
    // Insert admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password, role, name, phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 'System Administrator', '9999999999']);
    echo "<p>✓ Created admin user (admin/admin123)</p>";
    
    // Insert default settings
    $defaultSettings = [
        ['site_name', 'Student Management System'],
        ['site_logo', ''],
        ['certificate_template', ''],
        ['registration_fee', '100'],
        ['currency', 'INR'],
        ['academic_year', '2024-25'],
        ['whatsapp_api_url', ''],
        ['email_smtp_host', ''],
        ['email_smtp_port', '587'],
        ['email_smtp_username', ''],
        ['email_smtp_password', ''],
        ['assessment_passing_marks', '70'],
        ['theme_color', 'blue']
    ];
    
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    echo "<p>✓ Inserted default settings</p>";
    
    // Insert default sectors
    $sectors = [
        ['Information Technology', 'IT001', 'Information Technology and IT Enabled Services'],
        ['Healthcare', 'HC001', 'Healthcare and Medical Services'],
        ['Automotive', 'AU001', 'Automotive and Transportation'],
        ['Retail', 'RT001', 'Retail and Customer Service'],
        ['Banking', 'BK001', 'Banking and Financial Services'],
        ['Manufacturing', 'MF001', 'Manufacturing and Production']
    ];
    
    $stmt = $db->prepare("INSERT INTO sectors (name, code, description) VALUES (?, ?, ?)");
    foreach ($sectors as $sector) {
        $stmt->execute($sector);
    }
    echo "<p>✓ Inserted " . count($sectors) . " sectors</p>";
    
    // Insert default courses
    $courses = [
        ['Web Development', 'WD001', 1, 6, 15000.00, 'Full Stack Web Development with HTML, CSS, JavaScript, PHP'],
        ['Digital Marketing', 'DM001', 1, 3, 8000.00, 'Complete Digital Marketing and Social Media Marketing'],
        ['Data Entry Operator', 'DE001', 1, 2, 5000.00, 'Computer Data Entry and MS Office'],
        ['Mobile App Development', 'MA001', 1, 8, 20000.00, 'Android and iOS App Development'],
        ['Nursing Assistant', 'NA001', 2, 12, 25000.00, 'Healthcare and Patient Care Assistant'],
        ['Medical Lab Technician', 'ML001', 2, 10, 18000.00, 'Laboratory Testing and Analysis'],
        ['Automotive Technician', 'AT001', 3, 8, 18000.00, 'Vehicle Maintenance and Repair'],
        ['Electric Vehicle Tech', 'EV001', 3, 6, 22000.00, 'Electric Vehicle Technology and Maintenance'],
        ['Retail Sales Associate', 'RS001', 4, 4, 10000.00, 'Customer Service and Sales'],
        ['E-commerce Specialist', 'EC001', 4, 5, 12000.00, 'Online Business and E-commerce Management'],
        ['Banking Operations', 'BO001', 5, 6, 12000.00, 'Banking Procedures and Customer Relations'],
        ['Financial Services', 'FS001', 5, 8, 16000.00, 'Investment and Financial Planning'],
        ['Production Supervisor', 'PS001', 6, 9, 14000.00, 'Manufacturing Process Management'],
        ['Quality Control', 'QC001', 6, 7, 13000.00, 'Quality Assurance and Control'],
        ['Machine Operator', 'MO001', 6, 5, 11000.00, 'Industrial Machine Operations']
    ];
    
    $stmt = $db->prepare("INSERT INTO courses (name, code, sector_id, duration_months, fee_amount, description) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($courses as $course) {
        $stmt->execute($course);
    }
    echo "<p>✓ Inserted " . count($courses) . " courses</p>";
    
    echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✅ Database setup completed successfully!</p>";
    echo "<p><a href='setup_dummy_data.php' class='btn btn-primary'>→ Continue to Dummy Data Setup</a></p>";
    echo "<p><a href='login.php' class='btn btn-secondary'>← Back to Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

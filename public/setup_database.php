<?php
// Fixed Database Schema and Setup for Student Management System
require_once '../config/database-simple.php';

try {
    $db = getConnection();
    if (!$db) {
        throw new Exception('Database connection failed!');
    }
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup - Student Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .container { max-width: 800px; margin: 0 auto; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px; text-decoration: none; border-radius: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Student Management System - Database Setup</h1>";

try {
    // Disable foreign key checks temporarily
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p class='warning'>⚠ Disabled foreign key checks temporarily</p>";
    
    // Drop tables in reverse dependency order
    $dropTables = [
        'bulk_uploads', 'assessment_results', 'assessments', 'question_papers', 
        'certificates', 'notifications', 'results', 'fees', 'students', 
        'batches', 'courses', 'sectors', 'training_centers', 'users', 'settings'
    ];
    
    echo "<h2>Dropping existing tables...</h2>";
    foreach ($dropTables as $table) {
        try {
            $db->exec("DROP TABLE IF EXISTS `$table`");
            echo "<p class='success'>✓ Dropped table: $table</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>⚠ Could not drop $table: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>Creating new tables...</h2>";
    
    // 1. Users table (independent)
    $createUsers = "CREATE TABLE `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `email` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `role` enum('admin','training_partner','student') NOT NULL,
        `name` varchar(255) NOT NULL,
        `full_name` varchar(255) DEFAULT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `training_center_id` int(11) DEFAULT NULL,
        `avatar` varchar(255) DEFAULT NULL,
        `status` enum('active','inactive','suspended') DEFAULT 'active',
        `created_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createUsers);
    echo "<p class='success'>✓ Created users table</p>";
    
    // 2. Training Centers table (independent)
    $createTrainingCenters = "CREATE TABLE `training_centers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tc_id` varchar(20) NOT NULL,
        `name` varchar(255) NOT NULL,
        `contact_person` varchar(255) DEFAULT NULL,
        `email` varchar(255) NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `address` text DEFAULT NULL,
        `city` varchar(100) DEFAULT NULL,
        `state` varchar(100) DEFAULT NULL,
        `pincode` varchar(10) DEFAULT NULL,
        `password` varchar(255) NOT NULL,
        `status` enum('active','inactive','suspended','deleted') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tc_id` (`tc_id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createTrainingCenters);
    echo "<p class='success'>✓ Created training_centers table</p>";
    
    // 3. Sectors table (independent)
    $createSectors = "CREATE TABLE `sectors` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) NOT NULL,
        `description` text DEFAULT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createSectors);
    echo "<p class='success'>✓ Created sectors table</p>";
    
    // 4. Courses table (depends on sectors)
    $createCourses = "CREATE TABLE `courses` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) NOT NULL,
        `sector_id` int(11) DEFAULT NULL,
        `duration_months` int(11) NOT NULL,
        `fee_amount` decimal(10,2) NOT NULL,
        `description` text DEFAULT NULL,
        `training_center_id` int(11) DEFAULT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `sector_id` (`sector_id`),
        KEY `training_center_id` (`training_center_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createCourses);
    echo "<p class='success'>✓ Created courses table</p>";
    
    // 5. Batches table (depends on courses and training_centers)
    $createBatches = "CREATE TABLE `batches` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `course_id` int(11) DEFAULT NULL,
        `training_center_id` int(11) DEFAULT NULL,
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `start_time` time DEFAULT NULL,
        `end_time` time DEFAULT NULL,
        `status` enum('upcoming','ongoing','completed','cancelled','deleted') DEFAULT 'upcoming',
        `max_students` int(11) DEFAULT 30,
        `current_students` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `course_id` (`course_id`),
        KEY `training_center_id` (`training_center_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createBatches);
    echo "<p class='success'>✓ Created batches table</p>";
    
    // 6. Students table (depends on courses, batches, training_centers)
    $createStudents = "CREATE TABLE `students` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `enrollment_no` varchar(50) NOT NULL,
        `name` varchar(255) NOT NULL,
        `father_name` varchar(255) NOT NULL,
        `email` varchar(255) DEFAULT NULL,
        `phone` varchar(10) NOT NULL,
        `aadhaar` varchar(12) NOT NULL,
        `dob` date DEFAULT NULL,
        `gender` enum('Male','Female','Other') NOT NULL,
        `education` enum('Below SSC','SSC','Inter','Graduation','PG','B.Tech','Diploma') NOT NULL,
        `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT 'Single',
        `course_id` int(11) DEFAULT NULL,
        `batch_id` int(11) DEFAULT NULL,
        `training_center_id` int(11) DEFAULT NULL,
        `address` text DEFAULT NULL,
        `photo` varchar(255) DEFAULT NULL,
        `aadhaar_doc` varchar(255) DEFAULT NULL,
        `education_doc` varchar(255) DEFAULT NULL,
        `password` varchar(255) DEFAULT NULL,
        `status` enum('active','inactive','completed','dropped','deleted') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `enrollment_no` (`enrollment_no`),
        UNIQUE KEY `aadhaar` (`aadhaar`),
        UNIQUE KEY `email` (`email`),
        KEY `course_id` (`course_id`),
        KEY `batch_id` (`batch_id`),
        KEY `training_center_id` (`training_center_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createStudents);
    echo "<p class='success'>✓ Created students table</p>";
    
    // 7. Fees table (depends on students)
    $createFees = "CREATE TABLE `fees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `fee_type` enum('registration','course','exam','emi','other') DEFAULT 'course',
        `status` enum('pending','paid','approved','rejected') DEFAULT 'pending',
        `due_date` date DEFAULT NULL,
        `paid_date` date DEFAULT NULL,
        `approved_by` int(11) DEFAULT NULL,
        `approved_date` date DEFAULT NULL,
        `receipt_number` varchar(50) DEFAULT NULL,
        `payment_method` enum('cash','online','cheque','dd') DEFAULT 'cash',
        `transaction_id` varchar(100) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        KEY `approved_by` (`approved_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createFees);
    echo "<p class='success'>✓ Created fees table</p>";
    
    // 8. Settings table (independent)
    $createSettings = "CREATE TABLE `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text DEFAULT NULL,
        `setting_type` enum('text','number','boolean','file','json') DEFAULT 'text',
        `description` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createSettings);
    echo "<p class='success'>✓ Created settings table</p>";
    
    // 9. Notifications table (depends on users)
    $createNotifications = "CREATE TABLE `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `title` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `type` enum('info','success','warning','error') DEFAULT 'info',
        `is_read` tinyint(1) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createNotifications);
    echo "<p class='success'>✓ Created notifications table</p>";
    
    // 10. Bulk Uploads table (depends on users)
    $createBulkUploads = "CREATE TABLE `bulk_uploads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `filename` varchar(255) NOT NULL,
        `total_records` int(11) DEFAULT 0,
        `successful_imports` int(11) DEFAULT 0,
        `failed_imports` int(11) DEFAULT 0,
        `course_id` int(11) DEFAULT NULL,
        `batch_id` int(11) DEFAULT NULL,
        `training_center_id` int(11) DEFAULT NULL,
        `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `status` enum('processing','completed','failed') DEFAULT 'processing',
        `error_log` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `course_id` (`course_id`),
        KEY `batch_id` (`batch_id`),
        KEY `training_center_id` (`training_center_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createBulkUploads);
    echo "<p class='success'>✓ Created bulk_uploads table</p>";
    
    // 11. Question Papers table (for assessments)
    $createQuestionPapers = "CREATE TABLE `question_papers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `course_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `total_marks` int(11) DEFAULT 100,
        `duration_minutes` int(11) DEFAULT 60,
        `passing_marks` int(11) DEFAULT 40,
        `is_active` tinyint(1) DEFAULT 1,
        `created_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `course_id` (`course_id`),
        KEY `created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createQuestionPapers);
    echo "<p class='success'>✓ Created question_papers table</p>";
    
    // 12. Assessments table (depends on students, question_papers)
    $createAssessments = "CREATE TABLE `assessments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `question_paper_id` int(11) NOT NULL,
        `batch_id` int(11) DEFAULT NULL,
        `assessment_date` date NOT NULL,
        `start_time` timestamp NOT NULL,
        `end_time` timestamp NULL DEFAULT NULL,
        `status` enum('scheduled','in_progress','completed','missed','cancelled') DEFAULT 'scheduled',
        `attempt_number` int(11) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        KEY `question_paper_id` (`question_paper_id`),
        KEY `batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createAssessments);
    echo "<p class='success'>✓ Created assessments table</p>";
    
    // 13. Results table (depends on assessments)
    $createResults = "CREATE TABLE `results` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `assessment_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `question_paper_id` int(11) NOT NULL,
        `total_marks` int(11) NOT NULL,
        `obtained_marks` int(11) NOT NULL,
        `percentage` decimal(5,2) NOT NULL,
        `grade` varchar(10) DEFAULT NULL,
        `result_status` enum('pass','fail','absent') NOT NULL,
        `remarks` text DEFAULT NULL,
        `result_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `published_by` int(11) DEFAULT NULL,
        `published_at` timestamp NULL DEFAULT NULL,
        `is_published` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_assessment_result` (`assessment_id`),
        KEY `student_id` (`student_id`),
        KEY `question_paper_id` (`question_paper_id`),
        KEY `published_by` (`published_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createResults);
    echo "<p class='success'>✓ Created results table</p>";
    
    // 14. Certificates table (depends on students, results)
    $createCertificates = "CREATE TABLE `certificates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `course_id` int(11) NOT NULL,
        `result_id` int(11) DEFAULT NULL,
        `certificate_number` varchar(50) NOT NULL,
        `issue_date` date NOT NULL,
        `validity_date` date DEFAULT NULL,
        `certificate_type` enum('completion','excellence','participation') DEFAULT 'completion',
        `file_path` varchar(255) DEFAULT NULL,
        `qr_code` varchar(255) DEFAULT NULL,
        `verification_code` varchar(50) DEFAULT NULL,
        `status` enum('generated','issued','verified','revoked') DEFAULT 'generated',
        `issued_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `certificate_number` (`certificate_number`),
        UNIQUE KEY `verification_code` (`verification_code`),
        KEY `student_id` (`student_id`),
        KEY `course_id` (`course_id`),
        KEY `result_id` (`result_id`),
        KEY `issued_by` (`issued_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createCertificates);
    echo "<p class='success'>✓ Created certificates table</p>";
    
    echo "<h2>Adding foreign key constraints...</h2>";
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Add foreign key constraints
    $foreignKeys = [
        // Courses table
        "ALTER TABLE `courses` ADD CONSTRAINT `fk_courses_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `courses` ADD CONSTRAINT `fk_courses_training_center` FOREIGN KEY (`training_center_id`) REFERENCES `training_centers` (`id`) ON DELETE SET NULL",
        
        // Batches table
        "ALTER TABLE `batches` ADD CONSTRAINT `fk_batches_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `batches` ADD CONSTRAINT `fk_batches_training_center` FOREIGN KEY (`training_center_id`) REFERENCES `training_centers` (`id`) ON DELETE SET NULL",
        
        // Students table
        "ALTER TABLE `students` ADD CONSTRAINT `fk_students_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `students` ADD CONSTRAINT `fk_students_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `students` ADD CONSTRAINT `fk_students_training_center` FOREIGN KEY (`training_center_id`) REFERENCES `training_centers` (`id`) ON DELETE SET NULL",
        
        // Fees table
        "ALTER TABLE `fees` ADD CONSTRAINT `fk_fees_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `fees` ADD CONSTRAINT `fk_fees_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // Notifications table
        "ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        
        // Bulk Uploads table
        "ALTER TABLE `bulk_uploads` ADD CONSTRAINT `fk_bulk_uploads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `bulk_uploads` ADD CONSTRAINT `fk_bulk_uploads_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `bulk_uploads` ADD CONSTRAINT `fk_bulk_uploads_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `bulk_uploads` ADD CONSTRAINT `fk_bulk_uploads_training_center` FOREIGN KEY (`training_center_id`) REFERENCES `training_centers` (`id`) ON DELETE SET NULL",
        
        // Question Papers table
        "ALTER TABLE `question_papers` ADD CONSTRAINT `fk_question_papers_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `question_papers` ADD CONSTRAINT `fk_question_papers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // Assessments table
        "ALTER TABLE `assessments` ADD CONSTRAINT `fk_assessments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `assessments` ADD CONSTRAINT `fk_assessments_question_paper` FOREIGN KEY (`question_paper_id`) REFERENCES `question_papers` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `assessments` ADD CONSTRAINT `fk_assessments_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL",
        
        // Results table
        "ALTER TABLE `results` ADD CONSTRAINT `fk_results_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `results` ADD CONSTRAINT `fk_results_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `results` ADD CONSTRAINT `fk_results_question_paper` FOREIGN KEY (`question_paper_id`) REFERENCES `question_papers` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `results` ADD CONSTRAINT `fk_results_published_by` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // Certificates table
        "ALTER TABLE `certificates` ADD CONSTRAINT `fk_certificates_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `certificates` ADD CONSTRAINT `fk_certificates_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `certificates` ADD CONSTRAINT `fk_certificates_result` FOREIGN KEY (`result_id`) REFERENCES `results` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `certificates` ADD CONSTRAINT `fk_certificates_issued_by` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // Users table (added at the end because it references training_centers)
        "ALTER TABLE `users` ADD CONSTRAINT `fk_users_training_center` FOREIGN KEY (`training_center_id`) REFERENCES `training_centers` (`id`) ON DELETE SET NULL"
    ];
    
    foreach ($foreignKeys as $fk) {
        try {
            $db->exec($fk);
            echo "<p class='success'>✓ Added foreign key constraint</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>⚠ Skipped foreign key (might already exist): " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>Inserting default data...</h2>";
    
    // Insert admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO `users` (`username`, `email`, `password`, `role`, `name`, `full_name`, `phone`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@sms.com', $adminPassword, 'admin', 'System Administrator', 'System Administrator', '9999999999', 'active']);
    echo "<p class='success'>✓ Created admin user (username: admin, password: admin123)</p>";
    
    // Insert default settings
    $defaultSettings = [
        ['site_name', 'Student Management System', 'text', 'Website/Application name'],
        ['site_logo', '', 'file', 'Website logo image'],
        ['registration_fee', '100', 'number', 'Default registration fee amount'],
        ['currency', 'INR', 'text', 'Currency symbol'],
        ['academic_year', '2024-25', 'text', 'Current academic year'],
        ['theme_color', '#3498db', 'text', 'Primary theme color'],
        ['email_notifications', '1', 'boolean', 'Enable email notifications'],
        ['sms_notifications', '0', 'boolean', 'Enable SMS notifications']
    ];
    
    $stmt = $db->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES (?, ?, ?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    echo "<p class='success'>✓ Inserted " . count($defaultSettings) . " default settings</p>";
    
    // Insert default sectors
    $sectors = [
        ['Information Technology', 'IT001', 'Information Technology and IT Enabled Services'],
        ['Healthcare', 'HC001', 'Healthcare and Medical Services'],
        ['Automotive', 'AU001', 'Automotive and Transportation'],
        ['Retail', 'RT001', 'Retail and Customer Service'],
        ['Banking & Finance', 'BF001', 'Banking and Financial Services'],
        ['Manufacturing', 'MF001', 'Manufacturing and Production'],
        ['Beauty & Wellness', 'BW001', 'Beauty and Wellness Services'],
        ['Tourism & Hospitality', 'TH001', 'Tourism and Hospitality Services'],
        ['Agriculture', 'AG001', 'Agriculture and Related Services'],
        ['Construction', 'CS001', 'Construction and Real Estate']
    ];
    
    $stmt = $db->prepare("INSERT INTO `sectors` (`name`, `code`, `description`) VALUES (?, ?, ?)");
    foreach ($sectors as $sector) {
        $stmt->execute($sector);
    }
    echo "<p class='success'>✓ Inserted " . count($sectors) . " sectors</p>";
    
    // Insert default courses
    $courses = [
        ['Web Development', 'WD001', 1, 6, 15000.00, 'Full Stack Web Development with HTML, CSS, JavaScript, PHP'],
        ['Digital Marketing', 'DM001', 1, 3, 8000.00, 'Complete Digital Marketing and Social Media Marketing'],
        ['Data Entry Operator', 'DE001', 1, 2, 5000.00, 'Computer Data Entry and MS Office'],
        ['Mobile App Development', 'MA001', 1, 8, 20000.00, 'Android and iOS App Development'],
        ['Cyber Security', 'CS001', 1, 4, 18000.00, 'Information Security and Ethical Hacking'],
        
        ['Nursing Assistant', 'NA001', 2, 12, 25000.00, 'Healthcare and Patient Care Assistant'],
        ['Medical Lab Technician', 'ML001', 2, 10, 18000.00, 'Laboratory Testing and Analysis'],
        ['Pharmacy Assistant', 'PA001', 2, 6, 12000.00, 'Pharmaceutical Services and Medicine Management'],
        
        ['Automotive Technician', 'AT001', 3, 8, 18000.00, 'Vehicle Maintenance and Repair'],
        ['Electric Vehicle Tech', 'EV001', 3, 6, 22000.00, 'Electric Vehicle Technology and Maintenance'],
        
        ['Retail Sales Associate', 'RS001', 4, 4, 10000.00, 'Customer Service and Sales'],
        ['E-commerce Specialist', 'EC001', 4, 5, 12000.00, 'Online Business and E-commerce Management'],
        
        ['Banking Operations', 'BO001', 5, 6, 12000.00, 'Banking Procedures and Customer Relations'],
        ['Financial Services', 'FS001', 5, 8, 16000.00, 'Investment and Financial Planning'],
        ['Insurance Agent', 'IA001', 5, 3, 8000.00, 'Insurance Products and Customer Service']
    ];
    
    $stmt = $db->prepare("INSERT INTO `courses` (`name`, `code`, `sector_id`, `duration_months`, `fee_amount`, `description`) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($courses as $course) {
        $stmt->execute($course);
    }
    echo "<p class='success'>✓ Inserted " . count($courses) . " courses</p>";
    
    // Insert sample training center
    $tcPassword = password_hash('demo123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO `training_centers` (`tc_id`, `name`, `contact_person`, `email`, `phone`, `address`, `city`, `state`, `pincode`, `password`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['TC2024001', 'Demo Training Center', 'John Doe', 'demo@center.com', '9876543210', '123 Training Street', 'Mumbai', 'Maharashtra', '400001', $tcPassword, 'active']);
    echo "<p class='success'>✓ Created demo training center (email: demo@center.com, password: demo123)</p>";
    
    // Insert sample student
    $studentPassword = password_hash('student123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO `students` (`enrollment_no`, `name`, `father_name`, `email`, `phone`, `aadhaar`, `dob`, `gender`, `education`, `course_id`, `training_center_id`, `address`, `password`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['2024001', 'Test Student', 'Test Father', 'student@test.com', '9999999999', '123456789012', '2000-01-01', 'Male', 'Graduation', 1, 1, 'Test Address', $studentPassword, 'active']);
    echo "<p class='success'>✓ Created demo student (phone: 9999999999, password: student123)</p>";
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✅ Database Setup Completed Successfully!</h3>";
    echo "<p><strong>Admin Login:</strong> username: admin, password: admin123</p>";
    echo "<p><strong>Student Login:</strong> phone: 9999999999, password: student123</p>";
    echo "<p><strong>Training Center Login:</strong> email: demo@center.com, password: demo123</p>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='login-new.php' class='btn btn-primary'>🚀 Go to Login Page</a> ";
    echo "<a href='dashboard-modern.php' class='btn btn-secondary'>📊 View Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Database Setup Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<details><summary>Technical Details</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='config-check.php' class='btn btn-secondary'>🔧 Check Configuration</a> ";
    echo "<a href='../config/database.php' class='btn btn-secondary'>⚙️ Database Config</a>";
    echo "</div>";
} finally {
    // Always re-enable foreign key checks
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $e) {
        // Ignore errors when re-enabling
    }
}

echo "</div></body></html>";
?>

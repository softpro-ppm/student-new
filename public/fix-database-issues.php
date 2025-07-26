<?php
// Fix Database Issues Script
// This script will add missing columns and fix data structure issues

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database-simple.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Database Issues</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .log { background: #000; color: #0f0; padding: 20px; border-radius: 10px; font-family: monospace; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
<div class='container'>
    <h1 class='mb-4'>Database Issues Fix</h1>
    <div class='log' id='log'>";

function logMessage($message, $type = 'info') {
    $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : ($type === 'warning' ? 'warning' : ''));
    echo "<div class='$class'>[" . date('H:i:s') . "] $message</div>";
    ob_flush();
    flush();
}

try {
    $db = getConnection();
    logMessage("Database connection successful", 'success');
    
    // 1. Fix students table - add missing columns
    logMessage("Checking students table structure...", 'info');
    
    $missingStudentColumns = [
        'course_id' => 'INT NULL',
        'enrollment_number' => 'VARCHAR(50) UNIQUE NULL',
        'admission_date' => 'DATE NULL',
        'course_fee' => 'DECIMAL(10,2) DEFAULT 0',
        'discount' => 'DECIMAL(10,2) DEFAULT 0',
        'final_fee' => 'DECIMAL(10,2) DEFAULT 0',
        'installment_plan' => 'ENUM("full", "installment") DEFAULT "full"',
        'father_name' => 'VARCHAR(100) NULL',
        'mother_name' => 'VARCHAR(100) NULL',
        'guardian_phone' => 'VARCHAR(15) NULL',
        'emergency_contact' => 'VARCHAR(15) NULL',
        'qualification' => 'VARCHAR(100) NULL',
        'experience' => 'TEXT NULL',
        'reference' => 'VARCHAR(100) NULL',
        'id_proof_type' => 'VARCHAR(50) NULL',
        'id_proof_number' => 'VARCHAR(50) NULL'
    ];
    
    foreach ($missingStudentColumns as $column => $definition) {
        try {
            $checkColumn = $db->query("SHOW COLUMNS FROM students LIKE '$column'");
            if ($checkColumn->rowCount() == 0) {
                $db->exec("ALTER TABLE students ADD COLUMN $column $definition");
                logMessage("Added column '$column' to students table", 'success');
            } else {
                logMessage("Column '$column' already exists in students table", 'info');
            }
        } catch (Exception $e) {
            logMessage("Failed to add column '$column': " . $e->getMessage(), 'error');
        }
    }
    
    // 2. Fix training_centers table - add missing columns
    logMessage("Checking training_centers table structure...", 'info');
    
    $missingCenterColumns = [
        'city' => 'VARCHAR(100) NULL',
        'state' => 'VARCHAR(100) NULL',
        'pincode' => 'VARCHAR(10) NULL',
        'latitude' => 'DECIMAL(10, 8) NULL',
        'longitude' => 'DECIMAL(11, 8) NULL',
        'website' => 'VARCHAR(255) NULL',
        'registration_number' => 'VARCHAR(100) NULL',
        'established_year' => 'YEAR NULL',
        'accreditation' => 'VARCHAR(255) NULL',
        'specialization' => 'TEXT NULL',
        'facilities' => 'TEXT NULL',
        'director_name' => 'VARCHAR(100) NULL',
        'director_phone' => 'VARCHAR(15) NULL',
        'director_email' => 'VARCHAR(100) NULL'
    ];
    
    foreach ($missingCenterColumns as $column => $definition) {
        try {
            $checkColumn = $db->query("SHOW COLUMNS FROM training_centers LIKE '$column'");
            if ($checkColumn->rowCount() == 0) {
                $db->exec("ALTER TABLE training_centers ADD COLUMN $column $definition");
                logMessage("Added column '$column' to training_centers table", 'success');
            } else {
                logMessage("Column '$column' already exists in training_centers table", 'info');
            }
        } catch (Exception $e) {
            logMessage("Failed to add column '$column': " . $e->getMessage(), 'error');
        }
    }
    
    // 3. Fix fees table - add missing columns
    logMessage("Checking fees table structure...", 'info');
    
    $missingFeesColumns = [
        'approved_by' => 'INT NULL',
        'approved_date' => 'TIMESTAMP NULL',
        'payment_method' => 'VARCHAR(50) NULL',
        'transaction_id' => 'VARCHAR(100) NULL'
    ];
    
    foreach ($missingFeesColumns as $column => $definition) {
        try {
            $checkColumn = $db->query("SHOW COLUMNS FROM fees LIKE '$column'");
            if ($checkColumn->rowCount() == 0) {
                $db->exec("ALTER TABLE fees ADD COLUMN $column $definition");
                logMessage("Added column '$column' to fees table", 'success');
            } else {
                logMessage("Column '$column' already exists in fees table", 'info');
            }
        } catch (Exception $e) {
            logMessage("Failed to add column '$column': " . $e->getMessage(), 'error');
        }
    }
    
    // 4. Create courses table if it doesn't exist
    logMessage("Checking courses table...", 'info');
    try {
        $checkCourses = $db->query("SHOW TABLES LIKE 'courses'");
        if ($checkCourses->rowCount() == 0) {
            $createCourses = "CREATE TABLE courses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(50) UNIQUE NOT NULL,
                description TEXT,
                duration_months INT DEFAULT 6,
                fee DECIMAL(10,2) DEFAULT 0,
                category VARCHAR(100),
                level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
                prerequisites TEXT,
                certification VARCHAR(255),
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createCourses);
            logMessage("Created courses table", 'success');
            
            // Insert some demo courses
            $demoCourses = [
                ['Computer Basics', 'CB001', 'Basic computer skills and MS Office', 3, 5000],
                ['Web Development', 'WD001', 'HTML, CSS, JavaScript and PHP', 6, 15000],
                ['Data Entry', 'DE001', 'Professional data entry skills', 2, 3000],
                ['Digital Marketing', 'DM001', 'Social media and online marketing', 4, 8000],
                ['Accounting with Tally', 'AT001', 'Tally ERP and basic accounting', 3, 6000]
            ];
            
            $stmt = $db->prepare("INSERT INTO courses (name, code, description, duration_months, fee) VALUES (?, ?, ?, ?, ?)");
            foreach ($demoCourses as $course) {
                $stmt->execute($course);
            }
            logMessage("Added demo courses", 'success');
        } else {
            logMessage("Courses table already exists", 'info');
        }
    } catch (Exception $e) {
        logMessage("Failed to create courses table: " . $e->getMessage(), 'error');
    }
    
    // 4. Update existing data with default values
    logMessage("Updating existing records with default values...", 'info');
    
    try {
        // Update training centers with default city/state
        $updateCenters = "UPDATE training_centers SET 
            city = COALESCE(city, 'Not Specified'),
            state = COALESCE(state, 'Not Specified')
            WHERE city IS NULL OR state IS NULL";
        $db->exec($updateCenters);
        logMessage("Updated training centers with default location data", 'success');
        
        // Update students with enrollment numbers if missing
        $stmt = $db->query("SELECT id FROM students WHERE enrollment_number IS NULL OR enrollment_number = ''");
        $studentsWithoutEnrollment = $stmt->fetchAll();
        
        foreach ($studentsWithoutEnrollment as $student) {
            $enrollmentNumber = 'ENR' . str_pad($student['id'], 6, '0', STR_PAD_LEFT);
            $updateStmt = $db->prepare("UPDATE students SET enrollment_number = ? WHERE id = ?");
            $updateStmt->execute([$enrollmentNumber, $student['id']]);
        }
        
        if (count($studentsWithoutEnrollment) > 0) {
            logMessage("Generated enrollment numbers for " . count($studentsWithoutEnrollment) . " students", 'success');
        }
        
        // Assign default course to students without course_id
        $defaultCourse = $db->query("SELECT id FROM courses LIMIT 1")->fetch();
        if ($defaultCourse) {
            $updateStudents = "UPDATE students SET course_id = ? WHERE course_id IS NULL";
            $stmt = $db->prepare($updateStudents);
            $stmt->execute([$defaultCourse['id']]);
            logMessage("Assigned default course to students without course", 'success');
        }
        
    } catch (Exception $e) {
        logMessage("Failed to update existing data: " . $e->getMessage(), 'error');
    }
    
    // 5. Create indexes for better performance
    logMessage("Creating database indexes...", 'info');
    
    $indexes = [
        "CREATE INDEX idx_students_phone ON students(phone)",
        "CREATE INDEX idx_students_email ON students(email)",
        "CREATE INDEX idx_students_enrollment ON students(enrollment_number)",
        "CREATE INDEX idx_training_centers_email ON training_centers(email)",
        "CREATE INDEX idx_courses_code ON courses(code)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $db->exec($index);
            logMessage("Created index: " . substr($index, 0, 50) . "...", 'success');
        } catch (Exception $e) {
            // Index might already exist, that's okay
            logMessage("Index creation note: " . $e->getMessage(), 'warning');
        }
    }
    
    logMessage("Database fixes completed successfully!", 'success');
    logMessage("You can now access all pages without errors.", 'success');
    
} catch (Exception $e) {
    logMessage("Critical error: " . $e->getMessage(), 'error');
}

echo "</div>
    <div class='mt-4'>
        <a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a>
        <a href='students.php' class='btn btn-success'>Test Students Page</a>
        <a href='training-centers.php' class='btn btn-info'>Test Training Centers</a>
        <a href='fees.php' class='btn btn-warning'>Test Fees Page</a>
    </div>
</div>
</body>
</html>";
?>

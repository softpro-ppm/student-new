<?php
// Add sample data for testing
require_once '../config/database.php';

try {
    $db = getConnection();
    
    // Add sample sectors if none exist
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sectors");
    $stmt->execute();
    $sectorCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($sectorCount == 0) {
        echo "Adding sample sectors...\n";
        
        $sectors = [
            ['Information Technology', 'IT', 'IT and Software Development'],
            ['Healthcare', 'HC', 'Healthcare and Medical Services'],
            ['Marketing', 'MKT', 'Marketing and Advertisement'],
            ['Design', 'DES', 'Design and Creative Arts'],
            ['Finance', 'FIN', 'Finance and Banking'],
            ['Education', 'EDU', 'Education and Training']
        ];
        
        $insertSector = $db->prepare("INSERT INTO sectors (name, code, description) VALUES (?, ?, ?)");
        
        foreach ($sectors as $sector) {
            $insertSector->execute($sector);
        }
        
        echo "✓ Sample sectors added\n";
    }
    
    // Add more sample courses if needed
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM courses");
    $stmt->execute();
    $courseCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($courseCount < 5) {
        echo "Adding more sample courses...\n";
        
        // Get sector IDs
        $stmt = $db->prepare("SELECT id, name FROM sectors");
        $stmt->execute();
        $sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sectorMap = [];
        foreach ($sectors as $sector) {
            $sectorMap[$sector['name']] = $sector['id'];
        }
        
        $courses = [
            ['Full Stack Web Development', 'WEB001', 'Complete web development with frontend and backend', 6, 25000.00, $sectorMap['Information Technology'] ?? null],
            ['Data Science & Analytics', 'DS001', 'Data analysis, machine learning, and AI', 8, 35000.00, $sectorMap['Information Technology'] ?? null],
            ['Digital Marketing', 'DM001', 'SEO, SEM, Social Media Marketing', 4, 15000.00, $sectorMap['Marketing'] ?? null],
            ['UI/UX Design', 'UI001', 'User Interface and User Experience Design', 5, 20000.00, $sectorMap['Design'] ?? null],
            ['Healthcare Management', 'HM001', 'Hospital and healthcare administration', 6, 18000.00, $sectorMap['Healthcare'] ?? null],
            ['Financial Planning', 'FP001', 'Personal and corporate financial planning', 4, 22000.00, $sectorMap['Finance'] ?? null],
            ['Mobile App Development', 'MAD001', 'Android and iOS app development', 7, 28000.00, $sectorMap['Information Technology'] ?? null],
            ['Graphic Design', 'GD001', 'Print and digital graphic design', 4, 16000.00, $sectorMap['Design'] ?? null]
        ];
        
        $insertCourse = $db->prepare("INSERT INTO courses (name, code, description, duration_months, fee, sector_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
        
        foreach ($courses as $course) {
            $insertCourse->execute($course);
        }
        
        echo "✓ Sample courses added\n";
    }
    
    // Add sample fees if none exist
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM fees");
    $stmt->execute();
    $feeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($feeCount == 0) {
        echo "Adding sample fee records...\n";
        
        // Get a student ID
        $stmt = $db->prepare("SELECT id FROM students LIMIT 1");
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $fees = [
                [$student['id'], 5000.00, 'admission', date('Y-m-d'), 'Admission fee', 'pending'],
                [$student['id'], 20000.00, 'course', date('Y-m-d', strtotime('+30 days')), 'Course fee - installment 1', 'pending'],
                [$student['id'], 15000.00, 'course', date('Y-m-d', strtotime('+60 days')), 'Course fee - installment 2', 'pending'],
                [$student['id'], 2000.00, 'exam', date('Y-m-d', strtotime('+90 days')), 'Exam fee', 'pending']
            ];
            
            $insertFee = $db->prepare("INSERT INTO fees (student_id, amount, fee_type, due_date, notes, status) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($fees as $fee) {
                $insertFee->execute($fee);
            }
            
            echo "✓ Sample fee records added\n";
        }
    }
    
    // Add sample results if none exist
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results");
    $stmt->execute();
    $resultCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($resultCount == 0) {
        echo "Adding sample assessment results...\n";
        
        // Get a student ID
        $stmt = $db->prepare("SELECT id FROM students LIMIT 1");
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $results = [
                [$student['id'], null, 85, 100, 85.0, 'A', 'pass', 1],
                [$student['id'], null, 78, 100, 78.0, 'B+', 'pass', 1],
                [$student['id'], null, 92, 100, 92.0, 'A+', 'pass', 1]
            ];
            
            $insertResult = $db->prepare("INSERT INTO results (student_id, assessment_id, marks_obtained, total_marks, percentage, grade, result_status, attempt_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($results as $result) {
                $insertResult->execute($result);
            }
            
            echo "✓ Sample assessment results added\n";
        }
    }
    
    echo "\n=== FINAL SUMMARY ===\n";
    
    // Show final counts
    $tables = ['sectors', 'courses', 'students', 'fees', 'results', 'batches', 'training_centers'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo ucfirst($table) . ": $count records\n";
    }
    
    echo "\n✅ All pages should now have data to display!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

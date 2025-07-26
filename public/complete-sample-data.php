<?php
// Complete sample data insertion with proper order
require_once '../config/database.php';

try {
    $db = getConnection();
    
    echo "Creating comprehensive sample data...\n\n";
    
    // 1. Add sample sectors first
    echo "1. Adding sample sectors...\n";
    $sectors = [
        ['name' => 'Information Technology', 'code' => 'IT'],
        ['name' => 'Healthcare', 'code' => 'HC'],
        ['name' => 'Retail', 'code' => 'RT'],
        ['name' => 'Banking & Finance', 'code' => 'BF']
    ];
    
    foreach ($sectors as $sector) {
        $stmt = $db->prepare("INSERT IGNORE INTO sectors (name, code, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$sector['name'], $sector['code']]);
    }
    echo "✓ Sectors added\n";
    
    // 2. Add sample courses
    echo "2. Adding sample courses...\n";
    $courses = [
        ['name' => 'Web Development', 'code' => 'WD001', 'sector_id' => 1, 'duration' => 6],
        ['name' => 'Data Analysis', 'code' => 'DA001', 'sector_id' => 1, 'duration' => 4],
        ['name' => 'Nursing Assistant', 'code' => 'NA001', 'sector_id' => 2, 'duration' => 8],
        ['name' => 'Customer Service', 'code' => 'CS001', 'sector_id' => 3, 'duration' => 3],
        ['name' => 'Banking Operations', 'code' => 'BO001', 'sector_id' => 4, 'duration' => 5]
    ];
    
    foreach ($courses as $course) {
        $stmt = $db->prepare("INSERT IGNORE INTO courses (name, code, sector_id, duration_months, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$course['name'], $course['code'], $course['sector_id'], $course['duration']]);
    }
    echo "✓ Courses added\n";
    
    // 3. Add sample assessments
    echo "3. Adding sample assessments...\n";
    $assessments = [
        ['title' => 'Web Development Final Assessment', 'course_id' => 1, 'max_marks' => 100],
        ['title' => 'Data Analysis Project', 'course_id' => 2, 'max_marks' => 100],
        ['title' => 'Nursing Skills Test', 'course_id' => 3, 'max_marks' => 100],
        ['title' => 'Customer Service Role Play', 'course_id' => 4, 'max_marks' => 100],
        ['title' => 'Banking Knowledge Test', 'course_id' => 5, 'max_marks' => 100]
    ];
    
    foreach ($assessments as $assessment) {
        $stmt = $db->prepare("INSERT IGNORE INTO assessments (title, course_id, max_marks, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$assessment['title'], $assessment['course_id'], $assessment['max_marks']]);
    }
    echo "✓ Assessments added\n";
    
    // 4. Add sample students
    echo "4. Adding sample students...\n";
    $students = [
        ['name' => 'John Doe', 'email' => 'john@email.com', 'phone' => '9876543210', 'course_id' => 1],
        ['name' => 'Jane Smith', 'email' => 'jane@email.com', 'phone' => '9876543211', 'course_id' => 2],
        ['name' => 'Mike Johnson', 'email' => 'mike@email.com', 'phone' => '9876543212', 'course_id' => 3],
        ['name' => 'Sarah Williams', 'email' => 'sarah@email.com', 'phone' => '9876543213', 'course_id' => 4],
        ['name' => 'David Brown', 'email' => 'david@email.com', 'phone' => '9876543214', 'course_id' => 5],
        ['name' => 'Lisa Davis', 'email' => 'lisa@email.com', 'phone' => '9876543215', 'course_id' => 1],
        ['name' => 'Tom Wilson', 'email' => 'tom@email.com', 'phone' => '9876543216', 'course_id' => 2],
        ['name' => 'Emma Garcia', 'email' => 'emma@email.com', 'phone' => '9876543217', 'course_id' => 3]
    ];
    
    foreach ($students as $student) {
        $stmt = $db->prepare("INSERT IGNORE INTO students (name, email, phone, course_id, enrollment_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$student['name'], $student['email'], $student['phone'], $student['course_id']]);
    }
    echo "✓ Students added\n";
    
    // 5. Add sample fees
    echo "5. Adding sample fees...\n";
    $fees = [
        ['student_id' => 1, 'amount' => 25000, 'fee_type' => 'Course Fee', 'status' => 'paid'],
        ['student_id' => 2, 'amount' => 20000, 'fee_type' => 'Course Fee', 'status' => 'paid'],
        ['student_id' => 3, 'amount' => 30000, 'fee_type' => 'Course Fee', 'status' => 'pending'],
        ['student_id' => 4, 'amount' => 15000, 'fee_type' => 'Course Fee', 'status' => 'paid'],
        ['student_id' => 5, 'amount' => 22000, 'fee_type' => 'Course Fee', 'status' => 'partial'],
        ['student_id' => 6, 'amount' => 25000, 'fee_type' => 'Course Fee', 'status' => 'pending'],
        ['student_id' => 7, 'amount' => 20000, 'fee_type' => 'Course Fee', 'status' => 'paid'],
        ['student_id' => 8, 'amount' => 30000, 'fee_type' => 'Course Fee', 'status' => 'partial']
    ];
    
    foreach ($fees as $fee) {
        $stmt = $db->prepare("INSERT IGNORE INTO fees (student_id, amount, fee_type, status, due_date, created_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
        $stmt->execute([$fee['student_id'], $fee['amount'], $fee['fee_type'], $fee['status']]);
    }
    echo "✓ Fees added\n";
    
    // 6. Add sample results
    echo "6. Adding sample results...\n";
    $results = [
        ['student_id' => 1, 'assessment_id' => 1, 'marks_obtained' => 85, 'total_marks' => 100, 'result_status' => 'pass'],
        ['student_id' => 2, 'assessment_id' => 2, 'marks_obtained' => 92, 'total_marks' => 100, 'result_status' => 'pass'],
        ['student_id' => 3, 'assessment_id' => 3, 'marks_obtained' => 78, 'total_marks' => 100, 'result_status' => 'pass'],
        ['student_id' => 4, 'assessment_id' => 4, 'marks_obtained' => 88, 'total_marks' => 100, 'result_status' => 'pass'],
        ['student_id' => 5, 'assessment_id' => 5, 'marks_obtained' => 45, 'total_marks' => 100, 'result_status' => 'fail'],
        ['student_id' => 6, 'assessment_id' => 1, 'marks_obtained' => 91, 'total_marks' => 100, 'result_status' => 'pass'],
        ['student_id' => 7, 'assessment_id' => 2, 'marks_obtained' => 76, 'total_marks' => 100, 'result_status' => 'pass'],
        ['student_id' => 8, 'assessment_id' => 3, 'marks_obtained' => 82, 'total_marks' => 100, 'result_status' => 'pass']
    ];
    
    foreach ($results as $result) {
        $stmt = $db->prepare("INSERT IGNORE INTO results (student_id, assessment_id, marks_obtained, total_marks, result_status, attempt_number, completed_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$result['student_id'], $result['assessment_id'], $result['marks_obtained'], $result['total_marks'], $result['result_status']]);
    }
    echo "✓ Results added\n";
    
    // Display summary
    echo "\n📊 Sample Data Summary:\n";
    $stmt = $db->query("SELECT COUNT(*) as count FROM sectors");
    echo "- Sectors: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM courses");
    echo "- Courses: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM assessments");
    echo "- Assessments: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM students");
    echo "- Students: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM fees");
    echo "- Fee Records: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM results");
    echo "- Results: " . $stmt->fetch()['count'] . "\n";
    
    echo "\n✅ All sample data created successfully!\n";
    echo "You can now test the Reports, Fees, and Masters pages.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

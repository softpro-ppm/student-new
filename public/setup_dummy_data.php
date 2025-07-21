<?php
// Dummy Data Setup for Testing
require_once '../config/database-simple.php';

$db = getConnection();

if (!$db) {
    die('Database connection failed!');
}

echo "<h1>Student Management System - Dummy Data Setup</h1>";

try {
    echo "<h2>Creating sample training centers...</h2>";
    
    // Sample Training Centers
    $trainingCenters = [
        ['TC001', 'Excel Training Institute', 'excel@example.com', '9876543210', '123 IT Park, Hyderabad', password_hash('tc123', PASSWORD_DEFAULT)],
        ['TC002', 'Tech Skills Academy', 'techskills@example.com', '9876543211', '456 Tech City, Bangalore', password_hash('tc123', PASSWORD_DEFAULT)],
        ['TC003', 'Digital Learning Hub', 'digital@example.com', '9876543212', '789 Knowledge Lane, Chennai', password_hash('tc123', PASSWORD_DEFAULT)],
        ['TC004', 'Professional Skills Center', 'psc@example.com', '9876543213', '321 Education Street, Mumbai', password_hash('tc123', PASSWORD_DEFAULT)],
        ['TC005', 'Advanced Training Solutions', 'ats@example.com', '9876543214', '654 Learning Boulevard, Pune', password_hash('tc123', PASSWORD_DEFAULT)]
    ];
    
    $stmt = $db->prepare("INSERT INTO training_centers (tc_id, name, email, phone, address, password) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($trainingCenters as $tc) {
        $stmt->execute($tc);
    }
    echo "<p>✓ Created " . count($trainingCenters) . " training centers</p>";
    
    echo "<h2>Creating sample batches...</h2>";
    
    // Sample Batches
    $batches = [
        ['Web Dev Batch 001', 1, 1, '2024-01-15', '2024-07-15', '09:00:00', '17:00:00', 'ongoing'],
        ['Digital Marketing 001', 2, 1, '2024-02-01', '2024-05-01', '10:00:00', '16:00:00', 'ongoing'],
        ['Data Entry 001', 3, 2, '2024-01-20', '2024-03-20', '09:30:00', '15:30:00', 'completed'],
        ['Mobile App Dev 001', 4, 2, '2024-03-01', '2024-11-01', '10:00:00', '18:00:00', 'ongoing'],
        ['Nursing Assistant 001', 5, 3, '2024-01-10', '2025-01-10', '08:00:00', '16:00:00', 'ongoing'],
        ['Auto Tech 001', 7, 4, '2024-02-15', '2024-10-15', '09:00:00', '17:00:00', 'ongoing'],
        ['Retail Sales 001', 9, 5, '2024-03-10', '2024-07-10', '10:00:00', '16:00:00', 'planned'],
        ['Banking Ops 001', 11, 1, '2024-04-01', '2024-10-01', '09:30:00', '17:30:00', 'planned'],
        ['Production Supervisor 001', 13, 3, '2024-02-20', '2024-11-20', '08:30:00', '17:30:00', 'ongoing'],
        ['Quality Control 001', 14, 4, '2024-03-15', '2024-10-15', '09:00:00', '17:00:00', 'ongoing']
    ];
    
    $stmt = $db->prepare("INSERT INTO batches (name, course_id, training_center_id, start_date, end_date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($batches as $batch) {
        $stmt->execute($batch);
    }
    echo "<p>✓ Created " . count($batches) . " batches</p>";
    
    echo "<h2>Creating sample students...</h2>";
    
    // Sample Students
    $students = [
        ['ENR001', 'Rajesh Kumar', 'Suresh Kumar', 'rajesh@example.com', '9999999999', '123456789012', '1995-05-15', 'male', 'Graduation', 'single', 1, 1, 1],
        ['ENR002', 'Priya Sharma', 'Ram Sharma', 'priya@example.com', '9999999998', '123456789013', '1997-08-22', 'female', 'Intermediate', 'single', 2, 2, 1],
        ['ENR003', 'Amit Singh', 'Ravi Singh', 'amit@example.com', '9999999997', '123456789014', '1994-12-10', 'male', 'B.Tech', 'married', 3, 3, 2],
        ['ENR004', 'Sunita Patel', 'Mohan Patel', 'sunita@example.com', '9999999996', '123456789015', '1996-03-18', 'female', 'Diploma', 'single', 4, 4, 2],
        ['ENR005', 'Vikram Reddy', 'Krishna Reddy', 'vikram@example.com', '9999999995', '123456789016', '1993-07-25', 'male', 'Post Graduation', 'married', 5, 5, 3],
        ['ENR006', 'Kavita Gupta', 'Ramesh Gupta', 'kavita@example.com', '9999999994', '123456789017', '1998-11-12', 'female', 'SSC', 'single', 7, 6, 4],
        ['ENR007', 'Arjun Yadav', 'Sanjay Yadav', 'arjun@example.com', '9999999993', '123456789018', '1995-09-30', 'male', 'Graduation', 'single', 9, 7, 5],
        ['ENR008', 'Deepika Joshi', 'Mahesh Joshi', 'deepika@example.com', '9999999992', '123456789019', '1997-01-08', 'female', 'Intermediate', 'single', 11, 8, 1],
        ['ENR009', 'Rohit Malhotra', 'Anil Malhotra', 'rohit@example.com', '9999999991', '123456789020', '1994-04-14', 'male', 'B.Tech', 'married', 13, 9, 3],
        ['ENR010', 'Neha Agarwal', 'Sunil Agarwal', 'neha@example.com', '9999999990', '123456789021', '1996-06-20', 'female', 'Diploma', 'single', 14, 10, 4],
        ['ENR011', 'Manish Verma', 'Rakesh Verma', 'manish@example.com', '9999999989', '123456789022', '1995-02-28', 'male', 'Graduation', 'single', 1, 1, 1],
        ['ENR012', 'Anjali Tiwari', 'Dinesh Tiwari', 'anjali@example.com', '9999999988', '123456789023', '1998-10-15', 'female', 'SSC', 'single', 2, 2, 2],
        ['ENR013', 'Saurabh Mishra', 'Hari Mishra', 'saurabh@example.com', '9999999987', '123456789024', '1993-12-03', 'male', 'Post Graduation', 'married', 4, 4, 2],
        ['ENR014', 'Pooja Saxena', 'Ajay Saxena', 'pooja@example.com', '9999999986', '123456789025', '1997-05-11', 'female', 'Intermediate', 'single', 5, 5, 3],
        ['ENR015', 'Ravi Choudhary', 'Mohan Choudhary', 'ravi@example.com', '9999999985', '123456789026', '1996-08-19', 'male', 'Graduation', 'single', 7, 6, 4]
    ];
    
    $stmt = $db->prepare("INSERT INTO students (enrollment_number, name, father_name, email, phone, aadhaar, dob, gender, education, marital_status, course_id, batch_id, training_center_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($students as $student) {
        $stmt->execute($student);
    }
    echo "<p>✓ Created " . count($students) . " students</p>";
    
    echo "<h2>Creating fee records...</h2>";
    
    // Sample Fees
    $fees = [];
    for ($i = 1; $i <= 15; $i++) {
        // Registration fee
        $fees[] = [$i, 100.00, 'registration', 'paid', '2024-01-01', '2024-01-01', 1, '2024-01-01', 'REG' . str_pad($i, 3, '0', STR_PAD_LEFT), 'Registration fee'];
        
        // Course fee (split into 3 EMIs for some students)
        if ($i <= 10) {
            $courseFee = [15000, 8000, 5000, 20000, 25000, 18000, 10000, 12000, 14000, 13000][$i-1];
            $emiAmount = $courseFee / 3;
            
            // First EMI - paid
            $fees[] = [$i, $emiAmount, 'emi', 'paid', '2024-02-01', '2024-02-01', 1, '2024-02-01', 'EMI' . str_pad($i*3-2, 3, '0', STR_PAD_LEFT), 'Course fee EMI 1'];
            
            // Second EMI - paid for some, pending for others
            $status = $i <= 7 ? 'paid' : 'pending';
            $paidDate = $i <= 7 ? '2024-03-01' : null;
            $fees[] = [$i, $emiAmount, 'emi', $status, '2024-03-01', $paidDate, $status == 'paid' ? 1 : null, $status == 'paid' ? '2024-03-01' : null, $status == 'paid' ? 'EMI' . str_pad($i*3-1, 3, '0', STR_PAD_LEFT) : null, 'Course fee EMI 2'];
            
            // Third EMI - pending for all
            $fees[] = [$i, $emiAmount, 'emi', 'pending', '2024-04-01', null, null, null, null, 'Course fee EMI 3'];
        }
    }
    
    $stmt = $db->prepare("INSERT INTO fees (student_id, amount, fee_type, status, due_date, paid_date, approved_by, approved_date, receipt_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($fees as $fee) {
        $stmt->execute($fee);
    }
    echo "<p>✓ Created " . count($fees) . " fee records</p>";
    
    echo "<h2>Creating sample question papers...</h2>";
    
    // Sample Question Papers
    $questionPapers = [];
    for ($courseId = 1; $courseId <= 5; $courseId++) {
        $courseName = ['Web Development', 'Digital Marketing', 'Data Entry', 'Mobile App Development', 'Nursing Assistant'][$courseId-1];
        
        $questions = [
            [
                'question' => 'What does HTML stand for?',
                'options' => ['Hyper Text Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyperlink and Text Markup Language'],
                'correct' => 0
            ],
            [
                'question' => 'Which CSS property is used to change the text color?',
                'options' => ['color', 'text-color', 'font-color', 'text-style'],
                'correct' => 0
            ],
            [
                'question' => 'What is the correct way to create a function in JavaScript?',
                'options' => ['function myFunction() {}', 'create myFunction() {}', 'function = myFunction() {}', 'def myFunction() {}'],
                'correct' => 0
            ]
        ];
        
        $questionPapers[] = [$courseName . ' Assessment', $courseId, count($questions), 60, 70, json_encode($questions)];
    }
    
    $stmt = $db->prepare("INSERT INTO question_papers (title, course_id, total_questions, duration_minutes, passing_marks, questions) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($questionPapers as $qp) {
        $stmt->execute($qp);
    }
    echo "<p>✓ Created " . count($questionPapers) . " question papers</p>";
    
    echo "<h2>Creating sample assessments...</h2>";
    
    // Sample Assessments
    $assessments = [];
    for ($batchId = 1; $batchId <= 5; $batchId++) {
        $assessments[] = [$batchId, $batchId, '2024-06-15', 'Complete the assessment within the given time. All questions are mandatory.', 'scheduled'];
    }
    
    $stmt = $db->prepare("INSERT INTO assessments (batch_id, question_paper_id, assessment_date, instructions, status) VALUES (?, ?, ?, ?, ?)");
    foreach ($assessments as $assessment) {
        $stmt->execute($assessment);
    }
    echo "<p>✓ Created " . count($assessments) . " assessments</p>";
    
    echo "<h2>Creating training partner users...</h2>";
    
    // Create training partner users
    $stmt = $db->prepare("INSERT INTO users (username, email, password, role, name, phone, training_center_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    for ($i = 1; $i <= 5; $i++) {
        $tcData = $trainingCenters[$i-1];
        $username = 'tc' . str_pad($i, 3, '0', STR_PAD_LEFT);
        $password = password_hash('tc123', PASSWORD_DEFAULT);
        $stmt->execute([$username, $tcData[2], $password, 'training_partner', $tcData[1] . ' Admin', $tcData[3], $i]);
    }
    echo "<p>✓ Created 5 training partner users (tc001/tc123, tc002/tc123, etc.)</p>";
    
    echo "<h2>Creating student users...</h2>";
    
    // Create student users (using their phone numbers as username)
    $stmt = $db->prepare("INSERT INTO users (username, email, password, role, name, phone) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($students as $index => $student) {
        $studentId = $index + 1;
        $username = $student[4]; // phone number
        $password = password_hash('student123', PASSWORD_DEFAULT);
        $stmt->execute([$username, $student[3], $password, 'student', $student[1], $student[4]]);
    }
    echo "<p>✓ Created " . count($students) . " student users (phone/student123)</p>";
    
    echo "<h2>Creating sample notifications...</h2>";
    
    // Sample Notifications
    $notifications = [
        [1, 'Welcome to Student Management System', 'Your account has been created successfully. Start exploring the features.', 'success'],
        [1, 'New Training Center Added', 'Excel Training Institute has been added to the system.', 'info'],
        [1, 'Fee Payment Reminder', 'Multiple students have pending fee payments. Please review.', 'warning'],
        [2, 'Profile Updated', 'Your training center profile has been updated successfully.', 'success'],
        [3, 'Batch Assignment', 'You have been assigned to Web Dev Batch 001.', 'info'],
    ];
    
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    foreach ($notifications as $notification) {
        $stmt->execute($notification);
    }
    echo "<p>✓ Created " . count($notifications) . " notifications</p>";
    
    echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✅ Dummy data setup completed successfully!</p>";
    
    echo "<h3>Test Credentials:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Training Centers:</strong> tc001/tc123, tc002/tc123, tc003/tc123, tc004/tc123, tc005/tc123</li>";
    echo "<li><strong>Students:</strong> 9999999999/student123, 9999999998/student123, etc.</li>";
    echo "</ul>";
    
    echo "<p><a href='login.php' class='btn btn-success'>→ Login to System</a></p>";
    echo "<p><a href='dashboard.php' class='btn btn-primary'>→ Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

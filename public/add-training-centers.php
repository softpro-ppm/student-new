<?php
// Add sample training centers and update student assignments
require_once '../config/database.php';

try {
    $db = getConnection();
    
    echo "Adding sample training centers...\n";
    
    // Add sample training centers
    $centers = [
        [
            'center_name' => 'Tech Skills Institute',
            'email' => 'admin@techskills.com',
            'phone' => '9876543210',
            'address' => '123 Tech Street, IT Park, Mumbai',
            'contact_person' => 'Rajesh Kumar',
            'city' => 'Mumbai',
            'state' => 'Maharashtra'
        ],
        [
            'center_name' => 'Digital Learning Hub',
            'email' => 'info@digitalhub.com',
            'phone' => '9876543211',
            'address' => '456 Innovation Road, Bangalore',
            'contact_person' => 'Priya Sharma',
            'city' => 'Bangalore',
            'state' => 'Karnataka'
        ],
        [
            'center_name' => 'Healthcare Training Academy',
            'email' => 'contact@healthacademy.com',
            'phone' => '9876543212',
            'address' => '789 Medical Centre, Delhi',
            'contact_person' => 'Dr. Amit Singh',
            'city' => 'Delhi',
            'state' => 'Delhi'
        ]
    ];
    
    foreach ($centers as $center) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO training_centers 
            (center_name, email, phone, address, contact_person, status, city, state, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW())
        ");
        $stmt->execute([
            $center['center_name'],
            $center['email'],
            $center['phone'],
            $center['address'],
            $center['contact_person'],
            $center['city'],
            $center['state']
        ]);
    }
    
    echo "✓ Training centers added\n";
    
    // Update students to assign them to training centers
    echo "Assigning students to training centers...\n";
    
    $assignments = [
        [1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3], [7, 1], [8, 2]
    ];
    
    foreach ($assignments as $assignment) {
        $stmt = $db->prepare("UPDATE students SET training_center_id = ? WHERE id = ?");
        $stmt->execute([$assignment[1], $assignment[0]]);
    }
    
    echo "✓ Students assigned to training centers\n";
    
    // Show summary
    echo "\n📊 Training Centers Summary:\n";
    $stmt = $db->query("SELECT COUNT(*) as count FROM training_centers");
    echo "- Total Training Centers: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $db->query("
        SELECT tc.center_name, COUNT(s.id) as student_count 
        FROM training_centers tc 
        LEFT JOIN students s ON tc.id = s.training_center_id 
        GROUP BY tc.id, tc.center_name
    ");
    echo "- Students per center:\n";
    while ($row = $stmt->fetch()) {
        echo "  * {$row['center_name']}: {$row['student_count']} students\n";
    }
    
    echo "\n✅ Training centers setup complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

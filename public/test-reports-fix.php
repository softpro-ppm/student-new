<?php
// Test reports.php fix
require_once '../config/database.php';

try {
    $db = getConnection();
    echo "<h1>Testing Reports Fix</h1>\n";
    
    // Test the problematic query from generateStudentsReport
    $query = "
        SELECT s.*, c.name as course_name, b.batch_name as batch_name, 
               tc.center_name as training_center_name, sec.name as sector_name,
               COALESCE(fee_summary.total_fees, 0) as total_fees,
               COALESCE(fee_summary.paid_fees, 0) as paid_fees,
               COALESCE(result_summary.total_assessments, 0) as total_assessments,
               COALESCE(result_summary.passed_assessments, 0) as passed_assessments
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        LEFT JOIN sectors sec ON c.sector_id = sec.id
        LEFT JOIN (
            SELECT student_id, 
                   SUM(amount) as total_fees,
                   SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_fees
            FROM fees 
            GROUP BY student_id
        ) fee_summary ON s.id = fee_summary.student_id
        LEFT JOIN (
            SELECT student_id,
                   COUNT(*) as total_assessments,
                   SUM(CASE WHEN result_status = 'pass' THEN 1 ELSE 0 END) as passed_assessments
            FROM results
            GROUP BY student_id
        ) result_summary ON s.id = result_summary.student_id
        WHERE 1=1
        ORDER BY s.created_at DESC
        LIMIT 5
    ";
    
    echo "<p>Testing query...</p>\n";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>✓ Query executed successfully</p>\n";
    echo "<p>Found " . count($results) . " students</p>\n";
    
    if (count($results) > 0) {
        echo "<h3>Sample Data:</h3>\n";
        foreach ($results as $row) {
            echo "<p>Student: {$row['name']}, Course: {$row['course_name']}, Fees: {$row['total_fees']}, Assessments: {$row['total_assessments']}</p>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Error details: " . $e->getFile() . ":" . $e->getLine() . "</p>\n";
}
?>

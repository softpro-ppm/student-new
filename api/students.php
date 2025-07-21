<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = getConnection();

$currentUser = $auth->getCurrentUser();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_student':
            $studentId = intval($_GET['id']);
            
            $query = "
                SELECT s.*, c.name as course_name, tc.name as center_name 
                FROM students s 
                LEFT JOIN courses c ON s.course_id = c.id 
                LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
                WHERE s.id = ?
            ";
            
            // Add role-based restrictions
            if ($currentUser['role'] === 'training_partner') {
                $query .= " AND tc.user_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$studentId, $currentUser['id']]);
            } else {
                $stmt = $db->prepare($query);
                $stmt->execute([$studentId]);
            }
            
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                echo json_encode(['success' => true, 'student' => $student]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Student not found']);
            }
            break;
            
        case 'search_students':
            $search = $_GET['search'] ?? '';
            $limit = intval($_GET['limit'] ?? 10);
            
            $query = "
                SELECT s.id, s.name, s.enrollment_number, s.phone, c.name as course_name 
                FROM students s 
                LEFT JOIN courses c ON s.course_id = c.id 
                WHERE s.status = 'active' AND (s.name LIKE ? OR s.enrollment_number LIKE ? OR s.phone LIKE ?)
            ";
            
            // Add role-based restrictions
            if ($currentUser['role'] === 'training_partner') {
                $query .= " AND s.training_center_id IN (SELECT id FROM training_centers WHERE user_id = ?)";
                $stmt = $db->prepare($query . " LIMIT ?");
                $searchTerm = '%' . $search . '%';
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $currentUser['id'], $limit]);
            } else {
                $stmt = $db->prepare($query . " LIMIT ?");
                $searchTerm = '%' . $search . '%';
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
            }
            
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'students' => $students]);
            break;
            
        case 'get_student_fees':
            $studentId = intval($_GET['student_id']);
            
            $query = "
                SELECT fp.*, s.name as student_name 
                FROM fee_payments fp 
                JOIN students s ON fp.student_id = s.id 
                WHERE fp.student_id = ?
            ";
            
            // Add role-based restrictions
            if ($currentUser['role'] === 'training_partner') {
                $query .= " AND s.training_center_id IN (SELECT id FROM training_centers WHERE user_id = ?)";
                $stmt = $db->prepare($query . " ORDER BY fp.payment_date DESC");
                $stmt->execute([$studentId, $currentUser['id']]);
            } else {
                $stmt = $db->prepare($query . " ORDER BY fp.payment_date DESC");
                $stmt->execute([$studentId]);
            }
            
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'payments' => $payments]);
            break;
            
        case 'generate_enrollment_number':
            $courseId = intval($_GET['course_id'] ?? 0);
            
            $year = date('Y');
            $month = date('m');
            $prefix = 'ST' . $year . $month;
            
            if ($courseId) {
                $stmt = $db->prepare("SELECT code FROM courses WHERE id = ?");
                $stmt->execute([$courseId]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($course) {
                    $prefix .= strtoupper(substr($course['code'], 0, 3));
                }
            }
            
            // Get next sequence number
            $stmt = $db->prepare("SELECT COUNT(*) + 1 as next_seq FROM students WHERE enrollment_number LIKE ?");
            $stmt->execute([$prefix . '%']);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC)['next_seq'];
            
            $enrollmentNumber = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
            echo json_encode(['success' => true, 'enrollment_number' => $enrollmentNumber]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

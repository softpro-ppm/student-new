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
        case 'get_batch':
            $batchId = intval($_GET['id']);
            
            $query = "
                SELECT b.*, c.name as course_name, tc.name as center_name 
                FROM batches b 
                LEFT JOIN courses c ON b.course_id = c.id 
                LEFT JOIN training_centers tc ON b.training_center_id = tc.id 
                WHERE b.id = ?
            ";
            
            // Add role-based restrictions
            if ($currentUser['role'] === 'training_partner') {
                $query .= " AND tc.user_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$batchId, $currentUser['id']]);
            } else {
                $stmt = $db->prepare($query);
                $stmt->execute([$batchId]);
            }
            
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($batch) {
                echo json_encode(['success' => true, 'batch' => $batch]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Batch not found']);
            }
            break;
            
        case 'get_batch_students':
            $batchId = intval($_GET['id']);
            
            $query = "
                SELECT s.*, sb.id as student_batch_id, sb.enrollment_date, sb.status as batch_status
                FROM student_batches sb 
                JOIN students s ON sb.student_id = s.id 
                WHERE sb.batch_id = ? 
                ORDER BY sb.enrollment_date DESC
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$batchId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get batch details
            $batchQuery = "
                SELECT b.*, c.name as course_name 
                FROM batches b 
                LEFT JOIN courses c ON b.course_id = c.id 
                WHERE b.id = ?
            ";
            $stmt = $db->prepare($batchQuery);
            $stmt->execute([$batchId]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'students' => $students, 'batch' => $batch]);
            break;
            
        case 'get_available_students':
            $batchId = intval($_GET['batch_id']);
            
            // Get students not already in this batch
            $query = "
                SELECT s.id, s.name, s.enrollment_number, s.phone, c.name as course_name 
                FROM students s 
                LEFT JOIN courses c ON s.course_id = c.id 
                WHERE s.status = 'active' 
                AND s.id NOT IN (
                    SELECT student_id FROM student_batches 
                    WHERE batch_id = ? AND status = 'active'
                )
            ";
            
            // Add role-based restrictions
            if ($currentUser['role'] === 'training_partner') {
                $query .= " AND s.training_center_id IN (SELECT id FROM training_centers WHERE user_id = ?)";
                $stmt = $db->prepare($query . " ORDER BY s.name");
                $stmt->execute([$batchId, $currentUser['id']]);
            } else {
                $stmt = $db->prepare($query . " ORDER BY s.name");
                $stmt->execute([$batchId]);
            }
            
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'students' => $students]);
            break;
            
        case 'get_batch_by_course':
            $courseId = intval($_GET['course_id']);
            
            $query = "
                SELECT b.id, b.name, b.start_date, b.end_date, b.status,
                       COUNT(sb.id) as student_count, b.max_students
                FROM batches b 
                LEFT JOIN student_batches sb ON b.id = sb.batch_id AND sb.status = 'active'
                WHERE b.course_id = ? AND b.status IN ('upcoming', 'ongoing')
            ";
            
            // Add role-based restrictions
            if ($currentUser['role'] === 'training_partner') {
                $query .= " AND b.training_center_id IN (SELECT id FROM training_centers WHERE user_id = ?)";
                $stmt = $db->prepare($query . " GROUP BY b.id ORDER BY b.start_date");
                $stmt->execute([$courseId, $currentUser['id']]);
            } else {
                $stmt = $db->prepare($query . " GROUP BY b.id ORDER BY b.start_date");
                $stmt->execute([$courseId]);
            }
            
            $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'batches' => $batches]);
            break;
            
        case 'update_batch_status':
            if ($currentUser['role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $batchId = intval($_POST['batch_id']);
            $status = $_POST['status'];
            
            $validStatuses = ['upcoming', 'ongoing', 'completed'];
            if (!in_array($status, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                break;
            }
            
            $stmt = $db->prepare("UPDATE batches SET status = ? WHERE id = ?");
            $stmt->execute([$status, $batchId]);
            
            echo json_encode(['success' => true, 'message' => 'Batch status updated successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

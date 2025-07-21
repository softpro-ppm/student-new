<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user = $_SESSION['user'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_fee':
            if ($user['role'] !== 'admin') {
                throw new Exception('Only administrators can approve fees');
            }
            
            $feeId = $input['fee_id'] ?? $_POST['fee_id'] ?? 0;
            
            if (!$feeId) {
                throw new Exception('Fee ID is required');
            }
            
            // Update fee status
            $stmt = $db->prepare("
                UPDATE fees 
                SET status = 'approved', 
                    approved_by = ?, 
                    approved_date = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$user['id'], $feeId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Fee not found or already processed');
            }
            
            echo json_encode(['success' => true, 'message' => 'Fee approved successfully']);
            break;
            
        case 'reject_fee':
            if ($user['role'] !== 'admin') {
                throw new Exception('Only administrators can reject fees');
            }
            
            $feeId = $input['fee_id'] ?? $_POST['fee_id'] ?? 0;
            $reason = $input['reason'] ?? $_POST['reason'] ?? '';
            
            if (!$feeId) {
                throw new Exception('Fee ID is required');
            }
            
            // Update fee status
            $stmt = $db->prepare("
                UPDATE fees 
                SET status = 'rejected', 
                    notes = ?, 
                    approved_by = ?, 
                    approved_date = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$reason, $user['id'], $feeId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Fee not found or already processed');
            }
            
            echo json_encode(['success' => true, 'message' => 'Fee rejected successfully']);
            break;
            
        case 'get_fees':
            $whereClause = '';
            $params = [];
            
            if ($user['role'] === 'training_partner') {
                // Get fees for students in this training center
                $stmt = $db->prepare("SELECT training_center_id FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $trainingCenterId = $result['training_center_id'] ?? 0;
                
                if ($trainingCenterId) {
                    $whereClause = 'WHERE s.training_center_id = ?';
                    $params[] = $trainingCenterId;
                }
            } elseif ($user['role'] === 'student') {
                // Get fees for this student only
                $stmt = $db->prepare("SELECT id FROM students WHERE email = ? OR phone = ?");
                $stmt->execute([$user['email'], $user['username']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $studentId = $result['id'] ?? 0;
                
                if ($studentId) {
                    $whereClause = 'WHERE f.student_id = ?';
                    $params[] = $studentId;
                }
            }
            
            $stmt = $db->prepare("
                SELECT f.*, s.name as student_name, s.enrollment_number, 
                       c.name as course_name, tc.name as training_center_name
                FROM fees f
                JOIN students s ON f.student_id = s.id
                LEFT JOIN courses c ON s.course_id = c.id
                LEFT JOIN training_centers tc ON s.training_center_id = tc.id
                $whereClause
                ORDER BY f.created_at DESC
            ");
            $stmt->execute($params);
            $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $fees]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

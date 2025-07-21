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
        case 'get_payment':
            $paymentId = intval($_GET['id']);
            
            $query = "
                SELECT fp.*, s.name as student_name, s.enrollment_number, s.phone, 
                       c.name as course_name, tc.name as center_name,
                       u.name as approved_by_name
                FROM fee_payments fp 
                JOIN students s ON fp.student_id = s.id 
                LEFT JOIN courses c ON s.course_id = c.id 
                LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
                LEFT JOIN users u ON fp.approved_by = u.id 
                WHERE fp.id = ?
            ";
            
            // Add role-based restrictions
            if ($currentUser['role'] === 'training_partner') {
                $query .= " AND tc.user_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$paymentId, $currentUser['id']]);
            } else {
                $stmt = $db->prepare($query);
                $stmt->execute([$paymentId]);
            }
            
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                echo json_encode(['success' => true, 'payment' => $payment]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Payment not found']);
            }
            break;
            
        case 'get_pending_approvals':
            if ($currentUser['role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $query = "
                SELECT fp.*, s.name as student_name, s.enrollment_number, c.name as course_name 
                FROM fee_payments fp 
                JOIN students s ON fp.student_id = s.id 
                LEFT JOIN courses c ON s.course_id = c.id 
                WHERE fp.status = 'pending' 
                ORDER BY fp.created_at ASC 
                LIMIT 10
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'payments' => $payments]);
            break;
            
        case 'bulk_approve':
            if ($currentUser['role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $paymentIds = $_POST['payment_ids'] ?? [];
            
            if (empty($paymentIds)) {
                echo json_encode(['success' => false, 'message' => 'No payments selected']);
                break;
            }
            
            $placeholders = str_repeat('?,', count($paymentIds) - 1) . '?';
            $query = "UPDATE fee_payments SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id IN ($placeholders) AND status = 'pending'";
            
            $params = [$currentUser['id']];
            $params = array_merge($params, $paymentIds);
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            $approvedCount = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "$approvedCount payment(s) approved successfully"]);
            break;
            
        case 'get_fee_summary':
            $studentId = intval($_GET['student_id']);
            
            $query = "
                SELECT 
                    COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) as paid_amount,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as payment_count
                FROM fee_payments 
                WHERE student_id = ?
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$studentId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'summary' => $summary]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

$currentUser = $auth->getCurrentUser();
$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'regenerate_certificate':
                $resultId = intval($_POST['result_id']);
                
                // Get result details
                $stmt = $db->prepare("
                    SELECT r.*, s.name as student_name, s.enrollment_number, 
                           c.name as course_name, c.duration_months,
                           a.name as assessment_name, a.total_marks, a.passing_marks
                    FROM results r
                    JOIN students s ON r.student_id = s.id
                    JOIN courses c ON s.course_id = c.id
                    JOIN assessments a ON r.assessment_id = a.id
                    WHERE r.id = ? AND r.status = 'pass'
                ");
                $stmt->execute([$resultId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Generate certificate
                    $certificateData = generateCertificate($result, $db);
                    $successMessage = 'Certificate regenerated successfully!';
                } else {
                    throw new Exception("Result not found or student did not pass");
                }
                break;
                
            case 'update_certificate_template':
                if (!in_array($currentUser['role'], ['admin'])) {
                    throw new Exception("Access denied");
                }
                
                $templateType = $_POST['template_type'];
                $uploadDir = '../uploads/certificates/';
                
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    $fileType = $_FILES['template_file']['type'];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception("Only JPG, PNG, and PDF files are allowed");
                    }
                    
                    $fileName = 'certificate_template_' . $templateType . '.' . pathinfo($_FILES['template_file']['name'], PATHINFO_EXTENSION);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['template_file']['tmp_name'], $filePath)) {
                        // Update settings
                        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                        $stmt->execute([$filePath, 'certificate_template_' . $templateType]);
                        
                        $successMessage = 'Certificate template updated successfully!';
                    } else {
                        throw new Exception("Failed to upload template file");
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get filter parameters
$courseFilter = $_GET['course'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build WHERE clause
$whereConditions = ["1=1"];
$params = [];

if ($currentUser['role'] === 'training_partner') {
    $whereConditions[] = "s.training_center_id = ?";
    $params[] = $currentUser['training_center_id'];
} elseif ($currentUser['role'] === 'student') {
    $whereConditions[] = "s.id = ?";
    $params[] = $currentUser['student_id'];
}

if ($courseFilter) {
    $whereConditions[] = "c.id = ?";
    $params[] = $courseFilter;
}

if ($statusFilter) {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(r.completed_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(r.completed_at) <= ?";
    $params[] = $dateTo;
}

if ($searchQuery) {
    $whereConditions[] = "(s.name LIKE ? OR s.enrollment_number LIKE ? OR a.name LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $whereConditions);

// Get results with pagination
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM results r
    JOIN students s ON r.student_id = s.id
    JOIN courses c ON s.course_id = c.id
    JOIN assessments a ON r.assessment_id = a.id
    LEFT JOIN certificates cert ON r.id = cert.result_id
    WHERE $whereClause
");
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$stmt = $db->prepare("
    SELECT r.*, s.name as student_name, s.enrollment_number, 
           c.name as course_name, c.duration_months,
           a.name as assessment_name, a.total_marks, a.passing_marks,
           cert.id as certificate_id, cert.certificate_number, cert.certificate_path,
           cert.status as certificate_status, cert.issued_date
    FROM results r
    JOIN students s ON r.student_id = s.id
    JOIN courses c ON s.course_id = c.id
    JOIN assessments a ON r.assessment_id = a.id
    LEFT JOIN certificates cert ON r.id = cert.result_id
    WHERE $whereClause
    ORDER BY r.completed_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses for filter
if (in_array($currentUser['role'], ['admin', 'training_partner'])) {
    $courseQuery = "SELECT id, name FROM courses WHERE status = 'active' ORDER BY name";
    if ($currentUser['role'] === 'training_partner') {
        $courseQuery = "
            SELECT DISTINCT c.id, c.name 
            FROM courses c 
            JOIN students s ON c.id = s.course_id 
            WHERE c.status = 'active' AND s.training_center_id = ? 
            ORDER BY c.name
        ";
        $stmt = $db->prepare($courseQuery);
        $stmt->execute([$currentUser['training_center_id']]);
    } else {
        $stmt = $db->prepare($courseQuery);
        $stmt->execute();
    }
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $courses = [];
}

// Get certificate settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'certificate_%'");
$stmt->execute();
$certificateSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

include '../includes/layout.php';

// Certificate generation function
function generateCertificate($resultData, $db) {
    try {
        // Generate unique certificate number
        $certificateNumber = 'CERT-' . date('Y') . '-' . str_pad($resultData['student_id'], 6, '0', STR_PAD_LEFT) . '-' . time();
        
        // Calculate grade
        $percentage = ($resultData['marks_obtained'] / $resultData['total_marks']) * 100;
        $grade = 'A+';
        if ($percentage < 95) $grade = 'A';
        if ($percentage < 85) $grade = 'B+';
        if ($percentage < 75) $grade = 'B';
        if ($percentage < 65) $grade = 'C';
        
        // Create certificate directory
        $certDir = '../uploads/certificates/generated/';
        if (!file_exists($certDir)) {
            mkdir($certDir, 0755, true);
        }
        
        // Generate QR code data
        $qrData = json_encode([
            'certificate_number' => $certificateNumber,
            'student_name' => $resultData['student_name'],
            'course_name' => $resultData['course_name'],
            'enrollment_number' => $resultData['enrollment_number'],
            'grade' => $grade,
            'percentage' => number_format($percentage, 2),
            'issued_date' => date('Y-m-d')
        ]);
        
        // Generate QR code using online service (for production, use local library)
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qrData);
        $qrCodePath = $certDir . 'qr_' . $certificateNumber . '.png';
        
        // Download QR code
        $qrContent = file_get_contents($qrCodeUrl);
        if ($qrContent) {
            file_put_contents($qrCodePath, $qrContent);
        }
        
        // Generate certificate HTML (simplified version)
        $certificateHtml = generateCertificateHtml($resultData, $certificateNumber, $grade, $percentage, $qrCodePath);
        
        // Save certificate as HTML file
        $certificatePath = $certDir . 'certificate_' . $certificateNumber . '.html';
        file_put_contents($certificatePath, $certificateHtml);
        
        // Save to database
        $stmt = $db->prepare("
            INSERT INTO certificates (student_id, result_id, certificate_number, issued_date, certificate_path, qr_code_path, status)
            VALUES (?, ?, ?, ?, ?, ?, 'generated')
            ON DUPLICATE KEY UPDATE
            certificate_number = VALUES(certificate_number),
            issued_date = VALUES(issued_date),
            certificate_path = VALUES(certificate_path),
            qr_code_path = VALUES(qr_code_path)
        ");
        $stmt->execute([
            $resultData['student_id'],
            $resultData['id'],
            $certificateNumber,
            date('Y-m-d'),
            $certificatePath,
            $qrCodePath
        ]);
        
        return [
            'certificate_number' => $certificateNumber,
            'certificate_path' => $certificatePath,
            'qr_code_path' => $qrCodePath
        ];
        
    } catch (Exception $e) {
        throw new Exception("Failed to generate certificate: " . $e->getMessage());
    }
}

function generateCertificateHtml($data, $certificateNumber, $grade, $percentage, $qrCodePath) {
    $qrCodeBase64 = '';
    if (file_exists($qrCodePath)) {
        $qrCodeBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($qrCodePath));
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Certificate - ' . htmlspecialchars($data['student_name']) . '</title>
        <style>
            body { font-family: "Times New Roman", serif; margin: 0; padding: 40px; background: #f5f5f5; }
            .certificate { 
                background: white; 
                border: 10px solid #1e3a8a; 
                padding: 60px; 
                text-align: center; 
                max-width: 800px; 
                margin: 0 auto;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header { margin-bottom: 40px; }
            .title { font-size: 48px; color: #1e3a8a; font-weight: bold; margin-bottom: 20px; }
            .subtitle { font-size: 24px; color: #666; margin-bottom: 40px; }
            .student-name { font-size: 36px; color: #1e3a8a; font-weight: bold; margin: 30px 0; border-bottom: 3px solid #1e3a8a; display: inline-block; padding-bottom: 10px; }
            .course-info { font-size: 20px; margin: 20px 0; }
            .grade-info { font-size: 18px; margin: 30px 0; background: #f8f9fa; padding: 20px; border-radius: 10px; }
            .footer { margin-top: 50px; display: flex; justify-content: space-between; align-items: center; }
            .qr-code { width: 120px; height: 120px; }
            .cert-number { font-size: 14px; color: #666; }
            .date { font-size: 16px; color: #333; }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">
                <div class="title">CERTIFICATE OF COMPLETION</div>
                <div class="subtitle">This is to certify that</div>
            </div>
            
            <div class="student-name">' . htmlspecialchars($data['student_name']) . '</div>
            
            <div class="course-info">
                has successfully completed the course<br>
                <strong>' . htmlspecialchars($data['course_name']) . '</strong><br>
                Duration: ' . $data['duration_months'] . ' months
            </div>
            
            <div class="grade-info">
                <strong>Assessment: ' . htmlspecialchars($data['assessment_name']) . '</strong><br>
                Marks Obtained: ' . $data['marks_obtained'] . ' / ' . $data['total_marks'] . '<br>
                Percentage: ' . number_format($percentage, 2) . '%<br>
                Grade: ' . $grade . '
            </div>
            
            <div class="footer">
                <div>
                    <div class="cert-number">Certificate No: ' . $certificateNumber . '</div>
                    <div class="cert-number">Enrollment No: ' . htmlspecialchars($data['enrollment_number']) . '</div>
                </div>
                <div class="date">
                    Issued on: ' . date('F d, Y') . '
                </div>
                ' . ($qrCodeBase64 ? '<img src="' . $qrCodeBase64 . '" class="qr-code" alt="QR Code">' : '') . '
            </div>
        </div>
    </body>
    </html>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results & Certificates - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .result-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .result-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .grade-badge {
            font-size: 1.2rem;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 25px;
        }
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .certificate-preview {
            max-width: 200px;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            background: white;
        }
    </style>
</head>
<body>
    <?php renderHeader(); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php renderSidebar('results'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-trophy me-2 text-warning"></i>Results & Certificates
                    </h1>
                    <?php if (in_array($currentUser['role'], ['admin'])): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#certificateTemplateModal">
                            <i class="fas fa-upload me-2"></i>Manage Templates
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Student name, enrollment, assessment...">
                            </div>
                            <?php if (!empty($courses)): ?>
                                <div class="col-md-2">
                                    <label class="form-label">Course</label>
                                    <select class="form-select" name="course">
                                        <option value="">All Courses</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= $course['id'] ?>" <?= $courseFilter == $course['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($course['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="pass" <?= $statusFilter === 'pass' ? 'selected' : '' ?>>Pass</option>
                                    <option value="fail" <?= $statusFilter === 'fail' ? 'selected' : '' ?>>Fail</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-light me-2">
                                    <i class="fas fa-search"></i>
                                </button>
                                <a href="results.php" class="btn btn-outline-light">
                                    <i class="fas fa-undo"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <?php
                    $summaryStmt = $db->prepare("
                        SELECT 
                            COUNT(*) as total_results,
                            SUM(CASE WHEN r.status = 'pass' THEN 1 ELSE 0 END) as pass_count,
                            SUM(CASE WHEN r.status = 'fail' THEN 1 ELSE 0 END) as fail_count,
                            COUNT(cert.id) as certificates_issued
                        FROM results r
                        JOIN students s ON r.student_id = s.id
                        LEFT JOIN certificates cert ON r.id = cert.result_id
                        WHERE $whereClause
                    ");
                    $summaryStmt->execute($params);
                    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $passRate = $summary['total_results'] > 0 ? ($summary['pass_count'] / $summary['total_results']) * 100 : 0;
                    ?>
                    <div class="col-md-3">
                        <div class="card result-card text-center">
                            <div class="card-body">
                                <i class="fas fa-clipboard-list fa-2x text-primary mb-2"></i>
                                <h4><?= $summary['total_results'] ?></h4>
                                <p class="mb-0">Total Results</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card result-card text-center">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h4><?= $summary['pass_count'] ?></h4>
                                <p class="mb-0">Students Passed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card result-card text-center">
                            <div class="card-body">
                                <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                                <h4><?= number_format($passRate, 1) ?>%</h4>
                                <p class="mb-0">Pass Rate</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card result-card text-center">
                            <div class="card-body">
                                <i class="fas fa-certificate fa-2x text-warning mb-2"></i>
                                <h4><?= $summary['certificates_issued'] ?></h4>
                                <p class="mb-0">Certificates Issued</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="card result-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Assessment Results</h5>
                        <small class="text-muted">Showing <?= count($results) ?> of <?= $totalRecords ?> results</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Assessment</th>
                                        <th>Marks</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Certificate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <?php
                                        $percentage = ($result['marks_obtained'] / $result['total_marks']) * 100;
                                        $grade = 'A+';
                                        if ($percentage < 95) $grade = 'A';
                                        if ($percentage < 85) $grade = 'B+';
                                        if ($percentage < 75) $grade = 'B';
                                        if ($percentage < 65) $grade = 'C';
                                        if ($percentage < $result['passing_marks']) $grade = 'F';
                                        ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($result['student_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($result['enrollment_number']) ?></small>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($result['course_name']) ?></td>
                                            <td><?= htmlspecialchars($result['assessment_name']) ?></td>
                                            <td>
                                                <strong><?= $result['marks_obtained'] ?>/<?= $result['total_marks'] ?></strong><br>
                                                <small class="text-muted"><?= number_format($percentage, 1) ?>%</small>
                                            </td>
                                            <td>
                                                <span class="grade-badge bg-<?= $result['status'] === 'pass' ? 'success' : 'danger' ?>">
                                                    <?= $grade ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge bg-<?= $result['status'] === 'pass' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst($result['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($result['completed_at'])) ?></td>
                                            <td>
                                                <?php if ($result['status'] === 'pass'): ?>
                                                    <?php if ($result['certificate_id']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-certificate me-1"></i>Generated
                                                        </span><br>
                                                        <small class="text-muted"><?= $result['certificate_number'] ?></small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-clock me-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($result['status'] === 'pass'): ?>
                                                        <?php if ($result['certificate_id']): ?>
                                                            <a href="<?= str_replace('../', '', $result['certificate_path']) ?>" 
                                                               class="btn btn-sm btn-outline-primary" target="_blank" title="View Certificate">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="<?= str_replace('../', '', $result['certificate_path']) ?>" 
                                                               class="btn btn-sm btn-outline-success" download title="Download Certificate">
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                            <?php if (in_array($currentUser['role'], ['admin', 'training_partner'])): ?>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="regenerate_certificate">
                                                                    <input type="hidden" name="result_id" value="<?= $result['id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-warning" 
                                                                            title="Regenerate Certificate" onclick="return confirm('Regenerate certificate?')">
                                                                        <i class="fas fa-redo"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="regenerate_certificate">
                                                                <input type="hidden" name="result_id" value="<?= $result['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-primary" title="Generate Certificate">
                                                                    <i class="fas fa-certificate me-1"></i>Generate
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Results pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query($_GET) ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($_GET) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query($_GET) ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Certificate Template Modal -->
    <?php if (in_array($currentUser['role'], ['admin'])): ?>
    <div class="modal fade" id="certificateTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Certificate Templates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Upload Background Template</h6>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_certificate_template">
                                <input type="hidden" name="template_type" value="background">
                                <div class="mb-3">
                                    <label class="form-label">Background Image/PDF</label>
                                    <input type="file" class="form-control" name="template_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <div class="form-text">Supported formats: JPG, PNG, PDF (Max 5MB)</div>
                                </div>
                                <button type="submit" class="btn btn-primary">Upload Background</button>
                            </form>
                            <?php if (isset($certificateSettings['certificate_template_background'])): ?>
                                <div class="mt-3">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle"></i> Current: <?= basename($certificateSettings['certificate_template_background']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Certificate Preview</h6>
                            <div class="certificate-preview text-center p-3">
                                <i class="fas fa-certificate fa-3x text-warning mb-2"></i>
                                <p class="small mb-0">Certificate preview will be generated based on your template</p>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Template Settings</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Default Passing Marks (%)</label>
                            <input type="number" class="form-control" value="<?= $certificateSettings['assessment_passing_marks'] ?? '70' ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Certificate Directory</label>
                            <input type="text" class="form-control" value="../uploads/certificates/" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form on filter change
        document.querySelectorAll('select[name="course"], select[name="status"]').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Auto-generate certificate for passed students without certificates
        <?php if (in_array($currentUser['role'], ['admin', 'training_partner'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Check for passed students without certificates and auto-generate
            const passedWithoutCert = <?= json_encode(array_filter($results, function($r) { 
                return $r['status'] === 'pass' && !$r['certificate_id']; 
            })) ?>;
            
            if (passedWithoutCert.length > 0) {
                console.log('Found ' + passedWithoutCert.length + ' students with passed results but no certificates');
                // Auto-generate certificates could be implemented here
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

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

// Get filter parameters
$courseFilter = $_GET['course'] ?? '';
$batchFilter = $_GET['batch'] ?? '';
$trainingCenterFilter = $_GET['training_center'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$feeStatusFilter = $_GET['fee_status'] ?? '';
$resultStatusFilter = $_GET['result_status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$reportType = $_GET['report_type'] ?? 'students';
$exportFormat = $_GET['export'] ?? '';

// Handle export functionality
if ($exportFormat && in_array($exportFormat, ['excel', 'pdf'])) {
    handleExport($exportFormat, $reportType, $_GET, $db, $currentUser);
    exit;
}

// Get dropdown data for filters
$courses = [];
$batches = [];
$trainingCenters = [];

if (in_array($currentUser['role'], ['admin', 'training_partner'])) {
    // Get courses
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
    
    // Get batches
    $batchQuery = "SELECT id, name FROM batches ORDER BY name";
    if ($currentUser['role'] === 'training_partner') {
        $batchQuery = "
            SELECT DISTINCT b.id, b.name 
            FROM batches b 
            JOIN students s ON b.id = s.batch_id 
            WHERE s.training_center_id = ? 
            ORDER BY b.name
        ";
        $stmt = $db->prepare($batchQuery);
        $stmt->execute([$currentUser['training_center_id']]);
    } else {
        $stmt = $db->prepare($batchQuery);
        $stmt->execute();
    }
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get training centers (admin only)
    if ($currentUser['role'] === 'admin') {
        $stmt = $db->prepare("SELECT id, name FROM training_centers WHERE status = 'active' ORDER BY name");
        $stmt->execute();
        $trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Generate report data based on type
$reportData = generateReportData($reportType, $_GET, $db, $currentUser);

include '../includes/layout.php';

function generateReportData($reportType, $filters, $db, $currentUser) {
    $whereConditions = ["1=1"];
    $params = [];
    
    // Role-based filtering
    if ($currentUser['role'] === 'training_partner') {
        $whereConditions[] = "s.training_center_id = ?";
        $params[] = $currentUser['training_center_id'];
    } elseif ($currentUser['role'] === 'student') {
        $whereConditions[] = "s.id = ?";
        $params[] = $currentUser['student_id'];
    }
    
    // Apply filters
    if (!empty($filters['course'])) {
        $whereConditions[] = "c.id = ?";
        $params[] = $filters['course'];
    }
    
    if (!empty($filters['batch'])) {
        $whereConditions[] = "b.id = ?";
        $params[] = $filters['batch'];
    }
    
    if (!empty($filters['training_center'])) {
        $whereConditions[] = "tc.id = ?";
        $params[] = $filters['training_center'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "DATE(s.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "DATE(s.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = "(s.name LIKE ? OR s.enrollment_number LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
        $searchParam = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    switch ($reportType) {
        case 'students':
            return generateStudentsReport($whereConditions, $params, $filters, $db);
        case 'fees':
            return generateFeesReport($whereConditions, $params, $filters, $db);
        case 'results':
            return generateResultsReport($whereConditions, $params, $filters, $db);
        case 'certificates':
            return generateCertificatesReport($whereConditions, $params, $filters, $db);
        case 'batches':
            return generateBatchesReport($whereConditions, $params, $filters, $db);
        default:
            return generateStudentsReport($whereConditions, $params, $filters, $db);
    }
}

function generateStudentsReport($whereConditions, $params, $filters, $db) {
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT s.*, c.name as course_name, b.name as batch_name, 
               tc.name as training_center_name, sec.name as sector_name,
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
                   SUM(CASE WHEN status = 'pass' THEN 1 ELSE 0 END) as passed_assessments
            FROM results
            GROUP BY student_id
        ) result_summary ON s.id = result_summary.student_id
        WHERE $whereClause
        ORDER BY s.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateFeesReport($whereConditions, $params, $filters, $db) {
    // Add fee status filter
    if (!empty($filters['fee_status'])) {
        $whereConditions[] = "f.status = ?";
        $params[] = $filters['fee_status'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT f.*, s.name as student_name, s.enrollment_number, 
               c.name as course_name, b.name as batch_name,
               tc.name as training_center_name
        FROM fees f
        JOIN students s ON f.student_id = s.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        WHERE $whereClause
        ORDER BY f.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateResultsReport($whereConditions, $params, $filters, $db) {
    // Add result status filter
    if (!empty($filters['result_status'])) {
        $whereConditions[] = "r.status = ?";
        $params[] = $filters['result_status'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT r.*, s.name as student_name, s.enrollment_number,
               a.name as assessment_name, c.name as course_name,
               b.name as batch_name, tc.name as training_center_name,
               cert.certificate_number, cert.status as certificate_status
        FROM results r
        JOIN students s ON r.student_id = s.id
        JOIN assessments a ON r.assessment_id = a.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        LEFT JOIN certificates cert ON r.id = cert.result_id
        WHERE $whereClause
        ORDER BY r.completed_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateCertificatesReport($whereConditions, $params, $filters, $db) {
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT cert.*, s.name as student_name, s.enrollment_number,
               c.name as course_name, b.name as batch_name,
               tc.name as training_center_name, r.marks_obtained, r.total_marks
        FROM certificates cert
        JOIN students s ON cert.student_id = s.id
        JOIN results r ON cert.result_id = r.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        WHERE $whereClause
        ORDER BY cert.issued_date DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateBatchesReport($whereConditions, $params, $filters, $db) {
    $whereClause = str_replace('s.', 'b.', implode(' AND ', $whereConditions));
    
    $query = "
        SELECT b.*, c.name as course_name, sec.name as sector_name,
               COUNT(s.id) as total_students,
               SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_students,
               AVG(CASE WHEN r.status = 'pass' THEN 1.0 ELSE 0.0 END) * 100 as pass_rate
        FROM batches b
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN sectors sec ON c.sector_id = sec.id
        LEFT JOIN students s ON b.id = s.batch_id
        LEFT JOIN results r ON s.id = r.student_id
        WHERE $whereClause
        GROUP BY b.id
        ORDER BY b.start_date DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function handleExport($format, $reportType, $filters, $db, $currentUser) {
    $reportData = generateReportData($reportType, $filters, $db, $currentUser);
    
    if ($format === 'excel') {
        exportToExcel($reportData, $reportType);
    } elseif ($format === 'pdf') {
        exportToPDF($reportData, $reportType);
    }
}

function exportToExcel($data, $reportType) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $reportType . '_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    
    if (!empty($data)) {
        // Headers
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        echo '<th>S.No</th>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
        }
        echo '</tr>';
        
        // Data rows
        $sno = 1;
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . $sno++ . '</td>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars($value ?? '') . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</table>';
}

function exportToPDF($data, $reportType) {
    // Simple HTML to PDF conversion
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . ucfirst($reportType) . ' Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .report-info { margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Student Management System</h2>
            <h3>' . ucfirst($reportType) . ' Report</h3>
            <p>Generated on: ' . date('d-m-Y H:i:s') . '</p>
        </div>
        
        <div class="report-info">
            <p><strong>Total Records:</strong> ' . count($data) . '</p>
        </div>
        
        <table>';
    
    if (!empty($data)) {
        // Headers
        $html .= '<tr>';
        $html .= '<th>S.No</th>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
        }
        $html .= '</tr>';
        
        // Data rows
        $sno = 1;
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . $sno++ . '</td>';
            foreach ($row as $value) {
                $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
    }
    
    $html .= '</table></body></html>';
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $reportType . '_report_' . date('Y-m-d') . '.pdf"');
    
    // For production, use a proper PDF library like TCPDF or wkhtmltopdf
    // For now, we'll output HTML and let browser handle PDF conversion
    echo $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        .report-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-5px);
        }
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .report-tabs .nav-link {
            border-radius: 25px;
            padding: 12px 25px;
            margin: 0 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .report-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            color: white;
            border: none;
        }
        .export-buttons .btn {
            margin: 0 5px;
            border-radius: 25px;
            padding: 10px 20px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 20px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 20px;
            margin: 0 2px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        #reportTable thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .summary-stats {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php renderHeader(); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php renderSidebar('reports'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-bar me-2 text-info"></i>Advanced Reports
                    </h1>
                    <div class="export-buttons">
                        <button class="btn btn-success" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </button>
                        <button class="btn btn-danger" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </button>
                    </div>
                </div>

                <!-- Report Type Tabs -->
                <ul class="nav nav-pills mb-4 report-tabs" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $reportType === 'students' ? 'active' : '' ?>" 
                                onclick="changeReportType('students')">
                            <i class="fas fa-users me-2"></i>Students
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $reportType === 'fees' ? 'active' : '' ?>" 
                                onclick="changeReportType('fees')">
                            <i class="fas fa-money-bill-wave me-2"></i>Fees
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $reportType === 'results' ? 'active' : '' ?>" 
                                onclick="changeReportType('results')">
                            <i class="fas fa-chart-line me-2"></i>Results
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $reportType === 'certificates' ? 'active' : '' ?>" 
                                onclick="changeReportType('certificates')">
                            <i class="fas fa-certificate me-2"></i>Certificates
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $reportType === 'batches' ? 'active' : '' ?>" 
                                onclick="changeReportType('batches')">
                            <i class="fas fa-users-class me-2"></i>Batches
                        </button>
                    </li>
                </ul>

                <!-- Filters -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-filter me-2"></i>Report Filters
                        </h5>
                        <form method="GET" id="filterForm">
                            <input type="hidden" name="report_type" value="<?= htmlspecialchars($reportType) ?>">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                                           placeholder="Name, enrollment, phone...">
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
                                
                                <?php if (!empty($batches)): ?>
                                <div class="col-md-2">
                                    <label class="form-label">Batch</label>
                                    <select class="form-select" name="batch">
                                        <option value="">All Batches</option>
                                        <?php foreach ($batches as $batch): ?>
                                            <option value="<?= $batch['id'] ?>" <?= $batchFilter == $batch['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($batch['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($trainingCenters)): ?>
                                <div class="col-md-2">
                                    <label class="form-label">Training Center</label>
                                    <select class="form-select" name="training_center">
                                        <option value="">All Centers</option>
                                        <?php foreach ($trainingCenters as $center): ?>
                                            <option value="<?= $center['id'] ?>" <?= $trainingCenterFilter == $center['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($center['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-2">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                                </div>
                                
                                <?php if (in_array($reportType, ['fees'])): ?>
                                <div class="col-md-2">
                                    <label class="form-label">Fee Status</label>
                                    <select class="form-select" name="fee_status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?= $feeStatusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="paid" <?= $feeStatusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="overdue" <?= $feeStatusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array($reportType, ['results'])): ?>
                                <div class="col-md-2">
                                    <label class="form-label">Result Status</label>
                                    <select class="form-select" name="result_status">
                                        <option value="">All Results</option>
                                        <option value="pass" <?= $resultStatusFilter === 'pass' ? 'selected' : '' ?>>Pass</option>
                                        <option value="fail" <?= $resultStatusFilter === 'fail' ? 'selected' : '' ?>>Fail</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-light me-2">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-light" onclick="clearFilters()">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="summary-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4 class="text-primary"><?= count($reportData) ?></h4>
                            <p class="mb-0">Total Records</p>
                        </div>
                        <?php if ($reportType === 'students'): ?>
                            <div class="col-md-3">
                                <h4 class="text-success">
                                    <?= count(array_filter($reportData, function($r) { return $r['status'] === 'active'; })) ?>
                                </h4>
                                <p class="mb-0">Active Students</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning">
                                    ₹<?= number_format(array_sum(array_column($reportData, 'total_fees')), 2) ?>
                                </h4>
                                <p class="mb-0">Total Fees</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-info">
                                    ₹<?= number_format(array_sum(array_column($reportData, 'paid_fees')), 2) ?>
                                </h4>
                                <p class="mb-0">Fees Collected</p>
                            </div>
                        <?php elseif ($reportType === 'fees'): ?>
                            <div class="col-md-3">
                                <h4 class="text-success">
                                    ₹<?= number_format(array_sum(array_column($reportData, 'amount')), 2) ?>
                                </h4>
                                <p class="mb-0">Total Amount</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-info">
                                    <?= count(array_filter($reportData, function($r) { return $r['status'] === 'paid'; })) ?>
                                </h4>
                                <p class="mb-0">Paid Entries</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning">
                                    <?= count(array_filter($reportData, function($r) { return $r['status'] === 'pending'; })) ?>
                                </h4>
                                <p class="mb-0">Pending</p>
                            </div>
                        <?php elseif ($reportType === 'results'): ?>
                            <div class="col-md-3">
                                <h4 class="text-success">
                                    <?= count(array_filter($reportData, function($r) { return $r['status'] === 'pass'; })) ?>
                                </h4>
                                <p class="mb-0">Pass Results</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-danger">
                                    <?= count(array_filter($reportData, function($r) { return $r['status'] === 'fail'; })) ?>
                                </h4>
                                <p class="mb-0">Fail Results</p>
                            </div>
                            <div class="col-md-3">
                                <?php 
                                $passCount = count(array_filter($reportData, function($r) { return $r['status'] === 'pass'; }));
                                $totalCount = count($reportData);
                                $passRate = $totalCount > 0 ? ($passCount / $totalCount) * 100 : 0;
                                ?>
                                <h4 class="text-info"><?= number_format($passRate, 1) ?>%</h4>
                                <p class="mb-0">Pass Rate</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card report-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= ucfirst($reportType) ?> Report</h5>
                        <div class="d-flex align-items-center">
                            <label class="me-2">Show:</label>
                            <select id="pageLength" class="form-select form-select-sm" style="width: auto;">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="500">500</option>
                                <option value="-1">All</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>S.No</th>
                                        <?php if ($reportType === 'students'): ?>
                                            <th>Enrollment No</th>
                                            <th>Name</th>
                                            <th>Phone</th>
                                            <th>Course</th>
                                            <th>Batch</th>
                                            <th>Training Center</th>
                                            <th>Total Fees</th>
                                            <th>Paid Fees</th>
                                            <th>Assessments</th>
                                            <th>Status</th>
                                            <th>Reg. Date</th>
                                        <?php elseif ($reportType === 'fees'): ?>
                                            <th>Student</th>
                                            <th>Enrollment No</th>
                                            <th>Course</th>
                                            <th>Fee Type</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Paid Date</th>
                                            <th>Status</th>
                                            <th>Training Center</th>
                                        <?php elseif ($reportType === 'results'): ?>
                                            <th>Student</th>
                                            <th>Enrollment No</th>
                                            <th>Assessment</th>
                                            <th>Course</th>
                                            <th>Marks</th>
                                            <th>Percentage</th>
                                            <th>Status</th>
                                            <th>Certificate</th>
                                            <th>Completed Date</th>
                                        <?php elseif ($reportType === 'certificates'): ?>
                                            <th>Certificate No</th>
                                            <th>Student</th>
                                            <th>Enrollment No</th>
                                            <th>Course</th>
                                            <th>Marks</th>
                                            <th>Issued Date</th>
                                            <th>Status</th>
                                            <th>Training Center</th>
                                        <?php elseif ($reportType === 'batches'): ?>
                                            <th>Batch Name</th>
                                            <th>Course</th>
                                            <th>Sector</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Total Students</th>
                                            <th>Active Students</th>
                                            <th>Pass Rate</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sno = 1; foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><?= $sno++ ?></td>
                                            <?php if ($reportType === 'students'): ?>
                                                <td><strong><?= htmlspecialchars($row['enrollment_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                                <td><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($row['batch_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($row['training_center_name'] ?? 'N/A') ?></td>
                                                <td>₹<?= number_format($row['total_fees'], 2) ?></td>
                                                <td>₹<?= number_format($row['paid_fees'], 2) ?></td>
                                                <td><?= $row['passed_assessments'] ?>/<?= $row['total_assessments'] ?></td>
                                                <td>
                                                    <span class="status-badge bg-<?= $row['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
                                            <?php elseif ($reportType === 'fees'): ?>
                                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                                <td><?= htmlspecialchars($row['enrollment_number']) ?></td>
                                                <td><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($row['fee_type']) ?></td>
                                                <td>₹<?= number_format($row['amount'], 2) ?></td>
                                                <td><?= $row['due_date'] ? date('d-m-Y', strtotime($row['due_date'])) : '-' ?></td>
                                                <td><?= $row['paid_date'] ? date('d-m-Y', strtotime($row['paid_date'])) : '-' ?></td>
                                                <td>
                                                    <span class="status-badge bg-<?= $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($row['training_center_name'] ?? 'N/A') ?></td>
                                            <?php elseif ($reportType === 'results'): ?>
                                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                                <td><?= htmlspecialchars($row['enrollment_number']) ?></td>
                                                <td><?= htmlspecialchars($row['assessment_name']) ?></td>
                                                <td><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td>
                                                <td><?= $row['marks_obtained'] ?>/<?= $row['total_marks'] ?></td>
                                                <td><?= number_format(($row['marks_obtained'] / $row['total_marks']) * 100, 2) ?>%</td>
                                                <td>
                                                    <span class="status-badge bg-<?= $row['status'] === 'pass' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($row['certificate_number']): ?>
                                                        <span class="badge bg-success">Generated</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d-m-Y', strtotime($row['completed_at'])) ?></td>
                                            <?php elseif ($reportType === 'certificates'): ?>
                                                <td><strong><?= htmlspecialchars($row['certificate_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                                <td><?= htmlspecialchars($row['enrollment_number']) ?></td>
                                                <td><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td>
                                                <td><?= $row['marks_obtained'] ?>/<?= $row['total_marks'] ?></td>
                                                <td><?= date('d-m-Y', strtotime($row['issued_date'])) ?></td>
                                                <td>
                                                    <span class="status-badge bg-success">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($row['training_center_name'] ?? 'N/A') ?></td>
                                            <?php elseif ($reportType === 'batches'): ?>
                                                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($row['sector_name'] ?? 'N/A') ?></td>
                                                <td><?= date('d-m-Y', strtotime($row['start_date'])) ?></td>
                                                <td><?= date('d-m-Y', strtotime($row['end_date'])) ?></td>
                                                <td><?= $row['total_students'] ?></td>
                                                <td><?= $row['active_students'] ?></td>
                                                <td><?= number_format($row['pass_rate'] ?? 0, 1) ?>%</td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            const table = $('#reportTable').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, "All"]],
                order: [[0, 'asc']],
                columnDefs: [
                    { targets: 0, orderable: false } // S.No column
                ],
                responsive: true,
                fixedHeader: true,
                searching: true,
                language: {
                    search: "Search in table:",
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
            
            // Custom page length selector
            $('#pageLength').on('change', function() {
                table.page.len($(this).val()).draw();
            });
            
            // Auto-submit form on filter change
            $('select[name="course"], select[name="batch"], select[name="training_center"], select[name="fee_status"], select[name="result_status"]').on('change', function() {
                $('#filterForm').submit();
            });
        });
        
        function changeReportType(type) {
            window.location.href = 'reports.php?report_type=' + type;
        }
        
        function exportReport(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            window.location.href = 'reports.php?' + params.toString();
        }
        
        function clearFilters() {
            window.location.href = 'reports.php?report_type=<?= $reportType ?>';
        }
    </script>
</body>
</html>

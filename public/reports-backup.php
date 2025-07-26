<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = getConnection();
$currentUser = getCurrentUser();
$userRole = getCurrentUserRole();
$pageTitle = 'Reports & Analytics';

// Handle export functionality
if (isset($_GET['export']) && in_array($_GET['export'], ['excel', 'pdf', 'csv'])) {
    handleExport($_GET['export'], $_GET, $db, $currentUser);
    exit;
}

// Get filter parameters
$reportType = $_GET['report_type'] ?? 'students';
$courseFilter = $_GET['course'] ?? '';
$batchFilter = $_GET['batch'] ?? '';
$trainingCenterFilter = $_GET['training_center'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Get dropdown data for filters
$courses = [];
$batches = [];
$trainingCenters = [];

try {
    // Get courses based on user role
    if (in_array($userRole, ['admin', 'training_partner'])) {
        $courseQuery = "SELECT id, name FROM courses WHERE status = 'active' ORDER BY name";
        if ($userRole === 'training_partner' && !empty($currentUser['training_center_id'])) {
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
        $batchQuery = "SELECT id, batch_name as name FROM batches ORDER BY batch_name";
        if ($userRole === 'training_partner' && !empty($currentUser['training_center_id'])) {
            $batchQuery = "
                SELECT DISTINCT b.id, b.batch_name as name 
                FROM batches b 
                WHERE b.training_center_id = ? 
                ORDER BY b.batch_name
            ";
            $stmt = $db->prepare($batchQuery);
            $stmt->execute([$currentUser['training_center_id']]);
        } else {
            $stmt = $db->prepare($batchQuery);
            $stmt->execute();
        }
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get training centers (admin only)
        if ($userRole === 'admin') {
            $stmt = $db->prepare("SELECT id, center_name as name FROM training_centers WHERE status = 'active' ORDER BY center_name");
            $stmt->execute();
            $trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Error loading dropdown data: " . $e->getMessage());
}

// Generate report data
$reportData = generateReportData($reportType, $_GET, $db, $currentUser);
$reportSummary = generateReportSummary($reportType, $_GET, $db, $currentUser);

include '../includes/layout.php';

function generateReportData($reportType, $filters, $db, $currentUser) {
    $whereConditions = ["1=1"];
    $params = [];
    
    // Role-based filtering
    if ($currentUser['role'] === 'training_partner' && !empty($currentUser['training_center_id'])) {
        $whereConditions[] = "s.training_center_id = ?";
        $params[] = $currentUser['training_center_id'];
    } elseif ($currentUser['role'] === 'student' && !empty($currentUser['student_id'])) {
        $whereConditions[] = "s.id = ?";
        $params[] = $currentUser['student_id'];
    }
    
    // Apply common filters
    if (!empty($filters['course'])) {
        $whereConditions[] = "s.course_id = ?";
        $params[] = $filters['course'];
    }
    
    if (!empty($filters['batch'])) {
        $whereConditions[] = "s.batch_id = ?";
        $params[] = $filters['batch'];
    }
    
    if (!empty($filters['training_center'])) {
        $whereConditions[] = "s.training_center_id = ?";
        $params[] = $filters['training_center'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "s.created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "s.created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
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
        case 'batches':
            return generateBatchesReport($whereConditions, $params, $filters, $db);
        case 'centers':
            return generateCentersReport($whereConditions, $params, $filters, $db);
        default:
            return generateStudentsReport($whereConditions, $params, $filters, $db);
    }
}

function generateStudentsReport($whereConditions, $params, $filters, $db) {
    if (!empty($filters['status'])) {
        $whereConditions[] = "s.status = ?";
        $params[] = $filters['status'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT s.*, 
               c.name as course_name, 
               b.batch_name, 
               tc.center_name as training_center_name,
               COALESCE(fee_summary.total_fees, 0) as total_fees,
               COALESCE(fee_summary.paid_fees, 0) as paid_fees,
               COALESCE(fee_summary.pending_fees, 0) as pending_fees,
               COALESCE(result_summary.total_assessments, 0) as total_assessments,
               COALESCE(result_summary.passed_assessments, 0) as passed_assessments,
               CASE 
                   WHEN result_summary.total_assessments > 0 
                   THEN ROUND((result_summary.passed_assessments / result_summary.total_assessments) * 100, 2)
                   ELSE 0 
               END as pass_percentage
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        LEFT JOIN (
            SELECT student_id, 
                   SUM(amount) as total_fees,
                   SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_fees,
                   SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_fees
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
        WHERE $whereClause
        ORDER BY s.created_at DESC
        LIMIT 500
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Students report error: " . $e->getMessage());
        return [];
    }
}

function generateFeesReport($whereConditions, $params, $filters, $db) {
    if (!empty($filters['status'])) {
        $whereConditions[] = "f.status = ?";
        $params[] = $filters['status'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT f.*, 
               s.name as student_name, 
               s.enrollment_number,
               c.name as course_name,
               b.batch_name,
               tc.center_name as training_center_name
        FROM fees f
        JOIN students s ON f.student_id = s.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        WHERE $whereClause
        ORDER BY f.created_at DESC
        LIMIT 500
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Fees report error: " . $e->getMessage());
        return [];
    }
}

function generateResultsReport($whereConditions, $params, $filters, $db) {
    if (!empty($filters['status'])) {
        $whereConditions[] = "r.result_status = ?";
        $params[] = $filters['status'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT r.*, 
               s.name as student_name, 
               s.enrollment_number,
               a.title as assessment_title,
               c.name as course_name,
               tc.center_name as training_center_name
        FROM results r
        JOIN students s ON r.student_id = s.id
        LEFT JOIN assessments a ON r.assessment_id = a.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        WHERE $whereClause
        ORDER BY r.completed_at DESC
        LIMIT 500
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Results report error: " . $e->getMessage());
        return [];
    }
}

function generateBatchesReport($whereConditions, $params, $filters, $db) {
    $whereClause = str_replace('s.', 'b.', implode(' AND ', $whereConditions));
    
    $query = "
        SELECT b.*, 
               c.name as course_name,
               tc.center_name as training_center_name,
               COUNT(DISTINCT s.id) as total_students,
               COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_students,
               AVG(CASE WHEN r.result_status = 'pass' THEN 1.0 ELSE 0.0 END) * 100 as pass_rate
        FROM batches b
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN training_centers tc ON b.training_center_id = tc.id
        LEFT JOIN students s ON b.id = s.batch_id
        LEFT JOIN results r ON s.id = r.student_id
        WHERE $whereClause
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 200
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Batches report error: " . $e->getMessage());
        return [];
    }
}

function generateCentersReport($whereConditions, $params, $filters, $db) {
    $query = "
        SELECT tc.*, 
               COUNT(DISTINCT s.id) as total_students,
               COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_students,
               COUNT(DISTINCT b.id) as total_batches,
               SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END) as total_revenue
        FROM training_centers tc
        LEFT JOIN students s ON tc.id = s.training_center_id
        LEFT JOIN batches b ON tc.id = b.training_center_id
        LEFT JOIN fees f ON s.id = f.student_id
        WHERE tc.status = 'active'
        GROUP BY tc.id
        ORDER BY total_students DESC
        LIMIT 100
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Centers report error: " . $e->getMessage());
        return [];
    }
}

function generateReportSummary($reportType, $filters, $db, $currentUser) {
    try {
        switch ($reportType) {
            case 'students':
                $query = "SELECT 
                    COUNT(*) as total_count,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                    COUNT(CASE WHEN status = 'graduated' THEN 1 END) as graduated_count,
                    COUNT(CASE WHEN status = 'dropped' THEN 1 END) as dropped_count
                    FROM students s WHERE 1=1";
                break;
                
            case 'fees':
                $query = "SELECT 
                    COUNT(*) as total_count,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
                    FROM fees f JOIN students s ON f.student_id = s.id WHERE 1=1";
                break;
                
            case 'results':
                $query = "SELECT 
                    COUNT(*) as total_count,
                    COUNT(CASE WHEN result_status = 'pass' THEN 1 END) as pass_count,
                    COUNT(CASE WHEN result_status = 'fail' THEN 1 END) as fail_count,
                    AVG(percentage) as avg_percentage
                    FROM results r JOIN students s ON r.student_id = s.id WHERE 1=1";
                break;
                
            default:
                return [];
        }
        
        // Add role-based filtering
        if ($currentUser['role'] === 'training_partner' && !empty($currentUser['training_center_id'])) {
            $query .= " AND s.training_center_id = " . (int)$currentUser['training_center_id'];
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Summary report error: " . $e->getMessage());
        return [];
    }
}

function handleExport($format, $filters, $db, $currentUser) {
    $reportType = $filters['report_type'] ?? 'students';
    $data = generateReportData($reportType, $filters, $db, $currentUser);
    
    $filename = $reportType . '_report_' . date('Y-m-d_H-i-s');
    
    switch ($format) {
        case 'csv':
            exportToCSV($data, $filename, $reportType);
            break;
        case 'excel':
            exportToExcel($data, $filename, $reportType);
            break;
        case 'pdf':
            exportToPDF($data, $filename, $reportType);
            break;
    }
}

function exportToCSV($data, $filename, $reportType) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write header
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function exportToExcel($data, $filename, $reportType) {
    // For now, export as CSV with Excel MIME type
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    if (!empty($data)) {
        echo implode("\t", array_keys($data[0])) . "\n";
        foreach ($data as $row) {
            echo implode("\t", $row) . "\n";
        }
    }
}

function exportToPDF($data, $filename, $reportType) {
    // Basic PDF export - in production, use a proper PDF library
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    echo "PDF export would be implemented with a proper PDF library like TCPDF or mPDF";
}
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 px-0">
            <div class="sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-0">
                        <i class="fas fa-graduation-cap me-2"></i>SMS
                    </h4>
                    <small class="text-light opacity-75">Student Management</small>
                </div>
                <hr class="text-light">
                <nav class="nav flex-column px-3">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                    
                    <?php if ($userRole === 'admin'): ?>
                    <a class="nav-link" href="training-centers.php">
                        <i class="fas fa-building me-2"></i>Training Centers
                    </a>
                    <a class="nav-link" href="masters.php">
                        <i class="fas fa-cogs me-2"></i>Masters
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'admin' || $userRole === 'training_partner'): ?>
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-users me-2"></i>Students
                    </a>
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-layer-group me-2"></i>Batches
                    </a>
                    <a class="nav-link" href="assessments.php">
                        <i class="fas fa-clipboard-check me-2"></i>Assessments
                    </a>
                    <a class="nav-link" href="fees.php">
                        <i class="fas fa-money-bill me-2"></i>Fees
                    </a>
                    <?php endif; ?>
                    
                    <a class="nav-link active" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                    
                    <hr class="text-light">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h2 class="mb-1">Reports & Analytics</h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-chart-bar me-1"></i>
                                Generate and analyze system reports
                            </p>
                        </div>
                        <div class="col-auto">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Reports</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>">
                            <i class="fas fa-file-csv me-1"></i>Export as CSV
                        </a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>">
                            <i class="fas fa-file-excel me-1"></i>Export as Excel
                        </a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>">
                            <i class="fas fa-file-pdf me-1"></i>Export as PDF
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- Report Type Selection -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter me-1"></i>Report Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-2">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type" onchange="this.form.submit()">
                                <option value="students" <?php echo $reportType === 'students' ? 'selected' : ''; ?>>Students</option>
                                <option value="fees" <?php echo $reportType === 'fees' ? 'selected' : ''; ?>>Fees</option>
                                <option value="results" <?php echo $reportType === 'results' ? 'selected' : ''; ?>>Results</option>
                                <option value="batches" <?php echo $reportType === 'batches' ? 'selected' : ''; ?>>Batches</option>
                                <?php if ($userRole === 'admin'): ?>
                                <option value="centers" <?php echo $reportType === 'centers' ? 'selected' : ''; ?>>Training Centers</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>"
                                   placeholder="Search...">
                        </div>
                        
                        <?php if (!empty($courses)): ?>
                        <div class="col-md-2">
                            <label for="course" class="form-label">Course</label>
                            <select class="form-select" id="course" name="course">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo $courseFilter == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($batches)): ?>
                        <div class="col-md-2">
                            <label for="batch" class="form-label">Batch</label>
                            <select class="form-select" id="batch" name="batch">
                                <option value="">All Batches</option>
                                <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>" <?php echo $batchFilter == $batch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($trainingCenters)): ?>
                        <div class="col-md-2">
                            <label for="training_center" class="form-label">Training Center</label>
                            <select class="form-select" id="training_center" name="training_center">
                                <option value="">All Centers</option>
                                <?php foreach ($trainingCenters as $center): ?>
                                <option value="<?php echo $center['id']; ?>" <?php echo $trainingCenterFilter == $center['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($center['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <?php if ($reportType === 'students'): ?>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="graduated" <?php echo $statusFilter === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                                    <option value="dropped" <?php echo $statusFilter === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                                <?php elseif ($reportType === 'fees'): ?>
                                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $statusFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <?php elseif ($reportType === 'results'): ?>
                                    <option value="pass" <?php echo $statusFilter === 'pass' ? 'selected' : ''; ?>>Pass</option>
                                    <option value="fail" <?php echo $statusFilter === 'fail' ? 'selected' : ''; ?>>Fail</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <?php if (!empty($reportSummary)): ?>
            <div class="row mb-4">
                <?php if ($reportType === 'students'): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Students</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['total_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Students</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['active_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Graduated</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['graduated_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Dropped</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['dropped_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-times fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($reportType === 'fees'): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Amount</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($reportSummary['total_amount'] ?? 0, 2); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-rupee-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Paid Amount</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($reportSummary['paid_amount'] ?? 0, 2); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Amount</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($reportSummary['pending_amount'] ?? 0, 2); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Records</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['total_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($reportType === 'results'): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Results</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['total_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Passed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['pass_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Failed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($reportSummary['fail_count'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average %</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo round($reportSummary['avg_percentage'] ?? 0, 1); ?>%</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Report Data Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-table me-1"></i><?php echo ucfirst($reportType); ?> Report Data
                        <span class="badge bg-primary ms-2"><?php echo count($reportData); ?> records</span>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <?php if ($reportType === 'students'): ?>
                                        <th>Name</th>
                                        <th>Enrollment</th>
                                        <th>Course</th>
                                        <th>Batch</th>
                                        <?php if ($userRole === 'admin'): ?><th>Training Center</th><?php endif; ?>
                                        <th>Status</th>
                                        <th>Fees (Paid/Total)</th>
                                        <th>Pass Rate</th>
                                        <th>Admission Date</th>
                                    <?php elseif ($reportType === 'fees'): ?>
                                        <th>Student</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Paid Date</th>
                                        <th>Payment Method</th>
                                    <?php elseif ($reportType === 'results'): ?>
                                        <th>Student</th>
                                        <th>Assessment</th>
                                        <th>Marks Obtained</th>
                                        <th>Total Marks</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    <?php elseif ($reportType === 'batches'): ?>
                                        <th>Batch Name</th>
                                        <th>Course</th>
                                        <?php if ($userRole === 'admin'): ?><th>Training Center</th><?php endif; ?>
                                        <th>Students</th>
                                        <th>Active Students</th>
                                        <th>Pass Rate</th>
                                        <th>Start Date</th>
                                        <th>Status</th>
                                    <?php elseif ($reportType === 'centers'): ?>
                                        <th>Center Name</th>
                                        <th>Contact Person</th>
                                        <th>Students</th>
                                        <th>Batches</th>
                                        <th>Revenue</th>
                                        <th>City</th>
                                        <th>Status</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php if ($reportType === 'students'): ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($row['phone'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['enrollment_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['batch_name'] ?? 'N/A'); ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                        <td><?php echo htmlspecialchars($row['training_center_name'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'active' => 'bg-success',
                                                'graduated' => 'bg-info',
                                                'dropped' => 'bg-danger',
                                                'inactive' => 'bg-secondary'
                                            ];
                                            $class = $statusClass[$row['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $class; ?>"><?php echo ucfirst($row['status']); ?></span>
                                        </td>
                                        <td>
                                            ₹<?php echo number_format($row['paid_fees'], 2); ?> / ₹<?php echo number_format($row['total_fees'], 2); ?>
                                        </td>
                                        <td>
                                            <?php echo $row['pass_percentage']; ?>%
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar" style="width: <?php echo $row['pass_percentage']; ?>%"></div>
                                            </div>
                                        </td>
                                        <td><?php echo $row['admission_date'] ? date('d/m/Y', strtotime($row['admission_date'])) : 'N/A'; ?></td>
                                        
                                    <?php elseif ($reportType === 'fees'): ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($row['enrollment_number'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><strong>₹<?php echo number_format($row['amount'], 2); ?></strong></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($row['fee_type']); ?></span></td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'paid' => 'bg-success',
                                                'pending' => 'bg-warning',
                                                'overdue' => 'bg-danger',
                                                'waived' => 'bg-secondary'
                                            ];
                                            $class = $statusClass[$row['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $class; ?>"><?php echo ucfirst($row['status']); ?></span>
                                        </td>
                                        <td><?php echo $row['due_date'] ? date('d/m/Y', strtotime($row['due_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $row['paid_date'] ? date('d/m/Y', strtotime($row['paid_date'])) : '-'; ?></td>
                                        <td><?php echo $row['payment_method'] ? ucfirst($row['payment_method']) : '-'; ?></td>
                                        
                                    <?php elseif ($reportType === 'results'): ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($row['enrollment_number'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['assessment_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo $row['marks_obtained'] ?? '-'; ?></td>
                                        <td><?php echo $row['total_marks'] ?? '-'; ?></td>
                                        <td>
                                            <?php echo round($row['percentage'] ?? 0, 1); ?>%
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar" style="width: <?php echo $row['percentage'] ?? 0; ?>%"></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['grade'] ?? '-'); ?></td>
                                        <td>
                                            <?php
                                            $class = $row['result_status'] === 'pass' ? 'bg-success' : 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $class; ?>"><?php echo ucfirst($row['result_status'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td><?php echo $row['completed_at'] ? date('d/m/Y', strtotime($row['completed_at'])) : 'N/A'; ?></td>
                                        
                                    <?php elseif ($reportType === 'batches'): ?>
                                        <td><strong><?php echo htmlspecialchars($row['batch_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                        <td><?php echo htmlspecialchars($row['training_center_name'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo $row['total_students']; ?></td>
                                        <td><?php echo $row['active_students']; ?></td>
                                        <td>
                                            <?php echo round($row['pass_rate'] ?? 0, 1); ?>%
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar" style="width: <?php echo $row['pass_rate'] ?? 0; ?>%"></div>
                                            </div>
                                        </td>
                                        <td><?php echo $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'active' => 'bg-success',
                                                'completed' => 'bg-info',
                                                'planning' => 'bg-warning',
                                                'cancelled' => 'bg-danger'
                                            ];
                                            $class = $statusClass[$row['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $class; ?>"><?php echo ucfirst($row['status']); ?></span>
                                        </td>
                                        
                                    <?php elseif ($reportType === 'centers'): ?>
                                        <td><strong><?php echo htmlspecialchars($row['center_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['contact_person'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo $row['total_students']; ?>
                                            <small class="text-muted">(<?php echo $row['active_students']; ?> active)</small>
                                        </td>
                                        <td><?php echo $row['total_batches']; ?></td>
                                        <td>₹<?php echo number_format($row['total_revenue'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['city'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $class = $row['status'] === 'active' ? 'bg-success' : 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $class; ?>"><?php echo ucfirst($row['status']); ?></span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- End of main content -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#reportTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columnDefs: [
            { orderable: true, targets: '_all' }
        ]
    });
});
</script>

<style>
.sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}
.sidebar .nav-link {
    color: rgba(255,255,255,0.8);
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    margin: 0.25rem 0;
    transition: all 0.3s ease;
}
.sidebar .nav-link:hover, .sidebar .nav-link.active {
    background: rgba(255,255,255,0.2);
    color: white;
    transform: translateX(5px);
}

.border-left-primary { border-left: 4px solid var(--primary-color) !important; }
.border-left-success { border-left: 4px solid var(--success-color) !important; }
.border-left-warning { border-left: 4px solid var(--warning-color) !important; }
.border-left-danger { border-left: 4px solid var(--danger-color) !important; }
.border-left-info { border-left: 4px solid var(--info-color) !important; }
.text-gray-800 { color: #5a5c69 !important; }
.text-gray-300 { color: #dddfeb !important; }
.progress { margin-top: 2px; }
</style>

<?php include '../includes/layout-enhanced-footer.php'; ?>

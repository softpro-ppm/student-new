<?php
// Reports Management - v2.0
session_start();
require_once '../config/database.php';

$currentUser = ['role' => 'admin', 'name' => 'Administrator'];
$message = '';
$messageType = '';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$batch_filter = $_GET['batch'] ?? '';
$course_filter = $_GET['course'] ?? '';
$center_filter = $_GET['center'] ?? '';
$export = $_GET['export'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Build filter conditions
    $whereConditions = ["s.status != 'deleted'"];
    $params = [];
    
    if ($date_from) {
        $whereConditions[] = "s.admission_date >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $whereConditions[] = "s.admission_date <= ?";
        $params[] = $date_to;
    }
    
    if ($batch_filter) {
        $whereConditions[] = "sb.batch_id = ?";
        $params[] = $batch_filter;
    }
    
    if ($course_filter) {
        $whereConditions[] = "c.id = ?";
        $params[] = $course_filter;
    }
    
    if ($center_filter) {
        $whereConditions[] = "s.training_center_id = ?";
        $params[] = $center_filter;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(DISTINCT s.id) as total_students,
            COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_students,
            COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_students,
            COUNT(DISTINCT CASE WHEN s.status = 'dropped' THEN s.id END) as dropped_students,
            COUNT(DISTINCT b.id) as total_batches,
            COUNT(DISTINCT c.id) as total_courses,
            COALESCE(SUM(c.course_fee), 0) as total_course_fees,
            COALESCE(SUM(fp.amount), 0) as total_collected,
            COALESCE(SUM(c.course_fee), 0) - COALESCE(SUM(fp.amount), 0) as pending_dues
        FROM students s
        LEFT JOIN student_batches sb ON s.id = sb.student_id AND sb.status = 'active'
        LEFT JOIN batches b ON sb.batch_id = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id
        LEFT JOIN fee_payments fp ON s.id = fp.student_id AND fp.status = 'completed'
        WHERE $whereClause
    ";
    $summaryStmt = $conn->prepare($summaryQuery);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch();
    
    // Get center-wise data for charts
    $centerQuery = "
        SELECT 
            tc.center_name,
            COUNT(DISTINCT s.id) as student_count,
            COALESCE(SUM(fp.amount), 0) as fees_collected
        FROM training_centers tc
        LEFT JOIN students s ON tc.id = s.training_center_id
        LEFT JOIN student_batches sb ON s.id = sb.student_id AND sb.status = 'active'
        LEFT JOIN batches b ON sb.batch_id = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN fee_payments fp ON s.id = fp.student_id AND fp.status = 'completed'
        WHERE tc.status = 'active' " . ($whereClause ? " AND " . str_replace('s.status', 'COALESCE(s.status, \'active\')', $whereClause) : "") . "
        GROUP BY tc.id, tc.center_name
        ORDER BY student_count DESC
        LIMIT 10
    ";
    $centerStmt = $conn->prepare($centerQuery);
    $centerStmt->execute($params);
    $centerData = $centerStmt->fetchAll();
    
    // Get course-wise data for charts
    $courseQuery = "
        SELECT 
            c.course_name,
            COUNT(DISTINCT s.id) as student_count,
            COALESCE(SUM(fp.amount), 0) as fees_collected
        FROM courses c
        LEFT JOIN batches b ON c.id = b.course_id
        LEFT JOIN student_batches sb ON b.id = sb.batch_id AND sb.status = 'active'
        LEFT JOIN students s ON sb.student_id = s.id
        LEFT JOIN fee_payments fp ON s.id = fp.student_id AND fp.status = 'completed'
        WHERE c.status = 'active' " . ($whereClause ? " AND " . str_replace('s.status', 'COALESCE(s.status, \'active\')', $whereClause) : "") . "
        GROUP BY c.id, c.course_name
        ORDER BY student_count DESC
        LIMIT 10
    ";
    $courseStmt = $conn->prepare($courseQuery);
    $courseStmt->execute($params);
    $courseData = $courseStmt->fetchAll();
    
    // Get monthly enrollment data for trend chart
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT(s.admission_date, '%Y-%m') as month,
            COUNT(*) as enrollments,
            SUM(fp.amount) as fees_collected
        FROM students s
        LEFT JOIN fee_payments fp ON s.id = fp.student_id AND fp.status = 'completed'
        WHERE s.admission_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND s.status != 'deleted'
        GROUP BY DATE_FORMAT(s.admission_date, '%Y-%m')
        ORDER BY month ASC
    ";
    $monthlyStmt = $conn->query($monthlyQuery);
    $monthlyData = $monthlyStmt->fetchAll();
    
    // Get detailed student data for export
    if ($export) {
        $detailQuery = "
            SELECT 
                s.enrollment_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.phone,
                s.email,
                s.admission_date,
                s.status as student_status,
                tc.center_name,
                c.course_name,
                c.course_fee,
                b.batch_name,
                b.start_date as batch_start_date,
                b.end_date as batch_end_date,
                COALESCE(SUM(fp.amount), 0) as total_paid,
                (c.course_fee - COALESCE(SUM(fp.amount), 0)) as balance_due
            FROM students s
            LEFT JOIN training_centers tc ON s.training_center_id = tc.id
            LEFT JOIN student_batches sb ON s.id = sb.student_id AND sb.status = 'active'
            LEFT JOIN batches b ON sb.batch_id = b.id
            LEFT JOIN courses c ON b.course_id = c.id
            LEFT JOIN fee_payments fp ON s.id = fp.student_id AND fp.status = 'completed'
            WHERE $whereClause
            GROUP BY s.id
            ORDER BY s.admission_date DESC
        ";
        $detailStmt = $conn->prepare($detailQuery);
        $detailStmt->execute($params);
        $detailData = $detailStmt->fetchAll();
        
        if ($export === 'excel') {
            exportToExcel($detailData, $summary);
            exit;
        } elseif ($export === 'pdf') {
            exportToPDF($detailData, $summary);
            exit;
        }
    }
    
    // Get dropdowns data
    $batchStmt = $conn->query("SELECT id, batch_name FROM batches WHERE status != 'deleted' ORDER BY batch_name");
    $batches = $batchStmt->fetchAll();
    
    $courseStmt = $conn->query("SELECT id, course_name FROM courses WHERE status = 'active' ORDER BY course_name");
    $courses = $courseStmt->fetchAll();
    
    $centerStmt = $conn->query("SELECT id, center_name FROM training_centers WHERE status = 'active' ORDER BY center_name");
    $trainingCenters = $centerStmt->fetchAll();
    
} catch (Exception $e) {
    $summary = ['total_students' => 0, 'active_students' => 0, 'completed_students' => 0, 'dropped_students' => 0, 'total_batches' => 0, 'total_courses' => 0, 'total_course_fees' => 0, 'total_collected' => 0, 'pending_dues' => 0];
    $centerData = [];
    $courseData = [];
    $monthlyData = [];
    $batches = [];
    $courses = [];
    $trainingCenters = [];
    $message = "Error loading data: " . $e->getMessage();
    $messageType = "error";
}

// Export functions
function exportToExcel($data, $summary) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="student_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='14' style='background-color: #4CAF50; color: white; text-align: center;'>Student Management Report - " . date('d M Y') . "</th></tr>";
    echo "<tr><th colspan='14'></th></tr>";
    
    // Summary
    echo "<tr><th>Total Students</th><td>{$summary['total_students']}</td><th>Total Fees</th><td>₹" . number_format($summary['total_course_fees']) . "</td><th>Collected</th><td>₹" . number_format($summary['total_collected']) . "</td><th>Pending</th><td>₹" . number_format($summary['pending_dues']) . "</td><th colspan='6'></th></tr>";
    echo "<tr><th colspan='14'></th></tr>";
    
    // Headers
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Enrollment Number</th>";
    echo "<th>Student Name</th>";
    echo "<th>Phone</th>";
    echo "<th>Email</th>";
    echo "<th>Admission Date</th>";
    echo "<th>Status</th>";
    echo "<th>Training Center</th>";
    echo "<th>Course</th>";
    echo "<th>Course Fee</th>";
    echo "<th>Batch</th>";
    echo "<th>Batch Start</th>";
    echo "<th>Batch End</th>";
    echo "<th>Total Paid</th>";
    echo "<th>Balance Due</th>";
    echo "</tr>";
    
    // Data
    foreach ($data as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['enrollment_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['admission_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['center_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
        echo "<td>₹" . number_format($row['course_fee']) . "</td>";
        echo "<td>" . htmlspecialchars($row['batch_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['batch_start_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['batch_end_date']) . "</td>";
        echo "<td>₹" . number_format($row['total_paid']) . "</td>";
        echo "<td>₹" . number_format($row['balance_due']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

function exportToPDF($data, $summary) {
    // Generate a comprehensive HTML report for PDF printing
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    // Output HTML that's optimized for PDF printing
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Management Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .summary { margin-bottom: 30px; }
        .summary-item { display: inline-block; margin: 10px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Management Information System</h1>
        <h2>Comprehensive Report</h2>
        <p><strong>Report Period:</strong> <?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?></p>
        <p><strong>Generated:</strong> <?= date('d M Y, H:i:s') ?></p>
    </div>

    <div class="summary">
        <h3>Summary Statistics</h3>
        <div class="summary-item">
            <strong><?= $summary['total_students'] ?></strong><br>
            Total Students
        </div>
        <div class="summary-item">
            <strong><?= $summary['total_training_centers'] ?></strong><br>
            Training Centers
        </div>
        <div class="summary-item">
            <strong><?= $summary['total_courses'] ?></strong><br>
            Courses
        </div>
        <div class="summary-item">
            <strong>₹<?= number_format($summary['total_collected'], 2) ?></strong><br>
            Fees Collected
        </div>
        <div class="summary-item">
            <strong>₹<?= number_format($summary['pending_dues'], 2) ?></strong><br>
            Pending Dues
        </div>
    </div>

    <div class="details">
        <h3>Student Details</h3>
        <?php if (empty($data)): ?>
            <p>No student data found for the selected criteria.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Enrollment No.</th>
                        <th>Student Name</th>
                        <th>Phone</th>
                        <th>Training Center</th>
                        <th>Course</th>
                        <th>Admission Date</th>
                        <th>Status</th>
                        <th>Fees Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['enrollment_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= htmlspecialchars($row['center_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['admission_date'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(ucfirst($row['status'])) ?></td>
                            <td>₹<?= number_format($row['fees_paid'] ?? 0, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>This report was generated by Student Management Information System v2.0</p>
        <p>For any queries, please contact the system administrator.</p>
    </div>

    <div class="no-print" style="position: fixed; top: 10px; right: 10px; background: white; padding: 10px; border: 1px solid #ccc;">
        <button onclick="window.print()" style="background: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer;">Print/Save as PDF</button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; cursor: pointer; margin-left: 5px;">Close</button>
    </div>

    <script>
        // Auto-trigger print dialog
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
    <?php
    exit();
}

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">Reports & Analytics</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard-v2.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-success btn-rounded">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>" class="btn btn-danger btn-rounded">
                <i class="fas fa-file-pdf me-2"></i>Export PDF
            </a>
        </div>
    </div>
</div>

<!-- Content Body -->
<div class="content-body">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Batch</label>
                    <select class="form-select" name="batch">
                        <option value="">All Batches</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= $batch['id'] ?>" <?= $batch_filter == $batch['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($batch['batch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Course</label>
                    <select class="form-select" name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Training Center</label>
                    <select class="form-select" name="center">
                        <option value="">All Centers</option>
                        <?php foreach ($trainingCenters as $center): ?>
                            <option value="<?= $center['id'] ?>" <?= $center_filter == $center['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($center['center_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-primary">
                    <i class="fas fa-users"></i>
                </div>
                <h4><?= $summary['total_students'] ?></h4>
                <p class="text-muted mb-0">Total Students</p>
                <small class="text-success">
                    <i class="fas fa-check-circle"></i> <?= $summary['active_students'] ?> Active
                </small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-info">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <h4>₹<?= number_format($summary['total_course_fees']) ?></h4>
                <p class="text-muted mb-0">Total Fees</p>
                <small class="text-info">
                    <i class="fas fa-layer-group"></i> <?= $summary['total_batches'] ?> Batches
                </small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4>₹<?= number_format($summary['total_collected']) ?></h4>
                <p class="text-muted mb-0">Fees Collected</p>
                <small class="text-success">
                    <?= $summary['total_course_fees'] > 0 ? round(($summary['total_collected'] / $summary['total_course_fees']) * 100, 1) : 0 ?>% Collection Rate
                </small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <h4>₹<?= number_format($summary['pending_dues']) ?></h4>
                <p class="text-muted mb-0">Pending Dues</p>
                <small class="text-warning">
                    <i class="fas fa-graduation-cap"></i> <?= $summary['completed_students'] ?> Completed
                </small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Student Status Distribution -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Student Status Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Monthly Enrollment Trend -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Enrollment Trend</h6>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Center-wise Performance -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-building me-2"></i>Training Center Performance</h6>
                </div>
                <div class="card-body">
                    <canvas id="centerChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Course-wise Enrollment -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-book me-2"></i>Course-wise Enrollment</h6>
                </div>
                <div class="card-body">
                    <canvas id="courseChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Center-wise Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Training Center</th>
                                    <th>Students</th>
                                    <th>Fees Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($centerData as $center): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($center['center_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= $center['student_count'] ?></span></td>
                                        <td><strong class="text-success">₹<?= number_format($center['fees_collected']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Course-wise Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Students</th>
                                    <th>Fees Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courseData as $course): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($course['course_name']) ?></td>
                                        <td><span class="badge bg-info"><?= $course['student_count'] ?></span></td>
                                        <td><strong class="text-success">₹<?= number_format($course['fees_collected']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Student Status Distribution Pie Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: ['Active', 'Enrolled', 'Completed', 'Dropped'],
        datasets: [{
            data: [
                <?= $summary['active_students'] ?>,
                <?= $summary['total_students'] - $summary['active_students'] - $summary['completed_students'] - $summary['dropped_students'] ?>,
                <?= $summary['completed_students'] ?>,
                <?= $summary['dropped_students'] ?>
            ],
            backgroundColor: ['#28a745', '#17a2b8', '#007bff', '#dc3545'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Monthly Enrollment Trend Line Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: [<?php foreach($monthlyData as $month) echo "'" . date('M Y', strtotime($month['month'] . '-01')) . "',"; ?>],
        datasets: [{
            label: 'Enrollments',
            data: [<?php foreach($monthlyData as $month) echo $month['enrollments'] . ','; ?>],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Training Center Performance Bar Chart
const centerCtx = document.getElementById('centerChart').getContext('2d');
new Chart(centerCtx, {
    type: 'bar',
    data: {
        labels: [<?php foreach($centerData as $center) echo "'" . addslashes($center['center_name']) . "',"; ?>],
        datasets: [{
            label: 'Students',
            data: [<?php foreach($centerData as $center) echo $center['student_count'] . ','; ?>],
            backgroundColor: 'rgba(40, 167, 69, 0.8)',
            borderColor: '#28a745',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Course-wise Enrollment Horizontal Bar Chart
const courseCtx = document.getElementById('courseChart').getContext('2d');
new Chart(courseCtx, {
    type: 'horizontalBar',
    data: {
        labels: [<?php foreach($courseData as $course) echo "'" . addslashes($course['course_name']) . "',"; ?>],
        datasets: [{
            label: 'Students',
            data: [<?php foreach($courseData as $course) echo $course['student_count'] . ','; ?>],
            backgroundColor: 'rgba(23, 162, 184, 0.8)',
            borderColor: '#17a2b8',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            x: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Reports & Analytics', 'reports', $content);
?>

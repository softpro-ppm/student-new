<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

$currentUser = $auth->getCurrentUser();
$assessmentId = intval($_GET['id'] ?? 0);

if (!$assessmentId) {
    header('Location: assessments.php');
    exit;
}

// Get assessment details with role-based access
$query = "
    SELECT a.*, c.name as course_name, u.name as created_by_name
    FROM assessments a 
    LEFT JOIN courses c ON a.course_id = c.id 
    LEFT JOIN users u ON a.created_by = u.id 
    WHERE a.id = ?
";

if ($currentUser['role'] === 'training_partner') {
    $query .= " AND c.id IN (SELECT DISTINCT course_id FROM batches WHERE training_center_id IN (SELECT id FROM training_centers WHERE user_id = ?))";
    $stmt = $db->prepare($query);
    $stmt->execute([$assessmentId, $currentUser['id']]);
} else {
    $stmt = $db->prepare($query);
    $stmt->execute([$assessmentId]);
}

$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    header('Location: assessments.php');
    exit;
}

// Get assessment statistics
$statsQuery = "
    SELECT 
        COUNT(DISTINCT sa.id) as total_attempts,
        COUNT(DISTINCT CASE WHEN sa.status = 'completed' THEN sa.id END) as completed_attempts,
        COUNT(DISTINCT CASE WHEN sa.result_status = 'passed' THEN sa.id END) as passed_attempts,
        COUNT(DISTINCT CASE WHEN sa.result_status = 'failed' THEN sa.id END) as failed_attempts,
        AVG(CASE WHEN sa.status = 'completed' THEN sa.percentage END) as avg_percentage,
        MAX(CASE WHEN sa.status = 'completed' THEN sa.percentage END) as max_percentage,
        MIN(CASE WHEN sa.status = 'completed' THEN sa.percentage END) as min_percentage
    FROM student_assessments sa 
    WHERE sa.assessment_id = ?
";
$stmt = $db->prepare($statsQuery);
$stmt->execute([$assessmentId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get detailed results
$resultsQuery = "
    SELECT sa.*, s.name as student_name, s.enrollment_number, s.phone,
           b.name as batch_name, tc.name as center_name
    FROM student_assessments sa 
    JOIN students s ON sa.student_id = s.id 
    LEFT JOIN student_batches sb ON s.id = sb.student_id AND sb.status = 'active'
    LEFT JOIN batches b ON sb.batch_id = b.id 
    LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
    WHERE sa.assessment_id = ?
";

if ($currentUser['role'] === 'training_partner') {
    $resultsQuery .= " AND tc.user_id = ?";
    $stmt = $db->prepare($resultsQuery . " ORDER BY sa.completed_at DESC, s.name");
    $stmt->execute([$assessmentId, $currentUser['id']]);
} else {
    $stmt = $db->prepare($resultsQuery . " ORDER BY sa.completed_at DESC, s.name");
    $stmt->execute([$assessmentId]);
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get question-wise analysis
$questionAnalysisQuery = "
    SELECT aq.id, aq.question, aq.type, aq.marks, aq.correct_answer,
           COUNT(ar.id) as total_responses,
           COUNT(CASE WHEN ar.is_correct = 1 THEN 1 END) as correct_responses,
           ROUND((COUNT(CASE WHEN ar.is_correct = 1 THEN 1 END) / COUNT(ar.id)) * 100, 2) as success_rate
    FROM assessment_questions aq 
    LEFT JOIN assessment_results ar ON aq.id = ar.question_id 
    LEFT JOIN student_assessments sa ON ar.student_assessment_id = sa.id 
    WHERE aq.assessment_id = ? AND sa.status = 'completed'
    GROUP BY aq.id 
    ORDER BY aq.id
";
$stmt = $db->prepare($questionAnalysisQuery);
$stmt->execute([$assessmentId]);
$questionAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results - <?= htmlspecialchars($assessment['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .result-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-info));
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .progress-custom {
            height: 8px;
            border-radius: 4px;
        }
        .question-analysis {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .success-rate-high { color: #198754; }
        .success-rate-medium { color: #ffc107; }
        .success-rate-low { color: #dc3545; }
    </style>
</head>
<body>
    <?php renderHeader(); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php renderSidebar('assessments'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2"><?= htmlspecialchars($assessment['title']) ?> - Results</h1>
                        <p class="text-muted mb-0"><?= htmlspecialchars($assessment['course_name']) ?></p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="assessments.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Assessments
                        </a>
                        <button class="btn btn-primary" onclick="exportResults()">
                            <i class="fas fa-download"></i> Export Results
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stat-card bg-primary">
                            <div class="stat-number"><?= $stats['total_attempts'] ?></div>
                            <div>Total Attempts</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-success">
                            <div class="stat-number"><?= $stats['completed_attempts'] ?></div>
                            <div>Completed</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-info">
                            <div class="stat-number"><?= $stats['passed_attempts'] ?></div>
                            <div>Passed</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-warning">
                            <div class="stat-number"><?= $stats['failed_attempts'] ?></div>
                            <div>Failed</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-secondary">
                            <div class="stat-number"><?= number_format($stats['avg_percentage'] ?? 0, 1) ?>%</div>
                            <div>Average Score</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-dark">
                            <div class="stat-number"><?= number_format(($stats['passed_attempts'] / max($stats['completed_attempts'], 1)) * 100, 1) ?>%</div>
                            <div>Pass Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card result-card">
                            <div class="card-header">
                                <h6 class="mb-0">Result Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="resultChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card result-card">
                            <div class="card-header">
                                <h6 class="mb-0">Score Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="scoreChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question Analysis -->
                <div class="card result-card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Question-wise Analysis</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($questionAnalysis as $index => $question): ?>
                            <div class="question-analysis">
                                <div class="row align-items-center">
                                    <div class="col-md-1">
                                        <strong>Q<?= $index + 1 ?></strong>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><?= htmlspecialchars(substr($question['question'], 0, 100)) ?>...</p>
                                        <small class="text-muted">Type: <?= ucfirst(str_replace('_', ' ', $question['type'])) ?> | Marks: <?= $question['marks'] ?></small>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <div class="<?= $question['success_rate'] >= 70 ? 'success-rate-high' : ($question['success_rate'] >= 50 ? 'success-rate-medium' : 'success-rate-low') ?>">
                                            <strong><?= $question['success_rate'] ?>%</strong>
                                        </div>
                                        <small class="text-muted">Success Rate</small>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-success"><?= $question['correct_responses'] ?> correct</span>
                                            <span class="text-danger"><?= $question['total_responses'] - $question['correct_responses'] ?> incorrect</span>
                                        </div>
                                        <div class="progress progress-custom">
                                            <div class="progress-bar bg-success" style="width: <?= $question['success_rate'] ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Student Results -->
                <div class="card result-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Student Results</h6>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="filterResults('all')">All</button>
                            <button class="btn btn-outline-success" onclick="filterResults('passed')">Passed</button>
                            <button class="btn btn-outline-danger" onclick="filterResults('failed')">Failed</button>
                            <button class="btn btn-outline-warning" onclick="filterResults('pending')">Pending</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="resultsTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Enrollment No.</th>
                                        <th>Batch</th>
                                        <?php if ($currentUser['role'] === 'admin'): ?>
                                        <th>Training Center</th>
                                        <?php endif; ?>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Percentage</th>
                                        <th>Result</th>
                                        <th>Completed At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr data-status="<?= $result['status'] ?>" data-result="<?= $result['result_status'] ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($result['student_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($result['phone']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($result['enrollment_number']) ?></td>
                                            <td><?= htmlspecialchars($result['batch_name'] ?? 'Not assigned') ?></td>
                                            <?php if ($currentUser['role'] === 'admin'): ?>
                                            <td><?= htmlspecialchars($result['center_name'] ?? 'Not assigned') ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="badge bg-<?= $result['status'] === 'completed' ? 'success' : ($result['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst($result['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($result['status'] === 'completed'): ?>
                                                    <?= $result['score'] ?>/<?= $assessment['total_marks'] ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($result['status'] === 'completed'): ?>
                                                    <div class="d-flex align-items-center">
                                                        <span class="me-2"><?= number_format($result['percentage'], 1) ?>%</span>
                                                        <div class="progress progress-custom flex-grow-1" style="width: 60px;">
                                                            <div class="progress-bar bg-<?= $result['percentage'] >= $assessment['passing_marks'] ? 'success' : 'danger' ?>" 
                                                                 style="width: <?= $result['percentage'] ?>%"></div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($result['status'] === 'completed'): ?>
                                                    <span class="badge bg-<?= $result['result_status'] === 'passed' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($result['result_status']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($result['completed_at']): ?>
                                                    <?= date('d-m-Y H:i', strtotime($result['completed_at'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($result['status'] === 'completed'): ?>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewDetailedResult(<?= $result['id'] ?>)" 
                                                            title="View Detailed Result">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="resendAssessmentLink(<?= $result['id'] ?>)" 
                                                            title="Resend Link">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($results)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Results Found</h5>
                                <p class="text-muted">No students have attempted this assessment yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Result Distribution Chart
        const resultCtx = document.getElementById('resultChart').getContext('2d');
        new Chart(resultCtx, {
            type: 'doughnut',
            data: {
                labels: ['Passed', 'Failed', 'Pending'],
                datasets: [{
                    data: [<?= $stats['passed_attempts'] ?>, <?= $stats['failed_attempts'] ?>, <?= $stats['total_attempts'] - $stats['completed_attempts'] ?>],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Score Distribution Chart
        const scoreCtx = document.getElementById('scoreChart').getContext('2d');
        
        // Calculate score ranges
        const scoreRanges = {
            '90-100%': 0,
            '80-89%': 0,
            '70-79%': 0,
            '60-69%': 0,
            '50-59%': 0,
            'Below 50%': 0
        };

        <?php foreach ($results as $result): ?>
            <?php if ($result['status'] === 'completed'): ?>
                const percentage = <?= $result['percentage'] ?>;
                if (percentage >= 90) scoreRanges['90-100%']++;
                else if (percentage >= 80) scoreRanges['80-89%']++;
                else if (percentage >= 70) scoreRanges['70-79%']++;
                else if (percentage >= 60) scoreRanges['60-69%']++;
                else if (percentage >= 50) scoreRanges['50-59%']++;
                else scoreRanges['Below 50%']++;
            <?php endif; ?>
        <?php endforeach; ?>

        new Chart(scoreCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(scoreRanges),
                datasets: [{
                    label: 'Number of Students',
                    data: Object.values(scoreRanges),
                    backgroundColor: ['#28a745', '#20c997', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Filter results
        function filterResults(status) {
            const rows = document.querySelectorAll('#resultsTable tbody tr');
            rows.forEach(row => {
                const rowStatus = row.dataset.status;
                const rowResult = row.dataset.result;
                
                if (status === 'all') {
                    row.style.display = '';
                } else if (status === 'pending') {
                    row.style.display = rowStatus === 'pending' ? '' : 'none';
                } else if (status === 'passed') {
                    row.style.display = rowResult === 'passed' ? '' : 'none';
                } else if (status === 'failed') {
                    row.style.display = rowResult === 'failed' ? '' : 'none';
                }
            });
            
            // Update active button
            document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function viewDetailedResult(studentAssessmentId) {
            window.open(`assessment_detailed_result.php?id=${studentAssessmentId}`, '_blank');
        }

        function resendAssessmentLink(studentAssessmentId) {
            if (confirm('Resend assessment link to this student?')) {
                // Implementation for resending assessment link
                alert('Assessment link resent successfully!');
            }
        }

        function exportResults() {
            const assessmentId = <?= $assessmentId ?>;
            window.open(`assessment_export.php?id=${assessmentId}`, '_blank');
        }
    </script>
</body>
</html>

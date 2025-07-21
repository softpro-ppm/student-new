<?php
require_once '../includes/auth.php';

$db = getConnection();

$token = $_GET['token'] ?? '';

// Validate token and get result details
$stmt = $db->prepare("
    SELECT sa.*, s.name as student_name, s.enrollment_number, 
           a.title, a.description, a.total_marks, a.passing_marks,
           c.name as course_name
    FROM student_assessments sa 
    JOIN students s ON sa.student_id = s.id 
    JOIN assessments a ON sa.assessment_id = a.id 
    JOIN courses c ON a.course_id = c.id 
    WHERE sa.token = ? AND sa.status = 'completed'
");
$stmt->execute([$token]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die('<div class="alert alert-danger">Invalid token or assessment not completed.</div>');
}

// Get detailed question results
$stmt = $db->prepare("
    SELECT ar.*, aq.question, aq.type, aq.options, aq.correct_answer, aq.marks
    FROM assessment_results ar 
    JOIN assessment_questions aq ON ar.question_id = aq.id 
    WHERE ar.student_assessment_id = ? 
    ORDER BY aq.id
");
$stmt->execute([$result['id']]);
$questionResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Result - <?= htmlspecialchars($result['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .result-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .result-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .result-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .result-badge {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            border: 5px solid white;
        }
        .result-passed {
            background: #28a745;
        }
        .result-failed {
            background: #dc3545;
        }
        .question-review {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .question-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        .question-content {
            padding: 20px;
        }
        .answer-correct {
            background: #d1edff;
            border: 2px solid #0066cc;
            border-radius: 8px;
            padding: 10px;
            margin: 5px 0;
        }
        .answer-incorrect {
            background: #ffe6e6;
            border: 2px solid #cc0000;
            border-radius: 8px;
            padding: 10px;
            margin: 5px 0;
        }
        .answer-neutral {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            margin: 5px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
        }
        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #6c757d;
        }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        @media print {
            .print-btn { display: none; }
            body { background: white !important; }
        }
    </style>
</head>
<body>
    <button class="btn btn-primary print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print Result
    </button>

    <div class="result-container">
        <div class="result-card">
            <!-- Result Header -->
            <div class="result-header">
                <div class="result-badge <?= $result['result_status'] === 'passed' ? 'result-passed' : 'result-failed' ?>">
                    <?= number_format($result['percentage'], 1) ?>%
                </div>
                <h2 class="mb-3"><?= htmlspecialchars($result['title']) ?></h2>
                <h4 class="mb-2"><?= htmlspecialchars($result['student_name']) ?></h4>
                <p class="mb-1">Enrollment No: <?= htmlspecialchars($result['enrollment_number']) ?></p>
                <p class="mb-0">Course: <?= htmlspecialchars($result['course_name']) ?></p>
                
                <div class="mt-4">
                    <span class="badge badge-lg bg-<?= $result['result_status'] === 'passed' ? 'success' : 'danger' ?> fs-5 px-4 py-2">
                        <?= $result['result_status'] === 'passed' ? 'PASSED' : 'FAILED' ?>
                    </span>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number text-primary"><?= $result['score'] ?></div>
                    <div class="text-muted">Score Obtained</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-info"><?= $result['total_marks'] ?></div>
                    <div class="text-muted">Total Marks</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-<?= $result['result_status'] === 'passed' ? 'success' : 'danger' ?>"><?= number_format($result['percentage'], 1) ?>%</div>
                    <div class="text-muted">Percentage</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-warning"><?= $result['passing_marks'] ?>%</div>
                    <div class="text-muted">Passing Marks</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-secondary"><?= $result['time_spent'] ?></div>
                    <div class="text-muted">Time Taken (minutes)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-dark"><?= date('d-m-Y', strtotime($result['completed_at'])) ?></div>
                    <div class="text-muted">Completed On</div>
                </div>
            </div>

            <!-- Question Review -->
            <div class="px-4 pb-4">
                <h5 class="mb-4">
                    <i class="fas fa-list-alt me-2"></i>Question Review
                </h5>
                
                <?php foreach ($questionResults as $index => $qResult): ?>
                    <div class="question-review">
                        <div class="question-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="mb-0">
                                        Question <?= $index + 1 ?>
                                        <span class="badge bg-<?= $qResult['is_correct'] ? 'success' : 'danger' ?> ms-2">
                                            <?= $qResult['marks_obtained'] ?>/<?= $qResult['marks'] ?> marks
                                        </span>
                                    </h6>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-<?= $qResult['is_correct'] ? 'check-circle text-success' : 'times-circle text-danger' ?> fa-lg"></i>
                                </div>
                            </div>
                        </div>
                        <div class="question-content">
                            <p class="fw-bold mb-3"><?= nl2br(htmlspecialchars($qResult['question'])) ?></p>
                            
                            <?php if ($qResult['type'] === 'multiple_choice'): ?>
                                <?php $options = json_decode($qResult['options'], true); ?>
                                <?php foreach ($options as $key => $option): ?>
                                    <div class="<?= 
                                        $qResult['correct_answer'] == $key ? 'answer-correct' : 
                                        ($qResult['student_answer'] == $key ? 'answer-incorrect' : 'answer-neutral') 
                                    ?>">
                                        <strong><?= chr(65 + $key) ?>.</strong> <?= htmlspecialchars($option) ?>
                                        <?php if ($qResult['correct_answer'] == $key): ?>
                                            <span class="float-end"><i class="fas fa-check text-success"></i> Correct Answer</span>
                                        <?php elseif ($qResult['student_answer'] == $key): ?>
                                            <span class="float-end"><i class="fas fa-times text-danger"></i> Your Answer</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                            <?php elseif ($qResult['type'] === 'true_false'): ?>
                                <div class="<?= $qResult['correct_answer'] === 'true' ? 'answer-correct' : 'answer-neutral' ?>">
                                    <strong>True</strong>
                                    <?php if ($qResult['correct_answer'] === 'true'): ?>
                                        <span class="float-end"><i class="fas fa-check text-success"></i> Correct Answer</span>
                                    <?php elseif ($qResult['student_answer'] === 'true'): ?>
                                        <span class="float-end"><i class="fas fa-times text-danger"></i> Your Answer</span>
                                    <?php endif; ?>
                                </div>
                                <div class="<?= $qResult['correct_answer'] === 'false' ? 'answer-correct' : 'answer-neutral' ?>">
                                    <strong>False</strong>
                                    <?php if ($qResult['correct_answer'] === 'false'): ?>
                                        <span class="float-end"><i class="fas fa-check text-success"></i> Correct Answer</span>
                                    <?php elseif ($qResult['student_answer'] === 'false'): ?>
                                        <span class="float-end"><i class="fas fa-times text-danger"></i> Your Answer</span>
                                    <?php endif; ?>
                                </div>
                                
                            <?php elseif ($qResult['type'] === 'text'): ?>
                                <div class="<?= $qResult['is_correct'] ? 'answer-correct' : 'answer-incorrect' ?>">
                                    <strong>Your Answer:</strong> <?= htmlspecialchars($qResult['student_answer']) ?>
                                </div>
                                <div class="answer-correct mt-2">
                                    <strong>Expected Answer:</strong> <?= htmlspecialchars($qResult['correct_answer']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer -->
            <div class="bg-light text-center py-4">
                <p class="mb-2 text-muted">
                    <i class="fas fa-certificate me-2"></i>
                    This is an official assessment result from the Student Management System
                </p>
                <p class="mb-0 small text-muted">
                    Generated on <?= date('d-m-Y H:i:s') ?> | 
                    Token: <?= htmlspecialchars(substr($token, 0, 8)) ?>...
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

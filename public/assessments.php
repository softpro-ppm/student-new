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
            case 'create_assessment':
                $title = trim($_POST['title']);
                $courseId = intval($_POST['course_id']);
                $description = trim($_POST['description'] ?? '');
                $timeLimit = intval($_POST['time_limit']);
                $totalMarks = intval($_POST['total_marks']);
                $passingMarks = intval($_POST['passing_marks']);
                $maxAttempts = intval($_POST['max_attempts']);
                $status = $_POST['status'];
                
                $stmt = $db->prepare("
                    INSERT INTO assessments 
                    (title, course_id, description, time_limit, total_marks, passing_marks, max_attempts, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $courseId, $description, $timeLimit, $totalMarks, 
                    $passingMarks, $maxAttempts, $status, $currentUser['id']
                ]);
                
                $assessmentId = $db->lastInsertId();
                $successMessage = 'Assessment created successfully!';
                
                // Redirect to edit mode to add questions
                header("Location: assessments.php?edit=$assessmentId");
                exit;
                break;
                
            case 'add_question':
                $assessmentId = intval($_POST['assessment_id']);
                $question = trim($_POST['question']);
                $type = $_POST['type'];
                $options = $_POST['options'] ?? [];
                $correctAnswer = $_POST['correct_answer'] ?? '';
                $marks = intval($_POST['marks']);
                
                $stmt = $db->prepare("
                    INSERT INTO assessment_questions 
                    (assessment_id, question, type, options, correct_answer, marks, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $assessmentId, $question, $type, 
                    json_encode($options), $correctAnswer, $marks, $currentUser['id']
                ]);
                
                $successMessage = 'Question added successfully!';
                break;
                
            case 'update_question':
                $id = intval($_POST['id']);
                $question = trim($_POST['question']);
                $type = $_POST['type'];
                $options = $_POST['options'] ?? [];
                $correctAnswer = $_POST['correct_answer'] ?? '';
                $marks = intval($_POST['marks']);
                
                $stmt = $db->prepare("
                    UPDATE assessment_questions 
                    SET question = ?, type = ?, options = ?, correct_answer = ?, marks = ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $question, $type, json_encode($options), $correctAnswer, $marks, $id
                ]);
                
                $successMessage = 'Question updated successfully!';
                break;
                
            case 'delete_question':
                $id = intval($_POST['id']);
                $stmt = $db->prepare("DELETE FROM assessment_questions WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = 'Question deleted successfully!';
                break;
                
            case 'send_assessment_link':
                $batchId = intval($_POST['batch_id']);
                $assessmentId = intval($_POST['assessment_id']);
                
                // Get batch students
                $stmt = $db->prepare("
                    SELECT s.id, s.name, s.phone, s.email, b.name as batch_name, c.name as course_name
                    FROM student_batches sb 
                    JOIN students s ON sb.student_id = s.id 
                    JOIN batches b ON sb.batch_id = b.id 
                    JOIN courses c ON b.course_id = c.id 
                    WHERE sb.batch_id = ? AND sb.status = 'active'
                ");
                $stmt->execute([$batchId]);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($students as $student) {
                    // Create unique assessment token for each student
                    $token = bin2hex(random_bytes(32));
                    
                    $stmt = $db->prepare("
                        INSERT INTO student_assessments 
                        (student_id, assessment_id, token, status) 
                        VALUES (?, ?, ?, 'pending') 
                        ON DUPLICATE KEY UPDATE 
                        token = VALUES(token), status = 'pending', updated_at = NOW()
                    ");
                    $stmt->execute([$student['id'], $assessmentId, $token]);
                    
                    // Here you would integrate with WhatsApp API
                    // For now, we'll just log the assessment link
                    $assessmentLink = "http://yourdomain.com/public/assessment.php?token=" . $token;
                    error_log("Assessment link for {$student['name']}: {$assessmentLink}");
                }
                
                $successMessage = 'Assessment links sent to ' . count($students) . ' students!';
                break;
        }
    } catch (Exception $e) {
        $errorMessage = 'Error: ' . $e->getMessage();
    }
}

// Get current filter values
$courseFilter = $_GET['course'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query with filters
$whereConditions = [];
$queryParams = [];

if ($courseFilter) {
    $whereConditions[] = "c.id = ?";
    $queryParams[] = $courseFilter;
}

if ($statusFilter) {
    $whereConditions[] = "a.status = ?";
    $queryParams[] = $statusFilter;
}

// Role-based restrictions
if ($currentUser['role'] === 'training_partner') {
    $whereConditions[] = "c.id IN (SELECT DISTINCT course_id FROM batches WHERE training_center_id IN (SELECT id FROM training_centers WHERE user_id = ?))";
    $queryParams[] = $currentUser['id'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get assessments
$stmt = $db->prepare("
    SELECT a.*, c.name as course_name, u.name as created_by_name,
           COUNT(DISTINCT aq.id) as question_count,
           COUNT(DISTINCT sa.id) as student_attempts,
           COUNT(DISTINCT CASE WHEN sa.status = 'completed' THEN sa.id END) as completed_attempts
    FROM assessments a 
    LEFT JOIN courses c ON a.course_id = c.id 
    LEFT JOIN users u ON a.created_by = u.id 
    LEFT JOIN assessment_questions aq ON a.id = aq.assessment_id
    LEFT JOIN student_assessments sa ON a.id = sa.assessment_id
    $whereClause
    GROUP BY a.id 
    ORDER BY a.created_at DESC
");
$stmt->execute($queryParams);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses for filter
$courseQuery = "SELECT * FROM courses ORDER BY name";
if ($currentUser['role'] === 'training_partner') {
    $courseQuery = "
        SELECT DISTINCT c.* FROM courses c 
        JOIN batches b ON c.id = b.course_id 
        JOIN training_centers tc ON b.training_center_id = tc.id 
        WHERE tc.user_id = ? 
        ORDER BY c.name
    ";
    $stmt = $db->prepare($courseQuery);
    $stmt->execute([$currentUser['id']]);
} else {
    $stmt = $db->prepare($courseQuery);
    $stmt->execute();
}
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected assessment details if editing
$selectedAssessment = null;
$assessmentQuestions = [];
if (isset($_GET['edit'])) {
    $assessmentId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM assessments WHERE id = ?");
    $stmt->execute([$assessmentId]);
    $selectedAssessment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedAssessment) {
        $stmt = $db->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY id");
        $stmt->execute([$assessmentId]);
        $assessmentQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../includes/layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Management - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .question-card {
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            margin-bottom: 1rem;
            background: var(--bs-body-bg);
        }
        .question-header {
            background: var(--bs-primary);
            color: white;
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            font-weight: 500;
        }
        .question-content {
            padding: 15px;
        }
        .option-input {
            margin-bottom: 8px;
        }
        .correct-answer {
            background-color: var(--bs-success);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }
        .assessment-stats {
            background: var(--bs-light);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            border-right: 1px solid var(--bs-border-color);
        }
        .stat-item:last-child {
            border-right: none;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--bs-primary);
        }
    </style>
</head>
<body>
    <?php renderHeader(); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php renderSidebar('assessments'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Assessment Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAssessmentModal">
                                <i class="fas fa-plus"></i> New Assessment
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="course" class="form-label">Course</label>
                                <select name="course" id="course" class="form-select">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>" <?= $courseFilter == $course['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="assessments.php" class="btn btn-outline-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Assessments List -->
                <div class="row">
                    <?php foreach ($assessments as $assessment): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?= htmlspecialchars($assessment['title']) ?></h6>
                                    <span class="badge bg-<?= $assessment['status'] === 'active' ? 'success' : ($assessment['status'] === 'draft' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($assessment['status']) ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text text-muted mb-2">
                                        <i class="fas fa-book"></i> <?= htmlspecialchars($assessment['course_name']) ?>
                                    </p>
                                    <p class="card-text">
                                        <?= htmlspecialchars(substr($assessment['description'], 0, 100)) ?>
                                        <?= strlen($assessment['description']) > 100 ? '...' : '' ?>
                                    </p>
                                    
                                    <div class="assessment-stats">
                                        <div class="row">
                                            <div class="col-4 stat-item">
                                                <div class="stat-number"><?= $assessment['question_count'] ?></div>
                                                <div class="text-muted small">Questions</div>
                                            </div>
                                            <div class="col-4 stat-item">
                                                <div class="stat-number"><?= $assessment['student_attempts'] ?></div>
                                                <div class="text-muted small">Attempts</div>
                                            </div>
                                            <div class="col-4 stat-item">
                                                <div class="stat-number"><?= $assessment['completed_attempts'] ?></div>
                                                <div class="text-muted small">Completed</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-2 mt-2">
                                        <div class="col-6">
                                            <span class="text-muted small">Duration: <?= $assessment['time_limit'] ?> min</span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted small">Passing: <?= $assessment['passing_marks'] ?>%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="btn-group w-100" role="group">
                                        <a href="assessments.php?edit=<?= $assessment['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="sendAssessmentLink(<?= $assessment['id'] ?>)">
                                            <i class="fas fa-paper-plane"></i> Send
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="viewResults(<?= $assessment['id'] ?>)">
                                            <i class="fas fa-chart-bar"></i> Results
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($assessments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Assessments Found</h5>
                        <p class="text-muted">Create your first assessment to get started.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAssessmentModal">
                            <i class="fas fa-plus"></i> Create Assessment
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Assessment Editor (when editing) -->
                <?php if ($selectedAssessment): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Edit Assessment: <?= htmlspecialchars($selectedAssessment['title']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Assessment Info -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <h6><?= htmlspecialchars($selectedAssessment['title']) ?></h6>
                                <p class="text-muted"><?= htmlspecialchars($selectedAssessment['description']) ?></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-info">Time: <?= $selectedAssessment['time_limit'] ?> min</span>
                                <span class="badge bg-success">Total: <?= $selectedAssessment['total_marks'] ?> marks</span>
                                <span class="badge bg-warning">Pass: <?= $selectedAssessment['passing_marks'] ?>%</span>
                            </div>
                        </div>

                        <!-- Add Question Form -->
                        <div class="border rounded p-3 mb-4">
                            <h6>Add New Question</h6>
                            <form method="POST" id="addQuestionForm">
                                <input type="hidden" name="action" value="add_question">
                                <input type="hidden" name="assessment_id" value="<?= $selectedAssessment['id'] ?>">
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <label class="form-label">Question *</label>
                                        <textarea name="question" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Type *</label>
                                        <select name="type" class="form-select" id="questionType" required>
                                            <option value="multiple_choice">Multiple Choice</option>
                                            <option value="true_false">True/False</option>
                                            <option value="text">Text Answer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Marks *</label>
                                        <input type="number" name="marks" class="form-control" value="5" min="1" required>
                                    </div>
                                </div>
                                
                                <!-- Options for Multiple Choice -->
                                <div id="optionsContainer" class="mt-3">
                                    <label class="form-label">Options *</label>
                                    <div id="optionsList">
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">A</span>
                                            <input type="text" name="options[]" class="form-control" placeholder="Option A" required>
                                            <div class="input-group-text">
                                                <input type="radio" name="correct_answer" value="0" required>
                                            </div>
                                        </div>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">B</span>
                                            <input type="text" name="options[]" class="form-control" placeholder="Option B" required>
                                            <div class="input-group-text">
                                                <input type="radio" name="correct_answer" value="1" required>
                                            </div>
                                        </div>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">C</span>
                                            <input type="text" name="options[]" class="form-control" placeholder="Option C">
                                            <div class="input-group-text">
                                                <input type="radio" name="correct_answer" value="2">
                                            </div>
                                        </div>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">D</span>
                                            <input type="text" name="options[]" class="form-control" placeholder="Option D">
                                            <div class="input-group-text">
                                                <input type="radio" name="correct_answer" value="3">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- True/False Options -->
                                <div id="trueFalseContainer" class="mt-3" style="display: none;">
                                    <label class="form-label">Correct Answer *</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="correct_answer" value="true" id="trueOption">
                                        <label class="form-check-label" for="trueOption">True</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="correct_answer" value="false" id="falseOption">
                                        <label class="form-check-label" for="falseOption">False</label>
                                    </div>
                                </div>

                                <!-- Text Answer -->
                                <div id="textAnswerContainer" class="mt-3" style="display: none;">
                                    <label class="form-label">Expected Answer *</label>
                                    <input type="text" name="correct_answer" class="form-control" placeholder="Expected answer">
                                    <div class="form-text">For text questions, answers will be matched exactly (case-insensitive).</div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Question
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Existing Questions -->
                        <h6>Questions (<?= count($assessmentQuestions) ?>)</h6>
                        <?php if (!empty($assessmentQuestions)): ?>
                            <?php foreach ($assessmentQuestions as $index => $question): ?>
                                <div class="question-card">
                                    <div class="question-header">
                                        Question <?= $index + 1 ?> 
                                        <span class="float-end">
                                            <?= $question['marks'] ?> marks
                                            <button class="btn btn-sm btn-outline-light ms-2" onclick="deleteQuestion(<?= $question['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="question-content">
                                        <p><strong>Type:</strong> <?= ucfirst(str_replace('_', ' ', $question['type'])) ?></p>
                                        <p><strong>Question:</strong> <?= nl2br(htmlspecialchars($question['question'])) ?></p>
                                        
                                        <?php if ($question['type'] === 'multiple_choice'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <p><strong>Options:</strong></p>
                                            <ul>
                                                <?php foreach ($options as $key => $option): ?>
                                                    <li>
                                                        <?= chr(65 + $key) ?>. <?= htmlspecialchars($option) ?>
                                                        <?php if ($question['correct_answer'] == $key): ?>
                                                            <span class="correct-answer">Correct</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif ($question['type'] === 'true_false'): ?>
                                            <p><strong>Correct Answer:</strong> 
                                                <span class="correct-answer"><?= ucfirst($question['correct_answer']) ?></span>
                                            </p>
                                        <?php elseif ($question['type'] === 'text'): ?>
                                            <p><strong>Expected Answer:</strong> 
                                                <span class="correct-answer"><?= htmlspecialchars($question['correct_answer']) ?></span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-question-circle fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No questions added yet. Add your first question above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- New Assessment Modal -->
    <div class="modal fade" id="newAssessmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="assessmentForm">
                    <input type="hidden" name="action" value="create_assessment">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Assessment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="title" class="form-label">Assessment Title *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label for="course_id" class="form-label">Course *</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <label for="time_limit" class="form-label">Time Limit (minutes) *</label>
                                <input type="number" class="form-control" name="time_limit" value="60" required>
                            </div>
                            <div class="col-md-4">
                                <label for="total_marks" class="form-label">Total Marks *</label>
                                <input type="number" class="form-control" name="total_marks" value="100" required>
                            </div>
                            <div class="col-md-4">
                                <label for="passing_marks" class="form-label">Passing Marks (%) *</label>
                                <input type="number" class="form-control" name="passing_marks" value="70" min="1" max="100" required>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="max_attempts" class="form-label">Max Attempts</label>
                                <input type="number" class="form-control" name="max_attempts" value="3" min="1">
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Assessment Modal -->
    <div class="modal fade" id="sendAssessmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="send_assessment_link">
                    <input type="hidden" name="assessment_id" id="selectedAssessmentId">
                    <div class="modal-header">
                        <h5 class="modal-title">Send Assessment Link</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="batch_id" class="form-label">Select Batch *</label>
                            <select name="batch_id" class="form-select" required>
                                <option value="">Choose Batch</option>
                                <?php
                                // Get active batches for assessment sending
                                if ($currentUser['role'] === 'admin') {
                                    $batchQuery = "SELECT b.*, c.name as course_name, COUNT(sb.id) as student_count 
                                                  FROM batches b 
                                                  LEFT JOIN courses c ON b.course_id = c.id 
                                                  LEFT JOIN student_batches sb ON b.id = sb.batch_id AND sb.status = 'active'
                                                  WHERE b.status IN ('ongoing', 'upcoming') 
                                                  GROUP BY b.id ORDER BY b.start_date";
                                    $stmt = $db->prepare($batchQuery);
                                    $stmt->execute();
                                } else {
                                    $batchQuery = "SELECT b.*, c.name as course_name, COUNT(sb.id) as student_count 
                                                  FROM batches b 
                                                  LEFT JOIN courses c ON b.course_id = c.id 
                                                  LEFT JOIN student_batches sb ON b.id = sb.batch_id AND sb.status = 'active'
                                                  JOIN training_centers tc ON b.training_center_id = tc.id 
                                                  WHERE tc.user_id = ? AND b.status IN ('ongoing', 'upcoming') 
                                                  GROUP BY b.id ORDER BY b.start_date";
                                    $stmt = $db->prepare($batchQuery);
                                    $stmt->execute([$currentUser['id']]);
                                }
                                $sendBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($sendBatches as $batch): ?>
                                    <option value="<?= $batch['id'] ?>">
                                        <?= htmlspecialchars($batch['name'] . ' - ' . $batch['course_name']) ?> 
                                        (<?= $batch['student_count'] ?> students)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Assessment links will be sent to all active students in the selected batch via WhatsApp.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Send Links
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Question type switching
        document.getElementById('questionType').addEventListener('change', function() {
            const type = this.value;
            const optionsContainer = document.getElementById('optionsContainer');
            const trueFalseContainer = document.getElementById('trueFalseContainer');
            const textAnswerContainer = document.getElementById('textAnswerContainer');
            
            // Hide all containers first
            optionsContainer.style.display = 'none';
            trueFalseContainer.style.display = 'none';
            textAnswerContainer.style.display = 'none';
            
            // Clear required attributes
            document.querySelectorAll('#optionsContainer input[required]').forEach(input => input.removeAttribute('required'));
            document.querySelectorAll('#trueFalseContainer input[required]').forEach(input => input.removeAttribute('required'));
            document.querySelectorAll('#textAnswerContainer input[required]').forEach(input => input.removeAttribute('required'));
            
            // Show relevant container and set required attributes
            if (type === 'multiple_choice') {
                optionsContainer.style.display = 'block';
                document.querySelectorAll('#optionsList input[name="options[]"]:nth-child(-n+2)').forEach(input => input.setAttribute('required', 'required'));
                document.querySelector('input[name="correct_answer"][value="0"]').setAttribute('required', 'required');
            } else if (type === 'true_false') {
                trueFalseContainer.style.display = 'block';
                document.querySelector('#trueFalseContainer input[name="correct_answer"]').setAttribute('required', 'required');
            } else if (type === 'text') {
                textAnswerContainer.style.display = 'block';
                document.querySelector('#textAnswerContainer input[name="correct_answer"]').setAttribute('required', 'required');
            }
        });

        function sendAssessmentLink(assessmentId) {
            // Show modal to select batch
            const modal = new bootstrap.Modal(document.getElementById('sendAssessmentModal'));
            document.getElementById('selectedAssessmentId').value = assessmentId;
            modal.show();
        }

        function viewResults(assessmentId) {
            window.location.href = `assessment_results.php?id=${assessmentId}`;
        }

        function deleteQuestion(questionId) {
            if (confirm('Are you sure you want to delete this question?')) {
                const formData = new FormData();
                formData.append('action', 'delete_question');
                formData.append('id', questionId);
                
                fetch('assessments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the question.');
                });
            }
        }

        // Handle assessment form submission
        document.getElementById('assessmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('assessments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while creating the assessment.');
            });
        });

        // Dynamic option management for multiple choice questions
        function addOption() {
            const optionsList = document.getElementById('optionsList');
            const optionCount = optionsList.children.length;
            
            if (optionCount < 6) { // Maximum 6 options
                const optionLetter = String.fromCharCode(65 + optionCount);
                const optionHtml = `
                    <div class="input-group mb-2">
                        <span class="input-group-text">${optionLetter}</span>
                        <input type="text" name="options[]" class="form-control" placeholder="Option ${optionLetter}">
                        <div class="input-group-text">
                            <input type="radio" name="correct_answer" value="${optionCount}">
                        </div>
                        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                optionsList.insertAdjacentHTML('beforeend', optionHtml);
            }
        }

        function removeOption(button) {
            const optionGroup = button.closest('.input-group');
            const optionsList = document.getElementById('optionsList');
            
            if (optionsList.children.length > 2) { // Keep minimum 2 options
                optionGroup.remove();
                
                // Re-label remaining options
                Array.from(optionsList.children).forEach((option, index) => {
                    const letter = String.fromCharCode(65 + index);
                    option.querySelector('.input-group-text').textContent = letter;
                    option.querySelector('input[type="radio"]').value = index;
                    option.querySelector('input[type="text"]').placeholder = `Option ${letter}`;
                });
            }
        }

        // Add option button
        document.addEventListener('DOMContentLoaded', function() {
            const addOptionBtn = document.createElement('button');
            addOptionBtn.type = 'button';
            addOptionBtn.className = 'btn btn-sm btn-outline-primary mt-2';
            addOptionBtn.innerHTML = '<i class="fas fa-plus"></i> Add Option';
            addOptionBtn.onclick = addOption;
            
            document.getElementById('optionsList').parentNode.appendChild(addOptionBtn);
        });
    </script>
</body>
</html>

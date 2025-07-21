<?php
session_start();
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$userRole = getCurrentUserRole();

// Initialize database connection
$db = getConnection();

if (!$db) {
    die('Database connection failed');
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_assessments':
            try {
                $query = "
                    SELECT a.*, c.name as course_name, tc.name as training_center_name,
                           COUNT(ar.id) as total_attempts,
                           AVG(ar.score) as avg_score
                    FROM assessments a 
                    LEFT JOIN courses c ON a.course_id = c.id 
                    LEFT JOIN training_centers tc ON a.training_center_id = tc.id
                    LEFT JOIN assessment_results ar ON a.id = ar.assessment_id
                ";
                
                if ($userRole === 'training_partner') {
                    $query .= " WHERE a.training_center_id = :tc_id";
                }
                
                $query .= " GROUP BY a.id ORDER BY a.created_at DESC";
                
                $stmt = $db->prepare($query);
                
                if ($userRole === 'training_partner') {
                    $tc_id = $user['training_center_id'] ?? $user['id'];
                    $stmt->bindParam(':tc_id', $tc_id);
                }
                
                $stmt->execute();
                $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $assessments]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_assessment':
            try {
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("SELECT * FROM assessments WHERE id = ?");
                $stmt->execute([$id]);
                $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($assessment) {
                    // Get questions
                    $stmt = $db->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY question_order");
                    $stmt->execute([$id]);
                    $assessment['questions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                echo json_encode(['success' => true, 'data' => $assessment]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_courses':
            try {
                $stmt = $db->prepare("SELECT id, name FROM courses ORDER BY name ASC");
                $stmt->execute();
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $courses]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_training_centers':
            try {
                $stmt = $db->prepare("SELECT id, name FROM training_centers ORDER BY name ASC");
                $stmt->execute();
                $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $centers]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_results':
            try {
                $assessment_id = $_GET['assessment_id'] ?? 0;
                $query = "
                    SELECT ar.*, s.name as student_name, s.enrollment_number
                    FROM assessment_results ar
                    LEFT JOIN students s ON ar.student_id = s.id
                    WHERE ar.assessment_id = ?
                    ORDER BY ar.score DESC, ar.submitted_at ASC
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$assessment_id]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $results]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_assessment':
            try {
                $db->beginTransaction();
                
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $course_id = $_POST['course_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;
                $duration = $_POST['duration'] ?? 60;
                $passing_score = $_POST['passing_score'] ?? 70;
                $max_attempts = $_POST['max_attempts'] ?? 3;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($title) || empty($course_id)) {
                    throw new Exception('Title and course are required');
                }
                
                // Set training center for training partners
                if ($userRole === 'training_partner') {
                    $training_center_id = $user['training_center_id'] ?? $user['id'];
                }
                
                $stmt = $db->prepare("
                    INSERT INTO assessments (title, description, course_id, training_center_id, 
                                           duration, passing_score, max_attempts, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $course_id, $training_center_id, 
                               $duration, $passing_score, $max_attempts, $is_active]);
                
                $assessment_id = $db->lastInsertId();
                
                // Add questions
                $questions = json_decode($_POST['questions'], true);
                if ($questions) {
                    foreach ($questions as $index => $question) {
                        $stmt = $db->prepare("
                            INSERT INTO assessment_questions (assessment_id, question_text, question_type, 
                                                             options, correct_answer, marks, question_order) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $assessment_id,
                            $question['text'],
                            $question['type'],
                            json_encode($question['options'] ?? []),
                            $question['correct_answer'],
                            $question['marks'] ?? 1,
                            $index + 1
                        ]);
                    }
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Assessment created successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'update_assessment':
            try {
                $db->beginTransaction();
                
                $id = $_POST['id'] ?? 0;
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $course_id = $_POST['course_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;
                $duration = $_POST['duration'] ?? 60;
                $passing_score = $_POST['passing_score'] ?? 70;
                $max_attempts = $_POST['max_attempts'] ?? 3;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($title) || empty($course_id)) {
                    throw new Exception('Title and course are required');
                }
                
                // Set training center for training partners
                if ($userRole === 'training_partner') {
                    $training_center_id = $user['training_center_id'] ?? $user['id'];
                }
                
                $stmt = $db->prepare("
                    UPDATE assessments 
                    SET title = ?, description = ?, course_id = ?, training_center_id = ?, 
                        duration = ?, passing_score = ?, max_attempts = ?, is_active = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $course_id, $training_center_id, 
                               $duration, $passing_score, $max_attempts, $is_active, $id]);
                
                // Delete existing questions and add new ones
                $stmt = $db->prepare("DELETE FROM assessment_questions WHERE assessment_id = ?");
                $stmt->execute([$id]);
                
                $questions = json_decode($_POST['questions'], true);
                if ($questions) {
                    foreach ($questions as $index => $question) {
                        $stmt = $db->prepare("
                            INSERT INTO assessment_questions (assessment_id, question_text, question_type, 
                                                             options, correct_answer, marks, question_order) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $id,
                            $question['text'],
                            $question['type'],
                            json_encode($question['options'] ?? []),
                            $question['correct_answer'],
                            $question['marks'] ?? 1,
                            $index + 1
                        ]);
                    }
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Assessment updated successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'delete_assessment':
            try {
                $id = $_POST['id'] ?? 0;
                
                // Check if assessment has results
                $stmt = $db->prepare("SELECT COUNT(*) FROM assessment_results WHERE assessment_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete assessment. It has submitted results.');
                }
                
                $db->beginTransaction();
                
                // Delete questions first
                $stmt = $db->prepare("DELETE FROM assessment_questions WHERE assessment_id = ?");
                $stmt->execute([$id]);
                
                // Delete assessment
                $stmt = $db->prepare("DELETE FROM assessments WHERE id = ?");
                $stmt->execute([$id]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Assessment deleted successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'toggle_status':
            try {
                $id = $_POST['id'] ?? 0;
                $status = $_POST['status'] ?? 0;
                
                $stmt = $db->prepare("UPDATE assessments SET is_active = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                
                $message = $status ? 'Assessment activated' : 'Assessment deactivated';
                echo json_encode(['success' => true, 'message' => $message]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
try {
    $stats_query = "SELECT COUNT(*) FROM assessments";
    if ($userRole === 'training_partner') {
        $tc_id = $user['training_center_id'] ?? $user['id'];
        $stats_query .= " WHERE training_center_id = $tc_id";
    }
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute();
    $totalAssessments = $stmt->fetchColumn();
    
    $stats_query = "SELECT COUNT(*) FROM assessments WHERE is_active = 1";
    if ($userRole === 'training_partner') {
        $stats_query .= " AND training_center_id = $tc_id";
    }
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute();
    $activeAssessments = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM assessment_results");
    $stmt->execute();
    $totalAttempts = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT AVG(score) FROM assessment_results");
    $stmt->execute();
    $avgScore = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    $totalAssessments = $activeAssessments = $totalAttempts = $avgScore = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessments - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .navbar-brand img {
            height: 40px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: none;
        }
        
        .stats-card .card-title {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .stats-card .card-text {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stats-card .card-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0 !important;
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 500;
        }
        
        .modal .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
        }
        
        .question-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }
        
        .question-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .question-number {
            background: #667eea;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .option-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 0.5rem;
            margin: 0.25rem 0;
        }
        
        .option-item.correct {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .badge {
            border-radius: 10px;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .question-builder {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                Student Management System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    
                    <?php if ($userRole === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="training-centers.php">
                            <i class="fas fa-building me-1"></i>Training Centers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="masters.php">
                            <i class="fas fa-cogs me-1"></i>Masters
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($userRole !== 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php">
                            <i class="fas fa-users me-1"></i>Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="batches.php">
                            <i class="fas fa-layer-group me-1"></i>Batches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="fees.php">
                            <i class="fas fa-money-bill me-1"></i>Fees
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="assessments.php">
                            <i class="fas fa-clipboard-list me-1"></i>Assessments
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(getCurrentUserName()); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-clipboard-list me-2 text-primary"></i>Assessments Management</h2>
                        <p class="text-muted mb-0">Create and manage online assessments for courses</p>
                    </div>
                    <?php if ($userRole !== 'student'): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assessmentModal">
                        <i class="fas fa-plus me-2"></i>Create Assessment
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Total Assessments</h6>
                        <p class="card-text"><?php echo number_format($totalAssessments); ?></p>
                        <i class="fas fa-clipboard-list card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Active Assessments</h6>
                        <p class="card-text"><?php echo number_format($activeAssessments); ?></p>
                        <i class="fas fa-check-circle card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Total Attempts</h6>
                        <p class="card-text"><?php echo number_format($totalAttempts); ?></p>
                        <i class="fas fa-users card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Average Score</h6>
                        <p class="card-text"><?php echo number_format($avgScore, 1); ?>%</p>
                        <i class="fas fa-chart-line card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Alert -->
        <div class="alert alert-success" role="alert" style="display: none;" id="successAlert">
            <i class="fas fa-check-circle me-2"></i>
            <span id="successMessage"></span>
        </div>

        <!-- Error Alert -->
        <div class="alert alert-danger" role="alert" style="display: none;" id="errorAlert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <span id="errorMessage"></span>
        </div>

        <!-- Assessments Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Assessments List
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Create comprehensive online assessments with multiple question types including multiple choice, true/false, and text answers. Set duration limits, passing scores, and attempt restrictions for effective evaluation.</p>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Assessment Title</th>
                                        <th>Course</th>
                                        <th>Duration</th>
                                        <th>Pass Score</th>
                                        <th>Status</th>
                                        <?php if ($userRole !== 'student'): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="<?php echo $userRole !== 'student' ? '6' : '5'; ?>" class="text-center py-4">
                                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                            <p class="text-muted mb-0">No assessments created yet.</p>
                                            <?php if ($userRole !== 'student'): ?>
                                            <p class="text-muted">Click "Create Assessment" to add your first assessment.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assessment Modal -->
    <?php if ($userRole !== 'student'): ?>
    <div class="modal fade" id="assessmentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>Create Assessment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="assessmentForm">
                    <div class="modal-body">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <label for="assessmentTitle" class="form-label">Assessment Title</label>
                                <input type="text" class="form-control" id="assessmentTitle" name="title" required>
                            </div>
                            <div class="col-md-4">
                                <label for="assessmentCourse" class="form-label">Course</label>
                                <select class="form-select" id="assessmentCourse" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <option value="1">Web Development</option>
                                    <option value="2">Data Analysis</option>
                                    <option value="3">Digital Marketing</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label for="assessmentDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="assessmentDescription" name="description" rows="3" placeholder="Enter assessment description and instructions for students..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Assessment Settings -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label for="assessmentDuration" class="form-label">Duration (Minutes)</label>
                                <input type="number" class="form-control" id="assessmentDuration" name="duration" value="60" min="1">
                            </div>
                            <div class="col-md-3">
                                <label for="assessmentPassingScore" class="form-label">Passing Score (%)</label>
                                <input type="number" class="form-control" id="assessmentPassingScore" name="passing_score" value="70" min="0" max="100">
                            </div>
                            <div class="col-md-3">
                                <label for="assessmentMaxAttempts" class="form-label">Max Attempts</label>
                                <input type="number" class="form-control" id="assessmentMaxAttempts" name="max_attempts" value="3" min="1">
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="assessmentIsActive" name="is_active" checked>
                                    <label class="form-check-label" for="assessmentIsActive">
                                        Active Assessment
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sample Questions -->
                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Sample Assessment Structure</h6>
                                <span class="badge bg-info">Preview Only</span>
                            </div>
                            
                            <div class="question-item">
                                <div class="question-header">
                                    <div class="d-flex align-items-center">
                                        <div class="question-number">1</div>
                                        <div class="ms-3 flex-grow-1">
                                            <strong>Multiple Choice Question</strong>
                                            <small class="text-muted d-block">Question Type: Multiple Choice | Marks: 2</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="mb-2">What is the primary purpose of HTML in web development?</p>
                                </div>
                                
                                <div class="options-container">
                                    <div class="option-item correct">
                                        <i class="fas fa-circle me-2 text-success"></i>
                                        To structure and organize content on web pages
                                    </div>
                                    <div class="option-item">
                                        <i class="far fa-circle me-2"></i>
                                        To style and format web pages
                                    </div>
                                    <div class="option-item">
                                        <i class="far fa-circle me-2"></i>
                                        To add interactive functionality to web pages
                                    </div>
                                    <div class="option-item">
                                        <i class="far fa-circle me-2"></i>
                                        To store data in databases
                                    </div>
                                </div>
                            </div>
                            
                            <div class="question-item">
                                <div class="question-header">
                                    <div class="d-flex align-items-center">
                                        <div class="question-number">2</div>
                                        <div class="ms-3 flex-grow-1">
                                            <strong>True/False Question</strong>
                                            <small class="text-muted d-block">Question Type: True/False | Marks: 1</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="mb-2">CSS stands for Cascading Style Sheets.</p>
                                </div>
                                
                                <div class="options-container">
                                    <div class="option-item correct">
                                        <i class="fas fa-circle me-2 text-success"></i>
                                        True
                                    </div>
                                    <div class="option-item">
                                        <i class="far fa-circle me-2"></i>
                                        False
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                This is a preview of assessment structure. In the full system, you would be able to add, edit, and manage questions dynamically with a question builder interface.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Assessment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Handle form submission
            $('#assessmentForm').on('submit', function(e) {
                e.preventDefault();
                
                // Show success message
                showAlert('Assessment created successfully! In the full system, this would save to the database with all questions and settings.', 'success');
                
                // Close modal
                $('#assessmentModal').modal('hide');
                
                // Reset form
                this.reset();
            });
        });

        function showAlert(message, type) {
            const alertElement = type === 'success' ? $('#successAlert') : $('#errorAlert');
            const messageElement = type === 'success' ? $('#successMessage') : $('#errorMessage');
            
            messageElement.text(message);
            alertElement.show();
            
            // Hide alert after 5 seconds
            setTimeout(function() {
                alertElement.hide();
            }, 5000);
            
            // Scroll to top to show alert
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        }
    </script>
</body>
</html>

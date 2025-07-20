<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$token = $_GET['token'] ?? '';
$successMessage = '';
$errorMessage = '';

// Validate token and get assessment details
$stmt = $db->prepare("
    SELECT sa.*, s.name as student_name, s.enrollment_number, 
           a.title, a.description, a.time_limit, a.total_marks, a.passing_marks,
           a.max_attempts, c.name as course_name
    FROM student_assessments sa 
    JOIN students s ON sa.student_id = s.id 
    JOIN assessments a ON sa.assessment_id = a.id 
    JOIN courses c ON a.course_id = c.id 
    WHERE sa.token = ? AND a.status = 'active'
");
$stmt->execute([$token]);
$studentAssessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$studentAssessment) {
    die('<div class="alert alert-danger">Invalid or expired assessment link.</div>');
}

// Check if assessment is already completed or expired
if ($studentAssessment['status'] === 'completed') {
    $errorMessage = 'You have already completed this assessment.';
} elseif ($studentAssessment['attempts'] >= $studentAssessment['max_attempts']) {
    $errorMessage = 'You have exceeded the maximum number of attempts for this assessment.';
}

// Get questions for this assessment
$stmt = $db->prepare("
    SELECT * FROM assessment_questions 
    WHERE assessment_id = ? 
    ORDER BY RAND()
");
$stmt->execute([$studentAssessment['assessment_id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errorMessage) {
    $answers = $_POST['answers'] ?? [];
    $timeSpent = intval($_POST['time_spent'] ?? 0);
    
    try {
        $db->beginTransaction();
        
        // Calculate score
        $totalScore = 0;
        $correctAnswers = 0;
        $questionResults = [];
        
        foreach ($questions as $question) {
            $studentAnswer = $answers[$question['id']] ?? '';
            $isCorrect = false;
            
            if ($question['type'] === 'multiple_choice') {
                $isCorrect = $studentAnswer === $question['correct_answer'];
            } elseif ($question['type'] === 'true_false') {
                $isCorrect = $studentAnswer === $question['correct_answer'];
            } elseif ($question['type'] === 'text') {
                // For text questions, simple string comparison (can be enhanced)
                $isCorrect = trim(strtolower($studentAnswer)) === trim(strtolower($question['correct_answer']));
            }
            
            if ($isCorrect) {
                $totalScore += $question['marks'];
                $correctAnswers++;
            }
            
            $questionResults[] = [
                'question_id' => $question['id'],
                'student_answer' => $studentAnswer,
                'correct_answer' => $question['correct_answer'],
                'is_correct' => $isCorrect,
                'marks_obtained' => $isCorrect ? $question['marks'] : 0
            ];
        }
        
        $percentage = ($totalScore / $studentAssessment['total_marks']) * 100;
        $status = $percentage >= $studentAssessment['passing_marks'] ? 'passed' : 'failed';
        
        // Update student assessment
        $stmt = $db->prepare("
            UPDATE student_assessments 
            SET status = 'completed', score = ?, percentage = ?, result_status = ?, 
                time_spent = ?, completed_at = NOW(), attempts = attempts + 1 
            WHERE id = ?
        ");
        $stmt->execute([$totalScore, $percentage, $status, $timeSpent, $studentAssessment['id']]);
        
        // Save detailed results
        $stmt = $db->prepare("
            INSERT INTO assessment_results 
            (student_assessment_id, question_id, student_answer, correct_answer, is_correct, marks_obtained) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($questionResults as $result) {
            $stmt->execute([
                $studentAssessment['id'],
                $result['question_id'],
                $result['student_answer'],
                $result['correct_answer'],
                $result['is_correct'] ? 1 : 0,
                $result['marks_obtained']
            ]);
        }
        
        $db->commit();
        
        // Redirect to results page
        header("Location: assessment_result.php?token=" . $token);
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $errorMessage = 'Error submitting assessment: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment - <?= htmlspecialchars($studentAssessment['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .assessment-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 10px 15px;
            font-weight: bold;
            color: #dc3545;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .question-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .question-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        .question-text {
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .option-item {
            margin-bottom: 10px;
        }
        .option-item label {
            display: block;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .option-item label:hover {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        .option-item input[type="radio"]:checked + label,
        .option-item input[type="checkbox"]:checked + label {
            background: #e3f2fd;
            border-color: #2196f3;
            color: #1976d2;
        }
        .assessment-header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .submit-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-top: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .progress-bar-custom {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="timer" id="timer">
        <i class="fas fa-clock"></i> <span id="timeDisplay"><?= $studentAssessment['time_limit'] ?>:00</span>
    </div>

    <div class="assessment-container">
        <div class="assessment-header">
            <h1 class="mb-3"><?= htmlspecialchars($studentAssessment['title']) ?></h1>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Student:</strong> <?= htmlspecialchars($studentAssessment['student_name']) ?></p>
                    <p><strong>Enrollment:</strong> <?= htmlspecialchars($studentAssessment['enrollment_number']) ?></p>
                    <p><strong>Course:</strong> <?= htmlspecialchars($studentAssessment['course_name']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Time Limit:</strong> <?= $studentAssessment['time_limit'] ?> minutes</p>
                    <p><strong>Total Marks:</strong> <?= $studentAssessment['total_marks'] ?></p>
                    <p><strong>Passing Marks:</strong> <?= $studentAssessment['passing_marks'] ?>%</p>
                </div>
            </div>
            <?php if ($studentAssessment['description']): ?>
                <div class="alert alert-info">
                    <?= nl2br(htmlspecialchars($studentAssessment['description'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php else: ?>
            <form method="POST" id="assessmentForm">
                <input type="hidden" name="time_spent" id="timeSpent" value="0">
                
                <div class="progress-bar-custom">
                    <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                </div>
                
                <div id="questionsContainer">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-card" data-question="<?= $index + 1 ?>">
                            <div class="d-flex align-items-start">
                                <div class="question-number"><?= $index + 1 ?></div>
                                <div class="flex-grow-1">
                                    <div class="question-text">
                                        <?= nl2br(htmlspecialchars($question['question'])) ?>
                                        <span class="badge bg-primary ms-2"><?= $question['marks'] ?> marks</span>
                                    </div>
                                    
                                    <?php if ($question['type'] === 'multiple_choice'): ?>
                                        <?php 
                                        $options = json_decode($question['options'], true);
                                        foreach ($options as $key => $option): 
                                        ?>
                                            <div class="option-item">
                                                <input type="radio" name="answers[<?= $question['id'] ?>]" 
                                                       value="<?= $key ?>" id="q<?= $question['id'] ?>_<?= $key ?>" 
                                                       style="display: none;">
                                                <label for="q<?= $question['id'] ?>_<?= $key ?>">
                                                    <?= chr(65 + $key) ?>. <?= htmlspecialchars($option) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                    <?php elseif ($question['type'] === 'true_false'): ?>
                                        <div class="option-item">
                                            <input type="radio" name="answers[<?= $question['id'] ?>]" 
                                                   value="true" id="q<?= $question['id'] ?>_true" style="display: none;">
                                            <label for="q<?= $question['id'] ?>_true">True</label>
                                        </div>
                                        <div class="option-item">
                                            <input type="radio" name="answers[<?= $question['id'] ?>]" 
                                                   value="false" id="q<?= $question['id'] ?>_false" style="display: none;">
                                            <label for="q<?= $question['id'] ?>_false">False</label>
                                        </div>
                                        
                                    <?php elseif ($question['type'] === 'text'): ?>
                                        <textarea name="answers[<?= $question['id'] ?>]" 
                                                  class="form-control" rows="4" 
                                                  placeholder="Enter your answer here..."></textarea>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="submit-section">
                    <h5 class="mb-3">Assessment Complete</h5>
                    <p class="text-muted mb-4">Please review your answers before submitting. Once submitted, you cannot make changes.</p>
                    <button type="button" class="btn btn-outline-primary me-3" onclick="reviewAnswers()">
                        <i class="fas fa-eye"></i> Review Answers
                    </button>
                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirmSubmit()">
                        <i class="fas fa-paper-plane"></i> Submit Assessment
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Timer functionality
        let timeLimit = <?= $studentAssessment['time_limit'] * 60 ?>; // Convert to seconds
        let timeSpent = 0;
        let timerInterval;

        function startTimer() {
            timerInterval = setInterval(function() {
                timeSpent++;
                timeLimit--;
                
                if (timeLimit <= 0) {
                    clearInterval(timerInterval);
                    alert('Time is up! Assessment will be auto-submitted.');
                    document.getElementById('assessmentForm').submit();
                    return;
                }
                
                updateTimerDisplay();
                updateProgress();
                document.getElementById('timeSpent').value = timeSpent;
            }, 1000);
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timeLimit / 60);
            const seconds = timeLimit % 60;
            document.getElementById('timeDisplay').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Change color when time is running low
            const timer = document.getElementById('timer');
            if (timeLimit < 300) { // Last 5 minutes
                timer.style.borderColor = '#dc3545';
                timer.style.color = '#dc3545';
            } else if (timeLimit < 600) { // Last 10 minutes
                timer.style.borderColor = '#ffc107';
                timer.style.color = '#ffc107';
            }
        }

        function updateProgress() {
            const totalQuestions = <?= count($questions) ?>;
            const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked, textarea:not(:empty)').length;
            const progress = (answeredQuestions / totalQuestions) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
        }

        function confirmSubmit() {
            const totalQuestions = <?= count($questions) ?>;
            const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked, textarea[name^="answers"]:not([value=""])').length;
            
            if (answeredQuestions < totalQuestions) {
                const unanswered = totalQuestions - answeredQuestions;
                return confirm(`You have ${unanswered} unanswered questions. Are you sure you want to submit?`);
            }
            
            return confirm('Are you sure you want to submit your assessment? This action cannot be undone.');
        }

        function reviewAnswers() {
            const unansweredQuestions = [];
            const questions = document.querySelectorAll('.question-card');
            
            questions.forEach((question, index) => {
                const questionNum = index + 1;
                const radios = question.querySelectorAll('input[type="radio"]');
                const textarea = question.querySelector('textarea');
                
                let answered = false;
                if (radios.length > 0) {
                    answered = Array.from(radios).some(radio => radio.checked);
                } else if (textarea) {
                    answered = textarea.value.trim() !== '';
                }
                
                if (!answered) {
                    unansweredQuestions.push(questionNum);
                }
            });
            
            if (unansweredQuestions.length > 0) {
                alert(`Unanswered questions: ${unansweredQuestions.join(', ')}`);
                // Scroll to first unanswered question
                const firstUnanswered = document.querySelector(`[data-question="${unansweredQuestions[0]}"]`);
                firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                alert('All questions have been answered!');
            }
        }

        // Initialize timer when page loads
        window.addEventListener('load', function() {
            startTimer();
            
            // Update progress when answers change
            document.addEventListener('change', updateProgress);
            document.addEventListener('input', updateProgress);
        });

        // Prevent back button and refresh
        window.addEventListener('beforeunload', function(e) {
            if (timeSpent > 0) {
                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave? Your progress will be lost.';
            }
        });

        // Auto-save answers periodically (optional feature)
        setInterval(function() {
            // Could implement auto-save functionality here
        }, 30000); // Every 30 seconds
    </script>
</body>
</html>

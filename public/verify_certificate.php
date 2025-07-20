<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$certificate = null;
$errorMessage = '';
$searchQuery = $_GET['q'] ?? '';

if ($searchQuery) {
    try {
        // Search by certificate number or enrollment number
        $stmt = $db->prepare("
            SELECT c.*, s.name as student_name, s.enrollment_number,
                   co.name as course_name, co.duration_months,
                   r.marks_obtained, r.total_marks, r.completed_at,
                   a.name as assessment_name, a.passing_marks
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            JOIN results r ON c.result_id = r.id
            JOIN courses co ON s.course_id = co.id
            JOIN assessments a ON r.assessment_id = a.id
            WHERE c.certificate_number = ? OR s.enrollment_number = ?
            AND c.status = 'generated'
        ");
        $stmt->execute([$searchQuery, $searchQuery]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$certificate) {
            $errorMessage = 'Certificate not found. Please check the certificate number or enrollment number.';
        }
    } catch (Exception $e) {
        $errorMessage = 'Error searching for certificate. Please try again.';
    }
}

// Calculate grade if certificate found
$grade = '';
$percentage = 0;
if ($certificate) {
    $percentage = ($certificate['marks_obtained'] / $certificate['total_marks']) * 100;
    $grade = 'A+';
    if ($percentage < 95) $grade = 'A';
    if ($percentage < 85) $grade = 'B+';
    if ($percentage < 75) $grade = 'B';
    if ($percentage < 65) $grade = 'C';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verification-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .search-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 40px;
            margin-bottom: 30px;
        }
        .certificate-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .certificate-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .certificate-body {
            padding: 40px;
        }
        .student-name {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3a8a;
            text-align: center;
            margin: 20px 0;
            border-bottom: 3px solid #1e3a8a;
            display: inline-block;
            padding-bottom: 10px;
        }
        .grade-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            margin: 20px auto;
        }
        .verification-badge {
            background: #10b981;
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }
        .download-btn {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }
        .download-btn:hover {
            transform: translateY(-2px);
            color: white;
        }
        .search-input {
            border-radius: 50px;
            padding: 15px 25px;
            font-size: 1.1rem;
            border: 2px solid #e5e7eb;
        }
        .search-btn {
            border-radius: 50px;
            padding: 15px 30px;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .logo {
            font-size: 2.5rem;
            color: #1e3a8a;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container verification-container">
        <!-- Search Section -->
        <div class="search-card text-center">
            <div class="logo">
                <i class="fas fa-certificate"></i>
            </div>
            <h1 class="h2 mb-4">Certificate Verification Portal</h1>
            <p class="text-muted mb-4">Enter certificate number or enrollment number to verify authenticity</p>
            
            <form method="GET" class="row g-3 justify-content-center">
                <div class="col-md-8">
                    <input type="text" 
                           class="form-control search-input" 
                           name="q" 
                           value="<?= htmlspecialchars($searchQuery) ?>"
                           placeholder="Enter Certificate Number or Enrollment Number"
                           required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn search-btn w-100">
                        <i class="fas fa-search me-2"></i>Verify Certificate
                    </button>
                </div>
            </form>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger mt-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Certificate Display -->
        <?php if ($certificate): ?>
            <div class="certificate-card">
                <div class="certificate-header">
                    <div class="verification-badge mb-3">
                        <i class="fas fa-shield-check"></i>
                        Verified Certificate
                    </div>
                    <h2>CERTIFICATE OF COMPLETION</h2>
                    <p class="mb-0">This certificate has been verified and is authentic</p>
                </div>
                
                <div class="certificate-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="text-center mb-4">
                                <div class="student-name"><?= htmlspecialchars($certificate['student_name']) ?></div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Enrollment Number</h6>
                                    <p class="h5"><?= htmlspecialchars($certificate['enrollment_number']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Certificate Number</h6>
                                    <p class="h5"><?= htmlspecialchars($certificate['certificate_number']) ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Course</h6>
                                    <p class="h5"><?= htmlspecialchars($certificate['course_name']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Duration</h6>
                                    <p class="h5"><?= $certificate['duration_months'] ?> months</p>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Assessment</h6>
                                    <p class="h5"><?= htmlspecialchars($certificate['assessment_name']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Completion Date</h6>
                                    <p class="h5"><?= date('F d, Y', strtotime($certificate['completed_at'])) ?></p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Marks Obtained</h6>
                                    <p class="h5"><?= $certificate['marks_obtained'] ?> / <?= $certificate['total_marks'] ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Percentage</h6>
                                    <p class="h5"><?= number_format($percentage, 2) ?>%</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 text-center">
                            <div class="grade-circle bg-<?= $percentage >= $certificate['passing_marks'] ? 'success' : 'danger' ?>">
                                <?= $grade ?>
                            </div>
                            
                            <h6 class="text-muted">Final Grade</h6>
                            
                            <?php if ($certificate['certificate_path'] && file_exists($certificate['certificate_path'])): ?>
                                <div class="mt-4">
                                    <a href="<?= str_replace('../', '', $certificate['certificate_path']) ?>" 
                                       target="_blank" 
                                       class="btn download-btn">
                                        <i class="fas fa-download me-2"></i>Download Certificate
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($certificate['qr_code_path'] && file_exists($certificate['qr_code_path'])): ?>
                                <div class="mt-3">
                                    <img src="<?= str_replace('../', '', $certificate['qr_code_path']) ?>" 
                                         alt="QR Code" 
                                         class="img-fluid" 
                                         style="max-width: 120px;">
                                    <p class="small text-muted mt-2">Scan QR code for verification</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row text-center">
                        <div class="col-md-4">
                            <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                            <h6>Issued Date</h6>
                            <p><?= date('F d, Y', strtotime($certificate['issued_date'])) ?></p>
                        </div>
                        <div class="col-md-4">
                            <i class="fas fa-shield-check fa-2x text-success mb-2"></i>
                            <h6>Verification Status</h6>
                            <p class="text-success">Verified & Authentic</p>
                        </div>
                        <div class="col-md-4">
                            <i class="fas fa-award fa-2x text-warning mb-2"></i>
                            <h6>Credential Type</h6>
                            <p>Course Completion Certificate</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Information Section -->
        <div class="search-card mt-4">
            <div class="row text-center">
                <div class="col-md-4">
                    <i class="fas fa-search fa-2x text-primary mb-3"></i>
                    <h5>Easy Verification</h5>
                    <p class="text-muted">Simply enter the certificate number or enrollment number to instantly verify any certificate.</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-lock fa-2x text-success mb-3"></i>
                    <h5>Secure & Authentic</h5>
                    <p class="text-muted">All certificates are digitally signed and stored securely in our database.</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-download fa-2x text-warning mb-3"></i>
                    <h5>Download Option</h5>
                    <p class="text-muted">Verified certificates can be downloaded directly from this portal.</p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-light">
                <i class="fas fa-home me-2"></i>
                <a href="login.php" class="text-light">Return to Student Portal</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

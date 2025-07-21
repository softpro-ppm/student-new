<?php
/**
 * Bulk Upload Students Module
 * Features: Excel/CSV processing, validation, batch enrollment, import logging
 */
session_start();
require_once '../includes/auth.php';
require_once '../config/database-simple.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login-new.php');
    exit();
}

// Get user data
$user = getCurrentUser();
$userRole = getCurrentUserRole();

// Check permissions
if (!in_array($userRole, ['admin', 'training_partner'])) {
    header('Location: unauthorized.php');
    exit();
}

// Initialize database connection
$db = getConnection();
if (!$db) {
    die('Database connection failed');
}

$message = '';
$messageType = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'download_template':
                // Create Excel template
                $filename = 'student_upload_template.csv';
                $headers = [
                    'name', 'father_name', 'email', 'phone', 'aadhaar', 'dob', 
                    'gender', 'education', 'address', 'course_code', 'batch_name'
                ];
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filename);
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, $headers);
                
                // Add sample data
                fputcsv($output, [
                    'John Doe',
                    'Robert Doe', 
                    'john.doe@email.com',
                    '9876543210',
                    '123456789012',
                    '1995-05-15',
                    'Male',
                    'Graduation',
                    '123 Main Street, City',
                    'COURSE001',
                    'BATCH_JAN_2024'
                ]);
                
                fclose($output);
                exit();

            case 'validate_file':
                if (!isset($_FILES['bulk_file']) || $_FILES['bulk_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select a valid file to upload.');
                }

                $file = $_FILES['bulk_file'];
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
                    throw new Exception('Please upload a CSV or Excel file only.');
                }

                // Process file based on type
                $data = [];
                if ($fileExtension === 'csv') {
                    $data = processCsvFile($file['tmp_name']);
                } else {
                    throw new Exception('Excel file processing requires PhpSpreadsheet library. Please use CSV format.');
                }

                // Validate headers
                $requiredHeaders = ['name', 'father_name', 'email', 'phone', 'aadhaar', 'dob', 'gender', 'education', 'address'];
                $headers = array_keys($data[0] ?? []);
                $missingHeaders = array_diff($requiredHeaders, $headers);
                
                if (!empty($missingHeaders)) {
                    throw new Exception('Missing required columns: ' . implode(', ', $missingHeaders));
                }

                // Validate data
                $validationResults = validateBulkData($data, $db);
                
                // Store data in session for processing
                $_SESSION['bulk_upload_data'] = $data;
                $_SESSION['bulk_upload_validation'] = $validationResults;

                echo json_encode([
                    'success' => true,
                    'total_records' => count($data),
                    'valid_records' => $validationResults['valid_count'],
                    'invalid_records' => $validationResults['invalid_count'],
                    'errors' => $validationResults['errors'],
                    'preview' => array_slice($data, 0, 5) // First 5 records for preview
                ]);
                exit();

            case 'process_upload':
                if (!isset($_SESSION['bulk_upload_data']) || !isset($_SESSION['bulk_upload_validation'])) {
                    throw new Exception('No validated data found. Please upload and validate file first.');
                }

                $data = $_SESSION['bulk_upload_data'];
                $validation = $_SESSION['bulk_upload_validation'];
                $course_id = $_POST['course_id'] ?? 0;
                $batch_id = $_POST['batch_id'] ?? null;
                $training_center_id = $_POST['training_center_id'] ?? null;

                if (!$course_id) {
                    throw new Exception('Please select a course for bulk enrollment.');
                }

                // Set training center based on user role
                if ($userRole === 'training_partner') {
                    $training_center_id = $_SESSION['training_center_id'] ?? $user['id'];
                }

                $results = processBulkUpload($data, $validation, $course_id, $batch_id, $training_center_id, $db, $user['id']);

                // Log bulk upload
                $stmt = $db->prepare("
                    INSERT INTO bulk_uploads (
                        user_id, filename, total_records, successful_imports, 
                        failed_imports, course_id, batch_id, training_center_id, 
                        upload_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'completed')
                ");
                $stmt->execute([
                    $user['id'],
                    'bulk_upload_' . date('YmdHis'),
                    count($data),
                    $results['success_count'],
                    $results['error_count'],
                    $course_id,
                    $batch_id,
                    $training_center_id
                ]);

                // Clear session data
                unset($_SESSION['bulk_upload_data']);
                unset($_SESSION['bulk_upload_validation']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Bulk upload completed successfully!',
                    'results' => $results
                ]);
                exit();

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Helper function to process CSV file
function processCsvFile($filename) {
    $data = [];
    $handle = fopen($filename, 'r');
    $headers = fgetcsv($handle); // Get headers
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) === count($headers)) {
            $data[] = array_combine($headers, $row);
        }
    }
    
    fclose($handle);
    return $data;
}

// Helper function to validate bulk data
function validateBulkData($data, $db) {
    $errors = [];
    $validCount = 0;
    $invalidCount = 0;

    foreach ($data as $index => $row) {
        $rowErrors = [];
        $rowNumber = $index + 2; // +2 because CSV starts from row 2 (after header)

        // Validate required fields
        if (empty($row['name'])) $rowErrors[] = "Name is required";
        if (empty($row['father_name'])) $rowErrors[] = "Father's name is required";
        if (empty($row['email'])) $rowErrors[] = "Email is required";
        if (empty($row['phone'])) $rowErrors[] = "Phone is required";
        if (empty($row['aadhaar'])) $rowErrors[] = "Aadhaar is required";
        if (empty($row['dob'])) $rowErrors[] = "Date of birth is required";
        if (empty($row['gender'])) $rowErrors[] = "Gender is required";
        if (empty($row['education'])) $rowErrors[] = "Education is required";
        if (empty($row['address'])) $rowErrors[] = "Address is required";

        // Validate email format
        if (!empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $rowErrors[] = "Invalid email format";
        }

        // Validate phone number
        if (!empty($row['phone']) && !preg_match('/^[0-9]{10}$/', $row['phone'])) {
            $rowErrors[] = "Phone must be exactly 10 digits";
        }

        // Validate Aadhaar
        if (!empty($row['aadhaar']) && !preg_match('/^[0-9]{12}$/', $row['aadhaar'])) {
            $rowErrors[] = "Aadhaar must be exactly 12 digits";
        }

        // Check for duplicates in database
        if (!empty($row['email']) || !empty($row['phone']) || !empty($row['aadhaar'])) {
            $stmt = $db->prepare("SELECT id FROM students WHERE email = ? OR phone = ? OR aadhaar = ?");
            $stmt->execute([$row['email'], $row['phone'], $row['aadhaar']]);
            if ($stmt->fetch()) {
                $rowErrors[] = "Student with this email, phone, or Aadhaar already exists";
            }
        }

        // Validate date format
        if (!empty($row['dob'])) {
            $date = DateTime::createFromFormat('Y-m-d', $row['dob']);
            if (!$date || $date->format('Y-m-d') !== $row['dob']) {
                $rowErrors[] = "Invalid date format (use YYYY-MM-DD)";
            }
        }

        // Validate gender
        if (!empty($row['gender']) && !in_array($row['gender'], ['Male', 'Female', 'Other'])) {
            $rowErrors[] = "Gender must be Male, Female, or Other";
        }

        if (empty($rowErrors)) {
            $validCount++;
        } else {
            $invalidCount++;
            $errors[] = "Row $rowNumber: " . implode(', ', $rowErrors);
        }
    }

    return [
        'valid_count' => $validCount,
        'invalid_count' => $invalidCount,
        'errors' => $errors
    ];
}

// Helper function to process bulk upload
function processBulkUpload($data, $validation, $course_id, $batch_id, $training_center_id, $db, $user_id) {
    $successCount = 0;
    $errorCount = 0;
    $results = [];

    foreach ($data as $index => $row) {
        try {
            // Skip invalid rows
            if ($validation['invalid_count'] > 0) {
                // Check if this row has errors
                $rowNumber = $index + 2;
                $hasError = false;
                foreach ($validation['errors'] as $error) {
                    if (strpos($error, "Row $rowNumber:") === 0) {
                        $hasError = true;
                        break;
                    }
                }
                if ($hasError) {
                    $errorCount++;
                    continue;
                }
            }

            // Generate enrollment number
            $year = date('Y');
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE YEAR(created_at) = ?");
            $stmt->execute([$year]);
            $count = $stmt->fetchColumn() + 1;
            $enrollment_no = $year . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Check if enrollment number exists
            $stmt = $db->prepare("SELECT id FROM students WHERE enrollment_no = ?");
            $stmt->execute([$enrollment_no]);
            if ($stmt->fetch()) {
                $enrollment_no = $year . str_pad($count + rand(1, 100), 4, '0', STR_PAD_LEFT);
            }

            // Insert student
            $stmt = $db->prepare("
                INSERT INTO students (
                    enrollment_no, name, father_name, email, phone, aadhaar, 
                    dob, gender, education, address, course_id, batch_id, 
                    training_center_id, password, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");

            $defaultPassword = password_hash('student123', PASSWORD_DEFAULT);

            $stmt->execute([
                $enrollment_no,
                trim($row['name']),
                trim($row['father_name']),
                trim($row['email']),
                trim($row['phone']),
                trim($row['aadhaar']),
                $row['dob'],
                $row['gender'],
                $row['education'],
                trim($row['address']),
                $course_id,
                $batch_id,
                $training_center_id,
                $defaultPassword
            ]);

            $student_id = $db->lastInsertId();

            // Add registration fee
            $stmt = $db->prepare("
                INSERT INTO fees (student_id, amount, fee_type, due_date, status, created_at) 
                VALUES (?, 100, 'registration', DATE_ADD(NOW(), INTERVAL 30 DAY), 'pending', NOW())
            ");
            $stmt->execute([$student_id]);

            $successCount++;
            $results[] = [
                'status' => 'success',
                'name' => $row['name'],
                'enrollment_no' => $enrollment_no
            ];

        } catch (Exception $e) {
            $errorCount++;
            $results[] = [
                'status' => 'error',
                'name' => $row['name'] ?? 'Unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    return [
        'success_count' => $successCount,
        'error_count' => $errorCount,
        'details' => $results
    ];
}

// Fetch dropdown data
$sectors = [];
$courses = [];
$batches = [];
$trainingCenters = [];

// Get sectors
$stmt = $db->prepare("SELECT id, name FROM sectors WHERE status = 'active' ORDER BY name");
$stmt->execute();
$sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses
$stmt = $db->prepare("SELECT id, name, sector_id FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training centers (only for admin)
if ($userRole === 'admin') {
    $stmt = $db->prepare("SELECT id, name FROM training_centers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent bulk uploads
$recentUploads = [];
$stmt = $db->prepare("
    SELECT bu.*, c.name as course_name, tc.name as center_name 
    FROM bulk_uploads bu 
    LEFT JOIN courses c ON bu.course_id = c.id 
    LEFT JOIN training_centers tc ON bu.training_center_id = tc.id 
    WHERE bu.user_id = ? 
    ORDER BY bu.upload_date DESC 
    LIMIT 10
");
$stmt->execute([$user['id']]);
$recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the new layout
require_once '../includes/layout-new.php';
renderHeader('Bulk Upload Students - Student Management System');
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-upload"></i>
                    Bulk Upload Students
                </h1>
                <p class="page-subtitle">Import multiple students from Excel/CSV files</p>
            </div>
            <div>
                <a href="students-modern.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Students
                </a>
            </div>
        </div>
    </div>

    <!-- Upload Steps -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary-gradient text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list-ol me-2"></i>
                        Upload Process
                    </h5>
                </div>
                <div class="card-body">
                    <div class="steps-container">
                        <div class="step" id="step1">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h6>Download Template</h6>
                                <p>Download the Excel/CSV template with required format</p>
                            </div>
                        </div>
                        <div class="step" id="step2">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h6>Prepare Data</h6>
                                <p>Fill student information in the template</p>
                            </div>
                        </div>
                        <div class="step" id="step3">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h6>Upload & Validate</h6>
                                <p>Upload file and validate data</p>
                            </div>
                        </div>
                        <div class="step" id="step4">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h6>Import Students</h6>
                                <p>Review and import validated students</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-upload me-2"></i>
                        Upload Students File
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Step 1: Download Template -->
                    <div class="upload-step" id="downloadStep">
                        <h6 class="text-primary">Step 1: Download Template</h6>
                        <p>Download the template file and fill it with student data:</p>
                        <button type="button" class="btn btn-success" onclick="downloadTemplate()">
                            <i class="fas fa-download me-2"></i>Download CSV Template
                        </button>
                        <div class="mt-3">
                            <small class="text-muted">
                                <strong>Required columns:</strong> name, father_name, email, phone, aadhaar, dob, gender, education, address<br>
                                <strong>Optional columns:</strong> course_code, batch_name
                            </small>
                        </div>
                    </div>

                    <!-- Step 2: Upload File -->
                    <div class="upload-step mt-4" id="uploadStep">
                        <h6 class="text-primary">Step 2: Upload File</h6>
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="bulk_file" class="form-label">Select File</label>
                                    <input type="file" class="form-control" id="bulk_file" name="bulk_file" 
                                           accept=".csv,.xlsx,.xls" required>
                                    <div class="form-text">Accepted formats: CSV, Excel (.xlsx, .xls)</div>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary" onclick="validateFile()">
                                        <i class="fas fa-check me-2"></i>Validate File
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Step 3: Validation Results -->
                    <div class="upload-step mt-4" id="validationStep" style="display: none;">
                        <h6 class="text-primary">Step 3: Validation Results</h6>
                        <div id="validationResults"></div>
                    </div>

                    <!-- Step 4: Course Selection -->
                    <div class="upload-step mt-4" id="courseStep" style="display: none;">
                        <h6 class="text-primary">Step 4: Course Selection</h6>
                        <form id="importForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="import_sector_id" class="form-label">Sector <span class="text-danger">*</span></label>
                                    <select class="form-select" id="import_sector_id" name="sector_id" required>
                                        <option value="">Select Sector</option>
                                        <?php foreach ($sectors as $sector): ?>
                                        <option value="<?php echo $sector['id']; ?>">
                                            <?php echo htmlspecialchars($sector['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="import_course_id" class="form-label">Course <span class="text-danger">*</span></label>
                                    <select class="form-select" id="import_course_id" name="course_id" required>
                                        <option value="">Select Course</option>
                                    </select>
                                </div>
                                <?php if ($userRole === 'admin'): ?>
                                <div class="col-md-6">
                                    <label for="import_training_center_id" class="form-label">Training Center</label>
                                    <select class="form-select" id="import_training_center_id" name="training_center_id">
                                        <option value="">Select Training Center</option>
                                        <?php foreach ($trainingCenters as $center): ?>
                                        <option value="<?php echo $center['id']; ?>">
                                            <?php echo htmlspecialchars($center['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-6">
                                    <label for="import_batch_id" class="form-label">Batch (Optional)</label>
                                    <select class="form-select" id="import_batch_id" name="batch_id">
                                        <option value="">Select Batch</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-success" onclick="processUpload()">
                                        <i class="fas fa-upload me-2"></i>Import Students
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Uploads
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentUploads)): ?>
                        <p class="text-muted text-center">No recent uploads found.</p>
                    <?php else: ?>
                        <div class="upload-history">
                            <?php foreach ($recentUploads as $upload): ?>
                            <div class="upload-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($upload['filename']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($upload['upload_date'])); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-success">
                                        <?php echo $upload['status']; ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Total: <?php echo $upload['total_records']; ?> | 
                                        Success: <?php echo $upload['successful_imports']; ?> | 
                                        Failed: <?php echo $upload['failed_imports']; ?>
                                    </small>
                                </div>
                                <?php if ($upload['course_name']): ?>
                                <div class="mt-1">
                                    <small class="text-info">Course: <?php echo htmlspecialchars($upload['course_name']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Guidelines -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Upload Guidelines
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Phone numbers must be exactly 10 digits
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Aadhaar numbers must be exactly 12 digits
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Date format: YYYY-MM-DD
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Gender: Male, Female, or Other
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Email addresses must be valid
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            Maximum file size: 10MB
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Render Sidebar -->
<?php renderSidebar('students'); ?>

<style>
.steps-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    flex: 1;
    position: relative;
}

.step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 20px;
    right: -50%;
    width: 100%;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.step.active:not(:last-child)::after {
    background: var(--primary-color);
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 10px;
    position: relative;
    z-index: 2;
}

.step.active .step-number {
    background: var(--primary-color);
    color: white;
}

.step.completed .step-number {
    background: var(--success-color);
    color: white;
}

.step-content h6 {
    margin-bottom: 5px;
    font-size: 14px;
}

.step-content p {
    margin: 0;
    font-size: 12px;
    color: #6c757d;
}

.upload-history {
    max-height: 300px;
    overflow-y: auto;
}

.upload-item {
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #f8f9fa;
}

.upload-item:last-child {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .steps-container {
        flex-direction: column;
        gap: 20px;
    }
    
    .step:not(:last-child)::after {
        display: none;
    }
}
</style>

<script>
// Course loading functionality
$(document).ready(function() {
    // Import sector change handler
    $('#import_sector_id').on('change', function() {
        const sectorId = $(this).val();
        $('#import_course_id').html('<option value="">Select Course</option>');
        $('#import_batch_id').html('<option value="">Select Batch</option>');
        
        if (sectorId) {
            const courses = <?php echo json_encode($courses); ?>;
            const filteredCourses = courses.filter(course => course.sector_id == sectorId);
            
            filteredCourses.forEach(course => {
                $('#import_course_id').append(`<option value="${course.id}">${course.name}</option>`);
            });
        }
    });

    // Course change handler
    $('#import_course_id').on('change', function() {
        const courseId = $(this).val();
        const trainingCenterId = $('#import_training_center_id').val();
        $('#import_batch_id').html('<option value="">Select Batch</option>');
        
        if (courseId) {
            $.post('', {
                action: 'get_batches_by_course',
                course_id: courseId,
                training_center_id: trainingCenterId
            }, function(response) {
                if (response.success) {
                    response.batches.forEach(batch => {
                        $('#import_batch_id').append(`<option value="${batch.id}">${batch.name} (${batch.start_date} to ${batch.end_date})</option>`);
                    });
                }
            }, 'json');
        }
    });
});

// Download template
function downloadTemplate() {
    window.location.href = '?action=download_template';
    setStepActive(1);
}

// Validate file
function validateFile() {
    const fileInput = document.getElementById('bulk_file');
    if (!fileInput.files.length) {
        showToast('Please select a file to upload.', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'validate_file');
    formData.append('bulk_file', fileInput.files[0]);

    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayValidationResults(response);
                setStepActive(3);
                $('#validationStep').show();
                
                if (response.valid_records > 0) {
                    $('#courseStep').show();
                }
            } else {
                showToast(response.message, 'danger');
            }
        },
        error: function() {
            showToast('An error occurred while validating the file.', 'danger');
        }
    });
}

// Display validation results
function displayValidationResults(data) {
    let html = `
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle me-2"></i>Validation Summary</h6>
            <ul class="mb-0">
                <li>Total Records: ${data.total_records}</li>
                <li>Valid Records: <span class="text-success">${data.valid_records}</span></li>
                <li>Invalid Records: <span class="text-danger">${data.invalid_records}</span></li>
            </ul>
        </div>
    `;

    if (data.errors.length > 0) {
        html += `
            <div class="alert alert-danger">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Validation Errors</h6>
                <ul class="mb-0">
        `;
        data.errors.forEach(error => {
            html += `<li>${error}</li>`;
        });
        html += `</ul></div>`;
    }

    if (data.preview.length > 0) {
        html += `
            <div class="alert alert-success">
                <h6><i class="fas fa-eye me-2"></i>Data Preview (First 5 Records)</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Education</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        data.preview.forEach(row => {
            html += `
                <tr>
                    <td>${row.name || 'N/A'}</td>
                    <td>${row.email || 'N/A'}</td>
                    <td>${row.phone || 'N/A'}</td>
                    <td>${row.education || 'N/A'}</td>
                </tr>
            `;
        });
        html += `</tbody></table></div></div>`;
    }

    $('#validationResults').html(html);
}

// Process upload
function processUpload() {
    const courseId = $('#import_course_id').val();
    if (!courseId) {
        showToast('Please select a course for bulk enrollment.', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'process_upload');
    formData.append('course_id', courseId);
    formData.append('batch_id', $('#import_batch_id').val());
    formData.append('training_center_id', $('#import_training_center_id').val());

    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                setStepActive(4);
                displayImportResults(response.results);
                
                // Reset form after successful import
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                showToast(response.message, 'danger');
            }
        },
        error: function() {
            showToast('An error occurred during import.', 'danger');
        }
    });
}

// Display import results
function displayImportResults(results) {
    let html = `
        <div class="alert alert-success">
            <h6><i class="fas fa-check-circle me-2"></i>Import Results</h6>
            <ul class="mb-0">
                <li>Successfully Imported: <span class="text-success">${results.success_count}</span></li>
                <li>Failed: <span class="text-danger">${results.error_count}</span></li>
            </ul>
        </div>
    `;

    if (results.details && results.details.length > 0) {
        html += `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        results.details.forEach(item => {
            const statusBadge = item.status === 'success' ? 
                '<span class="badge bg-success">Success</span>' : 
                '<span class="badge bg-danger">Error</span>';
            
            const details = item.status === 'success' ? 
                `Enrollment: ${item.enrollment_no}` : 
                item.error;
            
            html += `
                <tr>
                    <td>${item.name}</td>
                    <td>${statusBadge}</td>
                    <td>${details}</td>
                </tr>
            `;
        });
        
        html += `</tbody></table></div>`;
    }

    $('#validationResults').html(html);
}

// Set step as active
function setStepActive(stepNumber) {
    $('.step').removeClass('active completed');
    
    for (let i = 1; i <= stepNumber; i++) {
        if (i < stepNumber) {
            $(`#step${i}`).addClass('completed');
        } else {
            $(`#step${i}`).addClass('active');
        }
    }
}

console.log('Bulk upload module loaded successfully');
</script>

<?php renderFooter(); ?>

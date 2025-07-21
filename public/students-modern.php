<?php
/**
 * Modern Students Management System
 * Features: Enhanced validation, auto-enrollment, document upload, photo cropping
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
$userName = getCurrentUserName();

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
            case 'add_student':
                // Validate required fields
                $name = trim($_POST['name'] ?? '');
                $father_name = trim($_POST['father_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $aadhaar = trim($_POST['aadhaar'] ?? '');
                $dob = $_POST['dob'] ?? '';
                $gender = $_POST['gender'] ?? '';
                $education = $_POST['education'] ?? '';
                $address = trim($_POST['address'] ?? '');
                $course_id = $_POST['course_id'] ?? 0;
                $batch_id = $_POST['batch_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;

                // Validation
                if (empty($name) || empty($father_name) || empty($email) || empty($phone) || 
                    empty($aadhaar) || empty($dob) || empty($gender) || empty($education) || 
                    empty($address) || empty($course_id)) {
                    throw new Exception('Please fill all required fields.');
                }

                // Phone validation (exactly 10 digits)
                if (!preg_match('/^[0-9]{10}$/', $phone)) {
                    throw new Exception('Phone number must be exactly 10 digits.');
                }

                // Email validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Please enter a valid email address.');
                }

                // Aadhaar validation (exactly 12 digits)
                if (!preg_match('/^[0-9]{12}$/', $aadhaar)) {
                    throw new Exception('Aadhaar number must be exactly 12 digits.');
                }

                // Check for duplicates
                $stmt = $db->prepare("SELECT id FROM students WHERE email = ? OR phone = ? OR aadhaar = ?");
                $stmt->execute([$email, $phone, $aadhaar]);
                if ($stmt->fetch()) {
                    throw new Exception('Student with this email, phone, or Aadhaar already exists.');
                }

                // Generate enrollment number
                $year = date('Y');
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE YEAR(created_at) = ?");
                $stmt->execute([$year]);
                $count = $stmt->fetchColumn() + 1;
                $enrollment_no = $year . str_pad($count, 4, '0', STR_PAD_LEFT);

                // Check if enrollment number exists (unlikely but safety check)
                $stmt = $db->prepare("SELECT id FROM students WHERE enrollment_no = ?");
                $stmt->execute([$enrollment_no]);
                if ($stmt->fetch()) {
                    $enrollment_no = $year . str_pad($count + rand(1, 100), 4, '0', STR_PAD_LEFT);
                }

                // Set training center based on user role
                if ($userRole === 'training_partner') {
                    $training_center_id = $_SESSION['training_center_id'] ?? $user['id'];
                }

                // Insert student
                $stmt = $db->prepare("
                    INSERT INTO students (
                        enrollment_no, name, father_name, email, phone, aadhaar, 
                        dob, gender, education, address, course_id, batch_id, 
                        training_center_id, password, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");

                // Default password for students
                $defaultPassword = password_hash('student123', PASSWORD_DEFAULT);

                $stmt->execute([
                    $enrollment_no, $name, $father_name, $email, $phone, $aadhaar,
                    $dob, $gender, $education, $address, $course_id, 
                    $batch_id ?: null, $training_center_id, $defaultPassword
                ]);

                $student_id = $db->lastInsertId();

                // Add registration fee (Rs. 100)
                $stmt = $db->prepare("
                    INSERT INTO fees (student_id, amount, fee_type, due_date, status, created_at) 
                    VALUES (?, 100, 'registration', DATE_ADD(NOW(), INTERVAL 30 DAY), 'pending', NOW())
                ");
                $stmt->execute([$student_id]);

                echo json_encode([
                    'success' => true, 
                    'message' => 'Student added successfully! Enrollment No: ' . $enrollment_no,
                    'enrollment_no' => $enrollment_no
                ]);
                exit();

            case 'get_courses_by_sector':
                $sector_id = $_POST['sector_id'] ?? 0;
                $stmt = $db->prepare("SELECT id, name, code FROM courses WHERE sector_id = ? AND status = 'active' ORDER BY name");
                $stmt->execute([$sector_id]);
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'courses' => $courses]);
                exit();

            case 'get_batches_by_course':
                $course_id = $_POST['course_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;

                $query = "
                    SELECT b.id, b.name, b.start_date, b.end_date 
                    FROM batches b 
                    WHERE b.course_id = ? AND b.status IN ('upcoming', 'ongoing')
                ";
                $params = [$course_id];

                if ($userRole === 'training_partner') {
                    $query .= " AND b.training_center_id = ?";
                    $params[] = $_SESSION['training_center_id'] ?? $user['id'];
                } elseif ($training_center_id) {
                    $query .= " AND b.training_center_id = ?";
                    $params[] = $training_center_id;
                }

                $query .= " ORDER BY b.start_date";

                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'batches' => $batches]);
                exit();

            case 'check_enrollment_duplicate':
                $enrollment_no = trim($_POST['enrollment_no'] ?? '');
                $student_id = $_POST['student_id'] ?? 0;

                $stmt = $db->prepare("SELECT id FROM students WHERE enrollment_no = ? AND id != ?");
                $stmt->execute([$enrollment_no, $student_id]);
                $exists = $stmt->fetch() ? true : false;

                echo json_encode(['exists' => $exists]);
                exit();

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Fetch students based on user role
$studentsQuery = "
    SELECT s.*, c.name as course_name, b.name as batch_name, tc.name as center_name,
           (SELECT COUNT(*) FROM fees f WHERE f.student_id = s.id AND f.status = 'pending') as pending_fees
    FROM students s 
    LEFT JOIN courses c ON s.course_id = c.id 
    LEFT JOIN batches b ON s.batch_id = b.id 
    LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
    WHERE s.status != 'deleted'
";

if ($userRole === 'training_partner') {
    $studentsQuery .= " AND s.training_center_id = ?";
    $stmt = $db->prepare($studentsQuery . " ORDER BY s.created_at DESC");
    $stmt->execute([$_SESSION['training_center_id'] ?? $user['id']]);
} else {
    $stmt = $db->prepare($studentsQuery . " ORDER BY s.created_at DESC");
    $stmt->execute();
}

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch dropdown data
$sectors = [];
$courses = [];
$trainingCenters = [];

// Get sectors
$stmt = $db->prepare("SELECT id, name, code FROM sectors WHERE status = 'active' ORDER BY name");
$stmt->execute();
$sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses
$stmt = $db->prepare("SELECT id, name, code, sector_id FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training centers (only for admin)
if ($userRole === 'admin') {
    $stmt = $db->prepare("SELECT id, name FROM training_centers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Include the new layout
require_once '../includes/layout-new.php';
renderHeader('Students Management - Student Management System');
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-user-graduate"></i>
                    Students Management
                </h1>
                <p class="page-subtitle">Manage student registrations, documents, and information</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-plus me-2"></i>Add New Student
                </button>
                <a href="bulk-upload.php" class="btn btn-success">
                    <i class="fas fa-upload me-2"></i>Bulk Upload
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo count($students); ?></div>
                        <div class="stats-label">Total Students</div>
                    </div>
                    <div class="stats-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo count(array_filter($students, function($s) { return $s['batch_name']; })); ?></div>
                        <div class="stats-label">Enrolled in Batches</div>
                    </div>
                    <div class="stats-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card warning">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo count(array_filter($students, function($s) { return $s['pending_fees'] > 0; })); ?></div>
                        <div class="stats-label">Pending Fees</div>
                    </div>
                    <div class="stats-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card danger">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-number"><?php echo count(array_filter($students, function($s) { return !$s['batch_name']; })); ?></div>
                        <div class="stats-label">Not Assigned</div>
                    </div>
                    <div class="stats-icon danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Students List
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="studentsTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Enrollment No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Course</th>
                            <th>Batch</th>
                            <th>Center</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($student['enrollment_no']); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2" style="width: 35px; height: 35px;">
                                        <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($student['name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['father_name'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['phone']); ?></td>
                            <td><?php echo htmlspecialchars($student['course_name'] ?? 'Not Assigned'); ?></td>
                            <td>
                                <?php if ($student['batch_name']): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($student['batch_name']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['center_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($student['pending_fees'] > 0): ?>
                                    <span class="badge bg-warning">Fees Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewStudent(<?php echo $student['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="editStudent(<?php echo $student['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="manageDocuments(<?php echo $student['id']; ?>)">
                                        <i class="fas fa-file-upload"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary-gradient text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add New Student
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm">
                    <div class="row g-3">
                        <!-- Personal Information -->
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">Personal Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="father_name" class="form-label">Father's Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="father_name" name="father_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="phone" name="phone" maxlength="10" pattern="[0-9]{10}" required>
                            <div class="form-text">Enter exactly 10 digits</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="aadhaar" class="form-label">Aadhaar Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="aadhaar" name="aadhaar" maxlength="12" pattern="[0-9]{12}" required>
                            <div class="form-text">Enter exactly 12 digits</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="education" class="form-label">Education <span class="text-danger">*</span></label>
                            <select class="form-select" id="education" name="education" required>
                                <option value="">Select Education</option>
                                <option value="Below SSC">Below SSC</option>
                                <option value="SSC">SSC</option>
                                <option value="Inter">Inter</option>
                                <option value="Graduation">Graduation</option>
                                <option value="PG">PG</option>
                                <option value="B.Tech">B.Tech</option>
                                <option value="Diploma">Diploma</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>

                        <!-- Course Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Course Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="sector_id" class="form-label">Sector <span class="text-danger">*</span></label>
                            <select class="form-select" id="sector_id" name="sector_id" required>
                                <option value="">Select Sector</option>
                                <?php foreach ($sectors as $sector): ?>
                                <option value="<?php echo $sector['id']; ?>">
                                    <?php echo htmlspecialchars($sector['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                            </select>
                        </div>

                        <?php if ($userRole === 'admin'): ?>
                        <div class="col-md-6">
                            <label for="training_center_id" class="form-label">Training Center</label>
                            <select class="form-select" id="training_center_id" name="training_center_id">
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
                            <label for="batch_id" class="form-label">Batch (Optional)</label>
                            <select class="form-select" id="batch_id" name="batch_id">
                                <option value="">Select Batch</option>
                            </select>
                        </div>

                        <!-- Photo Upload -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Photo Upload</h6>
                        </div>
                        
                        <div class="col-12">
                            <label for="photo" class="form-label">Student Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <div class="form-text">Upload a photo that will be cropped to 3.5cm x 4.5cm</div>
                            
                            <!-- Photo Cropper -->
                            <div id="photoCropper" style="display: none; margin-top: 15px;">
                                <img id="cropperImage" style="max-width: 100%;">
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" onclick="cropPhoto()">
                                        <i class="fas fa-crop me-2"></i>Crop Photo
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="cancelCrop()">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Cropped Photo Preview -->
                            <div id="photoPreview" style="display: none; margin-top: 15px;">
                                <img id="previewImage" style="width: 150px; height: 200px; object-fit: cover; border: 1px solid #ddd;">
                                <input type="hidden" id="croppedPhotoData" name="cropped_photo">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addStudent()">
                    <i class="fas fa-save me-2"></i>Add Student
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Render Sidebar -->
<?php renderSidebar('students'); ?>

<script>
let cropper = null;

// Document ready
$(document).ready(function() {
    // Initialize DataTable
    $('#studentsTable').DataTable({
        order: [[0, 'asc']],
        columnDefs: [
            { targets: [9], orderable: false } // Actions column
        ]
    });

    // Sector change handler
    $('#sector_id').on('change', function() {
        const sectorId = $(this).val();
        $('#course_id').html('<option value="">Select Course</option>');
        $('#batch_id').html('<option value="">Select Batch</option>');
        
        if (sectorId) {
            $.post('', {
                action: 'get_courses_by_sector',
                sector_id: sectorId
            }, function(response) {
                if (response.success) {
                    response.courses.forEach(course => {
                        $('#course_id').append(`<option value="${course.id}">${course.name}</option>`);
                    });
                }
            }, 'json');
        }
    });

    // Course change handler
    $('#course_id').on('change', function() {
        const courseId = $(this).val();
        const trainingCenterId = $('#training_center_id').val();
        $('#batch_id').html('<option value="">Select Batch</option>');
        
        if (courseId) {
            $.post('', {
                action: 'get_batches_by_course',
                course_id: courseId,
                training_center_id: trainingCenterId
            }, function(response) {
                if (response.success) {
                    response.batches.forEach(batch => {
                        $('#batch_id').append(`<option value="${batch.id}">${batch.name} (${batch.start_date} to ${batch.end_date})</option>`);
                    });
                }
            }, 'json');
        }
    });

    // Training center change handler
    $('#training_center_id').on('change', function() {
        if ($('#course_id').val()) {
            $('#course_id').trigger('change');
        }
    });

    // Phone number validation
    $('#phone').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });

    // Aadhaar validation
    $('#aadhaar').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 12) {
            this.value = this.value.slice(0, 12);
        }
    });

    // Photo upload handler
    $('#photo').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#cropperImage').attr('src', e.target.result);
                    $('#photoCropper').show();
                    $('#photoPreview').hide();
                    
                    // Initialize cropper
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    cropper = new Cropper($('#cropperImage')[0], {
                        aspectRatio: 3.5 / 4.5, // 3.5cm x 4.5cm ratio
                        viewMode: 2,
                        dragMode: 'move',
                        autoCropArea: 1,
                        responsive: true,
                        cropBoxResizable: true,
                        cropBoxMovable: true,
                        guides: true,
                        center: true,
                        highlight: true,
                        background: true
                    });
                };
                reader.readAsDataURL(file);
            } else {
                alert('Please select a valid image file.');
                this.value = '';
            }
        }
    });
});

// Crop photo function
function cropPhoto() {
    if (cropper) {
        const canvas = cropper.getCroppedCanvas({
            width: 350, // 3.5cm at 100 DPI
            height: 450, // 4.5cm at 100 DPI
            fillColor: '#fff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        const croppedDataURL = canvas.toDataURL('image/jpeg', 0.9);
        $('#previewImage').attr('src', croppedDataURL);
        $('#croppedPhotoData').val(croppedDataURL);
        $('#photoPreview').show();
        $('#photoCropper').hide();
        
        cropper.destroy();
        cropper = null;
    }
}

// Cancel crop function
function cancelCrop() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    $('#photoCropper').hide();
    $('#photo').val('');
}

// Add student function
function addStudent() {
    const form = document.getElementById('addStudentForm');
    if (!validateForm(form)) {
        showToast('Please fill all required fields correctly.', 'danger');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'add_student');

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
                $('#addStudentModal').modal('hide');
                form.reset();
                $('#photoCropper').hide();
                $('#photoPreview').hide();
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.message, 'danger');
            }
        },
        error: function() {
            showToast('An error occurred. Please try again.', 'danger');
        }
    });
}

// View student function
function viewStudent(studentId) {
    // Implementation for viewing student details
    showToast('View student feature - Coming soon!', 'info');
}

// Edit student function
function editStudent(studentId) {
    // Implementation for editing student
    showToast('Edit student feature - Coming soon!', 'info');
}

// Manage documents function
function manageDocuments(studentId) {
    // Implementation for document management
    showToast('Document management feature - Coming soon!', 'info');
}

console.log('Students management loaded successfully');
</script>

<?php renderFooter(); ?>

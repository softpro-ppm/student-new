<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user = getCurrentUser();
$userRole = getCurrentUserRole();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create':
                // Validate required fields
                $name = trim($_POST['name'] ?? '');
                $father_name = trim($_POST['father_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $aadhaar = trim($_POST['aadhaar'] ?? '');
                $dob = $_POST['dob'] ?? '';
                $gender = $_POST['gender'] ?? '';
                $education = $_POST['education'] ?? '';
                $marital_status = $_POST['marital_status'] ?? 'single';
                $course_id = $_POST['course_id'] ?? 0;
                $batch_id = $_POST['batch_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;
                
                // Validation
                if (empty($name) || empty($father_name) || empty($phone) || empty($aadhaar) || 
                    empty($dob) || empty($gender) || empty($education) || empty($course_id)) {
                    throw new Exception('All required fields must be filled');
                }
                
                // Phone validation
                if (strlen($phone) !== 10 || !ctype_digit($phone)) {
                    throw new Exception('Phone number must be exactly 10 digits');
                }
                
                // Aadhaar validation
                if (strlen($aadhaar) !== 12 || !ctype_digit($aadhaar)) {
                    throw new Exception('Aadhaar number must be exactly 12 digits');
                }
                
                // Email validation (if provided)
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                // Check if phone already exists
                $stmt = $db->prepare("SELECT id FROM students WHERE phone = ?");
                $stmt->execute([$phone]);
                if ($stmt->fetch()) {
                    throw new Exception('Phone number already exists');
                }
                
                // Check if aadhaar already exists
                $stmt = $db->prepare("SELECT id FROM students WHERE aadhaar = ?");
                $stmt->execute([$aadhaar]);
                if ($stmt->fetch()) {
                    throw new Exception('Aadhaar number already exists');
                }
                
                // Check if email already exists (if provided)
                if (!empty($email)) {
                    $stmt = $db->prepare("SELECT id FROM students WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        throw new Exception('Email already exists');
                    }
                }
                
                // Generate enrollment number
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM students");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                $enrollmentNumber = 'ENR' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
                
                // Set training center based on user role
                if ($userRole === 'training_partner') {
                    $stmt = $db->prepare("SELECT training_center_id FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $training_center_id = $result['training_center_id'] ?? 0;
                }
                
                // Insert student
                $stmt = $db->prepare("
                    INSERT INTO students (enrollment_number, name, father_name, email, phone, aadhaar, 
                                        dob, gender, education, marital_status, course_id, batch_id, 
                                        training_center_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $enrollmentNumber, $name, $father_name, $email, $phone, $aadhaar,
                    $dob, $gender, $education, $marital_status, $course_id, 
                    $batch_id ?: null, $training_center_id ?: null
                ]);
                
                $studentId = $db->lastInsertId();
                
                // Create user account for student
                $password = password_hash('student123', PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password, role, name, phone) 
                    VALUES (?, ?, ?, 'student', ?, ?)
                ");
                $stmt->execute([$phone, $email ?: $phone . '@student.local', $password, $name, $phone]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Student created successfully', 
                    'enrollment_number' => $enrollmentNumber
                ]);
                break;
                
            case 'update':
                $id = $_POST['id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $father_name = trim($_POST['father_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $aadhaar = trim($_POST['aadhaar'] ?? '');
                $dob = $_POST['dob'] ?? '';
                $gender = $_POST['gender'] ?? '';
                $education = $_POST['education'] ?? '';
                $marital_status = $_POST['marital_status'] ?? 'single';
                $course_id = $_POST['course_id'] ?? 0;
                $batch_id = $_POST['batch_id'] ?? 0;
                $training_center_id = $_POST['training_center_id'] ?? 0;
                $status = $_POST['status'] ?? 'active';
                
                // Validation
                if (empty($name) || empty($father_name) || empty($phone) || empty($aadhaar)) {
                    throw new Exception('Name, father name, phone, and Aadhaar are required');
                }
                
                // Phone validation
                if (strlen($phone) !== 10 || !ctype_digit($phone)) {
                    throw new Exception('Phone number must be exactly 10 digits');
                }
                
                // Aadhaar validation
                if (strlen($aadhaar) !== 12 || !ctype_digit($aadhaar)) {
                    throw new Exception('Aadhaar number must be exactly 12 digits');
                }
                
                // Email validation (if provided)
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                // Check if phone already exists for other students
                $stmt = $db->prepare("SELECT id FROM students WHERE phone = ? AND id != ?");
                $stmt->execute([$phone, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Phone number already exists');
                }
                
                // Check if aadhaar already exists for other students
                $stmt = $db->prepare("SELECT id FROM students WHERE aadhaar = ? AND id != ?");
                $stmt->execute([$aadhaar, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Aadhaar number already exists');
                }
                
                // Check if email already exists for other students (if provided)
                if (!empty($email)) {
                    $stmt = $db->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception('Email already exists');
                    }
                }
                
                // Update student
                $stmt = $db->prepare("
                    UPDATE students 
                    SET name = ?, father_name = ?, email = ?, phone = ?, aadhaar = ?, 
                        dob = ?, gender = ?, education = ?, marital_status = ?, 
                        course_id = ?, batch_id = ?, training_center_id = ?, status = ?,
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $father_name, $email, $phone, $aadhaar, $dob, $gender, $education, 
                    $marital_status, $course_id ?: null, $batch_id ?: null, 
                    $training_center_id ?: null, $status, $id
                ]);
                
                // Update user account
                $stmt = $db->prepare("
                    UPDATE users 
                    SET email = ?, name = ?, phone = ? 
                    WHERE username = ? AND role = 'student'
                ");
                $stmt->execute([
                    $email ?: $phone . '@student.local', $name, $phone, $phone
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                
                // Check if student has fee records or assessment results
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM fees WHERE student_id = ?");
                $stmt->execute([$id]);
                $feeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM assessment_results WHERE student_id = ?");
                $stmt->execute([$id]);
                $assessmentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($feeCount > 0 || $assessmentCount > 0) {
                    throw new Exception('Cannot delete student with existing fee records or assessment results');
                }
                
                // Get student phone for deleting user account
                $stmt = $db->prepare("SELECT phone FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $studentPhone = $stmt->fetch(PDO::FETCH_ASSOC)['phone'] ?? '';
                
                // Delete user account
                if ($studentPhone) {
                    $stmt = $db->prepare("DELETE FROM users WHERE username = ? AND role = 'student'");
                    $stmt->execute([$studentPhone]);
                }
                
                // Delete student
                $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Fetch data for dropdowns
$stmt = $db->prepare("SELECT id, name FROM courses WHERE status = 'active' ORDER BY name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id, name FROM batches WHERE status IN ('planned', 'ongoing') ORDER BY name");
$stmt->execute();
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id, name FROM training_centers WHERE status = 'active' ORDER BY name");
$stmt->execute();
$trainingCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch students based on user role
$whereClause = '';
$params = [];

if ($userRole === 'training_partner') {
    // Get training center for current user
    $stmt = $db->prepare("SELECT training_center_id FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userTrainingCenterId = $result['training_center_id'] ?? 0;
    
    if ($userTrainingCenterId) {
        $whereClause = 'WHERE s.training_center_id = ?';
        $params[] = $userTrainingCenterId;
    }
}

$stmt = $db->prepare("
    SELECT s.*, c.name as course_name, b.name as batch_name, tc.name as training_center_name
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN batches b ON s.batch_id = b.id
    LEFT JOIN training_centers tc ON s.training_center_id = tc.id
    $whereClause
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/layout.php';
renderHeader('Students');
?>

<div class="container-fluid">
    <div class="row">
        <?php renderSidebar($userRole); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-users me-2"></i>Students
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-1"></i>Add Student
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($students); ?></div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count(array_filter($students, function($s) { return $s['status'] === 'active'; })); ?>
                                    </div>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Male Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count(array_filter($students, function($s) { return $s['gender'] === 'male'; })); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-male fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Female Students
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count(array_filter($students, function($s) { return $s['gender'] === 'female'; })); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-female fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Students List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Enrollment No.</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Course</th>
                                    <th>Batch</th>
                                    <?php if ($userRole === 'admin'): ?>
                                    <th>Training Center</th>
                                    <?php endif; ?>
                                    <th>Gender</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['enrollment_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($student['name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['father_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['batch_name'] ?? 'N/A'); ?></td>
                                    <?php if ($userRole === 'admin'): ?>
                                    <td><?php echo htmlspecialchars($student['training_center_name'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($student['gender'] === 'male'): ?>
                                            <span class="badge bg-primary">Male</span>
                                        <?php elseif ($student['gender'] === 'female'): ?>
                                            <span class="badge bg-pink">Female</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Other</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($student['status']) {
                                            case 'active': $statusClass = 'bg-success'; break;
                                            case 'inactive': $statusClass = 'bg-warning'; break;
                                            case 'completed': $statusClass = 'bg-info'; break;
                                            case 'dropped': $statusClass = 'bg-danger'; break;
                                            default: $statusClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($student['status']); ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="viewStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($userRole === 'admin'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addStudentForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Student Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="father_name" class="form-label">Father's Name *</label>
                                <input type="text" class="form-control" id="father_name" name="father_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Mobile Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" pattern="[0-9]{10}" maxlength="10" required>
                                <div class="form-text">Enter 10-digit mobile number</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="aadhaar" class="form-label">Aadhaar Number *</label>
                                <input type="text" class="form-control" id="aadhaar" name="aadhaar" pattern="[0-9]{12}" maxlength="12" required>
                                <div class="form-text">Enter 12-digit Aadhaar number</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dob" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" id="dob" name="dob" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender *</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="education" class="form-label">Education *</label>
                                <select class="form-select" id="education" name="education" required>
                                    <option value="">Select Education</option>
                                    <option value="Below SSC">Below SSC</option>
                                    <option value="SSC">SSC</option>
                                    <option value="Intermediate">Intermediate</option>
                                    <option value="Graduation">Graduation</option>
                                    <option value="Post Graduation">Post Graduation</option>
                                    <option value="B.Tech">B.Tech</option>
                                    <option value="Diploma">Diploma</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="marital_status" class="form-label">Marital Status</label>
                                <select class="form-select" id="marital_status" name="marital_status">
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="widowed">Widowed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Course *</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="batch_id" class="form-label">Batch</label>
                                <select class="form-select" id="batch_id" name="batch_id">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php if ($userRole === 'admin'): ?>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="training_center_id" class="form-label">Training Center</label>
                                <select class="form-select" id="training_center_id" name="training_center_id">
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $tc): ?>
                                    <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStudentModalLabel">Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editStudentForm">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Student Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_father_name" class="form-label">Father's Name *</label>
                                <input type="text" class="form-control" id="edit_father_name" name="father_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_phone" class="form-label">Mobile Number *</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" pattern="[0-9]{10}" maxlength="10" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_aadhaar" class="form-label">Aadhaar Number *</label>
                                <input type="text" class="form-control" id="edit_aadhaar" name="aadhaar" pattern="[0-9]{12}" maxlength="12" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_dob" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" id="edit_dob" name="dob" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_gender" class="form-label">Gender *</label>
                                <select class="form-select" id="edit_gender" name="gender" required>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_education" class="form-label">Education *</label>
                                <select class="form-select" id="edit_education" name="education" required>
                                    <option value="Below SSC">Below SSC</option>
                                    <option value="SSC">SSC</option>
                                    <option value="Intermediate">Intermediate</option>
                                    <option value="Graduation">Graduation</option>
                                    <option value="Post Graduation">Post Graduation</option>
                                    <option value="B.Tech">B.Tech</option>
                                    <option value="Diploma">Diploma</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_marital_status" class="form-label">Marital Status</label>
                                <select class="form-select" id="edit_marital_status" name="marital_status">
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="widowed">Widowed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_course_id" class="form-label">Course *</label>
                                <select class="form-select" id="edit_course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_batch_id" class="form-label">Batch</label>
                                <select class="form-select" id="edit_batch_id" name="batch_id">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php if ($userRole === 'admin'): ?>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_training_center_id" class="form-label">Training Center</label>
                                <select class="form-select" id="edit_training_center_id" name="training_center_id">
                                    <option value="">Select Training Center</option>
                                    <?php foreach ($trainingCenters as $tc): ?>
                                    <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="completed">Completed</option>
                                    <option value="dropped">Dropped</option>
                                </select>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="completed">Completed</option>
                                    <option value="dropped">Dropped</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1" aria-labelledby="viewStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewStudentModalLabel">Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewStudentContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#studentsTable').DataTable({
        "pageLength": 25,
        "order": [[ 8, "desc" ]], // Sort by joined date
        "columnDefs": [
            { "orderable": false, "targets": -1 } // Disable sorting on Actions column
        ]
    });
    
    // Add Student Form
    $('#addStudentForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'create');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message + '\nEnrollment Number: ' + response.enrollment_number);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while creating the student.');
            }
        });
    });
    
    // Edit Student Form
    $('#editStudentForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'update');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while updating the student.');
            }
        });
    });
    
    // Phone and Aadhaar number validation
    $('#phone, #edit_phone').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    $('#aadhaar, #edit_aadhaar').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

function viewStudent(id) {
    const students = <?php echo json_encode($students); ?>;
    const student = students.find(s => s.id == id);
    
    if (student) {
        const content = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Personal Information</h6>
                    <p><strong>Enrollment Number:</strong> ${student.enrollment_number}</p>
                    <p><strong>Name:</strong> ${student.name}</p>
                    <p><strong>Father's Name:</strong> ${student.father_name}</p>
                    <p><strong>Email:</strong> ${student.email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${student.phone}</p>
                    <p><strong>Aadhaar:</strong> ${student.aadhaar}</p>
                    <p><strong>Date of Birth:</strong> ${student.dob ? new Date(student.dob).toLocaleDateString() : 'N/A'}</p>
                    <p><strong>Gender:</strong> ${student.gender ? student.gender.charAt(0).toUpperCase() + student.gender.slice(1) : 'N/A'}</p>
                    <p><strong>Education:</strong> ${student.education}</p>
                    <p><strong>Marital Status:</strong> ${student.marital_status ? student.marital_status.charAt(0).toUpperCase() + student.marital_status.slice(1) : 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Academic Information</h6>
                    <p><strong>Course:</strong> ${student.course_name || 'N/A'}</p>
                    <p><strong>Batch:</strong> ${student.batch_name || 'N/A'}</p>
                    <p><strong>Training Center:</strong> ${student.training_center_name || 'N/A'}</p>
                    <p><strong>Status:</strong> <span class="badge bg-${student.status === 'active' ? 'success' : student.status === 'completed' ? 'info' : student.status === 'dropped' ? 'danger' : 'warning'}">${student.status ? student.status.charAt(0).toUpperCase() + student.status.slice(1) : 'N/A'}</span></p>
                    <p><strong>Joined Date:</strong> ${new Date(student.created_at).toLocaleDateString()}</p>
                </div>
            </div>
        `;
        
        $('#viewStudentContent').html(content);
        $('#viewStudentModal').modal('show');
    }
}

function editStudent(id) {
    const students = <?php echo json_encode($students); ?>;
    const student = students.find(s => s.id == id);
    
    if (student) {
        $('#edit_id').val(student.id);
        $('#edit_name').val(student.name);
        $('#edit_father_name').val(student.father_name);
        $('#edit_email').val(student.email);
        $('#edit_phone').val(student.phone);
        $('#edit_aadhaar').val(student.aadhaar);
        $('#edit_dob').val(student.dob);
        $('#edit_gender').val(student.gender);
        $('#edit_education').val(student.education);
        $('#edit_marital_status').val(student.marital_status);
        $('#edit_course_id').val(student.course_id);
        $('#edit_batch_id').val(student.batch_id);
        $('#edit_training_center_id').val(student.training_center_id);
        $('#edit_status').val(student.status);
        $('#editStudentModal').modal('show');
    }
}

function deleteStudent(id) {
    if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while deleting the student.');
            }
        });
    }
}
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.text-xs {
    font-size: 0.7rem;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.bg-pink {
    background-color: #e83e8c !important;
}

.table th {
    border-top: none;
}
</style>

<?php renderFooter(); ?>

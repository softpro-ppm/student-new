<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

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
        case 'get_sectors':
            try {
                $stmt = $db->prepare("SELECT * FROM sectors ORDER BY name ASC");
                $stmt->execute();
                $sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $sectors]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_courses':
            try {
                $stmt = $db->prepare("
                    SELECT c.*, s.name as sector_name 
                    FROM courses c 
                    LEFT JOIN sectors s ON c.sector_id = s.id 
                    ORDER BY c.name ASC
                ");
                $stmt->execute();
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $courses]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_sector':
            try {
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("SELECT * FROM sectors WHERE id = ?");
                $stmt->execute([$id]);
                $sector = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $sector]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_course':
            try {
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $course]);
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
        case 'add_sector':
            try {
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || empty($code)) {
                    throw new Exception('Name and code are required');
                }
                
                // Check if sector code already exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM sectors WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Sector code already exists');
                }
                
                $stmt = $db->prepare("INSERT INTO sectors (name, code, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $description]);
                
                echo json_encode(['success' => true, 'message' => 'Sector added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'update_sector':
            try {
                $id = $_POST['id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || empty($code)) {
                    throw new Exception('Name and code are required');
                }
                
                // Check if sector code already exists for other records
                $stmt = $db->prepare("SELECT COUNT(*) FROM sectors WHERE code = ? AND id != ?");
                $stmt->execute([$code, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Sector code already exists');
                }
                
                $stmt = $db->prepare("UPDATE sectors SET name = ?, code = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $code, $description, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Sector updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'delete_sector':
            try {
                $id = $_POST['id'] ?? 0;
                
                // Check if sector has courses
                $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE sector_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete sector. It has associated courses.');
                }
                
                $stmt = $db->prepare("DELETE FROM sectors WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Sector deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'add_course':
            try {
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $sector_id = $_POST['sector_id'] ?? 0;
                $duration = $_POST['duration'] ?? 0;
                $fees = $_POST['fees'] ?? 0;
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || empty($code) || empty($sector_id)) {
                    throw new Exception('Name, code and sector are required');
                }
                
                // Check if course code already exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Course code already exists');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO courses (name, code, sector_id, duration, fees, description) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $code, $sector_id, $duration, $fees, $description]);
                
                echo json_encode(['success' => true, 'message' => 'Course added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'update_course':
            try {
                $id = $_POST['id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $sector_id = $_POST['sector_id'] ?? 0;
                $duration = $_POST['duration'] ?? 0;
                $fees = $_POST['fees'] ?? 0;
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || empty($code) || empty($sector_id)) {
                    throw new Exception('Name, code and sector are required');
                }
                
                // Check if course code already exists for other records
                $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE code = ? AND id != ?");
                $stmt->execute([$code, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Course code already exists');
                }
                
                $stmt = $db->prepare("
                    UPDATE courses 
                    SET name = ?, code = ?, sector_id = ?, duration = ?, fees = ?, description = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $code, $sector_id, $duration, $fees, $description, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'delete_course':
            try {
                $id = $_POST['id'] ?? 0;
                
                // Check if course has batches
                $stmt = $db->prepare("SELECT COUNT(*) FROM batches WHERE course_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete course. It has associated batches.');
                }
                
                $stmt = $db->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM sectors");
    $stmt->execute();
    $totalSectors = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM courses");
    $stmt->execute();
    $totalCourses = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT sector_id) FROM courses");
    $stmt->execute();
    $activeSectors = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT AVG(fees) FROM courses WHERE fees > 0");
    $stmt->execute();
    $avgFees = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    $totalSectors = $totalCourses = $activeSectors = $avgFees = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masters - Student Management System</title>
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
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            border: none;
            color: #495057;
            font-weight: 500;
            margin-right: 0.5rem;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                        <a class="nav-link active" href="masters.php">
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
                        <h2><i class="fas fa-cogs me-2 text-primary"></i>Masters Management</h2>
                        <p class="text-muted mb-0">Manage sectors and courses for the training programs</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Total Sectors</h6>
                        <p class="card-text"><?php echo number_format($totalSectors); ?></p>
                        <i class="fas fa-industry card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Total Courses</h6>
                        <p class="card-text"><?php echo number_format($totalCourses); ?></p>
                        <i class="fas fa-book card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Active Sectors</h6>
                        <p class="card-text"><?php echo number_format($activeSectors); ?></p>
                        <i class="fas fa-check-circle card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card position-relative">
                    <div class="card-body">
                        <h6 class="card-title">Avg. Course Fees</h6>
                        <p class="card-text">₹<?php echo number_format($avgFees, 0); ?></p>
                        <i class="fas fa-rupee-sign card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="mastersTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="sectors-tab" data-bs-toggle="tab" data-bs-target="#sectors" type="button" role="tab">
                                    <i class="fas fa-industry me-2"></i>Sectors
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab">
                                    <i class="fas fa-book me-2"></i>Courses
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content" id="mastersTabContent">
                            <!-- Sectors Tab -->
                            <div class="tab-pane fade show active" id="sectors" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Sectors Management</h5>
                                    <?php if ($userRole === 'admin'): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sectorModal">
                                        <i class="fas fa-plus me-2"></i>Add Sector
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="table-responsive">
                                    <table id="sectorsTable" class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Sector Name</th>
                                                <th>Code</th>
                                                <th>Description</th>
                                                <th>Courses</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data will be loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Courses Tab -->
                            <div class="tab-pane fade" id="courses" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Courses Management</h5>
                                    <?php if ($userRole === 'admin'): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">
                                        <i class="fas fa-plus me-2"></i>Add Course
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="table-responsive">
                                    <table id="coursesTable" class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Course Name</th>
                                                <th>Code</th>
                                                <th>Sector</th>
                                                <th>Duration (Hours)</th>
                                                <th>Fees (₹)</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data will be loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sector Modal -->
    <div class="modal fade" id="sectorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sectorModalTitle">
                        <i class="fas fa-industry me-2"></i>Add Sector
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="sectorForm">
                    <div class="modal-body">
                        <input type="hidden" id="sectorId" name="id">
                        <input type="hidden" id="sectorAction" name="action" value="add_sector">
                        
                        <div class="mb-3">
                            <label for="sectorName" class="form-label">Sector Name</label>
                            <input type="text" class="form-control" id="sectorName" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sectorCode" class="form-label">Sector Code</label>
                            <input type="text" class="form-control" id="sectorCode" name="code" required>
                            <small class="form-text text-muted">Unique identifier for the sector</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sectorDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="sectorDescription" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Sector
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Course Modal -->
    <div class="modal fade" id="courseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="courseModalTitle">
                        <i class="fas fa-book me-2"></i>Add Course
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="courseForm">
                    <div class="modal-body">
                        <input type="hidden" id="courseId" name="id">
                        <input type="hidden" id="courseAction" name="action" value="add_course">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="courseName" class="form-label">Course Name</label>
                                    <input type="text" class="form-control" id="courseName" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="courseCode" class="form-label">Course Code</label>
                                    <input type="text" class="form-control" id="courseCode" name="code" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="courseSector" class="form-label">Sector</label>
                                    <select class="form-select" id="courseSector" name="sector_id" required>
                                        <option value="">Select Sector</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="courseDuration" class="form-label">Duration (Hours)</label>
                                    <input type="number" class="form-control" id="courseDuration" name="duration" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="courseFees" class="form-label">Fees (₹)</label>
                                    <input type="number" class="form-control" id="courseFees" name="fees" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="courseDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="courseDescription" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Course
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            let sectorsTable = $('#sectorsTable').DataTable({
                responsive: true,
                ajax: {
                    url: 'masters.php?action=get_sectors',
                    dataSrc: 'data'
                },
                columns: [
                    { data: 'id' },
                    { data: 'name' },
                    { data: 'code' },
                    { 
                        data: 'description',
                        render: function(data) {
                            if (!data) return '-';
                            return data.length > 50 ? data.substr(0, 50) + '...' : data;
                        }
                    },
                    { 
                        data: null,
                        render: function(data) {
                            return '<span class="badge bg-info">Loading...</span>';
                        }
                    },
                    {
                        data: null,
                        render: function(data) {
                            <?php if ($userRole === 'admin'): ?>
                            return `
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editSector(${data.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSector(${data.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                            <?php else: ?>
                            return '-';
                            <?php endif; ?>
                        }
                    }
                ]
            });

            let coursesTable = $('#coursesTable').DataTable({
                responsive: true,
                ajax: {
                    url: 'masters.php?action=get_courses',
                    dataSrc: 'data'
                },
                columns: [
                    { data: 'id' },
                    { data: 'name' },
                    { data: 'code' },
                    { data: 'sector_name' },
                    { 
                        data: 'duration',
                        render: function(data) {
                            return data ? data + ' hrs' : '-';
                        }
                    },
                    { 
                        data: 'fees',
                        render: function(data) {
                            return data ? '₹' + parseFloat(data).toLocaleString() : '-';
                        }
                    },
                    {
                        data: null,
                        render: function(data) {
                            <?php if ($userRole === 'admin'): ?>
                            return `
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editCourse(${data.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCourse(${data.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                            <?php else: ?>
                            return '-';
                            <?php endif; ?>
                        }
                    }
                ]
            });

            // Load sectors in course form
            loadSectors();

            // Handle sector form submission
            $('#sectorForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'masters.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', response.message, 'success');
                            $('#sectorModal').modal('hide');
                            sectorsTable.ajax.reload();
                            loadSectors(); // Reload sectors for course form
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Something went wrong', 'error');
                    }
                });
            });

            // Handle course form submission
            $('#courseForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'masters.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', response.message, 'success');
                            $('#courseModal').modal('hide');
                            coursesTable.ajax.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Something went wrong', 'error');
                    }
                });
            });

            // Reset forms when modals are closed
            $('#sectorModal').on('hidden.bs.modal', function() {
                $('#sectorForm')[0].reset();
                $('#sectorId').val('');
                $('#sectorAction').val('add_sector');
                $('#sectorModalTitle').html('<i class="fas fa-industry me-2"></i>Add Sector');
            });

            $('#courseModal').on('hidden.bs.modal', function() {
                $('#courseForm')[0].reset();
                $('#courseId').val('');
                $('#courseAction').val('add_course');
                $('#courseModalTitle').html('<i class="fas fa-book me-2"></i>Add Course');
            });
        });

        function loadSectors() {
            $.get('masters.php?action=get_sectors', function(response) {
                if (response.success) {
                    const select = $('#courseSector');
                    select.empty().append('<option value="">Select Sector</option>');
                    
                    response.data.forEach(function(sector) {
                        select.append(`<option value="${sector.id}">${sector.name}</option>`);
                    });
                }
            });
        }

        function editSector(id) {
            $.get(`masters.php?action=get_sector&id=${id}`, function(response) {
                if (response.success) {
                    const sector = response.data;
                    $('#sectorId').val(sector.id);
                    $('#sectorName').val(sector.name);
                    $('#sectorCode').val(sector.code);
                    $('#sectorDescription').val(sector.description);
                    $('#sectorAction').val('update_sector');
                    $('#sectorModalTitle').html('<i class="fas fa-industry me-2"></i>Edit Sector');
                    $('#sectorModal').modal('show');
                }
            });
        }

        function deleteSector(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will delete the sector permanently!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('masters.php', {
                        action: 'delete_sector',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            $('#sectorsTable').DataTable().ajax.reload();
                            loadSectors();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    });
                }
            });
        }

        function editCourse(id) {
            $.get(`masters.php?action=get_course&id=${id}`, function(response) {
                if (response.success) {
                    const course = response.data;
                    $('#courseId').val(course.id);
                    $('#courseName').val(course.name);
                    $('#courseCode').val(course.code);
                    $('#courseSector').val(course.sector_id);
                    $('#courseDuration').val(course.duration);
                    $('#courseFees').val(course.fees);
                    $('#courseDescription').val(course.description);
                    $('#courseAction').val('update_course');
                    $('#courseModalTitle').html('<i class="fas fa-book me-2"></i>Edit Course');
                    $('#courseModal').modal('show');
                }
            });
        }

        function deleteCourse(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will delete the course permanently!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('masters.php', {
                        action: 'delete_course',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            $('#coursesTable').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>

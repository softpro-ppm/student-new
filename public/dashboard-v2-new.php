<?php
// Dashboard v2.0 - Student Management Information System
session_start();
require_once '../config/database-v2.php';

// For now, we'll simulate admin access. Later you can implement proper authentication
$currentUser = ['role' => 'admin', 'name' => 'Administrator'];

// Start output buffering to capture content
ob_start();

// Get statistics from v2.0 database
$stats = [
    'training_centers' => 0,
    'students' => 0,
    'batches' => 0,
    'active_batches' => 0,
    'fees_collected' => 0,
    'pending_fees' => 0
];

try {
    $conn = getV2Connection();
    
    // Training Centers count
    $stmt = $conn->query("SELECT COUNT(*) FROM training_centers WHERE status = 'active'");
    $stats['training_centers'] = $stmt->fetchColumn();
    
    // Students count
    $stmt = $conn->query("SELECT COUNT(*) FROM students WHERE status IN ('enrolled', 'active')");
    $stats['students'] = $stmt->fetchColumn();
    
    // Batches count
    $stmt = $conn->query("SELECT COUNT(*) FROM batches WHERE status = 'active'");
    $stats['batches'] = $stmt->fetchColumn();
    
    // Active batches
    $stmt = $conn->query("SELECT COUNT(*) FROM batches WHERE status = 'active' AND start_date <= CURDATE()");
    $stats['active_batches'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

// Get recent activity data
$recentCenters = [];
$recentStudents = [];

try {
    // Recent Training Centers
    $stmt = $conn->query("SELECT * FROM training_centers WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
    $recentCenters = $stmt->fetchAll();
    
    // Recent Students  
    $stmt = $conn->query("
        SELECT s.*, tc.center_name 
        FROM students s 
        LEFT JOIN training_centers tc ON s.training_center_id = tc.id 
        WHERE s.status IN ('enrolled', 'active') 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $recentStudents = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Database connection error: " . $e->getMessage();
}
?>

<!-- Dashboard Content -->
<div class="container-fluid">
    <!-- Welcome Banner -->
    <div class="welcome-banner mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="text-white mb-1">Welcome to SMIS v2.0!</h2>
                <p class="text-white mb-0">Manage your student information system with enhanced features and improved performance.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-graduation-cap" style="font-size: 4rem; opacity: 0.3;"></i>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-gradient-primary me-3">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h3 class="mb-0"><?= $stats['training_centers'] ?></h3>
                        <small class="text-muted">Training Centers</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-gradient-success me-3">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h3 class="mb-0"><?= $stats['students'] ?></h3>
                        <small class="text-muted">Students</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-gradient-warning me-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3 class="mb-0"><?= $stats['active_batches'] ?></h3>
                        <small class="text-muted">Active Batches</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-gradient-info me-3">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h3 class="mb-0"><?= $stats['batches'] ?></h3>
                        <small class="text-muted">Total Batches</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="training-centers.php" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-plus mb-2 d-block"></i>
                                Add Training Center
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="students-v2.php" class="btn btn-success w-100 py-3">
                                <i class="fas fa-user-plus mb-2 d-block"></i>
                                Add Student
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="batches-v2.php" class="btn btn-warning w-100 py-3">
                                <i class="fas fa-users mb-2 d-block"></i>
                                Create Batch
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="reports-v2.php" class="btn btn-info w-100 py-3">
                                <i class="fas fa-chart-bar mb-2 d-block"></i>
                                View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-building"></i> Recent Training Centers</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentCenters)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-building text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No training centers found.</p>
                            <a href="training-centers.php" class="btn btn-primary">Add First Training Center</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentCenters as $center): ?>
                                <div class="list-group-item d-flex align-items-center">
                                    <div class="me-3">
                                        <div class="badge bg-success">Active</div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong><?= htmlspecialchars($center['center_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($center['location']) ?></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-primary"><?= $center['capacity'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user-graduate"></i> Recent Students</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentStudents)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-graduate text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No students found.</p>
                            <a href="students-v2.php" class="btn btn-success">Add First Student</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentStudents as $student): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($student['center_name'] ?? 'No Center') ?></small>
                                        </div>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.welcome-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 2rem;
    color: white;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    border: 1px solid rgba(0,0,0,0.125);
    transition: transform 0.2s ease;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}

.card-header {
    background: white;
    border-bottom: 1px solid #eee;
    border-radius: 15px 15px 0 0 !important;
}
</style>

<?php
$content = ob_get_clean();
require_once '../includes/layout-v2.php';
renderLayout('Dashboard', 'dashboard', $content);
?>

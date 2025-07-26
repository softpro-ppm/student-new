<?php
// Enhanced Layout Template for SMIS v2.0
function renderLayout($title, $currentPage, $content) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - SMIS v2.0</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --navbar-height: 60px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        /* Top Navbar */
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--navbar-height);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1030;
            padding: 0 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }

        .navbar-toggler {
            border: none;
            color: white;
            font-size: 1.2rem;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: var(--navbar-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--navbar-height));
            background: var(--primary-color);
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            z-index: 1020;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: var(--secondary-color);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 3px;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            color: white;
            background: var(--accent-color);
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }

        .sidebar.collapsed .nav-link span {
            display: none;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            margin: 0.25rem 0.1rem;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--navbar-height);
            min-height: calc(100vh - var(--navbar-height));
            transition: all 0.3s ease;
            padding: 0;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .content-header {
            background: white;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 0;
        }

        .content-body {
            padding: 1.5rem;
        }

        /* Cards and Components */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.15);
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
            margin: 0 auto 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1015;
                display: none;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }

        /* Utilities */
        .bg-gradient-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%); }
        .bg-gradient-warning { background: linear-gradient(135deg, var(--warning-color) 0%, #e67e22 100%); }
        .bg-gradient-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #c0392b 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, var(--accent-color) 0%, #5dade2 100%); }

        .btn-rounded {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
        }

        .table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: var(--primary-color);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid #eee;
            border-radius: 15px 15px 0 0;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="top-navbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <button class="navbar-toggler me-3" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="dashboard-v2.php">
                <i class="fas fa-graduation-cap me-2"></i>
                SMIS v2.0
            </a>
        </div>
        
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-link text-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i>
                    Administrator
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <nav class="nav flex-column p-3">
            <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="dashboard-v2.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link <?= $currentPage === 'training-centers' ? 'active' : '' ?>" href="training-centers.php">
                <i class="fas fa-building"></i>
                <span>Training Centers</span>
            </a>
            <a class="nav-link <?= $currentPage === 'students' ? 'active' : '' ?>" href="students-v2.php">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a class="nav-link <?= $currentPage === 'batches' ? 'active' : '' ?>" href="batches-v2.php">
                <i class="fas fa-users"></i>
                <span>Batches</span>
            </a>
            <a class="nav-link <?= $currentPage === 'courses' ? 'active' : '' ?>" href="courses-v2.php">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
            <a class="nav-link <?= $currentPage === 'fees' ? 'active' : '' ?>" href="fees-v2.php">
                <i class="fas fa-money-bill-wave"></i>
                <span>Fees & Payments</span>
            </a>
            <a class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>" href="reports-v2.php">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            
            <hr class="my-3" style="border-color: rgba(255,255,255,0.2);">
            
            <small class="text-muted px-3 mb-2"><span>System</span></small>
            <a class="nav-link" href="setup-v2-schema-part1.php">
                <i class="fas fa-database"></i>
                <span>Setup Database</span>
            </a>
            <a class="nav-link" href="check-v2-database.php">
                <i class="fas fa-check-circle"></i>
                <span>Database Status</span>
            </a>
        </nav>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <?= $content ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    // Mobile behavior
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                } else {
                    // Desktop behavior
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                }
            });

            // Close sidebar on overlay click (mobile)
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                }
            });
        });
    </script>
</body>
</html>
<?php
}
?>

<?php
/**
 * Enhanced Layout Component for Student Management System
 * Features: Modern responsive design, security headers, theme support
 */

// Security headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Default page variables
$pageTitle = $pageTitle ?? 'Student Management System';
$pageDescription = $pageDescription ?? 'Modern student management system';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get user info
$user = getCurrentUser();
$userRole = getCurrentUserRole();
$userName = getCurrentUserName();

function renderPageHeader($title, $breadcrumbs = []) {
    echo '<div class="page-header">';
    echo '<div class="row align-items-center">';
    echo '<div class="col">';
    echo '<h1 class="page-title">' . htmlspecialchars($title) . '</h1>';
    
    if (!empty($breadcrumbs)) {
        echo '<nav aria-label="breadcrumb">';
        echo '<ol class="breadcrumb">';
        foreach ($breadcrumbs as $breadcrumb) {
            if (isset($breadcrumb['url'])) {
                echo '<li class="breadcrumb-item"><a href="' . htmlspecialchars($breadcrumb['url']) . '">' . htmlspecialchars($breadcrumb['label']) . '</a></li>';
            } else {
                echo '<li class="breadcrumb-item active">' . htmlspecialchars($breadcrumb['label']) . '</li>';
            }
        }
        echo '</ol>';
        echo '</nav>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function renderSidebar($currentPage) {
    $userRole = getCurrentUserRole();
    
    $menuItems = [
        'admin' => [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'page' => 'dashboard'],
            ['icon' => 'fas fa-user-graduate', 'label' => 'Students', 'url' => 'students.php', 'page' => 'students'],
            ['icon' => 'fas fa-users', 'label' => 'Batches', 'url' => 'batches.php', 'page' => 'batches'],
            ['icon' => 'fas fa-building', 'label' => 'Training Centers', 'url' => 'training-centers.php', 'page' => 'training-centers'],
            ['icon' => 'fas fa-clipboard-check', 'label' => 'Assessments', 'url' => 'assessments.php', 'page' => 'assessments'],
            ['icon' => 'fas fa-trophy', 'label' => 'Results', 'url' => 'results.php', 'page' => 'results'],
            ['icon' => 'fas fa-credit-card', 'label' => 'Fees', 'url' => 'fees.php', 'page' => 'fees'],
            ['icon' => 'fas fa-chart-bar', 'label' => 'Reports', 'url' => 'reports.php', 'page' => 'reports'],
            ['icon' => 'fas fa-cog', 'label' => 'Settings', 'url' => 'masters.php', 'page' => 'masters']
        ],
        'training_partner' => [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'page' => 'dashboard'],
            ['icon' => 'fas fa-user-graduate', 'label' => 'Students', 'url' => 'students.php', 'page' => 'students'],
            ['icon' => 'fas fa-users', 'label' => 'Batches', 'url' => 'batches.php', 'page' => 'batches'],
            ['icon' => 'fas fa-clipboard-check', 'label' => 'Assessments', 'url' => 'assessments.php', 'page' => 'assessments'],
            ['icon' => 'fas fa-trophy', 'label' => 'Results', 'url' => 'results.php', 'page' => 'results'],
            ['icon' => 'fas fa-credit-card', 'label' => 'Fees', 'url' => 'fees.php', 'page' => 'fees'],
            ['icon' => 'fas fa-chart-bar', 'label' => 'Reports', 'url' => 'reports.php', 'page' => 'reports']
        ],
        'student' => [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'page' => 'dashboard'],
            ['icon' => 'fas fa-clipboard-check', 'label' => 'Assessments', 'url' => 'assessments.php', 'page' => 'assessments'],
            ['icon' => 'fas fa-trophy', 'label' => 'Results', 'url' => 'results.php', 'page' => 'results'],
            ['icon' => 'fas fa-credit-card', 'label' => 'Fee Details', 'url' => 'fees.php', 'page' => 'fees'],
            ['icon' => 'fas fa-certificate', 'label' => 'Certificates', 'url' => 'verify_certificate.php', 'page' => 'verify_certificate']
        ]
    ];
    
    $items = $menuItems[$userRole] ?? $menuItems['student'];
    
    echo '<div class="sidebar">';
    echo '<div class="sidebar-header">';
    echo '<div class="brand">';
    echo '<i class="fas fa-graduation-cap"></i>';
    echo '<span>SMS</span>';
    echo '</div>';
    echo '</div>';
    
    echo '<nav class="sidebar-nav">';
    echo '<ul class="nav flex-column">';
    
    foreach ($items as $item) {
        $isActive = $currentPage === $item['page'];
        $activeClass = $isActive ? ' active' : '';
        
        echo '<li class="nav-item">';
        echo '<a class="nav-link' . $activeClass . '" href="' . htmlspecialchars($item['url']) . '">';
        echo '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
        echo '<span>' . htmlspecialchars($item['label']) . '</span>';
        echo '</a>';
        echo '</li>';
    }
    
    echo '</ul>';
    echo '</nav>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    
    <!-- Security Meta Tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --sidebar-width: 260px;
            --topbar-height: 70px;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --secondary-color: #94a3b8;
            --success-color: #22c55e;
            --danger-color: #f87171;
            --warning-color: #fbbf24;
            --info-color: #22d3ee;
            --dark-color: #0f172a;
            --light-color: #1e293b;
            --border-color: #374151;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar .brand {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .sidebar .brand i {
            margin-right: 0.75rem;
            font-size: 1.75rem;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--secondary-color);
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            border-radius: 0;
        }
        
        .sidebar .nav-link:hover {
            background-color: #f8fafc;
            color: var(--primary-color);
        }
        
        .sidebar .nav-link.active {
            background-color: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            border-right: 3px solid var(--primary-color);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }
        
        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .topbar {
            height: var(--topbar-height);
            background: white;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .main-content {
            flex: 1;
            padding: 2rem;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
        }
        
        .breadcrumb-item {
            font-size: 0.875rem;
        }
        
        .breadcrumb-item a {
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: var(--primary-color);
        }
        
        /* Cards */
        .card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.2s ease;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-1px);
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        /* Tables */
        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .table thead th {
            background-color: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--dark-color);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-wrapper {
                margin-left: 0;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .topbar {
                padding: 0 1rem;
            }
        }
        
        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        /* Badge */
        .badge {
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }
        
        /* Modal */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }
        
        /* Dark theme toggle */
        .theme-toggle {
            background: none;
            border: none;
            color: var(--secondary-color);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: color 0.2s ease;
        }
        
        .theme-toggle:hover {
            color: var(--primary-color);
        }
        
        /* Utility classes */
        .text-muted {
            color: var(--secondary-color) !important;
        }
        
        .border-light {
            border-color: var(--border-color) !important;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    
    <?php if (isset($additionalCSS)): ?>
    <style><?php echo $additionalCSS; ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- Sidebar -->
    <?php renderSidebar($currentPage); ?>
    
    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Topbar -->
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-secondary d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h6 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h6>
                    <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
                
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i>
                        <?php echo htmlspecialchars($userName); ?>
                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($userRole); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?php echo htmlspecialchars($userName); ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php if (isset($pageHeader) && $pageHeader): ?>
                <?php renderPageHeader($pageTitle, $breadcrumbs ?? []); ?>
            <?php endif; ?>
            
            <!-- Page Content Starts Here -->

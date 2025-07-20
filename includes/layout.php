<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Student Management System'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
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
            --topbar-height: 60px;
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
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] body {
            background-color: var(--dark-color);
            color: var(--light-color);
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-header .logo {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .sidebar.collapsed .sidebar-header .logo-text {
            display: none;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu .menu-item {
            display: block;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1rem;
            margin: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar-menu .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu .menu-item.active {
            background: rgba(255,255,255,0.2);
        }
        
        .sidebar.collapsed .menu-item .menu-text {
            display: none;
        }
        
        /* Top Navigation */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            z-index: 999;
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] .topbar {
            background: var(--light-color);
            border-bottom-color: #334155;
        }
        
        .sidebar.collapsed + .main-content .topbar {
            left: 70px;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .theme-toggle {
            background: none;
            border: none;
            color: var(--secondary-color);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .theme-toggle:hover {
            background: var(--light-color);
            color: var(--primary-color);
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding-top: var(--topbar-height);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 70px;
        }
        
        .content-wrapper {
            padding: 2rem;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] .card {
            background: var(--light-color);
            color: white;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card {
            padding: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger-color);
        }
        
        .stat-card.info {
            border-left-color: var(--info-color);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        /* Buttons */
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--dark-color);
            border-color: #374151;
            color: white;
        }
        
        /* Tables */
        .table {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        [data-theme="dark"] .table {
            --bs-table-bg: var(--light-color);
            --bs-table-color: white;
        }
        
        /* Notifications */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .topbar {
                left: 0;
            }
        }
        
        /* Loading Spinner */
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1060;
        }
        
        /* File Upload */
        .file-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.1);
        }
    </style>
</head>
<body>
    <?php if (!isset($hideLayout) || !$hideLayout): ?>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-graduation-cap logo"></i>
            <span class="logo-text">Student MS</span>
        </div>
        
        <div class="sidebar-menu">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            $menuItems = [
                ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'dashboard.php', 'roles' => ['admin', 'training_partner', 'student']],
                ['icon' => 'fas fa-building', 'text' => 'Training Centers', 'url' => 'training-centers.php', 'roles' => ['admin']],
                ['icon' => 'fas fa-users', 'text' => 'Students', 'url' => 'students.php', 'roles' => ['admin', 'training_partner']],
                ['icon' => 'fas fa-money-bill-wave', 'text' => 'Fees', 'url' => 'fees.php', 'roles' => ['admin', 'training_partner']],
                ['icon' => 'fas fa-users-class', 'text' => 'Batches', 'url' => 'batches.php', 'roles' => ['admin', 'training_partner']],
                ['icon' => 'fas fa-clipboard-list', 'text' => 'Assessments', 'url' => 'assessments.php', 'roles' => ['admin', 'training_partner']],
                ['icon' => 'fas fa-chart-line', 'text' => 'Results', 'url' => 'results.php', 'roles' => ['admin', 'training_partner', 'student']],
                ['icon' => 'fas fa-certificate', 'text' => 'Certification', 'url' => 'certification.php', 'roles' => ['admin', 'training_partner', 'student']],
                ['icon' => 'fas fa-cogs', 'text' => 'Masters', 'url' => 'masters.php', 'roles' => ['admin']],
                ['icon' => 'fas fa-file-alt', 'text' => 'Reports', 'url' => 'reports.php', 'roles' => ['admin', 'training_partner']],
                ['icon' => 'fas fa-user-cog', 'text' => 'Settings', 'url' => 'settings.php', 'roles' => ['admin', 'training_partner', 'student']]
            ];
            
            foreach ($menuItems as $item) {
                if (isset($_SESSION['role']) && in_array($_SESSION['role'], $item['roles'])) {
                    $activeClass = ($currentPage === $item['url']) ? 'active' : '';
                    echo "<a href='{$item['url']}' class='menu-item {$activeClass}'>
                            <i class='{$item['icon']}'></i>
                            <span class='menu-text'>{$item['text']}</span>
                          </a>";
                }
            }
            ?>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="topbar">
            <div class="topbar-left">
                <button class="btn btn-link" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0"><?php echo $pageTitle ?? 'Dashboard'; ?></h5>
            </div>
            
            <div class="topbar-right">
                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
                
                <!-- Notifications -->
                <div class="position-relative">
                    <button class="btn btn-link" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationCount" style="display: none;">0</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <div id="notificationsList">
                            <li><span class="dropdown-item-text">No new notifications</span></li>
                        </div>
                    </ul>
                </div>
                
                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo $_SESSION['name'] ?? 'User'; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Content Area -->
        <div class="content-wrapper">
    <?php endif; ?>
    
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
        // Theme Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const html = document.documentElement;
            
            // Load saved theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            html.setAttribute('data-theme', savedTheme);
            updateThemeIcon(savedTheme);
            
            themeToggle?.addEventListener('click', function() {
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
            });
            
            function updateThemeIcon(theme) {
                if (themeIcon) {
                    themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                }
            }
            
            // Sidebar Toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            sidebarToggle?.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
            
            // Load saved sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar?.classList.add('collapsed');
            }
            
            // Mobile sidebar toggle
            if (window.innerWidth <= 768) {
                sidebarToggle?.addEventListener('click', function() {
                    sidebar?.classList.toggle('mobile-open');
                });
            }
            
            // Load notifications
            loadNotifications();
        });
        
        // Utility Functions
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            
            const toastHtml = `
                <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <i class="fas fa-${getToastIcon(type)} me-2 text-${type}"></i>
                        <strong class="me-auto">Notification</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toast = new bootstrap.Toast(document.getElementById(toastId));
            toast.show();
            
            // Remove toast element after it's hidden
            document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }
        
        function getToastIcon(type) {
            const icons = {
                'success': 'check-circle',
                'error': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }
        
        function loadNotifications() {
            fetch('api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotifications(data.notifications);
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }
        
        function updateNotifications(notifications) {
            const notificationCount = document.getElementById('notificationCount');
            const notificationsList = document.getElementById('notificationsList');
            
            if (notifications.length > 0) {
                notificationCount.textContent = notifications.length;
                notificationCount.style.display = 'flex';
                
                notificationsList.innerHTML = notifications.map(notification => `
                    <li>
                        <a class="dropdown-item" href="#" onclick="markAsRead(${notification.id})">
                            <strong>${notification.title}</strong>
                            <br><small class="text-muted">${notification.message}</small>
                        </a>
                    </li>
                `).join('');
            } else {
                notificationCount.style.display = 'none';
                notificationsList.innerHTML = '<li><span class="dropdown-item-text">No new notifications</span></li>';
            }
        }
        
        function markAsRead(notificationId) {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        }
        
        // Form validation helper
        function validateForm(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        // Date formatting helper
        function formatDate(dateString, format = 'DD-MM-YYYY') {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            
            switch (format) {
                case 'DD-MM-YYYY':
                    return `${day}-${month}-${year}`;
                case 'MM-DD-YYYY':
                    return `${month}-${day}-${year}`;
                case 'YYYY-MM-DD':
                    return `${year}-${month}-${day}`;
                default:
                    return `${day}-${month}-${year}`;
            }
        }
        
        // File upload helper
        function setupFileUpload(inputId, allowedTypes = ['image/*'], maxSize = 2048) {
            const input = document.getElementById(inputId);
            const uploadArea = input.closest('.file-upload-area');
            
            if (uploadArea) {
                uploadArea.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                uploadArea.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                uploadArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        input.files = files;
                        validateFile(input, allowedTypes, maxSize);
                    }
                });
                
                uploadArea.addEventListener('click', function() {
                    input.click();
                });
            }
            
            input.addEventListener('change', function() {
                validateFile(this, allowedTypes, maxSize);
            });
        }
        
        function validateFile(input, allowedTypes, maxSize) {
            const file = input.files[0];
            if (!file) return;
            
            // Check file type
            const isValidType = allowedTypes.some(type => {
                if (type.endsWith('/*')) {
                    return file.type.startsWith(type.slice(0, -2));
                }
                return file.type === type;
            });
            
            if (!isValidType) {
                showToast('Invalid file type. Please select a valid file.', 'error');
                input.value = '';
                return false;
            }
            
            // Check file size (in KB)
            if (file.size > maxSize * 1024) {
                showToast(`File size must be less than ${maxSize}KB.`, 'error');
                input.value = '';
                return false;
            }
            
            return true;
        }
    </script>

<?php
// Render Header Function
function renderHeader() {
    global $currentUser, $pageTitle;
    ?>
    <!-- Header/Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                Student Management System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php
}

// Render Sidebar Function
function renderSidebar($activePage = '') {
    global $currentUser;
    
    $menuItems = [
        ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'dashboard.php', 'roles' => ['admin', 'training_partner', 'student']],
        ['icon' => 'fas fa-building', 'text' => 'Training Centers', 'url' => 'training-centers.php', 'roles' => ['admin']],
        ['icon' => 'fas fa-users', 'text' => 'Students', 'url' => 'students.php', 'roles' => ['admin', 'training_partner']],
        ['icon' => 'fas fa-money-bill-wave', 'text' => 'Fees', 'url' => 'fees.php', 'roles' => ['admin', 'training_partner']],
        ['icon' => 'fas fa-users-class', 'text' => 'Batches', 'url' => 'batches.php', 'roles' => ['admin', 'training_partner']],
        ['icon' => 'fas fa-clipboard-list', 'text' => 'Assessments', 'url' => 'assessments.php', 'roles' => ['admin', 'training_partner']],
        ['icon' => 'fas fa-chart-line', 'text' => 'Results', 'url' => 'results.php', 'roles' => ['admin', 'training_partner', 'student']],
        ['icon' => 'fas fa-certificate', 'text' => 'Certification', 'url' => 'certification.php', 'roles' => ['admin', 'training_partner', 'student']],
        ['icon' => 'fas fa-cogs', 'text' => 'Masters', 'url' => 'masters.php', 'roles' => ['admin']],
        ['icon' => 'fas fa-file-alt', 'text' => 'Reports', 'url' => 'reports.php', 'roles' => ['admin', 'training_partner']],
        ['icon' => 'fas fa-user-cog', 'text' => 'Settings', 'url' => 'settings.php', 'roles' => ['admin', 'training_partner', 'student']]
    ];
    ?>
    
    <!-- Sidebar -->
    <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" style="margin-top: 56px;">
        <div class="position-sticky pt-3">
            <ul class="nav flex-column">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (in_array($currentUser['role'], $item['roles'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($activePage === str_replace('.php', '', $item['url'])) ? 'active' : ''; ?>" 
                               href="<?php echo $item['url']; ?>">
                                <i class="<?php echo $item['icon']; ?> me-2"></i>
                                <?php echo $item['text']; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
    <?php
}
?>

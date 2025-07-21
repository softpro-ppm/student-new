<?php
/**
 * Modern Layout System for Student Management System
 * Features: Responsive design, modern UI, Bootstrap 5
 */

function renderHeader($pageTitle = 'Student Management System', $includeAuth = true) {
    $user = getCurrentUser();
    $userRole = getCurrentUserRole();
    $userName = getCurrentUserName();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($pageTitle); ?></title>
        
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Font Awesome Icons -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        
        <!-- DataTables CSS -->
        <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
        
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <!-- Cropper.js for image cropping -->
        <link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.js"></script>
        
        <!-- Custom Styles -->
        <style>
            :root {
                --primary-color: #3498db;
                --primary-dark: #2980b9;
                --secondary-color: #2ecc71;
                --secondary-dark: #27ae60;
                --accent-color: #e67e22;
                --accent-dark: #d35400;
                --dark-color: #2c3e50;
                --light-color: #ecf0f1;
                --gray-color: #95a5a6;
                --danger-color: #e74c3c;
                --warning-color: #f39c12;
                --info-color: #3498db;
                --success-color: #2ecc71;
                
                --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                --gradient-secondary: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
                --gradient-accent: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
                
                --sidebar-width: 280px;
                --header-height: 70px;
                --border-radius: 12px;
                --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f8f9fa;
                color: var(--dark-color);
                line-height: 1.6;
            }

            /* Header Styles */
            .header {
                background: white;
                height: var(--header-height);
                box-shadow: var(--box-shadow);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1030;
                display: flex;
                align-items: center;
                padding: 0 20px;
                border-bottom: 1px solid #e9ecef;
            }

            .header-brand {
                display: flex;
                align-items: center;
                font-weight: bold;
                color: var(--primary-color);
                text-decoration: none;
                margin-right: auto;
                font-size: 1.25rem;
            }

            .header-brand:hover {
                color: var(--primary-dark);
                text-decoration: none;
            }

            .header-brand i {
                margin-right: 10px;
                font-size: 1.5rem;
            }

            .header-controls {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .notification-bell {
                position: relative;
                color: var(--gray-color);
                font-size: 1.2rem;
                transition: var(--transition);
            }

            .notification-bell:hover {
                color: var(--primary-color);
            }

            .notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                background: var(--danger-color);
                color: white;
                border-radius: 50%;
                width: 18px;
                height: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.75rem;
                font-weight: bold;
            }

            .user-dropdown {
                display: flex;
                align-items: center;
                padding: 8px 12px;
                border-radius: var(--border-radius);
                transition: var(--transition);
                text-decoration: none;
                color: var(--dark-color);
            }

            .user-dropdown:hover {
                background: var(--light-color);
                color: var(--dark-color);
                text-decoration: none;
            }

            .user-avatar {
                width: 35px;
                height: 35px;
                border-radius: 50%;
                background: var(--gradient-primary);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                margin-right: 10px;
            }

            /* Sidebar Styles */
            .sidebar {
                position: fixed;
                top: var(--header-height);
                left: 0;
                width: var(--sidebar-width);
                height: calc(100vh - var(--header-height));
                background: white;
                box-shadow: var(--box-shadow);
                overflow-y: auto;
                transition: var(--transition);
                z-index: 1020;
                border-right: 1px solid #e9ecef;
            }

            .sidebar-nav {
                padding: 20px 0;
            }

            .nav-item {
                margin: 2px 15px;
            }

            .nav-link {
                display: flex;
                align-items: center;
                padding: 12px 20px;
                color: var(--dark-color);
                text-decoration: none;
                border-radius: var(--border-radius);
                transition: var(--transition);
                font-weight: 500;
            }

            .nav-link:hover {
                background: var(--light-color);
                color: var(--primary-color);
                text-decoration: none;
                transform: translateX(5px);
            }

            .nav-link.active {
                background: var(--gradient-primary);
                color: white;
                box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            }

            .nav-link i {
                width: 20px;
                margin-right: 12px;
                text-align: center;
            }

            .nav-badge {
                margin-left: auto;
                background: var(--secondary-color);
                color: white;
                padding: 2px 8px;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: bold;
            }

            /* Main Content Styles */
            .main-content {
                margin-left: var(--sidebar-width);
                margin-top: var(--header-height);
                padding: 30px;
                min-height: calc(100vh - var(--header-height));
            }

            .page-header {
                background: white;
                padding: 25px 30px;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                margin-bottom: 30px;
                border-left: 4px solid var(--primary-color);
            }

            .page-title {
                font-size: 1.75rem;
                font-weight: 700;
                color: var(--dark-color);
                margin: 0;
                display: flex;
                align-items: center;
            }

            .page-title i {
                margin-right: 15px;
                color: var(--primary-color);
            }

            .page-subtitle {
                color: var(--gray-color);
                margin: 5px 0 0 0;
                font-size: 1rem;
            }

            /* Card Styles */
            .card {
                border: none;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                transition: var(--transition);
            }

            .card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            }

            .card-header {
                background: transparent;
                border-bottom: 1px solid #e9ecef;
                padding: 20px 25px;
                font-weight: 600;
            }

            .card-body {
                padding: 25px;
            }

            /* Button Styles */
            .btn {
                border-radius: var(--border-radius);
                font-weight: 600;
                padding: 10px 20px;
                transition: var(--transition);
                border: none;
            }

            .btn-primary {
                background: var(--gradient-primary);
                border: none;
            }

            .btn-primary:hover {
                background: var(--gradient-primary);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            }

            .btn-success {
                background: var(--gradient-secondary);
                border: none;
            }

            .btn-success:hover {
                background: var(--gradient-secondary);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4);
            }

            .btn-warning {
                background: var(--gradient-accent);
                border: none;
                color: white;
            }

            .btn-warning:hover {
                background: var(--gradient-accent);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(230, 126, 34, 0.4);
                color: white;
            }

            /* Form Styles */
            .form-control {
                border-radius: var(--border-radius);
                border: 2px solid #e9ecef;
                transition: var(--transition);
                padding: 12px 15px;
            }

            .form-control:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            }

            .form-select {
                border-radius: var(--border-radius);
                border: 2px solid #e9ecef;
                transition: var(--transition);
                padding: 12px 15px;
            }

            .form-select:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            }

            /* Modal Styles */
            .modal-content {
                border-radius: var(--border-radius);
                border: none;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            }

            .modal-header {
                border-bottom: 1px solid #e9ecef;
                padding: 20px 25px;
            }

            .modal-body {
                padding: 25px;
            }

            .modal-footer {
                border-top: 1px solid #e9ecef;
                padding: 20px 25px;
            }

            /* Statistics Cards */
            .stats-card {
                background: white;
                border-radius: var(--border-radius);
                padding: 25px;
                box-shadow: var(--box-shadow);
                transition: var(--transition);
                border-left: 4px solid transparent;
            }

            .stats-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            }

            .stats-card.primary {
                border-left-color: var(--primary-color);
            }

            .stats-card.success {
                border-left-color: var(--secondary-color);
            }

            .stats-card.warning {
                border-left-color: var(--accent-color);
            }

            .stats-card.danger {
                border-left-color: var(--danger-color);
            }

            .stats-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                color: white;
                margin-bottom: 15px;
            }

            .stats-icon.primary {
                background: var(--gradient-primary);
            }

            .stats-icon.success {
                background: var(--gradient-secondary);
            }

            .stats-icon.warning {
                background: var(--gradient-accent);
            }

            .stats-icon.danger {
                background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            }

            .stats-number {
                font-size: 2.5rem;
                font-weight: 700;
                color: var(--dark-color);
                line-height: 1;
            }

            .stats-label {
                color: var(--gray-color);
                font-weight: 500;
                text-transform: uppercase;
                font-size: 0.875rem;
                letter-spacing: 0.5px;
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
                    padding: 20px 15px;
                }

                .page-header {
                    padding: 20px;
                }

                .page-title {
                    font-size: 1.5rem;
                }

                .header-brand {
                    font-size: 1.1rem;
                }
            }

            /* DataTables Customization */
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                margin-bottom: 15px;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 8px 12px;
                margin: 0 2px;
                border-radius: var(--border-radius);
                border: 1px solid #e9ecef;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                background: var(--primary-color);
                color: white !important;
                border-color: var(--primary-color);
            }

            /* Alert Styles */
            .alert {
                border-radius: var(--border-radius);
                border: none;
                padding: 15px 20px;
                box-shadow: var(--box-shadow);
            }

            /* Loading Spinner */
            .loading-spinner {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
            }

            .spinner-border {
                color: var(--primary-color);
            }

            /* Custom Utilities */
            .text-primary { color: var(--primary-color) !important; }
            .text-secondary { color: var(--secondary-color) !important; }
            .text-accent { color: var(--accent-color) !important; }
            .bg-primary-gradient { background: var(--gradient-primary) !important; }
            .bg-secondary-gradient { background: var(--gradient-secondary) !important; }
            .bg-accent-gradient { background: var(--gradient-accent) !important; }
        </style>
    </head>
    <body>
        <!-- Header -->
        <header class="header">
            <a href="dashboard.php" class="header-brand">
                <i class="fas fa-graduation-cap"></i>
                Student Management System
            </a>
            
            <div class="header-controls">
                <!-- Mobile Sidebar Toggle -->
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Notifications -->
                <div class="dropdown">
                    <a href="#" class="notification-bell" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#">New student registration</a></li>
                        <li><a class="dropdown-item" href="#">Payment received</a></li>
                        <li><a class="dropdown-item" href="#">Assessment completed</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">View all notifications</a></li>
                    </ul>
                </div>
                
                <!-- User Dropdown -->
                <div class="dropdown">
                    <a href="#" class="user-dropdown" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($userName, 0, 1)); ?>
                        </div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($userName); ?></div>
                            <small class="text-muted"><?php echo ucfirst($userRole); ?></small>
                        </div>
                        <i class="fas fa-chevron-down ms-2"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Account</h6></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
    <?php
}

function renderSidebar($activePage = '') {
    $userRole = getCurrentUserRole();
    ?>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-nav">
            <!-- Dashboard -->
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>

            <?php if (in_array($userRole, ['admin', 'training_partner'])): ?>
            <!-- Students Management -->
            <div class="nav-item">
                <a href="students.php" class="nav-link <?php echo $activePage === 'students' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i>
                    Students
                    <span class="nav-badge">New</span>
                </a>
            </div>

            <!-- Bulk Upload -->
            <div class="nav-item">
                <a href="bulk-upload.php" class="nav-link <?php echo $activePage === 'bulk-upload' ? 'active' : ''; ?>">
                    <i class="fas fa-upload"></i>
                    Bulk Upload
                </a>
            </div>
            <?php endif; ?>

            <?php if ($userRole === 'admin'): ?>
            <!-- Training Centers -->
            <div class="nav-item">
                <a href="training-centers.php" class="nav-link <?php echo $activePage === 'training-centers' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    Training Centers
                </a>
            </div>
            <?php endif; ?>

            <!-- Batches -->
            <div class="nav-item">
                <a href="batches.php" class="nav-link <?php echo $activePage === 'batches' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    Batches
                </a>
            </div>

            <!-- Assessments -->
            <div class="nav-item">
                <a href="assessments.php" class="nav-link <?php echo $activePage === 'assessments' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>
                    Assessments
                </a>
            </div>

            <!-- Results -->
            <div class="nav-item">
                <a href="results.php" class="nav-link <?php echo $activePage === 'results' ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i>
                    Results
                </a>
            </div>

            <!-- Fees Management -->
            <div class="nav-item">
                <a href="fees.php" class="nav-link <?php echo $activePage === 'fees' ? 'active' : ''; ?>">
                    <i class="fas fa-rupee-sign"></i>
                    Fees
                </a>
            </div>

            <!-- Certificates -->
            <div class="nav-item">
                <a href="certificates.php" class="nav-link <?php echo $activePage === 'certificates' ? 'active' : ''; ?>">
                    <i class="fas fa-certificate"></i>
                    Certificates
                </a>
            </div>

            <?php if ($userRole === 'admin'): ?>
            <!-- Masters Data -->
            <div class="nav-item">
                <a href="masters.php" class="nav-link <?php echo $activePage === 'masters' ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i>
                    Masters
                </a>
            </div>
            <?php endif; ?>

            <!-- Reports -->
            <div class="nav-item">
                <a href="reports.php" class="nav-link <?php echo $activePage === 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
            </div>

            <?php if ($userRole === 'admin'): ?>
            <!-- Settings -->
            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo $activePage === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
            <?php endif; ?>

            <!-- Verification -->
            <div class="nav-item">
                <a href="verify.php" class="nav-link <?php echo $activePage === 'verify' ? 'active' : ''; ?>" target="_blank">
                    <i class="fas fa-check-circle"></i>
                    Verify Certificate
                </a>
            </div>
        </div>
    </nav>
    <?php
}

function renderFooter() {
    ?>
        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- JavaScript Libraries -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

        <!-- Custom JavaScript -->
        <script>
            // Global Variables
            const userRole = '<?php echo getCurrentUserRole(); ?>';
            const userId = '<?php echo getCurrentUser()['id'] ?? ''; ?>';

            // Sidebar Toggle for Mobile
            document.getElementById('sidebarToggle')?.addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('show');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.getElementById('sidebarToggle');
                
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });

            // Loading Spinner Functions
            function showLoading() {
                document.getElementById('loadingSpinner').style.display = 'block';
            }

            function hideLoading() {
                document.getElementById('loadingSpinner').style.display = 'none';
            }

            // AJAX Setup
            $.ajaxSetup({
                beforeSend: function() {
                    showLoading();
                },
                complete: function() {
                    hideLoading();
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('An error occurred. Please try again.');
                }
            });

            // DataTables Default Configuration
            $.extend(true, $.fn.dataTable.defaults, {
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, "All"]],
                order: [[0, 'desc']],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    },
                    emptyTable: "No data available",
                    zeroRecords: "No matching records found"
                },
                dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-5'i><'col-sm-7'p>>",
            });

            // Form Validation Helper
            function validateForm(form) {
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                return isValid;
            }

            // Toast Notification Function
            function showToast(message, type = 'success') {
                const toastHtml = `
                    <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `;
                
                // Create toast container if it doesn't exist
                let toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.id = 'toastContainer';
                    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                    toastContainer.style.zIndex = '9999';
                    document.body.appendChild(toastContainer);
                }
                
                // Add toast to container
                toastContainer.insertAdjacentHTML('beforeend', toastHtml);
                
                // Initialize and show toast
                const toastElement = toastContainer.lastElementChild;
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
                
                // Remove toast element after it's hidden
                toastElement.addEventListener('hidden.bs.toast', () => {
                    toastElement.remove();
                });
            }

            // Confirm Dialog Helper
            function confirmAction(message, callback) {
                if (confirm(message)) {
                    callback();
                }
            }

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            console.log('Modern Student Management System - Layout Loaded');
        </script>
    </body>
    </html>
    <?php
}
?>

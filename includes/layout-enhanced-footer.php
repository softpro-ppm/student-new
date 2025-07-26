        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        © <?php echo date('Y'); ?> Student Management System. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted mb-0">
                        Version 2.0 | Built with ❤️
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center" style="z-index: 9999; display: none !important;">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Please wait...</p>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Global variables
        window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        window.userRole = '<?php echo $userRole; ?>';
        
        // Theme management
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update theme toggle icon
            const icon = document.querySelector('.theme-toggle i');
            icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Update theme toggle icon
            const icon = document.querySelector('.theme-toggle i');
            if (icon) {
                icon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
        
        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.querySelector('[onclick="toggleSidebar()"]');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggleBtn.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Loading overlay functions
        function showLoading(message = 'Please wait...') {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
                const messageEl = overlay.querySelector('p');
                if (messageEl) messageEl.textContent = message;
            }
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        // Toast notification function
        function showToast(message, type = 'info', duration = 5000) {
            const toastHtml = `
                <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            const toastContainer = document.querySelector('.toast-container');
            if (toastContainer) {
                const toastElement = document.createElement('div');
                toastElement.innerHTML = toastHtml;
                toastContainer.appendChild(toastElement.firstElementChild);
                
                const toast = new bootstrap.Toast(toastElement.firstElementChild, {
                    delay: duration
                });
                toast.show();
                
                // Remove from DOM after hiding
                toastElement.firstElementChild.addEventListener('hidden.bs.toast', function() {
                    this.remove();
                });
            }
        }
        
        // AJAX helper with CSRF protection
        function ajaxRequest(url, options = {}) {
            const defaultOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.csrfToken
                }
            };
            
            return fetch(url, { ...defaultOptions, ...options })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    showToast('An error occurred. Please try again.', 'danger');
                    throw error;
                });
        }
        
        // Initialize DataTables with default settings
        function initDataTable(selector, options = {}) {
            const defaultOptions = {
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search records...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries available",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            };
            
            return $(selector).DataTable({ ...defaultOptions, ...options });
        }
        
        // Initialize Select2 with default settings
        function initSelect2(selector, options = {}) {
            const defaultOptions = {
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Select an option...',
                allowClear: true
            };
            
            return $(selector).select2({ ...defaultOptions, ...options });
        }
        
        // Form validation helper
        function validateForm(formElement) {
            let isValid = true;
            const requiredFields = formElement.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                const value = field.value.trim();
                const fieldContainer = field.closest('.mb-3') || field.closest('.form-group');
                
                // Remove existing validation classes
                field.classList.remove('is-valid', 'is-invalid');
                const feedback = fieldContainer.querySelector('.invalid-feedback');
                if (feedback) feedback.remove();
                
                if (!value) {
                    field.classList.add('is-invalid');
                    isValid = false;
                    
                    // Add error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'This field is required.';
                    field.parentNode.appendChild(errorDiv);
                } else {
                    field.classList.add('is-valid');
                }
            });
            
            return isValid;
        }
        
        // Confirm dialog helper
        function confirmAction(title, text, icon = 'warning') {
            return Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'Cancel'
            });
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Auto-refresh functionality (optional)
        let autoRefreshInterval;
        function startAutoRefresh(callback, interval = 30000) {
            autoRefreshInterval = setInterval(callback, interval);
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        // Page visibility API to pause/resume auto-refresh
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else if (typeof window.resumeAutoRefresh === 'function') {
                window.resumeAutoRefresh();
            }
        });
    </script>
    
    <?php if (isset($additionalJS)): ?>
    <script><?php echo $additionalJS; ?></script>
    <?php endif; ?>
    
    <?php if (isset($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
    <script src="<?php echo htmlspecialchars($script); ?>"></script>
    <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

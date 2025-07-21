<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/auth.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            $result = $auth->login($username, $password);
            
            if ($result['success']) {
                header('Location: dashboard.php');
                exit();
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}

$hideLayout = true;
$pageTitle = 'Login - Student Management System';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        
        .login-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 2rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header .logo {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .login-header h2 {
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            padding: 1rem 0.75rem;
            height: auto;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .role-info {
            background: #f3f4f6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.875rem;
        }
        
        .role-info h6 {
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .role-info ul {
            margin: 0;
            padding-left: 1rem;
            color: #6b7280;
        }
        
        .demo-credentials {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        
        .demo-credentials h6 {
            color: #92400e;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .demo-credentials .credential {
            background: white;
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin: 0.25rem 0;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to your Student Management System account</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Debug Info (remove in production) -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="alert alert-info" role="alert">
                    <h6>Debug Information:</h6>
                    <small>
                        Session Status: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?><br>
                        Auth Class: <?php echo class_exists('Auth') ? 'Loaded' : 'Not Found'; ?><br>
                        POST Data: <?php echo $_SERVER['REQUEST_METHOD'] === 'POST' ? 'Received' : 'None'; ?><br>
                        Database: <?php 
                            try {
                                $db = (new Database())->getConnection();
                                echo $db ? 'Connected' : 'Failed';
                            } catch (Exception $e) {
                                echo 'Error: ' . $e->getMessage();
                            }
                        ?>
                    </small>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                    <label class="form-check-label" for="rememberMe">
                        Remember me
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
            
            <div class="forgot-password">
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
            
            <div class="demo-credentials">
                <h6><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                <div class="credential">
                    <span><strong>Admin:</strong> admin / admin123</span>
                </div>
                <div class="credential">
                    <span><strong>Student:</strong> 9999999999 / softpro@123</span>
                </div>
            </div>
            
            <div class="role-info">
                <h6><i class="fas fa-users me-2"></i>User Roles</h6>
                <ul>
                    <li><strong>Admin:</strong> Full system access and management</li>
                    <li><strong>Training Partner:</strong> Manage students, batches, and fees</li>
                    <li><strong>Student:</strong> View dashboard, results, and certificates</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Public Verification Link -->
    <div class="text-center mt-3">
        <a href="verify-certificate.php" class="text-white text-decoration-none me-3">
            <i class="fas fa-certificate me-2"></i>Verify Certificate
        </a>
        <?php if (!isset($_GET['debug'])): ?>
            <a href="?debug=1" class="text-white text-decoration-none">
                <i class="fas fa-bug me-2"></i>Debug Mode
            </a>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                showAlert('Please fill in all fields', 'danger');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            submitBtn.disabled = true;
            
            // Re-enable button after 3 seconds if form doesn't submit successfully
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
        
        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : 'check-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            const form = document.getElementById('loginForm');
            form.insertAdjacentHTML('beforebegin', alertHtml);
        }
        
        // Auto-focus on username field
        document.getElementById('username').focus();
        
        // Handle demo credential clicks
        document.querySelectorAll('.credential').forEach(credential => {
            credential.addEventListener('click', function() {
                const text = this.textContent;
                if (text.includes('admin')) {
                    document.getElementById('username').value = 'admin';
                    document.getElementById('password').value = 'admin123';
                } else if (text.includes('9999999999')) {
                    document.getElementById('username').value = '9999999999';
                    document.getElementById('password').value = 'softpro@123';
                }
            });
        });
    </script>
</body>
</html>

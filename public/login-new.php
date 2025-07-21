<?php
/**
 * Modern Student Management System - Enhanced Login
 * Features: Multi-format login, validation, modern UI
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$loginAttempts = $_SESSION['login_attempts'] ?? 0;
$lastAttempt = $_SESSION['last_attempt'] ?? 0;

// Rate limiting - 5 attempts per 15 minutes
if ($loginAttempts >= 5 && (time() - $lastAttempt) < 900) {
    $error = 'Too many login attempts. Please try again in 15 minutes.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Enhanced validation
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email/phone and password.';
    } else {
        // Validate input format
        $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
        $isPhone = preg_match('/^[0-9]{10}$/', $username);
        $isValidUsername = preg_match('/^[a-zA-Z0-9_]{3,}$/', $username);
        
        if (!$isEmail && !$isPhone && !$isValidUsername) {
            $error = 'Please enter a valid email, 10-digit phone number, or username.';
        }
        
        if (!$error) {
            try {
                $db = getConnection();
                
                if (!$db) {
                    $error = 'Database connection failed. Please try again later.';
                } else {
                    $loginSuccess = false;
                    
                    // Try users table first (admin and training partners)
                    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ? OR phone = ?) AND status = 'active'");
                    $stmt->execute([$username, $username, $username]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Success - create session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = $user['role'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['last_activity'] = time();
                        
                        // Reset login attempts
                        unset($_SESSION['login_attempts'], $_SESSION['last_attempt']);
                        
                        $loginSuccess = true;
                    } else {
                        // Try training_centers table
                        $stmt = $db->prepare("SELECT * FROM training_centers WHERE (email = ? OR phone = ?) AND status = 'active'");
                        $stmt->execute([$username, $username]);
                        $center = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($center && isset($center['password']) && password_verify($password, $center['password'])) {
                            // Success - create session for training center
                            $_SESSION['user_id'] = $center['id'];
                            $_SESSION['user_type'] = 'training_partner';
                            $_SESSION['user_name'] = $center['name'];
                            $_SESSION['user_email'] = $center['email'];
                            $_SESSION['training_center_id'] = $center['id'];
                            $_SESSION['logged_in'] = true;
                            $_SESSION['last_activity'] = time();
                            
                            // Reset login attempts
                            unset($_SESSION['login_attempts'], $_SESSION['last_attempt']);
                            
                            $loginSuccess = true;
                        } else {
                            // Try students table
                            $stmt = $db->prepare("SELECT * FROM students WHERE (email = ? OR phone = ? OR enrollment_no = ?) AND status = 'active'");
                            $stmt->execute([$username, $username, $username]);
                            $student = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($student && isset($student['password']) && password_verify($password, $student['password'])) {
                                // Success - create session for student
                                $_SESSION['user_id'] = $student['id'];
                                $_SESSION['user_type'] = 'student';
                                $_SESSION['user_name'] = $student['name'];
                                $_SESSION['user_email'] = $student['email'];
                                $_SESSION['student_id'] = $student['id'];
                                $_SESSION['logged_in'] = true;
                                $_SESSION['last_activity'] = time();
                                
                                // Reset login attempts
                                unset($_SESSION['login_attempts'], $_SESSION['last_attempt']);
                                
                                $loginSuccess = true;
                            }
                        }
                    }
                    
                    if ($loginSuccess) {
                        // Redirect based on role
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        // Track failed attempts
                        $_SESSION['login_attempts'] = $loginAttempts + 1;
                        $_SESSION['last_attempt'] = time();
                        $error = 'Invalid credentials. Please check your username/email/phone and password.';
                    }
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'Login failed. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e67e22;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --gradient-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: var(--gradient-bg);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-bg);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .logo-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .form-floating > .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-floating > .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-login {
            background: var(--gradient-bg);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: var(--dark-color);
        }
        
        .demo-credentials {
            background: rgba(52, 152, 219, 0.1);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 12px 0 0 12px;
        }
        
        .form-control.with-icon {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 5;
        }
        
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3 class="mb-0 fw-bold text-dark">Student Management</h3>
                <p class="text-muted mb-0">System</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control with-icon" 
                               id="username" 
                               name="username" 
                               placeholder="Email, Phone, or Username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               required
                               autocomplete="username">
                    </div>
                    <div class="form-text">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Enter your email, 10-digit phone number, or username
                        </small>
                    </div>
                </div>

                <div class="mb-4 position-relative">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control with-icon" 
                               id="password" 
                               name="password" 
                               placeholder="Password"
                               required
                               autocomplete="current-password">
                    </div>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                    </span>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Login
                    </button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="#" class="forgot-password">
                    <i class="fas fa-key me-1"></i>
                    Forgot Password?
                </a>
            </div>

            <div class="demo-credentials">
                <h6 class="mb-2">
                    <i class="fas fa-info-circle me-2"></i>
                    Demo Credentials
                </h6>
                <div class="row g-2">
                    <div class="col-12">
                        <small><strong>Admin:</strong> admin / admin123</small>
                    </div>
                    <div class="col-12">
                        <small><strong>Student:</strong> 9999999999 / student123</small>
                    </div>
                    <div class="col-12">
                        <small><strong>Training Center:</strong> tc@example.com / tc123</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return;
            }
            
            // Basic format validation
            const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(username);
            const isPhone = /^[0-9]{10}$/.test(username);
            const isUsername = /^[a-zA-Z0-9_]{3,}$/.test(username);
            
            if (!isEmail && !isPhone && !isUsername) {
                e.preventDefault();
                alert('Please enter a valid email, 10-digit phone number, or username.');
                return;
            }
        });

        // Auto-focus on first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            
            if (!username.value) {
                username.focus();
            } else {
                password.focus();
            }
        });
    </script>
</body>
</html>

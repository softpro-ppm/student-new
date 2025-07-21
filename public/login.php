<?php

echo  "Hello, this is a login page!";  die;

session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            $error = 'Database connection failed. Please try again later.';
        } else {
            try {
                // First try users table for admin and training partners
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION['user'] = $user;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: dashboard.php');
                            break;
                        case 'training_partner':
                            header('Location: dashboard.php');
                            break;
                        case 'student':
                            header('Location: dashboard.php');
                            break;
                        default:
                            header('Location: dashboard.php');
                    }
                    exit();
                } else {
                    // Also check training_centers table for direct login
                    $stmt = $db->prepare("SELECT * FROM training_centers WHERE email = ?");
                    $stmt->execute([$username]);
                    $tc = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($tc && password_verify($password, $tc['password'])) {
                        // Create session for training center
                        $_SESSION['user'] = [
                            'id' => $tc['id'],
                            'username' => $tc['email'],
                            'email' => $tc['email'],
                            'name' => $tc['name'],
                            'role' => 'training_partner',
                            'training_center_id' => $tc['id']
                        ];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['user_role'] = 'training_partner';
                        
                        header('Location: dashboard.php');
                        exit();
                    }
                    
                    // Check students table
                    $stmt = $db->prepare("SELECT * FROM students WHERE phone = ? OR email = ?");
                    $stmt->execute([$username, $username]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($student) {
                        // For students, we'll use a simple password system or check user table
                        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                        $stmt->execute([$student['phone'], $student['email']]);
                        $studentUser = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($studentUser && password_verify($password, $studentUser['password'])) {
                            $_SESSION['user'] = $studentUser;
                            $_SESSION['logged_in'] = true;
                            $_SESSION['user_role'] = 'student';
                            
                            header('Location: dashboard.php');
                            exit();
                        }
                    }
                    
                    $error = 'Invalid username or password.';
                }
            } catch (Exception $e) {
                if ($debug_mode) {
                    $error = 'Database error: ' . $e->getMessage();
                } else {
                    $error = 'Login failed. Please try again.';
                }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-image {
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
            text-align: center;
            padding: 3rem;
        }
        
        .login-image i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .login-form {
            padding: 3rem;
        }
        
        .form-control {
            border: none;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
            padding: 0.75rem 0;
            font-size: 1rem;
            background-color: transparent;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
            background-color: transparent;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: transform 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(45deg, #5a6fd8, #6a42a0);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            border-left: 4px solid #dc3545;
        }
        
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .demo-credentials h6 {
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .credential-item:hover {
            background: #e9ecef;
        }
        
        .credential-item:last-child {
            margin-bottom: 0;
        }
        
        .badge-role {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .login-image {
                display: none;
            }
            
            .login-form {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="login-container row g-0">
                    <!-- Left Side - Image/Branding -->
                    <div class="col-lg-6 login-image">
                        <i class="fas fa-graduation-cap"></i>
                        <h2 class="mb-3">Welcome Back!</h2>
                        <p class="lead">Student Management System</p>
                        <p>Manage students, training centers, courses, and more with our comprehensive platform.</p>
                    </div>
                    
                    <!-- Right Side - Login Form -->
                    <div class="col-lg-6">
                        <div class="login-form">
                            <div class="text-center mb-4">
                                <h3 class="fw-bold text-dark">Sign In</h3>
                                <p class="text-muted">Enter your credentials to access your account</p>
                            </div>
                            
                            <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-2"></i>Username / Email / Phone
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                           required autocomplete="username">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required autocomplete="current-password">
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-login">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Sign In
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Demo Credentials -->
                            <div class="demo-credentials">
                                <h6><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                                
                                <div class="credential-item" onclick="fillCredentials('admin', 'admin123')">
                                    <div>
                                        <strong>Administrator</strong><br>
                                        <small class="text-muted">admin / admin123</small>
                                    </div>
                                    <span class="badge bg-danger badge-role">Admin</span>
                                </div>
                                
                                <div class="credential-item" onclick="fillCredentials('tc001', 'tc123')">
                                    <div>
                                        <strong>Training Center</strong><br>
                                        <small class="text-muted">tc001 / tc123</small>
                                    </div>
                                    <span class="badge bg-warning badge-role">Partner</span>
                                </div>
                                
                                <div class="credential-item" onclick="fillCredentials('9999999999', 'student123')">
                                    <div>
                                        <strong>Student</strong><br>
                                        <small class="text-muted">9999999999 / student123</small>
                                    </div>
                                    <span class="badge bg-info badge-role">Student</span>
                                </div>
                            </div>
                            
                            <?php if ($debug_mode): ?>
                            <div class="debug-info">
                                <strong>Debug Mode:</strong> Database connection and error details are shown.<br>
                                <small>Remove ?debug=1 from URL to disable debug mode.</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    Secure login powered by Student Management System
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Add visual feedback
            const credentialItems = document.querySelectorAll('.credential-item');
            credentialItems.forEach(item => {
                item.style.background = 'white';
            });
            event.target.closest('.credential-item').style.background = '#e3f2fd';
            
            // Focus on submit button
            setTimeout(() => {
                document.querySelector('.btn-login').focus();
            }, 100);
        }
        
        // Auto-fill admin credentials if no username is set
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (!usernameField.value.trim()) {
                fillCredentials('admin', 'admin123');
            }
        });
        
        // Form submission enhancement
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('.btn-login');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>

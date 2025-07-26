<?php
// Modern Student Management System - Enhanced Login
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Prevent table creation during login
$_SESSION['skip_table_creation'] = true;

// Include required files
require_once '../config/database-simple.php';
require_once '../includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard-v2.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Enhanced validation
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        // Email format validation
        if (filter_var($username, FILTER_VALIDATE_EMAIL) === false && !preg_match('/^[0-9]{10}$/', $username)) {
            if (strlen($username) < 3) {
                $error = 'Please enter a valid email, phone number, or username.';
            }
        }
        
        if (!$error) {
            try {
                $db = getConnection();
                
                if (!$db) {
                    $error = 'Database connection failed. Please try again later.';
                } else {
                    // Check if required tables exist
                    $tables = ['users', 'training_centers', 'students'];
                    $missingTables = [];
                    
                    foreach ($tables as $table) {
                        $stmt = $db->query("SHOW TABLES LIKE '$table'");
                        if ($stmt->rowCount() == 0) {
                            $missingTables[] = $table;
                        }
                    }
                    
                    if (!empty($missingTables)) {
                        $error = 'Database not properly set up. Missing tables: ' . implode(', ', $missingTables) . '. <a href="setup-database-complete.php" class="text-decoration-none">Click here to set up the database</a>';
                    } else {
                        // Try users table first (admin and training partners)
                        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ?");
                        $stmt->execute([$username, $username, $username]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user && password_verify($password, $user['password'])) {
                            // Set session variables
                            $_SESSION['logged_in'] = true;
                            $_SESSION['user'] = $user;
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['full_name'] ?? $user['name'] ?? $user['username'];
                            
                            header('Location: dashboard-v2.php');
                            exit();
                        } else {
                            // Try training_centers table
                            $stmt = $db->prepare("SELECT * FROM training_centers WHERE email = ?");
                            $stmt->execute([$username]);
                            $tc = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($tc && password_verify($password, $tc['password'])) {
                                // Create session for training center
                                $_SESSION['logged_in'] = true;
                                $_SESSION['user'] = [
                                    'id' => $tc['id'],
                                    'username' => $tc['email'],
                                    'email' => $tc['email'],
                                    'full_name' => $tc['center_name'],
                                    'role' => 'training_partner'
                                ];
                                $_SESSION['user_role'] = 'training_partner';
                                $_SESSION['user_id'] = $tc['id'];
                                $_SESSION['user_name'] = $tc['center_name'];
                                
                                header('Location: dashboard-v2.php');
                                exit();
                            } else {
                                // Try students table
                                $stmt = $db->prepare("SELECT * FROM students WHERE phone = ? OR email = ?");
                                $stmt->execute([$username, $username]);
                                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($student && password_verify($password, $student['password'])) {
                                    // Create session for student
                                    $_SESSION['logged_in'] = true;
                                    $_SESSION['user'] = [
                                        'id' => $student['id'],
                                        'username' => $student['email'] ?? $student['phone'],
                                        'email' => $student['email'],
                                        'full_name' => $student['name'],
                                        'role' => 'student'
                                    ];
                                    $_SESSION['user_role'] = 'student';
                                    $_SESSION['user_id'] = $student['id'];
                                    $_SESSION['user_name'] = $student['name'];
                                    
                                    header('Location: dashboard-v2.php');
                                    exit();
                                } else {
                                    $error = 'Invalid username or password.';
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Login failed: ' . $e->getMessage();
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
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            border: 2px solid #e1e5e9;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid #667eea;
        }
        .input-group-text {
            background: transparent;
            border: 2px solid #e1e5e9;
            border-right: none;
            border-radius: 50px 0 0 50px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 50px 50px 0;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-container">
                    <div class="login-header">
                        <h2><i class="fas fa-graduation-cap me-2"></i>Student Management</h2>
                        <p class="mb-0">Sign in to your account</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php 
                                // Allow HTML in error message for setup link
                                if (strpos($error, 'Missing tables') !== false) {
                                    echo $error;
                                } else {
                                    echo htmlspecialchars($error);
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username/Email/Phone</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                           placeholder="Enter username, email, or phone" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>
                            </div>
                        </form>

                        <div class="demo-credentials">
                            <h6><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                            <small>
                                <strong>Admin:</strong> admin / admin123<br>
                                <strong>Student:</strong> 9999999999 / softpro@123<br>
                                <strong>Training Center:</strong> demo@center.com / demo123
                            </small>
                        </div>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <a href="config-check.php" class="text-decoration-none">System Status</a> | 
                                <a href="setup-database-complete.php" class="text-decoration-none">Setup Database</a> |
                                <a href="../" class="text-decoration-none">Home</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
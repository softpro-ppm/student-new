<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function login($username, $password) {
        try {
            // Validate inputs
            if (empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Username and password are required'
                ];
            }
            
            $query = "SELECT id, username, email, password, role, name, phone, status FROM users WHERE username = ? AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['logged_in'] = true;
                    
                    return [
                        'success' => true,
                        'message' => 'Login successful',
                        'user' => $user
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Invalid password'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'User not found or inactive'
                ];
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred. Please try again.'
            ];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ];
        }
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['role'], $roles);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
    
    public function requireRole($roles) {
        $this->requireLogin();
        
        if (!$this->hasRole($roles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'name' => $_SESSION['name'],
                'phone' => $_SESSION['phone']
            ];
        }
        return null;
    }
    
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            // Verify old password
            $query = "SELECT password FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($oldPassword, $user['password'])) {
                    // Validate new password
                    if (!$this->validatePassword($newPassword)) {
                        return [
                            'success' => false,
                            'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, number and special character'
                        ];
                    }
                    
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->execute([$hashedPassword, $userId]);
                    
                    return [
                        'success' => true,
                        'message' => 'Password changed successfully'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    public function validatePassword($password) {
        // Minimum 8 characters, at least one uppercase letter, one lowercase letter, one number and one special character
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
    }
    
    public function forgotPassword($email) {
        try {
            $query = "SELECT id, username, name FROM users WHERE email = ? AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token
                $insertToken = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
                $stmt = $this->db->prepare($insertToken);
                $stmt->execute([$user['id'], $token, $expiry, $token, $expiry]);
                
                // Send email (implement email sending)
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/public/reset-password.php?token=" . $token;
                
                return [
                    'success' => true,
                    'message' => 'Password reset link sent to your email',
                    'reset_link' => $resetLink
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Email not found'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    public function resetPassword($token, $newPassword) {
        try {
            // Check if token exists and is valid
            $query = "SELECT pr.user_id FROM password_resets pr 
                     JOIN users u ON pr.user_id = u.id 
                     WHERE pr.token = ? AND pr.expires_at > NOW() AND u.status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            
            if ($stmt->rowCount() > 0) {
                $reset = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Validate new password
                if (!$this->validatePassword($newPassword)) {
                    return [
                        'success' => false,
                        'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, number and special character'
                    ];
                }
                
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([$hashedPassword, $reset['user_id']]);
                
                // Delete used token
                $deleteToken = "DELETE FROM password_resets WHERE token = ?";
                $deleteStmt = $this->db->prepare($deleteToken);
                $deleteStmt->execute([$token]);
                
                return [
                    'success' => true,
                    'message' => 'Password reset successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}

// Create password resets table
function createPasswordResetsTable() {
    try {
        $db = getConnection();
        
        // Check if users table exists first
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            // Users table doesn't exist, skip creating password_resets
            return true;
        }
        
        $createPasswordResets = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token)
        )";
        
        $db->exec($createPasswordResets);
        return true;
    } catch(PDOException $exception) {
        // Silently fail - don't break the system if table creation fails
        error_log("Password resets table creation error: " . $exception->getMessage());
        return false;
    }
}

// Only create table if not in a critical path
if (!isset($_SESSION['skip_table_creation'])) {
    createPasswordResetsTable();
}

// Helper functions for backward compatibility
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION['user'] ?? [
            'username' => $_SESSION['username'] ?? '',
            'name' => $_SESSION['name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'id' => $_SESSION['user_id'] ?? 0
        ];
    }
    return null;
}

function getCurrentUserRole() {
    if (isLoggedIn()) {
        return $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
    }
    return null;
}

function getCurrentUserName() {
    $user = getCurrentUser();
    if ($user) {
        return $user['name'] ?? $user['username'] ?? 'User';
    }
    return 'Guest';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($roles) {
    requireLogin();
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    $userRole = getCurrentUserRole();
    if (!in_array($userRole, $roles)) {
        header('Location: unauthorized.php');
        exit();
    }
}
?>

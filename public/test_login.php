<?php
// Test script to debug login issues
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>Login Debug Test</h1>";

// Test database connection
try {
    $testQuery = $db->prepare("SELECT 1");
    $testQuery->execute();
    echo "<div style='color: green;'>✓ Database connection successful</div>";
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</div>";
    exit;
}

// Check if users table exists
try {
    $tableCheck = $db->prepare("SHOW TABLES LIKE 'users'");
    $tableCheck->execute();
    if ($tableCheck->rowCount() > 0) {
        echo "<div style='color: green;'>✓ Users table exists</div>";
    } else {
        echo "<div style='color: red;'>✗ Users table does not exist</div>";
        exit;
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error checking users table: " . $e->getMessage() . "</div>";
    exit;
}

// Check for admin user
try {
    $adminCheck = $db->prepare("SELECT id, username, email, role, status FROM users WHERE username = 'admin'");
    $adminCheck->execute();
    $admin = $adminCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<div style='color: green;'>✓ Admin user found</div>";
        echo "<pre>Admin Details: " . print_r($admin, true) . "</pre>";
    } else {
        echo "<div style='color: orange;'>⚠ Admin user not found, creating...</div>";
        
        // Create admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $insertAdmin = $db->prepare("INSERT INTO users (username, email, password, role, name, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $insertAdmin->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 'System Administrator', '9999999999']);
        
        if ($result) {
            echo "<div style='color: green;'>✓ Admin user created successfully</div>";
        } else {
            echo "<div style='color: red;'>✗ Failed to create admin user</div>";
        }
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error checking admin user: " . $e->getMessage() . "</div>";
}

// Check for student user (phone number as username)
try {
    $studentCheck = $db->prepare("SELECT id, username, email, role, status FROM users WHERE username = '9999999999'");
    $studentCheck->execute();
    $student = $studentCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo "<div style='color: green;'>✓ Student user found</div>";
        echo "<pre>Student Details: " . print_r($student, true) . "</pre>";
    } else {
        echo "<div style='color: orange;'>⚠ Student user not found, creating...</div>";
        
        // Create student user
        $studentPassword = password_hash('softpro@123', PASSWORD_DEFAULT);
        $insertStudent = $db->prepare("INSERT INTO users (username, email, password, role, name, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $insertStudent->execute(['9999999999', 'student@example.com', $studentPassword, 'student', 'Demo Student', '9999999999']);
        
        if ($result) {
            echo "<div style='color: green;'>✓ Student user created successfully</div>";
        } else {
            echo "<div style='color: red;'>✗ Failed to create student user</div>";
        }
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error checking student user: " . $e->getMessage() . "</div>";
}

// Test password verification
echo "<h2>Password Verification Test</h2>";

// Test admin password
try {
    $adminQuery = $db->prepare("SELECT password FROM users WHERE username = 'admin'");
    $adminQuery->execute();
    $adminData = $adminQuery->fetch(PDO::FETCH_ASSOC);
    
    if ($adminData && password_verify('admin123', $adminData['password'])) {
        echo "<div style='color: green;'>✓ Admin password verification successful</div>";
    } else {
        echo "<div style='color: red;'>✗ Admin password verification failed</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error verifying admin password: " . $e->getMessage() . "</div>";
}

// Test student password
try {
    $studentQuery = $db->prepare("SELECT password FROM users WHERE username = '9999999999'");
    $studentQuery->execute();
    $studentData = $studentQuery->fetch(PDO::FETCH_ASSOC);
    
    if ($studentData && password_verify('softpro@123', $studentData['password'])) {
        echo "<div style='color: green;'>✓ Student password verification successful</div>";
    } else {
        echo "<div style='color: red;'>✗ Student password verification failed</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error verifying student password: " . $e->getMessage() . "</div>";
}

// Test Auth class login function
echo "<h2>Auth Class Login Test</h2>";

require_once '../includes/auth.php';

try {
    $auth = new Auth();
    
    // Test admin login
    $adminLoginResult = $auth->login('admin', 'admin123');
    if ($adminLoginResult['success']) {
        echo "<div style='color: green;'>✓ Admin login via Auth class successful</div>";
    } else {
        echo "<div style='color: red;'>✗ Admin login via Auth class failed: " . $adminLoginResult['message'] . "</div>";
    }
    
    // Test student login
    $studentLoginResult = $auth->login('9999999999', 'softpro@123');
    if ($studentLoginResult['success']) {
        echo "<div style='color: green;'>✓ Student login via Auth class successful</div>";
    } else {
        echo "<div style='color: red;'>✗ Student login via Auth class failed: " . $studentLoginResult['message'] . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error testing Auth class: " . $e->getMessage() . "</div>";
}

echo "<h2>Complete User List</h2>";
try {
    $allUsers = $db->prepare("SELECT id, username, email, role, name, phone, status, created_at FROM users ORDER BY id");
    $allUsers->execute();
    $users = $allUsers->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Name</th><th>Phone</th><th>Status</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['phone'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($user['status']) . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='color: orange;'>No users found in database</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error fetching users: " . $e->getMessage() . "</div>";
}

echo "<br><br><a href='login.php'>← Back to Login</a>";
?>

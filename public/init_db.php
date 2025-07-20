<?php
// Database initialization script
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed!');
}

echo "<h1>Database Initialization</h1>";

try {
    // Check if admin user exists
    $checkAdmin = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $checkAdmin->execute();
    $adminExists = $checkAdmin->fetchColumn() > 0;
    
    if (!$adminExists) {
        echo "<p>Creating admin user...</p>";
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $insertAdmin = $db->prepare("INSERT INTO users (username, email, password, role, name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $insertAdmin->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 'System Administrator', '9999999999', 'active']);
        
        if ($result) {
            echo "<p style='color: green;'>✓ Admin user created successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create admin user</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Admin user already exists</p>";
    }
    
    // Check if student user exists
    $checkStudent = $db->prepare("SELECT COUNT(*) FROM users WHERE username = '9999999999'");
    $checkStudent->execute();
    $studentExists = $checkStudent->fetchColumn() > 0;
    
    if (!$studentExists) {
        echo "<p>Creating student user...</p>";
        $studentPassword = password_hash('softpro@123', PASSWORD_DEFAULT);
        $insertStudent = $db->prepare("INSERT INTO users (username, email, password, role, name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $insertStudent->execute(['9999999999', 'student@example.com', $studentPassword, 'student', 'Demo Student', '9999999999', 'active']);
        
        if ($result) {
            echo "<p style='color: green;'>✓ Student user created successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create student user</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Student user already exists</p>";
    }
    
    // Verify users
    echo "<h2>Current Users:</h2>";
    $allUsers = $db->prepare("SELECT username, email, role, name, status FROM users ORDER BY role, username");
    $allUsers->execute();
    $users = $allUsers->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Username</th><th style='padding: 8px;'>Email</th><th style='padding: 8px;'>Role</th><th style='padding: 8px;'>Name</th><th style='padding: 8px;'>Status</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($user['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No users found</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>Database initialization complete!</p>";
    echo "<p><a href='login.php'>← Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

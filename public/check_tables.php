<?php
// Database table checker and creator
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed!');
}

echo "<h1>Database Table Status Check</h1>";

// List of required tables for the system
$requiredTables = [
    'users' => "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'training_partner', 'student') NOT NULL,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        training_center_id INT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    'training_centers' => "CREATE TABLE training_centers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        address TEXT,
        phone VARCHAR(20),
        email VARCHAR(255),
        contact_person VARCHAR(255),
        password VARCHAR(255),
        user_id INT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    'sectors' => "CREATE TABLE sectors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'courses' => "CREATE TABLE courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        sector_id INT,
        duration_months INT NOT NULL,
        fee_amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE SET NULL
    )",
    
    'students' => "CREATE TABLE students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        enrollment_number VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(20),
        course_id INT,
        training_center_id INT,
        batch_id INT,
        status ENUM('active', 'inactive', 'completed', 'dropped') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
    )",
    
    'batches' => "CREATE TABLE batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        course_id INT,
        start_date DATE,
        end_date DATE,
        status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )",
    
    'fees' => "CREATE TABLE fees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        fee_type ENUM('registration', 'course', 'exam', 'other') DEFAULT 'course',
        status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
        due_date DATE,
        paid_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )",
    
    'settings' => "CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

try {
    // Check existing tables
    $stmt = $db->prepare("SHOW TABLES");
    $stmt->execute();
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Table Status:</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Table</th><th style='padding: 8px;'>Status</th><th style='padding: 8px;'>Action</th></tr>";
    
    $missingTables = [];
    
    foreach ($requiredTables as $tableName => $createSQL) {
        $exists = in_array($tableName, $existingTables);
        $status = $exists ? "<span style='color: green;'>✓ EXISTS</span>" : "<span style='color: red;'>✗ MISSING</span>";
        $action = $exists ? "No action needed" : "Will create table";
        
        if (!$exists) {
            $missingTables[$tableName] = $createSQL;
        }
        
        echo "<tr>";
        echo "<td style='padding: 8px;'><strong>$tableName</strong></td>";
        echo "<td style='padding: 8px;'>$status</td>";
        echo "<td style='padding: 8px;'>$action</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Create missing tables
    if (!empty($missingTables)) {
        echo "<h2>Creating Missing Tables:</h2>";
        
        foreach ($missingTables as $tableName => $createSQL) {
            try {
                echo "<p>Creating table: <strong>$tableName</strong>...</p>";
                $db->exec($createSQL);
                echo "<p style='color: green;'>✓ Table '$tableName' created successfully</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ Failed to create table '$tableName': " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<p style='color: blue; font-weight: bold;'>Table creation completed!</p>";
    } else {
        echo "<p style='color: green; font-weight: bold;'>All required tables exist!</p>";
    }
    
    // Show current table counts
    echo "<h2>Table Record Counts:</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Table</th><th style='padding: 8px;'>Record Count</th></tr>";
    
    foreach ($requiredTables as $tableName => $createSQL) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $tableName");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<tr>";
            echo "<td style='padding: 8px;'>$tableName</td>";
            echo "<td style='padding: 8px;'>$count</td>";
            echo "</tr>";
        } catch (PDOException $e) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>$tableName</td>";
            echo "<td style='padding: 8px; color: red;'>ERROR: " . $e->getMessage() . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br>";
echo "<p><a href='training-centers.php'>← Try Training Centers</a> | ";
echo "<a href='training-centers-simple.php'>← Try Simple Version</a> | ";
echo "<a href='login.php'>← Back to Login</a></p>";
?>

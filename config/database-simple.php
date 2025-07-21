<?php
// Simplified Database Configuration for Student Management System
function getConnection() {
    $host = 'localhost';
    $dbname = 'u820431346_student_new';
    $username = 'u820431346_student_new';
    $password = 'Softpro@123';
    
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception("Database connection failed. Please check configuration.");
    }
}

// Legacy Database class for backward compatibility
class Database {
    private $host = 'localhost';
    private $db_name = 'u820431346_student_new';
    private $username = 'u820431346_student_new';
    private $password = 'Softpro@123';
    private $conn;

    public function getConnection() {
        return getConnection();
    }
}

// Test database connection
function testConnection() {
    try {
        $db = getConnection();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Check if required tables exist
function checkTables() {
    try {
        $db = getConnection();
        $tables = ['users', 'training_centers', 'sectors', 'courses', 'batches', 'students', 'fees', 'settings'];
        $existingTables = [];
        
        foreach ($tables as $table) {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                $existingTables[] = $table;
            }
        }
        
        return [
            'total' => count($tables),
            'existing' => count($existingTables),
            'missing' => array_diff($tables, $existingTables),
            'tables' => $existingTables
        ];
    } catch (Exception $e) {
        return false;
    }
}

// Database configuration check
function getDatabaseConfig() {
    return [
        'host' => 'localhost',
        'database' => 'u820431346_student_new',
        'username' => 'u820431346_student_new',
        'charset' => 'utf8mb4',
        'engine' => 'InnoDB'
    ];
}
?>

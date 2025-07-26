<?php
// Database Configuration for v2.0
class DatabaseV2 {
    private $host = 'localhost';
    private $db_name = 'student_management_v2';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Create database if it doesn't exist
            $tempConn = new PDO("mysql:host=" . $this->host, $this->username, $this->password);
            $tempConn->exec("CREATE DATABASE IF NOT EXISTS `" . $this->db_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Connect to the specific database
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $exception) {
            error_log("Database v2.0 connection error: " . $exception->getMessage());
            throw new Exception("v2.0 Database connection failed: " . $exception->getMessage());
        }
        return $this->conn;
    }
    
    public function getDatabaseName() {
        return $this->db_name;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}

// Helper function for v2.0 connection
function getV2Connection() {
    $database = new DatabaseV2();
    return $database->getConnection();
}
?>

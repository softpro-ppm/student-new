<?php
// Database Configuration for the old database (v1.0)
function getOldConnection() {
    $host = 'localhost';
    $db_name = 'u820431346_smis'; // As per your .sql file
    $username = 'root';
    $password = '';

    try {
        $conn = new PDO("mysql:host=" . $host, $username, $password);
        $conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name`;");
        $conn->exec("USE `$db_name`;");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $exception) {
        error_log("Old database connection error: " . $exception->getMessage());
        throw new Exception("Connection error for old database. Please check configuration.");
    }
}
?>

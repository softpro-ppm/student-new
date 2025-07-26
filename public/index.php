<?php
session_start();
require_once '../includes/auth.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Redirect to dashboard if logged in
    header('Location: dashboard.php');
} else {
    // Redirect to login if not logged in
    header('Location: login.php');
}
exit();
?>

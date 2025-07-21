<?php
/**
 * Router for Student Management System
 * This file handles routing to remove /public from URLs
 */

// Get the requested URI
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Remove query string
$requestUri = strtok($requestUri, '?');

// Clean the URI
$requestUri = rtrim($requestUri, '/');

// Map routes
$routes = [
    '' => 'public/index.php',
    '/' => 'public/index.php',
    '/login' => 'public/login.php',
    '/dashboard' => 'public/dashboard.php',
    '/logout' => 'public/logout.php',
    '/students' => 'public/students.php',
    '/training-centers' => 'public/training-centers.php',
    '/masters' => 'public/masters.php',
    '/fees' => 'public/fees.php',
    '/batches' => 'public/batches.php',
    '/assessments' => 'public/assessments.php',
    '/reports' => 'public/reports.php',
    '/results' => 'public/results.php',
    '/test' => 'public/test.php'
];

// Check if route exists
if (array_key_exists($requestUri, $routes)) {
    $filePath = $routes[$requestUri];
    
    // Check if file exists
    if (file_exists($filePath)) {
        include $filePath;
        exit();
    }
}

// If no route matches, try to find file in public directory
$publicFile = 'public' . $requestUri;
if (file_exists($publicFile)) {
    include $publicFile;
    exit();
}

// Try adding .php extension
$publicFileWithExt = 'public' . $requestUri . '.php';
if (file_exists($publicFileWithExt)) {
    include $publicFileWithExt;
    exit();
}

// 404 Not Found
http_response_code(404);
echo "<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error-container { max-width: 600px; margin: 0 auto; }
        h1 { color: #e74c3c; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px; }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1>404 - Page Not Found</h1>
        <p>The page you're looking for doesn't exist.</p>
        <a href='/login' class='btn'>Go to Login</a>
        <a href='/dashboard' class='btn'>Go to Dashboard</a>
    </div>
</body>
</html>";
?>

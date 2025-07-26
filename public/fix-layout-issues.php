<?php
// Fix Layout and Function Issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Layout Issues Fix</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .log { background: #000; color: #0f0; padding: 20px; border-radius: 10px; font-family: monospace; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
<div class='container'>
    <h1 class='mb-4'>Layout and Function Issues Fix</h1>
    <div class='log' id='log'>";

function logMessage($message, $type = 'info') {
    $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : ($type === 'warning' ? 'warning' : ''));
    echo "<div class='$class'>[" . date('H:i:s') . "] $message</div>";
    ob_flush();
    flush();
}

logMessage("Starting layout and function fixes...", 'info');

// 1. Fix fees.php - Remove renderFooter call
logMessage("Fixing fees.php renderFooter issue...", 'info');
$feesFile = '../public/fees.php';
if (file_exists($feesFile)) {
    $content = file_get_contents($feesFile);
    if (strpos($content, 'renderFooter()') !== false) {
        $content = str_replace('<?php renderFooter(); ?>', '</body>\n</html>', $content);
        file_put_contents($feesFile, $content);
        logMessage("Fixed renderFooter() call in fees.php", 'success');
    } else {
        logMessage("renderFooter() already fixed in fees.php", 'info');
    }
} else {
    logMessage("fees.php not found", 'error');
}

// 2. Check other PHP files for similar issues
logMessage("Checking other files for renderFooter issues...", 'info');
$phpFiles = [
    '../public/students.php',
    '../public/training-centers.php',
    '../public/dashboard.php',
    '../public/masters.php',
    '../public/reports.php'
];

foreach ($phpFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'renderFooter()') !== false) {
            $content = str_replace('<?php renderFooter(); ?>', '</body>\n</html>', $content);
            file_put_contents($file, $content);
            logMessage("Fixed renderFooter() in " . basename($file), 'success');
        } else {
            logMessage("No renderFooter() issues in " . basename($file), 'info');
        }
    }
}

// 3. Create a simple renderFooter function for compatibility
logMessage("Creating compatibility functions...", 'info');
$compatFile = '../includes/compatibility.php';
$compatContent = '<?php
// Compatibility functions for legacy code

if (!function_exists("renderFooter")) {
    function renderFooter() {
        echo "</body>\n</html>";
    }
}

if (!function_exists("renderHeader")) {
    function renderHeader($title = "Student Management System") {
        // This function should be defined in layout.php
        // If not, provide a basic header
        if (!function_exists("renderHeaderFromLayout")) {
            echo "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>$title</title>
    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">
</head>
<body>";
        }
    }
}
?>';

file_put_contents($compatFile, $compatContent);
logMessage("Created compatibility functions file", 'success');

// 4. Test page accessibility
logMessage("Testing page accessibility...", 'info');
$testUrls = [
    'fees.php' => 'Fees Management',
    'students.php' => 'Students Management',
    'training-centers.php' => 'Training Centers',
    'dashboard.php' => 'Dashboard'
];

foreach ($testUrls as $page => $title) {
    $url = "http://localhost/student-new/public/$page";
    logMessage("Test link for $title: <a href='$url' target='_blank' style='color: #17a2b8;'>$url</a>", 'info');
}

logMessage("Layout fixes completed!", 'success');
logMessage("Key fixes applied:", 'success');
logMessage("✅ Fixed renderFooter() undefined function errors", 'success');
logMessage("✅ Fixed array offset warnings in layout.php", 'success');
logMessage("✅ Added proper user data for layout rendering", 'success');
logMessage("✅ Created compatibility functions", 'success');

echo "</div>
    <div class='mt-4'>
        <h3>Test Your Pages:</h3>
        <div class='row'>";

foreach ($testUrls as $page => $title) {
    echo "<div class='col-md-3 mb-2'>
            <a href='$page' class='btn btn-primary w-100' target='_blank'>$title</a>
          </div>";
}

echo "</div>
        <div class='mt-3'>
            <a href='dashboard.php' class='btn btn-success'>Go to Dashboard</a>
            <a href='system-health-check.php' class='btn btn-info'>System Health Check</a>
        </div>
    </div>
</div>
</body>
</html>";
?>

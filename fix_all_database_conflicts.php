<?php
// Comprehensive Database Conflict Fix Script
// This script will fix ALL files that have database include conflicts

echo "<h1>Fixing All Database Conflicts</h1>";

// List of all files that need to be fixed
$filesToFix = [
    // Files that include both auth.php (which has database-simple.php) AND database.php
    'public/assessment.php',
    'public/assessments.php', 
    'public/assessments_backup.php',
    'public/assessments_new.php',
    'public/assessment_result.php',
    'public/assessment_results.php',
    'public/batches-complete.php',
    'public/batches-new.php',
    'public/batches-old.php',
    'public/fees-new.php',
    'public/fees-old.php',
    'public/dashboard.php',
    'public/dashboard-new.php',
    'public/dashboard_new.php',
    'public/dashboard_old.php',
    'public/students.php',
    'public/students-complete.php',
    'public/students-new.php',
    'public/students-old.php',
    'public/training-centers.php',
    'public/training-centers-complete.php',
    'public/training-centers-new.php',
    'public/training-centers-old.php',
    'public/training-centers-simple.php',
    'public/masters.php',
    'public/masters_backup.php',
    'public/masters_new.php',
    'public/reports.php',
    'public/results.php',
    'public/login-new.php',
    'public/login_new.php'
];

// Patterns to replace
$patterns = [
    // Pattern 1: Remove database.php include when auth.php is present
    [
        'search' => "require_once '../config/database.php';\nrequire_once '../includes/auth.php';",
        'replace' => "require_once '../includes/auth.php';"
    ],
    [
        'search' => "require_once '../includes/auth.php';\nrequire_once '../config/database.php';", 
        'replace' => "require_once '../includes/auth.php';"
    ],
    // Pattern 2: Replace Database class usage
    [
        'search' => '$database = new Database();
$db = $database->getConnection();',
        'replace' => '$db = getConnection();'
    ],
    [
        'search' => '$database = new Database();
    $db = $database->getConnection();',
        'replace' => '$db = getConnection();'
    ]
];

$fixedFiles = 0;
$errors = [];

foreach ($filesToFix as $file) {
    if (file_exists($file)) {
        echo "<h3>Fixing: $file</h3>";
        
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Check if file has auth.php include
        $hasAuth = strpos($content, "require_once '../includes/auth.php'") !== false;
        $hasDatabase = strpos($content, "require_once '../config/database.php'") !== false;
        
        if ($hasAuth && $hasDatabase) {
            echo "<p style='color: orange;'>⚠ Found conflict in $file</p>";
            
            // Remove the database.php include line
            $content = str_replace("require_once '../config/database.php';", "", $content);
            
            // Replace Database class usage
            $content = preg_replace('/\$database\s*=\s*new\s+Database\(\);\s*\n\s*\$db\s*=\s*\$database->getConnection\(\);/', '$db = getConnection();', $content);
            
            // Clean up extra newlines
            $content = preg_replace('/\n\n\n+/', "\n\n", $content);
            
            if ($content !== $originalContent) {
                if (file_put_contents($file, $content)) {
                    echo "<p style='color: green;'>✓ Fixed $file</p>";
                    $fixedFiles++;
                } else {
                    echo "<p style='color: red;'>✗ Failed to write $file</p>";
                    $errors[] = $file;
                }
            } else {
                echo "<p style='color: blue;'>- No changes needed for $file</p>";
            }
        } else if ($hasDatabase && !$hasAuth) {
            echo "<p style='color: blue;'>- $file uses database.php only (no conflict)</p>";
        } else {
            echo "<p style='color: green;'>- $file already uses correct includes</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ File not found: $file</p>";
        $errors[] = $file . " (not found)";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p><strong>Files Fixed:</strong> $fixedFiles</p>";
echo "<p><strong>Errors:</strong> " . count($errors) . "</p>";

if (count($errors) > 0) {
    echo "<h3>Errors:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

echo "<p style='color: green; font-weight: bold;'>✓ Database conflict fix completed!</p>";
echo "<p><a href='login.php'>Test Login</a> | <a href='config-check.php'>System Check</a></p>";
?>

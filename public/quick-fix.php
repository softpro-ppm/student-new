<?php
/**
 * Quick Fix Script for Student Management System Issues
 * This script fixes the common issues found in config-check.php
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database config
require_once '../config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Fix - Student Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn-danger {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Quick Fix - Student Management System</h1>
        
        <?php
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            
            if ($action === 'fix_question_papers') {
                try {
                    $connection = getConnection();
                    
                    // Create question_papers table
                    $createQuestionPapers = "CREATE TABLE IF NOT EXISTS `question_papers` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `title` varchar(255) NOT NULL,
                        `course_id` int(11) NOT NULL,
                        `total_questions` int(11) NOT NULL DEFAULT 0,
                        `duration_minutes` int(11) NOT NULL DEFAULT 60,
                        `passing_marks` decimal(5,2) NOT NULL DEFAULT 50.00,
                        `questions` longtext DEFAULT NULL,
                        `instructions` text DEFAULT NULL,
                        `status` enum('draft','published','archived') DEFAULT 'draft',
                        `created_by` int(11) DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `fk_question_papers_course` (`course_id`),
                        KEY `fk_question_papers_created_by` (`created_by`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    
                    $connection->exec($createQuestionPapers);
                    echo "<div class='success'>✅ Successfully created question_papers table!</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Error creating question_papers table: " . $e->getMessage() . "</div>";
                }
            }
            
            if ($action === 'validate_fixes') {
                try {
                    $connection = getConnection();
                    
                    // Check if question_papers table exists now
                    $stmt = $connection->prepare("DESCRIBE `question_papers`");
                    $stmt->execute();
                    echo "<div class='success'>✅ question_papers table now exists and is accessible!</div>";
                    
                    // Test the training_centers query with correct column name
                    $stmt = $connection->prepare("SELECT * FROM training_centers WHERE name LIKE ?");
                    $stmt->execute(['%Demo%']);
                    $result = $stmt->fetch();
                    
                    if ($result) {
                        echo "<div class='success'>✅ Demo training center found using correct column name 'name'!</div>";
                    } else {
                        echo "<div class='warning'>⚠️ No demo training center found, but query executed without error!</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Validation error: " . $e->getMessage() . "</div>";
                }
            }
        }
        ?>
        
        <div style="margin: 20px 0;">
            <h3>Available Fixes:</h3>
            <a href="?action=fix_question_papers" class="btn btn-danger">Create Missing question_papers Table</a>
            <a href="?action=validate_fixes" class="btn">Validate All Fixes</a>
            <a href="config-check.php" class="btn">Back to Config Check</a>
        </div>
        
        <div style="margin: 20px 0;">
            <h3>Issues Found and Fixed:</h3>
            <ul>
                <li><strong>Missing question_papers table:</strong> Can be created using the button above</li>
                <li><strong>Column 'center_name' error:</strong> Fixed in config-check.php (changed to 'name')</li>
                <li><strong>Better error reporting:</strong> Added detailed error messages</li>
                <li><strong>PHP extension checks:</strong> Added validation for required extensions</li>
                <li><strong>File permission checks:</strong> Added checks for upload directories</li>
            </ul>
        </div>
        
        <div style="margin: 20px 0;">
            <h3>Manual Steps to Deploy Fixes:</h3>
            <ol>
                <li>Upload the updated config-check.php file to the server</li>
                <li>Run this quick-fix script to create missing tables</li>
                <li>Verify all fixes using the config-check.php page</li>
                <li>Ensure proper file permissions for upload directories</li>
            </ol>
        </div>
    </div>
</body>
</html>

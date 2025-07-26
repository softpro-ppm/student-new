<?php
require_once '../config/database-v1.php';
require_once '../config/database-v2.php';

class DataMigration {
    private $v1_conn;
    private $v2_conn;
    private $migration_log = [];
    
    public function __construct() {
        $this->v1_conn = getOldConnection();
        $this->v2_conn = getV2Connection();
    }
    
    public function startMigration() {
        echo "<h2>🔄 Data Migration: v1.0 → v2.0</h2>";
        echo "<p>Starting migration from <strong>u820431346_smis</strong> to <strong>student_management_v2</strong></p>";
        
        $this->logMessage("Migration started at " . date('Y-m-d H:i:s'));
        
        // Migration steps in order
        $steps = [
            '1' => 'Migrate Training Centers',
            '2' => 'Migrate Master Data (Sectors, Schemes, Job Roles)',
            '3' => 'Migrate Courses',
            '4' => 'Migrate Users (Admin)',
            '5' => 'Migrate Students',
            '6' => 'Migrate Batches',
            '7' => 'Migrate Batch-Student Relationships',
            '8' => 'Migrate Payments and Fees',
            '9' => 'Verify Data Integrity'
        ];
        
        echo "<h3>Migration Steps:</h3>";
        echo "<ol>";
        foreach ($steps as $num => $step) {
            echo "<li><a href='?step=$num'>$step</a></li>";
        }
        echo "</ol>";
        
        if (isset($_GET['step'])) {
            $step = $_GET['step'];
            switch ($step) {
                case '1':
                    $this->migrateTrainingCenters();
                    break;
                case '2':
                    $this->migrateMasterData();
                    break;
                case '3':
                    $this->migrateCourses();
                    break;
                case '4':
                    $this->migrateUsers();
                    break;
                case '5':
                    $this->migrateStudents();
                    break;
                case '6':
                    $this->migrateBatches();
                    break;
                case '7':
                    $this->migrateBatchStudents();
                    break;
                case '8':
                    $this->migratePayments();
                    break;
                case '9':
                    $this->verifyDataIntegrity();
                    break;
                default:
                    echo "<p>Invalid step selected.</p>";
            }
        }
        
        $this->showMigrationLog();
    }
    
    private function migrateTrainingCenters() {
        echo "<h3>Step 1: Migrating Training Centers</h3>";
        
        try {
            // Get training centers from v1
            $stmt = $this->v1_conn->query("SELECT * FROM tbltrainingcenter");
            $centers = $stmt->fetchAll();
            
            $migrated = 0;
            $errors = 0;
            
            foreach ($centers as $center) {
                try {
                    $sql = "INSERT INTO training_centers (
                        id, center_name, center_code, address, city, state, pincode,
                        phone, email, spoc_name, spoc_phone, spoc_email,
                        status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $this->v2_conn->prepare($sql);
                    $stmt->execute([
                        $center['TrainingcenterId'],
                        $center['trainingcentername'],
                        'TC' . str_pad($center['TrainingcenterId'], 4, '0', STR_PAD_LEFT),
                        $center['tcaddress'] . ', ' . $center['tclocation'],
                        $center['tclocation'],
                        'Andhra Pradesh', // Default state
                        '535501', // Default pincode
                        $center['spoccontact'],
                        $center['spocemailaddress'],
                        $center['spocname'],
                        $center['spoccontact'],
                        $center['spocemailaddress'],
                        'active',
                        $center['DateCreated'],
                        $center['DateModified']
                    ]);
                    
                    $migrated++;
                    $this->logMessage("Migrated training center: " . $center['trainingcentername']);
                    
                } catch (PDOException $e) {
                    $errors++;
                    $this->logMessage("Error migrating training center " . $center['TrainingcenterId'] . ": " . $e->getMessage());
                }
            }
            
            echo "<p>✅ Migration complete: $migrated centers migrated, $errors errors</p>";
            echo "<p><a href='?step=2'>Next: Migrate Master Data →</a></p>";
            
        } catch (Exception $e) {
            echo "<p>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    private function migrateMasterData() {
        echo "<h3>Step 2: Migrating Master Data</h3>";
        
        try {
            // Migrate Sectors
            echo "<h4>Migrating Sectors...</h4>";
            $stmt = $this->v1_conn->query("SELECT * FROM tblsector");
            $sectors = $stmt->fetchAll();
            
            $sector_migrated = 0;
            foreach ($sectors as $sector) {
                try {
                    $sql = "INSERT INTO sectors (id, sector_name, sector_code, description, status, created_at) 
                            VALUES (?, ?, ?, ?, 'active', NOW())";
                    $stmt = $this->v2_conn->prepare($sql);
                    $stmt->execute([
                        $sector['SectorId'],
                        $sector['SectorName'],
                        'SEC' . str_pad($sector['SectorId'], 3, '0', STR_PAD_LEFT),
                        $sector['SectorName']
                    ]);
                    $sector_migrated++;
                } catch (PDOException $e) {
                    $this->logMessage("Error migrating sector: " . $e->getMessage());
                }
            }
            
            // Migrate Schemes
            echo "<h4>Migrating Schemes...</h4>";
            $stmt = $this->v1_conn->query("SELECT * FROM tblscheme");
            $schemes = $stmt->fetchAll();
            
            $scheme_migrated = 0;
            foreach ($schemes as $scheme) {
                try {
                    // Get first sector as default
                    $sector_id = 1;
                    if (!empty($sectors)) {
                        $sector_id = $sectors[0]['SectorId'];
                    }
                    
                    $sql = "INSERT INTO schemes (id, scheme_name, scheme_code, sector_id, description, 
                            duration_months, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, 6, 'active', NOW())";
                    $stmt = $this->v2_conn->prepare($sql);
                    $stmt->execute([
                        $scheme['SchemeId'],
                        $scheme['SchemeName'],
                        'SCH' . str_pad($scheme['SchemeId'], 3, '0', STR_PAD_LEFT),
                        $sector_id,
                        $scheme['SchemeName']
                    ]);
                    $scheme_migrated++;
                } catch (PDOException $e) {
                    $this->logMessage("Error migrating scheme: " . $e->getMessage());
                }
            }
            
            // Migrate Job Roles
            echo "<h4>Migrating Job Roles...</h4>";
            $stmt = $this->v1_conn->query("SELECT * FROM tbljobroll");
            $jobroles = $stmt->fetchAll();
            
            $jobroll_migrated = 0;
            foreach ($jobroles as $jobroll) {
                try {
                    // Default to first scheme and sector
                    $scheme_id = !empty($schemes) ? $schemes[0]['SchemeId'] : 1;
                    $sector_id = !empty($sectors) ? $sectors[0]['SectorId'] : 1;
                    
                    $sql = "INSERT INTO job_roles (id, job_role_name, job_role_code, scheme_id, sector_id, 
                            description, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";
                    $stmt = $this->v2_conn->prepare($sql);
                    $stmt->execute([
                        $jobroll['JobRollId'],
                        $jobroll['JobRollName'],
                        'JR' . str_pad($jobroll['JobRollId'], 3, '0', STR_PAD_LEFT),
                        $scheme_id,
                        $sector_id,
                        $jobroll['JobRollName']
                    ]);
                    $jobroll_migrated++;
                } catch (PDOException $e) {
                    $this->logMessage("Error migrating job role: " . $e->getMessage());
                }
            }
            
            echo "<p>✅ Master data migration complete:</p>";
            echo "<ul>";
            echo "<li>Sectors: $sector_migrated migrated</li>";
            echo "<li>Schemes: $scheme_migrated migrated</li>";
            echo "<li>Job Roles: $jobroll_migrated migrated</li>";
            echo "</ul>";
            echo "<p><a href='?step=3'>Next: Migrate Courses →</a></p>";
            
        } catch (Exception $e) {
            echo "<p>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    private function migrateCourses() {
        echo "<h3>Step 3: Migrating Courses</h3>";
        
        try {
            // Create default courses based on job roles
            $stmt = $this->v2_conn->query("SELECT * FROM job_roles");
            $job_roles = $stmt->fetchAll();
            
            $course_migrated = 0;
            foreach ($job_roles as $job_role) {
                try {
                    $sql = "INSERT INTO courses (course_name, course_code, job_role_id, scheme_id, sector_id,
                            description, duration_hours, duration_months, theory_hours, practical_hours,
                            course_fee, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 600, 6, 200, 400, 15000, 'active', NOW())";
                    
                    $stmt = $this->v2_conn->prepare($sql);
                    $stmt->execute([
                        $job_role['job_role_name'] . ' Training Course',
                        'CRS' . str_pad($job_role['id'], 3, '0', STR_PAD_LEFT),
                        $job_role['id'],
                        $job_role['scheme_id'],
                        $job_role['sector_id'],
                        'Training course for ' . $job_role['job_role_name']
                    ]);
                    $course_migrated++;
                } catch (PDOException $e) {
                    $this->logMessage("Error creating course for job role " . $job_role['id'] . ": " . $e->getMessage());
                }
            }
            
            echo "<p>✅ Courses migration complete: $course_migrated courses created</p>";
            echo "<p><a href='?step=4'>Next: Migrate Users →</a></p>";
            
        } catch (Exception $e) {
            echo "<p>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    private function migrateUsers() {
        echo "<h3>Step 4: Migrating Users</h3>";
        
        try {
            // Migrate admin users
            $stmt = $this->v1_conn->query("SELECT * FROM admin");
            $admins = $stmt->fetchAll();
            
            $user_migrated = 0;
            foreach ($admins as $admin) {
                try {
                    // Determine role based on user_type
                    $role = 'admin';
                    if ($admin['user_type'] == 1) $role = 'super_admin';
                    elseif ($admin['user_type'] == 2) $role = 'admin';
                    elseif ($admin['user_type'] == 3) $role = 'training_partner';
                    
                    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name,
                            phone, status, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, ?, '0000000000', 'active', NOW(), NOW())";
                    
                    $stmt = $this->v2_conn->prepare($sql);
                    $stmt->execute([
                        $admin['UserName'],
                        $admin['email'],
                        $admin['Password'], // Keep existing hash for now
                        $role,
                        $admin['UserName'],
                        'Admin'
                    ]);
                    $user_migrated++;
                } catch (PDOException $e) {
                    $this->logMessage("Error migrating admin user " . $admin['id'] . ": " . $e->getMessage());
                }
            }
            
            echo "<p>✅ Users migration complete: $user_migrated users migrated</p>";
            echo "<p><a href='?step=5'>Next: Migrate Students →</a></p>";
            
        } catch (Exception $e) {
            echo "<p>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    private function logMessage($message) {
        $this->migration_log[] = date('H:i:s') . ' - ' . $message;
        echo "<small style='color: #666;'>$message</small><br>";
    }
    
    private function showMigrationLog() {
        if (!empty($this->migration_log)) {
            echo "<hr><h3>Migration Log:</h3>";
            echo "<div style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: auto;'>";
            foreach ($this->migration_log as $log) {
                echo $log . "<br>";
            }
            echo "</div>";
        }
    }
}

// Start migration
$migration = new DataMigration();
$migration->startMigration();
?>

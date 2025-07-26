<?php
// Check and fix courses table
require_once '../config/database.php';

try {
    $db = getConnection();
    
    // Check if courses table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'courses'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo "Courses table doesn't exist. Creating it...\n";
        
        // Create courses table
        $createCourses = "CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            duration_months INT DEFAULT 6,
            fee DECIMAL(10,2) DEFAULT 0,
            sector_id INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $db->exec($createCourses);
        
        // Insert sample courses
        $sampleCourses = [
            ['Web Development', 'WEB001', 'Full Stack Web Development Course', 6, 15000.00, null],
            ['Data Science', 'DS001', 'Data Science and Analytics Course', 8, 20000.00, null],
            ['Digital Marketing', 'DM001', 'Digital Marketing Course', 4, 12000.00, null],
            ['Graphic Design', 'GD001', 'Graphic Design and UI/UX Course', 5, 18000.00, null]
        ];
        
        $insertCourse = $db->prepare("INSERT INTO courses (name, code, description, duration_months, fee, sector_id) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($sampleCourses as $course) {
            $insertCourse->execute($course);
        }
        
        echo "✓ Courses table created with sample data\n";
    } else {
        echo "✓ Courses table exists\n";
        
        // Check if all required columns exist
        $stmt = $db->prepare("DESCRIBE courses");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $columnNames = array_column($columns, 'Field');
        
        // Check for sector_id column
        if (!in_array('sector_id', $columnNames)) {
            echo "Adding missing sector_id column...\n";
            $db->exec("ALTER TABLE courses ADD COLUMN sector_id INT NULL");
            echo "✓ sector_id column added\n";
        }
        
        // Check for code column
        if (!in_array('code', $columnNames)) {
            echo "Adding missing code column...\n";
            $db->exec("ALTER TABLE courses ADD COLUMN code VARCHAR(50) UNIQUE NULL");
            echo "✓ code column added\n";
        }
        
        // Update existing courses without codes
        $stmt = $db->prepare("SELECT id, name FROM courses WHERE code IS NULL OR code = ''");
        $stmt->execute();
        $coursesWithoutCode = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($coursesWithoutCode as $course) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $course['name']), 0, 3)) . str_pad($course['id'], 3, '0', STR_PAD_LEFT);
            $updateStmt = $db->prepare("UPDATE courses SET code = ? WHERE id = ?");
            $updateStmt->execute([$code, $course['id']]);
        }
        
        echo "✓ Course codes updated\n";
    }
    
    // Check if sectors table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'sectors'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo "Sectors table doesn't exist. Creating it...\n";
        
        // Create sectors table
        $createSectors = "CREATE TABLE IF NOT EXISTS sectors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $db->exec($createSectors);
        
        // Insert sample sectors
        $sampleSectors = [
            ['Information Technology', 'IT', 'IT and Software Development'],
            ['Healthcare', 'HC', 'Healthcare and Medical Services'],
            ['Marketing', 'MKT', 'Marketing and Advertisement'],
            ['Design', 'DES', 'Design and Creative Arts']
        ];
        
        $insertSector = $db->prepare("INSERT INTO sectors (name, code, description) VALUES (?, ?, ?)");
        
        foreach ($sampleSectors as $sector) {
            $insertSector->execute($sector);
        }
        
        echo "✓ Sectors table created with sample data\n";
    } else {
        echo "✓ Sectors table exists\n";
    }
    
    // Update courses with sector relationships
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM courses WHERE sector_id IS NOT NULL");
    $stmt->execute();
    $coursesWithSectors = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($coursesWithSectors == 0) {
        echo "Updating courses with sector relationships...\n";
        
        // Get sectors
        $stmt = $db->prepare("SELECT id, name FROM sectors");
        $stmt->execute();
        $sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($sectors)) {
            // Update some courses with sectors
            $updates = [
                'Web Development' => 'Information Technology',
                'Data Science' => 'Information Technology',
                'Digital Marketing' => 'Marketing',
                'Graphic Design' => 'Design'
            ];
            
            foreach ($updates as $courseName => $sectorName) {
                $sectorId = null;
                foreach ($sectors as $sector) {
                    if ($sector['name'] === $sectorName) {
                        $sectorId = $sector['id'];
                        break;
                    }
                }
                
                if ($sectorId) {
                    $updateStmt = $db->prepare("UPDATE courses SET sector_id = ? WHERE name = ?");
                    $updateStmt->execute([$sectorId, $courseName]);
                }
            }
            
            echo "✓ Course-sector relationships updated\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    
    // Show final counts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sectors");
    $stmt->execute();
    $sectorCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM courses");
    $stmt->execute();
    $courseCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Total Sectors: $sectorCount\n";
    echo "Total Courses: $courseCount\n";
    echo "Masters page should now work correctly!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

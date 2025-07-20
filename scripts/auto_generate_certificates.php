<?php
/**
 * Auto Certificate Generator
 * This script automatically generates certificates for students who passed assessments
 * Can be run via cron job or manually triggered
 */

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$generatedCount = 0;
$errors = [];

try {
    // Find all passed results without certificates
    $stmt = $db->prepare("
        SELECT r.*, s.name as student_name, s.enrollment_number,
               c.name as course_name, c.duration_months,
               a.name as assessment_name, a.total_marks, a.passing_marks
        FROM results r
        JOIN students s ON r.student_id = s.id
        JOIN courses c ON s.course_id = c.id
        JOIN assessments a ON r.assessment_id = a.id
        LEFT JOIN certificates cert ON r.id = cert.result_id
        WHERE r.status = 'pass' AND cert.id IS NULL
        ORDER BY r.completed_at ASC
    ");
    $stmt->execute();
    $passedResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($passedResults) . " passed students without certificates.\n";
    
    foreach ($passedResults as $result) {
        try {
            echo "Generating certificate for: " . $result['student_name'] . " (" . $result['enrollment_number'] . ")...\n";
            
            // Generate certificate
            $certificateData = generateCertificate($result, $db);
            
            echo "✓ Certificate generated: " . $certificateData['certificate_number'] . "\n";
            $generatedCount++;
            
        } catch (Exception $e) {
            $error = "Failed to generate certificate for " . $result['student_name'] . ": " . $e->getMessage();
            echo "✗ " . $error . "\n";
            $errors[] = $error;
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Certificates generated: {$generatedCount}\n";
    echo "Errors: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "- " . $error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

function generateCertificate($resultData, $db) {
    try {
        // Generate unique certificate number
        $certificateNumber = 'CERT-' . date('Y') . '-' . str_pad($resultData['student_id'], 6, '0', STR_PAD_LEFT) . '-' . time();
        
        // Calculate grade and percentage
        $percentage = ($resultData['marks_obtained'] / $resultData['total_marks']) * 100;
        $grade = calculateGrade($percentage);
        
        // Create certificate directories
        $certDir = '../uploads/certificates/generated/';
        $qrDir = '../uploads/certificates/qr_codes/';
        
        if (!file_exists($certDir)) {
            mkdir($certDir, 0755, true);
        }
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        
        // Generate QR code data
        $qrData = json_encode([
            'certificate_number' => $certificateNumber,
            'student_name' => $resultData['student_name'],
            'course_name' => $resultData['course_name'],
            'enrollment_number' => $resultData['enrollment_number'],
            'grade' => $grade,
            'percentage' => number_format($percentage, 2),
            'issued_date' => date('Y-m-d'),
            'verification_url' => 'https://yourdomain.com/verify_certificate.php?q=' . urlencode($certificateNumber)
        ]);
        
        // Generate QR code using online service
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&format=png&data=' . urlencode($qrData);
        $qrCodePath = $qrDir . 'qr_' . $certificateNumber . '.png';
        
        // Download QR code
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; Certificate Generator)'
            ]
        ]);
        
        $qrContent = file_get_contents($qrCodeUrl, false, $context);
        if ($qrContent === false) {
            throw new Exception("Failed to generate QR code");
        }
        
        file_put_contents($qrCodePath, $qrContent);
        
        // Generate certificate HTML
        $certificateHtml = generateCertificateHtml($resultData, $certificateNumber, $grade, $percentage, $qrCodePath);
        
        // Save certificate as HTML file
        $certificatePath = $certDir . 'certificate_' . $certificateNumber . '.html';
        file_put_contents($certificatePath, $certificateHtml);
        
        // Generate PDF version (if wkhtmltopdf is available)
        $pdfPath = $certDir . 'certificate_' . $certificateNumber . '.pdf';
        if (generatePdfCertificate($certificateHtml, $pdfPath)) {
            $certificatePath = $pdfPath; // Use PDF as primary certificate
        }
        
        // Save to database
        $stmt = $db->prepare("
            INSERT INTO certificates (student_id, result_id, certificate_number, issued_date, certificate_path, qr_code_path, status)
            VALUES (?, ?, ?, ?, ?, ?, 'generated')
        ");
        $stmt->execute([
            $resultData['student_id'],
            $resultData['id'],
            $certificateNumber,
            date('Y-m-d'),
            $certificatePath,
            $qrCodePath
        ]);
        
        return [
            'certificate_number' => $certificateNumber,
            'certificate_path' => $certificatePath,
            'qr_code_path' => $qrCodePath
        ];
        
    } catch (Exception $e) {
        throw new Exception("Failed to generate certificate: " . $e->getMessage());
    }
}

function calculateGrade($percentage) {
    if ($percentage >= 95) return 'A+';
    if ($percentage >= 85) return 'A';
    if ($percentage >= 75) return 'B+';
    if ($percentage >= 65) return 'B';
    if ($percentage >= 55) return 'C';
    return 'F';
}

function generatePdfCertificate($html, $outputPath) {
    // Check if wkhtmltopdf is available
    $wkhtmltopdf = trim(shell_exec('which wkhtmltopdf 2>/dev/null') ?: shell_exec('where wkhtmltopdf 2>nul'));
    
    if (empty($wkhtmltopdf)) {
        return false; // wkhtmltopdf not available
    }
    
    try {
        // Create temporary HTML file
        $tempHtml = tempnam(sys_get_temp_dir(), 'cert_') . '.html';
        file_put_contents($tempHtml, $html);
        
        // Generate PDF
        $command = escapeshellcmd($wkhtmltopdf) . ' --page-size A4 --orientation Landscape --margin-top 0.5in --margin-bottom 0.5in --margin-left 0.5in --margin-right 0.5in ' . 
                   escapeshellarg($tempHtml) . ' ' . escapeshellarg($outputPath) . ' 2>&1';
        
        $output = shell_exec($command);
        
        // Clean up temp file
        unlink($tempHtml);
        
        return file_exists($outputPath);
        
    } catch (Exception $e) {
        return false;
    }
}

function generateCertificateHtml($data, $certificateNumber, $grade, $percentage, $qrCodePath) {
    $qrCodeBase64 = '';
    if (file_exists($qrCodePath)) {
        $qrCodeBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($qrCodePath));
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Certificate - ' . htmlspecialchars($data['student_name']) . '</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap");
            
            body { 
                font-family: "Inter", sans-serif; 
                margin: 0; 
                padding: 40px; 
                background: #f8fafc;
                color: #1e293b;
            }
            
            .certificate { 
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                border: 15px solid #1e40af;
                border-image: linear-gradient(135deg, #1e40af, #3b82f6) 1;
                padding: 80px 60px;
                text-align: center; 
                max-width: 1000px; 
                margin: 0 auto;
                box-shadow: 0 25px 50px rgba(0,0,0,0.15);
                position: relative;
                min-height: 600px;
            }
            
            .certificate::before {
                content: "";
                position: absolute;
                top: 20px;
                left: 20px;
                right: 20px;
                bottom: 20px;
                border: 2px solid #e2e8f0;
                pointer-events: none;
            }
            
            .header { 
                margin-bottom: 50px; 
                position: relative;
                z-index: 2;
            }
            
            .institution-name {
                font-size: 28px;
                color: #1e40af;
                font-weight: 600;
                margin-bottom: 10px;
                letter-spacing: 2px;
                text-transform: uppercase;
            }
            
            .title { 
                font-family: "Playfair Display", serif;
                font-size: 56px; 
                color: #1e40af; 
                font-weight: 700; 
                margin: 30px 0;
                text-shadow: 2px 2px 4px rgba(30, 64, 175, 0.1);
                line-height: 1.2;
            }
            
            .subtitle { 
                font-size: 24px; 
                color: #64748b; 
                margin-bottom: 40px;
                font-weight: 300;
            }
            
            .student-name { 
                font-family: "Playfair Display", serif;
                font-size: 42px; 
                color: #1e40af; 
                font-weight: 700; 
                margin: 40px 0; 
                border-bottom: 4px solid #1e40af; 
                display: inline-block; 
                padding-bottom: 15px;
                position: relative;
            }
            
            .course-info { 
                font-size: 22px; 
                margin: 30px 0;
                line-height: 1.6;
                color: #374151;
            }
            
            .course-name {
                font-weight: 600;
                color: #1e40af;
                font-size: 26px;
            }
            
            .achievement-section {
                background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
                border-radius: 20px;
                padding: 30px;
                margin: 40px 0;
                border: 2px solid #cbd5e1;
            }
            
            .grade-display {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 40px;
                margin: 20px 0;
            }
            
            .grade-circle {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
                font-weight: 700;
                box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            }
            
            .marks-info {
                text-align: left;
            }
            
            .marks-info h4 {
                margin: 0 0 10px 0;
                color: #1e40af;
                font-size: 18px;
            }
            
            .marks-info p {
                margin: 5px 0;
                font-size: 16px;
                color: #64748b;
            }
            
            .footer { 
                margin-top: 60px; 
                display: flex; 
                justify-content: space-between; 
                align-items: center;
                position: relative;
                z-index: 2;
            }
            
            .cert-details {
                text-align: left;
            }
            
            .cert-number { 
                font-size: 14px; 
                color: #64748b;
                margin: 5px 0;
            }
            
            .date-section {
                text-align: center;
            }
            
            .date { 
                font-size: 18px; 
                color: #1e40af;
                font-weight: 600;
            }
            
            .qr-section {
                text-align: right;
            }
            
            .qr-code { 
                width: 100px; 
                height: 100px;
                border: 2px solid #e2e8f0;
                border-radius: 10px;
                padding: 5px;
                background: white;
            }
            
            .qr-text {
                font-size: 12px;
                color: #64748b;
                margin-top: 5px;
            }
            
            .decorative-elements {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                pointer-events: none;
                opacity: 0.1;
            }
            
            .seal {
                position: absolute;
                bottom: 40px;
                right: 40px;
                width: 80px;
                height: 80px;
                border: 3px solid #1e40af;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: white;
                font-size: 12px;
                font-weight: 600;
                color: #1e40af;
                text-align: center;
                line-height: 1.2;
            }
            
            @media print {
                body { padding: 0; background: white; }
                .certificate { box-shadow: none; }
            }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="decorative-elements"></div>
            
            <div class="header">
                <div class="institution-name">Student Management System</div>
                <div class="title">CERTIFICATE<br>OF COMPLETION</div>
                <div class="subtitle">This is to certify that</div>
            </div>
            
            <div class="student-name">' . htmlspecialchars($data['student_name']) . '</div>
            
            <div class="course-info">
                has successfully completed the course<br>
                <div class="course-name">' . htmlspecialchars($data['course_name']) . '</div>
                <div style="font-size: 18px; margin-top: 15px; color: #64748b;">
                    Duration: ' . $data['duration_months'] . ' months
                </div>
            </div>
            
            <div class="achievement-section">
                <h3 style="margin-top: 0; color: #1e40af;">Assessment Performance</h3>
                <div class="grade-display">
                    <div class="grade-circle">' . $grade . '</div>
                    <div class="marks-info">
                        <h4>Assessment: ' . htmlspecialchars($data['assessment_name']) . '</h4>
                        <p><strong>Marks Obtained:</strong> ' . $data['marks_obtained'] . ' out of ' . $data['total_marks'] . '</p>
                        <p><strong>Percentage:</strong> ' . number_format($percentage, 2) . '%</p>
                        <p><strong>Passing Marks:</strong> ' . $data['passing_marks'] . '%</p>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <div class="cert-details">
                    <div class="cert-number">Certificate No: ' . $certificateNumber . '</div>
                    <div class="cert-number">Enrollment No: ' . htmlspecialchars($data['enrollment_number']) . '</div>
                    <div class="cert-number">Student ID: ' . $data['student_id'] . '</div>
                </div>
                <div class="date-section">
                    <div style="color: #64748b; font-size: 16px; margin-bottom: 5px;">Issued on</div>
                    <div class="date">' . date('F d, Y') . '</div>
                </div>
                <div class="qr-section">
                    ' . ($qrCodeBase64 ? '<img src="' . $qrCodeBase64 . '" class="qr-code" alt="QR Code">' : '') . '
                    <div class="qr-text">Verify Online</div>
                </div>
            </div>
            
            <div class="seal">
                OFFICIAL<br>SEAL
            </div>
        </div>
    </body>
    </html>';
}

// If running from command line
if (php_sapi_name() === 'cli') {
    // Script is being run from command line
    exit(0);
}
?>

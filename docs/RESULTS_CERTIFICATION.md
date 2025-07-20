# Results & Certification Module

This module handles assessment results and automatic certificate generation for the Student Management System.

## Features

### 1. Results Management
- **Comprehensive Results Display**: View all assessment results with detailed information
- **Advanced Filtering**: Filter by course, status, date range, and search functionality
- **Role-based Access**: Different views for admin, training partners, and students
- **Real-time Statistics**: Pass rates, total results, and certificate counts

### 2. Automatic Certificate Generation
- **Auto-generation**: Certificates are automatically generated when students pass assessments
- **Beautiful Design**: Professional certificate templates with modern styling
- **QR Code Integration**: Each certificate includes a QR code for verification
- **PDF Export**: Certificates can be generated as PDF files (requires wkhtmltopdf)

### 3. Certificate Verification
- **Public Verification Portal**: `/verify_certificate.php` for public certificate verification
- **Search by Certificate Number**: Verify using certificate number or enrollment number
- **Secure Verification**: All certificates are stored securely in the database
- **Download Option**: Verified certificates can be downloaded directly

### 4. Template Management
- **Customizable Templates**: Admin can upload custom certificate backgrounds
- **Multiple Formats**: Support for JPG, PNG, and PDF templates
- **Settings Integration**: Template paths and settings managed through system settings

## File Structure

```
public/
├── results.php              # Main results management page
├── verify_certificate.php   # Public certificate verification portal
└── uploads/certificates/    # Certificate storage directory
    ├── generated/          # Generated certificate files
    ├── qr_codes/          # QR code images
    └── templates/         # Certificate templates

scripts/
└── auto_generate_certificates.php  # Background certificate generation script
```

## Database Tables

### certificates
```sql
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    result_id INT,
    certificate_number VARCHAR(100) UNIQUE NOT NULL,
    issued_date DATE,
    certificate_path VARCHAR(500),
    qr_code_path VARCHAR(500),
    status ENUM('generated', 'issued') DEFAULT 'generated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (result_id) REFERENCES results(id) ON DELETE CASCADE
);
```

### results
```sql
CREATE TABLE results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    assessment_id INT,
    marks_obtained INT,
    total_marks INT,
    percentage DECIMAL(5,2),
    status ENUM('pass', 'fail'),
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);
```

## Certificate Generation Process

### 1. Automatic Generation
When a student completes an assessment and passes:
1. Result is saved with status 'pass'
2. Certificate generation is triggered automatically
3. Unique certificate number is generated
4. QR code is created with verification data
5. Certificate HTML/PDF is generated
6. Record is saved to certificates table

### 2. Manual Generation
Administrators and training partners can:
- Regenerate certificates for any passed student
- Bulk generate certificates using the background script
- Upload and manage certificate templates

### 3. Certificate Number Format
```
CERT-YYYY-NNNNNN-TIMESTAMP
```
- CERT: Prefix
- YYYY: Year
- NNNNNN: 6-digit student ID (zero-padded)
- TIMESTAMP: Unix timestamp for uniqueness

## QR Code Data
Each certificate includes a QR code containing:
```json
{
    "certificate_number": "CERT-2025-000001-1234567890",
    "student_name": "John Doe",
    "course_name": "Web Development",
    "enrollment_number": "STU001",
    "grade": "A+",
    "percentage": "95.50",
    "issued_date": "2025-01-20",
    "verification_url": "https://yourdomain.com/verify_certificate.php?q=CERT-2025-000001-1234567890"
}
```

## Grading System
```php
function calculateGrade($percentage) {
    if ($percentage >= 95) return 'A+';
    if ($percentage >= 85) return 'A';
    if ($percentage >= 75) return 'B+';
    if ($percentage >= 65) return 'B';
    if ($percentage >= 55) return 'C';
    return 'F';
}
```

## API Endpoints

### Certificate Verification API
**Endpoint**: `verify_certificate.php?q={certificate_number}`
**Method**: GET
**Response**: HTML page with certificate details or error message

### Certificate Download
**Endpoint**: Direct file download from generated certificate path
**Security**: File paths are validated and secured

## Background Script Usage

### Manual Execution
```bash
cd scripts/
php auto_generate_certificates.php
```

### Cron Job Setup
Add to crontab for automatic generation:
```bash
# Run every hour to generate certificates for new passed students
0 * * * * /usr/bin/php /path/to/scripts/auto_generate_certificates.php
```

## Security Features

### 1. Access Control
- Role-based access to results and certificate management
- Students can only view their own results
- Training partners can only view students from their center

### 2. File Security
- Certificate files stored outside web root when possible
- File access controlled through application logic
- QR codes and certificates validated before display

### 3. Data Validation
- Certificate numbers validated for uniqueness
- File uploads restricted to allowed types
- Input sanitization for all form data

## Configuration

### Required Settings
Add to `settings` table:
```sql
INSERT INTO settings (setting_key, setting_value) VALUES
('certificate_template_background', '../uploads/certificates/templates/background.jpg'),
('assessment_passing_marks', '70'),
('certificate_template_path', '../uploads/certificates/templates/');
```

### File Permissions
```bash
mkdir -p uploads/certificates/{generated,qr_codes,templates}
chmod 755 uploads/certificates/
chmod 755 uploads/certificates/*
```

## Dependencies

### Required PHP Extensions
- GD or ImageMagick (for image processing)
- cURL (for QR code generation)
- PDO MySQL (database access)

### Optional Dependencies
- **wkhtmltopdf**: For PDF certificate generation
- **ImageMagick**: For advanced image processing
- **TCPDF**: Alternative PDF generation library

## Installation Instructions

### 1. File Setup
```bash
# Create required directories
mkdir -p uploads/certificates/{generated,qr_codes,templates}

# Set permissions
chmod 755 uploads/certificates/
chmod 755 uploads/certificates/*
```

### 2. Database Setup
Tables are automatically created when accessing the application.

### 3. Configuration
Update settings through the Masters module or directly in database.

## Troubleshooting

### Common Issues

1. **QR Code Generation Fails**
   - Check internet connectivity
   - Verify QR code service availability
   - Consider using local QR code library

2. **PDF Generation Issues**
   - Install wkhtmltopdf: `apt-get install wkhtmltopdf`
   - Check file permissions
   - Verify output directory exists

3. **Certificate Files Not Found**
   - Check file paths in database
   - Verify directory permissions
   - Ensure files exist on filesystem

### Debug Mode
Enable debugging by adding to script:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Future Enhancements

### Planned Features
1. **Email Integration**: Automatic certificate delivery via email
2. **WhatsApp Integration**: Certificate notifications via WhatsApp
3. **Blockchain Verification**: Immutable certificate verification
4. **Digital Signatures**: Cryptographic certificate signing
5. **Batch Processing**: Bulk certificate operations
6. **Analytics Dashboard**: Certificate generation analytics

### Template Enhancements
1. **WYSIWYG Editor**: Visual template editing
2. **Variable Positioning**: Drag-and-drop field placement
3. **Multiple Templates**: Different templates per course/sector
4. **Watermarks**: Background watermarks and security features

## Support

For technical support or feature requests, please contact the development team or refer to the main system documentation.

## License

This module is part of the Student Management System and follows the same licensing terms as the main application.

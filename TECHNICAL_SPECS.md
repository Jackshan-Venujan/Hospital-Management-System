# 🔧 Technical Specifications & Architecture

## System Architecture Overview

### Technology Stack
```
Frontend Layer:
├── HTML5 + CSS3
├── Bootstrap 5.1.3 (Responsive Framework)
├── Font Awesome 6.0 (Icons)
├── JavaScript (ES6+)
└── Custom CSS (assets/css/style.css)

Backend Layer:
├── PHP 8.0+ (Server-side scripting)
├── MySQLi (Database interaction)
├── Session Management (User authentication)
├── File I/O (Export functionality)
└── Security Libraries (Password hashing, input validation)

Database Layer:
├── MySQL 5.7+ (Primary database)
├── InnoDB Storage Engine
├── UTF-8 Character Set
├── Referential Integrity (Foreign keys)
└── Prepared Statements (SQL injection prevention)

Server Layer:
├── Apache HTTP Server (Web server)
├── XAMPP Stack (Development environment)
├── PHP-FPM (Process management)
└── mod_rewrite (URL routing)
```

---

## Database Architecture

### Entity Relationship Diagram
```
[USERS] ──┐
          ├── 1:1 ──> [PATIENTS]
          └── 1:1 ──> [DOCTORS] ──> Many:1 ──> [DEPARTMENTS]
                          │
[APPOINTMENTS] ──> Many:1 ──┴── Many:1 ──> [PATIENTS]
      │
      └── 1:Many ──> [BILLING]
```

### Core Database Tables

#### 1. Users Table
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','patient') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Indexes:**
- Primary Key: `id`
- Unique Index: `username`
- Composite Index: `role`, `status`

#### 2. Patients Table
```sql
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `patient_id` varchar(20) NOT NULL UNIQUE,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_name` (`first_name`, `last_name`),
  KEY `idx_phone` (`phone`),
  CONSTRAINT `fk_patients_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 3. Doctors Table
```sql
CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL UNIQUE,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` text DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `department_id` int(11) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `schedule_start` time DEFAULT NULL,
  `schedule_end` time DEFAULT NULL,
  `available_days` varchar(50) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `idx_name` (`first_name`, `last_name`),
  KEY `idx_specialization` (`specialization`),
  CONSTRAINT `fk_doctors_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doctors_departments` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 4. Departments Table
```sql
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_doctor_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `head_doctor_id` (`head_doctor_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 5. Appointments Table
```sql
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('scheduled','completed','cancelled','no-show') DEFAULT 'scheduled',
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `idx_date` (`appointment_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_appointments_patients` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_doctors` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 6. Billing Table
```sql
CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','refunded') DEFAULT 'pending',
  `payment_method` enum('cash','card','insurance','online') DEFAULT NULL,
  `billing_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `payment_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_billing_appointments` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_billing_patients` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## File Structure Architecture

### Directory Layout
```
Hospital_Management_System/
│
├── 📁 admin/                    # Administrative panel
│   ├── dashboard.php           # Admin dashboard with statistics
│   ├── patients.php            # Patient management interface
│   ├── patient_details.php     # Patient details modal
│   ├── patient_edit.php        # Patient edit modal
│   ├── export_patients.php     # Patient data export
│   ├── doctors.php             # Doctor management interface
│   ├── doctor_details.php      # Doctor details modal
│   ├── doctor_edit.php         # Doctor edit modal
│   ├── export_doctors.php      # Doctor data export
│   └── test_doctors.php        # Doctor system testing
│
├── 📁 assets/                   # Static resources
│   ├── 📁 css/
│   │   └── style.css           # Custom stylesheet (15KB)
│   ├── 📁 images/              # Image assets directory
│   │   ├── logo.png            # Hospital logo
│   │   └── avatars/            # User avatar placeholders
│   └── 📁 js/
│       └── script.js           # JavaScript functionality (8KB)
│
├── 📁 database/                 # Database files
│   ├── hospital_management.sql # Complete database schema
│   └── sample_data.sql         # Optional sample data
│
├── 📁 includes/                 # Core system components
│   ├── config.php              # Database connection & core functions
│   ├── header.php              # Common HTML header
│   └── footer.php              # Common HTML footer
│
├── 📁 pages/                    # Public user pages
│   └── patient_registration.php # Patient self-registration
│
├── 📄 index.php                 # Main landing page
├── 📄 login.php                 # Universal login system
├── 📄 logout.php                # Session cleanup
└── 📄 README.md                 # Project overview
```

### File Dependencies
```
Core Dependencies:
config.php ──┐
             ├── All PHP files depend on this
             ├── Database connection
             ├── Authentication functions
             └── Utility functions

header.php ──┐
             ├── All user-facing pages include this
             ├── Navigation menu
             ├── Bootstrap/CSS includes
             └── User session display

footer.php ──┐
             ├── All user-facing pages include this
             ├── JavaScript includes
             └── Footer content
```

---

## Security Architecture

### Authentication System
```php
// Password Security
- Hashing Algorithm: PHP password_hash() with PASSWORD_DEFAULT
- Salt: Automatically generated per password
- Hash Length: 255 characters storage
- Verification: password_verify() function

// Session Security  
- Session ID Regeneration: On login and privilege changes
- Session Timeout: 30 minutes of inactivity
- Secure Cookies: HttpOnly and Secure flags
- Session Fixation: Prevention through regeneration

// Role-Based Access Control
function check_role_access($allowed_roles) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: ../login.php?error=access_denied');
        exit();
    }
}
```

### Data Protection
```php
// SQL Injection Prevention
$db->query("SELECT * FROM users WHERE username = :username");
$db->bind(':username', $username);
$result = $db->single();

// XSS Protection
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// File Upload Security
$allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size = 5 * 1024 * 1024; // 5MB limit
```

---

## Performance Specifications

### Database Performance
```sql
-- Optimized Queries
-- Patient search with indexes
SELECT p.*, u.status 
FROM patients p 
JOIN users u ON p.user_id = u.id 
WHERE p.first_name LIKE :search 
   OR p.last_name LIKE :search 
   OR p.patient_id LIKE :search
ORDER BY p.created_at DESC 
LIMIT :offset, :limit;

-- Doctor search with department join
SELECT d.*, dept.name as department_name, u.status
FROM doctors d 
LEFT JOIN departments dept ON d.department_id = dept.id
JOIN users u ON d.user_id = u.id
WHERE d.specialization = :spec
  AND u.status = 'active'
ORDER BY d.first_name, d.last_name;
```

### Caching Strategy
```php
// Session-based caching for user data
if (!isset($_SESSION['user_cache']) || 
    $_SESSION['cache_time'] < time() - 300) {
    // Refresh cache every 5 minutes
    $_SESSION['user_cache'] = get_user_data($_SESSION['user_id']);
    $_SESSION['cache_time'] = time();
}
```

### File Size Optimization
- **CSS**: Minified, 15KB total
- **JavaScript**: ES6+, 8KB total  
- **Images**: Optimized PNGs, WebP support
- **Database**: Indexed for performance

---

## API Architecture (Future Enhancement)

### REST Endpoints Structure
```
GET    /api/patients           # List all patients
POST   /api/patients           # Create new patient
GET    /api/patients/{id}      # Get specific patient
PUT    /api/patients/{id}      # Update patient
DELETE /api/patients/{id}      # Delete patient

GET    /api/doctors            # List all doctors
POST   /api/doctors            # Create new doctor
GET    /api/doctors/{id}       # Get specific doctor
PUT    /api/doctors/{id}       # Update doctor

GET    /api/appointments       # List appointments
POST   /api/appointments       # Schedule appointment
PUT    /api/appointments/{id}  # Update appointment
```

### Response Format
```json
{
  "status": "success|error",
  "data": {
    "id": 123,
    "patient_id": "PAT001",
    "first_name": "John",
    "last_name": "Doe"
  },
  "message": "Operation completed successfully",
  "timestamp": "2025-10-02T10:30:00Z"
}
```

---

## Configuration Management

### Environment Configuration
```php
// config.php - Environment Variables
define('ENVIRONMENT', 'development'); // production, development, testing

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); 
define('DB_PASS', '');
define('DB_NAME', 'hospital_management');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('SITE_NAME', 'Hospital Management System');
define('SITE_URL', 'http://localhost/Hospital_Management_System');
define('ADMIN_EMAIL', 'admin@hospital.com');

// Security Configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx');
```

### System Requirements
```
Minimum Requirements:
├── PHP: 8.0.0 or higher
├── MySQL: 5.7.0 or higher
├── Apache: 2.4.0 or higher
├── Memory: 256MB PHP memory limit
├── Storage: 100MB minimum free space
└── Extensions: mysqli, session, json, fileinfo

Recommended Requirements:
├── PHP: 8.1+ 
├── MySQL: 8.0+
├── Apache: 2.4.41+
├── Memory: 512MB PHP memory limit
├── Storage: 1GB+ free space
└── Extensions: All minimum + mbstring, curl, gd
```

---

## Deployment Architecture

### Development Environment
```
Local Development (XAMPP):
├── Apache: localhost:80
├── MySQL: localhost:3306  
├── PHP: Built-in with XAMPP
├── phpMyAdmin: localhost/phpmyadmin
└── File Path: C:\xampp\htdocs\Hospital_Management_System\
```

### Production Environment (Recommended)
```
Production Server:
├── Web Server: Apache 2.4+ with mod_rewrite
├── Database: MySQL 8.0+ with InnoDB engine
├── PHP: 8.1+ with required extensions
├── SSL: HTTPS certificate required
├── Backup: Daily automated database backups
└── Monitoring: Error logging and performance monitoring
```

### Security Hardening
```apache
# .htaccess for production
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Prevent direct access to sensitive files
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

# Hide PHP version
Header unset X-Powered-By
```

---

## Monitoring & Logging

### Error Logging
```php
// Custom error logging
function log_error($message, $file = '', $line = '') {
    $log_message = date('Y-m-d H:i:s') . " - ERROR: {$message}";
    if ($file) $log_message .= " in {$file}";
    if ($line) $log_message .= " on line {$line}";
    
    error_log($log_message, 3, '../logs/system_errors.log');
}

// Database query logging
function log_query($query, $execution_time) {
    if ($execution_time > 1.0) { // Log slow queries
        $log_entry = date('Y-m-d H:i:s') . " - SLOW QUERY ({$execution_time}s): {$query}\n";
        file_put_contents('../logs/slow_queries.log', $log_entry, FILE_APPEND);
    }
}
```

### Performance Monitoring
```php
// Page load time tracking
$start_time = microtime(true);

// ... page content ...

$end_time = microtime(true);
$load_time = $end_time - $start_time;

if ($load_time > 2.0) {
    log_performance_issue($_SERVER['REQUEST_URI'], $load_time);
}
```

---

## Backup & Recovery

### Database Backup Strategy
```bash
# Daily automated backup
mysqldump -u root -p hospital_management > backup_$(date +%Y%m%d).sql

# Weekly full backup with compression
mysqldump -u root -p hospital_management | gzip > backup_weekly_$(date +%Y%m%d).sql.gz

# Monthly archive backup
tar -czf hospital_backup_$(date +%Y%m).tar.gz /var/www/hospital/
```

### Recovery Procedures
```bash
# Restore from backup
mysql -u root -p hospital_management < backup_20251002.sql

# Verify data integrity after restore
mysql -u root -p -e "
USE hospital_management;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_patients FROM patients;
SELECT COUNT(*) as total_doctors FROM doctors;
"
```

---

*This technical specification provides the complete architecture overview for the Hospital Management System. It should be used as a reference for development, deployment, and maintenance activities.*
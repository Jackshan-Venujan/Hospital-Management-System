# 🛠️ Troubleshooting Guide

## Quick Diagnosis Checklist

### ✅ System Health Check
```
□ XAMPP Control Panel shows Apache and MySQL as "Running" (green)
□ Can access http://localhost/Hospital_Management_System/
□ Can access http://localhost/phpmyadmin
□ Database 'hospital_management' exists in phpMyAdmin
□ Can login with admin credentials (admin/admin123)
```

If any item fails, see the corresponding section below.

---

## 🚨 Critical Issues & Solutions

### 1. System Won't Load - Blank Page

**Symptoms:**
- Browser shows blank white page
- No error messages visible
- URL shows correct path

**Diagnosis Steps:**
```
Step 1: Check PHP Error Logs
- Open: C:\xampp\apache\logs\error.log
- Look for recent PHP errors

Step 2: Enable Error Display
- Edit: C:\xampp\php\php.ini
- Set: display_errors = On
- Set: error_reporting = E_ALL
- Restart Apache

Step 3: Test Simple PHP
- Create test.php with: <?php echo "PHP is working"; ?>
- Access via browser to verify PHP execution
```

**Common Solutions:**
```
Fatal Error - Autoload:
├── Edit includes/config.php
├── Replace: function __autoload($class)
└── With: spl_autoload_register(function($class) { ... });

Memory Limit Exceeded:
├── Edit: C:\xampp\php\php.ini
├── Find: memory_limit = 128M
└── Change to: memory_limit = 256M

Missing Extensions:
├── Edit: C:\xampp\php\php.ini
├── Uncomment: extension=mysqli
└── Restart Apache
```

### 2. Database Connection Failed

**Error Messages:**
- "Connection failed: Access denied for user 'root'@'localhost'"
- "Unknown database 'hospital_management'"
- "Can't connect to MySQL server"

**Diagnosis Steps:**
```
Step 1: Verify MySQL Service
- XAMPP Control Panel → MySQL should show "Running"
- If not running, click "Start"

Step 2: Test Database Connection
- Open phpMyAdmin: http://localhost/phpmyadmin
- Should login without password

Step 3: Check Database Exists
- In phpMyAdmin, look for 'hospital_management' database
- If missing, import: database/hospital_management.sql
```

**Solutions:**
```
MySQL Service Won't Start:
├── Check if port 3306 is in use
├── Stop conflicting services (Skype, other MySQL)
├── Run XAMPP as Administrator
└── Check Windows Services for MySQL conflicts

Database Missing:
├── Open phpMyAdmin
├── Create new database: 'hospital_management'
├── Import SQL file: database/hospital_management.sql
└── Verify tables are created

Wrong Credentials:
├── Edit includes/config.php
├── Verify: DB_USER = 'root'
├── Verify: DB_PASS = '' (empty for XAMPP)
└── Verify: DB_HOST = 'localhost'
```

### 3. Login Issues

**Common Login Problems:**

#### "Invalid username or password"
```
For Patients:
├── Verify using USERNAME, not Patient ID
├── Patient ID (PAT001) is for records only
├── Login with chosen username during registration
└── Check account status in admin panel

For Doctors:  
├── Verify using USERNAME, not Employee ID
├── Employee ID (DOC001) is for identification only
├── Use admin-assigned username
└── Confirm account is active

For Admin:
├── Default: admin / admin123
├── Check users table in database
├── Password may have been changed
└── Reset via database if needed
```

#### Account Access Issues
```
Account Inactive:
├── Admin login required to reactivate
├── Go to Users management section
├── Change status from 'inactive' to 'active'
└── User can then login immediately

Password Reset Needed:
├── Admin can reset any user password
├── Edit user in admin panel
├── Set temporary password
└── User changes on next login
```

### 4. Page Permission Errors

**Error Messages:**
- "Access denied"
- "Unauthorized access"
- Redirected to login page repeatedly

**Solutions:**
```
Role Access Issues:
├── Verify user role in database (admin, doctor, patient)
├── Check if trying to access correct dashboard
├── Admin pages require admin role
└── Clear browser cache and cookies

Session Problems:
├── Clear all cookies for localhost
├── Close and reopen browser
├── Check PHP session configuration
└── Verify session files in C:\xampp\tmp\
```

---

## 🔧 Performance Issues

### 1. Slow Page Loading

**Symptoms:**
- Pages take more than 5 seconds to load
- Database queries timeout
- Export functions hang

**Diagnosis:**
```
Step 1: Check Database Performance
- Open phpMyAdmin
- Go to Status → Variables
- Look for slow_query_log_file

Step 2: Monitor PHP Memory Usage
- Add to top of problematic page:
  echo "Memory: " . memory_get_usage() . " bytes";

Step 3: Check Apache Error Logs
- Look for timeout errors in error.log
```

**Solutions:**
```
Database Optimization:
├── Run in phpMyAdmin: OPTIMIZE TABLE users, patients, doctors;
├── Check for missing indexes
├── Limit result sets with LIMIT clauses
└── Use prepared statements for repeated queries

PHP Performance:
├── Increase memory_limit in php.ini
├── Enable OPcache extension
├── Optimize file includes
└── Use session caching for user data

Large Dataset Issues:
├── Implement pagination for patient/doctor lists
├── Add database indexes for search fields
├── Use AJAX for modal loading
└── Cache frequently accessed data
```

### 2. Export Functions Not Working

**Symptoms:**
- Export buttons don't respond
- Downloaded files are empty or corrupted
- Browser shows download errors

**Solutions:**
```
Browser Issues:
├── Check popup blocker settings
├── Allow downloads from localhost
├── Try different browser (Chrome, Firefox, Edge)
└── Clear browser cache

File Permissions:
├── Verify write permissions on export directory
├── Check temp folder permissions
├── Run XAMPP as Administrator
└── Verify PHP file_get_contents() works

PHP Configuration:
├── Check max_execution_time in php.ini
├── Increase memory_limit for large exports
├── Verify file upload/download limits
└── Check for output buffering issues
```

---

## 💾 Database Issues

### 1. Data Corruption or Missing Data

**Symptoms:**
- Patient/doctor information missing
- Foreign key constraint errors
- Duplicate entry errors

**Diagnosis:**
```
Step 1: Check Table Integrity
- Run: CHECK TABLE users, patients, doctors;
- Look for corruption warnings

Step 2: Verify Foreign Key Constraints
- Run: SELECT * FROM information_schema.KEY_COLUMN_USAGE 
        WHERE CONSTRAINT_SCHEMA = 'hospital_management';

Step 3: Check for Orphaned Records
- Find patients without users:
  SELECT * FROM patients p 
  LEFT JOIN users u ON p.user_id = u.id 
  WHERE u.id IS NULL;
```

**Solutions:**
```
Restore from Backup:
├── Stop all operations
├── Export current data as backup
├── Import known good backup: mysql -u root hospital_management < backup.sql
└── Verify data integrity

Fix Foreign Key Issues:
├── Disable foreign key checks: SET foreign_key_checks = 0;
├── Fix data relationships
├── Re-enable checks: SET foreign_key_checks = 1;
└── Verify constraints pass

Clean Orphaned Data:
├── Remove patients without users
├── Remove doctors without departments (if required)
├── Update any invalid status values
└── Rebuild indexes if necessary
```

### 2. Auto-Increment Issues

**Symptoms:**
- Duplicate Patient/Employee IDs
- ID generation errors
- Database insertion failures

**Solutions:**
```sql
-- Reset Auto-Increment Counters
ALTER TABLE patients AUTO_INCREMENT = 1;
ALTER TABLE doctors AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

-- Fix Patient ID Generation
UPDATE patients SET patient_id = CONCAT('PAT', LPAD(id, 3, '0')) WHERE patient_id IS NULL;

-- Fix Employee ID Generation  
UPDATE doctors SET employee_id = CONCAT('DOC', LPAD(id, 3, '0')) WHERE employee_id IS NULL;
```

---

## 🌐 Web Server Issues

### 1. Apache Won't Start

**Common Causes & Solutions:**

#### Port 80 Conflict
```
Diagnosis:
├── Open Command Prompt as Administrator
├── Run: netstat -ano | findstr :80
├── Look for process using port 80

Solutions:
├── Stop IIS service: net stop iisadmin
├── Stop Skype (older versions use port 80)
├── Change Apache port in httpd.conf
└── Use port 8080 instead: http://localhost:8080/
```

#### Service Permission Issues
```
Solutions:
├── Run XAMPP Control Panel as Administrator
├── Right-click → "Run as administrator"
├── Install Apache as Windows Service
└── Check Windows Firewall settings
```

### 2. File Permission Problems

**Symptoms:**
- Cannot save uploaded files
- Config files cannot be modified
- Export functions fail

**Solutions:**
```
Windows Permissions:
├── Right-click Hospital_Management_System folder
├── Properties → Security → Edit
├── Give "Users" full control
└── Apply to all subfolders

PHP File Permissions:
├── Check upload_tmp_dir in php.ini
├── Verify file_uploads = On
├── Check upload_max_filesize setting
└── Ensure proper directory permissions
```

---

## 🔐 Security Issues

### 1. Authentication Bypassing

**Symptoms:**
- Users accessing pages without login
- Role restrictions not working
- Session not maintained

**Diagnosis:**
```php
// Add to problematic pages for debugging
echo "Session User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "Session Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive');
```

**Solutions:**
```
Session Configuration:
├── Check session.save_path in php.ini
├── Verify session files are being created
├── Check session.gc_maxlifetime setting
└── Clear session files from tmp directory

Code Fixes:
├── Ensure session_start() is called on every page
├── Add check_role_access() to protected pages
├── Verify logout.php destroys sessions properly
└── Check for session fixation vulnerabilities
```

### 2. SQL Injection Vulnerabilities

**Symptoms:**
- Unexpected database errors
- Data corruption from user input
- Security scanner alerts

**Solutions:**
```php
// Replace direct SQL with prepared statements
// BAD:
$query = "SELECT * FROM users WHERE username = '" . $_POST['username'] . "'";

// GOOD:
$db->query("SELECT * FROM users WHERE username = :username");
$db->bind(':username', $_POST['username']);
```

---

## 🗂️ File System Issues

### 1. Missing Files or Incorrect Paths

**Symptoms:**
- "File not found" errors
- Broken CSS/JavaScript links
- Include/require failures

**Diagnosis:**
```
Step 1: Verify File Structure
- Check that all files match the documented structure
- Verify case-sensitive file names
- Ensure no spaces in file/folder names

Step 2: Check File Paths
- Use absolute paths: C:\xampp\htdocs\Hospital_Management_System\
- Verify include paths in PHP files
- Check URL rewriting configuration
```

**Solutions:**
```
Fix Include Paths:
├── Use: require_once '../includes/config.php';
├── From admin folder: require_once '../includes/config.php';
├── From root folder: require_once 'includes/config.php';
└── Verify relative path accuracy

Fix Asset Paths:
├── CSS: ../assets/css/style.css
├── JS: ../assets/js/script.js  
├── Images: ../assets/images/logo.png
└── Use absolute URLs if necessary
```

### 2. Upload Directory Issues

**Symptoms:**
- File uploads fail
- "Permission denied" errors
- Uploaded files disappear

**Solutions:**
```
Create Upload Directories:
├── Create: Hospital_Management_System/uploads/
├── Create subdirectories: /patients/, /doctors/, /temp/
├── Set write permissions for all
└── Add .htaccess to prevent direct access

PHP Configuration:
├── file_uploads = On
├── upload_max_filesize = 10M
├── post_max_size = 10M
├── max_execution_time = 300
└── upload_tmp_dir = C:\xampp\tmp
```

---

## 📊 Data Export Issues

### 1. CSV Export Problems

**Symptoms:**
- Downloaded CSV files are empty
- Special characters appear garbled
- File cannot be opened in Excel

**Solutions:**
```php
// Fix CSV encoding issues
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export.csv"');

// Add BOM for Excel compatibility
echo "\xEF\xBB\xBF";

// Properly escape CSV data
fputcsv($output, $row, ',', '"');
```

### 2. PDF Export Issues

**Symptoms:**
- PDF files won't download
- Corrupted PDF content
- Browser shows HTML instead of PDF

**Solutions:**
```php
// For PDF export (HTML-based)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="report.html"');

// Or use a PDF library like TCPDF
require_once('tcpdf/tcpdf.php');
$pdf = new TCPDF();
```

---

## 🔄 System Maintenance

### Daily Maintenance Checklist
```
□ Check XAMPP services status
□ Review error logs for new issues
□ Verify database backups completed
□ Test critical login functionality
□ Monitor disk space usage
```

### Weekly Maintenance Tasks
```
□ Run database optimization: OPTIMIZE TABLE
□ Clear temporary files and session data
□ Review user accounts for inactive users
□ Update system documentation
□ Test backup restoration procedure
```

### Monthly Maintenance Tasks
```
□ Archive old log files
□ Review and update user passwords
□ Analyze system performance metrics
□ Update PHP/MySQL if needed
□ Review security configurations
```

---

## 🆘 Emergency Recovery Procedures

### System Completely Down
```
Step 1: Check Basic Services
├── XAMPP Control Panel - restart all services
├── Check Windows Services for MySQL/Apache
├── Verify no port conflicts (80, 3306)
└── Run XAMPP as Administrator

Step 2: Database Recovery
├── Access phpMyAdmin
├── If database missing, import backup
├── Check table structure integrity
└── Verify sample data exists

Step 3: File System Recovery
├── Verify all PHP files exist
├── Check file permissions
├── Restore from backup if necessary
└── Test with simple PHP file first
```

### Data Loss Recovery
```
Step 1: Immediate Actions
├── Stop all system access immediately
├── Do not make any database changes
├── Identify scope of data loss
└── Locate most recent backup

Step 2: Recovery Process
├── Create current state backup first
├── Restore from most recent good backup
├── Compare with current state
├── Manually recover recent changes if possible
└── Thoroughly test system before allowing access
```

---

## 📞 Getting Additional Help

### Before Contacting Support
1. **Gather Information:**
   - Exact error messages (copy/paste)
   - Steps to reproduce the issue
   - Browser and version being used
   - Operating system details
   - Recent changes made to the system

2. **Try Basic Solutions:**
   - Restart XAMPP services
   - Clear browser cache and cookies
   - Try different browser
   - Check error logs

3. **Document the Issue:**
   - Screenshot error messages
   - Note time when error occurred
   - List what was being attempted
   - Record any temporary solutions tried

### Log Files to Check
```
Apache Errors: C:\xampp\apache\logs\error.log
PHP Errors: C:\xampp\php\logs\php_error_log
MySQL Errors: C:\xampp\mysql\data\*.err
System Logs: Windows Event Viewer → Application
```

### Useful Diagnostic Commands
```sql
-- Database diagnostics
SHOW PROCESSLIST;
SHOW ENGINE INNODB STATUS;
CHECK TABLE users, patients, doctors;

-- System info
SELECT VERSION(); -- MySQL version
SELECT @@VERSION_COMMENT; -- MySQL distribution
SHOW VARIABLES LIKE 'character_set%';
```

---

*This troubleshooting guide should help resolve most common issues with the Hospital Management System. For persistent problems, ensure you have the latest backups before making any system changes.*
# 🔐 System Credentials & Access Guide

## Default User Accounts

### 👨‍💼 Administrator Account
```
Username: admin
Password: admin123
Role: Administrator
Access Level: Full System Control
```

**Admin Capabilities:** 
- Manage all patients and doctors
- View system statistics and reports
- Export data in CSV/PDF formats
- User account management
- System configuration access

**Login URL:** `http://localhost/Hospital_Management_System/login.php`

---

### 👨‍⚕️ Doctor Accounts

#### Primary Doctor Account
```
Username: dr.smith
Password: doctor123
Employee ID: DOC001
Name: Dr. John Smith
Specialization: General Medicine
Department: General Medicine
Status: Active
```

#### Sample Doctor Account 2
```
Username: dr.wilson
Password: doctor123
Employee ID: DOC002  
Name: Dr. Sarah Wilson
Specialization: Cardiology
Department: Cardiology
Status: Active
```

**Doctor Capabilities:**
- View assigned patients
- Manage appointment schedules
- Update professional profile
- Access patient medical records
- Generate consultation reports

---

### 👤 Patient Accounts

#### Primary Patient Account
```
Username: john.doe
Password: patient123
Patient ID: PAT001
Name: John Doe
DOB: 1985-06-15
Blood Type: O+
Status: Active
```

#### Sample Patient Account 2
```
Username: jane.smith
Password: patient123
Patient ID: PAT002
Name: Jane Smith  
DOB: 1990-03-22
Blood Type: A-
Status: Active
```

**Patient Capabilities:**
- View personal medical history
- Schedule appointments
- Update contact information
- Access test results
- Download medical reports

---

## 🗄️ Database Access

### MySQL Database Credentials
```
Host: localhost
Port: 3306
Database Name: hospital_management
Username: root
Password: (empty - XAMPP default)
Charset: utf8mb4
```

### phpMyAdmin Access
```
URL: http://localhost/phpmyadmin
Username: root
Password: (empty)
```

---

## 🚀 Quick Start Guide

### 1. First Time Setup
```bash
# 1. Start XAMPP Services
- Open XAMPP Control Panel
- Start Apache and MySQL services
- Verify both services are running (green status)

# 2. Access the System
- Open web browser
- Navigate to: http://localhost/Hospital_Management_System/
- Click "Login" button

# 3. Test Admin Access
- Use admin credentials above
- Explore admin dashboard
- Test patient and doctor management
```

### 2. Testing Patient Registration
```bash
# Method 1: Self Registration
1. Go to: http://localhost/Hospital_Management_System/
2. Click "Register as Patient"
3. Fill out registration form
4. Submit and note the generated Patient ID
5. Login with your chosen username/password

# Method 2: Admin Registration  
1. Login as admin
2. Go to Patients section
3. Click "Add New Patient"
4. Fill patient details
5. Set username and password
6. Test login with new credentials
```

### 3. Testing Doctor Management
```bash
# Add New Doctor (Admin Only)
1. Login as admin
2. Navigate to "Doctors" section  
3. Click "Add New Doctor"
4. Fill professional information:
   - Personal details
   - Specialization and qualifications
   - Department assignment
   - Schedule and consultation fees
5. Set account credentials
6. Test login with doctor credentials
```

---

## 🔧 System Configuration

### File Permissions (Windows/XAMPP)
```
Hospital_Management_System/ - Read/Write
├── admin/ - Read/Write
├── assets/ - Read/Write  
├── includes/ - Read/Write
├── database/ - Read/Write
└── pages/ - Read/Write
```

### Important Configuration Files

#### `includes/config.php`
```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hospital_management');

// System Configuration
define('SITE_NAME', 'Hospital Management System');
define('SITE_URL', 'http://localhost/Hospital_Management_System');
```

---

## 🧪 Testing Procedures

### 1. Authentication Testing
```bash
# Test All User Types
1. Admin Login: admin / admin123
2. Doctor Login: dr.smith / doctor123  
3. Patient Login: john.doe / patient123

# Verify Role Access
1. Each role should see appropriate dashboard
2. Access restrictions should be enforced
3. Logout should work properly
```

### 2. Database Testing
```sql
-- Verify Tables Exist
SHOW TABLES;

-- Check Sample Data
SELECT * FROM users LIMIT 5;
SELECT * FROM patients LIMIT 5; 
SELECT * FROM doctors LIMIT 5;

-- Test Relationships
SELECT u.username, u.role, p.patient_id, p.first_name 
FROM users u 
JOIN patients p ON u.id = p.user_id 
LIMIT 3;
```

### 3. Feature Testing Checklist
- [ ] Patient registration works
- [ ] All user types can login
- [ ] Admin can view all patients/doctors
- [ ] Search and filter functions work
- [ ] Export functionality generates files
- [ ] Edit forms save changes properly
- [ ] Status changes take effect
- [ ] Navigation menu works correctly

---

## 🔐 Security Notes

### Password Policy
- **Minimum Length**: 6 characters (recommended: 8+)
- **Default Passwords**: Change immediately in production
- **Password Hashing**: PHP `password_hash()` with default algorithm
- **Password Reset**: Admin can reset any user password

### Session Security
- **Session Timeout**: 30 minutes of inactivity
- **Session Regeneration**: On login and role changes
- **Secure Cookies**: Enabled for HTTPS environments
- **CSRF Protection**: Implemented on all forms

### Data Protection
- **SQL Injection**: Prevented with prepared statements
- **XSS Protection**: Input sanitization and output escaping
- **File Upload**: Restricted to specific types and sizes
- **Access Control**: Role-based page restrictions

---

## 🆘 Emergency Access

### If You're Locked Out
```sql
-- Reset Admin Password (via phpMyAdmin)
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE username = 'admin';
-- This sets password to 'password'
```

### If Database is Corrupted
```bash
# Restore from Backup
1. Go to phpMyAdmin
2. Drop database 'hospital_management'
3. Create new database 'hospital_management'  
4. Import fresh SQL file: database/hospital_management.sql
5. Verify all tables are created
```

### If XAMPP Issues
```bash
# Common Solutions
1. Run XAMPP as Administrator
2. Check if ports 80/3306 are available
3. Restart Apache and MySQL services
4. Check XAMPP error logs
5. Verify Windows Firewall settings
```

---

## 📊 Default Data Summary

### Users in System
- **1 Admin**: Full system access
- **2 Doctors**: Professional accounts with schedules
- **2 Patients**: Sample patient records
- **Total**: 5 default user accounts

### Database Records
- **Departments**: 8 medical departments
- **Specializations**: 15+ medical specialties
- **Sample Appointments**: 5+ test appointments
- **System Tables**: 6 main tables + relationships

---

## 📞 Getting Help

### If You Need Support
1. **Check Documentation**: Review DOCUMENTATION.md
2. **Test Default Accounts**: Use provided credentials
3. **Check Error Logs**: XAMPP logs for PHP/MySQL errors
4. **Verify Prerequisites**: PHP 8.0+, MySQL 5.7+, Apache running
5. **Database Check**: Ensure hospital_management database exists

### Common Solutions
- **Blank Pages**: Check PHP error logs, verify file paths
- **Login Issues**: Verify database connection, check user table
- **Permission Errors**: Run XAMPP as Administrator
- **Export Problems**: Check file permissions, browser settings

---

*Keep these credentials secure and change default passwords in production environments.*
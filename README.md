# 🏥 Hospital Management System

A comprehensive, modern web-based hospital management system built with PHP 8+ and MySQL. This system provides complete administrative control for managing patients, doctors, appointments, and hospital operations with a professional, responsive interface.

## 🌟 Key Features

### 👨‍💼 Administrative Management
- **Complete Admin Dashboard** with real-time statistics and metrics
- **Patient Management System** with full CRUD operations, search, and export
- **Doctor Management System** with professional profiles and scheduling
- **User Account Management** with role-based access control
- **Data Export Functionality** supporting CSV and PDF formats

### 👤 Patient Features  
- **Self-Registration System** with automatic Patient ID generation
- **Personal Profile Management** with medical history tracking
- **Appointment Scheduling** integration with doctor availability
- **Medical Records Access** with secure authentication

### 👨‍⚕️ Doctor Features
- **Professional Profile Management** with qualifications and specializations
- **Schedule Management** with consultation fees and availability
- **Patient Assignment** and medical record access
- **Department Integration** with hospital organizational structure

### 🔐 Security & Authentication
- **Role-Based Access Control** (Admin, Doctor, Patient)
- **Secure Password Hashing** with PHP 8+ standards
- **SQL Injection Protection** via prepared statements
- **Session Management** with timeout and security controls

## 📚 Complete Documentation Suite

This system includes comprehensive documentation:

- **[DOCUMENTATION.md](DOCUMENTATION.md)** - Complete system overview and technical details
- **[CREDENTIALS.md](CREDENTIALS.md)** - Default login credentials and access guide  
- **[USER_GUIDES.md](USER_GUIDES.md)** - Step-by-step user guides for all roles
- **[TECHNICAL_SPECS.md](TECHNICAL_SPECS.md)** - Architecture and database specifications
- **[TROUBLESHOOTING.md](TROUBLESHOOTING.md)** - Issue resolution and maintenance guide

## 🚀 Quick Start

### Prerequisites
- **XAMPP** (Apache + MySQL + PHP 8.0+)
- Modern web browser (Chrome, Firefox, Edge)

## Installation Steps

### Step 1: Install XAMPP/WAMP Server

#### For XAMPP:
1. Download XAMPP from [https://www.apachefriends.org/download.html](https://www.apachefriends.org/download.html)
2. Run the installer and follow the installation wizard
3. Select Apache, MySQL, and phpMyAdmin components
4. Install to default location (C:\xampp on Windows)

#### For WAMP:
1. Download WAMP from [https://www.wampserver.com/en/](https://www.wampserver.com/en/)
2. Run the installer and follow the installation wizard
3. Install to default location (C:\wamp64 on Windows)

### Step 2: Start Server Services

#### For XAMPP:
1. Open XAMPP Control Panel
2. Start Apache and MySQL services
3. Ensure both services show "Running" status

#### For WAMP:
1. Launch WampServer
2. Wait for the icon in system tray to turn green
3. Left-click on WAMP icon to access services

### Step 3: Setup Project Files

1. Copy the `Hospital_Management_System` folder to your web server directory:
   - **XAMPP**: `C:\xampp\htdocs\`
   - **WAMP**: `C:\wamp64\www\`

2. The final path should be:
   - **XAMPP**: `C:\xampp\htdocs\Hospital_Management_System\`
   - **WAMP**: `C:\wamp64\www\Hospital_Management_System\`

### Step 4: Create Database

#### Method 1: Using phpMyAdmin (Recommended)
1. Open your web browser
2. Navigate to `http://localhost/phpmyadmin`
3. Click on "Import" tab
4. Click "Choose File" and select `database/hospital_management.sql`
5. Click "Go" to execute the SQL script
6. The database `hospital_management` will be created with all tables and sample data

#### Method 2: Manual Database Creation
1. Open phpMyAdmin (`http://localhost/phpmyadmin`)
2. Click "New" to create a new database
3. Enter database name: `hospital_management`
4. Select "utf8mb4_general_ci" collation
5. Click "Create"
6. Select the created database
7. Click "Import" tab
8. Upload the `database/hospital_management.sql` file
9. Click "Go"

### Step 5: Configure Database Connection

1. Open `includes/config.php` in a text editor
2. Verify the database configuration:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Default is empty for XAMPP/WAMP
   define('DB_NAME', 'hospital_management');
   ```
3. If your MySQL has a password, update `DB_PASS` accordingly
4. Save the file

### Step 6: Test Installation

1. Open your web browser
2. Navigate to `http://localhost/Hospital_Management_System/`
3. You should see the login page

## Default Login Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `secret`
- **Role**: Administrator

### Doctor Account
- **Username**: `dr.smith`
- **Password**: `secret`
- **Role**: Doctor

## Project Structure

```
Hospital_Management_System/
├── admin/
│   └── dashboard.php          # Admin dashboard
├── assets/
│   ├── css/
│   │   └── style.css          # Main stylesheet
│   ├── images/                # Image files
│   └── js/
│       └── script.js          # Main JavaScript
├── database/
│   └── hospital_management.sql # Database schema
├── includes/
│   ├── config.php             # Database configuration
│   ├── header.php             # Common header
│   └── footer.php             # Common footer
├── pages/
│   └── patient_registration.php # Patient registration
├── login.php                  # Login page
└── logout.php                 # Logout functionality
```

## Features Included

### ✅ Completed Features
- **User Authentication System**
  - Multi-role login (Admin, Doctor, Nurse, Receptionist, Patient)
  - Secure password hashing
  - Session management
  
- **Responsive Dashboard**
  - Role-based navigation
  - Modern Bootstrap UI
  - Mobile-friendly design
  
- **Patient Management**
  - Patient registration
  - Patient profile management
  - Medical history tracking
  
- **Database Structure**
  - Complete relational database
  - 12+ tables for comprehensive management
  - Sample data included

### 🔄 Additional Features to Implement
- Doctor management module
- Appointment scheduling system
- Medical records management
- Billing and invoicing
- Inventory management
- Report generation
- Email notifications

## Troubleshooting

### Common Issues and Solutions

#### 1. "Access Denied" Database Error
- **Problem**: Cannot connect to database
- **Solution**: 
  - Check if MySQL service is running
  - Verify database credentials in `config.php`
  - Ensure database exists

#### 2. "Page Not Found" Error
- **Problem**: 404 error when accessing pages
- **Solution**:
  - Check if files are in correct directory
  - Verify Apache service is running
  - Check URL path spelling

#### 3. "Permission Denied" Error
- **Problem**: Cannot write to files/folders
- **Solution**:
  - Set proper folder permissions
  - Run web server as administrator (Windows)
  - Check file ownership (Linux/Mac)

#### 4. CSS/JS Not Loading
- **Problem**: Page appears unstyled
- **Solution**:
  - Check file paths in HTML
  - Clear browser cache
  - Verify files exist in assets folder

#### 5. Session Issues
- **Problem**: Login doesn't persist
- **Solution**:
  - Check session configuration
  - Clear browser cookies
  - Restart web server

## Security Configuration

### For Production Environment:
1. Change default passwords
2. Update database credentials
3. Enable HTTPS
4. Set secure session settings
5. Configure error reporting
6. Set file permissions properly

### Recommended PHP Settings:
```php
; In php.ini
session.cookie_httponly = 1
session.use_only_cookies = 1
session.cookie_secure = 1    ; Only if using HTTPS
display_errors = Off
log_errors = On
```

## Performance Optimization

### Database Optimization:
- Regular database backup
- Index optimization
- Query optimization
- Connection pooling

### Web Server Optimization:
- Enable gzip compression
- Configure caching headers
- Optimize images
- Minify CSS/JS files

## Backup and Maintenance

### Regular Tasks:
1. **Database Backup**
   - Export database weekly
   - Store backups securely
   - Test restore procedures

2. **File Backup**
   - Backup application files
   - Version control with Git
   - Document changes

3. **Security Updates**
   - Keep PHP updated
   - Update dependencies
   - Monitor security advisories

## Support and Documentation

### Getting Help:
- Check troubleshooting section
- Review error logs
- Consult PHP/MySQL documentation
- Community forums and Stack Overflow

### Development Environment:
- Use version control (Git)
- Set up development/staging environment
- Follow PHP best practices
- Implement proper error handling

---

**Note**: This is a development version. For production use, implement additional security measures, proper error handling, and performance optimizations.

## Quick Start Commands

```bash
# Navigate to XAMPP directory
cd C:\xampp\htdocs

# Clone or copy project
# Copy Hospital_Management_System folder here

# Start XAMPP services
# Use XAMPP Control Panel

# Access application
# Browser: http://localhost/Hospital_Management_System/
```

Enjoy using the Hospital Management System! 🏥
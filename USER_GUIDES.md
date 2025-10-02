# 📖 Hospital Management System - User Guides

## 👨‍💼 Administrator Guide

### Getting Started as Admin

#### 1. Initial Login
```
Step 1: Open http://localhost/Hospital_Management_System/
Step 2: Click "Login" button
Step 3: Enter credentials:
        Username: admin
        Password: admin123
Step 4: Click "Login" button
Step 5: You'll be redirected to Admin Dashboard
```

#### 2. Understanding the Admin Dashboard

**Dashboard Overview:**
- **Statistics Cards**: Live metrics (patients, doctors, appointments, revenue)
- **Recent Activities**: Latest appointments and patient registrations
- **Quick Actions**: Shortcuts to common tasks
- **Navigation Menu**: Access to all management sections

**Key Metrics Displayed:**
- Total Patients: Current patient count
- Total Doctors: Active doctor count
- Today's Appointments: Scheduled for current date
- Monthly Revenue: Financial summary for current month

---

### Patient Management (Admin)

#### Adding New Patients

```
Step 1: Navigate to "Patients" in the menu
Step 2: Click "Add New Patient" button
Step 3: Fill out Patient Information Form:
        
        Personal Information:
        ├── First Name: (Required)
        ├── Last Name: (Required)
        ├── Date of Birth: (Required)
        ├── Gender: (Required - dropdown)
        ├── Phone: (Required)
        └── Email: (Optional)
        
        Medical Information:
        ├── Blood Type: (Optional - dropdown)
        ├── Allergies: (Optional - text area)
        └── Medical Notes: (Optional)
        
        Emergency Contact:
        ├── Contact Name: (Required)
        └── Contact Phone: (Required)
        
        Account Information:
        ├── Username: (Required - for login)
        └── Password: (Required - minimum 6 characters)

Step 4: Click "Save Patient" button
Step 5: System generates unique Patient ID automatically
Step 6: Note the Patient ID for the patient's records
```

#### Managing Existing Patients

**Search and Filter:**
```
Step 1: Go to Patients section
Step 2: Use search tools:
        ├── Text Search: Name, Patient ID, email, phone
        ├── Status Filter: Active, Inactive, All
        └── Date Range: Registration date filtering

Step 3: Results update automatically as you type
```

**View Patient Details:**
```
Step 1: Find patient in the list
Step 2: Click on patient name or "View" button
Step 3: Modal window opens showing:
        ├── Complete patient information
        ├── Medical history summary
        ├── Emergency contact details
        ├── Account status and registration date
        └── Recent appointment history
```

**Edit Patient Information:**
```
Step 1: From patient details modal, click "Edit" button
Step 2: Edit form opens with current information
Step 3: Modify any field except Patient ID (protected)
Step 4: Update medical information as needed
Step 5: Change account status if required
Step 6: Click "Update Patient" to save changes
```

**Export Patient Data:**
```
Step 1: Apply any filters you want for the export
Step 2: Click "Export" button
Step 3: Choose format:
        ├── CSV: Spreadsheet format for data analysis
        └── PDF: Formatted report for printing
Step 4: File downloads automatically
Step 5: Export includes all filtered results
```

---

### Doctor Management (Admin)

#### Adding New Doctors

```
Step 1: Navigate to "Doctors" in the menu
Step 2: Click "Add New Doctor" button  
Step 3: Fill out Doctor Information Form:

        Personal Information:
        ├── First Name: (Required)
        ├── Last Name: (Required)
        ├── Phone: (Required)
        └── Email: (Required)
        
        Professional Information:
        ├── Employee ID: (Auto-generated)
        ├── Specialization: (Required)
        ├── Qualification: (Required - degrees, certifications)
        ├── Experience Years: (Required - numeric)
        ├── Department: (Required - dropdown)
        └── Consultation Fee: (Required - USD amount)
        
        Schedule Information:
        ├── Schedule Start: (Required - time picker)
        ├── Schedule End: (Required - time picker)
        └── Available Days: (Required - checkbox selection)
        
        Account Information:
        ├── Username: (Required - for login)
        └── Password: (Required - minimum 6 characters)

Step 4: Click "Save Doctor" button
Step 5: System assigns unique Employee ID
Step 6: Doctor can now login and manage schedule
```

#### Managing Doctor Profiles

**Search and Filter Doctors:**
```
Step 1: Access Doctors section
Step 2: Use advanced filtering:
        ├── Text Search: Name, Employee ID, specialization
        ├── Department Filter: Specific department
        ├── Specialization Filter: Medical specialty
        └── Status Filter: Active, Inactive, All
        
Step 3: Results display with key information:
        ├── Employee ID and full name
        ├── Specialization and department
        ├── Experience and consultation fee
        ├── Schedule and availability
        └── Account status
```

**View Doctor Details:**
```
Step 1: Click doctor name or "View" button
Step 2: Comprehensive profile shows:
        ├── Professional photo placeholder
        ├── Complete contact information
        ├── Qualifications and experience
        ├── Department and specialization
        ├── Current schedule and fees
        ├── Account status and registration
        └── Recent appointment statistics
```

**Edit Doctor Information:**
```
Step 1: From doctor details, click "Edit" button
Step 2: Comprehensive edit form opens
Step 3: Update any professional information:
        ├── Contact details and qualifications
        ├── Specialization and department
        ├── Experience years and consultation fees
        ├── Working schedule and available days
        └── Account status (Active/Inactive)
Step 4: Click "Update Doctor" to save changes
```

**Doctor Status Management:**
```
Active Status:
├── Doctor can login and access system
├── Appears in appointment scheduling
├── Included in department listings
└── Can receive new patient assignments

Inactive Status:
├── Doctor login is disabled
├── Hidden from appointment scheduling
├── Existing appointments remain visible
└── Profile preserved for historical records
```

---

## 👨‍⚕️ Doctor Guide

### Getting Started as Doctor

#### 1. First Login
```
Step 1: Visit the login page
Step 2: Enter your doctor credentials:
        Username: (provided by admin)
        Password: (provided by admin)
Step 3: Access doctor dashboard
Step 4: Update your profile information
Step 5: Set your availability schedule
```

#### 2. Profile Management

**Update Professional Information:**
```
Step 1: Navigate to "My Profile"
Step 2: Update sections as needed:
        ├── Contact Information: Phone, email, address
        ├── Professional Details: Specialization, qualifications
        ├── Experience: Years of practice, certifications
        └── Bio: Professional summary (optional)
Step 3: Save changes
```

**Schedule Management:**
```
Step 1: Access "Schedule" section
Step 2: Set working hours:
        ├── Start Time: When you begin consultations
        ├── End Time: When you finish consultations
        ├── Available Days: Select working days
        └── Break Times: Lunch or break periods
Step 3: Set consultation fees
Step 4: Update availability status
```

#### 3. Patient Management

**View Assigned Patients:**
```
Step 1: Go to "My Patients" section
Step 2: View patients assigned to you:
        ├── Current patients under your care
        ├── Patient medical history
        ├── Upcoming appointments
        └── Previous consultation notes
```

**Appointment Management:**
```
Step 1: Access "Appointments" section
Step 2: View your schedule:
        ├── Today's appointments
        ├── Upcoming appointments
        ├── Appointment history
        └── Patient contact information
Step 3: Update appointment status as needed
```

---

## 👤 Patient Guide

### Getting Started as Patient

#### 1. Self Registration
```
Step 1: Visit hospital website
Step 2: Click "Register as Patient" 
Step 3: Fill registration form:
        
        Personal Details:
        ├── Full Name: First and last name
        ├── Date of Birth: Select from calendar
        ├── Gender: Choose from dropdown
        ├── Contact Info: Phone (required), email (optional)
        └── Address: Current residential address
        
        Medical Information:
        ├── Blood Type: Select if known
        ├── Known Allergies: List any allergies
        └── Current Medications: List ongoing medications
        
        Emergency Contact:
        ├── Contact Name: Full name of emergency contact
        ├── Relationship: Spouse, parent, sibling, etc.
        └── Phone Number: Reachable phone number
        
        Account Setup:
        ├── Username: Choose login username
        └── Password: Create secure password

Step 4: Submit registration
Step 5: Receive Patient ID (save this number)
Step 6: Login with username and password (NOT Patient ID)
```

#### 2. Using Your Patient Account

**Login Process:**
```
Step 1: Go to login page
Step 2: Enter Login Credentials:
        ├── Username: The username you chose (NOT Patient ID)
        └── Password: Your chosen password
Step 3: Click "Login"
Step 4: Access patient dashboard
```

**Important Note:** Use your chosen USERNAME to login, not your Patient ID. The Patient ID is for medical records only.

**Dashboard Features:**
```
Your patient dashboard shows:
├── Personal Information Summary
├── Upcoming Appointments
├── Medical History Access
├── Test Results (when available)
├── Prescription History
└── Emergency Contact Information
```

#### 3. Managing Your Information

**Update Personal Information:**
```
Step 1: Navigate to "My Profile"
Step 2: Update editable fields:
        ├── Contact information (phone, email, address)
        ├── Emergency contact details
        ├── Medical information (allergies, medications)
        └── Password change option
Step 3: Save changes
Note: Some fields like Patient ID and DOB cannot be changed
```

**View Medical History:**
```
Step 1: Access "Medical History" section
Step 2: Review your records:
        ├── Previous appointments and consultations
        ├── Diagnoses and treatment plans
        ├── Prescribed medications
        ├── Test results and reports
        └── Doctor's notes and recommendations
```

---

## 🔄 Common Workflows

### Appointment Scheduling Process

#### From Admin Perspective:
```
Step 1: Admin receives appointment request
Step 2: Check doctor availability in system
Step 3: Verify patient information
Step 4: Schedule appointment in system
Step 5: Confirm with both doctor and patient
Step 6: Update appointment status as needed
```

#### From Patient Perspective:
```
Step 1: Contact hospital to request appointment
Step 2: Provide Patient ID and preferred doctor/time
Step 3: Receive confirmation with appointment details
Step 4: Attend appointment at scheduled time
Step 5: Check system for follow-up information
```

### User Account Management

#### Password Reset Process:
```
For Patients/Doctors (via Admin):
Step 1: Contact admin for password reset
Step 2: Admin locates user account
Step 3: Admin sets temporary password
Step 4: User logs in with temporary password
Step 5: User changes password on first login

For Admin Account:
Step 1: Access database via phpMyAdmin
Step 2: Reset admin password using SQL
Step 3: Login with reset password
Step 4: Change to secure password immediately
```

#### Account Status Changes:
```
Activating Account:
├── Admin changes status to "Active"
├── User can immediately login
├── All system features become available
└── User appears in relevant listings

Deactivating Account:
├── Admin changes status to "Inactive" 
├── User login is immediately disabled
├── Existing data is preserved
└── Can be reactivated at any time
```

---

## 📊 Reports and Data Export

### Patient Reports

**Individual Patient Report:**
```
Step 1: Access patient details
Step 2: Click "Generate Report" 
Step 3: Report includes:
        ├── Complete patient profile
        ├── Medical history summary
        ├── Appointment history
        ├── Current medications
        └── Emergency contacts
Step 4: Print or save as PDF
```

**Patient List Export:**
```
Step 1: Apply desired filters in Patients section
Step 2: Click "Export" button
Step 3: Choose format (CSV for data, PDF for reports)
Step 4: Export includes filtered results with:
        ├── Patient demographics
        ├── Contact information
        ├── Medical summary
        ├── Registration dates
        └── Account status
```

### Doctor Reports

**Doctor Directory Export:**
```
Step 1: Access Doctors section
Step 2: Apply filters (department, specialization, etc.)
Step 3: Click "Export" button
Step 4: Generated report includes:
        ├── Professional credentials
        ├── Contact information
        ├── Schedule and availability
        ├── Department assignments
        └── Experience and qualifications
```

---

## 🆘 Troubleshooting for Users

### Login Issues

**"Invalid username or password":**
```
Solution Steps:
1. Verify you're using USERNAME, not Patient/Employee ID
2. Check if Caps Lock is on
3. Try typing password manually (don't copy/paste)
4. Contact admin if account might be inactive
5. Request password reset if needed
```

**"Access denied" after login:**
```
Solution Steps:
1. Check if you're accessing correct URL
2. Verify your role permissions with admin
3. Clear browser cache and cookies
4. Try different browser
5. Contact admin to verify account status
```

### System Navigation Issues

**Pages not loading properly:**
```
Solution Steps:
1. Check internet connection
2. Refresh the page (F5 or Ctrl+R)
3. Clear browser cache
4. Try different browser
5. Check if XAMPP services are running (for local setup)
```

**Export functions not working:**
```
Solution Steps:
1. Check browser's popup blocker settings
2. Ensure downloads are allowed
3. Try right-click "Save As" on export links
4. Check browser's download folder
5. Contact admin if problem persists
```

---

## 📋 Best Practices

### For Administrators

**User Management:**
- Regularly review user accounts and deactivate unused ones
- Use strong passwords for all accounts
- Keep user information up to date
- Monitor system usage and performance

**Data Management:**
- Regular database backups
- Clean up old temporary files
- Monitor system storage usage
- Review and archive old records

### For Doctors

**Profile Maintenance:**
- Keep professional information current
- Update schedule changes promptly
- Maintain accurate contact information
- Review patient assignments regularly

**System Usage:**
- Log out properly after each session
- Update appointment statuses promptly
- Keep patient information confidential
- Report system issues immediately

### For Patients

**Account Security:**
- Use strong, unique passwords
- Keep login credentials secure
- Log out from shared computers
- Report suspicious account activity

**Information Accuracy:**
- Keep contact information current
- Update medical information as needed
- Verify appointment details
- Report changes in emergency contacts

---

*This guide covers all major user workflows in the Hospital Management System. For specific technical issues, refer to the main documentation or contact system administrators.*
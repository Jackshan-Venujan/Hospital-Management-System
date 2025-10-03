-- Database Update Script
-- Add new staff roles to the users table ENUM
-- Run this script to update your existing database

USE hospital_management;

-- Update the users table to support additional staff roles
ALTER TABLE users MODIFY COLUMN role ENUM(
    'admin', 
    'doctor', 
    'nurse', 
    'receptionist', 
    'patient',
    'technician',
    'pharmacist', 
    'administrator',
    'janitor',
    'security',
    'accountant'
) NOT NULL;

-- Verify the changes
DESCRIBE users;

-- Optional: Insert sample staff members for testing
-- Uncomment the lines below if you want to add sample data

/*
-- Sample Technician
INSERT INTO users (username, email, password, role) VALUES 
('tech.johnson', 'tech.johnson@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician');

INSERT INTO staff (user_id, employee_id, first_name, last_name, position, department_id, phone, email, hire_date, salary) VALUES 
(LAST_INSERT_ID(), 'TECH001', 'Mike', 'Johnson', 'Lab Technician', 1, '+1234567891', 'tech.johnson@hospital.com', '2024-01-15', 45000.00);

-- Sample Pharmacist  
INSERT INTO users (username, email, password, role) VALUES 
('pharm.davis', 'pharm.davis@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist');

INSERT INTO staff (user_id, employee_id, first_name, last_name, position, department_id, phone, email, hire_date, salary) VALUES 
(LAST_INSERT_ID(), 'PHARM001', 'Sarah', 'Davis', 'Pharmacist', 6, '+1234567892', 'pharm.davis@hospital.com', '2024-02-01', 65000.00);

-- Sample Administrator
INSERT INTO users (username, email, password, role) VALUES 
('admin.wilson', 'admin.wilson@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrator');

INSERT INTO staff (user_id, employee_id, first_name, last_name, position, department_id, phone, email, hire_date, salary) VALUES 
(LAST_INSERT_ID(), 'ADM001', 'James', 'Wilson', 'Healthcare Administrator', NULL, '+1234567893', 'admin.wilson@hospital.com', '2023-06-15', 55000.00);
*/

SELECT 'Database schema updated successfully. New staff roles added: technician, pharmacist, administrator, janitor, security, accountant' as message;
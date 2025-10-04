-- Enhanced Billing System Database
-- Hospital Management System
-- Billing Enhancements for Invoice Management

USE hospital_management;

-- Drop existing billing tables to recreate with enhanced structure
DROP TABLE IF EXISTS billing_items;
DROP TABLE IF EXISTS billing;

-- Enhanced Invoices table (renamed from billing)
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT,
    doctor_id INT,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 10.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    balance_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Enhanced Invoice Items table
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_type ENUM('consultation', 'procedure', 'medication', 'test', 'room', 'other') NOT NULL,
    description VARCHAR(200) NOT NULL,
    quantity DECIMAL(8,2) DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- Payments table for payment tracking
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(20) UNIQUE NOT NULL,
    invoice_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'insurance', 'other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(50),
    notes TEXT,
    received_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Service Catalog for predefined services and pricing
CREATE TABLE service_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_code VARCHAR(20) UNIQUE NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    service_type ENUM('consultation', 'procedure', 'medication', 'test', 'room', 'other') NOT NULL,
    department_id INT,
    default_price DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Insurance providers (for future enhancement)
CREATE TABLE insurance_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(100) NOT NULL,
    provider_code VARCHAR(20) UNIQUE NOT NULL,
    contact_number VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    coverage_percentage DECIMAL(5,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add indexes for better performance
CREATE INDEX idx_invoices_number ON invoices(invoice_number);
CREATE INDEX idx_invoices_patient ON invoices(patient_id);
CREATE INDEX idx_invoices_date ON invoices(invoice_date);
CREATE INDEX idx_invoices_status ON invoices(payment_status);
CREATE INDEX idx_invoice_items_invoice ON invoice_items(invoice_id);
CREATE INDEX idx_payments_invoice ON payments(invoice_id);
CREATE INDEX idx_payments_date ON payments(payment_date);
CREATE INDEX idx_service_catalog_code ON service_catalog(service_code);
CREATE INDEX idx_service_catalog_type ON service_catalog(service_type);

-- Insert default service catalog items
INSERT INTO service_catalog (service_code, service_name, service_type, default_price, description) VALUES
('CONS001', 'General Consultation', 'consultation', 50.00, 'General medical consultation'),
('CONS002', 'Specialist Consultation', 'consultation', 100.00, 'Specialist doctor consultation'),
('CONS003', 'Follow-up Consultation', 'consultation', 30.00, 'Follow-up visit consultation'),

('PROC001', 'Blood Pressure Check', 'procedure', 15.00, 'Blood pressure measurement'),
('PROC002', 'ECG', 'procedure', 75.00, 'Electrocardiogram'),
('PROC003', 'X-Ray Chest', 'procedure', 120.00, 'Chest X-ray examination'),
('PROC004', 'Ultrasound', 'procedure', 150.00, 'Ultrasound scan'),
('PROC005', 'CT Scan', 'procedure', 500.00, 'Computed tomography scan'),
('PROC006', 'MRI Scan', 'procedure', 800.00, 'Magnetic resonance imaging'),

('TEST001', 'Complete Blood Count', 'test', 25.00, 'CBC blood test'),
('TEST002', 'Blood Sugar Test', 'test', 20.00, 'Blood glucose test'),
('TEST003', 'Lipid Profile', 'test', 35.00, 'Cholesterol and lipid test'),
('TEST004', 'Liver Function Test', 'test', 40.00, 'LFT blood test'),
('TEST005', 'Kidney Function Test', 'test', 45.00, 'KFT blood test'),
('TEST006', 'Thyroid Function Test', 'test', 55.00, 'Thyroid hormone test'),

('MED001', 'Paracetamol 500mg', 'medication', 5.00, 'Pain relief medication'),
('MED002', 'Amoxicillin 250mg', 'medication', 12.00, 'Antibiotic medication'),
('MED003', 'Omeprazole 20mg', 'medication', 8.00, 'Acid reflux medication'),

('ROOM001', 'General Ward Per Day', 'room', 100.00, 'General ward accommodation per day'),
('ROOM002', 'Private Room Per Day', 'room', 200.00, 'Private room accommodation per day'),
('ROOM003', 'ICU Per Day', 'room', 500.00, 'ICU accommodation per day'),

('OTH001', 'Ambulance Service', 'other', 80.00, 'Emergency ambulance service'),
('OTH002', 'Medical Certificate', 'other', 25.00, 'Medical fitness certificate'),
('OTH003', 'Report Copy', 'other', 10.00, 'Medical report copy');

-- Insert sample insurance providers
INSERT INTO insurance_providers (provider_name, provider_code, contact_number, email, coverage_percentage) VALUES
('National Health Insurance', 'NHI001', '+1-800-123-4567', 'info@nhi.com', 80.00),
('Medicare Plus', 'MCP001', '+1-800-234-5678', 'support@medicareplus.com', 75.00),
('United Health Care', 'UHC001', '+1-800-345-6789', 'claims@unitedhc.com', 70.00),
('Blue Cross Blue Shield', 'BCBS001', '+1-800-456-7890', 'service@bcbs.com', 85.00);

-- Update departments table to add head_doctor foreign key constraint if not exists
ALTER TABLE departments 
ADD CONSTRAINT fk_departments_head_doctor 
FOREIGN KEY (head_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL;
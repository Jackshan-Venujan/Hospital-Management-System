-- Sample Billing Data (Corrected)
-- Hospital Management System
-- Comprehensive Invoice and Payment Sample Data

USE hospital_management;

-- First, let's add a few more sample patients if needed
INSERT IGNORE INTO patients (patient_id, first_name, last_name, phone, email, date_of_birth, gender, blood_group) VALUES
('PAT004', 'David', 'Miller', '+1234567894', 'david.miller@email.com', '1985-03-15', 'Male', 'A+'),
('PAT005', 'Emma', 'Davis', '+1234567895', 'emma.davis@email.com', '1992-07-22', 'Female', 'B+');

-- Insert sample invoices with correct patient and doctor IDs
INSERT INTO invoices (invoice_number, patient_id, appointment_id, doctor_id, invoice_date, due_date, subtotal, tax_rate, tax_amount, discount_amount, total_amount, paid_amount, balance_amount, payment_status, notes, created_by) VALUES

-- Recent invoices (current month) - using existing patient IDs (1-5) and doctor ID (1)
('INV-2025-10001', 1, 1, 1, '2025-10-01', '2025-10-31', 180.00, 10.00, 18.00, 0.00, 198.00, 198.00, 0.00, 'paid', 'Regular consultation and blood work', 1),
('INV-2025-10002', 2, 6, 1, '2025-10-02', '2025-11-01', 520.00, 10.00, 52.00, 20.00, 552.00, 300.00, 252.00, 'partial', 'Emergency treatment with follow-up required', 1),
('INV-2025-10003', 3, NULL, 1, '2025-10-03', '2025-11-02', 75.00, 10.00, 7.50, 0.00, 82.50, 0.00, 82.50, 'pending', 'Specialist consultation', 1),
('INV-2025-10004', 1, NULL, NULL, '2025-10-04', '2025-11-03', 245.00, 10.00, 24.50, 25.00, 244.50, 244.50, 0.00, 'paid', 'Routine medical certificate and health checkup', 1),
('INV-2025-10005', 2, 7, 1, '2025-10-05', '2025-11-04', 850.00, 10.00, 85.00, 50.00, 885.00, 400.00, 485.00, 'partial', 'CT scan and specialist consultation', 1),

-- Previous month invoices
('INV-2025-09001', 1, NULL, 1, '2025-09-15', '2025-10-15', 120.00, 10.00, 12.00, 0.00, 132.00, 132.00, 0.00, 'paid', 'Follow-up consultation', 1),
('INV-2025-09002', 3, NULL, 1, '2025-09-20', '2025-10-20', 300.00, 10.00, 30.00, 0.00, 330.00, 0.00, 330.00, 'overdue', 'Multiple tests and procedures', 1),
('INV-2025-09003', 2, NULL, NULL, '2025-09-25', '2025-10-25', 95.00, 10.00, 9.50, 5.00, 99.50, 99.50, 0.00, 'paid', 'Prescription refill and consultation', 1),

-- Older invoices for testing
('INV-2025-08001', 1, NULL, 1, '2025-08-10', '2025-09-09', 450.00, 10.00, 45.00, 0.00, 495.00, 495.00, 0.00, 'paid', 'Annual health checkup with comprehensive tests', 1),
('INV-2025-08002', 3, NULL, 1, '2025-08-20', '2025-09-19', 680.00, 10.00, 68.00, 30.00, 718.00, 500.00, 218.00, 'partial', 'Surgery consultation and pre-op tests', 1),

-- High-value invoices
('INV-2025-10006', 4, NULL, 1, '2025-10-06', '2025-11-05', 1200.00, 10.00, 120.00, 100.00, 1220.00, 1220.00, 0.00, 'paid', 'MRI scan with contrast and specialist review', 1),
('INV-2025-10007', 5, NULL, 1, '2025-10-07', '2025-11-06', 2500.00, 10.00, 250.00, 0.00, 2750.00, 1000.00, 1750.00, 'partial', 'Minor surgical procedure with overnight stay', 1),

-- Emergency invoices
('INV-2025-10008', 3, NULL, NULL, '2025-10-08', '2025-11-07', 850.00, 10.00, 85.00, 0.00, 935.00, 0.00, 935.00, 'pending', 'Emergency room visit with multiple tests', 1),
('INV-2025-10009', 4, NULL, 1, '2025-10-09', '2025-11-08', 320.00, 10.00, 32.00, 20.00, 332.00, 332.00, 0.00, 'paid', 'Urgent care visit with X-ray', 1),

-- Insurance-related invoices
('INV-2025-10010', 5, NULL, 1, '2025-10-10', '2025-11-09', 1500.00, 10.00, 150.00, 300.00, 1350.00, 1080.00, 270.00, 'partial', 'Insurance covered procedure - patient portion pending', 1);

-- Insert corresponding invoice items
INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price) VALUES

-- Items for INV-2025-10001 (Invoice ID will be auto-generated, so we need to get the IDs)
-- For the first invoice
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'consultation', 'General Medical Consultation', 1.00, 50.00, 50.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'test', 'Complete Blood Count (CBC)', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'test', 'Blood Sugar Test', 1.00, 20.00, 20.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'test', 'Lipid Profile', 1.00, 35.00, 35.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'procedure', 'Blood Pressure Check', 1.00, 15.00, 15.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'other', 'Medical Report Copy', 1.00, 10.00, 10.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'medication', 'Paracetamol 500mg (10 tablets)', 1.00, 5.00, 5.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), 'test', 'Liver Function Test', 1.00, 40.00, 40.00),

-- Items for INV-2025-10002
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), 'procedure', 'Emergency Room Visit', 1.00, 200.00, 200.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), 'procedure', 'X-Ray Chest', 1.00, 120.00, 120.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), 'procedure', 'ECG', 1.00, 75.00, 75.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), 'test', 'Complete Blood Count', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), 'medication', 'Pain Relief Injection', 1.00, 35.00, 35.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), 'consultation', 'Emergency Consultation', 1.00, 100.00, 100.00),

-- Items for INV-2025-10003
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10003'), 'consultation', 'Specialist Consultation - Cardiology', 1.00, 100.00, 100.00),

-- Items for INV-2025-10004
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), 'consultation', 'General Consultation', 1.00, 50.00, 50.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), 'procedure', 'Blood Pressure Check', 1.00, 15.00, 15.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), 'test', 'Blood Sugar Test', 1.00, 20.00, 20.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), 'other', 'Medical Fitness Certificate', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), 'procedure', 'BMI Assessment', 1.00, 10.00, 10.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), 'test', 'Urine Analysis', 1.00, 30.00, 30.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), 'other', 'Health Summary Report', 1.00, 15.00, 15.00),

-- Items for INV-2025-10005
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10005'), 'procedure', 'CT Scan - Abdomen', 1.00, 500.00, 500.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10005'), 'consultation', 'Specialist Consultation - Gastroenterology', 1.00, 150.00, 150.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10005'), 'test', 'Liver Function Test', 1.00, 40.00, 40.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10005'), 'test', 'Kidney Function Test', 1.00, 45.00, 45.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10005'), 'procedure', 'Ultrasound - Abdomen', 1.00, 150.00, 150.00),

-- Items for INV-2025-09001
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09001'), 'consultation', 'Follow-up Consultation', 1.00, 30.00, 30.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09001'), 'procedure', 'Blood Pressure Check', 1.00, 15.00, 15.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09001'), 'test', 'Blood Sugar Test', 1.00, 20.00, 20.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09001'), 'medication', 'Prescription Refill', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09001'), 'other', 'Consultation Summary', 1.00, 5.00, 5.00),

-- Items for INV-2025-09002
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09002'), 'test', 'Complete Blood Count', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09002'), 'test', 'Thyroid Function Test', 1.00, 55.00, 55.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09002'), 'procedure', 'ECG', 1.00, 75.00, 75.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09002'), 'procedure', 'Ultrasound - Thyroid', 1.00, 150.00, 150.00),

-- Items for INV-2025-09003
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09003'), 'consultation', 'General Consultation', 1.00, 50.00, 50.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09003'), 'medication', 'Chronic Disease Medication Refill', 1.00, 45.00, 45.00),

-- Items for INV-2025-08001
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'consultation', 'Comprehensive Health Consultation', 1.00, 100.00, 100.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'test', 'Complete Blood Count', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'test', 'Lipid Profile', 1.00, 35.00, 35.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'test', 'Liver Function Test', 1.00, 40.00, 40.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'test', 'Kidney Function Test', 1.00, 45.00, 45.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'procedure', 'ECG', 1.00, 75.00, 75.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'procedure', 'X-Ray Chest', 1.00, 120.00, 120.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), 'other', 'Comprehensive Health Report', 1.00, 35.00, 35.00),

-- Items for INV-2025-08002
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08002'), 'consultation', 'Surgical Consultation', 1.00, 200.00, 200.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08002'), 'test', 'Pre-operative Blood Work', 1.00, 80.00, 80.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08002'), 'procedure', 'CT Scan - Pre-op', 1.00, 500.00, 500.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08002'), 'test', 'Anesthesia Consultation', 1.00, 100.00, 100.00),

-- Items for INV-2025-10006
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10006'), 'procedure', 'MRI Scan with Contrast', 1.00, 800.00, 800.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10006'), 'consultation', 'Radiologist Review', 1.00, 150.00, 150.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10006'), 'consultation', 'Specialist Consultation - Neurology', 1.00, 150.00, 150.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10006'), 'other', 'MRI Report and Images', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10006'), 'medication', 'Contrast Agent', 1.00, 75.00, 75.00),

-- Items for INV-2025-10007
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10007'), 'procedure', 'Minor Surgical Procedure', 1.00, 1500.00, 1500.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10007'), 'room', 'Private Room - 1 Night', 1.00, 200.00, 200.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10007'), 'medication', 'Anesthesia', 1.00, 300.00, 300.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10007'), 'medication', 'Post-operative Medications', 1.00, 150.00, 150.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10007'), 'consultation', 'Post-operative Consultation', 1.00, 100.00, 100.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10007'), 'other', 'Surgical Supplies', 1.00, 250.00, 250.00),

-- Items for INV-2025-10008
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10008'), 'procedure', 'Emergency Room Assessment', 1.00, 250.00, 250.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10008'), 'test', 'Emergency Blood Panel', 1.00, 100.00, 100.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10008'), 'procedure', 'X-Ray - Multiple Views', 1.00, 180.00, 180.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10008'), 'procedure', 'ECG - Emergency', 1.00, 75.00, 75.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10008'), 'medication', 'Emergency Medications', 1.00, 120.00, 120.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10008'), 'other', 'Emergency Room Supplies', 1.00, 80.00, 80.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10008'), 'other', 'Ambulance Service', 1.00, 85.00, 85.00),

-- Items for INV-2025-10009
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10009'), 'consultation', 'Urgent Care Consultation', 1.00, 75.00, 75.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10009'), 'procedure', 'X-Ray - Single View', 1.00, 120.00, 120.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10009'), 'test', 'Quick Blood Test', 1.00, 30.00, 30.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10009'), 'medication', 'Pain Relief Medication', 1.00, 25.00, 25.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10009'), 'other', 'Urgent Care Supplies', 1.00, 15.00, 15.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10009'), 'procedure', 'Wound Dressing', 1.00, 35.00, 35.00),

-- Items for INV-2025-10010
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10010'), 'procedure', 'Insurance Covered Procedure', 1.00, 1200.00, 1200.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10010'), 'consultation', 'Pre-procedure Consultation', 1.00, 100.00, 100.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10010'), 'test', 'Pre-procedure Tests', 1.00, 150.00, 150.00),
((SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10010'), 'medication', 'Procedure Medications', 1.00, 80.00, 80.00);

-- Insert sample payments
INSERT INTO payments (payment_number, invoice_id, payment_date, payment_method, amount, reference_number, notes, received_by) VALUES

-- Payments for paid invoices
('PAY-2025-10001', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10001'), '2025-10-01', 'card', 198.00, 'CC-789123456', 'Paid in full at time of service', 1),
('PAY-2025-10002', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10004'), '2025-10-04', 'cash', 244.50, NULL, 'Cash payment - exact amount', 1),
('PAY-2025-10003', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09001'), '2025-09-16', 'bank_transfer', 132.00, 'TXN-987654321', 'Online payment', 1),
('PAY-2025-10004', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-09003'), '2025-09-25', 'insurance', 99.50, 'INS-456789123', 'Insurance direct payment', 1),
('PAY-2025-10005', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08001'), '2025-08-15', 'card', 495.00, 'CC-123789456', 'Annual checkup payment', 1),
('PAY-2025-10006', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10006'), '2025-10-06', 'bank_transfer', 1220.00, 'TXN-456123789', 'MRI scan payment - full amount', 1),
('PAY-2025-10007', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10009'), '2025-10-09', 'cash', 332.00, NULL, 'Urgent care cash payment', 1),

-- Partial payments
('PAY-2025-10008', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), '2025-10-03', 'card', 300.00, 'CC-321654987', 'Initial payment - balance due', 1),
('PAY-2025-10009', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10005'), '2025-10-06', 'insurance', 400.00, 'INS-789123456', 'Insurance portion paid', 1),
('PAY-2025-10010', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-08002'), '2025-08-25', 'card', 500.00, 'CC-654321987', 'Partial payment on surgery consultation', 1),
('PAY-2025-10011', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10007'), '2025-10-08', 'bank_transfer', 1000.00, 'TXN-147258369', 'Initial payment for surgical procedure', 1),
('PAY-2025-10012', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10010'), '2025-10-12', 'insurance', 1080.00, 'INS-369258147', 'Insurance coverage payment - 80%', 1),

-- Additional partial payments
('PAY-2025-10013', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10002'), '2025-10-15', 'cash', 50.00, NULL, 'Additional payment toward balance', 1),
('PAY-2025-10014', (SELECT id FROM invoices WHERE invoice_number = 'INV-2025-10005'), '2025-10-20', 'card', 100.00, 'CC-258147369', 'Partial payment on remaining balance', 1);

-- Update invoice paid amounts and payment status based on payments
UPDATE invoices i SET 
    paid_amount = (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.invoice_id = i.id),
    balance_amount = total_amount - (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.invoice_id = i.id),
    payment_status = CASE 
        WHEN (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.invoice_id = i.id) >= total_amount THEN 'paid'
        WHEN (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.invoice_id = i.id) > 0 THEN 'partial'
        WHEN due_date < CURDATE() AND (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.invoice_id = i.id) = 0 THEN 'overdue'
        ELSE 'pending'
    END
WHERE EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id) OR id IN (SELECT id FROM invoices WHERE invoice_number LIKE 'INV-2025-%');
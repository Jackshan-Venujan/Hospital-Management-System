-- Sample Billing Data
-- Hospital Management System
-- Comprehensive Invoice and Payment Sample Data

USE hospital_management;

-- Insert sample invoices with various statuses and scenarios
INSERT INTO invoices (invoice_number, patient_id, appointment_id, doctor_id, invoice_date, due_date, subtotal, tax_rate, tax_amount, discount_amount, total_amount, paid_amount, balance_amount, payment_status, notes, created_by) VALUES

-- Recent invoices (current month)
('INV-2025-10001', 1, 1, 1, '2025-10-01', '2025-10-31', 180.00, 10.00, 18.00, 0.00, 198.00, 198.00, 0.00, 'paid', 'Regular consultation and blood work', 1),
('INV-2025-10002', 2, 2, 1, '2025-10-02', '2025-11-01', 520.00, 10.00, 52.00, 20.00, 552.00, 300.00, 252.00, 'partial', 'Emergency treatment with follow-up required', 1),
('INV-2025-10003', 3, NULL, 2, '2025-10-03', '2025-11-02', 75.00, 10.00, 7.50, 0.00, 82.50, 0.00, 82.50, 'pending', 'Specialist consultation', 1),
('INV-2025-10004', 1, NULL, NULL, '2025-10-04', '2025-11-03', 245.00, 10.00, 24.50, 25.00, 244.50, 244.50, 0.00, 'paid', 'Routine medical certificate and health checkup', 1),
('INV-2025-10005', 2, 3, 1, '2025-10-05', '2025-11-04', 850.00, 10.00, 85.00, 50.00, 885.00, 400.00, 485.00, 'partial', 'CT scan and specialist consultation', 1),

-- Previous month invoices
('INV-2025-09001', 1, NULL, 1, '2025-09-15', '2025-10-15', 120.00, 10.00, 12.00, 0.00, 132.00, 132.00, 0.00, 'paid', 'Follow-up consultation', 1),
('INV-2025-09002', 3, NULL, 2, '2025-09-20', '2025-10-20', 300.00, 10.00, 30.00, 0.00, 330.00, 0.00, 330.00, 'overdue', 'Multiple tests and procedures', 1),
('INV-2025-09003', 2, NULL, NULL, '2025-09-25', '2025-10-25', 95.00, 10.00, 9.50, 5.00, 99.50, 99.50, 0.00, 'paid', 'Prescription refill and consultation', 1),

-- Older invoices for testing
('INV-2025-08001', 1, NULL, 1, '2025-08-10', '2025-09-09', 450.00, 10.00, 45.00, 0.00, 495.00, 495.00, 0.00, 'paid', 'Annual health checkup with comprehensive tests', 1),
('INV-2025-08002', 3, NULL, 2, '2025-08-20', '2025-09-19', 680.00, 10.00, 68.00, 30.00, 718.00, 500.00, 218.00, 'partial', 'Surgery consultation and pre-op tests', 1),

-- High-value invoices
('INV-2025-10006', 1, NULL, 1, '2025-10-06', '2025-11-05', 1200.00, 10.00, 120.00, 100.00, 1220.00, 1220.00, 0.00, 'paid', 'MRI scan with contrast and specialist review', 1),
('INV-2025-10007', 2, NULL, 2, '2025-10-07', '2025-11-06', 2500.00, 10.00, 250.00, 0.00, 2750.00, 1000.00, 1750.00, 'partial', 'Minor surgical procedure with overnight stay', 1),

-- Emergency invoices
('INV-2025-10008', 3, NULL, NULL, '2025-10-08', '2025-11-07', 850.00, 10.00, 85.00, 0.00, 935.00, 0.00, 935.00, 'pending', 'Emergency room visit with multiple tests', 1),
('INV-2025-10009', 1, NULL, 1, '2025-10-09', '2025-11-08', 320.00, 10.00, 32.00, 20.00, 332.00, 332.00, 0.00, 'paid', 'Urgent care visit with X-ray', 1),

-- Insurance-related invoices
('INV-2025-10010', 2, NULL, 2, '2025-10-10', '2025-11-09', 1500.00, 10.00, 150.00, 300.00, 1350.00, 1080.00, 270.00, 'partial', 'Insurance covered procedure - patient portion pending', 1);

-- Insert corresponding invoice items
INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price) VALUES

-- Items for INV-2025-10001 (Invoice ID 1)
(1, 'consultation', 'General Medical Consultation', 1.00, 50.00, 50.00),
(1, 'test', 'Complete Blood Count (CBC)', 1.00, 25.00, 25.00),
(1, 'test', 'Blood Sugar Test', 1.00, 20.00, 20.00),
(1, 'test', 'Lipid Profile', 1.00, 35.00, 35.00),
(1, 'procedure', 'Blood Pressure Check', 1.00, 15.00, 15.00),
(1, 'other', 'Medical Report Copy', 1.00, 10.00, 10.00),
(1, 'medication', 'Paracetamol 500mg (10 tablets)', 1.00, 5.00, 5.00),
(1, 'test', 'Liver Function Test', 1.00, 40.00, 40.00),

-- Items for INV-2025-10002 (Invoice ID 2)
(2, 'procedure', 'Emergency Room Visit', 1.00, 200.00, 200.00),
(2, 'procedure', 'X-Ray Chest', 1.00, 120.00, 120.00),
(2, 'procedure', 'ECG', 1.00, 75.00, 75.00),
(2, 'test', 'Complete Blood Count', 1.00, 25.00, 25.00),
(2, 'medication', 'Pain Relief Injection', 1.00, 35.00, 35.00),
(2, 'consultation', 'Emergency Consultation', 1.00, 100.00, 100.00),

-- Items for INV-2025-10003 (Invoice ID 3)
(3, 'consultation', 'Specialist Consultation - Cardiology', 1.00, 100.00, 100.00),

-- Items for INV-2025-10004 (Invoice ID 4)
(4, 'consultation', 'General Consultation', 1.00, 50.00, 50.00),
(4, 'procedure', 'Blood Pressure Check', 1.00, 15.00, 15.00),
(4, 'test', 'Blood Sugar Test', 1.00, 20.00, 20.00),
(4, 'other', 'Medical Fitness Certificate', 1.00, 25.00, 25.00),
(4, 'procedure', 'BMI Assessment', 1.00, 10.00, 10.00),
(4, 'test', 'Urine Analysis', 1.00, 30.00, 30.00),
(4, 'other', 'Health Summary Report', 1.00, 15.00, 15.00),

-- Items for INV-2025-10005 (Invoice ID 5)
(5, 'procedure', 'CT Scan - Abdomen', 1.00, 500.00, 500.00),
(5, 'consultation', 'Specialist Consultation - Gastroenterology', 1.00, 150.00, 150.00),
(5, 'test', 'Liver Function Test', 1.00, 40.00, 40.00),
(5, 'test', 'Kidney Function Test', 1.00, 45.00, 45.00),
(5, 'procedure', 'Ultrasound - Abdomen', 1.00, 150.00, 150.00),

-- Items for INV-2025-09001 (Invoice ID 6)
(6, 'consultation', 'Follow-up Consultation', 1.00, 30.00, 30.00),
(6, 'procedure', 'Blood Pressure Check', 1.00, 15.00, 15.00),
(6, 'test', 'Blood Sugar Test', 1.00, 20.00, 20.00),
(6, 'medication', 'Prescription Refill', 1.00, 25.00, 25.00),
(6, 'other', 'Consultation Summary', 1.00, 5.00, 5.00),

-- Items for INV-2025-09002 (Invoice ID 7)
(7, 'test', 'Complete Blood Count', 1.00, 25.00, 25.00),
(7, 'test', 'Thyroid Function Test', 1.00, 55.00, 55.00),
(7, 'procedure', 'ECG', 1.00, 75.00, 75.00),
(7, 'procedure', 'Ultrasound - Thyroid', 1.00, 150.00, 150.00),

-- Items for INV-2025-09003 (Invoice ID 8)
(8, 'consultation', 'General Consultation', 1.00, 50.00, 50.00),
(8, 'medication', 'Chronic Disease Medication Refill', 1.00, 45.00, 45.00),

-- Items for INV-2025-08001 (Invoice ID 9)
(9, 'consultation', 'Comprehensive Health Consultation', 1.00, 100.00, 100.00),
(9, 'test', 'Complete Blood Count', 1.00, 25.00, 25.00),
(9, 'test', 'Lipid Profile', 1.00, 35.00, 35.00),
(9, 'test', 'Liver Function Test', 1.00, 40.00, 40.00),
(9, 'test', 'Kidney Function Test', 1.00, 45.00, 45.00),
(9, 'procedure', 'ECG', 1.00, 75.00, 75.00),
(9, 'procedure', 'X-Ray Chest', 1.00, 120.00, 120.00),
(9, 'other', 'Comprehensive Health Report', 1.00, 35.00, 35.00),

-- Items for INV-2025-08002 (Invoice ID 10)
(10, 'consultation', 'Surgical Consultation', 1.00, 200.00, 200.00),
(10, 'test', 'Pre-operative Blood Work', 1.00, 80.00, 80.00),
(10, 'procedure', 'CT Scan - Pre-op', 1.00, 500.00, 500.00),
(10, 'test', 'Anesthesia Consultation', 1.00, 100.00, 100.00),

-- Items for INV-2025-10006 (Invoice ID 11)
(11, 'procedure', 'MRI Scan with Contrast', 1.00, 800.00, 800.00),
(11, 'consultation', 'Radiologist Review', 1.00, 150.00, 150.00),
(11, 'consultation', 'Specialist Consultation - Neurology', 1.00, 150.00, 150.00),
(11, 'other', 'MRI Report and Images', 1.00, 25.00, 25.00),
(11, 'medication', 'Contrast Agent', 1.00, 75.00, 75.00),

-- Items for INV-2025-10007 (Invoice ID 12)
(12, 'procedure', 'Minor Surgical Procedure', 1.00, 1500.00, 1500.00),
(12, 'room', 'Private Room - 1 Night', 1.00, 200.00, 200.00),
(12, 'medication', 'Anesthesia', 1.00, 300.00, 300.00),
(12, 'medication', 'Post-operative Medications', 1.00, 150.00, 150.00),
(12, 'consultation', 'Post-operative Consultation', 1.00, 100.00, 100.00),
(12, 'other', 'Surgical Supplies', 1.00, 250.00, 250.00),

-- Items for INV-2025-10008 (Invoice ID 13)
(13, 'procedure', 'Emergency Room Assessment', 1.00, 250.00, 250.00),
(13, 'test', 'Emergency Blood Panel', 1.00, 100.00, 100.00),
(13, 'procedure', 'X-Ray - Multiple Views', 1.00, 180.00, 180.00),
(13, 'procedure', 'ECG - Emergency', 1.00, 75.00, 75.00),
(13, 'medication', 'Emergency Medications', 1.00, 120.00, 120.00),
(13, 'other', 'Emergency Room Supplies', 1.00, 80.00, 80.00),
(13, 'other', 'Ambulance Service', 1.00, 85.00, 85.00),

-- Items for INV-2025-10009 (Invoice ID 14)
(14, 'consultation', 'Urgent Care Consultation', 1.00, 75.00, 75.00),
(14, 'procedure', 'X-Ray - Single View', 1.00, 120.00, 120.00),
(14, 'test', 'Quick Blood Test', 1.00, 30.00, 30.00),
(14, 'medication', 'Pain Relief Medication', 1.00, 25.00, 25.00),
(14, 'other', 'Urgent Care Supplies', 1.00, 15.00, 15.00),
(14, 'procedure', 'Wound Dressing', 1.00, 35.00, 35.00),

-- Items for INV-2025-10010 (Invoice ID 15)
(15, 'procedure', 'Insurance Covered Procedure', 1.00, 1200.00, 1200.00),
(15, 'consultation', 'Pre-procedure Consultation', 1.00, 100.00, 100.00),
(15, 'test', 'Pre-procedure Tests', 1.00, 150.00, 150.00),
(15, 'medication', 'Procedure Medications', 1.00, 80.00, 80.00);

-- Insert sample payments
INSERT INTO payments (payment_number, invoice_id, payment_date, payment_method, amount, reference_number, notes, received_by) VALUES

-- Payments for paid invoices
('PAY-2025-10001', 1, '2025-10-01', 'card', 198.00, 'CC-789123456', 'Paid in full at time of service', 1),
('PAY-2025-10002', 4, '2025-10-04', 'cash', 244.50, NULL, 'Cash payment - exact amount', 1),
('PAY-2025-10003', 6, '2025-09-16', 'bank_transfer', 132.00, 'TXN-987654321', 'Online payment', 1),
('PAY-2025-10004', 8, '2025-09-25', 'insurance', 99.50, 'INS-456789123', 'Insurance direct payment', 1),
('PAY-2025-10005', 9, '2025-08-15', 'card', 495.00, 'CC-123789456', 'Annual checkup payment', 1),
('PAY-2025-10006', 11, '2025-10-06', 'bank_transfer', 1220.00, 'TXN-456123789', 'MRI scan payment - full amount', 1),
('PAY-2025-10007', 14, '2025-10-09', 'cash', 332.00, NULL, 'Urgent care cash payment', 1),

-- Partial payments
('PAY-2025-10008', 2, '2025-10-03', 'card', 300.00, 'CC-321654987', 'Initial payment - balance due', 1),
('PAY-2025-10009', 5, '2025-10-06', 'insurance', 400.00, 'INS-789123456', 'Insurance portion paid', 1),
('PAY-2025-10010', 10, '2025-08-25', 'card', 500.00, 'CC-654321987', 'Partial payment on surgery consultation', 1),
('PAY-2025-10011', 12, '2025-10-08', 'bank_transfer', 1000.00, 'TXN-147258369', 'Initial payment for surgical procedure', 1),
('PAY-2025-10012', 15, '2025-10-12', 'insurance', 1080.00, 'INS-369258147', 'Insurance coverage payment - 80%', 1),

-- Additional partial payments
('PAY-2025-10013', 2, '2025-10-15', 'cash', 50.00, NULL, 'Additional payment toward balance', 1),
('PAY-2025-10014', 5, '2025-10-20', 'card', 100.00, 'CC-258147369', 'Partial payment on remaining balance', 1);

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
WHERE EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id);
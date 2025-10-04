-- Sample Appointments Data
-- Insert some sample appointments to demonstrate the appointment management system

USE hospital_management;

-- First, let's add some more sample patients if needed
INSERT IGNORE INTO patients (patient_id, first_name, last_name, date_of_birth, gender, phone, email, address, blood_group) VALUES 
('PAT001', 'Alice', 'Johnson', '1985-06-15', 'Female', '+1234567891', 'alice.johnson@email.com', '123 Main St, City', 'A+'),
('PAT002', 'Bob', 'Williams', '1978-03-22', 'Male', '+1234567892', 'bob.williams@email.com', '456 Oak Ave, City', 'O-'),
('PAT003', 'Carol', 'Brown', '1992-09-08', 'Female', '+1234567893', 'carol.brown@email.com', '789 Pine Rd, City', 'B+'),
('PAT004', 'David', 'Davis', '1965-12-03', 'Male', '+1234567894', 'david.davis@email.com', '321 Elm St, City', 'AB+'),
('PAT005', 'Emma', 'Wilson', '1988-07-19', 'Female', '+1234567895', 'emma.wilson@email.com', '654 Maple Dr, City', 'A-');

-- Insert sample appointments (make sure we have doctor ID 1 from our existing data)
INSERT INTO appointments (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, reason, status, notes, created_at) VALUES 

-- Today's appointments
('APT20251004001', 1, 1, CURDATE(), '09:00:00', 'Regular checkup and blood pressure monitoring', 'confirmed', 'Patient has history of hypertension', NOW() - INTERVAL 2 DAY),
('APT20251004002', 2, 1, CURDATE(), '10:30:00', 'Follow-up consultation for chest pain', 'scheduled', 'Referred from emergency department', NOW() - INTERVAL 1 DAY),
('APT20251004003', 3, 1, CURDATE(), '14:00:00', 'Cardiac evaluation and ECG', 'confirmed', '', NOW() - INTERVAL 3 HOUR),

-- Tomorrow's appointments
('APT20251005001', 4, 1, CURDATE() + INTERVAL 1 DAY, '09:30:00', 'Annual cardiac screening', 'scheduled', 'Patient over 50, routine screening', NOW() - INTERVAL 1 DAY),
('APT20251005002', 5, 1, CURDATE() + INTERVAL 1 DAY, '11:00:00', 'Post-operative follow-up', 'confirmed', 'Had cardiac procedure 2 weeks ago', NOW() - INTERVAL 2 HOUR),

-- Past appointments (completed)
('APT20251002001', 1, 1, CURDATE() - INTERVAL 2 DAY, '10:00:00', 'Routine consultation', 'completed', 'Blood pressure stable, continue medication', NOW() - INTERVAL 3 DAY),
('APT20251002002', 2, 1, CURDATE() - INTERVAL 2 DAY, '15:30:00', 'Emergency consultation', 'completed', 'Chest pain resolved, discharged', NOW() - INTERVAL 3 DAY),

-- Cancelled appointments
('APT20251001001', 3, 1, CURDATE() - INTERVAL 3 DAY, '09:00:00', 'Regular checkup', 'cancelled', 'Patient requested to reschedule', NOW() - INTERVAL 4 DAY),

-- No-show appointments
('APT20251001002', 4, 1, CURDATE() - INTERVAL 3 DAY, '14:30:00', 'Follow-up consultation', 'no-show', 'Patient did not arrive for appointment', NOW() - INTERVAL 4 DAY),

-- Future appointments (next week)
('APT20251010001', 5, 1, CURDATE() + INTERVAL 6 DAY, '10:00:00', 'Quarterly cardiac monitoring', 'scheduled', 'Regular monitoring for heart condition', NOW() - INTERVAL 1 HOUR),
('APT20251011001', 1, 1, CURDATE() + INTERVAL 7 DAY, '11:30:00', 'Medication review and adjustment', 'scheduled', 'Review current heart medications', NOW());

-- Verify the appointments were created
SELECT 
    a.appointment_number,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    CONCAT('Dr. ', d.first_name, ' ', d.last_name) as doctor_name,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.reason
FROM appointments a
LEFT JOIN patients p ON a.patient_id = p.id
LEFT JOIN doctors d ON a.doctor_id = d.id
ORDER BY a.appointment_date DESC, a.appointment_time;

SELECT 'Sample appointments created successfully!' as message;
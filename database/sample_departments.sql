-- Additional Department Data
-- Add more comprehensive department structure for the hospital

USE hospital_management;

-- Update existing departments with better descriptions and set head doctors
UPDATE departments SET 
    description = 'Specializes in heart and cardiovascular system diseases, including heart attacks, heart failure, coronary artery disease, and arrhythmias. Provides cardiac catheterization, echocardiography, and stress testing services.',
    head_doctor_id = 1
WHERE name = 'Cardiology' AND id = 1;

UPDATE departments SET 
    description = 'Focuses on disorders of the nervous system including the brain, spinal cord, and nerves. Treats conditions like stroke, epilepsy, Alzheimer\'s disease, Parkinson\'s disease, and multiple sclerosis.'
WHERE name = 'Neurology' AND id = 2;

UPDATE departments SET 
    description = 'Provides comprehensive healthcare for infants, children, and adolescents from birth to 18 years. Specializes in childhood diseases, growth and development, immunizations, and preventive care.'
WHERE name = 'Pediatrics' AND id = 3;

UPDATE departments SET 
    description = 'Specializes in the musculoskeletal system including bones, joints, ligaments, tendons, and muscles. Provides both surgical and non-surgical treatment for fractures, sports injuries, and joint disorders.'
WHERE name = 'Orthopedics' AND id = 4;

UPDATE departments SET 
    description = '24/7 emergency medical care for acute injuries, sudden illnesses, and life-threatening conditions. Equipped with trauma bay, resuscitation equipment, and rapid diagnostic capabilities.'
WHERE name = 'Emergency' AND id = 5;

UPDATE departments SET 
    description = 'Provides primary healthcare and treatment for a wide range of medical conditions in adults. Focuses on prevention, diagnosis, and treatment of diseases affecting internal organs.'
WHERE name = 'General Medicine' AND id = 6;

-- Add more specialized departments
INSERT INTO departments (name, description, head_doctor_id, created_at) VALUES 

('Radiology', 'Medical imaging department providing X-rays, CT scans, MRI, ultrasound, and mammography services. Specialized in diagnostic imaging and image-guided procedures for accurate diagnosis and treatment planning.', NULL, NOW()),

('Laboratory Services', 'Clinical laboratory providing blood tests, urine analysis, microbiology, pathology, and other diagnostic testing services. Supports all hospital departments with accurate and timely test results.', NULL, NOW()),

('Surgery', 'General surgery department performing a wide range of surgical procedures including abdominal, trauma, and emergency surgeries. Equipped with modern operating theaters and advanced surgical equipment.', NULL, NOW()),

('Obstetrics & Gynecology', 'Women\'s health department providing prenatal care, delivery services, gynecological treatments, and reproductive health services. Features a modern maternity ward and neonatal care unit.', NULL, NOW()),

('Anesthesiology', 'Provides anesthesia services for surgical procedures, pain management, and critical care. Ensures patient safety and comfort during operations and manages post-operative pain relief.', NULL, NOW()),

('Psychiatry', 'Mental health department providing diagnosis and treatment of mental health conditions including depression, anxiety, bipolar disorder, and substance abuse. Offers both inpatient and outpatient services.', NULL, NOW()),

('Dermatology', 'Specializes in skin, hair, and nail disorders. Provides treatment for skin cancers, acne, eczema, psoriasis, and cosmetic dermatology services. Offers both medical and surgical dermatological care.', NULL, NOW()),

('Ophthalmology', 'Eye care department providing comprehensive eye examinations, cataract surgery, glaucoma treatment, retinal care, and vision correction services. Equipped with advanced diagnostic and surgical equipment.', NULL, NOW()),

('Pharmacy', 'Hospital pharmacy services providing medication dispensing, drug information, medication therapy management, and clinical pharmacy consultations. Ensures safe and effective medication use throughout the hospital.', NULL, NOW()),

('Physical Therapy', 'Rehabilitation services helping patients recover from injuries, surgeries, and chronic conditions. Provides exercise therapy, manual therapy, and mobility training to restore function and reduce pain.', NULL, NOW());

-- Verify the departments
SELECT 
    id,
    name,
    CASE 
        WHEN head_doctor_id IS NOT NULL THEN 'Assigned'
        ELSE 'Not Assigned'
    END as head_status,
    SUBSTRING(description, 1, 50) as description_preview,
    created_at
FROM departments 
ORDER BY name;

-- Show department statistics
SELECT 
    COUNT(*) as total_departments,
    COUNT(CASE WHEN head_doctor_id IS NOT NULL THEN 1 END) as departments_with_head,
    COUNT(CASE WHEN head_doctor_id IS NULL THEN 1 END) as departments_without_head
FROM departments;

SELECT 'Additional departments created successfully!' as message;
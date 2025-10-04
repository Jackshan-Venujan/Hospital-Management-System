<?php
// Database setup script for medical records system
require_once __DIR__ . '/../includes/config.php';

$db = new Database();

try {
    echo "Setting up medical records database tables...\n\n";

    // Create medical_records table
    echo "Creating medical_records table...\n";
    $db->query("CREATE TABLE IF NOT EXISTS medical_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        appointment_id INT,
        visit_date DATE NOT NULL,
        chief_complaint TEXT,
        symptoms TEXT,
        diagnosis TEXT,
        treatment_plan TEXT,
        follow_up_instructions TEXT,
        vital_signs JSON,
        weight DECIMAL(5,2),
        height DECIMAL(5,2),
        blood_pressure VARCHAR(20),
        temperature DECIMAL(4,1),
        pulse_rate INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        INDEX idx_patient_date (patient_id, visit_date),
        INDEX idx_doctor_date (doctor_id, visit_date)
    )");
    $db->execute();
    echo "✓ medical_records table created\n";

    // Create medications table
    echo "Creating medications table...\n";
    $db->query("CREATE TABLE IF NOT EXISTS medications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        generic_name VARCHAR(255),
        category VARCHAR(100),
        dosage_forms JSON,
        common_dosages JSON,
        contraindications TEXT,
        side_effects TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_category (category)
    )");
    $db->execute();
    echo "✓ medications table created\n";

    // Create prescriptions table
    echo "Creating prescriptions table...\n";
    $db->query("CREATE TABLE IF NOT EXISTS prescriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        medical_record_id INT,
        prescription_number VARCHAR(50) UNIQUE,
        prescription_date DATE NOT NULL,
        status ENUM('active', 'completed', 'cancelled', 'expired') DEFAULT 'active',
        total_cost DECIMAL(10,2),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
        INDEX idx_patient (patient_id),
        INDEX idx_doctor (doctor_id),
        INDEX idx_prescription_number (prescription_number)
    )");
    $db->execute();
    echo "✓ prescriptions table created\n";

    // Create prescription_items table
    echo "Creating prescription_items table...\n";
    $db->query("CREATE TABLE IF NOT EXISTS prescription_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prescription_id INT NOT NULL,
        medication_id INT,
        medication_name VARCHAR(255) NOT NULL,
        dosage VARCHAR(100),
        frequency VARCHAR(100),
        duration VARCHAR(100),
        quantity INT,
        instructions TEXT,
        cost DECIMAL(8,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
        FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE SET NULL,
        INDEX idx_prescription (prescription_id)
    )");
    $db->execute();
    echo "✓ prescription_items table created\n";

    // Add some sample medications
    echo "Adding sample medications...\n";
    $sample_medications = [
        ['name' => 'Paracetamol', 'generic_name' => 'Acetaminophen', 'category' => 'Analgesic', 'dosage_forms' => '["500mg tablet", "250mg tablet", "120mg/5ml syrup"]', 'common_dosages' => '["500mg twice daily", "1g three times daily"]'],
        ['name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'category' => 'Antibiotic', 'dosage_forms' => '["500mg capsule", "250mg capsule", "125mg/5ml suspension"]', 'common_dosages' => '["500mg three times daily", "250mg three times daily"]'],
        ['name' => 'Omeprazole', 'generic_name' => 'Omeprazole', 'category' => 'Proton Pump Inhibitor', 'dosage_forms' => '["20mg capsule", "40mg capsule"]', 'common_dosages' => '["20mg once daily", "40mg once daily"]'],
        ['name' => 'Metformin', 'generic_name' => 'Metformin HCl', 'category' => 'Antidiabetic', 'dosage_forms' => '["500mg tablet", "850mg tablet", "1000mg tablet"]', 'common_dosages' => '["500mg twice daily", "850mg twice daily"]'],
        ['name' => 'Amlodipine', 'generic_name' => 'Amlodipine Besylate', 'category' => 'Antihypertensive', 'dosage_forms' => '["5mg tablet", "10mg tablet"]', 'common_dosages' => '["5mg once daily", "10mg once daily"]'],
        ['name' => 'Cetirizine', 'generic_name' => 'Cetirizine HCl', 'category' => 'Antihistamine', 'dosage_forms' => '["10mg tablet", "5mg/5ml syrup"]', 'common_dosages' => '["10mg once daily", "5mg twice daily"]'],
        ['name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'category' => 'NSAID', 'dosage_forms' => '["400mg tablet", "200mg tablet", "100mg/5ml suspension"]', 'common_dosages' => '["400mg three times daily", "200mg four times daily"]'],
        ['name' => 'Salbutamol', 'generic_name' => 'Salbutamol Sulfate', 'category' => 'Bronchodilator', 'dosage_forms' => '["100mcg inhaler", "2mg tablet", "2mg/5ml syrup"]', 'common_dosages' => '["2 puffs four times daily", "2mg three times daily"]']
    ];

    foreach ($sample_medications as $med) {
        $db->query("INSERT IGNORE INTO medications (name, generic_name, category, dosage_forms, common_dosages) 
                    VALUES (:name, :generic_name, :category, :dosage_forms, :common_dosages)");
        $db->bind(':name', $med['name']);
        $db->bind(':generic_name', $med['generic_name']);
        $db->bind(':category', $med['category']);
        $db->bind(':dosage_forms', $med['dosage_forms']);
        $db->bind(':common_dosages', $med['common_dosages']);
        $db->execute();
    }
    echo "✓ Sample medications added\n";

    echo "\n" . "=" . str_repeat("=", 60) . "\n";
    echo "MEDICAL RECORDS DATABASE SETUP COMPLETE!\n";
    echo "=" . str_repeat("=", 60) . "\n\n";
    
    echo "The following tables have been created:\n";
    echo "• medical_records - Patient medical history and examination records\n";
    echo "• medications - Master list of available medications\n";
    echo "• prescriptions - Prescription headers with patient and doctor info\n";
    echo "• prescription_items - Individual medication items in prescriptions\n\n";
    
    echo "Sample data added:\n";
    echo "• 8 common medications with dosage forms and common dosages\n\n";
    
    echo "Medical records system is now ready!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
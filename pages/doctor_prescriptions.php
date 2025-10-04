<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'Prescriptions';
$db = new Database();

// Get doctor information
$db->query("SELECT d.* FROM doctors d WHERE d.user_id = :user_id");
$db->bind(':user_id', $_SESSION['user_id']);
$doctor_info = $db->single();

if (!$doctor_info) {
    $_SESSION['error'] = 'Doctor profile not found.';
    redirect('../login.php');
}

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_prescription') {
            $patient_id = $_POST['patient_id'];
            $medical_record_id = $_POST['medical_record_id'] ?: null;
            $prescription_date = $_POST['prescription_date'];
            $notes = $_POST['notes'];
            $medications = $_POST['medications'] ?? [];
            
            // Generate prescription number
            $prescription_number = 'RX' . date('Ymd') . str_pad($doctor_info['id'], 3, '0', STR_PAD_LEFT) . rand(1000, 9999);
            
            // Calculate total cost
            $total_cost = 0;
            foreach ($medications as $med) {
                if (!empty($med['medication_name']) && !empty($med['quantity']) && !empty($med['cost'])) {
                    $total_cost += floatval($med['cost']);
                }
            }
            
            // Insert prescription
            $db->query("INSERT INTO prescriptions (patient_id, doctor_id, medical_record_id, prescription_number, prescription_date, total_cost, notes) 
                        VALUES (:patient_id, :doctor_id, :medical_record_id, :prescription_number, :prescription_date, :total_cost, :notes)");
            
            $db->bind(':patient_id', $patient_id);
            $db->bind(':doctor_id', $doctor_info['id']);
            $db->bind(':medical_record_id', $medical_record_id);
            $db->bind(':prescription_number', $prescription_number);
            $db->bind(':prescription_date', $prescription_date);
            $db->bind(':total_cost', $total_cost);
            $db->bind(':notes', $notes);
            $db->execute();
            
            $prescription_id = $db->lastInsertId();
            
            // Insert prescription items
            foreach ($medications as $med) {
                if (!empty($med['medication_name']) && !empty($med['dosage']) && !empty($med['frequency'])) {
                    $db->query("INSERT INTO prescription_items (prescription_id, medication_id, medication_name, dosage, frequency, duration, quantity, instructions, cost) 
                                VALUES (:prescription_id, :medication_id, :medication_name, :dosage, :frequency, :duration, :quantity, :instructions, :cost)");
                    
                    $db->bind(':prescription_id', $prescription_id);
                    $db->bind(':medication_id', $med['medication_id'] ?: null);
                    $db->bind(':medication_name', $med['medication_name']);
                    $db->bind(':dosage', $med['dosage']);
                    $db->bind(':frequency', $med['frequency']);
                    $db->bind(':duration', $med['duration']);
                    $db->bind(':quantity', $med['quantity'] ?: null);
                    $db->bind(':instructions', $med['instructions']);
                    $db->bind(':cost', $med['cost'] ?: 0);
                    $db->execute();
                }
            }
            
            $_SESSION['success'] = 'Prescription created successfully! Prescription #: ' . $prescription_number;
            redirect('doctor_prescriptions.php');
        }
        
        if ($_POST['action'] === 'update_status') {
            $prescription_id = $_POST['prescription_id'];
            $status = $_POST['status'];
            
            $db->query("UPDATE prescriptions SET status = :status WHERE id = :id AND doctor_id = :doctor_id");
            $db->bind(':status', $status);
            $db->bind(':id', $prescription_id);
            $db->bind(':doctor_id', $doctor_info['id']);
            $db->execute();
            
            $_SESSION['success'] = 'Prescription status updated successfully!';
            redirect('doctor_prescriptions.php');
        }
        
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Get filter parameters
$patient_search = $_GET['patient'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$patient_id_filter = $_GET['patient_id'] ?? '';
$record_id_filter = $_GET['record_id'] ?? '';

// Build query conditions
$where_conditions = ["p.doctor_id = :doctor_id"];
$params = [':doctor_id' => $doctor_info['id']];

if (!empty($patient_search)) {
    $where_conditions[] = "(pt.first_name LIKE :patient_search OR pt.last_name LIKE :patient_search OR pt.patient_id LIKE :patient_search)";
    $params[':patient_search'] = '%' . $patient_search . '%';
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = :status_filter";
    $params[':status_filter'] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "p.prescription_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "p.prescription_date <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get prescriptions
    $db->query("SELECT p.*, pt.first_name, pt.last_name, pt.patient_id, pt.phone, pt.date_of_birth,
                       COUNT(pi.id) as medication_count
                FROM prescriptions p
                JOIN patients pt ON p.patient_id = pt.id
                LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                WHERE $where_clause
                GROUP BY p.id
                ORDER BY p.prescription_date DESC, p.created_at DESC");
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    $prescriptions = $db->resultSet();
    
    // Get my patients
    $db->query("SELECT DISTINCT pt.id, pt.first_name, pt.last_name, pt.patient_id
                FROM patients pt
                JOIN appointments a ON pt.id = a.patient_id
                WHERE a.doctor_id = :doctor_id
                ORDER BY pt.first_name, pt.last_name");
    $db->bind(':doctor_id', $doctor_info['id']);
    $my_patients = $db->resultSet();
    
    // Get medications
    $db->query("SELECT * FROM medications ORDER BY category, name");
    $medications = $db->resultSet();
    
    // Get patient medical records if patient is selected
    $patient_records = [];
    if (!empty($patient_id_filter)) {
        $db->query("SELECT id, visit_date, diagnosis, chief_complaint 
                    FROM medical_records 
                    WHERE patient_id = :patient_id AND doctor_id = :doctor_id 
                    ORDER BY visit_date DESC");
        $db->bind(':patient_id', $patient_id_filter);
        $db->bind(':doctor_id', $doctor_info['id']);
        $patient_records = $db->resultSet();
    }
    
    // Get statistics
    $db->query("SELECT 
                    COUNT(*) as total_prescriptions,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_prescriptions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_prescriptions,
                    COUNT(CASE WHEN prescription_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_prescriptions
                FROM prescriptions p
                WHERE p.doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $stats = $db->single();

} catch (Exception $e) {
    $error_message = 'Error loading prescriptions: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.prescriptions-container {
    padding: 20px;
}

.stats-row {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.stat-item {
    text-align: center;
    padding: 15px;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.filter-card, .prescriptions-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.prescription-item {
    border: 1px solid #f1f1f1;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.prescription-item:hover {
    border-color: #667eea;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.1);
}

.prescription-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.prescription-number {
    font-size: 1.1rem;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 5px;
}

.prescription-date {
    color: #666;
    font-size: 0.9rem;
}

.patient-info h5 {
    color: #333;
    margin-bottom: 5px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-active { background: #e8f5e8; color: #2e7d32; }
.status-completed { background: #f3e5f5; color: #7b1fa2; }
.status-cancelled { background: #ffebee; color: #c62828; }
.status-expired { background: #fff3e0; color: #ef6c00; }

.medication-list {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.medication-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px solid #e0e0e0;
}

.medication-item:last-child {
    border-bottom: none;
}

.medication-details h6 {
    color: #333;
    margin-bottom: 5px;
}

.medication-meta {
    color: #666;
    font-size: 0.9rem;
}

.medication-cost {
    font-weight: bold;
    color: #667eea;
}

.add-prescription-form {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: none;
}

.form-section {
    margin-bottom: 25px;
}

.form-section h6 {
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #667eea;
}

.medication-row {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: white;
}

.medication-row .row {
    align-items: end;
}

.no-prescriptions {
    text-align: center;
    padding: 60px;
    color: #888;
}

.no-prescriptions i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .prescriptions-container {
        padding: 15px;
    }
    
    .prescription-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .medication-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .medication-cost {
        margin-top: 5px;
    }
    
    .stat-number {
        font-size: 1.4rem;
    }
}
</style>

<div class="prescriptions-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-prescription-bottle-alt me-2"></i>Prescriptions</h1>
            <p class="text-muted mb-0">Create and manage patient prescriptions</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="toggleAddForm()">
                <i class="fas fa-plus me-1"></i>New Prescription
            </button>
            <a href="doctor_medical_records.php" class="btn btn-outline-primary">
                <i class="fas fa-file-medical me-1"></i>Medical Records
            </a>
            <a href="doctor_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-row">
        <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_prescriptions']; ?></div>
                    <div class="stat-label">Total Prescriptions</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['active_prescriptions']; ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['completed_prescriptions']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['recent_prescriptions']; ?></div>
                    <div class="stat-label">Last 30 Days</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Prescription Form -->
    <div class="add-prescription-form" id="addPrescriptionForm">
        <h5 class="mb-4"><i class="fas fa-plus me-2"></i>New Prescription</h5>
        
        <form method="POST" id="prescriptionForm">
            <input type="hidden" name="action" value="add_prescription">
            
            <div class="form-section">
                <h6><i class="fas fa-user me-2"></i>Patient Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient</label>
                        <select class="form-select" name="patient_id" id="patientSelect" required onchange="loadPatientRecords()">
                            <option value="">Select Patient</option>
                            <?php foreach ($my_patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        <?php echo ($patient_id_filter == $patient['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Medical Record (Optional)</label>
                        <select class="form-select" name="medical_record_id" id="recordSelect">
                            <option value="">No specific record</option>
                            <?php foreach ($patient_records as $record): ?>
                                <option value="<?php echo $record['id']; ?>"
                                        <?php echo ($record_id_filter == $record['id']) ? 'selected' : ''; ?>>
                                    <?php echo date('M j, Y', strtotime($record['visit_date'])) . ' - ' . htmlspecialchars(substr($record['diagnosis'] ?: $record['chief_complaint'], 0, 50)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Prescription Date</label>
                        <input type="date" class="form-control" name="prescription_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h6><i class="fas fa-pills me-2"></i>Medications</h6>
                <div id="medicationRows">
                    <div class="medication-row">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Medication</label>
                                <select class="form-select medication-select" name="medications[0][medication_id]" onchange="loadMedicationDetails(this, 0)">
                                    <option value="">Select Medication</option>
                                    <?php foreach ($medications as $med): ?>
                                        <option value="<?php echo $med['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($med['name']); ?>"
                                                data-dosage-forms='<?php echo htmlspecialchars($med['dosage_forms']); ?>'
                                                data-common-dosages='<?php echo htmlspecialchars($med['common_dosages']); ?>'>
                                            <?php echo htmlspecialchars($med['name'] . ' (' . $med['generic_name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="medications[0][medication_name]" class="medication-name">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Dosage</label>
                                <input type="text" class="form-control" name="medications[0][dosage]" placeholder="500mg" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Frequency</label>
                                <select class="form-select" name="medications[0][frequency]" required>
                                    <option value="">Select</option>
                                    <option value="Once daily">Once daily</option>
                                    <option value="Twice daily">Twice daily</option>
                                    <option value="Three times daily">Three times daily</option>
                                    <option value="Four times daily">Four times daily</option>
                                    <option value="Every 4 hours">Every 4 hours</option>
                                    <option value="Every 6 hours">Every 6 hours</option>
                                    <option value="Every 8 hours">Every 8 hours</option>
                                    <option value="As needed">As needed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Duration</label>
                                <input type="text" class="form-control" name="medications[0][duration]" placeholder="7 days">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Qty</label>
                                <input type="number" class="form-control" name="medications[0][quantity]" placeholder="30">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Cost (Rs.)</label>
                                <input type="number" class="form-control" name="medications[0][cost]" step="0.01" placeholder="100.00">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMedicationRow(this)" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <label class="form-label">Instructions</label>
                                <input type="text" class="form-control" name="medications[0][instructions]" placeholder="Take with food, avoid alcohol...">
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addMedicationRow()">
                    <i class="fas fa-plus me-1"></i>Add Medication
                </button>
            </div>

            <div class="form-section">
                <h6><i class="fas fa-notes-medical me-2"></i>Prescription Notes</h6>
                <textarea class="form-control" name="notes" rows="3" placeholder="Additional prescription notes and general instructions..."></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Create Prescription
                </button>
            </div>
        </form>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Prescriptions</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Patient</label>
                <input type="text" class="form-control" name="patient" 
                       placeholder="Search by name or ID" 
                       value="<?php echo htmlspecialchars($patient_search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Prescriptions List -->
    <div class="prescriptions-card">
        <h5 class="mb-4">
            <i class="fas fa-list me-2"></i>Prescriptions 
            <?php if (!empty($prescriptions)): ?>
                <span class="badge bg-primary"><?php echo count($prescriptions); ?></span>
            <?php endif; ?>
        </h5>

        <?php if (!empty($prescriptions)): ?>
            <?php foreach ($prescriptions as $prescription): ?>
                <div class="prescription-item">
                    <div class="prescription-header">
                        <div>
                            <div class="prescription-number"><?php echo htmlspecialchars($prescription['prescription_number']); ?></div>
                            <div class="prescription-date">
                                <?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end">
                            <span class="status-badge status-<?php echo $prescription['status']; ?>">
                                <?php echo ucfirst($prescription['status']); ?>
                            </span>
                            <?php if ($prescription['total_cost'] > 0): ?>
                                <div class="mt-1">
                                    <strong>Rs. <?php echo number_format($prescription['total_cost'], 2); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="patient-info">
                        <h5><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></h5>
                        <div class="patient-details mb-2">
                            <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($prescription['patient_id']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($prescription['phone']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-pills me-1"></i><?php echo $prescription['medication_count']; ?> medication(s)
                        </div>
                    </div>

                    <!-- Get and display medications for this prescription -->
                    <?php
                    $db->query("SELECT * FROM prescription_items WHERE prescription_id = :prescription_id ORDER BY id");
                    $db->bind(':prescription_id', $prescription['id']);
                    $prescription_items = $db->resultSet();
                    ?>

                    <?php if (!empty($prescription_items)): ?>
                        <div class="medication-list">
                            <h6><i class="fas fa-pills me-2"></i>Medications</h6>
                            <?php foreach ($prescription_items as $item): ?>
                                <div class="medication-item">
                                    <div class="medication-details">
                                        <h6><?php echo htmlspecialchars($item['medication_name']); ?></h6>
                                        <div class="medication-meta">
                                            <span><strong>Dosage:</strong> <?php echo htmlspecialchars($item['dosage']); ?></span>
                                            <span class="mx-2">•</span>
                                            <span><strong>Frequency:</strong> <?php echo htmlspecialchars($item['frequency']); ?></span>
                                            <?php if (!empty($item['duration'])): ?>
                                                <span class="mx-2">•</span>
                                                <span><strong>Duration:</strong> <?php echo htmlspecialchars($item['duration']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['quantity'])): ?>
                                                <span class="mx-2">•</span>
                                                <span><strong>Qty:</strong> <?php echo $item['quantity']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item['instructions'])): ?>
                                            <div class="mt-1">
                                                <small><em><?php echo htmlspecialchars($item['instructions']); ?></em></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['cost'] > 0): ?>
                                        <div class="medication-cost">
                                            Rs. <?php echo number_format($item['cost'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($prescription['notes'])): ?>
                        <div class="mt-3">
                            <h6><i class="fas fa-notes-medical me-2"></i>Notes</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($prescription['notes']); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <small class="text-muted">
                                Created: <?php echo date('M j, Y g:i A', strtotime($prescription['created_at'])); ?>
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($prescription['status'] === 'active'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check me-1"></i>Mark Completed
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this prescription?')">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="doctor_patient_history.php?patient_id=<?php echo $prescription['patient_id']; ?>" 
                               class="btn btn-outline-info btn-sm">
                                <i class="fas fa-history me-1"></i>Patient History
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-prescriptions">
                <i class="fas fa-prescription-bottle-alt"></i>
                <h5>No prescriptions found</h5>
                <p>No prescriptions match your current filters.</p>
                <?php if (!empty($patient_search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="doctor_prescriptions.php" class="btn btn-outline-primary">
                        <i class="fas fa-refresh me-1"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let medicationRowCount = 1;

function toggleAddForm() {
    const form = document.getElementById('addPrescriptionForm');
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    } else {
        form.style.display = 'none';
    }
}

function addMedicationRow() {
    const container = document.getElementById('medicationRows');
    const newRow = document.createElement('div');
    newRow.className = 'medication-row';
    newRow.innerHTML = `
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Medication</label>
                <select class="form-select medication-select" name="medications[${medicationRowCount}][medication_id]" onchange="loadMedicationDetails(this, ${medicationRowCount})">
                    <option value="">Select Medication</option>
                    <?php foreach ($medications as $med): ?>
                        <option value="<?php echo $med['id']; ?>" 
                                data-name="<?php echo htmlspecialchars($med['name']); ?>"
                                data-dosage-forms='<?php echo htmlspecialchars($med['dosage_forms']); ?>'
                                data-common-dosages='<?php echo htmlspecialchars($med['common_dosages']); ?>'>
                            <?php echo htmlspecialchars($med['name'] . ' (' . $med['generic_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="medications[${medicationRowCount}][medication_name]" class="medication-name">
            </div>
            <div class="col-md-2">
                <label class="form-label">Dosage</label>
                <input type="text" class="form-control" name="medications[${medicationRowCount}][dosage]" placeholder="500mg" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Frequency</label>
                <select class="form-select" name="medications[${medicationRowCount}][frequency]" required>
                    <option value="">Select</option>
                    <option value="Once daily">Once daily</option>
                    <option value="Twice daily">Twice daily</option>
                    <option value="Three times daily">Three times daily</option>
                    <option value="Four times daily">Four times daily</option>
                    <option value="Every 4 hours">Every 4 hours</option>
                    <option value="Every 6 hours">Every 6 hours</option>
                    <option value="Every 8 hours">Every 8 hours</option>
                    <option value="As needed">As needed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Duration</label>
                <input type="text" class="form-control" name="medications[${medicationRowCount}][duration]" placeholder="7 days">
            </div>
            <div class="col-md-1">
                <label class="form-label">Qty</label>
                <input type="number" class="form-control" name="medications[${medicationRowCount}][quantity]" placeholder="30">
            </div>
            <div class="col-md-1">
                <label class="form-label">Cost (Rs.)</label>
                <input type="number" class="form-control" name="medications[${medicationRowCount}][cost]" step="0.01" placeholder="100.00">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMedicationRow(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-12">
                <label class="form-label">Instructions</label>
                <input type="text" class="form-control" name="medications[${medicationRowCount}][instructions]" placeholder="Take with food, avoid alcohol...">
            </div>
        </div>
    `;
    container.appendChild(newRow);
    medicationRowCount++;
    updateRemoveButtons();
}

function removeMedicationRow(button) {
    button.closest('.medication-row').remove();
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.medication-row');
    rows.forEach((row, index) => {
        const removeBtn = row.querySelector('.btn-outline-danger');
        removeBtn.disabled = rows.length === 1;
    });
}

function loadMedicationDetails(select, rowIndex) {
    const option = select.selectedOptions[0];
    if (option.value) {
        const nameInput = select.parentElement.parentElement.querySelector('.medication-name');
        nameInput.value = option.getAttribute('data-name');
    }
}

function loadPatientRecords() {
    const patientId = document.getElementById('patientSelect').value;
    if (patientId) {
        window.location.href = `?patient_id=${patientId}`;
    }
}

// Initialize remove buttons on page load
document.addEventListener('DOMContentLoaded', function() {
    updateRemoveButtons();
});
</script>

<?php include '../includes/footer.php'; ?>
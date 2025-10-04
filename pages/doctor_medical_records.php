<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'Medical Records';
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
        if ($_POST['action'] === 'add_record') {
            $patient_id = $_POST['patient_id'];
            $visit_date = $_POST['visit_date'];
            $chief_complaint = $_POST['chief_complaint'];
            $symptoms = $_POST['symptoms'];
            $diagnosis = $_POST['diagnosis'];
            $treatment_plan = $_POST['treatment_plan'];
            $follow_up_instructions = $_POST['follow_up_instructions'];
            
            // Vital signs
            $weight = $_POST['weight'] ?: null;
            $height = $_POST['height'] ?: null;
            $blood_pressure = $_POST['blood_pressure'] ?: null;
            $temperature = $_POST['temperature'] ?: null;
            $pulse_rate = $_POST['pulse_rate'] ?: null;
            $notes = $_POST['notes'];
            
            $vital_signs = json_encode([
                'weight' => $weight,
                'height' => $height,
                'blood_pressure' => $blood_pressure,
                'temperature' => $temperature,
                'pulse_rate' => $pulse_rate
            ]);
            
            $db->query("INSERT INTO medical_records (patient_id, doctor_id, visit_date, chief_complaint, symptoms, diagnosis, treatment_plan, follow_up_instructions, vital_signs, weight, height, blood_pressure, temperature, pulse_rate, notes) 
                        VALUES (:patient_id, :doctor_id, :visit_date, :chief_complaint, :symptoms, :diagnosis, :treatment_plan, :follow_up_instructions, :vital_signs, :weight, :height, :blood_pressure, :temperature, :pulse_rate, :notes)");
            
            $db->bind(':patient_id', $patient_id);
            $db->bind(':doctor_id', $doctor_info['id']);
            $db->bind(':visit_date', $visit_date);
            $db->bind(':chief_complaint', $chief_complaint);
            $db->bind(':symptoms', $symptoms);
            $db->bind(':diagnosis', $diagnosis);
            $db->bind(':treatment_plan', $treatment_plan);
            $db->bind(':follow_up_instructions', $follow_up_instructions);
            $db->bind(':vital_signs', $vital_signs);
            $db->bind(':weight', $weight);
            $db->bind(':height', $height);
            $db->bind(':blood_pressure', $blood_pressure);
            $db->bind(':temperature', $temperature);
            $db->bind(':pulse_rate', $pulse_rate);
            $db->bind(':notes', $notes);
            
            if ($db->execute()) {
                $_SESSION['success'] = 'Medical record added successfully!';
                redirect('doctor_medical_records.php');
            }
        }
        
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Get filter parameters
$patient_search = $_GET['patient'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$diagnosis_search = $_GET['diagnosis'] ?? '';

// Build query conditions
$where_conditions = ["mr.doctor_id = :doctor_id"];
$params = [':doctor_id' => $doctor_info['id']];

if (!empty($patient_search)) {
    $where_conditions[] = "(p.first_name LIKE :patient_search OR p.last_name LIKE :patient_search OR p.patient_id LIKE :patient_search)";
    $params[':patient_search'] = '%' . $patient_search . '%';
}

if (!empty($date_from)) {
    $where_conditions[] = "mr.visit_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "mr.visit_date <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($diagnosis_search)) {
    $where_conditions[] = "mr.diagnosis LIKE :diagnosis_search";
    $params[':diagnosis_search'] = '%' . $diagnosis_search . '%';
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get medical records
    $db->query("SELECT mr.*, p.first_name, p.last_name, p.patient_id, p.date_of_birth, p.gender, p.phone
                FROM medical_records mr
                JOIN patients p ON mr.patient_id = p.id
                WHERE $where_clause
                ORDER BY mr.visit_date DESC, mr.created_at DESC
                LIMIT 50");
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    $medical_records = $db->resultSet();
    
    // Get my patients (who have appointments with this doctor)
    $db->query("SELECT DISTINCT p.id, p.first_name, p.last_name, p.patient_id
                FROM patients p
                JOIN appointments a ON p.id = a.patient_id
                WHERE a.doctor_id = :doctor_id
                ORDER BY p.first_name, p.last_name");
    $db->bind(':doctor_id', $doctor_info['id']);
    $my_patients = $db->resultSet();
    
    // Get statistics
    $db->query("SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT mr.patient_id) as total_patients,
                    COUNT(CASE WHEN mr.visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_records,
                    COUNT(CASE WHEN mr.visit_date = CURDATE() THEN 1 END) as today_records
                FROM medical_records mr
                WHERE mr.doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $stats = $db->single();

} catch (Exception $e) {
    $error_message = 'Error loading medical records: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.medical-records-container {
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

.filter-card, .records-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.record-item {
    border: 1px solid #f1f1f1;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.record-item:hover {
    border-color: #667eea;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.1);
}

.record-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.patient-info h5 {
    color: #333;
    margin-bottom: 5px;
}

.patient-details {
    color: #666;
    font-size: 0.9rem;
}

.visit-info {
    text-align: right;
}

.visit-date {
    font-size: 1.1rem;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 5px;
}

.record-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 15px;
}

.content-section h6 {
    color: #333;
    margin-bottom: 10px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.content-section p {
    margin-bottom: 0;
    color: #555;
    line-height: 1.5;
}

.vital-signs {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.vital-signs h6 {
    color: #333;
    margin-bottom: 10px;
}

.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.vital-item {
    text-align: center;
    padding: 8px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.vital-value {
    font-weight: bold;
    color: #667eea;
    font-size: 0.9rem;
}

.vital-label {
    font-size: 0.8rem;
    color: #666;
    margin-top: 2px;
}

.add-record-form {
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

.vital-inputs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.no-records {
    text-align: center;
    padding: 60px;
    color: #888;
}

.no-records i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .medical-records-container {
        padding: 15px;
    }
    
    .record-content {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .record-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .visit-info {
        text-align: left;
        margin-top: 10px;
    }
    
    .vitals-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-number {
        font-size: 1.4rem;
    }
}
</style>

<div class="medical-records-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-file-medical me-2"></i>Medical Records</h1>
            <p class="text-muted mb-0">Manage patient medical records and history</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="toggleAddForm()">
                <i class="fas fa-plus me-1"></i>Add Record
            </button>
            <a href="doctor_prescriptions.php" class="btn btn-outline-primary">
                <i class="fas fa-prescription-bottle-alt me-1"></i>Prescriptions
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
                    <div class="stat-number"><?php echo $stats['total_records']; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                    <div class="stat-label">Patients</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['recent_records']; ?></div>
                    <div class="stat-label">Last 30 Days</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['today_records']; ?></div>
                    <div class="stat-label">Today</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Record Form -->
    <div class="add-record-form" id="addRecordForm">
        <h5 class="mb-4"><i class="fas fa-plus me-2"></i>Add New Medical Record</h5>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_record">
            
            <div class="form-section">
                <h6><i class="fas fa-user me-2"></i>Patient & Visit Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient</label>
                        <select class="form-select" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($my_patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Visit Date</label>
                        <input type="date" class="form-control" name="visit_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h6><i class="fas fa-stethoscope me-2"></i>Clinical Information</h6>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Chief Complaint</label>
                        <textarea class="form-control" name="chief_complaint" rows="2" placeholder="Main reason for visit..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Symptoms</label>
                        <textarea class="form-control" name="symptoms" rows="3" placeholder="Patient symptoms..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Diagnosis</label>
                        <textarea class="form-control" name="diagnosis" rows="3" placeholder="Clinical diagnosis..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Treatment Plan</label>
                        <textarea class="form-control" name="treatment_plan" rows="3" placeholder="Treatment recommendations..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Follow-up Instructions</label>
                        <textarea class="form-control" name="follow_up_instructions" rows="3" placeholder="Follow-up care instructions..."></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h6><i class="fas fa-heartbeat me-2"></i>Vital Signs</h6>
                <div class="vital-inputs">
                    <div>
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" name="weight" step="0.1" placeholder="70.5">
                    </div>
                    <div>
                        <label class="form-label">Height (cm)</label>
                        <input type="number" class="form-control" name="height" step="0.1" placeholder="175.0">
                    </div>
                    <div>
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" class="form-control" name="blood_pressure" placeholder="120/80">
                    </div>
                    <div>
                        <label class="form-label">Temperature (°C)</label>
                        <input type="number" class="form-control" name="temperature" step="0.1" placeholder="37.0">
                    </div>
                    <div>
                        <label class="form-label">Pulse Rate (bpm)</label>
                        <input type="number" class="form-control" name="pulse_rate" placeholder="72">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h6><i class="fas fa-notes-medical me-2"></i>Additional Notes</h6>
                <textarea class="form-control" name="notes" rows="3" placeholder="Additional clinical notes..."></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Save Record
                </button>
            </div>
        </form>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Records</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Patient</label>
                <input type="text" class="form-control" name="patient" 
                       placeholder="Search by name or ID" 
                       value="<?php echo htmlspecialchars($patient_search); ?>">
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
            <div class="col-md-3">
                <label class="form-label">Diagnosis</label>
                <input type="text" class="form-control" name="diagnosis" 
                       placeholder="Search diagnosis" 
                       value="<?php echo htmlspecialchars($diagnosis_search); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
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

    <!-- Medical Records List -->
    <div class="records-card">
        <h5 class="mb-4">
            <i class="fas fa-list me-2"></i>Medical Records 
            <?php if (!empty($medical_records)): ?>
                <span class="badge bg-primary"><?php echo count($medical_records); ?></span>
            <?php endif; ?>
        </h5>

        <?php if (!empty($medical_records)): ?>
            <?php foreach ($medical_records as $record): ?>
                <div class="record-item">
                    <div class="record-header">
                        <div class="patient-info">
                            <h5><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></h5>
                            <div class="patient-details">
                                <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($record['patient_id']); ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($record['phone']); ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-birthday-cake me-1"></i><?php echo date('M j, Y', strtotime($record['date_of_birth'])); ?>
                            </div>
                        </div>
                        <div class="visit-info">
                            <div class="visit-date">
                                <?php echo date('M j, Y', strtotime($record['visit_date'])); ?>
                            </div>
                            <small class="text-muted">
                                Recorded: <?php echo date('M j, Y g:i A', strtotime($record['created_at'])); ?>
                            </small>
                        </div>
                    </div>

                    <div class="record-content">
                        <?php if (!empty($record['chief_complaint'])): ?>
                            <div class="content-section">
                                <h6>Chief Complaint</h6>
                                <p><?php echo htmlspecialchars($record['chief_complaint']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['symptoms'])): ?>
                            <div class="content-section">
                                <h6>Symptoms</h6>
                                <p><?php echo htmlspecialchars($record['symptoms']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['diagnosis'])): ?>
                            <div class="content-section">
                                <h6>Diagnosis</h6>
                                <p><?php echo htmlspecialchars($record['diagnosis']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['treatment_plan'])): ?>
                            <div class="content-section">
                                <h6>Treatment Plan</h6>
                                <p><?php echo htmlspecialchars($record['treatment_plan']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['follow_up_instructions'])): ?>
                            <div class="content-section">
                                <h6>Follow-up Instructions</h6>
                                <p><?php echo htmlspecialchars($record['follow_up_instructions']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['notes'])): ?>
                            <div class="content-section">
                                <h6>Additional Notes</h6>
                                <p><?php echo htmlspecialchars($record['notes']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Vital Signs -->
                    <?php 
                    $has_vitals = !empty($record['weight']) || !empty($record['height']) || !empty($record['blood_pressure']) || !empty($record['temperature']) || !empty($record['pulse_rate']);
                    if ($has_vitals): 
                    ?>
                        <div class="vital-signs">
                            <h6><i class="fas fa-heartbeat me-2"></i>Vital Signs</h6>
                            <div class="vitals-grid">
                                <?php if (!empty($record['weight'])): ?>
                                    <div class="vital-item">
                                        <div class="vital-value"><?php echo $record['weight']; ?> kg</div>
                                        <div class="vital-label">Weight</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['height'])): ?>
                                    <div class="vital-item">
                                        <div class="vital-value"><?php echo $record['height']; ?> cm</div>
                                        <div class="vital-label">Height</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['blood_pressure'])): ?>
                                    <div class="vital-item">
                                        <div class="vital-value"><?php echo htmlspecialchars($record['blood_pressure']); ?></div>
                                        <div class="vital-label">Blood Pressure</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['temperature'])): ?>
                                    <div class="vital-item">
                                        <div class="vital-value"><?php echo $record['temperature']; ?>°C</div>
                                        <div class="vital-label">Temperature</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['pulse_rate'])): ?>
                                    <div class="vital-item">
                                        <div class="vital-value"><?php echo $record['pulse_rate']; ?> bpm</div>
                                        <div class="vital-label">Pulse Rate</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <a href="doctor_prescriptions.php?patient_id=<?php echo $record['patient_id']; ?>&record_id=<?php echo $record['id']; ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-prescription-bottle-alt me-1"></i>Add Prescription
                        </a>
                        <a href="doctor_patient_history.php?patient_id=<?php echo $record['patient_id']; ?>" 
                           class="btn btn-outline-info btn-sm">
                            <i class="fas fa-history me-1"></i>Patient History
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-records">
                <i class="fas fa-file-medical"></i>
                <h5>No medical records found</h5>
                <p>No medical records match your current filters.</p>
                <?php if (!empty($patient_search) || !empty($date_from) || !empty($date_to) || !empty($diagnosis_search)): ?>
                    <a href="doctor_medical_records.php" class="btn btn-outline-primary">
                        <i class="fas fa-refresh me-1"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('addRecordForm');
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    } else {
        form.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
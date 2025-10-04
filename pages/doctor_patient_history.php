<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'Patient Medical History';
$db = new Database();

// Get doctor information
$db->query("SELECT d.* FROM doctors d WHERE d.user_id = :user_id");
$db->bind(':user_id', $_SESSION['user_id']);
$doctor_info = $db->single();

if (!$doctor_info) {
    $_SESSION['error'] = 'Doctor profile not found.';
    redirect('../login.php');
}

// Get patient ID from URL
$patient_id = $_GET['patient_id'] ?? null;
if (!$patient_id) {
    $_SESSION['error'] = 'No patient specified.';
    redirect('doctor_patients.php');
}

try {
    // Get patient information
    $db->query("SELECT p.* FROM patients p WHERE p.id = :patient_id");
    $db->bind(':patient_id', $patient_id);
    $patient_info = $db->single();
    
    if (!$patient_info) {
        $_SESSION['error'] = 'Patient not found.';
        redirect('doctor_patients.php');
    }
    
    // Check if doctor has treated this patient
    $db->query("SELECT COUNT(*) as appointment_count FROM appointments WHERE patient_id = :patient_id AND doctor_id = :doctor_id");
    $db->bind(':patient_id', $patient_id);
    $db->bind(':doctor_id', $doctor_info['id']);
    $relationship = $db->single();
    
    if ($relationship['appointment_count'] == 0) {
        $_SESSION['error'] = 'You have not treated this patient.';
        redirect('doctor_patients.php');
    }
    
    // Get all medical records for this patient (from this doctor)
    $db->query("SELECT mr.*, d.first_name as doctor_fname, d.last_name as doctor_lname
                FROM medical_records mr
                LEFT JOIN doctors d ON mr.doctor_id = d.id
                WHERE mr.patient_id = :patient_id AND mr.doctor_id = :doctor_id
                ORDER BY mr.visit_date DESC, mr.created_at DESC");
    $db->bind(':patient_id', $patient_id);
    $db->bind(':doctor_id', $doctor_info['id']);
    $medical_records = $db->resultSet();
    
    // Get all prescriptions for this patient (from this doctor)
    $db->query("SELECT p.*, COUNT(pi.id) as medication_count
                FROM prescriptions p
                LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                WHERE p.patient_id = :patient_id AND p.doctor_id = :doctor_id
                GROUP BY p.id
                ORDER BY p.prescription_date DESC, p.created_at DESC");
    $db->bind(':patient_id', $patient_id);
    $db->bind(':doctor_id', $doctor_info['id']);
    $prescriptions = $db->resultSet();
    
    // Get all appointments for this patient (with this doctor)
    $db->query("SELECT a.* FROM appointments a 
                WHERE a.patient_id = :patient_id AND a.doctor_id = :doctor_id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $db->bind(':patient_id', $patient_id);
    $db->bind(':doctor_id', $doctor_info['id']);
    $appointments = $db->resultSet();
    
    // Get patient statistics
    $db->query("SELECT 
                    COUNT(DISTINCT mr.id) as total_visits,
                    COUNT(DISTINCT p.id) as total_prescriptions,
                    COUNT(DISTINCT a.id) as total_appointments,
                    MIN(mr.visit_date) as first_visit,
                    MAX(mr.visit_date) as last_visit
                FROM medical_records mr
                LEFT JOIN prescriptions p ON mr.patient_id = p.patient_id AND mr.doctor_id = p.doctor_id
                LEFT JOIN appointments a ON mr.patient_id = a.patient_id AND mr.doctor_id = a.doctor_id
                WHERE mr.patient_id = :patient_id AND mr.doctor_id = :doctor_id");
    $db->bind(':patient_id', $patient_id);
    $db->bind(':doctor_id', $doctor_info['id']);
    $stats = $db->single();
    
    // Calculate age
    $age = date_diff(date_create($patient_info['date_of_birth']), date_create('today'))->y;
    
    // Create timeline events (combine records, prescriptions, appointments)
    $timeline = [];
    
    foreach ($medical_records as $record) {
        $timeline[] = [
            'type' => 'medical_record',
            'date' => $record['visit_date'],
            'datetime' => $record['created_at'],
            'title' => 'Medical Record',
            'icon' => 'file-medical-alt',
            'color' => 'primary',
            'data' => $record
        ];
    }
    
    foreach ($prescriptions as $prescription) {
        $timeline[] = [
            'type' => 'prescription',
            'date' => $prescription['prescription_date'],
            'datetime' => $prescription['created_at'],
            'title' => 'Prescription',
            'icon' => 'prescription-bottle-alt',
            'color' => 'success',
            'data' => $prescription
        ];
    }
    
    foreach ($appointments as $appointment) {
        $timeline[] = [
            'type' => 'appointment',
            'date' => $appointment['appointment_date'],
            'datetime' => $appointment['created_at'],
            'title' => 'Appointment',
            'icon' => 'calendar-check',
            'color' => 'info',
            'data' => $appointment
        ];
    }
    
    // Sort timeline by date (most recent first)
    usort($timeline, function($a, $b) {
        $dateComparison = strtotime($b['date']) - strtotime($a['date']);
        if ($dateComparison === 0) {
            return strtotime($b['datetime']) - strtotime($a['datetime']);
        }
        return $dateComparison;
    });
    
} catch (Exception $e) {
    $error_message = 'Error loading patient history: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.patient-history-container {
    padding: 20px;
}

.patient-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.patient-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.patient-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 15px;
}

.patient-details {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    text-align: center;
    border-left: 4px solid #667eea;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.timeline-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #667eea, #764ba2);
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 4px solid #667eea;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -37px;
    top: 20px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: white;
    border: 3px solid #667eea;
    z-index: 2;
}

.timeline-item.primary::before { border-color: #007bff; }
.timeline-item.success::before { border-color: #28a745; }
.timeline-item.info::before { border-color: #17a2b8; }
.timeline-item.warning::before { border-color: #ffc107; }
.timeline-item.danger::before { border-color: #dc3545; }

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.timeline-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: bold;
    color: #333;
}

.timeline-title i {
    padding: 8px;
    border-radius: 50%;
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}

.timeline-date {
    background: #667eea;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.timeline-content {
    color: #555;
    line-height: 1.6;
}

.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.content-section h6 {
    color: #333;
    margin-bottom: 8px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vital-signs-mini {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.vital-mini {
    background: white;
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #ddd;
    font-size: 0.8rem;
}

.medication-mini {
    background: white;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ddd;
    margin-bottom: 8px;
}

.medication-mini h6 {
    margin-bottom: 5px;
    color: #333;
}

.no-history {
    text-align: center;
    padding: 60px;
    color: #888;
}

.no-history i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .patient-history-container {
        padding: 15px;
    }
    
    .patient-header {
        padding: 20px;
        text-align: center;
    }
    
    .patient-details {
        justify-content: center;
        flex-direction: column;
        align-items: center;
    }
    
    .timeline {
        padding-left: 25px;
    }
    
    .timeline-item::before {
        left: -32px;
    }
    
    .timeline-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-number {
        font-size: 1.4rem;
    }
}
</style>

<div class="patient-history-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-history me-2"></i>Patient Medical History</h1>
            <p class="text-muted mb-0">Complete medical timeline and records</p>
        </div>
        <div class="d-flex gap-2">
            <a href="doctor_medical_records.php?patient=<?php echo urlencode($patient_info['first_name'] . ' ' . $patient_info['last_name']); ?>" 
               class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Add Record
            </a>
            <a href="doctor_prescriptions.php?patient_id=<?php echo $patient_id; ?>" 
               class="btn btn-outline-primary">
                <i class="fas fa-prescription-bottle-alt me-1"></i>New Prescription
            </a>
            <a href="doctor_patients.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Patients
            </a>
        </div>
    </div>

    <!-- Patient Header -->
    <div class="patient-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="patient-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h2><?php echo htmlspecialchars($patient_info['first_name'] . ' ' . $patient_info['last_name']); ?></h2>
                <p class="mb-1">Patient ID: <?php echo htmlspecialchars($patient_info['patient_id']); ?></p>
                
                <div class="patient-details">
                    <div class="detail-item">
                        <i class="fas fa-birthday-cake"></i>
                        <span><?php echo $age; ?> years old</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-venus-mars"></i>
                        <span><?php echo ucfirst($patient_info['gender']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars($patient_info['phone']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($patient_info['email']); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <?php if (!empty($patient_info['blood_type'])): ?>
                    <div class="mb-2">
                        <strong>Blood Type</strong>
                    </div>
                    <div class="h3 mb-3"><?php echo htmlspecialchars($patient_info['blood_type']); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($patient_info['emergency_contact'])): ?>
                    <div class="mb-1">
                        <strong>Emergency Contact</strong>
                    </div>
                    <div><?php echo htmlspecialchars($patient_info['emergency_contact']); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_visits'] ?? 0; ?></div>
            <div class="stat-label">Medical Records</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_prescriptions'] ?? 0; ?></div>
            <div class="stat-label">Prescriptions</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_appointments'] ?? 0; ?></div>
            <div class="stat-label">Appointments</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php 
                if ($stats['first_visit']) {
                    $first_visit = new DateTime($stats['first_visit']);
                    $now = new DateTime();
                    $diff = $first_visit->diff($now);
                    if ($diff->y > 0) {
                        echo $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
                    } elseif ($diff->m > 0) {
                        echo $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                    } else {
                        echo $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                    }
                } else {
                    echo 'N/A';
                }
                ?>
            </div>
            <div class="stat-label">Patient Since</div>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Timeline -->
    <div class="timeline-container">
        <h5 class="mb-4">
            <i class="fas fa-clock me-2"></i>Medical Timeline
            <?php if (!empty($timeline)): ?>
                <span class="badge bg-primary"><?php echo count($timeline); ?> events</span>
            <?php endif; ?>
        </h5>

        <?php if (!empty($timeline)): ?>
            <div class="timeline">
                <?php foreach ($timeline as $event): ?>
                    <div class="timeline-item <?php echo $event['color']; ?>">
                        <div class="timeline-header">
                            <div class="timeline-title">
                                <i class="fas fa-<?php echo $event['icon']; ?>"></i>
                                <?php echo $event['title']; ?>
                            </div>
                            <div class="timeline-date">
                                <?php echo date('M j, Y', strtotime($event['date'])); ?>
                            </div>
                        </div>

                        <div class="timeline-content">
                            <?php if ($event['type'] === 'medical_record'): ?>
                                <?php $record = $event['data']; ?>
                                
                                <?php if (!empty($record['chief_complaint'])): ?>
                                    <div class="mb-3">
                                        <strong>Chief Complaint:</strong> <?php echo htmlspecialchars($record['chief_complaint']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($record['diagnosis'])): ?>
                                    <div class="mb-3">
                                        <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="content-grid">
                                    <?php if (!empty($record['symptoms'])): ?>
                                        <div class="content-section">
                                            <h6>Symptoms</h6>
                                            <p><?php echo htmlspecialchars($record['symptoms']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($record['treatment_plan'])): ?>
                                        <div class="content-section">
                                            <h6>Treatment Plan</h6>
                                            <p><?php echo htmlspecialchars($record['treatment_plan']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Vital Signs -->
                                <?php 
                                $has_vitals = !empty($record['weight']) || !empty($record['height']) || !empty($record['blood_pressure']) || !empty($record['temperature']) || !empty($record['pulse_rate']);
                                if ($has_vitals): 
                                ?>
                                    <div class="vital-signs-mini">
                                        <?php if (!empty($record['weight'])): ?>
                                            <div class="vital-mini">Weight: <?php echo $record['weight']; ?> kg</div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['height'])): ?>
                                            <div class="vital-mini">Height: <?php echo $record['height']; ?> cm</div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['blood_pressure'])): ?>
                                            <div class="vital-mini">BP: <?php echo htmlspecialchars($record['blood_pressure']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['temperature'])): ?>
                                            <div class="vital-mini">Temp: <?php echo $record['temperature']; ?>°C</div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['pulse_rate'])): ?>
                                            <div class="vital-mini">Pulse: <?php echo $record['pulse_rate']; ?> bpm</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($event['type'] === 'prescription'): ?>
                                <?php $prescription = $event['data']; ?>
                                
                                <div class="mb-3">
                                    <strong>Prescription #:</strong> <?php echo htmlspecialchars($prescription['prescription_number']); ?>
                                    <span class="mx-2">•</span>
                                    <strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $prescription['status'] === 'active' ? 'success' : ($prescription['status'] === 'completed' ? 'primary' : 'secondary'); ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                    <?php if ($prescription['total_cost'] > 0): ?>
                                        <span class="mx-2">•</span>
                                        <strong>Cost:</strong> Rs. <?php echo number_format($prescription['total_cost'], 2); ?>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-2">
                                    <strong><?php echo $prescription['medication_count']; ?> medication(s) prescribed</strong>
                                </div>

                                <!-- Get prescription items -->
                                <?php
                                $db->query("SELECT * FROM prescription_items WHERE prescription_id = :prescription_id ORDER BY id");
                                $db->bind(':prescription_id', $prescription['id']);
                                $prescription_items = $db->resultSet();
                                ?>

                                <?php if (!empty($prescription_items)): ?>
                                    <?php foreach ($prescription_items as $item): ?>
                                        <div class="medication-mini">
                                            <h6><?php echo htmlspecialchars($item['medication_name']); ?></h6>
                                            <small>
                                                <?php echo htmlspecialchars($item['dosage']); ?> • 
                                                <?php echo htmlspecialchars($item['frequency']); ?>
                                                <?php if (!empty($item['duration'])): ?>
                                                     • <?php echo htmlspecialchars($item['duration']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                            <?php elseif ($event['type'] === 'appointment'): ?>
                                <?php $appointment = $event['data']; ?>
                                
                                <div class="mb-3">
                                    <strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    <span class="mx-2">•</span>
                                    <strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'confirmed' ? 'primary' : 'secondary'); ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $appointment['status'])); ?>
                                    </span>
                                </div>

                                <?php if (!empty($appointment['reason'])): ?>
                                    <div class="mb-2">
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($appointment['notes'])): ?>
                                    <div class="mb-2">
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-history">
                <i class="fas fa-history"></i>
                <h5>No medical history found</h5>
                <p>No medical records, prescriptions, or appointments found for this patient.</p>
                <a href="doctor_medical_records.php?patient=<?php echo urlencode($patient_info['first_name'] . ' ' . $patient_info['last_name']); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Add First Medical Record
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

$patient_id = $_GET['id'] ?? null;

if (!$patient_id) {
    echo '<div class="alert alert-danger">Patient ID not provided</div>';
    exit;
}

try {
    // Get patient details
    $db->query('
        SELECT p.*, u.username, u.email as user_email, u.status as user_status, u.created_at as registered_date
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = :id
    ');
    $db->bind(':id', $patient_id);
    $patient = $db->single();
    
    if (!$patient) {
        echo '<div class="alert alert-danger">Patient not found</div>';
        exit;
    }
    
    // Get appointment statistics
    $db->query('SELECT COUNT(*) as total FROM appointments WHERE patient_id = :patient_id');
    $db->bind(':patient_id', $patient_id);
    $total_appointments = $db->single()['total'];
    
    // Get upcoming appointments
    $db->query('
        SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = :patient_id AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 3
    ');
    $db->bind(':patient_id', $patient_id);
    $upcoming_appointments = $db->resultSet();
    
    // Get recent appointments
    $db->query('
        SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = :patient_id AND a.appointment_date < CURDATE()
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 3
    ');
    $db->bind(':patient_id', $patient_id);
    $recent_appointments = $db->resultSet();
    
    // Get medical records count
    $db->query('SELECT COUNT(*) as total FROM medical_records WHERE patient_id = :patient_id');
    $db->bind(':patient_id', $patient_id);
    $total_medical_records = $db->single()['total'];
    
    // Get recent medical records
    $db->query('
        SELECT mr.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        WHERE mr.patient_id = :patient_id
        ORDER BY mr.visit_date DESC
        LIMIT 3
    ');
    $db->bind(':patient_id', $patient_id);
    $recent_medical_records = $db->resultSet();
    
    // Calculate age
    $age = date('Y') - date('Y', strtotime($patient['date_of_birth']));
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading patient details: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="row">
    <!-- Patient Info Card -->
    <div class="col-12 mb-4">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <div class="d-flex align-items-center">
                    <div class="user-avatar bg-white text-primary me-3" style="width: 50px; height: 50px; font-size: 20px;">
                        <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h5 class="mb-0"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h5>
                        <small>Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?></small>
                    </div>
                    <div class="ms-auto">
                        <?php if ($patient['user_status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Personal Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Full Name:</strong></td>
                                <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date of Birth:</strong></td>
                                <td><?php echo format_date($patient['date_of_birth']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Age:</strong></td>
                                <td><?php echo $age; ?> years</td>
                            </tr>
                            <tr>
                                <td><strong>Gender:</strong></td>
                                <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Blood Group:</strong></td>
                                <td>
                                    <?php if ($patient['blood_group']): ?>
                                        <span class="badge bg-danger"><?php echo htmlspecialchars($patient['blood_group']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Contact Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td>
                                    <a href="tel:<?php echo htmlspecialchars($patient['phone']); ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($patient['phone']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($patient['user_email']); ?>" class="text-decoration-none">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($patient['user_email']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Address:</strong></td>
                                <td><?php echo $patient['address'] ? htmlspecialchars($patient['address']) : '<span class="text-muted">Not provided</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><?php echo htmlspecialchars($patient['username']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Registered:</strong></td>
                                <td><?php echo format_date($patient['registered_date']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($patient['emergency_contact_name'] || $patient['insurance_number']): ?>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <?php if ($patient['emergency_contact_name']): ?>
                                <h6 class="text-warning mb-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Emergency Contact
                                </h6>
                                <p class="mb-0">
                                    <strong><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></strong>
                                    <?php if ($patient['emergency_contact_phone']): ?>
                                        <br><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if ($patient['insurance_number']): ?>
                                <h6 class="text-info mb-2">
                                    <i class="fas fa-shield-alt me-1"></i>Insurance
                                </h6>
                                <p class="mb-0"><?php echo htmlspecialchars($patient['insurance_number']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($patient['allergies'] || $patient['medical_history']): ?>
                    <hr>
                    <div class="row">
                        <?php if ($patient['allergies']): ?>
                            <div class="col-md-6">
                                <h6 class="text-danger mb-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Known Allergies
                                </h6>
                                <div class="alert alert-warning py-2">
                                    <?php echo nl2br(htmlspecialchars($patient['allergies'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($patient['medical_history']): ?>
                            <div class="col-md-6">
                                <h6 class="text-info mb-2">
                                    <i class="fas fa-notes-medical me-1"></i>Medical History
                                </h6>
                                <div class="alert alert-info py-2">
                                    <?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                <h4><?php echo $total_appointments; ?></h4>
                <small>Total Appointments</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body text-center">
                <i class="fas fa-file-medical-alt fa-2x mb-2"></i>
                <h4><?php echo $total_medical_records; ?></h4>
                <small>Medical Records</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                <h4><?php echo count($upcoming_appointments); ?></h4>
                <small>Upcoming Appointments</small>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Appointments -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Upcoming Appointments
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No upcoming appointments</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_appointments as $appointment): ?>
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <div class="me-3">
                                <div class="text-primary fw-bold"><?php echo format_date($appointment['appointment_date']); ?></div>
                                <small class="text-muted"><?php echo format_time($appointment['appointment_time']); ?></small>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">
                                    Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                            </div>
                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Medical Records -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-file-medical me-2"></i>Recent Medical Records
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_medical_records)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-file-medical-alt fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No medical records</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_medical_records as $record): ?>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="fw-bold">
                                    Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                                </div>
                                <small class="text-muted"><?php echo format_date($record['visit_date']); ?></small>
                            </div>
                            <?php if ($record['diagnosis']): ?>
                                <div class="mb-1">
                                    <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($record['treatment']): ?>
                                <div>
                                    <small class="text-muted">
                                        <strong>Treatment:</strong> 
                                        <?php echo htmlspecialchars(substr($record['treatment'], 0, 80)); ?>
                                        <?php if (strlen($record['treatment']) > 80) echo '...'; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="button" class="btn btn-primary" onclick="editPatient(<?php echo $patient['id']; ?>); $('#patientDetailsModal').modal('hide');">
        <i class="fas fa-edit me-1"></i>Edit Patient
    </button>
</div>
<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

$doctor_id = $_GET['id'] ?? null;

if (!$doctor_id) {
    echo '<div class="alert alert-danger">Doctor ID not provided</div>';
    exit;
}

try {
    // Get doctor details
    $db->query('
        SELECT d.*, u.username, u.email as user_email, u.status as user_status, u.created_at as registered_date,
               dept.name as department_name
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE d.id = :id
    ');
    $db->bind(':id', $doctor_id);
    $doctor = $db->single();
    
    if (!$doctor) {
        echo '<div class="alert alert-danger">Doctor not found</div>';
        exit;
    }
    
    // Get appointment statistics
    $db->query('SELECT COUNT(*) as total FROM appointments WHERE doctor_id = :doctor_id');
    $db->bind(':doctor_id', $doctor_id);
    $total_appointments = $db->single()['total'];
    
    // Get upcoming appointments
    $db->query('
        SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = :doctor_id AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ');
    $db->bind(':doctor_id', $doctor_id);
    $upcoming_appointments = $db->resultSet();
    
    // Get recent appointments
    $db->query('
        SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = :doctor_id AND a.appointment_date < CURDATE()
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ');
    $db->bind(':doctor_id', $doctor_id);
    $recent_appointments = $db->resultSet();
    
    // Get patients count
    $db->query('SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = :doctor_id');
    $db->bind(':doctor_id', $doctor_id);
    $total_patients = $db->single()['total'];
    
    // Get recent medical records
    $db->query('
        SELECT mr.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id
        FROM medical_records mr
        JOIN patients p ON mr.patient_id = p.id
        WHERE mr.doctor_id = :doctor_id
        ORDER BY mr.visit_date DESC
        LIMIT 5
    ');
    $db->bind(':doctor_id', $doctor_id);
    $recent_medical_records = $db->resultSet();
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading doctor details: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="row">
    <!-- Doctor Info Card -->
    <div class="col-12 mb-4">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <div class="d-flex align-items-center">
                    <div class="user-avatar bg-white text-primary me-3" style="width: 60px; height: 60px; font-size: 24px;">
                        <?php echo strtoupper(substr($doctor['first_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h5 class="mb-0">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h5>
                        <p class="mb-0"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                        <small>Employee ID: <?php echo htmlspecialchars($doctor['employee_id']); ?></small>
                    </div>
                    <div class="ms-auto text-end">
                        <?php if ($doctor['user_status'] === 'active'): ?>
                            <span class="badge bg-success fs-6">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary fs-6">Inactive</span>
                        <?php endif; ?>
                        <br>
                        <small>Joined: <?php echo format_date($doctor['registered_date']); ?></small>
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
                                <td>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td>
                                    <a href="tel:<?php echo htmlspecialchars($doctor['phone']); ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($doctor['phone']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($doctor['user_email']); ?>" class="text-decoration-none">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($doctor['user_email']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><?php echo htmlspecialchars($doctor['username']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Professional Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Specialization:</strong></td>
                                <td>
                                    <span class="badge bg-info fs-6">
                                        <?php echo htmlspecialchars($doctor['specialization']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Department:</strong></td>
                                <td>
                                    <?php if ($doctor['department_name']): ?>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($doctor['department_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Qualification:</strong></td>
                                <td><?php echo htmlspecialchars($doctor['qualification']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Experience:</strong></td>
                                <td><strong><?php echo $doctor['experience_years']; ?></strong> years</td>
                            </tr>
                            <tr>
                                <td><strong>Consultation Fee:</strong></td>
                                <td><strong class="text-success">Rs. <?php echo number_format($doctor['consultation_fee'], 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-warning mb-2">
                            <i class="fas fa-clock me-1"></i>Schedule Information
                        </h6>
                        <p class="mb-2">
                            <strong>Working Hours:</strong> 
                            <?php echo format_time($doctor['schedule_start']); ?> - 
                            <?php echo format_time($doctor['schedule_end']); ?>
                        </p>
                        <?php if ($doctor['available_days']): ?>
                            <p class="mb-0">
                                <strong>Available Days:</strong><br>
                                <?php 
                                $days = explode(',', $doctor['available_days']);
                                foreach ($days as $day) {
                                    echo '<span class="badge bg-light text-dark me-1">' . htmlspecialchars($day) . '</span>';
                                }
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-info mb-2">
                            <i class="fas fa-chart-bar me-1"></i>Statistics Overview
                        </h6>
                        <p class="mb-1">
                            <i class="fas fa-calendar-check me-2 text-primary"></i>
                            <strong><?php echo $total_appointments; ?></strong> Total Appointments
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-users me-2 text-success"></i>
                            <strong><?php echo $total_patients; ?></strong> Patients Treated
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-calendar-plus me-2 text-info"></i>
                            <strong><?php echo count($upcoming_appointments); ?></strong> Upcoming Appointments
                        </p>
                    </div>
                </div>
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
                <i class="fas fa-users fa-2x mb-2"></i>
                <h4><?php echo $total_patients; ?></h4>
                <small>Patients Treated</small>
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
                                    <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                </div>
                                <small class="text-muted">ID: <?php echo htmlspecialchars($appointment['patient_id']); ?></small>
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
                                    <?php echo htmlspecialchars($record['patient_first_name'] . ' ' . $record['patient_last_name']); ?>
                                </div>
                                <small class="text-muted"><?php echo format_date($record['visit_date']); ?></small>
                            </div>
                            <div class="mb-1">
                                <small class="text-muted">Patient ID: <?php echo htmlspecialchars($record['patient_id']); ?></small>
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
    
    <!-- Recent Appointments History -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Appointment History
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_appointments)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-calendar fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No recent appointments</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo format_date($appointment['appointment_date']); ?></td>
                                        <td><?php echo format_time($appointment['appointment_time']); ?></td>
                                        <td>
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_id']); ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($appointment['reason']): ?>
                                                <?php echo htmlspecialchars(substr($appointment['reason'], 0, 50)); ?>
                                                <?php if (strlen($appointment['reason']) > 50) echo '...'; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No reason provided</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="button" class="btn btn-primary" onclick="editDoctor(<?php echo $doctor['id']; ?>); $('#doctorDetailsModal').modal('hide');">
        <i class="fas fa-edit me-1"></i>Edit Doctor
    </button>
</div>
<?php
require_once '../includes/config.php';

// Check if user is logged in and is a patient
if (!is_logged_in()) {
    redirect('login.php');
}

if (get_user_role() !== 'patient') {
    redirect('login.php');
}

// Get patient information
$db->query('
    SELECT p.*, u.username, u.email as user_email 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE u.id = :user_id
');
$db->bind(':user_id', $_SESSION['user_id']);
$patient = $db->single();

if (!$patient) {
    redirect('login.php');
}

// Get upcoming appointments
$db->query('
    SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = :patient_id AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
');
$db->bind(':patient_id', $patient['id']);
$upcoming_appointments = $db->resultSet();

// Get recent medical records
$db->query('
    SELECT mr.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.id
    WHERE mr.patient_id = :patient_id
    ORDER BY mr.visit_date DESC
    LIMIT 5
');
$db->bind(':patient_id', $patient['id']);
$medical_records = $db->resultSet();

$page_title = 'Patient Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-hospital-alt me-2"></i>
                <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="patient_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="alert('Feature coming soon!')">
                            <i class="fas fa-calendar-alt me-1"></i>Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="alert('Feature coming soon!')">
                            <i class="fas fa-file-medical me-1"></i>Medical Records
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="alert('Feature coming soon!')">
                            <i class="fas fa-prescription-bottle-alt me-1"></i>Prescriptions
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="alert('Feature coming soon!')">
                                <i class="fas fa-user-edit me-2"></i>Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Welcome Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h1 class="card-title">
                            <i class="fas fa-user-circle me-2 text-primary"></i>
                            Welcome, <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>!
                        </h1>
                        <p class="card-text text-muted">
                            Patient ID: <strong><?php echo htmlspecialchars($patient['patient_id']); ?></strong> | 
                            Email: <strong><?php echo htmlspecialchars($patient['user_email']); ?></strong>
                        </p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count($upcoming_appointments); ?></h4>
                                        <p class="mb-0">Upcoming Appointments</p>
                                    </div>
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count($medical_records); ?></h4>
                                        <p class="mb-0">Medical Records</p>
                                    </div>
                                    <i class="fas fa-file-medical-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $patient['blood_group'] ? htmlspecialchars($patient['blood_group']) : 'N/A'; ?></h4>
                                        <p class="mb-0">Blood Group</p>
                                    </div>
                                    <i class="fas fa-tint fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo date('Y') - date('Y', strtotime($patient['date_of_birth'])); ?></h4>
                                        <p class="mb-0">Age (Years)</p>
                                    </div>
                                    <i class="fas fa-birthday-cake fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Upcoming Appointments -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Upcoming Appointments
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_appointments)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No upcoming appointments scheduled.</p>
                                        <button class="btn btn-primary" onclick="alert('Appointment booking feature coming soon!')">
                                            <i class="fas fa-plus me-1"></i>Book Appointment
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($upcoming_appointments as $appointment): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">
                                                        Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo format_date($appointment['appointment_date']); ?>
                                                    </small>
                                                </div>
                                                <p class="mb-1">
                                                    <i class="fas fa-stethoscope me-1"></i>
                                                    <?php echo htmlspecialchars($appointment['specialization']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo format_time($appointment['appointment_time']); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Medical Records -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-medical me-2"></i>
                                    Recent Medical Records
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($medical_records)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-file-medical-alt fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No medical records found.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($medical_records as $record): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">
                                                        Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo format_date($record['visit_date']); ?>
                                                    </small>
                                                </div>
                                                <?php if ($record['diagnosis']): ?>
                                                    <p class="mb-1">
                                                        <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($record['treatment']): ?>
                                                    <small class="text-muted">
                                                        <strong>Treatment:</strong> <?php echo htmlspecialchars(substr($record['treatment'], 0, 100)); ?>
                                                        <?php if (strlen($record['treatment']) > 100) echo '...'; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-info me-2"></i>
                                    Personal Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['patient_id']); ?></p>
                                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                                        <p><strong>Date of Birth:</strong> <?php echo format_date($patient['date_of_birth']); ?></p>
                                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
                                        <p><strong>Blood Group:</strong> <?php echo $patient['blood_group'] ? htmlspecialchars($patient['blood_group']) : 'Not specified'; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['user_email']); ?></p>
                                        <p><strong>Address:</strong> <?php echo $patient['address'] ? htmlspecialchars($patient['address']) : 'Not provided'; ?></p>
                                        <p><strong>Emergency Contact:</strong> 
                                            <?php if ($patient['emergency_contact_name']): ?>
                                                <?php echo htmlspecialchars($patient['emergency_contact_name']); ?>
                                                <?php if ($patient['emergency_contact_phone']): ?>
                                                    (<?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>)
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Not provided
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($patient['allergies'] || $patient['medical_history']): ?>
                                    <hr>
                                    <div class="row">
                                        <?php if ($patient['allergies']): ?>
                                            <div class="col-md-6">
                                                <h6 class="text-danger">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Known Allergies
                                                </h6>
                                                <p class="text-muted"><?php echo htmlspecialchars($patient['allergies']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($patient['medical_history']): ?>
                                            <div class="col-md-6">
                                                <h6 class="text-info">
                                                    <i class="fas fa-notes-medical me-1"></i>
                                                    Medical History
                                                </h6>
                                                <p class="text-muted"><?php echo htmlspecialchars($patient['medical_history']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
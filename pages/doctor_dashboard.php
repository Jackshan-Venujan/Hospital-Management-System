<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'Doctor Dashboard';
$db = new Database();

// Get doctor information
try {
    $db->query("SELECT d.*, dept.name as department_name, u.email
                FROM doctors d 
                LEFT JOIN departments dept ON d.department_id = dept.id
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.user_id = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
    $doctor_info = $db->single();

    if (!$doctor_info) {
        $_SESSION['error'] = 'Doctor profile not found. Please contact administrator.';
        redirect('../login.php');
    }

    // Today's appointments
    $db->query("SELECT COUNT(*) as total FROM appointments 
                WHERE doctor_id = :doctor_id AND appointment_date = CURDATE()");
    $db->bind(':doctor_id', $doctor_info['id']);
    $today_appointments = $db->single()['total'];

    // Pending appointments  
    $db->query("SELECT COUNT(*) as total FROM appointments 
                WHERE doctor_id = :doctor_id AND status IN ('scheduled', 'confirmed')");
    $db->bind(':doctor_id', $doctor_info['id']);
    $pending_appointments = $db->single()['total'];

    // Total patients (unique)
    $db->query("SELECT COUNT(DISTINCT patient_id) as total FROM appointments 
                WHERE doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $total_patients = $db->single()['total'];

    // This month's appointments
    $db->query("SELECT COUNT(*) as total FROM appointments 
                WHERE doctor_id = :doctor_id 
                AND MONTH(appointment_date) = MONTH(CURDATE()) 
                AND YEAR(appointment_date) = YEAR(CURDATE())");
    $db->bind(':doctor_id', $doctor_info['id']);
    $monthly_appointments = $db->single()['total'];

    // Medical records count
    $db->query("SELECT COUNT(*) as total FROM medical_records WHERE doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $medical_records_count = $db->single()['total'];

    // Prescriptions count
    $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $prescriptions_count = $db->single()['total'];

    // Recent appointments
    $db->query("SELECT a.*, p.first_name, p.last_name, p.phone, p.patient_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = :doctor_id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
                LIMIT 5");
    $db->bind(':doctor_id', $doctor_info['id']);
    $recent_appointments = $db->resultSet();

    // Today's schedule
    $db->query("SELECT a.*, p.first_name, p.last_name, p.phone, p.patient_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = :doctor_id AND a.appointment_date = CURDATE()
                ORDER BY a.appointment_time ASC");
    $db->bind(':doctor_id', $doctor_info['id']);
    $today_schedule = $db->resultSet();

    // Upcoming appointments (next 7 days)
    $db->query("SELECT a.*, p.first_name, p.last_name, p.phone, p.patient_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = :doctor_id 
                AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND a.status IN ('scheduled', 'confirmed')
                ORDER BY a.appointment_date ASC, a.appointment_time ASC
                LIMIT 10");
    $db->bind(':doctor_id', $doctor_info['id']);
    $upcoming_appointments = $db->resultSet();

} catch (Exception $e) {
    $error_message = 'Error loading dashboard: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.doctor-dashboard {
    padding: 20px;
}

.welcome-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.doctor-avatar {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    margin-right: 20px;
}

.stats-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    border-left: 4px solid;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stats-card.today {
    border-left-color: #4facfe;
}

.stats-card.pending {
    border-left-color: #f093fb;
}

.stats-card.patients {
    border-left-color: #43e97b;
}

.stats-card.monthly {
    border-left-color: #f9ca24;
}

.stats-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 10px;
}

.stats-number.today { color: #4facfe; }
.stats-number.pending { color: #f093fb; }
.stats-number.patients { color: #43e97b; }
.stats-number.monthly { color: #f9ca24; }

.stats-label {
    color: #666;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.stats-description {
    color: #888;
    font-size: 0.85rem;
}

.section-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.section-card h5 {
    color: #333;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f8f9fa;
}

.appointment-item {
    padding: 15px;
    border: 1px solid #f1f1f1;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.appointment-item:hover {
    border-color: #667eea;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
}

.appointment-time {
    font-weight: bold;
    color: #667eea;
    font-size: 1.1rem;
}

.appointment-patient {
    font-weight: 600;
    margin-bottom: 5px;
}

.appointment-reason {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-scheduled { background: #e3f2fd; color: #1565c0; }
.status-confirmed { background: #e8f5e8; color: #2e7d32; }
.status-completed { background: #f3e5f5; color: #7b1fa2; }
.status-cancelled { background: #ffebee; color: #c62828; }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.action-card {
    background: white;
    border: 2px solid #f1f1f1;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.action-card:hover {
    color: #667eea;
    border-color: #667eea;
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.15);
}

.action-card i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #888;
}

.no-data i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .doctor-dashboard {
        padding: 15px;
    }
    
    .welcome-card {
        padding: 20px;
    }
    
    .doctor-avatar {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
        margin-right: 15px;
    }
    
    .stats-number {
        font-size: 2rem;
    }
}
</style>

<div class="doctor-dashboard">
    <!-- Welcome Section -->
    <div class="welcome-card">
        <div class="d-flex align-items-center">
            <div class="doctor-avatar">
                <?php echo strtoupper(substr($doctor_info['first_name'], 0, 1) . substr($doctor_info['last_name'], 0, 1)); ?>
            </div>
            <div>
                <h2 class="mb-2">Welcome back, Dr. <?php echo htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']); ?></h2>
                <p class="mb-1 opacity-75">
                    <i class="fas fa-stethoscope me-2"></i><?php echo htmlspecialchars($doctor_info['specialization']); ?>
                </p>
                <p class="mb-1 opacity-75">
                    <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($doctor_info['department_name'] ?? 'No Department'); ?>
                </p>
                <p class="mb-0 opacity-75">
                    <i class="fas fa-id-badge me-2"></i><?php echo htmlspecialchars($doctor_info['employee_id']); ?>
                </p>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stats-card today">
                <div class="stats-label">Today's Appointments</div>
                <div class="stats-number today"><?php echo $today_appointments; ?></div>
                <div class="stats-description">Scheduled for today</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stats-card pending">
                <div class="stats-label">Pending Appointments</div>
                <div class="stats-number pending"><?php echo $pending_appointments; ?></div>
                <div class="stats-description">Awaiting confirmation</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stats-card patients">
                <div class="stats-label">Total Patients</div>
                <div class="stats-number patients"><?php echo $total_patients; ?></div>
                <div class="stats-description">Under your care</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stats-card monthly">
                <div class="stats-label">This Month</div>
                <div class="stats-number monthly"><?php echo $monthly_appointments; ?></div>
                <div class="stats-description">Total appointments</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stats-card" style="border-left-color: #28a745;">
                <div class="stats-label">Medical Records</div>
                <div class="stats-number" style="color: #28a745;"><?php echo $medical_records_count; ?></div>
                <div class="stats-description">Records created</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stats-card" style="border-left-color: #dc3545;">
                <div class="stats-label">Prescriptions</div>
                <div class="stats-number" style="color: #dc3545;"><?php echo $prescriptions_count; ?></div>
                <div class="stats-description">Prescriptions issued</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-card">
        <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
        <div class="quick-actions">
            <a href="doctor_medical_records.php" class="action-card">
                <i class="fas fa-file-medical-alt"></i>
                <div>Medical Records</div>
            </a>
            <a href="doctor_prescriptions.php" class="action-card">
                <i class="fas fa-prescription-bottle-alt"></i>
                <div>Prescriptions</div>
            </a>
            <a href="doctor_appointments.php" class="action-card">
                <i class="fas fa-calendar-check"></i>
                <div>My Appointments</div>
            </a>
            <a href="doctor_patients.php" class="action-card">
                <i class="fas fa-users"></i>
                <div>My Patients</div>
            </a>
            <a href="doctor_profile.php" class="action-card">
                <i class="fas fa-user-circle"></i>
                <div>Profile</div>
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Today's Schedule -->
        <div class="col-lg-6">
            <div class="section-card">
                <h5><i class="fas fa-calendar-day me-2"></i>Today's Schedule</h5>
                <?php if (!empty($today_schedule)): ?>
                    <?php foreach ($today_schedule as $appointment): ?>
                        <div class="appointment-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="appointment-time">
                                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                </div>
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <div class="appointment-patient">
                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                            </div>
                            <div class="appointment-reason">
                                <i class="fas fa-notes-medical me-1"></i>
                                <?php echo htmlspecialchars($appointment['reason'] ?: 'General consultation'); ?>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['phone']); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <p>No appointments scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="col-lg-6">
            <div class="section-card">
                <h5><i class="fas fa-calendar-alt me-2"></i>Upcoming Appointments</h5>
                <?php if (!empty($upcoming_appointments)): ?>
                    <?php foreach ($upcoming_appointments as $appointment): ?>
                        <div class="appointment-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="appointment-time">
                                    <?php echo date('M j, g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?>
                                </div>
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <div class="appointment-patient">
                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                            </div>
                            <div class="appointment-reason">
                                <i class="fas fa-notes-medical me-1"></i>
                                <?php echo htmlspecialchars($appointment['reason'] ?: 'General consultation'); ?>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['phone']); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="doctor_appointments.php" class="btn btn-outline-primary">
                            <i class="fas fa-calendar me-1"></i>View All Appointments
                        </a>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-plus"></i>
                        <p>No upcoming appointments</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="section-card">
        <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
        <?php if (!empty($recent_appointments)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_appointments as $appointment): ?>
                        <tr>
                            <td>
                                <?php echo date('M j, Y g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                                <br><small class="text-muted"><?php echo $appointment['patient_id']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($appointment['reason'] ?: 'General consultation'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-clipboard-list"></i>
                <p>No recent appointments</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
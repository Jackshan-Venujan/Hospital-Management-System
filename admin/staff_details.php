<?php
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit();
}

// Check if staff ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid staff ID.</div>';
    exit();
}

$staff_id = intval($_GET['id']);

// Get staff details with department and statistics
$query = "SELECT u.*, d.name as department_name, d.id as department_id
          FROM users u 
          LEFT JOIN departments d ON u.department_id = d.id 
          WHERE u.id = ? AND u.role IN ('doctor', 'nurse', 'receptionist')";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $staff_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$staff = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$staff) {
    echo '<div class="alert alert-danger">Staff member not found.</div>';
    exit();
}

// Get appointment statistics if staff is a doctor
$appointment_stats = null;
if ($staff['role'] === 'doctor') {
    $stats_query = "SELECT 
                        COUNT(*) as total_appointments,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
                        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_appointments,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_appointments,
                        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_appointments,
                        COUNT(CASE WHEN appointment_date = CURDATE() THEN 1 END) as today_appointments
                    FROM appointments 
                    WHERE doctor_id = ?";
    
    $stmt = mysqli_prepare($conn, $stats_query);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $stats_result = mysqli_stmt_get_result($stmt);
    $appointment_stats = mysqli_fetch_assoc($stats_result);
    mysqli_stmt_close($stmt);
}

// Get recent appointments (for doctors) or recent activities
$recent_appointments = [];
if ($staff['role'] === 'doctor') {
    $recent_query = "SELECT a.*, 
                            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                            p.phone as patient_phone
                     FROM appointments a
                     JOIN users p ON a.patient_id = p.id
                     WHERE a.doctor_id = ?
                     ORDER BY a.appointment_date DESC, a.appointment_time DESC
                     LIMIT 5";
    
    $stmt = mysqli_prepare($conn, $recent_query);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $recent_result = mysqli_stmt_get_result($stmt);
    while ($appointment = mysqli_fetch_assoc($recent_result)) {
        $recent_appointments[] = $appointment;
    }
    mysqli_stmt_close($stmt);
}

// Calculate years of service
$years_of_service = 0;
if (!empty($staff['hire_date'])) {
    $hire_date = new DateTime($staff['hire_date']);
    $current_date = new DateTime();
    $interval = $hire_date->diff($current_date);
    $years_of_service = $interval->y;
}
?>

<div class="row">
    <!-- Staff Information -->
    <div class="col-md-8">
        <div class="card border-start border-primary border-4 shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-user me-2"></i>Personal Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name:</label>
                            <p class="text-dark mb-1"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Role:</label>
                            <p>
                                <?php
                                $role_class = $staff['role'] === 'doctor' ? 'primary' : 
                                             ($staff['role'] === 'nurse' ? 'success' : 'info');
                                $role_icon = $staff['role'] === 'doctor' ? 'user-md' : 
                                            ($staff['role'] === 'nurse' ? 'user-nurse' : 'user-tie');
                                ?>
                                <span class="badge bg-<?php echo $role_class; ?> fs-6">
                                    <i class="fas fa-<?php echo $role_icon; ?> me-1"></i>
                                    <?php echo ucfirst($staff['role']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Department:</label>
                            <p class="text-dark">
                                <?php if (!empty($staff['department_name'])): ?>
                                    <i class="fas fa-building text-primary me-1"></i>
                                    <?php echo htmlspecialchars($staff['department_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-minus me-1"></i>Not Assigned
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if (!empty($staff['specialization'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Specialization:</label>
                            <p class="text-dark">
                                <i class="fas fa-stethoscope text-info me-1"></i>
                                <?php echo htmlspecialchars($staff['specialization']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email:</label>
                            <p class="text-dark">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($staff['email']); ?>
                                </a>
                            </p>
                        </div>
                        
                        <?php if (!empty($staff['phone'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone:</label>
                            <p class="text-dark">
                                <i class="fas fa-phone text-success me-2"></i>
                                <?php echo htmlspecialchars($staff['phone']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status:</label>
                            <p>
                                <?php if (($staff['status'] ?? 'active') === 'active'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-pause-circle me-1"></i>Inactive
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Staff ID:</label>
                            <p class="text-dark">
                                <i class="fas fa-id-badge text-secondary me-2"></i>
                                <?php echo $staff['id']; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($staff['address'])): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Address:</label>
                        <p class="text-dark">
                            <i class="fas fa-map-marker-alt text-danger me-2"></i>
                            <?php echo nl2br(htmlspecialchars($staff['address'])); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($staff['emergency_contact'])): ?>
                <div class="row mt-2">
                    <div class="col-12">
                        <label class="form-label fw-bold">Emergency Contact:</label>
                        <p class="text-dark">
                            <i class="fas fa-phone-alt text-warning me-2"></i>
                            <?php echo htmlspecialchars($staff['emergency_contact']); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Employment Information -->
        <div class="card border-start border-info border-4 shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-info">
                    <i class="fas fa-briefcase me-2"></i>Employment Details
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Hire Date:</label>
                            <p class="text-dark">
                                <?php if (!empty($staff['hire_date'])): ?>
                                    <i class="fas fa-calendar text-info me-2"></i>
                                    <?php echo date('F j, Y', strtotime($staff['hire_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Not specified</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Years of Service:</label>
                            <p class="text-dark">
                                <i class="fas fa-clock text-primary me-2"></i>
                                <?php echo $years_of_service; ?> 
                                <?php echo $years_of_service == 1 ? 'year' : 'years'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if (!empty($staff['salary'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Salary:</label>
                            <p class="text-dark">
                                <i class="fas fa-dollar-sign text-success me-2"></i>
                                <strong class="text-success">
                                    <?php echo '$' . number_format($staff['salary'], 2); ?>
                                </strong>
                                <small class="text-muted">per year</small>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Joined:</label>
                            <p class="text-dark">
                                <i class="fas fa-user-plus text-secondary me-2"></i>
                                <?php echo $staff['created_at'] ? date('M j, Y', strtotime($staff['created_at'])) : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics and Quick Info -->
    <div class="col-md-4">
        <?php if ($staff['role'] === 'doctor' && $appointment_stats): ?>
        <!-- Doctor Statistics -->
        <div class="card border-start border-success border-4 shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-chart-bar me-2"></i>Appointment Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <div class="h4 mb-0 text-primary"><?php echo $appointment_stats['total_appointments']; ?></div>
                        <div class="small text-muted">Total</div>
                    </div>
                    <div class="col-6">
                        <div class="h4 mb-0 text-info"><?php echo $appointment_stats['today_appointments']; ?></div>
                        <div class="small text-muted">Today</div>
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="small">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-check-circle text-success me-1"></i>Completed</span>
                        <strong><?php echo $appointment_stats['completed_appointments']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-clock text-warning me-1"></i>Confirmed</span>
                        <strong><?php echo $appointment_stats['confirmed_appointments']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-hourglass-half text-info me-1"></i>Pending</span>
                        <strong><?php echo $appointment_stats['pending_appointments']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-times-circle text-danger me-1"></i>Cancelled</span>
                        <strong><?php echo $appointment_stats['cancelled_appointments']; ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="card border-start border-warning border-4 shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-warning">
                    <i class="fas fa-tools me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="editStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)">
                        <i class="fas fa-edit me-1"></i>Edit Details
                    </button>
                    
                    <button class="btn btn-outline-warning btn-sm" onclick="resetPassword(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>')">
                        <i class="fas fa-key me-1"></i>Reset Password
                    </button>
                    
                    <?php if ($staff['role'] === 'doctor'): ?>
                    <a href="doctor_schedule.php?doctor_id=<?php echo $staff['id']; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-calendar me-1"></i>View Schedule
                    </a>
                    <?php endif; ?>
                    
                    <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-envelope me-1"></i>Send Email
                    </a>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="card border-start border-secondary border-4 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-secondary">
                    <i class="fas fa-info-circle me-2"></i>System Information
                </h6>
            </div>
            <div class="card-body small">
                <div class="mb-2">
                    <strong>Last Login:</strong><br>
                    <span class="text-muted">
                        <?php echo isset($staff['last_login']) && $staff['last_login'] ? 
                            date('M j, Y g:i A', strtotime($staff['last_login'])) : 'Never'; ?>
                    </span>
                </div>
                
                <div class="mb-2">
                    <strong>Account Created:</strong><br>
                    <span class="text-muted">
                        <?php echo $staff['created_at'] ? 
                            date('M j, Y g:i A', strtotime($staff['created_at'])) : 'N/A'; ?>
                    </span>
                </div>
                
                <?php if (!empty($staff['updated_at'])): ?>
                <div>
                    <strong>Last Updated:</strong><br>
                    <span class="text-muted">
                        <?php echo date('M j, Y g:i A', strtotime($staff['updated_at'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Appointments (for doctors) -->
<?php if ($staff['role'] === 'doctor' && !empty($recent_appointments)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-start border-primary border-4 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-calendar-check me-2"></i>Recent Appointments
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Status</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-calendar text-primary me-1"></i>
                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-clock text-info me-1"></i>
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $appointment['status'];
                                        $badge_class = $status === 'completed' ? 'success' : 
                                                      ($status === 'confirmed' ? 'primary' : 
                                                       ($status === 'pending' ? 'warning' : 'danger'));
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($appointment['patient_phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($appointment['patient_phone']); ?>" 
                                               class="text-decoration-none">
                                                <i class="fas fa-phone text-success me-1"></i>
                                                <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
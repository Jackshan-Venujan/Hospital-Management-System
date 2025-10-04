<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

$page_title = 'Admin Dashboard';

// Get dashboard statistics
try {
    // Total patients
    $db->query('SELECT COUNT(*) as total FROM patients');
    $total_patients = $db->single()['total'];
    
    // Total doctors
    $db->query('SELECT COUNT(*) as total FROM doctors');
    $total_doctors = $db->single()['total'];
    
    // Today's appointments
    $db->query('SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE()');
    $today_appointments = $db->single()['total'];
    
    // Pending appointments
    $db->query('SELECT COUNT(*) as total FROM appointments WHERE status = :status');
    $db->bind(':status', 'scheduled');
    $pending_appointments = $db->single()['total'];
    
    // Revenue this month
    $db->query('SELECT SUM(paid_amount) as total FROM billing WHERE MONTH(billing_date) = MONTH(CURDATE()) AND YEAR(billing_date) = YEAR(CURDATE())');
    $monthly_revenue = $db->single()['total'] ?? 0;
    
    // Recent appointments
    $db->query('
        SELECT a.*, p.first_name as patient_name, p.last_name as patient_lastname, 
               d.first_name as doctor_name, d.last_name as doctor_lastname
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        JOIN doctors d ON a.doctor_id = d.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ');
    $recent_appointments = $db->resultSet();
    
    // Recent patients
    $db->query('
        SELECT * FROM patients 
        ORDER BY created_at DESC 
        LIMIT 5
    ');
    $recent_patients = $db->resultSet();
    
} catch (Exception $e) {
    $error = 'Error loading dashboard data: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="page-title">
    <h1><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="stats-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-number"><?php echo number_format($total_patients); ?></div>
            <div class="stats-label">Total Patients</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="stats-icon success">
                <i class="fas fa-user-md"></i>
            </div>
            <div class="stats-number"><?php echo number_format($total_doctors); ?></div>
            <div class="stats-label">Total Doctors</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="stats-icon info">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stats-number"><?php echo number_format($today_appointments); ?></div>
            <div class="stats-label">Today's Appointments</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="stats-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-number"><?php echo number_format($pending_appointments); ?></div>
            <div class="stats-label">Pending Appointments</div>
        </div>
    </div>
</div>

<!-- Revenue Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-1">Monthly Revenue</h5>
                        <h2 class="text-success mb-0">Rs. <?php echo number_format($monthly_revenue, 2); ?></h2>
                        <small class="text-muted">Total revenue for <?php echo date('F Y'); ?></small>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-chart-line fa-3x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="row">
    <!-- Recent Appointments -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Appointments</h5>
                <a href="appointments.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_appointments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($appointment['patient_name'] . ' ' . $appointment['patient_lastname']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['doctor_name'] . ' ' . $appointment['doctor_lastname']); ?></td>
                                        <td><?php echo format_date($appointment['appointment_date']); ?></td>
                                        <td><?php echo format_time($appointment['appointment_time']); ?></td>
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
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent appointments</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Patients -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Patients</h5>
                <a href="patients.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_patients)): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <div class="user-avatar bg-secondary">
                                    <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h6>
                                <small class="text-muted">ID: <?php echo htmlspecialchars($patient['patient_id']); ?></small>
                            </div>
                            <div>
                                <small class="text-muted"><?php echo format_date($patient['created_at']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent patients</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                        <a href="patients.php?action=add" class="btn btn-outline-primary w-100">
                            <i class="fas fa-user-plus fa-2x mb-2 d-block"></i>
                            Add Patient
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                        <a href="doctors.php?action=add" class="btn btn-outline-success w-100">
                            <i class="fas fa-user-md fa-2x mb-2 d-block"></i>
                            Add Doctor
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                        <a href="appointments.php?action=add" class="btn btn-outline-info w-100">
                            <i class="fas fa-calendar-plus fa-2x mb-2 d-block"></i>
                            Schedule Appointment
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                        <a href="reports.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>
                            View Reports
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                        <a href="billing.php" class="btn btn-outline-danger w-100">
                            <i class="fas fa-file-invoice-dollar fa-2x mb-2 d-block"></i>
                            Manage Billing
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                        <a href="settings.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-cogs fa-2x mb-2 d-block"></i>
                            System Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
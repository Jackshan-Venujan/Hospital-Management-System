<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$page_title = 'Appointment Reports';
$db = new Database();

// Date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$doctor_id = $_GET['doctor_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$appointment_status = $_GET['status'] ?? '';

try {
    // Get available doctors for filter
    $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, employee_id FROM doctors ORDER BY first_name, last_name");
    $doctors_list = $db->resultSet();

    // Get available departments for filter
    $db->query("SELECT id, name FROM departments ORDER BY name");
    $departments_list = $db->resultSet();

    // Build where conditions
    $where_conditions = ["a.appointment_date BETWEEN :start_date AND :end_date"];
    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if (!empty($doctor_id)) {
        $where_conditions[] = "a.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctor_id;
    }

    if (!empty($department_id)) {
        $where_conditions[] = "d.department_id = :department_id";
        $params[':department_id'] = $department_id;
    }

    if (!empty($appointment_status)) {
        $where_conditions[] = "a.status = :status";
        $params[':status'] = $appointment_status;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Appointment Summary Statistics
    $db->query("SELECT 
                    COUNT(*) as total_appointments,
                    COUNT(CASE WHEN a.status = 'scheduled' THEN 1 END) as scheduled,
                    COUNT(CASE WHEN a.status = 'confirmed' THEN 1 END) as confirmed,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN a.status = 'no-show' THEN 1 END) as no_show
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE $where_clause");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $appointment_summary = $db->single();

    // Daily Appointment Trends (last 30 days)
    $db->query("SELECT 
                    DATE(a.appointment_date) as date,
                    COUNT(*) as total_appointments,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN a.status = 'no-show' THEN 1 END) as no_show
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    AND a.appointment_date <= CURDATE()
                GROUP BY DATE(a.appointment_date)
                ORDER BY date DESC
                LIMIT 30");
    $daily_trends = $db->resultSet();

    // Doctor Performance Analysis
    $db->query("SELECT 
                    d.employee_id,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                    d.specialization,
                    dept.name as department_name,
                    COUNT(a.id) as total_appointments,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
                    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled_appointments,
                    COUNT(CASE WHEN a.status = 'no-show' THEN 1 END) as no_show_appointments,
                    ROUND((COUNT(CASE WHEN a.status = 'completed' THEN 1 END) * 100.0 / COUNT(a.id)), 1) as completion_rate,
                    ROUND(d.consultation_fee, 2) as consultation_fee
                FROM doctors d
                LEFT JOIN appointments a ON d.id = a.doctor_id AND $where_clause
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE d.id IS NOT NULL
                GROUP BY d.id, d.employee_id, d.first_name, d.last_name, d.specialization, dept.name, d.consultation_fee
                HAVING total_appointments > 0
                ORDER BY completion_rate DESC, total_appointments DESC");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $doctor_performance = $db->resultSet();

    // Department-wise Appointment Distribution
    $db->query("SELECT 
                    dept.name as department_name,
                    COUNT(a.id) as total_appointments,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN a.status = 'scheduled' THEN 1 END) as scheduled,
                    COUNT(DISTINCT d.id) as doctors_count,
                    ROUND(AVG(d.consultation_fee), 2) as avg_consultation_fee
                FROM departments dept
                LEFT JOIN doctors d ON dept.id = d.department_id
                LEFT JOIN appointments a ON d.id = a.doctor_id AND $where_clause
                GROUP BY dept.id, dept.name
                HAVING total_appointments > 0
                ORDER BY total_appointments DESC");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $department_stats = $db->resultSet();

    // Time Slot Analysis
    $db->query("SELECT 
                    CASE 
                        WHEN HOUR(a.appointment_time) BETWEEN 8 AND 11 THEN 'Morning (8-12)'
                        WHEN HOUR(a.appointment_time) BETWEEN 12 AND 15 THEN 'Afternoon (12-16)'
                        WHEN HOUR(a.appointment_time) BETWEEN 16 AND 19 THEN 'Evening (16-20)'
                        ELSE 'Other Hours'
                    END as time_slot,
                    COUNT(*) as appointment_count,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_count,
                    ROUND((COUNT(CASE WHEN a.status = 'completed' THEN 1 END) * 100.0 / COUNT(*)), 1) as completion_rate
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE $where_clause
                GROUP BY time_slot
                ORDER BY appointment_count DESC");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $time_slot_analysis = $db->resultSet();

    // Most Common Appointment Reasons
    $db->query("SELECT 
                    a.reason,
                    COUNT(*) as frequency,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_count,
                    ROUND((COUNT(CASE WHEN a.status = 'completed' THEN 1 END) * 100.0 / COUNT(*)), 1) as completion_rate
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE $where_clause AND a.reason IS NOT NULL AND a.reason != ''
                GROUP BY a.reason
                ORDER BY frequency DESC
                LIMIT 10");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $appointment_reasons = $db->resultSet();

    // Recent Appointments (detailed view)
    $db->query("SELECT 
                    a.appointment_number,
                    a.appointment_date,
                    a.appointment_time,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.patient_id,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                    d.specialization,
                    dept.name as department_name,
                    a.reason,
                    a.status,
                    a.notes
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE $where_clause
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
                LIMIT 50");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $recent_appointments = $db->resultSet();

} catch (Exception $e) {
    $error_message = 'Error fetching appointment report data: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.appointment-reports-container {
    padding: 20px;
}

.report-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.report-card h5 {
    color: #333;
    font-weight: 600;
    margin-bottom: 20px;
}

.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 15px;
}

.stat-box.scheduled {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-box.confirmed {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-box.completed {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-box.cancelled {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-box.no-show {
    background: linear-gradient(135deg, #fad0c4 0%, #fad0c4 100%);
    color: #333;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.performance-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    transition: box-shadow 0.3s ease;
}

.performance-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.completion-bar {
    background: #f8f9fa;
    border-radius: 20px;
    height: 8px;
    overflow: hidden;
    margin: 10px 0;
}

.completion-fill {
    height: 100%;
    border-radius: 20px;
    transition: width 0.3s ease;
}

.completion-excellent {
    background: linear-gradient(90deg, #11998e, #38ef7d);
}

.completion-good {
    background: linear-gradient(90deg, #4facfe, #00f2fe);
}

.completion-fair {
    background: linear-gradient(90deg, #f093fb, #f5576c);
}

.filters-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.time-slot-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.reason-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.reason-item:last-child {
    border-bottom: none;
}

.status-badge {
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.status-scheduled { background: #e3f2fd; color: #1565c0; }
.status-confirmed { background: #e8f5e8; color: #2e7d32; }
.status-completed { background: #e8f5e8; color: #1b5e20; }
.status-cancelled { background: #ffebee; color: #c62828; }
.status-no-show { background: #fff3e0; color: #ef6c00; }

@media (max-width: 768px) {
    .appointment-reports-container {
        padding: 10px;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
}
</style>

<div class="appointment-reports-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-calendar-alt me-2"></i>Appointment Reports</h1>
            <p class="text-muted mb-0">Comprehensive appointment analytics and scheduling insights</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Reports
            </a>
            <button class="btn btn-success" onclick="exportAppointmentReport()">
                <i class="fas fa-download me-1"></i>Export Report
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Report Filters</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Doctor</label>
                <select class="form-select" name="doctor_id">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctors_list as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_id == $doctor['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doctor['name'] . ' (' . $doctor['employee_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Department</label>
                <select class="form-select" name="department_id">
                    <option value="">All Departments</option>
                    <?php foreach ($departments_list as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="scheduled" <?php echo $appointment_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="confirmed" <?php echo $appointment_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $appointment_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $appointment_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="no-show" <?php echo $appointment_status === 'no-show' ? 'selected' : ''; ?>>No Show</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
            </div>
        </form>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($appointment_summary['total_appointments']); ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box scheduled">
                <div class="stat-number"><?php echo number_format($appointment_summary['scheduled']); ?></div>
                <div class="stat-label">Scheduled</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box confirmed">
                <div class="stat-number"><?php echo number_format($appointment_summary['confirmed']); ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box completed">
                <div class="stat-number"><?php echo number_format($appointment_summary['completed']); ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box cancelled">
                <div class="stat-number"><?php echo number_format($appointment_summary['cancelled']); ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box no-show">
                <div class="stat-number"><?php echo number_format($appointment_summary['no_show']); ?></div>
                <div class="stat-label">No Shows</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Department Statistics -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-building me-2"></i>Department Performance</h5>
                <?php foreach ($department_stats as $dept): ?>
                    <div class="performance-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($dept['department_name']); ?></h6>
                                <small class="text-muted"><?php echo $dept['doctors_count']; ?> doctors</small>
                            </div>
                            <div class="text-end">
                                <strong><?php echo number_format($dept['total_appointments']); ?></strong> appointments
                                <br><small class="text-muted">Rs. <?php echo number_format($dept['avg_consultation_fee'], 2); ?> avg fee</small>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-3">
                                <small class="text-success"><?php echo $dept['completed']; ?><br>Completed</small>
                            </div>
                            <div class="col-3">
                                <small class="text-primary"><?php echo $dept['scheduled']; ?><br>Scheduled</small>
                            </div>
                            <div class="col-3">
                                <small class="text-danger"><?php echo $dept['cancelled']; ?><br>Cancelled</small>
                            </div>
                            <div class="col-3">
                                <?php 
                                $completion_rate = $dept['total_appointments'] > 0 ? 
                                    round(($dept['completed'] / $dept['total_appointments']) * 100, 1) : 0;
                                ?>
                                <small class="text-info"><?php echo $completion_rate; ?>%<br>Success Rate</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Time Slot Analysis -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-clock me-2"></i>Peak Hours Analysis</h5>
                <?php foreach ($time_slot_analysis as $slot): ?>
                    <div class="time-slot-item">
                        <div>
                            <strong><?php echo $slot['time_slot']; ?></strong>
                            <br><small class="text-muted"><?php echo $slot['completion_rate']; ?>% completion rate</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary"><?php echo number_format($slot['appointment_count']); ?> appointments</span>
                            <br><small class="text-success"><?php echo $slot['completed_count']; ?> completed</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Doctor Performance Analysis -->
    <div class="report-card">
        <h5><i class="fas fa-user-md me-2"></i>Doctor Performance Analysis</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Department</th>
                        <th>Specialization</th>
                        <th>Total</th>
                        <th>Completed</th>
                        <th>Cancelled</th>
                        <th>No Shows</th>
                        <th>Success Rate</th>
                        <th>Fee (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctor_performance as $doctor): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                            <br><small class="text-muted"><?php echo $doctor['employee_id']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($doctor['department_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                        <td><span class="badge bg-primary"><?php echo $doctor['total_appointments']; ?></span></td>
                        <td><span class="badge bg-success"><?php echo $doctor['completed_appointments']; ?></span></td>
                        <td><span class="badge bg-danger"><?php echo $doctor['cancelled_appointments']; ?></span></td>
                        <td><span class="badge bg-warning"><?php echo $doctor['no_show_appointments']; ?></span></td>
                        <td>
                            <div class="completion-bar">
                                <?php 
                                $rate = $doctor['completion_rate'];
                                $class = $rate >= 80 ? 'completion-excellent' : ($rate >= 60 ? 'completion-good' : 'completion-fair');
                                ?>
                                <div class="completion-fill <?php echo $class; ?>" style="width: <?php echo $rate; ?>%"></div>
                            </div>
                            <small><?php echo $rate; ?>%</small>
                        </td>
                        <td><?php echo number_format($doctor['consultation_fee'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <!-- Common Reasons -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-notes-medical me-2"></i>Common Appointment Reasons</h5>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($appointment_reasons as $reason): ?>
                    <div class="reason-item">
                        <div>
                            <strong><?php echo htmlspecialchars($reason['reason']); ?></strong>
                            <br><small class="text-muted"><?php echo $reason['completion_rate']; ?>% completion rate</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary"><?php echo $reason['frequency']; ?></span>
                            <br><small class="text-success"><?php echo $reason['completed_count']; ?> completed</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Daily Trends -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-chart-line me-2"></i>Daily Appointment Trends</h5>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th>Cancelled</th>
                                <th>No Shows</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_trends as $trend): ?>
                            <tr>
                                <td><strong><?php echo date('M j', strtotime($trend['date'])); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo $trend['total_appointments']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $trend['completed']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $trend['cancelled']; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $trend['no_show']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Appointments -->
    <div class="report-card">
        <h5><i class="fas fa-list me-2"></i>Recent Appointments</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Appointment #</th>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_appointments as $apt): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($apt['appointment_number']); ?></strong></td>
                        <td>
                            <?php echo date('M j, Y', strtotime($apt['appointment_date'])); ?>
                            <br><small class="text-muted"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                            <br><small class="text-muted"><?php echo $apt['patient_id']; ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($apt['doctor_name']); ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($apt['specialization']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($apt['department_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($apt['reason'] ?? 'N/A'); ?></td>
                        <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                        <td><small><?php echo htmlspecialchars($apt['notes'] ?? 'N/A'); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($recent_appointments)): ?>
        <div class="text-center text-muted py-4">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p>No appointments found for the selected criteria.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportAppointmentReport() {
    // Get current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'true');
    
    // Open export URL
    window.open(`export_appointment_report.php?${params.toString()}`, '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>
<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$page_title = 'Reports & Analytics';
$db = new Database();

// Date range for reports (default to current month)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

try {
    // Overall Statistics
    $db->query("SELECT COUNT(*) as total FROM patients WHERE id IS NOT NULL");
    $total_patients = $db->single()['total'];

    $db->query("SELECT COUNT(*) as total FROM doctors WHERE id IS NOT NULL");
    $total_doctors = $db->single()['total'];

    $db->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date BETWEEN :start AND :end");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $period_appointments = $db->single()['total'];

    $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM billing WHERE billing_date BETWEEN :start AND :end");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $period_revenue = $db->single()['total'];

    // Quick Stats for Cards
    $db->query("SELECT COUNT(*) as today FROM appointments WHERE DATE(appointment_date) = CURDATE()");
    $today_appointments = $db->single()['today'];

    $db->query("SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending'");
    $pending_appointments = $db->single()['pending'];

    $db->query("SELECT COUNT(*) as overdue FROM billing WHERE payment_status IN ('pending', 'partial') AND due_date < CURDATE()");
    $overdue_invoices = $db->single()['overdue'];

    // Department Statistics
    $db->query("SELECT d.name, COUNT(DISTINCT dr.id) as doctor_count, 
                COUNT(DISTINCT a.id) as appointments_count,
                COUNT(DISTINCT p.id) as patients_count
                FROM departments d
                LEFT JOIN doctors dr ON d.id = dr.department_id
                LEFT JOIN appointments a ON dr.id = a.doctor_id AND a.appointment_date BETWEEN :start AND :end
                LEFT JOIN patients p ON a.patient_id = p.id
                GROUP BY d.id, d.name
                ORDER BY appointments_count DESC");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $department_stats = $db->resultSet();

    // Monthly Revenue Trend (last 6 months)
    $db->query("SELECT 
                    DATE_FORMAT(billing_date, '%Y-%m') as month,
                    COUNT(*) as invoice_count,
                    SUM(total_amount) as total_revenue,
                    SUM(paid_amount) as paid_revenue
                FROM billing 
                WHERE billing_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(billing_date, '%Y-%m')
                ORDER BY month ASC");
    $revenue_trend = $db->resultSet();

    // Appointment Status Distribution
    $db->query("SELECT status, COUNT(*) as count 
                FROM appointments 
                WHERE appointment_date BETWEEN :start AND :end
                GROUP BY status
                ORDER BY count DESC");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $appointment_status = $db->resultSet();

    // Top Performing Doctors
    $db->query("SELECT 
                    d.first_name, d.last_name, d.employee_id,
                    COUNT(a.id) as total_appointments,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
                    d.consultation_fee as avg_fee
                FROM doctors d
                LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN :start AND :end
                WHERE d.id IS NOT NULL
                GROUP BY d.id, d.first_name, d.last_name, d.employee_id, d.consultation_fee
                HAVING total_appointments > 0
                ORDER BY completed_appointments DESC, total_appointments DESC
                LIMIT 10");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $top_doctors = $db->resultSet();

} catch (Exception $e) {
    $error_message = 'Error fetching report data: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.reports-container {
    padding: 20px;
}

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 25px;
    color: white;
    margin-bottom: 20px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.stats-card.revenue {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stats-card.appointments {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stats-card.patients {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stats-card.doctors {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stats-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stats-label {
    font-size: 1rem;
    opacity: 0.9;
}

.report-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.report-section h4 {
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}

.chart-container {
    position: relative;
    height: 300px;
    margin: 20px 0;
}

.report-filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.quick-reports {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.quick-report-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.quick-report-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    text-decoration: none;
    color: inherit;
}

.quick-report-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
}

.icon-patients { color: #e74c3c; }
.icon-appointments { color: #3498db; }
.icon-financial { color: #27ae60; }
.icon-staff { color: #f39c12; }
.icon-doctors { color: #9b59b6; }
.icon-analytics { color: #34495e; }

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

.badge-status {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

@media (max-width: 768px) {
    .reports-container {
        padding: 10px;
    }
    
    .stats-number {
        font-size: 2rem;
    }
    
    .quick-reports {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
}
</style>

<div class="reports-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1>
            <p class="text-muted mb-0">Comprehensive hospital management reports and insights</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print Reports
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customReportModal">
                <i class="fas fa-plus me-1"></i>Custom Report
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="report-filters">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" 
                       value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" 
                       value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="reports.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            </div>
            <div class="col-md-2">
                <select class="form-select" onchange="applyQuickFilter(this.value)">
                    <option value="">Quick Filters</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="quarter">This Quarter</option>
                    <option value="year">This Year</option>
                </select>
            </div>
        </form>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Key Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card patients">
                <div class="stats-number"><?php echo number_format($total_patients); ?></div>
                <div class="stats-label">Total Active Patients</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card doctors">
                <div class="stats-number"><?php echo number_format($total_doctors); ?></div>
                <div class="stats-label">Active Doctors</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card appointments">
                <div class="stats-number"><?php echo number_format($period_appointments); ?></div>
                <div class="stats-label">Appointments (Period)</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card revenue">
                <div class="stats-number">Rs. <?php echo number_format($period_revenue, 0); ?></div>
                <div class="stats-label">Revenue (Period)</div>
            </div>
        </div>
    </div>

    <!-- Quick Action Cards -->
    <div class="quick-reports">
        <a href="patient_reports.php" class="quick-report-card">
            <div class="quick-report-icon icon-patients">
                <i class="fas fa-users"></i>
            </div>
            <h5>Patient Reports</h5>
            <p class="text-muted mb-0">Demographics, registration trends, medical records</p>
        </a>
        
        <a href="appointment_reports.php" class="quick-report-card">
            <div class="quick-report-icon icon-appointments">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h5>Appointment Reports</h5>
            <p class="text-muted mb-0">Scheduling, status, department analysis</p>
        </a>
        
        <a href="financial_reports.php" class="quick-report-card">
            <div class="quick-report-icon icon-financial">
                <i class="fas fa-chart-line"></i>
            </div>
            <h5>Financial Reports</h5>
            <p class="text-muted mb-0">Revenue, billing, payments, outstanding</p>
        </a>
        
        <a href="staff_reports.php" class="quick-report-card">
            <div class="quick-report-icon icon-staff">
                <i class="fas fa-user-tie"></i>
            </div>
            <h5>Staff Reports</h5>
            <p class="text-muted mb-0">Performance, attendance, department stats</p>
        </a>
        
        <a href="doctor_reports.php" class="quick-report-card">
            <div class="quick-report-icon icon-doctors">
                <i class="fas fa-user-md"></i>
            </div>
            <h5>Doctor Reports</h5>
            <p class="text-muted mb-0">Consultations, performance, specialization</p>
        </a>
        
        <a href="analytics_dashboard.php" class="quick-report-card">
            <div class="quick-report-icon icon-analytics">
                <i class="fas fa-analytics"></i>
            </div>
            <h5>Advanced Analytics</h5>
            <p class="text-muted mb-0">Trends, forecasting, detailed insights</p>
        </a>
    </div>

    <!-- Alert Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h5><?php echo number_format($today_appointments); ?></h5>
                    <small class="text-muted">Today's Appointments</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-times fa-2x text-info mb-2"></i>
                    <h5><?php echo number_format($pending_appointments); ?></h5>
                    <small class="text-muted">Pending Appointments</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h5><?php echo number_format($overdue_invoices); ?></h5>
                    <small class="text-muted">Overdue Invoices</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Department Performance -->
        <div class="col-lg-6">
            <div class="report-section">
                <h4><i class="fas fa-building me-2"></i>Department Performance</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Doctors</th>
                                <th>Appointments</th>
                                <th>Patients</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($department_stats as $dept): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo $dept['doctor_count']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $dept['appointments_count']; ?></span></td>
                                <td><span class="badge bg-info"><?php echo $dept['patients_count']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Performing Doctors -->
        <div class="col-lg-6">
            <div class="report-section">
                <h4><i class="fas fa-star me-2"></i>Top Performing Doctors</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th>Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_doctors as $doctor): ?>
                            <?php $success_rate = $doctor['total_appointments'] > 0 ? ($doctor['completed_appointments'] / $doctor['total_appointments'] * 100) : 0; ?>
                            <tr>
                                <td>
                                    <strong>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($doctor['employee_id']); ?></small>
                                </td>
                                <td><span class="badge bg-primary"><?php echo $doctor['total_appointments']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $doctor['completed_appointments']; ?></span></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" style="width: <?php echo $success_rate; ?>%">
                                            <?php echo number_format($success_rate, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointment Status Distribution -->
    <div class="row">
        <div class="col-lg-6">
            <div class="report-section">
                <h4><i class="fas fa-chart-pie me-2"></i>Appointment Status Distribution</h4>
                <div class="row">
                    <?php foreach ($appointment_status as $status): ?>
                    <?php
                    $percentage = $period_appointments > 0 ? ($status['count'] / $period_appointments * 100) : 0;
                    $badge_class = [
                        'completed' => 'success',
                        'confirmed' => 'primary',
                        'pending' => 'warning',
                        'cancelled' => 'danger'
                    ][$status['status']] ?? 'secondary';
                    ?>
                    <div class="col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($status['status']); ?></span>
                                    <span><?php echo $status['count']; ?></span>
                                </div>
                                <div class="progress mt-1" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $badge_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Revenue Trend -->
        <div class="col-lg-6">
            <div class="report-section">
                <h4><i class="fas fa-trending-up me-2"></i>Revenue Trend (6 Months)</h4>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Invoices</th>
                                <th>Revenue</th>
                                <th>Collected</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revenue_trend as $month): ?>
                            <tr>
                                <td><strong><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></strong></td>
                                <td><?php echo number_format($month['invoice_count']); ?></td>
                                <td>Rs. <?php echo number_format($month['total_revenue'], 0); ?></td>
                                <td>Rs. <?php echo number_format($month['paid_revenue'], 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Report Modal -->
<div class="modal fade" id="customReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Custom Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customReportForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <option value="patients">Patient Analysis</option>
                                <option value="appointments">Appointment Summary</option>
                                <option value="financial">Financial Overview</option>
                                <option value="staff">Staff Performance</option>
                                <option value="doctors">Doctor Analysis</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Range</label>
                            <select class="form-select" name="date_range" required>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="quarter">This Quarter</option>
                                <option value="year">This Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                    </div>
                    <div class="row" id="customDateRange" style="display: none;">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="custom_start">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="custom_end">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <div class="d-flex gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format" value="pdf" id="pdf" checked>
                                <label class="form-check-label" for="pdf">PDF</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format" value="excel" id="excel">
                                <label class="form-check-label" for="excel">Excel</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format" value="csv" id="csv">
                                <label class="form-check-label" for="csv">CSV</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="generateCustomReport()">
                    <i class="fas fa-download me-1"></i>Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Quick filter functionality
function applyQuickFilter(period) {
    if (!period) return;
    
    const today = new Date();
    let startDate, endDate;
    
    switch(period) {
        case 'today':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
        case 'week':
            const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
            startDate = weekStart.toISOString().split('T')[0];
            endDate = new Date().toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarter = Math.floor((today.getMonth() / 3));
            startDate = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), quarter * 3 + 3, 0).toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
    document.querySelector('form').submit();
}

// Custom report modal functionality
document.querySelector('[name="date_range"]').addEventListener('change', function() {
    const customRange = document.getElementById('customDateRange');
    if (this.value === 'custom') {
        customRange.style.display = 'block';
    } else {
        customRange.style.display = 'none';
    }
});

function generateCustomReport() {
    const form = document.getElementById('customReportForm');
    const formData = new FormData(form);
    
    // Build URL for report generation
    const params = new URLSearchParams();
    for (let [key, value] of formData.entries()) {
        params.append(key, value);
    }
    
    // Open report in new window
    const reportUrl = `generate_report.php?${params.toString()}`;
    window.open(reportUrl, '_blank');
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('customReportModal')).hide();
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
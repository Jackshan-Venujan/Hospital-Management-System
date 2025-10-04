<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$page_title = 'Financial Reports';
$db = new Database();

// Date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$payment_status = $_GET['payment_status'] ?? '';
$department_id = $_GET['department_id'] ?? '';

try {
    // Get available departments for filter
    $db->query("SELECT id, name FROM departments ORDER BY name");
    $departments_list = $db->resultSet();

    // Build where conditions for financial data
    $where_conditions = ["b.billing_date BETWEEN :start_date AND :end_date"];
    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if (!empty($payment_status)) {
        $where_conditions[] = "b.payment_status = :payment_status";
        $params[':payment_status'] = $payment_status;
    }

    if (!empty($department_id)) {
        $where_conditions[] = "dept.id = :department_id";
        $params[':department_id'] = $department_id;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Financial Summary Statistics
    $db->query("SELECT 
                    COUNT(*) as total_bills,
                    SUM(b.total_amount) as total_billed,
                    SUM(b.paid_amount) as total_paid,
                    SUM(b.balance_amount) as total_outstanding,
                    COUNT(CASE WHEN b.payment_status = 'paid' THEN 1 END) as paid_bills,
                    COUNT(CASE WHEN b.payment_status = 'pending' THEN 1 END) as pending_bills,
                    COUNT(CASE WHEN b.payment_status = 'partial' THEN 1 END) as partial_bills,
                    COUNT(CASE WHEN b.due_date < CURDATE() AND b.payment_status != 'paid' THEN 1 END) as overdue_bills,
                    AVG(b.total_amount) as avg_bill_amount
                FROM billing b
                LEFT JOIN appointments a ON b.appointment_id = a.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE $where_clause");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $financial_summary = $db->single();

    // Monthly Revenue Trends (Last 12 months)
    $db->query("SELECT 
                    DATE_FORMAT(billing_date, '%Y-%m') as month,
                    COUNT(*) as bill_count,
                    SUM(total_amount) as total_revenue,
                    SUM(paid_amount) as collected_revenue,
                    SUM(balance_amount) as outstanding_amount,
                    ROUND((SUM(paid_amount) / SUM(total_amount)) * 100, 1) as collection_rate
                FROM billing 
                WHERE billing_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(billing_date, '%Y-%m')
                ORDER BY month ASC");
    $revenue_trends = $db->resultSet();

    // Department-wise Revenue Analysis
    $db->query("SELECT 
                    dept.name as department_name,
                    COUNT(b.id) as total_bills,
                    SUM(b.total_amount) as total_revenue,
                    SUM(b.paid_amount) as collected_revenue,
                    SUM(b.balance_amount) as outstanding_amount,
                    ROUND((SUM(b.paid_amount) / SUM(b.total_amount)) * 100, 1) as collection_rate,
                    COUNT(DISTINCT d.id) as doctors_count,
                    AVG(d.consultation_fee) as avg_consultation_fee
                FROM departments dept
                LEFT JOIN doctors d ON dept.id = d.department_id
                LEFT JOIN appointments a ON d.id = a.doctor_id
                LEFT JOIN billing b ON a.id = b.appointment_id AND $where_clause
                GROUP BY dept.id, dept.name
                HAVING total_bills > 0
                ORDER BY total_revenue DESC");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $department_revenue = $db->resultSet();

    // Payment Status Distribution
    $db->query("SELECT 
                    b.payment_status,
                    COUNT(*) as count,
                    SUM(b.total_amount) as total_amount,
                    SUM(b.paid_amount) as paid_amount,
                    SUM(b.balance_amount) as balance_amount,
                    ROUND(AVG(b.total_amount), 2) as avg_amount
                FROM billing b
                LEFT JOIN appointments a ON b.appointment_id = a.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE $where_clause
                GROUP BY b.payment_status
                ORDER BY total_amount DESC");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $payment_distribution = $db->resultSet();

    // Top Revenue Generating Services (Billing Items)
    $db->query("SELECT 
                    bi.description,
                    SUM(bi.quantity) as total_quantity,
                    SUM(bi.total_price) as total_revenue,
                    COUNT(DISTINCT bi.billing_id) as bill_count,
                    AVG(bi.unit_price) as avg_unit_price,
                    AVG(bi.quantity) as avg_quantity
                FROM billing_items bi
                JOIN billing b ON bi.billing_id = b.id
                LEFT JOIN appointments a ON b.appointment_id = a.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE $where_clause
                GROUP BY bi.description
                ORDER BY total_revenue DESC
                LIMIT 15");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $top_services = $db->resultSet();

    // Outstanding Invoices Analysis
    $db->query("SELECT 
                    b.bill_number,
                    b.billing_date,
                    b.due_date,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.patient_id,
                    p.phone as patient_phone,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                    dept.name as department_name,
                    b.total_amount,
                    b.paid_amount,
                    b.balance_amount,
                    b.payment_status,
                    DATEDIFF(CURDATE(), b.due_date) as days_overdue
                FROM billing b
                JOIN patients p ON b.patient_id = p.id
                LEFT JOIN appointments a ON b.appointment_id = a.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE b.payment_status IN ('pending', 'partial') 
                    AND b.billing_date BETWEEN :start_date AND :end_date
                ORDER BY b.balance_amount DESC, b.due_date ASC
                LIMIT 50");

    $db->bind(':start_date', $start_date);
    $db->bind(':end_date', $end_date);
    $outstanding_invoices = $db->resultSet();

    // Daily Collections (Last 30 days)
    $db->query("SELECT 
                    DATE(b.updated_at) as collection_date,
                    COUNT(*) as transactions,
                    SUM(b.paid_amount) as daily_collection
                FROM billing b
                WHERE b.updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    AND b.paid_amount > 0
                GROUP BY DATE(b.updated_at)
                ORDER BY collection_date DESC
                LIMIT 30");
    $daily_collections = $db->resultSet();

    // Doctor Revenue Performance
    $db->query("SELECT 
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                    d.employee_id,
                    d.specialization,
                    dept.name as department_name,
                    COUNT(b.id) as total_bills,
                    SUM(b.total_amount) as total_revenue,
                    SUM(b.paid_amount) as collected_revenue,
                    COUNT(CASE WHEN b.payment_status = 'paid' THEN 1 END) as paid_bills,
                    ROUND((SUM(b.paid_amount) / SUM(b.total_amount)) * 100, 1) as collection_rate,
                    d.consultation_fee
                FROM doctors d
                LEFT JOIN appointments a ON d.id = a.doctor_id
                LEFT JOIN billing b ON a.id = b.appointment_id AND $where_clause
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE d.id IS NOT NULL
                GROUP BY d.id, d.first_name, d.last_name, d.employee_id, d.specialization, dept.name, d.consultation_fee
                HAVING total_bills > 0
                ORDER BY total_revenue DESC
                LIMIT 20");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $doctor_revenue = $db->resultSet();

} catch (Exception $e) {
    $error_message = 'Error fetching financial report data: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.financial-reports-container {
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

.stat-box.revenue {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-box.collected {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-box.outstanding {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-box.overdue {
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
    color: #333;
}

.stat-box.avg-bill {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #333;
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

.revenue-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    background: #f8f9fa;
    transition: box-shadow 0.3s ease;
}

.revenue-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.collection-bar {
    background: #f8f9fa;
    border-radius: 20px;
    height: 12px;
    overflow: hidden;
    margin: 10px 0;
}

.collection-fill {
    height: 100%;
    border-radius: 20px;
    transition: width 0.3s ease;
}

.collection-excellent {
    background: linear-gradient(90deg, #11998e, #38ef7d);
}

.collection-good {
    background: linear-gradient(90deg, #4facfe, #00f2fe);
}

.collection-poor {
    background: linear-gradient(90deg, #f093fb, #f5576c);
}

.filters-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.service-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    background: white;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.payment-status-card {
    text-align: center;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.status-paid {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
}

.status-pending {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    color: #856404;
}

.status-partial {
    background: linear-gradient(135deg, #d1ecf1, #bee5eb);
    color: #0c5460;
}

.outstanding-item {
    border-left: 4px solid #dc3545;
    padding: 15px;
    margin-bottom: 10px;
    background: #fff5f5;
    border-radius: 0 8px 8px 0;
}

.overdue {
    border-left-color: #ff6b35;
    background: #fff2f0;
}

@media (max-width: 768px) {
    .financial-reports-container {
        padding: 10px;
    }
    
    .stat-number {
        font-size: 1.4rem;
    }
}
</style>

<div class="financial-reports-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-chart-line me-2"></i>Financial Reports</h1>
            <p class="text-muted mb-0">Comprehensive revenue analysis and billing insights</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Reports
            </a>
            <button class="btn btn-success" onclick="exportFinancialReport()">
                <i class="fas fa-download me-1"></i>Export Report
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Report Filters</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Payment Status</label>
                <select class="form-select" name="payment_status">
                    <option value="">All Statuses</option>
                    <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="partial" <?php echo $payment_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
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
            <div class="stat-box revenue">
                <div class="stat-number">Rs. <?php echo number_format($financial_summary['total_billed'], 0); ?></div>
                <div class="stat-label">Total Billed</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box collected">
                <div class="stat-number">Rs. <?php echo number_format($financial_summary['total_paid'], 0); ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box outstanding">
                <div class="stat-number">Rs. <?php echo number_format($financial_summary['total_outstanding'], 0); ?></div>
                <div class="stat-label">Outstanding</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box overdue">
                <div class="stat-number"><?php echo number_format($financial_summary['overdue_bills']); ?></div>
                <div class="stat-label">Overdue Bills</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box avg-bill">
                <div class="stat-number">Rs. <?php echo number_format($financial_summary['avg_bill_amount'], 0); ?></div>
                <div class="stat-label">Avg Bill Amount</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($financial_summary['total_bills']); ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Payment Status Distribution -->
        <div class="col-lg-4">
            <div class="report-card">
                <h5><i class="fas fa-chart-pie me-2"></i>Payment Status</h5>
                <?php foreach ($payment_distribution as $payment): ?>
                    <div class="payment-status-card status-<?php echo $payment['payment_status']; ?>">
                        <h6><?php echo ucfirst($payment['payment_status']); ?></h6>
                        <div class="stat-number"><?php echo number_format($payment['count']); ?></div>
                        <div>Rs. <?php echo number_format($payment['total_amount'], 2); ?></div>
                        <small>Avg: Rs. <?php echo number_format($payment['avg_amount'], 2); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Revenue Trends -->
        <div class="col-lg-8">
            <div class="report-card">
                <h5><i class="fas fa-chart-bar me-2"></i>Monthly Revenue Trends</h5>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Bills</th>
                                <th>Revenue</th>
                                <th>Collected</th>
                                <th>Outstanding</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revenue_trends as $trend): ?>
                            <tr>
                                <td><strong><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></strong></td>
                                <td><?php echo number_format($trend['bill_count']); ?></td>
                                <td>Rs. <?php echo number_format($trend['total_revenue']); ?></td>
                                <td>Rs. <?php echo number_format($trend['collected_revenue']); ?></td>
                                <td>Rs. <?php echo number_format($trend['outstanding_amount']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $trend['collection_rate'] >= 80 ? 'success' : ($trend['collection_rate'] >= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo $trend['collection_rate']; ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Revenue Analysis -->
    <div class="report-card">
        <h5><i class="fas fa-building me-2"></i>Department Revenue Analysis</h5>
        <div class="row">
            <?php foreach ($department_revenue as $dept): ?>
                <div class="col-lg-6 mb-3">
                    <div class="revenue-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($dept['department_name']); ?></h6>
                                <small class="text-muted"><?php echo $dept['doctors_count']; ?> doctors • Rs. <?php echo number_format($dept['avg_consultation_fee'], 0); ?> avg fee</small>
                            </div>
                            <div class="text-end">
                                <strong>Rs. <?php echo number_format($dept['total_revenue']); ?></strong>
                                <br><small class="text-muted"><?php echo number_format($dept['total_bills']); ?> bills</small>
                            </div>
                        </div>
                        <div class="collection-bar">
                            <?php 
                            $rate = $dept['collection_rate'];
                            $class = $rate >= 80 ? 'collection-excellent' : ($rate >= 60 ? 'collection-good' : 'collection-poor');
                            ?>
                            <div class="collection-fill <?php echo $class; ?>" style="width: <?php echo $rate; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between text-sm">
                            <span>Collected: Rs. <?php echo number_format($dept['collected_revenue']); ?></span>
                            <span><?php echo $rate; ?>%</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row">
        <!-- Top Services -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-medal me-2"></i>Top Revenue Services</h5>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($top_services as $index => $service): ?>
                    <div class="service-item">
                        <div>
                            <strong><?php echo htmlspecialchars($service['description']); ?></strong>
                            <br><small class="text-muted"><?php echo $service['bill_count']; ?> bills • Avg qty: <?php echo number_format($service['avg_quantity'], 1); ?></small>
                        </div>
                        <div class="text-end">
                            <strong>Rs. <?php echo number_format($service['total_revenue']); ?></strong>
                            <br><small class="text-muted">Rs. <?php echo number_format($service['avg_unit_price'], 2); ?> per unit</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Doctor Revenue Performance -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-user-md me-2"></i>Doctor Revenue Performance</h5>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Revenue</th>
                                <th>Bills</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctor_revenue as $doctor): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo $doctor['employee_id']; ?> • <?php echo htmlspecialchars($doctor['specialization']); ?></small>
                                </td>
                                <td>Rs. <?php echo number_format($doctor['total_revenue']); ?></td>
                                <td><?php echo $doctor['total_bills']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $doctor['collection_rate'] >= 80 ? 'success' : ($doctor['collection_rate'] >= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo $doctor['collection_rate']; ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Outstanding Invoices -->
    <div class="report-card">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Outstanding Invoices</h5>
        <div style="max-height: 500px; overflow-y: auto;">
            <?php foreach ($outstanding_invoices as $invoice): ?>
                <div class="outstanding-item <?php echo $invoice['days_overdue'] > 0 ? 'overdue' : ''; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <strong><?php echo $invoice['bill_number']; ?></strong>
                            <br><small class="text-muted"><?php echo date('M j, Y', strtotime($invoice['billing_date'])); ?></small>
                        </div>
                        <div class="col-md-3">
                            <strong><?php echo htmlspecialchars($invoice['patient_name']); ?></strong>
                            <br><small class="text-muted"><?php echo $invoice['patient_id']; ?> • <?php echo $invoice['patient_phone']; ?></small>
                        </div>
                        <div class="col-md-3">
                            <?php echo htmlspecialchars($invoice['doctor_name']); ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($invoice['department_name'] ?? 'N/A'); ?></small>
                        </div>
                        <div class="col-md-2 text-center">
                            <strong class="text-danger">Rs. <?php echo number_format($invoice['balance_amount']); ?></strong>
                            <br><small class="text-muted">of Rs. <?php echo number_format($invoice['total_amount']); ?></small>
                        </div>
                        <div class="col-md-2 text-end">
                            <span class="badge bg-<?php echo $invoice['payment_status'] === 'pending' ? 'danger' : 'warning'; ?>">
                                <?php echo ucfirst($invoice['payment_status']); ?>
                            </span>
                            <?php if ($invoice['days_overdue'] > 0): ?>
                                <br><small class="text-danger"><?php echo $invoice['days_overdue']; ?> days overdue</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($outstanding_invoices)): ?>
        <div class="text-center text-muted py-4">
            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
            <p>No outstanding invoices for the selected period!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportFinancialReport() {
    // Get current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'true');
    
    // Open export URL
    window.open(`export_financial_report.php?${params.toString()}`, '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>
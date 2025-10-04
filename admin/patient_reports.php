<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$page_title = 'Patient Reports';
$db = new Database();

// Date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$age_group = $_GET['age_group'] ?? '';
$gender = $_GET['gender'] ?? '';
$blood_group = $_GET['blood_group'] ?? '';

try {
    // Patient Demographics
    $db->query("SELECT 
                    gender,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM patients WHERE id IS NOT NULL)), 1) as percentage
                FROM patients 
                WHERE id IS NOT NULL
                GROUP BY gender");
    $gender_stats = $db->resultSet();

    // Age Distribution
    $db->query("SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 70 THEN '51-70'
                        ELSE 'Over 70'
                    END as age_group,
                    COUNT(*) as count
                FROM patients 
                WHERE date_of_birth IS NOT NULL
                GROUP BY age_group
                ORDER BY 
                    CASE age_group
                        WHEN 'Under 18' THEN 1
                        WHEN '18-30' THEN 2
                        WHEN '31-50' THEN 3
                        WHEN '51-70' THEN 4
                        WHEN 'Over 70' THEN 5
                    END");
    $age_stats = $db->resultSet();

    // Blood Group Distribution
    $db->query("SELECT 
                    blood_group,
                    COUNT(*) as count
                FROM patients 
                WHERE blood_group IS NOT NULL AND blood_group != ''
                GROUP BY blood_group
                ORDER BY count DESC");
    $blood_group_stats = $db->resultSet();

    // Registration Trends (Last 12 months)
    $db->query("SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as registrations
                FROM patients 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC");
    $registration_trends = $db->resultSet();

    // Patient Activity (Appointments in selected period)
    $where_conditions = ["a.appointment_date BETWEEN :start_date AND :end_date"];
    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if (!empty($age_group)) {
        switch ($age_group) {
            case 'under_18':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 18";
                break;
            case '18_30':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 18 AND 30";
                break;
            case '31_50':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 31 AND 50";
                break;
            case '51_70':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 51 AND 70";
                break;
            case 'over_70':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) > 70";
                break;
        }
    }

    if (!empty($gender)) {
        $where_conditions[] = "p.gender = :gender";
        $params[':gender'] = $gender;
    }

    if (!empty($blood_group)) {
        $where_conditions[] = "p.blood_group = :blood_group";
        $params[':blood_group'] = $blood_group;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Patient Activity Report
    $db->query("SELECT 
                    p.patient_id, p.first_name, p.last_name, p.gender, 
                    p.blood_group, p.phone, p.email,
                    TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                    COUNT(a.id) as total_appointments,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
                    MAX(a.appointment_date) as last_visit
                FROM patients p
                LEFT JOIN appointments a ON p.id = a.patient_id AND $where_clause
                WHERE p.id IS NOT NULL
                GROUP BY p.id, p.patient_id, p.first_name, p.last_name, p.gender, p.blood_group, p.phone, p.email, age
                HAVING total_appointments > 0
                ORDER BY total_appointments DESC, last_visit DESC
                LIMIT 50");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $patient_activity = $db->resultSet();

    // Top Medical Conditions (based on appointment reasons)
    $db->query("SELECT 
                    reason,
                    COUNT(*) as frequency
                FROM appointments 
                WHERE appointment_date BETWEEN :start_date AND :end_date 
                    AND reason IS NOT NULL 
                    AND reason != ''
                GROUP BY reason
                ORDER BY frequency DESC
                LIMIT 10");
    $db->bind(':start_date', $start_date);
    $db->bind(':end_date', $end_date);
    $medical_conditions = $db->resultSet();

    // Patient Statistics Summary
    $db->query("SELECT 
                    COUNT(*) as total_patients,
                    COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_patients_30days,
                    COUNT(CASE WHEN gender = 'Male' THEN 1 END) as male_patients,
                    COUNT(CASE WHEN gender = 'Female' THEN 1 END) as female_patients,
                    ROUND(AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())), 1) as avg_age
                FROM patients 
                WHERE id IS NOT NULL");
    $patient_summary = $db->single();

} catch (Exception $e) {
    $error_message = 'Error fetching patient report data: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.patient-reports-container {
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

.stat-box.male {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-box.female {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-box.age {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-box.new {
    background: linear-gradient(135deg, #fad0c4 0%, #ffd1ff 100%);
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

.chart-container {
    height: 300px;
    position: relative;
}

.progress-item {
    margin-bottom: 15px;
}

.progress-item .d-flex {
    margin-bottom: 5px;
}

.age-group-bar {
    background: #f8f9fa;
    border-radius: 20px;
    height: 25px;
    margin-bottom: 10px;
    overflow: hidden;
}

.age-group-fill {
    height: 100%;
    border-radius: 20px;
    display: flex;
    align-items: center;
    padding: 0 15px;
    color: white;
    font-weight: 500;
    font-size: 0.85rem;
}

.filters-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.condition-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.condition-item:last-child {
    border-bottom: none;
}

.blood-group-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.blood-group-card {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.blood-group-card .blood-type {
    font-size: 1.5rem;
    font-weight: bold;
    color: #dc3545;
    margin-bottom: 5px;
}

.blood-group-card .count {
    font-size: 0.9rem;
    color: #666;
}

@media (max-width: 768px) {
    .patient-reports-container {
        padding: 10px;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .blood-group-grid {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
}
</style>

<div class="patient-reports-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-users me-2"></i>Patient Reports</h1>
            <p class="text-muted mb-0">Comprehensive patient analytics and demographics</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Reports
            </a>
            <button class="btn btn-success" onclick="exportPatientReport()">
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
                <label class="form-label">Age Group</label>
                <select class="form-select" name="age_group">
                    <option value="">All Ages</option>
                    <option value="under_18" <?php echo $age_group === 'under_18' ? 'selected' : ''; ?>>Under 18</option>
                    <option value="18_30" <?php echo $age_group === '18_30' ? 'selected' : ''; ?>>18-30</option>
                    <option value="31_50" <?php echo $age_group === '31_50' ? 'selected' : ''; ?>>31-50</option>
                    <option value="51_70" <?php echo $age_group === '51_70' ? 'selected' : ''; ?>>51-70</option>
                    <option value="over_70" <?php echo $age_group === 'over_70' ? 'selected' : ''; ?>>Over 70</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Gender</label>
                <select class="form-select" name="gender">
                    <option value="">All Genders</option>
                    <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Blood Group</label>
                <select class="form-select" name="blood_group">
                    <option value="">All Blood Groups</option>
                    <option value="A+" <?php echo $blood_group === 'A+' ? 'selected' : ''; ?>>A+</option>
                    <option value="A-" <?php echo $blood_group === 'A-' ? 'selected' : ''; ?>>A-</option>
                    <option value="B+" <?php echo $blood_group === 'B+' ? 'selected' : ''; ?>>B+</option>
                    <option value="B-" <?php echo $blood_group === 'B-' ? 'selected' : ''; ?>>B-</option>
                    <option value="AB+" <?php echo $blood_group === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                    <option value="AB-" <?php echo $blood_group === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                    <option value="O+" <?php echo $blood_group === 'O+' ? 'selected' : ''; ?>>O+</option>
                    <option value="O-" <?php echo $blood_group === 'O-' ? 'selected' : ''; ?>>O-</option>
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
                <div class="stat-number"><?php echo number_format($patient_summary['total_patients']); ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box new">
                <div class="stat-number"><?php echo number_format($patient_summary['new_patients_30days']); ?></div>
                <div class="stat-label">New (30 days)</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box male">
                <div class="stat-number"><?php echo number_format($patient_summary['male_patients']); ?></div>
                <div class="stat-label">Male Patients</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box female">
                <div class="stat-number"><?php echo number_format($patient_summary['female_patients']); ?></div>
                <div class="stat-label">Female Patients</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box age">
                <div class="stat-number"><?php echo $patient_summary['avg_age']; ?></div>
                <div class="stat-label">Average Age</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-box">
                <div class="stat-number"><?php echo count($patient_activity); ?></div>
                <div class="stat-label">Active Patients</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Age Distribution -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-birthday-cake me-2"></i>Age Distribution</h5>
                <?php 
                $total_age_patients = array_sum(array_column($age_stats, 'count'));
                $colors = ['#e74c3c', '#3498db', '#f39c12', '#27ae60', '#9b59b6'];
                ?>
                <?php foreach ($age_stats as $index => $age): ?>
                    <?php $percentage = $total_age_patients > 0 ? ($age['count'] / $total_age_patients * 100) : 0; ?>
                    <div class="age-group-bar">
                        <div class="age-group-fill" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $colors[$index % 5]; ?>">
                            <?php echo $age['age_group']; ?>: <?php echo $age['count']; ?> (<?php echo number_format($percentage, 1); ?>%)
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Blood Group Distribution -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-tint me-2"></i>Blood Group Distribution</h5>
                <div class="blood-group-grid">
                    <?php foreach ($blood_group_stats as $bg): ?>
                    <div class="blood-group-card">
                        <div class="blood-type"><?php echo htmlspecialchars($bg['blood_group']); ?></div>
                        <div class="count"><?php echo number_format($bg['count']); ?> patients</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Registration Trends -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-chart-line me-2"></i>Registration Trends (12 Months)</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>New Registrations</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registration_trends as $index => $trend): ?>
                            <?php 
                            $prev_count = $index > 0 ? $registration_trends[$index - 1]['registrations'] : $trend['registrations'];
                            $change = $trend['registrations'] - $prev_count;
                            $trend_class = $change > 0 ? 'text-success' : ($change < 0 ? 'text-danger' : 'text-muted');
                            $trend_icon = $change > 0 ? 'fa-arrow-up' : ($change < 0 ? 'fa-arrow-down' : 'fa-minus');
                            ?>
                            <tr>
                                <td><strong><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></strong></td>
                                <td><?php echo number_format($trend['registrations']); ?></td>
                                <td class="<?php echo $trend_class; ?>">
                                    <i class="fas <?php echo $trend_icon; ?> me-1"></i>
                                    <?php echo $change >= 0 ? '+' : ''; ?><?php echo $change; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Medical Conditions -->
        <div class="col-lg-6">
            <div class="report-card">
                <h5><i class="fas fa-notes-medical me-2"></i>Top Medical Conditions</h5>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($medical_conditions as $condition): ?>
                    <div class="condition-item">
                        <div>
                            <strong><?php echo htmlspecialchars($condition['reason']); ?></strong>
                        </div>
                        <div>
                            <span class="badge bg-primary"><?php echo number_format($condition['frequency']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient Activity Report -->
    <div class="report-card">
        <h5><i class="fas fa-activity me-2"></i>Patient Activity Report</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age/Gender</th>
                        <th>Blood Group</th>
                        <th>Contact</th>
                        <th>Appointments</th>
                        <th>Completed</th>
                        <th>Last Visit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patient_activity as $patient): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($patient['patient_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                        <td>
                            <?php echo $patient['age']; ?> years
                            <br><small class="text-muted"><?php echo ucfirst($patient['gender']); ?></small>
                        </td>
                        <td>
                            <?php if ($patient['blood_group']): ?>
                                <span class="badge bg-danger"><?php echo $patient['blood_group']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($patient['phone']); ?>
                                <br><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($patient['email']); ?>
                            </small>
                        </td>
                        <td><span class="badge bg-primary"><?php echo $patient['total_appointments']; ?></span></td>
                        <td><span class="badge bg-success"><?php echo $patient['completed_appointments']; ?></span></td>
                        <td>
                            <?php if ($patient['last_visit']): ?>
                                <?php echo date('M j, Y', strtotime($patient['last_visit'])); ?>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($patient_activity)): ?>
        <div class="text-center text-muted py-4">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p>No patient activity found for the selected criteria.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportPatientReport() {
    // Get current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'true');
    
    // Open export URL
    window.open(`export_patient_report.php?${params.toString()}`, '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>
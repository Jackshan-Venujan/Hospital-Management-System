<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'My Patients';
$db = new Database();

// Get doctor information
$db->query("SELECT d.* FROM doctors d WHERE d.user_id = :user_id");
$db->bind(':user_id', $_SESSION['user_id']);
$doctor_info = $db->single();

if (!$doctor_info) {
    $_SESSION['error'] = 'Doctor profile not found.';
    redirect('../login.php');
}

// Filter parameters
$search = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_range = $_GET['age_range'] ?? '';
$appointment_status = $_GET['appointment_status'] ?? '';

// Build query conditions for patients who have appointments with this doctor
$where_conditions = ["a.doctor_id = :doctor_id"];
$params = [':doctor_id' => $doctor_info['id']];

if (!empty($search)) {
    $where_conditions[] = "(p.first_name LIKE :search OR p.last_name LIKE :search OR p.patient_id LIKE :search OR p.phone LIKE :search OR p.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($gender_filter)) {
    $where_conditions[] = "p.gender = :gender";
    $params[':gender'] = $gender_filter;
}

if (!empty($age_range)) {
    $age_conditions = [];
    switch ($age_range) {
        case '0-18':
            $age_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 0 AND 18";
            break;
        case '19-35':
            $age_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 19 AND 35";
            break;
        case '36-55':
            $age_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 36 AND 55";
            break;
        case '56+':
            $age_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) >= 56";
            break;
    }
    if (!empty($age_conditions)) {
        $where_conditions = array_merge($where_conditions, $age_conditions);
    }
}

if (!empty($appointment_status)) {
    $where_conditions[] = "a.status = :appointment_status";
    $params[':appointment_status'] = $appointment_status;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get patients with their latest appointment information
    $db->query("SELECT p.*, 
                    MAX(a.appointment_date) as last_appointment,
                    COUNT(a.id) as total_appointments,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
                    TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                    (SELECT a2.status FROM appointments a2 WHERE a2.patient_id = p.id AND a2.doctor_id = :doctor_id ORDER BY a2.appointment_date DESC, a2.appointment_time DESC LIMIT 1) as latest_status
                FROM patients p
                JOIN appointments a ON p.id = a.patient_id
                WHERE $where_clause
                GROUP BY p.id
                ORDER BY MAX(a.appointment_date) DESC");
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    $patients = $db->resultSet();
    
    // Get patient statistics
    $db->query("SELECT 
                    COUNT(DISTINCT p.id) as total_patients,
                    COUNT(CASE WHEN p.gender = 'male' THEN 1 END) as male_patients,
                    COUNT(CASE WHEN p.gender = 'female' THEN 1 END) as female_patients,
                    AVG(TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE())) as avg_age
                FROM patients p
                JOIN appointments a ON p.id = a.patient_id
                WHERE a.doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $stats = $db->single();

} catch (Exception $e) {
    $error_message = 'Error loading patients: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.patients-container {
    padding: 20px;
}

.filter-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.stats-row {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.stat-item {
    text-align: center;
    padding: 15px;
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

.patients-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.patient-item {
    border: 1px solid #f1f1f1;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.patient-item:hover {
    border-color: #667eea;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.1);
}

.patient-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.patient-name {
    font-size: 1.3rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.patient-id {
    color: #667eea;
    font-weight: 600;
}

.patient-info {
    margin-bottom: 15px;
}

.info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 10px;
}

.info-item {
    display: flex;
    align-items: center;
    color: #666;
    font-size: 0.9rem;
}

.info-item i {
    margin-right: 6px;
    width: 16px;
}

.appointment-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.summary-row:last-child {
    margin-bottom: 0;
}

.summary-label {
    font-size: 0.9rem;
    color: #666;
}

.summary-value {
    font-weight: 600;
    color: #333;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-scheduled { background: #e3f2fd; color: #1565c0; }
.status-confirmed { background: #e8f5e8; color: #2e7d32; }
.status-completed { background: #f3e5f5; color: #7b1fa2; }
.status-cancelled { background: #ffebee; color: #c62828; }
.status-no-show { background: #fff3e0; color: #ef6c00; }

.gender-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.gender-male { background: #e3f2fd; color: #1565c0; }
.gender-female { background: #fce4ec; color: #c2185b; }

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-sm-custom {
    padding: 6px 12px;
    font-size: 0.8rem;
    border-radius: 6px;
}

.no-patients {
    text-align: center;
    padding: 60px;
    color: #888;
}

.no-patients i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .patients-container {
        padding: 15px;
    }
    
    .patient-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .info-row {
        flex-direction: column;
        gap: 8px;
    }
    
    .summary-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
    
    .stat-number {
        font-size: 1.4rem;
    }
}
</style>

<div class="patients-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-user-injured me-2"></i>My Patients</h1>
            <p class="text-muted mb-0">Manage patients under your care</p>
        </div>
        <div>
            <a href="doctor_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-row">
        <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_patients'] ?? 0; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['male_patients'] ?? 0; ?></div>
                    <div class="stat-label">Male Patients</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['female_patients'] ?? 0; ?></div>
                    <div class="stat-label">Female Patients</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="stat-item">
                    <div class="stat-number"><?php echo round($stats['avg_age'] ?? 0); ?></div>
                    <div class="stat-label">Average Age</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Patients</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" 
                       placeholder="Name, ID, phone, email" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Gender</label>
                <select class="form-select" name="gender">
                    <option value="">All Genders</option>
                    <option value="male" <?php echo $gender_filter === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $gender_filter === 'female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Age Range</label>
                <select class="form-select" name="age_range">
                    <option value="">All Ages</option>
                    <option value="0-18" <?php echo $age_range === '0-18' ? 'selected' : ''; ?>>0-18 years</option>
                    <option value="19-35" <?php echo $age_range === '19-35' ? 'selected' : ''; ?>>19-35 years</option>
                    <option value="36-55" <?php echo $age_range === '36-55' ? 'selected' : ''; ?>>36-55 years</option>
                    <option value="56+" <?php echo $age_range === '56+' ? 'selected' : ''; ?>>56+ years</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Latest Appointment</label>
                <select class="form-select" name="appointment_status">
                    <option value="">All Statuses</option>
                    <option value="scheduled" <?php echo $appointment_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="confirmed" <?php echo $appointment_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $appointment_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $appointment_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Error Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Patients List -->
    <div class="patients-card">
        <h5 class="mb-4">
            <i class="fas fa-list me-2"></i>Patients 
            <?php if (!empty($patients)): ?>
                <span class="badge bg-primary"><?php echo count($patients); ?></span>
            <?php endif; ?>
        </h5>

        <?php if (!empty($patients)): ?>
            <?php foreach ($patients as $patient): ?>
                <div class="patient-item">
                    <div class="patient-header">
                        <div>
                            <div class="patient-name">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                <span class="gender-badge gender-<?php echo $patient['gender']; ?>">
                                    <?php echo ucfirst($patient['gender']); ?>
                                </span>
                            </div>
                            <div class="patient-id">
                                ID: <?php echo htmlspecialchars($patient['patient_id']); ?>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($patient['latest_status'])): ?>
                                <span class="status-badge status-<?php echo $patient['latest_status']; ?>">
                                    Latest: <?php echo ucfirst(str_replace('-', ' ', $patient['latest_status'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="patient-info">
                        <div class="info-row">
                            <div class="info-item">
                                <i class="fas fa-birthday-cake"></i>
                                Age: <?php echo $patient['age']; ?> years
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <?php echo htmlspecialchars($patient['phone']); ?>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($patient['email']); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($patient['address'])): ?>
                            <div class="info-row">
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($patient['address']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['emergency_contact'])): ?>
                            <div class="info-row">
                                <div class="info-item">
                                    <i class="fas fa-phone-alt"></i>
                                    Emergency: <?php echo htmlspecialchars($patient['emergency_contact']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="appointment-summary">
                        <div class="summary-row">
                            <span class="summary-label">Total Appointments:</span>
                            <span class="summary-value"><?php echo $patient['total_appointments']; ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Completed Appointments:</span>
                            <span class="summary-value"><?php echo $patient['completed_appointments']; ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Last Appointment:</span>
                            <span class="summary-value">
                                <?php 
                                if ($patient['last_appointment']) {
                                    echo date('M j, Y', strtotime($patient['last_appointment']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="doctor_appointments.php?patient=<?php echo urlencode($patient['first_name'] . ' ' . $patient['last_name']); ?>" 
                           class="btn btn-primary btn-sm-custom">
                            <i class="fas fa-calendar me-1"></i>View Appointments
                        </a>
                        <button class="btn btn-outline-info btn-sm-custom" onclick="viewPatientDetails(<?php echo $patient['id']; ?>)">
                            <i class="fas fa-user me-1"></i>View Details
                        </button>
                        <a href="doctor_schedule.php?patient_id=<?php echo $patient['id']; ?>" 
                           class="btn btn-outline-success btn-sm-custom">
                            <i class="fas fa-plus me-1"></i>New Appointment
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-patients">
                <i class="fas fa-user-times"></i>
                <h5>No patients found</h5>
                <p>No patients match your current filters or you haven't seen any patients yet.</p>
                <?php if (!empty($search) || !empty($gender_filter) || !empty($age_range) || !empty($appointment_status)): ?>
                    <a href="doctor_patients.php" class="btn btn-outline-primary">
                        <i class="fas fa-refresh me-1"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Patient Details Modal -->
<div class="modal fade" id="patientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Patient Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="patientDetails">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewPatientDetails(patientId) {
    const modal = new bootstrap.Modal(document.getElementById('patientModal'));
    modal.show();
    
    // You can implement an AJAX call here to fetch detailed patient information
    document.getElementById('patientDetails').innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Detailed patient information would be loaded here via AJAX.
        </div>
    `;
}
</script>

<?php include '../includes/footer.php'; ?>
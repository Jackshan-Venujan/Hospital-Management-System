<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

$page_title = 'Doctors Management';

// Handle actions
$action = $_GET['action'] ?? 'list';
$doctor_id = $_GET['id'] ?? null;
$success = '';
$error = '';

// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_doctor'])) {
        // Add new doctor logic
        try {
            $db->beginTransaction();
            
            // Sanitize inputs
            $first_name = sanitize_input($_POST['first_name']);
            $last_name = sanitize_input($_POST['last_name']);
            $phone = sanitize_input($_POST['phone']);
            $email = sanitize_input($_POST['email']);
            $specialization = sanitize_input($_POST['specialization']);
            $department_id = $_POST['department_id'] ?: null;
            $qualification = sanitize_input($_POST['qualification']);
            $experience_years = (int)$_POST['experience_years'];
            $consultation_fee = (float)$_POST['consultation_fee'];
            $schedule_start = $_POST['schedule_start'];
            $schedule_end = $_POST['schedule_end'];
            $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];
            
            // Generate employee ID
            $employee_id = generate_id('DOC', 3);
            
            // Check if employee ID already exists
            do {
                $db->query('SELECT id FROM doctors WHERE employee_id = :employee_id');
                $db->bind(':employee_id', $employee_id);
                $existing = $db->single();
                if ($existing) {
                    $employee_id = generate_id('DOC', 3);
                }
            } while ($existing);
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $db->query('INSERT INTO users (username, email, password, role, status) VALUES (:username, :email, :password, :role, :status)');
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $db->bind(':password', $hashed_password);
            $db->bind(':role', 'doctor');
            $db->bind(':status', 'active');
            $db->execute();
            
            $user_id = $db->lastInsertId();
            
            // Create doctor record
            $db->query('
                INSERT INTO doctors (
                    user_id, employee_id, first_name, last_name, phone, email, 
                    specialization, department_id, qualification, experience_years, 
                    consultation_fee, schedule_start, schedule_end, available_days
                ) VALUES (
                    :user_id, :employee_id, :first_name, :last_name, :phone, :email,
                    :specialization, :department_id, :qualification, :experience_years,
                    :consultation_fee, :schedule_start, :schedule_end, :available_days
                )
            ');
            
            $db->bind(':user_id', $user_id);
            $db->bind(':employee_id', $employee_id);
            $db->bind(':first_name', $first_name);
            $db->bind(':last_name', $last_name);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':specialization', $specialization);
            $db->bind(':department_id', $department_id);
            $db->bind(':qualification', $qualification);
            $db->bind(':experience_years', $experience_years);
            $db->bind(':consultation_fee', $consultation_fee);
            $db->bind(':schedule_start', $schedule_start);
            $db->bind(':schedule_end', $schedule_end);
            $db->bind(':available_days', $available_days);
            $db->execute();
            
            $db->endTransaction();
            
            set_message('success', 'Doctor added successfully! Employee ID: ' . $employee_id);
            redirect('doctors.php');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            $error = 'Error adding doctor: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_doctor'])) {
        // Update doctor logic
        try {
            $db->beginTransaction();
            
            $doctor_db_id = $_POST['doctor_db_id'];
            
            // Update doctor record
            $db->query('
                UPDATE doctors SET 
                    first_name = :first_name, last_name = :last_name, phone = :phone, 
                    email = :email, specialization = :specialization, department_id = :department_id,
                    qualification = :qualification, experience_years = :experience_years,
                    consultation_fee = :consultation_fee, schedule_start = :schedule_start,
                    schedule_end = :schedule_end, available_days = :available_days
                WHERE id = :id
            ');
            
            $db->bind(':id', $doctor_db_id);
            $db->bind(':first_name', sanitize_input($_POST['first_name']));
            $db->bind(':last_name', sanitize_input($_POST['last_name']));
            $db->bind(':phone', sanitize_input($_POST['phone']));
            $db->bind(':email', sanitize_input($_POST['email']));
            $db->bind(':specialization', sanitize_input($_POST['specialization']));
            $db->bind(':department_id', $_POST['department_id'] ?: null);
            $db->bind(':qualification', sanitize_input($_POST['qualification']));
            $db->bind(':experience_years', (int)$_POST['experience_years']);
            $db->bind(':consultation_fee', (float)$_POST['consultation_fee']);
            $db->bind(':schedule_start', $_POST['schedule_start']);
            $db->bind(':schedule_end', $_POST['schedule_end']);
            $db->bind(':available_days', isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '');
            $db->execute();
            
            $db->endTransaction();
            
            set_message('success', 'Doctor updated successfully!');
            redirect('doctors.php');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            $error = 'Error updating doctor: ' . $e->getMessage();
        }
    }
}

// Handle status updates
if (isset($_GET['toggle_status']) && isset($_GET['doctor_id'])) {
    try {
        // Get current doctor info
        $db->query('SELECT user_id FROM doctors WHERE id = :id');
        $db->bind(':id', $_GET['doctor_id']);
        $doctor = $db->single();
        
        if ($doctor) {
            // Get current user status
            $db->query('SELECT status FROM users WHERE id = :id');
            $db->bind(':id', $doctor['user_id']);
            $user = $db->single();
            
            $new_status = ($user['status'] === 'active') ? 'inactive' : 'active';
            
            // Update user status
            $db->query('UPDATE users SET status = :status WHERE id = :id');
            $db->bind(':status', $new_status);
            $db->bind(':id', $doctor['user_id']);
            $db->execute();
            
            set_message('success', 'Doctor status updated to ' . $new_status);
        }
    } catch (Exception $e) {
        set_message('error', 'Error updating doctor status: ' . $e->getMessage());
    }
    
    redirect('doctors.php');
}

// Pagination and search setup
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$specialization_filter = $_GET['specialization'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(d.first_name LIKE :search OR d.last_name LIKE :search OR d.employee_id LIKE :search OR d.email LIKE :search OR d.specialization LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($department_filter)) {
    $conditions[] = "d.department_id = :department";
    $params[':department'] = $department_filter;
}

if (!empty($specialization_filter)) {
    $conditions[] = "d.specialization = :specialization";
    $params[':specialization'] = $specialization_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM doctors d JOIN users u ON d.user_id = u.id LEFT JOIN departments dept ON d.department_id = dept.id {$where_clause}";
$db->query($count_query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$total_doctors = $db->single()['total'];
$total_pages = ceil($total_doctors / $per_page);

// Get doctors data
$doctors_query = "
    SELECT d.*, u.username, u.email as user_email, u.status as user_status, u.created_at as registered_date,
           dept.name as department_name
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    LEFT JOIN departments dept ON d.department_id = dept.id
    {$where_clause}
    ORDER BY d.created_at DESC 
    LIMIT {$per_page} OFFSET {$offset}
";
$db->query($doctors_query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$doctors = $db->resultSet();

// Get departments for filters and forms
$db->query('SELECT * FROM departments ORDER BY name');
$departments = $db->resultSet();

// Get unique specializations for filter
$db->query('SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization != "" ORDER BY specialization');
$specializations = $db->resultSet();

include '../includes/header.php';
?>

<div class="page-title">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-user-md me-2"></i>Doctors Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Doctors</li>
                </ol>
            </nav>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                <i class="fas fa-plus me-1"></i>Add Doctor
            </button>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search by name, ID, specialization"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="department" class="form-label">Department</label>
                <select class="form-select" id="department" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="specialization" class="form-label">Specialization</label>
                <select class="form-select" id="specialization" name="specialization">
                    <option value="">All Specializations</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" 
                                <?php echo $specialization_filter === $spec['specialization'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec['specialization']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">
                    <i class="fas fa-search me-1"></i>Search
                </button>
                <a href="doctors.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="dropdown">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportDoctors('csv')">
                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportDoctors('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF
                        </a></li>
                    </ul>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon primary">
                <i class="fas fa-user-md"></i>
            </div>
            <div class="stats-number"><?php echo number_format($total_doctors); ?></div>
            <div class="stats-label">Total Doctors</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <?php
            $active_count = 0;
            foreach ($doctors as $d) {
                if ($d['user_status'] === 'active') $active_count++;
            }
            ?>
            <div class="stats-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-number"><?php echo $active_count; ?></div>
            <div class="stats-label">Active Doctors</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <?php
            $specialization_count = count($specializations);
            ?>
            <div class="stats-icon info">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div class="stats-number"><?php echo $specialization_count; ?></div>
            <div class="stats-label">Specializations</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <?php
            $departments_count = count($departments);
            ?>
            <div class="stats-icon warning">
                <i class="fas fa-building"></i>
            </div>
            <div class="stats-number"><?php echo $departments_count; ?></div>
            <div class="stats-label">Departments</div>
        </div>
    </div>
</div>

<!-- Doctors Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Doctors List</h5>
    </div>
    <div class="card-body">
        <?php if (empty($doctors)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-md fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No doctors found</h4>
                <p class="text-muted">Get started by adding your first doctor.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                    <i class="fas fa-plus me-1"></i>Add Doctor
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Department</th>
                            <th>Experience</th>
                            <th>Consultation Fee</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctors as $doctor): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($doctor['employee_id']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar bg-success me-2">
                                            <?php echo strtoupper(substr($doctor['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold">
                                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($doctor['email']); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($doctor['phone']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($doctor['specialization']); ?>
                                    </span>
                                    <?php if ($doctor['qualification']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($doctor['qualification']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $doctor['department_name'] ? htmlspecialchars($doctor['department_name']) : '<span class="text-muted">Not assigned</span>'; ?>
                                </td>
                                <td>
                                    <strong><?php echo $doctor['experience_years']; ?></strong> years
                                </td>
                                <td>
                                    <strong class="text-success">Rs. <?php echo number_format($doctor['consultation_fee'], 2); ?></strong>
                                </td>
                                <td>
                                    <small>
                                        <?php echo format_time($doctor['schedule_start']); ?> - 
                                        <?php echo format_time($doctor['schedule_end']); ?>
                                    </small>
                                    <?php if ($doctor['available_days']): ?>
                                        <br><small class="text-muted">
                                            <?php 
                                            $days = explode(',', $doctor['available_days']);
                                            echo implode(', ', array_slice($days, 0, 3));
                                            if (count($days) > 3) echo '...';
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doctor['user_status'] === 'active'): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="viewDoctor(<?php echo $doctor['id']; ?>)">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="editDoctor(<?php echo $doctor['id']; ?>)">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item" href="?toggle_status=1&doctor_id=<?php echo $doctor['id']; ?>" 
                                                   onclick="return confirm('Are you sure you want to change the status?')">
                                                    <i class="fas fa-toggle-<?php echo $doctor['user_status'] === 'active' ? 'off' : 'on'; ?> me-2"></i>
                                                    <?php echo $doctor['user_status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Doctors pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&specialization=<?php echo urlencode($specialization_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&specialization=<?php echo urlencode($specialization_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&specialization=<?php echo urlencode($specialization_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-md me-2"></i>Add New Doctor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addDoctorForm">
                <div class="modal-body">
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-12">
                            <h6 class="text-primary mb-3">Personal Information</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <!-- Professional Information -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-3">Professional Information</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Specialization *</label>
                            <input type="text" class="form-control" name="specialization" required
                                   placeholder="e.g., Cardiologist, Neurologist">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qualification *</label>
                            <input type="text" class="form-control" name="qualification" required
                                   placeholder="e.g., MBBS, MD, PhD">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Experience (Years) *</label>
                            <input type="number" class="form-control" name="experience_years" min="0" max="50" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Consultation Fee ($) *</label>
                            <input type="number" class="form-control" name="consultation_fee" step="0.01" min="0" required>
                        </div>
                        
                        <!-- Schedule Information -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-3">Schedule Information</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Schedule Start Time</label>
                            <input type="time" class="form-control" name="schedule_start" value="09:00">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Schedule End Time</label>
                            <input type="time" class="form-control" name="schedule_end" value="17:00">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Available Days</label>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_days[]" value="Monday" id="monday">
                                        <label class="form-check-label" for="monday">Monday</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_days[]" value="Tuesday" id="tuesday">
                                        <label class="form-check-label" for="tuesday">Tuesday</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_days[]" value="Wednesday" id="wednesday">
                                        <label class="form-check-label" for="wednesday">Wednesday</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_days[]" value="Thursday" id="thursday">
                                        <label class="form-check-label" for="thursday">Thursday</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_days[]" value="Friday" id="friday">
                                        <label class="form-check-label" for="friday">Friday</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_days[]" value="Saturday" id="saturday">
                                        <label class="form-check-label" for="saturday">Saturday</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_days[]" value="Sunday" id="sunday">
                                        <label class="form-check-label" for="sunday">Sunday</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Information -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-3">Account Information</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                            <small class="text-muted">This will be used for login</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" minlength="6" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_doctor" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Add Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Doctor Details Modal -->
<div class="modal fade" id="doctorDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-md me-2"></i>Doctor Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="doctorDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Doctor Modal -->
<div class="modal fade" id="editDoctorModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Edit Doctor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editDoctorContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// View doctor details
function viewDoctor(doctorId) {
    fetch(`doctor_details.php?id=${doctorId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('doctorDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('doctorDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading doctor details');
        });
}

// Edit doctor
function editDoctor(doctorId) {
    fetch(`doctor_edit.php?id=${doctorId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('editDoctorContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('editDoctorModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading doctor edit form');
        });
}

// Export doctors
function exportDoctors(format = 'csv') {
    const search = document.getElementById('search').value;
    const department = document.getElementById('department').value;
    const specialization = document.getElementById('specialization').value;
    const status = document.getElementById('status').value;
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (department) params.append('department', department);
    if (specialization) params.append('specialization', specialization);
    if (status) params.append('status', status);
    params.append('format', format);
    
    window.open(`export_doctors.php?${params.toString()}`);
}
</script>

<?php include '../includes/footer.php'; ?>
<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

$page_title = 'Patients Management';

// Handle actions
$action = $_GET['action'] ?? 'list';
$patient_id = $_GET['id'] ?? null;
$success = '';
$error = '';

// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_patient'])) {
        // Add new patient logic
        try {
            $db->beginTransaction();
            
            // Sanitize inputs
            $first_name = sanitize_input($_POST['first_name']);
            $last_name = sanitize_input($_POST['last_name']);
            $date_of_birth = $_POST['date_of_birth'];
            $gender = $_POST['gender'];
            $phone = sanitize_input($_POST['phone']);
            $email = sanitize_input($_POST['email']);
            $address = sanitize_input($_POST['address']);
            $emergency_contact_name = sanitize_input($_POST['emergency_contact_name']);
            $emergency_contact_phone = sanitize_input($_POST['emergency_contact_phone']);
            $blood_group = $_POST['blood_group'];
            $allergies = sanitize_input($_POST['allergies']);
            $medical_history = sanitize_input($_POST['medical_history']);
            $insurance_number = sanitize_input($_POST['insurance_number']);
            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];
            
            // Generate patient ID
            $patient_id_new = generate_id('PAT', 6);
            
            // Check if patient ID already exists
            do {
                $db->query('SELECT id FROM patients WHERE patient_id = :patient_id');
                $db->bind(':patient_id', $patient_id_new);
                $existing = $db->single();
                if ($existing) {
                    $patient_id_new = generate_id('PAT', 6);
                }
            } while ($existing);
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $db->query('INSERT INTO users (username, email, password, role, status) VALUES (:username, :email, :password, :role, :status)');
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $db->bind(':password', $hashed_password);
            $db->bind(':role', 'patient');
            $db->bind(':status', 'active');
            $db->execute();
            
            $user_id = $db->lastInsertId();
            
            // Create patient record
            $db->query('
                INSERT INTO patients (
                    user_id, patient_id, first_name, last_name, date_of_birth, gender, 
                    phone, email, address, emergency_contact_name, emergency_contact_phone, 
                    blood_group, allergies, medical_history, insurance_number
                ) VALUES (
                    :user_id, :patient_id, :first_name, :last_name, :date_of_birth, :gender,
                    :phone, :email, :address, :emergency_contact_name, :emergency_contact_phone,
                    :blood_group, :allergies, :medical_history, :insurance_number
                )
            ');
            
            $db->bind(':user_id', $user_id);
            $db->bind(':patient_id', $patient_id_new);
            $db->bind(':first_name', $first_name);
            $db->bind(':last_name', $last_name);
            $db->bind(':date_of_birth', $date_of_birth);
            $db->bind(':gender', $gender);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':address', $address);
            $db->bind(':emergency_contact_name', $emergency_contact_name);
            $db->bind(':emergency_contact_phone', $emergency_contact_phone);
            $db->bind(':blood_group', $blood_group);
            $db->bind(':allergies', $allergies);
            $db->bind(':medical_history', $medical_history);
            $db->bind(':insurance_number', $insurance_number);
            $db->execute();
            
            $db->endTransaction();
            
            set_message('success', 'Patient added successfully! Patient ID: ' . $patient_id_new);
            redirect('admin/patients.php');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            $error = 'Error adding patient: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_patient'])) {
        // Update patient logic
        try {
            $db->beginTransaction();
            
            $patient_db_id = $_POST['patient_db_id'];
            
            // Update patient record
            $db->query('
                UPDATE patients SET 
                    first_name = :first_name, last_name = :last_name, date_of_birth = :date_of_birth,
                    gender = :gender, phone = :phone, email = :email, address = :address,
                    emergency_contact_name = :emergency_contact_name, emergency_contact_phone = :emergency_contact_phone,
                    blood_group = :blood_group, allergies = :allergies, medical_history = :medical_history,
                    insurance_number = :insurance_number
                WHERE id = :id
            ');
            
            $db->bind(':id', $patient_db_id);
            $db->bind(':first_name', sanitize_input($_POST['first_name']));
            $db->bind(':last_name', sanitize_input($_POST['last_name']));
            $db->bind(':date_of_birth', $_POST['date_of_birth']);
            $db->bind(':gender', $_POST['gender']);
            $db->bind(':phone', sanitize_input($_POST['phone']));
            $db->bind(':email', sanitize_input($_POST['email']));
            $db->bind(':address', sanitize_input($_POST['address']));
            $db->bind(':emergency_contact_name', sanitize_input($_POST['emergency_contact_name']));
            $db->bind(':emergency_contact_phone', sanitize_input($_POST['emergency_contact_phone']));
            $db->bind(':blood_group', $_POST['blood_group']);
            $db->bind(':allergies', sanitize_input($_POST['allergies']));
            $db->bind(':medical_history', sanitize_input($_POST['medical_history']));
            $db->bind(':insurance_number', sanitize_input($_POST['insurance_number']));
            $db->execute();
            
            $db->endTransaction();
            
            set_message('success', 'Patient updated successfully!');
            redirect('admin/patients.php');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            $error = 'Error updating patient: ' . $e->getMessage();
        }
    }
}

// Handle toggle patient status
if (isset($_POST['toggle_patient_status']) && isset($_POST['patient_id'])) {
    try {
        $patient_id = $_POST['patient_id'];
        
        // Get current patient info
        $db->query('SELECT user_id FROM patients WHERE id = :id');
        $db->bind(':id', $patient_id);
        $patient = $db->single();
        
        if ($patient) {
            // Get current user status
            $db->query('SELECT status FROM users WHERE id = :id');
            $db->bind(':id', $patient['user_id']);
            $user = $db->single();
            
            $new_status = ($user['status'] === 'active') ? 'inactive' : 'active';
            
            // Update user status
            $db->query('UPDATE users SET status = :status WHERE id = :id');
            $db->bind(':status', $new_status);
            $db->bind(':id', $patient['user_id']);
            $db->execute();
            
            // Return JSON response for AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'new_status' => $new_status,
                    'message' => 'Patient status updated to ' . $new_status
                ]);
                exit();
            }
            
            set_message('success', 'Patient status updated to ' . $new_status);
        }
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error updating patient status: ' . $e->getMessage()
            ]);
            exit();
        }
        set_message('error', 'Error updating patient status: ' . $e->getMessage());
    }
    
    // Only redirect if not AJAX
    if (!isset($_POST['ajax'])) {
        redirect('admin/patients.php');
    }
}

// Handle delete patient
if (isset($_POST['delete_patient']) && isset($_POST['patient_id'])) {
    try {
        $db->beginTransaction();
        
        $patient_id = $_POST['patient_id'];
        
        // Get patient info
        $db->query('SELECT user_id, patient_id FROM patients WHERE id = :id');
        $db->bind(':id', $patient_id);
        $patient = $db->single();
        
        if ($patient) {
            // Check if patient has any appointments, medical records, or prescriptions
            $db->query('SELECT COUNT(*) as count FROM appointments WHERE patient_id = :patient_id');
            $db->bind(':patient_id', $patient_id);
            $appointments = $db->single()['count'];
            
            $db->query('SELECT COUNT(*) as count FROM medical_records WHERE patient_id = :patient_id');
            $db->bind(':patient_id', $patient_id);
            $medical_records = $db->single()['count'];
            
            $db->query('SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = :patient_id');
            $db->bind(':patient_id', $patient_id);
            $prescriptions = $db->single()['count'];
            
            if ($appointments > 0 || $medical_records > 0 || $prescriptions > 0) {
                // Don't delete, just deactivate if has related records
                $db->query('UPDATE users SET status = "inactive" WHERE id = :id');
                $db->bind(':id', $patient['user_id']);
                $db->execute();
                
                set_message('warning', 'Patient account deactivated (cannot delete due to existing medical records/appointments)');
            } else {
                // Safe to delete - no related records
                $db->query('DELETE FROM patients WHERE id = :id');
                $db->bind(':id', $patient_id);
                $db->execute();
                
                $db->query('DELETE FROM users WHERE id = :id');
                $db->bind(':id', $patient['user_id']);
                $db->execute();
                
                set_message('success', 'Patient deleted successfully');
            }
        }
        
        $db->endTransaction();
    } catch (Exception $e) {
        $db->cancelTransaction();
        set_message('error', 'Error deleting patient: ' . $e->getMessage());
    }
    
    redirect('admin/patients.php');
}



// Pagination and search setup
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(p.first_name LIKE :search OR p.last_name LIKE :search OR p.patient_id LIKE :search OR p.email LIKE :search OR p.phone LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($status_filter)) {
    $conditions[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM patients p JOIN users u ON p.user_id = u.id {$where_clause}";
$db->query($count_query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$total_patients = $db->single()['total'];
$total_pages = ceil($total_patients / $per_page);

// Get patients data
$patients_query = "
    SELECT p.*, u.username, u.email as user_email, u.status as user_status, u.created_at as registered_date
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    {$where_clause}
    ORDER BY p.created_at DESC 
    LIMIT {$per_page} OFFSET {$offset}
";
$db->query($patients_query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$patients = $db->resultSet();

include '../includes/header.php';
?>

<div class="page-title">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-users me-2"></i>Patients Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Patients</li>
                </ol>
            </nav>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                <i class="fas fa-plus me-1"></i>Add Patient
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
        <div class="row g-3">
            <div class="col-lg-8">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Search by name, ID, email, or phone"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                            <a href="patients.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-4 d-flex align-items-end justify-content-end">
                <div class="dropdown">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="exportPatients('csv')">
                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportPatients('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-number"><?php echo number_format($total_patients); ?></div>
            <div class="stats-label">Total Patients</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <?php
            $active_count = 0;
            foreach ($patients as $p) {
                if ($p['user_status'] === 'active') $active_count++;
            }
            ?>
            <div class="stats-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-number"><?php echo $active_count; ?></div>
            <div class="stats-label">Active</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <?php
            $inactive_count = count($patients) - $active_count;
            ?>
            <div class="stats-icon warning">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="stats-number"><?php echo $inactive_count; ?></div>
            <div class="stats-label">Inactive</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <?php
            $new_this_month = 0;
            foreach ($patients as $p) {
                if (date('Y-m', strtotime($p['registered_date'])) === date('Y-m')) {
                    $new_this_month++;
                }
            }
            ?>
            <div class="stats-icon info">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stats-number"><?php echo $new_this_month; ?></div>
            <div class="stats-label">New This Month</div>
        </div>
    </div>
</div>

<!-- Patients Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Patients List</h5>
    </div>
    <div class="card-body" style="overflow: visible;">
        <?php if (empty($patients)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-plus fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No patients found</h4>
                <p class="text-muted">Get started by adding your first patient.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                    <i class="fas fa-plus me-1"></i>Add Patient
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive" style="overflow: visible;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Age</th>
                            <th>Blood Group</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($patient['patient_id']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar bg-primary me-2">
                                            <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            </div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($patient['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-envelope text-muted me-1"></i>
                                        <?php echo htmlspecialchars($patient['email']); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-phone text-muted me-1"></i>
                                        <?php echo htmlspecialchars($patient['phone']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $age = date('Y') - date('Y', strtotime($patient['date_of_birth']));
                                    echo $age . ' years';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo $patient['blood_group'] ? htmlspecialchars($patient['blood_group']) : 'N/A'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($patient['user_status'] === 'active'): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo format_date($patient['registered_date']); ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <!-- View Details Button -->
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="viewPatient(<?php echo $patient['id']; ?>)" 
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <!-- Edit Button -->
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="editPatient(<?php echo $patient['id']; ?>)" 
                                                title="Edit Patient">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <!-- Status Toggle Button -->
                                        <button type="button" 
                                                class="btn btn-sm <?php echo $patient['user_status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success'; ?>" 
                                                onclick="togglePatientStatus(<?php echo $patient['id']; ?>, '<?php echo $patient['user_status']; ?>', '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')" 
                                                title="<?php echo $patient['user_status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Patient">
                                            <i class="fas fa-toggle-<?php echo $patient['user_status'] === 'active' ? 'off' : 'on'; ?>"></i>
                                        </button>
                                        
                                        <!-- Delete Button -->
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDeletePatient(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')" 
                                                title="Delete Patient">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        
                                        <!-- More Actions Dropdown -->
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    data-bs-toggle="dropdown" 
                                                    title="More Actions">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><h6 class="dropdown-header">More Actions</h6></li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="viewMedicalHistory(<?php echo $patient['id']; ?>)">
                                                        <i class="fas fa-file-medical-alt me-2"></i>Medical History
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="viewAppointments(<?php echo $patient['id']; ?>)">
                                                        <i class="fas fa-calendar-alt me-2"></i>Appointments
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="viewBilling(<?php echo $patient['id']; ?>)">
                                                        <i class="fas fa-file-invoice-dollar me-2"></i>Billing History
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="generatePatientReport(<?php echo $patient['id']; ?>)">
                                                        <i class="fas fa-file-pdf me-2"></i>Generate Report
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Patients pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add New Patient
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addPatientForm">
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
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" name="date_of_birth" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-3">Contact Information</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        
                        <!-- Emergency Contact -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-3">Emergency Contact</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Name</label>
                            <input type="text" class="form-control" name="emergency_contact_name">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" name="emergency_contact_phone">
                        </div>
                        
                        <!-- Medical Information -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-3">Medical Information</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Blood Group</label>
                            <select class="form-select" name="blood_group">
                                <option value="">Select Blood Group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Insurance Number</label>
                            <input type="text" class="form-control" name="insurance_number">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Known Allergies</label>
                            <textarea class="form-control" name="allergies" rows="2"></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Medical History</label>
                            <textarea class="form-control" name="medical_history" rows="2"></textarea>
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
                    <button type="submit" name="add_patient" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Add Patient
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Patient Details Modal -->
<div class="modal fade" id="patientDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>Patient Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="patientDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Patient Modal -->
<div class="modal fade" id="editPatientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Edit Patient
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editPatientContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Patient Confirmation Modal -->
<div class="modal fade" id="deletePatientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5>Are you sure you want to delete this patient?</h5>
                    <p class="text-muted mb-3">
                        Patient: <strong id="deletePatientName"></strong>
                    </p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Important:</strong> If this patient has medical records, appointments, or prescriptions, 
                        the account will be deactivated instead of deleted to preserve medical history.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <form method="POST" class="d-inline">
                    <input type="hidden" id="deletePatientId" name="patient_id">
                    <button type="submit" name="delete_patient" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Action buttons styling */
.btn-group-actions {
    white-space: nowrap;
}

.btn-group-actions .btn {
    border-radius: 0.25rem;
    margin-right: 0.25rem;
}

.btn-group-actions .btn:last-child {
    margin-right: 0;
}

/* Status badges */
.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.status-active {
    background-color: #d1edff;
    color: #0969da;
}

.status-badge.status-inactive {
    background-color: #fff1c2;
    color: #7d4e00;
}

/* Table improvements */
.table td {
    vertical-align: middle;
}

/* Action column width */
.table th:last-child,
.table td:last-child {
    width: 220px;
    min-width: 220px;
}

/* Button spacing in action column */
.d-flex.gap-1 {
    gap: 0.25rem !important;
}

/* Dropdown menu improvements */
.dropdown-menu {
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
    min-width: 200px;
}

.dropdown-header {
    font-weight: 600;
    color: #495057;
}

/* Fix dropdown positioning in tables */
.table-responsive {
    overflow: visible !important;
}

.table .dropdown {
    position: static !important;
}

.table .dropdown-menu {
    position: absolute !important;
    z-index: 1050 !important;
    transform: translateX(-85%) !important;
}
</style>

<script>
// View patient details
function viewPatient(patientId) {
    fetch(`patient_details.php?id=${patientId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('patientDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('patientDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading patient details');
        });
}

// Edit patient
function editPatient(patientId) {
    fetch(`patient_edit.php?id=${patientId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('editPatientContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('editPatientModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading patient edit form');
        });
}

// Confirm delete patient
function confirmDeletePatient(patientId, patientName) {
    document.getElementById('deletePatientId').value = patientId;
    document.getElementById('deletePatientName').textContent = patientName;
    new bootstrap.Modal(document.getElementById('deletePatientModal')).show();
}

// View medical history
function viewMedicalHistory(patientId) {
    window.open(`../pages/doctor_patient_history.php?patient_id=${patientId}`, '_blank');
}

// View appointments
function viewAppointments(patientId) {
    window.open(`appointments.php?patient_filter=${patientId}`, '_blank');
}

// View billing
function viewBilling(patientId) {
    window.open(`billing.php?patient_filter=${patientId}`, '_blank');
}

// Generate patient report
function generatePatientReport(patientId) {
    window.open(`patient_report.php?id=${patientId}&format=pdf`, '_blank');
}

// Toggle patient status
function togglePatientStatus(patientId, currentStatus, patientName) {
    const newStatus = currentStatus === 'active' ? 'deactivate' : 'activate';
    const action = currentStatus === 'active' ? 'deactivation' : 'activation';
    
    if (confirm(`Are you sure you want to ${newStatus} ${patientName}?`)) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const patientIdInput = document.createElement('input');
        patientIdInput.type = 'hidden';
        patientIdInput.name = 'patient_id';
        patientIdInput.value = patientId;
        
        const toggleInput = document.createElement('input');
        toggleInput.type = 'hidden';
        toggleInput.name = 'toggle_patient_status';
        toggleInput.value = '1';
        
        form.appendChild(patientIdInput);
        form.appendChild(toggleInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Export patients
function exportPatients(format = 'csv') {
    const search = document.getElementById('search').value;
    const status = document.getElementById('status').value;
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    params.append('format', format);
    
    window.open(`export_patients.php?${params.toString()}`);
}

// Set max date for date of birth to today
document.addEventListener('DOMContentLoaded', function() {
    const dobInputs = document.querySelectorAll('input[name="date_of_birth"]');
    const today = new Date().toISOString().split('T')[0];
    dobInputs.forEach(input => {
        input.max = today;
    });

    // Fix dropdown positioning in table
    const dropdownToggles = document.querySelectorAll('.table .dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const dropdown = this.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (menu) {
                // Reset positioning
                menu.style.position = 'absolute';
                menu.style.zIndex = '1050';
                
                // Get table and row position
                const table = this.closest('.table-responsive');
                const row = this.closest('tr');
                const rect = row.getBoundingClientRect();
                const tableRect = table.getBoundingClientRect();
                
                // Check if we're in the first few rows
                const rowIndex = Array.from(row.parentElement.children).indexOf(row);
                
                // Position dropdown appropriately
                if (rowIndex <= 2) { // First few rows
                    menu.style.top = '100%';
                } else {
                    menu.style.bottom = '100%';
                    menu.style.top = 'auto';
                }
                
                // Ensure dropdown doesn't go off screen horizontally
                menu.style.right = '0';
                menu.style.left = 'auto';
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$conn) {
        $error_message = "Database connection failed.";
    } else {
        switch ($_POST['action']) {
            case 'add':
                $patient_id = intval($_POST['patient_id']);
                $doctor_id = intval($_POST['doctor_id']);
                $appointment_date = sanitize_input($_POST['appointment_date']);
                $appointment_time = sanitize_input($_POST['appointment_time']);
                $reason = sanitize_input($_POST['reason']);
                $status = sanitize_input($_POST['status']);
                $notes = sanitize_input($_POST['notes']);
                
                // Generate unique appointment number
                $appointment_number = 'APT' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Check if appointment slot is available
                $check_query = "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('cancelled', 'no-show')";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $appointment_date, $appointment_time);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error_message = "This time slot is already booked for the selected doctor.";
                } else {
                    $query = "INSERT INTO appointments (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, reason, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = mysqli_prepare($conn, $query);
                    
                    if (!$stmt) {
                        $error_message = "Query preparation failed: " . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($stmt, "siissssss", $appointment_number, $patient_id, $doctor_id, $appointment_date, $appointment_time, $reason, $status, $notes);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success_message = "Appointment scheduled successfully!";
                        } else {
                            $error_message = "Error scheduling appointment: " . mysqli_error($conn);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
                break;
                
            case 'edit':
                $appointment_id = intval($_POST['appointment_id']);
                $patient_id = intval($_POST['patient_id']);
                $doctor_id = intval($_POST['doctor_id']);
                $appointment_date = sanitize_input($_POST['appointment_date']);
                $appointment_time = sanitize_input($_POST['appointment_time']);
                $reason = sanitize_input($_POST['reason']);
                $status = sanitize_input($_POST['status']);
                $notes = sanitize_input($_POST['notes']);
                
                // Check if appointment slot is available (excluding current appointment)
                $check_query = "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND id != ? AND status NOT IN ('cancelled', 'no-show')";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "issi", $doctor_id, $appointment_date, $appointment_time, $appointment_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error_message = "This time slot is already booked for the selected doctor.";
                } else {
                    $query = "UPDATE appointments SET patient_id = ?, doctor_id = ?, appointment_date = ?, appointment_time = ?, reason = ?, status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    
                    if (!$stmt) {
                        $error_message = "Query preparation failed: " . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($stmt, "iisssssi", $patient_id, $doctor_id, $appointment_date, $appointment_time, $reason, $status, $notes, $appointment_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success_message = "Appointment updated successfully!";
                        } else {
                            $error_message = "Error updating appointment: " . mysqli_error($conn);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
                break;
                
            case 'delete':
                $appointment_id = intval($_POST['appointment_id']);
                
                $query = "DELETE FROM appointments WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                
                if (!$stmt) {
                    $error_message = "Query preparation failed: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($stmt, "i", $appointment_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        if (mysqli_affected_rows($conn) > 0) {
                            $success_message = "Appointment deleted successfully!";
                        } else {
                            $error_message = "Appointment not found.";
                        }
                    } else {
                        $error_message = "Error deleting appointment: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Pagination and filtering
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$doctor_filter = isset($_GET['doctor']) ? intval($_GET['doctor']) : '';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';

// Build query
$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR a.appointment_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($doctor_filter)) {
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
    $types .= "i";
}

if (!empty($date_filter)) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total 
                FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id 
                LEFT JOIN doctors d ON a.doctor_id = d.id 
                {$where_clause}";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_query);
    if (!$stmt) {
        die("Count query preparation failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_appointments = mysqli_fetch_assoc($result)['total'];
    mysqli_stmt_close($stmt);
} else {
    $result = mysqli_query($conn, $count_query);
    $total_appointments = mysqli_fetch_assoc($result)['total'];
}

// Get appointments
$limit = 10;
$offset = 0;
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$query = "SELECT a.*, 
                 p.first_name as patient_first_name,
                 p.last_name as patient_last_name,
                 p.patient_id as patient_number,
                 p.phone as patient_phone,
                 d.first_name as doctor_first_name,
                 d.last_name as doctor_last_name,
                 d.employee_id as doctor_employee_id,
                 d.specialization,
                 dept.name as department_name
          FROM appointments a 
          LEFT JOIN patients p ON a.patient_id = p.id 
          LEFT JOIN doctors d ON a.doctor_id = d.id 
          LEFT JOIN departments dept ON d.department_id = dept.id
          {$where_clause}
          ORDER BY a.appointment_date DESC, a.appointment_time DESC 
          LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Main query preparation failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$appointments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $appointments[] = $row;
}
mysqli_stmt_close($stmt);

// Get doctors for dropdown
$doctors_query = "SELECT d.*, u.email FROM doctors d LEFT JOIN users u ON d.user_id = u.id WHERE u.status = 'active' ORDER BY d.first_name, d.last_name";
$doctors_result = mysqli_query($conn, $doctors_query);
$doctors = [];
while ($doctor = mysqli_fetch_assoc($doctors_result)) {
    $doctors[] = $doctor;
}

// Get patients for dropdown
$patients_query = "SELECT * FROM patients ORDER BY first_name, last_name";
$patients_result = mysqli_query($conn, $patients_query);
$patients = [];
while ($patient = mysqli_fetch_assoc($patients_result)) {
    $patients[] = $patient;
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Appointment Management</h1>
        <div class="d-sm-flex">
            <button class="btn btn-success me-3" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                <i class="fas fa-plus"></i> Schedule Appointment
            </button>
            <div class="btn-group">
                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="export_appointments.php?format=csv">
                        <i class="fas fa-file-csv text-success me-2"></i>Export as CSV
                    </a>
                    <a class="dropdown-item" href="export_appointments.php?format=pdf">
                        <i class="fas fa-file-pdf text-danger me-2"></i>Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search patient name, appointment number..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="no-show" <?php echo $status_filter === 'no-show' ? 'selected' : ''; ?>>No Show</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="doctor" class="form-label">Doctor</label>
                    <select class="form-select" id="doctor" name="doctor">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Appointments Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Appointments (<?php echo $total_appointments; ?> total)
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($appointments)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Appointment #</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($appointment['appointment_number']); ?></div>
                                        <small class="text-muted">ID: <?php echo $appointment['id']; ?></small>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($appointment['patient_number']); ?>
                                        </small>
                                        <?php if (!empty($appointment['patient_phone'])): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></strong>
                                        </div>
                                        <?php if (!empty($appointment['specialization'])): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-stethoscope me-1"></i><?php echo htmlspecialchars($appointment['specialization']); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($appointment['department_name'])): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($appointment['department_name']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold">
                                            <i class="fas fa-calendar me-1 text-info"></i>
                                            <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $status_classes = [
                                                'scheduled' => 'bg-warning text-dark',
                                                'confirmed' => 'bg-info',
                                                'completed' => 'bg-success',
                                                'cancelled' => 'bg-secondary',
                                                'no-show' => 'bg-danger'
                                            ];
                                            $status_icons = [
                                                'scheduled' => 'fa-clock',
                                                'confirmed' => 'fa-check-circle',
                                                'completed' => 'fa-check-double',
                                                'cancelled' => 'fa-times-circle',
                                                'no-show' => 'fa-user-times'
                                            ];
                                            $status = $appointment['status'];
                                            $class = $status_classes[$status] ?? 'bg-secondary';
                                            $icon = $status_icons[$status] ?? 'fa-question';
                                        ?>
                                        <span class="badge <?php echo $class; ?>">
                                            <i class="fas <?php echo $icon; ?> me-1"></i>
                                            <?php echo ucfirst(str_replace('-', ' ', $status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($appointment['reason']); ?>">
                                            <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : '<em class="text-muted">No reason specified</em>'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="viewAppointment(<?php echo htmlspecialchars(json_encode($appointment)); ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="editAppointment(<?php echo htmlspecialchars(json_encode($appointment)); ?>)"
                                                    title="Edit Appointment">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deleteAppointment(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['appointment_number']); ?>')"
                                                    title="Delete Appointment">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php
                $total_pages = ceil($total_appointments / $limit);
                if ($total_pages > 1):
                ?>
                    <nav aria-label="Appointments pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No appointments found</h5>
                    <p class="text-muted">Try adjusting your search criteria or schedule a new appointment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-plus me-2"></i>Schedule New Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_patient_id" class="form-label">Patient *</label>
                                <select class="form-select" id="add_patient_id" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_doctor_id" class="form-label">Doctor *</label>
                                <select class="form-select" id="add_doctor_id" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            <?php if (!empty($doctor['specialization'])): ?>
                                                - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_appointment_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="add_appointment_date" 
                                       name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_appointment_time" class="form-label">Time *</label>
                                <input type="time" class="form-control" id="add_appointment_time" 
                                       name="appointment_time" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_status" class="form-label">Status *</label>
                                <select class="form-select" id="add_status" name="status" required>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="add_reason" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="add_reason" name="reason" 
                                  rows="3" placeholder="Enter the reason for this appointment..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="add_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="add_notes" name="notes" 
                                  rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Schedule Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-edit me-2"></i>Edit Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="appointment_id" id="edit_appointment_id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_patient_id" class="form-label">Patient *</label>
                                <select class="form-select" id="edit_patient_id" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_doctor_id" class="form-label">Doctor *</label>
                                <select class="form-select" id="edit_doctor_id" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            <?php if (!empty($doctor['specialization'])): ?>
                                                - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_appointment_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="edit_appointment_date" 
                                       name="appointment_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_appointment_time" class="form-label">Time *</label>
                                <input type="time" class="form-control" id="edit_appointment_time" 
                                       name="appointment_time" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status *</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="no-show">No Show</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_reason" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="edit_reason" name="reason" 
                                  rows="3" placeholder="Enter the reason for this appointment..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" 
                                  rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Appointment Modal -->
<div class="modal fade" id="viewAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar me-2"></i>Appointment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="appointment_id" id="delete_appointment_id">

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Are you sure you want to delete appointment <strong id="delete_appointment_number"></strong>?
                        This action cannot be undone.
                    </div>

                    <p class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        This will permanently remove the appointment from the system.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAppointment(appointment) {
    document.getElementById('edit_appointment_id').value = appointment.id;
    document.getElementById('edit_patient_id').value = appointment.patient_id;
    document.getElementById('edit_doctor_id').value = appointment.doctor_id;
    document.getElementById('edit_appointment_date').value = appointment.appointment_date;
    document.getElementById('edit_appointment_time').value = appointment.appointment_time;
    document.getElementById('edit_status').value = appointment.status;
    document.getElementById('edit_reason').value = appointment.reason || '';
    document.getElementById('edit_notes').value = appointment.notes || '';

    new bootstrap.Modal(document.getElementById('editAppointmentModal')).show();
}

function deleteAppointment(id, appointmentNumber) {
    document.getElementById('delete_appointment_id').value = id;
    document.getElementById('delete_appointment_number').textContent = appointmentNumber;
    new bootstrap.Modal(document.getElementById('deleteAppointmentModal')).show();
}

function viewAppointment(appointment) {
    const statusBadges = {
        'scheduled': 'warning',
        'confirmed': 'info', 
        'completed': 'success',
        'cancelled': 'secondary',
        'no-show': 'danger'
    };
    
    const statusIcons = {
        'scheduled': 'clock',
        'confirmed': 'check-circle',
        'completed': 'check-double', 
        'cancelled': 'times-circle',
        'no-show': 'user-times'
    };

    const content = `
        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong><br>${appointment.patient_first_name} ${appointment.patient_last_name}</p>
                        <p><strong>Patient ID:</strong><br>${appointment.patient_number}</p>
                        ${appointment.patient_phone ? `<p><strong>Phone:</strong><br>${appointment.patient_phone}</p>` : ''}
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user-md me-2"></i>Doctor Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong><br>Dr. ${appointment.doctor_first_name} ${appointment.doctor_last_name}</p>
                        ${appointment.specialization ? `<p><strong>Specialization:</strong><br>${appointment.specialization}</p>` : ''}
                        ${appointment.department_name ? `<p><strong>Department:</strong><br>${appointment.department_name}</p>` : ''}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-calendar me-2"></i>Appointment Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Appointment #:</strong><br>${appointment.appointment_number}</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Date & Time:</strong><br>
                                ${new Date(appointment.appointment_date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}<br>
                                ${new Date('2000-01-01 ' + appointment.appointment_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Status:</strong><br>
                                <span class="badge bg-${statusBadges[appointment.status] || 'secondary'}">
                                    <i class="fas fa-${statusIcons[appointment.status] || 'question'} me-1"></i>
                                    ${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1).replace('-', ' ')}
                                </span>
                                </p>
                            </div>
                        </div>
                        
                        ${appointment.reason ? `
                            <div class="mt-3">
                                <p><strong>Reason for Visit:</strong></p>
                                <p class="text-muted">${appointment.reason}</p>
                            </div>
                        ` : ''}
                        
                        ${appointment.notes ? `
                            <div class="mt-3">
                                <p><strong>Notes:</strong></p>
                                <p class="text-muted">${appointment.notes}</p>
                            </div>
                        ` : ''}
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Created: ${new Date(appointment.created_at).toLocaleString()}
                                ${appointment.updated_at !== appointment.created_at ? 
                                    `<br><i class="fas fa-edit me-1"></i>Updated: ${new Date(appointment.updated_at).toLocaleString()}` : ''
                                }
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('appointmentDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewAppointmentModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
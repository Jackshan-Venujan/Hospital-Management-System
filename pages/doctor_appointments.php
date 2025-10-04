<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'My Appointments';
$db = new Database();

// Get doctor information
$db->query("SELECT d.* FROM doctors d WHERE d.user_id = :user_id");
$db->bind(':user_id', $_SESSION['user_id']);
$doctor_info = $db->single();

if (!$doctor_info) {
    $_SESSION['error'] = 'Doctor profile not found.';
    redirect('../login.php');
}

// Handle appointment status updates
if ($_POST && isset($_POST['update_status'])) {
    try {
        $appointment_id = $_POST['appointment_id'];
        $new_status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        
        $db->query("UPDATE appointments SET status = :status, notes = :notes WHERE id = :id AND doctor_id = :doctor_id");
        $db->bind(':status', $new_status);
        $db->bind(':notes', $notes);
        $db->bind(':id', $appointment_id);
        $db->bind(':doctor_id', $doctor_info['id']);
        $db->execute();
        
        $_SESSION['success'] = 'Appointment status updated successfully!';
        redirect('doctor_appointments.php');
    } catch (Exception $e) {
        $error_message = 'Error updating appointment: ' . $e->getMessage();
    }
}

// Filter parameters
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? '';
$patient_search = $_GET['patient'] ?? '';

// Build query conditions
$where_conditions = ["a.doctor_id = :doctor_id"];
$params = [':doctor_id' => $doctor_info['id']];

if (!empty($date_filter)) {
    $where_conditions[] = "a.appointment_date = :date_filter";
    $params[':date_filter'] = $date_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = :status_filter";
    $params[':status_filter'] = $status_filter;
}

if (!empty($patient_search)) {
    $where_conditions[] = "(p.first_name LIKE :patient_search OR p.last_name LIKE :patient_search OR p.patient_id LIKE :patient_search)";
    $params[':patient_search'] = '%' . $patient_search . '%';
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get appointments
    $db->query("SELECT a.*, p.first_name, p.last_name, p.phone, p.email, p.patient_id, p.date_of_birth
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE $where_clause
                ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    $appointments = $db->resultSet();
    
    // Get appointment statistics
    $db->query("SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN status = 'no-show' THEN 1 END) as no_show
                FROM appointments a
                WHERE a.doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $stats = $db->single();

} catch (Exception $e) {
    $error_message = 'Error loading appointments: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.appointments-container {
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

.appointments-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.appointment-item {
    border: 1px solid #f1f1f1;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.appointment-item:hover {
    border-color: #667eea;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.1);
}

.appointment-header {
    display: flex;
    justify-content: between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.appointment-time {
    font-size: 1.2rem;
    font-weight: bold;
    color: #667eea;
}

.appointment-date {
    color: #666;
    font-size: 0.9rem;
}

.patient-info {
    margin-bottom: 15px;
}

.patient-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.patient-details {
    color: #666;
    font-size: 0.9rem;
}

.appointment-reason {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-style: italic;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-scheduled { background: #e3f2fd; color: #1565c0; }
.status-confirmed { background: #e8f5e8; color: #2e7d32; }
.status-completed { background: #f3e5f5; color: #7b1fa2; }
.status-cancelled { background: #ffebee; color: #c62828; }
.status-no-show { background: #fff3e0; color: #ef6c00; }

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

.no-appointments {
    text-align: center;
    padding: 60px;
    color: #888;
}

.no-appointments i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .appointments-container {
        padding: 15px;
    }
    
    .appointment-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
    
    .stat-number {
        font-size: 1.4rem;
    }
}
</style>

<div class="appointments-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-calendar-check me-2"></i>My Appointments</h1>
            <p class="text-muted mb-0">Manage your appointment schedule</p>
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
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['scheduled']; ?></div>
                    <div class="stat-label">Scheduled</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['confirmed']; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['no_show']; ?></div>
                    <div class="stat-label">No Show</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Appointments</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="no-show" <?php echo $status_filter === 'no-show' ? 'selected' : ''; ?>>No Show</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Patient Search</label>
                <input type="text" class="form-control" name="patient" 
                       placeholder="Search by name or patient ID" 
                       value="<?php echo htmlspecialchars($patient_search); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Appointments List -->
    <div class="appointments-card">
        <h5 class="mb-4">
            <i class="fas fa-list me-2"></i>Appointments 
            <?php if (!empty($appointments)): ?>
                <span class="badge bg-primary"><?php echo count($appointments); ?></span>
            <?php endif; ?>
        </h5>

        <?php if (!empty($appointments)): ?>
            <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-item">
                    <div class="appointment-header">
                        <div class="flex-grow-1">
                            <div class="appointment-time">
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <div class="appointment-date">
                                <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                <?php echo ucfirst(str_replace('-', ' ', $appointment['status'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="patient-info">
                        <div class="patient-name">
                            <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                        </div>
                        <div class="patient-details">
                            <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($appointment['patient_id']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['phone']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-birthday-cake me-1"></i><?php echo date('M j, Y', strtotime($appointment['date_of_birth'])); ?>
                        </div>
                    </div>

                    <?php if (!empty($appointment['reason'])): ?>
                        <div class="appointment-reason">
                            <i class="fas fa-notes-medical me-2"></i>
                            <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($appointment['notes'])): ?>
                        <div class="appointment-reason">
                            <i class="fas fa-clipboard me-2"></i>
                            <strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <?php if ($appointment['status'] === 'scheduled'): ?>
                            <button class="btn btn-success btn-sm-custom" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')">
                                <i class="fas fa-check me-1"></i>Confirm
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($appointment['status'], ['scheduled', 'confirmed'])): ?>
                            <button class="btn btn-primary btn-sm-custom" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'completed')">
                                <i class="fas fa-check-double me-1"></i>Complete
                            </button>
                            <button class="btn btn-warning btn-sm-custom" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'no-show')">
                                <i class="fas fa-user-times me-1"></i>No Show
                            </button>
                            <button class="btn btn-danger btn-sm-custom" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled')">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-info btn-sm-custom" onclick="addNotes(<?php echo $appointment['id']; ?>)">
                            <i class="fas fa-sticky-note me-1"></i>Add Notes
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-appointments">
                <i class="fas fa-calendar-times"></i>
                <h5>No appointments found</h5>
                <p>No appointments match your current filters.</p>
                <?php if (!empty($date_filter) || !empty($status_filter) || !empty($patient_search)): ?>
                    <a href="doctor_appointments.php" class="btn btn-outline-primary">
                        <i class="fas fa-refresh me-1"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Appointment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="statusForm">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="modalAppointmentId">
                    <input type="hidden" name="status" id="modalStatus">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="Add any notes about this appointment..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="statusMessage"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="notesForm">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="notesAppointmentId">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="4" 
                                  placeholder="Add notes about this appointment..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Notes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateStatus(appointmentId, newStatus) {
    document.getElementById('modalAppointmentId').value = appointmentId;
    document.getElementById('modalStatus').value = newStatus;
    
    const statusMessages = {
        'confirmed': 'This will confirm the appointment.',
        'completed': 'This will mark the appointment as completed.',
        'cancelled': 'This will cancel the appointment.',
        'no-show': 'This will mark the patient as no-show.'
    };
    
    document.getElementById('statusMessage').textContent = statusMessages[newStatus] || 'This will update the appointment status.';
    
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

function addNotes(appointmentId) {
    document.getElementById('notesAppointmentId').value = appointmentId;
    new bootstrap.Modal(document.getElementById('notesModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
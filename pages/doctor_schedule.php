<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'My Schedule';
$db = new Database();

// Get doctor information
$db->query("SELECT d.* FROM doctors d WHERE d.user_id = :user_id");
$db->bind(':user_id', $_SESSION['user_id']);
$doctor_info = $db->single();

if (!$doctor_info) {
    $_SESSION['error'] = 'Doctor profile not found.';
    redirect('../login.php');
}

// Handle schedule updates
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_availability') {
            $day_of_week = $_POST['day_of_week'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $appointment_duration = $_POST['appointment_duration'] ?? 30;
            
            // Check if availability already exists for this day
            $db->query("SELECT id FROM doctor_availability WHERE doctor_id = :doctor_id AND day_of_week = :day_of_week");
            $db->bind(':doctor_id', $doctor_info['id']);
            $db->bind(':day_of_week', $day_of_week);
            $existing = $db->single();
            
            if ($existing) {
                // Update existing
                $db->query("UPDATE doctor_availability SET start_time = :start_time, end_time = :end_time, appointment_duration = :duration WHERE id = :id");
                $db->bind(':start_time', $start_time);
                $db->bind(':end_time', $end_time);
                $db->bind(':duration', $appointment_duration);
                $db->bind(':id', $existing['id']);
                $db->execute();
                $message = "Schedule updated successfully!";
            } else {
                // Insert new
                $db->query("INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, appointment_duration) VALUES (:doctor_id, :day_of_week, :start_time, :end_time, :duration)");
                $db->bind(':doctor_id', $doctor_info['id']);
                $db->bind(':day_of_week', $day_of_week);
                $db->bind(':start_time', $start_time);
                $db->bind(':end_time', $end_time);
                $db->bind(':duration', $appointment_duration);
                $db->execute();
                $message = "Availability added successfully!";
            }
            
            $_SESSION['success'] = $message;
            redirect('doctor_schedule.php');
        }
        
        if ($_POST['action'] === 'delete_availability') {
            $availability_id = $_POST['availability_id'];
            $db->query("DELETE FROM doctor_availability WHERE id = :id AND doctor_id = :doctor_id");
            $db->bind(':id', $availability_id);
            $db->bind(':doctor_id', $doctor_info['id']);
            $db->execute();
            
            $_SESSION['success'] = "Availability removed successfully!";
            redirect('doctor_schedule.php');
        }
        
        if ($_POST['action'] === 'block_time') {
            $block_date = $_POST['block_date'];
            $start_time = $_POST['block_start_time'];
            $end_time = $_POST['block_end_time'];
            $reason = $_POST['block_reason'];
            
            $db->query("INSERT INTO doctor_blocks (doctor_id, block_date, start_time, end_time, reason) VALUES (:doctor_id, :block_date, :start_time, :end_time, :reason)");
            $db->bind(':doctor_id', $doctor_info['id']);
            $db->bind(':block_date', $block_date);
            $db->bind(':start_time', $start_time);
            $db->bind(':end_time', $end_time);
            $db->bind(':reason', $reason);
            $db->execute();
            
            $_SESSION['success'] = "Time blocked successfully!";
            redirect('doctor_schedule.php');
        }
        
    } catch (Exception $e) {
        $error_message = 'Error updating schedule: ' . $e->getMessage();
    }
}

// Get current week dates
$current_date = new DateTime();
$week_start = clone $current_date;
$week_start->modify('this week');
$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = clone $week_start;
    $week_start->add(new DateInterval('P1D'));
}

// Get doctor availability
try {
    $db->query("SELECT * FROM doctor_availability WHERE doctor_id = :doctor_id ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')");
    $db->bind(':doctor_id', $doctor_info['id']);
    $availability = $db->resultSet();
    
    // Get appointments for this week
    $week_start_str = $week_dates[0]->format('Y-m-d');
    $week_end_str = $week_dates[6]->format('Y-m-d');
    
    $db->query("SELECT a.*, p.first_name, p.last_name, p.phone
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = :doctor_id 
                AND a.appointment_date BETWEEN :start_date AND :end_date
                ORDER BY a.appointment_date, a.appointment_time");
    $db->bind(':doctor_id', $doctor_info['id']);
    $db->bind(':start_date', $week_start_str);
    $db->bind(':end_date', $week_end_str);
    $week_appointments = $db->resultSet();
    
    // Get blocked times for this week
    $db->query("SELECT * FROM doctor_blocks WHERE doctor_id = :doctor_id AND block_date BETWEEN :start_date AND :end_date ORDER BY block_date, start_time");
    $db->bind(':doctor_id', $doctor_info['id']);
    $db->bind(':start_date', $week_start_str);
    $db->bind(':end_date', $week_end_str);
    $blocked_times = $db->resultSet();
    
} catch (Exception $e) {
    $error_message = 'Error loading schedule: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.schedule-container {
    padding: 20px;
}

.availability-card, .schedule-card, .blocks-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.availability-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.availability-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.availability-item:hover {
    border-color: #667eea;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
}

.day-name {
    font-weight: bold;
    color: #333;
    text-transform: capitalize;
}

.time-range {
    color: #667eea;
    font-weight: 500;
}

.duration-badge {
    background: #e3f2fd;
    color: #1565c0;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
}

.schedule-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #ddd;
    border-radius: 10px;
    overflow: hidden;
}

.day-header {
    background: #667eea;
    color: white;
    padding: 15px 8px;
    text-align: center;
    font-weight: bold;
}

.day-cell {
    background: white;
    padding: 15px 8px;
    min-height: 300px;
    position: relative;
}

.day-cell.today {
    background: #f0f7ff;
}

.day-date {
    font-weight: bold;
    margin-bottom: 10px;
    text-align: center;
}

.appointment-slot {
    background: #e8f5e8;
    border: 1px solid #4caf50;
    border-radius: 4px;
    padding: 6px;
    margin-bottom: 4px;
    font-size: 0.8rem;
}

.appointment-confirmed {
    background: #e3f2fd;
    border-color: #2196f3;
}

.appointment-completed {
    background: #f3e5f5;
    border-color: #9c27b0;
}

.appointment-cancelled {
    background: #ffebee;
    border-color: #f44336;
}

.blocked-slot {
    background: #fff3e0;
    border: 1px solid #ff9800;
    border-radius: 4px;
    padding: 6px;
    margin-bottom: 4px;
    font-size: 0.8rem;
    font-style: italic;
}

.no-availability {
    color: #999;
    font-style: italic;
    text-align: center;
    padding: 20px;
}

.btn-sm-custom {
    padding: 4px 8px;
    font-size: 0.7rem;
}

@media (max-width: 768px) {
    .schedule-container {
        padding: 15px;
    }
    
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    
    .day-header {
        display: none;
    }
    
    .day-cell {
        margin-bottom: 15px;
        border-radius: 8px;
        border: 1px solid #ddd;
    }
    
    .day-date {
        background: #667eea;
        color: white;
        padding: 10px;
        margin: -15px -8px 10px;
        border-radius: 8px 8px 0 0;
    }
}
</style>

<div class="schedule-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-calendar-alt me-2"></i>My Schedule</h1>
            <p class="text-muted mb-0">Manage your availability and appointments</p>
        </div>
        <div>
            <a href="doctor_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Availability Management -->
    <div class="availability-card">
        <h5 class="mb-4"><i class="fas fa-clock me-2"></i>Weekly Availability</h5>
        
        <!-- Add/Update Availability Form -->
        <div class="availability-form">
            <h6 class="mb-3">Set Availability</h6>
            <form method="POST">
                <input type="hidden" name="action" value="add_availability">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Day of Week</label>
                        <select class="form-select" name="day_of_week" required>
                            <option value="">Select Day</option>
                            <option value="monday">Monday</option>
                            <option value="tuesday">Tuesday</option>
                            <option value="wednesday">Wednesday</option>
                            <option value="thursday">Thursday</option>
                            <option value="friday">Friday</option>
                            <option value="saturday">Saturday</option>
                            <option value="sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Duration (minutes)</label>
                        <select class="form-select" name="appointment_duration">
                            <option value="15">15 minutes</option>
                            <option value="30" selected>30 minutes</option>
                            <option value="45">45 minutes</option>
                            <option value="60">60 minutes</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Availability
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Current Availability -->
        <div class="current-availability">
            <h6 class="mb-3">Current Availability</h6>
            <?php if (!empty($availability)): ?>
                <?php foreach ($availability as $avail): ?>
                    <div class="availability-item">
                        <div>
                            <span class="day-name"><?php echo ucfirst($avail['day_of_week']); ?></span>
                            <span class="mx-2">•</span>
                            <span class="time-range">
                                <?php echo date('g:i A', strtotime($avail['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($avail['end_time'])); ?>
                            </span>
                            <span class="mx-2">•</span>
                            <span class="duration-badge"><?php echo $avail['appointment_duration']; ?> min slots</span>
                        </div>
                        <div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_availability">
                                <input type="hidden" name="availability_id" value="<?php echo $avail['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm-custom" 
                                        onclick="return confirm('Remove this availability?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-availability">
                    <i class="fas fa-calendar-times"></i>
                    <p>No availability set. Please add your working hours above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Weekly Schedule View -->
    <div class="schedule-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Weekly Schedule</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="previousWeek()">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="nextWeek()">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <div class="schedule-grid">
            <?php 
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($days as $day): ?>
                <div class="day-header"><?php echo $day; ?></div>
            <?php endforeach; ?>

            <?php foreach ($week_dates as $date): ?>
                <div class="day-cell <?php echo $date->format('Y-m-d') === date('Y-m-d') ? 'today' : ''; ?>">
                    <div class="day-date">
                        <?php echo $date->format('M j'); ?>
                    </div>
                    
                    <?php
                    $date_str = $date->format('Y-m-d');
                    $day_name = strtolower($date->format('l'));
                    
                    // Show appointments for this day
                    foreach ($week_appointments as $appointment) {
                        if ($appointment['appointment_date'] === $date_str) {
                            $status_class = 'appointment-' . str_replace('-', '', $appointment['status']);
                            echo '<div class="appointment-slot ' . $status_class . '">';
                            echo '<strong>' . date('g:i A', strtotime($appointment['appointment_time'])) . '</strong><br>';
                            echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']);
                            echo '</div>';
                        }
                    }
                    
                    // Show blocked times for this day
                    foreach ($blocked_times as $block) {
                        if ($block['block_date'] === $date_str) {
                            echo '<div class="blocked-slot">';
                            echo '<strong>BLOCKED</strong><br>';
                            echo date('g:i A', strtotime($block['start_time'])) . ' - ' . date('g:i A', strtotime($block['end_time']));
                            if (!empty($block['reason'])) {
                                echo '<br><em>' . htmlspecialchars($block['reason']) . '</em>';
                            }
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Block Time -->
    <div class="blocks-card">
        <h5 class="mb-4"><i class="fas fa-ban me-2"></i>Block Time</h5>
        
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="block_time">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="block_date" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Start Time</label>
                <input type="time" class="form-control" name="block_start_time" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">End Time</label>
                <input type="time" class="form-control" name="block_end_time" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reason</label>
                <input type="text" class="form-control" name="block_reason" placeholder="e.g., Meeting, Break, Surgery">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-ban me-1"></i>Block Time
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previousWeek() {
    // Implement week navigation
    window.location.href = '?week=' + (getCurrentWeek() - 1);
}

function nextWeek() {
    // Implement week navigation
    window.location.href = '?week=' + (getCurrentWeek() + 1);
}

function getCurrentWeek() {
    // Get current week number from URL or default to current week
    const urlParams = new URLSearchParams(window.location.search);
    return parseInt(urlParams.get('week')) || <?php echo date('W'); ?>;
}
</script>

<?php include '../includes/footer.php'; ?>
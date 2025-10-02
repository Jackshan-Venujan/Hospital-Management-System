<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

$doctor_id = $_GET['id'] ?? null;

if (!$doctor_id) {
    echo '<div class="alert alert-danger">Doctor ID not provided</div>';
    exit;
}

try {
    // Get doctor details
    $db->query('
        SELECT d.*, u.username, u.email as user_email, u.status as user_status
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.id = :id
    ');
    $db->bind(':id', $doctor_id);
    $doctor = $db->single();
    
    if (!$doctor) {
        echo '<div class="alert alert-danger">Doctor not found</div>';
        exit;
    }
    
    // Get departments for dropdown
    $db->query('SELECT * FROM departments ORDER BY name');
    $departments = $db->resultSet();
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading doctor details: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<form method="POST" action="doctors.php" id="editDoctorForm">
    <input type="hidden" name="doctor_db_id" value="<?php echo $doctor['id']; ?>">
    
    <div class="row">
        <!-- Personal Information -->
        <div class="col-12">
            <h6 class="text-primary mb-3">Personal Information</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">First Name *</label>
            <input type="text" class="form-control" name="first_name" 
                   value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-control" name="last_name" 
                   value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Phone *</label>
            <input type="tel" class="form-control" name="phone" 
                   value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Email *</label>
            <input type="email" class="form-control" name="email" 
                   value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
        </div>
        
        <!-- Professional Information -->
        <div class="col-12 mt-3">
            <h6 class="text-primary mb-3">Professional Information</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Specialization *</label>
            <input type="text" class="form-control" name="specialization" 
                   value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Department</label>
            <select class="form-select" name="department_id">
                <option value="">Select Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>" 
                            <?php echo $doctor['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Qualification *</label>
            <input type="text" class="form-control" name="qualification" 
                   value="<?php echo htmlspecialchars($doctor['qualification']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Experience (Years) *</label>
            <input type="number" class="form-control" name="experience_years" 
                   value="<?php echo $doctor['experience_years']; ?>" min="0" max="50" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Consultation Fee ($) *</label>
            <input type="number" class="form-control" name="consultation_fee" 
                   value="<?php echo $doctor['consultation_fee']; ?>" step="0.01" min="0" required>
        </div>
        
        <!-- Schedule Information -->
        <div class="col-12 mt-3">
            <h6 class="text-primary mb-3">Schedule Information</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Schedule Start Time</label>
            <input type="time" class="form-control" name="schedule_start" 
                   value="<?php echo $doctor['schedule_start']; ?>">
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Schedule End Time</label>
            <input type="time" class="form-control" name="schedule_end" 
                   value="<?php echo $doctor['schedule_end']; ?>">
        </div>
        
        <div class="col-12 mb-3">
            <label class="form-label">Available Days</label>
            <div class="row">
                <?php
                $available_days = explode(',', $doctor['available_days']);
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($days as $day):
                ?>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="available_days[]" 
                                   value="<?php echo $day; ?>" id="edit_<?php echo strtolower($day); ?>"
                                   <?php echo in_array($day, $available_days) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="edit_<?php echo strtolower($day); ?>">
                                <?php echo $day; ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Account Information (Read Only) -->
        <div class="col-12 mt-3">
            <h6 class="text-primary mb-3">Account Information</h6>
        </div>
        
        <div class="col-md-4 mb-3">
            <label class="form-label">Employee ID</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($doctor['employee_id']); ?>" readonly>
            <small class="text-muted">Employee ID cannot be changed</small>
        </div>
        
        <div class="col-md-4 mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($doctor['username']); ?>" readonly>
            <small class="text-muted">Username cannot be changed</small>
        </div>
        
        <div class="col-md-4 mb-3">
            <label class="form-label">Account Status</label>
            <div>
                <?php if ($doctor['user_status'] === 'active'): ?>
                    <span class="badge bg-success fs-6">Active</span>
                <?php else: ?>
                    <span class="badge bg-secondary fs-6">Inactive</span>
                <?php endif; ?>
            </div>
            <small class="text-muted">
                <a href="doctors.php?toggle_status=1&doctor_id=<?php echo $doctor['id']; ?>" 
                   onclick="return confirm('Are you sure you want to change the account status?')">
                    Click here to <?php echo $doctor['user_status'] === 'active' ? 'deactivate' : 'activate'; ?>
                </a>
            </small>
        </div>
        
        <!-- Additional Information -->
        <div class="col-12 mt-3">
            <div class="alert alert-info">
                <h6 class="alert-heading">
                    <i class="fas fa-info-circle me-2"></i>Additional Information
                </h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Registration Date:</strong> <?php echo format_date($doctor['created_at']); ?></p>
                        <p class="mb-0"><strong>Last Updated:</strong> <?php echo format_date($doctor['updated_at']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Profile Status:</strong> 
                            <?php echo $doctor['user_status'] === 'active' ? 
                                '<span class="text-success">Active & Available</span>' : 
                                '<span class="text-warning">Inactive - Not Available for Appointments</span>'; ?>
                        </p>
                        <p class="mb-0"><strong>Account Type:</strong> <span class="badge bg-primary">Doctor</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_doctor" class="btn btn-warning">
            <i class="fas fa-save me-1"></i>Update Doctor
        </button>
    </div>
</form>
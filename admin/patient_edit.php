<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

$patient_id = $_GET['id'] ?? null;

if (!$patient_id) {
    echo '<div class="alert alert-danger">Patient ID not provided</div>';
    exit;
}

try {
    // Get patient details
    $db->query('
        SELECT p.*, u.username, u.email as user_email, u.status as user_status
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = :id
    ');
    $db->bind(':id', $patient_id);
    $patient = $db->single();
    
    if (!$patient) {
        echo '<div class="alert alert-danger">Patient not found</div>';
        exit;
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading patient details: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<form method="POST" action="patients.php" id="editPatientForm">
    <input type="hidden" name="patient_db_id" value="<?php echo $patient['id']; ?>">
    
    <div class="row">
        <!-- Personal Information -->
        <div class="col-12">
            <h6 class="text-primary mb-3">Personal Information</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">First Name *</label>
            <input type="text" class="form-control" name="first_name" 
                   value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-control" name="last_name" 
                   value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Date of Birth *</label>
            <input type="date" class="form-control" name="date_of_birth" 
                   value="<?php echo $patient['date_of_birth']; ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Gender *</label>
            <select class="form-select" name="gender" required>
                <option value="">Select Gender</option>
                <option value="Male" <?php echo $patient['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $patient['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo $patient['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        
        <!-- Contact Information -->
        <div class="col-12 mt-3">
            <h6 class="text-primary mb-3">Contact Information</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Phone *</label>
            <input type="tel" class="form-control" name="phone" 
                   value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Email *</label>
            <input type="email" class="form-control" name="email" 
                   value="<?php echo htmlspecialchars($patient['email']); ?>" required>
        </div>
        
        <div class="col-12 mb-3">
            <label class="form-label">Address</label>
            <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($patient['address']); ?></textarea>
        </div>
        
        <!-- Emergency Contact -->
        <div class="col-12 mt-3">
            <h6 class="text-primary mb-3">Emergency Contact</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Contact Name</label>
            <input type="text" class="form-control" name="emergency_contact_name" 
                   value="<?php echo htmlspecialchars($patient['emergency_contact_name']); ?>">
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Contact Phone</label>
            <input type="tel" class="form-control" name="emergency_contact_phone" 
                   value="<?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>">
        </div>
        
        <!-- Medical Information -->
        <div class="col-12 mt-3">
            <h6 class="text-primary mb-3">Medical Information</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Blood Group</label>
            <select class="form-select" name="blood_group">
                <option value="">Select Blood Group</option>
                <option value="A+" <?php echo $patient['blood_group'] === 'A+' ? 'selected' : ''; ?>>A+</option>
                <option value="A-" <?php echo $patient['blood_group'] === 'A-' ? 'selected' : ''; ?>>A-</option>
                <option value="B+" <?php echo $patient['blood_group'] === 'B+' ? 'selected' : ''; ?>>B+</option>
                <option value="B-" <?php echo $patient['blood_group'] === 'B-' ? 'selected' : ''; ?>>B-</option>
                <option value="AB+" <?php echo $patient['blood_group'] === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                <option value="AB-" <?php echo $patient['blood_group'] === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                <option value="O+" <?php echo $patient['blood_group'] === 'O+' ? 'selected' : ''; ?>>O+</option>
                <option value="O-" <?php echo $patient['blood_group'] === 'O-' ? 'selected' : ''; ?>>O-</option>
            </select>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Insurance Number</label>
            <input type="text" class="form-control" name="insurance_number" 
                   value="<?php echo htmlspecialchars($patient['insurance_number']); ?>">
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Known Allergies</label>
            <textarea class="form-control" name="allergies" rows="3"><?php echo htmlspecialchars($patient['allergies']); ?></textarea>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Medical History</label>
            <textarea class="form-control" name="medical_history" rows="3"><?php echo htmlspecialchars($patient['medical_history']); ?></textarea>
        </div>
        
        <!-- Account Information (Read Only) -->
        <div class="col-12 mt-3">
            <h6 class="text-primary mb-3">Account Information</h6>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Patient ID</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient['patient_id']); ?>" readonly>
            <small class="text-muted">Patient ID cannot be changed</small>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient['username']); ?>" readonly>
            <small class="text-muted">Username cannot be changed</small>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Account Status</label>
            <div>
                <?php if ($patient['user_status'] === 'active'): ?>
                    <span class="badge bg-success fs-6">Active</span>
                <?php else: ?>
                    <span class="badge bg-secondary fs-6">Inactive</span>
                <?php endif; ?>
            </div>
            <small class="text-muted">
                <a href="patients.php?toggle_status=1&patient_id=<?php echo $patient['id']; ?>" 
                   onclick="return confirm('Are you sure you want to change the account status?')">
                    Click here to <?php echo $patient['user_status'] === 'active' ? 'deactivate' : 'activate'; ?>
                </a>
            </small>
        </div>
    </div>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_patient" class="btn btn-warning">
            <i class="fas fa-save me-1"></i>Update Patient
        </button>
    </div>
</form>

<script>
// Set max date for date of birth to today
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.querySelector('#editPatientForm input[name="date_of_birth"]');
    const today = new Date().toISOString().split('T')[0];
    dobInput.max = today;
});
</script>
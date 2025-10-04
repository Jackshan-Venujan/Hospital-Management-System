<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

$page_title = 'My Profile';
$db = new Database();

// Get doctor information
$db->query("SELECT d.*, u.username, u.email, u.created_at 
            FROM doctors d 
            JOIN users u ON d.user_id = u.id 
            WHERE d.user_id = :user_id");
$db->bind(':user_id', $_SESSION['user_id']);
$doctor_info = $db->single();

// Set default values for missing fields
if (!isset($doctor_info['department']) || empty($doctor_info['department'])) {
    $doctor_info['department'] = 'General Medicine';
}
if (!isset($doctor_info['experience']) || empty($doctor_info['experience'])) {
    $doctor_info['experience'] = 0;
}
if (!isset($doctor_info['consultation_fee']) || empty($doctor_info['consultation_fee'])) {
    $doctor_info['consultation_fee'] = 0.00;
}
if (!isset($doctor_info['qualifications']) || empty($doctor_info['qualifications'])) {
    $doctor_info['qualifications'] = '';
}
if (!isset($doctor_info['bio']) || empty($doctor_info['bio'])) {
    $doctor_info['bio'] = '';
}

if (!$doctor_info) {
    $_SESSION['error'] = 'Doctor profile not found.';
    redirect('../login.php');
}

// Handle profile updates
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_profile') {
            // Update doctor information
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $specialization = trim($_POST['specialization']);
            $phone = trim($_POST['phone']);
            $department = trim($_POST['department']);
            $qualifications = trim($_POST['qualifications']);
            $experience = (int)$_POST['experience'];
            $consultation_fee = (float)$_POST['consultation_fee'];
            $bio = trim($_POST['bio']);
            
            $db->query("UPDATE doctors SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        specialization = :specialization, 
                        phone = :phone,
                        department = :department,
                        qualifications = :qualifications,
                        experience = :experience,
                        consultation_fee = :consultation_fee,
                        bio = :bio
                        WHERE user_id = :user_id");
            
            $db->bind(':first_name', $first_name);
            $db->bind(':last_name', $last_name);
            $db->bind(':specialization', $specialization);
            $db->bind(':phone', $phone);
            $db->bind(':department', $department);
            $db->bind(':qualifications', $qualifications);
            $db->bind(':experience', $experience);
            $db->bind(':consultation_fee', $consultation_fee);
            $db->bind(':bio', $bio);
            $db->bind(':user_id', $_SESSION['user_id']);
            
            if ($db->execute()) {
                $_SESSION['success'] = 'Profile updated successfully!';
                redirect('doctor_profile.php');
            } else {
                throw new Exception('Failed to update profile.');
            }
        }
        
        if ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password
            $db->query("SELECT password FROM users WHERE id = :user_id");
            $db->bind(':user_id', $_SESSION['user_id']);
            $user = $db->single();
            
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('New password must be at least 6 characters long.');
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password = :password WHERE id = :user_id");
            $db->bind(':password', $hashed_password);
            $db->bind(':user_id', $_SESSION['user_id']);
            
            if ($db->execute()) {
                $_SESSION['success'] = 'Password changed successfully!';
                redirect('doctor_profile.php');
            } else {
                throw new Exception('Failed to update password.');
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get statistics
try {
    $db->query("SELECT 
                    COUNT(*) as total_appointments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
                    COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_appointments,
                    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_appointments
                FROM appointments WHERE doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $appointment_stats = $db->single();
    
    // Get patient count
    $db->query("SELECT COUNT(DISTINCT patient_id) as total_patients FROM appointments WHERE doctor_id = :doctor_id");
    $db->bind(':doctor_id', $doctor_info['id']);
    $patient_count = $db->single()['total_patients'];
    
    // Get recent reviews/ratings if you have a reviews system
    // For now, we'll use placeholder data
    $average_rating = 4.5;
    $total_reviews = 23;
    
} catch (Exception $e) {
    $stats_error = 'Error loading statistics: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<style>
.profile-container {
    padding: 20px;
}

.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin-bottom: 20px;
}

.profile-info h2 {
    margin-bottom: 10px;
    font-weight: 600;
}

.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    text-align: center;
    border-left: 4px solid #667eea;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.profile-tabs {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.nav-tabs {
    border-bottom: 2px solid #f1f1f1;
    padding: 0 20px;
}

.nav-tabs .nav-link {
    border: none;
    color: #666;
    font-weight: 500;
    padding: 15px 20px;
    border-bottom: 3px solid transparent;
}

.nav-tabs .nav-link.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: none;
}

.tab-content {
    padding: 30px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h6 {
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #f1f1f1;
}

.rating-display {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stars {
    color: #ffc107;
    font-size: 1.2rem;
}

.profile-image-upload {
    text-align: center;
    margin-bottom: 20px;
}

.image-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 3px dashed #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.image-preview:hover {
    border-color: #667eea;
    background: #f0f7ff;
}

.image-preview i {
    font-size: 2rem;
    color: #999;
}

@media (max-width: 768px) {
    .profile-container {
        padding: 15px;
    }
    
    .profile-header {
        padding: 20px;
        text-align: center;
    }
    
    .profile-meta {
        justify-content: center;
        flex-direction: column;
        align-items: center;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .tab-content {
        padding: 20px;
    }
}
</style>

<div class="profile-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-user-md me-2"></i>My Profile</h1>
            <p class="text-muted mb-0">Manage your professional profile and settings</p>
        </div>
        <div>
            <a href="doctor_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="profile-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']); ?></h2>
                    <p class="mb-1"><?php echo htmlspecialchars($doctor_info['specialization']); ?></p>
                    <p class="mb-0"><?php echo htmlspecialchars($doctor_info['department']); ?></p>
                    
                    <div class="profile-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Joined <?php echo date('M Y', strtotime($doctor_info['created_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo ($doctor_info['experience'] == 0 || empty($doctor_info['experience'])) ? 'Experience not specified' : $doctor_info['experience'] . ' years experience'; ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-star"></i>
                            <span><?php echo $average_rating; ?>/5.0 (<?php echo $total_reviews; ?> reviews)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="mb-2">
                    <strong>Consultation Fee</strong>
                </div>
                <div class="h4 mb-0">
                    Rs. <?php echo number_format($doctor_info['consultation_fee'], 2); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo $appointment_stats['total_appointments']; ?></div>
            <div class="stat-label">Total Appointments</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $appointment_stats['completed_appointments']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $patient_count; ?></div>
            <div class="stat-label">Patients Treated</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $appointment_stats['scheduled_appointments'] + $appointment_stats['confirmed_appointments']; ?></div>
            <div class="stat-label">Upcoming Appointments</div>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Profile Tabs -->
    <div class="profile-tabs">
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" 
                        type="button" role="tab" aria-controls="profile" aria-selected="true">
                    <i class="fas fa-user me-2"></i>Profile Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" 
                        type="button" role="tab" aria-controls="security" aria-selected="false">
                    <i class="fas fa-shield-alt me-2"></i>Security
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" 
                        type="button" role="tab" aria-controls="preferences" aria-selected="false">
                    <i class="fas fa-cog me-2"></i>Preferences
                </button>
            </li>
        </ul>

        <div class="tab-content" id="profileTabsContent">
            <!-- Profile Information Tab -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-section">
                        <h6><i class="fas fa-user me-2"></i>Personal Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?php echo htmlspecialchars($doctor_info['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?php echo htmlspecialchars($doctor_info['last_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($doctor_info['phone']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($doctor_info['email']); ?>" readonly>
                                <small class="text-muted">Email cannot be changed. Contact administrator.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h6><i class="fas fa-stethoscope me-2"></i>Professional Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-control" name="specialization" 
                                       value="<?php echo htmlspecialchars($doctor_info['specialization']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Cardiology" <?php echo $doctor_info['department'] === 'Cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                                    <option value="Neurology" <?php echo $doctor_info['department'] === 'Neurology' ? 'selected' : ''; ?>>Neurology</option>
                                    <option value="Orthopedics" <?php echo $doctor_info['department'] === 'Orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                                    <option value="Pediatrics" <?php echo $doctor_info['department'] === 'Pediatrics' ? 'selected' : ''; ?>>Pediatrics</option>
                                    <option value="General Medicine" <?php echo $doctor_info['department'] === 'General Medicine' ? 'selected' : ''; ?>>General Medicine</option>
                                    <option value="Surgery" <?php echo $doctor_info['department'] === 'Surgery' ? 'selected' : ''; ?>>Surgery</option>
                                    <option value="Dermatology" <?php echo $doctor_info['department'] === 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                                    <option value="Psychiatry" <?php echo $doctor_info['department'] === 'Psychiatry' ? 'selected' : ''; ?>>Psychiatry</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Qualifications</label>
                                <input type="text" class="form-control" name="qualifications" 
                                       value="<?php echo htmlspecialchars($doctor_info['qualifications']); ?>"
                                       placeholder="e.g., MBBS, MD, Fellowship...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" name="experience" 
                                       value="<?php echo $doctor_info['experience']; ?>" min="0" max="50">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Consultation Fee (Rs.)</label>
                                <input type="number" class="form-control" name="consultation_fee" 
                                       value="<?php echo $doctor_info['consultation_fee']; ?>" 
                                       step="0.01" min="0">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Professional Bio</label>
                                <textarea class="form-control" name="bio" rows="4" 
                                          placeholder="Brief description about yourself and your expertise..."><?php echo htmlspecialchars($doctor_info['bio']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Tab -->
            <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                <div class="form-section">
                    <h6><i class="fas fa-key me-2"></i>Change Password</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" minlength="6" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" minlength="6" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key me-1"></i>Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="form-section">
                    <h6><i class="fas fa-shield-alt me-2"></i>Account Security</h6>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Your account is protected with industry-standard security measures. 
                        Always use a strong password and never share your login credentials.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Two-Factor Authentication</span>
                                <span class="badge bg-warning">Not Enabled</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Last Login</span>
                                <span class="text-muted"><?php echo date('M j, Y g:i A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                <div class="form-section">
                    <h6><i class="fas fa-bell me-2"></i>Notification Preferences</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                <label class="form-check-label" for="emailNotifications">
                                    Email notifications for new appointments
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="smsNotifications" checked>
                                <label class="form-check-label" for="smsNotifications">
                                    SMS notifications for appointment reminders
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="dashboardNotifications" checked>
                                <label class="form-check-label" for="dashboardNotifications">
                                    Dashboard notifications for urgent updates
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6><i class="fas fa-clock me-2"></i>Schedule Preferences</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Default Appointment Duration</label>
                            <select class="form-select">
                                <option value="15">15 minutes</option>
                                <option value="30" selected>30 minutes</option>
                                <option value="45">45 minutes</option>
                                <option value="60">60 minutes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time Zone</label>
                            <select class="form-select">
                                <option value="Asia/Colombo" selected>Asia/Colombo (Sri Lanka)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Preferences
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.querySelector('input[name="new_password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    if (newPassword && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
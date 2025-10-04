<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$page_title = 'System Settings';
$db = new Database();
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['update_hospital_info'])) {
            // Update hospital information
            $hospital_name = trim($_POST['hospital_name']);
            $hospital_address = trim($_POST['hospital_address']);
            $hospital_phone = trim($_POST['hospital_phone']);
            $hospital_email = trim($_POST['hospital_email']);
            $hospital_website = trim($_POST['hospital_website']);
            
            // Create or update hospital settings
            $settings = [
                'hospital_name' => $hospital_name,
                'hospital_address' => $hospital_address,
                'hospital_phone' => $hospital_phone,
                'hospital_email' => $hospital_email,
                'hospital_website' => $hospital_website
            ];
            
            foreach ($settings as $key => $value) {
                $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) 
                           ON DUPLICATE KEY UPDATE setting_value = :value2");
                $db->bind(':key', $key);
                $db->bind(':value', $value);
                $db->bind(':value2', $value);
                $db->execute();
            }
            
            $success_message = 'Hospital information updated successfully!';
        }
        
        if (isset($_POST['update_system_settings'])) {
            // Update system settings
            $timezone = $_POST['timezone'];
            $date_format = $_POST['date_format'];
            $currency_symbol = $_POST['currency_symbol'];
            $appointment_duration = $_POST['appointment_duration'];
            $working_hours_start = $_POST['working_hours_start'];
            $working_hours_end = $_POST['working_hours_end'];
            
            $system_settings = [
                'timezone' => $timezone,
                'date_format' => $date_format,
                'currency_symbol' => $currency_symbol,
                'appointment_duration' => $appointment_duration,
                'working_hours_start' => $working_hours_start,
                'working_hours_end' => $working_hours_end
            ];
            
            foreach ($system_settings as $key => $value) {
                $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) 
                           ON DUPLICATE KEY UPDATE setting_value = :value2");
                $db->bind(':key', $key);
                $db->bind(':value', $value);
                $db->bind(':value2', $value);
                $db->execute();
            }
            
            $success_message = 'System settings updated successfully!';
        }
        
        if (isset($_POST['update_security_settings'])) {
            // Update security settings
            $password_min_length = $_POST['password_min_length'];
            $session_timeout = $_POST['session_timeout'];
            $max_login_attempts = $_POST['max_login_attempts'];
            $backup_frequency = $_POST['backup_frequency'];
            
            $security_settings = [
                'password_min_length' => $password_min_length,
                'session_timeout' => $session_timeout,
                'max_login_attempts' => $max_login_attempts,
                'backup_frequency' => $backup_frequency
            ];
            
            foreach ($security_settings as $key => $value) {
                $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) 
                           ON DUPLICATE KEY UPDATE setting_value = :value2");
                $db->bind(':key', $key);
                $db->bind(':value', $value);
                $db->bind(':value2', $value);
                $db->execute();
            }
            
            $success_message = 'Security settings updated successfully!';
        }
        
        if (isset($_POST['add_user'])) {
            // Add new user
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'];
            
            // Check if username or email already exists
            $db->query("SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email");
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $existing = $db->single();
            
            if ($existing['count'] > 0) {
                $error_message = 'Username or email already exists!';
            } else {
                $db->query("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
                $db->bind(':username', $username);
                $db->bind(':email', $email);
                $db->bind(':password', $password);
                $db->bind(':role', $role);
                $db->execute();
                
                $success_message = 'New user added successfully!';
            }
        }
        
        if (isset($_POST['update_user_status'])) {
            // Update user status
            $user_id = $_POST['user_id'];
            $status = $_POST['status'];
            
            $db->query("UPDATE users SET status = :status WHERE id = :user_id");
            $db->bind(':status', $status);
            $db->bind(':user_id', $user_id);
            $db->execute();
            
            $success_message = 'User status updated successfully!';
        }
        
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Create settings table if it doesn't exist
try {
    $db->query("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $db->execute();
} catch (Exception $e) {
    // Table might already exist, continue
}

// Get current settings
$db->query("SELECT setting_key, setting_value FROM settings");
$settings_data = $db->resultSet();
$settings = [];
foreach ($settings_data as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Set default values if not set
$defaults = [
    'hospital_name' => 'General Hospital',
    'hospital_address' => 'Colombo, Sri Lanka',
    'hospital_phone' => '+94 11 123 4567',
    'hospital_email' => 'info@hospital.lk',
    'hospital_website' => 'www.hospital.lk',
    'timezone' => 'Asia/Colombo',
    'date_format' => 'Y-m-d',
    'currency_symbol' => 'Rs.',
    'appointment_duration' => '30',
    'working_hours_start' => '08:00',
    'working_hours_end' => '18:00',
    'password_min_length' => '6',
    'session_timeout' => '60',
    'max_login_attempts' => '5',
    'backup_frequency' => 'daily'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get all users for user management
$db->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC");
$users = $db->resultSet();

// Get system statistics
$db->query("SELECT 
               (SELECT COUNT(*) FROM users) as total_users,
               (SELECT COUNT(*) FROM patients) as total_patients,
               (SELECT COUNT(*) FROM doctors) as total_doctors,
               (SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE()) as upcoming_appointments,
               (SELECT COUNT(*) FROM billing WHERE payment_status = 'pending') as pending_bills");
$system_stats = $db->single();

include '../includes/header.php';
?>

<style>
.settings-container {
    padding: 20px;
}

.settings-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    transition: box-shadow 0.3s ease;
}

.settings-card:hover {
    box-shadow: 0 4px 25px rgba(0,0,0,0.12);
}

.settings-card h5 {
    color: #333;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f8f9fa;
}

.settings-card .card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    margin-right: 12px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #ddd;
    padding: 12px 15px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-settings {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.btn-settings:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

.stats-overview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    backdrop-filter: blur(10px);
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

.user-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.user-table th {
    background: #f8f9fa;
    border: none;
    padding: 15px;
    font-weight: 600;
    color: #555;
}

.user-table td {
    padding: 15px;
    border-bottom: 1px solid #f1f1f1;
    vertical-align: middle;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-active {
    background: #d1f2eb;
    color: #00695c;
}

.status-inactive {
    background: #ffebee;
    color: #c62828;
}

.role-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.role-admin {
    background: #e3f2fd;
    color: #1565c0;
}

.role-doctor {
    background: #f3e5f5;
    color: #7b1fa2;
}

.role-nurse {
    background: #e8f5e8;
    color: #2e7d32;
}

.role-receptionist {
    background: #fff3e0;
    color: #ef6c00;
}

.role-patient {
    background: #fafafa;
    color: #616161;
}

.settings-tabs {
    border-bottom: 2px solid #f8f9fa;
    margin-bottom: 30px;
}

.settings-tab {
    display: inline-block;
    padding: 15px 25px;
    color: #666;
    text-decoration: none;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.settings-tab.active,
.settings-tab:hover {
    color: #667eea;
    border-bottom-color: #667eea;
}

.alert-custom {
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    border: none;
}

.alert-success-custom {
    background: linear-gradient(135deg, #d1f2eb, #a7f3d0);
    color: #065f46;
}

.alert-danger-custom {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
}

@media (max-width: 768px) {
    .settings-container {
        padding: 10px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
}
</style>

<div class="settings-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-cogs me-2"></i>System Settings</h1>
            <p class="text-muted mb-0">Configure and manage hospital system settings</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- System Overview -->
    <div class="stats-overview">
        <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>System Overview</h5>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($system_stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($system_stats['total_patients']); ?></div>
                <div class="stat-label">Registered Patients</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($system_stats['total_doctors']); ?></div>
                <div class="stat-label">Active Doctors</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($system_stats['upcoming_appointments']); ?></div>
                <div class="stat-label">Upcoming Appointments</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($system_stats['pending_bills']); ?></div>
                <div class="stat-label">Pending Bills</div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success-custom">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger-custom">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Settings Navigation Tabs -->
    <div class="settings-tabs">
        <a href="#hospital-info" class="settings-tab active" onclick="showTab('hospital-info', this)">
            <i class="fas fa-hospital me-2"></i>Hospital Information
        </a>
        <a href="#system-config" class="settings-tab" onclick="showTab('system-config', this)">
            <i class="fas fa-cog me-2"></i>System Configuration
        </a>
        <a href="#security" class="settings-tab" onclick="showTab('security', this)">
            <i class="fas fa-shield-alt me-2"></i>Security & Backup
        </a>
        <a href="#user-management" class="settings-tab" onclick="showTab('user-management', this)">
            <i class="fas fa-users-cog me-2"></i>User Management
        </a>
    </div>

    <!-- Hospital Information Settings -->
    <div id="hospital-info" class="tab-content">
        <div class="settings-card">
            <h5>
                <div class="card-icon"><i class="fas fa-hospital"></i></div>
                Hospital Information
            </h5>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Hospital Name *</label>
                            <input type="text" class="form-control" name="hospital_name" 
                                   value="<?php echo htmlspecialchars($settings['hospital_name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Hospital Phone</label>
                            <input type="text" class="form-control" name="hospital_phone" 
                                   value="<?php echo htmlspecialchars($settings['hospital_phone']); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Hospital Email</label>
                            <input type="email" class="form-control" name="hospital_email" 
                                   value="<?php echo htmlspecialchars($settings['hospital_email']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Hospital Website</label>
                            <input type="text" class="form-control" name="hospital_website" 
                                   value="<?php echo htmlspecialchars($settings['hospital_website']); ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Hospital Address</label>
                    <textarea class="form-control" name="hospital_address" rows="3"><?php echo htmlspecialchars($settings['hospital_address']); ?></textarea>
                </div>
                <button type="submit" name="update_hospital_info" class="btn btn-settings">
                    <i class="fas fa-save me-2"></i>Update Hospital Information
                </button>
            </form>
        </div>
    </div>

    <!-- System Configuration Settings -->
    <div id="system-config" class="tab-content" style="display: none;">
        <div class="settings-card">
            <h5>
                <div class="card-icon"><i class="fas fa-cog"></i></div>
                System Configuration
            </h5>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" name="timezone">
                                <option value="Asia/Colombo" <?php echo $settings['timezone'] === 'Asia/Colombo' ? 'selected' : ''; ?>>Asia/Colombo</option>
                                <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                <option value="Europe/London" <?php echo $settings['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Date Format</label>
                            <select class="form-select" name="date_format">
                                <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                <option value="d-M-Y" <?php echo $settings['date_format'] === 'd-M-Y' ? 'selected' : ''; ?>>DD-MMM-YYYY</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Currency Symbol</label>
                            <select class="form-select" name="currency_symbol">
                                <option value="Rs." <?php echo $settings['currency_symbol'] === 'Rs.' ? 'selected' : ''; ?>>Rs. (Sri Lankan Rupee)</option>
                                <option value="$" <?php echo $settings['currency_symbol'] === '$' ? 'selected' : ''; ?>>$ (US Dollar)</option>
                                <option value="€" <?php echo $settings['currency_symbol'] === '€' ? 'selected' : ''; ?>>€ (Euro)</option>
                                <option value="£" <?php echo $settings['currency_symbol'] === '£' ? 'selected' : ''; ?>>£ (British Pound)</option>
                                <option value="₹" <?php echo $settings['currency_symbol'] === '₹' ? 'selected' : ''; ?>>₹ (Indian Rupee)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Default Appointment Duration (minutes)</label>
                            <select class="form-select" name="appointment_duration">
                                <option value="15" <?php echo $settings['appointment_duration'] === '15' ? 'selected' : ''; ?>>15 minutes</option>
                                <option value="30" <?php echo $settings['appointment_duration'] === '30' ? 'selected' : ''; ?>>30 minutes</option>
                                <option value="45" <?php echo $settings['appointment_duration'] === '45' ? 'selected' : ''; ?>>45 minutes</option>
                                <option value="60" <?php echo $settings['appointment_duration'] === '60' ? 'selected' : ''; ?>>60 minutes</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Working Hours Start</label>
                            <input type="time" class="form-control" name="working_hours_start" 
                                   value="<?php echo htmlspecialchars($settings['working_hours_start']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Working Hours End</label>
                            <input type="time" class="form-control" name="working_hours_end" 
                                   value="<?php echo htmlspecialchars($settings['working_hours_end']); ?>">
                        </div>
                    </div>
                </div>
                <button type="submit" name="update_system_settings" class="btn btn-settings">
                    <i class="fas fa-save me-2"></i>Update System Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Security & Backup Settings -->
    <div id="security" class="tab-content" style="display: none;">
        <div class="settings-card">
            <h5>
                <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
                Security & Backup Settings
            </h5>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Minimum Password Length</label>
                            <select class="form-select" name="password_min_length">
                                <option value="6" <?php echo $settings['password_min_length'] === '6' ? 'selected' : ''; ?>>6 characters</option>
                                <option value="8" <?php echo $settings['password_min_length'] === '8' ? 'selected' : ''; ?>>8 characters</option>
                                <option value="10" <?php echo $settings['password_min_length'] === '10' ? 'selected' : ''; ?>>10 characters</option>
                                <option value="12" <?php echo $settings['password_min_length'] === '12' ? 'selected' : ''; ?>>12 characters</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Session Timeout (minutes)</label>
                            <select class="form-select" name="session_timeout">
                                <option value="30" <?php echo $settings['session_timeout'] === '30' ? 'selected' : ''; ?>>30 minutes</option>
                                <option value="60" <?php echo $settings['session_timeout'] === '60' ? 'selected' : ''; ?>>1 hour</option>
                                <option value="120" <?php echo $settings['session_timeout'] === '120' ? 'selected' : ''; ?>>2 hours</option>
                                <option value="480" <?php echo $settings['session_timeout'] === '480' ? 'selected' : ''; ?>>8 hours</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Maximum Login Attempts</label>
                            <select class="form-select" name="max_login_attempts">
                                <option value="3" <?php echo $settings['max_login_attempts'] === '3' ? 'selected' : ''; ?>>3 attempts</option>
                                <option value="5" <?php echo $settings['max_login_attempts'] === '5' ? 'selected' : ''; ?>>5 attempts</option>
                                <option value="10" <?php echo $settings['max_login_attempts'] === '10' ? 'selected' : ''; ?>>10 attempts</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Backup Frequency</label>
                            <select class="form-select" name="backup_frequency">
                                <option value="daily" <?php echo $settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $settings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="submit" name="update_security_settings" class="btn btn-settings">
                    <i class="fas fa-save me-2"></i>Update Security Settings
                </button>
            </form>
        </div>
    </div>

    <!-- User Management -->
    <div id="user-management" class="tab-content" style="display: none;">
        <!-- Add New User -->
        <div class="settings-card">
            <h5>
                <div class="card-icon"><i class="fas fa-user-plus"></i></div>
                Add New User
            </h5>
            <form method="POST">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" 
                                   minlength="<?php echo $settings['password_min_length']; ?>" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="nurse">Nurse</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <button type="submit" name="add_user" class="btn btn-settings">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Existing Users -->
        <div class="settings-card">
            <h5>
                <div class="card-icon"><i class="fas fa-users"></i></div>
                Manage Existing Users
            </h5>
            <div class="table-responsive">
                <table class="table user-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><span class="status-badge status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="status" class="form-select form-select-sm" style="display: inline; width: auto;" 
                                            onchange="this.form.submit()">
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                    <input type="hidden" name="update_user_status" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName, element) {
    // Hide all tab contents
    var tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(function(tab) {
        tab.style.display = 'none';
    });
    
    // Remove active class from all tabs
    var tabLinks = document.querySelectorAll('.settings-tab');
    tabLinks.forEach(function(link) {
        link.classList.remove('active');
    });
    
    // Show selected tab and mark as active
    document.getElementById(tabName).style.display = 'block';
    element.classList.add('active');
    
    // Prevent default link behavior
    event.preventDefault();
}

// Auto-submit form when user status dropdown changes
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation for status changes
    var statusSelects = document.querySelectorAll('select[name="status"]');
    statusSelects.forEach(function(select) {
        select.addEventListener('change', function() {
            if (confirm('Are you sure you want to change this user\'s status?')) {
                this.form.submit();
            } else {
                // Reset to previous value if cancelled
                this.selectedIndex = this.value === 'active' ? 1 : 0;
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
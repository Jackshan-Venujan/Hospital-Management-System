<?php
require_once '../includes/config.php';

$page_title = 'Patient Registration';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
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
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender) || empty($phone) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $db->beginTransaction();
            
            // Check if username or email already exists
            $db->query('SELECT id FROM users WHERE username = :username OR email = :email');
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $existing_user = $db->single();
            
            if ($existing_user) {
                throw new Exception('Username or email already exists.');
            }
            
            // Generate patient ID
            $patient_id = generate_id('PAT', 6);
            
            // Check if patient ID already exists
            do {
                $db->query('SELECT id FROM patients WHERE patient_id = :patient_id');
                $db->bind(':patient_id', $patient_id);
                $existing_patient = $db->single();
                if ($existing_patient) {
                    $patient_id = generate_id('PAT', 6);
                }
            } while ($existing_patient);
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $db->query('
                INSERT INTO users (username, email, password, role) 
                VALUES (:username, :email, :password, :role)
            ');
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $db->bind(':password', $hashed_password);
            $db->bind(':role', 'patient');
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
            $db->bind(':patient_id', $patient_id);
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
            
            $success = "Registration successful! Your Patient ID is: <strong>$patient_id</strong><br>You can now <a href='../login.php'>login</a> with your credentials.";
            
            // Clear form data on success
            $_POST = [];
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="card mt-5">
                    <div class="card-header bg-primary text-white text-center">
                        <h3><i class="fas fa-user-plus me-2"></i>Patient Registration</h3>
                        <p class="mb-0">Create your patient account</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <a href="../login.php" class="btn btn-link">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Login
                            </a>
                        </div>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <!-- Personal Information -->
                            <h5 class="text-primary mb-3">Personal Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter your first name.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter your last name.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter your date of birth.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your gender.</div>
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <h5 class="text-primary mb-3 mt-4">Contact Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter a valid phone number.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Emergency Contact -->
                            <h5 class="text-primary mb-3 mt-4">Emergency Contact</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                           value="<?php echo isset($_POST['emergency_contact_name']) ? htmlspecialchars($_POST['emergency_contact_name']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                           value="<?php echo isset($_POST['emergency_contact_phone']) ? htmlspecialchars($_POST['emergency_contact_phone']) : ''; ?>">
                                </div>
                            </div>
                            
                            <!-- Medical Information -->
                            <h5 class="text-primary mb-3 mt-4">Medical Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="blood_group" class="form-label">Blood Group</label>
                                    <select class="form-select" id="blood_group" name="blood_group">
                                        <option value="">Select Blood Group</option>
                                        <option value="A+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'A+') ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'A-') ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'B+') ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'B-') ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'O+') ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] === 'O-') ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="insurance_number" class="form-label">Insurance Number</label>
                                    <input type="text" class="form-control" id="insurance_number" name="insurance_number" 
                                           value="<?php echo isset($_POST['insurance_number']) ? htmlspecialchars($_POST['insurance_number']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="allergies" class="form-label">Known Allergies</label>
                                    <textarea class="form-control" id="allergies" name="allergies" rows="3" 
                                              placeholder="List any known allergies..."><?php echo isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies']) : ''; ?></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="medical_history" class="form-label">Medical History</label>
                                    <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                              placeholder="Brief medical history..."><?php echo isset($_POST['medical_history']) ? htmlspecialchars($_POST['medical_history']) : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Account Information -->
                            <h5 class="text-primary mb-3 mt-4">Account Information</h5>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter a username.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="6" required>
                                    <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="6" required>
                                    <div class="invalid-feedback">Please confirm your password.</div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Register
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Set max date for date of birth to today
        document.getElementById('date_of_birth').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
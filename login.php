<?php
require_once 'includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check user credentials
        $db->query('SELECT * FROM users WHERE username = :username AND status = :status');
        $db->bind(':username', $username);
        $db->bind(':status', 'active');
        $user = $db->single();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    redirect('admin/dashboard.php');
                    break;
                case 'doctor':
                    redirect('pages/doctor_dashboard.php');
                    break;
                case 'nurse':
                case 'receptionist':
                    redirect('pages/staff_dashboard.php');
                    break;
                case 'patient':
                    redirect('pages/patient_dashboard.php');
                    break;
                default:
                    redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container-fluid h-100">
        <div class="row h-100">
            <!-- Left Side - Login Form -->
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="login-form-container">
                    <div class="text-center mb-4">
                        <i class="fas fa-hospital-alt fa-3x text-primary mb-3"></i>
                        <h2 class="text-primary"><?php echo SITE_NAME; ?></h2>
                        <p class="text-muted">Please sign in to your account</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Sign In
                        </button>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="mb-2">Demo Accounts:</p>
                        <small class="text-muted">
                            <strong>Admin:</strong> admin / secret<br>
                            <strong>Doctor:</strong> dr.smith / secret
                        </small>
                    </div>

                    <div class="text-center mt-4">
                        <a href="pages/patient_registration.php" class="text-decoration-none">
                            <i class="fas fa-user-plus me-1"></i>
                            New Patient Registration
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Side - Information -->
            <div class="col-md-6 bg-primary d-none d-md-flex align-items-center justify-content-center text-white">
                <div class="text-center">
                    <i class="fas fa-heartbeat fa-5x mb-4"></i>
                    <h1 class="mb-4">Welcome to Our Hospital</h1>
                    <p class="lead mb-4">Providing quality healthcare services with advanced medical technology and experienced professionals.</p>
                    <div class="row text-center">
                        <div class="col-4">
                            <i class="fas fa-user-md fa-2x mb-2"></i>
                            <p>Expert Doctors</p>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <p>24/7 Service</p>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-ambulance fa-2x mb-2"></i>
                            <p>Emergency Care</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Auto-focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>
<?php
require_once 'includes/config.php';

// If user is already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    $role = get_user_role();
    switch ($role) {
        case 'admin':
            redirect('admin/dashboard.php');
            break;
        case 'doctor':
            redirect('pages/doctor_dashboard.php');
            break;
        case 'patient':
            redirect('pages/patient_dashboard.php');
            break;
        default:
            redirect('login.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left side - Welcome content -->
            <div class="col-lg-8 d-flex align-items-center justify-content-center bg-light">
                <div class="text-center">
                    <i class="fas fa-hospital-alt fa-5x text-primary mb-4"></i>
                    <h1 class="display-4 mb-4"><?php echo SITE_NAME; ?></h1>
                    <p class="lead mb-4">
                        Modern healthcare management system providing comprehensive 
                        patient care and administrative solutions.
                    </p>
                    
                    <div class="row text-center mt-5">
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <i class="fas fa-user-md fa-3x text-primary mb-3"></i>
                                    <h5>Expert Doctors</h5>
                                    <p class="text-muted">Qualified medical professionals providing quality healthcare services.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <i class="fas fa-clock fa-3x text-success mb-3"></i>
                                    <h5>24/7 Service</h5>
                                    <p class="text-muted">Round-the-clock medical assistance and emergency care services.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <i class="fas fa-laptop-medical fa-3x text-info mb-3"></i>
                                    <h5>Digital Records</h5>
                                    <p class="text-muted">Advanced digital health records and appointment management.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right side - Login options -->
            <div class="col-lg-4 d-flex align-items-center justify-content-center bg-primary">
                <div class="text-center text-white w-100 px-4">
                    <h2 class="mb-4">Get Started</h2>
                    <p class="mb-4">Access your account or register as a new patient</p>
                    
                    <div class="d-grid gap-3">
                        <a href="login.php" class="btn btn-light btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Login to Your Account
                        </a>
                        
                        <a href="pages/patient_registration.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-plus me-2"></i>
                            New Patient Registration
                        </a>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-start">
                        <h6>Demo Accounts:</h6>
                        <div class="row text-sm">
                            <div class="col-12 mb-2">
                                <strong>Admin Access:</strong><br>
                                <small>Username: admin | Password: secret</small>
                            </div>
                            <div class="col-12">
                                <strong>Doctor Access:</strong><br>
                                <small>Username: dr.smith | Password: secret</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-start">
                        <h6>Setup & Testing:</h6>
                        <div class="d-grid gap-2">
                            <a href="complete_setup.php" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-cog me-1"></i>Database Setup
                            </a>
                            <a href="admin/test.php" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-bug me-1"></i>Admin Test Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (!is_logged_in()) {
    redirect('login.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>assets/css/style.css" rel="stylesheet">
    
    <!-- Additional CSS if provided -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-hospital-alt fa-2x mb-2"></i>
                <h4><?php echo SITE_NAME; ?></h4>
            </div>
            
            <ul class="sidebar-nav">
                <?php
                $current_role = get_user_role();
                $current_page = basename($_SERVER['PHP_SELF']);
                
                // Define menu items based on role
                $menu_items = [];
                
                switch ($current_role) {
                    case 'admin':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'dashboard.php'],
                            ['icon' => 'fas fa-users', 'text' => 'Patients', 'url' => 'patients.php'],
                            ['icon' => 'fas fa-user-md', 'text' => 'Doctors', 'url' => 'doctors.php'],
                            ['icon' => 'fas fa-user-nurse', 'text' => 'Staff', 'url' => 'staff.php'],
                            ['icon' => 'fas fa-calendar-check', 'text' => 'Appointments', 'url' => 'appointments.php'],
                            ['icon' => 'fas fa-building', 'text' => 'Departments', 'url' => 'departments.php'],
                            ['icon' => 'fas fa-bed', 'text' => 'Rooms', 'url' => 'rooms.php'],
                            ['icon' => 'fas fa-file-invoice-dollar', 'text' => 'Billing', 'url' => 'billing.php'],
                            ['icon' => 'fas fa-chart-bar', 'text' => 'Reports', 'url' => 'reports.php'],
                            ['icon' => 'fas fa-cog', 'text' => 'Settings', 'url' => 'settings.php']
                        ];
                        break;
                        
                    case 'doctor':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'doctor_dashboard.php'],
                            ['icon' => 'fas fa-calendar-check', 'text' => 'My Appointments', 'url' => 'doctor_appointments.php'],
                            ['icon' => 'fas fa-users', 'text' => 'My Patients', 'url' => 'doctor_patients.php'],
                            ['icon' => 'fas fa-notes-medical', 'text' => 'Medical Records', 'url' => 'medical_records.php'],
                            ['icon' => 'fas fa-prescription-bottle', 'text' => 'Prescriptions', 'url' => 'prescriptions.php'],
                            ['icon' => 'fas fa-clock', 'text' => 'Schedule', 'url' => 'doctor_schedule.php'],
                            ['icon' => 'fas fa-user-circle', 'text' => 'Profile', 'url' => 'doctor_profile.php']
                        ];
                        break;
                        
                    case 'nurse':
                    case 'receptionist':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'staff_dashboard.php'],
                            ['icon' => 'fas fa-users', 'text' => 'Patients', 'url' => 'patients.php'],
                            ['icon' => 'fas fa-calendar-check', 'text' => 'Appointments', 'url' => 'appointments.php'],
                            ['icon' => 'fas fa-user-plus', 'text' => 'Patient Registration', 'url' => 'patient_registration.php'],
                            ['icon' => 'fas fa-file-invoice-dollar', 'text' => 'Billing', 'url' => 'billing.php'],
                            ['icon' => 'fas fa-user-circle', 'text' => 'Profile', 'url' => 'staff_profile.php']
                        ];
                        break;
                        
                    case 'patient':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'patient_dashboard.php'],
                            ['icon' => 'fas fa-calendar-plus', 'text' => 'Book Appointment', 'url' => 'book_appointment.php'],
                            ['icon' => 'fas fa-calendar-check', 'text' => 'My Appointments', 'url' => 'patient_appointments.php'],
                            ['icon' => 'fas fa-notes-medical', 'text' => 'Medical Records', 'url' => 'patient_medical_records.php'],
                            ['icon' => 'fas fa-prescription-bottle', 'text' => 'Prescriptions', 'url' => 'patient_prescriptions.php'],
                            ['icon' => 'fas fa-file-invoice', 'text' => 'Billing History', 'url' => 'patient_billing.php'],
                            ['icon' => 'fas fa-user-circle', 'text' => 'Profile', 'url' => 'patient_profile.php']
                        ];
                        break;
                }
                
                foreach ($menu_items as $item):
                    $active_class = ($current_page === $item['url']) ? 'active' : '';
                ?>
                    <li class="nav-item">
                        <a href="<?php echo $item['url']; ?>" class="nav-link <?php echo $active_class; ?>">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <?php echo $item['text']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                
                <li class="nav-item mt-4">
                    <a href="<?php echo SITE_URL; ?>logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="btn btn-link d-md-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="mb-0 d-none d-md-block"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h4>
                </div>
                
                <div class="header-right">
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-link position-relative" data-bs-toggle="dropdown">
                            <i class="fas fa-bell fa-lg"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                3
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-plus me-2"></i>New patient registered</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-calendar me-2"></i>Appointment scheduled</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle me-2"></i>System maintenance</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                        </ul>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="dropdown">
                        <div class="user-avatar" data-bs-toggle="dropdown" role="button">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header"><?php echo $_SESSION['username']; ?></h6></li>
                            <li><span class="dropdown-item-text small text-muted"><?php echo ucfirst($_SESSION['role']); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="content">
                <?php display_message(); ?>
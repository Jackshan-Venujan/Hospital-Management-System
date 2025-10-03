<?php
$page_title = "Staff Management";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $role = sanitize_input($_POST['role']);
                $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $salary = !empty($_POST['salary']) ? floatval($_POST['salary']) : null;
                $hire_date = sanitize_input($_POST['hire_date']);
                $address = sanitize_input($_POST['address']);
                
                // Check if email already exists
                $check_email = "SELECT id FROM users WHERE email = ?";
                $stmt = mysqli_prepare($conn, $check_email);
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error_message = "Email already exists. Please use a different email address.";
                } else {
                    // Start transaction
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Create username from email
                        $username = explode('@', $email)[0];
                        
                        // Insert into users table
                        $user_query = "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())";
                        $user_stmt = mysqli_prepare($conn, $user_query);
                        if (!$user_stmt) {
                            throw new Exception("User query preparation failed: " . mysqli_error($conn));
                        }
                        mysqli_stmt_bind_param($user_stmt, "ssss", $username, $email, $password, $role);
                        
                        if (!mysqli_stmt_execute($user_stmt)) {
                            throw new Exception("Error inserting user: " . mysqli_error($conn));
                        }
                        
                        $user_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($user_stmt);
                        
                        // Generate employee ID
                        $prefix = strtoupper(substr($role, 0, 3));
                        $employee_id = $prefix . str_pad($user_id, 3, '0', STR_PAD_LEFT);
                        
                        // Insert into appropriate table based on role
                        if ($role === 'doctor') {
                            $staff_query = "INSERT INTO doctors (user_id, employee_id, first_name, last_name, phone, email, department_id, consultation_fee, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                            $staff_stmt = mysqli_prepare($conn, $staff_query);
                            if (!$staff_stmt) {
                                throw new Exception("Doctor query preparation failed: " . mysqli_error($conn));
                            }
                            mysqli_stmt_bind_param($staff_stmt, "isssssid", $user_id, $employee_id, $first_name, $last_name, $phone, $email, $department_id, $salary);
                        } else {
                            $staff_query = "INSERT INTO staff (user_id, employee_id, first_name, last_name, position, department_id, phone, email, hire_date, salary, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                            $staff_stmt = mysqli_prepare($conn, $staff_query);
                            if (!$staff_stmt) {
                                throw new Exception("Staff query preparation failed: " . mysqli_error($conn));
                            }
                            $position = ucfirst($role);
                            mysqli_stmt_bind_param($staff_stmt, "issssissd", $user_id, $employee_id, $first_name, $last_name, $position, $department_id, $phone, $email, $hire_date, $salary);
                        }
                        
                        if (!mysqli_stmt_execute($staff_stmt)) {
                            throw new Exception("Error inserting staff details: " . mysqli_error($conn));
                        }
                        
                        mysqli_stmt_close($staff_stmt);
                        mysqli_commit($conn);
                        $success_message = "Staff member added successfully!";
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error_message = $e->getMessage();
                    }
                }
                break;
                
            case 'edit':
                $staff_id = intval($_POST['staff_id']);
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $role = sanitize_input($_POST['role']);
                $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
                $salary = !empty($_POST['salary']) ? floatval($_POST['salary']) : null;
                $hire_date = sanitize_input($_POST['hire_date']);
                $address = sanitize_input($_POST['address']);
                
                // Check if email already exists for another user
                $check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt = mysqli_prepare($conn, $check_email);
                mysqli_stmt_bind_param($stmt, "si", $email, $staff_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error_message = "Email already exists for another user. Please use a different email address.";
                } else {
                    // Start transaction
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Update users table
                        $user_query = "UPDATE users SET email = ?, role = ?, updated_at = NOW() WHERE id = ?";
                        $user_stmt = mysqli_prepare($conn, $user_query);
                        if (!$user_stmt) {
                            throw new Exception("User update query preparation failed: " . mysqli_error($conn));
                        }
                        mysqli_stmt_bind_param($user_stmt, "ssi", $email, $role, $staff_id);
                        
                        if (!mysqli_stmt_execute($user_stmt)) {
                            throw new Exception("Error updating user: " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($user_stmt);
                        
                        // Update appropriate staff table based on role
                        if ($role === 'doctor') {
                            // Update doctors table
                            $staff_query = "UPDATE doctors SET first_name = ?, last_name = ?, phone = ?, email = ?, department_id = ?, consultation_fee = ? WHERE user_id = ?";
                            $staff_stmt = mysqli_prepare($conn, $staff_query);
                            if (!$staff_stmt) {
                                throw new Exception("Doctor update query preparation failed: " . mysqli_error($conn));
                            }
                            mysqli_stmt_bind_param($staff_stmt, "ssssidi", $first_name, $last_name, $phone, $email, $department_id, $salary, $staff_id);
                        } else {
                            // Update staff table
                            $position = ucfirst($role);
                            $staff_query = "UPDATE staff SET first_name = ?, last_name = ?, position = ?, department_id = ?, phone = ?, email = ?, hire_date = ?, salary = ? WHERE user_id = ?";
                            $staff_stmt = mysqli_prepare($conn, $staff_query);
                            if (!$staff_stmt) {
                                throw new Exception("Staff update query preparation failed: " . mysqli_error($conn));
                            }
                            mysqli_stmt_bind_param($staff_stmt, "sssisssdi", $first_name, $last_name, $position, $department_id, $phone, $email, $hire_date, $salary, $staff_id);
                        }
                        
                        if (!mysqli_stmt_execute($staff_stmt)) {
                            throw new Exception("Error updating staff details: " . mysqli_error($conn));
                        }
                        
                        mysqli_stmt_close($staff_stmt);
                        mysqli_commit($conn);
                        $success_message = "Staff member updated successfully!";
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error_message = $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $staff_id = intval($_POST['staff_id']);
                
                // Check if staff has appointments
                $check_appointments = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?";
                $stmt = mysqli_prepare($conn, $check_appointments);
                mysqli_stmt_bind_param($stmt, "i", $staff_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $appointment_count = mysqli_fetch_assoc($result)['count'];
                mysqli_stmt_close($stmt);
                
                if ($appointment_count > 0) {
                    $error_message = "Cannot delete staff member. They have {$appointment_count} associated appointments.";
                } else {
                    $query = "DELETE FROM users WHERE id = ? AND role IN ('doctor', 'nurse', 'receptionist', 'technician', 'pharmacist', 'administrator', 'janitor', 'security', 'accountant')";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $staff_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        if (mysqli_affected_rows($conn) > 0) {
                            $success_message = "Staff member deleted successfully!";
                        } else {
                            $error_message = "Staff member not found or cannot be deleted.";
                        }
                    } else {
                        $error_message = "Error deleting staff member: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
        }
    }
}

// Pagination and filtering
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : '';

// Build query
$where_conditions = ["u.role IN ('doctor', 'nurse', 'receptionist', 'technician', 'pharmacist', 'administrator', 'janitor', 'security', 'accountant')"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(COALESCE(s.first_name, d.first_name) LIKE ? OR COALESCE(s.last_name, d.last_name) LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($department_filter)) {
    $where_conditions[] = "(s.department_id = ? OR d.department_id = ?)";
    $params[] = $department_filter;
    $params[] = $department_filter;
    $types .= "ii";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(DISTINCT u.id) as total 
                FROM users u 
                LEFT JOIN staff s ON u.id = s.user_id 
                LEFT JOIN doctors d ON u.id = d.user_id 
                {$where_clause}";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_query);
    if (!$stmt) {
        die("Count query preparation failed: " . mysqli_error($conn) . "<br>Query: " . $count_query);
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_staff = mysqli_fetch_assoc($result)['total'];
    mysqli_stmt_close($stmt);
} else {
    $result = mysqli_query($conn, $count_query);
    $total_staff = mysqli_fetch_assoc($result)['total'];
}

// Get staff members
$query = "SELECT u.*, 
                  COALESCE(s.first_name, d.first_name) as first_name,
                  COALESCE(s.last_name, d.last_name) as last_name,
                  COALESCE(s.phone, d.phone) as phone,
                  COALESCE(s.employee_id, d.employee_id) as employee_id,
                  COALESCE(s.position, 'Doctor') as position,
                  COALESCE(dept1.name, dept2.name) as department_name,
                  COALESCE(s.hire_date, 'N/A') as hire_date,
                  COALESCE(s.salary, d.consultation_fee) as salary
          FROM users u 
          LEFT JOIN staff s ON u.id = s.user_id 
          LEFT JOIN doctors d ON u.id = d.user_id 
          LEFT JOIN departments dept1 ON s.department_id = dept1.id
          LEFT JOIN departments dept2 ON d.department_id = dept2.id
          {$where_clause}
          ORDER BY COALESCE(s.first_name, d.first_name), COALESCE(s.last_name, d.last_name) 
          LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Query preparation failed: " . mysqli_error($conn) . "<br>Query: " . $query);
}
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$staff_members = [];
while ($row = mysqli_fetch_assoc($result)) {
    $staff_members[] = $row;
}
mysqli_stmt_close($stmt);

$total_pages = ceil($total_staff / $limit);

// Get departments for dropdown
$departments_query = "SELECT id, name FROM departments ORDER BY name";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
while ($dept = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $dept;
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Staff Management</h1>
        <div class="d-sm-flex">
            <button class="btn btn-success me-3" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="fas fa-plus"></i> Add Staff Member
            </button>
            <div class="btn-group">
                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="export_staff.php?format=csv">
                        <i class="fas fa-file-csv text-success mr-2"></i>Export as CSV
                    </a>
                    <a class="dropdown-item" href="export_staff.php?format=pdf">
                        <i class="fas fa-file-pdf text-danger mr-2"></i>Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Staff</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Role Filter</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="doctor" <?php echo $role_filter === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                        <option value="nurse" <?php echo $role_filter === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                        <option value="receptionist" <?php echo $role_filter === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                        <option value="technician" <?php echo $role_filter === 'technician' ? 'selected' : ''; ?>>Technician</option>
                        <option value="pharmacist" <?php echo $role_filter === 'pharmacist' ? 'selected' : ''; ?>>Pharmacist</option>
                        <option value="administrator" <?php echo $role_filter === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="janitor" <?php echo $role_filter === 'janitor' ? 'selected' : ''; ?>>Janitor</option>
                        <option value="security" <?php echo $role_filter === 'security' ? 'selected' : ''; ?>>Security</option>
                        <option value="accountant" <?php echo $role_filter === 'accountant' ? 'selected' : ''; ?>>Accountant</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="department" class="form-label">Department Filter</label>
                    <select class="form-select" id="department" name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="staff.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Staff Members (<?php echo $total_staff; ?> total)
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($staff_members)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                    <p class="text-muted">No staff members found.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="fas fa-plus"></i> Add First Staff Member
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Contact</th>
                                <th>Hire Date</th>
                                <th>Salary</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_members as $staff): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                </div>
                                                <small class="text-muted">ID: <?php echo $staff['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            // Define role styling
                                            $role_styles = [
                                                'doctor' => ['color' => 'primary', 'icon' => 'user-md'],
                                                'nurse' => ['color' => 'success', 'icon' => 'user-nurse'], 
                                                'receptionist' => ['color' => 'info', 'icon' => 'user-tie'],
                                                'technician' => ['color' => 'warning', 'icon' => 'microscope'],
                                                'pharmacist' => ['color' => 'danger', 'icon' => 'pills'],
                                                'administrator' => ['color' => 'secondary', 'icon' => 'user-cog'],
                                                'janitor' => ['color' => 'dark', 'icon' => 'broom'],
                                                'security' => ['color' => 'danger', 'icon' => 'shield-alt'],
                                                'accountant' => ['color' => 'success', 'icon' => 'calculator']
                                            ];
                                            
                                            $current_role = $staff['role'];
                                            $style = $role_styles[$current_role] ?? ['color' => 'secondary', 'icon' => 'user'];
                                        ?>
                                        <span class="badge bg-<?php echo $style['color']; ?>">
                                            <i class="fas fa-<?php echo $style['icon']; ?> me-1"></i>
                                            <?php echo ucfirst($staff['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff['department_name'])): ?>
                                            <i class="fas fa-building text-primary me-1"></i>
                                            <?php echo htmlspecialchars($staff['department_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-minus me-1"></i>No Department
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-envelope text-primary me-1"></i>
                                            <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>">
                                                <?php echo htmlspecialchars($staff['email']); ?>
                                            </a>
                                        </div>
                                        <?php if (!empty($staff['phone'])): ?>
                                            <div>
                                                <i class="fas fa-phone text-success me-1"></i>
                                                <?php echo htmlspecialchars($staff['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff['hire_date'])): ?>
                                            <i class="fas fa-calendar text-info me-1"></i>
                                            <?php echo date('M d, Y', strtotime($staff['hire_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff['salary'])): ?>
                                            <span class="fw-bold text-success">
                                                <i class="fas fa-dollar-sign me-1"></i>
                                                <?php echo number_format($staff['salary'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="viewStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)" 
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="editStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)" 
                                                    title="Edit Staff">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>')" 
                                                    title="Delete Staff">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add New Staff Member
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="add_first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="add_last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="add_email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="add_phone" name="phone">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_role" class="form-label">Role *</label>
                                <select class="form-select" id="add_role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="receptionist">Receptionist</option>
                                    <option value="technician">Technician</option>
                                    <option value="pharmacist">Pharmacist</option>
                                    <option value="administrator">Administrator</option>
                                    <option value="janitor">Janitor</option>
                                    <option value="security">Security</option>
                                    <option value="accountant">Accountant</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_department" class="form-label">Department</label>
                                <select class="form-select" id="add_department" name="department_id">
                                    <option value="">Select Department (Optional)</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="add_password" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_salary" class="form-label">Salary</label>
                                <input type="number" class="form-control" id="add_salary" name="salary" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_hire_date" class="form-label">Hire Date</label>
                                <input type="date" class="form-control" id="add_hire_date" name="hire_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_address" class="form-label">Address</label>
                        <textarea class="form-control" id="add_address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Edit Staff Member
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="staff_id" id="edit_staff_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_role" class="form-label">Role *</label>
                                <select class="form-select" id="edit_role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="receptionist">Receptionist</option>
                                    <option value="technician">Technician</option>
                                    <option value="pharmacist">Pharmacist</option>
                                    <option value="administrator">Administrator</option>
                                    <option value="janitor">Janitor</option>
                                    <option value="security">Security</option>
                                    <option value="accountant">Accountant</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_department" class="form-label">Department</label>
                                <select class="form-select" id="edit_department" name="department_id">
                                    <option value="">Select Department (Optional)</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_salary" class="form-label">Salary</label>
                                <input type="number" class="form-control" id="edit_salary" name="salary" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_hire_date" class="form-label">Hire Date</label>
                                <input type="date" class="form-control" id="edit_hire_date" name="hire_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Staff Modal -->
<div class="modal fade" id="viewStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>Staff Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="staffDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Staff Member
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="staff_id" id="delete_staff_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Are you sure you want to delete <strong id="delete_staff_name"></strong>?
                        This action cannot be undone.
                    </div>
                    
                    <p class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Note: Staff members with appointments cannot be deleted.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #0056b3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
}
</style>

<script>
function editStaff(staff) {
    document.getElementById('edit_staff_id').value = staff.id;
    document.getElementById('edit_first_name').value = staff.first_name;
    document.getElementById('edit_last_name').value = staff.last_name;
    document.getElementById('edit_email').value = staff.email;
    document.getElementById('edit_phone').value = staff.phone || '';
    document.getElementById('edit_role').value = staff.role;
    document.getElementById('edit_department').value = staff.department_id || '';
    document.getElementById('edit_salary').value = staff.salary || '';
    document.getElementById('edit_hire_date').value = staff.hire_date || '';
    document.getElementById('edit_address').value = staff.address || '';
    
    new bootstrap.Modal(document.getElementById('editStaffModal')).show();
}

function deleteStaff(id, name) {
    document.getElementById('delete_staff_id').value = id;
    document.getElementById('delete_staff_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteStaffModal')).show();
}

function viewStaff(staff) {
    const content = `
        <div class="row">
            <div class="col-md-4 text-center">
                <div class="avatar-circle mx-auto mb-3" style="width: 80px; height: 80px; font-size: 24px;">
                    ${staff.first_name.charAt(0)}${staff.last_name.charAt(0)}
                </div>
                <h5>${staff.first_name} ${staff.last_name}</h5>
                <span class="badge bg-${
                    staff.role === 'doctor' ? 'primary' : 
                    staff.role === 'nurse' ? 'success' : 
                    staff.role === 'receptionist' ? 'info' :
                    staff.role === 'technician' ? 'warning' :
                    staff.role === 'pharmacist' ? 'danger' :
                    staff.role === 'administrator' ? 'secondary' :
                    staff.role === 'janitor' ? 'dark' :
                    staff.role === 'security' ? 'danger' :
                    staff.role === 'accountant' ? 'success' : 'secondary'
                }">
                    ${staff.role.charAt(0).toUpperCase() + staff.role.slice(1)}
                </span>
            </div>
            <div class="col-md-8">
                <div class="row">
                    <div class="col-sm-6">
                        <p><strong>Email:</strong><br>${staff.email}</p>
                        <p><strong>Phone:</strong><br>${staff.phone || 'Not specified'}</p>
                        <p><strong>Department:</strong><br>${staff.department_name || 'Not assigned'}</p>
                    </div>
                    <div class="col-sm-6">
                        <p><strong>Hire Date:</strong><br>${staff.hire_date ? new Date(staff.hire_date).toLocaleDateString() : 'Not specified'}</p>
                        <p><strong>Salary:</strong><br>${staff.salary ? '$' + parseFloat(staff.salary).toLocaleString() : 'Not specified'}</p>
                        <p><strong>Staff ID:</strong><br>${staff.id}</p>
                    </div>
                </div>
                ${staff.address ? `<p><strong>Address:</strong><br>${staff.address}</p>` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('staffDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewStaffModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
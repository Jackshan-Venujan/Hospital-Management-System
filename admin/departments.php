<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$conn) {
        $error_message = "Database connection failed.";
    } else {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize_input($_POST['name']);
                $description = sanitize_input($_POST['description']);
                $head_doctor_id = !empty($_POST['head_doctor_id']) ? intval($_POST['head_doctor_id']) : null;
                
                // Check if department name already exists
                $check_query = "SELECT id FROM departments WHERE name = ?";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "s", $name);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error_message = "Department name already exists. Please use a different name.";
                } else {
                    $query = "INSERT INTO departments (name, description, head_doctor_id, created_at) VALUES (?, ?, ?, NOW())";
                    $stmt = mysqli_prepare($conn, $query);
                    
                    if (!$stmt) {
                        $error_message = "Query preparation failed: " . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($stmt, "ssi", $name, $description, $head_doctor_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success_message = "Department added successfully!";
                        } else {
                            $error_message = "Error adding department: " . mysqli_error($conn);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
                break;
                
            case 'edit':
                $department_id = intval($_POST['department_id']);
                $name = sanitize_input($_POST['name']);
                $description = sanitize_input($_POST['description']);
                $head_doctor_id = !empty($_POST['head_doctor_id']) ? intval($_POST['head_doctor_id']) : null;
                
                // Check if department name already exists for another department
                $check_query = "SELECT id FROM departments WHERE name = ? AND id != ?";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "si", $name, $department_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error_message = "Department name already exists. Please use a different name.";
                } else {
                    $query = "UPDATE departments SET name = ?, description = ?, head_doctor_id = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    
                    if (!$stmt) {
                        $error_message = "Query preparation failed: " . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($stmt, "ssii", $name, $description, $head_doctor_id, $department_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success_message = "Department updated successfully!";
                        } else {
                            $error_message = "Error updating department: " . mysqli_error($conn);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
                break;
                
            case 'delete':
                $department_id = intval($_POST['department_id']);
                
                // Check if department has associated staff or doctors
                $check_staff = "SELECT COUNT(*) as staff_count FROM staff WHERE department_id = ?";
                $check_doctors = "SELECT COUNT(*) as doctor_count FROM doctors WHERE department_id = ?";
                
                $stmt = mysqli_prepare($conn, $check_staff);
                mysqli_stmt_bind_param($stmt, "i", $department_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $staff_count = mysqli_fetch_assoc($result)['staff_count'];
                mysqli_stmt_close($stmt);
                
                $stmt = mysqli_prepare($conn, $check_doctors);
                mysqli_stmt_bind_param($stmt, "i", $department_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $doctor_count = mysqli_fetch_assoc($result)['doctor_count'];
                mysqli_stmt_close($stmt);
                
                $total_staff = $staff_count + $doctor_count;
                
                if ($total_staff > 0) {
                    $error_message = "Cannot delete department. It has {$total_staff} associated staff members.";
                } else {
                    $query = "DELETE FROM departments WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    
                    if (!$stmt) {
                        $error_message = "Query preparation failed: " . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($stmt, "i", $department_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            if (mysqli_affected_rows($conn) > 0) {
                                $success_message = "Department deleted successfully!";
                            } else {
                                $error_message = "Department not found.";
                            }
                        } else {
                            $error_message = "Error deleting department: " . mysqli_error($conn);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Pagination and filtering
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(d.name LIKE ? OR d.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total FROM departments d {$where_clause}";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_query);
    if (!$stmt) {
        die("Count query preparation failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_departments = mysqli_fetch_assoc($result)['total'];
    mysqli_stmt_close($stmt);
} else {
    $result = mysqli_query($conn, $count_query);
    $total_departments = mysqli_fetch_assoc($result)['total'];
}

// Get departments with statistics
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$query = "SELECT d.*, 
                 CONCAT(hd.first_name, ' ', hd.last_name) as head_doctor_name,
                 hd.employee_id as head_doctor_employee_id,
                 (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id) as doctor_count,
                 (SELECT COUNT(*) FROM staff s WHERE s.department_id = d.id) as staff_count
          FROM departments d 
          LEFT JOIN doctors hd ON d.head_doctor_id = hd.id 
          {$where_clause}
          ORDER BY d.name ASC 
          LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Main query preparation failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$departments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row;
}
mysqli_stmt_close($stmt);

// Get doctors for head doctor dropdown
$doctors_query = "SELECT d.*, u.email FROM doctors d LEFT JOIN users u ON d.user_id = u.id WHERE u.status = 'active' ORDER BY d.first_name, d.last_name";
$doctors_result = mysqli_query($conn, $doctors_query);
$doctors = [];
while ($doctor = mysqli_fetch_assoc($doctors_result)) {
    $doctors[] = $doctor;
}

// Get department statistics for dashboard
$stats_query = "SELECT 
                    COUNT(*) as total_departments,
                    COUNT(CASE WHEN head_doctor_id IS NOT NULL THEN 1 END) as departments_with_head,
                    AVG(doctor_count + staff_count) as avg_staff_per_dept
                FROM (
                    SELECT d.id, d.head_doctor_id,
                           (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id) as doctor_count,
                           (SELECT COUNT(*) FROM staff s WHERE s.department_id = d.id) as staff_count
                    FROM departments d
                ) as dept_stats";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Department Management</h1>
        <div class="d-sm-flex">
            <button class="btn btn-success me-3" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="fas fa-plus"></i> Add Department
            </button>
            <div class="btn-group">
                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="export_departments.php?format=csv">
                        <i class="fas fa-file-csv text-success me-2"></i>Export as CSV
                    </a>
                    <a class="dropdown-item" href="export_departments.php?format=pdf">
                        <i class="fas fa-file-pdf text-danger me-2"></i>Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Departments
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_departments; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Departments with Head
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['departments_with_head']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Average Staff per Dept
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['avg_staff_per_dept'], 1); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Departments without Head
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo ($total_departments - $stats['departments_with_head']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="search" class="form-label">Search Departments</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="departments.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Departments Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Departments (<?php echo $total_departments; ?> total)
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($departments)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Department Name</th>
                                <th>Head of Department</th>
                                <th>Staff Count</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="department-icon me-3">
                                                <i class="fas fa-building text-primary fa-2x"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-primary">
                                                    <?php echo htmlspecialchars($department['name']); ?>
                                                </div>
                                                <small class="text-muted">ID: <?php echo $department['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($department['head_doctor_name'])): ?>
                                            <div class="fw-bold">
                                                Dr. <?php echo htmlspecialchars($department['head_doctor_name']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-id-badge me-1"></i>
                                                <?php echo htmlspecialchars($department['head_doctor_employee_id']); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">
                                                <i class="fas fa-user-slash me-1"></i>
                                                No head assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="staff-stats">
                                            <span class="badge bg-info me-1">
                                                <i class="fas fa-user-md me-1"></i>
                                                <?php echo $department['doctor_count']; ?> Doctors
                                            </span>
                                            <span class="badge bg-success">
                                                <i class="fas fa-user-nurse me-1"></i>
                                                <?php echo $department['staff_count']; ?> Staff
                                            </span>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Total: <?php echo ($department['doctor_count'] + $department['staff_count']); ?> members
                                        </small>
                                    </td>
                                    <td>
                                        <div class="description-cell">
                                            <?php if (!empty($department['description'])): ?>
                                                <div class="text-truncate" style="max-width: 250px;" 
                                                     title="<?php echo htmlspecialchars($department['description']); ?>">
                                                    <?php echo htmlspecialchars($department['description']); ?>
                                                </div>
                                            <?php else: ?>
                                                <em class="text-muted">No description provided</em>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="viewDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="editDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)"
                                                    title="Edit Department">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deleteDepartment(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['name']); ?>')"
                                                    title="Delete Department">
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
                <?php
                $total_pages = ceil($total_departments / $limit);
                if ($total_pages > 1):
                ?>
                    <nav aria-label="Departments pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No departments found</h5>
                    <p class="text-muted">Create a new department to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-building me-2"></i>Add New Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label for="add_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="add_name" name="name" required>
                        <div class="form-text">Enter a unique name for the department</div>
                    </div>

                    <div class="mb-3">
                        <label for="add_head_doctor_id" class="form-label">Head of Department</label>
                        <select class="form-select" id="add_head_doctor_id" name="head_doctor_id">
                            <option value="">Select Head Doctor (Optional)</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    <?php if (!empty($doctor['specialization'])): ?>
                                        - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_description" name="description" 
                                  rows="4" placeholder="Describe the department's purpose, services, and specialties..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="department_id" id="edit_department_id">

                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="form-text">Enter a unique name for the department</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_head_doctor_id" class="form-label">Head of Department</label>
                        <select class="form-select" id="edit_head_doctor_id" name="head_doctor_id">
                            <option value="">Select Head Doctor (Optional)</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    <?php if (!empty($doctor['specialization'])): ?>
                                        - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" 
                                  rows="4" placeholder="Describe the department's purpose, services, and specialties..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Department Modal -->
<div class="modal fade" id="viewDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-building me-2"></i>Department Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="departmentDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="department_id" id="delete_department_id">

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Are you sure you want to delete the <strong id="delete_department_name"></strong> department?
                        This action cannot be undone.
                    </div>

                    <p class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Note: Departments with assigned staff members cannot be deleted.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.department-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(45deg, #007bff, #0056b3);
    border-radius: 10px;
    color: white !important;
}

.staff-stats .badge {
    font-size: 0.75em;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
</style>

<script>
function editDepartment(department) {
    document.getElementById('edit_department_id').value = department.id;
    document.getElementById('edit_name').value = department.name;
    document.getElementById('edit_head_doctor_id').value = department.head_doctor_id || '';
    document.getElementById('edit_description').value = department.description || '';

    new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
}

function deleteDepartment(id, name) {
    document.getElementById('delete_department_id').value = id;
    document.getElementById('delete_department_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteDepartmentModal')).show();
}

function viewDepartment(department) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-building me-2"></i>Department Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong><br>${department.name}</p>
                        <p><strong>Department ID:</strong><br>${department.id}</p>
                        <p><strong>Head of Department:</strong><br>
                        ${department.head_doctor_name ? 
                            `Dr. ${department.head_doctor_name}<br><small class="text-muted">ID: ${department.head_doctor_employee_id}</small>` : 
                            '<span class="text-muted">No head assigned</span>'
                        }</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Staff Statistics</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Doctors:</strong><br>
                        <span class="badge bg-info">${department.doctor_count} doctors</span></p>
                        <p><strong>Staff Members:</strong><br>
                        <span class="badge bg-success">${department.staff_count} staff</span></p>
                        <p><strong>Total Staff:</strong><br>
                        <span class="badge bg-primary">${parseInt(department.doctor_count) + parseInt(department.staff_count)} total members</span></p>
                    </div>
                </div>
            </div>
        </div>
        
        ${department.description ? `
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Description</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">${department.description}</p>
                        </div>
                    </div>
                </div>
            </div>
        ` : ''}
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Timestamps</h6>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            <i class="fas fa-plus-circle me-1"></i>
                            Created: ${new Date(department.created_at).toLocaleString()}
                            <br>
                            <i class="fas fa-edit me-1"></i>
                            Updated: ${new Date(department.updated_at).toLocaleString()}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('departmentDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewDepartmentModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
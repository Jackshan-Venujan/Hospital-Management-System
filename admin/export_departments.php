<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get export format
$format = $_GET['format'] ?? 'csv';

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query with same filters as main page
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

// Get departments data with statistics
$query = "SELECT d.*, 
                 CONCAT(hd.first_name, ' ', hd.last_name) as head_doctor_name,
                 hd.employee_id as head_doctor_employee_id,
                 hd.specialization as head_doctor_specialization,
                 (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id) as doctor_count,
                 (SELECT COUNT(*) FROM staff s WHERE s.department_id = d.id) as staff_count
          FROM departments d 
          LEFT JOIN doctors hd ON d.head_doctor_id = hd.id 
          {$where_clause}
          ORDER BY d.name ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

$departments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row;
}

if ($format === 'csv') {
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="departments_' . date('Y-m-d') . '.csv"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    fputcsv($output, [
        'Department ID',
        'Department Name',
        'Description',
        'Head of Department',
        'Head Doctor Employee ID',
        'Head Doctor Specialization',
        'Doctor Count',
        'Staff Count',
        'Total Staff',
        'Created Date',
        'Last Updated'
    ]);
    
    // Write CSV data
    foreach ($departments as $department) {
        fputcsv($output, [
            $department['id'],
            $department['name'],
            $department['description'] ?? '',
            $department['head_doctor_name'] ? 'Dr. ' . $department['head_doctor_name'] : 'Not assigned',
            $department['head_doctor_employee_id'] ?? '',
            $department['head_doctor_specialization'] ?? '',
            $department['doctor_count'],
            $department['staff_count'],
            ($department['doctor_count'] + $department['staff_count']),
            $department['created_at'],
            $department['updated_at']
        ]);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'pdf') {
    // For PDF, we'll create a simple HTML that can be converted to PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="departments_' . date('Y-m-d') . '.pdf"');
    
    // Calculate statistics
    $total_departments = count($departments);
    $departments_with_head = 0;
    $total_doctors = 0;
    $total_staff = 0;
    
    foreach ($departments as $dept) {
        if (!empty($dept['head_doctor_name'])) {
            $departments_with_head++;
        }
        $total_doctors += $dept['doctor_count'];
        $total_staff += $dept['staff_count'];
    }
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Departments Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .header { text-align: center; margin-bottom: 30px; }
            .stats { margin-bottom: 20px; background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
            .stat-card { text-align: center; }
            .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
            .stat-label { font-size: 11px; color: #666; text-transform: uppercase; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .with-head { background-color: #d4edda; }
            .without-head { background-color: #f8d7da; }
            .text-center { text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Hospital Management System</h1>
            <h2>Departments Report</h2>
            <p>Generated on: ' . date('F d, Y') . '</p>
        </div>
        
        <div class="stats">
            <h3>Summary Statistics</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">' . $total_departments . '</div>
                    <div class="stat-label">Total Departments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">' . $departments_with_head . '</div>
                    <div class="stat-label">With Department Head</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">' . $total_doctors . '</div>
                    <div class="stat-label">Total Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">' . $total_staff . '</div>
                    <div class="stat-label">Total Staff</div>
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Head of Department</th>
                    <th>Doctors</th>
                    <th>Staff</th>
                    <th>Total</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($departments as $department) {
        $row_class = !empty($department['head_doctor_name']) ? 'with-head' : 'without-head';
        echo '<tr class="' . $row_class . '">
                <td><strong>' . htmlspecialchars($department['name']) . '</strong></td>
                <td>' . ($department['head_doctor_name'] ? 'Dr. ' . htmlspecialchars($department['head_doctor_name']) : 'Not assigned') . '</td>
                <td class="text-center">' . $department['doctor_count'] . '</td>
                <td class="text-center">' . $department['staff_count'] . '</td>
                <td class="text-center"><strong>' . ($department['doctor_count'] + $department['staff_count']) . '</strong></td>
                <td>' . htmlspecialchars(substr($department['description'] ?? 'No description', 0, 100)) . '</td>
              </tr>';
    }
    
    echo '</tbody>
        </table>
        
        <div style="margin-top: 30px; font-size: 10px; color: #666;">
            <p><strong>Legend:</strong></p>
            <p><span style="background-color: #d4edda; padding: 2px 5px;">Green rows</span> = Departments with assigned head</p>
            <p><span style="background-color: #f8d7da; padding: 2px 5px;">Red rows</span> = Departments without head</p>
            <br>
            <p>This report was generated automatically by the Hospital Management System.</p>
        </div>
    </body>
    </html>';
    
} else {
    // Invalid format
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid export format specified.';
}
?>
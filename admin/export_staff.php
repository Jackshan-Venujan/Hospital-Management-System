<?php
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Get export format
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Get all staff members with department info
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, 
                 u.salary, u.hire_date, u.address, u.created_at,
                 d.name as department_name
          FROM users u 
          LEFT JOIN departments d ON u.department_id = d.id
          WHERE u.role IN ('doctor', 'nurse', 'receptionist')
          ORDER BY u.role, u.first_name, u.last_name";

$result = mysqli_query($conn, $query);
$staff_members = [];
while ($row = mysqli_fetch_assoc($result)) {
    $staff_members[] = $row;
}

if ($format === 'csv') {
    // CSV Export
    $filename = "staff_export_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for proper Excel display
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    $headers = [
        'Staff ID',
        'First Name',
        'Last Name',
        'Full Name',
        'Email',
        'Phone',
        'Role',
        'Department',
        'Salary',
        'Hire Date',
        'Address',
        'Registration Date'
    ];
    
    fputcsv($output, $headers);
    
    // CSV Data
    foreach ($staff_members as $staff) {
        $row = [
            $staff['id'],
            $staff['first_name'],
            $staff['last_name'],
            $staff['first_name'] . ' ' . $staff['last_name'],
            $staff['email'],
            $staff['phone'] ?: 'Not specified',
            ucfirst($staff['role']),
            $staff['department_name'] ?: 'Not assigned',
            $staff['salary'] ? 'Rs. ' . number_format($staff['salary'], 2) : 'Not specified',
            $staff['hire_date'] ? date('Y-m-d', strtotime($staff['hire_date'])) : 'Not specified',
            $staff['address'] ?: 'Not specified',
            $staff['created_at'] ? date('Y-m-d H:i:s', strtotime($staff['created_at'])) : 'N/A'
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();

} elseif ($format === 'pdf') {
    // PDF Export using HTML to PDF conversion
    $filename = "staff_export_" . date('Y-m-d_H-i-s') . ".pdf";
    
    // Calculate statistics
    $total_staff = count($staff_members);
    $doctors = count(array_filter($staff_members, function($s) { return $s['role'] === 'doctor'; }));
    $nurses = count(array_filter($staff_members, function($s) { return $s['role'] === 'nurse'; }));
    $receptionists = count(array_filter($staff_members, function($s) { return $s['role'] === 'receptionist'; }));
    $total_salary = array_sum(array_map(function($s) { return $s['salary'] ?: 0; }, $staff_members));
    $avg_salary = $total_staff > 0 ? $total_salary / $total_staff : 0;
    
    // Create HTML content for PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Staff Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 20px; font-weight: bold; color: #2c3e50; }
            .report-title { font-size: 16px; color: #7f8c8d; margin-top: 5px; }
            .report-date { font-size: 10px; color: #95a5a6; margin-top: 10px; }
            .summary { margin-bottom: 30px; background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
            .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
            .summary-item { text-align: center; }
            .summary-number { font-size: 18px; font-weight: bold; color: #007bff; }
            .summary-label { font-size: 10px; color: #6c757d; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .role-doctor { color: #007bff; font-weight: bold; }
            .role-nurse { color: #28a745; font-weight: bold; }
            .role-receptionist { color: #17a2b8; font-weight: bold; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .page-break { page-break-before: always; }
            .footer { margin-top: 30px; font-size: 9px; color: #7f8c8d; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">Hospital Management System</div>
            <div class="report-title">Staff Report</div>
            <div class="report-date">Generated on: ' . date('F j, Y \a\t g:i A') . '</div>
        </div>
        
        <div class="summary">
            <h3>Staff Summary</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-number">' . $total_staff . '</div>
                    <div class="summary-label">Total Staff</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">' . $doctors . '</div>
                    <div class="summary-label">Doctors</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">' . $nurses . '</div>
                    <div class="summary-label">Nurses</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">' . $receptionists . '</div>
                    <div class="summary-label">Receptionists</div>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <p><strong>Total Payroll:</strong> Rs. ' . number_format($total_salary, 2) . '</p>
                <p><strong>Average Salary:</strong> Rs. ' . number_format($avg_salary, 2) . '</p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Hire Date</th>
                    <th>Salary</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($staff_members as $staff) {
        $role_class = 'role-' . $staff['role'];
        $salary_display = $staff['salary'] ? 'Rs. ' . number_format($staff['salary'], 2) : 'N/A';
        $hire_date_display = $staff['hire_date'] ? date('M d, Y', strtotime($staff['hire_date'])) : 'N/A';
        
        $html .= '<tr>
                    <td class="text-center">' . $staff['id'] . '</td>
                    <td><strong>' . htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) . '</strong></td>
                    <td><span class="' . $role_class . '">' . ucfirst($staff['role']) . '</span></td>
                    <td>' . htmlspecialchars($staff['department_name'] ?: 'Not assigned') . '</td>
                    <td>' . htmlspecialchars($staff['email']) . '</td>
                    <td>' . htmlspecialchars($staff['phone'] ?: 'N/A') . '</td>
                    <td>' . $hire_date_display . '</td>
                    <td class="text-right">' . $salary_display . '</td>
                  </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="footer">
            <div>This report contains ' . $total_staff . ' staff members across all departments.</div>
            <div>Generated by Hospital Management System on ' . date('F j, Y \a\t g:i A') . '</div>
            <div style="margin-top: 10px;">
                <strong>Role Distribution:</strong> 
                Doctors: ' . $doctors . ' (' . ($total_staff > 0 ? round(($doctors / $total_staff) * 100, 1) : 0) . '%), 
                Nurses: ' . $nurses . ' (' . ($total_staff > 0 ? round(($nurses / $total_staff) * 100, 1) : 0) . '%), 
                Receptionists: ' . $receptionists . ' (' . ($total_staff > 0 ? round(($receptionists / $total_staff) * 100, 1) : 0) . '%)
            </div>
        </div>
    </body>
    </html>';
    
    // For now, we'll output the HTML directly
    // In production, you would convert this to PDF using a library like dompdf or wkhtmltopdf
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    // Simple HTML to PDF conversion (you can replace this with actual PDF generation)
    echo $html;
    exit();

} else {
    // Invalid format
    set_message('Invalid export format specified.', 'danger');
    redirect('staff.php');
}
?>
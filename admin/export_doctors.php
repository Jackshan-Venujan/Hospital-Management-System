<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

// Get filters from query parameters
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$specialization_filter = $_GET['specialization'] ?? '';
$status_filter = $_GET['status'] ?? '';
$format = $_GET['format'] ?? 'csv'; // csv or pdf

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(d.first_name LIKE :search OR d.last_name LIKE :search OR d.employee_id LIKE :search OR d.email LIKE :search OR d.specialization LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($department_filter)) {
    $conditions[] = "d.department_id = :department";
    $params[':department'] = $department_filter;
}

if (!empty($specialization_filter)) {
    $conditions[] = "d.specialization = :specialization";
    $params[':specialization'] = $specialization_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    // Get doctors data for export
    $doctors_query = "
        SELECT 
            d.employee_id,
            d.first_name,
            d.last_name,
            d.phone,
            d.email,
            d.specialization,
            dept.name as department_name,
            d.qualification,
            d.experience_years,
            d.consultation_fee,
            d.schedule_start,
            d.schedule_end,
            d.available_days,
            u.username,
            u.status as user_status,
            u.created_at as registered_date
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        LEFT JOIN departments dept ON d.department_id = dept.id
        {$where_clause}
        ORDER BY d.created_at DESC
    ";
    
    $db->query($doctors_query);
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $doctors = $db->resultSet();
    
    if (empty($doctors)) {
        die('No doctors found to export.');
    }
    
    if ($format === 'csv') {
        // Export as CSV
        $filename = 'doctors_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        $headers = [
            'Employee ID',
            'First Name',
            'Last Name',
            'Phone',
            'Email',
            'Specialization',
            'Department',
            'Qualification',
            'Experience (Years)',
            'Consultation Fee ($)',
            'Schedule Start',
            'Schedule End',
            'Available Days',
            'Username',
            'Status',
            'Registered Date'
        ];
        
        fputcsv($output, $headers);
        
        // Write doctor data
        foreach ($doctors as $doctor) {
            $row = [
                $doctor['employee_id'],
                $doctor['first_name'],
                $doctor['last_name'],
                $doctor['phone'],
                $doctor['email'],
                $doctor['specialization'],
                $doctor['department_name'] ?: 'Not Assigned',
                $doctor['qualification'],
                $doctor['experience_years'],
                number_format($doctor['consultation_fee'], 2),
                $doctor['schedule_start'],
                $doctor['schedule_end'],
                $doctor['available_days'],
                $doctor['username'],
                ucfirst($doctor['user_status']),
                format_date($doctor['registered_date'])
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
        
    } elseif ($format === 'pdf') {
        // Create HTML content for PDF
        $filename = 'doctors_export_' . date('Y-m-d_H-i-s') . '.pdf';
        
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Doctors Export</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 11px; }
                .header { text-align: center; margin-bottom: 30px; }
                .info { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .text-center { text-align: center; }
                .status-active { color: green; font-weight: bold; }
                .status-inactive { color: red; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
                <h2>Doctors Export Report</h2>
                <p>Generated on: ' . date('F d, Y H:i:s') . '</p>
            </div>
            
            <div class="info">
                <p><strong>Total Doctors:</strong> ' . count($doctors) . '</p>';
        
        if (!empty($search)) {
            $html .= '<p><strong>Search Filter:</strong> ' . htmlspecialchars($search) . '</p>';
        }
        
        if (!empty($department_filter)) {
            // Get department name
            $db->query('SELECT name FROM departments WHERE id = :id');
            $db->bind(':id', $department_filter);
            $dept = $db->single();
            $html .= '<p><strong>Department Filter:</strong> ' . htmlspecialchars($dept['name']) . '</p>';
        }
        
        if (!empty($specialization_filter)) {
            $html .= '<p><strong>Specialization Filter:</strong> ' . htmlspecialchars($specialization_filter) . '</p>';
        }
        
        if (!empty($status_filter)) {
            $html .= '<p><strong>Status Filter:</strong> ' . ucfirst(htmlspecialchars($status_filter)) . '</p>';
        }
        
        $html .= '</div>
            
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Department</th>
                        <th>Experience</th>
                        <th>Fee ($)</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Contact</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($doctors as $doctor) {
            $status_class = $doctor['user_status'] === 'active' ? 'status-active' : 'status-inactive';
            $html .= '<tr>
                <td>' . htmlspecialchars($doctor['employee_id']) . '</td>
                <td>Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . '</td>
                <td>' . htmlspecialchars($doctor['specialization']) . '</td>
                <td>' . htmlspecialchars($doctor['department_name'] ?: 'Not Assigned') . '</td>
                <td>' . $doctor['experience_years'] . ' years</td>
                <td>$' . number_format($doctor['consultation_fee'], 2) . '</td>
                <td>' . format_time($doctor['schedule_start']) . ' - ' . format_time($doctor['schedule_end']) . '</td>
                <td class="' . $status_class . '">' . ucfirst(htmlspecialchars($doctor['user_status'])) . '</td>
                <td>' . htmlspecialchars($doctor['phone']) . '<br>' . htmlspecialchars($doctor['email']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
            
            <div style="margin-top: 30px; font-size: 10px;">
                <h4>Summary by Status:</h4>';
        
        // Calculate status summary
        $active_count = 0;
        $inactive_count = 0;
        foreach ($doctors as $doctor) {
            if ($doctor['user_status'] === 'active') {
                $active_count++;
            } else {
                $inactive_count++;
            }
        }
        
        $html .= '<p>Active Doctors: <strong>' . $active_count . '</strong></p>
                <p>Inactive Doctors: <strong>' . $inactive_count . '</strong></p>';
        
        // Calculate specialization summary
        $specializations = [];
        foreach ($doctors as $doctor) {
            $spec = $doctor['specialization'];
            if (!isset($specializations[$spec])) {
                $specializations[$spec] = 0;
            }
            $specializations[$spec]++;
        }
        
        $html .= '<h4>Summary by Specialization:</h4>';
        foreach ($specializations as $spec => $count) {
            $html .= '<p>' . htmlspecialchars($spec) . ': <strong>' . $count . '</strong></p>';
        }
        
        $html .= '</div>
        </body>
        </html>';
        
        // Output HTML that can be printed as PDF
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        echo $html;
        exit;
    }
    
} catch (Exception $e) {
    die('Error exporting doctors: ' . $e->getMessage());
}
?>
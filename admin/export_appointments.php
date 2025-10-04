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
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$doctor_filter = isset($_GET['doctor']) ? intval($_GET['doctor']) : '';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';

// Build query with same filters as main page
$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR a.appointment_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($doctor_filter)) {
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
    $types .= "i";
}

if (!empty($date_filter)) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get appointments data
$query = "SELECT a.*, 
                 p.first_name as patient_first_name,
                 p.last_name as patient_last_name,
                 p.patient_id as patient_number,
                 p.phone as patient_phone,
                 d.first_name as doctor_first_name,
                 d.last_name as doctor_last_name,
                 d.employee_id as doctor_employee_id,
                 d.specialization,
                 dept.name as department_name
          FROM appointments a 
          LEFT JOIN patients p ON a.patient_id = p.id 
          LEFT JOIN doctors d ON a.doctor_id = d.id 
          LEFT JOIN departments dept ON d.department_id = dept.id
          {$where_clause}
          ORDER BY a.appointment_date DESC, a.appointment_time DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

$appointments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $appointments[] = $row;
}

if ($format === 'csv') {
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d') . '.csv"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    fputcsv($output, [
        'Appointment Number',
        'Patient Name',
        'Patient ID',
        'Patient Phone',
        'Doctor Name',
        'Doctor Employee ID',
        'Specialization',
        'Department',
        'Date',
        'Time',
        'Status',
        'Reason',
        'Notes',
        'Created At'
    ]);
    
    // Write CSV data
    foreach ($appointments as $appointment) {
        fputcsv($output, [
            $appointment['appointment_number'],
            $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'],
            $appointment['patient_number'],
            $appointment['patient_phone'] ?? '',
            'Dr. ' . $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'],
            $appointment['doctor_employee_id'] ?? '',
            $appointment['specialization'] ?? '',
            $appointment['department_name'] ?? '',
            $appointment['appointment_date'],
            $appointment['appointment_time'],
            ucfirst(str_replace('-', ' ', $appointment['status'])),
            $appointment['reason'] ?? '',
            $appointment['notes'] ?? '',
            $appointment['created_at']
        ]);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'pdf') {
    // For PDF, we'll create a simple HTML that can be converted to PDF
    // In a production environment, you might want to use a library like TCPDF or FPDF
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d') . '.pdf"');
    
    // Simple HTML to PDF conversion (this would need a proper PDF library in production)
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Appointments Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .header { text-align: center; margin-bottom: 30px; }
            .stats { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .status-scheduled { background-color: #fff3cd; }
            .status-confirmed { background-color: #d1ecf1; }
            .status-completed { background-color: #d4edda; }
            .status-cancelled { background-color: #e2e3e5; }
            .status-no-show { background-color: #f8d7da; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Hospital Management System</h1>
            <h2>Appointments Report</h2>
            <p>Generated on: ' . date('F d, Y') . '</p>
        </div>
        
        <div class="stats">
            <h3>Report Summary</h3>
            <p><strong>Total Appointments:</strong> ' . count($appointments) . '</p>';
            
    // Calculate status statistics
    $status_counts = [];
    foreach ($appointments as $appointment) {
        $status = $appointment['status'];
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
    }
    
    foreach ($status_counts as $status => $count) {
        echo '<p><strong>' . ucfirst(str_replace('-', ' ', $status)) . ':</strong> ' . $count . '</p>';
    }
    
    echo '</div>
        
        <table>
            <thead>
                <tr>
                    <th>Appointment #</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($appointments as $appointment) {
        $status_class = 'status-' . str_replace('_', '-', $appointment['status']);
        echo '<tr class="' . $status_class . '">
                <td>' . htmlspecialchars($appointment['appointment_number']) . '</td>
                <td>' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '</td>
                <td>Dr. ' . htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) . '</td>
                <td>' . date('M d, Y', strtotime($appointment['appointment_date'])) . '</td>
                <td>' . date('h:i A', strtotime($appointment['appointment_time'])) . '</td>
                <td>' . ucfirst(str_replace('-', ' ', $appointment['status'])) . '</td>
                <td>' . htmlspecialchars(substr($appointment['reason'] ?? '', 0, 50)) . '</td>
              </tr>';
    }
    
    echo '</tbody>
        </table>
        
        <div style="margin-top: 30px; font-size: 10px; color: #666;">
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
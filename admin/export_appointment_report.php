<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$db = new Database();

// Date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$doctor_id = $_GET['doctor_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$appointment_status = $_GET['status'] ?? '';
$export_format = $_GET['format'] ?? 'excel'; // excel or csv

// Set headers for download
$filename = 'appointment_report_' . date('Y-m-d') . ($export_format == 'csv' ? '.csv' : '.xlsx');

if ($export_format == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
} else {
    // For Excel format, we'll create a simple HTML table that Excel can open
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

try {
    // Build where conditions
    $where_conditions = ["a.appointment_date BETWEEN :start_date AND :end_date"];
    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if (!empty($doctor_id)) {
        $where_conditions[] = "a.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctor_id;
    }

    if (!empty($department_id)) {
        $where_conditions[] = "d.department_id = :department_id";
        $params[':department_id'] = $department_id;
    }

    if (!empty($appointment_status)) {
        $where_conditions[] = "a.status = :status";
        $params[':status'] = $appointment_status;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get appointment data
    $db->query("SELECT 
                    a.appointment_number,
                    a.appointment_date,
                    a.appointment_time,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.patient_id,
                    p.phone as patient_phone,
                    p.email as patient_email,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                    d.employee_id as doctor_id,
                    d.specialization,
                    dept.name as department_name,
                    a.reason,
                    a.status,
                    a.notes,
                    a.created_at,
                    d.consultation_fee
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN departments dept ON d.department_id = dept.id
                WHERE $where_clause
                ORDER BY a.appointment_date DESC, a.appointment_time DESC");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $appointments = $db->resultSet();

    // Get summary statistics
    $db->query("SELECT 
                    COUNT(*) as total_appointments,
                    COUNT(CASE WHEN a.status = 'scheduled' THEN 1 END) as scheduled,
                    COUNT(CASE WHEN a.status = 'confirmed' THEN 1 END) as confirmed,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN a.status = 'no-show' THEN 1 END) as no_show
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE $where_clause");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $summary = $db->single();

} catch (Exception $e) {
    die('Error generating appointment report: ' . $e->getMessage());
}

if ($export_format == 'csv') {
    // CSV Export
    // Header row
    fputcsv($output, [
        'Appointment Report - Generated on ' . date('Y-m-d H:i:s'),
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    ]);
    
    fputcsv($output, ['']); // Empty row
    
    // Summary statistics
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Total Appointments:', $summary['total_appointments']]);
    fputcsv($output, ['Scheduled:', $summary['scheduled']]);
    fputcsv($output, ['Confirmed:', $summary['confirmed']]);
    fputcsv($output, ['Completed:', $summary['completed']]);
    fputcsv($output, ['Cancelled:', $summary['cancelled']]);
    fputcsv($output, ['No Shows:', $summary['no_show']]);
    
    fputcsv($output, ['']); // Empty row
    fputcsv($output, ['FILTERS APPLIED']);
    fputcsv($output, ['Date Range:', $start_date . ' to ' . $end_date]);
    if ($doctor_id) fputcsv($output, ['Doctor ID:', $doctor_id]);
    if ($department_id) fputcsv($output, ['Department ID:', $department_id]);
    if ($appointment_status) fputcsv($output, ['Status:', ucfirst($appointment_status)]);
    
    fputcsv($output, ['']); // Empty row
    fputcsv($output, ['']); // Empty row
    
    // Column headers
    fputcsv($output, [
        'Appointment Number',
        'Date',
        'Time',
        'Patient Name',
        'Patient ID',
        'Patient Phone',
        'Patient Email',
        'Doctor Name',
        'Doctor ID',
        'Specialization',
        'Department',
        'Reason',
        'Status',
        'Consultation Fee (Rs.)',
        'Notes',
        'Created Date'
    ]);
    
    // Appointment data
    foreach ($appointments as $apt) {
        fputcsv($output, [
            $apt['appointment_number'],
            date('Y-m-d', strtotime($apt['appointment_date'])),
            date('H:i', strtotime($apt['appointment_time'])),
            $apt['patient_name'],
            $apt['patient_id'],
            $apt['patient_phone'],
            $apt['patient_email'],
            $apt['doctor_name'],
            $apt['doctor_id'],
            $apt['specialization'],
            $apt['department_name'] ?: 'N/A',
            $apt['reason'] ?: 'N/A',
            ucfirst($apt['status']),
            number_format($apt['consultation_fee'], 2),
            $apt['notes'] ?: 'N/A',
            date('Y-m-d', strtotime($apt['created_at']))
        ]);
    }
    
    fclose($output);
    
} else {
    // Excel HTML Export
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }';
    echo 'th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f0f0f0; font-weight: bold; }';
    echo '.summary { background-color: #e8f4fd; }';
    echo '.header { background-color: #d4edda; font-size: 16px; font-weight: bold; }';
    echo '.status-completed { background-color: #d1ecf1; color: #0c5460; }';
    echo '.status-cancelled { background-color: #f8d7da; color: #721c24; }';
    echo '.status-scheduled { background-color: #fff3cd; color: #856404; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<table>';
    
    // Header
    echo '<tr class="header"><td colspan="16">Appointment Report - Generated on ' . date('Y-m-d H:i:s') . '</td></tr>';
    echo '<tr><td colspan="16"></td></tr>';
    
    // Summary statistics
    echo '<tr class="summary"><td colspan="16"><strong>SUMMARY STATISTICS</strong></td></tr>';
    echo '<tr><td><strong>Total Appointments:</strong></td><td>' . number_format($summary['total_appointments']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>Scheduled:</strong></td><td>' . number_format($summary['scheduled']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>Confirmed:</strong></td><td>' . number_format($summary['confirmed']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>Completed:</strong></td><td>' . number_format($summary['completed']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>Cancelled:</strong></td><td>' . number_format($summary['cancelled']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>No Shows:</strong></td><td>' . number_format($summary['no_show']) . '</td><td colspan="14"></td></tr>';
    
    echo '<tr><td colspan="16"></td></tr>';
    
    // Filters
    echo '<tr class="summary"><td colspan="16"><strong>FILTERS APPLIED</strong></td></tr>';
    echo '<tr><td><strong>Date Range:</strong></td><td>' . $start_date . ' to ' . $end_date . '</td><td colspan="14"></td></tr>';
    if ($doctor_id) echo '<tr><td><strong>Doctor ID:</strong></td><td>' . $doctor_id . '</td><td colspan="14"></td></tr>';
    if ($department_id) echo '<tr><td><strong>Department ID:</strong></td><td>' . $department_id . '</td><td colspan="14"></td></tr>';
    if ($appointment_status) echo '<tr><td><strong>Status:</strong></td><td>' . ucfirst($appointment_status) . '</td><td colspan="14"></td></tr>';
    
    echo '<tr><td colspan="16"></td></tr>';
    echo '<tr><td colspan="16"></td></tr>';
    
    // Column headers
    echo '<tr>';
    echo '<th>Appointment #</th>';
    echo '<th>Date</th>';
    echo '<th>Time</th>';
    echo '<th>Patient Name</th>';
    echo '<th>Patient ID</th>';
    echo '<th>Patient Phone</th>';
    echo '<th>Patient Email</th>';
    echo '<th>Doctor Name</th>';
    echo '<th>Doctor ID</th>';
    echo '<th>Specialization</th>';
    echo '<th>Department</th>';
    echo '<th>Reason</th>';
    echo '<th>Status</th>';
    echo '<th>Fee (Rs.)</th>';
    echo '<th>Notes</th>';
    echo '<th>Created</th>';
    echo '</tr>';
    
    // Appointment data
    foreach ($appointments as $apt) {
        $status_class = 'status-' . $apt['status'];
        echo '<tr>';
        echo '<td>' . htmlspecialchars($apt['appointment_number']) . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($apt['appointment_date'])) . '</td>';
        echo '<td>' . date('H:i', strtotime($apt['appointment_time'])) . '</td>';
        echo '<td>' . htmlspecialchars($apt['patient_name']) . '</td>';
        echo '<td>' . htmlspecialchars($apt['patient_id']) . '</td>';
        echo '<td>' . htmlspecialchars($apt['patient_phone']) . '</td>';
        echo '<td>' . htmlspecialchars($apt['patient_email']) . '</td>';
        echo '<td>' . htmlspecialchars($apt['doctor_name']) . '</td>';
        echo '<td>' . htmlspecialchars($apt['doctor_id']) . '</td>';
        echo '<td>' . htmlspecialchars($apt['specialization']) . '</td>';
        echo '<td>' . htmlspecialchars($apt['department_name'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($apt['reason'] ?: 'N/A') . '</td>';
        echo '<td class="' . $status_class . '">' . ucfirst($apt['status']) . '</td>';
        echo '<td>' . number_format($apt['consultation_fee'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($apt['notes'] ?: 'N/A') . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($apt['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
}

exit;
?>
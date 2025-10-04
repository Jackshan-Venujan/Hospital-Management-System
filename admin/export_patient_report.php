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
$age_group = $_GET['age_group'] ?? '';
$gender = $_GET['gender'] ?? '';
$blood_group = $_GET['blood_group'] ?? '';
$export_format = $_GET['format'] ?? 'excel'; // excel or csv

// Set headers for download
$filename = 'patient_report_' . date('Y-m-d') . ($export_format == 'csv' ? '.csv' : '.xlsx');

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
    // Patient Activity Report with filters
    $where_conditions = ["p.id IS NOT NULL"];
    $params = [];

    if (!empty($start_date) && !empty($end_date)) {
        $where_conditions[] = "p.created_at BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    }

    if (!empty($age_group)) {
        switch ($age_group) {
            case 'under_18':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 18";
                break;
            case '18_30':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 18 AND 30";
                break;
            case '31_50':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 31 AND 50";
                break;
            case '51_70':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 51 AND 70";
                break;
            case 'over_70':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) > 70";
                break;
        }
    }

    if (!empty($gender)) {
        $where_conditions[] = "p.gender = :gender";
        $params[':gender'] = $gender;
    }

    if (!empty($blood_group)) {
        $where_conditions[] = "p.blood_group = :blood_group";
        $params[':blood_group'] = $blood_group;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get patient data
    $db->query("SELECT 
                    p.patient_id, p.first_name, p.last_name, p.gender, 
                    p.blood_group, p.phone, p.email, p.address,
                    p.date_of_birth, p.emergency_contact_name, p.emergency_contact_phone,
                    TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                    p.created_at as registration_date,
                    COUNT(a.id) as total_appointments,
                    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
                    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled_appointments,
                    MAX(a.appointment_date) as last_visit
                FROM patients p
                LEFT JOIN appointments a ON p.id = a.patient_id
                WHERE $where_clause
                GROUP BY p.id, p.patient_id, p.first_name, p.last_name, p.gender, p.blood_group, 
                         p.phone, p.email, p.address, p.date_of_birth, p.emergency_contact_name, 
                         p.emergency_contact_phone, age, p.created_at
                ORDER BY p.created_at DESC");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $patients = $db->resultSet();

    // Get summary statistics
    $db->query("SELECT 
                    COUNT(*) as total_patients,
                    COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_patients,
                    COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_patients,
                    ROUND(AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())), 1) as avg_age
                FROM patients p
                WHERE $where_clause");

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $summary = $db->single();

} catch (Exception $e) {
    die('Error generating patient report: ' . $e->getMessage());
}

if ($export_format == 'csv') {
    // CSV Export
    // Header row
    fputcsv($output, [
        'Patient Report - Generated on ' . date('Y-m-d H:i:s'),
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
    fputcsv($output, ['Total Patients:', $summary['total_patients']]);
    fputcsv($output, ['Male Patients:', $summary['male_patients']]);
    fputcsv($output, ['Female Patients:', $summary['female_patients']]);
    fputcsv($output, ['Average Age:', $summary['avg_age']]);
    
    fputcsv($output, ['']); // Empty row
    fputcsv($output, ['FILTERS APPLIED']);
    fputcsv($output, ['Date Range:', $start_date . ' to ' . $end_date]);
    if ($age_group) fputcsv($output, ['Age Group:', str_replace('_', '-', $age_group)]);
    if ($gender) fputcsv($output, ['Gender:', ucfirst($gender)]);
    if ($blood_group) fputcsv($output, ['Blood Group:', $blood_group]);
    
    fputcsv($output, ['']); // Empty row
    fputcsv($output, ['']); // Empty row
    
    // Column headers
    fputcsv($output, [
        'Patient ID',
        'First Name',
        'Last Name', 
        'Age',
        'Gender',
        'Blood Group',
        'Phone',
        'Email',
        'Address',
        'Emergency Contact',
        'Emergency Phone',
        'Registration Date',
        'Total Appointments',
        'Completed',
        'Cancelled',
        'Last Visit'
    ]);
    
    // Patient data
    foreach ($patients as $patient) {
        fputcsv($output, [
            $patient['patient_id'],
            $patient['first_name'],
            $patient['last_name'],
            $patient['age'],
            ucfirst($patient['gender']),
            $patient['blood_group'] ?: 'N/A',
            $patient['phone'],
            $patient['email'],
            $patient['address'],
            $patient['emergency_contact_name'],
            $patient['emergency_contact_phone'],
            date('Y-m-d', strtotime($patient['registration_date'])),
            $patient['total_appointments'],
            $patient['completed_appointments'],
            $patient['cancelled_appointments'],
            $patient['last_visit'] ? date('Y-m-d', strtotime($patient['last_visit'])) : 'Never'
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
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<table>';
    
    // Header
    echo '<tr class="header"><td colspan="16">Patient Report - Generated on ' . date('Y-m-d H:i:s') . '</td></tr>';
    echo '<tr><td colspan="16"></td></tr>';
    
    // Summary statistics
    echo '<tr class="summary"><td colspan="16"><strong>SUMMARY STATISTICS</strong></td></tr>';
    echo '<tr><td><strong>Total Patients:</strong></td><td>' . number_format($summary['total_patients']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>Male Patients:</strong></td><td>' . number_format($summary['male_patients']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>Female Patients:</strong></td><td>' . number_format($summary['female_patients']) . '</td><td colspan="14"></td></tr>';
    echo '<tr><td><strong>Average Age:</strong></td><td>' . $summary['avg_age'] . ' years</td><td colspan="14"></td></tr>';
    
    echo '<tr><td colspan="16"></td></tr>';
    
    // Filters
    echo '<tr class="summary"><td colspan="16"><strong>FILTERS APPLIED</strong></td></tr>';
    echo '<tr><td><strong>Date Range:</strong></td><td>' . $start_date . ' to ' . $end_date . '</td><td colspan="14"></td></tr>';
    if ($age_group) echo '<tr><td><strong>Age Group:</strong></td><td>' . str_replace('_', '-', $age_group) . '</td><td colspan="14"></td></tr>';
    if ($gender) echo '<tr><td><strong>Gender:</strong></td><td>' . ucfirst($gender) . '</td><td colspan="14"></td></tr>';
    if ($blood_group) echo '<tr><td><strong>Blood Group:</strong></td><td>' . $blood_group . '</td><td colspan="14"></td></tr>';
    
    echo '<tr><td colspan="16"></td></tr>';
    echo '<tr><td colspan="16"></td></tr>';
    
    // Column headers
    echo '<tr>';
    echo '<th>Patient ID</th>';
    echo '<th>First Name</th>';
    echo '<th>Last Name</th>';
    echo '<th>Age</th>';
    echo '<th>Gender</th>';
    echo '<th>Blood Group</th>';
    echo '<th>Phone</th>';
    echo '<th>Email</th>';
    echo '<th>Address</th>';
    echo '<th>Emergency Contact</th>';
    echo '<th>Emergency Phone</th>';
    echo '<th>Registration Date</th>';
    echo '<th>Total Appointments</th>';
    echo '<th>Completed</th>';
    echo '<th>Cancelled</th>';
    echo '<th>Last Visit</th>';
    echo '</tr>';
    
    // Patient data
    foreach ($patients as $patient) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($patient['patient_id']) . '</td>';
        echo '<td>' . htmlspecialchars($patient['first_name']) . '</td>';
        echo '<td>' . htmlspecialchars($patient['last_name']) . '</td>';
        echo '<td>' . $patient['age'] . '</td>';
        echo '<td>' . ucfirst($patient['gender']) . '</td>';
        echo '<td>' . ($patient['blood_group'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($patient['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($patient['email']) . '</td>';
        echo '<td>' . htmlspecialchars($patient['address']) . '</td>';
        echo '<td>' . htmlspecialchars($patient['emergency_contact_name']) . '</td>';
        echo '<td>' . htmlspecialchars($patient['emergency_contact_phone']) . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($patient['registration_date'])) . '</td>';
        echo '<td>' . $patient['total_appointments'] . '</td>';
        echo '<td>' . $patient['completed_appointments'] . '</td>';
        echo '<td>' . $patient['cancelled_appointments'] . '</td>';
        echo '<td>' . ($patient['last_visit'] ? date('Y-m-d', strtotime($patient['last_visit'])) : 'Never') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
}

exit;
?>
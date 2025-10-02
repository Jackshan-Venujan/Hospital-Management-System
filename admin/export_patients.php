<?php
require_once '../includes/config.php';

// Check admin access
check_role_access(['admin']);

// Get filters from query parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$format = $_GET['format'] ?? 'csv'; // csv or pdf

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(p.first_name LIKE :search OR p.last_name LIKE :search OR p.patient_id LIKE :search OR p.email LIKE :search OR p.phone LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($status_filter)) {
    $conditions[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    // Get patients data for export
    $patients_query = "
        SELECT 
            p.patient_id,
            p.first_name,
            p.last_name,
            p.date_of_birth,
            p.gender,
            p.phone,
            p.email,
            p.address,
            p.emergency_contact_name,
            p.emergency_contact_phone,
            p.blood_group,
            p.allergies,
            p.medical_history,
            p.insurance_number,
            u.username,
            u.status as user_status,
            u.created_at as registered_date
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        {$where_clause}
        ORDER BY p.created_at DESC
    ";
    
    $db->query($patients_query);
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $patients = $db->resultSet();
    
    if (empty($patients)) {
        die('No patients found to export.');
    }
    
    if ($format === 'csv') {
        // Export as CSV
        $filename = 'patients_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        $headers = [
            'Patient ID',
            'First Name',
            'Last Name',
            'Date of Birth',
            'Age',
            'Gender',
            'Phone',
            'Email',
            'Address',
            'Emergency Contact',
            'Emergency Phone',
            'Blood Group',
            'Allergies',
            'Medical History',
            'Insurance Number',
            'Username',
            'Status',
            'Registered Date'
        ];
        
        fputcsv($output, $headers);
        
        // Write patient data
        foreach ($patients as $patient) {
            $age = date('Y') - date('Y', strtotime($patient['date_of_birth']));
            
            $row = [
                $patient['patient_id'],
                $patient['first_name'],
                $patient['last_name'],
                $patient['date_of_birth'],
                $age,
                $patient['gender'],
                $patient['phone'],
                $patient['email'],
                $patient['address'],
                $patient['emergency_contact_name'],
                $patient['emergency_contact_phone'],
                $patient['blood_group'],
                $patient['allergies'],
                $patient['medical_history'],
                $patient['insurance_number'],
                $patient['username'],
                ucfirst($patient['user_status']),
                format_date($patient['registered_date'])
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
        
    } elseif ($format === 'pdf') {
        // For PDF export, we'll create a simple HTML table and convert it
        // In a real application, you'd use a library like TCPDF or FPDF
        
        $filename = 'patients_export_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Create HTML content for PDF
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Patients Export</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; }
                .info { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
                <h2>Patients Export Report</h2>
                <p>Generated on: ' . date('F d, Y H:i:s') . '</p>
            </div>
            
            <div class="info">
                <p><strong>Total Patients:</strong> ' . count($patients) . '</p>';
        
        if (!empty($search)) {
            $html .= '<p><strong>Search Filter:</strong> ' . htmlspecialchars($search) . '</p>';
        }
        
        if (!empty($status_filter)) {
            $html .= '<p><strong>Status Filter:</strong> ' . ucfirst(htmlspecialchars($status_filter)) . '</p>';
        }
        
        $html .= '</div>
            
            <table>
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Blood Group</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($patients as $patient) {
            $age = date('Y') - date('Y', strtotime($patient['date_of_birth']));
            $html .= '<tr>
                <td>' . htmlspecialchars($patient['patient_id']) . '</td>
                <td>' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '</td>
                <td>' . $age . '</td>
                <td>' . htmlspecialchars($patient['gender']) . '</td>
                <td>' . htmlspecialchars($patient['phone']) . '</td>
                <td>' . htmlspecialchars($patient['email']) . '</td>
                <td>' . htmlspecialchars($patient['blood_group'] ?: 'N/A') . '</td>
                <td>' . ucfirst(htmlspecialchars($patient['user_status'])) . '</td>
                <td>' . format_date($patient['registered_date']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </body>
        </html>';
        
        // For now, we'll output HTML that can be printed as PDF
        // In production, you'd use a proper PDF library
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        echo $html;
        exit;
    }
    
} catch (Exception $e) {
    die('Error exporting patients: ' . $e->getMessage());
}
?>
<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get export parameters
$format = $_GET['format'] ?? 'csv';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause (same as billing.php)
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE :search OR p.first_name LIKE :search OR p.last_name LIKE :search OR CONCAT(p.first_name, ' ', p.last_name) LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.payment_status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "i.invoice_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "i.invoice_date <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get invoices with related data
    $invoices_query = "SELECT i.*, 
                       p.patient_id as patient_number, p.first_name as patient_first_name, p.last_name as patient_last_name,
                       p.phone as patient_phone, p.email as patient_email,
                       d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization,
                       a.appointment_number,
                       (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = i.id) as item_count,
                       (SELECT COUNT(*) FROM payments pp WHERE pp.invoice_id = i.id) as payment_count
                       FROM invoices i 
                       LEFT JOIN patients p ON i.patient_id = p.id 
                       LEFT JOIN doctors d ON i.doctor_id = d.id 
                       LEFT JOIN appointments a ON i.appointment_id = a.id 
                       $where_clause
                       ORDER BY i.created_at DESC";

    $db->query($invoices_query);
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    $invoices = $db->resultSet();

    if ($format === 'csv') {
        // Generate CSV
        $filename = 'invoices_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        $headers = [
            'Invoice Number',
            'Patient ID',
            'Patient Name',
            'Patient Phone',
            'Patient Email',
            'Doctor Name',
            'Doctor Specialization',
            'Appointment Number',
            'Invoice Date',
            'Due Date',
            'Subtotal',
            'Tax Rate (%)',
            'Tax Amount',
            'Discount Amount',
            'Total Amount',
            'Paid Amount',
            'Balance Amount',
            'Payment Status',
            'Items Count',
            'Payments Count',
            'Notes',
            'Created At'
        ];
        
        fputcsv($output, $headers);
        
        // CSV Data
        foreach ($invoices as $invoice) {
            $row = [
                $invoice['invoice_number'],
                $invoice['patient_number'],
                $invoice['patient_first_name'] . ' ' . $invoice['patient_last_name'],
                $invoice['patient_phone'] ?? '',
                $invoice['patient_email'] ?? '',
                $invoice['doctor_first_name'] ? 'Dr. ' . $invoice['doctor_first_name'] . ' ' . $invoice['doctor_last_name'] : '',
                $invoice['specialization'] ?? '',
                $invoice['appointment_number'] ?? '',
                $invoice['invoice_date'],
                $invoice['due_date'] ?? '',
                number_format($invoice['subtotal'], 2),
                $invoice['tax_rate'],
                number_format($invoice['tax_amount'], 2),
                number_format($invoice['discount_amount'], 2),
                number_format($invoice['total_amount'], 2),
                number_format($invoice['paid_amount'], 2),
                number_format($invoice['balance_amount'], 2),
                ucfirst($invoice['payment_status']),
                $invoice['item_count'],
                $invoice['payment_count'],
                $invoice['notes'] ?? '',
                $invoice['created_at']
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
        
    } else {
        // Generate PDF Report
        $filename = 'invoices_report_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Calculate summary statistics
        $total_invoices = count($invoices);
        $total_revenue = array_sum(array_column($invoices, 'total_amount'));
        $total_paid = array_sum(array_column($invoices, 'paid_amount'));
        $total_outstanding = array_sum(array_column($invoices, 'balance_amount'));
        
        $status_counts = [];
        foreach ($invoices as $invoice) {
            $status = $invoice['payment_status'];
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
        }
        
        $html_content = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoices Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 20px; }
                .hospital-name { font-size: 24px; font-weight: bold; color: #007bff; }
                .report-title { font-size: 18px; margin-top: 10px; }
                .report-date { font-size: 12px; color: #666; margin-top: 5px; }
                .summary { margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
                .summary h3 { margin: 0 0 15px 0; color: #007bff; }
                .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
                .summary-item { text-align: center; }
                .summary-label { font-weight: bold; color: #666; font-size: 11px; }
                .summary-value { font-size: 14px; font-weight: bold; color: #333; }
                .filters { margin: 20px 0; padding: 10px; background-color: #e9ecef; border-radius: 3px; }
                .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table th { background-color: #007bff; color: white; padding: 8px 6px; font-size: 11px; text-align: left; }
                .table td { padding: 6px; border-bottom: 1px solid #dee2e6; font-size: 10px; }
                .table tr:nth-child(even) { background-color: #f8f9fa; }
                .text-right { text-align: right; }
                .status-badge { padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
                .status-paid { background-color: #d4edda; color: #155724; }
                .status-partial { background-color: #d1ecf1; color: #0c5460; }
                .status-pending { background-color: #fff3cd; color: #856404; }
                .status-overdue { background-color: #f8d7da; color: #721c24; }
                .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #dee2e6; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="hospital-name">MediCare Hospital</div>
                <div class="report-title">Invoices Report</div>
                <div class="report-date">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
            </div>';

        // Add filters information if any applied
        if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)) {
            $html_content .= '<div class="filters"><strong>Applied Filters:</strong> ';
            $filter_parts = [];
            if (!empty($search)) $filter_parts[] = "Search: \"$search\"";
            if (!empty($status_filter)) $filter_parts[] = "Status: " . ucfirst($status_filter);
            if (!empty($date_from)) $filter_parts[] = "From: " . date('M j, Y', strtotime($date_from));
            if (!empty($date_to)) $filter_parts[] = "To: " . date('M j, Y', strtotime($date_to));
            $html_content .= implode(' | ', $filter_parts);
            $html_content .= '</div>';
        }

        $html_content .= '
            <div class="summary">
                <h3>Summary Statistics</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Total Invoices</div>
                        <div class="summary-value">' . number_format($total_invoices) . '</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Revenue</div>
                        <div class="summary-value">Rs. ' . number_format($total_revenue, 2) . '</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Paid</div>
                        <div class="summary-value">Rs. ' . number_format($total_paid, 2) . '</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Outstanding</div>
                        <div class="summary-value">Rs. ' . number_format($total_outstanding, 2) . '</div>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <strong>Payment Status Distribution:</strong> ';
        
        foreach ($status_counts as $status => $count) {
            $percentage = ($count / $total_invoices) * 100;
            $html_content .= ucfirst($status) . ': ' . $count . ' (' . number_format($percentage, 1) . '%) | ';
        }
        
        $html_content = rtrim($html_content, ' | ');
        $html_content .= '
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Items</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($invoices as $invoice) {
            $status_class = [
                'pending' => 'status-pending',
                'partial' => 'status-partial', 
                'paid' => 'status-paid',
                'overdue' => 'status-overdue'
            ][$invoice['payment_status']] ?? 'status-pending';
            
            $html_content .= '
                    <tr>
                        <td>' . htmlspecialchars($invoice['invoice_number']) . '</td>
                        <td>' . htmlspecialchars($invoice['patient_first_name'] . ' ' . $invoice['patient_last_name']) . '</td>
                        <td>' . ($invoice['doctor_first_name'] ? 'Dr. ' . htmlspecialchars($invoice['doctor_first_name'] . ' ' . $invoice['doctor_last_name']) : '-') . '</td>
                        <td>' . date('M j, Y', strtotime($invoice['invoice_date'])) . '</td>
                        <td class="text-right">Rs. ' . number_format($invoice['total_amount'], 2) . '</td>
                        <td class="text-right">Rs. ' . number_format($invoice['paid_amount'], 2) . '</td>
                        <td class="text-right">Rs. ' . number_format($invoice['balance_amount'], 2) . '</td>
                        <td><span class="status-badge ' . $status_class . '">' . ucfirst($invoice['payment_status']) . '</span></td>
                        <td class="text-right">' . $invoice['item_count'] . '</td>
                    </tr>';
        }
        
        $html_content .= '
                </tbody>
            </table>
            
            <div class="footer">
                <p>This report contains ' . number_format($total_invoices) . ' invoice(s) | Generated by MediCare Hospital Billing System</p>
                <p>Report generated on ' . date('F j, Y \a\t g:i A') . ' by ' . htmlspecialchars($_SESSION['username'] ?? 'Admin') . '</p>
            </div>
        </body>
        </html>';

        // Output PDF headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Simple HTML to PDF conversion
        echo '<script>window.onload = function() { window.print(); }</script>';
        echo $html_content;
        exit();
    }

} catch (Exception $e) {
    die("Error generating export: " . $e->getMessage());
}
?>
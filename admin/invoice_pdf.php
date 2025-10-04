<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: billing.php");
    exit();
}

$invoice_id = (int)$_GET['id'];

try {
    // Get invoice with patient and doctor data
    $db->query("SELECT i.*, 
               p.patient_id as patient_number, p.first_name as patient_first_name, p.last_name as patient_last_name,
               p.phone as patient_phone, p.email as patient_email, p.address as patient_address,
               p.date_of_birth, p.gender, p.blood_group, p.insurance_number,
               d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization,
               d.phone as doctor_phone, d.email as doctor_email,
               a.appointment_number, a.appointment_date, a.appointment_time,
               u.username as created_by_name
               FROM invoices i 
               LEFT JOIN patients p ON i.patient_id = p.id 
               LEFT JOIN doctors d ON i.doctor_id = d.id 
               LEFT JOIN appointments a ON i.appointment_id = a.id
               LEFT JOIN users u ON i.created_by = u.id
               WHERE i.id = :id");
    $db->bind(':id', $invoice_id);
    $invoice = $db->single();
    
    if (!$invoice) {
        header("Location: billing.php");
        exit();
    }
    
    // Get invoice items
    $db->query("SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id");
    $db->bind(':invoice_id', $invoice_id);
    $items = $db->resultSet();
    
    // Get payments
    $db->query("SELECT p.*, u.username as received_by_name 
               FROM payments p 
               LEFT JOIN users u ON p.received_by = u.id 
               WHERE p.invoice_id = :invoice_id 
               ORDER BY p.payment_date DESC");
    $db->bind(':invoice_id', $invoice_id);
    $payments = $db->resultSet();
    
} catch (Exception $e) {
    die("Error loading invoice: " . $e->getMessage());
}

// Generate PDF content
$html_content = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.4;
        }
        .invoice-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .hospital-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .hospital-name {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .hospital-tagline {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .hospital-details {
            font-size: 12px;
            color: #666;
        }
        .invoice-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .invoice-details, .patient-details {
            width: 48%;
        }
        .detail-group {
            margin-bottom: 15px;
        }
        .detail-label {
            font-weight: bold;
            color: #333;
            display: inline-block;
            width: 120px;
        }
        .detail-value {
            color: #666;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            background-color: #007bff;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
        }
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-section {
            margin-top: 30px;
            border-top: 2px solid #dee2e6;
            padding-top: 20px;
        }
        .totals-table {
            width: 300px;
            margin-left: auto;
        }
        .totals-table td {
            padding: 8px 15px;
            border: none;
        }
        .total-row {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #333;
        }
        .payment-section {
            margin-top: 30px;
            border-top: 2px solid #dee2e6;
            padding-top: 20px;
        }
        .payment-summary {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-partial { background-color: #d1ecf1; color: #0c5460; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-overdue { background-color: #f8d7da; color: #721c24; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 11px;
            background-color: #007bff;
            color: white;
            border-radius: 3px;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    <div class="invoice-header">
        <div class="hospital-info">
            <div class="hospital-name">MediCare Hospital</div>
            <div class="hospital-tagline">Quality Healthcare for All</div>
            <div class="hospital-details">
                123 Medical Center Drive, Health City, HC 12345<br>
                Phone: (555) 123-4567 | Email: info@medicarehospital.com<br>
                www.medicarehospital.com
            </div>
        </div>
        
        <div class="invoice-title">MEDICAL INVOICE</div>
        
        <div class="invoice-meta">
            <div class="invoice-details">
                <h3 style="margin-top: 0; color: #007bff;">Invoice Information</h3>
                <div class="detail-group">
                    <span class="detail-label">Invoice Number:</span>
                    <span class="detail-value">' . htmlspecialchars($invoice['invoice_number']) . '</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Invoice Date:</span>
                    <span class="detail-value">' . date('F j, Y', strtotime($invoice['invoice_date'])) . '</span>
                </div>
                ' . ($invoice['due_date'] ? '<div class="detail-group">
                    <span class="detail-label">Due Date:</span>
                    <span class="detail-value">' . date('F j, Y', strtotime($invoice['due_date'])) . '</span>
                </div>' : '') . '
                ' . ($invoice['appointment_number'] ? '<div class="detail-group">
                    <span class="detail-label">Appointment:</span>
                    <span class="detail-value">' . htmlspecialchars($invoice['appointment_number']) . '</span>
                </div>' : '') . '
                <div class="detail-group">
                    <span class="detail-label">Payment Status:</span>
                    <span class="payment-status status-' . $invoice['payment_status'] . '">' . ucfirst($invoice['payment_status']) . '</span>
                </div>
            </div>
            
            <div class="patient-details">
                <h3 style="margin-top: 0; color: #007bff;">Patient Information</h3>
                <div class="detail-group">
                    <span class="detail-label">Patient ID:</span>
                    <span class="detail-value">' . htmlspecialchars($invoice['patient_number']) . '</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Patient Name:</span>
                    <span class="detail-value">' . htmlspecialchars($invoice['patient_first_name'] . ' ' . $invoice['patient_last_name']) . '</span>
                </div>
                ' . ($invoice['patient_phone'] ? '<div class="detail-group">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">' . htmlspecialchars($invoice['patient_phone']) . '</span>
                </div>' : '') . '
                ' . ($invoice['patient_email'] ? '<div class="detail-group">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">' . htmlspecialchars($invoice['patient_email']) . '</span>
                </div>' : '') . '
                ' . ($invoice['insurance_number'] ? '<div class="detail-group">
                    <span class="detail-label">Insurance:</span>
                    <span class="detail-value">' . htmlspecialchars($invoice['insurance_number']) . '</span>
                </div>' : '') . '
                ' . ($invoice['doctor_first_name'] ? '<div class="detail-group">
                    <span class="detail-label">Attending Doctor:</span>
                    <span class="detail-value">Dr. ' . htmlspecialchars($invoice['doctor_first_name'] . ' ' . $invoice['doctor_last_name']) . '</span>
                </div>' : '') . '
            </div>
        </div>
    </div>

    <div class="items-section">
        <h3 style="color: #007bff; margin-bottom: 15px;">Services & Charges</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">#</th>
                    <th style="width: 15%;">Type</th>
                    <th style="width: 40%;">Description</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 12%;" class="text-right">Unit Price</th>
                    <th style="width: 13%;" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';

$item_number = 1;
foreach ($items as $item) {
    $html_content .= '
                <tr>
                    <td class="text-center">' . $item_number++ . '</td>
                    <td><span class="badge">' . htmlspecialchars($item['item_type']) . '</span></td>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td class="text-center">' . number_format($item['quantity'], 2) . '</td>
                    <td class="text-right">$' . number_format($item['unit_price'], 2) . '</td>
                    <td class="text-right">$' . number_format($item['total_price'], 2) . '</td>
                </tr>';
}

$html_content .= '
            </tbody>
        </table>
    </div>

    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">$' . number_format($invoice['subtotal'], 2) . '</td>
            </tr>
            ' . ($invoice['discount_amount'] > 0 ? '<tr>
                <td>Discount:</td>
                <td class="text-right">-$' . number_format($invoice['discount_amount'], 2) . '</td>
            </tr>' : '') . '
            <tr>
                <td>Tax (' . $invoice['tax_rate'] . '%):</td>
                <td class="text-right">$' . number_format($invoice['tax_amount'], 2) . '</td>
            </tr>
            <tr class="total-row">
                <td>Total Amount:</td>
                <td class="text-right">$' . number_format($invoice['total_amount'], 2) . '</td>
            </tr>
        </table>
    </div>';

if (!empty($payments)) {
    $html_content .= '
    <div class="payment-section">
        <h3 style="color: #007bff; margin-bottom: 15px;">Payment History</h3>
        <div class="payment-summary">
            <strong>Payment Summary:</strong><br>
            Total Amount: $' . number_format($invoice['total_amount'], 2) . ' | 
            Paid Amount: $' . number_format($invoice['paid_amount'], 2) . ' | 
            Balance Due: $' . number_format($invoice['balance_amount'], 2) . '
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Payment #</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($payments as $payment) {
        $html_content .= '
                <tr>
                    <td>' . htmlspecialchars($payment['payment_number']) . '</td>
                    <td>' . date('M j, Y', strtotime($payment['payment_date'])) . '</td>
                    <td>' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . '</td>
                    <td class="text-right">$' . number_format($payment['amount'], 2) . '</td>
                    <td>' . htmlspecialchars($payment['reference_number'] ?? '-') . '</td>
                </tr>';
    }
    
    $html_content .= '
            </tbody>
        </table>
    </div>';
}

if (!empty($invoice['notes'])) {
    $html_content .= '
    <div class="notes-section">
        <h4 style="margin-top: 0; color: #007bff;">Notes:</h4>
        <p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>
    </div>';
}

$html_content .= '
    <div class="footer">
        <p><strong>Thank you for choosing MediCare Hospital!</strong></p>
        <p>Invoice generated on ' . date('F j, Y \a\t g:i A') . ' by ' . htmlspecialchars($invoice['created_by_name'] ?? 'System') . '</p>
        <p style="margin-top: 15px; font-size: 11px;">
            <strong>Payment Terms:</strong> Payment is due within 30 days of invoice date. 
            Late payments may be subject to additional charges. For questions about this invoice, 
            please contact our billing department at (555) 123-4567 ext. 789.
        </p>
        <p style="font-size: 11px;">
            <strong>Remit Payment To:</strong> MediCare Hospital, Accounts Receivable, 
            123 Medical Center Drive, Health City, HC 12345
        </p>
    </div>
</body>
</html>';

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Invoice_' . $invoice['invoice_number'] . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Simple HTML to PDF conversion using DomPDF-like approach
// For production, you might want to use a proper PDF library like TCPDF or DomPDF
// This is a basic implementation that works with most browsers

// Convert HTML to PDF using browser print functionality
echo '<script>
window.onload = function() {
    window.print();
}
</script>';

echo $html_content;
?>
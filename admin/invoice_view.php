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
$is_print_mode = isset($_GET['print']) && $_GET['print'] == '1';

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> - MediCare Hospital</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .hospital-name {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hospital-tagline {
            font-size: 1.1em;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .hospital-contact {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .invoice-title {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 1.8em;
            font-weight: bold;
            color: #007bff;
            border-bottom: 3px solid #007bff;
        }
        
        .invoice-content {
            padding: 30px;
        }
        
        .invoice-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .info-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            width: 120px;
            color: #555;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .payment-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-partial { background-color: #d1ecf1; color: #0c5460; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-overdue { background-color: #f8d7da; color: #721c24; }
        
        .items-section {
            margin: 30px 0;
        }
        
        .section-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .items-table th {
            background-color: #007bff;
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .items-table tr:hover {
            background-color: #e3f2fd;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .item-type-badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #007bff;
            color: white;
            border-radius: 4px;
            font-size: 0.8em;
            text-transform: capitalize;
        }
        
        .totals-section {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        
        .totals-table {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .totals-table table {
            width: 300px;
        }
        
        .totals-table td {
            padding: 8px 15px;
            border: none;
        }
        
        .total-row {
            font-weight: bold;
            font-size: 1.1em;
            border-top: 2px solid #007bff;
            color: #007bff;
        }
        
        .payment-section {
            margin-top: 40px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .payment-summary {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .notes-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #fff3cd;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
        }
        
        .footer {
            margin-top: 40px;
            padding: 20px;
            background-color: #f8f9fa;
            text-align: center;
            font-size: 0.9em;
            color: #666;
            border-top: 1px solid #dee2e6;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }
        
        /* Print Styles */
        @media print {
            body {
                background-color: white;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .container {
                max-width: none;
                box-shadow: none;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .invoice-header {
                background: #007bff !important;
                color: white !important;
            }
            
            .items-table th {
                background-color: #007bff !important;
                color: white !important;
            }
            
            .items-table tr:nth-child(even) {
                background-color: #f8f9fa !important;
            }
            
            .info-section {
                background-color: #f8f9fa !important;
            }
            
            .payment-section {
                background-color: #f8f9fa !important;
            }
            
            .footer {
                background-color: #f8f9fa !important;
            }
            
            /* Ensure page breaks work properly */
            .invoice-header, .invoice-title {
                page-break-after: avoid;
            }
            
            .items-table {
                page-break-inside: avoid;
            }
            
            .totals-section {
                page-break-before: avoid;
            }
        }
        
        /* Screen-only styles */
        @media screen {
            body {
                padding: 20px 0;
            }
            
            .container {
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <?php if (!$is_print_mode): ?>
    <div class="no-print">
        <a href="billing.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Billing
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <a href="invoice_pdf.php?id=<?php echo $invoice_id; ?>" class="btn btn-success" target="_blank">
            <i class="fas fa-file-pdf"></i> Download PDF
        </a>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="hospital-name">MediCare Hospital</div>
            <div class="hospital-tagline">Quality Healthcare for All</div>
            <div class="hospital-contact">
                123 Medical Center Drive, Health City, HC 12345<br>
                Phone: (555) 123-4567 | Email: info@medicarehospital.com
            </div>
        </div>
        
        <div class="invoice-title">MEDICAL INVOICE</div>
        
        <div class="invoice-content">
            <!-- Invoice and Patient Information -->
            <div class="invoice-meta">
                <div class="info-section">
                    <div class="info-title">Invoice Information</div>
                    <div class="info-item">
                        <span class="info-label">Invoice Number:</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Invoice Date:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></span>
                    </div>
                    <?php if ($invoice['due_date']): ?>
                    <div class="info-item">
                        <span class="info-label">Due Date:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['appointment_number']): ?>
                    <div class="info-item">
                        <span class="info-label">Appointment:</span>
                        <span class="info-value"><?php echo htmlspecialchars($invoice['appointment_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Payment Status:</span>
                        <span class="info-value">
                            <span class="payment-status status-<?php echo $invoice['payment_status']; ?>">
                                <?php echo ucfirst($invoice['payment_status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-title">Patient Information</div>
                    <div class="info-item">
                        <span class="info-label">Patient ID:</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($invoice['patient_number']); ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Patient Name:</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($invoice['patient_first_name'] . ' ' . $invoice['patient_last_name']); ?></strong></span>
                    </div>
                    <?php if ($invoice['patient_phone']): ?>
                    <div class="info-item">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($invoice['patient_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['patient_email']): ?>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($invoice['patient_email']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['insurance_number']): ?>
                    <div class="info-item">
                        <span class="info-label">Insurance:</span>
                        <span class="info-value"><?php echo htmlspecialchars($invoice['insurance_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['doctor_first_name']): ?>
                    <div class="info-item">
                        <span class="info-label">Doctor:</span>
                        <span class="info-value">Dr. <?php echo htmlspecialchars($invoice['doctor_first_name'] . ' ' . $invoice['doctor_last_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Invoice Items -->
            <div class="items-section">
                <div class="section-title">Services & Charges</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 8%;">#</th>
                            <th style="width: 15%;">Type</th>
                            <th style="width: 42%;">Description</th>
                            <th style="width: 10%;" class="text-center">Quantity</th>
                            <th style="width: 12%;" class="text-right">Unit Price</th>
                            <th style="width: 13%;" class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $item_number = 1;
                        foreach ($items as $item): 
                        ?>
                        <tr>
                            <td class="text-center"><strong><?php echo $item_number++; ?></strong></td>
                            <td>
                                <span class="item-type-badge">
                                    <?php echo htmlspecialchars($item['item_type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td class="text-center"><?php echo number_format($item['quantity'], 2); ?></td>
                            <td class="text-right">$<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-right"><strong>$<?php echo number_format($item['total_price'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-table">
                    <table>
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-right">$<?php echo number_format($invoice['subtotal'], 2); ?></td>
                        </tr>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-right">-$<?php echo number_format($invoice['discount_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Tax (<?php echo $invoice['tax_rate']; ?>%):</td>
                            <td class="text-right">$<?php echo number_format($invoice['tax_amount'], 2); ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total Amount:</strong></td>
                            <td class="text-right"><strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Payments -->
            <?php if (!empty($payments)): ?>
            <div class="payment-section">
                <div class="section-title">Payment History</div>
                <div class="payment-summary">
                    <strong>Payment Summary:</strong><br>
                    Total Amount: <strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong> | 
                    Paid Amount: <strong>$<?php echo number_format($invoice['paid_amount'], 2); ?></strong> | 
                    Balance Due: <strong>$<?php echo number_format($invoice['balance_amount'], 2); ?></strong>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Payment #</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th class="text-right">Amount</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                            <td class="text-right"><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($invoice['notes'])): ?>
            <div class="notes-section">
                <div class="section-title">Notes</div>
                <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer">
                <p><strong>Thank you for choosing MediCare Hospital!</strong></p>
                <p>Invoice generated on <?php echo date('F j, Y \a\t g:i A'); ?> by <?php echo htmlspecialchars($invoice['created_by_name'] ?? 'System'); ?></p>
                
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6; font-size: 0.85em;">
                    <p><strong>Payment Terms:</strong> Payment is due within 30 days of invoice date. Late payments may be subject to additional charges.</p>
                    <p><strong>Questions?</strong> Contact our billing department at (555) 123-4567 ext. 789</p>
                    <p><strong>Remit Payment To:</strong> MediCare Hospital, Accounts Receivable, 123 Medical Center Drive, Health City, HC 12345</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_print_mode): ?>
    <script>
        window.onload = function() {
            window.print();
            window.onafterprint = function() {
                window.close();
            };
        };
    </script>
    <?php endif; ?>
</body>
</html>
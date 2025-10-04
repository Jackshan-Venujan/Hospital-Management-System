<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_invoice') {
        $db->beginTransaction();
        try {
            // Generate invoice number
            $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Insert invoice
            $db->query("INSERT INTO invoices (invoice_number, patient_id, appointment_id, doctor_id, invoice_date, due_date, subtotal, tax_rate, tax_amount, discount_amount, total_amount, balance_amount, payment_status, notes, created_by) 
                       VALUES (:invoice_number, :patient_id, :appointment_id, :doctor_id, :invoice_date, :due_date, :subtotal, :tax_rate, :tax_amount, :discount_amount, :total_amount, :balance_amount, :payment_status, :notes, :created_by)");
            
            $db->bind(':invoice_number', $invoice_number)
               ->bind(':patient_id', $_POST['patient_id'])
               ->bind(':appointment_id', $_POST['appointment_id'] ?: null)
               ->bind(':doctor_id', $_POST['doctor_id'] ?: null)
               ->bind(':invoice_date', $_POST['invoice_date'])
               ->bind(':due_date', $_POST['due_date'])
               ->bind(':subtotal', $_POST['subtotal'])
               ->bind(':tax_rate', $_POST['tax_rate'])
               ->bind(':tax_amount', $_POST['tax_amount'])
               ->bind(':discount_amount', $_POST['discount_amount'] ?: 0)
               ->bind(':total_amount', $_POST['total_amount'])
               ->bind(':balance_amount', $_POST['total_amount'])
               ->bind(':payment_status', 'pending')
               ->bind(':notes', $_POST['notes'])
               ->bind(':created_by', $_SESSION['user_id']);
            
            $db->execute();
            $invoice_id = $db->lastInsertId();
            
            // Insert invoice items
            if (!empty($_POST['items'])) {
                $items = json_decode($_POST['items'], true);
                foreach ($items as $item) {
                    $db->query("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price) 
                               VALUES (:invoice_id, :item_type, :description, :quantity, :unit_price, :total_price)");
                    
                    $db->bind(':invoice_id', $invoice_id)
                       ->bind(':item_type', $item['type'])
                       ->bind(':description', $item['description'])
                       ->bind(':quantity', $item['quantity'])
                       ->bind(':unit_price', $item['unit_price'])
                       ->bind(':total_price', $item['total_price']);
                    
                    $db->execute();
                }
            }
            
            $db->endTransaction();
            set_message('success', 'Invoice created successfully!');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            set_message('error', 'Error creating invoice: ' . $e->getMessage());
        }
    }
    
    elseif ($action === 'update_invoice') {
        $db->beginTransaction();
        try {
            // Update invoice
            $db->query("UPDATE invoices SET patient_id = :patient_id, appointment_id = :appointment_id, doctor_id = :doctor_id, 
                       invoice_date = :invoice_date, due_date = :due_date, subtotal = :subtotal, tax_rate = :tax_rate, 
                       tax_amount = :tax_amount, discount_amount = :discount_amount, total_amount = :total_amount, 
                       balance_amount = :balance_amount, notes = :notes WHERE id = :id");
            
            $balance = $_POST['total_amount'] - ($_POST['paid_amount'] ?? 0);
            
            $db->bind(':patient_id', $_POST['patient_id'])
               ->bind(':appointment_id', $_POST['appointment_id'] ?: null)
               ->bind(':doctor_id', $_POST['doctor_id'] ?: null)
               ->bind(':invoice_date', $_POST['invoice_date'])
               ->bind(':due_date', $_POST['due_date'])
               ->bind(':subtotal', $_POST['subtotal'])
               ->bind(':tax_rate', $_POST['tax_rate'])
               ->bind(':tax_amount', $_POST['tax_amount'])
               ->bind(':discount_amount', $_POST['discount_amount'] ?: 0)
               ->bind(':total_amount', $_POST['total_amount'])
               ->bind(':balance_amount', $balance)
               ->bind(':notes', $_POST['notes'])
               ->bind(':id', $_POST['invoice_id']);
            
            $db->execute();
            
            // Delete existing items and insert new ones
            $db->query("DELETE FROM invoice_items WHERE invoice_id = :invoice_id");
            $db->bind(':invoice_id', $_POST['invoice_id']);
            $db->execute();
            
            // Insert updated items
            if (!empty($_POST['items'])) {
                $items = json_decode($_POST['items'], true);
                foreach ($items as $item) {
                    $db->query("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price) 
                               VALUES (:invoice_id, :item_type, :description, :quantity, :unit_price, :total_price)");
                    
                    $db->bind(':invoice_id', $_POST['invoice_id'])
                       ->bind(':item_type', $item['type'])
                       ->bind(':description', $item['description'])
                       ->bind(':quantity', $item['quantity'])
                       ->bind(':unit_price', $item['unit_price'])
                       ->bind(':total_price', $item['total_price']);
                    
                    $db->execute();
                }
            }
            
            $db->endTransaction();
            set_message('success', 'Invoice updated successfully!');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            set_message('error', 'Error updating invoice: ' . $e->getMessage());
        }
    }
    
    elseif ($action === 'delete_invoice') {
        try {
            $db->query("DELETE FROM invoices WHERE id = :id");
            $db->bind(':id', $_POST['invoice_id']);
            $db->execute();
            
            set_message('success', 'Invoice deleted successfully!');
        } catch (Exception $e) {
            set_message('error', 'Error deleting invoice: ' . $e->getMessage());
        }
    }
    
    elseif ($action === 'record_payment') {
        $db->beginTransaction();
        try {
            // Generate payment number
            $payment_number = 'PAY-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Insert payment
            $db->query("INSERT INTO payments (payment_number, invoice_id, payment_date, payment_method, amount, reference_number, notes, received_by) 
                       VALUES (:payment_number, :invoice_id, :payment_date, :payment_method, :amount, :reference_number, :notes, :received_by)");
            
            $db->bind(':payment_number', $payment_number)
               ->bind(':invoice_id', $_POST['invoice_id'])
               ->bind(':payment_date', $_POST['payment_date'])
               ->bind(':payment_method', $_POST['payment_method'])
               ->bind(':amount', $_POST['payment_amount'])
               ->bind(':reference_number', $_POST['reference_number'])
               ->bind(':notes', $_POST['payment_notes'])
               ->bind(':received_by', $_SESSION['user_id']);
            
            $db->execute();
            
            // Update invoice payment status
            $db->query("UPDATE invoices SET 
                       paid_amount = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :invoice_id),
                       balance_amount = total_amount - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :invoice_id2),
                       payment_status = CASE 
                           WHEN (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :invoice_id3) >= total_amount THEN 'paid'
                           WHEN (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :invoice_id4) > 0 THEN 'partial'
                           ELSE 'pending'
                       END
                       WHERE id = :invoice_id5");
            
            $db->bind(':invoice_id', $_POST['invoice_id'])
               ->bind(':invoice_id2', $_POST['invoice_id'])
               ->bind(':invoice_id3', $_POST['invoice_id'])
               ->bind(':invoice_id4', $_POST['invoice_id'])
               ->bind(':invoice_id5', $_POST['invoice_id']);
            
            $db->execute();
            
            $db->endTransaction();
            set_message('success', 'Payment recorded successfully!');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            set_message('error', 'Error recording payment: ' . $e->getMessage());
        }
    }
    
    header("Location: billing.php");
    exit();
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
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

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM invoices i 
                LEFT JOIN patients p ON i.patient_id = p.id 
                $where_clause";

$db->query($count_query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$total_records = $db->single()['total'];
$total_pages = ceil($total_records / $limit);

// Get invoices with related data
$invoices_query = "SELECT i.*, 
                   p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id as patient_number,
                   d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                   a.appointment_number,
                   (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = i.id) as item_count
                   FROM invoices i 
                   LEFT JOIN patients p ON i.patient_id = p.id 
                   LEFT JOIN doctors d ON i.doctor_id = d.id 
                   LEFT JOIN appointments a ON i.appointment_id = a.id 
                   $where_clause
                   ORDER BY i.created_at DESC 
                   LIMIT $limit OFFSET $offset";

$db->query($invoices_query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$invoices = $db->resultSet();

// Get patients for dropdown
$db->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name, last_name");
$patients = $db->resultSet();

// Get doctors for dropdown
$db->query("SELECT id, first_name, last_name, specialization FROM doctors ORDER BY first_name, last_name");
$doctors = $db->resultSet();

// Get service catalog for quick add
$db->query("SELECT * FROM service_catalog WHERE is_active = TRUE ORDER BY service_type, service_name");
$services = $db->resultSet();

// Get dashboard statistics
$db->query("SELECT 
    COUNT(*) as total_invoices,
    SUM(total_amount) as total_revenue,
    SUM(paid_amount) as total_paid,
    SUM(balance_amount) as total_outstanding,
    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_invoices,
    COUNT(CASE WHEN payment_status = 'overdue' THEN 1 END) as overdue_invoices
    FROM invoices 
    WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stats = $db->single();

$page_title = 'Billing & Invoices';
include '../includes/header.php';
?>

<div class="page-title">
    <h1><i class="fas fa-file-invoice-dollar me-2"></i>Billing & Invoices</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Billing</li>
        </ol>
    </nav>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Invoice Management</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal">
        <i class="fas fa-plus me-2"></i>Create Invoice
    </button>
</div>

                <?php display_message(); ?>

                <!-- Dashboard Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="card-title">Total Revenue (30 days)</h6>
                                        <h4 class="mb-0">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="card-title">Total Paid</h6>
                                        <h4 class="mb-0">$<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="card-title">Outstanding</h6>
                                        <h4 class="mb-0">$<?php echo number_format($stats['total_outstanding'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-exclamation-circle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="card-title">Total Invoices</h6>
                                        <h4 class="mb-0"><?php echo $stats['total_invoices'] ?? 0; ?></h4>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Invoice number, patient name...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Payment Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="billing.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                    <a href="export_invoices.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                                        <i class="fas fa-download me-1"></i>Export
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Patient</th>
                                        <th>Date</th>
                                        <th>Total Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($invoices)): ?>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                                    <?php if ($invoice['item_count'] > 0): ?>
                                                        <small class="text-muted d-block"><?php echo $invoice['item_count']; ?> items</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($invoice['patient_first_name'] . ' ' . $invoice['patient_last_name']); ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($invoice['patient_number']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?>
                                                    <?php if ($invoice['due_date']): ?>
                                                        <small class="text-muted d-block">Due: <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                                <td>$<?php echo number_format($invoice['paid_amount'], 2); ?></td>
                                                <td>$<?php echo number_format($invoice['balance_amount'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'pending' => 'bg-warning text-dark',
                                                        'partial' => 'bg-info text-white',
                                                        'paid' => 'bg-success text-white',
                                                        'overdue' => 'bg-danger text-white'
                                                    ][$invoice['payment_status']] ?? 'bg-secondary text-white';
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($invoice['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="invoice_view.php?id=<?php echo $invoice['id']; ?>" target="_blank">
                                                                    <i class="fas fa-eye me-2"></i>View Invoice
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="invoice_pdf.php?id=<?php echo $invoice['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf me-2"></i>Download PDF
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="javascript:void(0);" onclick="printInvoice(<?php echo $invoice['id']; ?>);">
                                                                    <i class="fas fa-print me-2"></i>Print
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item" href="javascript:void(0);" onclick="editInvoice(<?php echo $invoice['id']; ?>);">
                                                                    <i class="fas fa-edit me-2"></i>Edit
                                                                </a>
                                                            </li>
                                                            <?php if ($invoice['balance_amount'] > 0): ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="recordPayment(<?php echo $invoice['id']; ?>);">
                                                                        <i class="fas fa-money-bill me-2"></i>Record Payment
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="deleteInvoice(<?php echo $invoice['id']; ?>);">
                                                                    <i class="fas fa-trash me-2"></i>Delete
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fas fa-file-invoice fa-3x mb-3 d-block"></i>
                                                No invoices found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Invoice pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Create New Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="invoiceForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_invoice">
                    <input type="hidden" name="invoice_id" id="invoice_id">
                    <input type="hidden" name="items" id="items_json">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="patient_id" class="form-label">Patient *</label>
                            <select class="form-select" id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="doctor_id" class="form-label">Doctor</label>
                            <select class="form-select" id="doctor_id" name="doctor_id">
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name'] . ' - ' . $doctor['specialization']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="invoice_date" class="form-label">Invoice Date *</label>
                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="appointment_id" class="form-label">Appointment</label>
                            <input type="text" class="form-control" id="appointment_id" name="appointment_id" 
                                   placeholder="Appointment ID (optional)">
                        </div>
                    </div>
                    
                    <!-- Invoice Items Section -->
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Invoice Items</h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-success me-2" onclick="addServiceFromCatalog()">
                                    <i class="fas fa-list me-1"></i>Add from Catalog
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addInvoiceItem()">
                                    <i class="fas fa-plus me-1"></i>Add Item
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="invoice_items">
                                <!-- Items will be added dynamically -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Totals Section -->
                    <div class="row">
                        <div class="col-md-6">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Additional notes..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <div class="col-6">Subtotal:</div>
                                        <div class="col-6 text-end">$<span id="subtotal_display">0.00</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            Discount:
                                            <input type="number" class="form-control form-control-sm mt-1" 
                                                   id="discount_amount" name="discount_amount" value="0" 
                                                   step="0.01" min="0" onchange="calculateTotals()">
                                        </div>
                                        <div class="col-6 text-end">-$<span id="discount_display">0.00</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            Tax (%):
                                            <input type="number" class="form-control form-control-sm mt-1" 
                                                   id="tax_rate" name="tax_rate" value="10" 
                                                   step="0.01" min="0" max="100" onchange="calculateTotals()">
                                        </div>
                                        <div class="col-6 text-end">$<span id="tax_display">0.00</span></div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6"><strong>Total:</strong></div>
                                        <div class="col-6 text-end"><strong>$<span id="total_display">0.00</span></strong></div>
                                    </div>
                                    
                                    <input type="hidden" id="subtotal" name="subtotal" value="0">
                                    <input type="hidden" id="tax_amount" name="tax_amount" value="0">
                                    <input type="hidden" id="total_amount" name="total_amount" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="invoice_id" id="payment_invoice_id">
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="insurance">Insurance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label">Payment Amount *</label>
                        <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                               step="0.01" min="0" required>
                        <small class="form-text text-muted">Outstanding Balance: $<span id="outstanding_balance">0.00</span></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference_number" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" 
                               placeholder="Check/Transaction number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" name="payment_notes" rows="3" 
                                  placeholder="Payment notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-money-bill me-2"></i>Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Service Catalog Modal -->
<div class="modal fade" id="serviceCatalogModal" tabindex="-1" aria-labelledby="serviceCatalogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serviceCatalogModalLabel">Service Catalog</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="service_search" placeholder="Search services...">
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="service_type_filter">
                            <option value="">All Types</option>
                            <option value="consultation">Consultation</option>
                            <option value="procedure">Procedure</option>
                            <option value="test">Test</option>
                            <option value="medication">Medication</option>
                            <option value="room">Room</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="service_catalog_tbody">
                            <?php foreach ($services as $service): ?>
                                <tr data-type="<?php echo $service['service_type']; ?>" data-search="<?php echo strtolower($service['service_name'] . ' ' . $service['description']); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($service['description']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo ucfirst($service['service_type']); ?></span>
                                    </td>
                                    <td>$<?php echo number_format($service['default_price'], 2); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="addServiceToInvoice('<?php echo htmlspecialchars($service['service_name']); ?>', '<?php echo $service['service_type']; ?>', <?php echo $service['default_price']; ?>)">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
.invoice-item {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
    background-color: #f8f9fa;
}

.invoice-item:last-child {
    margin-bottom: 0;
}

.item-header {
    display: flex;
    justify-content-between;
    align-items-center;
    margin-bottom: 0.5rem;
}

.item-number {
    font-weight: bold;
    color: #6c757d;
}

.remove-item {
    color: #dc3545;
    cursor: pointer;
}

.remove-item:hover {
    color: #b02a37;
}

@media print {
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
}

/* Ensure dropdown menus are visible and properly positioned */
.dropdown-menu {
    z-index: 1050 !important;
    position: absolute !important;
    will-change: transform;
    min-width: 10rem;
    padding: 0.5rem 0;
    margin: 0.125rem 0 0;
    font-size: 0.875rem;
    color: #212529;
    text-align: left;
    list-style: none;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.dropdown-menu.show {
    display: block !important;
}

.dropdown-toggle {
    white-space: nowrap;
}

.dropdown-toggle::after {
    display: inline-block;
    margin-left: 0.255em;
    vertical-align: 0.255em;
    content: "";
    border-top: 0.3em solid;
    border-right: 0.3em solid transparent;
    border-bottom: 0;
    border-left: 0.3em solid transparent;
}

/* Debug styling to make dropdown issues visible */
.dropdown {
    position: relative;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

/* Make sure table cell doesn't clip dropdown */
.table td {
    position: relative;
    overflow: visible;
}

/* Visual feedback for dropdown button */
.dropdown-toggle:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}

.dropdown.show .dropdown-toggle {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: white !important;
}

/* Better visual separation for dropdown items */
.dropdown-item {
    padding: 0.5rem 1rem;
    transition: background-color 0.15s ease-in-out;
}

.dropdown-item:hover, 
.dropdown-item:focus {
    background-color: #e9ecef;
    color: #495057;
}
</style>

<script>
let itemCounter = 0;

function addInvoiceItem() {
    itemCounter++;
    const itemHtml = `
        <div class="invoice-item" id="item_${itemCounter}">
            <div class="item-header">
                <span class="item-number">Item #${itemCounter}</span>
                <i class="fas fa-times remove-item" onclick="removeInvoiceItem(${itemCounter})"></i>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-control item-type" name="item_type_${itemCounter}" required>
                        <option value="">Select Type</option>
                        <option value="consultation">Consultation</option>
                        <option value="procedure">Procedure</option>
                        <option value="test">Test</option>
                        <option value="medication">Medication</option>
                        <option value="room">Room</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control item-description" name="item_description_${itemCounter}" 
                           placeholder="Service description" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Qty</label>
                    <input type="number" class="form-control item-quantity" name="item_quantity_${itemCounter}" 
                           value="1" min="0.01" step="0.01" onchange="calculateTotals()" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit Price</label>
                    <input type="number" class="form-control item-unit-price" name="item_unit_price_${itemCounter}" 
                           value="0" min="0" step="0.01" onchange="calculateTotals()" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Total</label>
                    <input type="text" class="form-control item-total" name="item_total_${itemCounter}" 
                           value="0.00" readonly>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('invoice_items').insertAdjacentHTML('beforeend', itemHtml);
    calculateTotals();
}

function removeInvoiceItem(itemId) {
    document.getElementById(`item_${itemId}`).remove();
    calculateTotals();
}

function addServiceFromCatalog() {
    var serviceCatalogModal = new bootstrap.Modal(document.getElementById('serviceCatalogModal'));
    serviceCatalogModal.show();
}

function addServiceToInvoice(serviceName, serviceType, defaultPrice) {
    addInvoiceItem();
    
    // Fill the last added item with service data
    const lastItem = document.querySelector('#invoice_items .invoice-item:last-child');
    if (lastItem) {
        lastItem.querySelector('.item-type').value = serviceType;
        lastItem.querySelector('.item-description').value = serviceName;
        lastItem.querySelector('.item-unit-price').value = defaultPrice;
        calculateTotals();
    }
    
    var serviceCatalogModal = bootstrap.Modal.getInstance(document.getElementById('serviceCatalogModal'));
    serviceCatalogModal.hide();
}

function calculateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll('.invoice-item').forEach(item => {
        const quantity = parseFloat(item.querySelector('.item-quantity').value) || 0;
        const unitPrice = parseFloat(item.querySelector('.item-unit-price').value) || 0;
        const total = quantity * unitPrice;
        
        item.querySelector('.item-total').value = total.toFixed(2);
        subtotal += total;
    });
    
    const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    
    const afterDiscount = subtotal - discountAmount;
    const taxAmount = (afterDiscount * taxRate) / 100;
    const totalAmount = afterDiscount + taxAmount;
    
    // Update displays
    document.getElementById('subtotal_display').textContent = subtotal.toFixed(2);
    document.getElementById('discount_display').textContent = discountAmount.toFixed(2);
    document.getElementById('tax_display').textContent = taxAmount.toFixed(2);
    document.getElementById('total_display').textContent = totalAmount.toFixed(2);
    
    // Update hidden fields
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('tax_amount').value = taxAmount.toFixed(2);
    document.getElementById('total_amount').value = totalAmount.toFixed(2);
}

document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collect all invoice items
    const items = [];
    document.querySelectorAll('.invoice-item').forEach(item => {
        const type = item.querySelector('.item-type').value;
        const description = item.querySelector('.item-description').value;
        const quantity = parseFloat(item.querySelector('.item-quantity').value) || 1;
        const unitPrice = parseFloat(item.querySelector('.item-unit-price').value) || 0;
        const totalPrice = quantity * unitPrice;
        
        if (type && description) {
            items.push({
                type: type,
                description: description,
                quantity: quantity,
                unit_price: unitPrice,
                total_price: totalPrice
            });
        }
    });
    
    document.getElementById('items_json').value = JSON.stringify(items);
    this.submit();
});

function editInvoice(invoiceId) {
    console.log('Edit invoice called for ID:', invoiceId);
    
    // For now, show a simple modal until we fix the AJAX issue
    alert('Edit Invoice functionality - Invoice ID: ' + invoiceId + '\n\nThis feature will be available once we resolve the session authentication for AJAX requests.');
    
    // TODO: Implement proper edit functionality
    // The issue is that get_invoice.php requires authentication
    // We need to either fix the session handling or implement inline editing
}

function deleteInvoice(invoiceId) {
    console.log('Delete invoice called for ID:', invoiceId);
    
    if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_invoice">
            <input type="hidden" name="invoice_id" value="${invoiceId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function recordPayment(invoiceId) {
    console.log('Record payment called for ID:', invoiceId);
    
    // For now, show the payment modal with a default setup
    document.getElementById('payment_invoice_id').value = invoiceId;
    document.getElementById('outstanding_balance').textContent = '0.00';
    document.getElementById('payment_amount').value = '';
    
    // Show the payment modal
    var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    paymentModal.show();
}

function printInvoice(invoiceId) {
    console.log('Print invoice called for ID:', invoiceId);
    window.open(`invoice_view.php?id=${invoiceId}&print=1`, '_blank');
}

// Service catalog search and filter
document.getElementById('service_search').addEventListener('input', filterServiceCatalog);
document.getElementById('service_type_filter').addEventListener('change', filterServiceCatalog);

function filterServiceCatalog() {
    const searchTerm = document.getElementById('service_search').value.toLowerCase();
    const typeFilter = document.getElementById('service_type_filter').value;
    
    document.querySelectorAll('#service_catalog_tbody tr').forEach(row => {
        const searchText = row.dataset.search;
        const serviceType = row.dataset.type;
        
        const matchesSearch = searchTerm === '' || searchText.includes(searchTerm);
        const matchesType = typeFilter === '' || serviceType === typeFilter;
        
        row.style.display = matchesSearch && matchesType ? '' : 'none';
    });
}

// Reset modal when closed
document.getElementById('invoiceModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('invoiceForm').reset();
    document.getElementById('invoice_items').innerHTML = '';
    document.querySelector('[name="action"]').value = 'create_invoice';
    document.getElementById('invoiceModalLabel').textContent = 'Create New Invoice';
    itemCounter = 0;
    calculateTotals();
});

// Initialize Bootstrap dropdowns and debug issues
document.addEventListener('DOMContentLoaded', function() {
    console.log('Billing page JavaScript loaded successfully');
    
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
        alert('Bootstrap JavaScript is not loaded. Dropdown actions will not work.');
        return;
    }
    
    console.log('Bootstrap is loaded:', bootstrap.Tooltip.VERSION);
    
    // Initialize all dropdowns manually to ensure they work
    var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    console.log('Initialized ' + dropdownList.length + ' dropdowns');
    
    // Add debugging event listeners
    dropdownElementList.forEach(function(dropdown, index) {
        dropdown.addEventListener('click', function(e) {
            console.log('Dropdown ' + index + ' clicked');
            // Ensure the click isn't blocked
            e.stopPropagation();
        });
        
        dropdown.addEventListener('show.bs.dropdown', function() {
            console.log('Dropdown ' + index + ' is showing');
        });
        
        dropdown.addEventListener('shown.bs.dropdown', function() {
            console.log('Dropdown ' + index + ' shown successfully');
        });
        
        dropdown.addEventListener('hide.bs.dropdown', function() {
            console.log('Dropdown ' + index + ' is hiding');
        });
    });
    
    // Test a specific dropdown after 2 seconds
    setTimeout(function() {
        if (dropdownList.length > 0) {
            console.log('Testing first dropdown programmatically...');
            try {
                dropdownList[0].show();
                setTimeout(() => dropdownList[0].hide(), 1000);
                console.log('Dropdown test successful');
            } catch (error) {
                console.error('Dropdown test failed:', error);
            }
        }
    }, 2000);
});
</script>

<?php include '../includes/footer.php'; ?>
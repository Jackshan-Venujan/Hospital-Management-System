<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
    exit();
}

$invoice_id = (int)$_GET['id'];

try {
    // Get invoice data
    $db->query("SELECT * FROM invoices WHERE id = :id");
    $db->bind(':id', $invoice_id);
    $invoice = $db->single();
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit();
    }
    
    // Get invoice items
    $db->query("SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id");
    $db->bind(':invoice_id', $invoice_id);
    $items = $db->resultSet();
    
    // Get payments
    $db->query("SELECT * FROM payments WHERE invoice_id = :invoice_id ORDER BY payment_date DESC");
    $db->bind(':invoice_id', $invoice_id);
    $payments = $db->resultSet();
    
    echo json_encode([
        'success' => true,
        'invoice' => $invoice,
        'items' => $items,
        'payments' => $payments
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
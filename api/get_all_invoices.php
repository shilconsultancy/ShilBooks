<?php
header('Content-Type: application/json');
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated.']);
    exit;
}

if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID is required.']);
    exit;
}

$userId = $_SESSION['user_id'];
$customerId = (int)$_GET['customer_id'];

try {
    // Fetch all invoices for the selected customer, now including the balance due
    $sql = "SELECT id, invoice_number, invoice_date, total, amount_paid, (total - amount_paid) as balance_due
            FROM invoices
            WHERE customer_id = :customer_id
            ORDER BY invoice_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['customer_id' => $customerId]);
    
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($invoices);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'A server error occurred.', 'details' => $e->getMessage()]);
}
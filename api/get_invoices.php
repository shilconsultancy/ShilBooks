<?php
// Set the content type to JSON for the response
header('Content-Type: application/json');

// We need the config file for database connection and session management
require_once '../config.php';

// --- Security Check ---
// Ensure the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Not logged in
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'User not authenticated.']);
    exit;
}

// Ensure a customer ID is provided
if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
    // Bad request
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID is required.']);
    exit;
}

$userId = $_SESSION['user_id'];
$customerId = (int)$_GET['customer_id'];

try {
    // Fetch all invoices for the selected customer that are NOT fully paid
    // We also calculate the balance_due in the SQL query
    $sql = "SELECT 
                id, 
                invoice_number, 
                invoice_date,
                due_date,
                total, 
                amount_paid,
                (total - amount_paid) as balance_due
            FROM invoices 
            WHERE user_id = :user_id 
              AND customer_id = :customer_id
              AND (status = 'sent' OR status = 'overdue' OR (status = 'paid' AND amount_paid < total))
              AND (total - amount_paid) > 0.00
            ORDER BY invoice_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $userId,
        'customer_id' => $customerId
    ]);
    
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Send the data back as a JSON response
    echo json_encode($invoices);

} catch (Exception $e) {
    // Server error
    http_response_code(500);
    echo json_encode(['error' => 'A server error occurred.', 'details' => $e->getMessage()]);
}
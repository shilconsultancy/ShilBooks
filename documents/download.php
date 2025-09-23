<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    exit("Unauthorized access.");
}

$userId = $_SESSION['user_id'];
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($doc_id > 0) {
    // Fetch the document record from the database, ensuring it belongs to the logged-in user
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND user_id = ?");
    $stmt->execute([$doc_id, $userId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($doc) {
        $file_path = '../' . $doc['file_path'] . $doc['stored_filename'];

        if (file_exists($file_path)) {
            // Set headers to force download
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $doc['file_type']);
            header('Content-Disposition: attachment; filename="' . basename($doc['original_filename']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            
            // Clear output buffer
            flush(); 
            
            // Read the file and send it to the browser
            readfile($file_path);
            exit;
        } else {
            http_response_code(404);
            exit("File not found on server.");
        }
    }
}

// If document not found for the user, or ID is invalid
http_response_code(404);
exit("Document not found.");
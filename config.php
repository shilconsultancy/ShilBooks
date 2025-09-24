<?php
// // --- PHP ERROR REPORTING (FOR DEVELOPMENT ONLY) ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Start the session on every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CSRF Protection ---
/**
 * Generates and stores a CSRF token in the session if one doesn't exist.
 * This should be called on pages that display forms.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Validates the CSRF token from a POST request.
 * Dies with an error message if validation fails. Call this at the beginning of form processing.
 */
function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Clear the session token to prevent reuse in case of an attack attempt
        unset($_SESSION['csrf_token']);
        // A simple die is effective, but for a production app, you might want a more user-friendly error page.
        die("CSRF token validation failed. Please go back, refresh the page, and try again.");
    }
    // On successful validation, unset the token to ensure it's used only once.
    unset($_SESSION['csrf_token']);
}

// Automatically generate a token on every page that is loaded with a GET request.
// This ensures that any form on the page will have a token ready.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    generate_csrf_token();
}


// --- FULLY DYNAMIC BASE PATH ---
$doc_root = $_SERVER['DOCUMENT_ROOT'];
$project_dir = __DIR__;
$doc_root = str_replace('\\', '/', $doc_root);
$project_dir = str_replace('\\', '/', $project_dir);
$subfolder = str_replace($doc_root, '', $project_dir);
$base_path = rtrim($subfolder, '/') . '/';
define('BASE_PATH', $base_path);


// --- DATABASE CONFIGURATION ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'accounting_app');

// --- Establish Database Connection ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("ERROR: Could not connect. " . $e->getMessage());
}

// --- LOAD GLOBAL SETTINGS ---
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        // If settings table doesn't exist or there's an error, use defaults
        $settings_raw = [];
    }
    
    define('CURRENCY_SYMBOL', $settings_raw['currency_symbol'] ?? '৳');
    define('INVOICE_PREFIX', $settings_raw['invoice_prefix'] ?? 'INV-');
} else {
    // Default values for logged-out pages
    define('CURRENCY_SYMBOL', '৳');
    define('INVOICE_PREFIX', 'INV-');
}
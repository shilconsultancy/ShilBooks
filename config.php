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

// --- ROLE-BASED ACCESS CONTROL ---
/**
 * Checks if the logged-in user has a specific role or is an admin.
 * @param array|string $required_roles The role(s) required to perform an action.
 * @return bool True if the user has permission, false otherwise.
 */
function hasPermission($required_roles) {
    $user_role = $_SESSION['user_role'] ?? null;

    // If user_role is not in session, try to get it from database (for existing sessions)
    if (!$user_role && isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_data) {
                $user_role = $user_data['role'];
                $_SESSION['user_role'] = $user_role; // Store for future requests
            }
        } catch (Exception $e) {
            // If database query fails, return false
            return false;
        }
    }

    if (!$user_role) {
        return false;
    }

    if ($user_role === 'admin') {
        return true; // Admin has access to everything.
    }
    if (is_array($required_roles)) {
        return in_array($user_role, $required_roles);
    }
    return $user_role === $required_roles;
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
    // Try TCP connection first, fallback to socket if needed
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=3306;dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    try {
        // Fallback to socket connection
        $pdo = new PDO("mysql:host=" . DB_HOST . ";unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e2){
        die("ERROR: Could not connect to database. " . $e2->getMessage());
    }
}

// --- LOAD GLOBAL SETTINGS ---
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    // If settings table doesn't exist or there's an error, use defaults
    $settings_raw = [];
}

define('CURRENCY_SYMBOL', $settings_raw['currency_symbol'] ?? '৳');
define('INVOICE_PREFIX', $settings_raw['invoice_prefix'] ?? 'INV-');
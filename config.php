<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shilbooks');

// Application Configuration
define('APP_NAME', 'ShilBooks');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/shilbooks/');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', 'uploads/');

// Currency and Locale Configuration
define('DEFAULT_CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');
define('LOCALE', 'en_US');

// Security Configuration
define('PASSWORD_MIN_LENGTH', 8);
define('ENABLE_2FA', false);
define('ENABLE_CSRF_PROTECTION', true);
define('RATE_LIMIT_REQUESTS', 100); // requests per hour
define('RATE_LIMIT_WINDOW', 3600); // 1 hour
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Error Reporting (set to true for development)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Security Headers (only send if not CLI and headers not already sent)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Start Session with secure settings (only if not CLI)
if (!headers_sent() && session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'gc_maxlifetime' => SESSION_LIFETIME
    ]);
}

// Database Connection Function
function getDBConnection() {
    try {
        // Try different connection methods
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;

        // Try with socket first (for XAMPP on Mac)
        if (file_exists('/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock')) {
            $dsn .= ";unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock";
        }

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            )
        );
        return $pdo;
    } catch(PDOException $e) {
        // Try alternative connection method
        try {
            $pdo = new PDO(
                "mysql:host=127.0.0.1;port=3306;dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            return $pdo;
        } catch(PDOException $e2) {
            throw new PDOException("Database connection failed. Please ensure MySQL is running. Original error: " . $e->getMessage() . " | Alternative error: " . $e2->getMessage());
        }
    }
}

// Authentication Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION['user_name'];
    }
    return null;
}

// Utility Functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function generateInvoiceNumber() {
    return 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function uploadFile($file, $targetDir = UPLOAD_PATH) {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetFile = $targetDir . basename($file['name']);
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File is too large'];
    }

    // Allow certain file formats
    if (!in_array($fileType, ALLOWED_FILE_TYPES)) {
        return ['error' => 'File type not allowed'];
    }

    // Generate unique filename to prevent conflicts
    $uniqueFileName = uniqid() . '_' . time() . '.' . $fileType;
    $targetFile = $targetDir . $uniqueFileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'filename' => $uniqueFileName, 'original_name' => basename($file['name'])];
    } else {
        return ['error' => 'Failed to upload file'];
    }
}

// Security Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}


function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    return strlen($password) >= PASSWORD_MIN_LENGTH &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function checkRateLimit($identifier, $maxRequests = RATE_LIMIT_REQUESTS, $windowSeconds = RATE_LIMIT_WINDOW) {
    $key = 'rate_limit_' . $identifier;

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $windowSeconds];
    }

    $rateLimit = $_SESSION[$key];

    if (time() > $rateLimit['reset_time']) {
        $_SESSION[$key] = ['count' => 1, 'reset_time' => time() + $windowSeconds];
        return true;
    }

    if ($rateLimit['count'] >= $maxRequests) {
        return false;
    }

    $_SESSION[$key]['count']++;
    return true;
}

function logSecurityEvent($event, $details = []) {
    $logFile = 'logs/security.log';
    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'event' => $event,
        'details' => $details,
        'session_id' => session_id()
    ];

    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function isValidFileUpload($file) {
    // Check if file was uploaded via HTTP POST
    if (!is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE || $file['size'] === 0) {
        return false;
    }

    // Check file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, ALLOWED_FILE_TYPES)) {
        return false;
    }

    // Check MIME type
    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain'
    ];

    if (isset($allowedMimeTypes[$fileType])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mimeType !== $allowedMimeTypes[$fileType]) {
            return false;
        }
    }

    return true;
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

// Session security
function regenerateSession() {
    session_regenerate_id(true);
}

function destroySession() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
?>
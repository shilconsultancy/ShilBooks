<?php
/**
 * ShilBooks System Test Suite
 * Tests all modules and functionality
 */

require_once 'config.php';

// Test results
$testResults = [];
$errors = [];
$warnings = [];

// Helper function to run test
function runTest($testName, $testFunction) {
    global $testResults;
    echo "Testing: $testName... ";

    try {
        $result = call_user_func($testFunction);
        if ($result === true) {
            echo "<span style='color: green;'>✓ PASS</span><br>";
            $testResults[] = ['name' => $testName, 'status' => 'PASS'];
        } else {
            echo "<span style='color: red;'>✗ FAIL</span><br>";
            $testResults[] = ['name' => $testName, 'status' => 'FAIL', 'message' => $result];
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>✗ ERROR: " . $e->getMessage() . "</span><br>";
        $testResults[] = ['name' => $testName, 'status' => 'ERROR', 'message' => $e->getMessage()];
    }
}

// Test database connection
function testDatabaseConnection() {
    try {
        $pdo = getDBConnection();
        return $pdo ? true : 'Database connection failed';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// Test required files exist
function testRequiredFiles() {
    $requiredFiles = [
        'config.php',
        'index.php',
        'dashboard.php',
        'includes/header.php',
        'includes/sidebar.php',
        'includes/footer.php',
        'assets/css/style.css',
        'assets/js/main.js'
    ];

    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            return "Missing required file: $file";
        }
    }

    return true;
}

// Test directory structure
function testDirectoryStructure() {
    $requiredDirs = [
        'assets/css',
        'assets/js',
        'assets/images',
        'includes',
        'items',
        'sales',
        'purchases',
        'banking',
        'employees',
        'accountant',
        'reports',
        'documents',
        'uploads'
    ];

    foreach ($requiredDirs as $dir) {
        if (!is_dir($dir)) {
            return "Missing required directory: $dir";
        }
    }

    return true;
}

// Test configuration constants
function testConfiguration() {
    $requiredConstants = [
        'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME',
        'APP_NAME', 'APP_VERSION', 'BASE_URL',
        'SESSION_LIFETIME', 'MAX_FILE_SIZE', 'UPLOAD_PATH',
        'DEFAULT_CURRENCY', 'CURRENCY_SYMBOL', 'LOCALE',
        'PASSWORD_MIN_LENGTH', 'ENABLE_2FA'
    ];

    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            return "Missing required constant: $constant";
        }
    }

    return true;
}

// Test utility functions
function testUtilityFunctions() {
    // Test sanitizeInput
    $testInput = '<script>alert("xss")</script>';
    $sanitized = sanitizeInput($testInput);
    if ($sanitized !== '<script>alert("xss")</script>') {
        return 'sanitizeInput function not working correctly';
    }

    // Test formatCurrency
    $formatted = formatCurrency(1234.56);
    if ($formatted !== '$1,234.56') {
        return 'formatCurrency function not working correctly';
    }

    // Test formatDate
    $formattedDate = formatDate('2024-01-15');
    if ($formattedDate !== 'Jan 15, 2024') {
        return 'formatDate function not working correctly';
    }

    return true;
}

// Test security functions
function testSecurityFunctions() {
    // Test CSRF token generation
    $token1 = generateCSRFToken();
    $token2 = generateCSRFToken();

    if ($token1 !== $token2) {
        return 'CSRF token generation not working correctly';
    }

    if (!isset($_SESSION['csrf_token'])) {
        return 'CSRF token not stored in session';
    }

    // Test email validation
    if (!validateEmail('test@example.com')) {
        return 'Email validation not working correctly';
    }

    if (validateEmail('invalid-email')) {
        return 'Email validation allowing invalid emails';
    }

    return true;
}

// Test session management
function testSessionManagement() {
    // Test login status
    if (isLoggedIn()) {
        return 'Session should not be logged in initially';
    }

    // Simulate login
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Test User';

    if (!isLoggedIn()) {
        return 'Session login detection not working';
    }

    if (getCurrentUser() !== 'Test User') {
        return 'getCurrentUser function not working';
    }

    // Clean up
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);

    return true;
}

// Test file upload validation
function testFileUploadValidation() {
    // Test valid file
    $validFile = [
        'name' => 'test.pdf',
        'size' => 1024,
        'tmp_name' => '/tmp/test.pdf',
        'error' => UPLOAD_ERR_OK
    ];

    if (!isValidFileUpload($validFile)) {
        return 'Valid file upload validation failed';
    }

    // Test invalid file type
    $invalidFile = [
        'name' => 'test.exe',
        'size' => 1024,
        'tmp_name' => '/tmp/test.exe',
        'error' => UPLOAD_ERR_OK
    ];

    if (isValidFileUpload($invalidFile)) {
        return 'Invalid file type validation failed';
    }

    return true;
}

// Test rate limiting
function testRateLimiting() {
    $identifier = 'test_user';

    // Reset rate limit
    unset($_SESSION['rate_limit_' . $identifier]);

    // Test within limit
    for ($i = 0; $i < 5; $i++) {
        if (!checkRateLimit($identifier, 10, 3600)) {
            return 'Rate limiting blocking valid requests';
        }
    }

    return true;
}

// Test password functions
function testPasswordFunctions() {
    $password = 'TestPassword123';

    if (!validatePassword($password)) {
        return 'Password validation not working correctly';
    }

    $weakPassword = '123';
    if (validatePassword($weakPassword)) {
        return 'Password validation allowing weak passwords';
    }

    return true;
}

// Test validation class
function testValidationClass() {
    $validator = validate([
        'email' => 'test@example.com',
        'password' => 'TestPassword123',
        'amount' => '123.45'
    ]);

    $validator->required(['email', 'password'])
              ->email('email')
              ->password('password')
              ->numeric('amount');

    if ($validator->fails()) {
        return 'Validation class not working correctly';
    }

    return true;
}

// Test module files
function testModuleFiles() {
    $modules = [
        'items/index.php',
        'sales/customers.php',
        'sales/invoices.php',
        'purchases/vendors.php',
        'purchases/expenses.php',
        'banking/index.php',
        'employees/index.php',
        'accountant/chart.php',
        'accountant/journals.php',
        'reports/index.php',
        'documents/index.php',
        'settings.php'
    ];

    foreach ($modules as $module) {
        if (!file_exists($module)) {
            return "Missing module file: $module";
        }

        // Check if file contains PHP code
        $content = file_get_contents($module);
        if (strpos($content, '<?php') === false) {
            return "Module file $module does not contain PHP code";
        }
    }

    return true;
}

// Test database tables
function testDatabaseTables() {
    try {
        $pdo = getDBConnection();
        $tables = [
            'users', 'company_settings', 'chart_of_accounts', 'customers',
            'vendors', 'items', 'invoices', 'invoice_items', 'payments_received',
            'expenses', 'bank_accounts', 'bank_transactions', 'employees',
            'payroll', 'journal_entries', 'journal_entry_lines', 'documents'
        ];

        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                return "Missing database table: $table";
            }
        }

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// Test responsive CSS
function testResponsiveCSS() {
    $cssContent = file_get_contents('assets/css/style.css');

    $requiredMediaQueries = [
        '@media (max-width: 768px)',
        '@media (max-width: 640px)',
        '@media (max-width: 480px)',
        '@media (hover: none)',
        '@media (prefers-contrast: high)',
        '@media (prefers-reduced-motion: reduce)',
        '@media (prefers-color-scheme: dark)'
    ];

    foreach ($requiredMediaQueries as $query) {
        if (strpos($cssContent, $query) === false) {
            return "Missing responsive CSS media query: $query";
        }
    }

    return true;
}

// Test JavaScript functionality
function testJavaScript() {
    $jsContent = file_get_contents('assets/js/main.js');

    $requiredFunctions = [
        'initializeSidebar',
        'initializeModals',
        'initializeForms',
        'openModal',
        'closeModal',
        'validateForm'
    ];

    foreach ($requiredFunctions as $function) {
        if (strpos($jsContent, $function) === false) {
            return "Missing JavaScript function: $function";
        }
    }

    return true;
}

// Run all tests
echo "<h1>ShilBooks System Test Suite</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 8px;'>";

// Core system tests
runTest('Database Connection', 'testDatabaseConnection');
runTest('Required Files', 'testRequiredFiles');
runTest('Directory Structure', 'testDirectoryStructure');
runTest('Configuration Constants', 'testConfiguration');
runTest('Utility Functions', 'testUtilityFunctions');
runTest('Security Functions', 'testSecurityFunctions');
runTest('Session Management', 'testSessionManagement');
runTest('File Upload Validation', 'testFileUploadValidation');
runTest('Rate Limiting', 'testRateLimiting');
runTest('Password Functions', 'testPasswordFunctions');
runTest('Validation Class', 'testValidationClass');
runTest('Module Files', 'testModuleFiles');
runTest('Database Tables', 'testDatabaseTables');
runTest('Responsive CSS', 'testResponsiveCSS');
runTest('JavaScript Functionality', 'testJavaScript');

// Summary
$passed = 0;
$failed = 0;
$errors = 0;

foreach ($testResults as $result) {
    switch ($result['status']) {
        case 'PASS':
            $passed++;
            break;
        case 'FAIL':
            $failed++;
            break;
        case 'ERROR':
            $errors++;
            break;
    }
}

echo "<br><hr>";
echo "<h3>Test Summary</h3>";
echo "Total Tests: " . count($testResults) . "<br>";
echo "Passed: <span style='color: green;'>$passed</span><br>";
echo "Failed: <span style='color: red;'>$failed</span><br>";
echo "Errors: <span style='color: red;'>$errors</span><br>";

if ($failed === 0 && $errors === 0) {
    echo "<br><div style='color: green; font-weight: bold;'>✓ All tests passed! System is ready for use.</div>";
} else {
    echo "<br><div style='color: red; font-weight: bold;'>✗ Some tests failed. Please check the errors above.</div>";
}

echo "</div>";

// Display failed tests
if ($failed > 0 || $errors > 0) {
    echo "<h3>Failed Tests Details</h3>";
    echo "<ul>";
    foreach ($testResults as $result) {
        if ($result['status'] !== 'PASS') {
            echo "<li><strong>{$result['name']}:</strong> ";
            echo $result['status'] === 'ERROR' ? 'Error - ' : 'Failed - ';
            echo $result['message'] ?? 'Unknown error';
            echo "</li>";
        }
    }
    echo "</ul>";
}

echo "<h3>Next Steps</h3>";
echo "<ol>";
echo "<li>Ensure all tests pass before using the system</li>";
echo "<li>Configure database settings in config.php</li>";
echo "<li>Import database schema from database_schema.sql</li>";
echo "<li>Set up web server to serve the application</li>";
echo "<li>Test the application in a browser</li>";
echo "<li>Configure company settings through the Settings module</li>";
echo "</ol>";
?>
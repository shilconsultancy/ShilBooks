<?php
// Comprehensive Application Debugger
echo "<h1>ShilBooks - Comprehensive Application Debugger</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
    .pass { background: #d4edda; padding: 5px; margin: 2px; }
    .fail { background: #f8d7da; padding: 5px; margin: 2px; }
</style>";

// Test 1: File Structure
echo "<div class='section'>";
echo "<h2 class='info'>1. File Structure Check</h2>";

$requiredFiles = [
    'config.php',
    'index.php',
    'dashboard.php',
    'database_schema.sql',
    'includes/header.php',
    'includes/sidebar.php',
    'includes/footer.php',
    'assets/css/style.css',
    'assets/js/main.js'
];

$requiredDirs = [
    'items',
    'sales',
    'purchases',
    'banking',
    'employees',
    'accountant',
    'reports',
    'documents'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<div class='pass'>✓ $file exists</div>";
    } else {
        echo "<div class='fail'>✗ $file missing</div>";
    }
}

foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        echo "<div class='pass'>✓ $dir/ directory exists</div>";
    } else {
        echo "<div class='fail'>✗ $dir/ directory missing</div>";
    }
}
echo "</div>";

// Test 2: Configuration
echo "<div class='section'>";
echo "<h2 class='info'>2. Configuration Test</h2>";

try {
    require_once 'config.php';
    echo "<div class='pass'>✓ Config file loads successfully</div>";
    echo "<div class='pass'>✓ APP_NAME: " . APP_NAME . "</div>";
    echo "<div class='pass'>✓ BASE_URL: " . BASE_URL . "</div>";
} catch (Exception $e) {
    echo "<div class='fail'>✗ Config file error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 3: Database Connection
echo "<div class='section'>";
echo "<h2 class='info'>3. Database Connection Test</h2>";

try {
    $pdo = getDBConnection();
    echo "<div class='pass'>✓ Database connection successful</div>";

    // Test database tables
    $tables = ['users', 'company_settings', 'chart_of_accounts', 'customers', 'items'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "<div class='pass'>✓ $table table exists (records: {$result['count']})</div>";
        } catch (PDOException $e) {
            echo "<div class='fail'>✗ $table table missing: " . $e->getMessage() . "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='fail'>✗ Database connection failed: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Authentication
echo "<div class='section'>";
echo "<h2 class='info'>4. Authentication Test</h2>";

if (function_exists('isLoggedIn')) {
    echo "<div class='pass'>✓ Authentication functions available</div>";

    // Test session
    if (session_status() == PHP_SESSION_ACTIVE) {
        echo "<div class='pass'>✓ Session is active</div>";
    } else {
        echo "<div class='fail'>✗ Session not active</div>";
    }
} else {
    echo "<div class='fail'>✗ Authentication functions missing</div>";
}
echo "</div>";

// Test 5: Module Files
echo "<div class='section'>";
echo "<h2 class='info'>5. Module Files Test</h2>";

$modules = [
    'items/index.php' => 'Items Module',
    'sales/customers.php' => 'Sales - Customers',
    'sales/invoices.php' => 'Sales - Invoices',
    'purchases/vendors.php' => 'Purchases - Vendors',
    'purchases/expenses.php' => 'Purchases - Expenses',
    'banking/index.php' => 'Banking Module',
    'employees/index.php' => 'Employees Module',
    'accountant/chart.php' => 'Accountant - Chart',
    'accountant/journals.php' => 'Accountant - Journals',
    'reports/index.php' => 'Reports Module',
    'documents/index.php' => 'Documents Module'
];

foreach ($modules as $file => $name) {
    if (file_exists($file)) {
        echo "<div class='pass'>✓ $name exists</div>";

        // Test for basic PHP syntax
        $content = file_get_contents($file);
        if (strpos($content, '<?php') !== false) {
            echo "<div class='pass'>✓ $name has PHP opening tag</div>";
        } else {
            echo "<div class='fail'>✗ $name missing PHP opening tag</div>";
        }
    } else {
        echo "<div class='fail'>✗ $name missing</div>";
    }
}
echo "</div>";

// Test 6: CSS and JS Assets
echo "<div class='section'>";
echo "<h2 class='info'>6. Assets Test</h2>";

$cssFile = 'assets/css/style.css';
$jsFile = 'assets/js/main.js';

if (file_exists($cssFile)) {
    $cssSize = filesize($cssFile);
    echo "<div class='pass'>✓ CSS file exists ($cssSize bytes)</div>";

    $cssContent = file_get_contents($cssFile);
    if (strpos($cssContent, '.sidebar') !== false) {
        echo "<div class='pass'>✓ CSS contains sidebar styles</div>";
    } else {
        echo "<div class='fail'>✗ CSS missing sidebar styles</div>";
    }
} else {
    echo "<div class='fail'>✗ CSS file missing</div>";
}

if (file_exists($jsFile)) {
    $jsSize = filesize($jsFile);
    echo "<div class='pass'>✓ JS file exists ($jsSize bytes)</div>";

    $jsContent = file_get_contents($jsFile);
    if (strpos($jsContent, 'menuToggle') !== false) {
        echo "<div class='pass'>✓ JS contains menu functionality</div>";
    } else {
        echo "<div class='fail'>✗ JS missing menu functionality</div>";
    }
} else {
    echo "<div class='fail'>✗ JS file missing</div>";
}
echo "</div>";

// Test 7: Path Resolution
echo "<div class='section'>";
echo "<h2 class='info'>7. Path Resolution Test</h2>";

$testPaths = [
    'config.php' => 'Config file',
    'includes/header.php' => 'Header include',
    'includes/sidebar.php' => 'Sidebar include',
    'includes/footer.php' => 'Footer include',
    'assets/css/style.css' => 'CSS file',
    'assets/js/main.js' => 'JS file'
];

foreach ($testPaths as $path => $name) {
    if (file_exists($path)) {
        echo "<div class='pass'>✓ $name path resolves</div>";
    } else {
        echo "<div class='fail'>✗ $name path fails</div>";
    }
}
echo "</div>";

// Test 8: PHP Error Reporting
echo "<div class='section'>";
echo "<h2 class='info'>8. PHP Configuration Test</h2>";

if (ini_get('display_errors')) {
    echo "<div class='warning'>⚠ PHP errors are displayed (development mode)</div>";
} else {
    echo "<div class='pass'>✓ PHP errors are hidden (production mode)</div>";
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'session'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='pass'>✓ $ext extension loaded</div>";
    } else {
        echo "<div class='fail'>✗ $ext extension missing</div>";
    }
}
echo "</div>";

// Summary
echo "<div class='section'>";
echo "<h2 class='info'>Summary</h2>";
echo "<p>This debug script tests all major components of the ShilBooks application.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If database tests fail: Run setup_database.php</li>";
echo "<li>If file tests fail: Check file permissions and paths</li>";
echo "<li>If authentication fails: Check session configuration</li>";
echo "<li>If styling fails: Check CSS and JS paths</li>";
echo "</ul>";
echo "</div>";

// Links
echo "<div class='section'>";
echo "<h2 class='info'>Quick Access Links</h2>";
echo "<a href='index.php'>Login Page</a> | ";
echo "<a href='dashboard.php'>Dashboard</a> | ";
echo "<a href='setup_database.php'>Setup Database</a> | ";
echo "<a href='test_paths.php'>Path Test</a>";
echo "</div>";

echo "<hr>";
echo "<p><small>Debug script executed at: " . date('Y-m-d H:i:s') . "</small></p>";
?>
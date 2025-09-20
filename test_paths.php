<?php
// Test file to verify path resolution
echo "<h1>Path Resolution Test</h1>";

// Test 1: Current directory
echo "<h2>Test 1: Current Directory</h2>";
echo "Current file: " . __FILE__ . "<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Parent directory: " . dirname(__DIR__) . "<br>";

// Test 2: Config file path
echo "<h2>Test 2: Config File Path</h2>";
$configPath = dirname(__DIR__) . '/config.php';
echo "Config path: " . $configPath . "<br>";
echo "Config exists: " . (file_exists($configPath) ? 'YES' : 'NO') . "<br>";

// Test 3: CSS file path
echo "<h2>Test 3: CSS File Path</h2>";
$cssPath = dirname(__DIR__) . '/assets/css/style.css';
echo "CSS path: " . $cssPath . "<br>";
echo "CSS exists: " . (file_exists($cssPath) ? 'YES' : 'NO') . "<br>";

// Test 4: JS file path
echo "<h2>Test 4: JavaScript File Path</h2>";
$jsPath = dirname(__DIR__) . '/assets/js/main.js';
echo "JS path: " . $jsPath . "<br>";
echo "JS exists: " . (file_exists($jsPath) ? 'YES' : 'NO') . "<br>";

// Test 5: Include test
echo "<h2>Test 5: Include Test</h2>";
try {
    require_once $configPath;
    echo "Config include: SUCCESS<br>";
    echo "App name: " . APP_NAME . "<br>";
} catch (Exception $e) {
    echo "Config include: FAILED - " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>Test Dashboard</a> | <a href='index.php'>Test Login</a></p>";
?>
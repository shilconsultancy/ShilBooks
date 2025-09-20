<?php
// Script to fix all module files with reliable path includes
echo "<h1>Fixing All Module Files</h1>";

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
    'documents/index.php'
];

$replacements = [
    // Config and header includes
    "require_once '../config.php';" => "require_once __DIR__ . '/../config.php';",
    "require_once '../includes/header.php';" => "require_once __DIR__ . '/../includes/header.php';",

    // Sidebar include
    "require_once '../includes/sidebar.php';" => "require_once __DIR__ . '/../includes/sidebar.php';",

    // Footer include
    "require_once '../includes/footer.php';" => "require_once __DIR__ . '/../includes/footer.php';",

    // Redirect paths
    "header('Location: ../index.php');" => "header('Location: ' . __DIR__ . '/../index.php');",
];

$fixed = 0;
$errors = 0;

foreach ($modules as $module) {
    if (!file_exists($module)) {
        echo "<div style='color: orange;'>⚠ $module not found, skipping...</div>";
        continue;
    }

    echo "<h3>Fixing $module</h3>";

    $content = file_get_contents($module);
    $originalContent = $content;

    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }

    if ($content !== $originalContent) {
        if (file_put_contents($module, $content)) {
            echo "<div style='color: green;'>✓ Fixed $module</div>";
            $fixed++;
        } else {
            echo "<div style='color: red;'>✗ Failed to write $module</div>";
            $errors++;
        }
    } else {
        echo "<div style='color: blue;'>ℹ $module was already correct</div>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<div style='color: green;'>Fixed: $fixed files</div>";
echo "<div style='color: red;'>Errors: $errors files</div>";
echo "<div style='color: blue;'>Total processed: " . count($modules) . " files</div>";

echo "<hr>";
echo "<p><a href='debug_app.php'>Run Debug Script</a> | ";
echo "<a href='dashboard.php'>Test Dashboard</a> | ";
echo "<a href='items/'>Test Items</a></p>";
?>
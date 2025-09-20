<?php
require_once 'config.php';

echo "<h1>Database Connection Test</h1>";

try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";

    // Test if tables exist
    $tables = [
        'users', 'company_settings', 'chart_of_accounts', 'customers',
        'vendors', 'items', 'invoices', 'invoice_items', 'payments_received',
        'expenses', 'bank_accounts', 'bank_transactions', 'employees',
        'payroll', 'journal_entries', 'journal_entry_lines', 'documents'
    ];

    echo "<h2>Table Status:</h2>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "<p style='color: green;'>✓ $table table exists (records: {$result['count']})</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ $table table missing: " . $e->getMessage() . "</p>";
        }
    }

    // Test user login
    echo "<h2>Admin User Test:</h2>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch();

    if ($user) {
        echo "<p style='color: green;'>✓ Admin user exists</p>";
        echo "<p>Username: {$user['username']}</p>";
        echo "<p>Email: {$user['email']}</p>";
        echo "<p>Role: {$user['role']}</p>";
    } else {
        echo "<p style='color: red;'>✗ Admin user not found</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ol>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Database 'shilbooks' exists</li>";
    echo "<li>Database tables are created using the SQL script</li>";
    echo "</ol>";
}
?>
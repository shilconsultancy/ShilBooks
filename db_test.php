<?php
// Database Connection Test Script
echo "=== Database Connection Test ===\n\n";

$connections = [
    ['mysql:host=localhost;dbname=accounting_app', 'root', ''],
    ['mysql:host=127.0.0.1;port=3306;dbname=accounting_app', 'root', ''],
    ['mysql:host=localhost;unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=accounting_app', 'root', ''],
];

foreach ($connections as $i => $conn) {
    try {
        $pdo = new PDO($conn[0], $conn[1], $conn[2]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✓ Connection $i successful\n";

        // Check if tables exist
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo "  Tables found: " . count($tables) . "\n";
        if (count($tables) > 0) {
            echo "  Table list: " . implode(', ', $tables) . "\n";
        } else {
            echo "  No tables found - need to import SQL file\n";
        }

        // Check if customers table exists and has data
        if (in_array('customers', $tables)) {
            $customer_count = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
            echo "  Customers count: " . $customer_count . "\n";
        }

        $pdo = null;
        break;
    } catch (Exception $e) {
        echo "✗ Connection $i failed: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Setup Instructions ===\n";
echo "1. Make sure XAMPP is running\n";
echo "2. Open http://localhost/phpmyadmin/\n";
echo "3. Create database 'accounting_app'\n";
echo "4. Import the SQL file: accounting_app (3).sql\n";
echo "5. Try accessing your application again\n";
?>
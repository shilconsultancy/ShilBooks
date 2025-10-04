<?php
/**
 * Database Setup Script for ShilBooks
 * This script creates the essential database tables needed for the application
 */

// Database connection parameters
$host = '127.0.0.1';
$port = '3306';
$dbname = 'accounting_app';
$username = 'root';
$password = '';

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host;port=$port", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Connected to MySQL server successfully\n\n";

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "✅ Database '$dbname' created or already exists\n";

    // Select the database
    $pdo->exec("USE `$dbname`");
    echo "✅ Using database '$dbname'\n\n";

    // Create essential tables
    $tables = [
        // Users table
        "users" => "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `email` varchar(100) NOT NULL,
            `user_name` varchar(100) NOT NULL,
            `role` enum('admin','accountant','user') NOT NULL DEFAULT 'user',
            `profile_picture` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        )",

        // Settings table
        "settings" => "CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        )",

        // Customers table
        "customers" => "CREATE TABLE IF NOT EXISTS `customers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `customer_type` enum('individual','company') NOT NULL DEFAULT 'individual',
            `contact_person` varchar(255) DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `phone` varchar(50) DEFAULT NULL,
            `address` text,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        )",

        // Invoices table
        "invoices" => "CREATE TABLE IF NOT EXISTS `invoices` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_number` varchar(50) NOT NULL,
            `customer_id` int(11) NOT NULL,
            `invoice_date` date NOT NULL,
            `due_date` date NOT NULL,
            `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
            `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
            `total` decimal(10,2) NOT NULL DEFAULT '0.00',
            `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
            `status` enum('draft','sent','paid','overdue') NOT NULL DEFAULT 'draft',
            `notes` text,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`),
            KEY `user_id` (`user_id`)
        )",

        // Invoice items table
        "invoice_items" => "CREATE TABLE IF NOT EXISTS `invoice_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_id` int(11) NOT NULL,
            `item_id` int(11) NOT NULL,
            `description` text,
            `quantity` decimal(10,2) NOT NULL,
            `price` decimal(10,2) NOT NULL,
            `total` decimal(10,2) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `invoice_id` (`invoice_id`),
            KEY `item_id` (`item_id`)
        )",

        // Items table
        "items" => "CREATE TABLE IF NOT EXISTS `items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `description` text,
            `item_type` enum('product','service') NOT NULL DEFAULT 'product',
            `purchase_price` decimal(10,2) DEFAULT '0.00',
            `quantity` int(11) DEFAULT '0',
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        )",

        // Expenses table
        "expenses" => "CREATE TABLE IF NOT EXISTS `expenses` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `description` text NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `expense_date` date NOT NULL,
            `category_id` int(11) NOT NULL,
            `vendor_id` int(11) DEFAULT NULL,
            `notes` text,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `category_id` (`category_id`),
            KEY `vendor_id` (`vendor_id`),
            KEY `user_id` (`user_id`)
        )",

        // Expense categories table
        "expense_categories" => "CREATE TABLE IF NOT EXISTS `expense_categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `description` text,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        )",

        // Credit notes table
        "credit_notes" => "CREATE TABLE IF NOT EXISTS `credit_notes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `credit_note_number` varchar(50) NOT NULL,
            `invoice_id` int(11) NOT NULL,
            `customer_id` int(11) NOT NULL,
            `credit_note_date` date NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `notes` text,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `invoice_id` (`invoice_id`),
            KEY `customer_id` (`customer_id`),
            KEY `user_id` (`user_id`)
        )",

        // Payments table
        "payments" => "CREATE TABLE IF NOT EXISTS `payments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `customer_id` int(11) NOT NULL,
            `payment_date` date NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `payment_method` varchar(100) DEFAULT NULL,
            `notes` text,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`),
            KEY `user_id` (`user_id`)
        )",

        // Invoice payments table (junction table)
        "invoice_payments" => "CREATE TABLE IF NOT EXISTS `invoice_payments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_id` int(11) NOT NULL,
            `payment_id` int(11) NOT NULL,
            `amount_applied` decimal(10,2) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `invoice_id` (`invoice_id`),
            KEY `payment_id` (`payment_id`)
        )"
    ];

    foreach ($tables as $table_name => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Table '$table_name' created successfully\n";
        } catch (Exception $e) {
            echo "⚠️  Table '$table_name' may already exist or failed: " . $e->getMessage() . "\n";
        }
    }

    // Insert default settings
    $default_settings = [
        ['currency_symbol', '৳'],
        ['invoice_prefix', 'INV-'],
        ['language', 'en_US'],
        ['timezone', 'Asia/Dhaka'],
        ['company_name', 'Your Company Name'],
        ['company_address', '123 Business Street, City, State, 12345'],
        ['company_phone', '+880-123-456789'],
        ['company_email', 'info@yourcompany.com']
    ];

    foreach ($default_settings as $setting) {
        try {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$setting[0], $setting[1], $setting[1]]);
            echo "✅ Setting '{$setting[0]}' configured\n";
        } catch (Exception $e) {
            echo "⚠️  Setting '{$setting[0]}' failed: " . $e->getMessage() . "\n";
        }
    }

    echo "\n🎉 Database setup completed successfully!\n";
    echo "📊 You can now access your reports at:\n";
    echo "   - Profit & Loss: http://localhost/git/ShilBooks/reports/profit-and-loss.php\n";
    echo "   - Balance Sheet: http://localhost/git/ShilBooks/reports/balance-sheet.php\n";
    echo "   - A/R Aging: http://localhost/git/ShilBooks/reports/ar-aging.php\n";

} catch (Exception $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    echo "Please make sure:\n";
    echo "1. XAMPP is running\n";
    echo "2. MySQL is started\n";
    echo "3. You have the correct database credentials\n";
}
?>
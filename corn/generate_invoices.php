<?php
// This script is meant to be run by a cron job, not accessed via a browser.

// We need to figure out the path to the config file.
// __DIR__ gives us the directory of this cron script. We go up one level to the project root.
require_once dirname(__DIR__) . '/config.php';

try {
    $pdo->beginTransaction();

    $today = new DateTime();
    $generated_count = 0;
    
    // Find active recurring profiles that are due for an invoice
    $sql = "SELECT * FROM recurring_invoices 
            WHERE user_id IN (SELECT id FROM users) AND status = 'active' 
            AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($profiles as $profile) {
        $last_gen = $profile['last_generated_date'] ? new DateTime($profile['last_generated_date']) : null;
        $start_date = new DateTime($profile['start_date']);
        
        $next_due_date = $last_gen ? clone $last_gen : clone $start_date;
        if ($last_gen) {
             switch ($profile['frequency']) {
                case 'weekly': $next_due_date->modify('+1 week'); break;
                case 'monthly': $next_due_date->modify('+1 month'); break;
                case 'quarterly': $next_due_date->modify('+3 months'); break;
                case 'yearly': $next_due_date->modify('+1 year'); break;
            }
        }
       
        if ($next_due_date <= $today) {
            // This profile is due, generate an invoice
            
            // Get new invoice number
            $inv_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ?");
            $inv_count_stmt->execute([$profile['user_id']]);
            $invoice_number = 'INV-' . str_pad($inv_count_stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
            
            $invoice_date = $today->format('Y-m-d');
            $due_date = (clone $today)->modify('+30 days')->format('Y-m-d');

            // Insert into invoices table
            $inv_sql = "INSERT INTO invoices (user_id, customer_id, invoice_number, invoice_date, due_date, subtotal, tax, total, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent')";
            $pdo->prepare($inv_sql)->execute([$profile['user_id'], $profile['customer_id'], $invoice_number, $invoice_date, $due_date, $profile['subtotal'], $profile['tax'], $profile['total'], $profile['notes']]);
            $invoice_id = $pdo->lastInsertId();

            // Copy items and update inventory
            $items_sql = "SELECT * FROM recurring_invoice_items WHERE recurring_invoice_id = ?";
            $items_stmt = $pdo->prepare($items_sql);
            $items_stmt->execute([$profile['id']]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($items as $item) {
                $inv_item_sql = "INSERT INTO invoice_items (invoice_id, item_id, description, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($inv_item_sql)->execute([$invoice_id, $item['item_id'], $item['description'], $item['quantity'], $item['price'], $item['total']]);
                
                $update_inv_sql = "UPDATE items SET quantity = quantity - ? WHERE id = ? AND item_type = 'product'";
                $pdo->prepare($update_inv_sql)->execute([$item['quantity'], $item['item_id']]);
            }

            // Update the last_generated_date on the profile
            $update_profile_sql = "UPDATE recurring_invoices SET last_generated_date = ? WHERE id = ?";
            $pdo->prepare($update_profile_sql)->execute([$invoice_date, $profile['id']]);

            $generated_count++;
        }
    }
    
    $pdo->commit();
    // Optional: Log success
    echo "Cron job ran successfully. Generated " . $generated_count . " invoice(s) on " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    $pdo->rollBack();
    // Optional: Log error
    error_log("Error generating invoices: " . $e->getMessage() . "\n");
    echo "Error generating invoices: " . $e->getMessage() . "\n";
}
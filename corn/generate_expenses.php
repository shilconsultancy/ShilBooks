<?php
// This script is meant to be run by a cron job, not accessed via a browser.
require_once dirname(__DIR__) . '/config.php';

try {
    $pdo->beginTransaction();

    $today = new DateTime();
    $generated_count = 0;
    
    // Find active recurring expense profiles that are due
    $sql = "SELECT * FROM recurring_expenses
            WHERE status = 'active'
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
            // This profile is due, generate an expense
            $expense_date = $today->format('Y-m-d');

            // Insert into expenses table
            $exp_sql = "INSERT INTO expenses (vendor_id, category_id, description, expense_date, amount, notes, status, amount_paid)
                        VALUES (?, ?, ?, ?, ?, ?, 'unpaid', 0)";
            $pdo->prepare($exp_sql)->execute([$profile['vendor_id'], $profile['category_id'], $profile['description'], $expense_date, $profile['amount'], $profile['notes']]);

            // Update the last_generated_date on the profile
            $update_profile_sql = "UPDATE recurring_expenses SET last_generated_date = ? WHERE id = ?";
            $pdo->prepare($update_profile_sql)->execute([$expense_date, $profile['id']]);

            $generated_count++;
        }
    }
    
    $pdo->commit();
    echo "Cron job for expenses ran successfully. Generated " . $generated_count . " expense(s) on " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error generating expenses: " . $e->getMessage() . "\n");
    echo "Error generating expenses: " . $e->getMessage() . "\n";
}
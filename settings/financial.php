<?php
require_once '../config.php';

// Admin-only access check
if (!hasPermission('admin')) {
    // Redirect non-admins to the dashboard or show an error
    header("location: " . BASE_PATH . "dashboard.php");
    exit;
}

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$errors = [];
$message = '';

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    try {
        $pdo->beginTransaction();
        
        // --- Handle General Settings ---
        $settings_to_save = ['currency_symbol', 'currency_name', 'invoice_prefix', 'default_payment_terms'];
        foreach ($settings_to_save as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
                $pdo->prepare($sql)->execute([$key, $value, $value]);
            }
        }

        // --- Handle Tax Rates ---
        $tax_ids = $_POST['tax_id'] ?? [];
        $tax_names = $_POST['tax_name'] ?? [];
        $tax_rates = $_POST['tax_rate'] ?? [];
        $default_tax_id = $_POST['default_tax'] ?? null;
        
        // Sanitize deleted taxes input
        $deleted_taxes_raw = isset($_POST['deleted_taxes']) && !empty($_POST['deleted_taxes']) ? explode(',', $_POST['deleted_taxes']) : [];
        $deleted_taxes = array_map('intval', $deleted_taxes_raw);
        $deleted_taxes = array_filter($deleted_taxes, function($id) { return $id > 0; });


        // Delete taxes marked for deletion
        if(!empty($deleted_taxes)) {
            $delete_placeholders = implode(',', array_fill(0, count($deleted_taxes), '?'));
            $delete_sql = "DELETE FROM tax_rates WHERE id IN (" . $delete_placeholders . ")";
            $pdo->prepare($delete_sql)->execute($deleted_taxes);
        }

        // Update is_default flag to false for all
        $pdo->prepare("UPDATE tax_rates SET is_default = FALSE")->execute();

        // Upsert (Update/Insert) tax rates
        foreach ($tax_names as $key => $name) {
            $rate = (float) $tax_rates[$key];
            $id = $tax_ids[$key];
            
            // For new rows, the radio value is unique, so we check if the ID is also the default
            $is_default = ($id == $default_tax_id);

            if (!empty($name) && is_numeric($rate)) {
                if (is_numeric($id)) { // Update existing
                    $sql = "UPDATE tax_rates SET tax_name = ?, tax_rate = ?, is_default = ? WHERE id = ?";
                    $pdo->prepare($sql)->execute([$name, $rate, $is_default, $id]);
                } else { // Insert new
                    $sql = "INSERT INTO tax_rates (tax_name, tax_rate, is_default) VALUES (?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $rate, 0]); // Insert as non-default first
                    $new_id = $pdo->lastInsertId();
                    if($default_tax_id == $id) { // if the 'new' radio button was checked
                       $pdo->prepare("UPDATE tax_rates SET is_default = TRUE WHERE id = ?")->execute([$new_id]);
                    }
                }
            }
        }

        $pdo->commit();
        $message = "Financial settings saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Error saving settings: " . $e->getMessage();
    }
}

// --- Fetch data for the form ---
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
$stmt->execute();
$settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = function($key, $default = '') { return htmlspecialchars($settings_raw[$key] ?? $default); };

$tax_stmt = $pdo->prepare("SELECT * FROM tax_rates ORDER BY tax_name ASC");
$tax_stmt->execute();
$tax_rates = $tax_stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'Financial Settings';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Financial Settings</h1>
        <a href="<?php echo BASE_PATH; ?>settings/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Settings</a>
    </header>
    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $errors[0]; ?></div><?php endif; ?>

            <form action="financial.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200 mb-6">
                    <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-6">Currency Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label for="currency_symbol" class="block text-sm font-medium text-gray-700">Base Currency Symbol</label><input type="text" name="currency_symbol" id="currency_symbol" value="<?php echo $s('currency_symbol', '৳'); ?>" placeholder="e.g., ৳ or $" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="currency_name" class="block text-sm font-medium text-gray-700">Base Currency Name</label><input type="text" name="currency_name" id="currency_name" value="<?php echo $s('currency_name', 'BDT'); ?>" placeholder="e.g., BDT or USD" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                    </div>
                </div>

                 <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200 mb-6">
                    <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-6">Tax Settings</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="w-1/2 py-2 text-left text-xs font-medium text-macgray-500 uppercase">Tax Name</th>
                                    <th class="w-1/4 py-2 text-left text-xs font-medium text-macgray-500 uppercase">Rate (%)</th>
                                    <th class="w-1/4 py-2 text-center text-xs font-medium text-macgray-500 uppercase">Default</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="tax-rates-body">
                                <?php foreach($tax_rates as $tax): ?>
                                <tr class="tax-rate-row">
                                    <td class="pr-2 py-2"><input type="hidden" name="tax_id[]" value="<?php echo $tax['id']; ?>"><input type="text" name="tax_name[]" value="<?php echo htmlspecialchars($tax['tax_name']); ?>" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
                                    <td class="px-2 py-2"><input type="number" name="tax_rate[]" value="<?php echo htmlspecialchars($tax['tax_rate']); ?>" step="0.01" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
                                    <td class="px-2 py-2 text-center"><input type="radio" name="default_tax" value="<?php echo $tax['id']; ?>" <?php echo $tax['is_default'] ? 'checked' : ''; ?> class="form-radio h-4 w-4 text-macblue-600"></td>
                                    <td class="pl-2 py-2"><button type="button" class="remove-tax-rate text-red-500 hover:text-red-700 p-1"><i data-feather="trash-2" class="w-4 h-4"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" id="add-tax-rate" class="mt-4 px-3 py-2 text-sm font-medium text-macblue-600 hover:text-macblue-800">+ Add Tax Rate</button>
                    <input type="hidden" name="deleted_taxes" id="deleted_taxes" value="">
                </div>

                 <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-6">Invoice & Billing Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div>
                            <label for="invoice_prefix" class="block text-sm font-medium text-gray-700">Invoice Number Prefix</label>
                            <input type="text" name="invoice_prefix" id="invoice_prefix" value="<?php echo $s('invoice_prefix', 'INV-'); ?>" placeholder="e.g., INV-" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div class="md:col-span-2">
                            <label for="default_payment_terms" class="block text-sm font-medium text-gray-700">Default Payment Terms</label>
                            <textarea name="default_payment_terms" id="default_payment_terms" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="e.g., Payment due within 30 days."><?php echo $s('default_payment_terms'); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end"><button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Settings</button></div>
            </form>
        </div>
    </main>
</div>

<template id="tax-rate-template">
    <tr class="tax-rate-row">
        <td class="pr-2 py-2"><input type="hidden" name="tax_id[]" value="new"><input type="text" name="tax_name[]" placeholder="e.g., VAT" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
        <td class="px-2 py-2"><input type="number" name="tax_rate[]" placeholder="15.00" step="0.01" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
        <td class="px-2 py-2 text-center"><input type="radio" name="default_tax" value="" class="form-radio h-4 w-4 text-macblue-600"></td>
        <td class="pl-2 py-2"><button type="button" class="remove-tax-rate text-red-500 hover:text-red-700 p-1"><i data-feather="trash-2" class="w-4 h-4"></i></button></td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const taxBody = document.getElementById('tax-rates-body');
    const addTaxBtn = document.getElementById('add-tax-rate');
    const template = document.getElementById('tax-rate-template');
    const deletedTaxesInput = document.getElementById('deleted_taxes');

    addTaxBtn.addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        // Ensure new radio buttons have unique values to function correctly before save
        const uniqueVal = 'new_' + Date.now();
        clone.querySelector('input[type="radio"]').value = uniqueVal;
        clone.querySelector('input[name="tax_id[]"]').value = uniqueVal;
        taxBody.appendChild(clone);
        feather.replace();
    });

    taxBody.addEventListener('click', e => {
        if (e.target.closest('.remove-tax-rate')) {
            const row = e.target.closest('.tax-rate-row');
            const taxIdInput = row.querySelector('input[name="tax_id[]"]');
            
            // Only add numeric IDs to the delete list (i.e., already saved records)
            if (taxIdInput && !isNaN(parseInt(taxIdInput.value))) {
                let deleted = deletedTaxesInput.value.split(',').filter(id => id.trim() !== '');
                deleted.push(taxIdInput.value);
                deletedTaxesInput.value = deleted.join(',');
            }
            row.remove();
        }
    });
});
</script>

<?php
require_once '../partials/footer.php';
?>
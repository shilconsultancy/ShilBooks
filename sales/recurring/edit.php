<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($profile_id == 0) {
    header("location: index.php");
    exit;
}

// --- Handle Form Submission for UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    if (empty($_POST['customer_id']) || empty($_POST['start_date']) || empty($_POST['frequency']) || empty($_POST['item_id'])) {
        $errors[] = "Please fill all required fields and add at least one item.";
    }
    if (!empty($_POST['end_date']) && $_POST['end_date'] < $_POST['start_date']) {
        $errors[] = "End date cannot be before the start date.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update the main recurring_invoices table
            $sql = "UPDATE recurring_invoices SET customer_id = :customer_id, start_date = :start_date, end_date = :end_date, 
                    frequency = :frequency, status = :status, subtotal = :subtotal, tax = :tax, total = :total, notes = :notes 
                    WHERE id = :id AND user_id = :user_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'customer_id' => $_POST['customer_id'],
                'start_date' => $_POST['start_date'],
                'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'frequency' => $_POST['frequency'],
                'status' => $_POST['status'],
                'subtotal' => $_POST['subtotal'],
                'tax' => $_POST['tax'],
                'total' => $_POST['total'],
                'notes' => $_POST['notes'],
                'id' => $profile_id,
                'user_id' => $userId
            ]);

            // 2. Delete old line items
            $delete_stmt = $pdo->prepare("DELETE FROM recurring_invoice_items WHERE recurring_invoice_id = :id");
            $delete_stmt->execute(['id' => $profile_id]);

            // 3. Re-insert new line items
            foreach ($_POST['item_id'] as $key => $itemId) {
                if (!empty($itemId)) {
                    $item_sql = "INSERT INTO recurring_invoice_items (recurring_invoice_id, item_id, description, quantity, price, total) 
                                 VALUES (:recurring_invoice_id, :item_id, :description, :quantity, :price, :total)";
                    $item_stmt = $pdo->prepare($item_sql);
                    $item_stmt->execute([
                        'recurring_invoice_id' => $profile_id,
                        'item_id' => $itemId,
                        'description' => $_POST['description'][$key],
                        'quantity' => $_POST['quantity'][$key],
                        'price' => $_POST['price'][$key],
                        'total' => $_POST['line_total'][$key]
                    ]);
                }
            }

            $pdo->commit();
            header("location: view.php?id=" . $profile_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }
}


// --- Fetch existing data for the form ---
$profile_sql = "SELECT * FROM recurring_invoices WHERE id = :id AND user_id = :user_id";
$profile_stmt = $pdo->prepare($profile_sql);
$profile_stmt->execute(['id' => $profile_id, 'user_id' => $userId]);
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header("location: index.php");
    exit;
}

$profile_items_sql = "SELECT * FROM recurring_invoice_items WHERE recurring_invoice_id = :id";
$profile_items_stmt = $pdo->prepare($profile_items_sql);
$profile_items_stmt->execute(['id' => $profile_id]);
$profile_items = $profile_items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Customers & Items for dropdowns
$customer_stmt = $pdo->prepare("SELECT id, name FROM customers WHERE user_id = :user_id ORDER BY name ASC");
$customer_stmt->execute(['user_id' => $userId]);
$customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);
$item_stmt = $pdo->prepare("SELECT id, name, description, sale_price FROM items WHERE user_id = :user_id ORDER BY name ASC");
$item_stmt->execute(['user_id' => $userId]);
$items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'Edit Recurring Profile';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6">
        <h1 class="text-xl font-semibold text-macgray-800">Edit Recurring Invoice Profile</h1>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <ul><?php foreach ($errors as $error) echo "<li>".htmlspecialchars($error)."</li>"; ?></ul>
            </div>
            <?php endif; ?>

            <form action="edit.php?id=<?php echo $profile_id; ?>" method="POST" id="recurring-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer*</label>
                            <select name="customer_id" id="customer_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo ($customer['id'] == $profile['customer_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                             <label for="status" class="block text-sm font-medium text-gray-700">Status*</label>
                            <select name="status" id="status" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="active" <?php echo ($profile['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="paused" <?php echo ($profile['status'] == 'paused') ? 'selected' : ''; ?>>Paused</option>
                                <option value="finished" <?php echo ($profile['status'] == 'finished') ? 'selected' : ''; ?>>Finished</option>
                            </select>
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date*</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($profile['start_date']); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date (Optional)</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($profile['end_date']); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="frequency" class="block text-sm font-medium text-gray-700">Frequency*</label>
                            <select name="frequency" id="frequency" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="weekly" <?php echo ($profile['frequency'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo ($profile['frequency'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                <option value="quarterly" <?php echo ($profile['frequency'] == 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                                <option value="yearly" <?php echo ($profile['frequency'] == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-6 bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <h2 class="text-lg font-medium text-macgray-800 mb-4">Invoice Line Items</h2>
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="w-2/5 text-left text-sm font-medium text-gray-500 pb-2">Item</th>
                                <th class="w-1/5 text-left text-sm font-medium text-gray-500 pb-2">Description</th>
                                <th class="w-1/6 text-left text-sm font-medium text-gray-500 pb-2">Qty</th>
                                <th class="w-1/6 text-left text-sm font-medium text-gray-500 pb-2">Price</th>
                                <th class="w-1/6 text-right text-sm font-medium text-gray-500 pb-2">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="line-items">
                            <?php foreach ($profile_items as $p_item): ?>
                            <tr class="line-item-row">
                                <td>
                                    <select name="item_id[]" class="line-item-select block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        <option value="">Select an item</option>
                                        <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" <?php echo ($item['id'] == $p_item['item_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><textarea name="description[]" rows="1" class="line-item-description block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($p_item['description']); ?></textarea></td>
                                <td><input type="number" name="quantity[]" value="<?php echo htmlspecialchars($p_item['quantity']); ?>" min="1" class="line-item-quantity block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
                                <td><input type="number" name="price[]" value="<?php echo htmlspecialchars($p_item['price']); ?>" step="0.01" class="line-item-price block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
                                <td class="text-right">
                                    <input type="text" class="line-item-total-display border-none bg-transparent text-right w-full" readonly>
                                    <input type="hidden" name="line_total[]" class="line-item-total-input">
                                </td>
                                <td><button type="button" class="remove-line-item text-red-500 hover:text-red-700 p-1"><i data-feather="trash-2" class="w-4 h-4"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" id="add-line-item" class="mt-4 px-3 py-2 text-sm font-medium text-macblue-600 hover:text-macblue-800">+ Add Line Item</button>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes / Terms</label>
                        <textarea name="notes" id="notes" rows="4" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($profile['notes']); ?></textarea>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center"><span class="text-sm font-medium text-gray-500">Subtotal</span><span id="subtotal-display">৳0.00</span></div>
                            <div class="flex justify-between items-center"><span class="text-sm font-medium text-gray-500">Tax (0%)</span><span id="tax-display">৳0.00</span></div>
                            <hr>
                            <div class="flex justify-between items-center"><span class="text-lg font-semibold text-gray-900">Total</span><span id="total-display" class="font-semibold">৳0.00</span></div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="subtotal" id="subtotal-input"><input type="hidden" name="tax" id="tax-input"><input type="hidden" name="total" id="total-input">
                <div class="mt-6 flex justify-end"><button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Changes</button></div>
            </form>
        </div>
    </main>
</div>

<template id="line-item-template">
    <tr class="line-item-row">
        <td>
            <select name="item_id[]" class="line-item-select block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <option value="">Select an item</option>
                <?php foreach ($items as $item) echo "<option value='{$item['id']}'>".htmlspecialchars($item['name'])."</option>"; ?>
            </select>
        </td>
        <td><textarea name="description[]" rows="1" class="line-item-description block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></td>
        <td><input type="number" name="quantity[]" value="1" min="1" class="line-item-quantity block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
        <td><input type="number" name="price[]" value="0.00" step="0.01" class="line-item-price block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
        <td class="text-right">
            <input type="text" class="line-item-total-display border-none bg-transparent text-right w-full" readonly value="৳0.00">
            <input type="hidden" name="line_total[]" class="line-item-total-input" value="0.00">
        </td>
        <td><button type="button" class="remove-line-item text-red-500 hover:text-red-700 p-1"><i data-feather="trash-2" class="w-4 h-4"></i></button></td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const allItems = <?php echo json_encode($items); ?>;
    // ... The JavaScript logic is identical to the invoice/edit page ...
    const lineItemsContainer = document.getElementById('line-items');
    const template = document.getElementById('line-item-template');
    
    function addLineItem() {
        const clone = template.content.cloneNode(true);
        lineItemsContainer.appendChild(clone);
        feather.replace();
    }
    
    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.line-item-row').forEach(row => {
            const quantity = parseFloat(row.querySelector('.line-item-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.line-item-price').value) || 0;
            const lineTotal = quantity * price;
            row.querySelector('.line-item-total-display').value = '৳' + lineTotal.toFixed(2);
            row.querySelector('.line-item-total-input').value = lineTotal.toFixed(2);
            subtotal += lineTotal;
        });
        const tax = 0;
        const total = subtotal + tax;
        document.getElementById('subtotal-display').innerText = '৳' + subtotal.toFixed(2);
        document.getElementById('tax-display').innerText = '৳' + tax.toFixed(2);
        document.getElementById('total-display').innerText = '৳' + total.toFixed(2);
        document.getElementById('subtotal-input').value = subtotal.toFixed(2);
        document.getElementById('tax-input').value = tax.toFixed(2);
        document.getElementById('total-input').value = total.toFixed(2);
    }
    
    calculateTotals();
    feather.replace();
    
    document.getElementById('add-line-item').addEventListener('click', addLineItem);
    
    lineItemsContainer.addEventListener('click', e => e.target.closest('.remove-line-item') && (e.target.closest('.line-item-row').remove(), calculateTotals()));

    lineItemsContainer.addEventListener('change', e => {
        if (e.target.classList.contains('line-item-select')) {
            const selectedItem = allItems.find(item => item.id == e.target.value);
            const row = e.target.closest('.line-item-row');
            if (selectedItem) {
                row.querySelector('.line-item-description').value = selectedItem.description;
                row.querySelector('.line-item-price').value = parseFloat(selectedItem.sale_price).toFixed(2);
            }
            calculateTotals();
        }
    });

    lineItemsContainer.addEventListener('input', e => (e.target.classList.contains('line-item-quantity') || e.target.classList.contains('line-item-price')) && calculateTotals());
});
</script>

<?php
require_once '../../partials/footer.php';
?>
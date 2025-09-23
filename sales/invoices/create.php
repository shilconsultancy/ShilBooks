<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];

// --- Fetch data for form dropdowns ---
$customer_sql = "SELECT id, name FROM customers WHERE user_id = :user_id ORDER BY name ASC";
$customer_stmt = $pdo->prepare($customer_sql);
$customer_stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$customer_stmt->execute();
$customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);

$item_sql = "SELECT id, name, description, sale_price, item_type FROM items WHERE user_id = :user_id ORDER BY name ASC";
$item_stmt = $pdo->prepare($item_sql);
$item_stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$item_stmt->execute();
$items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['customer_id']) || empty($_POST['invoice_date']) || empty($_POST['due_date']) || empty($_POST['item_id'])) {
        $errors[] = "Please fill all required fields and add at least one item.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Get the next invoice number
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $invoice_count = $stmt->fetchColumn();
            $invoice_number = 'INV-' . str_pad($invoice_count + 1, 4, '0', STR_PAD_LEFT);

            // 2. Insert into the main `invoices` table
            $sql = "INSERT INTO invoices (user_id, customer_id, invoice_number, invoice_date, due_date, subtotal, tax, total, notes, status) 
                    VALUES (:user_id, :customer_id, :invoice_number, :invoice_date, :due_date, :subtotal, :tax, :total, :notes, 'draft')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'customer_id' => $_POST['customer_id'],
                'invoice_number' => $invoice_number,
                'invoice_date' => $_POST['invoice_date'],
                'due_date' => $_POST['due_date'],
                'subtotal' => $_POST['subtotal'],
                'tax' => $_POST['tax'],
                'total' => $_POST['total'],
                'notes' => $_POST['notes']
            ]);
            
            $invoice_id = $pdo->lastInsertId();

            // 3. Loop through items, insert them, and update inventory
            foreach ($_POST['item_id'] as $key => $itemId) {
                if (!empty($itemId)) {
                    $quantity = $_POST['quantity'][$key];

                    // Insert into invoice_items
                    $item_sql = "INSERT INTO invoice_items (invoice_id, item_id, description, quantity, price, total) 
                                 VALUES (:invoice_id, :item_id, :description, :quantity, :price, :total)";
                    $item_stmt = $pdo->prepare($item_sql);
                    $item_stmt->execute([
                        'invoice_id' => $invoice_id,
                        'item_id' => $itemId,
                        'description' => $_POST['description'][$key],
                        'quantity' => $quantity,
                        'price' => $_POST['price'][$key],
                        'total' => $_POST['line_total'][$key]
                    ]);

                    // Update inventory for products
                    $item_type_sql = "SELECT item_type FROM items WHERE id = :id AND user_id = :user_id";
                    $item_type_stmt = $pdo->prepare($item_type_sql);
                    $item_type_stmt->execute(['id' => $itemId, 'user_id' => $userId]);
                    $item_result = $item_type_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($item_result && $item_result['item_type'] == 'product') {
                        $update_inv_sql = "UPDATE items SET quantity = quantity - :quantity WHERE id = :id";
                        $update_inv_stmt = $pdo->prepare($update_inv_sql);
                        $update_inv_stmt->execute(['quantity' => $quantity, 'id' => $itemId]);
                    }
                }
            }

            $pdo->commit();
            header("location: " . BASE_PATH . "sales/invoices/");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error creating invoice: " . $e->getMessage();
        }
    }
}


$pageTitle = 'Create Invoice';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6">
        <h1 class="text-xl font-semibold text-macgray-800">Create New Invoice</h1>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <ul><?php foreach ($errors as $error) echo "<li>".htmlspecialchars($error)."</li>"; ?></ul>
            </div>
            <?php endif; ?>

            <form action="create.php" method="POST" id="invoice-form">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer*</label>
                            <select name="customer_id" id="customer_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="">Select a customer</option>
                                <?php foreach ($customers as $customer) echo "<option value='{$customer['id']}'>".htmlspecialchars($customer['name'])."</option>"; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="invoice_date" class="block text-sm font-medium text-gray-700">Invoice Date*</label>
                                <input type="date" name="invoice_date" id="invoice_date" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date*</label>
                                <input type="date" name="due_date" id="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <h2 class="text-lg font-medium text-macgray-800 mb-4">Items</h2>
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
                        <tbody id="line-items"></tbody>
                    </table>
                    <button type="button" id="add-line-item" class="mt-4 px-3 py-2 text-sm font-medium text-macblue-600 hover:text-macblue-800">+ Add Line Item</button>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes / Terms</label>
                        <textarea name="notes" id="notes" rows="4" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
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

                <input type="hidden" name="subtotal" id="subtotal-input" value="0"><input type="hidden" name="tax" id="tax-input" value="0"><input type="hidden" name="total" id="total-input" value="0">
                <div class="mt-6 flex justify-end"><button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Invoice</button></div>
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
    
    addLineItem();
    
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
<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$errors = [];

if ($invoice_id == 0) {
    header("location: index.php");
    exit;
}

// Handle Record Payment Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_payment'])) {
    validate_csrf_token();
    $payment_amount = (float)$_POST['payment_amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $payment_notes = trim($_POST['payment_notes']);
    
    // Fetch current invoice data to validate payment amount
    $stmt = $pdo->prepare("SELECT total, amount_paid FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_due = $invoice_data['total'] - $invoice_data['amount_paid'];

    if ($payment_amount <= 0 || $payment_amount > $balance_due) {
        $errors[] = "Invalid payment amount. Must be between 0.01 and " . $balance_due;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert into payments table (user_id removed)
            $sql = "INSERT INTO payments (customer_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['customer_id'], $payment_date, $payment_amount, $payment_method, $payment_notes]);
            $payment_id = $pdo->lastInsertId();

            // 2. Link payment to this invoice
            $sql_link = "INSERT INTO invoice_payments (payment_id, invoice_id, amount_applied) VALUES (?, ?, ?)";
            $pdo->prepare($sql_link)->execute([$payment_id, $invoice_id, $payment_amount]);

            // 3. Update the invoice's amount_paid
            $sql_update = "UPDATE invoices SET amount_paid = amount_paid + ? WHERE id = ?";
            $pdo->prepare($sql_update)->execute([$payment_amount, $invoice_id]);
            
            // 4. Update invoice status if it's now fully paid
            $sql_status = "UPDATE invoices SET status = 'paid' WHERE id = ? AND total <= amount_paid";
            $pdo->prepare($sql_status)->execute([$invoice_id]);
            
            $pdo->commit();
            $message = "Payment of " . CURRENCY_SYMBOL . number_format($payment_amount, 2) . " recorded successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error recording payment: " . $e->getMessage();
        }
    }
}

// Handle Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_status'])) {
    validate_csrf_token();
    
    $new_status = $_POST['new_status'];
    $allowed_statuses = ['draft', 'sent', 'paid', 'overdue'];
    if (in_array($new_status, $allowed_statuses)) {
        $sql = "UPDATE invoices SET status = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(['status' => $new_status, 'id' => $invoice_id])) {
            $message = "Status updated successfully!";
        } else {
            $errors[] = "Error updating status.";
        }
    }
}

// Fetch invoice details
$sql = "SELECT i.*, c.name as customer_name, c.email as customer_email, c.address as customer_address
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("location: index.php");
    exit;
}
$balance_due = $invoice['total'] - $invoice['amount_paid'];

// Fetch invoice items
$items_sql = "SELECT ii.*, i.name as item_name 
              FROM invoice_items ii
              JOIN items i ON ii.item_id = i.id
              WHERE ii.invoice_id = :invoice_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['invoice_id' => $invoice_id]);
$invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch company settings for display
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
$settings_stmt->execute();
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = function($key, $default = '') { return htmlspecialchars($settings_raw[$key] ?? $default); };

$payment_methods = ['Bank Transfer', 'Cash', 'Credit Card', 'Check', 'Mobile Banking', 'Online Payment Gateway'];


$pageTitle = 'View Invoice ' . htmlspecialchars($invoice['invoice_number']);
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Invoice: <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>sales/invoices/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to All Invoices</a>
            <?php if($balance_due > 0): ?>
            <button id="recordPaymentBtn" class="px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center space-x-2 text-sm">
                <i data-feather="dollar-sign" class="w-4 h-4"></i><span>Record Payment</span>
            </button>
            <?php endif; ?>
            <a href="edit.php?id=<?php echo $invoice_id; ?>" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm"><i data-feather="edit-2" class="w-4 h-4"></i><span>Edit</span></a>
            <a href="print.php?id=<?php echo $invoice_id; ?>" target="_blank" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm"><i data-feather="printer" class="w-4 h-4"></i><span>Print</span></a>
            <a href="<?php echo BASE_PATH; ?>sales/credit-notes/create.php?from_invoice_id=<?php echo $invoice_id; ?>" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm"><i data-feather="file-minus" class="w-4 h-4"></i><span>Create Credit Note</span></a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php foreach($errors as $e) echo '<span>'.$e.'</span><br>'; ?></div><?php endif; ?>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6 flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700 mr-4">Status: <strong class="uppercase"><?php echo htmlspecialchars($invoice['status']); ?></strong></span>
                <form method="POST" action="view.php?id=<?php echo $invoice_id; ?>" class="flex items-center space-x-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="change_status" value="1">
                    <select name="new_status" class="text-sm border-gray-300 rounded-md">
                        <option value="draft" <?php echo ($invoice['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo ($invoice['status'] == 'sent') ? 'selected' : ''; ?>>Sent</option>
                        <option value="paid" <?php echo ($invoice['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="overdue" <?php echo ($invoice['status'] == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                    <button type="submit" class="px-3 py-1 bg-macgray-700 text-white text-xs font-semibold rounded-md hover:bg-macgray-800">Update</button>
                </form>
            </div>

            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="flex justify-between items-start pb-4 border-b">
                    <div>
                        <h2 class="text-2xl font-bold text-macgray-900">INVOICE</h2>
                        <p class="text-macgray-500">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                    </div>
                    <div class="text-right">
                        <h3 class="text-lg font-semibold text-macgray-800"><?php echo $s('company_name', 'Your Company'); ?></h3>
                        <p class="text-sm text-macgray-500"><?php echo nl2br($s('company_address', '123 Business Rd.<br>City, State, 12345')); ?></p>
                    </div>
                </div>

                <div class="flex justify-between items-start mt-6">
                    <div>
                        <p class="font-semibold text-macgray-500">Billed To</p>
                        <p class="font-bold text-macgray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                        <p class="text-sm text-macgray-500"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
                    </div>
                    <div class="text-right">
                        <div class="grid grid-cols-2 gap-x-4">
                            <p class="font-semibold text-macgray-500">Invoice Date:</p>
                            <p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($invoice['invoice_date']))); ?></p>
                            <p class="font-semibold text-macgray-500">Due Date:</p>
                            <p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($invoice['due_date']))); ?></p>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <table class="min-w-full">
                        <thead class="border-b-2 border-macgray-200">
                            <tr>
                                <th class="px-2 py-3 text-left text-xs font-semibold text-macgray-500 uppercase tracking-wider">Item</th>
                                <th class="px-2 py-3 text-center text-xs font-semibold text-macgray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-2 py-3 text-right text-xs font-semibold text-macgray-500 uppercase tracking-wider">Price</th>
                                <th class="px-2 py-3 text-right text-xs font-semibold text-macgray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-100">
                            <?php foreach ($invoice_items as $item): ?>
                            <tr>
                                <td class="px-2 py-4 whitespace-nowrap text-sm"><div class="font-medium text-macgray-800"><?php echo htmlspecialchars($item['item_name']); ?></div><div class="text-macgray-500 text-xs"><?php echo htmlspecialchars($item['description']); ?></div></td>
                                <td class="px-2 py-4 whitespace-nowrap text-sm text-center text-macgray-500"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="px-2 py-4 whitespace-nowrap text-sm text-right text-macgray-500"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
                                <td class="px-2 py-4 whitespace-nowrap text-sm text-right font-medium text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($item['total'], 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end mt-8">
                    <div class="w-full max-w-xs">
                        <div class="space-y-2">
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Subtotal</span><span class="text-sm text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($invoice['subtotal'], 2)); ?></span></div>
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Tax</span><span class="text-sm text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($invoice['tax'], 2)); ?></span></div>
                            <div class="flex justify-between pt-2 border-t"><span class="font-bold text-macgray-900">Total</span><span class="font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($invoice['total'], 2)); ?></span></div>
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Amount Paid</span><span class="text-sm text-macgray-800">- <?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($invoice['amount_paid'], 2)); ?></span></div>
                            <div class="flex justify-between mt-2 pt-2 border-t bg-macgray-50 p-2 rounded-md"><span class="text-base font-bold text-macgray-900">Balance Due</span><span class="text-base font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($balance_due, 2)); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="paymentModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="view.php?id=<?php echo $invoice_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="customer_id" value="<?php echo $invoice['customer_id']; ?>">
                <input type="hidden" name="record_payment" value="1">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Record Payment for #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="payment_amount" class="block text-sm font-medium text-gray-700">Amount*</label>
                            <input type="number" name="payment_amount" id="payment_amount" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" max="<?php echo number_format($balance_due, 2, '.', ''); ?>" step="0.01" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date*</label>
                                <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                <select name="payment_method" id="payment_method" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                     <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method); ?>"><?php echo htmlspecialchars($method); ?></option>
                                <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                         <div>
                            <label for="payment_notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                            <textarea name="payment_notes" id="payment_notes" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Save Payment</button>
                    <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('paymentModal');
    const recordPaymentBtn = document.getElementById('recordPaymentBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');

    if (recordPaymentBtn) {
        recordPaymentBtn.addEventListener('click', () => modal.classList.remove('hidden'));
    }
    if(closeModalBtn) {
        closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
    }
});
</script>

<?php
require_once '../../partials/footer.php';
?>
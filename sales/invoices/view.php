<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($invoice_id == 0) {
    header("location: index.php");
    exit;
}

// Handle Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_status'])) {
    validate_csrf_token();
    
    $new_status = $_POST['new_status'];
    $allowed_statuses = ['draft', 'sent', 'paid', 'overdue'];
    if (in_array($new_status, $allowed_statuses)) {
        $sql = "UPDATE invoices SET status = :status WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(['status' => $new_status, 'id' => $invoice_id, 'user_id' => $userId])) {
            $message = "Status updated successfully!";
        } else {
            $message = "Error updating status.";
        }
    }
}

// Fetch invoice details
$sql = "SELECT i.*, c.name as customer_name, c.email as customer_email, c.address as customer_address
        FROM invoices i 
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = :id AND i.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $invoice_id, 'user_id' => $userId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("location: index.php");
    exit;
}

// Fetch invoice items
$items_sql = "SELECT ii.*, i.name as item_name 
              FROM invoice_items ii
              JOIN items i ON ii.item_id = i.id
              WHERE ii.invoice_id = :invoice_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['invoice_id' => $invoice_id]);
$invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'View Invoice ' . htmlspecialchars($invoice['invoice_number']);
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Invoice: <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>sales/invoices/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to All Invoices</a>
            <a href="edit.php?id=<?php echo $invoice_id; ?>" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm"><i data-feather="edit-2" class="w-4 h-4"></i><span>Edit</span></a>
            <a href="<?php echo BASE_PATH; ?>sales/credit-notes/create.php?from_invoice_id=<?php echo $invoice_id; ?>" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm"><i data-feather="file-minus" class="w-4 h-4"></i><span>Create Credit Note</span></a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
            </div>
            <?php endif; ?>

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
                        <h3 class="text-lg font-semibold text-macgray-800">Your Company Name</h3>
                        <p class="text-sm text-macgray-500">123 Business Rd.<br>City, State, 12345</p>
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
                            <div class="flex justify-between mt-2 pt-2 border-t bg-macgray-50 p-2 rounded-md"><span class="text-base font-bold text-macgray-900">Balance Due</span><span class="text-base font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($invoice['total'] - $invoice['amount_paid'], 2)); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>
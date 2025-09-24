<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$quote_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($quote_id == 0) {
    header("location: index.php");
    exit;
}

// Handle Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_status'])) {
    validate_csrf_token();
    
    $new_status = $_POST['new_status'];
    $allowed_statuses = ['draft', 'sent', 'accepted', 'rejected'];
    if (in_array($new_status, $allowed_statuses)) {
        $sql = "UPDATE quotes SET status = :status WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(['status' => $new_status, 'id' => $quote_id, 'user_id' => $userId])) {
            $message = "Status updated successfully!";
        }
    }
}

// Fetch quote details
$sql = "SELECT q.*, c.name as customer_name, c.email as customer_email, c.address as customer_address
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id
        WHERE q.id = :id AND q.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $quote_id, 'user_id' => $userId]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header("location: index.php");
    exit;
}

// Fetch quote items
$items_sql = "SELECT qi.*, i.name as item_name 
              FROM quote_items qi
              JOIN items i ON qi.item_id = i.id
              WHERE qi.quote_id = :quote_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['quote_id' => $quote_id]);
$quote_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'View Quote ' . htmlspecialchars($quote['quote_number']);
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Quote: <?php echo htmlspecialchars($quote['quote_number']); ?></h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>sales/quotes/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to All Quotes</a>
            <a href="edit.php?id=<?php echo $quote_id; ?>" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm">
                <i data-feather="edit-2" class="w-4 h-4"></i><span>Edit</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
             <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6 flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700 mr-4">Current Status: <strong class="uppercase"><?php echo htmlspecialchars($quote['status']); ?></strong></span>
                <form method="POST" action="view.php?id=<?php echo $quote_id; ?>" class="flex items-center space-x-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="change_status" value="1">
                    <select name="new_status" class="text-sm border-gray-300 rounded-md">
                        <option value="draft" <?php echo ($quote['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo ($quote['status'] == 'sent') ? 'selected' : ''; ?>>Sent</option>
                        <option value="accepted" <?php echo ($quote['status'] == 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                        <option value="rejected" <?php echo ($quote['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <button type="submit" class="px-3 py-1 bg-macgray-700 text-white text-xs font-semibold rounded-md hover:bg-macgray-800">Update Status</button>
                </form>
            </div>

            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="flex justify-between items-start pb-4 border-b">
                    <div><h2 class="text-2xl font-bold text-macgray-900">QUOTE</h2><p class="text-macgray-500">#<?php echo htmlspecialchars($quote['quote_number']); ?></p></div>
                    <div class="text-right"><h3 class="text-lg font-semibold text-macgray-800">Your Company Name</h3><p class="text-sm text-macgray-500">123 Business Rd.<br>City, State, 12345</p></div>
                </div>

                <div class="flex justify-between items-start mt-6">
                    <div><p class="font-semibold text-macgray-500">Billed To</p><p class="font-bold text-macgray-800"><?php echo htmlspecialchars($quote['customer_name']); ?></p><p class="text-sm text-macgray-500"><?php echo nl2br(htmlspecialchars($quote['customer_address'])); ?></p></div>
                    <div class="text-right"><div class="grid grid-cols-2 gap-x-4"><p class="font-semibold text-macgray-500">Quote Date:</p><p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($quote['quote_date']))); ?></p><p class="font-semibold text-macgray-500">Expiry Date:</p><p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($quote['expiry_date']))); ?></p></div></div>
                </div>

                <div class="mt-8">
                    <table class="min-w-full">
                        <thead class="border-b-2 border-macgray-200">
                            <tr>
                                <th class="px-2 py-3 text-left text-xs font-semibold text-macgray-500 uppercase">Item</th><th class="px-2 py-3 text-center text-xs font-semibold text-macgray-500 uppercase">Qty</th><th class="px-2 py-3 text-right text-xs font-semibold text-macgray-500 uppercase">Price</th><th class="px-2 py-3 text-right text-xs font-semibold text-macgray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-100">
                            <?php foreach ($quote_items as $item): ?>
                            <tr>
                                <td class="px-2 py-4 text-sm"><div class="font-medium text-macgray-800"><?php echo htmlspecialchars($item['item_name']); ?></div><div class="text-macgray-500 text-xs"><?php echo htmlspecialchars($item['description']); ?></div></td>
                                <td class="px-2 py-4 text-sm text-center text-macgray-500"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="px-2 py-4 text-sm text-right text-macgray-500"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
                                <td class="px-2 py-4 text-sm text-right font-medium text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($item['total'], 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end mt-8">
                    <div class="w-full max-w-xs">
                        <div class="space-y-2">
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Subtotal</span><span class="text-sm text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($quote['subtotal'], 2)); ?></span></div>
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Tax</span><span class="text-sm text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($quote['tax'], 2)); ?></span></div>
                            <div class="flex justify-between pt-2 border-t"><span class="text-base font-bold text-macgray-900">Total</span><span class="text-base font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($quote['total'], 2)); ?></span></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($quote['notes'])): ?>
                <div class="mt-8 pt-4 border-t"><h4 class="text-sm font-semibold text-macgray-800">Notes</h4><p class="text-sm text-macgray-500 mt-2"><?php echo nl2br(htmlspecialchars($quote['notes'])); ?></p></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php
require_once '../../partials/footer.php';
?>
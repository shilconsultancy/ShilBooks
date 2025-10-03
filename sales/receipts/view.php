<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($receipt_id == 0) {
    header("location: index.php");
    exit;
}

// Fetch receipt details
$sql = "SELECT r.*, c.name as customer_name, c.email as customer_email, c.address as customer_address
        FROM sales_receipts r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $receipt_id]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    header("location: index.php");
    exit;
}

// Fetch receipt items
$items_sql = "SELECT ri.*, i.name as item_name 
              FROM sales_receipt_items ri
              JOIN items i ON ri.item_id = i.id
              WHERE ri.receipt_id = :receipt_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['receipt_id' => $receipt_id]);
$receipt_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch company settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
$settings_stmt->execute();
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = function($key, $default = '') { return htmlspecialchars($settings_raw[$key] ?? $default); };


$pageTitle = 'View Sales Receipt ' . htmlspecialchars($receipt['receipt_number']);
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Sales Receipt: <?php echo htmlspecialchars($receipt['receipt_number']); ?></h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>sales/receipts/" class="text-sm text-macblue-600 hover:text-macblue-800">
                &larr; Back to All Sales Receipts
            </a>
            <a href="edit.php?id=<?php echo $receipt_id; ?>" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm">
                <i data-feather="edit-2" class="w-4 h-4"></i>
                <span>Edit</span>
            </a>
            <a href="print.php?id=<?php echo $receipt_id; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
                <i data-feather="printer" class="w-4 h-4"></i>
                <span>Print</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="flex justify-between items-start pb-4 border-b">
                    <div>
                        <h2 class="text-2xl font-bold text-macgray-900">SALES RECEIPT</h2>
                        <p class="text-macgray-500">#<?php echo htmlspecialchars($receipt['receipt_number']); ?></p>
                    </div>
                    <div class="text-right">
                        <h3 class="text-lg font-semibold text-macgray-800"><?php echo $s('company_name', 'Your Company'); ?></h3>
                        <p class="text-sm text-macgray-500"><?php echo nl2br($s('company_address', '123 Business Rd.<br>City, State, 12345')); ?></p>
                    </div>
                </div>

                <div class="flex justify-between items-start mt-6">
                    <div>
                        <p class="font-semibold text-macgray-500">Customer</p>
                        <p class="font-bold text-macgray-800"><?php echo htmlspecialchars($receipt['customer_name']); ?></p>
                        <p class="text-sm text-macgray-500"><?php echo nl2br(htmlspecialchars($receipt['customer_address'])); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-macgray-500">Receipt Date:</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($receipt['receipt_date']))); ?></p>
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
                            <?php foreach ($receipt_items as $item): ?>
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
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Subtotal</span><span class="text-sm text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($receipt['subtotal'], 2)); ?></span></div>
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Tax</span><span class="text-sm text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($receipt['tax'], 2)); ?></span></div>
                            <div class="flex justify-between pt-2 border-t bg-macgray-100 p-2 rounded-md"><span class="text-base font-bold text-macgray-900">Total Paid</span><span class="text-base font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($receipt['total'], 2)); ?></span></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($receipt['notes'])): ?>
                <div class="mt-8 pt-4 border-t">
                    <h4 class="text-sm font-semibold text-macgray-800">Notes</h4>
                    <p class="text-sm text-macgray-500 mt-2"><?php echo nl2br(htmlspecialchars($receipt['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>
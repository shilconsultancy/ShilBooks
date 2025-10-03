<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id == 0) {
    die("Invalid invoice ID.");
}

// Fetch invoice details
$sql = "SELECT i.*, c.name as customer_name, c.address as customer_address
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found.");
}

// Fetch invoice items
$items_sql = "SELECT ii.*, i.name as item_name 
              FROM invoice_items ii
              JOIN items i ON ii.item_id = i.id
              WHERE ii.invoice_id = :invoice_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['invoice_id' => $invoice_id]);
$invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch company settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
$settings_stmt->execute();
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = fn($key, $default = '') => htmlspecialchars($settings_raw[$key] ?? $default);

$pageTitle = 'Print Invoice ' . htmlspecialchars($invoice['invoice_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto my-8 bg-white p-10 shadow-lg">
        
        <header class="flex justify-between items-start pb-4 border-b">
            <div class="w-1/2 flex justify-left">
                <?php $logoPath = BASE_PATH . 'uploads/1/logo.png'; ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo" class="h-20 w-auto">
            </div>
            <div class="w-1/2 text-right">
                <h2 class="text-4xl font-bold uppercase text-gray-800">Invoice</h2>
                <p class="text-gray-500 mt-2">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
            </div>
        </header>

        <section class="mt-8 flex justify-between">
            <div>
                <h4 class="font-semibold text-gray-500">Billed To</h4>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
            </div>
            <div class="text-right">
                 <div class="grid grid-cols-2 gap-x-4">
                    <p class="font-semibold text-gray-500">Invoice Date:</p>
                    <p class="text-gray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($invoice['invoice_date']))); ?></p>
                    <p class="font-semibold text-gray-500">Due Date:</p>
                    <p class="text-gray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($invoice['due_date']))); ?></p>
                </div>
            </div>
        </section>

        <section class="mt-10">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($invoice_items as $item): ?>
                    <tr>
                        <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['item_name']); ?></div></td>
                        <td class="px-6 py-4 text-center text-sm text-gray-500"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td class="px-6 py-4 text-right text-sm text-gray-500"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['price'], 2); ?></td>
                        <td class="px-6 py-4 text-right text-sm font-medium text-gray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="mt-8 flex justify-end">
            <div class="w-full max-w-sm">
                <div class="space-y-3">
                    <div class="flex justify-between"><span class="font-medium text-gray-500">Subtotal</span><span class="text-gray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['subtotal'], 2); ?></span></div>
                    <div class="flex justify-between"><span class="font-medium text-gray-500">Tax</span><span class="text-gray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['tax'], 2); ?></span></div>
                    <div class="flex justify-between border-t pt-2"><span class="font-medium text-gray-900">Total</span><span class="text-gray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['total'], 2); ?></span></div>
                    <div class="flex justify-between"><span class="font-medium text-gray-500">Amount Paid</span><span class="text-gray-800">- <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['amount_paid'], 2); ?></span></div>
                    <div class="flex justify-between pt-2 border-t bg-gray-50 p-2">
                        <span class="text-lg font-bold text-gray-900">Balance Due</span>
                        <span class="text-lg font-bold text-gray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['total'] - $invoice['amount_paid'], 2); ?></span>
                    </div>
                </div>
            </div>
        </section>
        
        <footer class="text-center mt-12 pt-6 border-t text-gray-500 text-sm">
             <p><?php echo $s('company_name'); ?> | <?php echo $s('company_email'); ?> | <?php echo $s('company_phone'); ?></p>
             <p><?php echo $s('company_address'); ?></p>
        </footer>
    </div>

    <div class="fixed bottom-5 right-5 no-print">
        <button onclick="window.print()" class="p-4 bg-black text-white rounded-full shadow-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        </button>
    </div>

    <script>
        window.onload = function() { window.print(); };
    </script>
</body>
</html>
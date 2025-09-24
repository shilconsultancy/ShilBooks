<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$quote_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($quote_id == 0) {
    die("Invalid quote ID.");
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
    die("Quote not found or you do not have permission to view it.");
}

// Fetch quote items
$items_sql = "SELECT qi.*, i.name as item_name 
              FROM quote_items qi
              JOIN items i ON qi.item_id = i.id
              WHERE qi.quote_id = :quote_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['quote_id' => $quote_id]);
$quote_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch company settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE user_id = ? AND setting_key LIKE 'company_%'");
$settings_stmt->execute([$userId]);
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = fn($key, $default = '') => htmlspecialchars($settings_raw[$key] ?? $default);

$pageTitle = 'Print Quote ' . htmlspecialchars($quote['quote_number']);
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
                <?php 
                $logoPath = BASE_PATH . 'uploads/1/logo.png'; 
                ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo" class="h-20 w-auto">
            </div>

            <div class="w-1/2 text-right">
                <h2 class="text-4xl font-bold uppercase text-gray-800">Quote</h2>
                <p class="text-gray-500 mt-2">#<?php echo htmlspecialchars($quote['quote_number']); ?></p>
            </div>
        </header>

        <section class="mt-8 flex justify-between">
            <div>
                <h4 class="font-semibold text-gray-500">Billed To</h4>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($quote['customer_name']); ?></p>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($quote['customer_address'])); ?></p>
            </div>
            <div class="text-right">
                 <div class="grid grid-cols-2 gap-x-4">
                    <p class="font-semibold text-gray-500">Quote Date:</p>
                    <p class="text-gray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($quote['quote_date']))); ?></p>
                    <p class="font-semibold text-gray-500">Expiry Date:</p>
                    <p class="text-gray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($quote['expiry_date']))); ?></p>
                </div>
            </div>
        </section>

        <section class="mt-10">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($quote_items as $item): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item['description']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($item['total'], 2)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="mt-8 flex justify-end">
            <div class="w-full max-w-sm">
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-500">Subtotal</span>
                        <span class="text-gray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($quote['subtotal'], 2)); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-500">Tax</span>
                        <span class="text-gray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($quote['tax'], 2)); ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t">
                        <span class="text-lg font-bold text-gray-900">Total</span>
                        <span class="text-lg font-bold text-gray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($quote['total'], 2)); ?></span>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($quote['notes'])): ?>
        <section class="mt-10 pt-6 border-t">
            <h4 class="text-sm font-semibold text-gray-800">Notes</h4>
            <p class="text-sm text-gray-500 mt-2"><?php echo nl2br(htmlspecialchars($quote['notes'])); ?></p>
        </section>
        <?php endif; ?>

        <footer class="text-center mt-12 pt-6 border-t text-gray-500 text-sm">
             <p><?php echo $s('company_name'); ?> | <?php echo $s('company_email'); ?> | <?php echo $s('company_phone'); ?></p>
             <p><?php echo $s('company_address'); ?></p>
        </footer>
    </div>

    <div class="fixed bottom-5 right-5 no-print">
        <button onclick="window.print()" class="p-4 bg-black text-white font-semibold rounded-full shadow-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        </button>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
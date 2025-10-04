<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

// --- Handle Date Range ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- Sales by Item Calculations ---
try {
    // Fetch sales data grouped by item
    $sql = "SELECT
                i.id,
                i.name as item_name,
                i.item_type,
                COUNT(ii.id) as times_sold,
                SUM(ii.quantity) as total_quantity,
                SUM(ii.total) as total_revenue,
                AVG(ii.price) as avg_price
            FROM items i
            LEFT JOIN invoice_items ii ON i.id = ii.item_id
            LEFT JOIN invoices inv ON ii.invoice_id = inv.id AND inv.invoice_date BETWEEN ? AND ?
            WHERE i.user_id = ? AND inv.user_id = ?
            GROUP BY i.id, i.name, i.item_type
            ORDER BY total_revenue DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date, $_SESSION['user_id'], $_SESSION['user_id']]);
    $salesByItem = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalItems = count($salesByItem);
    $totalTimesSold = array_sum(array_column($salesByItem, 'times_sold'));
    $totalQuantity = array_sum(array_column($salesByItem, 'total_quantity'));
    $totalRevenue = array_sum(array_column($salesByItem, 'total_revenue'));

} catch (Exception $e) {
    $salesByItem = [];
    $totalItems = 0;
    $totalTimesSold = 0;
    $totalQuantity = 0;
    $totalRevenue = 0;
}

// Fetch company settings
try {
    $settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
    $settings_stmt->execute();
    $settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings_raw = [];
}
$s = function($key, $default = '') use ($settings_raw) { return htmlspecialchars($settings_raw[$key] ?? $default); };

$pageTitle = 'Print Sales by Item/Service Report';
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
                $logo_file = '';
                $logoPath = BASE_PATH . 'uploads/company/logo.png';
                try {
                    $logo_setting = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'");
                    $logo_setting->execute();
                    $logo_file = $logo_setting->fetchColumn();
                    $logoPath = $logo_file ? BASE_PATH . 'uploads/company/' . $logo_file : BASE_PATH . 'uploads/company/logo.png';
                } catch (Exception $e) {
                    // Use default logo path if database query fails
                }
                ?>
                <?php if (file_exists('../' . $logoPath)): ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo" class="h-20 w-auto">
                <?php endif; ?>
            </div>
            <div class="w-1/2 text-right">
                <h2 class="text-2xl font-bold uppercase text-gray-800">Sales by Item/Service</h2>
                <p class="text-gray-500 mt-2"><?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
            </div>
        </header>

        <section class="mt-10">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <div class="text-blue-600 text-sm font-medium">Total Items</div>
                    <div class="text-2xl font-bold text-blue-800"><?php echo $totalItems; ?></div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <div class="text-green-600 text-sm font-medium">Times Sold</div>
                    <div class="text-2xl font-bold text-green-800"><?php echo $totalTimesSold; ?></div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg text-center">
                    <div class="text-yellow-600 text-sm font-medium">Total Quantity</div>
                    <div class="text-2xl font-bold text-yellow-800"><?php echo number_format($totalQuantity, 2); ?></div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg text-center">
                    <div class="text-purple-600 text-sm font-medium">Total Revenue</div>
                    <div class="text-2xl font-bold text-purple-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalRevenue, 2); ?></div>
                </div>
            </div>

            <table class="min-w-full">
                <thead class="border-b-2 border-macgray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-macgray-500 uppercase">Item/Service</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-macgray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-macgray-500 uppercase">Times Sold</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-macgray-500 uppercase">Total Qty</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-macgray-500 uppercase">Avg Price</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-macgray-500 uppercase">Total Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-macgray-200">
                    <?php if (empty($salesByItem)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-macgray-500">No sales data found for this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($salesByItem as $item): ?>
                        <tr>
                            <td class="px-4 py-4 text-sm font-medium text-macgray-800">
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </td>
                            <td class="px-4 py-4 text-sm text-center">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $item['item_type'] == 'product' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo ucfirst($item['item_type']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-center text-macgray-600">
                                <?php echo htmlspecialchars($item['times_sold']); ?>
                            </td>
                            <td class="px-4 py-4 text-sm text-center text-macgray-600">
                                <?php echo number_format($item['total_quantity'], 2); ?>
                            </td>
                            <td class="px-4 py-4 text-sm text-center text-macgray-600">
                                <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['avg_price'], 2); ?>
                            </td>
                            <td class="px-4 py-4 text-sm text-right font-medium text-macgray-800">
                                <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['total_revenue'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-macgray-50">
                    <tr>
                        <td class="px-4 py-3 text-right font-bold text-macgray-800" colspan="3"> Totals</td>
                        <td class="px-4 py-3 text-center font-bold text-macgray-800"><?php echo number_format($totalQuantity, 2); ?></td>
                        <td class="px-4 py-3 text-center font-bold text-macgray-800">-</td>
                        <td class="px-4 py-3 text-right font-bold text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalRevenue, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
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
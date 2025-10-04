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
            GROUP BY i.id, i.name, i.item_type
            ORDER BY total_revenue DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
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
    $error_message = "Database error: " . $e->getMessage();
}

$pageTitle = 'Sales by Item/Service Report';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Sales by Item/Service</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
            <a href="sales-by-item-print.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
                <i data-feather="printer" class="w-4 h-4"></i><span>Print</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6">
                <form action="sales-by-item.php" method="GET" class="flex items-center space-x-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div class="pt-5">
                        <button type="submit" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600">Apply Filter</button>
                    </div>
                </form>
            </div>

            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-macgray-900">Sales by Item/Service Summary</h2>
                    <p class="text-macgray-500">For the period: <?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-blue-600 text-sm font-medium">Total Items</div>
                        <div class="text-2xl font-bold text-blue-800"><?php echo $totalItems; ?></div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-green-600 text-sm font-medium">Times Sold</div>
                        <div class="text-2xl font-bold text-green-800"><?php echo $totalTimesSold; ?></div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <div class="text-yellow-600 text-sm font-medium">Total Quantity</div>
                        <div class="text-2xl font-bold text-yellow-800"><?php echo number_format($totalQuantity, 2); ?></div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <div class="text-purple-600 text-sm font-medium">Total Revenue</div>
                        <div class="text-2xl font-bold text-purple-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalRevenue, 2); ?></div>
                    </div>
                </div>

                <div class="overflow-x-auto">
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
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
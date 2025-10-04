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

// --- Sales by Customer Calculations ---
try {
    // Fetch sales data grouped by customer
    $sql = "SELECT
                c.id,
                c.name as customer_name,
                COUNT(DISTINCT i.id) as invoice_count,
                SUM(i.total) as total_sales,
                SUM(i.amount_paid) as total_paid,
                (SUM(i.total) - SUM(i.amount_paid)) as outstanding_amount
            FROM customers c
            LEFT JOIN invoices i ON c.id = i.customer_id AND i.invoice_date BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY total_sales DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $salesByCustomer = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalInvoices = array_sum(array_column($salesByCustomer, 'invoice_count'));
    $totalSales = array_sum(array_column($salesByCustomer, 'total_sales'));
    $totalPaid = array_sum(array_column($salesByCustomer, 'total_paid'));
    $totalOutstanding = array_sum(array_column($salesByCustomer, 'outstanding_amount'));

} catch (Exception $e) {
    $salesByCustomer = [];
    $totalInvoices = 0;
    $totalSales = 0;
    $totalPaid = 0;
    $totalOutstanding = 0;
    $error_message = "Database error: " . $e->getMessage();
}

$pageTitle = 'Sales by Customer Report';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Sales by Customer</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
            <a href="sales-by-customer-print.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
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
                <form action="sales-by-customer.php" method="GET" class="flex items-center space-x-4">
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
                    <h2 class="text-2xl font-bold text-macgray-900">Sales by Customer Summary</h2>
                    <p class="text-macgray-500">For the period: <?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-blue-600 text-sm font-medium">Total Customers</div>
                        <div class="text-2xl font-bold text-blue-800"><?php echo count($salesByCustomer); ?></div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-green-600 text-sm font-medium">Total Invoices</div>
                        <div class="text-2xl font-bold text-green-800"><?php echo $totalInvoices; ?></div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <div class="text-yellow-600 text-sm font-medium">Total Sales</div>
                        <div class="text-2xl font-bold text-yellow-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalSales, 2); ?></div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <div class="text-red-600 text-sm font-medium">Outstanding</div>
                        <div class="text-2xl font-bold text-red-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalOutstanding, 2); ?></div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="border-b-2 border-macgray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-macgray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-macgray-500 uppercase">Invoices</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-macgray-500 uppercase">Total Sales</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-macgray-500 uppercase">Amount Paid</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-macgray-500 uppercase">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($salesByCustomer)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-macgray-500">No sales data found for this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salesByCustomer as $customer): ?>
                                <tr>
                                    <td class="px-4 py-4 text-sm font-medium text-macgray-800">
                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-center text-macgray-600">
                                        <?php echo htmlspecialchars($customer['invoice_count']); ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-right font-medium text-macgray-800">
                                        <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($customer['total_sales'], 2); ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-right text-green-600">
                                        <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($customer['total_paid'], 2); ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-right <?php echo $customer['outstanding_amount'] > 0 ? 'text-red-600 font-medium' : 'text-macgray-600'; ?>">
                                        <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($customer['outstanding_amount'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-macgray-50">
                            <tr>
                                <td class="px-4 py-3 text-right font-bold text-macgray-800" colspan="2"> Totals</td>
                                <td class="px-4 py-3 text-right font-bold text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalSales, 2); ?></td>
                                <td class="px-4 py-3 text-right font-bold text-green-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalPaid, 2); ?></td>
                                <td class="px-4 py-3 text-right font-bold text-red-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalOutstanding, 2); ?></td>
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
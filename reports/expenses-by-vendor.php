<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

// --- Handle Date Filter ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- Expenses by Vendor Calculations ---
try {
    // Fetch expenses grouped by vendor/category for the date range
    $sql = "SELECT
                ec.name as vendor_name,
                COUNT(e.id) as transaction_count,
                SUM(e.amount) as total_amount,
                AVG(e.amount) as avg_amount,
                MIN(e.expense_date) as first_expense_date,
                MAX(e.expense_date) as last_expense_date
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.expense_date BETWEEN :start_date AND :end_date
            GROUP BY ec.id, ec.name
            ORDER BY total_amount DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    $vendor_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate grand totals
    $grand_total = 0;
    $grand_transaction_count = 0;

    foreach ($vendor_expenses as $vendor) {
        $grand_total += $vendor['total_amount'];
        $grand_transaction_count += $vendor['transaction_count'];
    }

    // Fetch detailed transactions for each vendor
    $vendor_details = [];
    foreach ($vendor_expenses as $vendor) {
        $vendor_id = $vendor['vendor_name']; // Using name as key since we don't have vendor_id

        $detail_sql = "SELECT
                        e.expense_date,
                        e.description,
                        e.amount,
                        e.notes
                    FROM expenses e
                    JOIN expense_categories ec ON e.category_id = ec.id
                    WHERE ec.name = :vendor_name
                    AND e.expense_date BETWEEN :start_date AND :end_date
                    ORDER BY e.expense_date DESC";

        $detail_stmt = $pdo->prepare($detail_sql);
        $detail_stmt->execute([
            'vendor_name' => $vendor['vendor_name'],
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        $vendor_details[$vendor_id] = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $vendor_expenses = [];
    $vendor_details = [];
    $grand_total = 0;
    $grand_transaction_count = 0;
    $error_message = "Database error: " . $e->getMessage();
}

$pageTitle = 'Expenses by Vendor Report';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Expenses by Vendor</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
            <a href="expenses-by-vendor-print.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
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
                <form action="expenses-by-vendor.php" method="GET" class="flex items-center space-x-4">
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
                    <h2 class="text-2xl font-bold text-macgray-900">Expenses by Vendor Summary</h2>
                    <p class="text-macgray-500"><?php echo htmlspecialchars(date("F d, Y", strtotime($start_date))); ?> - <?php echo htmlspecialchars(date("F d, Y", strtotime($end_date))); ?></p>
                </div>

                <!-- Summary Table -->
                <div class="overflow-x-auto mb-8">
                    <table class="min-w-full">
                        <thead class="border-b-2 border-macgray-300">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-macgray-600">Vendor/Category</th>
                                <th class="px-4 py-2 text-right text-sm font-semibold text-macgray-600">Transactions</th>
                                <th class="px-4 py-2 text-right text-sm font-semibold text-macgray-600">Total Amount</th>
                                <th class="px-4 py-2 text-right text-sm font-semibold text-macgray-600">Avg Amount</th>
                                <th class="px-4 py-2 text-right text-sm font-semibold text-macgray-600">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendor_expenses as $vendor): ?>
                                <tr class="border-b border-macgray-100">
                                    <td class="px-4 py-3 text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($vendor['vendor_name']); ?></td>
                                    <td class="px-4 py-3 text-sm text-right text-macgray-600"><?php echo number_format($vendor['transaction_count']); ?></td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['total_amount'], 2); ?></td>
                                    <td class="px-4 py-3 text-sm text-right text-macgray-600"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['avg_amount'], 2); ?></td>
                                    <td class="px-4 py-3 text-sm text-right text-macgray-600"><?php echo $grand_total > 0 ? number_format(($vendor['total_amount'] / $grand_total) * 100, 1) : '0.0'; ?>%</td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Grand Total Row -->
                            <tr class="border-t-2 border-macgray-300">
                                <td class="px-4 py-3 text-sm font-bold text-macgray-900">TOTAL</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-macgray-900"><?php echo number_format($grand_transaction_count); ?></td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-macgray-900"></td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-macgray-900">100.0%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Detailed Transactions -->
                <div class="space-y-8">
                    <?php foreach ($vendor_expenses as $vendor): ?>
                        <?php
                        $vendor_id = $vendor['vendor_name'];
                        $transactions = $vendor_details[$vendor_id] ?? [];
                        ?>
                        <div>
                            <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2 mb-4">
                                <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                <span class="text-sm font-normal text-macgray-500 ml-2">
                                    (<?php echo number_format($vendor['transaction_count']); ?> transactions, <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['total_amount'], 2); ?>)
                                </span>
                            </h3>

                            <?php if (!empty($transactions)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th class="py-2 text-left text-xs font-medium text-macgray-500">Date</th>
                                            <th class="py-2 text-left text-xs font-medium text-macgray-500">Description</th>
                                            <th class="py-2 text-left text-xs font-medium text-macgray-500">Notes</th>
                                            <th class="py-2 text-right text-xs font-medium text-macgray-500">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-macgray-100">
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars(date("M d, Y", strtotime($transaction['expense_date']))); ?></td>
                                                <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></td>
                                                <td class="py-2 text-sm text-right font-medium text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($transaction['amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-sm text-macgray-500 italic">No transactions found for this vendor in the selected period.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($vendor_expenses)): ?>
                <div class="text-center py-8 text-macgray-500">
                    <p>No expenses found for this period.</p>
                    <p class="text-sm mt-2">This report will show vendor expenses as they are added to the system.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
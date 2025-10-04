<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

// --- Handle Date Filter ---
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// --- A/P Aging Calculations ---
try {
    // Fetch all unpaid expenses/bills created on or before the "as of" date
    // Note: This is a placeholder since we don't have a full vendor/bills system yet
    $sql = "SELECT
                'Expense' as type,
                e.id,
                ec.name as vendor_name,
                e.expense_date as due_date,
                e.amount as amount,
                e.description,
                DATEDIFF(:as_of_date, e.expense_date) as days_overdue
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.expense_date <= :as_of_date
            AND e.amount > 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['as_of_date' => $as_of_date]);
    $unpaid_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare buckets for categorizing bills
    $buckets = [
        'Current' => [],
        '1 - 30 Days' => [],
        '31 - 60 Days' => [],
        '61 - 90 Days' => [],
        '91+ Days' => [],
    ];
    $totals = array_fill_keys(array_keys($buckets), 0);
    $grand_total = 0;

    foreach ($unpaid_bills as $bill) {
        $days = $bill['days_overdue'];
        $amount = $bill['amount'];
        $grand_total += $amount;

        if ($days <= 0) {
            $buckets['Current'][] = $bill;
            $totals['Current'] += $amount;
        } elseif ($days >= 1 && $days <= 30) {
            $buckets['1 - 30 Days'][] = $bill;
            $totals['1 - 30 Days'] += $amount;
        } elseif ($days >= 31 && $days <= 60) {
            $buckets['31 - 60 Days'][] = $bill;
            $totals['31 - 60 Days'] += $amount;
        } elseif ($days >= 61 && $days <= 90) {
            $buckets['61 - 90 Days'][] = $bill;
            $totals['61 - 90 Days'] += $amount;
        } else {
            $buckets['91+ Days'][] = $bill;
            $totals['91+ Days'] += $amount;
        }
    }

} catch (Exception $e) {
    $unpaid_bills = [];
    $buckets = array_fill_keys(['Current', '1 - 30 Days', '31 - 60 Days', '61 - 90 Days', '91+ Days'], []);
    $totals = array_fill_keys(array_keys($buckets), 0);
    $grand_total = 0;
    $error_message = "Database error: " . $e->getMessage();
}

$pageTitle = 'A/P Aging Report';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Accounts Payable Aging</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
            <a href="ap-aging-print.php?as_of_date=<?php echo $as_of_date; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
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
                <form action="ap-aging.php" method="GET" class="flex items-center space-x-4">
                    <div>
                        <label for="as_of_date" class="block text-sm font-medium text-gray-700">As of Date</label>
                        <input type="date" name="as_of_date" id="as_of_date" value="<?php echo htmlspecialchars($as_of_date); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div class="pt-5">
                        <button type="submit" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600">Apply Filter</button>
                    </div>
                </form>
            </div>

            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-macgray-900">A/P Aging Summary</h2>
                    <p class="text-macgray-500">As of <?php echo htmlspecialchars(date("F d, Y", strtotime($as_of_date))); ?></p>
                </div>

                <div class="overflow-x-auto mb-8">
                    <table class="min-w-full">
                        <thead class="border-b-2 border-macgray-300">
                            <tr>
                                <?php foreach (array_keys($buckets) as $bucket_name): ?>
                                <th class="px-4 py-2 text-right text-sm font-semibold text-macgray-600"><?php echo $bucket_name; ?></th>
                                <?php endforeach; ?>
                                <th class="px-4 py-2 text-right text-sm font-semibold text-macgray-800">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach ($totals as $total): ?>
                                <td class="px-4 py-3 text-right text-sm font-medium text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($total, 2); ?></td>
                                <?php endforeach; ?>
                                <td class="px-4 py-3 text-right text-sm font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-6">
                    <?php foreach ($buckets as $bucket_name => $bills): ?>
                        <?php if (!empty($bills)): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2 mb-2"><?php echo $bucket_name; ?></h3>
                            <table class="min-w-full">
                                <thead>
                                    <tr>
                                        <th class="py-2 text-left text-xs font-medium text-macgray-500">Vendor/Category</th>
                                        <th class="py-2 text-left text-xs font-medium text-macgray-500">Description</th>
                                        <th class="py-2 text-left text-xs font-medium text-macgray-500">Date</th>
                                        <th class="py-2 text-right text-xs font-medium text-macgray-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-macgray-100">
                                    <?php foreach ($bills as $bill): ?>
                                        <tr>
                                            <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars($bill['vendor_name']); ?></td>
                                            <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars($bill['description']); ?></td>
                                            <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars(date("M d, Y", strtotime($bill['due_date']))); ?></td>
                                            <td class="py-2 text-sm text-right font-medium text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($bill['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($unpaid_bills)): ?>
                <div class="text-center py-8 text-macgray-500">
                    <p>No accounts payable found for this period.</p>
                    <p class="text-sm mt-2">This report will show vendor bills and other payables as they are added to the system.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
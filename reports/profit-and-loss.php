<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

// --- Handle Date Range ---
// Default to the current month if no dates are provided
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');


// --- Report Data Fetching within the Date Range ---

// 1. Calculate Gross Revenue
$paid_invoices_total_stmt = $pdo->prepare("SELECT SUM(total) FROM invoices WHERE status = 'paid' AND invoice_date BETWEEN ? AND ?");
$paid_invoices_total_stmt->execute([$start_date, $end_date]);
$grossRevenue = $paid_invoices_total_stmt->fetchColumn() ?? 0;

$receipts_total_stmt = $pdo->prepare("SELECT SUM(total) FROM sales_receipts WHERE receipt_date BETWEEN ? AND ?");
$receipts_total_stmt->execute([$start_date, $end_date]);
$grossRevenue += $receipts_total_stmt->fetchColumn() ?? 0;

// 2. Subtract all Credit Notes
$credit_notes_stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_notes WHERE credit_note_date BETWEEN ? AND ?");
$credit_notes_stmt->execute([$start_date, $end_date]);
$totalCredits = $credit_notes_stmt->fetchColumn() ?? 0;
$netRevenue = $grossRevenue - $totalCredits;

// 3. Fetch Expenses grouped by category
$expenses_sql = "SELECT ec.name as category_name, SUM(e.amount) as total_amount
                 FROM expenses e
                 JOIN expense_categories ec ON e.category_id = ec.id
                 WHERE e.expense_date BETWEEN ? AND ?
                 GROUP BY e.category_id
                 ORDER BY ec.name ASC";
$expenses_stmt = $pdo->prepare($expenses_sql);
$expenses_stmt->execute([$start_date, $end_date]);
$expensesByCategory = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Calculate Total Expenses and Net Profit
$totalExpenses = array_sum(array_column($expensesByCategory, 'total_amount'));
$netProfit = $netRevenue - $totalExpenses;

$pageTitle = 'Profit & Loss Report';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Profit & Loss Statement</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
            <a href="profit-and-loss-print.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
                <i data-feather="printer" class="w-4 h-4"></i><span>Print</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6">
                <form action="profit-and-loss.php" method="GET" class="flex items-center space-x-4">
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
                    <h2 class="text-2xl font-bold text-macgray-900">Profit & Loss</h2>
                    <p class="text-macgray-500">For the period: <?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Revenue</h3>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-macgray-600">Gross Sales (Invoices + Receipts)</span>
                            <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grossRevenue, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-macgray-600">Less: Returns & Allowances (Credit Notes)</span>
                            <span>(<?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalCredits, 2); ?>)</span>
                        </div>
                    </div>
                    <div class="flex justify-between mt-4 pt-2 border-t font-bold">
                        <span>Net Revenue</span>
                        <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netRevenue, 2); ?></span>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Expenses</h3>
                    <div class="mt-4 space-y-2 text-sm">
                        <?php if(empty($expensesByCategory)): ?>
                            <p class="text-macgray-500 text-sm">No expenses recorded for this period.</p>
                        <?php else: ?>
                            <?php foreach($expensesByCategory as $expense): ?>
                            <div class="flex justify-between">
                                <span class="text-macgray-600"><?php echo htmlspecialchars($expense['category_name']); ?></span>
                                <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($expense['total_amount'], 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                         <?php endif; ?>
                    </div>
                     <div class="flex justify-between mt-4 pt-2 border-t font-bold">
                        <span>Total Expenses</span>
                        <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalExpenses, 2); ?></span>
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t-2 border-macgray-800">
                    <div class="flex justify-between font-bold text-xl <?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <span>Net Profit / (Loss)</span>
                        <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netProfit, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
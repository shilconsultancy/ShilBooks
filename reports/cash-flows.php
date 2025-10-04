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

// --- Cash Flow Calculations ---
try {
    // 1. Cash from Operating Activities
    $invoice_payments = $pdo->prepare("SELECT SUM(amount) FROM invoice_payments ip JOIN payments p ON ip.payment_id = p.id WHERE p.payment_date BETWEEN ? AND ?");
    $invoice_payments->execute([$start_date, $end_date]);
    $cashFromInvoices = $invoice_payments->fetchColumn() ?? 0;

    $expenses_paid = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $expenses_paid->execute([$start_date, $end_date]);
    $cashFromExpenses = -($expenses_paid->fetchColumn() ?? 0); // Negative for cash outflow

    $cashFromOperations = $cashFromInvoices + $cashFromExpenses;

    // 2. Cash from Investing Activities (placeholder for now)
    $cashFromInvesting = 0;

    // 3. Cash from Financing Activities (placeholder for now)
    $cashFromFinancing = 0;

    // Calculate totals
    $netCashFlow = $cashFromOperations + $cashFromInvesting + $cashFromFinancing;
    $beginningCash = 0; // Would need to calculate from previous period
    $endingCash = $beginningCash + $netCashFlow;

} catch (Exception $e) {
    $cashFromInvoices = 0;
    $cashFromExpenses = 0;
    $cashFromOperations = 0;
    $cashFromInvesting = 0;
    $cashFromFinancing = 0;
    $netCashFlow = 0;
    $beginningCash = 0;
    $endingCash = 0;
    $error_message = "Database error: " . $e->getMessage();
}

$pageTitle = 'Statement of Cash Flows';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Statement of Cash Flows</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
            <a href="cash-flows-print.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
                <i data-feather="printer" class="w-4 h-4"></i><span>Print</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6">
                <form action="cash-flows.php" method="GET" class="flex items-center space-x-4">
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
                    <h2 class="text-2xl font-bold text-macgray-900">Statement of Cash Flows</h2>
                    <p class="text-macgray-500">For the period: <?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
                </div>

                <div class="space-y-8">
                    <!-- Cash from Operating Activities -->
                    <div>
                        <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2 mb-4">Cash Flows from Operating Activities</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-macgray-600">Cash received from customers (Invoice payments)</span>
                                <span class="text-green-600">+ <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($cashFromInvoices, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-macgray-600">Cash paid for expenses</span>
                                <span class="text-red-600"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($cashFromExpenses, 2); ?></span>
                            </div>
                        </div>
                        <div class="flex justify-between mt-4 pt-2 border-t font-bold">
                            <span>Net cash from operating activities</span>
                            <span class="<?php echo $cashFromOperations >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($cashFromOperations, 2); ?></span>
                        </div>
                    </div>

                    <!-- Cash from Investing Activities -->
                    <div>
                        <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2 mb-4">Cash Flows from Investing Activities</h3>
                        <div class="space-y-2 text-sm">
                            <p class="text-macgray-500 text-sm italic">Investment activities will be tracked here</p>
                        </div>
                        <div class="flex justify-between mt-4 pt-2 border-t font-bold">
                            <span>Net cash from investing activities</span>
                            <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($cashFromInvesting, 2); ?></span>
                        </div>
                    </div>

                    <!-- Cash from Financing Activities -->
                    <div>
                        <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2 mb-4">Cash Flows from Financing Activities</h3>
                        <div class="space-y-2 text-sm">
                            <p class="text-macgray-500 text-sm italic">Financing activities will be tracked here</p>
                        </div>
                        <div class="flex justify-between mt-4 pt-2 border-t font-bold">
                            <span>Net cash from financing activities</span>
                            <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($cashFromFinancing, 2); ?></span>
                        </div>
                    </div>

                    <!-- Net Cash Flow -->
                    <div class="pt-4 border-t-2 border-macgray-800">
                        <div class="flex justify-between font-bold text-xl <?php echo $netCashFlow >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <span>Net increase in cash</span>
                            <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netCashFlow, 2); ?></span>
                        </div>
                        <div class="flex justify-between mt-2 text-macgray-600">
                            <span>Cash at beginning of period</span>
                            <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($beginningCash, 2); ?></span>
                        </div>
                        <div class="flex justify-between mt-2 pt-2 border-t font-bold text-lg">
                            <span>Cash at end of period</span>
                            <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($endingCash, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
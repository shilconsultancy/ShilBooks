<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// --- Handle Date Filter ---
// Default to today if no date is provided
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');


// --- Balance Sheet Calculations (as of the selected date) ---

// --- ASSETS ---
// 1. Cash (This is complex to calculate historically, so we'll show the current balance of accounts created before the as_of_date)
$cash_stmt = $pdo->prepare("SELECT SUM(current_balance) FROM bank_accounts WHERE user_id = ? AND DATE(created_at) <= ?");
$cash_stmt->execute([$userId, $as_of_date]);
$totalCash = $cash_stmt->fetchColumn() ?? 0;

// 2. Accounts Receivable (current balance of invoices created before the as_of_date)
$ar_stmt = $pdo->prepare("SELECT SUM(total - amount_paid) FROM invoices WHERE user_id = ? AND status IN ('sent', 'overdue') AND invoice_date <= ?");
$ar_stmt->execute([$userId, $as_of_date]);
$totalAR = $ar_stmt->fetchColumn() ?? 0;

// 3. Inventory Asset (current value)
$inv_stmt = $pdo->prepare("SELECT SUM(purchase_price * quantity) FROM items WHERE user_id = ? AND item_type = 'product'");
$inv_stmt->execute([$userId]);
$totalInventory = $inv_stmt->fetchColumn() ?? 0;

$totalAssets = $totalCash + $totalAR + $totalInventory;

// --- LIABILITIES --- (Placeholder)
$totalLiabilities = 0.00;

// --- EQUITY ---
// 1. Retained Earnings (Net Profit from the beginning of time up to the as_of_date)
$revenue_start_date = '1970-01-01'; // A date far in the past
$paid_invoices_total_stmt = $pdo->prepare("SELECT SUM(total) FROM invoices WHERE user_id = ? AND status = 'paid' AND invoice_date BETWEEN ? AND ?");
$paid_invoices_total_stmt->execute([$userId, $revenue_start_date, $as_of_date]);
$grossRevenue = $paid_invoices_total_stmt->fetchColumn() ?? 0;
$receipts_total_stmt = $pdo->prepare("SELECT SUM(total) FROM sales_receipts WHERE user_id = ? AND receipt_date BETWEEN ? AND ?");
$receipts_total_stmt->execute([$userId, $revenue_start_date, $as_of_date]);
$grossRevenue += $receipts_total_stmt->fetchColumn() ?? 0;
$credit_notes_stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_notes WHERE user_id = ? AND credit_note_date BETWEEN ? AND ?");
$credit_notes_stmt->execute([$userId, $revenue_start_date, $as_of_date]);
$totalCredits = $credit_notes_stmt->fetchColumn() ?? 0;
$netRevenue = $grossRevenue - $totalCredits;
$expenses_stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?");
$expenses_stmt->execute([$userId, $revenue_start_date, $as_of_date]);
$totalExpenses = $expenses_stmt->fetchColumn() ?? 0;
$retainedEarnings = $netRevenue - $totalExpenses;

// Owner's Equity (Placeholder for now)
$ownersEquity = 0.00;
$totalEquity = $retainedEarnings + $ownersEquity;

$totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;


$pageTitle = 'Balance Sheet Report';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Balance Sheet</h1>
        <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6">
                <form action="balance-sheet.php" method="GET" class="flex items-center space-x-4">
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
                    <h2 class="text-2xl font-bold text-macgray-900">Balance Sheet</h2>
                    <p class="text-macgray-500">As of <?php echo htmlspecialchars(date("F d, Y", strtotime($as_of_date))); ?></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Assets</h3>
                        
                        <div>
                            <h4 class="font-semibold text-macgray-700">Current Assets</h4>
                            <div class="mt-2 space-y-2 text-sm pl-4">
                                <div class="flex justify-between"><span class="text-macgray-600">Cash and Bank</span><span>৳<?php echo number_format($totalCash, 2); ?></span></div>
                                <div class="flex justify-between"><span class="text-macgray-600">Accounts Receivable</span><span>৳<?php echo number_format($totalAR, 2); ?></span></div>
                                <div class="flex justify-between"><span class="text-macgray-600">Inventory</span><span>৳<?php echo number_format($totalInventory, 2); ?></span></div>
                            </div>
                        </div>

                        <div class="flex justify-between mt-4 pt-2 border-t font-bold text-lg">
                            <span>Total Assets</span>
                            <span>৳<?php echo number_format($totalAssets, 2); ?></span>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Liabilities & Equity</h3>
                        
                        <div>
                            <h4 class="font-semibold text-macgray-700">Liabilities</h4>
                            <div class="mt-2 space-y-2 text-sm pl-4">
                                <div class="flex justify-between"><span class="text-macgray-600">Accounts Payable</span><span>৳0.00</span></div>
                                <p class="text-xs text-macgray-400">(Feature coming in Purchases module)</p>
                            </div>
                        </div>

                         <div>
                            <h4 class="font-semibold text-macgray-700 mt-4">Equity</h4>
                            <div class="mt-2 space-y-2 text-sm pl-4">
                                <div class="flex justify-between"><span class="text-macgray-600">Owner's Equity</span><span>৳0.00</span></div>
                                <div class="flex justify-between"><span class="text-macgray-600">Retained Earnings</span><span>৳<?php echo number_format($retainedEarnings, 2); ?></span></div>
                            </div>
                             <div class="flex justify-between mt-2 pt-2 border-t font-semibold">
                                <span>Total Equity</span>
                                <span>৳<?php echo number_format($totalEquity, 2); ?></span>
                            </div>
                        </div>

                        <div class="flex justify-between mt-4 pt-2 border-t font-bold text-lg">
                            <span>Total Liabilities & Equity</span>
                            <span>৳<?php echo number_format($totalLiabilitiesAndEquity, 2); ?></span>
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
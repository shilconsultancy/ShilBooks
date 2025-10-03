<?php
require_once 'config.php';

// Security Check & Logout Logic
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $_SESSION = array();
    session_destroy();
    header("location: " . BASE_PATH . "index.php");
    exit;
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// --- Dashboard Data Fetching ---

// Helper function to calculate Net Revenue for a given period
function getNetRevenueForPeriod($pdo, $startDate, $endDate) {
    $paid_invoices_total_stmt = $pdo->prepare("SELECT SUM(total) FROM invoices WHERE status = 'paid' AND invoice_date BETWEEN ? AND ?");
    $paid_invoices_total_stmt->execute([$startDate, $endDate]);
    $grossRevenue = $paid_invoices_total_stmt->fetchColumn() ?? 0;

    $receipts_total_stmt = $pdo->prepare("SELECT SUM(total) FROM sales_receipts WHERE receipt_date BETWEEN ? AND ?");
    $receipts_total_stmt->execute([$startDate, $endDate]);
    $grossRevenue += $receipts_total_stmt->fetchColumn() ?? 0;

    $credit_notes_stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_notes WHERE credit_note_date BETWEEN ? AND ?");
    $credit_notes_stmt->execute([$startDate, $endDate]);
    $totalCredits = $credit_notes_stmt->fetchColumn() ?? 0;

    return $grossRevenue - $totalCredits;
}

// Helper function to calculate Expenses for a given period
function getExpensesForPeriod($pdo, $startDate, $endDate) {
    $expenses_stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $expenses_stmt->execute([$startDate, $endDate]);
    return $expenses_stmt->fetchColumn() ?? 0;
}

// Helper function to format percentage change
function formatPercentageChange($current, $previous) {
    if ($previous == 0) {
        if ($current > 0) return '<span class="text-green-500">New</span>';
        return '<span class="text-macgray-500">-</span>';
    }
    $percentage = (($current - $previous) / abs($previous)) * 100;
    $color = $percentage >= 0 ? 'green' : 'red';
    $icon = $percentage >= 0 ? 'trending-up' : 'trending-down';
    return '<span class="text-'.$color.'-500 flex items-center"><i data-feather="'.$icon.'" class="w-3 h-3 mr-1"></i> ' . round(abs($percentage)) . '%</span>';
}


// --- Calculations ---
$start_this_month = date('Y-m-01');
$end_this_month = date('Y-m-t');
$start_last_month = date('Y-m-01', strtotime('first day of last month'));
$end_last_month = date('Y-m-t', strtotime('last day of last month'));

$revenue_this_month = getNetRevenueForPeriod($pdo, $start_this_month, $end_this_month);
$expenses_this_month = getExpensesForPeriod($pdo, $start_this_month, $end_this_month);
$profit_this_month = $revenue_this_month - $expenses_this_month;

$revenue_last_month = getNetRevenueForPeriod($pdo, $start_last_month, $end_last_month);
$expenses_last_month = getExpensesForPeriod($pdo, $start_last_month, $end_last_month);
$profit_last_month = $revenue_last_month - $expenses_last_month;

$outstanding_stmt = $pdo->prepare("SELECT SUM(total - amount_paid) FROM invoices WHERE status IN ('sent', 'overdue')");
$outstanding_stmt->execute();
$outstandingAmount = $outstanding_stmt->fetchColumn() ?? 0;

$recent_invoices_sql = "SELECT i.*, c.name as customer_name FROM invoices i JOIN customers c ON i.customer_id = c.id ORDER BY i.invoice_date DESC, i.id DESC LIMIT 5";
$recent_invoices_stmt = $pdo->prepare($recent_invoices_sql);
$recent_invoices_stmt->execute();
$recentInvoices = $recent_invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

$activity = [];
$payments_sql = "SELECT p.amount, p.payment_date as date, c.name as customer_name FROM payments p JOIN customers c ON p.customer_id = c.id ORDER BY p.payment_date DESC LIMIT 3";
$payments_stmt = $pdo->prepare($payments_sql);
$payments_stmt->execute();
while($row = $payments_stmt->fetch(PDO::FETCH_ASSOC)) {
    $activity[] = ['type' => 'payment', 'data' => $row, 'date' => new DateTime($row['date'])];
}
$expenses_sql = "SELECT e.amount, e.expense_date as date, ec.name as category_name FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id ORDER BY e.expense_date DESC LIMIT 3";
$expenses_stmt = $pdo->prepare($expenses_sql);
$expenses_stmt->execute();
while($row = $expenses_stmt->fetch(PDO::FETCH_ASSOC)) {
    $activity[] = ['type' => 'expense', 'data' => $row, 'date' => new DateTime($row['date'])];
}
usort($activity, fn($a, $b) => $b['date'] <=> $a['date']);
$recentActivities = array_slice($activity, 0, 5);

$pageTitle = 'Dashboard';
require_once 'partials/header.php';
require_once 'partials/sidebar.php';

function getInvoiceStatusBadge($status) {
    switch ($status) {
        case 'sent': return 'bg-blue-100 text-blue-800';
        case 'paid': return 'bg-green-100 text-green-800';
        case 'overdue': return 'bg-red-100 text-red-800';
        case 'draft': default: return 'bg-yellow-100 text-yellow-800';
    }
}
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Dashboard</h1>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-macgray-100 text-macgray-600"><i data-feather="bell"></i></button>
            <button class="p-2 rounded-full hover:bg-macgray-100 text-macgray-600"><i data-feather="search"></i></button>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-macgray-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-macgray-800">Welcome back, <?php echo explode(' ', htmlspecialchars($_SESSION["user_name"]))[0]; ?></h2>
                        <p class="text-macgray-500 mt-2">Here's what's happening with your business today.</p>
                    </div>
                    <a href="<?php echo BASE_PATH; ?>sales/invoices/create.php" class="mt-4 md:mt-0 px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
                        <i data-feather="plus" class="w-4 h-4"></i>
                        <span>New Invoice</span>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-macgray-500">Net Revenue (This Month)</p>
                            <p class="text-2xl font-semibold text-macgray-800 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($revenue_this_month, 2); ?></p>
                            <p class="text-xs mt-1"><?php echo formatPercentageChange($revenue_this_month, $revenue_last_month); ?> from last month</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center"><i data-feather="dollar-sign" class="text-green-500"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-macgray-500">Outstanding (All Time)</p>
                            <p class="text-2xl font-semibold text-macgray-800 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($outstandingAmount, 2); ?></p>
                            <p class="text-xs text-macgray-400 mt-1">Total amount owed</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center"><i data-feather="alert-circle" class="text-red-500"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-macgray-500">Expenses (This Month)</p>
                            <p class="text-2xl font-semibold text-macgray-800 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($expenses_this_month, 2); ?></p>
                            <p class="text-xs mt-1"><?php echo formatPercentageChange($expenses_this_month, $expenses_last_month); ?> from last month</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center"><i data-feather="credit-card" class="text-yellow-500"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-macgray-500">Profit (This Month)</p>
                            <p class="text-2xl font-semibold text-macgray-800 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($profit_this_month, 2); ?></p>
                             <p class="text-xs mt-1"><?php echo formatPercentageChange($profit_this_month, $profit_last_month); ?> from last month</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center"><i data-feather="bar-chart-2" class="text-blue-500"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <h3 class="text-lg font-semibold text-macgray-800 mb-4">Recent Activity</h3>
                    <div class="space-y-4">
                        <?php if (empty($recentActivities)): ?>
                            <p class="text-sm text-macgray-500">No recent activity.</p>
                        <?php else: ?>
                            <?php foreach($recentActivities as $activity): ?>
                            <div class="flex items-start">
                                <?php if($activity['type'] == 'payment'): ?>
                                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3 flex-shrink-0"><i data-feather="dollar-sign" class="text-green-500 w-4 h-4"></i></div>
                                    <div>
                                        <p class="text-sm font-medium text-macgray-800">Payment of <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($activity['data']['amount'], 2); ?> received</p>
                                        <p class="text-xs text-macgray-500 mt-1"><?php echo htmlspecialchars($activity['data']['customer_name']); ?> • <?php echo $activity['date']->format('M d, Y'); ?></p>
                                    </div>
                                <?php elseif($activity['type'] == 'expense'): ?>
                                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3 flex-shrink-0"><i data-feather="credit-card" class="text-yellow-500 w-4 h-4"></i></div>
                                    <div>
                                        <p class="text-sm font-medium text-macgray-800">Expense of <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($activity['data']['amount'], 2); ?> recorded</p>
                                        <p class="text-xs text-macgray-500 mt-1"><?php echo htmlspecialchars($activity['data']['category_name']); ?> • <?php echo $activity['date']->format('M d, Y'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <h3 class="text-lg font-semibold text-macgray-800 mb-4">Recent Invoices</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-macgray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-macgray-200">
                                <?php if (empty($recentInvoices)): ?>
                                    <tr><td colspan="4" class="px-4 py-3 text-center text-macgray-500">No recent invoices.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentInvoices as $invoice): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-macgray-800"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['total'], 2); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getInvoiceStatusBadge($invoice['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once 'partials/footer.php';
?>
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

// --- Dashboard Data Fetching ---

function get_dates_for_quarter($quarter, $year) {
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $start_month + 2;
    $start_date = new DateTime("$year-$start_month-01");
    $end_date = new DateTime("$year-$end_month-01");
    $end_date->modify('last day of this month');
    return ['start' => $start_date->format('Y-m-d'), 'end' => $end_date->format('Y-m-d')];
}

$current_month = date('n');
$current_quarter = ceil($current_month / 3);
$current_year = date('Y');
$current_quarter_dates = get_dates_for_quarter($current_quarter, $current_year);

$previous_quarter = $current_quarter - 1;
$previous_quarter_year = $current_year;
if ($previous_quarter == 0) {
    $previous_quarter = 4;
    $previous_quarter_year = $current_year - 1;
}
$previous_quarter_dates = get_dates_for_quarter($previous_quarter, $previous_quarter_year);


// Helper function to calculate Total Revenue for a given period (Accrual Basis)
function getTotalRevenueForPeriod($pdo, $startDate, $endDate) {
    $invoices_total_stmt = $pdo->prepare("SELECT SUM(total) FROM invoices WHERE invoice_date BETWEEN ? AND ?");
    $invoices_total_stmt->execute([$startDate, $endDate]);
    $grossRevenue = $invoices_total_stmt->fetchColumn() ?? 0;
    $receipts_total_stmt = $pdo->prepare("SELECT SUM(total) FROM sales_receipts WHERE receipt_date BETWEEN ? AND ?");
    $receipts_total_stmt->execute([$startDate, $endDate]);
    $grossRevenue += $receipts_total_stmt->fetchColumn() ?? 0;
    $credit_notes_stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_notes WHERE credit_note_date BETWEEN ? AND ?");
    $credit_notes_stmt->execute([$startDate, $endDate]);
    $totalCredits = $credit_notes_stmt->fetchColumn() ?? 0;
    return $grossRevenue - $totalCredits;
}

// Helper function to calculate total payments received for a given period
function getPaymentsReceivedForPeriod($pdo, $startDate, $endDate) {
    $payments_stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE payment_date BETWEEN ? AND ?");
    $payments_stmt->execute([$startDate, $endDate]);
    return $payments_stmt->fetchColumn() ?? 0;
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
        if ($current > 0) return '<span class="text-xs text-green-500">New</span>';
        return '<span class="text-xs text-macgray-500">-</span>';
    }
    $percentage = (($current - $previous) / abs($previous)) * 100;
    $color = $percentage >= 0 ? 'green' : 'red';
    $icon = $percentage >= 0 ? 'trending-up' : 'trending-down';
    return '<span class="text-xs text-'.$color.'-500 flex items-center"><i data-feather="'.$icon.'" class="w-3 h-3 mr-1"></i> ' . round(abs($percentage)) . '%</span>';
}

// --- Calculations for current and previous quarter ---
$total_revenue_current_quarter = getTotalRevenueForPeriod($pdo, $current_quarter_dates['start'], $current_quarter_dates['end']);
$payments_received_current_quarter = getPaymentsReceivedForPeriod($pdo, $current_quarter_dates['start'], $current_quarter_dates['end']);
$expenses_current_quarter = getExpensesForPeriod($pdo, $current_quarter_dates['start'], $current_quarter_dates['end']);
$profit_current_quarter = $total_revenue_current_quarter - $expenses_current_quarter;

$total_revenue_previous_quarter = getTotalRevenueForPeriod($pdo, $previous_quarter_dates['start'], $previous_quarter_dates['end']);
$payments_received_previous_quarter = getPaymentsReceivedForPeriod($pdo, $previous_quarter_dates['start'], $previous_quarter_dates['end']);
$expenses_previous_quarter = getExpensesForPeriod($pdo, $previous_quarter_dates['start'], $previous_quarter_dates['end']);
$profit_previous_quarter = $total_revenue_previous_quarter - $expenses_previous_quarter;


$outstanding_stmt = $pdo->prepare("SELECT SUM(total - amount_paid) FROM invoices WHERE status IN ('sent', 'overdue')");
$outstanding_stmt->execute();
$outstandingAmount = $outstanding_stmt->fetchColumn() ?? 0;

// Fetch pending invoices
$pending_invoices_sql = "SELECT i.*, c.name as customer_name FROM invoices i JOIN customers c ON i.customer_id = c.id WHERE i.status IN ('sent', 'overdue') ORDER BY i.invoice_date DESC LIMIT 5";
$pending_invoices_stmt = $pdo->prepare($pending_invoices_sql);
$pending_invoices_stmt->execute();
$pendingInvoices = $pending_invoices_stmt->fetchAll(PDO::FETCH_ASSOC);


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
usort($activity, function($a, $b) { return $b['date'] <=> $a['date']; });
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
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-macgray-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-macgray-800">Welcome back, <?php echo explode(' ', htmlspecialchars($_SESSION["user_name"]))[0]; ?></h2>
                        <p class="text-macgray-500 mt-2">Here's a snapshot of your business performance this quarter.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <p class="text-sm font-medium text-macgray-500">Total Revenue</p>
                    <p class="text-2xl font-semibold text-macgray-800 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($total_revenue_current_quarter, 2); ?></p>
                    <p class="mt-1"><?php echo formatPercentageChange($total_revenue_current_quarter, $total_revenue_previous_quarter); ?> from last quarter</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <p class="text-sm font-medium text-macgray-500">Payments Received</p>
                    <p class="text-2xl font-semibold text-green-600 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($payments_received_current_quarter, 2); ?></p>
                    <p class="mt-1"><?php echo formatPercentageChange($payments_received_current_quarter, $payments_received_previous_quarter); ?> from last quarter</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <p class="text-sm font-medium text-macgray-500">Outstanding</p>
                    <p class="text-2xl font-semibold text-red-600 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($outstandingAmount, 2); ?></p>
                    <p class="text-xs text-macgray-400 mt-1">Total amount owed</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <p class="text-sm font-medium text-macgray-500">Expenses</p>
                    <p class="text-2xl font-semibold text-macgray-800 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($expenses_current_quarter, 2); ?></p>
                    <p class="mt-1"><?php echo formatPercentageChange($expenses_current_quarter, $expenses_previous_quarter); ?> from last quarter</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <p class="text-sm font-medium text-macgray-500">Profit</p>
                    <p class="text-2xl font-semibold text-macgray-800 mt-1"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($profit_current_quarter, 2); ?></p>
                    <p class="mt-1"><?php echo formatPercentageChange($profit_current_quarter, $profit_previous_quarter); ?> from last quarter</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
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
                    <h3 class="text-lg font-semibold text-macgray-800 mb-4">Pending Invoices</h3>
                    <div class="space-y-3">
                         <?php if (empty($pendingInvoices)): ?>
                            <p class="text-sm text-macgray-500">No pending invoices.</p>
                        <?php else: ?>
                            <?php foreach ($pendingInvoices as $invoice): ?>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-macgray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                                    <p class="text-xs text-macgray-500"><?php echo htmlspecialchars($invoice['invoice_number']); ?> • Due: <?php echo date("M d, Y", strtotime($invoice['due_date'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['total'] - $invoice['amount_paid'], 2); ?></p>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getInvoiceStatusBadge($invoice['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                    <h3 class="text-lg font-semibold text-macgray-800 mb-4">Upcoming Expenses</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-macgray-800">Office Rent</p>
                                <p class="text-xs text-macgray-500">Due: Oct 31, 2025</p>
                            </div>
                            <p class="font-semibold text-macgray-800"><?php echo CURRENCY_SYMBOL; ?>25,000.00</p>
                        </div>
                         <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-macgray-800">Internet Bill</p>
                                <p class="text-xs text-macgray-500">Due: Nov 05, 2025</p>
                            </div>
                            <p class="font-semibold text-macgray-800"><?php echo CURRENCY_SYMBOL; ?>2,000.00</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-macgray-800">Software Subscription</p>
                                <p class="text-xs text-macgray-500">Due: Nov 15, 2025</p>
                            </div>
                            <p class="font-semibold text-macgray-800"><?php echo CURRENCY_SYMBOL; ?>1,500.00</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once 'partials/footer.php';
?>
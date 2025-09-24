<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// --- Handle Date Filter ---
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');


// --- A/R Aging Calculations ---

// Fetch all unpaid invoices created on or before the "as of" date
$sql = "SELECT 
            i.id, 
            i.invoice_number, 
            i.due_date,
            (i.total - i.amount_paid) as balance_due,
            c.name as customer_name,
            DATEDIFF(:as_of_date, i.due_date) as days_overdue
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.user_id = :user_id 
        AND i.status IN ('sent', 'overdue')
        AND i.invoice_date <= :as_of_date";

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $userId, 'as_of_date' => $as_of_date]);
$unpaid_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare buckets for categorizing invoices
$buckets = [
    'Current' => [],
    '1 - 30 Days' => [],
    '31 - 60 Days' => [],
    '61 - 90 Days' => [],
    '91+ Days' => [],
];
$totals = array_fill_keys(array_keys($buckets), 0);
$grand_total = 0;

foreach ($unpaid_invoices as $invoice) {
    $days = $invoice['days_overdue'];
    $balance = $invoice['balance_due'];
    $grand_total += $balance;

    if ($days <= 0) {
        $buckets['Current'][] = $invoice;
        $totals['Current'] += $balance;
    } elseif ($days >= 1 && $days <= 30) {
        $buckets['1 - 30 Days'][] = $invoice;
        $totals['1 - 30 Days'] += $balance;
    } elseif ($days >= 31 && $days <= 60) {
        $buckets['31 - 60 Days'][] = $invoice;
        $totals['31 - 60 Days'] += $balance;
    } elseif ($days >= 61 && $days <= 90) {
        $buckets['61 - 90 Days'][] = $invoice;
        $totals['61 - 90 Days'] += $balance;
    } else {
        $buckets['91+ Days'][] = $invoice;
        $totals['91+ Days'] += $balance;
    }
}

$pageTitle = 'A/R Aging Report';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Accounts Receivable Aging</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>reports/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Reports</a>
            <a href="ar-aging-print.php?as_of_date=<?php echo $as_of_date; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
                <i data-feather="printer" class="w-4 h-4"></i><span>Print</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
             <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6">
                <form action="ar-aging.php" method="GET" class="flex items-center space-x-4">
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
                    <h2 class="text-2xl font-bold text-macgray-900">A/R Aging Summary</h2>
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
                    <?php foreach ($buckets as $bucket_name => $invoices): ?>
                        <?php if (!empty($invoices)): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2 mb-2"><?php echo $bucket_name; ?></h3>
                            <table class="min-w-full">
                                <thead>
                                    <tr>
                                        <th class="py-2 text-left text-xs font-medium text-macgray-500">Customer</th>
                                        <th class="py-2 text-left text-xs font-medium text-macgray-500">Invoice #</th>
                                        <th class="py-2 text-left text-xs font-medium text-macgray-500">Due Date</th>
                                        <th class="py-2 text-right text-xs font-medium text-macgray-500">Balance Due</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-macgray-100">
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                            <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                            <td class="py-2 text-sm text-macgray-600"><?php echo htmlspecialchars(date("M d, Y", strtotime($invoice['due_date']))); ?></td>
                                            <td class="py-2 text-sm text-right font-medium text-macgray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['balance_due'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
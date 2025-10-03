<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ".BASE_PATH."index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// --- A/R Aging Calculations ---
$sql = "SELECT i.id, i.invoice_number, i.due_date, (i.total - i.amount_paid) as balance_due, c.name as customer_name, DATEDIFF(:as_of_date, i.due_date) as days_overdue
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.status IN ('sent', 'overdue') AND i.invoice_date <= :as_of_date";
$stmt = $pdo->prepare($sql);
$stmt->execute(['as_of_date' => $as_of_date]);
$unpaid_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$buckets = ['Current' => [], '1 - 30 Days' => [], '31 - 60 Days' => [], '61 - 90 Days' => [], '91+ Days' => []];
$totals = array_fill_keys(array_keys($buckets), 0);
$grand_total = 0;

foreach ($unpaid_invoices as $invoice) {
    $days = $invoice['days_overdue'];
    $balance = $invoice['balance_due'];
    $grand_total += $balance;

    if ($days <= 0) { $buckets['Current'][] = $invoice; $totals['Current'] += $balance; } 
    elseif ($days <= 30) { $buckets['1 - 30 Days'][] = $invoice; $totals['1 - 30 Days'] += $balance; } 
    elseif ($days <= 60) { $buckets['31 - 60 Days'][] = $invoice; $totals['31 - 60 Days'] += $balance; } 
    elseif ($days <= 90) { $buckets['61 - 90 Days'][] = $invoice; $totals['61 - 90 Days'] += $balance; } 
    else { $buckets['91+ Days'][] = $invoice; $totals['91+ Days'] += $balance; }
}

// Fetch company settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
$settings_stmt->execute();
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = fn($key, $default = '') => htmlspecialchars($settings_raw[$key] ?? $default);

$pageTitle = 'Print A/R Aging Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto my-8 bg-white p-10 shadow-lg">
        
        <header class="flex justify-between items-start pb-4 border-b">
            <div class="w-1/2 flex justify-left">
                <?php $logoPath = BASE_PATH . 'uploads/1/logo.png'; ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo" class="h-20 w-auto">
            </div>
            <div class="w-1/2 text-right">
                <h2 class="text-2xl font-bold uppercase text-gray-800">A/R Aging Summary</h2>
                <p class="text-gray-500 mt-2">As of <?php echo htmlspecialchars(date("F d, Y", strtotime($as_of_date))); ?></p>
            </div>
        </header>

        <section class="mt-10">
            <table class="w-full mb-8">
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
        </section>
        
        <footer class="text-center mt-12 pt-6 border-t text-gray-500 text-sm">
             <p><?php echo $s('company_name'); ?> | <?php echo $s('company_email'); ?> | <?php echo $s('company_phone'); ?></p>
             <p><?php echo $s('company_address'); ?></p>
        </footer>
    </div>

    <div class="fixed bottom-5 right-5 no-print">
        <button onclick="window.print()" class="p-4 bg-black text-white rounded-full shadow-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        </button>
    </div>

    <script>
        window.onload = function() { window.print(); };
    </script>
</body>
</html>
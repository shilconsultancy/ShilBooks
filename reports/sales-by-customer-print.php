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
            WHERE c.user_id = ?
            GROUP BY c.id, c.name
            ORDER BY total_sales DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date, $_SESSION['user_id']]);
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
}

// Fetch company settings
try {
    $settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
    $settings_stmt->execute();
    $settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings_raw = [];
}
$s = function($key, $default = '') use ($settings_raw) { return htmlspecialchars($settings_raw[$key] ?? $default); };

$pageTitle = 'Print Sales by Customer Report';
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
                <?php
                $logo_file = '';
                $logoPath = BASE_PATH . 'uploads/company/logo.png';
                try {
                    $logo_setting = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'");
                    $logo_setting->execute();
                    $logo_file = $logo_setting->fetchColumn();
                    $logoPath = $logo_file ? BASE_PATH . 'uploads/company/' . $logo_file : BASE_PATH . 'uploads/company/logo.png';
                } catch (Exception $e) {
                    // Use default logo path if database query fails
                }
                ?>
                <?php if (file_exists('../' . $logoPath)): ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo" class="h-20 w-auto">
                <?php endif; ?>
            </div>
            <div class="w-1/2 text-right">
                <h2 class="text-2xl font-bold uppercase text-gray-800">Sales by Customer</h2>
                <p class="text-gray-500 mt-2"><?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
            </div>
        </header>

        <section class="mt-10">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <div class="text-blue-600 text-sm font-medium">Total Customers</div>
                    <div class="text-2xl font-bold text-blue-800"><?php echo count($salesByCustomer); ?></div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <div class="text-green-600 text-sm font-medium">Total Invoices</div>
                    <div class="text-2xl font-bold text-green-800"><?php echo $totalInvoices; ?></div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg text-center">
                    <div class="text-yellow-600 text-sm font-medium">Total Sales</div>
                    <div class="text-2xl font-bold text-yellow-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalSales, 2); ?></div>
                </div>
                <div class="bg-red-50 p-4 rounded-lg text-center">
                    <div class="text-red-600 text-sm font-medium">Outstanding</div>
                    <div class="text-2xl font-bold text-red-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalOutstanding, 2); ?></div>
                </div>
            </div>

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
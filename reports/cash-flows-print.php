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

$pageTitle = 'Print Statement of Cash Flows';
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
                <h2 class="text-2xl font-bold uppercase text-gray-800">Statement of Cash Flows</h2>
                <p class="text-gray-500 mt-2"><?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
            </div>
        </header>

        <section class="mt-10">
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
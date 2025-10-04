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

// --- Report Data Fetching ---
try {
    $paid_invoices_total_stmt = $pdo->prepare("SELECT SUM(total) FROM invoices WHERE status = 'paid' AND invoice_date BETWEEN ? AND ?");
    $paid_invoices_total_stmt->execute([$start_date, $end_date]);
    $grossRevenue = $paid_invoices_total_stmt->fetchColumn() ?? 0;
    
    // Note: Sales receipts feature has been removed from this project

    $credit_notes_stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_notes WHERE credit_note_date BETWEEN ? AND ?");
    $credit_notes_stmt->execute([$start_date, $end_date]);
    $totalCredits = $credit_notes_stmt->fetchColumn() ?? 0;
    $netRevenue = $grossRevenue - $totalCredits;

    $expenses_sql = "SELECT ec.name as category_name, SUM(e.amount) as total_amount
                     FROM expenses e
                     JOIN expense_categories ec ON e.category_id = ec.id
                     WHERE e.expense_date BETWEEN ? AND ?
                     GROUP BY e.category_id
                     ORDER BY ec.name ASC";
    $expenses_stmt = $pdo->prepare($expenses_sql);
    $expenses_stmt->execute([$start_date, $end_date]);
    $expensesByCategory = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalExpenses = array_sum(array_column($expensesByCategory, 'total_amount'));
    $netProfit = $netRevenue - $totalExpenses;
} catch (Exception $e) {
    // Handle database errors gracefully
    $grossRevenue = 0;
    $totalCredits = 0;
    $netRevenue = 0;
    $expensesByCategory = [];
    $totalExpenses = 0;
    $netProfit = 0;
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

$pageTitle = 'Print Profit & Loss';
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
                <h2 class="text-2xl font-bold uppercase text-gray-800">Profit & Loss Statement</h2>
                <p class="text-gray-500 mt-2"><?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?></p>
            </div>
        </header>

        <section class="mt-10">
            <div>
                <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Revenue</h3>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-macgray-600">Gross Sales (Invoices)</span><span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grossRevenue, 2); ?></span></div>
                    <div class="flex justify-between"><span class="text-macgray-600">Less: Returns & Allowances (Credit Notes)</span><span>(<?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalCredits, 2); ?>)</span></div>
                </div>
                <div class="flex justify-between mt-4 pt-2 border-t font-bold"><span class="text-base">Net Revenue</span><span class="text-base"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netRevenue, 2); ?></span></div>
            </div>

            <div class="mt-8">
                <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Expenses</h3>
                <div class="mt-4 space-y-2 text-sm">
                    <?php if(empty($expensesByCategory)): ?>
                        <p class="text-macgray-500 text-sm">No expenses recorded for this period.</p>
                    <?php else: ?>
                        <?php foreach($expensesByCategory as $expense): ?>
                        <div class="flex justify-between"><span class="text-macgray-600"><?php echo htmlspecialchars($expense['category_name']); ?></span><span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($expense['total_amount'], 2); ?></span></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between mt-4 pt-2 border-t font-bold"><span class="text-base">Total Expenses</span><span class="text-base"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalExpenses, 2); ?></span></div>
            </div>

            <div class="mt-8 pt-4 border-t-2 border-macgray-800">
                <div class="flex justify-between font-bold text-xl <?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <span>Net Profit / (Loss)</span>
                    <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netProfit, 2); ?></span>
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
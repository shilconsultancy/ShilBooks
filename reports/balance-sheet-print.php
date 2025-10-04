<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ".BASE_PATH."index.php");
    exit;
}

$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// --- ASSETS ---
try {
    $cash_stmt = $pdo->prepare("SELECT SUM(current_balance) FROM bank_accounts WHERE DATE(created_at) <= ?");
    $cash_stmt->execute([$as_of_date]);
    $totalCash = $cash_stmt->fetchColumn() ?? 0;

    $ar_stmt = $pdo->prepare("SELECT SUM(total - amount_paid) FROM invoices WHERE status IN ('sent', 'overdue') AND invoice_date <= ?");
    $ar_stmt->execute([$as_of_date]);
    $totalAR = $ar_stmt->fetchColumn() ?? 0;

    $inv_stmt = $pdo->prepare("SELECT SUM(purchase_price * quantity) FROM items WHERE item_type = 'product'");
    $inv_stmt->execute();
    $totalInventory = $inv_stmt->fetchColumn() ?? 0;

    $totalAssets = $totalCash + $totalAR + $totalInventory;

    // --- LIABILITIES ---
    $totalLiabilities = 0.00; // Placeholder

    // --- EQUITY ---
    $revenue_start_date = '1970-01-01';
    $paid_invoices_total_stmt = $pdo->prepare("SELECT SUM(total) FROM invoices WHERE status = 'paid' AND invoice_date BETWEEN ? AND ?");
    $paid_invoices_total_stmt->execute([$revenue_start_date, $as_of_date]);
    $grossRevenue = $paid_invoices_total_stmt->fetchColumn() ?? 0;
    // Note: Sales receipts feature has been removed from this project

    $credit_notes_stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_notes WHERE credit_note_date BETWEEN ? AND ?");
    $credit_notes_stmt->execute([$revenue_start_date, $as_of_date]);
    $totalCredits = $credit_notes_stmt->fetchColumn() ?? 0;
    $netRevenue = $grossRevenue - $totalCredits;

    $expenses_stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $expenses_stmt->execute([$revenue_start_date, $as_of_date]);
    $totalExpenses = $expenses_stmt->fetchColumn() ?? 0;
    $retainedEarnings = $netRevenue - $totalExpenses;

    $ownersEquity = 0.00; // Placeholder
    $totalEquity = $retainedEarnings + $ownersEquity;
    $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
} catch (Exception $e) {
    // Handle database errors gracefully
    $totalCash = 0;
    $totalAR = 0;
    $totalInventory = 0;
    $totalAssets = 0;
    $totalLiabilities = 0;
    $retainedEarnings = 0;
    $ownersEquity = 0;
    $totalEquity = 0;
    $totalLiabilitiesAndEquity = 0;
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

$pageTitle = 'Print Balance Sheet';
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
                <h2 class="text-2xl font-bold uppercase text-gray-800">Balance Sheet</h2>
                <p class="text-gray-500 mt-2">As of <?php echo htmlspecialchars(date("F d, Y", strtotime($as_of_date))); ?></p>
            </div>
        </header>

        <section class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-8">
             <div class="space-y-6">
                <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Assets</h3>
                <div>
                    <h4 class="font-semibold text-macgray-700">Current Assets</h4>
                    <div class="mt-2 space-y-2 text-sm pl-4">
                        <div class="flex justify-between"><span class="text-macgray-600">Cash and Bank</span><span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalCash, 2); ?></span></div>
                        <div class="flex justify-between"><span class="text-macgray-600">Accounts Receivable</span><span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalAR, 2); ?></span></div>
                        <div class="flex justify-between"><span class="text-macgray-600">Inventory</span><span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalInventory, 2); ?></span></div>
                    </div>
                </div>
                <div class="flex justify-between mt-4 pt-2 border-t font-bold text-lg">
                    <span>Total Assets</span>
                    <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalAssets, 2); ?></span>
                </div>
            </div>

            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-macgray-800 border-b pb-2">Liabilities & Equity</h3>
                <div>
                    <h4 class="font-semibold text-macgray-700">Liabilities</h4>
                    <div class="mt-2 space-y-2 text-sm pl-4">
                        <div class="flex justify-between"><span class="text-macgray-600">Accounts Payable</span><span><?php echo CURRENCY_SYMBOL; ?>0.00</span></div>
                    </div>
                </div>
                 <div>
                    <h4 class="font-semibold text-macgray-700 mt-4">Equity</h4>
                    <div class="mt-2 space-y-2 text-sm pl-4">
                        <div class="flex justify-between"><span class="text-macgray-600">Owner's Equity</span><span><?php echo CURRENCY_SYMBOL; ?>0.00</span></div>
                        <div class="flex justify-between"><span class="text-macgray-600">Retained Earnings</span><span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($retainedEarnings, 2); ?></span></div>
                    </div>
                     <div class="flex justify-between mt-2 pt-2 border-t font-semibold">
                        <span>Total Equity</span>
                        <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalEquity, 2); ?></span>
                    </div>
                </div>
                <div class="flex justify-between mt-4 pt-2 border-t font-bold text-lg">
                    <span>Total Liabilities & Equity</span>
                    <span><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalLiabilitiesAndEquity, 2); ?></span>
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
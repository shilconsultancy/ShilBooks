<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Reports';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Get date range for reports (default to current month)
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');

    // Profit & Loss Statement Data
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN account_type = 'income' THEN total_credit - total_debit ELSE 0 END) as total_income,
            SUM(CASE WHEN account_type = 'expense' THEN total_debit - total_credit ELSE 0 END) as total_expenses
        FROM journal_entries je
        LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        LEFT JOIN chart_of_accounts coa ON jel.account_id = coa.id
        WHERE je.entry_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $pnlData = $stmt->fetch();

    $totalIncome = $pnlData['total_income'] ?? 0;
    $totalExpenses = $pnlData['total_expenses'] ?? 0;
    $netProfit = $totalIncome - $totalExpenses;

    // Balance Sheet Data
    $stmt = $pdo->prepare("
        SELECT
            account_type,
            SUM(CASE WHEN account_type IN ('asset', 'expense') THEN total_debit - total_credit
                     WHEN account_type IN ('liability', 'income', 'equity') THEN total_credit - total_debit
                     ELSE 0 END) as balance
        FROM journal_entries je
        LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        LEFT JOIN chart_of_accounts coa ON jel.account_id = coa.id
        WHERE je.entry_date <= ?
        GROUP BY account_type
    ");
    $stmt->execute([$endDate]);
    $bsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $totalAssets = $bsData['asset'] ?? 0;
    $totalLiabilities = $bsData['liability'] ?? 0;
    $totalEquity = ($bsData['equity'] ?? 0) + $netProfit; // Include current year profit

    // Accounts Receivable Aging
    $stmt = $pdo->query("
        SELECT
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN total_amount - paid_amount ELSE 0 END) as current_30,
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN total_amount - paid_amount ELSE 0 END) as past_31_60,
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 61 AND 90 THEN total_amount - paid_amount ELSE 0 END) as past_61_90,
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 90 THEN total_amount - paid_amount ELSE 0 END) as past_90
        FROM invoices
        WHERE status IN ('sent', 'overdue')
    ");
    $arAging = $stmt->fetch();

    // Sales by Customer
    $stmt = $pdo->query("
        SELECT
            c.name as customer_name,
            COUNT(i.id) as invoice_count,
            SUM(i.total_amount) as total_sales,
            SUM(i.total_amount - i.paid_amount) as outstanding
        FROM customers c
        LEFT JOIN invoices i ON c.id = i.customer_id AND i.status = 'paid'
        WHERE i.invoice_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY c.id, c.name
        ORDER BY total_sales DESC
        LIMIT 10
    ");
    $salesByCustomer = $stmt->fetchAll();

    // Expenses by Category
    $stmt = $pdo->query("
        SELECT
            category,
            COUNT(*) as expense_count,
            SUM(amount) as total_amount
        FROM expenses
        WHERE expense_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY category
        ORDER BY total_amount DESC
    ");
    $expensesByCategory = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!-- Mobile menu button -->
<div class="md:hidden fixed top-4 left-4 z-50">
    <button id="menuToggle" class="p-2 rounded-md bg-white shadow-md text-gray-600">
        <i class="fas fa-bars"></i>
    </button>
</div>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main content -->
<div class="main-content">
    <!-- Top bar -->
    <header class="top-bar">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-gray-800">Reports</h1>
        </div>
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2">
                <input type="date" id="startDate" value="<?php echo $startDate; ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                <span class="text-gray-500">to</span>
                <input type="date" id="endDate" value="<?php echo $endDate; ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                <button onclick="updateDateRange()" class="px-3 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 text-sm">
                    Update
                </button>
            </div>
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-download"></i>
            </button>
        </div>
    </header>

    <!-- Content area -->
    <main class="content-area">
        <div class="max-w-7xl mx-auto">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Report Navigation -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <a href="#pnl" class="border-primary-500 text-primary-600 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Profit & Loss
                        </a>
                        <a href="#balance-sheet" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Balance Sheet
                        </a>
                        <a href="#ar-aging" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            AR Aging
                        </a>
                        <a href="#sales-report" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Sales Report
                        </a>
                        <a href="#expense-report" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Expense Report
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Profit & Loss Statement -->
            <div id="pnl" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-800">Profit & Loss Statement</h2>
                            <span class="text-sm text-gray-500">
                                <?php echo formatDate($startDate) . ' - ' . formatDate($endDate); ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="space-y-4">
                            <!-- Income Section -->
                            <div class="border-b border-gray-200 pb-4">
                                <h3 class="font-semibold text-gray-800 mb-2">Income</h3>
                                <div class="flex justify-between">
                                    <span>Total Income</span>
                                    <span class="font-medium text-green-600"><?php echo formatCurrency($totalIncome); ?></span>
                                </div>
                            </div>

                            <!-- Expenses Section -->
                            <div class="border-b border-gray-200 pb-4">
                                <h3 class="font-semibold text-gray-800 mb-2">Expenses</h3>
                                <div class="flex justify-between">
                                    <span>Total Expenses</span>
                                    <span class="font-medium text-red-600"><?php echo formatCurrency($totalExpenses); ?></span>
                                </div>
                            </div>

                            <!-- Net Profit Section -->
                            <div class="pt-4">
                                <div class="flex justify-between text-lg font-semibold">
                                    <span>Net Profit</span>
                                    <span class="<?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo formatCurrency($netProfit); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Sheet -->
            <div id="balance-sheet" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-800">Balance Sheet</h2>
                            <span class="text-sm text-gray-500">As of <?php echo formatDate($endDate); ?></span>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Assets -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-4">Assets</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Total Assets</span>
                                        <span class="font-medium"><?php echo formatCurrency($totalAssets); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Liabilities & Equity -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-4">Liabilities & Equity</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Total Liabilities</span>
                                        <span class="font-medium text-red-600"><?php echo formatCurrency($totalLiabilities); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Total Equity</span>
                                        <span class="font-medium text-blue-600"><?php echo formatCurrency($totalEquity); ?></span>
                                    </div>
                                    <hr class="my-2">
                                    <div class="flex justify-between font-semibold">
                                        <span>Total Liabilities & Equity</span>
                                        <span><?php echo formatCurrency($totalLiabilities + $totalEquity); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accounts Receivable Aging -->
            <div id="ar-aging" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Accounts Receivable Aging</h2>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">
                                    <?php echo formatCurrency($arAging['current_30']); ?>
                                </div>
                                <div class="text-sm text-gray-600">0-30 Days</div>
                            </div>
                            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">
                                    <?php echo formatCurrency($arAging['past_31_60']); ?>
                                </div>
                                <div class="text-sm text-gray-600">31-60 Days</div>
                            </div>
                            <div class="text-center p-4 bg-orange-50 rounded-lg">
                                <div class="text-2xl font-bold text-orange-600">
                                    <?php echo formatCurrency($arAging['past_61_90']); ?>
                                </div>
                                <div class="text-sm text-gray-600">61-90 Days</div>
                            </div>
                            <div class="text-center p-4 bg-red-50 rounded-lg">
                                <div class="text-2xl font-bold text-red-600">
                                    <?php echo formatCurrency($arAging['past_90']); ?>
                                </div>
                                <div class="text-sm text-gray-600">90+ Days</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales by Customer -->
            <div id="sales-report" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Sales by Customer</h2>
                    </div>

                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoices</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($salesByCustomer as $customer): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $customer['customer_name']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $customer['invoice_count']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($customer['total_sales']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($customer['outstanding']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses by Category -->
            <div id="expense-report" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Expenses by Category</h2>
                    </div>

                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($expensesByCategory as $category): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $category['category']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $category['expense_count']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($category['total_amount']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php
                                                $totalExpenses = array_sum(array_column($expensesByCategory, 'total_amount'));
                                                $percentage = $totalExpenses > 0 ? ($category['total_amount'] / $totalExpenses) * 100 : 0;
                                                echo number_format($percentage, 1) . '%';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function updateDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (startDate && endDate) {
        const url = new URL(window.location);
        url.searchParams.set('start_date', startDate);
        url.searchParams.set('end_date', endDate);
        window.location.href = url.toString();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
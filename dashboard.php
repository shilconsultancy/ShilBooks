<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Get dashboard statistics
try {
    $pdo = getDBConnection();

    // Total Revenue (sum of paid invoices)
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM invoices WHERE status = 'paid'");
    $revenue = $stmt->fetch()['total'] ?? 0;

    // Outstanding Amount (sum of unpaid invoices)
    $stmt = $pdo->query("SELECT SUM(total_amount - paid_amount) as outstanding FROM invoices WHERE status IN ('sent', 'overdue')");
    $outstanding = $stmt->fetch()['outstanding'] ?? 0;

    // Total Expenses
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses WHERE status = 'paid'");
    $expenses = $stmt->fetch()['total'] ?? 0;

    // Profit (Revenue - Expenses)
    $profit = $revenue - $expenses;

    // Recent invoices
    $stmt = $pdo->query("
        SELECT i.*, c.name as customer_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
    $recentInvoices = $stmt->fetchAll();

    // Recent activities
    $activities = [];

    // Get recent invoice activities
    $stmt = $pdo->query("
        SELECT 'invoice_paid' as type, invoice_number as reference, total_amount as amount,
               CONCAT('Invoice ', invoice_number, ' was paid') as description,
               created_at as activity_date
        FROM invoices
        WHERE status = 'paid'
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $activities = array_merge($activities, $stmt->fetchAll());

    // Get recent expense activities
    $stmt = $pdo->query("
        SELECT 'expense_added' as type, expense_number as reference, amount,
               CONCAT('Expense ', expense_number, ' was recorded') as description,
               created_at as activity_date
        FROM expenses
        ORDER BY created_at DESC
        LIMIT 2
    ");
    $activities = array_merge($activities, $stmt->fetchAll());

    // Sort activities by date
    usort($activities, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });

    $activities = array_slice($activities, 0, 5);

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

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<!-- Main content -->
<div class="main-content">
    <!-- Top bar -->
    <header class="top-bar">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-bell"></i>
            </button>
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-full bg-primary-500 flex items-center justify-center">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
                <span class="text-sm font-medium text-gray-700"><?php echo getCurrentUser(); ?></span>
            </div>
        </div>
    </header>

    <!-- Content area -->
    <main class="content-area">
        <div class="max-w-7xl mx-auto">
            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Welcome card -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Welcome back, <?php echo getCurrentUser(); ?>!</h2>
                        <p class="text-gray-500 mt-2">Here's what's happening with your business today.</p>
                    </div>
                    <button class="mt-4 md:mt-0 px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>New Invoice</span>
                    </button>
                </div>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                            <div class="amount"><?php echo formatCurrency($revenue); ?></div>
                            <div class="change text-green-500">
                                <i class="fas fa-arrow-up"></i>
                                <span>12% from last month</span>
                            </div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-dollar-sign text-green-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Outstanding</h3>
                            <div class="amount"><?php echo formatCurrency($outstanding); ?></div>
                            <div class="change text-red-500">
                                <i class="fas fa-arrow-down"></i>
                                <span>5% from last month</span>
                            </div>
                        </div>
                        <div class="stats-icon bg-red-100">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Expenses</h3>
                            <div class="amount"><?php echo formatCurrency($expenses); ?></div>
                            <div class="change text-yellow-500">
                                <i class="fas fa-arrow-up"></i>
                                <span>8% from last month</span>
                            </div>
                        </div>
                        <div class="stats-icon bg-yellow-100">
                            <i class="fas fa-credit-card text-yellow-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Profit</h3>
                            <div class="amount"><?php echo formatCurrency($profit); ?></div>
                            <div class="change text-green-500">
                                <i class="fas fa-arrow-up"></i>
                                <span>15% from last month</span>
                            </div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-chart-line text-blue-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent activity and invoices -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent activity -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                        <a href="#" class="text-sm text-primary-500 hover:text-primary-600">View All</a>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($activities as $activity): ?>
                            <div class="flex items-start">
                                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-file-text text-purple-500"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800"><?php echo $activity['description']; ?></p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo formatDate($activity['activity_date']); ?>
                                        <?php if ($activity['amount']): ?>
                                            • <?php echo formatCurrency($activity['amount']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent invoices -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Invoices</h3>
                        <a href="sales/invoices.php" class="text-sm text-primary-500 hover:text-primary-600">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($recentInvoices as $invoice): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">
                                            <?php echo $invoice['invoice_number']; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $invoice['customer_name'] ?: 'N/A'; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo formatCurrency($invoice['total_amount']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                                <?php
                                                switch($invoice['status']) {
                                                    case 'paid':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'sent':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'overdue':
                                                        echo 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
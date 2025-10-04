<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$pageTitle = 'Reports';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6">
        <h1 class="text-xl font-semibold text-macgray-800">Reports</h1>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <a href="<?php echo BASE_PATH; ?>reports/profit-and-loss.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <i data-feather="trending-up" class="text-green-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">Profit & Loss</h2>
                            <p class="text-sm text-macgray-500">View your income, expenses, and net profit.</p>
                        </div>
                    </div>
                </a>

                <a href="<?php echo BASE_PATH; ?>reports/balance-sheet.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                     <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <i data-feather="briefcase" class="text-blue-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">Balance Sheet</h2>
                            <p class="text-sm text-macgray-500">View your assets, liabilities, and equity.</p>
                        </div>
                    </div>
                </a>

                 <a href="<?php echo BASE_PATH; ?>reports/ar-aging.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                     <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                            <i data-feather="clock" class="text-yellow-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">A/R Aging</h2>
                            <p class="text-sm text-macgray-500">See who owes you money and how overdue it is.</p>
                        </div>
                    </div>
                </a>
                
                <a href="<?php echo BASE_PATH; ?>reports/cash-flows.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                     <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <i data-feather="dollar-sign" class="text-green-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">Statement of Cash Flows</h2>
                            <p class="text-sm text-macgray-500">Track your cash inflows and outflows.</p>
                        </div>
                    </div>
                </a>

                <a href="<?php echo BASE_PATH; ?>reports/sales-by-customer.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                     <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                            <i data-feather="users" class="text-yellow-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">Sales by Customer</h2>
                            <p class="text-sm text-macgray-500">Analyze sales performance by customer.</p>
                        </div>
                    </div>
                </a>
                
                <a href="<?php echo BASE_PATH; ?>reports/sales-by-item.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                     <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                            <i data-feather="package" class="text-indigo-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">Sales by Item/Service</h2>
                            <p class="text-sm text-macgray-500">Track sales performance by product or service.</p>
                        </div>
                    </div>
                </a>

                <a href="<?php echo BASE_PATH; ?>reports/ap-aging.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                     <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                            <i data-feather="alert-circle" class="text-red-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">A/P Aging</h2>
                            <p class="text-sm text-macgray-500">See what you owe and how overdue it is.</p>
                        </div>
                    </div>
                </a>

                <a href="<?php echo BASE_PATH; ?>reports/expenses-by-vendor.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow">
                     <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <i data-feather="truck" class="text-purple-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-macgray-800">Expenses by Vendor</h2>
                            <p class="text-sm text-macgray-500">Analyze expenses by vendor or category.</p>
                        </div>
                    </div>
                </a>

            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
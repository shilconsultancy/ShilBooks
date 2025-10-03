<?php
// Get the current request URI to determine the active module/page
$currentUri = $_SERVER['REQUEST_URI'];

// --- Define active states for different sections ---
$is_dashboard_page = (strpos($currentUri, 'dashboard.php') !== false);
$is_items_section = (strpos($currentUri, '/items/') !== false);
$is_sales_section = (strpos($currentUri, '/sales/') !== false);
$is_customers_page = (strpos($currentUri, '/sales/customers/') !== false);
$is_quotes_page = (strpos($currentUri, '/sales/quotes/') !== false);
$is_invoices_page = (strpos($currentUri, '/sales/invoices/') !== false);
$is_payments_page = (strpos($currentUri, '/sales/payments/') !== false);
$is_credit_notes_page = (strpos($currentUri, '/sales/credit-notes/') !== false);
$is_purchases_section = (strpos($currentUri, '/purchases/') !== false);
$is_vendors_page = (strpos($currentUri, '/purchases/vendors/') !== false);
$is_categories_page = (strpos($currentUri, '/purchases/categories/') !== false);
$is_expenses_page = (strpos($currentUri, '/purchases/expenses/') !== false);
$is_recurring_expenses_page = (strpos($currentUri, '/purchases/recurring/') !== false);
$is_banking_page = (strpos($currentUri, '/banking/') !== false);
$is_accountant_section = (strpos($currentUri, '/accountant/') !== false);
$is_coa_page = (strpos($currentUri, '/accountant/chart-of-accounts/') !== false);
$is_journals_page = (strpos($currentUri, '/accountant/manual-journals/') !== false);
$is_reports_page = (strpos($currentUri, '/reports/') !== false);
$is_documents_page = (strpos($currentUri, '/documents/') !== false);
$is_settings_page = (strpos($currentUri, '/settings/') !== false);
?>
<div id="sidebar" class="sidebar w-64 bg-macgray-800 text-white flex-shrink-0 flex flex-col h-full fixed md:relative z-40">
    <div class="p-4 border-b border-macgray-700 flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <div class="w-8 h-8 rounded-md bg-macblue-500 flex items-center justify-center">
                <i data-feather="dollar-sign" class="text-white"></i>
            </div>
            <span class="font-semibold text-lg">ShilBook</span>
        </div>
        <div class="md:hidden">
            <button id="closeMenu" class="p-1 rounded-md hover:bg-macgray-700">
                <i data-feather="x"></i>
            </button>
        </div>
    </div>

    <div class="p-4 border-b border-macgray-700 flex items-center space-x-3">
        <?php if (!empty($_SESSION['profile_picture'])): ?>
            <img src="<?php echo BASE_PATH . 'uploads/company/profile/' . $_SESSION['profile_picture'] . '?t=' . time(); ?>" alt="Profile Picture" class="w-10 h-10 rounded-full object-cover">
        <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-macblue-500 flex items-center justify-center">
                <i data-feather="user" class="text-white"></i>
            </div>
        <?php endif; ?>
        <div>
            <div class="font-medium"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></div>
            </div>
    </div>

    <div class="flex-1 overflow-y-auto py-2">
        <nav>
            <ul class="space-y-1 px-2">
                <li><a href="<?php echo BASE_PATH; ?>dashboard.php" class="flex items-center px-3 py-2 rounded-md <?php echo $is_dashboard_page ? 'bg-macblue-700 text-white' : 'hover:bg-macgray-700'; ?>"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="home" class="w-4 h-4"></i></span><span>Home</span></a></li>

                <li class="mt-4">
                    <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer"><div class="flex items-center"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="box" class="w-4 h-4"></i></span><span>Items</span></div><i data-feather="chevron-down" class="w-4 h-4 transition-transform transform <?php echo $is_items_section ? 'rotate-180' : ''; ?>"></i></div>
                    <ul class="pl-4 mt-1 space-y-1 <?php echo !$is_items_section ? 'hidden' : ''; ?>"><li><a href="<?php echo BASE_PATH; ?>items/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_items_section ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">All Items</a></li></ul>
                </li>

                <li class="mt-4">
                    <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer"><div class="flex items-center"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="shopping-cart" class="w-4 h-4"></i></span><span>Sales</span></div><i data-feather="chevron-down" class="w-4 h-4 transition-transform transform <?php echo $is_sales_section ? 'rotate-180' : ''; ?>"></i></div>
                    <ul class="pl-4 mt-1 space-y-1 <?php echo !$is_sales_section ? 'hidden' : ''; ?>">
                        <li><a href="<?php echo BASE_PATH; ?>sales/customers/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_customers_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Customers</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>sales/quotes/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_quotes_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Quotes</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>sales/invoices/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_invoices_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Invoices</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>sales/payments/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_payments_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Payments Received</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>sales/credit-notes/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_credit_notes_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Credit Notes</a></li>
                    </ul>
                </li>

                <li class="mt-4">
                    <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer"><div class="flex items-center"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="credit-card" class="w-4 h-4"></i></span><span>Purchases</span></div><i data-feather="chevron-down" class="w-4 h-4 transition-transform transform <?php echo $is_purchases_section ? 'rotate-180' : ''; ?>"></i></div>
                    <ul class="pl-4 mt-1 space-y-1 <?php echo !$is_purchases_section ? 'hidden' : ''; ?>">
                        <li><a href="<?php echo BASE_PATH; ?>purchases/vendors/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_vendors_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Vendors</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>purchases/categories/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_categories_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Expense Categories</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>purchases/expenses/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_expenses_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Expenses</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>purchases/recurring/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_recurring_expenses_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Recurring Expenses</a></li>
                    </ul>
                </li>

                <li class="mt-4"><a href="<?php echo BASE_PATH; ?>banking/" class="flex items-center px-3 py-2 rounded-md <?php echo $is_banking_page ? 'bg-macblue-700 text-white' : 'hover:bg-macgray-700'; ?>"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="briefcase" class="w-4 h-4"></i></span><span>Banking</span></a></li>

                <li class="mt-4">
                    <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer"><div class="flex items-center"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="book" class="w-4 h-4"></i></span><span>Accountant</span></div><i data-feather="chevron-down" class="w-4 h-4 transition-transform transform <?php echo $is_accountant_section ? 'rotate-180' : ''; ?>"></i></div>
                    <ul class="pl-4 mt-1 space-y-1 <?php echo !$is_accountant_section ? 'hidden' : ''; ?>">
                        <li><a href="<?php echo BASE_PATH; ?>accountant/manual-journals/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_journals_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Manual Journals</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>accountant/chart-of-accounts/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm <?php echo $is_coa_page ? 'text-white bg-macgray-700' : 'text-macgray-300 hover:text-white'; ?>">Chart of Accounts</a></li>
                    </ul>
                </li>
                
                <li class="mt-4"><a href="<?php echo BASE_PATH; ?>reports/" class="flex items-center px-3 py-2 rounded-md <?php echo $is_reports_page ? 'bg-macblue-700 text-white' : 'hover:bg-macgray-700'; ?>"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="bar-chart-2" class="w-4 h-4"></i></span><span>Reports</span></a></li>
                
                <li class="mt-4"><a href="<?php echo BASE_PATH; ?>documents/" class="flex items-center px-3 py-2 rounded-md <?php echo $is_documents_page ? 'bg-macblue-700 text-white' : 'hover:bg-macgray-700'; ?>"><span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3"><i data-feather="file-text" class="w-4 h-4"></i></span><span>Documents</span></a></li>
            </ul>
        </nav>
    </div>

    <div class="p-4 border-t border-macgray-700 space-y-2">
        <a href="<?php echo BASE_PATH; ?>settings/" class="flex items-center space-x-3 text-macgray-400 hover:text-white">
            <div class="w-8 h-8 rounded-full <?php echo $is_settings_page ? 'bg-macblue-700' : 'bg-macgray-700'; ?> flex items-center justify-center"><i data-feather="settings" class="w-4 h-4"></i></div>
            <div class="text-sm">Settings</div>
        </a>
        <a href="<?php echo BASE_PATH; ?>dashboard.php?action=logout" class="flex items-center space-x-3 text-macgray-400 hover:text-white">
            <div class="w-8 h-8 rounded-full bg-macgray-700 flex items-center justify-center"><i data-feather="log-out" class="w-4 h-4"></i></div>
            <div class="text-sm">Logout</div>
        </a>
    </div>
</div>
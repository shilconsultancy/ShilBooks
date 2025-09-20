<?php
require_once __DIR__ . '/../config.php';
?>
<!-- Sidebar -->
<div id="sidebar" class="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fas fa-dollar-sign text-white text-sm"></i>
            </div>
            <span class="font-semibold text-lg"><?php echo APP_NAME; ?></span>
        </div>
        <div class="md:hidden">
            <button id="closeMenu" class="p-1 rounded-md hover:bg-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- User Profile -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <i class="fas fa-user text-white text-sm"></i>
        </div>
        <div>
            <div class="font-medium"><?php echo getCurrentUser() ?: 'Guest User'; ?></div>
            <div class="text-xs text-gray-400">Premium Account</div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <nav>
            <ul class="space-y-1 px-2">
                <!-- Home -->
                <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>dashboard.php" class="flex items-center px-3 py-2 rounded-md text-white">
                        <span class="sidebar-icon">
                            <i class="fas fa-home w-4 h-4"></i>
                        </span>
                        <span>Home</span>
                    </a>
                </li>

                <!-- Items -->
                <li class="mt-4">
                    <div class="sidebar-item-content px-3 py-2 flex items-center justify-between rounded-md hover:bg-gray-700 cursor-pointer">
                        <div class="flex items-center">
                            <span class="sidebar-icon">
                                <i class="fas fa-box w-4 h-4"></i>
                            </span>
                            <span>Items</span>
                        </div>
                        <i class="fas fa-chevron-down w-4 h-4 transition-transform transform chevron-icon"></i>
                    </div>
                    <ul class="sidebar-submenu pl-4 mt-1 space-y-1 hidden">
                         <li>
                             <a href="<?php echo BASE_URL; ?>items/" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Items</a>
                         </li>
                     </ul>
                </li>

                <!-- Sales -->
                <li class="mt-4">
                    <div class="sidebar-item-content px-3 py-2 flex items-center justify-between rounded-md hover:bg-gray-700 cursor-pointer">
                        <div class="flex items-center">
                            <span class="sidebar-icon">
                                <i class="fas fa-shopping-cart w-4 h-4"></i>
                            </span>
                            <span>Sales</span>
                        </div>
                        <i class="fas fa-chevron-down w-4 h-4 transition-transform transform chevron-icon rotate-0"></i>
                    </div>
                    <ul class="sidebar-submenu pl-4 mt-1 space-y-1 hidden">
                         <li><a href="<?php echo BASE_URL; ?>sales/customers.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Customers</a></li>
                         <li><a href="<?php echo BASE_URL; ?>sales/quotes.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Quotes</a></li>
                         <li><a href="<?php echo BASE_URL; ?>sales/invoices.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Invoices</a></li>
                         <li><a href="<?php echo BASE_URL; ?>sales/receipts.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Sales Receipts</a></li>
                         <li><a href="<?php echo BASE_URL; ?>sales/recurring.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Recurring Invoices</a></li>
                         <li><a href="<?php echo BASE_URL; ?>sales/payments.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Payments Received</a></li>
                         <li><a href="<?php echo BASE_URL; ?>sales/credit-notes.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Credit Notes</a></li>
                     </ul>
                </li>

                <!-- Purchases -->
                <li class="mt-4">
                    <div class="sidebar-item-content px-3 py-2 flex items-center justify-between rounded-md hover:bg-gray-700 cursor-pointer">
                        <div class="flex items-center">
                            <span class="sidebar-icon">
                                <i class="fas fa-credit-card w-4 h-4"></i>
                            </span>
                            <span>Purchases</span>
                        </div>
                        <i class="fas fa-chevron-down w-4 h-4 transition-transform transform chevron-icon"></i>
                    </div>
                    <ul class="sidebar-submenu pl-4 mt-1 space-y-1 hidden">
                         <li><a href="<?php echo BASE_URL; ?>purchases/vendors.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Vendors</a></li>
                         <li><a href="<?php echo BASE_URL; ?>purchases/expenses.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Expenses</a></li>
                     </ul>
                </li>

                <!-- Banking -->
                <li class="sidebar-item mt-4 <?php echo strpos($_SERVER['PHP_SELF'], 'banking') !== false ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>banking/" class="flex items-center px-3 py-2 rounded-md hover:bg-gray-700">
                        <span class="sidebar-icon">
                            <i class="fas fa-university w-4 h-4"></i>
                        </span>
                        <span>Banking</span>
                    </a>
                </li>

                <!-- Employees -->
                <li class="sidebar-item mt-4 <?php echo strpos($_SERVER['PHP_SELF'], 'employees') !== false ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>employees/" class="flex items-center px-3 py-2 rounded-md hover:bg-gray-700">
                        <span class="sidebar-icon">
                            <i class="fas fa-users w-4 h-4"></i>
                        </span>
                        <span>Employees</span>
                    </a>
                </li>

                <!-- Accountant -->
                <li class="mt-4">
                    <div class="sidebar-item-content px-3 py-2 flex items-center justify-between rounded-md hover:bg-gray-700 cursor-pointer">
                        <div class="flex items-center">
                            <span class="sidebar-icon">
                                <i class="fas fa-book w-4 h-4"></i>
                            </span>
                            <span>Accountant</span>
                        </div>
                        <i class="fas fa-chevron-down w-4 h-4 transition-transform transform chevron-icon"></i>
                    </div>
                    <ul class="sidebar-submenu pl-4 mt-1 space-y-1 hidden">
                         <li><a href="<?php echo BASE_URL; ?>accountant/journals.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Manual Journals</a></li>
                         <li><a href="<?php echo BASE_URL; ?>accountant/chart.php" class="sidebar-subitem block px-3 py-2 rounded-md text-sm">Chart of Accounts</a></li>
                     </ul>
                </li>

                <!-- Reports -->
                <li class="sidebar-item mt-4 <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>reports/" class="flex items-center px-3 py-2 rounded-md hover:bg-gray-700">
                        <span class="sidebar-icon">
                            <i class="fas fa-chart-bar w-4 h-4"></i>
                        </span>
                        <span>Reports</span>
                    </a>
                </li>

                <!-- Documents -->
                <li class="sidebar-item mt-4 <?php echo strpos($_SERVER['PHP_SELF'], 'documents') !== false ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>documents/" class="flex items-center px-3 py-2 rounded-md hover:bg-gray-700">
                        <span class="sidebar-icon">
                            <i class="fas fa-file-alt w-4 h-4"></i>
                        </span>
                        <span>Documents</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Bottom section -->
    <div class="sidebar-footer">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center">
                <i class="fas fa-cog text-gray-400 w-4 h-4"></i>
            </div>
            <div class="text-sm text-gray-400">Settings</div>
        </div>
    </div>
</div>
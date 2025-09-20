<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Accounting App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        macblue: {
                            50: '#f0f6ff',
                            100: '#e0edff',
                            200: '#c9e0ff',
                            300: '#a8ceff',
                            400: '#86b2ff',
                            500: '#5e8eff',
                            600: '#3d6bff',
                            700: '#2d5af1',
                            800: '#1f49d4',
                            900: '#1d3fab',
                        },
                        macgray: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .sidebar-item:hover .sidebar-icon {
            transform: scale(1.1);
        }
        .sidebar-subitem {
            transition: all 0.2s ease;
        }
        .sidebar-subitem:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .content-area {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .content-area::-webkit-scrollbar {
            width: 6px;
        }
        .content-area::-webkit-scrollbar-track {
            background: transparent;
        }
        .content-area::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar-open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="h-full bg-macgray-50 flex overflow-hidden">
    <!-- Mobile menu button -->
    <div class="md:hidden fixed top-4 left-4 z-50">
        <button id="menuToggle" class="p-2 rounded-md bg-white shadow-md text-macgray-600">
            <i data-feather="menu"></i>
        </button>
    </div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar w-64 bg-macgray-800 text-white flex-shrink-0 flex flex-col h-full fixed md:relative z-40">
        <!-- Logo -->
        <div class="p-4 border-b border-macgray-700 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-md bg-macblue-500 flex items-center justify-center">
                    <i data-feather="dollar-sign" class="text-white"></i>
                </div>
                <span class="font-semibold text-lg">Financia</span>
            </div>
            <div class="md:hidden">
                <button id="closeMenu" class="p-1 rounded-md hover:bg-macgray-700">
                    <i data-feather="x"></i>
                </button>
            </div>
        </div>

        <!-- User Profile -->
        <div class="p-4 border-b border-macgray-700 flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-macblue-500 flex items-center justify-center">
                <i data-feather="user" class="text-white"></i>
            </div>
            <div>
                <div class="font-medium">John Doe</div>
                <div class="text-xs text-macgray-400">Premium Account</div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto py-2">
            <nav>
                <ul class="space-y-1 px-2">
                    <!-- Home -->
                    <li>
                        <a href="#" class="flex items-center px-3 py-2 rounded-md bg-macblue-700 text-white">
                            <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                <i data-feather="home" class="w-4 h-4"></i>
                            </span>
                            <span>Home</span>
                        </a>
                    </li>

                    <!-- Items -->
                    <li class="mt-4">
                        <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer">
                            <div class="flex items-center">
                                <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                    <i data-feather="box" class="w-4 h-4"></i>
                                </span>
                                <span>Items</span>
                            </div>
                            <i data-feather="chevron-down" class="w-4 h-4 transition-transform transform"></i>
                        </div>
                        <ul class="pl-4 mt-1 space-y-1 hidden">
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Items</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Sales -->
                    <li class="mt-4">
                        <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer">
                            <div class="flex items-center">
                                <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                    <i data-feather="shopping-cart" class="w-4 h-4"></i>
                                </span>
                                <span>Sales</span>
                            </div>
                            <i data-feather="chevron-down" class="w-4 h-4 transition-transform transform"></i>
                        </div>
                        <ul class="pl-4 mt-1 space-y-1 hidden">
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Customers</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Quotes</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Invoices</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Sales Receipts</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Recurring Invoices</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Payments Received</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Credit Notes</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Purchases -->
                    <li class="mt-4">
                        <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer">
                            <div class="flex items-center">
                                <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                    <i data-feather="credit-card" class="w-4 h-4"></i>
                                </span>
                                <span>Purchases</span>
                            </div>
                            <i data-feather="chevron-down" class="w-4 h-4 transition-transform transform"></i>
                        </div>
                        <ul class="pl-4 mt-1 space-y-1 hidden">
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Vendors</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Expenses</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Banking -->
                    <li class="mt-4">
                        <a href="#" class="flex items-center px-3 py-2 rounded-md hover:bg-macgray-700">
                            <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                <i data-feather="bank" class="w-4 h-4"></i>
                            </span>
                            <span>Banking</span>
                        </a>
                    </li>

                    <!-- Accountant -->
                    <li class="mt-4">
                        <div class="px-3 py-2 flex items-center justify-between rounded-md hover:bg-macgray-700 cursor-pointer">
                            <div class="flex items-center">
                                <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                    <i data-feather="book" class="w-4 h-4"></i>
                                </span>
                                <span>Accountant</span>
                            </div>
                            <i data-feather="chevron-down" class="w-4 h-4 transition-transform transform"></i>
                        </div>
                        <ul class="pl-4 mt-1 space-y-1 hidden">
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Manual Journals</a>
                            </li>
                            <li>
                                <a href="#" class="sidebar-subitem block px-3 py-2 rounded-md text-sm text-macgray-300 hover:text-white">Chart of Accounts</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Reports -->
                    <li class="mt-4">
                        <a href="#" class="flex items-center px-3 py-2 rounded-md hover:bg-macgray-700">
                            <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                <i data-feather="bar-chart-2" class="w-4 h-4"></i>
                            </span>
                            <span>Reports</span>
                        </a>
                    </li>

                    <!-- Documents -->
                    <li class="mt-4">
                        <a href="#" class="flex items-center px-3 py-2 rounded-md hover:bg-macgray-700">
                            <span class="sidebar-icon w-6 h-6 flex items-center justify-center mr-3 transition-transform">
                                <i data-feather="file-text" class="w-4 h-4"></i>
                            </span>
                            <span>Documents</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Bottom section -->
        <div class="p-4 border-t border-macgray-700">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-full bg-macgray-700 flex items-center justify-center">
                    <i data-feather="settings" class="text-macgray-400 w-4 h-4"></i>
                </div>
                <div class="text-sm text-macgray-400">Settings</div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top bar -->
        <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-semibold text-macgray-800">Dashboard</h1>
            </div>
            <div class="flex items-center space-x-4">
                <button class="p-2 rounded-full hover:bg-macgray-100 text-macgray-600">
                    <i data-feather="bell"></i>
                </button>
                <button class="p-2 rounded-full hover:bg-macgray-100 text-macgray-600">
                    <i data-feather="search"></i>
                </button>
            </div>
        </header>

        <!-- Content area -->
        <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
            <div class="max-w-7xl mx-auto">
                <!-- Welcome card -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-macgray-200">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-2xl font-semibold text-macgray-800">Welcome back, John</h2>
                            <p class="text-macgray-500 mt-2">Here's what's happening with your business today.</p>
                        </div>
                        <button class="mt-4 md:mt-0 px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
                            <i data-feather="plus" class="w-4 h-4"></i>
                            <span>New Invoice</span>
                        </button>
                    </div>
                </div>

                <!-- Stats cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-macgray-500">Total Revenue</p>
                                <p class="text-2xl font-semibold text-macgray-800 mt-1">$24,780</p>
                                <p class="text-xs text-green-500 mt-1 flex items-center">
                                    <i data-feather="trending-up" class="w-3 h-3 mr-1"></i>
                                    <span>12% from last month</span>
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                                <i data-feather="dollar-sign" class="text-green-500"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-macgray-500">Outstanding</p>
                                <p class="text-2xl font-semibold text-macgray-800 mt-1">$8,450</p>
                                <p class="text-xs text-red-500 mt-1 flex items-center">
                                    <i data-feather="trending-down" class="w-3 h-3 mr-1"></i>
                                    <span>5% from last month</span>
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                                <i data-feather="alert-circle" class="text-red-500"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-macgray-500">Expenses</p>
                                <p class="text-2xl font-semibold text-macgray-800 mt-1">$5,230</p>
                                <p class="text-xs text-yellow-500 mt-1 flex items-center">
                                    <i data-feather="trending-up" class="w-3 h-3 mr-1"></i>
                                    <span>8% from last month</span>
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                                <i data-feather="credit-card" class="text-yellow-500"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-macgray-500">Profit</p>
                                <p class="text-2xl font-semibold text-macgray-800 mt-1">$19,550</p>
                                <p class="text-xs text-green-500 mt-1 flex items-center">
                                    <i data-feather="trending-up" class="w-3 h-3 mr-1"></i>
                                    <span>15% from last month</span>
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                <i data-feather="bar-chart-2" class="text-blue-500"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent activity and invoices -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent activity -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-macgray-800">Recent Activity</h3>
                            <a href="#" class="text-sm text-macblue-500 hover:text-macblue-600">View All</a>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3 flex-shrink-0">
                                    <i data-feather="file-text" class="text-purple-500 w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-macgray-800">Invoice #INV-2023-0012 was paid</p>
                                    <p class="text-xs text-macgray-500 mt-1">Acme Inc. • 2 hours ago</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3 flex-shrink-0">
                                    <i data-feather="user-plus" class="text-green-500 w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-macgray-800">New customer added</p>
                                    <p class="text-xs text-macgray-500 mt-1">Stark Industries • 5 hours ago</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                                    <i data-feather="dollar-sign" class="text-blue-500 w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-macgray-800">Payment received</p>
                                    <p class="text-xs text-macgray-500 mt-1">Wayne Enterprises • Yesterday</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3 flex-shrink-0">
                                    <i data-feather="alert-circle" class="text-yellow-500 w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-macgray-800">Invoice overdue</p>
                                    <p class="text-xs text-macgray-500 mt-1">Oscorp Industries • 2 days ago</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent invoices -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-macgray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-macgray-800">Recent Invoices</h3>
                            <a href="#" class="text-sm text-macblue-500 hover:text-macblue-600">View All</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-macgray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Invoice</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Client</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-macgray-200">
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-macgray-800">#INV-2023-0015</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">Acme Inc.</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">$2,500.00</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Paid</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-macgray-800">#INV-2023-0014</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">Stark Industries</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">$5,750.00</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-macgray-800">#INV-2023-0013</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">Wayne Enterprises</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">$3,200.00</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Overdue</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-macgray-800">#INV-2023-0012</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">Oscorp Industries</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-macgray-500">$1,800.00</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Paid</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize feather icons
        feather.replace();
        
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');

        menuToggle.addEventListener('click', () => {
            sidebar.classList.add('sidebar-open');
        });

        closeMenu.addEventListener('click', () => {
            sidebar.classList.remove('sidebar-open');
        });

        // Toggle submenus
        document.querySelectorAll('nav li > div').forEach(item => {
            item.addEventListener('click', () => {
                const submenu = item.nextElementSibling;
                const chevron = item.querySelector('i[data-feather="chevron-down"]');
                
                if (submenu.classList.contains('hidden')) {
                    submenu.classList.remove('hidden');
                    chevron.classList.add('rotate-180');
                } else {
                    submenu.classList.add('hidden');
                    chevron.classList.remove('rotate-180');
                }
            });
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                sidebar.classList.remove('sidebar-open');
            }
        });
    </script>
</body>
</html>

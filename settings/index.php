<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$pageTitle = 'Settings';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6">
        <h1 class="text-xl font-semibold text-macgray-800">Settings</h1>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="<?php echo BASE_PATH; ?>settings/company.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-full bg-macblue-100 flex items-center justify-center flex-shrink-0">
                        <i data-feather="briefcase" class="text-macblue-500"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-macgray-800">Company Settings</h2>
                        <p class="text-sm text-macgray-500 mt-1">Update your company name, address, logo, and regional preferences.</p>
                    </div>
                </a>

                <a href="<?php echo BASE_PATH; ?>settings/profile.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                        <i data-feather="user" class="text-green-500"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-macgray-800">Profile Settings</h2>
                        <p class="text-sm text-macgray-500 mt-1">Manage your personal information, password, and profile picture.</p>
                    </div>
                </a>
                
                <a href="<?php echo BASE_PATH; ?>settings/financial.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0">
                        <i data-feather="dollar-sign" class="text-yellow-500"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-macgray-800">Financial Settings</h2>
                        <p class="text-sm text-macgray-500 mt-1">Configure currency, taxes, fiscal year, and billing defaults.</p>
                    </div>
                </a>

                <a href="<?php echo BASE_PATH; ?>settings/users.php" class="bg-white rounded-xl shadow-sm border border-macgray-200 p-6 hover:shadow-md transition-shadow flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                        <i data-feather="users" class="text-purple-500"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-macgray-800">User & Role Management</h2>
                        <p class="text-sm text-macgray-500 mt-1">Add or remove users and manage their permissions.</p>
                    </div>
                </a>

            </div>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
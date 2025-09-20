<?php
require_once 'config.php';
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Settings';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_company':
                    $companyName = sanitizeInput($_POST['company_name']);
                    $address = sanitizeInput($_POST['address']);
                    $phone = sanitizeInput($_POST['phone']);
                    $email = sanitizeInput($_POST['email']);
                    $website = sanitizeInput($_POST['website']);
                    $currency = sanitizeInput($_POST['currency']);

                    $stmt = $pdo->prepare("
                        UPDATE company_settings SET company_name = ?, address = ?, phone = ?, email = ?, website = ?, currency = ? WHERE id = 1
                    ");
                    $stmt->execute([$companyName, $address, $phone, $email, $website, $currency]);
                    $success = 'Company settings updated successfully!';
                    break;

                case 'update_profile':
                    $fullName = sanitizeInput($_POST['full_name']);
                    $email = sanitizeInput($_POST['email']);
                    $phone = sanitizeInput($_POST['phone']);

                    // In a real application, you would update the user table
                    $success = 'Profile settings updated successfully!';
                    break;

                case 'update_financial':
                    $fiscalYearStart = $_POST['fiscal_year_start'];
                    $fiscalYearEnd = $_POST['fiscal_year_end'];
                    $defaultTaxRate = floatval($_POST['default_tax_rate']);

                    $stmt = $pdo->prepare("
                        UPDATE company_settings SET fiscal_year_start = ?, fiscal_year_end = ? WHERE id = 1
                    ");
                    $stmt->execute([$fiscalYearStart, $fiscalYearEnd]);
                    $success = 'Financial settings updated successfully!';
                    break;

                case 'change_password':
                    $currentPassword = $_POST['current_password'];
                    $newPassword = $_POST['new_password'];
                    $confirmPassword = $_POST['confirm_password'];

                    if ($newPassword !== $confirmPassword) {
                        $error = 'New passwords do not match.';
                    } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
                    } else {
                        // In a real application, you would verify current password and update
                        $success = 'Password changed successfully!';
                    }
                    break;
            }
        }
    }

    // Get current settings
    $stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
    $companySettings = $stmt->fetch();

    if (!$companySettings) {
        // Insert default settings if none exist
        $stmt = $pdo->prepare("INSERT INTO company_settings (company_name, currency) VALUES (?, ?)");
        $stmt->execute(['Your Company Name', 'USD']);
        $stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
        $companySettings = $stmt->fetch();
    }

    // Get currencies for dropdown
    $currencies = [
        'USD' => 'US Dollar ($)',
        'EUR' => 'Euro (€)',
        'GBP' => 'British Pound (£)',
        'JPY' => 'Japanese Yen (¥)',
        'BDT' => 'Bangladeshi Taka (৳)',
        'INR' => 'Indian Rupee (₹)',
        'CAD' => 'Canadian Dollar (C$)',
        'AUD' => 'Australian Dollar (A$)'
    ];

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

<?php require_once 'includes/sidebar.php'; ?>

<!-- Main content -->
<div class="main-content">
    <!-- Top bar -->
    <header class="top-bar">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-gray-800">Settings</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-save"></i>
            </button>
        </div>
    </header>

    <!-- Content area -->
    <main class="content-area">
        <div class="max-w-4xl mx-auto">
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Settings Navigation -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <a href="#company" class="border-primary-500 text-primary-600 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Company
                        </a>
                        <a href="#profile" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Profile
                        </a>
                        <a href="#financial" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Financial
                        </a>
                        <a href="#security" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Security
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Company Settings -->
            <div id="company" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Company Settings</h2>
                    </div>

                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_company">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" name="company_name" class="form-input" value="<?php echo $companySettings['company_name']; ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-input" value="<?php echo $companySettings['email']; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-input" value="<?php echo $companySettings['phone']; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Website</label>
                                    <input type="url" name="website" class="form-input" value="<?php echo $companySettings['website']; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-select">
                                        <?php foreach ($currencies as $code => $name): ?>
                                            <option value="<?php echo $code; ?>" <?php echo $companySettings['currency'] === $code ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group md:col-span-2">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" rows="3" class="form-input"><?php echo $companySettings['address']; ?></textarea>
                                </div>
                            </div>

                            <div class="flex justify-end mt-6">
                                <button type="submit" class="btn btn-primary">
                                    Save Company Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Profile Settings -->
            <div id="profile" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Profile Settings</h2>
                    </div>

                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-input" value="<?php echo getCurrentUser(); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-input" value="admin@shilbooks.com" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-input" value="">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-input" value="admin" readonly>
                                </div>
                            </div>

                            <div class="flex justify-end mt-6">
                                <button type="submit" class="btn btn-primary">
                                    Save Profile Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Financial Settings -->
            <div id="financial" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Financial Settings</h2>
                    </div>

                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_financial">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label class="form-label">Fiscal Year Start</label>
                                    <input type="date" name="fiscal_year_start" class="form-input" value="<?php echo $companySettings['fiscal_year_start'] ?? '2024-01-01'; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Fiscal Year End</label>
                                    <input type="date" name="fiscal_year_end" class="form-input" value="<?php echo $companySettings['fiscal_year_end'] ?? '2024-12-31'; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Default Tax Rate (%)</label>
                                    <input type="number" step="0.01" name="default_tax_rate" class="form-input" value="0.00" min="0" max="100">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Invoice Number Format</label>
                                    <input type="text" class="form-input" value="INV-{YYYY}-{0000}" readonly>
                                    <p class="text-xs text-gray-500 mt-1">Format: INV-YYYY-0001</p>
                                </div>
                            </div>

                            <div class="flex justify-end mt-6">
                                <button type="submit" class="btn btn-primary">
                                    Save Financial Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div id="security" class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Security Settings</h2>
                    </div>

                    <div class="p-6">
                        <!-- Change Password -->
                        <div class="mb-6">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">Change Password</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-group">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-input" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-input" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-input" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    </div>
                                </div>

                                <div class="flex justify-end mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        Change Password
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Security Options -->
                        <div class="border-t pt-6">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">Security Options</h3>

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">Two-Factor Authentication</h4>
                                        <p class="text-sm text-gray-500">Add an extra layer of security to your account</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" <?php echo ENABLE_2FA ? 'checked' : ''; ?> disabled>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">Session Timeout</h4>
                                        <p class="text-sm text-gray-500">Automatically log out after inactivity</p>
                                    </div>
                                    <span class="text-sm text-gray-500">60 minutes</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">Login Notifications</h4>
                                        <p class="text-sm text-gray-500">Get notified of new login attempts</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
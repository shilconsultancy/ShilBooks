<?php
require_once '../config.php';

// Admin-only access check
if (!hasPermission('admin')) {
    // Redirect non-admins to the dashboard or show an error
    header("location: " . BASE_PATH . "dashboard.php");
    exit;
}

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$errors = [];
$message = '';

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();

    try {
        $pdo->beginTransaction();
        
        // Loop through all posted text data and save it
        foreach ($_POST as $key => $value) {
            // Skip the token itself from being saved to settings
            if ($key === 'csrf_token') {
                continue;
            }
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, trim($value), trim($value)]);
        }

        // --- Handle Logo Upload ---
        if (isset($_FILES["company_logo"]) && $_FILES["company_logo"]["error"] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower(pathinfo(basename($_FILES["company_logo"]["name"]), PATHINFO_EXTENSION));

            if (in_array($_FILES["company_logo"]["type"], $allowed_types) && in_array($file_extension, $allowed_extensions)) {
                $upload_dir = '../uploads/company/';

                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception("Failed to create upload directory. Please check permissions.");
                    }
                }

                // Ensure directory is writable
                if (!is_writable($upload_dir)) {
                    throw new Exception("Upload directory is not writable. Please check permissions.");
                }

                $stored_filename = 'logo.' . $file_extension;

                if (move_uploaded_file($_FILES["company_logo"]["tmp_name"], $upload_dir . $stored_filename)) {
                    $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('company_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?";
                    $pdo->prepare($sql)->execute([$stored_filename, $stored_filename]);
                } else {
                    throw new Exception("Failed to move uploaded logo. Please check directory permissions.");
                }
            } else { throw new Exception("Invalid logo file type. Please use JPG, PNG, or GIF."); }
        }

        $pdo->commit();
        $message = "Settings saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Error saving settings: " . $e->getMessage();
    }
}

// --- Fetch all existing settings to display in the form ---
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
$stmt->execute();
$settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = fn($key, $default = '') => htmlspecialchars($settings_raw[$key] ?? $default);

$pageTitle = 'Company Settings';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Company Settings</h1>
        <a href="<?php echo BASE_PATH; ?>settings/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Settings</a>
    </header>
    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $errors[0]; ?></div><?php endif; ?>

            <form action="company.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-6">Company Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label><input type="text" name="company_name" id="company_name" value="<?php echo $s('company_name'); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="company_reg_number" class="block text-sm font-medium text-gray-700">Registration Number</label><input type="text" name="company_reg_number" id="company_reg_number" value="<?php echo $s('company_reg_number'); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div class="md:col-span-2"><label for="company_address" class="block text-sm font-medium text-gray-700">Address</label><textarea name="company_address" id="company_address" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo $s('company_address'); ?></textarea></div>
                        <div><label for="company_phone" class="block text-sm font-medium text-gray-700">Phone Number</label><input type="tel" name="company_phone" id="company_phone" value="<?php echo $s('company_phone'); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="company_email" class="block text-sm font-medium text-gray-700">Email Address</label><input type="email" name="company_email" id="company_email" value="<?php echo $s('company_email'); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div class="md:col-span-2"><label for="company_website" class="block text-sm font-medium text-gray-700">Website</label><input type="url" name="company_website" id="company_website" value="<?php echo $s('company_website'); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                         <div>
                            <label for="company_logo" class="block text-sm font-medium text-gray-700">Company Logo</label>
                            <input type="file" name="company_logo" id="company_logo" class="mt-1 block w-full text-sm text-macgray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-macblue-50 file:text-macblue-700 hover:file:bg-macblue-100">
                             <?php if ($s('company_logo')): ?>
                                <img src="<?php echo BASE_PATH . 'uploads/company/' . $s('company_logo') . '?t=' . time(); ?>" alt="Current Logo" class="mt-2 h-16 w-auto">
                            <?php endif; ?>
                        </div>
                    </div>

                    <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-6 mt-8">Regional Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label for="language" class="block text-sm font-medium text-gray-700">Language</label><select name="language" id="language" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><option value="en_US" <?php echo ($s('language', 'en_US') == 'en_US') ? 'selected' : ''; ?>>English (US)</option></select></div>
                        <div><label for="timezone" class="block text-sm font-medium text-gray-700">Time Zone</label><select name="timezone" id="timezone" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><option value="Asia/Dhaka" <?php echo ($s('timezone', 'Asia/Dhaka') == 'Asia/Dhaka') ? 'selected' : ''; ?>>Asia/Dhaka</option></select></div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Settings</button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
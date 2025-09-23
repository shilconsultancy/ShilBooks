<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$message = '';
$password_message = '';
$password_errors = [];

// --- Handle Profile Information Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    if (empty($name) || empty($email)) {
        $errors[] = "Name and email are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if email is already taken by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        $errors[] = "This email is already in use by another account.";
    }
    
    // Handle Profile Picture Upload
    $profile_picture_filename = $_POST['existing_profile_picture']; // Keep old one by default
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
         $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES["profile_picture"]["type"], $allowed_types)) {
            $upload_dir = '../uploads/' . $userId . '/profile/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
            $file_extension = pathinfo(basename($_FILES["profile_picture"]["name"]), PATHINFO_EXTENSION);
            $stored_filename = 'pfp.' . $file_extension;
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $upload_dir . $stored_filename)) {
                $profile_picture_filename = $stored_filename;
            } else { $errors[] = "Failed to upload profile picture."; }
        } else { $errors[] = "Invalid profile picture file type (JPG, PNG, GIF only)."; }
    }


    if (empty($errors)) {
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$name, $email, $phone, $profile_picture_filename, $userId])) {
            $_SESSION['user_name'] = $name; // Update session variable
            $message = "Profile updated successfully!";
        } else {
            $errors[] = "Failed to update profile.";
        }
    }
}


// --- Handle Password Change ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_errors[] = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_errors[] = "New password and confirmation do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update_stmt->execute([$hashed_password, $userId])) {
                $password_message = "Password changed successfully!";
            } else {
                $password_errors[] = "Failed to update password.";
            }
        } else {
            $password_errors[] = "Incorrect current password.";
        }
    }
}


// Fetch current user data for the form
$stmt = $pdo->prepare("SELECT name, email, phone, profile_picture FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Profile Settings';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Profile Settings</h1>
        <a href="<?php echo BASE_PATH; ?>settings/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Settings</a>
    </header>
    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto space-y-8">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-6">Your Profile</h2>
                    <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
                    <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $errors[0]; ?></div><?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2 space-y-6">
                            <div><label for="name" class="block text-sm font-medium text-gray-700">Full Name</label><input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                            <div><label for="email" class="block text-sm font-medium text-gray-700">Email Address</label><input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                            <div><label for="phone" class="block text-sm font-medium text-gray-700">Phone</label><input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Profile Picture</label>
                            <div class="mt-1 flex items-center">
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <img src="<?php echo BASE_PATH . 'uploads/' . $userId . '/profile/' . $user['profile_picture'] . '?t=' . time(); ?>" alt="Profile Picture" class="h-24 w-24 rounded-full object-cover">
                                <?php else: ?>
                                     <span class="h-24 w-24 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center"><i data-feather="user" class="h-12 w-12 text-gray-400"></i></span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="profile_picture" id="profile_picture" class="mt-2 block w-full text-sm text-macgray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-macblue-50 file:text-macblue-700 hover:file:bg-macblue-100">
                            <input type="hidden" name="existing_profile_picture" value="<?php echo htmlspecialchars($user['profile_picture']); ?>">
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><button type="submit" name="update_profile" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Profile</button></div>
            </form>

            <form action="profile.php" method="POST">
                 <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-6">Change Password(need to add forget password seeting)</h2>
                    <?php if ($password_message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $password_message; ?></div><?php endif; ?>
                    <?php if (!empty($password_errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $password_errors[0]; ?></div><?php endif; ?>
                    <div class="space-y-4">
                        <div><label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label><input type="password" name="current_password" id="current_password" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label><input type="password" name="new_password" id="new_password" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label><input type="password" name="confirm_password" id="confirm_password" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><button type="submit" name="change_password" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Change Password</button></div>
            </form>

        </div>
    </main>
</div>

<?php
require_once '../partials/footer.php';
?>
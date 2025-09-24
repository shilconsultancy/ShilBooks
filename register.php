<?php
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$name = $email = $password = '';
$errors = [];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validate the CSRF token
    validate_csrf_token();

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($name)) { $errors['name'] = "Please enter your name."; }
    if (empty($email)) { $errors['email'] = "Please enter your email."; }
    if (empty($password)) { $errors['password'] = "Please enter a password."; }
    elseif (strlen($password) < 6) { $errors['password'] = "Password must have at least 6 characters."; }

    if (empty($errors)) {
        $sql_check = "SELECT id FROM users WHERE email = :email";
        if ($stmt_check = $pdo->prepare($sql_check)) {
            $stmt_check->bindParam(":email", $email, PDO::PARAM_STR);
            if ($stmt_check->execute()) {
                if ($stmt_check->rowCount() == 1) {
                    $errors['email'] = "This email is already taken.";
                }
            } else { echo "Oops! Something went wrong."; }
        }
        unset($stmt_check);
    }
    
    if (empty($errors)) {
        // Updated query to include the 'role'
        $sql = "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)";
        if ($stmt = $pdo->prepare($sql)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin'; // New sign-ups are always admins of their own account
            
            $stmt->bindParam(":name", $name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(":role", $role, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // Redirect to login page with a success message (optional)
                header("location: index.php?status=registered");
                exit;
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            unset($stmt);
        }
    }
    unset($pdo);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Modern Accounting App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        macblue: { 50: '#f0f6ff', 100: '#e0edff', 200: '#c9e0ff', 300: '#a8ceff', 400: '#86b2ff', 500: '#5e8eff', 600: '#3d6bff', 700: '#2d5af1', 800: '#1f49d4', 900: '#1d3fab' },
                        macgray: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a' }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; -webkit-font-smoothing: antialiased; }
    </style>
</head>
<body class="h-full bg-macgray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto w-16 h-16 rounded-md bg-macblue-500 flex items-center justify-center">
                <i data-feather="user-plus" class="text-white h-8 w-8"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-macgray-900">
                Create a new account
            </h2>
            <p class="mt-2 text-center text-sm text-macgray-600">
                Or <a href="index.php" class="font-medium text-macblue-600 hover:text-macblue-500">
                    sign in to your existing account
                </a>
            </p>
        </div>

        <form class="mt-8 space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="name" class="sr-only">Full Name</label>
                    <input id="name" name="name" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-macgray-300 placeholder-macgray-500 text-macgray-900 rounded-t-md focus:outline-none focus:ring-macblue-500 focus:border-macblue-500 focus:z-10 sm:text-sm" placeholder="Full Name" value="<?php echo htmlspecialchars($name); ?>">
                </div>
                <div>
                    <label for="email-address" class="sr-only">Email address</label>
                    <input id="email-address" name="email" type="email" autocomplete="email" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-macgray-300 placeholder-macgray-500 text-macgray-900 focus:outline-none focus:ring-macblue-500 focus:border-macblue-500 focus:z-10 sm:text-sm" placeholder="Email address" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-macgray-300 placeholder-macgray-500 text-macgray-900 rounded-b-md focus:outline-none focus:ring-macblue-500 focus:border-macblue-500 focus:z-10 sm:text-sm" placeholder="Password (min 6 characters)">
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <?php
                    if (isset($errors['name'])) echo '<p>' . htmlspecialchars($errors['name']) . '</p>';
                    if (isset($errors['email'])) echo '<p>' . htmlspecialchars($errors['email']) . '</p>';
                    if (isset($errors['password'])) echo '<p>' . htmlspecialchars($errors['password']) . '</p>';
                ?>
            </div>
            <?php endif; ?>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-macblue-600 hover:bg-macblue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-macblue-500">
                    Create Account
                </button>
            </div>
        </form>
    </div>
    <script>
        feather.replace()
    </script>
</body>
</html>
<?php
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$email = $password = '';
$error = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["email"])) || empty(trim($_POST["password"]))) {
        $error = "Please enter both email and password.";
    } else {
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);
    }

    if (empty($error)) {
        $sql = "SELECT id, name, email, password FROM users WHERE email = :email";

        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $param_email = $email;

            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $id = $row["id"];
                        $name = $row["name"];
                        $hashed_password = $row["password"];
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            session_regenerate_id();
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["user_name"] = $name;

                            // Redirect user to dashboard
                            header("location: dashboard.php");
                            exit;
                        } else {
                            // Password is not valid
                            $error = "The password you entered was not valid.";
                        }
                    }
                } else {
                    // Email doesn't exist
                    $error = "No account found with that email.";
                }
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
    <title>Sign In - Modern Accounting App</title>
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
                <i data-feather="dollar-sign" class="text-white h-8 w-8"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-macgray-900">
                Sign in to your account
            </h2>
            <p class="mt-2 text-center text-sm text-macgray-600">
                Or <a href="register.php" class="font-medium text-macblue-600 hover:text-macblue-500">
                    create a new account
                </a>
            </p>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="remember" value="true">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="email-address" class="sr-only">Email address</label>
                    <input id="email-address" name="email" type="email" autocomplete="email" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-macgray-300 placeholder-macgray-500 text-macgray-900 rounded-t-md focus:outline-none focus:ring-macblue-500 focus:border-macblue-500 focus:z-10 sm:text-sm" placeholder="Email address" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-macgray-300 placeholder-macgray-500 text-macgray-900 rounded-b-md focus:outline-none focus:ring-macblue-500 focus:border-macblue-500 focus:z-10 sm:text-sm" placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-macblue-600 hover:bg-macblue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-macblue-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
    <script>
        feather.replace()
    </script>
</body>
</html>
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
                header("location: index.php");
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
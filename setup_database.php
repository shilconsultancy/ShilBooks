<?php
require_once 'config.php';

echo "<h1>ShilBooks Database Setup</h1>";

try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";

    // Read and execute the SQL schema
    $sqlFile = 'database_schema.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        echo "<p>Executing database schema...</p>";

        // Split SQL file into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(CREATE DATABASE|USE)/i', $statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore if table already exists
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "<p style='color: red;'>Error executing: " . substr($statement, 0, 50) . "...</p>";
                        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
                    }
                }
            }
        }

        echo "<p style='color: green;'>✓ Database schema executed successfully!</p>";

        // Verify admin user
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $stmt->execute(['admin']);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
            echo "<p><strong>Login credentials:</strong></p>";
            echo "<p>Username: <code>admin</code></p>";
            echo "<p>Password: <code>admin123</code></p>";
        } else {
            echo "<p style='color: red;'>✗ Admin user not found. Please check the SQL file.</p>";
        }

    } else {
        echo "<p style='color: red;'>✗ database_schema.sql file not found!</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ol>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Database credentials in config.php are correct</li>";
    echo "<li>Database 'shilbooks' exists or can be created</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><a href='test_database.php'>Test Database Connection</a> | <a href='index.php'>Go to Login</a></p>";
?>
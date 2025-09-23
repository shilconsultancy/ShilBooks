<?php
// --- PHP ERROR REPORTING (FOR DEVELOPMENT ONLY) ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Start the session on every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- FULLY DYNAMIC BASE PATH ---
// This automatically detects the project's subfolder path
// and works on both localhost and live servers without any changes.
$doc_root = $_SERVER['DOCUMENT_ROOT'];
$project_dir = __DIR__; // The directory of this config file
// Replace backslashes with forward slashes for Windows compatibility
$doc_root = str_replace('\\', '/', $doc_root);
$project_dir = str_replace('\\', '/', $project_dir);
// Get the subfolder path by removing the document root from the project directory
$subfolder = str_replace($doc_root, '', $project_dir);
// Ensure it's a single slash if at the root, or has leading/trailing slashes if in a subfolder
$base_path = rtrim($subfolder, '/') . '/';
define('BASE_PATH', $base_path);


// --- DATABASE CONFIGURATION ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'accounting_app');

// --- Establish Database Connection ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("ERROR: Could not connect. " . $e->getMessage());
}
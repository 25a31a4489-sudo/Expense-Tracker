<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Leave empty for XAMPP default
define('DB_NAME', 'expense_tracker');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'ExpenseTracker');
define('SITE_URL', 'http://localhost/expense-tracker'); // Change to your project path

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone
date_default_timezone_set('Asia/Kolkata');

// Currency settings
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

// Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}
?>
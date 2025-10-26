<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'phcard');

// Game configuration
define('XP_PER_WIN', 100);
define('XP_BONUS_MULTIPLIER', 1.5); // Multiplier for higher AI levels
define('STARTING_HP', 2000);
define('CARDS_IN_HAND', 5);

// Level requirements (XP needed to reach each level)
$LEVEL_REQUIREMENTS = [
    1 => 0,
    2 => 100,
    3 => 300,
    4 => 600,
    5 => 1000,
    6 => 1500,
    7 => 2100,
    8 => 2800,
    9 => 3600,
    10 => 4500
];

// Session configuration
session_start();

// Database connection
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        error_log("Connection failed: " . $e->getMessage());
        return null;
    }
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
}
?>

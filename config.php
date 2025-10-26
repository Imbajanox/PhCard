<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'phcard');

// Game configuration
define('XP_PER_WIN', 150);
define('XP_PER_LOSS', 25);
define('XP_QUEST_DAILY', 50);
define('XP_QUEST_WEEKLY', 200);
define('XP_BONUS_MULTIPLIER', 1.5); // Multiplier for higher AI levels
define('STARTING_HP', 2000);
define('CARDS_IN_HAND', 5);
define('STARTING_MANA', 1);
define('MAX_MANA', 10);
define('MANA_PER_TURN', 1);
define('MAX_DECK_SIZE', 30);
define('MIN_DECK_SIZE', 30);
define('MAX_CARD_DUPLICATES', 2);
define('MULLIGAN_CARDS', 3); // Number of cards player can exchange at start

/**
 * Generate level requirements for progression system.
 * 
 * @param int $maxLevel Maximum level to generate (default 60)
 * @param int $base Base XP for first level (default 100)
 * @param float $exp Exponential growth factor (default 1.15)
 * @param int $growth Linear growth per level (default 50)
 * @return array Associative array mapping level => cumulative XP required
 * 
 * Example tuning:
 * - Faster progression: lower $exp (e.g., 1.10) or higher $base
 * - Slower progression: higher $exp (e.g., 1.20) or lower $base
 * - More linear: lower $exp and higher $growth
 */
function generateLevelRequirements($maxLevel = 60, $base = 100, $exp = 1.15, $growth = 50) {
    $requirements = [1 => 0]; // Level 1 requires 0 XP
    $cumulativeXP = 0;
    
    for ($level = 2; $level <= $maxLevel; $level++) {
        // Calculate XP needed for this level using exponential + linear growth
        $xpForLevel = floor($base * pow($exp, $level - 2) + ($growth * ($level - 2)));
        $cumulativeXP += $xpForLevel;
        $requirements[$level] = $cumulativeXP;
    }
    
    return $requirements;
}

// Generate level requirements (XP needed to reach each level)
// Tunable constants: adjust these to change progression curve
// Default: 60 levels, ~30+ hours of gameplay at avg 150 XP per 10-min game
$LEVEL_REQUIREMENTS = generateLevelRequirements(60, 100, 1.15, 50);

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

// Helper function to check if user is admin
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user && $user['is_admin'];
    } catch(PDOException $e) {
        return false;
    }
}

// Helper function to require admin privileges
function requireAdmin() {
    if (!isAdmin()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit();
    }
}
?>

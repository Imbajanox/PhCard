<?php
require_once '../config.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'profile':
        getUserProfile();
        break;
    case 'cards':
        getUserCards();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getUserProfile() {
    global $LEVEL_REQUIREMENTS;
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT username, level, xp, total_wins, total_losses FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $currentLevel = $user['level'];
            $nextLevel = $currentLevel + 1;
            $xpForCurrent = $LEVEL_REQUIREMENTS[$currentLevel] ?? 0;
            $xpForNext = $LEVEL_REQUIREMENTS[$nextLevel] ?? $user['xp'];
            
            $user['xp_for_next_level'] = $xpForNext;
            $user['xp_progress'] = $user['xp'] - $xpForCurrent;
            $user['xp_needed'] = $xpForNext - $xpForCurrent;
            
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function getUserCards() {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT c.*, uc.quantity 
            FROM user_cards uc 
            JOIN cards c ON uc.card_id = c.id 
            WHERE uc.user_id = ?
            ORDER BY c.required_level, c.rarity, c.name
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'cards' => $cards]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}
?>

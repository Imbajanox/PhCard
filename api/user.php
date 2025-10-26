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
    case 'statistics':
        getUserStatistics();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getUserProfile() {
    global $LEVEL_REQUIREMENTS;
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT username, level, xp, total_wins, total_losses, is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $currentLevel = $user['level'];
            $nextLevel = $currentLevel + 1;
            $xpForCurrent = $LEVEL_REQUIREMENTS[$currentLevel] ?? 0;
            
            // Edge case: if user level exceeds generated max, fallback to current XP
            // This prevents negative progress bars and ensures graceful degradation
            if (!isset($LEVEL_REQUIREMENTS[$nextLevel])) {
                // User is at or beyond max level
                $xpForNext = $user['xp'];
                $user['xp_for_next_level'] = $xpForNext;
                $user['xp_progress'] = 0; // Show full/empty bar
                $user['xp_needed'] = max(1, $xpForNext - $xpForCurrent); // Avoid division by zero
            } else {
                // Normal case: next level exists in requirements
                $xpForNext = $LEVEL_REQUIREMENTS[$nextLevel];
                $user['xp_for_next_level'] = $xpForNext;
                $user['xp_progress'] = $user['xp'] - $xpForCurrent;
                $user['xp_needed'] = $xpForNext - $xpForCurrent;
            }
            
            $user['is_admin'] = (bool)$user['is_admin'];
            
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

function getUserStatistics() {
    $conn = getDBConnection();
    
    try {
        // Get overall statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_games,
                SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as total_wins,
                SUM(CASE WHEN result = 'loss' THEN 1 ELSE 0 END) as total_losses,
                ROUND(AVG(turns_played), 1) as avg_turns,
                ROUND(AVG(xp_gained), 1) as avg_xp,
                MAX(xp_gained) as max_xp_game
            FROM game_history
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $overall = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate winrate
        if ($overall['total_games'] > 0) {
            $overall['winrate'] = round(($overall['total_wins'] / $overall['total_games']) * 100, 1);
        } else {
            $overall['winrate'] = 0;
        }
        
        // Get winrate by AI level
        $stmt = $conn->prepare("
            SELECT 
                ai_level,
                COUNT(*) as games,
                SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                ROUND(SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as winrate
            FROM game_history
            WHERE user_id = ?
            GROUP BY ai_level
            ORDER BY ai_level
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $byAiLevel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent games
        $stmt = $conn->prepare("
            SELECT 
                result,
                ai_level,
                xp_gained,
                turns_played,
                played_at
            FROM game_history
            WHERE user_id = ?
            ORDER BY played_at DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recentGames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'overall' => $overall,
            'by_ai_level' => $byAiLevel,
            'recent_games' => $recentGames
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get statistics']);
    }
}
?>

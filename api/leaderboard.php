<?php
require_once '../config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        getLeaderboard();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getLeaderboard() {
    $conn = getDBConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        return;
    }
    
    try {
        // Get top players by level and XP
        $stmt = $conn->prepare("
            SELECT 
                username, 
                level, 
                xp, 
                total_wins, 
                total_losses,
                (total_wins + total_losses) as total_games,
                CASE 
                    WHEN (total_wins + total_losses) > 0 
                    THEN ROUND((total_wins * 100.0) / (total_wins + total_losses), 1)
                    ELSE 0 
                END as win_rate
            FROM users 
            ORDER BY level DESC, xp DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'leaderboard' => $players
        ]);
    } catch(PDOException $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to fetch leaderboard: ' . $e->getMessage()
        ]);
    }
}
?>

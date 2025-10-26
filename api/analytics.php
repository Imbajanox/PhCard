<?php
require_once '../config.php';

header('Content-Type: application/json');
requireLogin();

$action = $_REQUEST['action'] ?? '';

// Admin-only actions
$adminActions = ['card_stats', 'create_ab_test', 'ab_test_results'];
if (in_array($action, $adminActions)) {
    requireAdmin();
}

switch ($action) {
    case 'record_event':
        recordEvent();
        break;
    case 'card_stats':
        getCardStats();
        break;
    case 'winrate_analysis':
        getWinrateAnalysis();
        break;
    case 'deck_performance':
        getDeckPerformance();
        break;
    case 'create_ab_test':
        createABTest();
        break;
    case 'get_ab_variant':
        getABVariant();
        break;
    case 'record_ab_result':
        recordABResult();
        break;
    case 'ab_test_results':
        getABTestResults();
        break;
    case 'update_card_metrics':
        updateCardMetrics();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function recordEvent() {
    $gameId = intval($_POST['game_id'] ?? 0);
    $cardId = intval($_POST['card_id'] ?? 0);
    $eventType = $_POST['event_type'] ?? '';
    $turnNumber = intval($_POST['turn_number'] ?? 0);
    $playerHp = intval($_POST['player_hp'] ?? 0);
    $opponentHp = intval($_POST['opponent_hp'] ?? 0);
    $metadata = $_POST['metadata'] ?? '{}';
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO game_telemetry 
            (game_id, user_id, card_id, event_type, turn_number, player_hp, opponent_hp, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $gameId ?: null,
            $_SESSION['user_id'],
            $cardId ?: null,
            $eventType,
            $turnNumber,
            $playerHp,
            $opponentHp,
            $metadata
        ]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to record event']);
    }
}

function getCardStats() {
    $cardId = intval($_REQUEST['card_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        if ($cardId > 0) {
            // Get stats for specific card
            $stmt = $conn->prepare("
                SELECT 
                    c.id, c.name, c.type, c.rarity,
                    m.times_played,
                    m.times_in_winning_deck,
                    m.times_in_losing_deck,
                    CASE 
                        WHEN (m.times_in_winning_deck + m.times_in_losing_deck) > 0 
                        THEN ROUND(m.times_in_winning_deck * 100.0 / (m.times_in_winning_deck + m.times_in_losing_deck), 2)
                        ELSE 0 
                    END as winrate,
                    m.total_damage_dealt,
                    m.total_healing_done,
                    m.avg_turn_played,
                    m.last_updated
                FROM cards c
                LEFT JOIN card_balance_metrics m ON c.id = m.card_id
                WHERE c.id = ?
            ");
            $stmt->execute([$cardId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'card' => $stats
            ]);
        } else {
            // Get stats for all cards
            $stmt = $conn->prepare("
                SELECT 
                    c.id, c.name, c.type, c.rarity,
                    m.times_played,
                    m.times_in_winning_deck,
                    m.times_in_losing_deck,
                    CASE 
                        WHEN (m.times_in_winning_deck + m.times_in_losing_deck) > 0 
                        THEN ROUND(m.times_in_winning_deck * 100.0 / (m.times_in_winning_deck + m.times_in_losing_deck), 2)
                        ELSE 0 
                    END as winrate,
                    m.total_damage_dealt,
                    m.total_healing_done,
                    m.avg_turn_played
                FROM cards c
                LEFT JOIN card_balance_metrics m ON c.id = m.card_id
                WHERE m.times_played > 0
                ORDER BY m.times_played DESC
                LIMIT 100
            ");
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'cards' => $stats
            ]);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get card stats']);
    }
}

function getWinrateAnalysis() {
    $conn = getDBConnection();
    
    try {
        // Get overall statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_games,
                SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN result = 'loss' THEN 1 ELSE 0 END) as losses,
                AVG(turns_played) as avg_turns,
                AVG(xp_gained) as avg_xp
            FROM game_history
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $overall = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get winrate by AI level
        $stmt = $conn->prepare("
            SELECT 
                ai_level,
                COUNT(*) as games,
                SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                ROUND(SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as winrate
            FROM game_history
            WHERE user_id = ?
            GROUP BY ai_level
            ORDER BY ai_level
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $byAiLevel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get most played cards
        $stmt = $conn->prepare("
            SELECT 
                c.name,
                COUNT(*) as times_played,
                SUM(CASE WHEN gh.result = 'win' THEN 1 ELSE 0 END) as wins
            FROM game_telemetry gt
            JOIN cards c ON gt.card_id = c.id
            JOIN game_history gh ON gt.game_id = gh.id
            WHERE gt.user_id = ? AND gt.event_type = 'card_played'
            GROUP BY c.id
            ORDER BY times_played DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $mostPlayed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'overall' => $overall,
            'by_ai_level' => $byAiLevel,
            'most_played_cards' => $mostPlayed
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get winrate analysis']);
    }
}

function getDeckPerformance() {
    $deckId = intval($_REQUEST['deck_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        if ($deckId > 0) {
            // Verify deck ownership
            $stmt = $conn->prepare("SELECT id FROM user_decks WHERE id = ? AND user_id = ?");
            $stmt->execute([$deckId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Deck not found']);
                return;
            }
            
            // Get deck stats
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as games_played,
                    SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN result = 'loss' THEN 1 ELSE 0 END) as losses,
                    ROUND(SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as winrate,
                    AVG(turns_played) as avg_turns,
                    AVG(cards_played) as avg_cards_played
                FROM game_history
                WHERE deck_id = ? AND user_id = ?
            ");
            $stmt->execute([$deckId, $_SESSION['user_id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'deck_stats' => $stats
            ]);
        } else {
            // Get performance for all decks
            $stmt = $conn->prepare("
                SELECT 
                    d.id, d.name,
                    COUNT(gh.id) as games_played,
                    SUM(CASE WHEN gh.result = 'win' THEN 1 ELSE 0 END) as wins,
                    ROUND(SUM(CASE WHEN gh.result = 'win' THEN 1 ELSE 0 END) * 100.0 / COUNT(gh.id), 2) as winrate
                FROM user_decks d
                LEFT JOIN game_history gh ON d.id = gh.deck_id AND gh.user_id = d.user_id
                WHERE d.user_id = ?
                GROUP BY d.id
                ORDER BY winrate DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $decks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'decks' => $decks
            ]);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get deck performance']);
    }
}

function createABTest() {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $cardId = intval($_POST['card_id'] ?? 0);
    $variantA = $_POST['variant_a'] ?? '{}';
    $variantB = $_POST['variant_b'] ?? '{}';
    
    // Only admins should be able to create A/B tests
    // For now, we'll allow it for demo purposes
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO ab_test_configs (name, description, card_id, variant_a_config, variant_b_config)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $cardId ?: null, $variantA, $variantB]);
        
        $testId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'test_id' => $testId,
            'message' => 'A/B test created successfully'
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to create A/B test']);
    }
}

function getABVariant() {
    $testId = intval($_REQUEST['test_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM ab_test_configs WHERE id = ? AND is_active = 1");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            echo json_encode(['success' => false, 'error' => 'Test not found or inactive']);
            return;
        }
        
        // Randomly assign variant based on user ID (consistent assignment)
        $variant = ($_SESSION['user_id'] % 2 === 0) ? 'A' : 'B';
        $config = $variant === 'A' ? $test['variant_a_config'] : $test['variant_b_config'];
        
        echo json_encode([
            'success' => true,
            'variant' => $variant,
            'config' => json_decode($config, true)
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get A/B variant']);
    }
}

function recordABResult() {
    $testId = intval($_POST['test_id'] ?? 0);
    $variant = $_POST['variant'] ?? 'A';
    $gameResult = $_POST['game_result'] ?? 'loss';
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO ab_test_results (test_id, user_id, variant, game_result)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$testId, $_SESSION['user_id'], $variant, $gameResult]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to record A/B result']);
    }
}

function getABTestResults() {
    $testId = intval($_REQUEST['test_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM ab_test_configs WHERE id = ?");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            echo json_encode(['success' => false, 'error' => 'Test not found']);
            return;
        }
        
        // Get results for each variant
        $stmt = $conn->prepare("
            SELECT 
                variant,
                COUNT(*) as total_games,
                SUM(CASE WHEN game_result = 'win' THEN 1 ELSE 0 END) as wins,
                ROUND(SUM(CASE WHEN game_result = 'win' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as winrate
            FROM ab_test_results
            WHERE test_id = ?
            GROUP BY variant
        ");
        $stmt->execute([$testId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'test' => $test,
            'results' => $results
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get A/B test results']);
    }
}

function updateCardMetrics() {
    $cardId = intval($_POST['card_id'] ?? 0);
    $gameResult = $_POST['game_result'] ?? 'loss';
    $damageDealt = intval($_POST['damage_dealt'] ?? 0);
    $healingDone = intval($_POST['healing_done'] ?? 0);
    $turnPlayed = intval($_POST['turn_played'] ?? 0);
    
    $conn = getDBConnection();
    
    try {
        // Update metrics
        $stmt = $conn->prepare("
            INSERT INTO card_balance_metrics 
            (card_id, times_played, times_in_winning_deck, times_in_losing_deck, 
             total_damage_dealt, total_healing_done, avg_turn_played)
            VALUES (?, 1, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                times_played = times_played + 1,
                times_in_winning_deck = times_in_winning_deck + ?,
                times_in_losing_deck = times_in_losing_deck + ?,
                total_damage_dealt = total_damage_dealt + ?,
                total_healing_done = total_healing_done + ?,
                avg_turn_played = ((avg_turn_played * times_played) + ?) / (times_played + 1)
        ");
        
        $winIncrement = $gameResult === 'win' ? 1 : 0;
        $lossIncrement = $gameResult === 'loss' ? 1 : 0;
        
        $stmt->execute([
            $cardId,
            $winIncrement,
            $lossIncrement,
            $damageDealt,
            $healingDone,
            $turnPlayed,
            $winIncrement,
            $lossIncrement,
            $damageDealt,
            $healingDone,
            $turnPlayed
        ]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update card metrics']);
    }
}
?>

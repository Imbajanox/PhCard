<?php
require_once '../config.php';

header('Content-Type: application/json');
requireLogin();

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'start':
        startGame();
        break;
    case 'play_card':
        playCard();
        break;
    case 'end_turn':
        endTurn();
        break;
    case 'end_game':
        endGame();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function startGame() {
    $aiLevel = intval($_POST['ai_level'] ?? 1);
    $conn = getDBConnection();
    
    try {
        // Get user's cards
        $stmt = $conn->prepare("
            SELECT c.* FROM user_cards uc 
            JOIN cards c ON uc.card_id = c.id 
            WHERE uc.user_id = ? AND uc.quantity > 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $availableCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialize game state
        $gameState = [
            'player_hp' => STARTING_HP,
            'ai_hp' => STARTING_HP,
            'ai_level' => $aiLevel,
            'turn' => 'player',
            'available_cards' => $availableCards,
            'player_hand' => [],
            'player_field' => [],
            'ai_field' => [],
            'turn_count' => 1
        ];
        
        // Draw initial hand
        $gameState['player_hand'] = drawCards($availableCards, CARDS_IN_HAND);
        
        $_SESSION['game_state'] = $gameState;
        
        echo json_encode([
            'success' => true,
            'game_state' => [
                'player_hp' => $gameState['player_hp'],
                'ai_hp' => $gameState['ai_hp'],
                'ai_level' => $aiLevel,
                'turn' => $gameState['turn'],
                'player_hand' => $gameState['player_hand'],
                'player_field' => $gameState['player_field'],
                'ai_field' => $gameState['ai_field'],
                'turn_count' => $gameState['turn_count']
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to start game']);
    }
}

function drawCards($availableCards, $count) {
    $drawn = [];
    for ($i = 0; $i < $count && count($availableCards) > 0; $i++) {
        $index = array_rand($availableCards);
        $drawn[] = $availableCards[$index];
    }
    return $drawn;
}

function playCard() {
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $cardIndex = intval($_POST['card_index'] ?? -1);
    $target = $_POST['target'] ?? 'opponent';
    
    $gameState = $_SESSION['game_state'];
    
    if ($gameState['turn'] !== 'player') {
        echo json_encode(['success' => false, 'error' => 'Not your turn']);
        return;
    }
    
    if ($cardIndex < 0 || $cardIndex >= count($gameState['player_hand'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid card']);
        return;
    }
    
    $card = $gameState['player_hand'][$cardIndex];
    array_splice($gameState['player_hand'], $cardIndex, 1);
    
    $message = '';
    
    if ($card['type'] === 'monster') {
        $gameState['player_field'][] = $card;
        $message = "Played {$card['name']} (ATK: {$card['attack']}, DEF: {$card['defense']})";
    } else if ($card['type'] === 'spell') {
        // KORREKTUR: applySpellEffect gibt das geänderte $gameState zurück.
        $result = applySpellEffect($gameState, $card, 'player', $target); 
        $gameState = $result['gameState'];
        $message = $result['message'];
    }
    
    $_SESSION['game_state'] = $gameState;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'game_state' => [
            'player_hp' => $gameState['player_hp'],
            'ai_hp' => $gameState['ai_hp'],
            'player_hand' => $gameState['player_hand'],
            'player_field' => $gameState['player_field'],
            'ai_field' => $gameState['ai_field']
        ]
    ]);
}

// KORREKTUR: Nimmt $gameState per Wert und gibt es zusammen mit der Nachricht zurück.
function applySpellEffect($gameState, $card, $caster, $target) {
    $effect = $card['effect'];
    $message = "Cast {$card['name']}: ";
    
    if (!$effect) {
        return ['message' => $message . "No effect", 'gameState' => $gameState];
    }
    
    list($type, $value) = explode(':', $effect);
    $value = intval($value);
    
    switch ($type) {
        case 'damage':
            if ($target === 'opponent') {
                if ($caster === 'player') {
                    $gameState['ai_hp'] -= $value;
                    $message .= "Dealt {$value} damage to AI";
                } else {
                    $gameState['player_hp'] -= $value;
                    $message .= "AI dealt {$value} damage to you";
                }
            }
            break;
        case 'heal':
            // KORREKTUR: Funktioniert jetzt, da $gameState zurückgegeben wird
            if ($target === 'self' || $caster === $target) {
                if ($caster === 'player') {
                    $gameState['player_hp'] = min($gameState['player_hp'] + $value, STARTING_HP);
                    $message .= "Healed {$value} HP";
                } else {
                    $gameState['ai_hp'] = min($gameState['ai_hp'] + $value, STARTING_HP);
                    $message .= "AI healed {$value} HP";
                }
            }
            break;
        case 'boost':
            // An dieser Stelle müssten die boost-Effekte auf Monster im Feld angewendet werden. 
            // Derzeit wird nur die Nachricht ausgegeben.
            $message .= "Boosted attack by {$value}";
            break;
        case 'shield':
            // An dieser Stelle müssten die shield-Effekte angewendet werden.
            $message .= "Gained {$value} shield";
            break;
    }
    
    return ['message' => $message, 'gameState' => $gameState];
}

function endTurn() {
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $gameState = $_SESSION['game_state'];
    
    if ($gameState['turn'] !== 'player') {
        echo json_encode(['success' => false, 'error' => 'Not your turn']);
        return;
    }
    
    // Battle phase - player monsters attack
    $battleLog = [];
    foreach ($gameState['player_field'] as $playerMonster) {
        if (count($gameState['ai_field']) > 0) {
            $aiMonster = $gameState['ai_field'][0];
            $damage = max(0, $playerMonster['attack'] - $aiMonster['defense']);
            $gameState['ai_hp'] -= $damage;
            $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} for {$damage} damage";
            
            if ($aiMonster['defense'] <= $playerMonster['attack']) {
                array_shift($gameState['ai_field']);
                $battleLog[] = "{$aiMonster['name']} was destroyed!";
            }
        } else {
            $gameState['ai_hp'] -= $playerMonster['attack'];
            $battleLog[] = "{$playerMonster['name']} attacks directly for {$playerMonster['attack']} damage";
        }
    }
    
    // Switch to AI turn
    $gameState['turn'] = 'ai';
    
    // AI plays
    // KORREKTUR: PerformAITurn gibt $gameState zurück und muss übernommen werden.
    $aiResult = performAITurn($gameState);
    $aiActions = $aiResult['actions'];
    $gameState = $aiResult['gameState'];
    
    // Battle phase - AI monsters attack
    foreach ($gameState['ai_field'] as $aiMonster) {
        if (count($gameState['player_field']) > 0) {
            $playerMonster = $gameState['player_field'][0];
            $damage = max(0, $aiMonster['attack'] - $playerMonster['defense']);
            $gameState['player_hp'] -= $damage;
            $battleLog[] = "AI {$aiMonster['name']} attacks {$playerMonster['name']} for {$damage} damage";
            
            if ($playerMonster['defense'] <= $aiMonster['attack']) {
                array_shift($gameState['player_field']);
                $battleLog[] = "{$playerMonster['name']} was destroyed!";
            }
        } else {
            $gameState['player_hp'] -= $aiMonster['attack'];
            $battleLog[] = "AI {$aiMonster['name']} attacks directly for {$aiMonster['attack']} damage";
        }
    }
    
    // Switch back to player
    $gameState['turn'] = 'player';
    $gameState['turn_count']++;
    
    // Draw a card
    if (count($gameState['player_hand']) < CARDS_IN_HAND) {
        $newCards = drawCards($gameState['available_cards'], 1);
        if (count($newCards) > 0) {
            $gameState['player_hand'][] = $newCards[0];
        }
    }
    
    $_SESSION['game_state'] = $gameState;
    
    // Check for game over
    $winner = null;
    if ($gameState['player_hp'] <= 0) {
        $winner = 'ai';
    } else if ($gameState['ai_hp'] <= 0) {
        $winner = 'player';
    }
    
    echo json_encode([
        'success' => true,
        'battle_log' => $battleLog,
        'ai_actions' => $aiActions,
        'game_state' => [
            'player_hp' => $gameState['player_hp'],
            'ai_hp' => $gameState['ai_hp'],
            'player_hand' => $gameState['player_hand'],
            'player_field' => $gameState['player_field'],
            'ai_field' => $gameState['ai_field'],
            'turn_count' => $gameState['turn_count']
        ],
        'winner' => $winner
    ]);
}

// KORREKTUR: Nimmt $gameState per Wert und gibt es zusammen mit den Aktionen zurück.
function performAITurn($gameState) {
    $actions = [];
    $aiLevel = $gameState['ai_level'];
    
    // Get AI cards based on level
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM cards WHERE required_level <= ? ORDER BY RAND() LIMIT 3");
    $stmt->execute([min($aiLevel * 2, 10)]);
    $aiCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // AI plays 1-2 cards based on level
    $cardsToPlay = min($aiLevel, 2);
    for ($i = 0; $i < $cardsToPlay && count($aiCards) > 0; $i++) {
        $card = array_shift($aiCards);
        
        if ($card['type'] === 'monster') {
            $gameState['ai_field'][] = $card;
            $actions[] = "AI played {$card['name']} (ATK: {$card['attack']}, DEF: {$card['defense']})";
        } else if ($card['type'] === 'spell') {
            $target = 'opponent';
            // Bessere Heilungs-Logik: heile nur, wenn HP unter 50%
            if (strpos($card['effect'], 'heal') !== false && $gameState['ai_hp'] < STARTING_HP * 0.5) {
                $target = 'self';
            }
            // KORREKTUR: applySpellEffect gibt das geänderte $gameState zurück
            $result = applySpellEffect($gameState, $card, 'ai', $target); 
            $message = $result['message'];
            $gameState = $result['gameState']; // Übernahme des geänderten State-Arrays
            $actions[] = $message;
        }
    }
    
    // KORREKTUR: Geändertes $gameState zurückgeben
    return ['actions' => $actions, 'gameState' => $gameState];
}

function endGame() {
    global $LEVEL_REQUIREMENTS;
    
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $result = $_POST['result'] ?? 'loss';
    $gameState = $_SESSION['game_state'];
    $conn = getDBConnection();
    
    try {
        // Calculate XP
        $xpGained = 0;
        if ($result === 'win') {
            $xpGained = XP_PER_WIN;
            if ($gameState['ai_level'] > 1) {
                $xpGained = floor($xpGained * (1 + ($gameState['ai_level'] - 1) * 0.2));
            }
        }
        
        // Update user stats
        $stmt = $conn->prepare("SELECT level, xp FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $newXp = $user['xp'] + $xpGained;
        $newLevel = $user['level'];
        
        // Check for level up
        foreach ($LEVEL_REQUIREMENTS as $level => $requiredXp) {
            if ($newXp >= $requiredXp && $level > $newLevel) {
                $newLevel = $level;
            }
        }
        
        $leveledUp = $newLevel > $user['level'];
        
        // Update user
        if ($result === 'win') {
            $stmt = $conn->prepare("UPDATE users SET xp = ?, level = ?, total_wins = total_wins + 1 WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE users SET xp = ?, level = ?, total_losses = total_losses + 1 WHERE id = ?");
        }
        $stmt->execute([$newXp, $newLevel, $_SESSION['user_id']]);
        
        // Record game history
        $stmt = $conn->prepare("INSERT INTO game_history (user_id, ai_level, result, xp_gained) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $gameState['ai_level'], $result, $xpGained]);
        
        // If leveled up, unlock new cards
        $unlockedCards = [];
        if ($leveledUp) {
            $stmt = $conn->prepare("SELECT * FROM cards WHERE required_level = ?");
            $stmt->execute([$newLevel]);
            $newCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("INSERT INTO user_cards (user_id, card_id, quantity) VALUES (?, ?, 2) ON DUPLICATE KEY UPDATE quantity = quantity + 2");
            foreach ($newCards as $card) {
                $stmt->execute([$_SESSION['user_id'], $card['id']]);
                $unlockedCards[] = $card;
            }
        }
        
        unset($_SESSION['game_state']);
        
        echo json_encode([
            'success' => true,
            'result' => $result,
            'xp_gained' => $xpGained,
            'new_level' => $newLevel,
            'leveled_up' => $leveledUp,
            'unlocked_cards' => $unlockedCards
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to save game result']);
    }
}
?>
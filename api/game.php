<?php
require_once '../config.php';

header('Content-Type: application/json');
requireLogin();

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'start':
        startGame();
        break;
    case 'mulligan':
        performMulligan();
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
    $deckId = intval($_POST['deck_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        // Get user's cards from deck or collection
        if ($deckId > 0) {
            // Verify deck ownership
            $stmt = $conn->prepare("SELECT id FROM user_decks WHERE id = ? AND user_id = ?");
            $stmt->execute([$deckId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Deck not found']);
                return;
            }
            
            // Get cards from deck
            $stmt = $conn->prepare("
                SELECT c.*, dc.quantity 
                FROM deck_cards dc 
                JOIN cards c ON dc.card_id = c.id 
                WHERE dc.deck_id = ?
            ");
            $stmt->execute([$deckId]);
            $deckCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Expand deck cards based on quantity
            $availableCards = [];
            foreach ($deckCards as $card) {
                for ($i = 0; $i < $card['quantity']; $i++) {
                    $availableCards[] = $card;
                }
            }
            shuffle($availableCards);
        } else {
            // Get user's cards from collection
            $stmt = $conn->prepare("
                SELECT c.* FROM user_cards uc 
                JOIN cards c ON uc.card_id = c.id 
                WHERE uc.user_id = ? AND uc.quantity > 0
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $availableCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Initialize game state with new mechanics
        $gameState = [
            'player_hp' => STARTING_HP,
            'ai_hp' => STARTING_HP,
            'player_mana' => STARTING_MANA,
            'ai_mana' => STARTING_MANA,
            'player_max_mana' => STARTING_MANA,
            'ai_max_mana' => STARTING_MANA,
            'player_overload' => 0,
            'ai_overload' => 0,
            'ai_level' => $aiLevel,
            'turn' => 'player',
            'available_cards' => $availableCards,
            'player_hand' => [],
            'player_field' => [],
            'ai_field' => [],
            'turn_count' => 1,
            'mulligan_done' => false,
            'deck_id' => $deckId,
            'cards_played_this_turn' => 0
        ];
        
        // Draw initial hand
        $gameState['player_hand'] = drawCards($availableCards, CARDS_IN_HAND);
        
        $_SESSION['game_state'] = $gameState;
        
        echo json_encode([
            'success' => true,
            'game_state' => [
                'player_hp' => $gameState['player_hp'],
                'ai_hp' => $gameState['ai_hp'],
                'player_mana' => $gameState['player_mana'],
                'player_max_mana' => $gameState['player_max_mana'],
                'ai_level' => $aiLevel,
                'turn' => $gameState['turn'],
                'player_hand' => $gameState['player_hand'],
                'player_field' => $gameState['player_field'],
                'ai_field' => $gameState['ai_field'],
                'turn_count' => $gameState['turn_count'],
                'mulligan_available' => !$gameState['mulligan_done']
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to start game']);
    }
}

function drawCards(&$availableCards, $count) {
    $drawn = [];
    for ($i = 0; $i < $count && count($availableCards) > 0; $i++) {
        $index = array_rand($availableCards);
        $drawn[] = $availableCards[$index];
        array_splice($availableCards, $index, 1);
    }
    return $drawn;
}

function performMulligan() {
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $cardIndices = json_decode($_POST['card_indices'] ?? '[]', true);
    $gameState = $_SESSION['game_state'];
    
    if ($gameState['mulligan_done']) {
        echo json_encode(['success' => false, 'error' => 'Mulligan already used']);
        return;
    }
    
    if (count($cardIndices) > MULLIGAN_CARDS) {
        echo json_encode(['success' => false, 'error' => 'Can only mulligan up to ' . MULLIGAN_CARDS . ' cards']);
        return;
    }
    
    // Put selected cards back and draw new ones
    $newHand = [];
    for ($i = 0; $i < count($gameState['player_hand']); $i++) {
        if (!in_array($i, $cardIndices)) {
            $newHand[] = $gameState['player_hand'][$i];
        }
    }
    
    // Draw replacement cards
    $replacements = drawCards($gameState['available_cards'], count($cardIndices));
    $newHand = array_merge($newHand, $replacements);
    
    $gameState['player_hand'] = $newHand;
    $gameState['mulligan_done'] = true;
    
    $_SESSION['game_state'] = $gameState;
    
    echo json_encode([
        'success' => true,
        'message' => 'Mulliganed ' . count($cardIndices) . ' cards',
        'game_state' => [
            'player_hand' => $gameState['player_hand'],
            'mulligan_available' => false
        ]
    ]);
}

function playCard() {
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $cardIndex = intval($_POST['card_index'] ?? -1);
    $target = $_POST['target'] ?? 'opponent';
    $choice = intval($_POST['choice'] ?? 0); // For Choose One cards
    
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
    $manaCost = intval($card['mana_cost'] ?? 1);
    
    // Check mana
    if ($gameState['player_mana'] < $manaCost) {
        echo json_encode(['success' => false, 'error' => 'Not enough mana']);
        return;
    }
    
    // Deduct mana
    $gameState['player_mana'] -= $manaCost;
    
    // Apply overload for next turn
    if (isset($card['overload']) && $card['overload'] > 0) {
        $gameState['player_overload'] += intval($card['overload']);
    }
    
    array_splice($gameState['player_hand'], $cardIndex, 1);
    
    $message = '';
    
    if ($card['type'] === 'monster') {
        // Handle Choose One effects
        if (!empty($card['choice_effects'])) {
            $choices = json_decode($card['choice_effects'], true);
            if (isset($choices['choices'][$choice])) {
                $selectedChoice = $choices['choices'][$choice];
                $card['attack'] = $selectedChoice['attack'] ?? $card['attack'];
                $card['defense'] = $selectedChoice['defense'] ?? $card['defense'];
                $message = "Played {$card['name']} ({$selectedChoice['name']}) - ";
            }
        }
        
        // Apply keywords
        $card['status_effects'] = [];
        if (!empty($card['keywords'])) {
            $keywords = explode(',', $card['keywords']);
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (in_array($keyword, ['taunt', 'divine_shield', 'stealth', 'windfury', 'lifesteal', 'poison', 'charge', 'rush'])) {
                    $card['status_effects'][] = $keyword;
                }
            }
        }
        
        $gameState['player_field'][] = $card;
        if (empty($message)) {
            $message = "Played {$card['name']} (ATK: {$card['attack']}, DEF: {$card['defense']})";
        } else {
            $message .= "(ATK: {$card['attack']}, DEF: {$card['defense']})";
        }
    } else if ($card['type'] === 'spell') {
        $result = applySpellEffect($gameState, $card, 'player', $target); 
        $gameState = $result['gameState'];
        $message = $result['message'];
    }
    
    $gameState['cards_played_this_turn']++;
    
    $_SESSION['game_state'] = $gameState;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'game_state' => [
            'player_hp' => $gameState['player_hp'],
            'ai_hp' => $gameState['ai_hp'],
            'player_mana' => $gameState['player_mana'],
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
            // Boost all monsters on caster's field
            $fieldKey = $caster . '_field';
            foreach ($gameState[$fieldKey] as &$monster) {
                $monster['attack'] = ($monster['attack'] ?? 0) + $value;
            }
            $message .= "Boosted attack by {$value}";
            break;
        case 'shield':
            $message .= "Gained {$value} shield";
            break;
        case 'stun':
            // Stun all opponent monsters
            $opponentField = ($caster === 'player') ? 'ai_field' : 'player_field';
            foreach ($gameState[$opponentField] as &$monster) {
                if (!isset($monster['status_effects'])) {
                    $monster['status_effects'] = [];
                }
                $monster['status_effects'][] = 'stunned';
                $monster['stun_duration'] = $value;
            }
            $message .= "Stunned all enemy monsters for {$value} turns";
            break;
        case 'poison':
            // Poison opponent
            $opponentKey = ($caster === 'player') ? 'ai' : 'player';
            if (!isset($gameState[$opponentKey . '_status_effects'])) {
                $gameState[$opponentKey . '_status_effects'] = [];
            }
            $gameState[$opponentKey . '_status_effects']['poison'] = $value;
            $message .= "Poisoned opponent for {$value} turns";
            break;
        case 'combo_boost':
            // Combo effect based on cards played this turn
            $cardsPlayed = $gameState['cards_played_this_turn'] ?? 0;
            $boostAmount = $value * $cardsPlayed;
            $fieldKey = $caster . '_field';
            if (count($gameState[$fieldKey]) > 0) {
                $gameState[$fieldKey][count($gameState[$fieldKey]) - 1]['attack'] += $boostAmount;
                $message .= "Combo! Boosted by {$boostAmount} (cards played: {$cardsPlayed})";
            }
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
    
    // Process status effects at start of battle
    $battleLog = array_merge($battleLog, processStatusEffects($gameState, 'player'));
    
    foreach ($gameState['player_field'] as $i => $playerMonster) {
        // Check if monster can attack (not stunned/frozen)
        $canAttack = true;
        if (isset($playerMonster['status_effects']) && in_array('stunned', $playerMonster['status_effects'])) {
            $canAttack = false;
            $battleLog[] = "{$playerMonster['name']} is stunned and cannot attack";
        }
        
        if (!$canAttack) continue;
        
        // Check for Taunt monsters on opponent's field
        $tauntMonsters = array_filter($gameState['ai_field'], function($m) {
            return isset($m['status_effects']) && in_array('taunt', $m['status_effects']);
        });
        
        if (count($tauntMonsters) > 0) {
            $aiMonster = reset($tauntMonsters);
            $damage = max(0, $playerMonster['attack'] - $aiMonster['defense']);
            
            // Check for Divine Shield
            if (isset($aiMonster['status_effects']) && in_array('divine_shield', $aiMonster['status_effects'])) {
                $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} but Divine Shield absorbs the damage!";
                // Remove divine shield
                $aiMonster['status_effects'] = array_diff($aiMonster['status_effects'], ['divine_shield']);
            } else {
                $gameState['ai_hp'] -= $damage;
                $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} for {$damage} damage";
                
                // Apply Lifesteal
                if (isset($playerMonster['status_effects']) && in_array('lifesteal', $playerMonster['status_effects'])) {
                    $gameState['player_hp'] = min($gameState['player_hp'] + $damage, STARTING_HP);
                    $battleLog[] = "{$playerMonster['name']} heals for {$damage} HP (Lifesteal)";
                }
                
                // Apply Poison
                if (isset($playerMonster['status_effects']) && in_array('poison', $playerMonster['status_effects'])) {
                    if (!isset($aiMonster['status_effects'])) $aiMonster['status_effects'] = [];
                    $aiMonster['status_effects'][] = 'poisoned';
                    $aiMonster['poison_damage'] = 50;
                }
                
                if ($aiMonster['defense'] <= $playerMonster['attack']) {
                    // Find and remove the monster
                    foreach ($gameState['ai_field'] as $idx => $m) {
                        if ($m['id'] === $aiMonster['id']) {
                            array_splice($gameState['ai_field'], $idx, 1);
                            break;
                        }
                    }
                    $battleLog[] = "{$aiMonster['name']} was destroyed!";
                }
            }
        } else if (count($gameState['ai_field']) > 0) {
            $aiMonster = $gameState['ai_field'][0];
            $damage = max(0, $playerMonster['attack'] - $aiMonster['defense']);
            
            // Check for Divine Shield
            if (isset($aiMonster['status_effects']) && in_array('divine_shield', $aiMonster['status_effects'])) {
                $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} but Divine Shield absorbs the damage!";
                $aiMonster['status_effects'] = array_diff($aiMonster['status_effects'], ['divine_shield']);
                $gameState['ai_field'][0] = $aiMonster;
            } else {
                $gameState['ai_hp'] -= $damage;
                $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} for {$damage} damage";
                
                // Apply Lifesteal
                if (isset($playerMonster['status_effects']) && in_array('lifesteal', $playerMonster['status_effects'])) {
                    $gameState['player_hp'] = min($gameState['player_hp'] + $damage, STARTING_HP);
                    $battleLog[] = "{$playerMonster['name']} heals for {$damage} HP (Lifesteal)";
                }
                
                if ($aiMonster['defense'] <= $playerMonster['attack']) {
                    array_shift($gameState['ai_field']);
                    $battleLog[] = "{$aiMonster['name']} was destroyed!";
                }
            }
        } else {
            $gameState['ai_hp'] -= $playerMonster['attack'];
            $battleLog[] = "{$playerMonster['name']} attacks directly for {$playerMonster['attack']} damage";
            
            // Apply Lifesteal
            if (isset($playerMonster['status_effects']) && in_array('lifesteal', $playerMonster['status_effects'])) {
                $gameState['player_hp'] = min($gameState['player_hp'] + $playerMonster['attack'], STARTING_HP);
                $battleLog[] = "{$playerMonster['name']} heals for {$playerMonster['attack']} HP (Lifesteal)";
            }
        }
        
        // Windfury - attack twice
        if (isset($playerMonster['status_effects']) && in_array('windfury', $playerMonster['status_effects'])) {
            if (count($gameState['ai_field']) > 0) {
                $damage = max(0, $playerMonster['attack'] - $gameState['ai_field'][0]['defense']);
                $gameState['ai_hp'] -= $damage;
                $battleLog[] = "{$playerMonster['name']} attacks again (Windfury) for {$damage} damage!";
            } else {
                $gameState['ai_hp'] -= $playerMonster['attack'];
                $battleLog[] = "{$playerMonster['name']} attacks again (Windfury) for {$playerMonster['attack']} damage!";
            }
        }
    }
    
    // Switch to AI turn
    $gameState['turn'] = 'ai';
    
    // AI plays
    $aiResult = performAITurn($gameState);
    $aiActions = $aiResult['actions'];
    $gameState = $aiResult['gameState'];
    
    // Process AI status effects
    $battleLog = array_merge($battleLog, processStatusEffects($gameState, 'ai'));
    
    // Battle phase - AI monsters attack
    foreach ($gameState['ai_field'] as $aiMonster) {
        // Check if monster can attack
        $canAttack = true;
        if (isset($aiMonster['status_effects']) && in_array('stunned', $aiMonster['status_effects'])) {
            $canAttack = false;
            $battleLog[] = "AI {$aiMonster['name']} is stunned and cannot attack";
        }
        
        if (!$canAttack) continue;
        
        // Check for Taunt
        $tauntMonsters = array_filter($gameState['player_field'], function($m) {
            return isset($m['status_effects']) && in_array('taunt', $m['status_effects']);
        });
        
        if (count($tauntMonsters) > 0) {
            $playerMonster = reset($tauntMonsters);
            $damage = max(0, $aiMonster['attack'] - $playerMonster['defense']);
            $gameState['player_hp'] -= $damage;
            $battleLog[] = "AI {$aiMonster['name']} attacks {$playerMonster['name']} for {$damage} damage";
            
            if ($playerMonster['defense'] <= $aiMonster['attack']) {
                foreach ($gameState['player_field'] as $idx => $m) {
                    if ($m['id'] === $playerMonster['id']) {
                        array_splice($gameState['player_field'], $idx, 1);
                        break;
                    }
                }
                $battleLog[] = "{$playerMonster['name']} was destroyed!";
            }
        } else if (count($gameState['player_field']) > 0) {
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
    $gameState['cards_played_this_turn'] = 0;
    
    // Increase max mana
    $gameState['player_max_mana'] = min(MAX_MANA, $gameState['player_max_mana'] + MANA_PER_TURN);
    $gameState['ai_max_mana'] = min(MAX_MANA, $gameState['ai_max_mana'] + MANA_PER_TURN);
    
    // Restore mana minus overload
    $gameState['player_mana'] = max(0, $gameState['player_max_mana'] - $gameState['player_overload']);
    $gameState['ai_mana'] = max(0, $gameState['ai_max_mana'] - $gameState['ai_overload']);
    
    // Clear overload
    $gameState['player_overload'] = 0;
    $gameState['ai_overload'] = 0;
    
    // Draw a card
    if (count($gameState['player_hand']) < 10) {
        $newCards = drawCards($gameState['available_cards'], 1);
        if (count($newCards) > 0) {
            $gameState['player_hand'][] = $newCards[0];
        }
    }
    
    // Decrease stun durations
    foreach ($gameState['player_field'] as &$monster) {
        if (isset($monster['stun_duration'])) {
            $monster['stun_duration']--;
            if ($monster['stun_duration'] <= 0) {
                $monster['status_effects'] = array_diff($monster['status_effects'], ['stunned']);
                unset($monster['stun_duration']);
            }
        }
    }
    foreach ($gameState['ai_field'] as &$monster) {
        if (isset($monster['stun_duration'])) {
            $monster['stun_duration']--;
            if ($monster['stun_duration'] <= 0) {
                $monster['status_effects'] = array_diff($monster['status_effects'], ['stunned']);
                unset($monster['stun_duration']);
            }
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
            'player_mana' => $gameState['player_mana'],
            'player_max_mana' => $gameState['player_max_mana'],
            'player_hand' => $gameState['player_hand'],
            'player_field' => $gameState['player_field'],
            'ai_field' => $gameState['ai_field'],
            'turn_count' => $gameState['turn_count']
        ],
        'winner' => $winner
    ]);
}

function processStatusEffects(&$gameState, $player) {
    $log = [];
    
    // Process poison damage
    if (isset($gameState[$player . '_status_effects']['poison'])) {
        $poisonDuration = $gameState[$player . '_status_effects']['poison'];
        if ($poisonDuration > 0) {
            $poisonDamage = 50;
            $gameState[$player . '_hp'] -= $poisonDamage;
            $log[] = ($player === 'player' ? 'You take' : 'AI takes') . " {$poisonDamage} poison damage";
            $gameState[$player . '_status_effects']['poison']--;
            if ($gameState[$player . '_status_effects']['poison'] <= 0) {
                unset($gameState[$player . '_status_effects']['poison']);
            }
        }
    }
    
    // Process poisoned monsters
    $fieldKey = $player . '_field';
    if (isset($gameState[$fieldKey]) && is_array($gameState[$fieldKey])) {
        // Iterate backwards to safely remove elements
        for ($i = count($gameState[$fieldKey]) - 1; $i >= 0; $i--) {
            $monster = &$gameState[$fieldKey][$i];
            if (isset($monster['poison_damage'])) {
                $monster['defense'] -= $monster['poison_damage'];
                $log[] = "{$monster['name']} takes {$monster['poison_damage']} poison damage";
                if ($monster['defense'] <= 0) {
                    array_splice($gameState[$fieldKey], $i, 1);
                    $log[] = "{$monster['name']} was destroyed by poison!";
                }
            }
            unset($monster);
        }
    }
    
    return $log;
}

// KORREKTUR: Nimmt $gameState per Wert und gibt es zusammen mit den Aktionen zurück.
function performAITurn($gameState) {
    $actions = [];
    $aiLevel = $gameState['ai_level'];
    
    // Get AI cards based on level
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM cards WHERE required_level <= ? ORDER BY RAND() LIMIT 8");
    $stmt->execute([min($aiLevel * 2, 10)]);
    $aiCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // AI plays cards based on available mana and strategy
    $aiMana = $gameState['ai_mana'];
    
    // Analyze game state
    $playerFieldSize = count($gameState['player_field']);
    $aiFieldSize = count($gameState['ai_field']);
    $aiHPPercent = $gameState['ai_hp'] / STARTING_HP;
    $playerHPPercent = $gameState['player_hp'] / STARTING_HP;
    
    // Calculate total player field power
    $playerFieldPower = 0;
    foreach ($gameState['player_field'] as $monster) {
        $playerFieldPower += intval($monster['attack'] ?? 0);
    }
    
    // Prioritize and score cards
    $scoredCards = [];
    foreach ($aiCards as $card) {
        $manaCost = intval($card['mana_cost'] ?? 1);
        if ($aiMana < $manaCost) {
            continue; // Can't afford this card
        }
        
        $score = scoreCard($card, $gameState, $aiHPPercent, $playerHPPercent, $playerFieldSize, $aiFieldSize, $playerFieldPower, $aiLevel);
        $scoredCards[] = ['card' => $card, 'score' => $score];
    }
    
    // Sort cards by score (highest first)
    usort($scoredCards, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Play cards in priority order
    foreach ($scoredCards as $scoredCard) {
        $card = $scoredCard['card'];
        $manaCost = intval($card['mana_cost'] ?? 1);
        
        if ($aiMana < $manaCost) {
            continue; // Can't afford this card anymore
        }
        
        if ($card['type'] === 'monster') {
            // Normalize numeric stats
            $card['attack'] = intval($card['attack'] ?? 0);
            $card['defense'] = intval($card['defense'] ?? 0);
            
            // Ensure status_effects is an array
            if (!isset($card['status_effects']) || !is_array($card['status_effects'])) {
                $card['status_effects'] = [];
            }
            
            // Apply keywords to monster
            if (!empty($card['keywords'])) {
                $keywords = explode(',', $card['keywords']);
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    if (in_array($keyword, ['taunt', 'divine_shield', 'stealth', 'windfury', 'lifesteal', 'poison', 'charge', 'rush'])) {
                        $card['status_effects'][] = $keyword;
                    }
                }
                // Deduplicate status effects
                $card['status_effects'] = array_values(array_unique($card['status_effects']));
            }
            
            $gameState['ai_field'][] = $card;
            $actions[] = "AI played {$card['name']} (ATK: {$card['attack']}, DEF: {$card['defense']})";
            $aiMana -= $manaCost;
            $aiFieldSize++;
            
            // Apply overload
            if (isset($card['overload']) && $card['overload'] > 0) {
                $gameState['ai_overload'] += intval($card['overload']);
            }
        } else if ($card['type'] === 'spell') {
            $target = 'opponent';
            
            // Smart spell targeting
            if (strpos($card['effect'], 'heal') !== false) {
                // Only heal if HP is low
                if ($gameState['ai_hp'] < STARTING_HP * 0.6) {
                    $target = 'self';
                } else {
                    continue; // Skip heal spell if not needed
                }
            } else if (strpos($card['effect'], 'boost') !== false) {
                // Only use boost if we have monsters on field
                if ($aiFieldSize === 0) {
                    continue; // Skip boost if no monsters
                }
            }
            
            $result = applySpellEffect($gameState, $card, 'ai', $target); 
            $message = $result['message'];
            $gameState = $result['gameState'];
            $actions[] = $message;
            $aiMana -= $manaCost;
            
            // Apply overload
            if (isset($card['overload']) && $card['overload'] > 0) {
                $gameState['ai_overload'] += intval($card['overload']);
            }
        }
        
        // Stop if out of mana
        if ($aiMana <= 0) break;
    }
    
    $gameState['ai_mana'] = $aiMana;
    
    return ['actions' => $actions, 'gameState' => $gameState];
}

// New function to score cards based on game state
function scoreCard($card, $gameState, $aiHPPercent, $playerHPPercent, $playerFieldSize, $aiFieldSize, $playerFieldPower, $aiLevel) {
    $score = 0;
    $manaCost = intval($card['mana_cost'] ?? 1);
    
    if ($card['type'] === 'monster') {
        $attack = intval($card['attack'] ?? 0);
        $defense = intval($card['defense'] ?? 0);
        
        // Base value: stats relative to mana cost
        $statsValue = ($attack + $defense) / max(1, $manaCost);
        $score += $statsValue * 10;
        
        // Bonus for keywords
        if (!empty($card['keywords'])) {
            $keywords = explode(',', $card['keywords']);
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                
                // Taunt is very valuable when player has strong board
                if ($keyword === 'taunt' && $playerFieldPower > 0) {
                    $score += 15 * $aiLevel;
                }
                
                // Divine Shield is always good
                if ($keyword === 'divine_shield') {
                    $score += 10 * $aiLevel;
                }
                
                // Lifesteal is valuable when HP is low
                if ($keyword === 'lifesteal' && $aiHPPercent < 0.7) {
                    $score += 12 * $aiLevel;
                }
                
                // Charge/Rush for immediate impact
                if (in_array($keyword, ['charge', 'rush'])) {
                    $score += 8 * $aiLevel;
                }
                
                // Windfury for damage
                if ($keyword === 'windfury') {
                    $score += 10 * $aiLevel;
                }
            }
        }
        
        // Prioritize monsters when we need board presence
        if ($aiFieldSize < $playerFieldSize) {
            $score += 20;
        }
        
        // Higher level AI values efficient trades
        if ($aiLevel >= 3) {
            $score += $attack * 2; // Value attack more for aggression
        }
        
    } else if ($card['type'] === 'spell') {
        $effect = $card['effect'];
        
        if (strpos($effect, 'damage') !== false) {
            preg_match('/damage:(\d+)/', $effect, $matches);
            $damage = isset($matches[1]) ? intval($matches[1]) : 0;
            
            // Value damage based on opponent HP
            $score += $damage * 5;
            
            // More valuable when opponent is low HP (finish them!)
            if ($playerHPPercent < 0.3) {
                $score += 50 * $aiLevel;
            }
            
            // Less valuable early game
            if ($gameState['turn_count'] < 3) {
                $score -= 10;
            }
        } else if (strpos($effect, 'heal') !== false) {
            preg_match('/heal:(\d+)/', $effect, $matches);
            $heal = isset($matches[1]) ? intval($matches[1]) : 0;
            
            // Only valuable when HP is low
            if ($aiHPPercent < 0.6) {
                $score += $heal * (1 - $aiHPPercent) * 10;
            } else {
                $score -= 20; // Negative value if not needed
            }
        } else if (strpos($effect, 'boost') !== false) {
            // Only valuable if we have monsters
            if ($aiFieldSize > 0) {
                $score += 15 * $aiFieldSize * $aiLevel;
            } else {
                $score -= 30; // Very negative if no monsters
            }
        } else if (strpos($effect, 'stun') !== false) {
            // Valuable against strong player board
            if ($playerFieldSize > 0) {
                $score += 20 * $playerFieldSize * $aiLevel;
            }
        }
        
        // Higher level AI uses spells more strategically
        if ($aiLevel >= 4) {
            $score += 5; // Slight preference for smart spell usage
        }
    }
    
    // Mana efficiency bonus
    $manaEfficiency = 10 - $manaCost;
    $score += $manaEfficiency;
    
    return $score;
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
        
        // Record game history with extended data
        $stmt = $conn->prepare("
            INSERT INTO game_history 
            (user_id, ai_level, result, xp_gained, turns_played, final_player_hp, final_ai_hp, deck_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            $gameState['ai_level'], 
            $result, 
            $xpGained,
            $gameState['turn_count'],
            $gameState['player_hp'],
            $gameState['ai_hp'],
            $gameState['deck_id'] ?: null
        ]);
        
        $gameHistoryId = $conn->lastInsertId();
        
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
            'unlocked_cards' => $unlockedCards,
            'game_history_id' => $gameHistoryId
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to save game result']);
    }
}
?>
<?php
require_once '../config.php';
require_once 'GameEventSystem.php';
require_once 'CardEffectRegistry.php';
require_once 'PluginSystem.php';

header('Content-Type: application/json');
requireLogin();

// Initialize event system and effect registry
GameEventSystem::initDefaultHooks();
CardEffectRegistry::init();

// Load plugins
PluginSystem::init();

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
        // If no deck_id provided, use the user's active deck (is_active = true)
        if ($deckId <= 0) {
            $stmt = $conn->prepare("SELECT id FROM user_decks WHERE user_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $activeDeck = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($activeDeck) {
            $deckId = intval($activeDeck['id']);
            }
        }

        if ($deckId > 0) {
            // Verify deck ownership (redundant if selected above, but keeps checks consistent)
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
        $gameState['player_hand'] = drawCards($gameState['available_cards'], CARDS_IN_HAND);
        
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
        
        // Initialize current_health to max health (or defense if health not set)
        $card['current_health'] = $card['health'] ?? $card['defense'];
        $card['max_health'] = $card['health'] ?? $card['defense'];
        
        $gameState['player_field'][] = $card;
        if (empty($message)) {
            $message = "Played {$card['name']} (ATK: {$card['attack']}, HP: {$card['current_health']})";
        } else {
            $message .= "(ATK: {$card['attack']}, HP: {$card['current_health']})";
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
    $battleEvents = []; // Track structured events for client-side animations
    
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
            // Attack taunt monster
            $aiMonsterIndex = null;
            foreach ($gameState['ai_field'] as $idx => $m) {
                if (isset($m['status_effects']) && in_array('taunt', $m['status_effects'])) {
                    $aiMonsterIndex = $idx;
                    break;
                }
            }
            
            if ($aiMonsterIndex !== null) {
                $aiMonster = &$gameState['ai_field'][$aiMonsterIndex];
                
                // Check for Divine Shield
                if (isset($aiMonster['status_effects']) && in_array('divine_shield', $aiMonster['status_effects'])) {
                    $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} but Divine Shield absorbs the damage!";
                    // Remove divine shield - defender still deals counter-damage
                    $aiMonster['status_effects'] = array_diff($aiMonster['status_effects'], ['divine_shield']);
                    
                    // Counter-attack damage (defender fights back)
                    $counterDamage = $aiMonster['attack'];
                    $playerMonster['current_health'] -= $counterDamage;
                    $gameState['player_field'][$i]['current_health'] = $playerMonster['current_health'];
                    $battleLog[] = "{$aiMonster['name']} deals {$counterDamage} counter-damage to {$playerMonster['name']}";
                } else {
                    // Deal damage to the monster's HP
                    $damage = $playerMonster['attack'];
                    $aiMonster['current_health'] -= $damage;
                    $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} for {$damage} damage (HP: {$aiMonster['current_health']}/{$aiMonster['max_health']})";
                    $battleEvents[] = [
                        'type' => 'damage',
                        'source' => $playerMonster['name'],
                        'target' => $aiMonster['name'],
                        'targetPlayer' => 'ai',
                        'targetIndex' => $aiMonsterIndex,
                        'amount' => $damage
                    ];
                    
                    // Counter-attack damage (both monsters damage each other)
                    $counterDamage = $aiMonster['attack'];
                    $playerMonster['current_health'] -= $counterDamage;
                    $gameState['player_field'][$i]['current_health'] = $playerMonster['current_health'];
                    $battleLog[] = "{$aiMonster['name']} deals {$counterDamage} counter-damage to {$playerMonster['name']} (HP: {$playerMonster['current_health']}/{$playerMonster['max_health']})";
                    $battleEvents[] = [
                        'type' => 'damage',
                        'source' => $aiMonster['name'],
                        'target' => $playerMonster['name'],
                        'targetPlayer' => 'player',
                        'targetIndex' => $i,
                        'amount' => $counterDamage
                    ];
                    
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
                    
                    // Check if defender is destroyed
                    if ($aiMonster['current_health'] <= 0) {
                        $battleEvents[] = [
                            'type' => 'destroyed',
                            'target' => $aiMonster['name'],
                            'targetPlayer' => 'ai',
                            'targetIndex' => $aiMonsterIndex
                        ];
                        array_splice($gameState['ai_field'], $aiMonsterIndex, 1);
                        $battleLog[] = "{$aiMonster['name']} was destroyed!";
                    }
                }
            }
        } else if (count($gameState['ai_field']) > 0) {
            // Attack first monster on field
            $aiMonster = &$gameState['ai_field'][0];
            
            // Check for Divine Shield
            if (isset($aiMonster['status_effects']) && in_array('divine_shield', $aiMonster['status_effects'])) {
                $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} but Divine Shield absorbs the damage!";
                $aiMonster['status_effects'] = array_diff($aiMonster['status_effects'], ['divine_shield']);
                
                // Counter-attack damage
                $counterDamage = $aiMonster['attack'];
                $playerMonster['current_health'] -= $counterDamage;
                $gameState['player_field'][$i]['current_health'] = $playerMonster['current_health'];
                $battleLog[] = "{$aiMonster['name']} deals {$counterDamage} counter-damage to {$playerMonster['name']}";
            } else {
                // Deal damage to the monster's HP
                $damage = $playerMonster['attack'];
                $aiMonster['current_health'] -= $damage;
                $battleLog[] = "{$playerMonster['name']} attacks {$aiMonster['name']} for {$damage} damage (HP: {$aiMonster['current_health']}/{$aiMonster['max_health']})";
                $battleEvents[] = [
                    'type' => 'damage',
                    'source' => $playerMonster['name'],
                    'target' => $aiMonster['name'],
                    'targetPlayer' => 'ai',
                    'targetIndex' => 0,
                    'amount' => $damage
                ];
                
                // Counter-attack damage (both monsters damage each other)
                $counterDamage = $aiMonster['attack'];
                $playerMonster['current_health'] -= $counterDamage;
                $gameState['player_field'][$i]['current_health'] = $playerMonster['current_health'];
                $battleLog[] = "{$aiMonster['name']} deals {$counterDamage} counter-damage to {$playerMonster['name']} (HP: {$playerMonster['current_health']}/{$playerMonster['max_health']})";
                $battleEvents[] = [
                    'type' => 'damage',
                    'source' => $aiMonster['name'],
                    'target' => $playerMonster['name'],
                    'targetPlayer' => 'player',
                    'targetIndex' => $i,
                    'amount' => $counterDamage
                ];
                
                // Apply Lifesteal
                if (isset($playerMonster['status_effects']) && in_array('lifesteal', $playerMonster['status_effects'])) {
                    $gameState['player_hp'] = min($gameState['player_hp'] + $damage, STARTING_HP);
                    $battleLog[] = "{$playerMonster['name']} heals for {$damage} HP (Lifesteal)";
                }
                
                // Check if defender is destroyed
                if ($aiMonster['current_health'] <= 0) {
                    $battleEvents[] = [
                        'type' => 'destroyed',
                        'target' => $aiMonster['name'],
                        'targetPlayer' => 'ai',
                        'targetIndex' => 0
                    ];
                    array_shift($gameState['ai_field']);
                    $battleLog[] = "{$aiMonster['name']} was destroyed!";
                }
            }
        } else {
            // Direct attack to player
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
                $aiMonster = &$gameState['ai_field'][0];
                $damage = $playerMonster['attack'];
                $aiMonster['current_health'] -= $damage;
                $battleLog[] = "{$playerMonster['name']} attacks again (Windfury) for {$damage} damage!";
                
                // Counter-attack from second Windfury attack
                $counterDamage = $aiMonster['attack'];
                $playerMonster['current_health'] -= $counterDamage;
                $gameState['player_field'][$i]['current_health'] = $playerMonster['current_health'];
                $battleLog[] = "{$aiMonster['name']} deals {$counterDamage} counter-damage";
                
                if ($aiMonster['current_health'] <= 0) {
                    array_shift($gameState['ai_field']);
                    $battleLog[] = "{$aiMonster['name']} was destroyed!";
                }
            } else {
                $gameState['ai_hp'] -= $playerMonster['attack'];
                $battleLog[] = "{$playerMonster['name']} attacks again (Windfury) for {$playerMonster['attack']} damage!";
            }
        }
    }
    
    // Clean up destroyed player monsters (killed by counter-attacks)
    for ($i = count($gameState['player_field']) - 1; $i >= 0; $i--) {
        if ($gameState['player_field'][$i]['current_health'] <= 0) {
            $destroyedMonster = $gameState['player_field'][$i];
            $battleEvents[] = [
                'type' => 'destroyed',
                'target' => $destroyedMonster['name'],
                'targetPlayer' => 'player',
                'targetIndex' => $i
            ];
            array_splice($gameState['player_field'], $i, 1);
            $battleLog[] = "{$destroyedMonster['name']} was destroyed in combat!";
        }
    }
    
    // Check if AI is already defeated after player's attack phase
    // If so, skip AI turn entirely to prevent healing/playing when already dead
    $aiActions = [];
    if ($gameState['ai_hp'] > 0) {
        // Switch to AI turn
        $gameState['turn'] = 'ai';
        
        // AI plays
        $aiResult = performAITurn($gameState);
        $aiActions = $aiResult['actions'];
        $gameState = $aiResult['gameState'];
        
        // Process AI status effects
        $battleLog = array_merge($battleLog, processStatusEffects($gameState, 'ai'));
    }
    
    // Battle phase - AI monsters attack (only if AI is still alive)
    if ($gameState['ai_hp'] > 0) {
        // Use indexed loop to track monster positions
        for ($ai_i = 0; $ai_i < count($gameState['ai_field']); $ai_i++) {
            $aiMonster = $gameState['ai_field'][$ai_i];
            
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
            // Attack taunt monster
            $playerMonsterIndex = null;
            foreach ($gameState['player_field'] as $idx => $m) {
                if (isset($m['status_effects']) && in_array('taunt', $m['status_effects'])) {
                    $playerMonsterIndex = $idx;
                    break;
                }
            }
            
            if ($playerMonsterIndex !== null) {
                $playerMonster = &$gameState['player_field'][$playerMonsterIndex];
                
                // AI monster attacks player monster - both deal damage to each other
                $damage = $aiMonster['attack'];
                $playerMonster['current_health'] -= $damage;
                $battleLog[] = "AI {$aiMonster['name']} attacks {$playerMonster['name']} for {$damage} damage (HP: {$playerMonster['current_health']}/{$playerMonster['max_health']})";
                $battleEvents[] = [
                    'type' => 'damage',
                    'source' => $aiMonster['name'],
                    'target' => $playerMonster['name'],
                    'targetPlayer' => 'player',
                    'targetIndex' => $playerMonsterIndex,
                    'amount' => $damage
                ];
                
                // Counter-attack damage
                $counterDamage = $playerMonster['attack'];
                $aiMonster['current_health'] -= $counterDamage;
                $gameState['ai_field'][$ai_i]['current_health'] = $aiMonster['current_health'];
                $battleLog[] = "{$playerMonster['name']} deals {$counterDamage} counter-damage to {$aiMonster['name']} (HP: {$aiMonster['current_health']}/{$aiMonster['max_health']})";
                $battleEvents[] = [
                    'type' => 'damage',
                    'source' => $playerMonster['name'],
                    'target' => $aiMonster['name'],
                    'targetPlayer' => 'ai',
                    'targetIndex' => $ai_i,
                    'amount' => $counterDamage
                ];
                
                // Check if player monster is destroyed
                if ($playerMonster['current_health'] <= 0) {
                    $battleEvents[] = [
                        'type' => 'destroyed',
                        'target' => $playerMonster['name'],
                        'targetPlayer' => 'player',
                        'targetIndex' => $playerMonsterIndex
                    ];
                    array_splice($gameState['player_field'], $playerMonsterIndex, 1);
                    $battleLog[] = "{$playerMonster['name']} was destroyed!";
                }
            }
        } else if (count($gameState['player_field']) > 0) {
            // Attack first monster on field
            $playerMonster = &$gameState['player_field'][0];
            
            // AI monster attacks player monster - both deal damage to each other
            $damage = $aiMonster['attack'];
            $playerMonster['current_health'] -= $damage;
            $battleLog[] = "AI {$aiMonster['name']} attacks {$playerMonster['name']} for {$damage} damage (HP: {$playerMonster['current_health']}/{$playerMonster['max_health']})";
            $battleEvents[] = [
                'type' => 'damage',
                'source' => $aiMonster['name'],
                'target' => $playerMonster['name'],
                'targetPlayer' => 'player',
                'targetIndex' => 0,
                'amount' => $damage
            ];
            
            // Counter-attack damage
            $counterDamage = $playerMonster['attack'];
            $aiMonster['current_health'] -= $counterDamage;
            $gameState['ai_field'][$ai_i]['current_health'] = $aiMonster['current_health'];
            $battleLog[] = "{$playerMonster['name']} deals {$counterDamage} counter-damage to {$aiMonster['name']} (HP: {$aiMonster['current_health']}/{$aiMonster['max_health']})";
            $battleEvents[] = [
                'type' => 'damage',
                'source' => $playerMonster['name'],
                'target' => $aiMonster['name'],
                'targetPlayer' => 'ai',
                'targetIndex' => $ai_i,
                'amount' => $counterDamage
            ];
            
            // Check if player monster is destroyed
            if ($playerMonster['current_health'] <= 0) {
                $battleEvents[] = [
                    'type' => 'destroyed',
                    'target' => $playerMonster['name'],
                    'targetPlayer' => 'player',
                    'targetIndex' => 0
                ];
                array_shift($gameState['player_field']);
                $battleLog[] = "{$playerMonster['name']} was destroyed!";
            }
        } else {
            // Direct attack to player
            $gameState['player_hp'] -= $aiMonster['attack'];
            $battleLog[] = "AI {$aiMonster['name']} attacks directly for {$aiMonster['attack']} damage";
        }
        }
    }
    
    // Clean up destroyed AI monsters (killed by counter-attacks)
    for ($i = count($gameState['ai_field']) - 1; $i >= 0; $i--) {
        if ($gameState['ai_field'][$i]['current_health'] <= 0) {
            $destroyedMonster = $gameState['ai_field'][$i];
            $battleEvents[] = [
                'type' => 'destroyed',
                'target' => $destroyedMonster['name'],
                'targetPlayer' => 'ai',
                'targetIndex' => $i
            ];
            array_splice($gameState['ai_field'], $i, 1);
            $battleLog[] = "AI {$destroyedMonster['name']} was destroyed in combat!";
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
    if ($gameState['player_hp'] <= 0 && $gameState['ai_hp'] <= 0) {
        // Both players defeated - it's a draw
        $winner = 'draw';
    } else if ($gameState['player_hp'] <= 0) {
        $winner = 'ai';
    } else if ($gameState['ai_hp'] <= 0) {
        $winner = 'player';
    }
    
    echo json_encode([
        'success' => true,
        'battle_log' => $battleLog,
        'battle_events' => $battleEvents,
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
                $monster['current_health'] -= $monster['poison_damage'];
                $log[] = "{$monster['name']} takes {$monster['poison_damage']} poison damage (HP: {$monster['current_health']}/{$monster['max_health']})";
                if ($monster['current_health'] <= 0) {
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
    
    // Lower difficulty levels get fewer cards to choose from
    $cardLimit = match($aiLevel) {
        1 => 5,   // Level 1: Very limited choices
        2 => 6,   // Level 2: Limited choices
        3 => 7,   // Level 3: Moderate choices
        default => 8  // Level 4+: Full choices
    };
    
    $stmt = $conn->prepare("SELECT * FROM cards WHERE required_level <= ? ORDER BY RAND() LIMIT ?");
    $stmt->execute([min($aiLevel * 2, 10), $cardLimit]);
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
        
        // Add randomness to lower difficulty levels to simulate mistakes
        if ($aiLevel == 1) {
            // Level 1: Very random, often plays wrong cards (±60% randomness)
            $randomFactor = mt_rand(40, 160) / 100.0;
            $score *= $randomFactor;
        } else if ($aiLevel == 2) {
            // Level 2: Some randomness (±30% randomness)
            $randomFactor = mt_rand(70, 130) / 100.0;
            $score *= $randomFactor;
        } else if ($aiLevel == 3) {
            // Level 3: Small randomness (±15% randomness)
            $randomFactor = mt_rand(85, 115) / 100.0;
            $score *= $randomFactor;
        }
        // Level 4+: No randomness, plays optimally
        
        $scoredCards[] = ['card' => $card, 'score' => $score];
    }
    
    // Sort cards by score (highest first)
    usort($scoredCards, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Play cards in priority order
    $cardsPlayed = 0;
    $maxCardsToPlay = match($aiLevel) {
        1 => 2,   // Level 1: Plays max 2 cards per turn (inefficient)
        2 => 3,   // Level 2: Plays max 3 cards per turn
        3 => 4,   // Level 3: Plays max 4 cards per turn
        default => 10  // Level 4+: Plays as many as possible
    };
    
    foreach ($scoredCards as $scoredCard) {
        if ($cardsPlayed >= $maxCardsToPlay) {
            break; // Low level AI stops playing cards early
        }
        
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
            
            // Initialize current_health to max health (or defense if health not set)
            $card['current_health'] = $card['health'] ?? $card['defense'];
            $card['max_health'] = $card['health'] ?? $card['defense'];
            
            $gameState['ai_field'][] = $card;
            $actions[] = "AI played {$card['name']} (ATK: {$card['attack']}, HP: {$card['current_health']})";
            $aiMana -= $manaCost;
            $aiFieldSize++;
            $cardsPlayed++;
            
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
            $cardsPlayed++;
            
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
    
    // Lower difficulty levels use simpler/worse evaluation
    // Level 1-2: Very basic, makes mistakes
    // Level 3: Moderate play
    // Level 4-5: Strong strategic play
    
    if ($card['type'] === 'monster') {
        $attack = intval($card['attack'] ?? 0);
        $defense = intval($card['defense'] ?? 0);
        
        // Base value: stats relative to mana cost
        $statsValue = ($attack + $defense) / max(1, $manaCost);
        $score += $statsValue * 10;
        
        // Bonus for keywords - scaled down significantly for low levels
        if (!empty($card['keywords'])) {
            $keywords = explode(',', $card['keywords']);
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                
                // Low level AI doesn't understand keyword value well
                $keywordMultiplier = match($aiLevel) {
                    1 => 0.3,  // Level 1: Barely values keywords
                    2 => 0.6,  // Level 2: Some value
                    3 => 1.0,  // Level 3: Normal value
                    4 => 1.5,  // Level 4: Higher value
                    default => 2.0  // Level 5+: Very high value
                };
                
                // Taunt is very valuable when player has strong board
                if ($keyword === 'taunt' && $playerFieldPower > 0) {
                    $score += 15 * $keywordMultiplier;
                }
                
                // Divine Shield is always good
                if ($keyword === 'divine_shield') {
                    $score += 10 * $keywordMultiplier;
                }
                
                // Lifesteal is valuable when HP is low
                if ($keyword === 'lifesteal' && $aiHPPercent < 0.7) {
                    $score += 12 * $keywordMultiplier;
                }
                
                // Charge/Rush for immediate impact
                if (in_array($keyword, ['charge', 'rush'])) {
                    $score += 8 * $keywordMultiplier;
                }
                
                // Windfury for damage
                if ($keyword === 'windfury') {
                    $score += 10 * $keywordMultiplier;
                }
            }
        }
        
        // Prioritize monsters when we need board presence
        // Lower level AI is worse at board management
        if ($aiFieldSize < $playerFieldSize) {
            if ($aiLevel >= 3) {
                $score += 20;
            } else if ($aiLevel == 2) {
                $score += 10;
            }
            // Level 1 doesn't consider board presence much
        }
        
        // Higher level AI values efficient trades
        if ($aiLevel >= 3) {
            $score += $attack * 2; // Value attack more for aggression
        } else if ($aiLevel == 2) {
            $score += $attack; // Some preference for attack
        }
        // Level 1 doesn't have attack preference
        
    } else if ($card['type'] === 'spell') {
        $effect = $card['effect'];
        
        if (strpos($effect, 'damage') !== false) {
            preg_match('/damage:(\d+)/', $effect, $matches);
            $damage = isset($matches[1]) ? intval($matches[1]) : 0;
            
            // Value damage based on opponent HP
            // Low level AI doesn't value damage spells as well
            $damageMultiplier = match($aiLevel) {
                1 => 2,   // Level 1: Undervalues damage
                2 => 3,   // Level 2: Some value
                3 => 5,   // Level 3: Normal value
                4 => 6,   // Level 4: Higher value
                default => 7  // Level 5+: Very high value
            };
            $score += $damage * $damageMultiplier;
            
            // More valuable when opponent is low HP (finish them!)
            // Low level AI is bad at calculating lethal
            if ($playerHPPercent < 0.3) {
                if ($aiLevel >= 4) {
                    $score += 50;
                } else if ($aiLevel == 3) {
                    $score += 30;
                } else if ($aiLevel == 2) {
                    $score += 15;
                }
                // Level 1 doesn't recognize lethal opportunities
            }
            
            // Less valuable early game - but low level AI doesn't know this
            if ($gameState['turn_count'] < 3 && $aiLevel >= 3) {
                $score -= 10;
            }
        } else if (strpos($effect, 'heal') !== false) {
            preg_match('/heal:(\d+)/', $effect, $matches);
            $heal = isset($matches[1]) ? intval($matches[1]) : 0;
            
            // Only valuable when HP is low
            // Low level AI uses heals inefficiently
            if ($aiLevel <= 2) {
                // Levels 1-2 overvalue healing
                if ($aiHPPercent < 0.8) {
                    $score += $heal * (1 - $aiHPPercent) * 15;
                } else {
                    $score -= 5; // Small negative
                }
            } else {
                if ($aiHPPercent < 0.6) {
                    $score += $heal * (1 - $aiHPPercent) * 10;
                } else {
                    $score -= 20; // Negative value if not needed
                }
            }
        } else if (strpos($effect, 'boost') !== false) {
            // Only valuable if we have monsters
            // Low level AI doesn't understand boost value well
            if ($aiFieldSize > 0) {
                $boostValue = match($aiLevel) {
                    1 => 5,   // Level 1: Barely values boosts
                    2 => 10,  // Level 2: Some value
                    3 => 15,  // Level 3: Normal value
                    4 => 20,  // Level 4: Higher value
                    default => 25  // Level 5+: Very high value
                };
                $score += $boostValue * $aiFieldSize;
            } else {
                $score -= 30; // Very negative if no monsters
            }
        } else if (strpos($effect, 'stun') !== false) {
            // Valuable against strong player board
            // Low level AI doesn't understand stun value
            if ($playerFieldSize > 0) {
                $stunValue = match($aiLevel) {
                    1 => 5,   // Level 1: Barely values stun
                    2 => 10,  // Level 2: Some value
                    3 => 15,  // Level 3: Normal value
                    4 => 20,  // Level 4: Higher value
                    default => 25  // Level 5+: Very high value
                };
                $score += $stunValue * $playerFieldSize;
            }
        }
        
        // Higher level AI uses spells more strategically
        if ($aiLevel >= 4) {
            $score += 5; // Slight preference for smart spell usage
        }
    }
    
    // Mana efficiency bonus - low level AI doesn't care about efficiency as much
    $manaEfficiency = 10 - $manaCost;
    if ($aiLevel >= 3) {
        $score += $manaEfficiency;
    } else if ($aiLevel == 2) {
        $score += $manaEfficiency * 0.5;
    }
    // Level 1 doesn't consider mana efficiency
    
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
        // Calculate XP based on result using new constants
        $baseXP = 0;
        if ($result === 'win') {
            $baseXP = XP_PER_WIN;
        } else if ($result === 'loss') {
            $baseXP = XP_PER_LOSS;
        } else if ($result === 'draw') {
            // Award half of win XP for a draw
            $baseXP = floor(XP_PER_WIN / 2);
        }
        
        // Apply AI level multiplier for increased difficulty
        $xpGained = $baseXP;
        if ($gameState['ai_level'] > 1) {
            $xpGained = floor($baseXP * (1 + ($gameState['ai_level'] - 1) * 0.2));
        }
        
        // Update user stats
        $stmt = $conn->prepare("SELECT level, xp FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $newXp = $user['xp'] + $xpGained;
        $newLevel = $user['level'];
        
        // Check for level up using generated requirements
        foreach ($LEVEL_REQUIREMENTS as $level => $requiredXp) {
            if ($newXp >= $requiredXp && $level > $newLevel) {
                $newLevel = $level;
            }
        }
        
        $leveledUp = $newLevel > $user['level'];
        
        // Calculate currency rewards
        $coinsEarned = 0;
        $gemsEarned = 0;
        
        if ($result === 'win') {
            // Base coin reward + AI level bonus
            $coinsEarned = 50 + ($gameState['ai_level'] * 10);
            
            // Chance for gems on win (10% base chance, +5% per AI level, capped at 100%)
            $gemChance = min(100, 10 + ($gameState['ai_level'] * 5));
            if (mt_rand(1, 100) <= $gemChance) {
                $gemsEarned = 1 + floor($gameState['ai_level'] / 2);
            }
        } else if ($result === 'loss') {
            // Small consolation coins
            $coinsEarned = 10 + ($gameState['ai_level'] * 2);
        } else if ($result === 'draw') {
            // Half of win coins for draw
            $coinsEarned = 25 + ($gameState['ai_level'] * 5);
        }
        
        // Update user with currency
        if ($result === 'win') {
            $stmt = $conn->prepare("UPDATE users SET xp = ?, level = ?, total_wins = total_wins + 1, coins = coins + ?, gems = gems + ? WHERE id = ?");
            $stmt->execute([$newXp, $newLevel, $coinsEarned, $gemsEarned, $_SESSION['user_id']]);
        } else if ($result === 'draw') {
            // Draw doesn't count as win or loss, just update XP, level, and currency
            $stmt = $conn->prepare("UPDATE users SET xp = ?, level = ?, coins = coins + ?, gems = gems + ? WHERE id = ?");
            $stmt->execute([$newXp, $newLevel, $coinsEarned, $gemsEarned, $_SESSION['user_id']]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET xp = ?, level = ?, total_losses = total_losses + 1, coins = coins + ?, gems = gems + ? WHERE id = ?");
            $stmt->execute([$newXp, $newLevel, $coinsEarned, $gemsEarned, $_SESSION['user_id']]);
        }
        
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
        
        // Trigger game end event
        GameEventSystem::trigger('game_end', [
            'user_id' => $_SESSION['user_id'],
            'result' => $result,
            'xp_gained' => $xpGained,
            'game_history_id' => $gameHistoryId,
            'ai_level' => $gameState['ai_level']
        ]);
        
        // Trigger level up event if applicable
        if ($leveledUp) {
            GameEventSystem::trigger('level_up', [
                'user_id' => $_SESSION['user_id'],
                'old_level' => $user['level'],
                'new_level' => $newLevel,
                'unlocked_cards' => $unlockedCards
            ]);
        }
        
        // Update quest progress for game completion
        updateQuestProgressForGame($result, $gameState['ai_level'], $conn);
        
        // Check for achievement unlocks
        checkAchievementsForUser($conn);
        
        unset($_SESSION['game_state']);
        
        echo json_encode([
            'success' => true,
            'result' => $result,
            'xp_gained' => $xpGained,
            'new_level' => $newLevel,
            'leveled_up' => $leveledUp,
            'unlocked_cards' => $unlockedCards,
            'game_history_id' => $gameHistoryId,
            'coins_earned' => $coinsEarned,
            'gems_earned' => $gemsEarned
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to save game result']);
    }
}

/**
 * Update quest progress after a game is completed
 */
function updateQuestProgressForGame($result, $aiLevel, $conn) {
    $userId = $_SESSION['user_id'];
    
    // Update quests for wins
    if ($result === 'win') {
        updateQuestProgressHelper($conn, $userId, 'win_games', 1);
        
        // Update quests for wins at specific AI levels
        if ($aiLevel >= 3) {
            updateQuestProgressHelper($conn, $userId, 'win_games', 1, ['ai_level' => $aiLevel]);
        }
    }
}

/**
 * Helper function to update quest progress
 */
function updateQuestProgressHelper($conn, $userId, $objectiveType, $value, $metadata = []) {
    try {
        // Find matching quests
        $sql = "SELECT q.id, q.objective_target, q.objective_metadata
                FROM quests q
                WHERE q.is_active = true 
                AND q.objective_type = ?
                AND (q.end_date IS NULL OR q.end_date > NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$objectiveType]);
        $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($quests as $quest) {
            // Check if metadata matches (if quest has specific requirements)
            if ($quest['objective_metadata']) {
                $questMeta = json_decode($quest['objective_metadata'], true);
                $matches = true;
                foreach ($questMeta as $key => $val) {
                    if (!isset($metadata[$key]) || $metadata[$key] != $val) {
                        $matches = false;
                        break;
                    }
                }
                if (!$matches) continue;
            } else {
                // Quest has no metadata requirements (e.g., general "win 3 games" quest)
                // Only update if we're not providing specific metadata to avoid double-counting
                // Example: prevents general win quests from being updated when tracking specific AI level wins
                if (!empty($metadata)) continue;
            }
            
            // Update or create progress
            $stmt = $conn->prepare("
                INSERT INTO user_quest_progress (user_id, quest_id, progress, completed)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    progress = LEAST(progress + ?, ?),
                    completed = (progress + ? >= ?)
            ");
            $completed = ($value >= $quest['objective_target']);
            $stmt->execute([
                $userId, $quest['id'], $value, $completed,
                $value, $quest['objective_target'],
                $value, $quest['objective_target']
            ]);
        }
    } catch (Exception $e) {
        error_log("Error updating quest progress: " . $e->getMessage());
    }
}

/**
 * Check and unlock achievements for the current user
 */
function checkAchievementsForUser($conn) {
    $userId = $_SESSION['user_id'];
    
    try {
        // Get user stats
        $stmt = $conn->prepare("SELECT total_wins, level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            return;
        }
        
        // Check total wins achievements
        $stmt = $conn->prepare("
            SELECT id, requirement_value, xp_reward
            FROM achievements 
            WHERE achievement_type = 'total_wins' 
            AND requirement_value <= ?
            AND id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ? AND unlocked = true)
        ");
        $stmt->execute([$stats['total_wins'], $userId]);
        while ($achievement = $stmt->fetch(PDO::FETCH_ASSOC)) {
            unlockAchievementForUser($userId, $achievement['id'], $conn);
        }
        
        // Check level achievements
        $stmt = $conn->prepare("
            SELECT id, requirement_value, xp_reward
            FROM achievements 
            WHERE achievement_type = 'level_reached' 
            AND requirement_value <= ?
            AND id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ? AND unlocked = true)
        ");
        $stmt->execute([$stats['level'], $userId]);
        while ($achievement = $stmt->fetch(PDO::FETCH_ASSOC)) {
            unlockAchievementForUser($userId, $achievement['id'], $conn);
        }
    } catch (Exception $e) {
        error_log("Error checking achievements: " . $e->getMessage());
    }
}

/**
 * Unlock an achievement for a user
 */
function unlockAchievementForUser($userId, $achievementId, $conn) {
    try {
        // Insert or update achievement
        $stmt = $conn->prepare("
            INSERT INTO user_achievements (user_id, achievement_id, unlocked, unlocked_at, notified)
            VALUES (?, ?, true, NOW(), false)
            ON DUPLICATE KEY UPDATE unlocked = true, unlocked_at = NOW()
        ");
        $stmt->execute([$userId, $achievementId]);
        
        // Award XP
        $stmt = $conn->prepare("SELECT xp_reward FROM achievements WHERE id = ?");
        $stmt->execute([$achievementId]);
        $xpReward = $stmt->fetchColumn();
        
        if ($xpReward > 0) {
            $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
            $stmt->execute([$xpReward, $userId]);
        }
        
        // Trigger event
        GameEventSystem::trigger('achievement_unlocked', [
            'user_id' => $userId,
            'achievement_id' => $achievementId,
            'xp_reward' => $xpReward
        ]);
    } catch (Exception $e) {
        error_log("Error unlocking achievement: " . $e->getMessage());
    }
}
?>
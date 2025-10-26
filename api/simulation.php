<?php
require_once '../config.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'run_simulation':
        runSimulation();
        break;
    case 'batch_simulate':
        batchSimulate();
        break;
    case 'get_simulation_results':
        getSimulationResults();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function runSimulation() {
    $deckConfigA = json_decode($_POST['deck_a'] ?? '[]', true);
    $deckConfigB = json_decode($_POST['deck_b'] ?? '[]', true);
    $iterations = intval($_POST['iterations'] ?? 1);
    
    if (empty($deckConfigA) || empty($deckConfigB)) {
        echo json_encode(['success' => false, 'error' => 'Both decks required']);
        return;
    }
    
    $results = [
        'deck_a_wins' => 0,
        'deck_b_wins' => 0,
        'draws' => 0,
        'avg_turns' => 0,
        'games' => []
    ];
    
    $totalTurns = 0;
    
    for ($i = 0; $i < $iterations; $i++) {
        $gameResult = simulateGame($deckConfigA, $deckConfigB);
        
        if ($gameResult['winner'] === 'A') {
            $results['deck_a_wins']++;
        } else if ($gameResult['winner'] === 'B') {
            $results['deck_b_wins']++;
        } else {
            $results['draws']++;
        }
        
        $totalTurns += $gameResult['turns'];
        $results['games'][] = $gameResult;
    }
    
    $results['avg_turns'] = $iterations > 0 ? round($totalTurns / $iterations, 2) : 0;
    $results['total_games'] = $iterations;
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
}

function simulateGame($deckA, $deckB) {
    $gameState = [
        'a_hp' => STARTING_HP,
        'b_hp' => STARTING_HP,
        'a_mana' => STARTING_MANA,
        'b_mana' => STARTING_MANA,
        'a_field' => [],
        'b_field' => [],
        'a_hand' => [],
        'b_hand' => [],
        'turn' => 0,
        'max_turns' => 50 // Prevent infinite games
    ];
    
    // Draw initial hands
    $gameState['a_hand'] = array_slice($deckA, 0, CARDS_IN_HAND);
    $gameState['b_hand'] = array_slice($deckB, 0, CARDS_IN_HAND);
    
    $deckARemaining = array_slice($deckA, CARDS_IN_HAND);
    $deckBRemaining = array_slice($deckB, CARDS_IN_HAND);
    
    while ($gameState['a_hp'] > 0 && $gameState['b_hp'] > 0 && $gameState['turn'] < $gameState['max_turns']) {
        $gameState['turn']++;
        
        // Player A's turn
        $gameState['a_mana'] = min(MAX_MANA, STARTING_MANA + $gameState['turn'] - 1);
        $gameState = simulateTurn($gameState, 'a', $deckARemaining);
        
        if ($gameState['b_hp'] <= 0) break;
        
        // Player B's turn
        $gameState['b_mana'] = min(MAX_MANA, STARTING_MANA + $gameState['turn'] - 1);
        $gameState = simulateTurn($gameState, 'b', $deckBRemaining);
    }
    
    $winner = null;
    if ($gameState['a_hp'] > $gameState['b_hp']) {
        $winner = 'A';
    } else if ($gameState['b_hp'] > $gameState['a_hp']) {
        $winner = 'B';
    }
    
    return [
        'winner' => $winner,
        'turns' => $gameState['turn'],
        'final_hp_a' => $gameState['a_hp'],
        'final_hp_b' => $gameState['b_hp']
    ];
}

function simulateTurn(&$gameState, $player, &$deckRemaining) {
    $opponent = $player === 'a' ? 'b' : 'a';
    
    // Draw a card
    if (count($deckRemaining) > 0 && count($gameState[$player . '_hand']) < 10) {
        $gameState[$player . '_hand'][] = array_shift($deckRemaining);
    }
    
    // Play cards from hand
    $playedThisTurn = [];
    foreach ($gameState[$player . '_hand'] as $index => $card) {
        if ($gameState[$player . '_mana'] >= ($card['mana_cost'] ?? 1)) {
            // Play the card
            $gameState[$player . '_mana'] -= ($card['mana_cost'] ?? 1);
            
            if ($card['type'] === 'monster') {
                $gameState[$player . '_field'][] = $card;
            } else if ($card['type'] === 'spell') {
                // Apply spell effect
                if (isset($card['effect'])) {
                    $gameState = applySimulatedSpell($gameState, $card, $player, $opponent);
                }
            }
            
            $playedThisTurn[] = $index;
        }
    }
    
    // Remove played cards from hand
    foreach (array_reverse($playedThisTurn) as $index) {
        array_splice($gameState[$player . '_hand'], $index, 1);
    }
    
    // Battle phase - monsters attack
    foreach ($gameState[$player . '_field'] as $i => $monster) {
        // Check for stun or freeze (simplified - not tracking status effects in simulation)
        if (count($gameState[$opponent . '_field']) > 0) {
            // Attack first enemy monster
            $target = $gameState[$opponent . '_field'][0];
            $damage = max(0, ($monster['attack'] ?? 0) - ($target['defense'] ?? 0));
            $gameState[$opponent . '_hp'] -= $damage;
            
            // Check if target is destroyed
            if (($target['defense'] ?? 0) <= ($monster['attack'] ?? 0)) {
                array_shift($gameState[$opponent . '_field']);
            }
        } else {
            // Direct attack
            $gameState[$opponent . '_hp'] -= ($monster['attack'] ?? 0);
        }
    }
    
    return $gameState;
}

function applySimulatedSpell($gameState, $card, $caster, $target) {
    if (!isset($card['effect'])) return $gameState;
    
    $parts = explode(':', $card['effect']);
    if (count($parts) < 2) return $gameState;
    
    $type = $parts[0];
    $value = intval($parts[1]);
    
    switch ($type) {
        case 'damage':
            $gameState[$target . '_hp'] -= $value;
            break;
        case 'heal':
            $gameState[$caster . '_hp'] = min($gameState[$caster . '_hp'] + $value, STARTING_HP);
            break;
        case 'boost':
            // Boost all monsters on field
            foreach ($gameState[$caster . '_field'] as &$monster) {
                $monster['attack'] = ($monster['attack'] ?? 0) + $value;
            }
            break;
    }
    
    return $gameState;
}

function batchSimulate() {
    $cardConfigs = json_decode($_POST['card_configs'] ?? '[]', true);
    $iterations = intval($_POST['iterations'] ?? 100);
    
    if (empty($cardConfigs)) {
        echo json_encode(['success' => false, 'error' => 'Card configurations required']);
        return;
    }
    
    $conn = getDBConnection();
    
    // Get actual card data
    $cardIds = array_column($cardConfigs, 'card_id');
    $placeholders = implode(',', array_fill(0, count($cardIds), '?'));
    
    try {
        $stmt = $conn->prepare("SELECT * FROM cards WHERE id IN ($placeholders)");
        $stmt->execute($cardIds);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build decks from configurations
        $decks = [];
        foreach ($cardConfigs as $config) {
            $deckCards = [];
            foreach ($config['cards'] as $cardEntry) {
                $cardData = array_filter($cards, fn($c) => $c['id'] == $cardEntry['card_id']);
                $cardData = reset($cardData);
                if ($cardData) {
                    for ($i = 0; $i < ($cardEntry['quantity'] ?? 1); $i++) {
                        $deckCards[] = $cardData;
                    }
                }
            }
            shuffle($deckCards);
            $decks[] = $deckCards;
        }
        
        // Run simulations between all deck pairs
        $allResults = [];
        for ($i = 0; $i < count($decks); $i++) {
            for ($j = $i + 1; $j < count($decks); $j++) {
                $matchupResults = [
                    'deck_a_index' => $i,
                    'deck_b_index' => $j,
                    'deck_a_wins' => 0,
                    'deck_b_wins' => 0,
                    'draws' => 0
                ];
                
                for ($k = 0; $k < $iterations; $k++) {
                    $result = simulateGame($decks[$i], $decks[$j]);
                    if ($result['winner'] === 'A') {
                        $matchupResults['deck_a_wins']++;
                    } else if ($result['winner'] === 'B') {
                        $matchupResults['deck_b_wins']++;
                    } else {
                        $matchupResults['draws']++;
                    }
                }
                
                $allResults[] = $matchupResults;
            }
        }
        
        echo json_encode([
            'success' => true,
            'results' => $allResults,
            'iterations_per_matchup' => $iterations
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to run batch simulation']);
    }
}

function getSimulationResults() {
    // This would retrieve previously saved simulation results
    // For now, just return a placeholder
    echo json_encode([
        'success' => true,
        'message' => 'No saved simulation results yet'
    ]);
}
?>

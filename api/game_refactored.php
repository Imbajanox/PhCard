<?php
require_once '../config.php';
require_once '../autoload.php';
require_once '../src/backend/utils/GameEventSystem.php';
require_once '../src/backend/utils/CardEffectRegistry.php';
require_once '../src/backend/utils/PluginSystem.php';

use Game\GameActions;
use Game\BattleSystem;
use Features\Quest;
use Features\Achievement;

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
    
    $gameActions = new GameActions();
    $result = $gameActions->start($_SESSION['user_id'], $aiLevel, $deckId);
    
    if ($result['success']) {
        $_SESSION['game_state'] = $result['full_state'];
        unset($result['full_state']); // Don't send full state to client
    }
    
    echo json_encode($result);
}

function performMulligan() {
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $cardIndices = json_decode($_POST['card_indices'] ?? '[]', true);
    $gameState = $_SESSION['game_state'];
    
    $gameActions = new GameActions();
    $result = $gameActions->performMulligan($gameState, $cardIndices);
    
    if ($result['success']) {
        $_SESSION['game_state'] = $gameState;
    }
    
    echo json_encode($result);
}

function playCard() {
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $cardIndex = intval($_POST['card_index'] ?? -1);
    $target = $_POST['target'] ?? 'opponent';
    $choice = intval($_POST['choice'] ?? 0);
    
    $gameState = $_SESSION['game_state'];
    
    $battleSystem = new BattleSystem();
    $result = $battleSystem->playCard($gameState, $cardIndex, $target, $choice);
    
    if ($result['success']) {
        $_SESSION['game_state'] = $gameState;
    }
    
    echo json_encode($result);
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
    
    $battleSystem = new BattleSystem();
    $result = $battleSystem->executeTurnBattle($gameState);
    
    $_SESSION['game_state'] = $gameState;
    
    echo json_encode([
        'success' => true,
        'battle_log' => $result['battle_log'],
        'battle_events' => $result['battle_events'],
        'ai_actions' => $result['ai_actions'],
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
        'winner' => $result['winner']
    ]);
}

function endGame() {
    if (!isset($_SESSION['game_state'])) {
        echo json_encode(['success' => false, 'error' => 'No active game']);
        return;
    }
    
    $result = $_POST['result'] ?? 'loss';
    $gameState = $_SESSION['game_state'];
    $conn = getDBConnection();
    
    $gameActions = new GameActions();
    $endResult = $gameActions->endGame($_SESSION['user_id'], $gameState, $result);
    
    if ($endResult['success']) {
        // Update quest progress for game completion
        $quest = new Quest();
        $quest->updateProgress($_SESSION['user_id'], 'play_game', 1);
        
        if ($result === 'win') {
            $quest->updateProgress($_SESSION['user_id'], 'win_game', 1);
            $quest->updateProgress($_SESSION['user_id'], 'win_game_ai_level', 1, ['ai_level' => $gameState['ai_level']]);
        }
        
        // Check for achievement unlocks
        $achievement = new Achievement();
        $achievement->checkAchievements($_SESSION['user_id']);
        
        unset($_SESSION['game_state']);
    }
    
    echo json_encode($endResult);
}

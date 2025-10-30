<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../autoload.php';
require_once '../src/backend/utils/GameEventSystem.php';

use Game\Multiplayer;

header('Content-Type: application/json');
requireLogin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$multiplayer = new Multiplayer();

switch ($action) {
    case 'create_game':
        createGame($multiplayer);
        break;
    case 'join_game':
        joinGame($multiplayer);
        break;
    case 'list_games':
        listGames($multiplayer);
        break;
    case 'get_state':
        getState($multiplayer);
        break;
    case 'play_card':
        playCard($multiplayer);
        break;
    case 'end_turn':
        endTurn($multiplayer);
        break;
    case 'surrender':
        surrender($multiplayer);
        break;
    case 'current_game':
        getCurrentGame($multiplayer);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function createGame($multiplayer) {
    $deckId = intval($_POST['deck_id'] ?? 0);
    $result = $multiplayer->createGame($_SESSION['user_id'], $deckId);
    echo json_encode($result);
}

function joinGame($multiplayer) {
    $gameId = intval($_POST['game_id'] ?? 0);
    $deckId = intval($_POST['deck_id'] ?? 0);
    
    if ($gameId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return;
    }
    
    $result = $multiplayer->joinGame($_SESSION['user_id'], $gameId, $deckId);
    echo json_encode($result);
}

function listGames($multiplayer) {
    $result = $multiplayer->listAvailableGames($_SESSION['user_id']);
    echo json_encode($result);
}

function getState($multiplayer) {
    $gameId = intval($_GET['game_id'] ?? $_POST['game_id'] ?? 0);
    
    if ($gameId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return;
    }
    
    $result = $multiplayer->getGameState($_SESSION['user_id'], $gameId);
    echo json_encode($result);
}

function playCard($multiplayer) {
    $gameId = intval($_POST['game_id'] ?? 0);
    $cardIndex = intval($_POST['card_index'] ?? -1);
    $target = $_POST['target'] ?? 'opponent';
    
    if ($gameId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return;
    }
    
    $result = $multiplayer->playCard($_SESSION['user_id'], $gameId, $cardIndex, $target);
    echo json_encode($result);
}

function endTurn($multiplayer) {
    $gameId = intval($_POST['game_id'] ?? 0);
    
    if ($gameId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return;
    }
    
    $result = $multiplayer->endTurn($_SESSION['user_id'], $gameId);
    echo json_encode($result);
}

function surrender($multiplayer) {
    $gameId = intval($_POST['game_id'] ?? 0);
    
    if ($gameId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return;
    }
    
    $result = $multiplayer->surrender($_SESSION['user_id'], $gameId);
    echo json_encode($result);
}

function getCurrentGame($multiplayer) {
    $result = $multiplayer->getCurrentGame($_SESSION['user_id']);
    echo json_encode($result);
}

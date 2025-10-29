<?php
require_once '../config.php';
require_once '../autoload.php';
require_once '../src/backend/utils/GameEventSystem.php';

use Features\Quest;
use Features\Achievement;

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$quest = new Quest();
$achievement = new Achievement();

switch ($action) {
    case 'get_active_quests':
        $result = $quest->getActiveQuests($_SESSION['user_id']);
        echo json_encode($result);
        break;
        
    case 'get_quest_progress':
        $result = $quest->getQuestProgress($_SESSION['user_id']);
        echo json_encode($result);
        break;
        
    case 'claim_quest_reward':
        $questId = intval($_POST['quest_id'] ?? 0);
        $result = $quest->claimReward($_SESSION['user_id'], $questId);
        echo json_encode($result);
        break;
        
    case 'update_quest_progress':
        $objectiveType = $_POST['objective_type'] ?? '';
        $value = intval($_POST['value'] ?? 0);
        $metadata = json_decode($_POST['metadata'] ?? '[]', true);
        $quest->updateProgress($_SESSION['user_id'], $objectiveType, $value, $metadata);
        echo json_encode(['success' => true]);
        break;
        
    case 'get_achievements':
        $result = $achievement->getAchievements();
        echo json_encode($result);
        break;
        
    case 'get_user_achievements':
        $result = $achievement->getUserAchievements($_SESSION['user_id']);
        echo json_encode($result);
        break;
        
    case 'check_achievements':
        $achievement->checkAchievements($_SESSION['user_id']);
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

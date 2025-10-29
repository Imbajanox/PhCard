<?php
require_once '../config.php';
require_once '../autoload.php';
require_once '../src/backend/utils/GameEventSystem.php';

use Features\Shop;
use Features\DailyReward;

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$shop = new Shop();
$dailyReward = new DailyReward();

switch ($action) {
    case 'get_shop_items':
        $result = $shop->getShopItems($_SESSION['user_id']);
        echo json_encode($result);
        break;
        
    case 'get_card_packs':
        $result = $shop->getCardPacks();
        echo json_encode($result);
        break;
        
    case 'purchase_card':
        $cardId = intval($_POST['card_id'] ?? 0);
        $result = $shop->purchaseCard($_SESSION['user_id'], $cardId);
        echo json_encode($result);
        break;
        
    case 'purchase_pack':
        $packId = intval($_POST['pack_id'] ?? 0);
        $result = $shop->purchasePack($_SESSION['user_id'], $packId);
        echo json_encode($result);
        break;
        
    case 'get_user_currency':
        $result = $shop->getUserCurrency($_SESSION['user_id']);
        echo json_encode($result);
        break;
        
    case 'claim_daily_login':
        $result = $dailyReward->claimDailyLogin($_SESSION['user_id']);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

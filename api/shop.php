<?php
require_once '../config.php';
require_once 'GameEventSystem.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_shop_items':
        getShopItems();
        break;
    case 'get_card_packs':
        getCardPacks();
        break;
    case 'purchase_card':
        purchaseCard();
        break;
    case 'purchase_pack':
        purchasePack();
        break;
    case 'get_user_currency':
        getUserCurrency();
        break;
    case 'claim_daily_login':
        claimDailyLogin();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getShopItems() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Get user's level for filtering
    $stmt = $conn->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userLevel = $stmt->fetchColumn();
    
    // Get available shop items
    $stmt = $conn->prepare("
        SELECT si.*, c.name, c.type, c.rarity, c.description, c.attack, c.defense
        FROM shop_items si
        JOIN cards c ON si.card_id = c.id
        WHERE si.is_active = true 
        AND si.required_level <= ?
        ORDER BY c.rarity, c.required_level, c.name
    ");
    $stmt->execute([$userLevel]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
}

function getCardPacks() {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM card_packs
        WHERE is_active = true
        ORDER BY 
            CASE pack_type
                WHEN 'starter' THEN 1
                WHEN 'standard' THEN 2
                WHEN 'premium' THEN 3
                WHEN 'legendary' THEN 4
            END
    ");
    $stmt->execute();
    $packs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'packs' => $packs]);
}

function purchaseCard() {
    $cardId = intval($_POST['card_id'] ?? 0);
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get shop item details
        $stmt = $conn->prepare("
            SELECT si.*, c.name, c.required_level
            FROM shop_items si
            JOIN cards c ON si.card_id = c.id
            WHERE si.card_id = ? AND si.is_active = true
        ");
        $stmt->execute([$cardId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception("Card not available in shop");
        }
        
        // Check user level
        $stmt = $conn->prepare("SELECT level, coins, gems FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['level'] < $item['required_level']) {
            throw new Exception("Level " . $item['required_level'] . " required");
        }
        
        // Check if user can afford it
        if ($item['price_coins'] > 0 && $user['coins'] < $item['price_coins']) {
            throw new Exception("Not enough coins");
        }
        if ($item['price_gems'] > 0 && $user['gems'] < $item['price_gems']) {
            throw new Exception("Not enough gems");
        }
        
        // Deduct currency
        $newCoins = $user['coins'] - $item['price_coins'];
        $newGems = $user['gems'] - $item['price_gems'];
        $stmt = $conn->prepare("UPDATE users SET coins = ?, gems = ? WHERE id = ?");
        $stmt->execute([$newCoins, $newGems, $userId]);
        
        // Add card to user's collection
        $stmt = $conn->prepare("
            INSERT INTO user_cards (user_id, card_id, quantity)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE quantity = quantity + 1
        ");
        $stmt->execute([$userId, $cardId]);
        
        // Record purchase
        $stmt = $conn->prepare("
            INSERT INTO purchase_history (user_id, item_type, item_id, price_coins, price_gems)
            VALUES (?, 'card', ?, ?, ?)
        ");
        $stmt->execute([$userId, $cardId, $item['price_coins'], $item['price_gems']]);
        
        $conn->commit();
        
        // Trigger event
        GameEventSystem::trigger('card_purchased', [
            'user_id' => $userId,
            'card_id' => $cardId,
            'price_coins' => $item['price_coins'],
            'price_gems' => $item['price_gems']
        ]);
        
        echo json_encode([
            'success' => true,
            'card_name' => $item['name'],
            'coins_remaining' => $newCoins,
            'gems_remaining' => $newGems
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function purchasePack() {
    $packId = intval($_POST['pack_id'] ?? 0);
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get pack details
        $stmt = $conn->prepare("SELECT * FROM card_packs WHERE id = ? AND is_active = true");
        $stmt->execute([$packId]);
        $pack = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pack) {
            throw new Exception("Pack not available");
        }
        
        // Check user currency
        $stmt = $conn->prepare("SELECT coins, gems FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pack['price_coins'] > 0 && $user['coins'] < $pack['price_coins']) {
            throw new Exception("Not enough coins");
        }
        if ($pack['price_gems'] > 0 && $user['gems'] < $pack['price_gems']) {
            throw new Exception("Not enough gems");
        }
        
        // Deduct currency
        $newCoins = $user['coins'] - $pack['price_coins'];
        $newGems = $user['gems'] - $pack['price_gems'];
        $stmt = $conn->prepare("UPDATE users SET coins = ?, gems = ? WHERE id = ?");
        $stmt->execute([$newCoins, $newGems, $userId]);
        
        // Generate pack contents
        $cards = generatePackCards($conn, $pack);
        
        // Add cards to user's collection
        foreach ($cards as $card) {
            $stmt = $conn->prepare("
                INSERT INTO user_cards (user_id, card_id, quantity)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE quantity = quantity + 1
            ");
            $stmt->execute([$userId, $card['id']]);
        }
        
        // Record purchase
        $stmt = $conn->prepare("
            INSERT INTO purchase_history (user_id, item_type, item_id, price_coins, price_gems)
            VALUES (?, 'pack', ?, ?, ?)
        ");
        $stmt->execute([$userId, $packId, $pack['price_coins'], $pack['price_gems']]);
        
        $conn->commit();
        
        // Trigger event
        GameEventSystem::trigger('pack_opened', [
            'user_id' => $userId,
            'pack_id' => $packId,
            'cards' => $cards
        ]);
        
        echo json_encode([
            'success' => true,
            'pack_name' => $pack['name'],
            'cards' => $cards,
            'coins_remaining' => $newCoins,
            'gems_remaining' => $newGems
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generatePackCards($conn, $pack) {
    $cards = [];
    $cardsNeeded = $pack['cards_per_pack'];
    
    // Get pack contents configuration
    $stmt = $conn->prepare("
        SELECT ppc.*, c.id, c.name, c.type, c.rarity, c.description, c.attack, c.defense
        FROM card_pack_contents ppc
        LEFT JOIN card_set_members csm ON ppc.set_id = csm.set_id OR ppc.set_id IS NULL
        JOIN cards c ON csm.card_id = c.id
        WHERE ppc.pack_id = ? AND c.rarity = ppc.rarity
        GROUP BY c.id, ppc.rarity, ppc.drop_weight
    ");
    $stmt->execute([$pack['id']]);
    $availableCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($availableCards)) {
        // Fallback: get all cards
        $stmt = $conn->prepare("SELECT * FROM cards ORDER BY RAND() LIMIT ?");
        $stmt->execute([$cardsNeeded]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Guarantee minimum rarity if specified
    $guaranteedAdded = false;
    if ($pack['guaranteed_rarity']) {
        $guaranteedCards = array_filter($availableCards, function($card) use ($pack) {
            return $card['rarity'] === $pack['guaranteed_rarity'] || 
                   getRarityValue($card['rarity']) >= getRarityValue($pack['guaranteed_rarity']);
        });
        
        if (!empty($guaranteedCards)) {
            $cards[] = $guaranteedCards[array_rand($guaranteedCards)];
            $cardsNeeded--;
            $guaranteedAdded = true;
        }
    }
    
    // Fill remaining slots with weighted random selection
    for ($i = 0; $i < $cardsNeeded; $i++) {
        $totalWeight = array_sum(array_column($availableCards, 'drop_weight'));
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        
        foreach ($availableCards as $card) {
            $currentWeight += $card['drop_weight'];
            if ($random <= $currentWeight) {
                $cards[] = $card;
                break;
            }
        }
    }
    
    return $cards;
}

function getRarityValue($rarity) {
    $values = ['common' => 1, 'rare' => 2, 'epic' => 3, 'legendary' => 4];
    return $values[$rarity] ?? 1;
}

function getUserCurrency() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT coins, gems FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currency = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'currency' => $currency]);
}

function claimDailyLogin() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get user's login streak
        $stmt = $conn->prepare("
            SELECT * FROM user_login_streaks WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Check if already claimed today
        $stmt = $conn->prepare("SELECT last_daily_login FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $lastLogin = $stmt->fetchColumn();
        
        if ($lastLogin === $today) {
            throw new Exception("Daily reward already claimed today");
        }
        
        // Update or create streak
        if (!$streak) {
            // First time login
            $currentStreak = 1;
            $stmt = $conn->prepare("
                INSERT INTO user_login_streaks (user_id, current_streak, longest_streak, last_login_date, total_logins)
                VALUES (?, 1, 1, ?, 1)
            ");
            $stmt->execute([$userId, $today]);
        } else {
            // Check if streak continues
            if ($streak['last_login_date'] === $yesterday) {
                $currentStreak = $streak['current_streak'] + 1;
            } else {
                $currentStreak = 1; // Streak broken
            }
            
            $longestStreak = max($currentStreak, $streak['longest_streak']);
            
            $stmt = $conn->prepare("
                UPDATE user_login_streaks 
                SET current_streak = ?, longest_streak = ?, last_login_date = ?, total_logins = total_logins + 1
                WHERE user_id = ?
            ");
            $stmt->execute([$currentStreak, $longestStreak, $today, $userId]);
        }
        
        // Get reward for current day in cycle (1-7)
        $dayInCycle = (($currentStreak - 1) % 7) + 1;
        $stmt = $conn->prepare("SELECT * FROM daily_login_rewards WHERE day_number = ?");
        $stmt->execute([$dayInCycle]);
        $reward = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reward) {
            throw new Exception("No reward configured for this day");
        }
        
        // Apply reward
        $rewardMessage = '';
        switch ($reward['reward_type']) {
            case 'coins':
                $stmt = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                $stmt->execute([$reward['reward_amount'], $userId]);
                $rewardMessage = $reward['reward_amount'] . ' coins';
                break;
            
            case 'gems':
                $stmt = $conn->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
                $stmt->execute([$reward['reward_amount'], $userId]);
                $rewardMessage = $reward['reward_amount'] . ' gems';
                break;
            
            case 'card':
                $stmt = $conn->prepare("
                    INSERT INTO user_cards (user_id, card_id, quantity)
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE quantity = quantity + 1
                ");
                $stmt->execute([$userId, $reward['reward_amount']]);
                $rewardMessage = 'a special card';
                break;
            
            case 'pack':
                // Award pack means we generate pack cards
                $stmt = $conn->prepare("SELECT * FROM card_packs WHERE id = ?");
                $stmt->execute([$reward['reward_amount']]);
                $pack = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($pack) {
                    $cards = generatePackCards($conn, $pack);
                    foreach ($cards as $card) {
                        $stmt = $conn->prepare("
                            INSERT INTO user_cards (user_id, card_id, quantity)
                            VALUES (?, ?, 1)
                            ON DUPLICATE KEY UPDATE quantity = quantity + 1
                        ");
                        $stmt->execute([$userId, $card['id']]);
                    }
                    $rewardMessage = $pack['name'];
                }
                break;
        }
        
        // Update last daily login
        $stmt = $conn->prepare("UPDATE users SET last_daily_login = ? WHERE id = ?");
        $stmt->execute([$today, $userId]);
        
        $conn->commit();
        
        // Trigger event
        GameEventSystem::trigger('daily_login_claimed', [
            'user_id' => $userId,
            'streak' => $currentStreak,
            'reward_type' => $reward['reward_type'],
            'reward_amount' => $reward['reward_amount']
        ]);
        
        echo json_encode([
            'success' => true,
            'streak' => $currentStreak,
            'reward' => $rewardMessage,
            'description' => $reward['description']
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>

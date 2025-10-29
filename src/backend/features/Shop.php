<?php

namespace Features;

use Core\Database;

/**
 * Shop handles shop operations and pack purchases
 */
class Shop {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get available shop items for a user
     */
    public function getShopItems($userId) {
        // Get user's level for filtering
        $stmt = $this->db->prepare("SELECT level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userLevel = $stmt->fetchColumn();
        
        // Get available shop items
        $stmt = $this->db->prepare("
            SELECT si.*, c.name, c.type, c.rarity, c.description, c.attack, c.defense
            FROM shop_items si
            JOIN cards c ON si.card_id = c.id
            WHERE si.is_active = true 
            AND si.required_level <= ?
            ORDER BY c.rarity, c.required_level, c.name
        ");
        $stmt->execute([$userLevel]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return ['success' => true, 'items' => $items];
    }
    
    /**
     * Get available card packs
     */
    public function getCardPacks() {
        $stmt = $this->db->prepare("
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
        $packs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return ['success' => true, 'packs' => $packs];
    }
    
    /**
     * Purchase a card from the shop
     */
    public function purchaseCard($userId, $cardId) {
        try {
            $this->db->beginTransaction();
            
            // Get shop item details
            $stmt = $this->db->prepare("
                SELECT si.*, c.name, c.required_level
                FROM shop_items si
                JOIN cards c ON si.card_id = c.id
                WHERE si.card_id = ? AND si.is_active = true
            ");
            $stmt->execute([$cardId]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new \Exception("Card not available in shop");
            }
            
            // Check user level
            $stmt = $this->db->prepare("SELECT level, coins, gems FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($user['level'] < $item['required_level']) {
                throw new \Exception("Level " . $item['required_level'] . " required");
            }
            
            // Check if user can afford it
            if ($item['price_coins'] > 0 && $user['coins'] < $item['price_coins']) {
                throw new \Exception("Not enough coins");
            }
            if ($item['price_gems'] > 0 && $user['gems'] < $item['price_gems']) {
                throw new \Exception("Not enough gems");
            }
            
            // Deduct currency
            $newCoins = $user['coins'] - $item['price_coins'];
            $newGems = $user['gems'] - $item['price_gems'];
            $stmt = $this->db->prepare("UPDATE users SET coins = ?, gems = ? WHERE id = ?");
            $stmt->execute([$newCoins, $newGems, $userId]);
            
            // Add card to user's collection
            $stmt = $this->db->prepare("
                INSERT INTO user_cards (user_id, card_id, quantity)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE quantity = quantity + 1
            ");
            $stmt->execute([$userId, $cardId]);
            
            // Record purchase
            $stmt = $this->db->prepare("
                INSERT INTO purchase_history (user_id, item_type, item_id, price_coins, price_gems)
                VALUES (?, 'card', ?, ?, ?)
            ");
            $stmt->execute([$userId, $cardId, $item['price_coins'], $item['price_gems']]);
            
            $this->db->commit();
            
            // Trigger event
            \GameEventSystem::trigger('card_purchased', [
                'user_id' => $userId,
                'card_id' => $cardId,
                'price_coins' => $item['price_coins'],
                'price_gems' => $item['price_gems']
            ]);
            
            return [
                'success' => true,
                'card_name' => $item['name'],
                'coins_remaining' => $newCoins,
                'gems_remaining' => $newGems
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Purchase a card pack
     */
    public function purchasePack($userId, $packId) {
        try {
            $this->db->beginTransaction();
            
            // Get pack details
            $stmt = $this->db->prepare("SELECT * FROM card_packs WHERE id = ? AND is_active = true");
            $stmt->execute([$packId]);
            $pack = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$pack) {
                throw new \Exception("Pack not available");
            }
            
            // Check user currency
            $stmt = $this->db->prepare("SELECT coins, gems FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($pack['price_coins'] > 0 && $user['coins'] < $pack['price_coins']) {
                throw new \Exception("Not enough coins");
            }
            if ($pack['price_gems'] > 0 && $user['gems'] < $pack['price_gems']) {
                throw new \Exception("Not enough gems");
            }
            
            // Deduct currency
            $newCoins = $user['coins'] - $pack['price_coins'];
            $newGems = $user['gems'] - $pack['price_gems'];
            $stmt = $this->db->prepare("UPDATE users SET coins = ?, gems = ? WHERE id = ?");
            $stmt->execute([$newCoins, $newGems, $userId]);
            
            // Generate pack contents
            $cards = $this->generatePackCards($pack);
            
            // Add cards to user's collection
            foreach ($cards as $card) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_cards (user_id, card_id, quantity)
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE quantity = quantity + 1
                ");
                $stmt->execute([$userId, $card['id']]);
            }
            
            // Record purchase
            $stmt = $this->db->prepare("
                INSERT INTO purchase_history (user_id, item_type, item_id, price_coins, price_gems)
                VALUES (?, 'pack', ?, ?, ?)
            ");
            $stmt->execute([$userId, $packId, $pack['price_coins'], $pack['price_gems']]);
            
            $this->db->commit();
            
            // Trigger event
            \GameEventSystem::trigger('pack_opened', [
                'user_id' => $userId,
                'pack_id' => $packId,
                'cards' => $cards
            ]);
            
            return [
                'success' => true,
                'pack_name' => $pack['name'],
                'cards' => $cards,
                'coins_remaining' => $newCoins,
                'gems_remaining' => $newGems
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate cards for a pack
     */
    private function generatePackCards($pack) {
        $cards = [];
        $cardsNeeded = $pack['cards_per_pack'];
        
        // Get pack contents configuration
        $stmt = $this->db->prepare("
            SELECT ppc.*, c.id, c.name, c.type, c.rarity, c.description, c.attack, c.defense
            FROM card_pack_contents ppc
            LEFT JOIN card_set_members csm ON ppc.set_id = csm.set_id OR ppc.set_id IS NULL
            JOIN cards c ON csm.card_id = c.id
            WHERE ppc.pack_id = ? AND c.rarity = ppc.rarity
            GROUP BY c.id, ppc.rarity, ppc.drop_weight
        ");
        $stmt->execute([$pack['id']]);
        $availableCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($availableCards)) {
            // Fallback: get all cards
            $safeLimit = max(1, min(100, intval($cardsNeeded)));
            $stmt = $this->db->prepare("SELECT * FROM cards ORDER BY RAND() LIMIT " . $safeLimit);
            $stmt->execute([]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        // Guarantee minimum rarity if specified
        if ($pack['guaranteed_rarity']) {
            $guaranteedCards = array_filter($availableCards, function($card) use ($pack) {
                return $card['rarity'] === $pack['guaranteed_rarity'] || 
                       $this->getRarityValue($card['rarity']) >= $this->getRarityValue($pack['guaranteed_rarity']);
            });
            
            if (!empty($guaranteedCards)) {
                $cards[] = $guaranteedCards[array_rand($guaranteedCards)];
                $cardsNeeded--;
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
    
    /**
     * Get rarity value for comparison
     */
    private function getRarityValue($rarity) {
        $values = ['common' => 1, 'rare' => 2, 'epic' => 3, 'legendary' => 4];
        return $values[$rarity] ?? 1;
    }
    
    /**
     * Get user currency
     */
    public function getUserCurrency($userId) {
        $stmt = $this->db->prepare("SELECT coins, gems FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currency = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return ['success' => true, 'currency' => $currency];
    }
}

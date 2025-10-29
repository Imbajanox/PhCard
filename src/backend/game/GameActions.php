<?php

namespace Game;

use Core\Database;
use Models\User;
use Models\Deck;
use Utils\GameEventSystem;

/**
 * GameActions handles game flow operations (start, mulligan, end)
 */
class GameActions {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Start a new game
     */
    public function start($userId, $aiLevel = 1, $deckId = 0) {
        try {
            $aiLevel = intval($aiLevel);
            $deckId = intval($deckId);
            
            // Get user's cards from deck or collection
            if ($deckId <= 0) {
                $stmt = $this->db->prepare("SELECT id FROM user_decks WHERE user_id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$userId]);
                $activeDeck = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($activeDeck) {
                    $deckId = intval($activeDeck['id']);
                }
            }

            $availableCards = [];
            
            if ($deckId > 0) {
                // Verify deck ownership
                $stmt = $this->db->prepare("SELECT id FROM user_decks WHERE id = ? AND user_id = ?");
                $stmt->execute([$deckId, $userId]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'error' => 'Deck not found'];
                }
                
                // Get cards from deck
                $stmt = $this->db->prepare("
                    SELECT c.*, dc.quantity 
                    FROM deck_cards dc 
                    JOIN cards c ON dc.card_id = c.id 
                    WHERE dc.deck_id = ?
                ");
                $stmt->execute([$deckId]);
                $deckCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                // Expand deck cards based on quantity
                foreach ($deckCards as $card) {
                    for ($i = 0; $i < $card['quantity']; $i++) {
                        $availableCards[] = $card;
                    }
                }
                shuffle($availableCards);
            } else {
                // Get user's cards from collection
                $stmt = $this->db->prepare("
                    SELECT c.* FROM user_cards uc 
                    JOIN cards c ON uc.card_id = c.id 
                    WHERE uc.user_id = ? AND uc.quantity > 0
                ");
                $stmt->execute([$userId]);
                $availableCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            // Initialize game state
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
            $gameState['player_hand'] = $this->drawCards($gameState['available_cards'], CARDS_IN_HAND);
            
            return [
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
                ],
                'full_state' => $gameState
            ];
        } catch(\PDOException $e) {
            return ['success' => false, 'error' => 'Failed to start game'];
        }
    }
    
    /**
     * Draw cards from available cards
     */
    public function drawCards(&$availableCards, $count) {
        $drawn = [];
        for ($i = 0; $i < $count && count($availableCards) > 0; $i++) {
            $index = array_rand($availableCards);
            $drawn[] = $availableCards[$index];
            array_splice($availableCards, $index, 1);
        }
        return $drawn;
    }
    
    /**
     * Perform mulligan
     */
    public function performMulligan(&$gameState, $cardIndices) {
        if ($gameState['mulligan_done']) {
            return ['success' => false, 'error' => 'Mulligan already used'];
        }
        
        if (count($cardIndices) > MULLIGAN_CARDS) {
            return ['success' => false, 'error' => 'Can only mulligan up to ' . MULLIGAN_CARDS . ' cards'];
        }
        
        // Put selected cards back and draw new ones
        $newHand = [];
        for ($i = 0; $i < count($gameState['player_hand']); $i++) {
            if (!in_array($i, $cardIndices)) {
                $newHand[] = $gameState['player_hand'][$i];
            }
        }
        
        // Draw replacement cards
        $replacements = $this->drawCards($gameState['available_cards'], count($cardIndices));
        $newHand = array_merge($newHand, $replacements);
        
        $gameState['player_hand'] = $newHand;
        $gameState['mulligan_done'] = true;
        
        return [
            'success' => true,
            'message' => 'Mulliganed ' . count($cardIndices) . ' cards',
            'game_state' => [
                'player_hand' => $gameState['player_hand'],
                'mulligan_available' => false
            ]
        ];
    }
    
    /**
     * End game and update user stats
     */
    public function endGame($userId, &$gameState, $result = 'loss') {
        global $LEVEL_REQUIREMENTS;
        
        try {
            // Calculate XP based on result
            $baseXP = 0;
            if ($result === 'win') {
                $baseXP = XP_PER_WIN;
            } else if ($result === 'loss') {
                $baseXP = XP_PER_LOSS;
            } else if ($result === 'draw') {
                $baseXP = floor(XP_PER_WIN / 2);
            }
            
            // Apply AI level multiplier
            $xpGained = $baseXP;
            if ($gameState['ai_level'] > 1) {
                $xpGained = floor($baseXP * (1 + ($gameState['ai_level'] - 1) * 0.2));
            }
            
            // Update user stats
            $stmt = $this->db->prepare("SELECT level, xp FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $newXp = $user['xp'] + $xpGained;
            $newLevel = $user['level'];
            
            // Check for level up
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
                $coinsEarned = 50 + ($gameState['ai_level'] * 10);
                $gemChance = min(100, 10 + ($gameState['ai_level'] * 5));
                if (mt_rand(1, 100) <= $gemChance) {
                    $gemsEarned = 1 + floor($gameState['ai_level'] / 2);
                }
            } else if ($result === 'loss') {
                $coinsEarned = 10 + ($gameState['ai_level'] * 2);
            } else if ($result === 'draw') {
                $coinsEarned = 25 + ($gameState['ai_level'] * 5);
            }
            
            // Update user with currency
            if ($result === 'win') {
                $stmt = $this->db->prepare("UPDATE users SET xp = ?, level = ?, total_wins = total_wins + 1, coins = coins + ?, gems = gems + ? WHERE id = ?");
                $stmt->execute([$newXp, $newLevel, $coinsEarned, $gemsEarned, $userId]);
            } else if ($result === 'draw') {
                $stmt = $this->db->prepare("UPDATE users SET xp = ?, level = ?, coins = coins + ?, gems = gems + ? WHERE id = ?");
                $stmt->execute([$newXp, $newLevel, $coinsEarned, $gemsEarned, $userId]);
            } else {
                $stmt = $this->db->prepare("UPDATE users SET xp = ?, level = ?, total_losses = total_losses + 1, coins = coins + ?, gems = gems + ? WHERE id = ?");
                $stmt->execute([$newXp, $newLevel, $coinsEarned, $gemsEarned, $userId]);
            }
            
            // Record game history
            $stmt = $this->db->prepare("
                INSERT INTO game_history 
                (user_id, ai_level, result, xp_gained, turns_played, final_player_hp, final_ai_hp, deck_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, 
                $gameState['ai_level'], 
                $result, 
                $xpGained,
                $gameState['turn_count'],
                $gameState['player_hp'],
                $gameState['ai_hp'],
                $gameState['deck_id'] ?: null
            ]);
            
            $gameHistoryId = $this->db->lastInsertId();
            
            // If leveled up, unlock new cards
            $unlockedCards = [];
            if ($leveledUp) {
                $stmt = $this->db->prepare("SELECT * FROM cards WHERE required_level = ?");
                $stmt->execute([$newLevel]);
                $newCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $stmt = $this->db->prepare("INSERT INTO user_cards (user_id, card_id, quantity) VALUES (?, ?, 2) ON DUPLICATE KEY UPDATE quantity = quantity + 2");
                foreach ($newCards as $card) {
                    $stmt->execute([$userId, $card['id']]);
                    $unlockedCards[] = $card;
                }
            }
            
            // Trigger game end event
            GameEventSystem::trigger('game_end', [
                'user_id' => $userId,
                'result' => $result,
                'xp_gained' => $xpGained,
                'game_history_id' => $gameHistoryId,
                'ai_level' => $gameState['ai_level']
            ]);
            
            // Trigger level up event if applicable
            if ($leveledUp) {
                GameEventSystem::trigger('level_up', [
                    'user_id' => $userId,
                    'old_level' => $user['level'],
                    'new_level' => $newLevel,
                    'unlocked_cards' => $unlockedCards
                ]);
            }
            
            return [
                'success' => true,
                'result' => $result,
                'xp_gained' => $xpGained,
                'new_level' => $newLevel,
                'leveled_up' => $leveledUp,
                'unlocked_cards' => $unlockedCards,
                'game_history_id' => $gameHistoryId,
                'coins_earned' => $coinsEarned,
                'gems_earned' => $gemsEarned
            ];
        } catch(\PDOException $e) {
            return ['success' => false, 'error' => 'Failed to save game result'];
        }
    }
}

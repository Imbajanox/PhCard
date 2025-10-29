<?php

namespace Features;

use Core\Database;
use Utils\GameEventSystem;

/**
 * DailyReward handles daily login rewards and streaks
 */
class DailyReward {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Claim daily login reward
     */
    public function claimDailyLogin($userId) {
        try {
            $this->db->beginTransaction();
            
            // Get user's login streak
            $stmt = $this->db->prepare("
                SELECT * FROM user_login_streaks WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $streak = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            // Check if already claimed today
            $stmt = $this->db->prepare("SELECT last_daily_login FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $lastLogin = $stmt->fetchColumn();
            
            if ($lastLogin === $today) {
                throw new \Exception("Daily reward already claimed today");
            }
            
            // Update or create streak
            $currentStreak = 1;
            if (!$streak) {
                // First time login
                $stmt = $this->db->prepare("
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
                
                $stmt = $this->db->prepare("
                    UPDATE user_login_streaks 
                    SET current_streak = ?, longest_streak = ?, last_login_date = ?, total_logins = total_logins + 1
                    WHERE user_id = ?
                ");
                $stmt->execute([$currentStreak, $longestStreak, $today, $userId]);
            }
            
            // Get reward for current day in cycle (1-7)
            $dayInCycle = (($currentStreak - 1) % 7) + 1;
            $stmt = $this->db->prepare("SELECT * FROM daily_login_rewards WHERE day_number = ?");
            $stmt->execute([$dayInCycle]);
            $reward = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$reward) {
                throw new \Exception("No reward configured for this day");
            }
            
            // Apply reward
            $rewardMessage = '';
            switch ($reward['reward_type']) {
                case 'coins':
                    $stmt = $this->db->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                    $stmt->execute([$reward['reward_amount'], $userId]);
                    $rewardMessage = $reward['reward_amount'] . ' coins';
                    break;
                
                case 'gems':
                    $stmt = $this->db->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
                    $stmt->execute([$reward['reward_amount'], $userId]);
                    $rewardMessage = $reward['reward_amount'] . ' gems';
                    break;
                
                case 'card':
                    $stmt = $this->db->prepare("
                        INSERT INTO user_cards (user_id, card_id, quantity)
                        VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE quantity = quantity + 1
                    ");
                    $stmt->execute([$userId, $reward['reward_amount']]);
                    $rewardMessage = 'a special card';
                    break;
                
                case 'pack':
                    // Award pack means we generate pack cards
                    $stmt = $this->db->prepare("SELECT * FROM card_packs WHERE id = ?");
                    $stmt->execute([$reward['reward_amount']]);
                    $pack = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($pack) {
                        $shop = new Shop();
                        // Access private method via reflection (not ideal, but for simplicity)
                        $reflection = new \ReflectionClass($shop);
                        $method = $reflection->getMethod('generatePackCards');
                        $method->setAccessible(true);
                        $cards = $method->invoke($shop, $pack);
                        
                        foreach ($cards as $card) {
                            $stmt = $this->db->prepare("
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
            $stmt = $this->db->prepare("UPDATE users SET last_daily_login = ? WHERE id = ?");
            $stmt->execute([$today, $userId]);
            
            $this->db->commit();
            
            // Trigger event
            GameEventSystem::trigger('daily_login_claimed', [
                'user_id' => $userId,
                'streak' => $currentStreak,
                'reward_type' => $reward['reward_type'],
                'reward_amount' => $reward['reward_amount']
            ]);
            
            return [
                'success' => true,
                'streak' => $currentStreak,
                'reward' => $rewardMessage,
                'description' => $reward['description']
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

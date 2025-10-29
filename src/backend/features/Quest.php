<?php

namespace Features;

use Core\Database;
use Utils\GameEventSystem;

/**
 * Quest manages quest system operations
 */
class Quest {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get active quests for a user
     */
    public function getActiveQuests($userId) {
        // Get user's level
        $stmt = $this->db->prepare("SELECT level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userLevel = $stmt->fetchColumn();
        
        // Get active quests for user's level
        $stmt = $this->db->prepare("
            SELECT q.*, 
                   COALESCE(uqp.progress, 0) as current_progress,
                   COALESCE(uqp.completed, false) as completed,
                   COALESCE(uqp.claimed, false) as claimed
            FROM quests q
            LEFT JOIN user_quest_progress uqp ON q.id = uqp.quest_id AND uqp.user_id = ?
            WHERE q.is_active = true 
            AND q.required_level <= ?
            AND (q.end_date IS NULL OR q.end_date > NOW())
            ORDER BY q.quest_type, q.id
        ");
        $stmt->execute([$userId, $userLevel]);
        $quests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Parse JSON metadata
        foreach ($quests as &$quest) {
            if ($quest['objective_metadata']) {
                $quest['objective_metadata'] = json_decode($quest['objective_metadata'], true);
            }
        }
        
        return ['success' => true, 'quests' => $quests];
    }
    
    /**
     * Get quest progress for a user
     */
    public function getQuestProgress($userId) {
        $stmt = $this->db->prepare("
            SELECT q.id, q.name, q.description, q.objective_type, q.objective_target,
                   uqp.progress, uqp.completed, uqp.claimed
            FROM user_quest_progress uqp
            JOIN quests q ON uqp.quest_id = q.id
            WHERE uqp.user_id = ?
            ORDER BY uqp.completed ASC, uqp.progress DESC
        ");
        $stmt->execute([$userId]);
        $progress = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return ['success' => true, 'progress' => $progress];
    }
    
    /**
     * Claim quest reward
     */
    public function claimReward($userId, $questId) {
        try {
            $this->db->beginTransaction();
            
            // Verify quest is completed and not claimed
            $stmt = $this->db->prepare("
                SELECT uqp.*, q.xp_reward, q.card_reward_id
                FROM user_quest_progress uqp
                JOIN quests q ON uqp.quest_id = q.id
                WHERE uqp.user_id = ? AND uqp.quest_id = ? AND uqp.completed = true AND uqp.claimed = false
            ");
            $stmt->execute([$userId, $questId]);
            $quest = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$quest) {
                throw new \Exception("Quest not found or already claimed");
            }
            
            // Award XP
            $stmt = $this->db->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $currentXp = intval($row['xp'] ?? 0);
            $currentLevel = max(1, intval($row['level'] ?? 1));
            $xpReward = intval($quest['xp_reward'] ?? 0);
            $newXp = $currentXp + $xpReward;

            // Determine new level
            $newLevel = $currentLevel;
            global $LEVEL_REQUIREMENTS;
            if (!empty($LEVEL_REQUIREMENTS) && is_array($LEVEL_REQUIREMENTS)) {
                $found = 1;
                foreach ($LEVEL_REQUIREMENTS as $lvl => $reqXp) {
                    if ($newXp >= intval($reqXp)) {
                        $found = max($found, intval($lvl));
                    } else {
                        break;
                    }
                }
                $newLevel = $found;
            }

            $stmt = $this->db->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
            $stmt->execute([$newXp, $newLevel, $userId]);
            
            // Award card if specified
            if ($quest['card_reward_id']) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_cards (user_id, card_id, quantity)
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE quantity = quantity + 1
                ");
                $stmt->execute([$userId, $quest['card_reward_id']]);
            }
            
            // Mark as claimed
            $stmt = $this->db->prepare("
                UPDATE user_quest_progress 
                SET claimed = true, claimed_at = NOW() 
                WHERE user_id = ? AND quest_id = ?
            ");
            $stmt->execute([$userId, $questId]);
            
            $this->db->commit();
            
            // Trigger event
            GameEventSystem::trigger('quest_claimed', [
                'user_id' => $userId,
                'quest_id' => $questId,
                'xp_reward' => $xpReward
            ]);
            
            return [
                'success' => true,
                'xp_gained' => $xpReward,
                'new_level' => $newLevel,
                'leveled_up' => $newLevel > $currentLevel
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update quest progress
     */
    public function updateProgress($userId, $objectiveType, $value, $metadata = []) {
        try {
            // Find matching quests
            $sql = "SELECT q.id, q.objective_target, q.objective_metadata
                    FROM quests q
                    WHERE q.is_active = true 
                    AND q.objective_type = ?
                    AND (q.end_date IS NULL OR q.end_date > NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$objectiveType]);
            $quests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($quests as $quest) {
                // Check if metadata matches
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
                    if (!empty($metadata)) continue;
                }
                
                // Update or create progress
                $stmt = $this->db->prepare("
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
        } catch (\Exception $e) {
            error_log("Error updating quest progress: " . $e->getMessage());
        }
    }
}

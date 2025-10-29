<?php

namespace Features;

use Core\Database;

/**
 * Achievement manages achievement system operations
 */
class Achievement {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all available achievements
     */
    public function getAchievements() {
        $stmt = $this->db->prepare("
            SELECT * FROM achievements 
            WHERE is_active = true 
            ORDER BY achievement_type, requirement_value
        ");
        $stmt->execute();
        $achievements = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return ['success' => true, 'achievements' => $achievements];
    }
    
    /**
     * Get user's achievements
     */
    public function getUserAchievements($userId) {
        $stmt = $this->db->prepare("
            SELECT a.*, ua.unlocked, ua.unlocked_at, ua.progress
            FROM achievements a
            LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
            WHERE a.is_active = true
            ORDER BY ua.unlocked DESC, a.achievement_type, a.requirement_value
        ");
        $stmt->execute([$userId]);
        $achievements = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return ['success' => true, 'achievements' => $achievements];
    }
    
    /**
     * Check and unlock achievements for a user
     */
    public function checkAchievements($userId) {
        try {
            // Get user stats
            $stmt = $this->db->prepare("SELECT total_wins, level FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$stats) {
                return;
            }
            
            // Check total wins achievements
            $stmt = $this->db->prepare("
                SELECT id, requirement_value, xp_reward
                FROM achievements 
                WHERE achievement_type = 'total_wins' 
                AND requirement_value <= ?
                AND id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ? AND unlocked = true)
            ");
            $stmt->execute([$stats['total_wins'], $userId]);
            while ($achievement = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->unlockAchievement($userId, $achievement['id']);
            }
            
            // Check level achievements
            $stmt = $this->db->prepare("
                SELECT id, requirement_value, xp_reward
                FROM achievements 
                WHERE achievement_type = 'level_reached' 
                AND requirement_value <= ?
                AND id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ? AND unlocked = true)
            ");
            $stmt->execute([$stats['level'], $userId]);
            while ($achievement = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->unlockAchievement($userId, $achievement['id']);
            }
        } catch (\Exception $e) {
            error_log("Error checking achievements: " . $e->getMessage());
        }
    }
    
    /**
     * Unlock an achievement for a user
     */
    public function unlockAchievement($userId, $achievementId) {
        try {
            // Insert or update achievement
            $stmt = $this->db->prepare("
                INSERT INTO user_achievements (user_id, achievement_id, unlocked, unlocked_at, notified)
                VALUES (?, ?, true, NOW(), false)
                ON DUPLICATE KEY UPDATE unlocked = true, unlocked_at = NOW()
            ");
            $stmt->execute([$userId, $achievementId]);
            
            // Award XP
            $stmt = $this->db->prepare("SELECT xp_reward FROM achievements WHERE id = ?");
            $stmt->execute([$achievementId]);
            $xpReward = $stmt->fetchColumn();
            
            if ($xpReward > 0) {
                $stmt = $this->db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
                $stmt->execute([$xpReward, $userId]);
            }
            
            // Trigger event
            \GameEventSystem::trigger('achievement_unlocked', [
                'user_id' => $userId,
                'achievement_id' => $achievementId,
                'xp_reward' => $xpReward
            ]);
        } catch (\Exception $e) {
            error_log("Error unlocking achievement: " . $e->getMessage());
        }
    }
}

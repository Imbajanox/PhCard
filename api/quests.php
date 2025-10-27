<?php
require_once '../config.php';
require_once 'GameEventSystem.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_active_quests':
        getActiveQuests();
        break;
    case 'get_quest_progress':
        getQuestProgress();
        break;
    case 'claim_quest_reward':
        claimQuestReward();
        break;
    case 'reset_daily_quests':
        resetDailyQuests();
        break;
    case 'reset_weekly_quests':
        resetWeeklyQuests();
        break;
    case 'check_auto_reset':
        checkAutoReset();
        break;
    case 'get_achievements':
        getAchievements();
        break;
    case 'get_user_achievements':
        getUserAchievements();
        break;
    case 'update_quest_progress':
        updateQuestProgress();
        break;
    case 'check_achievements':
        checkAchievements();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getActiveQuests() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Get user's level
    $stmt = $conn->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userLevel = $stmt->fetchColumn();
    
    // Get active quests for user's level
    $stmt = $conn->prepare("
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
    $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON metadata
    foreach ($quests as &$quest) {
        if ($quest['objective_metadata']) {
            $quest['objective_metadata'] = json_decode($quest['objective_metadata'], true);
        }
    }
    
    echo json_encode(['success' => true, 'quests' => $quests]);
}

function getQuestProgress() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT q.id, q.name, q.description, q.objective_type, q.objective_target,
               uqp.progress, uqp.completed, uqp.claimed
        FROM user_quest_progress uqp
        JOIN quests q ON uqp.quest_id = q.id
        WHERE uqp.user_id = ?
        ORDER BY uqp.completed ASC, uqp.progress DESC
    ");
    $stmt->execute([$userId]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'progress' => $progress]);
}

function claimQuestReward() {
    $questId = intval($_POST['quest_id'] ?? 0);
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        $conn->beginTransaction();
        
        // Verify quest is completed and not claimed
        $stmt = $conn->prepare("
            SELECT uqp.*, q.xp_reward, q.card_reward_id
            FROM user_quest_progress uqp
            JOIN quests q ON uqp.quest_id = q.id
            WHERE uqp.user_id = ? AND uqp.quest_id = ? AND uqp.completed = true AND uqp.claimed = false
        ");
        $stmt->execute([$userId, $questId]);
        $quest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quest) {
            throw new Exception("Quest not found or already claimed");
        }
        
        // Award XP
        // Fetch current xp and level (lock row because we're in a transaction)
        $stmt = $conn->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentXp = intval($row['xp'] ?? 0);
        $currentLevel = max(1, intval($row['level'] ?? 1));
        $xpReward = intval($quest['xp_reward'] ?? 0);
        $newXp = $currentXp + $xpReward;

        // Determine new level. Prefer a 'levels' table if present, otherwise use $LEVEL_REQUIREMENTS from config.php
        $newLevel = $currentLevel;
        try {
            $lvlStmt = $conn->prepare("SELECT level FROM levels WHERE xp_required <= ? ORDER BY level DESC LIMIT 1");
            if ($lvlStmt->execute([$newXp]) && ($lvl = $lvlStmt->fetchColumn()) !== false) {
            $newLevel = max(1, intval($lvl));
            } else {
            // Fallback to generated table from config.php
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
            } else {
                // Final fallback formula
                $newLevel = max(1, floor($newXp / 100) + 1);
            }
            }
        } catch (Exception $e) {
            // If levels table doesn't exist or query fails, fall back to config
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
            } else {
            $newLevel = max(1, floor($newXp / 100) + 1);
            }
        }

        // Apply XP (and level if increased)
        if ($newLevel > $currentLevel) {
            $updateStmt = $conn->prepare("UPDATE users SET xp = ?, level = ?, last_level_up_at = NOW() WHERE id = ?");
            $updateStmt->execute([$newXp, $newLevel, $userId]);

            // Trigger level up event
            GameEventSystem::trigger('level_up', [
            'user_id' => $userId,
            'old_level' => $currentLevel,
            'new_level' => $newLevel,
            'xp' => $newXp
            ]);
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET xp = ? WHERE id = ?");
            $updateStmt->execute([$newXp, $userId]);
        }

        // Prepare a harmless statement so the existing $stmt->execute([$quest['xp_reward'], $userId]) call later doesn't break
        $stmt = $conn->prepare("SELECT ? AS xp_delta, ? AS user_id");
        $stmt->execute([$quest['xp_reward'], $userId]);
        
        // Award card if applicable
        if ($quest['card_reward_id']) {
            $stmt = $conn->prepare("
                INSERT INTO user_cards (user_id, card_id, quantity)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE quantity = quantity + 1
            ");
            $stmt->execute([$userId, $quest['card_reward_id']]);
        }
        
        // Mark as claimed
        $stmt = $conn->prepare("
            UPDATE user_quest_progress 
            SET claimed = true, claimed_at = NOW()
            WHERE user_id = ? AND quest_id = ?
        ");
        $stmt->execute([$userId, $questId]);
        
        $conn->commit();
        
        // Trigger event
        GameEventSystem::trigger('quest_completed', [
            'user_id' => $userId,
            'quest_id' => $questId,
            'xp_reward' => $quest['xp_reward']
        ]);
        
        echo json_encode([
            'success' => true,
            'xp_gained' => $quest['xp_reward'],
            'card_reward' => $quest['card_reward_id']
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getAchievements() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT a.*,
               COALESCE(ua.progress, 0) as current_progress,
               COALESCE(ua.unlocked, false) as unlocked,
               ua.unlocked_at
        FROM achievements a
        LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
        WHERE a.is_hidden = false OR ua.unlocked = true
        ORDER BY ua.unlocked DESC, a.category, a.requirement_value
    ");
    $stmt->execute([$userId]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'achievements' => $achievements]);
}

function getUserAchievements() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT a.*, ua.progress, ua.unlocked, ua.unlocked_at, ua.notified
        FROM user_achievements ua
        JOIN achievements a ON ua.achievement_id = a.id
        WHERE ua.user_id = ? AND ua.unlocked = true
        ORDER BY ua.unlocked_at DESC
    ");
    $stmt->execute([$userId]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'achievements' => $achievements]);
}

function updateQuestProgress() {
    $questType = $_POST['objective_type'] ?? '';
    $value = intval($_POST['value'] ?? 1);
    $metadata = json_decode($_POST['metadata'] ?? '{}', true);
    
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        // Find matching quests
        $sql = "SELECT q.id, q.objective_target, q.objective_metadata
                FROM quests q
                WHERE q.is_active = true 
                AND q.objective_type = ?
                AND (q.end_date IS NULL OR q.end_date > NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$questType]);
        $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        foreach ($quests as $quest) {
            // Check if metadata matches (if quest has specific requirements)
            if ($quest['objective_metadata']) {
                $questMeta = json_decode($quest['objective_metadata'], true);
                $matches = true;
                foreach ($questMeta as $key => $val) {
                    if (!isset($metadata[$key]) || $metadata[$key] !== $val) {
                        $matches = false;
                        break;
                    }
                }
                if (!$matches) continue;
            }
            
            // Ensure integer target
            $target = intval($quest['objective_target']);
            
            // Get current progress for this user & quest
            $curStmt = $conn->prepare("SELECT progress FROM user_quest_progress WHERE user_id = ? AND quest_id = ?");
            $curStmt->execute([$userId, $quest['id']]);
            $currentProgress = intval($curStmt->fetchColumn() ?? 0);
            
            // Compute new progress and completed flag in PHP to avoid SQL ordering/boolean issues
            $newProgress = $currentProgress + $value;
            if ($newProgress > $target) {
                $newProgress = $target;
            }
            $completed = ($newProgress >= $target) ? 1 : 0;
            
            // Update or create progress (use concrete new values for both insert and update)
            $stmt = $conn->prepare("
                INSERT INTO user_quest_progress (user_id, quest_id, progress, completed)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    progress = ?,
                    completed = ?
            ");
            $stmt->execute([
                $userId, $quest['id'], $newProgress, $completed,
                $newProgress, $completed
            ]);
            $updated++;
        }
        
        echo json_encode(['success' => true, 'updated' => $updated]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function checkAchievements() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        // Get user stats
        $stmt = $conn->prepare("SELECT total_wins, level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get card collection count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM user_cards WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cardCount = $stmt->fetchColumn();
        
        // Check achievements
        $newUnlocks = [];
        
        // Total wins achievements
        $stmt = $conn->prepare("
            SELECT id, requirement_value, xp_reward
            FROM achievements 
            WHERE achievement_type = 'total_wins' 
            AND requirement_value <= ?
            AND id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ? AND unlocked = true)
        ");
        $stmt->execute([$stats['total_wins'], $userId]);
        while ($achievement = $stmt->fetch(PDO::FETCH_ASSOC)) {
            unlockAchievement($userId, $achievement['id'], $conn);
            $newUnlocks[] = $achievement;
        }
        
        // Level achievements
        $stmt = $conn->prepare("
            SELECT id, requirement_value, xp_reward
            FROM achievements 
            WHERE achievement_type = 'level_reached' 
            AND requirement_value <= ?
            AND id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ? AND unlocked = true)
        ");
        $stmt->execute([$stats['level'], $userId]);
        while ($achievement = $stmt->fetch(PDO::FETCH_ASSOC)) {
            unlockAchievement($userId, $achievement['id'], $conn);
            $newUnlocks[] = $achievement;
        }
        
        echo json_encode(['success' => true, 'new_unlocks' => $newUnlocks]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function unlockAchievement($userId, $achievementId, $conn) {
    // Insert or update achievement
    $stmt = $conn->prepare("
        INSERT INTO user_achievements (user_id, achievement_id, unlocked, unlocked_at, notified)
        VALUES (?, ?, true, NOW(), false)
        ON DUPLICATE KEY UPDATE unlocked = true, unlocked_at = NOW()
    ");
    $stmt->execute([$userId, $achievementId]);
    
    // Award XP
    $stmt = $conn->prepare("SELECT xp_reward FROM achievements WHERE id = ?");
    $stmt->execute([$achievementId]);
    $xpReward = $stmt->fetchColumn();
    
    if ($xpReward > 0) {
        $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->execute([$xpReward, $userId]);
    }
    
    // Trigger event
    GameEventSystem::trigger('achievement_unlocked', [
        'user_id' => $userId,
        'achievement_id' => $achievementId,
        'xp_reward' => $xpReward
    ]);
}

function resetDailyQuests() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        // Check if reset is allowed (once per day)
        $stmt = $conn->prepare("SELECT last_daily_reset FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $lastReset = $stmt->fetchColumn();
        
        $today = date('Y-m-d');
        if ($lastReset === $today) {
            throw new Exception("Daily quests can only be reset once per day");
        }
        
        // Delete progress for daily quests that haven't been claimed
        $stmt = $conn->prepare("
            DELETE uqp FROM user_quest_progress uqp
            JOIN quests q ON uqp.quest_id = q.id
            WHERE uqp.user_id = ? 
            AND q.quest_type = 'daily'
            AND uqp.claimed = false
        ");
        $stmt->execute([$userId]);
        $resetCount = $stmt->rowCount();
        
        // Update last reset time
        $stmt = $conn->prepare("UPDATE users SET last_daily_reset = ? WHERE id = ?");
        $stmt->execute([$today, $userId]);
        
        // Trigger event
        GameEventSystem::trigger('daily_quests_reset', [
            'user_id' => $userId,
            'reset_count' => $resetCount
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Reset $resetCount daily quest(s)",
            'reset_count' => $resetCount
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function resetWeeklyQuests() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        // Check if reset is allowed (once per week)
        $stmt = $conn->prepare("SELECT last_weekly_reset FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $lastReset = $stmt->fetchColumn();
        
        $thisWeek = date('Y-W'); // Year-Week format
        $lastResetWeek = $lastReset ? date('Y-W', strtotime($lastReset)) : null;
        
        if ($lastResetWeek === $thisWeek) {
            throw new Exception("Weekly quests can only be reset once per week");
        }
        
        // Delete progress for weekly quests that haven't been claimed
        $stmt = $conn->prepare("
            DELETE uqp FROM user_quest_progress uqp
            JOIN quests q ON uqp.quest_id = q.id
            WHERE uqp.user_id = ? 
            AND q.quest_type = 'weekly'
            AND uqp.claimed = false
        ");
        $stmt->execute([$userId]);
        $resetCount = $stmt->rowCount();
        
        // Update last reset time
        $stmt = $conn->prepare("UPDATE users SET last_weekly_reset = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Trigger event
        GameEventSystem::trigger('weekly_quests_reset', [
            'user_id' => $userId,
            'reset_count' => $resetCount
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Reset $resetCount weekly quest(s)",
            'reset_count' => $resetCount
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function checkAutoReset() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    try {
        $stmt = $conn->prepare("SELECT last_daily_reset, last_weekly_reset FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $today = date('Y-m-d');
        $thisWeek = date('Y-W');
        
        $needsDailyReset = false;
        $needsWeeklyReset = false;
        
        // Check if daily reset needed
        if (!$user['last_daily_reset'] || $user['last_daily_reset'] < $today) {
            $needsDailyReset = true;
            
            // Auto-reset unclaimed daily quests from previous days
            $stmt = $conn->prepare("
                DELETE uqp FROM user_quest_progress uqp
                JOIN quests q ON uqp.quest_id = q.id
                WHERE uqp.user_id = ? 
                AND q.quest_type = 'daily'
                AND uqp.claimed = false
                AND DATE(uqp.started_at) < ?
            ");
            $stmt->execute([$userId, $today]);
            
            $stmt = $conn->prepare("UPDATE users SET last_daily_reset = ? WHERE id = ?");
            $stmt->execute([$today, $userId]);
        }
        
        // Check if weekly reset needed
        $lastWeeklyResetWeek = $user['last_weekly_reset'] ? date('Y-W', strtotime($user['last_weekly_reset'])) : null;
        if (!$lastWeeklyResetWeek || $lastWeeklyResetWeek < $thisWeek) {
            $needsWeeklyReset = true;
            
            // Auto-reset unclaimed weekly quests from previous weeks
            $stmt = $conn->prepare("
                DELETE uqp FROM user_quest_progress uqp
                JOIN quests q ON uqp.quest_id = q.id
                WHERE uqp.user_id = ? 
                AND q.quest_type = 'weekly'
                AND uqp.claimed = false
                AND YEARWEEK(uqp.started_at) < YEARWEEK(?)
            ");
            $stmt->execute([$userId, $today]);
            
            $stmt = $conn->prepare("UPDATE users SET last_weekly_reset = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        }
        
        echo json_encode([
            'success' => true,
            'daily_reset' => $needsDailyReset,
            'weekly_reset' => $needsWeeklyReset
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>

-- Extensible Quest and Achievement System
-- Allows adding new quests and achievements without code changes

USE phcard;

-- Quest definitions table
CREATE TABLE IF NOT EXISTS quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    quest_type ENUM('daily', 'weekly', 'story', 'special') DEFAULT 'daily',
    objective_type ENUM('win_games', 'play_cards', 'deal_damage', 'heal_hp', 'play_card_type', 'use_keyword', 'reach_level', 'collect_cards', 'custom') NOT NULL,
    objective_target INT DEFAULT 1 COMMENT 'Number required to complete',
    objective_metadata JSON COMMENT 'Additional objective data (e.g., card_type, keyword_name)',
    xp_reward INT DEFAULT 50,
    card_reward_id INT DEFAULT NULL COMMENT 'Optional card reward',
    is_active BOOLEAN DEFAULT true,
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NULL COMMENT 'For limited-time quests',
    required_level INT DEFAULT 1 COMMENT 'Minimum level to see quest',
    FOREIGN KEY (card_reward_id) REFERENCES cards(id),
    INDEX idx_active (is_active, quest_type),
    INDEX idx_dates (start_date, end_date)
);

-- User quest progress tracking
CREATE TABLE IF NOT EXISTS user_quest_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quest_id INT NOT NULL,
    progress INT DEFAULT 0 COMMENT 'Current progress towards objective',
    completed BOOLEAN DEFAULT false,
    completed_at TIMESTAMP NULL,
    claimed BOOLEAN DEFAULT false COMMENT 'Whether reward has been claimed',
    claimed_at TIMESTAMP NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_quest (user_id, quest_id),
    INDEX idx_user_active (user_id, completed, claimed)
);

-- Achievement definitions table
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category ENUM('combat', 'collection', 'progression', 'special') DEFAULT 'special',
    achievement_type ENUM('win_streak', 'total_wins', 'card_collection', 'level_reached', 'damage_milestone', 'perfect_game', 'custom') NOT NULL,
    requirement_value INT DEFAULT 1 COMMENT 'Value needed to unlock',
    requirement_metadata JSON COMMENT 'Additional requirement data',
    xp_reward INT DEFAULT 100,
    card_reward_id INT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'trophy',
    is_hidden BOOLEAN DEFAULT false COMMENT 'Hidden until unlocked',
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    FOREIGN KEY (card_reward_id) REFERENCES cards(id),
    INDEX idx_category (category),
    INDEX idx_type (achievement_type)
);

-- User achievement tracking
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    progress INT DEFAULT 0,
    unlocked BOOLEAN DEFAULT false,
    unlocked_at TIMESTAMP NULL,
    notified BOOLEAN DEFAULT false COMMENT 'Whether user has been notified',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_unlocked (user_id, unlocked)
);

-- Card sets/expansions table for organizing content
CREATE TABLE IF NOT EXISTS card_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL COMMENT 'Short code like "CORE", "EXP1"',
    description TEXT,
    release_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    set_type ENUM('core', 'expansion', 'promo', 'seasonal') DEFAULT 'expansion',
    icon VARCHAR(50),
    INDEX idx_active (is_active),
    INDEX idx_type (set_type)
);

-- Link cards to sets
CREATE TABLE IF NOT EXISTS card_set_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    set_id INT NOT NULL,
    set_number INT COMMENT 'Card number within the set',
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (set_id) REFERENCES card_sets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_card_set (card_id, set_id),
    INDEX idx_set (set_id, set_number)
);

-- Insert some default quests
INSERT INTO quests (name, description, quest_type, objective_type, objective_target, xp_reward) VALUES
('First Victory', 'Win your first game', 'story', 'win_games', 1, 100),
('Daily Champion', 'Win 3 games today', 'daily', 'win_games', 3, 50),
('Spell Caster', 'Play 5 spell cards', 'daily', 'play_card_type', 5, 30),
('Monster Master', 'Play 10 monster cards', 'daily', 'play_card_type', 10, 40),
('Damage Dealer', 'Deal 5000 damage in total', 'weekly', 'deal_damage', 5000, 150),
('Healer', 'Heal 2000 HP in total', 'weekly', 'heal_hp', 2000, 100);

-- Update objective_metadata for card type quests
UPDATE quests SET objective_metadata = JSON_OBJECT('card_type', 'spell') 
WHERE name = 'Spell Caster';

UPDATE quests SET objective_metadata = JSON_OBJECT('card_type', 'monster') 
WHERE name = 'Monster Master';

-- Insert some default achievements
INSERT INTO achievements (name, description, category, achievement_type, requirement_value, xp_reward, rarity) VALUES
('First Steps', 'Win your first game', 'progression', 'total_wins', 1, 50, 'common'),
('Novice Champion', 'Win 10 games', 'progression', 'total_wins', 10, 100, 'common'),
('Veteran Champion', 'Win 50 games', 'progression', 'total_wins', 50, 300, 'rare'),
('Master Champion', 'Win 100 games', 'progression', 'total_wins', 100, 500, 'epic'),
('Legendary Champion', 'Win 500 games', 'progression', 'total_wins', 500, 1000, 'legendary'),
('Card Collector', 'Collect 10 different cards', 'collection', 'card_collection', 10, 100, 'common'),
('Card Hoarder', 'Collect all cards', 'collection', 'card_collection', 15, 500, 'legendary'),
('Damage Master', 'Deal 10000 damage in a single game', 'combat', 'damage_milestone', 10000, 200, 'epic'),
('Untouchable', 'Win a game without taking damage', 'combat', 'perfect_game', 1, 300, 'epic'),
('Level 10', 'Reach level 10', 'progression', 'level_reached', 10, 150, 'rare'),
('Level 30', 'Reach level 30', 'progression', 'level_reached', 30, 400, 'epic'),
('Level 60', 'Reach max level', 'progression', 'level_reached', 60, 1000, 'legendary');

-- Insert core card set
INSERT INTO card_sets (name, code, description, set_type) VALUES
('Core Set', 'CORE', 'The original set of cards', 'core');

-- Link existing cards to core set
INSERT INTO card_set_members (card_id, set_id, set_number)
SELECT id, (SELECT id FROM card_sets WHERE code = 'CORE'), ROW_NUMBER() OVER (ORDER BY id)
FROM cards;

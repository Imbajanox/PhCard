-- Quest Reset and Card Shop System
-- Adds functionality for resetting quests and purchasing cards

USE phcard;

-- Add currency and quest reset tracking to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS coins INT DEFAULT 1000 COMMENT 'In-game currency for shop',
ADD COLUMN IF NOT EXISTS gems INT DEFAULT 50 COMMENT 'Premium currency',
ADD COLUMN IF NOT EXISTS last_daily_reset TIMESTAMP NULL COMMENT 'Last time daily quests were reset',
ADD COLUMN IF NOT EXISTS last_weekly_reset TIMESTAMP NULL COMMENT 'Last time weekly quests were reset',
ADD COLUMN IF NOT EXISTS last_daily_login DATE NULL COMMENT 'Last date user logged in for daily rewards';

-- Card shop inventory table
CREATE TABLE IF NOT EXISTS shop_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    price_coins INT DEFAULT 0 COMMENT 'Price in coins',
    price_gems INT DEFAULT 0 COMMENT 'Price in gems',
    stock_limit INT DEFAULT NULL COMMENT 'NULL = unlimited stock',
    is_active BOOLEAN DEFAULT true,
    required_level INT DEFAULT 1,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    INDEX idx_active (is_active, required_level)
);

-- Card pack definitions
CREATE TABLE IF NOT EXISTS card_packs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price_coins INT DEFAULT 0,
    price_gems INT DEFAULT 0,
    cards_per_pack INT DEFAULT 5,
    guaranteed_rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT NULL COMMENT 'Guaranteed minimum rarity',
    pack_type ENUM('starter', 'standard', 'premium', 'legendary') DEFAULT 'standard',
    is_active BOOLEAN DEFAULT true,
    INDEX idx_active (is_active)
);

-- Card pack contents (defines which card sets/rarities can appear)
CREATE TABLE IF NOT EXISTS card_pack_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pack_id INT NOT NULL,
    set_id INT NULL COMMENT 'NULL = all sets',
    rarity ENUM('common', 'rare', 'epic', 'legendary') NOT NULL,
    drop_weight INT DEFAULT 100 COMMENT 'Relative probability',
    FOREIGN KEY (pack_id) REFERENCES card_packs(id) ON DELETE CASCADE,
    FOREIGN KEY (set_id) REFERENCES card_sets(id) ON DELETE CASCADE
);

-- Purchase history
CREATE TABLE IF NOT EXISTS purchase_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_type ENUM('card', 'pack') NOT NULL,
    item_id INT NOT NULL COMMENT 'card_id or pack_id',
    price_coins INT DEFAULT 0,
    price_gems INT DEFAULT 0,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_purchases (user_id, purchased_at)
);

-- Daily login rewards
CREATE TABLE IF NOT EXISTS daily_login_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_number INT NOT NULL COMMENT 'Day 1, 2, 3, etc.',
    reward_type ENUM('coins', 'gems', 'card', 'pack') NOT NULL,
    reward_amount INT DEFAULT 0 COMMENT 'Amount of coins/gems or card_id/pack_id',
    description VARCHAR(255),
    UNIQUE KEY unique_day (day_number)
);

-- User daily login streak tracking
CREATE TABLE IF NOT EXISTS user_login_streaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    last_login_date DATE NOT NULL,
    total_logins INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);

-- Insert default card packs
INSERT INTO card_packs (name, description, price_coins, price_gems, cards_per_pack, guaranteed_rarity, pack_type) VALUES
('Starter Pack', 'A basic pack with 5 cards, great for beginners', 100, 0, 5, NULL, 'starter'),
('Standard Pack', 'A standard pack with 5 cards and guaranteed rare+', 500, 0, 5, 'rare', 'standard'),
('Premium Pack', 'A premium pack with 7 cards and guaranteed epic+', 0, 10, 7, 'epic', 'premium'),
('Legendary Pack', 'An exclusive pack with 10 cards and guaranteed legendary', 0, 25, 10, 'legendary', 'legendary');

-- Set up pack contents (allow all rarities from core set for now)
INSERT INTO card_pack_contents (pack_id, set_id, rarity, drop_weight) 
SELECT p.id, s.id, 'common', 100
FROM card_packs p, card_sets s
WHERE s.code = 'CORE';

INSERT INTO card_pack_contents (pack_id, set_id, rarity, drop_weight) 
SELECT p.id, s.id, 'rare', 25
FROM card_packs p, card_sets s
WHERE s.code = 'CORE' AND p.pack_type IN ('standard', 'premium', 'legendary');

INSERT INTO card_pack_contents (pack_id, set_id, rarity, drop_weight) 
SELECT p.id, s.id, 'epic', 10
FROM card_packs p, card_sets s
WHERE s.code = 'CORE' AND p.pack_type IN ('premium', 'legendary');

INSERT INTO card_pack_contents (pack_id, set_id, rarity, drop_weight) 
SELECT p.id, s.id, 'legendary', 5
FROM card_packs p, card_sets s
WHERE s.code = 'CORE' AND p.pack_type = 'legendary';

-- Populate shop with some cards (make common/rare cards available for purchase)
INSERT INTO shop_items (card_id, price_coins, price_gems, required_level)
SELECT id, 
    CASE 
        WHEN rarity = 'common' THEN 200
        WHEN rarity = 'rare' THEN 500
        WHEN rarity = 'epic' THEN 1500
        WHEN rarity = 'legendary' THEN 5000
    END as price_coins,
    CASE 
        WHEN rarity = 'epic' THEN 5
        WHEN rarity = 'legendary' THEN 15
        ELSE 0
    END as price_gems,
    required_level
FROM cards
WHERE rarity IN ('common', 'rare');

-- Insert daily login rewards (7-day cycle)
INSERT INTO daily_login_rewards (day_number, reward_type, reward_amount, description) VALUES
(1, 'coins', 100, 'Welcome! Here are 100 coins'),
(2, 'coins', 150, 'Day 2 bonus: 150 coins'),
(3, 'gems', 5, 'Day 3 bonus: 5 gems'),
(4, 'coins', 200, 'Day 4 bonus: 200 coins'),
(5, 'coins', 300, 'Day 5 bonus: 300 coins'),
(6, 'gems', 10, 'Day 6 bonus: 10 gems'),
(7, 'pack', 2, 'Week complete! Standard Pack reward');

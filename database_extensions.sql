-- Database Extensions for Advanced Card Mechanics
-- This file extends the base schema with new tables and columns

USE phcard;

-- Add new columns to cards table for advanced mechanics
ALTER TABLE cards 
ADD COLUMN keywords VARCHAR(255) DEFAULT NULL COMMENT 'Comma-separated keywords: charge,taunt,rush,lifesteal,divine_shield,windfury,stealth,poison,overload',
ADD COLUMN mana_cost INT DEFAULT 1 COMMENT 'Mana cost to play the card',
ADD COLUMN overload INT DEFAULT 0 COMMENT 'Overload amount for next turn',
ADD COLUMN card_class ENUM('neutral', 'warrior', 'mage', 'rogue', 'priest', 'paladin') DEFAULT 'neutral',
ADD COLUMN choice_effects TEXT DEFAULT NULL COMMENT 'JSON array of choice effects for Choose One cards';

-- Create table for card status effects
CREATE TABLE IF NOT EXISTS card_status_effects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT 'stun, poison, burn, freeze, etc.',
    description TEXT,
    duration_type ENUM('permanent', 'turns', 'until_triggered') DEFAULT 'turns',
    is_debuff BOOLEAN DEFAULT true
);

-- Insert common status effects
INSERT INTO card_status_effects (name, description, duration_type, is_debuff) VALUES
('stun', 'Cannot attack for 1 turn', 'turns', true),
('poison', 'Takes damage at the end of each turn', 'turns', true),
('burn', 'Takes increasing damage each turn', 'turns', true),
('freeze', 'Cannot attack or use abilities', 'turns', true),
('taunt', 'Must be attacked first', 'permanent', false),
('divine_shield', 'Prevents the next damage', 'until_triggered', false),
('stealth', 'Cannot be targeted until attacks', 'until_triggered', false),
('windfury', 'Can attack twice per turn', 'permanent', false),
('lifesteal', 'Heals for damage dealt', 'permanent', false);

-- Create deck archetypes table
CREATE TABLE IF NOT EXISTS deck_archetypes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    preferred_keywords VARCHAR(255),
    min_cards INT DEFAULT 30,
    max_cards INT DEFAULT 30,
    max_duplicates INT DEFAULT 2
);

-- Insert deck archetypes
INSERT INTO deck_archetypes (name, description, preferred_keywords, min_cards, max_cards, max_duplicates) VALUES
('Aggro', 'Fast, aggressive deck focused on early damage', 'charge,rush', 30, 30, 2),
('Control', 'Slow deck focused on board control and late game', 'taunt,lifesteal', 30, 30, 2),
('Combo', 'Deck built around specific card combinations', '', 30, 30, 2),
('Midrange', 'Balanced deck with good tempo', 'rush,taunt', 30, 30, 2),
('Tempo', 'Maintains board control through efficient trades', 'rush', 30, 30, 2);

-- Create user decks table
CREATE TABLE IF NOT EXISTS user_decks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    archetype_id INT,
    card_class ENUM('neutral', 'warrior', 'mage', 'rogue', 'priest', 'paladin') DEFAULT 'neutral',
    is_active BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (archetype_id) REFERENCES deck_archetypes(id)
);

-- Create deck cards table (which cards are in which deck)
CREATE TABLE IF NOT EXISTS deck_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deck_id INT NOT NULL,
    card_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (deck_id) REFERENCES user_decks(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id),
    UNIQUE KEY unique_deck_card (deck_id, card_id)
);

-- Create game telemetry table for balance analysis
CREATE TABLE IF NOT EXISTS game_telemetry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT,
    user_id INT NOT NULL,
    card_id INT,
    event_type ENUM('card_played', 'card_drawn', 'damage_dealt', 'healing_done', 'monster_destroyed', 'effect_triggered') NOT NULL,
    turn_number INT,
    player_hp INT,
    opponent_hp INT,
    metadata JSON COMMENT 'Additional data like damage amount, target, etc.',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES game_history(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id),
    INDEX idx_card_event (card_id, event_type),
    INDEX idx_game (game_id),
    INDEX idx_timestamp (timestamp)
);

-- Create card balance metrics table
CREATE TABLE IF NOT EXISTS card_balance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL UNIQUE,
    times_played INT DEFAULT 0,
    times_in_winning_deck INT DEFAULT 0,
    times_in_losing_deck INT DEFAULT 0,
    total_damage_dealt BIGINT DEFAULT 0,
    total_healing_done BIGINT DEFAULT 0,
    avg_turn_played DECIMAL(5,2) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    INDEX idx_winrate (times_in_winning_deck, times_in_losing_deck)
);

-- Initialize metrics for all existing cards
INSERT INTO card_balance_metrics (card_id)
SELECT id FROM cards
ON DUPLICATE KEY UPDATE card_id=card_id;

-- Create A/B test configurations table
CREATE TABLE IF NOT EXISTS ab_test_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    card_id INT,
    variant_a_config JSON COMMENT 'Original card stats',
    variant_b_config JSON COMMENT 'Modified card stats',
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT true,
    FOREIGN KEY (card_id) REFERENCES cards(id)
);

-- Create A/B test results table
CREATE TABLE IF NOT EXISTS ab_test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    user_id INT NOT NULL,
    variant ENUM('A', 'B') NOT NULL,
    game_result ENUM('win', 'loss') NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES ab_test_configs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_test_variant (test_id, variant)
);

-- Update existing cards with new mechanics
UPDATE cards SET 
    keywords = 'charge',
    mana_cost = 1
WHERE name = 'Goblin Scout';

UPDATE cards SET 
    keywords = 'rush',
    mana_cost = 2
WHERE name = 'Forest Wolf';

UPDATE cards SET 
    keywords = 'taunt',
    mana_cost = 3
WHERE name = 'Stone Golem';

UPDATE cards SET 
    mana_cost = 1
WHERE name = 'Heal';

UPDATE cards SET 
    mana_cost = 2
WHERE name = 'Fireball';

UPDATE cards SET 
    keywords = 'taunt,lifesteal',
    mana_cost = 5
WHERE name = 'Dark Knight';

UPDATE cards SET 
    keywords = 'windfury',
    mana_cost = 6
WHERE name = 'Ice Dragon';

UPDATE cards SET 
    mana_cost = 4
WHERE name = 'Lightning Strike';

UPDATE cards SET 
    keywords = 'divine_shield',
    mana_cost = 8,
    description = 'Ein mächtiger Phönix mit göttlichem Schild'
WHERE name = 'Phoenix';

UPDATE cards SET 
    keywords = 'stealth,poison',
    mana_cost = 7
WHERE name = 'Shadow Assassin';

UPDATE cards SET 
    mana_cost = 3,
    overload = 2
WHERE name = 'Power Boost';

UPDATE cards SET 
    keywords = 'taunt',
    mana_cost = 10
WHERE name = 'Titan of Destruction';

UPDATE cards SET 
    keywords = 'windfury,lifesteal',
    mana_cost = 12
WHERE name = 'Ancient Dragon';

UPDATE cards SET 
    mana_cost = 8,
    overload = 3
WHERE name = 'Meteor Storm';

UPDATE cards SET 
    mana_cost = 6
WHERE name = 'Divine Shield';

-- Add new cards with advanced mechanics
INSERT INTO cards (name, type, attack, defense, effect, required_level, rarity, description, keywords, mana_cost, choice_effects) VALUES
-- Choice cards
('Druid of the Flame', 'monster', 500, 500, NULL, 3, 'rare', 'Wähle: Verwandlung in 5/2 Angriff ODER 2/5 Verteidigung', 'charge', 3, '{"choices": [{"name": "Flame Form", "attack": 500, "defense": 200}, {"name": "Bear Form", "attack": 200, "defense": 500}]}'),

-- Overload cards
('Chain Lightning', 'spell', 0, 0, 'damage:800', 4, 'rare', 'Fügt 800 Schaden zu. Overload: (2)', NULL, 3, NULL),

-- Combo synergy cards
('Combo Master', 'monster', 300, 300, 'combo_boost:200', 4, 'rare', 'Erhält +200 ATK für jede gespielte Karte diese Runde', NULL, 3, NULL),

-- Poison cards
('Venomous Spider', 'monster', 200, 100, 'poison:2', 2, 'common', 'Vergiftet den Gegner für 2 Runden', 'poison', 2, NULL),

-- Stun cards
('Thunder Bolt', 'spell', 0, 0, 'stun:1', 3, 'rare', 'Betäubt alle gegnerischen Monster für 1 Runde', NULL, 4, NULL);

-- Update game_history to track more details
ALTER TABLE game_history
ADD COLUMN turns_played INT DEFAULT 0,
ADD COLUMN cards_played INT DEFAULT 0,
ADD COLUMN final_player_hp INT DEFAULT 0,
ADD COLUMN final_ai_hp INT DEFAULT 0,
ADD COLUMN deck_id INT DEFAULT NULL,
ADD COLUMN telemetry_recorded BOOLEAN DEFAULT false,
ADD FOREIGN KEY (deck_id) REFERENCES user_decks(id) ON DELETE SET NULL;

-- PhCard Database Schema
-- Create database for the card game

CREATE DATABASE IF NOT EXISTS phcard;
USE phcard;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    total_wins INT DEFAULT 0,
    total_losses INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cards table
CREATE TABLE IF NOT EXISTS cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('monster', 'spell') NOT NULL,
    attack INT DEFAULT 0,
    defense INT DEFAULT 0,
    effect VARCHAR(255),
    required_level INT DEFAULT 1,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    description TEXT
);

-- User cards table (cards owned by users)
CREATE TABLE IF NOT EXISTS user_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_card (user_id, card_id)
);

-- Game history table
CREATE TABLE IF NOT EXISTS game_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ai_level INT NOT NULL,
    result ENUM('win', 'loss') NOT NULL,
    xp_gained INT DEFAULT 0,
    duration_seconds INT,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert starter cards
INSERT INTO cards (name, type, attack, defense, effect, required_level, rarity, description) VALUES
-- Starter Monster Cards (Level 1)
('Goblin Scout', 'monster', 200, 100, NULL, 1, 'common', 'Ein schwacher Goblin-Späher'),
('Forest Wolf', 'monster', 250, 150, NULL, 1, 'common', 'Ein wilder Wolf aus dem Wald'),
('Stone Golem', 'monster', 150, 300, NULL, 1, 'common', 'Ein robuster Steingolem'),

-- Starter Spell Cards (Level 1)
('Heal', 'spell', 0, 0, 'heal:300', 1, 'common', 'Heilt 300 Lebenspunkte'),
('Fireball', 'spell', 400, 0, 'damage:400', 1, 'common', 'Verursacht 400 Schaden'),

-- Level 3 Cards
('Dark Knight', 'monster', 500, 400, NULL, 3, 'rare', 'Ein dunkler Ritter mit starker Verteidigung'),
('Ice Dragon', 'monster', 700, 300, NULL, 3, 'rare', 'Ein eisiger Drache'),
('Lightning Strike', 'spell', 0, 0, 'damage:600', 3, 'rare', 'Verursacht 600 Schaden'),

-- Level 5 Cards
('Phoenix', 'monster', 900, 500, 'revive', 5, 'epic', 'Kann einmal pro Spiel wiederbeleben'),
('Shadow Assassin', 'monster', 1100, 200, 'pierce', 5, 'epic', 'Ignoriert Verteidigung'),
('Power Boost', 'spell', 0, 0, 'boost:500', 5, 'epic', 'Erhöht Angriff um 500'),

-- Level 8 Cards
('Titan of Destruction', 'monster', 1500, 800, NULL, 8, 'legendary', 'Ein mächtiger Titan'),
('Ancient Dragon', 'monster', 1800, 600, NULL, 8, 'legendary', 'Der mächtigste Drache'),
('Meteor Storm', 'spell', 0, 0, 'damage:1200', 8, 'legendary', 'Verursacht 1200 Schaden'),
('Divine Shield', 'spell', 0, 0, 'shield:1000', 8, 'legendary', 'Gewährt 1000 Verteidigung');

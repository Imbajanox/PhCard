-- PhCard Multiplayer System
-- Database schema for player vs player functionality

USE phcard;

-- Multiplayer game rooms
CREATE TABLE IF NOT EXISTS multiplayer_games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player1_id INT NOT NULL,
    player2_id INT,
    status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
    current_turn INT, -- References player1_id or player2_id
    game_state LONGTEXT, -- JSON encoded game state
    winner_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_last_activity (last_activity)
);

-- Multiplayer game moves/actions log
CREATE TABLE IF NOT EXISTS multiplayer_moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    player_id INT NOT NULL,
    move_type ENUM('play_card', 'end_turn', 'surrender') NOT NULL,
    move_data TEXT, -- JSON encoded move details
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES multiplayer_games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_game_id (game_id)
);

-- Multiplayer statistics
CREATE TABLE IF NOT EXISTS multiplayer_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    games_lost INT DEFAULT 0,
    games_drawn INT DEFAULT 0,
    rating INT DEFAULT 1000,
    highest_rating INT DEFAULT 1000,
    win_streak INT DEFAULT 0,
    longest_win_streak INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

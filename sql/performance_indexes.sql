-- Performance Optimization Indexes for PhCard
-- This file adds indexes to improve query performance across the application

USE phcard;

-- Add indexes to user_cards table for faster lookups
ALTER TABLE user_cards 
    ADD INDEX idx_user_id (user_id),
    ADD INDEX idx_card_id (card_id);

-- Add indexes to game_history table for faster statistics queries
ALTER TABLE game_history 
    ADD INDEX idx_user_id (user_id),
    ADD INDEX idx_ai_level (ai_level),
    ADD INDEX idx_result (result),
    ADD INDEX idx_played_at (played_at),
    ADD INDEX idx_user_result (user_id, result);

-- Add indexes to deck_cards table for faster deck loading
ALTER TABLE deck_cards 
    ADD INDEX idx_deck_id (deck_id),
    ADD INDEX idx_card_id (card_id);

-- Add indexes to user_decks table for faster deck queries
ALTER TABLE user_decks 
    ADD INDEX idx_user_id (user_id),
    ADD INDEX idx_is_active (is_active),
    ADD INDEX idx_user_active (user_id, is_active);

-- Add indexes to cards table for faster filtering
ALTER TABLE cards 
    ADD INDEX idx_required_level (required_level),
    ADD INDEX idx_type (type),
    ADD INDEX idx_rarity (rarity),
    ADD INDEX idx_level_type (required_level, type);

-- Add indexes to multiplayer_games for faster game listing
-- Check if indexes already exist before adding
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'multiplayer_games' 
               AND index_name = 'idx_player1_id');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Index idx_player1_id already exists"', 
    'ALTER TABLE multiplayer_games ADD INDEX idx_player1_id (player1_id)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'multiplayer_games' 
               AND index_name = 'idx_player2_id');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Index idx_player2_id already exists"', 
    'ALTER TABLE multiplayer_games ADD INDEX idx_player2_id (player2_id)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add composite index for multiplayer game actions
-- Check if table exists first
SET @exist := (SELECT COUNT(*) FROM information_schema.tables 
               WHERE table_schema = DATABASE() 
               AND table_name = 'multiplayer_game_actions');

SET @sqlstmt := IF(@exist > 0, 
    'SET @idx_exist := (SELECT COUNT(*) FROM information_schema.statistics 
                        WHERE table_schema = DATABASE() 
                        AND table_name = "multiplayer_game_actions" 
                        AND index_name = "idx_game_turn")', 
    'SELECT "Table multiplayer_game_actions does not exist"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for quest system if it exists
SET @exist := (SELECT COUNT(*) FROM information_schema.tables 
               WHERE table_schema = DATABASE() 
               AND table_name = 'user_quests');

SET @sqlstmt := IF(@exist > 0, 
    'SET @idx_exist := (SELECT COUNT(*) FROM information_schema.statistics 
                        WHERE table_schema = DATABASE() 
                        AND table_name = "user_quests" 
                        AND index_name = "idx_user_status"); 
     SET @add_idx := IF(@idx_exist = 0, 
         "ALTER TABLE user_quests ADD INDEX idx_user_status (user_id, status)", 
         "SELECT \'Index idx_user_status already exists\'"); 
     PREPARE stmt2 FROM @add_idx; 
     EXECUTE stmt2; 
     DEALLOCATE PREPARE stmt2', 
    'SELECT "Table user_quests does not exist"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

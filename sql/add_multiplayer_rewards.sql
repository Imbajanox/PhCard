-- Add rewards columns to multiplayer_games table
-- This allows rewards to be stored with the game and retrieved later

USE phcard;

ALTER TABLE multiplayer_games 
ADD COLUMN IF NOT EXISTS player1_rewards TEXT COMMENT 'JSON encoded rewards for player 1',
ADD COLUMN IF NOT EXISTS player2_rewards TEXT COMMENT 'JSON encoded rewards for player 2';

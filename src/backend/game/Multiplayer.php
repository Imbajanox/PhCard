<?php

namespace Game;

use Core\Database;
use Utils\GameEventSystem;

/**
 * Multiplayer game management
 * Handles creating, joining, and managing PvP games
 */
class Multiplayer {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new multiplayer game room
     */
    public function createGame($userId, $deckId = 0) {
        try {
            // Check if user already has an active game
            $stmt = $this->db->prepare("
                SELECT id FROM multiplayer_games 
                WHERE (player1_id = ? OR player2_id = ?) 
                AND status IN ('waiting', 'active')
            ");
            $stmt->execute([$userId, $userId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'You already have an active game'];
            }
            
            // Create new game room
            $stmt = $this->db->prepare("
                INSERT INTO multiplayer_games (player1_id, status, game_state) 
                VALUES (?, 'waiting', '{}')
            ");
            $stmt->execute([$userId]);
            $gameId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'game_id' => $gameId,
                'message' => 'Game room created. Waiting for opponent...'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to create game: ' . $e->getMessage()];
        }
    }
    
    /**
     * Join an existing multiplayer game
     */
    public function joinGame($userId, $gameId, $deckId = 0) {
        try {
            // Check if user already has an active game
            $stmt = $this->db->prepare("
                SELECT id FROM multiplayer_games 
                WHERE (player1_id = ? OR player2_id = ?) 
                AND status IN ('waiting', 'active')
            ");
            $stmt->execute([$userId, $userId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'You already have an active game'];
            }
            
            // Get game room
            $stmt = $this->db->prepare("
                SELECT * FROM multiplayer_games 
                WHERE id = ? AND status = 'waiting'
            ");
            $stmt->execute([$gameId]);
            $game = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$game) {
                return ['success' => false, 'error' => 'Game not found or already started'];
            }
            
            if ($game['player1_id'] == $userId) {
                return ['success' => false, 'error' => 'Cannot join your own game'];
            }
            
            // Join game and initialize game state
            $gameState = $this->initializeMultiplayerGame($game['player1_id'], $userId, $deckId);
            
            $stmt = $this->db->prepare("
                UPDATE multiplayer_games 
                SET player2_id = ?, 
                    status = 'active', 
                    current_turn = ?,
                    game_state = ?,
                    started_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $userId, 
                $game['player1_id'], 
                json_encode($gameState),
                $gameId
            ]);
            
            // Initialize multiplayer stats if needed
            $this->initializeStats($game['player1_id']);
            $this->initializeStats($userId);
            
            // Trigger event
            GameEventSystem::trigger('multiplayer_game_start', [
                'game_id' => $gameId,
                'player1_id' => $game['player1_id'],
                'player2_id' => $userId
            ]);
            
            return [
                'success' => true,
                'game_id' => $gameId,
                'game_state' => $this->getClientGameState($gameState, $userId),
                'message' => 'Joined game successfully!'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to join game: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get list of available games to join
     */
    public function listAvailableGames($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    mg.id,
                    mg.created_at,
                    u.username as host_username,
                    u.level as host_level
                FROM multiplayer_games mg
                JOIN users u ON mg.player1_id = u.id
                WHERE mg.status = 'waiting' 
                AND mg.player1_id != ?
                AND mg.created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                ORDER BY mg.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$userId]);
            $games = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return ['success' => true, 'games' => $games];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to fetch games: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get current game state for a player
     */
    public function getGameState($userId, $gameId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM multiplayer_games 
                WHERE id = ? 
                AND (player1_id = ? OR player2_id = ?)
            ");
            $stmt->execute([$gameId, $userId, $userId]);
            $game = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$game) {
                return ['success' => false, 'error' => 'Game not found'];
            }
            
            if ($game['status'] === 'waiting') {
                return [
                    'success' => true,
                    'status' => 'waiting',
                    'message' => 'Waiting for opponent to join...'
                ];
            }
            
            $gameState = json_decode($game['game_state'], true);
            
            // Get rewards from database if game is finished
            $rewards = null;
            if ($game['status'] === 'finished') {
                $isPlayer1 = ($game['player1_id'] == $userId);
                $rewardsJson = $isPlayer1 ? $game['player1_rewards'] : $game['player2_rewards'];
                if ($rewardsJson) {
                    $rewards = json_decode($rewardsJson, true);
                }
            }
            
            return [
                'success' => true,
                'status' => $game['status'],
                'game_state' => $this->getClientGameState($gameState, $userId),
                'current_turn' => $game['current_turn'],
                'is_your_turn' => ($game['current_turn'] == $userId),
                'winner_id' => $game['winner_id'],
                'rewards' => $rewards
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to get game state: ' . $e->getMessage()];
        }
    }
    
    /**
     * Play a card in multiplayer game
     */
    public function playCard($userId, $gameId, $cardIndex, $target = 'opponent') {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM multiplayer_games 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$gameId]);
            $game = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$game) {
                return ['success' => false, 'error' => 'Game not found or not active'];
            }
            
            if ($game['current_turn'] != $userId) {
                return ['success' => false, 'error' => 'Not your turn'];
            }
            
            $gameState = json_decode($game['game_state'], true);
            
            // Determine which player is making the move
            $playerKey = ($game['player1_id'] == $userId) ? 'player1' : 'player2';
            
            // Play the card using BattleSystem
            $battleSystem = new BattleSystem();
            $result = $battleSystem->playCard($gameState, $cardIndex, $target, 0, $playerKey);
            
            if (!$result['success']) {
                return $result;
            }
            
            // Update game state
            $stmt = $this->db->prepare("
                UPDATE multiplayer_games 
                SET game_state = ?, last_activity = NOW()
                WHERE id = ?
            ");
            $stmt->execute([json_encode($gameState), $gameId]);
            
            // Log the move
            $this->logMove($gameId, $userId, 'play_card', [
                'card_index' => $cardIndex,
                'target' => $target
            ]);
            
            return [
                'success' => true,
                'message' => $result['message'],
                'game_state' => $this->getClientGameState($gameState, $userId)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to play card: ' . $e->getMessage()];
        }
    }
    
    /**
     * End turn in multiplayer game
     */
    public function endTurn($userId, $gameId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM multiplayer_games 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$gameId]);
            $game = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$game) {
                return ['success' => false, 'error' => 'Game not found or not active'];
            }
            
            if ($game['current_turn'] != $userId) {
                return ['success' => false, 'error' => 'Not your turn'];
            }
            
            $gameState = json_decode($game['game_state'], true);
            
            // Determine which player is ending turn
            $playerKey = ($game['player1_id'] == $userId) ? 'player1' : 'player2';
            $opponentKey = ($playerKey === 'player1') ? 'player2' : 'player1';
            $opponentId = ($game['player1_id'] == $userId) ? $game['player2_id'] : $game['player1_id'];
            
            // Execute battle phase
            $battleSystem = new BattleSystem();
            $result = $battleSystem->executeMultiplayerTurnBattle($gameState, $playerKey);
            
            // Check for winner
            $winner = null;
            if ($gameState[$playerKey . '_hp'] <= 0 && $gameState[$opponentKey . '_hp'] <= 0) {
                $winner = 'draw';
            } elseif ($gameState[$playerKey . '_hp'] <= 0) {
                $winner = $opponentId;
            } elseif ($gameState[$opponentKey . '_hp'] <= 0) {
                $winner = $userId;
            }
            
            // Switch turn
            $gameState['turn'] = $opponentKey;
            $gameState['turn_count'] = ($gameState['turn_count'] ?? 1) + 1;
            
            // Start new turn for opponent
            $gameStateObj = new GameState($gameState);
            $gameStateObj->startTurn($opponentKey);
            $gameState = $gameStateObj->getState();
            
            // Update database
            if ($winner) {
                $this->endGame($gameId, $winner, $gameState);
                $status = 'finished';
                
                // Retrieve the game again to get rewards
                $stmt = $this->db->prepare("SELECT * FROM multiplayer_games WHERE id = ?");
                $stmt->execute([$gameId]);
                $updatedGame = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                // Get rewards for current user from database
                $isPlayer1 = ($updatedGame['player1_id'] == $userId);
                $rewardsJson = $isPlayer1 ? $updatedGame['player1_rewards'] : $updatedGame['player2_rewards'];
                $rewards = $rewardsJson ? json_decode($rewardsJson, true) : null;
            } else {
                $stmt = $this->db->prepare("
                    UPDATE multiplayer_games 
                    SET game_state = ?, current_turn = ?, last_activity = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([json_encode($gameState), $opponentId, $gameId]);
                $status = 'active';
                $rewards = null;
            }
            
            // Log the move
            $this->logMove($gameId, $userId, 'end_turn', [
                'battle_log' => $result['battle_log']
            ]);
            
            return [
                'success' => true,
                'battle_log' => $result['battle_log'],
                'battle_events' => $result['battle_events'],
                'game_state' => $this->getClientGameState($gameState, $userId),
                'status' => $status,
                'winner' => $winner,
                'rewards' => $rewards
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to end turn: ' . $e->getMessage()];
        }
    }
    
    /**
     * Surrender/forfeit a multiplayer game
     */
    public function surrender($userId, $gameId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM multiplayer_games 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$gameId]);
            $game = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$game) {
                return ['success' => false, 'error' => 'Game not found or not active'];
            }
            
            $winnerId = ($game['player1_id'] == $userId) ? $game['player2_id'] : $game['player1_id'];
            $gameState = json_decode($game['game_state'], true);
            
            $this->endGame($gameId, $winnerId, $gameState);
            $this->logMove($gameId, $userId, 'surrender', []);
            
            // Retrieve the game again to get rewards
            $stmt = $this->db->prepare("SELECT * FROM multiplayer_games WHERE id = ?");
            $stmt->execute([$gameId]);
            $updatedGame = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Get rewards for current user from database
            $isPlayer1 = ($updatedGame['player1_id'] == $userId);
            $rewardsJson = $isPlayer1 ? $updatedGame['player1_rewards'] : $updatedGame['player2_rewards'];
            $rewards = $rewardsJson ? json_decode($rewardsJson, true) : null;
            
            return [
                'success' => true,
                'message' => 'You have surrendered',
                'winner_id' => $winnerId,
                'rewards' => $rewards
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to surrender: ' . $e->getMessage()];
        }
    }
    
    /**
     * Initialize multiplayer game state
     */
    private function initializeMultiplayerGame($player1Id, $player2Id, $deckId) {
        $gameActions = new GameActions();
        
        // Get player 1 deck
        $player1Cards = $this->getPlayerDeck($player1Id, $deckId);
        // Get player 2 deck
        $player2Cards = $this->getPlayerDeck($player2Id, $deckId);
        
        // Initialize game state
        $gameState = [
            'player1_hp' => STARTING_HP,
            'player2_hp' => STARTING_HP,
            'player1_mana' => STARTING_MANA,
            'player2_mana' => STARTING_MANA,
            'player1_max_mana' => STARTING_MANA,
            'player2_max_mana' => STARTING_MANA,
            'player1_overload' => 0,
            'player2_overload' => 0,
            'player1_turn_count' => 0,
            'player2_turn_count' => 0,
            'player1_hand' => [],
            'player2_hand' => [],
            'player1_field' => [],
            'player2_field' => [],
            'player1_deck' => $player1Cards,
            'player2_deck' => $player2Cards,
            'turn' => 'player1',
            'turn_count' => 1,
            'player1_id' => $player1Id,
            'player2_id' => $player2Id
        ];
        
        // Draw initial hands
        for ($i = 0; $i < CARDS_IN_HAND; $i++) {
            if (!empty($gameState['player1_deck'])) {
                $gameState['player1_hand'][] = array_shift($gameState['player1_deck']);
            }
            if (!empty($gameState['player2_deck'])) {
                $gameState['player2_hand'][] = array_shift($gameState['player2_deck']);
            }
        }
        
        // Set up first turn for player1 (increment turn count but keep mana at 1)
        // This ensures player1 starts with turn_count=1 and when they end turn,
        // player2 will start with turn_count=1 as well (both have 1 mana on first turn)
        $gameState['player1_turn_count'] = 1;
        
        return $gameState;
    }
    
    /**
     * Get player's deck for multiplayer game
     */
    private function getPlayerDeck($userId, $deckId = 0) {
        if ($deckId <= 0) {
            $stmt = $this->db->prepare("SELECT id FROM user_decks WHERE user_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$userId]);
            $activeDeck = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($activeDeck) {
                $deckId = intval($activeDeck['id']);
            }
        }
        
        $cards = [];
        
        if ($deckId > 0) {
            $stmt = $this->db->prepare("
                SELECT c.*, dc.quantity 
                FROM deck_cards dc 
                JOIN cards c ON dc.card_id = c.id 
                WHERE dc.deck_id = ?
            ");
            $stmt->execute([$deckId]);
            $deckCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($deckCards as $card) {
                for ($i = 0; $i < $card['quantity']; $i++) {
                    $cards[] = $card;
                }
            }
        }
        
        // If no cards from deck, fallback to user's collection
        if (empty($cards)) {
            $stmt = $this->db->prepare("
                SELECT c.* FROM user_cards uc 
                JOIN cards c ON uc.card_id = c.id 
                WHERE uc.user_id = ? AND uc.quantity > 0
            ");
            $stmt->execute([$userId]);
            $cards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        // If still no cards, provide default starter cards
        if (empty($cards)) {
            $stmt = $this->db->prepare("
                SELECT * FROM cards 
                WHERE required_level <= 1 
                ORDER BY id 
                LIMIT 30
            ");
            $stmt->execute();
            $cards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        shuffle($cards);
        return $cards;
    }
    
    /**
     * Get game state for client (hide opponent's hand)
     */
    private function getClientGameState($gameState, $userId) {
        $isPlayer1 = ($gameState['player1_id'] == $userId);
        
        $clientState = $gameState;
        
        // Hide opponent's hand
        if ($isPlayer1) {
            $clientState['player2_hand'] = array_map(function($card) {
                return ['hidden' => true];
            }, $gameState['player2_hand']);
            $clientState['player2_deck'] = ['count' => count($gameState['player2_deck'])];
        } else {
            $clientState['player1_hand'] = array_map(function($card) {
                return ['hidden' => true];
            }, $gameState['player1_hand']);
            $clientState['player1_deck'] = ['count' => count($gameState['player1_deck'])];
        }
        
        // Simplify deck info for current player too
        $playerKey = $isPlayer1 ? 'player1' : 'player2';
        $clientState[$playerKey . '_deck'] = ['count' => count($gameState[$playerKey . '_deck'])];
        
        return $clientState;
    }
    
    /**
     * End a multiplayer game
     */
    private function endGame($gameId, $winnerId, $gameState) {
        // Get game details first to know who the players are
        $stmt = $this->db->prepare("SELECT * FROM multiplayer_games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Calculate rewards for both players
        $player1Rewards = $this->calculateRewards($game['player1_id'], $winnerId);
        $player2Rewards = $this->calculateRewards($game['player2_id'], $winnerId);
        
        // Update stats for both players
        $this->updatePlayerStats($game['player1_id'], $winnerId, $player1Rewards);
        $this->updatePlayerStats($game['player2_id'], $winnerId, $player2Rewards);
        
        // Update game status and store rewards
        $stmt = $this->db->prepare("
            UPDATE multiplayer_games 
            SET status = 'finished', 
                winner_id = ?, 
                finished_at = NOW(), 
                game_state = ?,
                player1_rewards = ?,
                player2_rewards = ?
            WHERE id = ?
        ");
        $winnerIdValue = ($winnerId === 'draw') ? null : $winnerId;
        $stmt->execute([
            $winnerIdValue, 
            json_encode($gameState),
            json_encode($player1Rewards),
            json_encode($player2Rewards),
            $gameId
        ]);
        
        // Trigger event
        GameEventSystem::trigger('multiplayer_game_end', [
            'game_id' => $gameId,
            'winner_id' => $winnerId,
            'player1_id' => $game['player1_id'],
            'player2_id' => $game['player2_id']
        ]);
    }
    
    /**
     * Calculate rewards for a player based on game result
     */
    private function calculateRewards($userId, $winnerId) {
        global $LEVEL_REQUIREMENTS;
        
        $this->initializeStats($userId);
        
        $isWinner = ($winnerId == $userId);
        $isDraw = ($winnerId === 'draw');
        
        // Calculate XP based on result
        $xpGained = 0;
        $coinsEarned = 0;
        $gemsEarned = 0;
        
        if ($isDraw) {
            $xpGained = 25;
            $coinsEarned = 30;
        } elseif ($isWinner) {
            $xpGained = 75;
            $coinsEarned = 100;
            // 30% chance to get 1-3 gems on win
            if (mt_rand(1, 100) <= 30) {
                $gemsEarned = mt_rand(1, 3);
            }
        } else {
            // Losing rewards - reduced but still meaningful
            $xpGained = 15;
            $coinsEarned = 20;
        }
        
        // Get user's current level and XP
        $stmt = $this->db->prepare("SELECT level, xp FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $newXp = $user['xp'] + $xpGained;
        $newLevel = $user['level'];
        
        // Check for level up
        foreach ($LEVEL_REQUIREMENTS as $level => $requiredXp) {
            if ($newXp >= $requiredXp && $level > $newLevel) {
                $newLevel = $level;
            }
        }
        
        $leveledUp = $newLevel > $user['level'];
        
        // Get unlocked cards if leveled up
        $unlockedCards = [];
        if ($leveledUp) {
            $stmt = $this->db->prepare("SELECT * FROM cards WHERE required_level = ?");
            $stmt->execute([$newLevel]);
            $unlockedCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return [
            'xp_gained' => $xpGained,
            'coins_earned' => $coinsEarned,
            'gems_earned' => $gemsEarned,
            'leveled_up' => $leveledUp,
            'new_level' => $newLevel,
            'unlocked_cards' => $unlockedCards
        ];
    }
    
    /**
     * Update player stats and apply rewards
     */
    private function updatePlayerStats($userId, $winnerId, $rewards) {
        $this->initializeStats($userId);
        
        $isWinner = ($winnerId == $userId);
        $isDraw = ($winnerId === 'draw');
        
        // Update multiplayer stats
        if ($isDraw) {
            $stmt = $this->db->prepare("
                UPDATE multiplayer_stats 
                SET games_played = games_played + 1,
                    games_drawn = games_drawn + 1,
                    win_streak = 0
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } elseif ($isWinner) {
            $stmt = $this->db->prepare("
                UPDATE multiplayer_stats 
                SET games_played = games_played + 1,
                    games_won = games_won + 1,
                    win_streak = win_streak + 1,
                    longest_win_streak = GREATEST(longest_win_streak, win_streak + 1),
                    rating = rating + 25
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE multiplayer_stats 
                SET games_played = games_played + 1,
                    games_lost = games_lost + 1,
                    win_streak = 0,
                    rating = GREATEST(rating - 20, 0)
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        }
        
        // Update highest rating
        $stmt = $this->db->prepare("
            UPDATE multiplayer_stats 
            SET highest_rating = GREATEST(highest_rating, rating)
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        // Apply XP and currency rewards
        $stmt = $this->db->prepare("
            UPDATE users 
            SET xp = xp + ?, 
                level = ?, 
                coins = coins + ?, 
                gems = gems + ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $rewards['xp_gained'], 
            $rewards['new_level'], 
            $rewards['coins_earned'], 
            $rewards['gems_earned'], 
            $userId
        ]);
        
        // Unlock new cards if leveled up
        if ($rewards['leveled_up'] && !empty($rewards['unlocked_cards'])) {
            $stmt = $this->db->prepare("
                INSERT INTO user_cards (user_id, card_id, quantity) 
                VALUES (?, ?, 2) 
                ON DUPLICATE KEY UPDATE quantity = quantity + 2
            ");
            foreach ($rewards['unlocked_cards'] as $card) {
                $stmt->execute([$userId, $card['id']]);
            }
        }
    }
    
    /**
     * Update multiplayer statistics and give rewards (legacy method for compatibility)
     * @deprecated Use calculateRewards() and updatePlayerStats() instead
     */
    private function updateStatsAndRewards($userId, $winnerId) {
        $rewards = $this->calculateRewards($userId, $winnerId);
        $this->updatePlayerStats($userId, $winnerId, $rewards);
        
        // Store rewards in session for backward compatibility
        $_SESSION['mp_rewards_' . $userId] = $rewards;
    }
    
    /**
     * Initialize stats for a user if not exists
     */
    private function initializeStats($userId) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO multiplayer_stats (user_id) VALUES (?)
        ");
        $stmt->execute([$userId]);
    }
    
    /**
     * Log a move
     */
    private function logMove($gameId, $userId, $moveType, $moveData) {
        $stmt = $this->db->prepare("
            INSERT INTO multiplayer_moves (game_id, player_id, move_type, move_data)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$gameId, $userId, $moveType, json_encode($moveData)]);
    }
    
    /**
     * Get user's current game
     */
    public function getCurrentGame($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, status FROM multiplayer_games 
                WHERE (player1_id = ? OR player2_id = ?) 
                AND status IN ('waiting', 'active')
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $userId]);
            $game = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($game) {
                return ['success' => true, 'game' => $game];
            }
            
            return ['success' => true, 'game' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to get current game: ' . $e->getMessage()];
        }
    }
}

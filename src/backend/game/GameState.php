<?php

namespace Game;

/**
 * Game State Management
 * Handles game state initialization and persistence
 */
class GameState {
    private $state;
    
    public function __construct($state = null) {
        if ($state) {
            $this->state = $state;
        } else {
            $this->state = $this->initializeNew();
        }
    }
    
    /**
     * Initialize a new game state
     */
    private function initializeNew() {
        return [
            'player_hp' => STARTING_HP,
            'ai_hp' => STARTING_HP,
            'player_mana' => STARTING_MANA,
            'ai_mana' => STARTING_MANA,
            'player_max_mana' => STARTING_MANA,
            'ai_max_mana' => STARTING_MANA,
            'player_hand' => [],
            'ai_hand' => [],
            'player_field' => [],
            'ai_field' => [],
            'player_deck' => [],
            'ai_deck' => [],
            'turn' => 'player',
            'turn_count' => 1,
            'ai_level' => 1,
            'mulligan_available' => true,
            'player_overload' => 0,
            'ai_overload' => 0,
        ];
    }
    
    /**
     * Load game state from session
     */
    public static function loadFromSession() {
        if (isset($_SESSION['game_state'])) {
            return new self($_SESSION['game_state']);
        }
        return null;
    }
    
    /**
     * Save game state to session
     */
    public function saveToSession() {
        $_SESSION['game_state'] = $this->state;
    }
    
    /**
     * Get state array
     */
    public function getState() {
        return $this->state;
    }
    
    /**
     * Get state property
     */
    public function get($key) {
        return $this->state[$key] ?? null;
    }
    
    /**
     * Set state property
     */
    public function set($key, $value) {
        $this->state[$key] = $value;
    }
    
    /**
     * Update multiple state properties
     */
    public function update($data) {
        foreach ($data as $key => $value) {
            $this->state[$key] = $value;
        }
    }
    
    /**
     * Initialize player deck
     */
    public function initializePlayerDeck($cards) {
        shuffle($cards);
        $this->state['player_deck'] = $cards;
        $this->state['player_hand'] = $this->drawCards('player', 4);
    }
    
    /**
     * Draw cards for a player
     */
    public function drawCards($player, $count = 1) {
        $deckKey = $player . '_deck';
        $handKey = $player . '_hand';
        $drawnCards = [];
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($this->state[$deckKey])) {
                $card = array_shift($this->state[$deckKey]);
                $this->state[$handKey][] = $card;
                $drawnCards[] = $card;
            }
        }
        
        return $drawnCards;
    }
    
    /**
     * Check if game is over
     */
    public function isGameOver() {
        if ($this->state['player_hp'] <= 0 && $this->state['ai_hp'] <= 0) {
            return 'draw';
        }
        if ($this->state['player_hp'] <= 0) {
            return 'ai';
        }
        if ($this->state['ai_hp'] <= 0) {
            return 'player';
        }
        return false;
    }
    
    /**
     * Switch turn
     */
    public function switchTurn() {
        if ($this->state['turn'] === 'player') {
            $this->state['turn'] = 'ai';
        } else {
            $this->state['turn'] = 'player';
            $this->state['turn_count']++;
        }
    }
    
    /**
     * Start new turn
     */
    public function startTurn($player) {
        $manaKey = $player . '_mana';
        $maxManaKey = $player . '_max_mana';
        $overloadKey = $player . '_overload';
        $turnCountKey = $player . '_turn_count';
        
        // Initialize turn count if not exists
        if (!isset($this->state[$turnCountKey])) {
            $this->state[$turnCountKey] = 0;
        }
        
        // Increment turn count for this player
        $this->state[$turnCountKey]++;
        
        // Only increase max mana after the first turn (i.e., from turn 2 onwards)
        if ($this->state[$turnCountKey] > 1 && $this->state[$maxManaKey] < 10) {
            $this->state[$maxManaKey]++;
        }
        
        // Restore mana minus overload
        $overload = $this->state[$overloadKey] ?? 0;
        $this->state[$manaKey] = max(0, $this->state[$maxManaKey] - $overload);
        
        // Reset overload
        $this->state[$overloadKey] = 0;
        
        // Draw a card
        $this->drawCards($player, 1);
    }
}

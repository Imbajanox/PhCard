<?php
/**
 * User Model
 * Handles user data and operations
 */
class User {
    private $db;
    private $id;
    private $data;
    
    public function __construct($userId = null) {
        $this->db = Database::getInstance();
        if ($userId) {
            $this->id = $userId;
            $this->load();
        }
    }
    
    /**
     * Load user data from database
     */
    private function load() {
        $this->data = $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$this->id]
        );
    }
    
    /**
     * Get user by username
     */
    public static function getByUsername($username) {
        $db = Database::getInstance();
        $userData = $db->fetchOne(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
        
        if ($userData) {
            $user = new self();
            $user->id = $userData['id'];
            $user->data = $userData;
            return $user;
        }
        return null;
    }
    
    /**
     * Get user ID
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     * Get user data
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get specific user property
     */
    public function get($key) {
        return $this->data[$key] ?? null;
    }
    
    /**
     * Update user property
     */
    public function update($data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $this->id;
        
        $this->db->execute(
            "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
        
        // Reload user data
        $this->load();
    }
    
    /**
     * Get user's cards
     */
    public function getCards() {
        return $this->db->fetchAll(
            "SELECT c.*, uc.quantity 
             FROM user_cards uc 
             JOIN cards c ON uc.card_id = c.id 
             WHERE uc.user_id = ? AND uc.quantity > 0
             ORDER BY c.rarity DESC, c.name ASC",
            [$this->id]
        );
    }
    
    /**
     * Get user's active deck
     */
    public function getActiveDeck() {
        $deck = $this->db->fetchOne(
            "SELECT * FROM user_decks WHERE user_id = ? AND is_active = 1 LIMIT 1",
            [$this->id]
        );
        
        if ($deck) {
            return new Deck($deck['id']);
        }
        return null;
    }
    
    /**
     * Add XP to user
     */
    public function addXP($amount) {
        $currentXP = $this->get('xp') + $amount;
        $level = $this->get('level');
        $xpNeeded = 100 * $level; // XP needed for next level
        
        // Level up if enough XP
        while ($currentXP >= $xpNeeded) {
            $currentXP -= $xpNeeded;
            $level++;
            $xpNeeded = 100 * $level;
        }
        
        $this->update([
            'xp' => $currentXP,
            'level' => $level
        ]);
    }
    
    /**
     * Add currency to user
     */
    public function addCurrency($coins = 0, $gems = 0) {
        $this->update([
            'coins' => $this->get('coins') + $coins,
            'gems' => $this->get('gems') + $gems
        ]);
    }
    
    /**
     * Record game result
     */
    public function recordGameResult($won, $xpGained) {
        $this->db->execute(
            "UPDATE users 
             SET total_games = total_games + 1,
                 total_wins = total_wins + ?,
                 total_losses = total_losses + ?
             WHERE id = ?",
            [$won ? 1 : 0, $won ? 0 : 1, $this->id]
        );
        
        $this->addXP($xpGained);
    }
}

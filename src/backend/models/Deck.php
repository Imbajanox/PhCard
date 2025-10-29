<?php

namespace Models;

use Core\Database;

/**
 * Deck Model
 * Handles deck data and operations
 */
class Deck {
    private $db;
    private $id;
    private $data;
    
    public function __construct($deckId = null) {
        $this->db = Database::getInstance();
        if ($deckId) {
            $this->id = $deckId;
            $this->load();
        }
    }
    
    /**
     * Load deck data from database
     */
    private function load() {
        $this->data = $this->db->fetchOne(
            "SELECT * FROM user_decks WHERE id = ?",
            [$this->id]
        );
    }
    
    /**
     * Get deck ID
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     * Get deck data
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get specific deck property
     */
    public function get($key) {
        return $this->data[$key] ?? null;
    }
    
    /**
     * Get deck cards
     */
    public function getCards() {
        return $this->db->fetchAll(
            "SELECT c.*, dc.quantity 
             FROM deck_cards dc 
             JOIN cards c ON dc.card_id = c.id 
             WHERE dc.deck_id = ?
             ORDER BY c.mana_cost ASC, c.name ASC",
            [$this->id]
        );
    }
    
    /**
     * Get expanded deck (with duplicates based on quantity)
     */
    public function getExpandedCards() {
        $deckCards = $this->getCards();
        $expandedCards = [];
        
        foreach ($deckCards as $card) {
            for ($i = 0; $i < $card['quantity']; $i++) {
                $expandedCards[] = $card;
            }
        }
        
        shuffle($expandedCards);
        return $expandedCards;
    }
    
    /**
     * Verify deck ownership
     */
    public function isOwnedBy($userId) {
        return $this->data && $this->data['user_id'] == $userId;
    }
}

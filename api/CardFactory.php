<?php
/**
 * Card Factory - Configuration-driven card creation
 * 
 * Allows creating cards from JSON/array configurations, making it easy
 * to add new cards without writing code.
 * 
 * Usage:
 * $card = CardFactory::createFromConfig($config);
 * CardFactory::importFromJSON($jsonFile);
 */
class CardFactory {
    
    /**
     * Create a card from a configuration array
     * 
     * @param array $config Card configuration
     * @return array Card data ready for database insertion
     */
    public static function createFromConfig($config) {
        $defaults = [
            'type' => 'monster',
            'attack' => 0,
            'defense' => 0,
            'effect' => null,
            'required_level' => 1,
            'rarity' => 'common',
            'description' => '',
            'keywords' => null,
            'mana_cost' => 1,
            'overload' => 0,
            'card_class' => 'neutral',
            'choice_effects' => null
        ];
        
        $card = array_merge($defaults, $config);
        
        // Validate required fields
        if (!isset($card['name'])) {
            throw new InvalidArgumentException("Card must have a name");
        }
        
        // Process keywords array to comma-separated string
        if (is_array($card['keywords'])) {
            $card['keywords'] = implode(',', $card['keywords']);
        }
        
        // Process choice_effects to JSON
        if (is_array($card['choice_effects'])) {
            $card['choice_effects'] = json_encode($card['choice_effects']);
        }
        
        // Validate card type
        if (!in_array($card['type'], ['monster', 'spell'])) {
            throw new InvalidArgumentException("Card type must be 'monster' or 'spell'");
        }
        
        // Validate rarity
        $validRarities = ['common', 'rare', 'epic', 'legendary'];
        if (!in_array($card['rarity'], $validRarities)) {
            throw new InvalidArgumentException("Invalid rarity: {$card['rarity']}");
        }
        
        return $card;
    }
    
    /**
     * Import cards from a JSON file
     * 
     * @param string $filePath Path to JSON file
     * @param PDO $conn Database connection
     * @return array Results of import (inserted, failed)
     */
    public static function importFromJSON($filePath, $conn) {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: $filePath");
        }
        
        $json = file_get_contents($filePath);
        $configs = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON: " . json_last_error_msg());
        }
        
        $results = ['inserted' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($configs as $config) {
            try {
                $card = self::createFromConfig($config);
                self::insertCard($card, $conn);
                $results['inserted']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Insert a card into the database
     * 
     * @param array $card Card data
     * @param PDO $conn Database connection
     * @return int Inserted card ID
     */
    private static function insertCard($card, $conn) {
        $sql = "INSERT INTO cards (name, type, attack, defense, effect, required_level, 
                rarity, description, keywords, mana_cost, overload, card_class, choice_effects)
                VALUES (:name, :type, :attack, :defense, :effect, :required_level,
                :rarity, :description, :keywords, :mana_cost, :overload, :card_class, :choice_effects)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':name' => $card['name'],
            ':type' => $card['type'],
            ':attack' => $card['attack'],
            ':defense' => $card['defense'],
            ':effect' => $card['effect'],
            ':required_level' => $card['required_level'],
            ':rarity' => $card['rarity'],
            ':description' => $card['description'],
            ':keywords' => $card['keywords'],
            ':mana_cost' => $card['mana_cost'],
            ':overload' => $card['overload'],
            ':card_class' => $card['card_class'],
            ':choice_effects' => $card['choice_effects']
        ]);
        
        return $conn->lastInsertId();
    }
    
    /**
     * Export cards to JSON file
     * 
     * @param PDO $conn Database connection
     * @param string $filePath Output file path
     * @param array $filters Optional filters (e.g., ['rarity' => 'legendary'])
     * @return int Number of cards exported
     */
    public static function exportToJSON($conn, $filePath, $filters = []) {
        $sql = "SELECT name, type, attack, defense, effect, required_level, rarity, 
                description, keywords, mana_cost, overload, card_class, choice_effects 
                FROM cards";
        
        $conditions = [];
        $params = [];
        
        foreach ($filters as $key => $value) {
            $conditions[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process cards for export
        foreach ($cards as &$card) {
            if ($card['keywords']) {
                $card['keywords'] = explode(',', $card['keywords']);
            }
            if ($card['choice_effects']) {
                $card['choice_effects'] = json_decode($card['choice_effects'], true);
            }
        }
        
        $json = json_encode($cards, JSON_PRETTY_PRINT);
        file_put_contents($filePath, $json);
        
        return count($cards);
    }
    
    /**
     * Validate a card configuration
     * 
     * @param array $config Card configuration
     * @return array Validation results ['valid' => bool, 'errors' => array]
     */
    public static function validate($config) {
        $errors = [];
        
        if (!isset($config['name']) || empty($config['name'])) {
            $errors[] = "Card name is required";
        }
        
        if (isset($config['type']) && !in_array($config['type'], ['monster', 'spell'])) {
            $errors[] = "Invalid card type";
        }
        
        if (isset($config['rarity']) && !in_array($config['rarity'], ['common', 'rare', 'epic', 'legendary'])) {
            $errors[] = "Invalid rarity";
        }
        
        if (isset($config['mana_cost']) && ($config['mana_cost'] < 0 || $config['mana_cost'] > 10)) {
            $errors[] = "Mana cost must be between 0 and 10";
        }
        
        if (isset($config['attack']) && $config['attack'] < 0) {
            $errors[] = "Attack cannot be negative";
        }
        
        if (isset($config['defense']) && $config['defense'] < 0) {
            $errors[] = "Defense cannot be negative";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>

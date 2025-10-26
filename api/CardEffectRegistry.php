<?php
/**
 * Card Effect Registry - Extensible card effect system
 * 
 * This class provides a plugin-style system for registering and executing card effects.
 * Developers can easily add new card effects without modifying core game logic.
 * 
 * Usage:
 * 1. Register effect: CardEffectRegistry::register('burn', 'handleBurnEffect');
 * 2. Apply effect: CardEffectRegistry::apply('burn', $context);
 */
class CardEffectRegistry {
    private static $effects = [];
    private static $initialized = false;
    
    /**
     * Initialize the registry with built-in effects
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        // Register built-in effects
        self::register('damage', [__CLASS__, 'applyDamage']);
        self::register('heal', [__CLASS__, 'applyHeal']);
        self::register('boost', [__CLASS__, 'applyBoost']);
        self::register('shield', [__CLASS__, 'applyShield']);
        self::register('draw', [__CLASS__, 'applyDraw']);
        self::register('poison', [__CLASS__, 'applyPoison']);
        self::register('burn', [__CLASS__, 'applyBurn']);
        self::register('stun', [__CLASS__, 'applyStun']);
        self::register('freeze', [__CLASS__, 'applyFreeze']);
        self::register('lifesteal', [__CLASS__, 'applyLifesteal']);
        
        self::$initialized = true;
    }
    
    /**
     * Register a new card effect
     * 
     * @param string $effectName Name of the effect (e.g., 'damage', 'heal')
     * @param callable $handler Function to handle the effect
     * @return bool Success status
     */
    public static function register($effectName, $handler) {
        if (!is_callable($handler)) {
            error_log("CardEffectRegistry: Handler for '$effectName' is not callable");
            return false;
        }
        
        self::$effects[$effectName] = $handler;
        return true;
    }
    
    /**
     * Apply a card effect
     * 
     * @param string $effectName Name of the effect to apply
     * @param array $context Context including gameState, value, target, etc.
     * @return array Modified game state or null if effect not found
     */
    public static function apply($effectName, $context) {
        self::init();
        
        if (!isset(self::$effects[$effectName])) {
            error_log("CardEffectRegistry: Unknown effect '$effectName'");
            return null;
        }
        
        return call_user_func(self::$effects[$effectName], $context);
    }
    
    /**
     * Check if an effect is registered
     */
    public static function hasEffect($effectName) {
        self::init();
        return isset(self::$effects[$effectName]);
    }
    
    /**
     * Get all registered effects
     */
    public static function getAllEffects() {
        self::init();
        return array_keys(self::$effects);
    }
    
    // ===== Built-in Effect Handlers =====
    
    private static function applyDamage($context) {
        $value = $context['value'] ?? 0;
        $target = $context['target'] ?? 'ai';
        $gameState = $context['gameState'];
        
        if ($target === 'ai') {
            $gameState['ai_hp'] -= $value;
        } else {
            $gameState['player_hp'] -= $value;
        }
        
        return $gameState;
    }
    
    private static function applyHeal($context) {
        $value = $context['value'] ?? 0;
        $target = $context['target'] ?? 'player';
        $gameState = $context['gameState'];
        
        if ($target === 'player') {
            $gameState['player_hp'] = min($gameState['player_hp'] + $value, STARTING_HP);
        } else {
            $gameState['ai_hp'] = min($gameState['ai_hp'] + $value, STARTING_HP);
        }
        
        return $gameState;
    }
    
    private static function applyBoost($context) {
        $value = $context['value'] ?? 0;
        $gameState = $context['gameState'];
        
        // Apply boost to all player monsters
        foreach ($gameState['player_field'] as &$monster) {
            $monster['attack'] += $value;
        }
        
        return $gameState;
    }
    
    private static function applyShield($context) {
        $value = $context['value'] ?? 0;
        $gameState = $context['gameState'];
        
        // Add temporary shield (stored in game state)
        if (!isset($gameState['player_shield'])) {
            $gameState['player_shield'] = 0;
        }
        $gameState['player_shield'] += $value;
        
        return $gameState;
    }
    
    private static function applyDraw($context) {
        $value = $context['value'] ?? 1;
        $gameState = $context['gameState'];
        
        // Draw cards from available cards
        for ($i = 0; $i < $value; $i++) {
            if (!empty($gameState['available_cards'])) {
                $card = array_shift($gameState['available_cards']);
                $gameState['player_hand'][] = $card;
            }
        }
        
        return $gameState;
    }
    
    private static function applyPoison($context) {
        $value = $context['value'] ?? 100;
        $duration = $context['duration'] ?? 3;
        $target = $context['target'] ?? 'ai';
        $gameState = $context['gameState'];
        
        if (!isset($gameState['status_effects'])) {
            $gameState['status_effects'] = [];
        }
        
        $gameState['status_effects'][] = [
            'type' => 'poison',
            'target' => $target,
            'damage' => $value,
            'duration' => $duration
        ];
        
        return $gameState;
    }
    
    private static function applyBurn($context) {
        $value = $context['value'] ?? 50;
        $duration = $context['duration'] ?? 4;
        $target = $context['target'] ?? 'ai';
        $gameState = $context['gameState'];
        
        if (!isset($gameState['status_effects'])) {
            $gameState['status_effects'] = [];
        }
        
        $gameState['status_effects'][] = [
            'type' => 'burn',
            'target' => $target,
            'damage' => $value,
            'duration' => $duration,
            'escalating' => true
        ];
        
        return $gameState;
    }
    
    private static function applyStun($context) {
        $target = $context['target'] ?? null;
        $gameState = $context['gameState'];
        
        if ($target !== null && isset($gameState['ai_field'][$target])) {
            $gameState['ai_field'][$target]['stunned'] = true;
            $gameState['ai_field'][$target]['stun_duration'] = 1;
        }
        
        return $gameState;
    }
    
    private static function applyFreeze($context) {
        $target = $context['target'] ?? null;
        $duration = $context['duration'] ?? 2;
        $gameState = $context['gameState'];
        
        if ($target !== null) {
            if (isset($gameState['ai_field'][$target])) {
                $gameState['ai_field'][$target]['frozen'] = true;
                $gameState['ai_field'][$target]['freeze_duration'] = $duration;
            }
        }
        
        return $gameState;
    }
    
    private static function applyLifesteal($context) {
        $damage = $context['damage'] ?? 0;
        $gameState = $context['gameState'];
        
        // Heal player for damage dealt
        $gameState['player_hp'] = min($gameState['player_hp'] + $damage, STARTING_HP);
        
        return $gameState;
    }
}
?>

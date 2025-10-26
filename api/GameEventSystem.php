<?php
/**
 * Game Event System
 * 
 * Provides an event-driven architecture for game actions.
 * Allows plugins/extensions to hook into game events without modifying core code.
 * 
 * Events include: card_played, card_drawn, damage_dealt, monster_summoned,
 *                 turn_start, turn_end, game_start, game_end, etc.
 * 
 * Usage:
 * 1. Register listener: GameEventSystem::on('card_played', function($data) { ... });
 * 2. Trigger event: GameEventSystem::trigger('card_played', ['card' => $card]);
 */
class GameEventSystem {
    private static $listeners = [];
    private static $eventLog = [];
    private static $maxLogSize = 100;
    
    /**
     * Register an event listener
     * 
     * @param string $eventName Name of the event
     * @param callable $callback Function to call when event is triggered
     * @param int $priority Priority (higher = earlier execution)
     * @return bool Success status
     */
    public static function on($eventName, $callback, $priority = 10) {
        if (!is_callable($callback)) {
            error_log("GameEventSystem: Callback for '$eventName' is not callable");
            return false;
        }
        
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }
        
        self::$listeners[$eventName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        
        // Sort by priority (descending)
        usort(self::$listeners[$eventName], function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        return true;
    }
    
    /**
     * Trigger an event
     * 
     * @param string $eventName Name of the event to trigger
     * @param array $data Event data to pass to listeners
     * @return array Modified data after all listeners have processed it
     */
    public static function trigger($eventName, $data = []) {
        // Log the event
        self::logEvent($eventName, $data);
        
        if (!isset(self::$listeners[$eventName])) {
            return $data;
        }
        
        foreach (self::$listeners[$eventName] as $listener) {
            try {
                $result = call_user_func($listener['callback'], $data);
                if ($result !== null) {
                    $data = $result;
                }
            } catch (Exception $e) {
                error_log("GameEventSystem: Error in listener for '$eventName': " . $e->getMessage());
            }
        }
        
        return $data;
    }
    
    /**
     * Remove all listeners for an event
     */
    public static function off($eventName) {
        unset(self::$listeners[$eventName]);
    }
    
    /**
     * Check if event has listeners
     */
    public static function hasListeners($eventName) {
        return isset(self::$listeners[$eventName]) && !empty(self::$listeners[$eventName]);
    }
    
    /**
     * Get all registered event names
     */
    public static function getEventNames() {
        return array_keys(self::$listeners);
    }
    
    /**
     * Log an event for debugging/analytics
     */
    private static function logEvent($eventName, $data) {
        self::$eventLog[] = [
            'event' => $eventName,
            'data' => $data,
            'timestamp' => microtime(true)
        ];
        
        // Keep log size manageable
        if (count(self::$eventLog) > self::$maxLogSize) {
            array_shift(self::$eventLog);
        }
    }
    
    /**
     * Get event log for debugging
     */
    public static function getEventLog($limit = null) {
        if ($limit !== null) {
            return array_slice(self::$eventLog, -$limit);
        }
        return self::$eventLog;
    }
    
    /**
     * Clear event log
     */
    public static function clearLog() {
        self::$eventLog = [];
    }
    
    /**
     * Initialize default event hooks
     */
    public static function initDefaultHooks() {
        // Example: Log all card plays to telemetry
        self::on('card_played', function($data) {
            if (isset($data['card']) && isset($data['user_id'])) {
                // Could record to database here
                error_log("Card played: {$data['card']['name']} by user {$data['user_id']}");
            }
            return $data;
        }, 5);
        
        // Example: Track damage for statistics
        self::on('damage_dealt', function($data) {
            if (isset($data['amount']) && isset($data['source'])) {
                // Could update statistics here
            }
            return $data;
        }, 5);
    }
}

/**
 * Standard game events that can be triggered:
 * 
 * - game_start: When a new game begins
 * - game_end: When a game ends
 * - turn_start: At the beginning of each turn
 * - turn_end: At the end of each turn
 * - card_drawn: When a card is drawn
 * - card_played: When a card is played
 * - monster_summoned: When a monster is placed on the field
 * - spell_cast: When a spell is cast
 * - damage_dealt: When damage is dealt
 * - healing_done: When healing occurs
 * - monster_destroyed: When a monster is destroyed
 * - status_effect_applied: When a status effect is applied
 * - status_effect_expired: When a status effect expires
 * - mana_gained: When mana is gained
 * - mana_spent: When mana is spent
 * - level_up: When player levels up
 * - achievement_unlocked: When achievement is earned
 * - quest_completed: When quest is completed
 */
?>

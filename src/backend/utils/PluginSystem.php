<?php

namespace Utils;

/**
 * Plugin System for PhCard
 * 
 * Allows developers to create plugin files that are automatically loaded.
 * Plugins can register effects, add event listeners, and extend functionality.
 * 
 * Plugin files should be placed in: plugins/
 * Plugin files must be named: plugin_*.php
 * 
 * Example plugin file (plugins/plugin_example.php):
 * 
 * <?php
 * // Register custom effect
 * CardEffectRegistry::register('triple_damage', function($context) {
 *     $gameState = $context['gameState'];
 *     $gameState['ai_hp'] -= ($context['value'] ?? 100) * 3;
 *     return $gameState;
 * });
 * 
 * // Add event listener
 * GameEventSystem::on('game_end', function($data) {
 *     error_log("Game ended with result: {$data['result']}");
 *     return $data;
 * });
 * ?>
 */

class PluginSystem {
    private static $loadedPlugins = [];
    private static $pluginDirectory = '../plugins';
    private static $initialized = false;
    
    /**
     * Initialize the plugin system
     * 
     * @param string $directory Optional custom plugin directory
     */
    public static function init($directory = null) {
        if (self::$initialized) {
            return;
        }
        
        if ($directory !== null) {
            // Validate directory path to prevent path traversal
            $realPath = realpath($directory);
            if ($realPath === false || strpos($realPath, realpath(__DIR__)) !== 0) {
                error_log("PluginSystem: Invalid or unsafe plugin directory: $directory");
                self::$pluginDirectory = '../plugins'; // Fallback to default
            } else {
                self::$pluginDirectory = $directory;
            }
        }
        
        // Ensure CardEffectRegistry and GameEventSystem are initialized
        if (class_exists('Utils\\CardEffectRegistry')) {
            CardEffectRegistry::init();
        }
        
        if (class_exists('Utils\\GameEventSystem')) {
            GameEventSystem::initDefaultHooks();
        }
        
        self::loadPlugins();
        self::$initialized = true;
    }
    
    /**
     * Load all plugins from the plugin directory
     */
    private static function loadPlugins() {
        $pluginPath = __DIR__ . '/' . self::$pluginDirectory;
        
        if (!is_dir($pluginPath)) {
            // Create plugin directory if it doesn't exist
            if (!mkdir($pluginPath, 0755, true)) {
                error_log("PluginSystem: Failed to create plugin directory: $pluginPath");
                return;
            }
            self::createExamplePlugin($pluginPath);
            return;
        }
        
        // Find all plugin files
        $pluginFiles = glob($pluginPath . '/plugin_*.php');
        
        foreach ($pluginFiles as $file) {
            self::loadPlugin($file);
        }
    }
    
    /**
     * Load a single plugin file
     * 
     * @param string $file Path to plugin file
     * @return bool Success status
     */
    private static function loadPlugin($file) {
        $pluginName = basename($file, '.php');
        
        try {
            // Check if already loaded
            if (isset(self::$loadedPlugins[$pluginName])) {
                return false;
            }
            
            // Basic security: Validate plugin file path
            $realPath = realpath($file);
            if ($realPath === false) {
                error_log("PluginSystem: Plugin file not found: $file");
                return false;
            }
            
            // Ensure plugin is within plugin directory
            $pluginDir = realpath(__DIR__ . '/' . self::$pluginDirectory);
            if (strpos($realPath, $pluginDir) !== 0) {
                error_log("PluginSystem: Plugin outside plugin directory rejected: $file");
                return false;
            }
            
            // Security note: Plugins are trusted code and must be carefully reviewed
            // In production, only allow admin-approved plugins
            require_once $file;
            
            self::$loadedPlugins[$pluginName] = [
                'file' => $file,
                'loaded_at' => time()
            ];
            
            error_log("Plugin loaded: $pluginName");
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to load plugin $pluginName: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get list of loaded plugins
     * 
     * @return array List of plugin names
     */
    public static function getLoadedPlugins() {
        return array_keys(self::$loadedPlugins);
    }
    
    /**
     * Check if a specific plugin is loaded
     * 
     * @param string $pluginName Plugin name
     * @return bool Whether plugin is loaded
     */
    public static function isPluginLoaded($pluginName) {
        return isset(self::$loadedPlugins[$pluginName]);
    }
    
    /**
     * Get plugin information
     * 
     * @param string $pluginName Plugin name
     * @return array|null Plugin info or null if not found
     */
    public static function getPluginInfo($pluginName) {
        return self::$loadedPlugins[$pluginName] ?? null;
    }
    
    /**
     * Create an example plugin file
     */
    private static function createExamplePlugin($directory) {
        $exampleFile = $directory . '/plugin_example.php.disabled';
        
        $content = <<<'PHP'
<?php
/**
 * Example Plugin for PhCard
 * 
 * This is an example plugin that demonstrates how to:
 * 1. Register custom card effects
 * 2. Add event listeners
 * 3. Extend game functionality
 * 
 * To enable this plugin, rename it to plugin_example.php
 */

// === Custom Card Effects ===

// Example: Register a "triple damage" effect
CardEffectRegistry::register('triple_damage', function($context) {
    $value = $context['value'] ?? 100;
    $gameState = $context['gameState'];
    $target = $context['target'] ?? 'ai';
    
    $damage = $value * 3;
    
    if ($target === 'ai') {
        $gameState['ai_hp'] -= $damage;
    } else {
        $gameState['player_hp'] -= $damage;
    }
    
    error_log("Triple damage dealt: $damage");
    return $gameState;
});

// Example: Register a "summon companion" effect
CardEffectRegistry::register('summon_companion', function($context) {
    $gameState = $context['gameState'];
    
    // Add a 2/2 companion to player field
    $companion = [
        'name' => 'Companion',
        'type' => 'monster',
        'attack' => 200,
        'defense' => 200,
        'is_token' => true
    ];
    
    $gameState['player_field'][] = $companion;
    return $gameState;
});

// === Event Listeners ===

// Example: Track legendary card plays
GameEventSystem::on('card_played', function($data) {
    if (isset($data['card']['rarity']) && $data['card']['rarity'] === 'legendary') {
        error_log("Legendary card played: {$data['card']['name']}");
        
        // Could save to database, trigger achievement, etc.
    }
    return $data;
}, 15);

// Example: Bonus XP for winning on weekends
GameEventSystem::on('game_end', function($data) {
    if ($data['result'] === 'win') {
        $dayOfWeek = date('N'); // 1 (Monday) to 7 (Sunday)
        
        if ($dayOfWeek >= 6) { // Weekend
            // Add bonus XP (would need to modify in actual implementation)
            error_log("Weekend bonus: +50 XP!");
        }
    }
    return $data;
});

// Example: Log player progression
GameEventSystem::on('level_up', function($data) {
    $userId = $data['user_id'];
    $newLevel = $data['new_level'];
    
    error_log("Player $userId reached level $newLevel!");
    
    // Could trigger notifications, achievements, etc.
    return $data;
});

// Example: Quest progress tracking
GameEventSystem::on('damage_dealt', function($data) {
    $amount = $data['amount'] ?? 0;
    
    // Track total damage for quests
    if (isset($data['user_id']) && $amount > 0) {
        // Could update quest progress here
        error_log("Damage dealt for quest tracking: $amount");
    }
    
    return $data;
});

error_log("Example plugin loaded successfully!");

?>
PHP;
        
        file_put_contents($exampleFile, $content);
    }
    
    /**
     * Reload all plugins (useful for development)
     */
    public static function reload() {
        self::$loadedPlugins = [];
        self::$initialized = false;
        self::init();
    }
}
?>

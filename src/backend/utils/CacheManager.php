<?php

namespace Utils;

/**
 * Simple cache manager for improving performance
 * Uses session-based caching for user-specific data
 */
class CacheManager {
    private static $cache = [];
    private static $initialized = false;
    
    /**
     * Initialize cache from session
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        if (isset($_SESSION['cache'])) {
            self::$cache = $_SESSION['cache'];
        }
        
        self::$initialized = true;
    }
    
    /**
     * Get cached value
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return mixed|null Returns cached value or null if not found/expired
     */
    public static function get($key, $ttl = 300) {
        self::init();
        
        if (!isset(self::$cache[$key])) {
            return null;
        }
        
        $cached = self::$cache[$key];
        
        // Check if expired (if TTL is set)
        if ($ttl > 0 && isset($cached['expires_at'])) {
            if (time() > $cached['expires_at']) {
                self::delete($key);
                return null;
            }
        }
        
        return $cached['value'];
    }
    
    /**
     * Set cached value
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     */
    public static function set($key, $value, $ttl = 300) {
        self::init();
        
        $cached = [
            'value' => $value,
            'created_at' => time()
        ];
        
        if ($ttl > 0) {
            $cached['expires_at'] = time() + $ttl;
        }
        
        self::$cache[$key] = $cached;
        self::persist();
    }
    
    /**
     * Delete cached value
     * @param string $key Cache key
     */
    public static function delete($key) {
        self::init();
        
        if (isset(self::$cache[$key])) {
            unset(self::$cache[$key]);
            self::persist();
        }
    }
    
    /**
     * Clear all cache
     */
    public static function clear() {
        self::$cache = [];
        self::persist();
    }
    
    /**
     * Clear cache for specific user
     * @param int $userId User ID
     */
    public static function clearUserCache($userId) {
        self::init();
        
        $prefix = "user_{$userId}_";
        foreach (self::$cache as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                unset(self::$cache[$key]);
            }
        }
        
        self::persist();
    }
    
    /**
     * Persist cache to session
     */
    private static function persist() {
        $_SESSION['cache'] = self::$cache;
    }
    
    /**
     * Get or set cached value using a callback
     * @param string $key Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or generated value
     */
    public static function remember($key, $callback, $ttl = 300) {
        $value = self::get($key, $ttl);
        
        if ($value === null) {
            $value = $callback();
            self::set($key, $value, $ttl);
        }
        
        return $value;
    }
}

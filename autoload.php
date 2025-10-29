<?php
/**
 * Autoloader for PhCard classes
 * Automatically loads classes from src/backend directory
 */
spl_autoload_register(function ($className) {
    // Base directory for the namespace prefix
    $baseDir = __DIR__ . '/src/backend/';
    
    // Map of class prefixes to subdirectories
    $prefixMap = [
        'Core\\' => 'core/',
        'Game\\' => 'game/',
        'Features\\' => 'features/',
        'Models\\' => 'models/',
        'Utils\\' => 'utils/',
    ];
    
    // Check if class uses namespace
    foreach ($prefixMap as $prefix => $dir) {
        if (strpos($className, $prefix) === 0) {
            $relativeClass = substr($className, strlen($prefix));
            $file = $baseDir . $dir . str_replace('\\', '/', $relativeClass) . '.php';
            
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
    
    // Fallback for classes without namespace - search all directories
    $directories = ['core', 'game', 'features', 'models', 'utils'];
    foreach ($directories as $dir) {
        $file = $baseDir . $dir . '/' . $className . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

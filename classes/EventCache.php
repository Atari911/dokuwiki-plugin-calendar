<?php
/**
 * Calendar Plugin - Event Cache
 * 
 * Provides caching for calendar events to avoid reloading JSON files
 * on every page view. Uses file-based caching with TTL.
 * 
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 * @version 7.0.8
 */

if (!defined('DOKU_INC')) die();

class CalendarEventCache {
    
    /** @var int Default cache TTL in seconds (5 minutes) */
    private const DEFAULT_TTL = 300;
    
    /** @var string Cache directory relative to DokuWiki data */
    private const CACHE_DIR = 'cache/calendar/';
    
    /** @var array In-memory cache for current request */
    private static $memoryCache = [];
    
    /** @var string|null Base cache directory path */
    private static $cacheDir = null;
    
    /**
     * Get the cache directory path
     * 
     * @return string Cache directory path
     */
    private static function getCacheDir() {
        if (self::$cacheDir === null) {
            self::$cacheDir = DOKU_INC . 'data/' . self::CACHE_DIR;
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }
    
    /**
     * Generate cache key for a month's events
     * 
     * @param string $namespace Namespace filter
     * @param int $year Year
     * @param int $month Month
     * @return string Cache key
     */
    private static function getMonthCacheKey($namespace, $year, $month) {
        $ns = preg_replace('/[^a-zA-Z0-9_-]/', '_', $namespace ?: 'default');
        return sprintf('events_%s_%04d_%02d', $ns, $year, $month);
    }
    
    /**
     * Get cache file path
     * 
     * @param string $key Cache key
     * @return string File path
     */
    private static function getCacheFile($key) {
        return self::getCacheDir() . $key . '.cache';
    }
    
    /**
     * Get cached events for a month
     * 
     * @param string $namespace Namespace filter
     * @param int $year Year
     * @param int $month Month
     * @param int $ttl TTL in seconds (default 5 minutes)
     * @return array|null Cached events or null if cache miss/expired
     */
    public static function getMonthEvents($namespace, $year, $month, $ttl = self::DEFAULT_TTL) {
        $key = self::getMonthCacheKey($namespace, $year, $month);
        
        // Check memory cache first
        if (isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key];
        }
        
        $cacheFile = self::getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        // Check if cache is expired
        $mtime = filemtime($cacheFile);
        if ($mtime === false || (time() - $mtime) > $ttl) {
            @unlink($cacheFile);
            return null;
        }
        
        $contents = @file_get_contents($cacheFile);
        if ($contents === false) {
            return null;
        }
        
        $data = @unserialize($contents);
        if ($data === false) {
            @unlink($cacheFile);
            return null;
        }
        
        // Store in memory cache
        self::$memoryCache[$key] = $data;
        
        return $data;
    }
    
    /**
     * Set cached events for a month
     * 
     * @param string $namespace Namespace filter
     * @param int $year Year
     * @param int $month Month
     * @param array $events Events data
     * @return bool Success status
     */
    public static function setMonthEvents($namespace, $year, $month, array $events) {
        $key = self::getMonthCacheKey($namespace, $year, $month);
        
        // Store in memory cache
        self::$memoryCache[$key] = $events;
        
        $cacheFile = self::getCacheFile($key);
        $serialized = serialize($events);
        
        // Use temp file for atomic write
        $tempFile = $cacheFile . '.tmp';
        if (@file_put_contents($tempFile, $serialized) === false) {
            return false;
        }
        
        if (!@rename($tempFile, $cacheFile)) {
            @unlink($tempFile);
            return false;
        }
        
        return true;
    }
    
    /**
     * Invalidate cache for a specific month
     * 
     * @param string $namespace Namespace filter
     * @param int $year Year
     * @param int $month Month
     */
    public static function invalidateMonth($namespace, $year, $month) {
        $key = self::getMonthCacheKey($namespace, $year, $month);
        
        // Clear memory cache
        unset(self::$memoryCache[$key]);
        
        // Delete cache file
        $cacheFile = self::getCacheFile($key);
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
    
    /**
     * Invalidate cache for a namespace (all months)
     * 
     * @param string $namespace Namespace to invalidate
     */
    public static function invalidateNamespace($namespace) {
        $ns = preg_replace('/[^a-zA-Z0-9_-]/', '_', $namespace ?: 'default');
        $pattern = self::getCacheDir() . "events_{$ns}_*.cache";
        
        foreach (glob($pattern) as $file) {
            @unlink($file);
        }
        
        // Clear matching memory cache entries
        $prefix = "events_{$ns}_";
        foreach (array_keys(self::$memoryCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset(self::$memoryCache[$key]);
            }
        }
    }
    
    /**
     * Invalidate all event caches
     */
    public static function invalidateAll() {
        $pattern = self::getCacheDir() . "events_*.cache";
        
        foreach (glob($pattern) as $file) {
            @unlink($file);
        }
        
        // Clear memory cache
        self::$memoryCache = [];
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache stats
     */
    public static function getStats() {
        $cacheDir = self::getCacheDir();
        $files = glob($cacheDir . "*.cache");
        
        $stats = [
            'files' => count($files),
            'size' => 0,
            'oldest' => null,
            'newest' => null,
            'memory_entries' => count(self::$memoryCache)
        ];
        
        foreach ($files as $file) {
            $size = filesize($file);
            $mtime = filemtime($file);
            
            $stats['size'] += $size;
            
            if ($stats['oldest'] === null || $mtime < $stats['oldest']) {
                $stats['oldest'] = $mtime;
            }
            if ($stats['newest'] === null || $mtime > $stats['newest']) {
                $stats['newest'] = $mtime;
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean up expired cache files
     * 
     * @param int $ttl TTL in seconds
     * @return int Number of files cleaned
     */
    public static function cleanup($ttl = self::DEFAULT_TTL) {
        $cacheDir = self::getCacheDir();
        $files = glob($cacheDir . "*.cache");
        $cleaned = 0;
        $now = time();
        
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && ($now - $mtime) > $ttl) {
                if (@unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
}

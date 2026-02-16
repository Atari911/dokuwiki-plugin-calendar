<?php
/**
 * Calendar Plugin - Rate Limiter
 * 
 * Provides rate limiting for AJAX endpoints to prevent abuse.
 * Uses file-based tracking for simplicity and compatibility.
 * 
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 * @version 7.0.8
 */

if (!defined('DOKU_INC')) die();

class CalendarRateLimiter {
    
    /** @var int Default rate limit (requests per window) */
    private const DEFAULT_LIMIT = 60;
    
    /** @var int Default time window in seconds (1 minute) */
    private const DEFAULT_WINDOW = 60;
    
    /** @var int Write action rate limit (more restrictive) */
    private const WRITE_LIMIT = 30;
    
    /** @var int Write action time window */
    private const WRITE_WINDOW = 60;
    
    /** @var string Rate limit data directory */
    private const RATE_DIR = 'cache/calendar/ratelimit/';
    
    /** @var int Cleanup probability (1 in X requests) */
    private const CLEANUP_PROBABILITY = 100;
    
    /**
     * Get the rate limit directory path
     * 
     * @return string Directory path
     */
    private static function getRateDir() {
        $dir = DOKU_INC . 'data/' . self::RATE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
    
    /**
     * Get identifier for rate limiting (user or IP)
     * 
     * @return string Identifier hash
     */
    private static function getIdentifier() {
        // Prefer username if logged in
        if (!empty($_SERVER['REMOTE_USER'])) {
            return 'user_' . md5($_SERVER['REMOTE_USER']);
        }
        
        // Fall back to IP address
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Check for proxy headers
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return 'ip_' . md5($ip);
    }
    
    /**
     * Get rate limit file path
     * 
     * @param string $identifier User/IP identifier
     * @param string $action Action type
     * @return string File path
     */
    private static function getRateFile($identifier, $action) {
        $action = preg_replace('/[^a-z0-9_]/', '', strtolower($action));
        return self::getRateDir() . "{$identifier}_{$action}.rate";
    }
    
    /**
     * Check if request is rate limited
     * 
     * @param string $action Action being performed
     * @param bool $isWrite Whether this is a write action
     * @return bool True if allowed, false if rate limited
     */
    public static function check($action, $isWrite = false) {
        $identifier = self::getIdentifier();
        $limit = $isWrite ? self::WRITE_LIMIT : self::DEFAULT_LIMIT;
        $window = $isWrite ? self::WRITE_WINDOW : self::DEFAULT_WINDOW;
        
        $rateFile = self::getRateFile($identifier, $action);
        $now = time();
        
        // Read current rate data
        $data = ['requests' => [], 'blocked_until' => 0];
        if (file_exists($rateFile)) {
            $contents = @file_get_contents($rateFile);
            if ($contents) {
                $decoded = @json_decode($contents, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        
        // Check if currently blocked
        if (isset($data['blocked_until']) && $data['blocked_until'] > $now) {
            return false;
        }
        
        // Clean old requests outside the window
        $windowStart = $now - $window;
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        $data['requests'] = array_values($data['requests']);
        
        // Check if over limit
        if (count($data['requests']) >= $limit) {
            // Block for remaining window time
            $data['blocked_until'] = $now + $window;
            self::saveRateData($rateFile, $data);
            
            self::logRateLimit($identifier, $action, count($data['requests']));
            return false;
        }
        
        // Add current request
        $data['requests'][] = $now;
        $data['blocked_until'] = 0;
        
        self::saveRateData($rateFile, $data);
        
        // Probabilistic cleanup
        if (rand(1, self::CLEANUP_PROBABILITY) === 1) {
            self::cleanup();
        }
        
        return true;
    }
    
    /**
     * Save rate data to file
     * 
     * @param string $rateFile File path
     * @param array $data Rate data
     */
    private static function saveRateData($rateFile, array $data) {
        $json = json_encode($data);
        @file_put_contents($rateFile, $json, LOCK_EX);
    }
    
    /**
     * Get remaining requests for current user
     * 
     * @param string $action Action type
     * @param bool $isWrite Whether this is a write action
     * @return array Info about remaining requests
     */
    public static function getRemaining($action, $isWrite = false) {
        $identifier = self::getIdentifier();
        $limit = $isWrite ? self::WRITE_LIMIT : self::DEFAULT_LIMIT;
        $window = $isWrite ? self::WRITE_WINDOW : self::DEFAULT_WINDOW;
        
        $rateFile = self::getRateFile($identifier, $action);
        $now = time();
        
        $data = ['requests' => [], 'blocked_until' => 0];
        if (file_exists($rateFile)) {
            $contents = @file_get_contents($rateFile);
            if ($contents) {
                $decoded = @json_decode($contents, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        
        // Check if blocked
        if (isset($data['blocked_until']) && $data['blocked_until'] > $now) {
            return [
                'remaining' => 0,
                'limit' => $limit,
                'reset' => $data['blocked_until'] - $now,
                'blocked' => true
            ];
        }
        
        // Count requests in window
        $windowStart = $now - $window;
        $currentRequests = count(array_filter($data['requests'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        }));
        
        return [
            'remaining' => max(0, $limit - $currentRequests),
            'limit' => $limit,
            'reset' => $window,
            'blocked' => false
        ];
    }
    
    /**
     * Reset rate limit for a user/action
     * 
     * @param string $action Action type
     * @param string|null $identifier Specific identifier (null for current user)
     */
    public static function reset($action, $identifier = null) {
        if ($identifier === null) {
            $identifier = self::getIdentifier();
        }
        
        $rateFile = self::getRateFile($identifier, $action);
        if (file_exists($rateFile)) {
            @unlink($rateFile);
        }
    }
    
    /**
     * Clean up old rate limit files
     * 
     * @param int $maxAge Maximum age in seconds (default 1 hour)
     * @return int Number of files cleaned
     */
    public static function cleanup($maxAge = 3600) {
        $dir = self::getRateDir();
        $files = glob($dir . '*.rate');
        $cleaned = 0;
        $now = time();
        
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && ($now - $mtime) > $maxAge) {
                if (@unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Log rate limit event
     * 
     * @param string $identifier User/IP identifier
     * @param string $action Action that was limited
     * @param int $requests Number of requests made
     */
    private static function logRateLimit($identifier, $action, $requests) {
        if (defined('CALENDAR_DEBUG') && CALENDAR_DEBUG) {
            error_log("[Calendar RateLimiter] Rate limited: $identifier, action: $action, requests: $requests");
        }
    }
    
    /**
     * Add rate limit headers to response
     * 
     * @param string $action Action type
     * @param bool $isWrite Whether this is a write action
     */
    public static function addHeaders($action, $isWrite = false) {
        $info = self::getRemaining($action, $isWrite);
        
        header('X-RateLimit-Limit: ' . $info['limit']);
        header('X-RateLimit-Remaining: ' . $info['remaining']);
        header('X-RateLimit-Reset: ' . $info['reset']);
        
        if ($info['blocked']) {
            header('Retry-After: ' . $info['reset']);
        }
    }
}

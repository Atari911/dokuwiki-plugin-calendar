<?php
/**
 * Calendar Plugin - File Handler
 * 
 * Provides atomic file operations with locking to prevent data corruption
 * from concurrent writes. This addresses the critical race condition issue
 * where simultaneous event saves could corrupt JSON files.
 * 
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 * @version 7.0.8
 */

if (!defined('DOKU_INC')) die();

class CalendarFileHandler {
    
    /** @var int Lock timeout in seconds */
    private const LOCK_TIMEOUT = 10;
    
    /** @var int Maximum retry attempts for acquiring lock */
    private const MAX_RETRIES = 50;
    
    /** @var int Microseconds to wait between lock attempts */
    private const RETRY_DELAY = 100000; // 100ms
    
    /**
     * Read and decode a JSON file safely
     * 
     * @param string $filepath Path to JSON file
     * @return array Decoded array or empty array on error
     */
    public static function readJson($filepath) {
        if (!file_exists($filepath)) {
            return [];
        }
        
        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            self::logError("Failed to open file for reading: $filepath");
            return [];
        }
        
        // Acquire shared lock for reading
        $locked = false;
        for ($i = 0; $i < self::MAX_RETRIES; $i++) {
            if (flock($handle, LOCK_SH | LOCK_NB)) {
                $locked = true;
                break;
            }
            usleep(self::RETRY_DELAY);
        }
        
        if (!$locked) {
            fclose($handle);
            self::logError("Failed to acquire read lock: $filepath");
            return [];
        }
        
        $contents = '';
        while (!feof($handle)) {
            $contents .= fread($handle, 8192);
        }
        
        flock($handle, LOCK_UN);
        fclose($handle);
        
        if (empty($contents)) {
            return [];
        }
        
        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::logError("JSON decode error in $filepath: " . json_last_error_msg());
            return [];
        }
        
        return is_array($decoded) ? $decoded : [];
    }
    
    /**
     * Write data to JSON file atomically with locking
     * 
     * Uses a temp file + atomic rename strategy to prevent partial writes.
     * This ensures that the file is never in a corrupted state.
     * 
     * @param string $filepath Path to JSON file
     * @param array $data Data to encode and write
     * @return bool Success status
     */
    public static function writeJson($filepath, array $data) {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                self::logError("Failed to create directory: $dir");
                return false;
            }
        }
        
        // Create temp file in same directory (ensures same filesystem for rename)
        $tempFile = $dir . '/.tmp_' . uniqid() . '_' . basename($filepath);
        
        // Encode with pretty print for debugging
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            self::logError("JSON encode error: " . json_last_error_msg());
            return false;
        }
        
        // Write to temp file
        $handle = @fopen($tempFile, 'w');
        if (!$handle) {
            self::logError("Failed to create temp file: $tempFile");
            return false;
        }
        
        // Acquire exclusive lock on temp file
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            @unlink($tempFile);
            self::logError("Failed to lock temp file: $tempFile");
            return false;
        }
        
        $written = fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        
        if ($written === false) {
            @unlink($tempFile);
            self::logError("Failed to write to temp file: $tempFile");
            return false;
        }
        
        // Now we need to lock the target file during rename
        // If target exists, lock it first
        if (file_exists($filepath)) {
            $targetHandle = @fopen($filepath, 'r+');
            if ($targetHandle) {
                // Try to get exclusive lock
                $locked = false;
                for ($i = 0; $i < self::MAX_RETRIES; $i++) {
                    if (flock($targetHandle, LOCK_EX | LOCK_NB)) {
                        $locked = true;
                        break;
                    }
                    usleep(self::RETRY_DELAY);
                }
                
                if (!$locked) {
                    fclose($targetHandle);
                    @unlink($tempFile);
                    self::logError("Failed to lock target file: $filepath");
                    return false;
                }
                
                // Atomic rename while holding lock
                $renamed = @rename($tempFile, $filepath);
                
                flock($targetHandle, LOCK_UN);
                fclose($targetHandle);
                
                if (!$renamed) {
                    @unlink($tempFile);
                    self::logError("Failed to rename temp to target: $filepath");
                    return false;
                }
            } else {
                // Can't open target, try rename anyway
                if (!@rename($tempFile, $filepath)) {
                    @unlink($tempFile);
                    self::logError("Failed to rename (no handle): $filepath");
                    return false;
                }
            }
        } else {
            // Target doesn't exist, just rename
            if (!@rename($tempFile, $filepath)) {
                @unlink($tempFile);
                self::logError("Failed to rename new file: $filepath");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Delete a file safely
     * 
     * @param string $filepath Path to file
     * @return bool Success status
     */
    public static function delete($filepath) {
        if (!file_exists($filepath)) {
            return true;
        }
        
        $handle = @fopen($filepath, 'r+');
        if (!$handle) {
            // Try direct delete
            return @unlink($filepath);
        }
        
        // Get exclusive lock before deleting
        $locked = false;
        for ($i = 0; $i < self::MAX_RETRIES; $i++) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $locked = true;
                break;
            }
            usleep(self::RETRY_DELAY);
        }
        
        if ($locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
        
        return @unlink($filepath);
    }
    
    /**
     * Ensure directory exists
     * 
     * @param string $dir Directory path
     * @return bool Success status
     */
    public static function ensureDir($dir) {
        if (is_dir($dir)) {
            return true;
        }
        return @mkdir($dir, 0755, true);
    }
    
    /**
     * Read a simple text file
     * 
     * @param string $filepath Path to file
     * @param string $default Default value if file doesn't exist
     * @return string File contents or default
     */
    public static function readText($filepath, $default = '') {
        if (!file_exists($filepath)) {
            return $default;
        }
        $contents = @file_get_contents($filepath);
        return $contents !== false ? $contents : $default;
    }
    
    /**
     * Write a simple text file atomically
     * 
     * @param string $filepath Path to file
     * @param string $content Content to write
     * @return bool Success status
     */
    public static function writeText($filepath, $content) {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return false;
            }
        }
        
        $tempFile = $dir . '/.tmp_' . uniqid() . '_' . basename($filepath);
        
        if (@file_put_contents($tempFile, $content) === false) {
            return false;
        }
        
        if (!@rename($tempFile, $filepath)) {
            @unlink($tempFile);
            return false;
        }
        
        return true;
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private static function logError($message) {
        if (defined('CALENDAR_DEBUG') && CALENDAR_DEBUG) {
            error_log("[Calendar FileHandler] $message");
        }
    }
}

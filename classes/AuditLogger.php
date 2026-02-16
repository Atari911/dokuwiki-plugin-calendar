<?php
/**
 * Calendar Plugin - Audit Logger
 * 
 * Logs all event modifications for compliance and debugging.
 * Log files are stored in data/cache/calendar/audit/
 * 
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 * @version 7.0.8
 */

if (!defined('DOKU_INC')) die();

class CalendarAuditLogger {
    
    /** @var string Base directory for audit logs */
    private $logDir;
    
    /** @var bool Whether audit logging is enabled */
    private $enabled = true;
    
    /** @var int Maximum log file size in bytes (5MB) */
    const MAX_LOG_SIZE = 5242880;
    
    /** @var int Number of rotated log files to keep */
    const MAX_LOG_FILES = 10;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $conf;
        $this->logDir = $conf['cachedir'] . '/calendar/audit';
        $this->ensureLogDir();
    }
    
    /**
     * Ensure the audit log directory exists
     */
    private function ensureLogDir() {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
    }
    
    /**
     * Log an event action
     * 
     * @param string $action The action performed (create, update, delete, etc.)
     * @param array $data Additional data about the action
     * @param string|null $user The user who performed the action (null = current user)
     */
    public function log($action, $data = [], $user = null) {
        if (!$this->enabled) return;
        
        global $INFO;
        
        // Get user info
        if ($user === null) {
            $user = isset($INFO['client']) ? $INFO['client'] : 'anonymous';
        }
        
        // Build log entry
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'unix_time' => time(),
            'action' => $action,
            'user' => $user,
            'ip' => $this->getClientIP(),
            'data' => $data
        ];
        
        // Write to log file
        $this->writeLog($entry);
    }
    
    /**
     * Log event creation
     */
    public function logCreate($namespace, $date, $eventId, $title, $user = null) {
        $this->log('create', [
            'namespace' => $namespace,
            'date' => $date,
            'event_id' => $eventId,
            'title' => $title
        ], $user);
    }
    
    /**
     * Log event update
     */
    public function logUpdate($namespace, $date, $eventId, $title, $changes = [], $user = null) {
        $this->log('update', [
            'namespace' => $namespace,
            'date' => $date,
            'event_id' => $eventId,
            'title' => $title,
            'changes' => $changes
        ], $user);
    }
    
    /**
     * Log event deletion
     */
    public function logDelete($namespace, $date, $eventId, $title = '', $user = null) {
        $this->log('delete', [
            'namespace' => $namespace,
            'date' => $date,
            'event_id' => $eventId,
            'title' => $title
        ], $user);
    }
    
    /**
     * Log event move (date change)
     */
    public function logMove($namespace, $oldDate, $newDate, $eventId, $title, $user = null) {
        $this->log('move', [
            'namespace' => $namespace,
            'old_date' => $oldDate,
            'new_date' => $newDate,
            'event_id' => $eventId,
            'title' => $title
        ], $user);
    }
    
    /**
     * Log task completion toggle
     */
    public function logTaskToggle($namespace, $date, $eventId, $title, $completed, $user = null) {
        $this->log('task_toggle', [
            'namespace' => $namespace,
            'date' => $date,
            'event_id' => $eventId,
            'title' => $title,
            'completed' => $completed
        ], $user);
    }
    
    /**
     * Log bulk operations
     */
    public function logBulk($operation, $count, $details = [], $user = null) {
        $this->log('bulk_' . $operation, [
            'count' => $count,
            'details' => $details
        ], $user);
    }
    
    /**
     * Write log entry to file
     * 
     * @param array $entry Log entry data
     */
    private function writeLog($entry) {
        $logFile = $this->logDir . '/calendar_audit.log';
        
        // Rotate log if needed
        $this->rotateLogIfNeeded($logFile);
        
        // Format log line
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Append to log file with locking
        $fp = @fopen($logFile, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
    
    /**
     * Rotate log file if it exceeds maximum size
     * 
     * @param string $logFile Path to log file
     */
    private function rotateLogIfNeeded($logFile) {
        if (!file_exists($logFile)) return;
        
        $size = @filesize($logFile);
        if ($size < self::MAX_LOG_SIZE) return;
        
        // Rotate existing numbered logs
        for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                if ($i + 1 > self::MAX_LOG_FILES) {
                    @unlink($oldFile);
                } else {
                    @rename($oldFile, $newFile);
                }
            }
        }
        
        // Rotate current log
        @rename($logFile, $logFile . '.1');
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get recent audit entries
     * 
     * @param int $limit Number of entries to return
     * @param string|null $action Filter by action type
     * @return array
     */
    public function getRecentEntries($limit = 100, $action = null) {
        $logFile = $this->logDir . '/calendar_audit.log';
        if (!file_exists($logFile)) return [];
        
        $entries = [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$lines) return [];
        
        // Read from end (most recent first)
        $lines = array_reverse($lines);
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            
            if ($action !== null && $entry['action'] !== $action) {
                continue;
            }
            
            $entries[] = $entry;
            
            if (count($entries) >= $limit) break;
        }
        
        return $entries;
    }
    
    /**
     * Get audit entries for a specific date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array
     */
    public function getEntriesByDateRange($startDate, $endDate) {
        $logFile = $this->logDir . '/calendar_audit.log';
        if (!file_exists($logFile)) return [];
        
        $startTime = strtotime($startDate . ' 00:00:00');
        $endTime = strtotime($endDate . ' 23:59:59');
        
        $entries = [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$lines) return [];
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            
            $entryTime = $entry['unix_time'] ?? strtotime($entry['timestamp']);
            
            if ($entryTime >= $startTime && $entryTime <= $endTime) {
                $entries[] = $entry;
            }
        }
        
        return $entries;
    }
    
    /**
     * Enable or disable audit logging
     * 
     * @param bool $enabled
     */
    public function setEnabled($enabled) {
        $this->enabled = (bool)$enabled;
    }
    
    /**
     * Check if audit logging is enabled
     * 
     * @return bool
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Get the audit log directory path
     * 
     * @return string
     */
    public function getLogDir() {
        return $this->logDir;
    }
    
    /**
     * Get total size of all audit logs
     * 
     * @return int Size in bytes
     */
    public function getTotalLogSize() {
        $total = 0;
        $files = glob($this->logDir . '/calendar_audit.log*');
        foreach ($files as $file) {
            $total += filesize($file);
        }
        return $total;
    }
    
    /**
     * Clear all audit logs (use with caution)
     * 
     * @return bool
     */
    public function clearLogs() {
        $files = glob($this->logDir . '/calendar_audit.log*');
        foreach ($files as $file) {
            @unlink($file);
        }
        
        // Log the clear action itself
        $this->log('audit_cleared', ['cleared_files' => count($files)]);
        
        return true;
    }
}

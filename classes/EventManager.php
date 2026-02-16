<?php
/**
 * Calendar Plugin - Event Manager
 * 
 * Consolidates event CRUD operations with proper file locking and caching.
 * This class is the single point of entry for all event data operations.
 * 
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 * @version 7.0.8
 */

if (!defined('DOKU_INC')) die();

// Require dependencies
require_once __DIR__ . '/FileHandler.php';
require_once __DIR__ . '/EventCache.php';

class CalendarEventManager {
    
    /** @var string Base data directory */
    private static $baseDir = null;
    
    /**
     * Get the base calendar data directory
     * 
     * @return string Base directory path
     */
    private static function getBaseDir() {
        if (self::$baseDir === null) {
            self::$baseDir = DOKU_INC . 'data/meta/';
        }
        return self::$baseDir;
    }
    
    /**
     * Get the data directory for a namespace
     * 
     * @param string $namespace Namespace (empty for default)
     * @return string Directory path
     */
    private static function getNamespaceDir($namespace = '') {
        $dir = self::getBaseDir();
        if ($namespace) {
            $dir .= str_replace(':', '/', $namespace) . '/';
        }
        $dir .= 'calendar/';
        return $dir;
    }
    
    /**
     * Get the event file path for a specific month
     * 
     * @param string $namespace Namespace
     * @param int $year Year
     * @param int $month Month
     * @return string File path
     */
    private static function getEventFile($namespace, $year, $month) {
        $dir = self::getNamespaceDir($namespace);
        return $dir . sprintf('%04d-%02d.json', $year, $month);
    }
    
    /**
     * Load events for a specific month
     * 
     * @param string $namespace Namespace filter
     * @param int $year Year
     * @param int $month Month
     * @param bool $useCache Whether to use caching
     * @return array Events indexed by date
     */
    public static function loadMonth($namespace, $year, $month, $useCache = true) {
        // Check cache first
        if ($useCache) {
            $cached = CalendarEventCache::getMonthEvents($namespace, $year, $month);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $events = [];
        
        // Handle wildcards and multiple namespaces
        if (strpos($namespace, '*') !== false || strpos($namespace, ';') !== false) {
            $events = self::loadMonthMultiNamespace($namespace, $year, $month);
        } else {
            $eventFile = self::getEventFile($namespace, $year, $month);
            $events = CalendarFileHandler::readJson($eventFile);
        }
        
        // Store in cache
        if ($useCache) {
            CalendarEventCache::setMonthEvents($namespace, $year, $month, $events);
        }
        
        return $events;
    }
    
    /**
     * Load events from multiple namespaces
     * 
     * @param string $namespacePattern Namespace pattern (with * or ;)
     * @param int $year Year
     * @param int $month Month
     * @return array Merged events indexed by date
     */
    private static function loadMonthMultiNamespace($namespacePattern, $year, $month) {
        $allEvents = [];
        $namespaces = self::expandNamespacePattern($namespacePattern);
        
        foreach ($namespaces as $ns) {
            $eventFile = self::getEventFile($ns, $year, $month);
            $events = CalendarFileHandler::readJson($eventFile);
            
            foreach ($events as $date => $dateEvents) {
                if (!isset($allEvents[$date])) {
                    $allEvents[$date] = [];
                }
                foreach ($dateEvents as $event) {
                    // Ensure namespace is set
                    if (!isset($event['namespace'])) {
                        $event['namespace'] = $ns;
                    }
                    $allEvents[$date][] = $event;
                }
            }
        }
        
        return $allEvents;
    }
    
    /**
     * Expand namespace pattern to list of namespaces
     * 
     * @param string $pattern Namespace pattern
     * @return array List of namespace paths
     */
    private static function expandNamespacePattern($pattern) {
        $namespaces = [];
        
        // Handle semicolon-separated namespaces
        if (strpos($pattern, ';') !== false) {
            $parts = explode(';', $pattern);
            foreach ($parts as $part) {
                $expanded = self::expandNamespacePattern(trim($part));
                $namespaces = array_merge($namespaces, $expanded);
            }
            return array_unique($namespaces);
        }
        
        // Handle wildcard
        if (strpos($pattern, '*') !== false) {
            // Get base directory
            $basePattern = str_replace('*', '', $pattern);
            $basePattern = rtrim($basePattern, ':');
            
            $searchDir = self::getBaseDir();
            if ($basePattern) {
                $searchDir .= str_replace(':', '/', $basePattern) . '/';
            }
            
            // Always include the base namespace
            $namespaces[] = $basePattern;
            
            // Find subdirectories with calendar data
            if (is_dir($searchDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($searchDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    if ($file->isDir() && $file->getFilename() === 'calendar') {
                        // Extract namespace from path
                        $path = dirname($file->getPathname());
                        $relPath = str_replace(self::getBaseDir(), '', $path);
                        $ns = str_replace('/', ':', trim($relPath, '/'));
                        if ($ns && !in_array($ns, $namespaces)) {
                            $namespaces[] = $ns;
                        }
                    }
                }
            }
            
            return $namespaces;
        }
        
        // Simple namespace
        return [$pattern];
    }
    
    /**
     * Save an event
     * 
     * @param array $eventData Event data
     * @param string|null $oldDate Previous date (for moves)
     * @param string|null $oldNamespace Previous namespace (for moves)
     * @return array Result with success status and event data
     */
    public static function saveEvent(array $eventData, $oldDate = null, $oldNamespace = null) {
        // Validate required fields
        if (empty($eventData['date']) || empty($eventData['title'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }
        
        $date = $eventData['date'];
        $namespace = $eventData['namespace'] ?? '';
        $eventId = $eventData['id'] ?? uniqid();
        
        // Parse date
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return ['success' => false, 'error' => 'Invalid date format'];
        }
        list(, $year, $month, $day) = $matches;
        $year = (int)$year;
        $month = (int)$month;
        
        // Ensure ID is set
        $eventData['id'] = $eventId;
        
        // Set created timestamp if new
        if (!isset($eventData['created'])) {
            $eventData['created'] = date('Y-m-d H:i:s');
        }
        
        // Handle event move (different date or namespace)
        $dateChanged = $oldDate && $oldDate !== $date;
        $namespaceChanged = $oldNamespace !== null && $oldNamespace !== $namespace;
        
        if ($dateChanged || $namespaceChanged) {
            // Delete from old location
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $oldDate ?: $date, $oldMatches)) {
                return ['success' => false, 'error' => 'Invalid old date format'];
            }
            list(, $oldYear, $oldMonth, $oldDay) = $oldMatches;
            
            $oldEventFile = self::getEventFile($oldNamespace ?? $namespace, (int)$oldYear, (int)$oldMonth);
            $oldEvents = CalendarFileHandler::readJson($oldEventFile);
            
            $deleteDate = $oldDate ?: $date;
            if (isset($oldEvents[$deleteDate])) {
                $oldEvents[$deleteDate] = array_values(array_filter(
                    $oldEvents[$deleteDate],
                    function($evt) use ($eventId) {
                        return $evt['id'] !== $eventId;
                    }
                ));
                
                if (empty($oldEvents[$deleteDate])) {
                    unset($oldEvents[$deleteDate]);
                }
                
                CalendarFileHandler::writeJson($oldEventFile, $oldEvents);
                
                // Invalidate old location cache
                CalendarEventCache::invalidateMonth($oldNamespace ?? $namespace, (int)$oldYear, (int)$oldMonth);
            }
        }
        
        // Load current events
        $eventFile = self::getEventFile($namespace, $year, $month);
        $events = CalendarFileHandler::readJson($eventFile);
        
        // Ensure date array exists
        if (!isset($events[$date]) || !is_array($events[$date])) {
            $events[$date] = [];
        }
        
        // Update or add event
        $found = false;
        foreach ($events[$date] as $key => $evt) {
            if ($evt['id'] === $eventId) {
                $events[$date][$key] = $eventData;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $events[$date][] = $eventData;
        }
        
        // Save with atomic write
        if (!CalendarFileHandler::writeJson($eventFile, $events)) {
            return ['success' => false, 'error' => 'Failed to save event'];
        }
        
        // Invalidate cache
        CalendarEventCache::invalidateMonth($namespace, $year, $month);
        
        return ['success' => true, 'event' => $eventData];
    }
    
    /**
     * Delete an event
     * 
     * @param string $eventId Event ID
     * @param string $date Event date
     * @param string $namespace Namespace
     * @return array Result with success status
     */
    public static function deleteEvent($eventId, $date, $namespace = '') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return ['success' => false, 'error' => 'Invalid date format'];
        }
        list(, $year, $month, $day) = $matches;
        $year = (int)$year;
        $month = (int)$month;
        
        $eventFile = self::getEventFile($namespace, $year, $month);
        $events = CalendarFileHandler::readJson($eventFile);
        
        if (!isset($events[$date])) {
            return ['success' => false, 'error' => 'Event not found'];
        }
        
        $originalCount = count($events[$date]);
        $events[$date] = array_values(array_filter(
            $events[$date],
            function($evt) use ($eventId) {
                return $evt['id'] !== $eventId;
            }
        ));
        
        if (count($events[$date]) === $originalCount) {
            return ['success' => false, 'error' => 'Event not found'];
        }
        
        if (empty($events[$date])) {
            unset($events[$date]);
        }
        
        if (!CalendarFileHandler::writeJson($eventFile, $events)) {
            return ['success' => false, 'error' => 'Failed to delete event'];
        }
        
        // Invalidate cache
        CalendarEventCache::invalidateMonth($namespace, $year, $month);
        
        return ['success' => true];
    }
    
    /**
     * Get a single event by ID
     * 
     * @param string $eventId Event ID
     * @param string $date Event date
     * @param string $namespace Namespace (use * for all)
     * @return array|null Event data or null if not found
     */
    public static function getEvent($eventId, $date, $namespace = '') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return null;
        }
        list(, $year, $month, $day) = $matches;
        
        $events = self::loadMonth($namespace, (int)$year, (int)$month);
        
        if (!isset($events[$date])) {
            return null;
        }
        
        foreach ($events[$date] as $event) {
            if ($event['id'] === $eventId) {
                return $event;
            }
        }
        
        return null;
    }
    
    /**
     * Find which namespace an event is in
     * 
     * @param string $eventId Event ID
     * @param string $date Event date
     * @return string|null Namespace or null if not found
     */
    public static function findEventNamespace($eventId, $date) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return null;
        }
        list(, $year, $month, $day) = $matches;
        
        // Load all namespaces
        $events = self::loadMonth('*', (int)$year, (int)$month, false);
        
        if (!isset($events[$date])) {
            return null;
        }
        
        foreach ($events[$date] as $event) {
            if ($event['id'] === $eventId) {
                return $event['namespace'] ?? '';
            }
        }
        
        return null;
    }
    
    /**
     * Search events across all namespaces
     * 
     * @param string $query Search query
     * @param array $options Search options (dateFrom, dateTo, namespace)
     * @return array Matching events
     */
    public static function searchEvents($query, array $options = []) {
        $results = [];
        $query = strtolower(trim($query));
        
        $namespace = $options['namespace'] ?? '*';
        $dateFrom = $options['dateFrom'] ?? date('Y-m-01');
        $dateTo = $options['dateTo'] ?? date('Y-m-d', strtotime('+1 year'));
        
        // Parse date range
        $startDate = new DateTime($dateFrom);
        $endDate = new DateTime($dateTo);
        
        // Iterate through months
        $current = clone $startDate;
        $current->modify('first day of this month');
        
        while ($current <= $endDate) {
            $year = (int)$current->format('Y');
            $month = (int)$current->format('m');
            
            $events = self::loadMonth($namespace, $year, $month);
            
            foreach ($events as $date => $dateEvents) {
                if ($date < $dateFrom || $date > $dateTo) {
                    continue;
                }
                
                foreach ($dateEvents as $event) {
                    $titleMatch = stripos($event['title'] ?? '', $query) !== false;
                    $descMatch = stripos($event['description'] ?? '', $query) !== false;
                    
                    if ($titleMatch || $descMatch) {
                        $event['_date'] = $date;
                        $results[] = $event;
                    }
                }
            }
            
            $current->modify('+1 month');
        }
        
        // Sort by date
        usort($results, function($a, $b) {
            return strcmp($a['_date'], $b['_date']);
        });
        
        return $results;
    }
    
    /**
     * Get all namespaces that have calendar data
     * 
     * @return array List of namespaces
     */
    public static function getNamespaces() {
        return self::expandNamespacePattern('*');
    }
    
    /**
     * Debug log helper
     * 
     * @param string $message Message to log
     */
    private static function log($message) {
        if (defined('CALENDAR_DEBUG') && CALENDAR_DEBUG) {
            error_log("[Calendar EventManager] $message");
        }
    }
}

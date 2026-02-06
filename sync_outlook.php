#!/usr/bin/env php
<?php
/**
 * DokuWiki Calendar → Outlook Sync
 * 
 * Syncs calendar events from DokuWiki to Office 365/Outlook via Microsoft Graph API
 * 
 * Usage:
 *   php sync_outlook.php                       # Full sync
 *   php sync_outlook.php --dry-run             # Show changes without applying
 *   php sync_outlook.php --namespace=work      # Sync only specific namespace
 *   php sync_outlook.php --clean-duplicates    # Remove duplicate events
 *   php sync_outlook.php --reset               # Reset sync state, rebuild from scratch
 *   php sync_outlook.php --force               # Force re-sync all events
 * 
 * Setup:
 *   1. Edit sync_config.php with your Azure credentials
 *   2. Run: php sync_outlook.php --dry-run
 *   3. If looks good: php sync_outlook.php
 *   4. Add to cron (see documentation for cron syntax)
 */

// Parse command line options
$options = getopt('', ['dry-run', 'namespace:', 'force', 'verbose', 'clean-duplicates', 'reset']);
$dryRun = isset($options['dry-run']);
$forceSync = isset($options['force']);
$verbose = isset($options['verbose']) || $dryRun;
$cleanDuplicates = isset($options['clean-duplicates']);
$reset = isset($options['reset']);
$filterNamespace = isset($options['namespace']) ? $options['namespace'] : null;

// Determine script directory
$scriptDir = __DIR__;
$dokuwikiRoot = dirname(dirname(dirname($scriptDir))); // Go up to dokuwiki root

// Load configuration
$configFile = $scriptDir . '/sync_config.php';
if (!file_exists($configFile)) {
    die("ERROR: Configuration file not found: $configFile\n" .
        "Please copy sync_config.php and add your credentials.\n");
}

$config = require $configFile;

// Validate configuration
if (empty($config['tenant_id']) || strpos($config['tenant_id'], 'YOUR_') !== false) {
    die("ERROR: Please configure your Azure credentials in sync_config.php\n");
}

// Files
$stateFile = $scriptDir . '/sync_state.json';
$logFile = $scriptDir . '/sync.log';

// Initialize
$stats = [
    'scanned' => 0,
    'created' => 0,
    'updated' => 0,
    'deleted' => 0,
    'recreated' => 0,
    'skipped' => 0,
    'errors' => 0
];

// Logging
function logMessage($message, $level = 'INFO') {
    global $logFile, $verbose;
    
    // Set timezone to Los Angeles
    $tz = new DateTimeZone('America/Los_Angeles');
    $now = new DateTime('now', $tz);
    $timestamp = $now->format('Y-m-d H:i:s');
    
    $logLine = "[$timestamp] [$level] $message\n";
    
    if ($verbose || $level === 'ERROR') {
        echo $logLine;
    }
    
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

logMessage("=== DokuWiki → Outlook Sync Started ===");
if ($dryRun) logMessage("DRY RUN MODE - No changes will be made");
if ($filterNamespace) logMessage("Filtering namespace: $filterNamespace");
if ($reset) logMessage("RESET MODE - Will rebuild sync state from scratch");
if ($cleanDuplicates) logMessage("CLEAN DUPLICATES MODE - Will remove all duplicate events");

// =============================================================================
// MICROSOFT GRAPH API CLIENT
// =============================================================================

class MicrosoftGraphClient {
    private $config;
    private $accessToken = null;
    private $tokenExpiry = 0;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function getAccessToken() {
        // Check if we have a valid cached token
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }
        
        // Request new token
        $tokenUrl = "https://login.microsoftonline.com/{$this->config['tenant_id']}/oauth2/v2.0/token";
        
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'scope' => 'https://graph.microsoft.com/.default'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['api_timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to get access token: HTTP $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        if (!isset($result['access_token'])) {
            throw new Exception("No access token in response: $response");
        }
        
        $this->accessToken = $result['access_token'];
        $this->tokenExpiry = time() + ($result['expires_in'] - 300); // Refresh 5min early
        
        return $this->accessToken;
    }
    
    public function apiRequest($method, $endpoint, $data = null) {
        $token = $this->getAccessToken();
        $url = "https://graph.microsoft.com/v1.0" . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['api_timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Prefer: outlook.timezone="' . $this->config['timezone'] . '"'
        ]);
        
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        
        if ($data !== null) {
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonData === false) {
                throw new Exception("Failed to encode JSON: " . json_last_error_msg());
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("API request failed: $method $endpoint - HTTP $httpCode - $response");
        }
        
        return json_decode($response, true);
    }
    
    public function createEvent($userEmail, $eventData) {
        return $this->apiRequest('POST', "/users/$userEmail/events", $eventData);
    }
    
    public function updateEvent($userEmail, $outlookId, $eventData) {
        return $this->apiRequest('PATCH', "/users/$userEmail/events/$outlookId", $eventData);
    }
    
    public function deleteEvent($userEmail, $outlookId) {
        return $this->apiRequest('DELETE', "/users/$userEmail/events/$outlookId");
    }
    
    public function getEvent($userEmail, $outlookId) {
        try {
            return $this->apiRequest('GET', "/users/$userEmail/events/$outlookId");
        } catch (Exception $e) {
            return null; // Event not found
        }
    }
    
    public function findEventByDokuWikiId($userEmail, $dokuwikiId) {
        // Search for events with our custom extended property
        $filter = rawurlencode("singleValueExtendedProperties/Any(ep: ep/id eq 'String {66f5a359-4659-4830-9070-00047ec6ac6e} Name DokuWikiId' and ep/value eq '$dokuwikiId')");
        
        try {
            $result = $this->apiRequest('GET', "/users/$userEmail/events?\$filter=$filter&\$select=id,subject");
            return isset($result['value']) ? $result['value'] : [];
        } catch (Exception $e) {
            logMessage("ERROR searching for event: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    public function deleteAllDuplicates($userEmail, $dokuwikiId) {
        $events = $this->findEventByDokuWikiId($userEmail, $dokuwikiId);
        
        if (count($events) <= 1) {
            return 0; // No duplicates
        }
        
        // Keep the first one, delete the rest
        $deleted = 0;
        for ($i = 1; $i < count($events); $i++) {
            try {
                $this->deleteEvent($userEmail, $events[$i]['id']);
                $deleted++;
                logMessage("Deleted duplicate: {$events[$i]['subject']}", 'DEBUG');
            } catch (Exception $e) {
                logMessage("ERROR deleting duplicate: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return $deleted;
    }
}

// =============================================================================
// DOKUWIKI CALENDAR READER
// =============================================================================

function loadDokuWikiEvents($dokuwikiRoot, $filterNamespace = null) {
    $metaDir = $dokuwikiRoot . '/data/meta';
    $allEvents = [];
    
    if (!is_dir($metaDir)) {
        logMessage("ERROR: Meta directory not found: $metaDir", 'ERROR');
        return [];
    }
    
    scanCalendarDirs($metaDir, '', $allEvents, $filterNamespace);
    
    return $allEvents;
}

function scanCalendarDirs($dir, $namespace, &$allEvents, $filterNamespace) {
    $items = @scandir($dir);
    if (!$items) return;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            if ($item === 'calendar') {
                // Found a calendar directory
                $currentNamespace = trim($namespace, ':');
                
                // Check filter
                if ($filterNamespace !== null && $currentNamespace !== $filterNamespace) {
                    continue;
                }
                
                logMessage("Scanning calendar: $currentNamespace", 'DEBUG');
                loadCalendarFiles($path, $currentNamespace, $allEvents);
            } else {
                // Recurse into subdirectory
                $newNamespace = $namespace ? $namespace . ':' . $item : $item;
                scanCalendarDirs($path, $newNamespace, $allEvents, $filterNamespace);
            }
        }
    }
}

function loadCalendarFiles($calendarDir, $namespace, &$allEvents) {
    global $stats;
    
    $files = glob($calendarDir . '/*.json');
    
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        
        // Skip empty files
        if (trim($contents) === '' || trim($contents) === '{}' || trim($contents) === '[]') {
            continue;
        }
        
        $data = json_decode($contents, true);
        
        // Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("ERROR: Invalid JSON in $file: " . json_last_error_msg(), 'ERROR');
            continue;
        }
        
        if (!is_array($data)) continue;
        if (empty($data)) continue;
        
        // MATCH DOKUWIKI LOGIC: Load everything from the file, no filtering
        foreach ($data as $dateKey => $events) {
            if (!is_array($events)) continue;
            
            foreach ($events as $event) {
                if (!isset($event['id'])) continue;
                
                $stats['scanned']++;
                
                // Get event's namespace field
                $eventNamespace = isset($event['namespace']) ? $event['namespace'] : '';
                
                // Create unique ID based on event's namespace field
                // Empty namespace = root namespace
                if ($eventNamespace === '') {
                    $uniqueId = ':' . $event['id'];
                } else {
                    $uniqueId = $eventNamespace . ':' . $event['id'];
                }
                
                // Store file location for reference
                $event['_fileNamespace'] = $namespace;
                $event['_dateKey'] = $dateKey;
                
                // Add to collection - just like DokuWiki does
                $allEvents[$uniqueId] = $event;
            }
        }
    }
}

// =============================================================================
// EVENT CONVERSION
// =============================================================================

function convertToOutlookEvent($dwEvent, $config) {
    $timezone = $config['timezone'];
    
    // Parse date and time
    $dateKey = $dwEvent['_dateKey'];
    $startDate = $dateKey;
    $endDate = isset($dwEvent['endDate']) && $dwEvent['endDate'] ? $dwEvent['endDate'] : $dateKey;
    
    // Handle time
    $isAllDay = empty($dwEvent['time']);
    
    if ($isAllDay) {
        // All-day events: Use just the date, and end date is next day
        $startDateTime = $startDate;
        
        // For all-day events, end date must be the day AFTER the last day
        $endDateObj = new DateTime($endDate);
        $endDateObj->modify('+1 day');
        $endDateTime = $endDateObj->format('Y-m-d');
    } else {
        // Timed events: Add time to date
        $startDateTime = $startDate . 'T' . $dwEvent['time'] . ':00';
        
        // End time: if no end date, add 1 hour to start time
        if ($endDate === $dateKey) {
            $dt = new DateTime($startDateTime, new DateTimeZone($timezone));
            $dt->modify('+1 hour');
            $endDateTime = $dt->format('Y-m-d\TH:i:s');
        } else {
            $endDateTime = $endDate . 'T23:59:59';
        }
    }
    
    // Determine category based on namespace FIRST (takes precedence)
    $namespace = isset($dwEvent['namespace']) ? $dwEvent['namespace'] : '';
    $category = null;
    
    // Priority 1: Namespace mapping
    if (!empty($namespace) && isset($config['category_mapping'][$namespace])) {
        $category = $config['category_mapping'][$namespace];
    }
    
    // Priority 2: Color mapping (fallback if no namespace or namespace not mapped)
    if ($category === null && isset($dwEvent['color'])) {
        $colorToCategoryMap = [
            '#3498db' => 'Blue Category',      // Blue
            '#2ecc71' => 'Green Category',     // Green
            '#f39c12' => 'Orange Category',    // Orange
            '#e74c3c' => 'Red Category',       // Red
            '#f1c40f' => 'Yellow Category',    // Yellow
            '#9b59b6' => 'Purple Category',    // Purple
        ];
        
        $eventColor = strtolower($dwEvent['color']);
        foreach ($colorToCategoryMap as $color => $cat) {
            if (strtolower($color) === $eventColor) {
                $category = $cat;
                break;
            }
        }
    }
    
    // Priority 3: Default category
    if ($category === null) {
        $category = $config['default_category'];
    }
    
    // Clean and sanitize text fields
    $title = isset($dwEvent['title']) ? trim($dwEvent['title']) : 'Untitled Event';
    $description = isset($dwEvent['description']) ? trim($dwEvent['description']) : '';
    
    // Remove any null bytes and control characters that can break JSON
    $title = preg_replace('/[\x00-\x1F\x7F]/u', '', $title);
    $description = preg_replace('/[\x00-\x1F\x7F]/u', '', $description);
    
    // Ensure proper UTF-8 encoding
    if (!mb_check_encoding($title, 'UTF-8')) {
        $title = mb_convert_encoding($title, 'UTF-8', 'UTF-8');
    }
    if (!mb_check_encoding($description, 'UTF-8')) {
        $description = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
    }
    
    // Build Outlook event structure
    if ($isAllDay) {
        // All-day events use different format (no time component, no timezone)
        $outlookEvent = [
            'subject' => $title,
            'body' => [
                'contentType' => 'text',
                'content' => $description
            ],
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => 'UTC'  // All-day events should use UTC
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => 'UTC'
            ],
            'isAllDay' => true,
            'categories' => [$category],
            'isReminderOn' => false,  // All-day events typically don't need reminders
            'singleValueExtendedProperties' => [
                [
                    'id' => 'String {66f5a359-4659-4830-9070-00047ec6ac6e} Name DokuWikiId',
                    'value' => $namespace . ':' . $dwEvent['id']
                ]
            ]
        ];
    } else {
        // Timed events
        $outlookEvent = [
            'subject' => $title,
            'body' => [
                'contentType' => 'text',
                'content' => $description
            ],
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => $timezone
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => $timezone
            ],
            'isAllDay' => false,
            'categories' => [$category],
            'isReminderOn' => true,
            'reminderMinutesBeforeStart' => $config['reminder_minutes'],
            'singleValueExtendedProperties' => [
                [
                    'id' => 'String {66f5a359-4659-4830-9070-00047ec6ac6e} Name DokuWikiId',
                    'value' => $namespace . ':' . $dwEvent['id']
                ]
            ]
        ];
    }
    
    return $outlookEvent;
}

// =============================================================================
// SYNC STATE MANAGEMENT
// =============================================================================

function loadSyncState($stateFile) {
    if (!file_exists($stateFile)) {
        return ['mapping' => [], 'last_sync' => 0];
    }
    
    $data = json_decode(file_get_contents($stateFile), true);
    return $data ?: ['mapping' => [], 'last_sync' => 0];
}

function saveSyncState($stateFile, $state) {
    $state['last_sync'] = time();
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
}

// =============================================================================
// MAIN SYNC LOGIC
// =============================================================================

try {
    // Initialize API client
    $client = new MicrosoftGraphClient($config);
    logMessage("Authenticating with Microsoft Graph API...");
    $client->getAccessToken();
    logMessage("Authentication successful");
    
    // Load sync state
    $state = loadSyncState($stateFile);
    $mapping = $state['mapping']; // dwId => outlookId
    
    // Reset mode - clear the mapping
    if ($reset) {
        logMessage("Resetting sync state...");
        $mapping = [];
    }
    
    // Load DokuWiki events
    logMessage("Loading DokuWiki calendar events...");
    $dwEvents = loadDokuWikiEvents($dokuwikiRoot, $filterNamespace);
    logMessage("Found " . count($dwEvents) . " events in DokuWiki");
    
    // Clean duplicates mode
    if ($cleanDuplicates) {
        logMessage("=== Cleaning Duplicates ===");
        $duplicatesFound = 0;
        $duplicatesDeleted = 0;
        
        foreach ($dwEvents as $dwId => $dwEvent) {
            $existingEvents = $client->findEventByDokuWikiId($config['user_email'], $dwId);
            
            if (count($existingEvents) > 1) {
                $duplicatesFound += count($existingEvents) - 1;
                logMessage("Found " . count($existingEvents) . " copies of: {$dwEvent['title']}");
                
                if (!$dryRun) {
                    $deleted = $client->deleteAllDuplicates($config['user_email'], $dwId);
                    $duplicatesDeleted += $deleted;
                    
                    // Update mapping with the remaining event
                    $remaining = $client->findEventByDokuWikiId($config['user_email'], $dwId);
                    if (count($remaining) == 1) {
                        $mapping[$dwId] = $remaining[0]['id'];
                    }
                }
            }
        }
        
        logMessage("=== Duplicate Cleanup Complete ===");
        logMessage("Duplicates found: $duplicatesFound");
        logMessage("Duplicates deleted: $duplicatesDeleted");
        
        if (!$dryRun) {
            $state['mapping'] = $mapping;
            saveSyncState($stateFile, $state);
        }
        
        exit(0);
    }
    
    // Track which Outlook events we've seen (to detect deletions)
    $seenOutlookIds = [];
    
    // Sync each DokuWiki event
    foreach ($dwEvents as $dwId => $dwEvent) {
        // Check for abort flag
        $abortFile = __DIR__ . '/.sync_abort';
        if (file_exists($abortFile)) {
            logMessage("=== SYNC ABORTED BY USER ===", 'WARN');
            logMessage("Partial sync completed. Some events may not be synced.", 'WARN');
            @unlink($abortFile); // Clean up abort flag
            break; // Exit the loop
        }
        
        // Skip completed tasks if configured
        if (!$config['sync_completed_tasks'] && 
            !empty($dwEvent['isTask']) && 
            !empty($dwEvent['completed'])) {
            $stats['skipped']++;
            continue;
        }
        
        $outlookEvent = convertToOutlookEvent($dwEvent, $config);
        
        try {
            // Check if we have this event mapped
            $outlookId = isset($mapping[$dwId]) ? $mapping[$dwId] : null;
            
            // If not mapped, search Outlook for it (might exist from previous sync)
            if (!$outlookId) {
                $existingEvents = $client->findEventByDokuWikiId($config['user_email'], $dwId);
                
                if (count($existingEvents) > 1) {
                    // Found duplicates! Clean them up
                    logMessage("WARN: Found " . count($existingEvents) . " duplicates for: {$dwEvent['title']}", 'WARN');
                    
                    if (!$dryRun) {
                        $deleted = $client->deleteAllDuplicates($config['user_email'], $dwId);
                        logMessage("Deleted $deleted duplicate(s)", 'INFO');
                        
                        // Re-search to get the remaining one
                        $existingEvents = $client->findEventByDokuWikiId($config['user_email'], $dwId);
                    }
                }
                
                if (count($existingEvents) == 1) {
                    // Found existing event
                    $outlookId = $existingEvents[0]['id'];
                    $mapping[$dwId] = $outlookId;
                    logMessage("Mapped existing event: {$dwEvent['title']} (ID: $outlookId)", 'DEBUG');
                } elseif (count($existingEvents) > 1 && !$dryRun) {
                    // Still duplicates after cleanup? Just pick the first one
                    $outlookId = $existingEvents[0]['id'];
                    $mapping[$dwId] = $outlookId;
                    logMessage("WARN: Multiple versions remain, using first: {$dwEvent['title']}", 'WARN');
                }
            }
            
            if ($outlookId) {
                // Event exists in mapping - try to update it
                $seenOutlookIds[] = $outlookId;
                
                if (!$dryRun) {
                    try {
                        $client->updateEvent($config['user_email'], $outlookId, $outlookEvent);
                        $stats['updated']++;
                        $eventNamespace = isset($dwEvent['namespace']) ? $dwEvent['namespace'] : '';
                        logMessage("Updated: {$dwEvent['title']} [$eventNamespace]");
                    } catch (Exception $e) {
                        // Check if it's a 404 (event was deleted from Outlook)
                        if (strpos($e->getMessage(), 'HTTP 404') !== false || 
                            strpos($e->getMessage(), 'ErrorItemNotFound') !== false) {
                            
                            logMessage("Event deleted from Outlook, recreating: {$dwEvent['title']}", 'WARN');
                            
                            // Remove from mapping and recreate
                            unset($mapping[$dwId]);
                            $result = $client->createEvent($config['user_email'], $outlookEvent);
                            $mapping[$dwId] = $result['id'];
                            $seenOutlookIds[] = $result['id'];
                            $stats['recreated']++;
                            $eventNamespace = isset($dwEvent['namespace']) ? $dwEvent['namespace'] : '';
                            logMessage("Recreated: {$dwEvent['title']} [$eventNamespace] (new ID: {$result['id']})", 'INFO');
                        } else {
                            // Different error - rethrow
                            throw $e;
                        }
                    }
                } else {
                    $stats['updated']++;
                    $eventNamespace = isset($dwEvent['namespace']) ? $dwEvent['namespace'] : '';
                    logMessage("Would update: {$dwEvent['title']} [$eventNamespace]");
                }
                
            } else {
                // New event - create in Outlook
                if (!$dryRun) {
                    $result = $client->createEvent($config['user_email'], $outlookEvent);
                    $mapping[$dwId] = $result['id'];
                    $seenOutlookIds[] = $result['id'];
                    $eventNamespace = isset($dwEvent['namespace']) ? $dwEvent['namespace'] : '';
                    logMessage("Created: {$dwEvent['title']} [$eventNamespace] (ID: {$result['id']})", 'DEBUG');
                } else {
                    $eventNamespace = isset($dwEvent['namespace']) ? $dwEvent['namespace'] : '';
                    logMessage("Would create: {$dwEvent['title']} [$eventNamespace]");
                }
                $stats['created']++;
            }
            
            // Save state periodically (every 50 events)
            if (!$dryRun && (count($seenOutlookIds) % 50 == 0)) {
                $state['mapping'] = $mapping;
                saveSyncState($stateFile, $state);
                logMessage("State saved (checkpoint)", 'DEBUG');
            }
            
        } catch (Exception $e) {
            $stats['errors']++;
            logMessage("ERROR syncing {$dwEvent['title']}: " . $e->getMessage(), 'ERROR');
        }
    }
    
    // Delete events that were removed from DokuWiki
    if ($config['delete_outlook_events']) {
        logMessage("=== Checking for deleted events ===", 'DEBUG');
        logMessage("Total in mapping: " . count($mapping), 'DEBUG');
        logMessage("Total seen (should exist): " . count($seenOutlookIds), 'DEBUG');
        
        $deletedCount = 0;
        foreach ($mapping as $dwId => $outlookId) {
            if (!in_array($outlookId, $seenOutlookIds)) {
                // Event was deleted in DokuWiki
                logMessage("Event $dwId not in DokuWiki anymore (Outlook ID: $outlookId)", 'DEBUG');
                try {
                    if (!$dryRun) {
                        $client->deleteEvent($config['user_email'], $outlookId);
                        unset($mapping[$dwId]);
                        logMessage("Deleted from Outlook: $dwId", 'INFO');
                    } else {
                        logMessage("Would delete: $dwId", 'INFO');
                    }
                    $stats['deleted']++;
                    $deletedCount++;
                } catch (Exception $e) {
                    // If it's already gone (404), just remove from mapping
                    if (strpos($e->getMessage(), 'HTTP 404') !== false || 
                        strpos($e->getMessage(), 'ErrorItemNotFound') !== false) {
                        logMessage("Event $dwId already gone from Outlook, removing from mapping", 'DEBUG');
                        unset($mapping[$dwId]);
                        $stats['deleted']++;
                        $deletedCount++;
                    } else {
                        logMessage("ERROR deleting $dwId: " . $e->getMessage(), 'ERROR');
                    }
                }
            }
        }
        logMessage("Deleted $deletedCount events from Outlook", 'DEBUG');
    }
    
    // Save state
    if (!$dryRun) {
        $state['mapping'] = $mapping;
        saveSyncState($stateFile, $state);
    }
    
    // Summary
    logMessage("=== Sync Complete ===");
    logMessage("Scanned: {$stats['scanned']} events");
    logMessage("Created: {$stats['created']}");
    logMessage("Updated: {$stats['updated']}");
    logMessage("Recreated: {$stats['recreated']} (deleted from Outlook)");
    logMessage("Deleted: {$stats['deleted']}");
    logMessage("Skipped: {$stats['skipped']}");
    logMessage("Errors: {$stats['errors']}");
    
    // =============================================================================
    // FINAL STEP: Check for and remove any duplicates in Outlook
    // =============================================================================
    
    if (!$dryRun) {
        logMessage("");
        logMessage("=== Final Duplicate Check ===");
        
        $duplicatesFound = 0;
        $duplicatesRemoved = 0;
        
        // Check each event we synced for duplicates
        foreach ($dwEvents as $dwId => $dwEvent) {
            try {
                // Search Outlook for this DokuWiki ID
                $outlookEvents = $client->findEventByDokuWikiId($config['user_email'], $dwId);
                
                if (count($outlookEvents) > 1) {
                    $duplicatesFound += count($outlookEvents) - 1;
                    logMessage("DUPLICATE: Found " . count($outlookEvents) . " copies of: {$dwEvent['title']} [$dwId]", 'WARN');
                    
                    // Keep the first one, delete the rest
                    $kept = array_shift($outlookEvents); // Remove first from array
                    $mapping[$dwId] = $kept['id']; // Update mapping to first one
                    
                    foreach ($outlookEvents as $duplicate) {
                        try {
                            $client->deleteEvent($config['user_email'], $duplicate['id']);
                            $duplicatesRemoved++;
                            logMessage("  Removed duplicate: " . $duplicate['id'], 'DEBUG');
                        } catch (Exception $e) {
                            logMessage("  ERROR removing duplicate: " . $e->getMessage(), 'ERROR');
                        }
                    }
                }
            } catch (Exception $e) {
                // Continue checking other events even if one fails
                logMessage("ERROR checking duplicates for {$dwEvent['title']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        if ($duplicatesFound > 0) {
            logMessage("");
            logMessage("Duplicates found: $duplicatesFound");
            logMessage("Duplicates removed: $duplicatesRemoved");
            
            // Save updated mapping
            $state['mapping'] = $mapping;
            saveSyncState($stateFile, $state);
            logMessage("Mapping updated after duplicate cleanup");
        } else {
            logMessage("No duplicates found - Outlook is clean!");
        }
    }
    
    logMessage("");
    if ($dryRun) {
        logMessage("DRY RUN - No changes were made");
    } else {
        logMessage("Sync completed successfully!");
    }
    
    exit($stats['errors'] > 0 ? 1 : 0);
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage(), 'ERROR');
    exit(1);
}

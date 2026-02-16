<?php
/**
 * DokuWiki Plugin calendar (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 * @version 7.0.8
 */

if (!defined('DOKU_INC')) die();

// Set to true to enable verbose debug logging (should be false in production)
if (!defined('CALENDAR_DEBUG')) {
    define('CALENDAR_DEBUG', false);
}

// Load new class dependencies
require_once __DIR__ . '/classes/FileHandler.php';
require_once __DIR__ . '/classes/EventCache.php';
require_once __DIR__ . '/classes/RateLimiter.php';
require_once __DIR__ . '/classes/EventManager.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/classes/GoogleCalendarSync.php';

class action_plugin_calendar extends DokuWiki_Action_Plugin {
    
    /** @var CalendarAuditLogger */
    private $auditLogger = null;
    
    /** @var GoogleCalendarSync */
    private $googleSync = null;
    
    /**
     * Get the audit logger instance
     */
    private function getAuditLogger() {
        if ($this->auditLogger === null) {
            $this->auditLogger = new CalendarAuditLogger();
        }
        return $this->auditLogger;
    }
    
    /**
     * Get the Google Calendar sync instance
     */
    private function getGoogleSync() {
        if ($this->googleSync === null) {
            $this->googleSync = new GoogleCalendarSync();
        }
        return $this->googleSync;
    }
    
    /**
     * Log debug message only if CALENDAR_DEBUG is enabled
     */
    private function debugLog($message) {
        if (CALENDAR_DEBUG) {
            error_log($message);
        }
    }
    
    /**
     * Safely read and decode a JSON file with error handling
     * Uses the new CalendarFileHandler for atomic reads with locking
     * @param string $filepath Path to JSON file
     * @return array Decoded array or empty array on error
     */
    private function safeJsonRead($filepath) {
        return CalendarFileHandler::readJson($filepath);
    }
    
    /**
     * Safely write JSON data to file with atomic writes
     * Uses the new CalendarFileHandler for atomic writes with locking
     * @param string $filepath Path to JSON file
     * @param array $data Data to write
     * @return bool Success status
     */
    private function safeJsonWrite($filepath, array $data) {
        return CalendarFileHandler::writeJson($filepath, $data);
    }

    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'addAssets');
    }

    public function handleAjax(Doku_Event $event, $param) {
        if ($event->data !== 'plugin_calendar') return;
        $event->preventDefault();
        $event->stopPropagation();

        $action = $_REQUEST['action'] ?? '';
        
        // Actions that modify data require authentication and CSRF token verification
        $writeActions = ['save_event', 'delete_event', 'toggle_task', 'cleanup_empty_namespaces',
                         'trim_all_past_recurring', 'rescan_recurring', 'extend_recurring',
                         'trim_recurring', 'pause_recurring', 'resume_recurring',
                         'change_start_recurring', 'change_pattern_recurring'];
        
        $isWriteAction = in_array($action, $writeActions);
        
        // Rate limiting check - apply to all AJAX actions
        if (!CalendarRateLimiter::check($action, $isWriteAction)) {
            CalendarRateLimiter::addHeaders($action, $isWriteAction);
            http_response_code(429);
            echo json_encode([
                'success' => false, 
                'error' => 'Rate limit exceeded. Please wait before making more requests.',
                'retry_after' => CalendarRateLimiter::getRemaining($action, $isWriteAction)['reset']
            ]);
            return;
        }
        
        // Add rate limit headers to all responses
        CalendarRateLimiter::addHeaders($action, $isWriteAction);
        
        if ($isWriteAction) {
            global $INPUT, $INFO;
            
            // Check if user is logged in (at minimum)
            if (empty($_SERVER['REMOTE_USER'])) {
                echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
                return;
            }
            
            // Check for valid security token - try multiple sources
            $sectok = $INPUT->str('sectok', '');
            if (empty($sectok)) {
                $sectok = $_REQUEST['sectok'] ?? '';
            }
            
            // Use DokuWiki's built-in check
            if (!checkSecurityToken($sectok)) {
                // Log for debugging
                $this->debugLog("Security token check failed. Received: '$sectok'");
                echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page and try again.']);
                return;
            }
        }

        switch ($action) {
            case 'save_event':
                $this->saveEvent();
                break;
            case 'delete_event':
                $this->deleteEvent();
                break;
            case 'get_event':
                $this->getEvent();
                break;
            case 'load_month':
                $this->loadMonth();
                break;
            case 'get_static_calendar':
                $this->getStaticCalendar();
                break;
            case 'search_all':
                $this->searchAllDates();
                break;
            case 'toggle_task':
                $this->toggleTaskComplete();
                break;
            case 'google_auth_url':
                $this->getGoogleAuthUrl();
                break;
            case 'google_callback':
                $this->handleGoogleCallback();
                break;
            case 'google_status':
                $this->getGoogleStatus();
                break;
            case 'google_calendars':
                $this->getGoogleCalendars();
                break;
            case 'google_import':
                $this->googleImport();
                break;
            case 'google_export':
                $this->googleExport();
                break;
            case 'google_disconnect':
                $this->googleDisconnect();
                break;
            case 'cleanup_empty_namespaces':
            case 'trim_all_past_recurring':
            case 'rescan_recurring':
            case 'extend_recurring':
            case 'trim_recurring':
            case 'pause_recurring':
            case 'resume_recurring':
            case 'change_start_recurring':
            case 'change_pattern_recurring':
                $this->routeToAdmin($action);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
    }
    
    /**
     * Route AJAX actions to admin plugin methods
     */
    private function routeToAdmin($action) {
        $admin = plugin_load('admin', 'calendar');
        if ($admin && method_exists($admin, 'handleAjaxAction')) {
            $admin->handleAjaxAction($action);
        } else {
            echo json_encode(['success' => false, 'error' => 'Admin handler not available']);
        }
    }

    private function saveEvent() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $date = $INPUT->str('date');
        $eventId = $INPUT->str('eventId', '');
        $title = $INPUT->str('title');
        $time = $INPUT->str('time', '');
        $endTime = $INPUT->str('endTime', '');
        $description = $INPUT->str('description', '');
        $color = $INPUT->str('color', '#3498db');
        $oldDate = $INPUT->str('oldDate', ''); // Track original date for moves
        $isTask = $INPUT->bool('isTask', false);
        $completed = $INPUT->bool('completed', false);
        $endDate = $INPUT->str('endDate', '');
        $isRecurring = $INPUT->bool('isRecurring', false);
        $recurrenceType = $INPUT->str('recurrenceType', 'weekly');
        $recurrenceEnd = $INPUT->str('recurrenceEnd', '');
        
        // New recurrence options
        $recurrenceInterval = $INPUT->int('recurrenceInterval', 1);
        if ($recurrenceInterval < 1) $recurrenceInterval = 1;
        if ($recurrenceInterval > 99) $recurrenceInterval = 99;
        
        $weekDaysStr = $INPUT->str('weekDays', '');
        $weekDays = $weekDaysStr ? array_map('intval', explode(',', $weekDaysStr)) : [];
        
        $monthlyType = $INPUT->str('monthlyType', 'dayOfMonth');
        $monthDay = $INPUT->int('monthDay', 0);
        $ordinalWeek = $INPUT->int('ordinalWeek', 1);
        $ordinalDay = $INPUT->int('ordinalDay', 0);
        
        $this->debugLog("=== Calendar saveEvent START ===");
        $this->debugLog("Calendar saveEvent: INPUT namespace='$namespace', eventId='$eventId', date='$date', oldDate='$oldDate', title='$title'");
        $this->debugLog("Calendar saveEvent: Recurrence - type='$recurrenceType', interval=$recurrenceInterval, weekDays=" . implode(',', $weekDays) . ", monthlyType='$monthlyType'");
        
        if (!$date || !$title) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            echo json_encode(['success' => false, 'error' => 'Invalid date format']);
            return;
        }
        
        // Validate oldDate if provided
        if ($oldDate && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $oldDate) || !strtotime($oldDate))) {
            echo json_encode(['success' => false, 'error' => 'Invalid old date format']);
            return;
        }
        
        // Validate endDate if provided
        if ($endDate && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || !strtotime($endDate))) {
            echo json_encode(['success' => false, 'error' => 'Invalid end date format']);
            return;
        }
        
        // Validate time format (HH:MM) if provided
        if ($time && !preg_match('/^\d{2}:\d{2}$/', $time)) {
            echo json_encode(['success' => false, 'error' => 'Invalid time format']);
            return;
        }
        
        // Validate endTime format if provided
        if ($endTime && !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            echo json_encode(['success' => false, 'error' => 'Invalid end time format']);
            return;
        }
        
        // Validate color format (hex color)
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#3498db'; // Reset to default if invalid
        }
        
        // Validate namespace (prevent path traversal)
        if ($namespace && !preg_match('/^[a-zA-Z0-9_:;*-]*$/', $namespace)) {
            echo json_encode(['success' => false, 'error' => 'Invalid namespace format']);
            return;
        }
        
        // Validate recurrence type
        $validRecurrenceTypes = ['daily', 'weekly', 'biweekly', 'monthly', 'yearly'];
        if ($isRecurring && !in_array($recurrenceType, $validRecurrenceTypes)) {
            $recurrenceType = 'weekly';
        }
        
        // Validate recurrenceEnd if provided
        if ($recurrenceEnd && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $recurrenceEnd) || !strtotime($recurrenceEnd))) {
            echo json_encode(['success' => false, 'error' => 'Invalid recurrence end date format']);
            return;
        }
        
        // Sanitize title length
        $title = substr(trim($title), 0, 500);
        
        // Sanitize description length
        $description = substr($description, 0, 10000);
        
        // If editing, find the event's ACTUAL namespace (for finding/deleting old event)
        // We need to search ALL namespaces because user may be changing namespace
        $oldNamespace = null;  // null means "not found yet"
        if ($eventId) {
            // Use oldDate if available (date was changed), otherwise use current date
            $searchDate = ($oldDate && $oldDate !== $date) ? $oldDate : $date;
            
            // Search using wildcard to find event in ANY namespace
            $foundNamespace = $this->findEventNamespace($eventId, $searchDate, '*');
            
            if ($foundNamespace !== null) {
                $oldNamespace = $foundNamespace;  // Could be '' for default namespace
                $this->debugLog("Calendar saveEvent: Found existing event in namespace '$oldNamespace'");
            } else {
                $this->debugLog("Calendar saveEvent: Event $eventId not found in any namespace");
            }
        }
        
        // Use the namespace provided by the user (allow namespace changes!)
        // But normalize wildcards and multi-namespace to empty for NEW events
        if (!$eventId) {
            $this->debugLog("Calendar saveEvent: NEW event, received namespace='$namespace'");
            // Normalize namespace: treat wildcards and multi-namespace as empty (default) for NEW events
            if (!empty($namespace) && (strpos($namespace, '*') !== false || strpos($namespace, ';') !== false)) {
                $this->debugLog("Calendar saveEvent: Namespace contains wildcard/multi, clearing to empty");
                $namespace = '';
            } else {
                $this->debugLog("Calendar saveEvent: Namespace is clean, keeping as '$namespace'");
            }
        } else {
            $this->debugLog("Calendar saveEvent: EDITING event $eventId, user selected namespace='$namespace'");
        }
        
        // Generate event ID if new
        $generatedId = $eventId ?: uniqid();
        
        // If editing a recurring event, load existing data to preserve unchanged fields
        $existingEventData = null;
        if ($eventId && $isRecurring) {
            $searchDate = ($oldDate && $oldDate !== $date) ? $oldDate : $date;
            // Use null coalescing: if oldNamespace is null (not found), use new namespace; if '' (default), use ''
            $existingEventData = $this->getExistingEventData($eventId, $searchDate, $oldNamespace ?? $namespace);
            if ($existingEventData) {
                $this->debugLog("Calendar saveEvent recurring: Loaded existing data - namespace='" . ($existingEventData['namespace'] ?? 'NOT SET') . "'");
            }
        }
        
        // If recurring, generate multiple events
        if ($isRecurring) {
            // Merge with existing data if editing (preserve values that weren't changed)
            if ($existingEventData) {
                $title = $title ?: $existingEventData['title'];
                $time = $time ?: (isset($existingEventData['time']) ? $existingEventData['time'] : '');
                $endTime = $endTime ?: (isset($existingEventData['endTime']) ? $existingEventData['endTime'] : '');
                $description = $description ?: (isset($existingEventData['description']) ? $existingEventData['description'] : '');
                // Only use existing color if new color is default
                if ($color === '#3498db' && isset($existingEventData['color'])) {
                    $color = $existingEventData['color'];
                }
                
                // Preserve namespace in these cases:
                // 1. Namespace field is empty (user didn't select anything)
                // 2. Namespace contains wildcards (like "personal;work" or "work*")
                // 3. Namespace is the same as what was passed (no change intended)
                $receivedNamespace = $namespace;
                if (empty($namespace) || strpos($namespace, '*') !== false || strpos($namespace, ';') !== false) {
                    if (isset($existingEventData['namespace'])) {
                        $namespace = $existingEventData['namespace'];
                        $this->debugLog("Calendar saveEvent recurring: Preserving namespace '$namespace' (received='$receivedNamespace')");
                    } else {
                        $this->debugLog("Calendar saveEvent recurring: No existing namespace to preserve (received='$receivedNamespace')");
                    }
                } else {
                    $this->debugLog("Calendar saveEvent recurring: Using new namespace '$namespace' (received='$receivedNamespace')");
                }
            } else {
                $this->debugLog("Calendar saveEvent recurring: No existing data found, using namespace='$namespace'");
            }
            
            $this->createRecurringEvents($namespace, $date, $endDate, $title, $time, $endTime, $description, 
                                        $color, $isTask, $recurrenceType, $recurrenceInterval, $recurrenceEnd, 
                                        $weekDays, $monthlyType, $monthDay, $ordinalWeek, $ordinalDay, $generatedId);
            echo json_encode(['success' => true]);
            return;
        }
        
        list($year, $month, $day) = explode('-', $date);
        
        // NEW namespace directory (where we'll save)
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        $this->debugLog("Calendar saveEvent: NEW eventFile='$eventFile'");
        
        $events = [];
        if (file_exists($eventFile)) {
            $events = json_decode(file_get_contents($eventFile), true);
            $this->debugLog("Calendar saveEvent: Loaded " . count($events) . " dates from new location");
        } else {
            $this->debugLog("Calendar saveEvent: New location file does not exist yet");
        }
        
        // If editing and (date changed OR namespace changed), remove from old location first
        // $oldNamespace is null if event not found, '' for default namespace, or 'name' for named namespace
        $namespaceChanged = ($eventId && $oldNamespace !== null && $oldNamespace !== $namespace);
        $dateChanged = ($eventId && $oldDate && $oldDate !== $date);
        
        $this->debugLog("Calendar saveEvent: eventId='$eventId', oldNamespace=" . var_export($oldNamespace, true) . ", newNamespace='$namespace', namespaceChanged=" . ($namespaceChanged ? 'YES' : 'NO') . ", dateChanged=" . ($dateChanged ? 'YES' : 'NO'));
        
        if ($namespaceChanged || $dateChanged) {
            // Construct OLD data directory using OLD namespace
            $oldDataDir = DOKU_INC . 'data/meta/';
            if ($oldNamespace) {
                $oldDataDir .= str_replace(':', '/', $oldNamespace) . '/';
            }
            $oldDataDir .= 'calendar/';
            
            $deleteDate = $dateChanged ? $oldDate : $date;
            list($oldYear, $oldMonth, $oldDay) = explode('-', $deleteDate);
            $oldEventFile = $oldDataDir . sprintf('%04d-%02d.json', $oldYear, $oldMonth);
            
            $this->debugLog("Calendar saveEvent: Attempting to delete from OLD eventFile='$oldEventFile', deleteDate='$deleteDate'");
            
            if (file_exists($oldEventFile)) {
                $oldEvents = json_decode(file_get_contents($oldEventFile), true);
                $this->debugLog("Calendar saveEvent: OLD file exists, has " . count($oldEvents) . " dates");
                
                if (isset($oldEvents[$deleteDate])) {
                    $countBefore = count($oldEvents[$deleteDate]);
                    $oldEvents[$deleteDate] = array_values(array_filter($oldEvents[$deleteDate], function($evt) use ($eventId) {
                        return $evt['id'] !== $eventId;
                    }));
                    $countAfter = count($oldEvents[$deleteDate]);
                    
                    $this->debugLog("Calendar saveEvent: Events on date before=$countBefore, after=$countAfter");
                    
                    if (empty($oldEvents[$deleteDate])) {
                        unset($oldEvents[$deleteDate]);
                    }
                    
                    CalendarFileHandler::writeJson($oldEventFile, $oldEvents);
                    $this->debugLog("Calendar saveEvent: DELETED event from old location - namespace:'$oldNamespace', date:'$deleteDate'");
                } else {
                    $this->debugLog("Calendar saveEvent: No events found on deleteDate='$deleteDate' in old file");
                }
            } else {
                $this->debugLog("Calendar saveEvent: OLD file does NOT exist: $oldEventFile");
            }
        } else {
            $this->debugLog("Calendar saveEvent: No namespace/date change detected, skipping deletion from old location");
        }
        
        if (!isset($events[$date])) {
            $events[$date] = [];
        } elseif (!is_array($events[$date])) {
            // Fix corrupted data - ensure it's an array
            $this->debugLog("Calendar saveEvent: Fixing corrupted data at $date - was not an array");
            $events[$date] = [];
        }
        
        // Store the namespace with the event
        $eventData = [
            'id' => $generatedId,
            'title' => $title,
            'time' => $time,
            'endTime' => $endTime,
            'description' => $description,
            'color' => $color,
            'isTask' => $isTask,
            'completed' => $completed,
            'endDate' => $endDate,
            'namespace' => $namespace, // Store namespace with event
            'created' => date('Y-m-d H:i:s')
        ];
        
        // Debug logging
        $this->debugLog("Calendar saveEvent: Saving event '$title' with namespace='$namespace' to file $eventFile");
        
        // If editing, replace existing event
        if ($eventId) {
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
        } else {
            $events[$date][] = $eventData;
        }
        
        CalendarFileHandler::writeJson($eventFile, $events);
        
        // If event spans multiple months, add it to the first day of each subsequent month
        if ($endDate && $endDate !== $date) {
            $startDateObj = new DateTime($date);
            $endDateObj = new DateTime($endDate);
            
            // Get the month/year of the start date
            $startMonth = $startDateObj->format('Y-m');
            
            // Iterate through each month the event spans
            $currentDate = clone $startDateObj;
            $currentDate->modify('first day of next month'); // Jump to first of next month
            
            while ($currentDate <= $endDateObj) {
                $currentMonth = $currentDate->format('Y-m');
                $firstDayOfMonth = $currentDate->format('Y-m-01');
                
                list($currentYear, $currentMonthNum, $currentDay) = explode('-', $firstDayOfMonth);
                
                // Get the file for this month
                $currentEventFile = $dataDir . sprintf('%04d-%02d.json', $currentYear, $currentMonthNum);
                
                $currentEvents = [];
                if (file_exists($currentEventFile)) {
                    $contents = file_get_contents($currentEventFile);
                    $decoded = json_decode($contents, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $currentEvents = $decoded;
                    } else {
                        $this->debugLog("Calendar saveEvent: JSON decode error in $currentEventFile: " . json_last_error_msg());
                    }
                }
                
                // Add entry for the first day of this month
                if (!isset($currentEvents[$firstDayOfMonth])) {
                    $currentEvents[$firstDayOfMonth] = [];
                } elseif (!is_array($currentEvents[$firstDayOfMonth])) {
                    // Fix corrupted data - ensure it's an array
                    $this->debugLog("Calendar saveEvent: Fixing corrupted data at $firstDayOfMonth - was not an array");
                    $currentEvents[$firstDayOfMonth] = [];
                }
                
                // Create a copy with the original start date preserved
                $eventDataForMonth = $eventData;
                $eventDataForMonth['originalStartDate'] = $date; // Preserve the actual start date
                
                // Check if event already exists (when editing)
                $found = false;
                if ($eventId) {
                    foreach ($currentEvents[$firstDayOfMonth] as $key => $evt) {
                        if ($evt['id'] === $eventId) {
                            $currentEvents[$firstDayOfMonth][$key] = $eventDataForMonth;
                            $found = true;
                            break;
                        }
                    }
                }
                
                if (!$found) {
                    $currentEvents[$firstDayOfMonth][] = $eventDataForMonth;
                }
                
                CalendarFileHandler::writeJson($currentEventFile, $currentEvents);
                
                // Move to next month
                $currentDate->modify('first day of next month');
            }
        }
        
        // Audit logging
        $audit = $this->getAuditLogger();
        if ($eventId && ($dateChanged || $namespaceChanged)) {
            // Event was moved
            $audit->logMove($namespace, $oldDate ?: $date, $date, $generatedId, $title);
        } elseif ($eventId) {
            // Event was updated
            $audit->logUpdate($namespace, $date, $generatedId, $title);
        } else {
            // New event created
            $audit->logCreate($namespace, $date, $generatedId, $title);
        }
        
        echo json_encode(['success' => true, 'events' => $events, 'eventId' => $eventData['id']]);
    }

    private function deleteEvent() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $date = $INPUT->str('date');
        $eventId = $INPUT->str('eventId');
        
        // Find where the event actually lives
        $storedNamespace = $this->findEventNamespace($eventId, $date, $namespace);
        
        if ($storedNamespace === null) {
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            return;
        }
        
        // Use the found namespace
        $namespace = $storedNamespace;
        
        list($year, $month, $day) = explode('-', $date);
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        // First, get the event to check if it spans multiple months or is recurring
        $eventToDelete = null;
        $isRecurring = false;
        $recurringId = null;
        
        if (file_exists($eventFile)) {
            $events = json_decode(file_get_contents($eventFile), true);
            
            if (isset($events[$date])) {
                foreach ($events[$date] as $event) {
                    if ($event['id'] === $eventId) {
                        $eventToDelete = $event;
                        $isRecurring = isset($event['recurring']) && $event['recurring'];
                        $recurringId = isset($event['recurringId']) ? $event['recurringId'] : null;
                        break;
                    }
                }
                
                $events[$date] = array_values(array_filter($events[$date], function($event) use ($eventId) {
                    return $event['id'] !== $eventId;
                }));
                
                if (empty($events[$date])) {
                    unset($events[$date]);
                }
                
                CalendarFileHandler::writeJson($eventFile, $events);
            }
        }
        
        // If this is a recurring event, delete ALL occurrences with the same recurringId
        if ($isRecurring && $recurringId) {
            $this->deleteAllRecurringInstances($recurringId, $namespace, $dataDir);
        }
        
        // If event spans multiple months, delete it from the first day of each subsequent month
        if ($eventToDelete && isset($eventToDelete['endDate']) && $eventToDelete['endDate'] && $eventToDelete['endDate'] !== $date) {
            $startDateObj = new DateTime($date);
            $endDateObj = new DateTime($eventToDelete['endDate']);
            
            // Iterate through each month the event spans
            $currentDate = clone $startDateObj;
            $currentDate->modify('first day of next month'); // Jump to first of next month
            
            while ($currentDate <= $endDateObj) {
                $firstDayOfMonth = $currentDate->format('Y-m-01');
                list($currentYear, $currentMonth, $currentDay) = explode('-', $firstDayOfMonth);
                
                // Get the file for this month
                $currentEventFile = $dataDir . sprintf('%04d-%02d.json', $currentYear, $currentMonth);
                
                if (file_exists($currentEventFile)) {
                    $currentEvents = json_decode(file_get_contents($currentEventFile), true);
                    
                    if (isset($currentEvents[$firstDayOfMonth])) {
                        $currentEvents[$firstDayOfMonth] = array_values(array_filter($currentEvents[$firstDayOfMonth], function($event) use ($eventId) {
                            return $event['id'] !== $eventId;
                        }));
                        
                        if (empty($currentEvents[$firstDayOfMonth])) {
                            unset($currentEvents[$firstDayOfMonth]);
                        }
                        
                        CalendarFileHandler::writeJson($currentEventFile, $currentEvents);
                    }
                }
                
                // Move to next month
                $currentDate->modify('first day of next month');
            }
        }
        
        // Audit logging
        $audit = $this->getAuditLogger();
        $eventTitle = $eventToDelete ? ($eventToDelete['title'] ?? '') : '';
        $audit->logDelete($namespace, $date, $eventId, $eventTitle);
        
        echo json_encode(['success' => true]);
    }

    private function getEvent() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $date = $INPUT->str('date');
        $eventId = $INPUT->str('eventId');
        
        // Find where the event actually lives
        $storedNamespace = $this->findEventNamespace($eventId, $date, $namespace);
        
        if ($storedNamespace === null) {
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            return;
        }
        
        // Use the found namespace
        $namespace = $storedNamespace;
        
        list($year, $month, $day) = explode('-', $date);
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        if (file_exists($eventFile)) {
            $events = json_decode(file_get_contents($eventFile), true);
            
            if (isset($events[$date])) {
                foreach ($events[$date] as $event) {
                    if ($event['id'] === $eventId) {
                        // Include the namespace so JavaScript knows where this event actually lives
                        $event['namespace'] = $namespace;
                        echo json_encode(['success' => true, 'event' => $event]);
                        return;
                    }
                }
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Event not found']);
    }

    private function loadMonth() {
        global $INPUT;
        
        // Prevent caching of AJAX responses
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $namespace = $INPUT->str('namespace', '');
        $year = $INPUT->int('year');
        $month = $INPUT->int('month');
        
        // Validate year (reasonable range: 1970-2100)
        if ($year < 1970 || $year > 2100) {
            $year = (int)date('Y');
        }
        
        // Validate month (1-12)
        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }
        
        // Validate namespace format
        if ($namespace && !preg_match('/^[a-zA-Z0-9_:;*-]*$/', $namespace)) {
            echo json_encode(['success' => false, 'error' => 'Invalid namespace format']);
            return;
        }
        
        $this->debugLog("=== Calendar loadMonth DEBUG ===");
        $this->debugLog("Requested: year=$year, month=$month, namespace='$namespace'");
        
        // Check if multi-namespace or wildcard
        $isMultiNamespace = !empty($namespace) && (strpos($namespace, ';') !== false || strpos($namespace, '*') !== false);
        
        $this->debugLog("isMultiNamespace: " . ($isMultiNamespace ? 'true' : 'false'));
        
        if ($isMultiNamespace) {
            $events = $this->loadEventsMultiNamespace($namespace, $year, $month);
        } else {
            $events = $this->loadEventsSingleNamespace($namespace, $year, $month);
        }
        
        $this->debugLog("Returning " . count($events) . " date keys");
        foreach ($events as $dateKey => $dayEvents) {
            $this->debugLog("  dateKey=$dateKey has " . count($dayEvents) . " events");
        }
        
        echo json_encode([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'events' => $events
        ]);
    }
    
    /**
     * Get static calendar HTML via AJAX for navigation
     */
    private function getStaticCalendar() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $year = $INPUT->int('year');
        $month = $INPUT->int('month');
        
        // Validate
        if ($year < 1970 || $year > 2100) {
            $year = (int)date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }
        
        // Get syntax plugin to render the static calendar
        $syntax = plugin_load('syntax', 'calendar');
        if (!$syntax) {
            echo json_encode(['success' => false, 'error' => 'Syntax plugin not found']);
            return;
        }
        
        // Build data array for render
        $data = [
            'year' => $year,
            'month' => $month,
            'namespace' => $namespace,
            'static' => true
        ];
        
        // Call the render method via reflection (since renderStaticCalendar is private)
        $reflector = new \ReflectionClass($syntax);
        $method = $reflector->getMethod('renderStaticCalendar');
        $method->setAccessible(true);
        $html = $method->invoke($syntax, $data);
        
        echo json_encode([
            'success' => true,
            'html' => $html
        ]);
    }
    
    private function loadEventsSingleNamespace($namespace, $year, $month) {
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        // Load ONLY current month
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        $events = [];
        if (file_exists($eventFile)) {
            $contents = file_get_contents($eventFile);
            $decoded = json_decode($contents, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $events = $decoded;
            }
        }
        
        return $events;
    }
    
    private function loadEventsMultiNamespace($namespaces, $year, $month) {
        // Check for wildcard pattern
        if (preg_match('/^(.+):\*$/', $namespaces, $matches)) {
            $baseNamespace = $matches[1];
            return $this->loadEventsWildcard($baseNamespace, $year, $month);
        }
        
        // Check for root wildcard
        if ($namespaces === '*') {
            return $this->loadEventsWildcard('', $year, $month);
        }
        
        // Parse namespace list (semicolon separated)
        $namespaceList = array_map('trim', explode(';', $namespaces));
        
        // Load events from all namespaces
        $allEvents = [];
        foreach ($namespaceList as $ns) {
            $ns = trim($ns);
            if (empty($ns)) continue;
            
            $events = $this->loadEventsSingleNamespace($ns, $year, $month);
            
            // Add namespace tag to each event
            foreach ($events as $dateKey => $dayEvents) {
                if (!isset($allEvents[$dateKey])) {
                    $allEvents[$dateKey] = [];
                }
                foreach ($dayEvents as $event) {
                    $event['_namespace'] = $ns;
                    $allEvents[$dateKey][] = $event;
                }
            }
        }
        
        return $allEvents;
    }
    
    private function loadEventsWildcard($baseNamespace, $year, $month) {
        $dataDir = DOKU_INC . 'data/meta/';
        if ($baseNamespace) {
            $dataDir .= str_replace(':', '/', $baseNamespace) . '/';
        }
        
        $allEvents = [];
        
        // First, load events from the base namespace itself
        $events = $this->loadEventsSingleNamespace($baseNamespace, $year, $month);
        
        foreach ($events as $dateKey => $dayEvents) {
            if (!isset($allEvents[$dateKey])) {
                $allEvents[$dateKey] = [];
            }
            foreach ($dayEvents as $event) {
                $event['_namespace'] = $baseNamespace;
                $allEvents[$dateKey][] = $event;
            }
        }
        
        // Recursively find all subdirectories
        $this->findSubNamespaces($dataDir, $baseNamespace, $year, $month, $allEvents);
        
        return $allEvents;
    }
    
    private function findSubNamespaces($dir, $baseNamespace, $year, $month, &$allEvents) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . $item;
            if (is_dir($path) && $item !== 'calendar') {
                // This is a namespace directory
                $namespace = $baseNamespace ? $baseNamespace . ':' . $item : $item;
                
                // Load events from this namespace
                $events = $this->loadEventsSingleNamespace($namespace, $year, $month);
                foreach ($events as $dateKey => $dayEvents) {
                    if (!isset($allEvents[$dateKey])) {
                        $allEvents[$dateKey] = [];
                    }
                    foreach ($dayEvents as $event) {
                        $event['_namespace'] = $namespace;
                        $allEvents[$dateKey][] = $event;
                    }
                }
                
                // Recurse into subdirectories
                $this->findSubNamespaces($path . '/', $namespace, $year, $month, $allEvents);
            }
        }
    }

    /**
     * Search all dates for events matching the search term
     */
    private function searchAllDates() {
        global $INPUT;
        
        $searchTerm = strtolower(trim($INPUT->str('search', '')));
        $namespace = $INPUT->str('namespace', '');
        
        if (strlen($searchTerm) < 2) {
            echo json_encode(['success' => false, 'error' => 'Search term too short']);
            return;
        }
        
        // Normalize search term for fuzzy matching
        $normalizedSearch = $this->normalizeForSearch($searchTerm);
        
        $results = [];
        $dataDir = DOKU_INC . 'data/meta/';
        
        // Helper to search calendar directory
        $searchCalendarDir = function($calDir, $eventNamespace) use ($normalizedSearch, &$results) {
            if (!is_dir($calDir)) return;
            
            foreach (glob($calDir . '/*.json') as $file) {
                $data = @json_decode(file_get_contents($file), true);
                if (!$data || !is_array($data)) continue;
                
                foreach ($data as $dateKey => $dayEvents) {
                    // Skip non-date keys
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) continue;
                    if (!is_array($dayEvents)) continue;
                    
                    foreach ($dayEvents as $event) {
                        if (!isset($event['title'])) continue;
                        
                        // Build searchable text
                        $searchableText = strtolower($event['title']);
                        if (isset($event['description'])) {
                            $searchableText .= ' ' . strtolower($event['description']);
                        }
                        
                        // Normalize for fuzzy matching
                        $normalizedText = $this->normalizeForSearch($searchableText);
                        
                        // Check if matches using fuzzy match
                        if ($this->fuzzyMatchText($normalizedText, $normalizedSearch)) {
                            $results[] = [
                                'date' => $dateKey,
                                'title' => $event['title'],
                                'time' => isset($event['time']) ? $event['time'] : '',
                                'endTime' => isset($event['endTime']) ? $event['endTime'] : '',
                                'color' => isset($event['color']) ? $event['color'] : '',
                                'namespace' => isset($event['namespace']) ? $event['namespace'] : $eventNamespace,
                                'id' => isset($event['id']) ? $event['id'] : ''
                            ];
                        }
                    }
                }
            }
        };
        
        // Search root calendar directory
        $searchCalendarDir($dataDir . 'calendar', '');
        
        // Search namespace directories
        $this->searchNamespaceDirs($dataDir, $searchCalendarDir);
        
        // Sort results by date (newest first for past, oldest first for future)
        usort($results, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        // Limit results
        $results = array_slice($results, 0, 50);
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'total' => count($results)
        ]);
    }
    
    /**
     * Check if normalized text matches normalized search term
     * Supports multi-word search where all words must be present
     */
    private function fuzzyMatchText($normalizedText, $normalizedSearch) {
        // Direct substring match
        if (strpos($normalizedText, $normalizedSearch) !== false) {
            return true;
        }
        
        // Multi-word search: all words must be present
        $searchWords = array_filter(explode(' ', $normalizedSearch));
        if (count($searchWords) > 1) {
            foreach ($searchWords as $word) {
                if (strlen($word) > 0 && strpos($normalizedText, $word) === false) {
                    return false;
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Normalize text for fuzzy search matching
     * Removes apostrophes, extra spaces, and common variations
     */
    private function normalizeForSearch($text) {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove apostrophes and quotes (father's -> fathers)
        $text = preg_replace('/[\x27\x60\x22\xE2\x80\x98\xE2\x80\x99\xE2\x80\x9C\xE2\x80\x9D]/u', '', $text);
        
        // Normalize dashes and underscores to spaces
        $text = preg_replace('/[-_\x{2013}\x{2014}]/u', ' ', $text);
        
        // Remove other punctuation but keep letters, numbers, spaces
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        
        // Normalize multiple spaces to single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Recursively search namespace directories for calendar data
     */
    private function searchNamespaceDirs($baseDir, $callback) {
        foreach (glob($baseDir . '*', GLOB_ONLYDIR) as $nsDir) {
            $name = basename($nsDir);
            if ($name === 'calendar') continue;
            
            $calDir = $nsDir . '/calendar';
            if (is_dir($calDir)) {
                $relPath = str_replace(DOKU_INC . 'data/meta/', '', $nsDir);
                $namespace = str_replace('/', ':', $relPath);
                $callback($calDir, $namespace);
            }
            
            // Recurse
            $this->searchNamespaceDirs($nsDir . '/', $callback);
        }
    }

    private function toggleTaskComplete() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $date = $INPUT->str('date');
        $eventId = $INPUT->str('eventId');
        $completed = $INPUT->bool('completed', false);
        
        // Find where the event actually lives
        $storedNamespace = $this->findEventNamespace($eventId, $date, $namespace);
        
        if ($storedNamespace === null) {
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            return;
        }
        
        // Use the found namespace
        $namespace = $storedNamespace;
        
        list($year, $month, $day) = explode('-', $date);
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        if (file_exists($eventFile)) {
            $events = json_decode(file_get_contents($eventFile), true);
            
            if (isset($events[$date])) {
                $eventTitle = '';
                foreach ($events[$date] as $key => $event) {
                    if ($event['id'] === $eventId) {
                        $events[$date][$key]['completed'] = $completed;
                        $eventTitle = $event['title'] ?? '';
                        break;
                    }
                }
                
                CalendarFileHandler::writeJson($eventFile, $events);
                
                // Audit logging
                $audit = $this->getAuditLogger();
                $audit->logTaskToggle($namespace, $date, $eventId, $eventTitle, $completed);
                
                echo json_encode(['success' => true, 'events' => $events]);
                return;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Event not found']);
    }
    
    // ========================================================================
    // GOOGLE CALENDAR SYNC HANDLERS
    // ========================================================================
    
    /**
     * Get Google OAuth authorization URL
     */
    private function getGoogleAuthUrl() {
        if (!auth_isadmin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        $sync = $this->getGoogleSync();
        
        if (!$sync->isConfigured()) {
            echo json_encode(['success' => false, 'error' => 'Google sync not configured. Please enter Client ID and Secret first.']);
            return;
        }
        
        // Build redirect URI
        $redirectUri = DOKU_URL . 'lib/exe/ajax.php?call=plugin_calendar&action=google_callback';
        
        $authUrl = $sync->getAuthUrl($redirectUri);
        
        echo json_encode(['success' => true, 'url' => $authUrl]);
    }
    
    /**
     * Handle Google OAuth callback
     */
    private function handleGoogleCallback() {
        global $INPUT;
        
        $code = $INPUT->str('code');
        $state = $INPUT->str('state');
        $error = $INPUT->str('error');
        
        // Check for OAuth error
        if ($error) {
            $this->showGoogleCallbackResult(false, 'Authorization denied: ' . $error);
            return;
        }
        
        if (!$code) {
            $this->showGoogleCallbackResult(false, 'No authorization code received');
            return;
        }
        
        $sync = $this->getGoogleSync();
        
        // Verify state for CSRF protection
        if (!$sync->verifyState($state)) {
            $this->showGoogleCallbackResult(false, 'Invalid state parameter');
            return;
        }
        
        // Exchange code for tokens
        $redirectUri = DOKU_URL . 'lib/exe/ajax.php?call=plugin_calendar&action=google_callback';
        $result = $sync->handleCallback($code, $redirectUri);
        
        if ($result['success']) {
            $this->showGoogleCallbackResult(true, 'Successfully connected to Google Calendar!');
        } else {
            $this->showGoogleCallbackResult(false, $result['error']);
        }
    }
    
    /**
     * Show OAuth callback result page
     */
    private function showGoogleCallbackResult($success, $message) {
        $status = $success ? 'Success!' : 'Error';
        $color = $success ? '#2ecc71' : '#e74c3c';
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Google Calendar - ' . $status . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               display: flex; align-items: center; justify-content: center; 
               min-height: 100vh; margin: 0; background: #f5f5f5; }
        .card { background: white; padding: 40px; border-radius: 12px; 
                box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        h1 { color: ' . $color . '; margin: 0 0 16px 0; }
        p { color: #666; margin: 0 0 24px 0; }
        button { background: #3498db; color: white; border: none; padding: 12px 24px;
                 border-radius: 6px; cursor: pointer; font-size: 14px; }
        button:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="card">
        <h1>' . ($success ? '✓' : '✕') . ' ' . $status . '</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <button onclick="window.close()">Close Window</button>
    </div>
    <script>
        // Notify parent window
        if (window.opener) {
            window.opener.postMessage({ type: "google_auth_complete", success: ' . ($success ? 'true' : 'false') . ' }, "*");
        }
    </script>
</body>
</html>';
    }
    
    /**
     * Get Google sync status
     */
    private function getGoogleStatus() {
        $sync = $this->getGoogleSync();
        echo json_encode(['success' => true, 'status' => $sync->getStatus()]);
    }
    
    /**
     * Get list of Google calendars
     */
    private function getGoogleCalendars() {
        if (!auth_isadmin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        $sync = $this->getGoogleSync();
        $result = $sync->getCalendars();
        echo json_encode($result);
    }
    
    /**
     * Import events from Google Calendar
     */
    private function googleImport() {
        global $INPUT;
        
        if (!auth_isadmin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        $namespace = $INPUT->str('namespace', '');
        $startDate = $INPUT->str('startDate', '');
        $endDate = $INPUT->str('endDate', '');
        
        $sync = $this->getGoogleSync();
        $result = $sync->importEvents($namespace, $startDate ?: null, $endDate ?: null);
        
        echo json_encode($result);
    }
    
    /**
     * Export events to Google Calendar
     */
    private function googleExport() {
        global $INPUT;
        
        if (!auth_isadmin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        $namespace = $INPUT->str('namespace', '');
        $startDate = $INPUT->str('startDate', '');
        $endDate = $INPUT->str('endDate', '');
        
        $sync = $this->getGoogleSync();
        $result = $sync->exportEvents($namespace, $startDate ?: null, $endDate ?: null);
        
        echo json_encode($result);
    }
    
    /**
     * Disconnect from Google Calendar
     */
    private function googleDisconnect() {
        if (!auth_isadmin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        $sync = $this->getGoogleSync();
        $sync->disconnect();
        
        echo json_encode(['success' => true]);
    }
    
    private function createRecurringEvents($namespace, $startDate, $endDate, $title, $time, $endTime,
                                          $description, $color, $isTask, $recurrenceType, $recurrenceInterval,
                                          $recurrenceEnd, $weekDays, $monthlyType, $monthDay, 
                                          $ordinalWeek, $ordinalDay, $baseId) {
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Ensure interval is at least 1
        if ($recurrenceInterval < 1) $recurrenceInterval = 1;
        
        // Set maximum end date if not specified (1 year from start)
        $maxEnd = $recurrenceEnd ?: date('Y-m-d', strtotime($startDate . ' +1 year'));
        
        // Calculate event duration for multi-day events
        $eventDuration = 0;
        if ($endDate && $endDate !== $startDate) {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $eventDuration = $start->diff($end)->days;
        }
        
        // Generate recurring events
        $currentDate = new DateTime($startDate);
        $endLimit = new DateTime($maxEnd);
        $counter = 0;
        $maxOccurrences = 365; // Allow up to 365 occurrences (e.g., daily for 1 year)
        
        // For weekly with specific days, we need to track the interval counter differently
        $weekCounter = 0;
        $startWeekNumber = (int)$currentDate->format('W');
        $startYear = (int)$currentDate->format('Y');
        
        while ($currentDate <= $endLimit && $counter < $maxOccurrences) {
            $shouldCreateEvent = false;
            
            switch ($recurrenceType) {
                case 'daily':
                    // Every N days from start
                    $daysSinceStart = $currentDate->diff(new DateTime($startDate))->days;
                    $shouldCreateEvent = ($daysSinceStart % $recurrenceInterval === 0);
                    break;
                    
                case 'weekly':
                    // Every N weeks, on specified days
                    $currentDayOfWeek = (int)$currentDate->format('w'); // 0=Sun, 6=Sat
                    
                    // Calculate weeks since start
                    $daysSinceStart = $currentDate->diff(new DateTime($startDate))->days;
                    $weeksSinceStart = floor($daysSinceStart / 7);
                    
                    // Check if we're in the right week (every N weeks)
                    $isCorrectWeek = ($weeksSinceStart % $recurrenceInterval === 0);
                    
                    // Check if this day is selected
                    $isDaySelected = empty($weekDays) || in_array($currentDayOfWeek, $weekDays);
                    
                    // For the first week, only include days on or after the start date
                    $isOnOrAfterStart = ($currentDate >= new DateTime($startDate));
                    
                    $shouldCreateEvent = $isCorrectWeek && $isDaySelected && $isOnOrAfterStart;
                    break;
                    
                case 'monthly':
                    // Calculate months since start
                    $startDT = new DateTime($startDate);
                    $monthsSinceStart = (($currentDate->format('Y') - $startDT->format('Y')) * 12) + 
                                        ($currentDate->format('n') - $startDT->format('n'));
                    
                    // Check if we're in the right month (every N months)
                    $isCorrectMonth = ($monthsSinceStart >= 0 && $monthsSinceStart % $recurrenceInterval === 0);
                    
                    if (!$isCorrectMonth) {
                        // Skip to first day of next potential month
                        $currentDate->modify('first day of next month');
                        continue 2;
                    }
                    
                    if ($monthlyType === 'dayOfMonth') {
                        // Specific day of month (e.g., 15th)
                        $targetDay = $monthDay ?: (int)(new DateTime($startDate))->format('j');
                        $currentDay = (int)$currentDate->format('j');
                        $daysInMonth = (int)$currentDate->format('t');
                        
                        // If target day exceeds days in month, use last day
                        $effectiveTargetDay = min($targetDay, $daysInMonth);
                        $shouldCreateEvent = ($currentDay === $effectiveTargetDay);
                    } else {
                        // Ordinal weekday (e.g., 2nd Wednesday, last Friday)
                        $shouldCreateEvent = $this->isOrdinalWeekday($currentDate, $ordinalWeek, $ordinalDay);
                    }
                    break;
                    
                case 'yearly':
                    // Every N years on same month/day
                    $startDT = new DateTime($startDate);
                    $yearsSinceStart = (int)$currentDate->format('Y') - (int)$startDT->format('Y');
                    
                    // Check if we're in the right year
                    $isCorrectYear = ($yearsSinceStart >= 0 && $yearsSinceStart % $recurrenceInterval === 0);
                    
                    // Check if it's the same month and day
                    $sameMonthDay = ($currentDate->format('m-d') === $startDT->format('m-d'));
                    
                    $shouldCreateEvent = $isCorrectYear && $sameMonthDay;
                    break;
                    
                default:
                    $shouldCreateEvent = false;
            }
            
            if ($shouldCreateEvent) {
                $dateKey = $currentDate->format('Y-m-d');
                list($year, $month, $day) = explode('-', $dateKey);
                
                // Calculate end date for this occurrence if multi-day
                $occurrenceEndDate = '';
                if ($eventDuration > 0) {
                    $occurrenceEnd = clone $currentDate;
                    $occurrenceEnd->modify('+' . $eventDuration . ' days');
                    $occurrenceEndDate = $occurrenceEnd->format('Y-m-d');
                }
                
                // Load month file
                $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
                $events = [];
                if (file_exists($eventFile)) {
                    $events = json_decode(file_get_contents($eventFile), true);
                    if (!is_array($events)) $events = [];
                }
                
                if (!isset($events[$dateKey])) {
                    $events[$dateKey] = [];
                }
                
                // Create event for this occurrence
                $eventData = [
                    'id' => $baseId . '-' . $counter,
                    'title' => $title,
                    'time' => $time,
                    'endTime' => $endTime,
                    'description' => $description,
                    'color' => $color,
                    'isTask' => $isTask,
                    'completed' => false,
                    'endDate' => $occurrenceEndDate,
                    'recurring' => true,
                    'recurringId' => $baseId,
                    'recurrenceType' => $recurrenceType,
                    'recurrenceInterval' => $recurrenceInterval,
                    'namespace' => $namespace,
                    'created' => date('Y-m-d H:i:s')
                ];
                
                // Store additional recurrence info for reference
                if ($recurrenceType === 'weekly' && !empty($weekDays)) {
                    $eventData['weekDays'] = $weekDays;
                }
                if ($recurrenceType === 'monthly') {
                    $eventData['monthlyType'] = $monthlyType;
                    if ($monthlyType === 'dayOfMonth') {
                        $eventData['monthDay'] = $monthDay;
                    } else {
                        $eventData['ordinalWeek'] = $ordinalWeek;
                        $eventData['ordinalDay'] = $ordinalDay;
                    }
                }
                
                $events[$dateKey][] = $eventData;
                CalendarFileHandler::writeJson($eventFile, $events);
                
                $counter++;
            }
            
            // Move to next day (we check each day individually for complex patterns)
            $currentDate->modify('+1 day');
        }
    }
    
    /**
     * Check if a date is the Nth occurrence of a weekday in its month
     * @param DateTime $date The date to check
     * @param int $ordinalWeek 1-5 for first-fifth, -1 for last
     * @param int $targetDayOfWeek 0=Sunday through 6=Saturday
     * @return bool
     */
    private function isOrdinalWeekday($date, $ordinalWeek, $targetDayOfWeek) {
        $currentDayOfWeek = (int)$date->format('w');
        
        // First, check if it's the right day of week
        if ($currentDayOfWeek !== $targetDayOfWeek) {
            return false;
        }
        
        $dayOfMonth = (int)$date->format('j');
        $daysInMonth = (int)$date->format('t');
        
        if ($ordinalWeek === -1) {
            // Last occurrence: check if there's no more of this weekday in the month
            $daysRemaining = $daysInMonth - $dayOfMonth;
            return $daysRemaining < 7;
        } else {
            // Nth occurrence: check which occurrence this is
            $weekNumber = ceil($dayOfMonth / 7);
            return $weekNumber === $ordinalWeek;
        }
    }

    public function addAssets(Doku_Event $event, $param) {
        $event->data['link'][] = array(
            'type' => 'text/css',
            'rel' => 'stylesheet',
            'href' => DOKU_BASE . 'lib/plugins/calendar/style.css'
        );
        
        // Load the main calendar JavaScript
        // Note: script.js is intentionally empty to avoid DokuWiki's auto-concatenation issues
        // The actual code is in calendar-main.js
        $event->data['script'][] = array(
            'type' => 'text/javascript',
            'src' => DOKU_BASE . 'lib/plugins/calendar/calendar-main.js'
        );
    }
    // Helper function to find an event's stored namespace
    private function findEventNamespace($eventId, $date, $searchNamespace) {
        list($year, $month, $day) = explode('-', $date);
        
        // List of namespaces to check
        $namespacesToCheck = [''];
        
        // If searchNamespace is a wildcard or multi, we need to search multiple locations
        if (!empty($searchNamespace)) {
            if (strpos($searchNamespace, ';') !== false) {
                // Multi-namespace - check each one
                $namespacesToCheck = array_map('trim', explode(';', $searchNamespace));
                $namespacesToCheck[] = ''; // Also check default
            } elseif (strpos($searchNamespace, '*') !== false) {
                // Wildcard - need to scan directories
                $baseNs = trim(str_replace('*', '', $searchNamespace), ':');
                $namespacesToCheck = $this->findAllNamespaces($baseNs);
                $namespacesToCheck[] = ''; // Also check default
            } else {
                // Single namespace
                $namespacesToCheck = [$searchNamespace, '']; // Check specified and default
            }
        }
        
        $this->debugLog("findEventNamespace: Looking for eventId='$eventId' on date='$date' in namespaces: " . implode(', ', array_map(function($n) { return $n === '' ? '(default)' : $n; }, $namespacesToCheck)));
        
        // Search for the event in all possible namespaces
        foreach ($namespacesToCheck as $ns) {
            $dataDir = DOKU_INC . 'data/meta/';
            if ($ns) {
                $dataDir .= str_replace(':', '/', $ns) . '/';
            }
            $dataDir .= 'calendar/';
            
            $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
            
            if (file_exists($eventFile)) {
                $events = json_decode(file_get_contents($eventFile), true);
                if (isset($events[$date])) {
                    foreach ($events[$date] as $evt) {
                        if ($evt['id'] === $eventId) {
                            // IMPORTANT: Return the DIRECTORY namespace ($ns), not the stored namespace
                            // The directory is what matters for deletion - that's where the file actually is
                            $this->debugLog("findEventNamespace: FOUND event in file=$eventFile (dir namespace='$ns', stored namespace='" . ($evt['namespace'] ?? 'NOT SET') . "')");
                            return $ns;
                        }
                    }
                }
            }
        }
        
        $this->debugLog("findEventNamespace: Event NOT FOUND in any namespace");
        return null; // Event not found
    }
    
    // Helper to find all namespaces under a base namespace
    private function findAllNamespaces($baseNamespace) {
        $dataDir = DOKU_INC . 'data/meta/';
        if ($baseNamespace) {
            $dataDir .= str_replace(':', '/', $baseNamespace) . '/';
        }
        
        $namespaces = [];
        if ($baseNamespace) {
            $namespaces[] = $baseNamespace;
        }
        
        $this->scanForNamespaces($dataDir, $baseNamespace, $namespaces);
        
        $this->debugLog("findAllNamespaces: baseNamespace='$baseNamespace', found " . count($namespaces) . " namespaces: " . implode(', ', array_map(function($n) { return $n === '' ? '(default)' : $n; }, $namespaces)));
        
        return $namespaces;
    }
    
    // Recursive scan for namespaces
    private function scanForNamespaces($dir, $baseNamespace, &$namespaces) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'calendar') continue;
            
            $path = $dir . $item;
            if (is_dir($path)) {
                $namespace = $baseNamespace ? $baseNamespace . ':' . $item : $item;
                $namespaces[] = $namespace;
                $this->scanForNamespaces($path . '/', $namespace, $namespaces);
            }
        }
    }
    
    /**
     * Delete all instances of a recurring event across all months
     */
    private function deleteAllRecurringInstances($recurringId, $namespace, $dataDir) {
        // Scan all JSON files in the calendar directory
        $calendarFiles = glob($dataDir . '*.json');
        
        foreach ($calendarFiles as $file) {
            $modified = false;
            $events = json_decode(file_get_contents($file), true);
            
            if (!$events) continue;
            
            // Check each date in the file
            foreach ($events as $date => &$dayEvents) {
                // Filter out events with matching recurringId
                $originalCount = count($dayEvents);
                $dayEvents = array_values(array_filter($dayEvents, function($event) use ($recurringId) {
                    $eventRecurringId = isset($event['recurringId']) ? $event['recurringId'] : null;
                    return $eventRecurringId !== $recurringId;
                }));
                
                if (count($dayEvents) !== $originalCount) {
                    $modified = true;
                }
                
                // Remove empty dates
                if (empty($dayEvents)) {
                    unset($events[$date]);
                }
            }
            
            // Save if modified
            if ($modified) {
                CalendarFileHandler::writeJson($file, $events);
            }
        }
    }
    
    /**
     * Get existing event data for preserving unchanged fields during edit
     */
    private function getExistingEventData($eventId, $date, $namespace) {
        list($year, $month, $day) = explode('-', $date);
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        if (!file_exists($eventFile)) {
            return null;
        }
        
        $events = json_decode(file_get_contents($eventFile), true);
        
        if (!isset($events[$date])) {
            return null;
        }
        
        // Find the event by ID
        foreach ($events[$date] as $event) {
            if ($event['id'] === $eventId) {
                return $event;
            }
        }
        
        return null;
    }
}

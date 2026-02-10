<?php
/**
 * DokuWiki Plugin calendar (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 */

if (!defined('DOKU_INC')) die();

class action_plugin_calendar extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'addAssets');
    }

    public function handleAjax(Doku_Event $event, $param) {
        if ($event->data !== 'plugin_calendar') return;
        $event->preventDefault();
        $event->stopPropagation();

        $action = $_REQUEST['action'] ?? '';

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
            case 'toggle_task':
                $this->toggleTaskComplete();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
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
        
        if (!$date || !$title) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        // If editing, find the event's stored namespace (for finding/deleting old event)
        $storedNamespace = '';
        $oldNamespace = '';
        if ($eventId) {
            // Use oldDate if available (date was changed), otherwise use current date
            $searchDate = ($oldDate && $oldDate !== $date) ? $oldDate : $date;
            $storedNamespace = $this->findEventNamespace($eventId, $searchDate, $namespace);
            
            // Store the old namespace for deletion purposes
            if ($storedNamespace !== null) {
                $oldNamespace = $storedNamespace;
                error_log("Calendar saveEvent: Found existing event in namespace '$oldNamespace'");
            }
        }
        
        // Use the namespace provided by the user (allow namespace changes!)
        // But normalize wildcards and multi-namespace to empty for NEW events
        if (!$eventId) {
            error_log("Calendar saveEvent: NEW event, received namespace='$namespace'");
            // Normalize namespace: treat wildcards and multi-namespace as empty (default) for NEW events
            if (!empty($namespace) && (strpos($namespace, '*') !== false || strpos($namespace, ';') !== false)) {
                error_log("Calendar saveEvent: Namespace contains wildcard/multi, clearing to empty");
                $namespace = '';
            } else {
                error_log("Calendar saveEvent: Namespace is clean, keeping as '$namespace'");
            }
        } else {
            error_log("Calendar saveEvent: EDITING event $eventId, user selected namespace='$namespace'");
        }
        
        // Generate event ID if new
        $generatedId = $eventId ?: uniqid();
        
        // If editing a recurring event, load existing data to preserve unchanged fields
        $existingEventData = null;
        if ($eventId && $isRecurring) {
            $searchDate = ($oldDate && $oldDate !== $date) ? $oldDate : $date;
            $existingEventData = $this->getExistingEventData($eventId, $searchDate, $oldNamespace ?: $namespace);
            if ($existingEventData) {
                error_log("Calendar saveEvent recurring: Loaded existing data - namespace='" . ($existingEventData['namespace'] ?? 'NOT SET') . "'");
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
                        error_log("Calendar saveEvent recurring: Preserving namespace '$namespace' (received='$receivedNamespace')");
                    } else {
                        error_log("Calendar saveEvent recurring: No existing namespace to preserve (received='$receivedNamespace')");
                    }
                } else {
                    error_log("Calendar saveEvent recurring: Using new namespace '$namespace' (received='$receivedNamespace')");
                }
            } else {
                error_log("Calendar saveEvent recurring: No existing data found, using namespace='$namespace'");
            }
            
            $this->createRecurringEvents($namespace, $date, $endDate, $title, $time, $description, 
                                        $color, $isTask, $recurrenceType, $recurrenceEnd, $generatedId);
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
        
        $events = [];
        if (file_exists($eventFile)) {
            $events = json_decode(file_get_contents($eventFile), true);
        }
        
        // If editing and (date changed OR namespace changed), remove from old location first
        $namespaceChanged = ($eventId && $oldNamespace !== '' && $oldNamespace !== $namespace);
        $dateChanged = ($eventId && $oldDate && $oldDate !== $date);
        
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
            
            if (file_exists($oldEventFile)) {
                $oldEvents = json_decode(file_get_contents($oldEventFile), true);
                if (isset($oldEvents[$deleteDate])) {
                    $oldEvents[$deleteDate] = array_values(array_filter($oldEvents[$deleteDate], function($evt) use ($eventId) {
                        return $evt['id'] !== $eventId;
                    }));
                    
                    if (empty($oldEvents[$deleteDate])) {
                        unset($oldEvents[$deleteDate]);
                    }
                    
                    file_put_contents($oldEventFile, json_encode($oldEvents, JSON_PRETTY_PRINT));
                    error_log("Calendar saveEvent: Deleted event from old location - namespace:'$oldNamespace', date:'$deleteDate'");
                }
            }
        }
        
        if (!isset($events[$date])) {
            $events[$date] = [];
        } elseif (!is_array($events[$date])) {
            // Fix corrupted data - ensure it's an array
            error_log("Calendar saveEvent: Fixing corrupted data at $date - was not an array");
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
        error_log("Calendar saveEvent: Saving event '$title' with namespace='$namespace' to file $eventFile");
        
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
        
        file_put_contents($eventFile, json_encode($events, JSON_PRETTY_PRINT));
        
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
                        error_log("Calendar saveEvent: JSON decode error in $currentEventFile: " . json_last_error_msg());
                    }
                }
                
                // Add entry for the first day of this month
                if (!isset($currentEvents[$firstDayOfMonth])) {
                    $currentEvents[$firstDayOfMonth] = [];
                } elseif (!is_array($currentEvents[$firstDayOfMonth])) {
                    // Fix corrupted data - ensure it's an array
                    error_log("Calendar saveEvent: Fixing corrupted data at $firstDayOfMonth - was not an array");
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
                
                file_put_contents($currentEventFile, json_encode($currentEvents, JSON_PRETTY_PRINT));
                
                // Move to next month
                $currentDate->modify('first day of next month');
            }
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
                
                file_put_contents($eventFile, json_encode($events, JSON_PRETTY_PRINT));
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
                        
                        file_put_contents($currentEventFile, json_encode($currentEvents, JSON_PRETTY_PRINT));
                    }
                }
                
                // Move to next month
                $currentDate->modify('first day of next month');
            }
        }
        
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
        
        error_log("=== Calendar loadMonth DEBUG ===");
        error_log("Requested: year=$year, month=$month, namespace='$namespace'");
        
        // Check if multi-namespace or wildcard
        $isMultiNamespace = !empty($namespace) && (strpos($namespace, ';') !== false || strpos($namespace, '*') !== false);
        
        error_log("isMultiNamespace: " . ($isMultiNamespace ? 'true' : 'false'));
        
        if ($isMultiNamespace) {
            $events = $this->loadEventsMultiNamespace($namespace, $year, $month);
        } else {
            $events = $this->loadEventsSingleNamespace($namespace, $year, $month);
        }
        
        error_log("Returning " . count($events) . " date keys");
        foreach ($events as $dateKey => $dayEvents) {
            error_log("  dateKey=$dateKey has " . count($dayEvents) . " events");
        }
        
        echo json_encode([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'events' => $events
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
                foreach ($events[$date] as $key => $event) {
                    if ($event['id'] === $eventId) {
                        $events[$date][$key]['completed'] = $completed;
                        break;
                    }
                }
                
                file_put_contents($eventFile, json_encode($events, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'events' => $events]);
                return;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Event not found']);
    }
    
    private function createRecurringEvents($namespace, $startDate, $endDate, $title, $time, 
                                          $description, $color, $isTask, $recurrenceType, 
                                          $recurrenceEnd, $baseId) {
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Calculate recurrence interval
        $interval = '';
        switch ($recurrenceType) {
            case 'daily': $interval = '+1 day'; break;
            case 'weekly': $interval = '+1 week'; break;
            case 'monthly': $interval = '+1 month'; break;
            case 'yearly': $interval = '+1 year'; break;
            default: $interval = '+1 week';
        }
        
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
        $maxOccurrences = 100; // Prevent infinite loops
        
        while ($currentDate <= $endLimit && $counter < $maxOccurrences) {
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
                'namespace' => $namespace,  // Add namespace!
                'created' => date('Y-m-d H:i:s')
            ];
            
            $events[$dateKey][] = $eventData;
            file_put_contents($eventFile, json_encode($events, JSON_PRETTY_PRINT));
            
            // Move to next occurrence
            $currentDate->modify($interval);
            $counter++;
        }
    }

    public function addAssets(Doku_Event $event, $param) {
        $event->data['link'][] = array(
            'type' => 'text/css',
            'rel' => 'stylesheet',
            'href' => DOKU_BASE . 'lib/plugins/calendar/style.css'
        );
        
        $event->data['script'][] = array(
            'type' => 'text/javascript',
            'src' => DOKU_BASE . 'lib/plugins/calendar/script.js'
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
                            // Found the event! Return its stored namespace
                            return isset($evt['namespace']) ? $evt['namespace'] : $ns;
                        }
                    }
                }
            }
        }
        
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
                file_put_contents($file, json_encode($events, JSON_PRETTY_PRINT));
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

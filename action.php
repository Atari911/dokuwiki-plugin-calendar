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
        
        // Generate event ID if new
        $generatedId = $eventId ?: uniqid();
        
        // If recurring, generate multiple events
        if ($isRecurring) {
            $this->createRecurringEvents($namespace, $date, $endDate, $title, $time, $description, 
                                        $color, $isTask, $recurrenceType, $recurrenceEnd, $generatedId);
            echo json_encode(['success' => true]);
            return;
        }
        
        list($year, $month, $day) = explode('-', $date);
        
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
        
        // If editing and date changed, remove from old date first
        if ($eventId && $oldDate && $oldDate !== $date) {
            list($oldYear, $oldMonth, $oldDay) = explode('-', $oldDate);
            $oldEventFile = $dataDir . sprintf('%04d-%02d.json', $oldYear, $oldMonth);
            
            if (file_exists($oldEventFile)) {
                $oldEvents = json_decode(file_get_contents($oldEventFile), true);
                if (isset($oldEvents[$oldDate])) {
                    $oldEvents[$oldDate] = array_filter($oldEvents[$oldDate], function($evt) use ($eventId) {
                        return $evt['id'] !== $eventId;
                    });
                    
                    if (empty($oldEvents[$oldDate])) {
                        unset($oldEvents[$oldDate]);
                    }
                    
                    file_put_contents($oldEventFile, json_encode($oldEvents, JSON_PRETTY_PRINT));
                }
            }
        }
        
        if (!isset($events[$date])) {
            $events[$date] = [];
        }
        
        $eventData = [
            'id' => $generatedId,
            'title' => $title,
            'time' => $time,
            'description' => $description,
            'color' => $color,
            'isTask' => $isTask,
            'completed' => $completed,
            'endDate' => $endDate,
            'created' => date('Y-m-d H:i:s')
        ];
        
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
        
        echo json_encode(['success' => true, 'events' => $events, 'eventId' => $eventData['id']]);
    }

    private function deleteEvent() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $date = $INPUT->str('date');
        $eventId = $INPUT->str('eventId');
        
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
                $events[$date] = array_filter($events[$date], function($event) use ($eventId) {
                    return $event['id'] !== $eventId;
                });
                
                if (empty($events[$date])) {
                    unset($events[$date]);
                }
                
                file_put_contents($eventFile, json_encode($events, JSON_PRETTY_PRINT));
            }
        }
        
        echo json_encode(['success' => true]);
    }

    private function getEvent() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $date = $INPUT->str('date');
        $eventId = $INPUT->str('eventId');
        
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
        
        $namespace = $INPUT->str('namespace', '');
        $year = $INPUT->int('year');
        $month = $INPUT->int('month');
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        error_log("Calendar loadMonth: Loading $year-$month");
        
        // Load current month
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        $events = [];
        if (file_exists($eventFile)) {
            $contents = file_get_contents($eventFile);
            $decoded = json_decode($contents, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $events = $decoded;
                error_log("Calendar loadMonth: Loaded " . count($events) . " dates from $eventFile");
            } else {
                error_log('Calendar: JSON decode error in ' . $eventFile . ': ' . json_last_error_msg());
            }
        } else {
            error_log("Calendar loadMonth: File not found: $eventFile");
        }
        
        // Load previous month to catch events spanning into current month
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        $prevEventFile = $dataDir . sprintf('%04d-%02d.json', $prevYear, $prevMonth);
        if (file_exists($prevEventFile)) {
            $contents = file_get_contents($prevEventFile);
            $decoded = json_decode($contents, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                error_log("Calendar loadMonth: Loaded " . count($decoded) . " dates from $prevEventFile");
                $events = array_merge($events, $decoded);
            } else {
                error_log('Calendar: JSON decode error in ' . $prevEventFile . ': ' . json_last_error_msg());
            }
        }
        
        // Load next month to catch events spanning from current month
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        $nextEventFile = $dataDir . sprintf('%04d-%02d.json', $nextYear, $nextMonth);
        if (file_exists($nextEventFile)) {
            $contents = file_get_contents($nextEventFile);
            $decoded = json_decode($contents, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                error_log("Calendar loadMonth: Loaded " . count($decoded) . " dates from $nextEventFile");
                $events = array_merge($events, $decoded);
            } else {
                error_log('Calendar: JSON decode error in ' . $nextEventFile . ': ' . json_last_error_msg());
            }
        }
        
        error_log("Calendar loadMonth: Total dates returned: " . count($events));
        
        echo json_encode(['success' => true, 'events' => $events, 'year' => $year, 'month' => $month]);
    }

    private function toggleTaskComplete() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace', '');
        $date = $INPUT->str('date');
        $eventId = $INPUT->str('eventId');
        $completed = $INPUT->bool('completed', false);
        
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
                'description' => $description,
                'color' => $color,
                'isTask' => $isTask,
                'completed' => false,
                'endDate' => $occurrenceEndDate,
                'recurring' => true,
                'recurringId' => $baseId,
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
}

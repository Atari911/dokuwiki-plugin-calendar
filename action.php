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
        
        if (!$date || !$title) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
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
            'id' => $eventId ?: uniqid(),
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
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        $events = [];
        if (file_exists($eventFile)) {
            $events = json_decode(file_get_contents($eventFile), true);
        }
        
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

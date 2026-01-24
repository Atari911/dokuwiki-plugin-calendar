<?php
/**
 * DokuWiki Plugin calendar (Syntax Component)
 * Compact design with integrated event list
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 */

if (!defined('DOKU_INC')) die();

class syntax_plugin_calendar extends DokuWiki_Syntax_Plugin {
    
    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 155;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{calendar(?:[^\}]*)\}\}', $mode, 'plugin_calendar');
        $this->Lexer->addSpecialPattern('\{\{eventlist(?:[^\}]*)\}\}', $mode, 'plugin_calendar');
        $this->Lexer->addSpecialPattern('\{\{eventpanel(?:[^\}]*)\}\}', $mode, 'plugin_calendar');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $isEventList = (strpos($match, '{{eventlist') === 0);
        $isEventPanel = (strpos($match, '{{eventpanel') === 0);
        
        if ($isEventList) {
            $match = substr($match, 12, -2);
        } elseif ($isEventPanel) {
            $match = substr($match, 13, -2);
        } else {
            $match = substr($match, 10, -2);
        }
        
        $params = array(
            'type' => $isEventPanel ? 'eventpanel' : ($isEventList ? 'eventlist' : 'calendar'),
            'year' => date('Y'),
            'month' => date('n'),
            'namespace' => '',
            'daterange' => '',
            'date' => ''
        );
        
        if (trim($match)) {
            $pairs = preg_split('/\s+/', trim($match));
            foreach ($pairs as $pair) {
                if (strpos($pair, '=') !== false) {
                    list($key, $value) = explode('=', $pair, 2);
                    $params[trim($key)] = trim($value);
                }
            }
        }
        
        return $params;
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode !== 'xhtml') return false;
        
        if ($data['type'] === 'eventlist') {
            $html = $this->renderStandaloneEventList($data);
        } elseif ($data['type'] === 'eventpanel') {
            $html = $this->renderEventPanelOnly($data);
        } else {
            $html = $this->renderCompactCalendar($data);
        }
        
        $renderer->doc .= $html;
        return true;
    }
    
    private function renderCompactCalendar($data) {
        $year = (int)$data['year'];
        $month = (int)$data['month'];
        $namespace = $data['namespace'];
        
        $events = $this->loadEvents($namespace, $year, $month);
        $calId = 'cal_' . md5(serialize($data) . microtime());
        
        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        
        $html = '<div class="calendar-compact-container" id="' . $calId . '" data-namespace="' . htmlspecialchars($namespace) . '" data-year="' . $year . '" data-month="' . $month . '">';
        
        // Embed events data as JSON for JavaScript access
        $html .= '<script type="application/json" id="events-data-' . $calId . '">' . json_encode($events) . '</script>';
        
        // Left side: Calendar
        $html .= '<div class="calendar-compact-left">';
        
        // Header with navigation
        $html .= '<div class="calendar-compact-header">';
        $html .= '<button class="cal-nav-btn" onclick="navCalendar(\'' . $calId . '\', ' . $prevYear . ', ' . $prevMonth . ', \'' . $namespace . '\')">‹</button>';
        $html .= '<h3>' . $monthName . '</h3>';
        $html .= '<button class="cal-nav-btn" onclick="navCalendar(\'' . $calId . '\', ' . $nextYear . ', ' . $nextMonth . ', \'' . $namespace . '\')">›</button>';
        $html .= '</div>';
        
        // Calendar grid
        $html .= '<table class="calendar-compact-grid">';
        $html .= '<thead><tr>';
        $html .= '<th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>';
        $html .= '</tr></thead><tbody>';
        
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $dayOfWeek = date('w', $firstDay);
        
        $currentDay = 1;
        $rowCount = ceil(($daysInMonth + $dayOfWeek) / 7);
        
        for ($row = 0; $row < $rowCount; $row++) {
            $html .= '<tr>';
            for ($col = 0; $col < 7; $col++) {
                if (($row === 0 && $col < $dayOfWeek) || $currentDay > $daysInMonth) {
                    $html .= '<td class="cal-empty"></td>';
                } else {
                    $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                    $isToday = ($dateKey === date('Y-m-d'));
                    $hasEvents = isset($events[$dateKey]) && !empty($events[$dateKey]);
                    
                    $classes = 'cal-day';
                    if ($isToday) $classes .= ' cal-today';
                    if ($hasEvents) $classes .= ' cal-has-events';
                    
                    $html .= '<td class="' . $classes . '" data-date="' . $dateKey . '" onclick="showDayPopup(\'' . $calId . '\', \'' . $dateKey . '\', \'' . $namespace . '\')">';
                    $html .= '<span class="day-num">' . $currentDay . '</span>';
                    
                    if ($hasEvents) {
                        // Sort events by time (no time first, then by time)
                        $sortedEvents = $events[$dateKey];
                        usort($sortedEvents, function($a, $b) {
                            $timeA = isset($a['time']) ? $a['time'] : '';
                            $timeB = isset($b['time']) ? $b['time'] : '';
                            
                            // Events without time go first
                            if (empty($timeA) && !empty($timeB)) return -1;
                            if (!empty($timeA) && empty($timeB)) return 1;
                            if (empty($timeA) && empty($timeB)) return 0;
                            
                            // Sort by time
                            return strcmp($timeA, $timeB);
                        });
                        
                        // Show colored stacked bars for each event
                        $html .= '<div class="event-indicators">';
                        foreach ($sortedEvents as $evt) {
                            $eventId = isset($evt['id']) ? $evt['id'] : '';
                            $eventColor = isset($evt['color']) ? htmlspecialchars($evt['color']) : '#3498db';
                            $eventTime = isset($evt['time']) ? $evt['time'] : '';
                            $eventTitle = isset($evt['title']) ? htmlspecialchars($evt['title']) : 'Event';
                            
                            $barClass = empty($eventTime) ? 'event-bar-no-time' : 'event-bar-timed';
                            
                            $html .= '<span class="event-bar ' . $barClass . '" ';
                            $html .= 'style="background: ' . $eventColor . ';" ';
                            $html .= 'title="' . $eventTitle . ($eventTime ? ' @ ' . $eventTime : '') . '" ';
                            $html .= 'onclick="event.stopPropagation(); highlightEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\');">';
                            $html .= '</span>';
                        }
                        $html .= '</div>';
                    }
                    
                    $html .= '</td>';
                    $currentDay++;
                }
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>'; // End calendar-left
        
        // Right side: Event list
        $html .= '<div class="calendar-compact-right">';
        $html .= '<div class="event-list-header">';
        $html .= '<div class="event-list-header-content">';
        $html .= '<h4 id="eventlist-title-' . $calId . '">Events</h4>';
        if ($namespace) {
            $html .= '<span class="namespace-badge">' . htmlspecialchars($namespace) . '</span>';
        }
        $html .= '</div>';
        $html .= '<button class="add-event-compact" onclick="openAddEvent(\'' . $calId . '\', \'' . $namespace . '\')">+ Add</button>';
        $html .= '</div>';
        
        $html .= '<div class="event-list-compact" id="eventlist-' . $calId . '">';
        $html .= $this->renderEventListContent($events, $calId, $namespace);
        $html .= '</div>';
        
        $html .= '</div>'; // End calendar-right
        
        // Event dialog
        $html .= $this->renderEventDialog($calId, $namespace);
        
        $html .= '</div>'; // End container
        
        return $html;
    }
    
    private function renderEventListContent($events, $calId, $namespace) {
        if (empty($events)) {
            return '<p class="no-events-msg">No events this month</p>';
        }
        
        $html = '';
        ksort($events);
        
        foreach ($events as $dateKey => $dayEvents) {
            foreach ($dayEvents as $event) {
                $eventId = isset($event['id']) ? $event['id'] : '';
                $title = isset($event['title']) ? htmlspecialchars($event['title']) : 'Untitled';
                $time = isset($event['time']) ? htmlspecialchars($event['time']) : '';
                $color = isset($event['color']) ? htmlspecialchars($event['color']) : '#3498db';
                $description = isset($event['description']) ? $event['description'] : '';
                $isTask = isset($event['isTask']) ? $event['isTask'] : false;
                $completed = isset($event['completed']) ? $event['completed'] : false;
                $endDate = isset($event['endDate']) ? $event['endDate'] : '';
                
                // Process description for wiki syntax, HTML, images, and links
                $renderedDescription = $this->renderDescription($description);
                
                // Convert to 12-hour format
                $displayTime = '';
                if ($time) {
                    $timeObj = DateTime::createFromFormat('H:i', $time);
                    if ($timeObj) {
                        $displayTime = $timeObj->format('g:i A');
                    } else {
                        $displayTime = $time;
                    }
                }
                
                // Format date display
                $dateObj = new DateTime($dateKey);
                $displayDate = $dateObj->format('M j');
                
                // Multi-day indicator
                $multiDay = '';
                if ($endDate && $endDate !== $dateKey) {
                    $endObj = new DateTime($endDate);
                    $multiDay = ' → ' . $endObj->format('M j');
                }
                
                $completedClass = $completed ? ' event-completed' : '';
                
                $html .= '<div class="event-compact-item' . $completedClass . '" data-event-id="' . $eventId . '" data-date="' . $dateKey . '" style="border-left-color: ' . $color . ';">';
                
                $html .= '<div class="event-info">';
                $html .= '<div class="event-title-row">';
                $html .= '<span class="event-title-compact">' . $title . '</span>';
                $html .= '</div>';
                
                $html .= '<div class="event-meta-compact">';
                $html .= '<span class="event-date-time">' . $displayDate . $multiDay;
                if ($displayTime) {
                    $html .= ' • ' . $displayTime;
                }
                $html .= '</span>';
                $html .= '</div>';
                
                if ($description) {
                    $html .= '<div class="event-desc-compact">' . $renderedDescription . '</div>';
                }
                
                $html .= '</div>'; // event-info
                
                $html .= '<div class="event-actions-compact">';
                $html .= '<button class="event-action-btn" onclick="deleteEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $namespace . '\')">🗑️</button>';
                $html .= '<button class="event-action-btn" onclick="editEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $namespace . '\')">✏️</button>';
                $html .= '</div>';
                
                // Checkbox for tasks - ON THE FAR RIGHT
                if ($isTask) {
                    $checked = $completed ? 'checked' : '';
                    $html .= '<input type="checkbox" class="task-checkbox" ' . $checked . ' onclick="toggleTaskComplete(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $namespace . '\', this.checked)">';
                }
                
                $html .= '</div>';
            }
        }
        
        return $html;
    }
    
    private function renderEventPanelOnly($data) {
        $year = (int)$data['year'];
        $month = (int)$data['month'];
        $namespace = $data['namespace'];
        
        $events = $this->loadEvents($namespace, $year, $month);
        $calId = 'panel_' . md5(serialize($data) . microtime());
        
        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        
        $html = '<div class="event-panel-standalone" id="' . $calId . '">';
        
        // Header with navigation
        $html .= '<div class="panel-standalone-header">';
        $html .= '<button class="cal-nav-btn" onclick="navEventPanel(\'' . $calId . '\', ' . $prevYear . ', ' . $prevMonth . ', \'' . $namespace . '\')">‹</button>';
        $html .= '<h3>' . $monthName . ' Events</h3>';
        $html .= '<button class="cal-nav-btn" onclick="navEventPanel(\'' . $calId . '\', ' . $nextYear . ', ' . $nextMonth . ', \'' . $namespace . '\')">›</button>';
        $html .= '</div>';
        
        $html .= '<div class="panel-standalone-actions">';
        $html .= '<button class="add-event-compact" onclick="openAddEventPanel(\'' . $calId . '\', \'' . $namespace . '\')">+ Add Event</button>';
        $html .= '</div>';
        
        $html .= '<div class="event-list-compact" id="eventlist-' . $calId . '">';
        $html .= $this->renderEventListContent($events, $calId, $namespace);
        $html .= '</div>';
        
        $html .= $this->renderEventDialog($calId, $namespace);
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderStandaloneEventList($data) {
        $namespace = $data['namespace'];
        $daterange = $data['daterange'];
        $date = $data['date'];
        
        if ($daterange) {
            list($startDate, $endDate) = explode(':', $daterange);
        } elseif ($date) {
            $startDate = $date;
            $endDate = $date;
        } else {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }
        
        $allEvents = array();
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day');
        
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        
        static $loadedMonths = array();
        
        foreach ($period as $dt) {
            $year = (int)$dt->format('Y');
            $month = (int)$dt->format('n');
            $dateKey = $dt->format('Y-m-d');
            
            $monthKey = $year . '-' . $month;
            
            if (!isset($loadedMonths[$monthKey])) {
                $loadedMonths[$monthKey] = $this->loadEvents($namespace, $year, $month);
            }
            
            $monthEvents = $loadedMonths[$monthKey];
            
            if (isset($monthEvents[$dateKey]) && !empty($monthEvents[$dateKey])) {
                $allEvents[$dateKey] = $monthEvents[$dateKey];
            }
        }
        
        $html = '<div class="eventlist-standalone">';
        $html .= '<h3>Events: ' . date('M j', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)) . '</h3>';
        
        if (empty($allEvents)) {
            $html .= '<p class="no-events-msg">No events in this date range</p>';
        } else {
            foreach ($allEvents as $dateKey => $dayEvents) {
                $displayDate = date('l, F j, Y', strtotime($dateKey));
                
                $html .= '<div class="eventlist-day-group">';
                $html .= '<h4 class="eventlist-date">' . $displayDate . '</h4>';
                
                foreach ($dayEvents as $event) {
                    $title = isset($event['title']) ? htmlspecialchars($event['title']) : 'Untitled';
                    $time = isset($event['time']) ? htmlspecialchars($event['time']) : '';
                    $color = isset($event['color']) ? htmlspecialchars($event['color']) : '#3498db';
                    $description = isset($event['description']) ? htmlspecialchars($event['description']) : '';
                    
                    $html .= '<div class="eventlist-item">';
                    $html .= '<div class="event-color-bar" style="background: ' . $color . ';"></div>';
                    $html .= '<div class="eventlist-content">';
                    if ($time) {
                        $html .= '<span class="eventlist-time">' . $time . '</span>';
                    }
                    $html .= '<span class="eventlist-title">' . $title . '</span>';
                    if ($description) {
                        $html .= '<div class="eventlist-desc">' . nl2br($description) . '</div>';
                    }
                    $html .= '</div></div>';
                }
                
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderEventDialog($calId, $namespace) {
        $html = '<div class="event-dialog-compact" id="dialog-' . $calId . '" style="display:none;">';
        $html .= '<div class="dialog-overlay" onclick="closeEventDialog(\'' . $calId . '\')"></div>';
        
        // Draggable dialog
        $html .= '<div class="dialog-content-sleek" id="dialog-content-' . $calId . '">';
        
        // Header with drag handle and close button
        $html .= '<div class="dialog-header-sleek dialog-drag-handle" id="drag-handle-' . $calId . '">';
        $html .= '<h3 id="dialog-title-' . $calId . '">Add Event</h3>';
        $html .= '<button type="button" class="dialog-close-btn" onclick="closeEventDialog(\'' . $calId . '\')">×</button>';
        $html .= '</div>';
        
        // Form content
        $html .= '<form id="eventform-' . $calId . '" onsubmit="saveEventCompact(\'' . $calId . '\', \'' . $namespace . '\'); return false;" class="sleek-form">';
        
        // Hidden ID field
        $html .= '<input type="hidden" id="event-id-' . $calId . '" name="eventId" value="">';
        
        // Task checkbox
        $html .= '<div class="form-field form-field-checkbox">';
        $html .= '<label class="checkbox-label">';
        $html .= '<input type="checkbox" id="event-is-task-' . $calId . '" name="isTask" class="task-toggle">';
        $html .= '<span>📋 This is a task (can be checked off)</span>';
        $html .= '</label>';
        $html .= '</div>';
        
        // Date and Time in a row
        $html .= '<div class="form-row-group">';
        
        // Start Date field
        $html .= '<div class="form-field form-field-date">';
        $html .= '<label class="field-label">📅 Start Date</label>';
        $html .= '<input type="date" id="event-date-' . $calId . '" name="date" required class="input-sleek input-date">';
        $html .= '</div>';
        
        // End Date field (for multi-day events)
        $html .= '<div class="form-field form-field-date">';
        $html .= '<label class="field-label">📅 End Date</label>';
        $html .= '<input type="date" id="event-end-date-' . $calId . '" name="endDate" class="input-sleek input-date">';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Time field
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">🕐 Time (optional)</label>';
        $html .= '<input type="time" id="event-time-' . $calId . '" name="time" class="input-sleek">';
        $html .= '</div>';
        
        // Title field
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">📝 Title</label>';
        $html .= '<input type="text" id="event-title-' . $calId . '" name="title" required class="input-sleek" placeholder="Event or task title...">';
        $html .= '</div>';
        
        // Description field
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">📄 Description</label>';
        $html .= '<textarea id="event-desc-' . $calId . '" name="description" rows="3" class="input-sleek textarea-sleek" placeholder="Add details (optional)..."></textarea>';
        $html .= '</div>';
        
        // Color picker
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">🎨 Color</label>';
        $html .= '<div class="color-picker-container">';
        $html .= '<input type="color" id="event-color-' . $calId . '" name="color" value="#3498db" class="input-color-sleek">';
        $html .= '<span class="color-label">Choose event color</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Action buttons
        $html .= '<div class="dialog-actions-sleek">';
        $html .= '<button type="button" class="btn-sleek btn-cancel-sleek" onclick="closeEventDialog(\'' . $calId . '\')">Cancel</button>';
        $html .= '<button type="submit" class="btn-sleek btn-save-sleek">💾 Save</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderDescription($description) {
        if (empty($description)) {
            return '';
        }
        
        // Convert newlines to <br> for basic formatting
        $rendered = nl2br($description);
        
        // Convert DokuWiki image syntax {{image.jpg}} to HTML
        $rendered = preg_replace_callback(
            '/\{\{([^}|]+?)(?:\|([^}]+))?\}\}/',
            function($matches) {
                $imagePath = trim($matches[1]);
                $alt = isset($matches[2]) ? trim($matches[2]) : '';
                
                // Handle external URLs (http:// or https://)
                if (preg_match('/^https?:\/\//', $imagePath)) {
                    return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($alt) . '" class="event-image" />';
                }
                
                // Handle internal DokuWiki images
                $imageUrl = DOKU_BASE . 'lib/exe/fetch.php?media=' . rawurlencode($imagePath);
                return '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($alt) . '" class="event-image" />';
            },
            $rendered
        );
        
        // Convert DokuWiki link syntax [[link|text]] to HTML
        $rendered = preg_replace_callback(
            '/\[\[([^|\]]+?)(?:\|([^\]]+))?\]\]/',
            function($matches) {
                $link = trim($matches[1]);
                $text = isset($matches[2]) ? trim($matches[2]) : $link;
                
                // Handle external URLs
                if (preg_match('/^https?:\/\//', $link)) {
                    return '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($text) . '</a>';
                }
                
                // Handle internal DokuWiki links
                $wikiUrl = DOKU_BASE . 'doku.php?id=' . rawurlencode($link);
                return '<a href="' . $wikiUrl . '">' . htmlspecialchars($text) . '</a>';
            },
            $rendered
        );
        
        // Convert markdown-style links [text](url) to HTML
        $rendered = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function($matches) {
                $text = trim($matches[1]);
                $url = trim($matches[2]);
                
                if (preg_match('/^https?:\/\//', $url)) {
                    return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($text) . '</a>';
                }
                
                return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($text) . '</a>';
            },
            $rendered
        );
        
        // Convert plain URLs to clickable links
        $rendered = preg_replace_callback(
            '/(https?:\/\/[^\s<]+)/',
            function($matches) {
                $url = $matches[1];
                return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($url) . '</a>';
            },
            $rendered
        );
        
        // Allow basic HTML tags (bold, italic, strong, em, u, code)
        // Already in the description, just pass through
        
        return $rendered;
    }
    
    private function loadEvents($namespace, $year, $month) {
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        if (file_exists($eventFile)) {
            $json = file_get_contents($eventFile);
            return json_decode($json, true);
        }
        
        return array();
    }
}

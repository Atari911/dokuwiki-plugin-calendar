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
                } else {
                    // Handle standalone flags like "today"
                    $params[trim($pair)] = true;
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
        $html .= '<h3 class="calendar-month-picker" onclick="openMonthPicker(\'' . $calId . '\', ' . $year . ', ' . $month . ', \'' . $namespace . '\')" title="Click to jump to month">' . $monthName . '</h3>';
        $html .= '<button class="cal-nav-btn" onclick="navCalendar(\'' . $calId . '\', ' . $nextYear . ', ' . $nextMonth . ', \'' . $namespace . '\')">›</button>';
        $html .= '<button class="cal-today-btn" onclick="jumpToToday(\'' . $calId . '\', \'' . $namespace . '\')">Today</button>';
        $html .= '</div>';
        
        // Calendar grid
        $html .= '<table class="calendar-compact-grid">';
        $html .= '<thead><tr>';
        $html .= '<th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>';
        $html .= '</tr></thead><tbody>';
        
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $dayOfWeek = date('w', $firstDay);
        
        // Load events from previous and next months to catch spanning events
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
        
        $prevMonthEvents = $this->loadEvents($namespace, $prevYear, $prevMonth);
        $nextMonthEvents = $this->loadEvents($namespace, $nextYear, $nextMonth);
        
        // Combine all events for processing
        $allEvents = array_merge($events, $prevMonthEvents, $nextMonthEvents);
        
        // Build a map of all events with their date ranges
        $eventRanges = array();
        foreach ($allEvents as $dateKey => $dayEvents) {
            foreach ($dayEvents as $evt) {
                $eventId = isset($evt['id']) ? $evt['id'] : '';
                $startDate = $dateKey;
                $endDate = isset($evt['endDate']) && $evt['endDate'] ? $evt['endDate'] : $dateKey;
                
                // Only process events that touch this month
                $eventStart = new DateTime($startDate);
                $eventEnd = new DateTime($endDate);
                $monthStart = new DateTime(sprintf('%04d-%02d-01', $year, $month));
                $monthEnd = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth));
                
                // Skip if event doesn't overlap with current month
                if ($eventEnd < $monthStart || $eventStart > $monthEnd) {
                    continue;
                }
                
                // Create entry for each day the event spans
                $current = clone $eventStart;
                while ($current <= $eventEnd) {
                    $currentKey = $current->format('Y-m-d');
                    
                    // Check if this date is in current month
                    $currentDate = DateTime::createFromFormat('Y-m-d', $currentKey);
                    if ($currentDate && $currentDate->format('Y-m') === sprintf('%04d-%02d', $year, $month)) {
                        if (!isset($eventRanges[$currentKey])) {
                            $eventRanges[$currentKey] = array();
                        }
                        
                        // Add event with span information
                        $evt['_span_start'] = $startDate;
                        $evt['_span_end'] = $endDate;
                        $evt['_is_first_day'] = ($currentKey === $startDate);
                        $evt['_is_last_day'] = ($currentKey === $endDate);
                        $evt['_original_date'] = $dateKey; // Keep track of original date
                        
                        // Check if event continues from previous month or to next month
                        $evt['_continues_from_prev'] = ($eventStart < $monthStart);
                        $evt['_continues_to_next'] = ($eventEnd > $monthEnd);
                        
                        $eventRanges[$currentKey][] = $evt;
                    }
                    
                    $current->modify('+1 day');
                }
            }
        }
        
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
                    $hasEvents = isset($eventRanges[$dateKey]) && !empty($eventRanges[$dateKey]);
                    
                    $classes = 'cal-day';
                    if ($isToday) $classes .= ' cal-today';
                    if ($hasEvents) $classes .= ' cal-has-events';
                    
                    $html .= '<td class="' . $classes . '" data-date="' . $dateKey . '" onclick="showDayPopup(\'' . $calId . '\', \'' . $dateKey . '\', \'' . $namespace . '\')">';
                    $html .= '<span class="day-num">' . $currentDay . '</span>';
                    
                    if ($hasEvents) {
                        // Sort events by time (no time first, then by time)
                        $sortedEvents = $eventRanges[$dateKey];
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
                            $originalDate = isset($evt['_original_date']) ? $evt['_original_date'] : $dateKey;
                            $isFirstDay = isset($evt['_is_first_day']) ? $evt['_is_first_day'] : true;
                            $isLastDay = isset($evt['_is_last_day']) ? $evt['_is_last_day'] : true;
                            
                            $barClass = empty($eventTime) ? 'event-bar-no-time' : 'event-bar-timed';
                            
                            // Add classes for multi-day spanning
                            if (!$isFirstDay) $barClass .= ' event-bar-continues';
                            if (!$isLastDay) $barClass .= ' event-bar-continuing';
                            
                            $html .= '<span class="event-bar ' . $barClass . '" ';
                            $html .= 'style="background: ' . $eventColor . ';" ';
                            $html .= 'title="' . $eventTitle . ($eventTime ? ' @ ' . $eventTime : '') . '" ';
                            $html .= 'onclick="event.stopPropagation(); highlightEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $originalDate . '\');">';
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
        
        // Month/Year picker dialog (at container level for proper overlay)
        $html .= $this->renderMonthPicker($calId, $year, $month, $namespace);
        
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
                
                // Format date display with day of week
                $dateObj = new DateTime($dateKey);
                $displayDate = $dateObj->format('D, M j'); // e.g., "Mon, Jan 24"
                
                // Multi-day indicator
                $multiDay = '';
                if ($endDate && $endDate !== $dateKey) {
                    $endObj = new DateTime($endDate);
                    $multiDay = ' → ' . $endObj->format('D, M j');
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
        $height = isset($data['height']) ? $data['height'] : '400px';
        
        // Validate height format (must be px, em, rem, vh, or %)
        if (!preg_match('/^\d+(\.\d+)?(px|em|rem|vh|%)$/', $height)) {
            $height = '400px'; // Default fallback
        }
        
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
        
        $html = '<div class="event-panel-standalone" id="' . $calId . '" data-height="' . htmlspecialchars($height) . '" data-namespace="' . htmlspecialchars($namespace) . '">';
        
        // Header with navigation
        $html .= '<div class="panel-standalone-header">';
        $html .= '<button class="cal-nav-btn" onclick="navEventPanel(\'' . $calId . '\', ' . $prevYear . ', ' . $prevMonth . ', \'' . $namespace . '\')">‹</button>';
        $html .= '<div class="panel-header-content">';
        $html .= '<h3 class="calendar-month-picker" onclick="openMonthPickerPanel(\'' . $calId . '\', ' . $year . ', ' . $month . ', \'' . $namespace . '\')" title="Click to jump to month">' . $monthName . ' Events</h3>';
        if ($namespace) {
            $namespaceUrl = DOKU_BASE . 'doku.php?id=' . str_replace(':', ':', $namespace);
            $html .= '<a href="' . $namespaceUrl . '" class="namespace-badge" title="Go to namespace page">' . htmlspecialchars($namespace) . '</a>';
        }
        $html .= '</div>';
        $html .= '<button class="cal-nav-btn" onclick="navEventPanel(\'' . $calId . '\', ' . $nextYear . ', ' . $nextMonth . ', \'' . $namespace . '\')">›</button>';
        $html .= '<button class="cal-today-btn" onclick="jumpTodayPanel(\'' . $calId . '\', \'' . $namespace . '\')">Today</button>';
        $html .= '</div>';
        
        $html .= '<div class="panel-standalone-actions">';
        $html .= '<button class="add-event-compact" onclick="openAddEventPanel(\'' . $calId . '\', \'' . $namespace . '\')">+ Add Event</button>';
        $html .= '</div>';
        
        $html .= '<div class="event-list-compact" id="eventlist-' . $calId . '" style="max-height: ' . htmlspecialchars($height) . ';">';
        $html .= $this->renderEventListContent($events, $calId, $namespace);
        $html .= '</div>';
        
        $html .= $this->renderEventDialog($calId, $namespace);
        
        // Month/Year picker for event panel
        $html .= $this->renderMonthPicker($calId, $year, $month, $namespace);
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderStandaloneEventList($data) {
        $namespace = $data['namespace'];
        $daterange = $data['daterange'];
        $date = $data['date'];
        $width = isset($data['width']) ? $data['width'] : '300px';
        $height = isset($data['height']) ? $data['height'] : '400px';
        $today = isset($data['today']) ? true : false;
        
        // Validate width/height format
        if (!preg_match('/^\d+(\.\d+)?(px|em|rem|vh|vw|%)$/', $width)) {
            $width = '300px';
        }
        if (!preg_match('/^\d+(\.\d+)?(px|em|rem|vh|%)$/', $height)) {
            $height = '400px';
        }
        
        // Handle "today" parameter
        if ($today) {
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
        } elseif ($daterange) {
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
        
        // Compact container with custom size
        $html = '<div class="eventlist-compact-widget" style="width: ' . htmlspecialchars($width) . '; max-height: ' . htmlspecialchars($height) . ';">';
        
        // Compact header
        if ($today) {
            $html .= '<div class="eventlist-widget-header">';
            $html .= '<h4>📅 Today\'s Events</h4>';
            $html .= '</div>';
        } else {
            $html .= '<div class="eventlist-widget-header">';
            $html .= '<h4>' . date('M j', strtotime($startDate));
            if ($startDate !== $endDate) {
                $html .= ' - ' . date('M j', strtotime($endDate));
            }
            $html .= '</h4>';
            $html .= '</div>';
        }
        
        // Scrollable event list
        $html .= '<div class="eventlist-widget-content">';
        
        if (empty($allEvents)) {
            $html .= '<p class="eventlist-widget-empty">No events</p>';
        } else {
            foreach ($allEvents as $dateKey => $dayEvents) {
                // Compact date header (only if not "today" mode or multi-day range)
                if (!$today && $startDate !== $endDate) {
                    $dateObj = new DateTime($dateKey);
                    $html .= '<div class="eventlist-widget-date">' . $dateObj->format('D, M j') . '</div>';
                }
                
                foreach ($dayEvents as $event) {
                    $title = isset($event['title']) ? htmlspecialchars($event['title']) : 'Untitled';
                    $time = isset($event['time']) ? $event['time'] : '';
                    $color = isset($event['color']) ? htmlspecialchars($event['color']) : '#3498db';
                    $description = isset($event['description']) ? $event['description'] : '';
                    
                    // Convert time to 12-hour format
                    $displayTime = '';
                    if ($time) {
                        $timeParts = explode(':', $time);
                        if (count($timeParts) === 2) {
                            $hour = (int)$timeParts[0];
                            $minute = $timeParts[1];
                            $ampm = $hour >= 12 ? 'PM' : 'AM';
                            $hour = $hour % 12;
                            if ($hour === 0) $hour = 12;
                            $displayTime = $hour . ':' . $minute . ' ' . $ampm;
                        } else {
                            $displayTime = $time;
                        }
                    }
                    
                    // Compact event item
                    $html .= '<div class="eventlist-widget-item" style="border-left-color: ' . $color . ';">';
                    $html .= '<div class="eventlist-widget-title">' . $title . '</div>';
                    if ($displayTime) {
                        $html .= '<div class="eventlist-widget-time">' . $displayTime . '</div>';
                    }
                    if ($description) {
                        $renderedDesc = $this->renderDescription($description);
                        $html .= '<div class="eventlist-widget-desc">' . $renderedDesc . '</div>';
                    }
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</div>'; // End content
        $html .= '</div>'; // End container
        
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
        
        // Recurring event section
        $html .= '<div class="form-field form-field-checkbox">';
        $html .= '<label class="checkbox-label">';
        $html .= '<input type="checkbox" id="event-recurring-' . $calId . '" name="isRecurring" class="recurring-toggle" onchange="toggleRecurringOptions(\'' . $calId . '\')">';
        $html .= '<span>🔄 Repeating Event</span>';
        $html .= '</label>';
        $html .= '</div>';
        
        // Recurring options (hidden by default)
        $html .= '<div id="recurring-options-' . $calId . '" class="recurring-options" style="display:none;">';
        
        // Recurrence pattern
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">Repeat Every</label>';
        $html .= '<select id="event-recurrence-type-' . $calId . '" name="recurrenceType" class="input-sleek">';
        $html .= '<option value="daily">Daily</option>';
        $html .= '<option value="weekly">Weekly</option>';
        $html .= '<option value="monthly">Monthly</option>';
        $html .= '<option value="yearly">Yearly</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        // Recurrence end date
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">📅 Repeat Until (optional)</label>';
        $html .= '<input type="date" id="event-recurrence-end-' . $calId . '" name="recurrenceEnd" class="input-sleek input-date">';
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
    
    private function renderMonthPicker($calId, $year, $month, $namespace) {
        $html = '<div class="month-picker-overlay" id="month-picker-overlay-' . $calId . '" style="display:none;" onclick="closeMonthPicker(\'' . $calId . '\')">';
        $html .= '<div class="month-picker-dialog" onclick="event.stopPropagation();">';
        $html .= '<h4>Jump to Month</h4>';
        
        $html .= '<div class="month-picker-selects">';
        $html .= '<select id="month-picker-month-' . $calId . '" class="month-picker-select">';
        $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        for ($m = 1; $m <= 12; $m++) {
            $selected = ($m == $month) ? ' selected' : '';
            $html .= '<option value="' . $m . '"' . $selected . '>' . $monthNames[$m - 1] . '</option>';
        }
        $html .= '</select>';
        
        $html .= '<select id="month-picker-year-' . $calId . '" class="month-picker-select">';
        $currentYear = (int)date('Y');
        for ($y = $currentYear - 5; $y <= $currentYear + 5; $y++) {
            $selected = ($y == $year) ? ' selected' : '';
            $html .= '<option value="' . $y . '"' . $selected . '>' . $y . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';
        
        $html .= '<div class="month-picker-actions">';
        $html .= '<button class="btn-sleek btn-cancel-sleek" onclick="closeMonthPicker(\'' . $calId . '\')">Cancel</button>';
        $html .= '<button class="btn-sleek btn-save-sleek" onclick="jumpToSelectedMonth(\'' . $calId . '\', \'' . $namespace . '\')">Go</button>';
        $html .= '</div>';
        
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
                
                // Handle internal DokuWiki links with section anchors
                // Split page and section (e.g., "page#section" or "namespace:page#section")
                $parts = explode('#', $link, 2);
                $pagePart = $parts[0];
                $sectionPart = isset($parts[1]) ? '#' . $parts[1] : '';
                
                // Build URL with properly encoded page and unencoded section anchor
                $wikiUrl = DOKU_BASE . 'doku.php?id=' . rawurlencode($pagePart) . $sectionPart;
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

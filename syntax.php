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
            'date' => '',
            'range' => ''
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
        
        // Check if multiple namespaces or wildcard specified
        $isMultiNamespace = !empty($namespace) && (strpos($namespace, ';') !== false || strpos($namespace, '*') !== false);
        
        if ($isMultiNamespace) {
            $events = $this->loadEventsMultiNamespace($namespace, $year, $month);
        } else {
            $events = $this->loadEvents($namespace, $year, $month);
        }
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
        
        // Build a map of all events with their date ranges for the calendar grid
        $eventRanges = array();
        foreach ($events as $dateKey => $dayEvents) {
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
        
        // Sort by date ascending (chronological order - oldest first)
        ksort($events);
        
        // Sort events within each day by time
        foreach ($events as $dateKey => &$dayEvents) {
            usort($dayEvents, function($a, $b) {
                $timeA = isset($a['time']) ? $a['time'] : '00:00';
                $timeB = isset($b['time']) ? $b['time'] : '00:00';
                return strcmp($timeA, $timeB);
            });
        }
        unset($dayEvents); // Break reference
        
        // Get today's date for comparison
        $today = date('Y-m-d');
        $firstFutureEventId = null;
        
        // Build HTML for each event
        $html = '';
        
        foreach ($events as $dateKey => $dayEvents) {
            $isPast = $dateKey < $today;
            $isToday = $dateKey === $today;
            
            foreach ($dayEvents as $event) {
                // Track first future/today event for auto-scroll
                if (!$firstFutureEventId && $dateKey >= $today) {
                    $firstFutureEventId = isset($event['id']) ? $event['id'] : '';
                }
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
                // Use originalStartDate if this is a multi-month event continuation
                $displayDateKey = isset($event['originalStartDate']) ? $event['originalStartDate'] : $dateKey;
                $dateObj = new DateTime($displayDateKey);
                $displayDate = $dateObj->format('D, M j'); // e.g., "Mon, Jan 24"
                
                // Multi-day indicator
                $multiDay = '';
                if ($endDate && $endDate !== $displayDateKey) {
                    $endObj = new DateTime($endDate);
                    $multiDay = ' → ' . $endObj->format('D, M j');
                }
                
                $completedClass = $completed ? ' event-completed' : '';
                $pastClass = $isPast ? ' event-past' : '';
                $firstFutureAttr = ($firstFutureEventId === $eventId) ? ' data-first-future="true"' : '';
                
                $html .= '<div class="event-compact-item' . $completedClass . $pastClass . '" data-event-id="' . $eventId . '" data-date="' . $dateKey . '" style="border-left-color: ' . $color . ';"' . $firstFutureAttr . '>';
                
                $html .= '<div class="event-info">';
                $html .= '<div class="event-title-row">';
                $html .= '<span class="event-title-compact">' . $title . '</span>';
                $html .= '</div>';
                
                // For past events, hide meta and description (collapsed)
                if (!$isPast) {
                    $html .= '<div class="event-meta-compact">';
                    $html .= '<span class="event-date-time">' . $displayDate . $multiDay;
                    if ($displayTime) {
                        $html .= ' • ' . $displayTime;
                    }
                    // Add TODAY badge for today's events
                    if ($isToday) {
                        $html .= ' <span class="event-today-badge">TODAY</span>';
                    }
                    // Add namespace badge (for multi-namespace or stored namespace)
                    $eventNamespace = isset($event['namespace']) ? $event['namespace'] : '';
                    if (!$eventNamespace && isset($event['_namespace'])) {
                        $eventNamespace = $event['_namespace']; // Fallback to _namespace for backward compatibility
                    }
                    if ($eventNamespace) {
                        $html .= ' <span class="event-namespace-badge">' . htmlspecialchars($eventNamespace) . '</span>';
                    }
                    $html .= '</span>';
                    $html .= '</div>';
                    
                    if ($description) {
                        $html .= '<div class="event-desc-compact">' . $renderedDescription . '</div>';
                    }
                }
                
                $html .= '</div>'; // event-info
                
                // Use stored namespace from event, fallback to passed namespace
                $buttonNamespace = isset($event['namespace']) ? $event['namespace'] : $namespace;
                
                $html .= '<div class="event-actions-compact">';
                $html .= '<button class="event-action-btn" onclick="deleteEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $buttonNamespace . '\')">🗑️</button>';
                $html .= '<button class="event-action-btn" onclick="editEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $buttonNamespace . '\')">✏️</button>';
                $html .= '</div>';
                
                // Checkbox for tasks - ON THE FAR RIGHT
                if ($isTask) {
                    $checked = $completed ? 'checked' : '';
                    $html .= '<input type="checkbox" class="task-checkbox" ' . $checked . ' onclick="toggleTaskComplete(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $buttonNamespace . '\', this.checked)">';
                }
                
                $html .= '</div>';
                
                // Add to HTML output
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
        
        // Check if multiple namespaces or wildcard specified
        $isMultiNamespace = !empty($namespace) && (strpos($namespace, ';') !== false || strpos($namespace, '*') !== false);
        
        if ($isMultiNamespace) {
            $events = $this->loadEventsMultiNamespace($namespace, $year, $month);
        } else {
            $events = $this->loadEvents($namespace, $year, $month);
        }
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
            // Show multiple namespace badges if multi-namespace
            if ($isMultiNamespace) {
                // Handle wildcard
                if (strpos($namespace, '*') !== false) {
                    $html .= '<span class="namespace-badge">' . htmlspecialchars($namespace) . '</span> ';
                } else {
                    // Semicolon-separated list
                    $namespaceList = array_map('trim', explode(';', $namespace));
                    foreach ($namespaceList as $ns) {
                        $ns = trim($ns);
                        if (empty($ns)) continue;
                        $namespaceUrl = DOKU_BASE . 'doku.php?id=' . str_replace(':', ':', $ns);
                        $html .= '<a href="' . $namespaceUrl . '" class="namespace-badge" title="Go to namespace page">' . htmlspecialchars($ns) . '</a> ';
                    }
                }
            } else {
                $namespaceUrl = DOKU_BASE . 'doku.php?id=' . str_replace(':', ':', $namespace);
                $html .= '<a href="' . $namespaceUrl . '" class="namespace-badge" title="Go to namespace page">' . htmlspecialchars($namespace) . '</a>';
            }
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
        $range = isset($data['range']) ? strtolower($data['range']) : '';
        $today = isset($data['today']) ? true : false;
        $sidebar = isset($data['sidebar']) ? true : false;
        
        // Handle "range" parameter - day, week, or month
        if ($range === 'day') {
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            $headerText = 'Today';
        } elseif ($range === 'week') {
            $startDate = date('Y-m-d'); // Today
            $endDateTime = new DateTime($startDate);
            $endDateTime->modify('+7 days');
            $endDate = $endDateTime->format('Y-m-d');
            $headerText = 'This Week';
        } elseif ($range === 'month') {
            $startDate = date('Y-m-01'); // First of current month
            $endDate = date('Y-m-t'); // Last of current month
            $dt = new DateTime($startDate);
            $headerText = $dt->format('F Y');
        } elseif ($sidebar) {
            // Handle "sidebar" parameter - shows today through one month from today
            $startDate = date('Y-m-d'); // Today
            $endDateTime = new DateTime($startDate);
            $endDateTime->modify('+1 month');
            $endDate = $endDateTime->format('Y-m-d'); // One month from today
            $headerText = 'Upcoming';
        } elseif ($today) {
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            $headerText = 'Today';
        } elseif ($daterange) {
            list($startDate, $endDate) = explode(':', $daterange);
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $headerText = $start->format('M j') . ' - ' . $end->format('M j, Y');
        } elseif ($date) {
            $startDate = $date;
            $endDate = $date;
            $dt = new DateTime($date);
            $headerText = $dt->format('l, F j, Y');
        } else {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $dt = new DateTime($startDate);
            $headerText = $dt->format('F Y');
        }
        
        // Load all events in date range
        $allEvents = array();
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day');
        
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        
        // Check if multiple namespaces or wildcard specified
        $isMultiNamespace = !empty($namespace) && (strpos($namespace, ';') !== false || strpos($namespace, '*') !== false);
        
        static $loadedMonths = array();
        
        foreach ($period as $dt) {
            $year = (int)$dt->format('Y');
            $month = (int)$dt->format('n');
            $dateKey = $dt->format('Y-m-d');
            
            $monthKey = $year . '-' . $month . '-' . $namespace;
            
            if (!isset($loadedMonths[$monthKey])) {
                if ($isMultiNamespace) {
                    $loadedMonths[$monthKey] = $this->loadEventsMultiNamespace($namespace, $year, $month);
                } else {
                    $loadedMonths[$monthKey] = $this->loadEvents($namespace, $year, $month);
                }
            }
            
            $monthEvents = $loadedMonths[$monthKey];
            
            if (isset($monthEvents[$dateKey]) && !empty($monthEvents[$dateKey])) {
                $allEvents[$dateKey] = $monthEvents[$dateKey];
            }
        }
        
        // Simple 2-line display widget
        $html = '<div class="eventlist-simple">';
        
        if (empty($allEvents)) {
            $html .= '<div class="eventlist-simple-empty">';
            $html .= '<div class="eventlist-simple-header">' . htmlspecialchars($headerText);
            if ($namespace) {
                $html .= ' <span class="eventlist-simple-namespace">' . htmlspecialchars($namespace) . '</span>';
            }
            $html .= '</div>';
            $html .= '<div class="eventlist-simple-body">No events</div>';
            $html .= '</div>';
        } else {
            // Calculate today and tomorrow's dates for highlighting
            $today = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            
            foreach ($allEvents as $dateKey => $dayEvents) {
                $dateObj = new DateTime($dateKey);
                $displayDate = $dateObj->format('D, M j');
                
                // Check if this date is today or tomorrow
                // Enable highlighting for sidebar mode AND range modes (day, week, month)
                $enableHighlighting = $sidebar || !empty($range);
                $isToday = $enableHighlighting && ($dateKey === $today);
                $isTomorrow = $enableHighlighting && ($dateKey === $tomorrow);
                
                foreach ($dayEvents as $event) {
                    // Skip completed tasks when in sidebar mode or day/week range
                    $skipCompleted = $sidebar || ($range === 'day') || ($range === 'week');
                    if ($skipCompleted && !empty($event['isTask']) && !empty($event['completed'])) {
                        continue;
                    }
                    
                    // Line 1: Header (Title, Time, Date, Namespace)
                    $todayClass = $isToday ? ' eventlist-simple-today' : '';
                    $tomorrowClass = $isTomorrow ? ' eventlist-simple-tomorrow' : '';
                    $html .= '<div class="eventlist-simple-item' . $todayClass . $tomorrowClass . '">';
                    $html .= '<div class="eventlist-simple-header">';
                    
                    // Title
                    $html .= '<span class="eventlist-simple-title">' . htmlspecialchars($event['title']) . '</span>';
                    
                    // Time (12-hour format)
                    if (!empty($event['time'])) {
                        $timeParts = explode(':', $event['time']);
                        if (count($timeParts) === 2) {
                            $hour = (int)$timeParts[0];
                            $minute = $timeParts[1];
                            $ampm = $hour >= 12 ? 'PM' : 'AM';
                            $hour = $hour % 12 ?: 12;
                            $displayTime = $hour . ':' . $minute . ' ' . $ampm;
                            $html .= ' <span class="eventlist-simple-time">' . $displayTime . '</span>';
                        }
                    }
                    
                    // Date
                    $html .= ' <span class="eventlist-simple-date">' . $displayDate . '</span>';
                    
                    // TODAY badge (show for today's events in sidebar)
                    if ($isToday) {
                        $html .= ' <span class="eventlist-simple-today-badge">TODAY</span>';
                    }
                    
                    // Namespace badge (show individual event's namespace)
                    $eventNamespace = isset($event['namespace']) ? $event['namespace'] : '';
                    if (!$eventNamespace && isset($event['_namespace'])) {
                        $eventNamespace = $event['_namespace']; // Fallback to _namespace for multi-namespace loading
                    }
                    if ($eventNamespace) {
                        $html .= ' <span class="eventlist-simple-namespace">' . htmlspecialchars($eventNamespace) . '</span>';
                    }
                    
                    $html .= '</div>'; // header
                    
                    // Line 2: Body (Description only) - only show if description exists
                    if (!empty($event['description'])) {
                        $html .= '<div class="eventlist-simple-body">' . $this->renderDescription($event['description']) . '</div>';
                    }
                    
                    $html .= '</div>'; // item
                }
            }
        }
        
        $html .= '</div>'; // eventlist-simple
        
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
        
        // Time field - dropdown with 15-minute intervals
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">🕐 Time (optional)</label>';
        $html .= '<select id="event-time-' . $calId . '" name="time" class="input-sleek">';
        $html .= '<option value="">No specific time</option>';
        
        // Generate time options in 15-minute intervals
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 15) {
                $timeValue = sprintf('%02d:%02d', $hour, $minute);
                $displayHour = $hour == 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);
                $ampm = $hour < 12 ? 'AM' : 'PM';
                $displayTime = sprintf('%d:%02d %s', $displayHour, $minute, $ampm);
                $html .= '<option value="' . $timeValue . '">' . $displayTime . '</option>';
            }
        }
        
        $html .= '</select>';
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
        
        // Token-based parsing to avoid escaping issues
        $rendered = $description;
        $tokens = array();
        $tokenIndex = 0;
        
        // Convert DokuWiki image syntax {{image.jpg}} to tokens
        $pattern = '/\{\{([^}|]+?)(?:\|([^}]+))?\}\}/';
        preg_match_all($pattern, $rendered, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $imagePath = trim($match[1]);
            $alt = isset($match[2]) ? trim($match[2]) : '';
            
            // Handle external URLs
            if (preg_match('/^https?:\/\//', $imagePath)) {
                $imageHtml = '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($alt) . '" class="event-image" />';
            } else {
                // Handle internal DokuWiki images
                $imageUrl = DOKU_BASE . 'lib/exe/fetch.php?media=' . rawurlencode($imagePath);
                $imageHtml = '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($alt) . '" class="event-image" />';
            }
            
            $token = "\x00TOKEN" . $tokenIndex . "\x00";
            $tokens[$tokenIndex] = $imageHtml;
            $tokenIndex++;
            $rendered = str_replace($match[0], $token, $rendered);
        }
        
        // Convert DokuWiki link syntax [[link|text]] to tokens
        $pattern = '/\[\[([^|\]]+?)(?:\|([^\]]+))?\]\]/';
        preg_match_all($pattern, $rendered, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $link = trim($match[1]);
            $text = isset($match[2]) ? trim($match[2]) : $link;
            
            // Handle external URLs
            if (preg_match('/^https?:\/\//', $link)) {
                $linkHtml = '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($text) . '</a>';
            } else {
                // Handle internal DokuWiki links with section anchors
                $parts = explode('#', $link, 2);
                $pagePart = $parts[0];
                $sectionPart = isset($parts[1]) ? '#' . $parts[1] : '';
                
                $wikiUrl = DOKU_BASE . 'doku.php?id=' . rawurlencode($pagePart) . $sectionPart;
                $linkHtml = '<a href="' . $wikiUrl . '">' . htmlspecialchars($text) . '</a>';
            }
            
            $token = "\x00TOKEN" . $tokenIndex . "\x00";
            $tokens[$tokenIndex] = $linkHtml;
            $tokenIndex++;
            $rendered = str_replace($match[0], $token, $rendered);
        }
        
        // Convert markdown-style links [text](url) to tokens
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';
        preg_match_all($pattern, $rendered, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $text = trim($match[1]);
            $url = trim($match[2]);
            
            if (preg_match('/^https?:\/\//', $url)) {
                $linkHtml = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($text) . '</a>';
            } else {
                $linkHtml = '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($text) . '</a>';
            }
            
            $token = "\x00TOKEN" . $tokenIndex . "\x00";
            $tokens[$tokenIndex] = $linkHtml;
            $tokenIndex++;
            $rendered = str_replace($match[0], $token, $rendered);
        }
        
        // Convert plain URLs to tokens
        $pattern = '/(https?:\/\/[^\s<]+)/';
        preg_match_all($pattern, $rendered, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $url = $match[1];
            $linkHtml = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($url) . '</a>';
            
            $token = "\x00TOKEN" . $tokenIndex . "\x00";
            $tokens[$tokenIndex] = $linkHtml;
            $tokenIndex++;
            $rendered = str_replace($match[0], $token, $rendered);
        }
        
        // NOW escape HTML (tokens are protected)
        $rendered = htmlspecialchars($rendered);
        
        // Convert newlines to <br>
        $rendered = nl2br($rendered);
        
        // DokuWiki text formatting
        // Bold: **text** or __text__
        $rendered = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $rendered);
        $rendered = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $rendered);
        
        // Italic: //text//
        $rendered = preg_replace('/\/\/(.+?)\/\//', '<em>$1</em>', $rendered);
        
        // Strikethrough: <del>text</del>
        $rendered = preg_replace('/&lt;del&gt;(.+?)&lt;\/del&gt;/', '<del>$1</del>', $rendered);
        
        // Monospace: ''text''
        $rendered = preg_replace('/&#039;&#039;(.+?)&#039;&#039;/', '<code>$1</code>', $rendered);
        
        // Subscript: <sub>text</sub>
        $rendered = preg_replace('/&lt;sub&gt;(.+?)&lt;\/sub&gt;/', '<sub>$1</sub>', $rendered);
        
        // Superscript: <sup>text</sup>
        $rendered = preg_replace('/&lt;sup&gt;(.+?)&lt;\/sup&gt;/', '<sup>$1</sup>', $rendered);
        
        // Restore tokens
        foreach ($tokens as $i => $html) {
            $rendered = str_replace("\x00TOKEN" . $i . "\x00", $html, $rendered);
        }
        
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
    
    private function loadEventsMultiNamespace($namespaces, $year, $month) {
        // Check for wildcard pattern (namespace:*)
        if (preg_match('/^(.+):\*$/', $namespaces, $matches)) {
            $baseNamespace = $matches[1];
            return $this->loadEventsWildcard($baseNamespace, $year, $month);
        }
        
        // Check for root wildcard (just *)
        if ($namespaces === '*') {
            return $this->loadEventsWildcard('', $year, $month);
        }
        
        // Parse namespace list (semicolon separated)
        // e.g., "team:projects;personal;work:tasks" = three namespaces
        $namespaceList = array_map('trim', explode(';', $namespaces));
        
        // Load events from all namespaces
        $allEvents = array();
        foreach ($namespaceList as $ns) {
            $ns = trim($ns);
            if (empty($ns)) continue;
            
            $events = $this->loadEvents($ns, $year, $month);
            
            // Add namespace tag to each event
            foreach ($events as $dateKey => $dayEvents) {
                if (!isset($allEvents[$dateKey])) {
                    $allEvents[$dateKey] = array();
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
        // Find all subdirectories under the base namespace
        $dataDir = DOKU_INC . 'data/meta/';
        if ($baseNamespace) {
            $dataDir .= str_replace(':', '/', $baseNamespace) . '/';
        }
        
        $allEvents = array();
        
        // First, load events from the base namespace itself
        if (empty($baseNamespace)) {
            // Root wildcard - load from root calendar
            $events = $this->loadEvents('', $year, $month);
            foreach ($events as $dateKey => $dayEvents) {
                if (!isset($allEvents[$dateKey])) {
                    $allEvents[$dateKey] = array();
                }
                foreach ($dayEvents as $event) {
                    $event['_namespace'] = '';
                    $allEvents[$dateKey][] = $event;
                }
            }
        } else {
            $events = $this->loadEvents($baseNamespace, $year, $month);
            foreach ($events as $dateKey => $dayEvents) {
                if (!isset($allEvents[$dateKey])) {
                    $allEvents[$dateKey] = array();
                }
                foreach ($dayEvents as $event) {
                    $event['_namespace'] = $baseNamespace;
                    $allEvents[$dateKey][] = $event;
                }
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
                $events = $this->loadEvents($namespace, $year, $month);
                foreach ($events as $dateKey => $dayEvents) {
                    if (!isset($allEvents[$dateKey])) {
                        $allEvents[$dateKey] = array();
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
}

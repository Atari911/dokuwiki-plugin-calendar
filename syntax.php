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
        
        $html = '<div class="calendar-compact-container" id="' . $calId . '" data-namespace="' . htmlspecialchars($namespace) . '" data-original-namespace="' . htmlspecialchars($namespace) . '" data-year="' . $year . '" data-month="' . $month . '">';
        
        // Load calendar JavaScript manually (not through DokuWiki concatenation)
        $html .= '<script src="' . DOKU_BASE . 'lib/plugins/calendar/calendar-main.js"></script>';
        
        // Initialize DOKU_BASE for JavaScript
        $html .= '<script>if(typeof DOKU_BASE==="undefined"){window.DOKU_BASE="' . DOKU_BASE . '";}</script>';
        
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
        
        // Namespace filter indicator - only show if actively filtering a specific namespace
        if ($namespace && $namespace !== '*' && strpos($namespace, '*') === false && strpos($namespace, ';') === false) {
            $html .= '<div class="calendar-namespace-filter" id="namespace-filter-' . $calId . '">';
            $html .= '<span class="namespace-filter-label">Filtering:</span>';
            $html .= '<span class="namespace-filter-name">' . htmlspecialchars($namespace) . '</span>';
            $html .= '<button class="namespace-filter-clear" onclick="clearNamespaceFilter(\'' . $calId . '\')" title="Clear filter and show all namespaces">✕</button>';
            $html .= '</div>';
        }
        
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
        
        // Search bar in header
        $html .= '<div class="event-search-container-inline">';
        $html .= '<input type="text" class="event-search-input-inline" id="event-search-' . $calId . '" placeholder="🔍 Search..." oninput="filterEvents(\'' . $calId . '\', this.value)">';
        $html .= '<button class="event-search-clear-inline" id="search-clear-' . $calId . '" onclick="clearEventSearch(\'' . $calId . '\')" style="display:none;">✕</button>';
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
        
        // Check for time conflicts
        $events = $this->checkTimeConflicts($events);
        
        // Sort by date ascending (chronological order - oldest first)
        ksort($events);
        
        // Sort events within each day by time
        foreach ($events as $dateKey => &$dayEvents) {
            usort($dayEvents, function($a, $b) {
                $timeA = isset($a['time']) && !empty($a['time']) ? $a['time'] : null;
                $timeB = isset($b['time']) && !empty($b['time']) ? $b['time'] : null;
                
                // All-day events (no time) go to the TOP
                if ($timeA === null && $timeB !== null) return -1; // A before B
                if ($timeA !== null && $timeB === null) return 1;  // A after B  
                if ($timeA === null && $timeB === null) return 0;  // Both all-day, equal
                
                // Both have times, sort chronologically
                return strcmp($timeA, $timeB);
            });
        }
        unset($dayEvents); // Break reference
        
        // Get today's date for comparison
        $today = date('Y-m-d');
        $firstFutureEventId = null;
        
        // Helper function to check if event is past (with 15-minute grace period for timed events)
        $isEventPast = function($dateKey, $time) use ($today) {
            // If event is on a past date, it's definitely past
            if ($dateKey < $today) {
                return true;
            }
            
            // If event is on a future date, it's definitely not past
            if ($dateKey > $today) {
                return false;
            }
            
            // Event is today - check time with grace period
            if ($time && $time !== '') {
                try {
                    $currentDateTime = new DateTime();
                    $eventDateTime = new DateTime($dateKey . ' ' . $time);
                    
                    // Add 15-minute grace period
                    $eventDateTime->modify('+15 minutes');
                    
                    // Event is past if current time > event time + 15 minutes
                    return $currentDateTime > $eventDateTime;
                } catch (Exception $e) {
                    // If time parsing fails, fall back to date-only comparison
                    return false;
                }
            }
            
            // No time specified for today's event, treat as future
            return false;
        };
        
        // Build HTML for each event - separate past/completed from future
        $pastHtml = '';
        $futureHtml = '';
        $pastCount = 0;
        
        foreach ($events as $dateKey => $dayEvents) {
            
            foreach ($dayEvents as $event) {
                // Track first future/today event for auto-scroll
                if (!$firstFutureEventId && $dateKey >= $today) {
                    $firstFutureEventId = isset($event['id']) ? $event['id'] : '';
                }
                $eventId = isset($event['id']) ? $event['id'] : '';
                $title = isset($event['title']) ? htmlspecialchars($event['title']) : 'Untitled';
                $timeRaw = isset($event['time']) ? $event['time'] : '';
                $time = htmlspecialchars($timeRaw);
                $endTime = isset($event['endTime']) ? htmlspecialchars($event['endTime']) : '';
                $color = isset($event['color']) ? htmlspecialchars($event['color']) : '#3498db';
                $description = isset($event['description']) ? $event['description'] : '';
                $isTask = isset($event['isTask']) ? $event['isTask'] : false;
                $completed = isset($event['completed']) ? $event['completed'] : false;
                $endDate = isset($event['endDate']) ? $event['endDate'] : '';
                
                // Use helper function to determine if event is past (with grace period)
                $isPast = $isEventPast($dateKey, $timeRaw);
                $isToday = $dateKey === $today;
                
                // Check if event should be in past section
                // EXCEPTION: Uncompleted tasks (isTask && !completed) should stay visible even if past
                $isPastOrCompleted = ($isPast && (!$isTask || $completed)) || $completed;
                if ($isPastOrCompleted) {
                    $pastCount++;
                }
                
                // Determine if task is past due (past date, is task, not completed)
                $isPastDue = $isPast && $isTask && !$completed;
                
                // Process description for wiki syntax, HTML, images, and links
                $renderedDescription = $this->renderDescription($description);
                
                // Convert to 12-hour format and handle time ranges
                $displayTime = '';
                if ($time) {
                    $timeObj = DateTime::createFromFormat('H:i', $time);
                    if ($timeObj) {
                        $displayTime = $timeObj->format('g:i A');
                        
                        // Add end time if present and different from start time
                        if ($endTime && $endTime !== $time) {
                            $endTimeObj = DateTime::createFromFormat('H:i', $endTime);
                            if ($endTimeObj) {
                                $displayTime .= ' - ' . $endTimeObj->format('g:i A');
                            }
                        }
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
                // Don't grey out past due tasks - they need attention!
                $pastClass = ($isPast && !$isPastDue) ? ' event-past' : '';
                $pastDueClass = $isPastDue ? ' event-pastdue' : '';
                $firstFutureAttr = ($firstFutureEventId === $eventId) ? ' data-first-future="true"' : '';
                
                $eventHtml = '<div class="event-compact-item' . $completedClass . $pastClass . $pastDueClass . '" data-event-id="' . $eventId . '" data-date="' . $dateKey . '" style="border-left-color: ' . $color . ';"' . $firstFutureAttr . '>';
                
                $eventHtml .= '<div class="event-info">';
                $eventHtml .= '<div class="event-title-row">';
                $eventHtml .= '<span class="event-title-compact">' . $title . '</span>';
                $eventHtml .= '</div>';
                
                // For past events, hide meta and description (collapsed)
                // EXCEPTION: Past due tasks should show their details
                if (!$isPast || $isPastDue) {
                    $eventHtml .= '<div class="event-meta-compact">';
                    $eventHtml .= '<span class="event-date-time">' . $displayDate . $multiDay;
                    if ($displayTime) {
                        $eventHtml .= ' • ' . $displayTime;
                    }
                    // Add TODAY badge for today's events OR PAST DUE for uncompleted past tasks
                    if ($isPastDue) {
                        $eventHtml .= ' <span class="event-pastdue-badge">PAST DUE</span>';
                    } elseif ($isToday) {
                        $eventHtml .= ' <span class="event-today-badge">TODAY</span>';
                    }
                    // Add namespace badge - ALWAYS show if event has a namespace
                    $eventNamespace = isset($event['namespace']) ? $event['namespace'] : '';
                    if (!$eventNamespace && isset($event['_namespace'])) {
                        $eventNamespace = $event['_namespace']; // Fallback to _namespace for backward compatibility
                    }
                    // Show badge if namespace exists and is not empty
                    if ($eventNamespace && $eventNamespace !== '') {
                        $eventHtml .= ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' . $calId . '\', \'' . htmlspecialchars($eventNamespace) . '\')" style="cursor:pointer;" title="Click to filter by this namespace">' . htmlspecialchars($eventNamespace) . '</span>';
                    }
                    
                    // Add conflict warning if event has time conflicts
                    if (isset($event['hasConflict']) && $event['hasConflict'] && isset($event['conflictsWith'])) {
                        $conflictList = [];
                        foreach ($event['conflictsWith'] as $conflict) {
                            $conflictText = htmlspecialchars($conflict['title']);
                            if (!empty($conflict['time'])) {
                                // Format time range
                                $startTimeObj = DateTime::createFromFormat('H:i', $conflict['time']);
                                $startTimeFormatted = $startTimeObj ? $startTimeObj->format('g:i A') : $conflict['time'];
                                
                                if (!empty($conflict['endTime']) && $conflict['endTime'] !== $conflict['time']) {
                                    $endTimeObj = DateTime::createFromFormat('H:i', $conflict['endTime']);
                                    $endTimeFormatted = $endTimeObj ? $endTimeObj->format('g:i A') : $conflict['endTime'];
                                    $conflictText .= ' (' . $startTimeFormatted . ' - ' . $endTimeFormatted . ')';
                                } else {
                                    $conflictText .= ' (' . $startTimeFormatted . ')';
                                }
                            }
                            $conflictList[] = $conflictText;
                        }
                        $conflictCount = count($event['conflictsWith']);
                        $conflictJson = htmlspecialchars(json_encode($conflictList), ENT_QUOTES, 'UTF-8');
                        $eventHtml .= ' <span class="event-conflict-badge" data-conflicts="' . $conflictJson . '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' . $conflictCount . '</span>';
                    }
                    
                    $eventHtml .= '</span>';
                    $eventHtml .= '</div>';
                    
                    if ($description) {
                        $eventHtml .= '<div class="event-desc-compact">' . $renderedDescription . '</div>';
                    }
                } else {
                    // Past events: render with display:none for click-to-expand
                    $eventHtml .= '<div class="event-meta-compact" style="display:none;">';
                    $eventHtml .= '<span class="event-date-time">' . $displayDate . $multiDay;
                    if ($displayTime) {
                        $eventHtml .= ' • ' . $displayTime;
                    }
                    $eventNamespace = isset($event['namespace']) ? $event['namespace'] : '';
                    if (!$eventNamespace && isset($event['_namespace'])) {
                        $eventNamespace = $event['_namespace'];
                    }
                    if ($eventNamespace && $eventNamespace !== '') {
                        $eventHtml .= ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' . $calId . '\', \'' . htmlspecialchars($eventNamespace) . '\')" style="cursor:pointer;" title="Click to filter by this namespace">' . htmlspecialchars($eventNamespace) . '</span>';
                    }
                    
                    // Add conflict warning if event has time conflicts
                    if (isset($event['hasConflict']) && $event['hasConflict'] && isset($event['conflictsWith'])) {
                        $conflictList = [];
                        foreach ($event['conflictsWith'] as $conflict) {
                            $conflictText = htmlspecialchars($conflict['title']);
                            if (!empty($conflict['time'])) {
                                $startTimeObj = DateTime::createFromFormat('H:i', $conflict['time']);
                                $startTimeFormatted = $startTimeObj ? $startTimeObj->format('g:i A') : $conflict['time'];
                                
                                if (!empty($conflict['endTime']) && $conflict['endTime'] !== $conflict['time']) {
                                    $endTimeObj = DateTime::createFromFormat('H:i', $conflict['endTime']);
                                    $endTimeFormatted = $endTimeObj ? $endTimeObj->format('g:i A') : $conflict['endTime'];
                                    $conflictText .= ' (' . $startTimeFormatted . ' - ' . $endTimeFormatted . ')';
                                } else {
                                    $conflictText .= ' (' . $startTimeFormatted . ')';
                                }
                            }
                            $conflictList[] = $conflictText;
                        }
                        $conflictCount = count($event['conflictsWith']);
                        $conflictJson = htmlspecialchars(json_encode($conflictList), ENT_QUOTES, 'UTF-8');
                        $eventHtml .= ' <span class="event-conflict-badge" data-conflicts="' . $conflictJson . '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' . $conflictCount . '</span>';
                    }
                    
                    $eventHtml .= '</span>';
                    $eventHtml .= '</div>';
                    
                    if ($description) {
                        $eventHtml .= '<div class="event-desc-compact" style="display:none;">' . $renderedDescription . '</div>';
                    }
                }
                
                $eventHtml .= '</div>'; // event-info
                
                // Use stored namespace from event, fallback to passed namespace
                $buttonNamespace = isset($event['namespace']) ? $event['namespace'] : $namespace;
                
                $eventHtml .= '<div class="event-actions-compact">';
                $eventHtml .= '<button class="event-action-btn" onclick="deleteEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $buttonNamespace . '\')">🗑️</button>';
                $eventHtml .= '<button class="event-action-btn" onclick="editEvent(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $buttonNamespace . '\')">✏️</button>';
                $eventHtml .= '</div>';
                
                // Checkbox for tasks - ON THE FAR RIGHT
                if ($isTask) {
                    $checked = $completed ? 'checked' : '';
                    $eventHtml .= '<input type="checkbox" class="task-checkbox" ' . $checked . ' onclick="toggleTaskComplete(\'' . $calId . '\', \'' . $eventId . '\', \'' . $dateKey . '\', \'' . $buttonNamespace . '\', this.checked)">';
                }
                
                $eventHtml .= '</div>';
                
                // Add to appropriate section
                if ($isPastOrCompleted) {
                    $pastHtml .= $eventHtml;
                } else {
                    $futureHtml .= $eventHtml;
                }
            }
        }
        
        // Build final HTML with collapsible past events section
        $html = '';
        
        // Add collapsible past events section if any exist
        if ($pastCount > 0) {
            $html .= '<div class="past-events-section">';
            $html .= '<div class="past-events-toggle" onclick="togglePastEvents(\'' . $calId . '\')">';
            $html .= '<span class="past-events-arrow" id="past-arrow-' . $calId . '">▶</span> ';
            $html .= '<span class="past-events-label">Past Events (' . $pastCount . ')</span>';
            $html .= '</div>';
            $html .= '<div class="past-events-content" id="past-events-' . $calId . '" style="display:none;">';
            $html .= $pastHtml;
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Add future events
        $html .= $futureHtml;
        
        return $html;
    }
    
    /**
     * Check for time conflicts between events
     */
    private function checkTimeConflicts($events) {
        // Group events by date
        $eventsByDate = [];
        foreach ($events as $date => $dateEvents) {
            if (!is_array($dateEvents)) continue;
            
            foreach ($dateEvents as $evt) {
                if (empty($evt['time'])) continue; // Skip all-day events
                
                if (!isset($eventsByDate[$date])) {
                    $eventsByDate[$date] = [];
                }
                $eventsByDate[$date][] = $evt;
            }
        }
        
        // Check for overlaps on each date
        foreach ($eventsByDate as $date => $dateEvents) {
            for ($i = 0; $i < count($dateEvents); $i++) {
                for ($j = $i + 1; $j < count($dateEvents); $j++) {
                    if ($this->eventsOverlap($dateEvents[$i], $dateEvents[$j])) {
                        // Mark both events as conflicting
                        $dateEvents[$i]['hasConflict'] = true;
                        $dateEvents[$j]['hasConflict'] = true;
                        
                        // Store conflict info
                        if (!isset($dateEvents[$i]['conflictsWith'])) {
                            $dateEvents[$i]['conflictsWith'] = [];
                        }
                        if (!isset($dateEvents[$j]['conflictsWith'])) {
                            $dateEvents[$j]['conflictsWith'] = [];
                        }
                        
                        $dateEvents[$i]['conflictsWith'][] = [
                            'id' => $dateEvents[$j]['id'],
                            'title' => $dateEvents[$j]['title'],
                            'time' => $dateEvents[$j]['time'],
                            'endTime' => isset($dateEvents[$j]['endTime']) ? $dateEvents[$j]['endTime'] : ''
                        ];
                        
                        $dateEvents[$j]['conflictsWith'][] = [
                            'id' => $dateEvents[$i]['id'],
                            'title' => $dateEvents[$i]['title'],
                            'time' => $dateEvents[$i]['time'],
                            'endTime' => isset($dateEvents[$i]['endTime']) ? $dateEvents[$i]['endTime'] : ''
                        ];
                    }
                }
            }
            
            // Update the events array with conflict information
            foreach ($events[$date] as &$evt) {
                foreach ($dateEvents as $checkedEvt) {
                    if ($evt['id'] === $checkedEvt['id']) {
                        if (isset($checkedEvt['hasConflict'])) {
                            $evt['hasConflict'] = $checkedEvt['hasConflict'];
                        }
                        if (isset($checkedEvt['conflictsWith'])) {
                            $evt['conflictsWith'] = $checkedEvt['conflictsWith'];
                        }
                        break;
                    }
                }
            }
        }
        
        return $events;
    }
    
    /**
     * Check if two events overlap in time
     */
    private function eventsOverlap($evt1, $evt2) {
        if (empty($evt1['time']) || empty($evt2['time'])) {
            return false; // All-day events don't conflict
        }
        
        $start1 = $evt1['time'];
        $end1 = isset($evt1['endTime']) && !empty($evt1['endTime']) ? $evt1['endTime'] : $evt1['time'];
        
        $start2 = $evt2['time'];
        $end2 = isset($evt2['endTime']) && !empty($evt2['endTime']) ? $evt2['endTime'] : $evt2['time'];
        
        // Convert to minutes for easier comparison
        $start1Mins = $this->timeToMinutes($start1);
        $end1Mins = $this->timeToMinutes($end1);
        $start2Mins = $this->timeToMinutes($start2);
        $end2Mins = $this->timeToMinutes($end2);
        
        // Check for overlap: start1 < end2 AND start2 < end1
        return $start1Mins < $end2Mins && $start2Mins < $end1Mins;
    }
    
    /**
     * Convert HH:MM time to minutes since midnight
     */
    private function timeToMinutes($timeStr) {
        $parts = explode(':', $timeStr);
        if (count($parts) !== 2) return 0;
        
        return (int)$parts[0] * 60 + (int)$parts[1];
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
        
        $html = '<div class="event-panel-standalone" id="' . $calId . '" data-height="' . htmlspecialchars($height) . '" data-namespace="' . htmlspecialchars($namespace) . '" data-original-namespace="' . htmlspecialchars($namespace) . '">';
        
        // Load calendar JavaScript manually (not through DokuWiki concatenation)
        $html .= '<script src="' . DOKU_BASE . 'lib/plugins/calendar/calendar-main.js"></script>';
        
        // Initialize DOKU_BASE for JavaScript
        $html .= '<script>if(typeof DOKU_BASE==="undefined"){window.DOKU_BASE="' . DOKU_BASE . '";}</script>';
        
        // Compact two-row header designed for ~500px width
        $html .= '<div class="panel-header-compact">';
        
        // Row 1: Navigation and title
        $html .= '<div class="panel-header-row-1">';
        $html .= '<button class="panel-nav-btn" onclick="navEventPanel(\'' . $calId . '\', ' . $prevYear . ', ' . $prevMonth . ', \'' . $namespace . '\')">‹</button>';
        
        // Compact month name (e.g. "Feb 2026" instead of "February 2026 Events")
        $shortMonthName = date('M Y', mktime(0, 0, 0, $month, 1, $year));
        $html .= '<h3 class="panel-month-title" onclick="openMonthPickerPanel(\'' . $calId . '\', ' . $year . ', ' . $month . ', \'' . $namespace . '\')" title="Click to jump to month">' . $shortMonthName . '</h3>';
        
        $html .= '<button class="panel-nav-btn" onclick="navEventPanel(\'' . $calId . '\', ' . $nextYear . ', ' . $nextMonth . ', \'' . $namespace . '\')">›</button>';
        
        // Namespace badge (if applicable)
        if ($namespace) {
            if ($isMultiNamespace) {
                if (strpos($namespace, '*') !== false) {
                    $html .= '<span class="panel-ns-badge" title="' . htmlspecialchars($namespace) . '">' . htmlspecialchars($namespace) . '</span>';
                } else {
                    $namespaceList = array_map('trim', explode(';', $namespace));
                    $nsCount = count($namespaceList);
                    $html .= '<span class="panel-ns-badge" title="' . htmlspecialchars(implode(', ', $namespaceList)) . '">' . $nsCount . ' NS</span>';
                }
            } else {
                $isFiltering = ($namespace !== '*' && strpos($namespace, '*') === false && strpos($namespace, ';') === false);
                if ($isFiltering) {
                    $html .= '<span class="panel-ns-badge filter-on" title="Filtering by ' . htmlspecialchars($namespace) . ' - click to clear" onclick="clearNamespaceFilterPanel(\'' . $calId . '\')">' . htmlspecialchars($namespace) . ' ✕</span>';
                } else {
                    $html .= '<span class="panel-ns-badge" title="' . htmlspecialchars($namespace) . '">' . htmlspecialchars($namespace) . '</span>';
                }
            }
        }
        
        $html .= '<button class="panel-today-btn" onclick="jumpTodayPanel(\'' . $calId . '\', \'' . $namespace . '\')">Today</button>';
        $html .= '</div>';
        
        // Row 2: Search and add button
        $html .= '<div class="panel-header-row-2">';
        $html .= '<div class="panel-search-box">';
        $html .= '<input type="text" class="panel-search-input" id="event-search-' . $calId . '" placeholder="Search events..." oninput="filterEvents(\'' . $calId . '\', this.value)">';
        $html .= '<button class="panel-search-clear" id="search-clear-' . $calId . '" onclick="clearEventSearch(\'' . $calId . '\')" style="display:none;">✕</button>';
        $html .= '</div>';
        $html .= '<button class="panel-add-btn" onclick="openAddEventPanel(\'' . $calId . '\', \'' . $namespace . '\')">+ Add</button>';
        $html .= '</div>';
        
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
        // If no namespace specified, show all namespaces
        if (empty($namespace)) {
            $namespace = '*';
        }
        $daterange = $data['daterange'];
        $date = $data['date'];
        $range = isset($data['range']) ? strtolower($data['range']) : '';
        $today = isset($data['today']) ? true : false;
        $sidebar = isset($data['sidebar']) ? true : false;
        $showchecked = isset($data['showchecked']) ? true : false; // New parameter
        $noheader = isset($data['noheader']) ? true : false; // New parameter to hide header
        
        // Handle "range" parameter - day, week, or month
        if ($range === 'day') {
            $startDate = date('Y-m-d', strtotime('-30 days')); // Include past 30 days for past due tasks
            $endDate = date('Y-m-d');
            $headerText = 'Today';
        } elseif ($range === 'week') {
            $startDate = date('Y-m-d', strtotime('-30 days')); // Include past 30 days for past due tasks
            $endDateTime = new DateTime();
            $endDateTime->modify('+7 days');
            $endDate = $endDateTime->format('Y-m-d');
            $headerText = 'This Week';
        } elseif ($range === 'month') {
            $startDate = date('Y-m-01', strtotime('-1 month')); // Include previous month for past due tasks
            $endDate = date('Y-m-t'); // Last of current month
            $dt = new DateTime();
            $headerText = $dt->format('F Y');
        } elseif ($sidebar) {
            // NEW: Sidebar widget - load current week's events
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            
            // Load events for the entire week
            $start = new DateTime($weekStart);
            $end = new DateTime($weekEnd);
            $end->modify('+1 day'); // DatePeriod excludes end date
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end);
            
            $isMultiNamespace = !empty($namespace) && (strpos($namespace, ';') !== false || strpos($namespace, '*') !== false);
            $allEvents = [];
            $loadedMonths = [];
            
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
            
            // Apply time conflict detection
            $allEvents = $this->checkTimeConflicts($allEvents);
            
            $calId = 'sidebar-' . substr(md5($namespace . $weekStart), 0, 8);
            
            // Render sidebar widget and return immediately
            return $this->renderSidebarWidget($allEvents, $namespace, $calId);
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
        
        // Sort events by date (already sorted by dateKey), then by time within each day
        foreach ($allEvents as $dateKey => &$dayEvents) {
            usort($dayEvents, function($a, $b) {
                $timeA = isset($a['time']) && !empty($a['time']) ? $a['time'] : null;
                $timeB = isset($b['time']) && !empty($b['time']) ? $b['time'] : null;
                
                // All-day events (no time) go to the TOP
                if ($timeA === null && $timeB !== null) return -1; // A before B
                if ($timeA !== null && $timeB === null) return 1;  // A after B  
                if ($timeA === null && $timeB === null) return 0;  // Both all-day, equal
                
                // Both have times, sort chronologically
                return strcmp($timeA, $timeB);
            });
        }
        unset($dayEvents); // Break reference
        
        // Simple 2-line display widget
        $calId = 'eventlist_' . uniqid();
        $html = '<div class="eventlist-simple" id="' . $calId . '">';
        
        // Load calendar JavaScript manually (not through DokuWiki concatenation)
        $html .= '<script src="' . DOKU_BASE . 'lib/plugins/calendar/calendar-main.js"></script>';
        
        // Initialize DOKU_BASE for JavaScript
        $html .= '<script>if(typeof DOKU_BASE==="undefined"){window.DOKU_BASE="' . DOKU_BASE . '";}</script>';
        
        // Add compact header with date and clock for "today" mode (unless noheader is set)
        if ($today && !empty($allEvents) && !$noheader) {
            $todayDate = new DateTime();
            $displayDate = $todayDate->format('D, M j, Y'); // "Fri, Jan 30, 2026"
            $currentTime = $todayDate->format('g:i:s A'); // "2:45:30 PM"
            
            $html .= '<div class="eventlist-today-header">';
            $html .= '<span class="eventlist-today-clock" id="clock-' . $calId . '">' . $currentTime . '</span>';
            $html .= '<div class="eventlist-bottom-info">';
            $html .= '<span class="eventlist-weather"><span id="weather-icon-' . $calId . '">🌤️</span> <span id="weather-temp-' . $calId . '">--°</span></span>';
            $html .= '<span class="eventlist-today-date">' . $displayDate . '</span>';
            $html .= '</div>';
            
            // Three CPU/Memory bars (all update live)
            $html .= '<div class="eventlist-stats-container">';
            
            // 5-minute load average (green, updates every 2 seconds)
            $html .= '<div class="eventlist-cpu-bar" onmouseover="showTooltip_' . $calId . '(\'green\')" onmouseout="hideTooltip_' . $calId . '(\'green\')">';
            $html .= '<div class="eventlist-cpu-fill" id="cpu-5min-' . $calId . '" style="width: 0%;"></div>';
            $html .= '<div class="system-tooltip" id="tooltip-green-' . $calId . '" style="display:none;"></div>';
            $html .= '</div>';
            
            // Real-time CPU (purple, updates with 5-sec average)
            $html .= '<div class="eventlist-cpu-bar eventlist-cpu-realtime" onmouseover="showTooltip_' . $calId . '(\'purple\')" onmouseout="hideTooltip_' . $calId . '(\'purple\')">';
            $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-purple" id="cpu-realtime-' . $calId . '" style="width: 0%;"></div>';
            $html .= '<div class="system-tooltip" id="tooltip-purple-' . $calId . '" style="display:none;"></div>';
            $html .= '</div>';
            
            // Real-time Memory (orange, updates)
            $html .= '<div class="eventlist-cpu-bar eventlist-mem-realtime" onmouseover="showTooltip_' . $calId . '(\'orange\')" onmouseout="hideTooltip_' . $calId . '(\'orange\')">';
            $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-orange" id="mem-realtime-' . $calId . '" style="width: 0%;"></div>';
            $html .= '<div class="system-tooltip" id="tooltip-orange-' . $calId . '" style="display:none;"></div>';
            $html .= '</div>';
            
            $html .= '</div>';
            $html .= '</div>';
            
            // Add JavaScript to update clock and weather
            $html .= '<script>
(function() {
    // Update clock every second
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, "0");
        const seconds = String(now.getSeconds()).padStart(2, "0");
        const ampm = hours >= 12 ? "PM" : "AM";
        hours = hours % 12 || 12;
        const timeStr = hours + ":" + minutes + ":" + seconds + " " + ampm;
        const clockEl = document.getElementById("clock-' . $calId . '");
        if (clockEl) clockEl.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    
    // Fetch weather (geolocation-based)
    function updateWeather() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                
                // Use Open-Meteo API (free, no key required)
                fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&temperature_unit=fahrenheit`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.current_weather) {
                            const temp = Math.round(data.current_weather.temperature);
                            const weatherCode = data.current_weather.weathercode;
                            const icon = getWeatherIcon(weatherCode);
                            const iconEl = document.getElementById("weather-icon-' . $calId . '");
                            const tempEl = document.getElementById("weather-temp-' . $calId . '");
                            if (iconEl) iconEl.textContent = icon;
                            if (tempEl) tempEl.innerHTML = temp + "&deg;";
                        }
                    })
                    .catch(error => {
                        console.log("Weather fetch error:", error);
                    });
            }, function(error) {
                // If geolocation fails, use Sacramento as default
                fetch("https://api.open-meteo.com/v1/forecast?latitude=38.5816&longitude=-121.4944&current_weather=true&temperature_unit=fahrenheit")
                    .then(response => response.json())
                    .then(data => {
                        if (data.current_weather) {
                            const temp = Math.round(data.current_weather.temperature);
                            const weatherCode = data.current_weather.weathercode;
                            const icon = getWeatherIcon(weatherCode);
                            const iconEl = document.getElementById("weather-icon-' . $calId . '");
                            const tempEl = document.getElementById("weather-temp-' . $calId . '");
                            if (iconEl) iconEl.textContent = icon;
                            if (tempEl) tempEl.innerHTML = temp + "&deg;";
                        }
                    })
                    .catch(err => console.log("Weather error:", err));
            });
        } else {
            // No geolocation, use Sacramento
            fetch("https://api.open-meteo.com/v1/forecast?latitude=38.5816&longitude=-121.4944&current_weather=true&temperature_unit=fahrenheit")
                .then(response => response.json())
                .then(data => {
                    if (data.current_weather) {
                        const temp = Math.round(data.current_weather.temperature);
                        const weatherCode = data.current_weather.weathercode;
                        const icon = getWeatherIcon(weatherCode);
                        const iconEl = document.getElementById("weather-icon-' . $calId . '");
                        const tempEl = document.getElementById("weather-temp-' . $calId . '");
                        if (iconEl) iconEl.textContent = icon;
                        if (tempEl) tempEl.innerHTML = temp + "&deg;";
                    }
                })
                .catch(err => console.log("Weather error:", err));
        }
    }
    
    // WMO Weather interpretation codes
    function getWeatherIcon(code) {
        const icons = {
            0: "☀️",   // Clear sky
            1: "🌤️",   // Mainly clear
            2: "⛅",   // Partly cloudy
            3: "☁️",   // Overcast
            45: "🌫️",  // Fog
            48: "🌫️",  // Depositing rime fog
            51: "🌦️",  // Light drizzle
            53: "🌦️",  // Moderate drizzle
            55: "🌧️",  // Dense drizzle
            61: "🌧️",  // Slight rain
            63: "🌧️",  // Moderate rain
            65: "⛈️",  // Heavy rain
            71: "🌨️",  // Slight snow
            73: "🌨️",  // Moderate snow
            75: "❄️",  // Heavy snow
            77: "🌨️",  // Snow grains
            80: "🌦️",  // Slight rain showers
            81: "🌧️",  // Moderate rain showers
            82: "⛈️",  // Violent rain showers
            85: "🌨️",  // Slight snow showers
            86: "❄️",  // Heavy snow showers
            95: "⛈️",  // Thunderstorm
            96: "⛈️",  // Thunderstorm with slight hail
            99: "⛈️"   // Thunderstorm with heavy hail
        };
        return icons[code] || "🌤️";
    }
    
    // Update weather immediately and every 10 minutes
    updateWeather();
    setInterval(updateWeather, 600000);
    
    // CPU load history for 4-second rolling average
    const cpuHistory = [];
    const CPU_HISTORY_SIZE = 2; // 2 samples × 2 seconds = 4 seconds
    
    // Store latest system stats for tooltips
    let latestStats = {
        load: {"1min": 0, "5min": 0, "15min": 0},
        uptime: "",
        memory_details: {},
        top_processes: []
    };
    
    // Tooltip functions
    window["showTooltip_' . $calId . '"] = function(color) {
        const tooltip = document.getElementById("tooltip-" + color + "-' . $calId . '");
        if (!tooltip) {
            console.log("Tooltip element not found for color:", color);
            return;
        }
        
        console.log("Showing tooltip for:", color, "latestStats:", latestStats);
        
        let content = "";
        
        if (color === "green") {
            // Green bar: Load averages and uptime
            content = "<div class=\"tooltip-title\">CPU Load Average</div>";
            content += "<div>1 min: " + (latestStats.load["1min"] || "N/A") + "</div>";
            content += "<div>5 min: " + (latestStats.load["5min"] || "N/A") + "</div>";
            content += "<div>15 min: " + (latestStats.load["15min"] || "N/A") + "</div>";
            if (latestStats.uptime) {
                content += "<div style=\"margin-top:3px; padding-top:2px; border-top:1px solid rgba(0,204,7,0.3);\">Uptime: " + latestStats.uptime + "</div>";
            }
            tooltip.style.borderColor = "#00cc07";
            tooltip.style.color = "#00cc07";
        } else if (color === "purple") {
            // Purple bar: Load averages (short-term) and top processes
            content = "<div class=\"tooltip-title\">CPU Load (Short-term)</div>";
            content += "<div>1 min: " + (latestStats.load["1min"] || "N/A") + "</div>";
            content += "<div>5 min: " + (latestStats.load["5min"] || "N/A") + "</div>";
            if (latestStats.top_processes && latestStats.top_processes.length > 0) {
                content += "<div style=\"margin-top:3px; padding-top:2px; border-top:1px solid rgba(155,89,182,0.3);\" class=\"tooltip-title\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.borderColor = "#9b59b6";
            tooltip.style.color = "#9b59b6";
        } else if (color === "orange") {
            // Orange bar: Memory details and top processes
            content = "<div class=\"tooltip-title\">Memory Usage</div>";
            if (latestStats.memory_details && latestStats.memory_details.total) {
                content += "<div>Total: " + latestStats.memory_details.total + "</div>";
                content += "<div>Used: " + latestStats.memory_details.used + "</div>";
                content += "<div>Available: " + latestStats.memory_details.available + "</div>";
                if (latestStats.memory_details.cached) {
                    content += "<div>Cached: " + latestStats.memory_details.cached + "</div>";
                }
            } else {
                content += "<div>Loading...</div>";
            }
            if (latestStats.top_processes && latestStats.top_processes.length > 0) {
                content += "<div style=\"margin-top:3px; padding-top:2px; border-top:1px solid rgba(255,140,0,0.3);\" class=\"tooltip-title\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.borderColor = "#ff9800";
            tooltip.style.color = "#ff9800";
        }
        
        console.log("Tooltip content:", content);
        tooltip.innerHTML = content;
        tooltip.style.display = "block";
        
        // Position tooltip using fixed positioning above the bar
        const bar = tooltip.parentElement;
        const barRect = bar.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        // Center horizontally on the bar
        const left = barRect.left + (barRect.width / 2) - (tooltipRect.width / 2);
        // Position above the bar with 8px gap
        const top = barRect.top - tooltipRect.height - 8;
        
        tooltip.style.left = left + "px";
        tooltip.style.top = top + "px";
    };
    
    window["hideTooltip_' . $calId . '"] = function(color) {
        const tooltip = document.getElementById("tooltip-" + color + "-' . $calId . '");
        if (tooltip) {
            tooltip.style.display = "none";
        }
    };
    
    // Update CPU and memory bars every 2 seconds
    function updateSystemStats() {
        // Fetch real system stats from server
        fetch("' . DOKU_BASE . 'lib/plugins/calendar/get_system_stats.php")
            .then(response => response.json())
            .then(data => {
                console.log("System stats received:", data);
                
                // Store data for tooltips
                latestStats = {
                    load: data.load || {"1min": 0, "5min": 0, "15min": 0},
                    uptime: data.uptime || "",
                    memory_details: data.memory_details || {},
                    top_processes: data.top_processes || []
                };
                
                console.log("latestStats updated to:", latestStats);
                
                // Update green bar (5-minute average) - updates live now!
                const greenBar = document.getElementById("cpu-5min-' . $calId . '");
                if (greenBar) {
                    greenBar.style.width = Math.min(100, data.cpu_5min) + "%";
                }
                
                // Add current CPU to history for purple bar
                cpuHistory.push(data.cpu);
                if (cpuHistory.length > CPU_HISTORY_SIZE) {
                    cpuHistory.shift(); // Remove oldest
                }
                
                // Calculate 5-second average for CPU
                const cpuAverage = cpuHistory.reduce((sum, val) => sum + val, 0) / cpuHistory.length;
                
                // Update CPU bar (purple) with 5-second average
                const cpuBar = document.getElementById("cpu-realtime-' . $calId . '");
                if (cpuBar) {
                    cpuBar.style.width = Math.min(100, cpuAverage) + "%";
                }
                
                // Update memory bar (orange) with real data
                const memBar = document.getElementById("mem-realtime-' . $calId . '");
                if (memBar) {
                    memBar.style.width = Math.min(100, data.memory) + "%";
                }
            })
            .catch(error => {
                console.log("System stats error:", error);
                // Fallback to client-side estimates on error
                const cpuFallback = Math.random() * 100;
                cpuHistory.push(cpuFallback);
                if (cpuHistory.length > CPU_HISTORY_SIZE) {
                    cpuHistory.shift();
                }
                const cpuAverage = cpuHistory.reduce((sum, val) => sum + val, 0) / cpuHistory.length;
                
                const greenBar = document.getElementById("cpu-5min-' . $calId . '");
                if (greenBar) greenBar.style.width = Math.min(100, cpuFallback) + "%";
                
                const cpuBar = document.getElementById("cpu-realtime-' . $calId . '");
                if (cpuBar) cpuBar.style.width = Math.min(100, cpuAverage) + "%";
                
                let memoryUsage = 0;
                if (performance.memory) {
                    memoryUsage = (performance.memory.usedJSHeapSize / performance.memory.jsHeapSizeLimit) * 100;
                } else {
                    memoryUsage = Math.random() * 100;
                }
                const memBar = document.getElementById("mem-realtime-' . $calId . '");
                if (memBar) memBar.style.width = Math.min(100, memoryUsage) + "%";
            });
    }
    
    // Update immediately and then every 2 seconds
    updateSystemStats();
    setInterval(updateSystemStats, 2000);
})();
</script>';
        }
        
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
            $todayStr = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            
            foreach ($allEvents as $dateKey => $dayEvents) {
                $dateObj = new DateTime($dateKey);
                $displayDate = $dateObj->format('D, M j');
                
                // Check if this date is today or tomorrow or past
                // Enable highlighting for sidebar mode AND range modes (day, week, month)
                $enableHighlighting = $sidebar || !empty($range);
                $isToday = $enableHighlighting && ($dateKey === $todayStr);
                $isTomorrow = $enableHighlighting && ($dateKey === $tomorrow);
                $isPast = $dateKey < $todayStr;
                
                foreach ($dayEvents as $event) {
                    // Check if this is a task and if it's completed
                    $isTask = !empty($event['isTask']);
                    $completed = !empty($event['completed']);
                    
                    // ALWAYS skip completed tasks UNLESS showchecked is explicitly set
                    if (!$showchecked && $isTask && $completed) {
                        continue;
                    }
                    
                    // Skip past events that are NOT tasks (only show past due tasks from the past)
                    if ($isPast && !$isTask) {
                        continue;
                    }
                    
                    // Determine if task is past due (past date, is task, not completed)
                    $isPastDue = $isPast && $isTask && !$completed;
                    
                    // Line 1: Header (Title, Time, Date, Namespace)
                    $todayClass = $isToday ? ' eventlist-simple-today' : '';
                    $tomorrowClass = $isTomorrow ? ' eventlist-simple-tomorrow' : '';
                    $pastDueClass = $isPastDue ? ' eventlist-simple-pastdue' : '';
                    $html .= '<div class="eventlist-simple-item' . $todayClass . $tomorrowClass . $pastDueClass . '">';
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
                    
                    // Badge: PAST DUE, TODAY, or nothing
                    if ($isPastDue) {
                        $html .= ' <span class="eventlist-simple-pastdue-badge">PAST DUE</span>';
                    } elseif ($isToday) {
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
        
        // 1. TITLE
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">📝 Title</label>';
        $html .= '<input type="text" id="event-title-' . $calId . '" name="title" required class="input-sleek input-compact" placeholder="Event or task title...">';
        $html .= '</div>';
        
        // 1.5 NAMESPACE SELECTOR (Searchable with fuzzy matching)
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">📁 Namespace</label>';
        
        // Hidden field to store actual selected namespace
        $html .= '<input type="hidden" id="event-namespace-' . $calId . '" name="namespace" value="">';
        
        // Searchable input
        $html .= '<div class="namespace-search-wrapper">';
        $html .= '<input type="text" id="event-namespace-search-' . $calId . '" class="input-sleek input-compact namespace-search-input" placeholder="Type to search or leave empty for default..." autocomplete="off">';
        $html .= '<div class="namespace-dropdown" id="event-namespace-dropdown-' . $calId . '" style="display:none;"></div>';
        $html .= '</div>';
        
        // Store namespaces as JSON for JavaScript
        $allNamespaces = $this->getAllNamespaces();
        $html .= '<script type="application/json" id="namespaces-data-' . $calId . '">' . json_encode($allNamespaces) . '</script>';
        
        $html .= '</div>';
        
        // 2. DESCRIPTION
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label">📄 Description</label>';
        $html .= '<textarea id="event-desc-' . $calId . '" name="description" rows="1" class="input-sleek textarea-sleek textarea-compact" placeholder="Optional details..."></textarea>';
        $html .= '</div>';
        
        // 3. START DATE - END DATE (inline)
        $html .= '<div class="form-row-group">';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">📅 Start Date</label>';
        $html .= '<input type="date" id="event-date-' . $calId . '" name="date" required class="input-sleek input-date input-compact">';
        $html .= '</div>';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">🏁 End Date</label>';
        $html .= '<input type="date" id="event-end-date-' . $calId . '" name="endDate" class="input-sleek input-date input-compact" placeholder="Optional">';
        $html .= '</div>';
        
        $html .= '</div>'; // End row
        
        // 4. IS REPEATING CHECKBOX
        $html .= '<div class="form-field form-field-checkbox form-field-checkbox-compact">';
        $html .= '<label class="checkbox-label checkbox-label-compact">';
        $html .= '<input type="checkbox" id="event-recurring-' . $calId . '" name="isRecurring" class="recurring-toggle" onchange="toggleRecurringOptions(\'' . $calId . '\')">';
        $html .= '<span>🔄 Repeating Event</span>';
        $html .= '</label>';
        $html .= '</div>';
        
        // Recurring options (shown when checkbox is checked)
        $html .= '<div id="recurring-options-' . $calId . '" class="recurring-options" style="display:none;">';
        
        $html .= '<div class="form-row-group">';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">Repeat Every</label>';
        $html .= '<select id="event-recurrence-type-' . $calId . '" name="recurrenceType" class="input-sleek input-compact">';
        $html .= '<option value="daily">Daily</option>';
        $html .= '<option value="weekly">Weekly</option>';
        $html .= '<option value="monthly">Monthly</option>';
        $html .= '<option value="yearly">Yearly</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">Repeat Until</label>';
        $html .= '<input type="date" id="event-recurrence-end-' . $calId . '" name="recurrenceEnd" class="input-sleek input-date input-compact" placeholder="Optional">';
        $html .= '</div>';
        
        $html .= '</div>'; // End row
        $html .= '</div>'; // End recurring options
        
        // 5. TIME (Start & End) - COLOR (inline)
        $html .= '<div class="form-row-group">';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">🕐 Start Time</label>';
        $html .= '<select id="event-time-' . $calId . '" name="time" class="input-sleek input-compact" onchange="updateEndTimeOptions(\'' . $calId . '\')">';
        $html .= '<option value="">All day</option>';
        
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
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">🕐 End Time</label>';
        $html .= '<select id="event-end-time-' . $calId . '" name="endTime" class="input-sleek input-compact">';
        $html .= '<option value="">Same as start</option>';
        
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
        
        $html .= '</div>'; // End row
        
        // Color field (new row)
        $html .= '<div class="form-row-group">';
        
        $html .= '<div class="form-field form-field-full">';
        $html .= '<label class="field-label-compact">🎨 Color</label>';
        $html .= '<div class="color-picker-wrapper">';
        $html .= '<select id="event-color-' . $calId . '" name="color" class="input-sleek input-compact color-select" onchange="updateCustomColorPicker(\'' . $calId . '\')">';
        $html .= '<option value="#3498db" style="background:#3498db;color:white">🔵 Blue</option>';
        $html .= '<option value="#2ecc71" style="background:#2ecc71;color:white">🟢 Green</option>';
        $html .= '<option value="#e74c3c" style="background:#e74c3c;color:white">🔴 Red</option>';
        $html .= '<option value="#f39c12" style="background:#f39c12;color:white">🟠 Orange</option>';
        $html .= '<option value="#9b59b6" style="background:#9b59b6;color:white">🟣 Purple</option>';
        $html .= '<option value="#e91e63" style="background:#e91e63;color:white">🔴 Pink</option>';
        $html .= '<option value="#1abc9c" style="background:#1abc9c;color:white">🟢 Teal</option>';
        $html .= '<option value="custom">🎨 Custom...</option>';
        $html .= '</select>';
        $html .= '<input type="color" id="event-color-custom-' . $calId . '" class="color-picker-input color-picker-compact" value="#3498db" onchange="updateColorFromPicker(\'' . $calId . '\')">';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>'; // End row
        
        // Task checkbox
        $html .= '<div class="form-field form-field-checkbox form-field-checkbox-compact">';
        $html .= '<label class="checkbox-label checkbox-label-compact">';
        $html .= '<input type="checkbox" id="event-is-task-' . $calId . '" name="isTask" class="task-toggle">';
        $html .= '<span>📋 This is a task (can be checked off)</span>';
        $html .= '</label>';
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
    
    private function getAllNamespaces() {
        $dataDir = DOKU_INC . 'data/meta/';
        $namespaces = [];
        
        // Scan for namespaces that have calendar data
        $this->scanForCalendarNamespaces($dataDir, '', $namespaces);
        
        // Sort alphabetically
        sort($namespaces);
        
        return $namespaces;
    }
    
    private function scanForCalendarNamespaces($dir, $baseNamespace, &$namespaces) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . $item;
            if (is_dir($path)) {
                // Check if this directory has a calendar subdirectory with data
                $calendarDir = $path . '/calendar/';
                if (is_dir($calendarDir)) {
                    // Check if there are any JSON files in the calendar directory
                    $jsonFiles = glob($calendarDir . '*.json');
                    if (!empty($jsonFiles)) {
                        // This namespace has calendar data
                        $namespace = $baseNamespace ? $baseNamespace . ':' . $item : $item;
                        $namespaces[] = $namespace;
                    }
                }
                
                // Recurse into subdirectories
                $namespace = $baseNamespace ? $baseNamespace . ':' . $item : $item;
                $this->scanForCalendarNamespaces($path . '/', $namespace, $namespaces);
            }
        }
    }
    
    /**
     * Render new sidebar widget - Week at a glance itinerary (200px wide)
     */
    private function renderSidebarWidget($events, $namespace, $calId) {
        if (empty($events)) {
            return '<div style="width:200px; padding:12px; text-align:center; color:#999; font-size:11px;">No events this week</div>';
        }
        
        // Get important namespaces from config
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $importantNsList = ['important']; // default
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (isset($config['important_namespaces']) && !empty($config['important_namespaces'])) {
                $importantNsList = array_map('trim', explode(',', $config['important_namespaces']));
            }
        }
        
        // Calculate date ranges
        $todayStr = date('Y-m-d');
        $tomorrowStr = date('Y-m-d', strtotime('+1 day'));
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        
        // Group events by category
        $todayEvents = [];
        $tomorrowEvents = [];
        $importantEvents = [];
        $weekEvents = []; // For week grid
        
        // Process all events
        foreach ($events as $dateKey => $dayEvents) {
            // Skip events before this week
            if ($dateKey < $weekStart) continue;
            
            // Initialize week grid day if in current week
            if ($dateKey >= $weekStart && $dateKey <= $weekEnd) {
                if (!isset($weekEvents[$dateKey])) {
                    $weekEvents[$dateKey] = [];
                }
            }
            
            foreach ($dayEvents as $event) {
                // Add to week grid if in week range
                if ($dateKey >= $weekStart && $dateKey <= $weekEnd) {
                    // Pre-render DokuWiki syntax to HTML for JavaScript display
                    $eventWithHtml = $event;
                    if (isset($event['title'])) {
                        $eventWithHtml['title_html'] = $this->renderDokuWikiToHtml($event['title']);
                    }
                    if (isset($event['description'])) {
                        $eventWithHtml['description_html'] = $this->renderDokuWikiToHtml($event['description']);
                    }
                    $weekEvents[$dateKey][] = $eventWithHtml;
                }
                
                // Categorize for detailed sections
                if ($dateKey === $todayStr) {
                    $todayEvents[] = array_merge($event, ['date' => $dateKey]);
                } elseif ($dateKey === $tomorrowStr) {
                    $tomorrowEvents[] = array_merge($event, ['date' => $dateKey]);
                } else {
                    // Check if this is an important namespace
                    $eventNs = isset($event['namespace']) ? $event['namespace'] : '';
                    $isImportant = false;
                    foreach ($importantNsList as $impNs) {
                        if ($eventNs === $impNs || strpos($eventNs, $impNs . ':') === 0) {
                            $isImportant = true;
                            break;
                        }
                    }
                    
                    // Important events: this week but not today/tomorrow
                    if ($isImportant && $dateKey >= $weekStart && $dateKey <= $weekEnd) {
                        $importantEvents[] = array_merge($event, ['date' => $dateKey]);
                    }
                }
            }
        }
        
        // Start building HTML - Dynamic width with default font
        $html = '<div class="sidebar-widget sidebar-matrix" style="width:100%; max-width:100%; box-sizing:border-box; font-family:system-ui, sans-serif; background:#242424; border:2px solid #00cc07; border-radius:4px; overflow:hidden; box-shadow:0 0 10px rgba(0, 204, 7, 0.3);">';
        
        // Sanitize calId for use in JavaScript variable names (remove dashes)
        $jsCalId = str_replace('-', '_', $calId);
        
        // CRITICAL: Add ALL JavaScript FIRST before any HTML that uses it
        $html .= '<script>
(function() {
    // Shared state for system stats and tooltips
    const sharedState_' . $jsCalId . ' = {
        latestStats: {
            load: {"1min": 0, "5min": 0, "15min": 0},
            uptime: "",
            memory_details: {},
            top_processes: []
        },
        cpuHistory: [],
        CPU_HISTORY_SIZE: 2
    };
    
    // Tooltip functions - MUST be defined before HTML uses them
    window["showTooltip_' . $jsCalId . '"] = function(color) {
        const tooltip = document.getElementById("tooltip-" + color + "-' . $calId . '");
        if (!tooltip) {
            console.log("Tooltip element not found for color:", color);
            return;
        }
        
        const latestStats = sharedState_' . $jsCalId . '.latestStats;
        let content = "";
        
        if (color === "green") {
            content = "<div class=\\"tooltip-title\\">CPU Load Average</div>";
            content += "<div>1 min: " + (latestStats.load["1min"] || "N/A") + "</div>";
            content += "<div>5 min: " + (latestStats.load["5min"] || "N/A") + "</div>";
            content += "<div>15 min: " + (latestStats.load["15min"] || "N/A") + "</div>";
            if (latestStats.uptime) {
                content += "<div style=\\"margin-top:3px; padding-top:2px; border-top:1px solid rgba(0,204,7,0.3);\\">Uptime: " + latestStats.uptime + "</div>";
            }
            tooltip.style.borderColor = "#00cc07";
            tooltip.style.color = "#00cc07";
        } else if (color === "purple") {
            content = "<div class=\\"tooltip-title\\">CPU Load (Short-term)</div>";
            content += "<div>1 min: " + (latestStats.load["1min"] || "N/A") + "</div>";
            content += "<div>5 min: " + (latestStats.load["5min"] || "N/A") + "</div>";
            if (latestStats.top_processes && latestStats.top_processes.length > 0) {
                content += "<div style=\\"margin-top:3px; padding-top:2px; border-top:1px solid rgba(155,89,182,0.3);\\" class=\\"tooltip-title\\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.borderColor = "#9b59b6";
            tooltip.style.color = "#9b59b6";
        } else if (color === "orange") {
            content = "<div class=\\"tooltip-title\\">Memory Usage</div>";
            if (latestStats.memory_details && latestStats.memory_details.total) {
                content += "<div>Total: " + latestStats.memory_details.total + "</div>";
                content += "<div>Used: " + latestStats.memory_details.used + "</div>";
                content += "<div>Available: " + latestStats.memory_details.available + "</div>";
                if (latestStats.memory_details.cached) {
                    content += "<div>Cached: " + latestStats.memory_details.cached + "</div>";
                }
            } else {
                content += "<div>Loading...</div>";
            }
            if (latestStats.top_processes && latestStats.top_processes.length > 0) {
                content += "<div style=\\"margin-top:3px; padding-top:2px; border-top:1px solid rgba(255,140,0,0.3);\\" class=\\"tooltip-title\\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.borderColor = "#ff9800";
            tooltip.style.color = "#ff9800";
        }
        
        tooltip.innerHTML = content;
        tooltip.style.display = "block";
        
        const bar = tooltip.parentElement;
        const barRect = bar.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        const left = barRect.left + (barRect.width / 2) - (tooltipRect.width / 2);
        const top = barRect.top - tooltipRect.height - 8;
        
        tooltip.style.left = left + "px";
        tooltip.style.top = top + "px";
    };
    
    window["hideTooltip_' . $jsCalId . '"] = function(color) {
        const tooltip = document.getElementById("tooltip-" + color + "-' . $calId . '");
        if (tooltip) {
            tooltip.style.display = "none";
        }
    };
    
    // Update clock every second
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, "0");
        const seconds = String(now.getSeconds()).padStart(2, "0");
        const ampm = hours >= 12 ? "PM" : "AM";
        hours = hours % 12 || 12;
        const timeStr = hours + ":" + minutes + ":" + seconds + " " + ampm;
        const clockEl = document.getElementById("clock-' . $calId . '");
        if (clockEl) clockEl.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    
    // Weather update function
    function updateWeather() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                
                fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&temperature_unit=fahrenheit`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.current_weather) {
                            const temp = Math.round(data.current_weather.temperature);
                            const weatherCode = data.current_weather.weathercode;
                            const icon = getWeatherIcon(weatherCode);
                            const iconEl = document.getElementById("weather-icon-' . $calId . '");
                            const tempEl = document.getElementById("weather-temp-' . $calId . '");
                            if (iconEl) iconEl.textContent = icon;
                            if (tempEl) tempEl.innerHTML = temp + "&deg;";
                        }
                    })
                    .catch(error => console.log("Weather fetch error:", error));
            }, function(error) {
                // If geolocation fails, use default location (Irvine, CA)
                fetch("https://api.open-meteo.com/v1/forecast?latitude=33.6846&longitude=-117.8265&current_weather=true&temperature_unit=fahrenheit")
                    .then(response => response.json())
                    .then(data => {
                        if (data.current_weather) {
                            const temp = Math.round(data.current_weather.temperature);
                            const weatherCode = data.current_weather.weathercode;
                            const icon = getWeatherIcon(weatherCode);
                            const iconEl = document.getElementById("weather-icon-' . $calId . '");
                            const tempEl = document.getElementById("weather-temp-' . $calId . '");
                            if (iconEl) iconEl.textContent = icon;
                            if (tempEl) tempEl.innerHTML = temp + "&deg;";
                        }
                    })
                    .catch(err => console.log("Weather error:", err));
            });
        } else {
            // No geolocation, use default (Irvine, CA)
            fetch("https://api.open-meteo.com/v1/forecast?latitude=33.6846&longitude=-117.8265&current_weather=true&temperature_unit=fahrenheit")
                .then(response => response.json())
                .then(data => {
                    if (data.current_weather) {
                        const temp = Math.round(data.current_weather.temperature);
                        const weatherCode = data.current_weather.weathercode;
                        const icon = getWeatherIcon(weatherCode);
                        const iconEl = document.getElementById("weather-icon-' . $calId . '");
                        const tempEl = document.getElementById("weather-temp-' . $calId . '");
                        if (iconEl) iconEl.textContent = icon;
                        if (tempEl) tempEl.innerHTML = temp + "&deg;";
                    }
                })
                .catch(err => console.log("Weather error:", err));
        }
    }
    
    function getWeatherIcon(code) {
        const icons = {
            0: "☀️", 1: "🌤️", 2: "⛅", 3: "☁️",
            45: "🌫️", 48: "🌫️", 51: "🌦️", 53: "🌦️", 55: "🌧️",
            61: "🌧️", 63: "🌧️", 65: "⛈️", 71: "🌨️", 73: "🌨️",
            75: "❄️", 77: "🌨️", 80: "🌦️", 81: "🌧️", 82: "⛈️",
            85: "🌨️", 86: "❄️", 95: "⛈️", 96: "⛈️", 99: "⛈️"
        };
        return icons[code] || "🌤️";
    }
    
    // Update weather immediately and every 10 minutes
    updateWeather();
    setInterval(updateWeather, 600000);
    
    // Update system stats and tooltips data
    function updateSystemStats() {
        fetch("' . DOKU_BASE . 'lib/plugins/calendar/get_system_stats.php")
            .then(response => response.json())
            .then(data => {
                sharedState_' . $jsCalId . '.latestStats = {
                    load: data.load || {"1min": 0, "5min": 0, "15min": 0},
                    uptime: data.uptime || "",
                    memory_details: data.memory_details || {},
                    top_processes: data.top_processes || []
                };
                
                const greenBar = document.getElementById("cpu-5min-' . $calId . '");
                if (greenBar) {
                    greenBar.style.width = Math.min(100, data.cpu_5min) + "%";
                }
                
                sharedState_' . $jsCalId . '.cpuHistory.push(data.cpu);
                if (sharedState_' . $jsCalId . '.cpuHistory.length > sharedState_' . $jsCalId . '.CPU_HISTORY_SIZE) {
                    sharedState_' . $jsCalId . '.cpuHistory.shift();
                }
                
                const cpuAverage = sharedState_' . $jsCalId . '.cpuHistory.reduce((sum, val) => sum + val, 0) / sharedState_' . $jsCalId . '.cpuHistory.length;
                
                const cpuBar = document.getElementById("cpu-realtime-' . $calId . '");
                if (cpuBar) {
                    cpuBar.style.width = Math.min(100, cpuAverage) + "%";
                }
                
                const memBar = document.getElementById("mem-realtime-' . $calId . '");
                if (memBar) {
                    memBar.style.width = Math.min(100, data.memory) + "%";
                }
            })
            .catch(error => {
                console.log("System stats error:", error);
            });
    }
    
    updateSystemStats();
    setInterval(updateSystemStats, 2000);
})();
</script>';
        
        // NOW add the header HTML (after JavaScript is defined)
        $todayDate = new DateTime();
        $displayDate = $todayDate->format('D, M j, Y');
        $currentTime = $todayDate->format('g:i:s A');
        
        $html .= '<div class="eventlist-today-header">';
        $html .= '<span class="eventlist-today-clock" id="clock-' . $calId . '">' . $currentTime . '</span>';
        $html .= '<div class="eventlist-bottom-info">';
        $html .= '<span class="eventlist-weather"><span id="weather-icon-' . $calId . '">🌤️</span> <span id="weather-temp-' . $calId . '">--°</span></span>';
        $html .= '<span class="eventlist-today-date">' . $displayDate . '</span>';
        $html .= '</div>';
        
        // Three CPU/Memory bars (all update live)
        $html .= '<div class="eventlist-stats-container">';
        
        // 5-minute load average (green, updates every 2 seconds)
        $html .= '<div class="eventlist-cpu-bar" onmouseover="showTooltip_' . $jsCalId . '(\'green\')" onmouseout="hideTooltip_' . $jsCalId . '(\'green\')">';
        $html .= '<div class="eventlist-cpu-fill" id="cpu-5min-' . $calId . '" style="width: 0%;"></div>';
        $html .= '<div class="system-tooltip" id="tooltip-green-' . $calId . '" style="display:none;"></div>';
        $html .= '</div>';
        
        // Real-time CPU (purple, updates with 5-sec average)
        $html .= '<div class="eventlist-cpu-bar eventlist-cpu-realtime" onmouseover="showTooltip_' . $jsCalId . '(\'purple\')" onmouseout="hideTooltip_' . $jsCalId . '(\'purple\')">';
        $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-purple" id="cpu-realtime-' . $calId . '" style="width: 0%;"></div>';
        $html .= '<div class="system-tooltip" id="tooltip-purple-' . $calId . '" style="display:none;"></div>';
        $html .= '</div>';
        
        // Real-time Memory (orange, updates)
        $html .= '<div class="eventlist-cpu-bar eventlist-mem-realtime" onmouseover="showTooltip_' . $jsCalId . '(\'orange\')" onmouseout="hideTooltip_' . $jsCalId . '(\'orange\')">';
        $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-orange" id="mem-realtime-' . $calId . '" style="width: 0%;"></div>';
        $html .= '<div class="system-tooltip" id="tooltip-orange-' . $calId . '" style="display:none;"></div>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        // Get today's date for default event date
        $todayStr = date('Y-m-d');
        
        // Thin dark green "Add Event" bar between header and week grid (zero margin, smaller text, text positioned higher)
        $html .= '<div style="background:#006400; padding:0; margin:0; height:12px; line-height:10px; text-align:center; cursor:pointer; border-top:1px solid rgba(0, 100, 0, 0.3); border-bottom:1px solid rgba(0, 100, 0, 0.3); box-shadow:0 0 8px rgba(0, 100, 0, 0.4); transition:all 0.2s;" onclick="openAddEvent(\'' . $calId . '\', \'' . $namespace . '\', \'' . $todayStr . '\');" onmouseover="this.style.background=\'#004d00\'; this.style.boxShadow=\'0 0 12px rgba(0, 100, 0, 0.6)\';" onmouseout="this.style.background=\'#006400\'; this.style.boxShadow=\'0 0 8px rgba(0, 100, 0, 0.4)\';">';
        $html .= '<span style="color:#00ff00; font-size:8px; font-weight:700; letter-spacing:0.4px; font-family:system-ui, sans-serif; text-shadow:0 0 3px rgba(0, 255, 0, 0.5); position:relative; top:-1px;">+ ADD EVENT</span>';
        $html .= '</div>';
        
        // Week grid (7 cells)
        $html .= $this->renderWeekGrid($weekEvents, $weekStart);
        
        // Today section (orange)
        if (!empty($todayEvents)) {
            $html .= $this->renderSidebarSection('Today', $todayEvents, '#ff9800', $calId);
        }
        
        // Tomorrow section (green)
        if (!empty($tomorrowEvents)) {
            $html .= $this->renderSidebarSection('Tomorrow', $tomorrowEvents, '#4caf50', $calId);
        }
        
        // Important events section (purple)
        if (!empty($importantEvents)) {
            $html .= $this->renderSidebarSection('Important Events', $importantEvents, '#9b59b6', $calId);
        }
        
        $html .= '</div>';
        
        // Add event dialog for sidebar widget
        $html .= $this->renderEventDialog($calId, $namespace);
        
        return $html;
    }
    
    /**
     * Render compact week grid (7 cells with event bars) - Matrix themed with clickable days
     */
    private function renderWeekGrid($weekEvents, $weekStart) {
        // Generate unique ID for this calendar instance - sanitize for JavaScript
        $calId = 'cal_' . substr(md5($weekStart . microtime()), 0, 8);
        $jsCalId = str_replace('-', '_', $calId);  // Sanitize for JS variable names
        
        $html = '<div style="display:grid; grid-template-columns:repeat(7, 1fr); gap:1px; background:#1a3d1a; border-bottom:2px solid #00cc07;">';
        
        $dayNames = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
        $today = date('Y-m-d');
        
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($weekStart . ' +' . $i . ' days'));
            $dayNum = date('j', strtotime($date));
            $isToday = $date === $today;
            
            $events = isset($weekEvents[$date]) ? $weekEvents[$date] : [];
            $eventCount = count($events);
            
            $bgColor = $isToday ? '#2a4d2a' : '#242424';
            $textColor = $isToday ? '#00ff00' : '#00cc07';
            $fontWeight = $isToday ? '700' : '500';
            $textShadow = $isToday ? 'text-shadow:0 0 6px rgba(0, 255, 0, 0.6);' : 'text-shadow:0 0 4px rgba(0, 204, 7, 0.4);';
            
            $hasEvents = $eventCount > 0;
            $clickableStyle = $hasEvents ? 'cursor:pointer;' : '';
            $clickHandler = $hasEvents ? ' onclick="showDayEvents_' . $jsCalId . '(\'' . $date . '\')"' : '';
            
            $html .= '<div style="background:' . $bgColor . '; padding:4px 2px; text-align:center; min-height:45px; position:relative; border:1px solid rgba(0, 204, 7, 0.2); ' . $clickableStyle . '" ' . $clickHandler . '>';
            
            // Day letter
            $html .= '<div style="font-size:9px; color:#00cc07; font-weight:500; font-family:system-ui, sans-serif; ' . $textShadow . '">' . $dayNames[$i] . '</div>';
            
            // Day number
            $html .= '<div style="font-size:12px; color:' . $textColor . '; font-weight:' . $fontWeight . '; margin:2px 0; font-family:system-ui, sans-serif; ' . $textShadow . '">' . $dayNum . '</div>';
            
            // Event bars (max 3 visible) with glow effect
            if ($eventCount > 0) {
                $showCount = min($eventCount, 3);
                for ($j = 0; $j < $showCount; $j++) {
                    $event = $events[$j];
                    $color = isset($event['color']) ? $event['color'] : '#00cc07';
                    $html .= '<div style="height:2px; background:' . htmlspecialchars($color) . '; margin:1px 0; border-radius:1px; box-shadow:0 0 3px ' . htmlspecialchars($color) . ';"></div>';
                }
                
                // Show "+N more" if more than 3
                if ($eventCount > 3) {
                    $html .= '<div style="font-size:7px; color:#00cc07; margin-top:1px; font-family:system-ui, sans-serif;">+' . ($eventCount - 3) . '</div>';
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Add container for selected day events display (with unique ID)
        $html .= '<div id="selected-day-events-' . $calId . '" style="display:none; margin:8px 4px; border-left:3px solid #3498db; box-shadow:0 0 5px rgba(0, 204, 7, 0.2);">';
        $html .= '<div style="background:#3498db; color:#000; padding:4px 6px; font-size:9px; font-weight:700; letter-spacing:0.3px; font-family:system-ui, sans-serif; box-shadow:0 0 8px #3498db; display:flex; justify-content:space-between; align-items:center;">';
        $html .= '<span id="selected-day-title-' . $calId . '"></span>';
        $html .= '<span onclick="document.getElementById(\'selected-day-events-' . $calId . '\').style.display=\'none\';" style="cursor:pointer; font-size:12px; padding:0 4px; font-weight:700;">✕</span>';
        $html .= '</div>';
        $html .= '<div id="selected-day-content-' . $calId . '" style="padding:4px 0; background:rgba(36, 36, 36, 0.5);"></div>';
        $html .= '</div>';
        
        // Add JavaScript for day selection with event data
        $html .= '<script>';
        // Sanitize calId for JavaScript variable names
        $jsCalId = str_replace('-', '_', $calId);
        $html .= 'window.weekEventsData_' . $jsCalId . ' = ' . json_encode($weekEvents) . ';';
        $html .= '
        window.showDayEvents_' . $jsCalId . ' = function(dateKey) {
            const eventsData = window.weekEventsData_' . $jsCalId . ';
            const container = document.getElementById("selected-day-events-' . $calId . '");
            const title = document.getElementById("selected-day-title-' . $calId . '");
            const content = document.getElementById("selected-day-content-' . $calId . '");
            
            if (!eventsData[dateKey] || eventsData[dateKey].length === 0) return;
            
            // Format date for display
            const dateObj = new Date(dateKey + "T00:00:00");
            const dayName = dateObj.toLocaleDateString("en-US", { weekday: "long" });
            const monthDay = dateObj.toLocaleDateString("en-US", { month: "short", day: "numeric" });
            title.textContent = dayName + ", " + monthDay;
            
            // Clear content
            content.innerHTML = "";
            
            // Sort events by time (all-day events first, then timed events chronologically)
            const sortedEvents = [...eventsData[dateKey]].sort((a, b) => {
                // All-day events (no time) go to the beginning
                if (!a.time && !b.time) return 0;
                if (!a.time) return -1;  // a is all-day, comes first
                if (!b.time) return 1;   // b is all-day, comes first
                
                // Compare times (format: "HH:MM")
                const timeA = a.time.split(":").map(Number);
                const timeB = b.time.split(":").map(Number);
                const minutesA = timeA[0] * 60 + timeA[1];
                const minutesB = timeB[0] * 60 + timeB[1];
                
                return minutesA - minutesB;
            });
            
            // Build events HTML with single color bar (event color only)
            sortedEvents.forEach(event => {
                const eventColor = event.color || "#00cc07";
                
                const eventDiv = document.createElement("div");
                eventDiv.style.cssText = "padding:4px 6px; border-bottom:1px solid rgba(0, 204, 7, 0.2); font-size:10px; display:flex; align-items:stretch; gap:6px; background:rgba(36, 36, 36, 0.3); min-height:20px;";
                
                let eventHTML = "";
                
                // Event assigned color bar (single bar on left)
                eventHTML += "<div style=\\"width:3px; align-self:stretch; background:" + eventColor + "; border-radius:1px; flex-shrink:0; box-shadow:0 0 3px " + eventColor + ";\\"></div>";
                
                // Content wrapper
                eventHTML += "<div style=\\"flex:1; min-width:0; display:flex; justify-content:space-between; align-items:start; gap:4px;\\">";
                
                // Left side: event details
                eventHTML += "<div style=\\"flex:1; min-width:0;\\">";
                eventHTML += "<div style=\\"font-weight:600; color:#00cc07; word-wrap:break-word; font-family:system-ui, sans-serif; text-shadow:0 0 3px rgba(0, 204, 7, 0.4);\\">";
                
                // Time
                if (event.time) {
                    const timeParts = event.time.split(":");
                    let hours = parseInt(timeParts[0]);
                    const minutes = timeParts[1];
                    const ampm = hours >= 12 ? "PM" : "AM";
                    hours = hours % 12 || 12;
                    eventHTML += "<span style=\\"color:#00dd00; font-weight:500; font-size:9px;\\">" + hours + ":" + minutes + " " + ampm + "</span> ";
                }
                
                // Title - use HTML version if available
                const titleHTML = event.title_html || event.title || "Untitled";
                eventHTML += titleHTML;
                eventHTML += "</div>";
                
                // Description if present - use HTML version
                if (event.description_html || event.description) {
                    const descHTML = event.description_html || event.description;
                    eventHTML += "<div style=\\"font-size:9px; color:#00aa00; margin-top:2px;\\">" + descHTML + "</div>";
                }
                
                eventHTML += "</div>"; // Close event details
                
                // Right side: conflict badge (if present)
                if (event.conflict) {
                    eventHTML += "<div style=\\"flex-shrink:0; color:#ff9800; font-size:10px; margin-top:2px; opacity:0.8;\\" title=\\"Time conflict detected\\">⚠</div>";
                }
                
                eventHTML += "</div>"; // Close content wrapper
                
                eventDiv.innerHTML = eventHTML;
                content.appendChild(eventDiv);
            });
            
            container.style.display = "block";
        };
        ';
        $html .= '</script>';
        
        return $html;
    }
    
    /**
     * Render a sidebar section (Today/Tomorrow/Important) - Matrix themed with colored borders
     */
    private function renderSidebarSection($title, $events, $accentColor, $calId) {
        // Keep the original accent colors for borders
        $borderColor = $accentColor;
        
        // Show date for Important Events section
        $showDate = ($title === 'Important Events');
        
        $html = '<div style="border-left:3px solid ' . $borderColor . '; margin:8px 4px; box-shadow:0 0 5px rgba(0, 204, 7, 0.2);">';
        
        // Section header with accent color background - smaller, not all caps
        $html .= '<div style="background:' . $accentColor . '; color:#000; padding:4px 6px; font-size:9px; font-weight:700; letter-spacing:0.3px; font-family:system-ui, sans-serif; box-shadow:0 0 8px ' . $accentColor . ';">';
        $html .= htmlspecialchars($title);
        $html .= '</div>';
        
        // Events
        $html .= '<div style="padding:4px 0; background:rgba(36, 36, 36, 0.5);">';
        
        foreach ($events as $event) {
            $html .= $this->renderSidebarEvent($event, $calId, $showDate, $accentColor);
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render individual event in sidebar - Matrix themed with dual color bars
     */
    private function renderSidebarEvent($event, $calId, $showDate = false, $sectionColor = '#00cc07') {
        $title = isset($event['title']) ? htmlspecialchars($event['title']) : 'Untitled';
        $time = isset($event['time']) ? $event['time'] : '';
        $endTime = isset($event['endTime']) ? $event['endTime'] : '';
        $eventColor = isset($event['color']) ? htmlspecialchars($event['color']) : '#00cc07';
        $date = isset($event['date']) ? $event['date'] : '';
        $isTask = isset($event['isTask']) && $event['isTask'];
        $completed = isset($event['completed']) && $event['completed'];
        
        // Check for conflicts
        $hasConflict = isset($event['conflicts']) && !empty($event['conflicts']);
        
        $html = '<div style="padding:4px 6px; border-bottom:1px solid rgba(0, 204, 7, 0.2); font-size:10px; display:flex; align-items:stretch; gap:6px; background:rgba(36, 36, 36, 0.3); min-height:20px;">';
        
        // Event's assigned color bar (single bar on the left)
        $html .= '<div style="width:3px; align-self:stretch; background:' . $eventColor . '; border-radius:1px; flex-shrink:0; box-shadow:0 0 3px ' . $eventColor . ';"></div>';
        
        // Content
        $html .= '<div style="flex:1; min-width:0;">';
        
        // Time + title
        $html .= '<div style="font-weight:600; color:#00cc07; word-wrap:break-word; font-family:system-ui, sans-serif; text-shadow:0 0 3px rgba(0, 204, 7, 0.4);">';
        
        if ($time) {
            $displayTime = $this->formatTimeDisplay($time, $endTime);
            $html .= '<span style="color:#00dd00; font-weight:500; font-size:9px;">' . htmlspecialchars($displayTime) . '</span> ';
        }
        
        // Task checkbox
        if ($isTask) {
            $checkIcon = $completed ? '☑' : '☐';
            $html .= '<span style="font-size:11px; color:#00ff00;">' . $checkIcon . '</span> ';
        }
        
        $html .= htmlspecialchars($title);
        
        // Conflict badge
        if ($hasConflict) {
            $conflictCount = count($event['conflicts']);
            $html .= ' <span style="background:#ff0000; color:#000; padding:1px 3px; border-radius:2px; font-size:8px; font-weight:700; box-shadow:0 0 4px #ff0000;">⚠ ' . $conflictCount . '</span>';
        }
        
        $html .= '</div>';
        
        // Date display BELOW event name for Important events
        if ($showDate && $date) {
            $dateObj = new DateTime($date);
            $displayDate = $dateObj->format('D, M j'); // e.g., "Mon, Feb 10"
            $html .= '<div style="font-size:8px; color:#00aa00; font-weight:500; margin-top:2px; text-shadow:0 0 2px rgba(0, 170, 0, 0.3);">' . htmlspecialchars($displayDate) . '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Format time display (12-hour format with optional end time)
     */
    private function formatTimeDisplay($startTime, $endTime = '') {
        // Convert start time
        list($hour, $minute) = explode(':', $startTime);
        $hour = (int)$hour;
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $displayHour = $hour % 12;
        if ($displayHour === 0) $displayHour = 12;
        
        $display = $displayHour . ':' . $minute . ' ' . $ampm;
        
        // Add end time if provided
        if ($endTime && $endTime !== '') {
            list($endHour, $endMinute) = explode(':', $endTime);
            $endHour = (int)$endHour;
            $endAmpm = $endHour >= 12 ? 'PM' : 'AM';
            $endDisplayHour = $endHour % 12;
            if ($endDisplayHour === 0) $endDisplayHour = 12;
            
            $display .= '-' . $endDisplayHour . ':' . $endMinute . ' ' . $endAmpm;
        }
        
        return $display;
    }
    
    /**
     * Render DokuWiki syntax to HTML
     * Converts **bold**, //italic//, [[links]], etc. to HTML
     */
    private function renderDokuWikiToHtml($text) {
        if (empty($text)) return '';
        
        // Use DokuWiki's parser to render the text
        $instructions = p_get_instructions($text);
        
        // Render instructions to XHTML
        $xhtml = p_render('xhtml', $instructions, $info);
        
        // Remove surrounding <p> tags if present (we're rendering inline)
        $xhtml = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($xhtml));
        
        return $xhtml;
    }
    
    // Keep old scanForNamespaces for backward compatibility (not used anymore)
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
}

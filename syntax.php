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
            'range' => '',
            'static' => false,
            'title' => '',
            'noprint' => false,
            'theme' => '',
            'locked' => false  // Will be set true if month/year specified
        );
        
        // Track if user explicitly set month or year
        $userSetMonth = false;
        $userSetYear = false;
        
        if (trim($match)) {
            // Parse parameters, handling quoted strings properly
            // Match: key="value with spaces" OR key=value OR standalone_flag
            preg_match_all('/(\w+)=["\']([^"\']+)["\']|(\w+)=(\S+)|(\w+)/', trim($match), $matches, PREG_SET_ORDER);
            
            foreach ($matches as $m) {
                if (!empty($m[1]) && isset($m[2])) {
                    // key="quoted value"
                    $key = $m[1];
                    $value = $m[2];
                    $params[$key] = $value;
                    if ($key === 'month') $userSetMonth = true;
                    if ($key === 'year') $userSetYear = true;
                } elseif (!empty($m[3]) && isset($m[4])) {
                    // key=unquoted_value
                    $key = $m[3];
                    $value = $m[4];
                    $params[$key] = $value;
                    if ($key === 'month') $userSetMonth = true;
                    if ($key === 'year') $userSetYear = true;
                } elseif (!empty($m[5])) {
                    // standalone flag
                    $params[$m[5]] = true;
                }
            }
        }
        
        // If user explicitly set month or year, lock navigation
        if ($userSetMonth || $userSetYear) {
            $params['locked'] = true;
        }
        
        return $params;
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode !== 'xhtml') return false;
        
        // Disable caching - theme can change via admin without page edit
        $renderer->nocache();
        
        if ($data['type'] === 'eventlist') {
            $html = $this->renderStandaloneEventList($data);
        } elseif ($data['type'] === 'eventpanel') {
            $html = $this->renderEventPanelOnly($data);
        } elseif ($data['static']) {
            $html = $this->renderStaticCalendar($data);
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
        
        // Get theme - prefer inline theme= parameter, fall back to admin default
        $theme = !empty($data['theme']) ? $data['theme'] : $this->getSidebarTheme();
        $themeStyles = $this->getSidebarThemeStyles($theme);
        $themeClass = 'calendar-theme-' . $theme;
        
        // Determine button text color: professional uses white, others use bg color
        $btnTextColor = ($theme === 'professional') ? '#fff' : $themeStyles['bg'];
        
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
        
        // Get important namespaces from config for highlighting
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $importantNsList = ['important']; // default
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (isset($config['important_namespaces']) && !empty($config['important_namespaces'])) {
                $importantNsList = array_map('trim', explode(',', $config['important_namespaces']));
            }
        }
        
        // Container - all styling via CSS variables
        $html = '<div class="calendar-compact-container ' . $themeClass . '" id="' . $calId . '" data-namespace="' . htmlspecialchars($namespace) . '" data-original-namespace="' . htmlspecialchars($namespace) . '" data-year="' . $year . '" data-month="' . $month . '" data-theme="' . $theme . '" data-theme-styles="' . htmlspecialchars(json_encode($themeStyles)) . '" data-important-namespaces="' . htmlspecialchars(json_encode($importantNsList)) . '">';
        
        // Inject CSS variables for this calendar instance - all theming flows from here
        $html .= '<style>
        #' . $calId . ' {
            --background-site: ' . $themeStyles['bg'] . ';
            --background-alt: ' . $themeStyles['cell_bg'] . ';
            --background-header: ' . $themeStyles['header_bg'] . ';
            --text-primary: ' . $themeStyles['text_primary'] . ';
            --text-dim: ' . $themeStyles['text_dim'] . ';
            --text-bright: ' . $themeStyles['text_bright'] . ';
            --border-color: ' . $themeStyles['grid_border'] . ';
            --border-main: ' . $themeStyles['border'] . ';
            --cell-bg: ' . $themeStyles['cell_bg'] . ';
            --cell-today-bg: ' . $themeStyles['cell_today_bg'] . ';
            --shadow-color: ' . $themeStyles['shadow'] . ';
            --header-border: ' . $themeStyles['header_border'] . ';
            --header-shadow: ' . $themeStyles['header_shadow'] . ';
            --grid-bg: ' . $themeStyles['grid_bg'] . ';
            --btn-text: ' . $btnTextColor . ';
            --pastdue-color: ' . $themeStyles['pastdue_color'] . ';
            --pastdue-bg: ' . $themeStyles['pastdue_bg'] . ';
            --pastdue-bg-strong: ' . $themeStyles['pastdue_bg_strong'] . ';
            --pastdue-bg-light: ' . $themeStyles['pastdue_bg_light'] . ';
            --tomorrow-bg: ' . $themeStyles['tomorrow_bg'] . ';
            --tomorrow-bg-strong: ' . $themeStyles['tomorrow_bg_strong'] . ';
            --tomorrow-bg-light: ' . $themeStyles['tomorrow_bg_light'] . ';
        }
        #event-search-' . $calId . '::placeholder { color: ' . $themeStyles['text_dim'] . '; opacity: 1; }
        #event-search-' . $calId . '::-webkit-input-placeholder { color: ' . $themeStyles['text_dim'] . '; opacity: 1; }
        #event-search-' . $calId . '::-moz-placeholder { color: ' . $themeStyles['text_dim'] . '; opacity: 1; }
        #event-search-' . $calId . ':-ms-input-placeholder { color: ' . $themeStyles['text_dim'] . '; opacity: 1; }
        </style>';
        
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
        
        // Calendar grid - day name headers as a separate div (avoids Firefox th height issues)
        $html .= '<div class="calendar-day-headers">';
        $html .= '<span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>';
        $html .= '</div>';
        $html .= '<table class="calendar-compact-grid">';
        $html .= '<tbody>';
        
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
                    
                    $dayNumClass = $isToday ? 'day-num day-num-today' : 'day-num';
                    $html .= '<span class="' . $dayNumClass . '">' . $currentDay . '</span>';
                    
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
                            
                            // Check if this event is from an important namespace
                            $evtNs = isset($evt['namespace']) ? $evt['namespace'] : '';
                            if (!$evtNs && isset($evt['_namespace'])) {
                                $evtNs = $evt['_namespace'];
                            }
                            $isImportantEvent = false;
                            foreach ($importantNsList as $impNs) {
                                if ($evtNs === $impNs || strpos($evtNs, $impNs . ':') === 0) {
                                    $isImportantEvent = true;
                                    break;
                                }
                            }
                            
                            $barClass = empty($eventTime) ? 'event-bar-no-time' : 'event-bar-timed';
                            
                            // Add classes for multi-day spanning
                            if (!$isFirstDay) $barClass .= ' event-bar-continues';
                            if (!$isLastDay) $barClass .= ' event-bar-continuing';
                            if ($isImportantEvent) {
                                $barClass .= ' event-bar-important';
                                if ($isFirstDay) {
                                    $barClass .= ' event-bar-has-star';
                                }
                            }
                            
                            $titlePrefix = $isImportantEvent ? '⭐ ' : '';
                            
                            $html .= '<span class="event-bar ' . $barClass . '" ';
                            $html .= 'style="background: ' . $eventColor . ';" ';
                            $html .= 'title="' . $titlePrefix . $eventTitle . ($eventTime ? ' @ ' . $eventTime : '') . '" ';
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
        $html .= '<button class="event-search-mode-inline" id="search-mode-' . $calId . '" onclick="toggleSearchMode(\'' . $calId . '\', \'' . $namespace . '\')" title="Search this month only">📅</button>';
        $html .= '</div>';
        
        $html .= '<button class="add-event-compact" onclick="openAddEvent(\'' . $calId . '\', \'' . $namespace . '\')">+ Add</button>';
        $html .= '</div>';
        
        $html .= '<div class="event-list-compact" id="eventlist-' . $calId . '">';
        $html .= $this->renderEventListContent($events, $calId, $namespace, $themeStyles);
        $html .= '</div>';
        
        $html .= '</div>'; // End calendar-right
        
        // Event dialog
        $html .= $this->renderEventDialog($calId, $namespace, $theme);
        
        // Month/Year picker dialog (at container level for proper overlay)
        $html .= $this->renderMonthPicker($calId, $year, $month, $namespace, $theme, $themeStyles);
        
        $html .= '</div>'; // End container
        
        return $html;
    }
    
    /**
     * Render a static/read-only calendar for presentation and printing
     * No edit buttons, clean layout, print-friendly itinerary
     */
    private function renderStaticCalendar($data) {
        $year = (int)$data['year'];
        $month = (int)$data['month'];
        $namespace = isset($data['namespace']) ? $data['namespace'] : '';
        $customTitle = isset($data['title']) ? $data['title'] : '';
        $noprint = isset($data['noprint']) && $data['noprint'];
        $locked = isset($data['locked']) && $data['locked'];
        $themeOverride = isset($data['theme']) ? $data['theme'] : '';
        
        // Generate unique ID for this static calendar
        $calId = 'static-cal-' . substr(md5($namespace . $year . $month . uniqid()), 0, 8);
        
        // Get theme settings
        if ($themeOverride && in_array($themeOverride, ['matrix', 'pink', 'purple', 'professional', 'wiki', 'dark', 'light'])) {
            $theme = $themeOverride;
        } else {
            $theme = $this->getSidebarTheme();
        }
        $themeStyles = $this->getSidebarThemeStyles($theme);
        
        // Get important namespaces
        $importantNsList = $this->getImportantNamespaces();
        
        // Load events - check for multi-namespace or wildcard
        $isMultiNamespace = !empty($namespace) && (strpos($namespace, ';') !== false || strpos($namespace, '*') !== false);
        if ($isMultiNamespace) {
            $events = $this->loadEventsMultiNamespace($namespace, $year, $month);
        } else {
            $events = $this->loadEvents($namespace, $year, $month);
        }
        
        // Month info
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $startDayOfWeek = (int)date('w', $firstDay);
        $monthName = date('F', $firstDay);
        
        // Display title - custom or default month/year
        $displayTitle = $customTitle ? $customTitle : $monthName . ' ' . $year;
        
        // Theme class for styling
        $themeClass = 'static-theme-' . $theme;
        
        // Build HTML
        $html = '<div class="calendar-static ' . $themeClass . '" id="' . $calId . '" data-year="' . $year . '" data-month="' . $month . '" data-namespace="' . hsc($namespace) . '" data-locked="' . ($locked ? '1' : '0') . '">';
        
        // Screen view: Calendar Grid
        $html .= '<div class="static-screen-view">';
        
        // Header with navigation (hide nav buttons if locked)
        $html .= '<div class="static-header">';
        if (!$locked) {
            $html .= '<button class="static-nav-btn" onclick="navStaticCalendar(\'' . $calId . '\', -1)" title="' . $this->getLang('previous_month') . '">◀</button>';
        }
        $html .= '<h2 class="static-month-title">' . hsc($displayTitle) . '</h2>';
        if (!$locked) {
            $html .= '<button class="static-nav-btn" onclick="navStaticCalendar(\'' . $calId . '\', 1)" title="' . $this->getLang('next_month') . '">▶</button>';
        }
        if (!$noprint) {
            $html .= '<button class="static-print-btn" onclick="printStaticCalendar(\'' . $calId . '\')" title="' . $this->getLang('print_calendar') . '">🖨️</button>';
        }
        $html .= '</div>';
        
        // Calendar grid
        $html .= '<table class="static-calendar-grid">';
        $html .= '<thead><tr>';
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ($dayNames as $day) {
            $html .= '<th>' . $day . '</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        $dayCount = 1;
        $totalCells = $startDayOfWeek + $daysInMonth;
        $rows = ceil($totalCells / 7);
        
        for ($row = 0; $row < $rows; $row++) {
            $html .= '<tr>';
            for ($col = 0; $col < 7; $col++) {
                $cellNum = $row * 7 + $col;
                
                if ($cellNum < $startDayOfWeek || $dayCount > $daysInMonth) {
                    $html .= '<td class="static-day-empty"></td>';
                } else {
                    $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $dayCount);
                    $dayEvents = isset($events[$dateKey]) ? $events[$dateKey] : [];
                    $isToday = ($dateKey === date('Y-m-d'));
                    $isWeekend = ($col === 0 || $col === 6);
                    
                    $cellClass = 'static-day';
                    if ($isToday) $cellClass .= ' static-day-today';
                    if ($isWeekend) $cellClass .= ' static-day-weekend';
                    if (!empty($dayEvents)) $cellClass .= ' static-day-has-events';
                    
                    $html .= '<td class="' . $cellClass . '">';
                    $html .= '<div class="static-day-number">' . $dayCount . '</div>';
                    
                    if (!empty($dayEvents)) {
                        $html .= '<div class="static-day-events">';
                        foreach ($dayEvents as $event) {
                            $color = isset($event['color']) ? $event['color'] : '#3498db';
                            $title = hsc($event['title']);
                            $time = isset($event['time']) && $event['time'] ? $event['time'] : '';
                            $desc = isset($event['description']) ? $event['description'] : '';
                            $eventNs = isset($event['namespace']) ? $event['namespace'] : $namespace;
                            
                            // Check if important
                            $isImportant = false;
                            foreach ($importantNsList as $impNs) {
                                if ($eventNs === $impNs || strpos($eventNs, $impNs . ':') === 0) {
                                    $isImportant = true;
                                    break;
                                }
                            }
                            
                            // Build tooltip - plain text with basic formatting indicators
                            $tooltipText = $event['title'];
                            if ($time) {
                                $tooltipText .= "\n🕐 " . $this->formatTime12Hour($time);
                                if (isset($event['endTime']) && $event['endTime']) {
                                    $tooltipText .= ' - ' . $this->formatTime12Hour($event['endTime']);
                                }
                            }
                            if ($desc) {
                                // Convert formatting to plain text equivalents
                                $plainDesc = $desc;
                                $plainDesc = preg_replace('/\*\*(.+?)\*\*/', '*$1*', $plainDesc);
                                $plainDesc = preg_replace('/__(.+?)__/', '*$1*', $plainDesc);
                                $plainDesc = preg_replace('/\/\/(.+?)\/\//', '_$1_', $plainDesc);
                                $plainDesc = preg_replace('/\[\[([^|\]]+?)(?:\|([^\]]+))?\]\]/', '$2 ($1)', $plainDesc);
                                $plainDesc = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $plainDesc);
                                $tooltipText .= "\n\n" . $plainDesc;
                            }
                            
                            $eventClass = 'static-event';
                            if ($isImportant) $eventClass .= ' static-event-important';
                            
                            $html .= '<div class="' . $eventClass . '" style="border-left-color: ' . $color . ';" title="' . hsc($tooltipText) . '">';
                            if ($isImportant) {
                                $html .= '<span class="static-event-star">⭐</span>';
                            }
                            if ($time) {
                                $html .= '<span class="static-event-time">' . $this->formatTime12Hour($time) . '</span> ';
                            }
                            $html .= '<span class="static-event-title">' . $title . '</span>';
                            $html .= '</div>';
                        }
                        $html .= '</div>';
                    }
                    
                    $html .= '</td>';
                    $dayCount++;
                }
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>'; // End screen view
        
        // Print view: Itinerary format (skip if noprint)
        if (!$noprint) {
            $html .= '<div class="static-print-view">';
            $html .= '<h2 class="static-print-title">' . hsc($displayTitle) . '</h2>';
            
            if (!empty($namespace)) {
                $html .= '<p class="static-print-namespace">' . $this->getLang('calendar_label') . ': ' . hsc($namespace) . '</p>';
            }
            
            // Collect all events sorted by date
            $allEvents = [];
        foreach ($events as $dateKey => $dayEvents) {
            foreach ($dayEvents as $event) {
                $event['_date'] = $dateKey;
                $allEvents[] = $event;
            }
        }
        
        // Sort by date, then time
        usort($allEvents, function($a, $b) {
            $dateCompare = strcmp($a['_date'], $b['_date']);
            if ($dateCompare !== 0) return $dateCompare;
            $timeA = isset($a['time']) ? $a['time'] : '99:99';
            $timeB = isset($b['time']) ? $b['time'] : '99:99';
            return strcmp($timeA, $timeB);
        });
        
        if (empty($allEvents)) {
            $html .= '<p class="static-print-empty">' . $this->getLang('no_events_scheduled') . '</p>';
        } else {
            $html .= '<table class="static-itinerary">';
            $html .= '<thead><tr><th>Date</th><th>Time</th><th>Event</th><th>Details</th></tr></thead>';
            $html .= '<tbody>';
            
            $lastDate = '';
            foreach ($allEvents as $event) {
                $dateKey = $event['_date'];
                $dateObj = new \DateTime($dateKey);
                $dateDisplay = $dateObj->format('D, M j');
                $eventNs = isset($event['namespace']) ? $event['namespace'] : $namespace;
                
                // Check if important
                $isImportant = false;
                foreach ($importantNsList as $impNs) {
                    if ($eventNs === $impNs || strpos($eventNs, $impNs . ':') === 0) {
                        $isImportant = true;
                        break;
                    }
                }
                
                $rowClass = $isImportant ? 'static-itinerary-important' : '';
                
                $html .= '<tr class="' . $rowClass . '">';
                
                // Only show date if different from previous row
                if ($dateKey !== $lastDate) {
                    $html .= '<td class="static-itinerary-date">' . $dateDisplay . '</td>';
                    $lastDate = $dateKey;
                } else {
                    $html .= '<td></td>';
                }
                
                // Time
                $time = isset($event['time']) && $event['time'] ? $this->formatTime12Hour($event['time']) : $this->getLang('all_day');
                if (isset($event['endTime']) && $event['endTime'] && isset($event['time']) && $event['time']) {
                    $time .= ' - ' . $this->formatTime12Hour($event['endTime']);
                }
                $html .= '<td class="static-itinerary-time">' . $time . '</td>';
                
                // Title with star for important
                $html .= '<td class="static-itinerary-title">';
                if ($isImportant) {
                    $html .= '⭐ ';
                }
                $html .= hsc($event['title']);
                $html .= '</td>';
                
                // Description - with formatting
                $desc = isset($event['description']) ? $this->renderDescription($event['description']) : '';
                $html .= '<td class="static-itinerary-desc">' . $desc . '</td>';
                
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        $html .= '</div>'; // End print view
        } // End noprint check
        
        $html .= '</div>'; // End container
        
        return $html;
    }
    
    /**
     * Format time to 12-hour format
     */
    private function formatTime12Hour($time) {
        if (!$time) return '';
        $parts = explode(':', $time);
        $hour = (int)$parts[0];
        $minute = isset($parts[1]) ? $parts[1] : '00';
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour == 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);
        return $hour12 . ':' . $minute . ' ' . $ampm;
    }
    
    /**
     * Get list of important namespaces from config
     */
    private function getImportantNamespaces() {
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $importantNsList = ['important']; // default
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (isset($config['important_namespaces']) && !empty($config['important_namespaces'])) {
                $importantNsList = array_map('trim', explode(',', $config['important_namespaces']));
            }
        }
        return $importantNsList;
    }
    
    private function renderEventListContent($events, $calId, $namespace, $themeStyles = null) {
        if (empty($events)) {
            return '<p class="no-events-msg">No events this month</p>';
        }
        
        // Default theme styles if not provided
        if ($themeStyles === null) {
            $theme = $this->getSidebarTheme();
            $themeStyles = $this->getSidebarThemeStyles($theme);
        } else {
            $theme = $this->getSidebarTheme();
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
                $renderedDescription = $this->renderDescription($description, $themeStyles);
                
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
                
                // Check if this is an important namespace event
                $eventNamespace = isset($event['namespace']) ? $event['namespace'] : '';
                if (!$eventNamespace && isset($event['_namespace'])) {
                    $eventNamespace = $event['_namespace'];
                }
                $isImportantNs = false;
                foreach ($importantNsList as $impNs) {
                    if ($eventNamespace === $impNs || strpos($eventNamespace, $impNs . ':') === 0) {
                        $isImportantNs = true;
                        break;
                    }
                }
                $importantClass = $isImportantNs ? ' event-important' : '';
                
                // For all themes: use CSS variables, only keep border-left-color as inline
                $pastClickHandler = ($isPast && !$isPastDue) ? ' onclick="togglePastEventExpand(this)"' : '';
                $eventHtml = '<div class="event-compact-item' . $completedClass . $pastClass . $pastDueClass . $importantClass . '" data-event-id="' . $eventId . '" data-date="' . $dateKey . '" style="border-left-color: ' . $color . ' !important;"' . $pastClickHandler . $firstFutureAttr . '>';
                $eventHtml .= '<div class="event-info">';
                
                $eventHtml .= '<div class="event-title-row">';
                // Add star for important namespace events
                if ($isImportantNs) {
                    $eventHtml .= '<span class="event-important-star" title="Important">⭐</span> ';
                }
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
                        $eventHtml .= ' <span class="event-pastdue-badge" style="background:' . $themeStyles['pastdue_color'] . ' !important; color:white !important; -webkit-text-fill-color:white !important;">' . 'PAST DUE</span>';
                    } elseif ($isToday) {
                        $eventHtml .= ' <span class="event-today-badge" style="background:' . $themeStyles['border'] . ' !important; color:' . $themeStyles['bg'] . ' !important; -webkit-text-fill-color:' . $themeStyles['bg'] . ' !important;">' . 'TODAY</span>';
                    }
                    // Add namespace badge - ALWAYS show if event has a namespace
                    $eventNamespace = isset($event['namespace']) ? $event['namespace'] : '';
                    if (!$eventNamespace && isset($event['_namespace'])) {
                        $eventNamespace = $event['_namespace']; // Fallback to _namespace for backward compatibility
                    }
                    // Show badge if namespace exists and is not empty
                    if ($eventNamespace && $eventNamespace !== '') {
                        $eventHtml .= ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' . $calId . '\', \'' . htmlspecialchars($eventNamespace) . '\')" style="cursor:pointer; background:' . $themeStyles['text_bright'] . ' !important; color:' . $themeStyles['bg'] . ' !important; -webkit-text-fill-color:' . $themeStyles['bg'] . ' !important;" title="Click to filter by this namespace">' . htmlspecialchars($eventNamespace) . '</span>';
                    }
                    
                    // Add conflict warning if event has time conflicts
                    if (isset($event['hasConflict']) && $event['hasConflict'] && isset($event['conflictsWith'])) {
                        $conflictList = [];
                        foreach ($event['conflictsWith'] as $conflict) {
                            $conflictText = $conflict['title'];
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
                        $conflictJson = base64_encode(json_encode($conflictList));
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
                        $eventHtml .= ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' . $calId . '\', \'' . htmlspecialchars($eventNamespace) . '\')" style="cursor:pointer; background:' . $themeStyles['text_bright'] . ' !important; color:' . $themeStyles['bg'] . ' !important; -webkit-text-fill-color:' . $themeStyles['bg'] . ' !important;" title="Click to filter by this namespace">' . htmlspecialchars($eventNamespace) . '</span>';
                    }
                    
                    // Add conflict warning if event has time conflicts
                    if (isset($event['hasConflict']) && $event['hasConflict'] && isset($event['conflictsWith'])) {
                        $conflictList = [];
                        foreach ($event['conflictsWith'] as $conflict) {
                            $conflictText = $conflict['title'];
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
                        $conflictJson = base64_encode(json_encode($conflictList));
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
        
        // Get theme - prefer inline theme= parameter, fall back to admin default
        $theme = !empty($data['theme']) ? $data['theme'] : $this->getSidebarTheme();        $themeStyles = $this->getSidebarThemeStyles($theme);
        
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
        
        // Determine button text color based on theme
        $btnTextColor = ($theme === 'professional') ? '#fff' : $themeStyles['bg'];
        
        // Get important namespaces from config for highlighting
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $importantNsList = ['important']; // default
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (isset($config['important_namespaces']) && !empty($config['important_namespaces'])) {
                $importantNsList = array_map('trim', explode(',', $config['important_namespaces']));
            }
        }
        
        $html = '<div class="event-panel-standalone" id="' . $calId . '" data-height="' . htmlspecialchars($height) . '" data-namespace="' . htmlspecialchars($namespace) . '" data-original-namespace="' . htmlspecialchars($namespace) . '" data-theme="' . $theme . '" data-theme-styles="' . htmlspecialchars(json_encode($themeStyles)) . '" data-important-namespaces="' . htmlspecialchars(json_encode($importantNsList)) . '">';
        
        // Inject CSS variables for this panel instance - same as main calendar
        $html .= '<style>
        #' . $calId . ' {
            --background-site: ' . $themeStyles['bg'] . ';
            --background-alt: ' . $themeStyles['cell_bg'] . ';
            --background-header: ' . $themeStyles['header_bg'] . ';
            --text-primary: ' . $themeStyles['text_primary'] . ';
            --text-dim: ' . $themeStyles['text_dim'] . ';
            --text-bright: ' . $themeStyles['text_bright'] . ';
            --border-color: ' . $themeStyles['grid_border'] . ';
            --border-main: ' . $themeStyles['border'] . ';
            --cell-bg: ' . $themeStyles['cell_bg'] . ';
            --cell-today-bg: ' . $themeStyles['cell_today_bg'] . ';
            --shadow-color: ' . $themeStyles['shadow'] . ';
            --header-border: ' . $themeStyles['header_border'] . ';
            --header-shadow: ' . $themeStyles['header_shadow'] . ';
            --grid-bg: ' . $themeStyles['grid_bg'] . ';
            --btn-text: ' . $btnTextColor . ';
            --pastdue-color: ' . $themeStyles['pastdue_color'] . ';
            --pastdue-bg: ' . $themeStyles['pastdue_bg'] . ';
            --pastdue-bg-strong: ' . $themeStyles['pastdue_bg_strong'] . ';
            --pastdue-bg-light: ' . $themeStyles['pastdue_bg_light'] . ';
            --tomorrow-bg: ' . $themeStyles['tomorrow_bg'] . ';
            --tomorrow-bg-strong: ' . $themeStyles['tomorrow_bg_strong'] . ';
            --tomorrow-bg-light: ' . $themeStyles['tomorrow_bg_light'] . ';
        }
        #event-search-' . $calId . '::placeholder { color: ' . $themeStyles['text_dim'] . '; opacity: 1; }
        </style>';
        
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
                    $html .= '<span class="panel-ns-badge" style="background:var(--cell-today-bg) !important; color:var(--text-bright) !important; -webkit-text-fill-color:var(--text-bright) !important;" title="' . htmlspecialchars($namespace) . '">' . htmlspecialchars($namespace) . '</span>';
                } else {
                    $namespaceList = array_map('trim', explode(';', $namespace));
                    $nsCount = count($namespaceList);
                    $html .= '<span class="panel-ns-badge" style="background:var(--cell-today-bg) !important; color:var(--text-bright) !important; -webkit-text-fill-color:var(--text-bright) !important;" title="' . htmlspecialchars(implode(', ', $namespaceList)) . '">' . $nsCount . ' NS</span>';
                }
            } else {
                $isFiltering = ($namespace !== '*' && strpos($namespace, '*') === false && strpos($namespace, ';') === false);
                if ($isFiltering) {
                    $html .= '<span class="panel-ns-badge filter-on" style="background:var(--text-bright) !important; color:var(--background-site) !important; -webkit-text-fill-color:var(--background-site) !important;" title="Filtering by ' . htmlspecialchars($namespace) . ' - click to clear" onclick="clearNamespaceFilterPanel(\'' . $calId . '\')">' . htmlspecialchars($namespace) . ' ✕</span>';
                } else {
                    $html .= '<span class="panel-ns-badge" style="background:var(--cell-today-bg) !important; color:var(--text-bright) !important; -webkit-text-fill-color:var(--text-bright) !important;" title="' . htmlspecialchars($namespace) . '">' . htmlspecialchars($namespace) . '</span>';
                }
            }
        }
        
        $html .= '<button class="panel-today-btn" onclick="jumpTodayPanel(\'' . $calId . '\', \'' . $namespace . '\')">Today</button>';
        $html .= '</div>';
        
        // Row 2: Search and add button
        $html .= '<div class="panel-header-row-2">';
        $html .= '<div class="panel-search-box">';
        $html .= '<input type="text" class="panel-search-input" id="event-search-' . $calId . '" placeholder="Search this month..." oninput="filterEvents(\'' . $calId . '\', this.value)">';
        $html .= '<button class="panel-search-clear" id="search-clear-' . $calId . '" onclick="clearEventSearch(\'' . $calId . '\')" style="display:none;">✕</button>';
        $html .= '<button class="panel-search-mode" id="search-mode-' . $calId . '" onclick="toggleSearchMode(\'' . $calId . '\', \'' . $namespace . '\')" title="Search this month only">📅</button>';
        $html .= '</div>';
        $html .= '<button class="panel-add-btn" onclick="openAddEventPanel(\'' . $calId . '\', \'' . $namespace . '\')">+ Add</button>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        $html .= '<div class="event-list-compact" id="eventlist-' . $calId . '" style="max-height: ' . htmlspecialchars($height) . ';">';
        $html .= $this->renderEventListContent($events, $calId, $namespace);
        $html .= '</div>';
        
        $html .= $this->renderEventDialog($calId, $namespace, $theme);
        
        // Month/Year picker for event panel
        $html .= $this->renderMonthPicker($calId, $year, $month, $namespace, $theme, $themeStyles);
        
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
            $weekStartDay = $this->getWeekStartDay(); // Get saved preference  
            
            if ($weekStartDay === 'monday') {
                // Monday start
                $weekStart = date('Y-m-d', strtotime('monday this week'));
                $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            } else {
                // Sunday start (default - US/Canada standard)
                $today = date('w'); // 0 (Sun) to 6 (Sat)
                if ($today == 0) {
                    // Today is Sunday
                    $weekStart = date('Y-m-d');
                } else {
                    // Monday-Saturday: go back to last Sunday
                    $weekStart = date('Y-m-d', strtotime('-' . $today . ' days'));
                }
                $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            }
            
            // Load events for the entire week PLUS tomorrow (if tomorrow is outside week)
            // PLUS next 2 weeks for Important events
            $start = new DateTime($weekStart);
            $end = new DateTime($weekEnd);
            
            // Check if we need to extend to include tomorrow
            $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
            if ($tomorrowDate > $weekEnd) {
                // Tomorrow is outside the week, extend end date to include it
                $end = new DateTime($tomorrowDate);
            }
            
            // Extend 2 weeks into the future for Important events
            $twoWeeksOut = date('Y-m-d', strtotime($weekEnd . ' +14 days'));
            $end = new DateTime($twoWeeksOut);
            
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
            $themeOverride = !empty($data['theme']) ? $data['theme'] : null;
            return $this->renderSidebarWidget($allEvents, $namespace, $calId, $themeOverride);
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
        $theme = !empty($data['theme']) ? $data['theme'] : $this->getSidebarTheme();
        $themeStyles = $this->getSidebarThemeStyles($theme);
        $isDark = in_array($theme, ['matrix', 'purple', 'pink']);
        $btnTextColor = ($theme === 'professional') ? '#fff' : $themeStyles['bg'];
        
        // Theme class for CSS targeting
        $themeClass = 'eventlist-theme-' . $theme;
        
        // Container styling - dark themes get border + glow, light themes get subtle border
        $containerStyle = 'background:' . $themeStyles['bg'] . ' !important;';
        if ($isDark) {
            $containerStyle .= ' border:2px solid ' . $themeStyles['border'] . ';';
            $containerStyle .= ' border-radius:4px;';
            $containerStyle .= ' box-shadow:0 0 10px ' . $themeStyles['shadow'] . ';';
        } else {
            $containerStyle .= ' border:1px solid ' . $themeStyles['grid_border'] . ';';
            $containerStyle .= ' border-radius:4px;';
        }
        
        $html = '<div class="eventlist-simple ' . $themeClass . '" id="' . $calId . '" style="' . $containerStyle . '" data-show-system-load="' . ($this->getShowSystemLoad() ? 'yes' : 'no') . '">';
        
        // Inject CSS variables for this eventlist instance
        $html .= '<style>
        #' . $calId . ' {
            --background-site: ' . $themeStyles['bg'] . ';
            --background-alt: ' . $themeStyles['cell_bg'] . ';
            --text-primary: ' . $themeStyles['text_primary'] . ';
            --text-dim: ' . $themeStyles['text_dim'] . ';
            --text-bright: ' . $themeStyles['text_bright'] . ';
            --border-color: ' . $themeStyles['grid_border'] . ';
            --border-main: ' . $themeStyles['border'] . ';
            --cell-bg: ' . $themeStyles['cell_bg'] . ';
            --cell-today-bg: ' . $themeStyles['cell_today_bg'] . ';
            --shadow-color: ' . $themeStyles['shadow'] . ';
            --grid-bg: ' . $themeStyles['grid_bg'] . ';
            --btn-text: ' . $btnTextColor . ';
            --pastdue-color: ' . $themeStyles['pastdue_color'] . ';
            --pastdue-bg: ' . $themeStyles['pastdue_bg'] . ';
            --pastdue-bg-strong: ' . $themeStyles['pastdue_bg_strong'] . ';
            --pastdue-bg-light: ' . $themeStyles['pastdue_bg_light'] . ';
            --tomorrow-bg: ' . $themeStyles['tomorrow_bg'] . ';
            --tomorrow-bg-strong: ' . $themeStyles['tomorrow_bg_strong'] . ';
            --tomorrow-bg-light: ' . $themeStyles['tomorrow_bg_light'] . ';
        }
        </style>';
        
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
            
            // Three CPU/Memory bars (all update live) - only if enabled
            $showSystemLoad = $this->getShowSystemLoad();
            if ($showSystemLoad) {
                $html .= '<div class="eventlist-stats-container">';
                
                // 5-minute load average (green, updates every 2 seconds)
                $html .= '<div class="eventlist-cpu-bar" style="background:' . $themeStyles['cell_today_bg'] . ' !important;" onmouseover="showTooltip_' . $calId . '(\'green\')" onmouseout="hideTooltip_' . $calId . '(\'green\')">';
                $html .= '<div class="eventlist-cpu-fill" id="cpu-5min-' . $calId . '" style="width: 0%; background:' . $themeStyles['text_bright'] . ' !important;"></div>';
                $html .= '<div class="system-tooltip" id="tooltip-green-' . $calId . '" style="display:none;"></div>';
                $html .= '</div>';
                
                // Real-time CPU (purple, updates with 5-sec average)
                $html .= '<div class="eventlist-cpu-bar eventlist-cpu-realtime" style="background:' . $themeStyles['cell_today_bg'] . ' !important;" onmouseover="showTooltip_' . $calId . '(\'purple\')" onmouseout="hideTooltip_' . $calId . '(\'purple\')">';
                $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-purple" id="cpu-realtime-' . $calId . '" style="width: 0%; background:' . $themeStyles['border'] . ' !important;"></div>';
                $html .= '<div class="system-tooltip" id="tooltip-purple-' . $calId . '" style="display:none;"></div>';
                $html .= '</div>';
                
                // Real-time Memory (orange, updates)
                $html .= '<div class="eventlist-cpu-bar eventlist-mem-realtime" style="background:' . $themeStyles['cell_today_bg'] . ' !important;" onmouseover="showTooltip_' . $calId . '(\'orange\')" onmouseout="hideTooltip_' . $calId . '(\'orange\')">';
                $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-orange" id="mem-realtime-' . $calId . '" style="width: 0%; background:' . $themeStyles['text_primary'] . ' !important;"></div>';
                $html .= '<div class="system-tooltip" id="tooltip-orange-' . $calId . '" style="display:none;"></div>';
                $html .= '</div>';
                
                $html .= '</div>';
            }
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
    
    // Fetch weather - uses default location, click weather to get local
    var userLocationGranted = false;
    var userLat = 38.5816;  // Sacramento default
    var userLon = -121.4944;
    
    function fetchWeatherData(lat, lon) {
        fetch("https://api.open-meteo.com/v1/forecast?latitude=" + lat + "&longitude=" + lon + "&current_weather=true&temperature_unit=fahrenheit")
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
    }
    
    function updateWeather() {
        fetchWeatherData(userLat, userLon);
    }
    
    // Allow user to click weather to get local weather (requires user gesture)
    function requestLocalWeather() {
        if (userLocationGranted) return; // Already have permission
        
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                userLat = position.coords.latitude;
                userLon = position.coords.longitude;
                userLocationGranted = true;
                fetchWeatherData(userLat, userLon);
            }, function(error) {
                console.log("Geolocation denied or unavailable, using default location");
            });
        }
    }
    
    // Add click handler to weather widget for local weather
    setTimeout(function() {
        var weatherEl = document.querySelector("#weather-icon-' . $calId . '");
        if (weatherEl) {
            weatherEl.style.cursor = "pointer";
            weatherEl.title = "Click for local weather";
            weatherEl.addEventListener("click", requestLocalWeather);
        }
    }, 100);
    
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
    
    // Check if system load bars are enabled
    const container = document.getElementById("' . $calId . '");
    const showSystemLoad = container && container.dataset.showSystemLoad !== "no";
    
    if (showSystemLoad) {
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
        
        
        let content = "";
        
        if (color === "green") {
            // Green bar: Load averages and uptime
            content = "<div class=\"tooltip-title\">CPU Load Average</div>";
            content += "<div>1 min: " + (latestStats.load["1min"] || "N/A") + "</div>";
            content += "<div>5 min: " + (latestStats.load["5min"] || "N/A") + "</div>";
            content += "<div>15 min: " + (latestStats.load["15min"] || "N/A") + "</div>";
            if (latestStats.uptime) {
                content += "<div style=\"margin-top:3px; padding-top:2px; border-top:1px solid ' . $themeStyles['text_bright'] . ';\">Uptime: " + latestStats.uptime + "</div>";
            }
            tooltip.style.setProperty("border-color", "' . $themeStyles['text_bright'] . '", "important");
            tooltip.style.setProperty("color", "' . $themeStyles['text_bright'] . '", "important");
            tooltip.style.setProperty("-webkit-text-fill-color", "' . $themeStyles['text_bright'] . '", "important");
        } else if (color === "purple") {
            // Purple bar: Load averages (short-term) and top processes
            content = "<div class=\"tooltip-title\">CPU Load (Short-term)</div>";
            content += "<div>1 min: " + (latestStats.load["1min"] || "N/A") + "</div>";
            content += "<div>5 min: " + (latestStats.load["5min"] || "N/A") + "</div>";
            if (latestStats.top_processes && latestStats.top_processes.length > 0) {
                content += "<div style=\"margin-top:3px; padding-top:2px; border-top:1px solid ' . $themeStyles['border'] . ';\" class=\"tooltip-title\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.setProperty("border-color", "' . $themeStyles['border'] . '", "important");
            tooltip.style.setProperty("color", "' . $themeStyles['border'] . '", "important");
            tooltip.style.setProperty("-webkit-text-fill-color", "' . $themeStyles['border'] . '", "important");
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
                content += "<div style=\"margin-top:3px; padding-top:2px; border-top:1px solid ' . $themeStyles['text_primary'] . ';\" class=\"tooltip-title\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.setProperty("border-color", "' . $themeStyles['text_primary'] . '", "important");
            tooltip.style.setProperty("color", "' . $themeStyles['text_primary'] . '", "important");
            tooltip.style.setProperty("-webkit-text-fill-color", "' . $themeStyles['text_primary'] . '", "important");
        }
        
        tooltip.innerHTML = content;
        tooltip.style.setProperty("display", "block");
        tooltip.style.setProperty("background", "' . $themeStyles['bg'] . '", "important");
        
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
                
                // Store data for tooltips
                latestStats = {
                    load: data.load || {"1min": 0, "5min": 0, "15min": 0},
                    uptime: data.uptime || "",
                    memory_details: data.memory_details || {},
                    top_processes: data.top_processes || []
                };
                
                
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
    } // End showSystemLoad check
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
                        $html .= ' <span class="eventlist-simple-pastdue-badge" style="background:' . $themeStyles['pastdue_color'] . ' !important; color:white !important; -webkit-text-fill-color:white !important;">PAST DUE</span>';
                    } elseif ($isToday) {
                        $html .= ' <span class="eventlist-simple-today-badge" style="background:' . $themeStyles['border'] . ' !important; color:' . $themeStyles['bg'] . ' !important; -webkit-text-fill-color:' . $themeStyles['bg'] . ' !important;">TODAY</span>';
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
    
    private function renderEventDialog($calId, $namespace, $theme = null) {
        // Get theme for dialog
        if ($theme === null) {
            $theme = $this->getSidebarTheme();
        }
        $themeStyles = $this->getSidebarThemeStyles($theme);
        
        $html = '<div class="event-dialog-compact" id="dialog-' . $calId . '" style="display:none;">';
        $html .= '<div class="dialog-overlay" onclick="closeEventDialog(\'' . $calId . '\')"></div>';
        
        // Draggable dialog with theme
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
        $html .= '<textarea id="event-desc-' . $calId . '" name="description" rows="2" class="input-sleek textarea-sleek textarea-compact" placeholder="Optional details..."></textarea>';
        $html .= '</div>';
        
        // 3. START DATE - END DATE (inline)
        $html .= '<div class="form-row-group">';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">📅 Start Date</label>';
        $html .= '<input type="date" id="event-date-' . $calId . '" name="date" required class="input-sleek input-date input-compact" onchange="updateEndTimeOptions(\'' . $calId . '\')">';
        $html .= '</div>';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">🏁 End Date</label>';
        $html .= '<input type="date" id="event-end-date-' . $calId . '" name="endDate" class="input-sleek input-date input-compact" placeholder="Optional" onchange="updateEndTimeOptions(\'' . $calId . '\')">';
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
        $html .= '<div id="recurring-options-' . $calId . '" class="recurring-options" style="display:none; border:1px solid var(--border-color, #333); border-radius:4px; padding:8px; margin:4px 0; background:var(--background-alt, rgba(0,0,0,0.2));">';
        
        // Row 1: Repeat every [N] [period]
        $html .= '<div class="form-row-group" style="margin-bottom:6px;">';
        
        $html .= '<div class="form-field" style="flex:0 0 auto; min-width:0;">';
        $html .= '<label class="field-label-compact">Repeat every</label>';
        $html .= '<input type="number" id="event-recurrence-interval-' . $calId . '" name="recurrenceInterval" class="input-sleek input-compact" value="1" min="1" max="99" style="width:50px;">';
        $html .= '</div>';
        
        $html .= '<div class="form-field" style="flex:1; min-width:0;">';
        $html .= '<label class="field-label-compact">&nbsp;</label>';
        $html .= '<select id="event-recurrence-type-' . $calId . '" name="recurrenceType" class="input-sleek input-compact" onchange="updateRecurrenceOptions(\'' . $calId . '\')">';
        $html .= '<option value="daily">Day(s)</option>';
        $html .= '<option value="weekly">Week(s)</option>';
        $html .= '<option value="monthly">Month(s)</option>';
        $html .= '<option value="yearly">Year(s)</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        $html .= '</div>'; // End row 1
        
        // Row 2: Weekly options - day of week checkboxes
        $html .= '<div id="weekly-options-' . $calId . '" class="weekly-options" style="display:none; margin-bottom:6px;">';
        $html .= '<label class="field-label-compact" style="display:block; margin-bottom:4px;">On these days:</label>';
        $html .= '<div style="display:flex; flex-wrap:wrap; gap:2px;">';
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ($dayNames as $idx => $day) {
            $html .= '<label style="display:inline-flex; align-items:center; padding:2px 6px; background:var(--cell-bg, #1a1a1a); border:1px solid var(--border-color, #333); border-radius:3px; cursor:pointer; font-size:10px;">';
            $html .= '<input type="checkbox" name="weekDays[]" value="' . $idx . '" style="margin-right:3px; width:12px; height:12px;">';
            $html .= '<span>' . $day . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        $html .= '</div>'; // End weekly options
        
        // Row 3: Monthly options - day of month OR ordinal weekday
        $html .= '<div id="monthly-options-' . $calId . '" class="monthly-options" style="display:none; margin-bottom:6px;">';
        $html .= '<label class="field-label-compact" style="display:block; margin-bottom:4px;">Repeat on:</label>';
        
        // Radio: Day of month vs Ordinal weekday
        $html .= '<div style="margin-bottom:6px;">';
        $html .= '<label style="display:inline-flex; align-items:center; margin-right:12px; cursor:pointer; font-size:11px;">';
        $html .= '<input type="radio" name="monthlyType" value="dayOfMonth" checked onchange="updateMonthlyType(\'' . $calId . '\')" style="margin-right:4px;">';
        $html .= 'Day of month';
        $html .= '</label>';
        $html .= '<label style="display:inline-flex; align-items:center; cursor:pointer; font-size:11px;">';
        $html .= '<input type="radio" name="monthlyType" value="ordinalWeekday" onchange="updateMonthlyType(\'' . $calId . '\')" style="margin-right:4px;">';
        $html .= 'Weekday pattern';
        $html .= '</label>';
        $html .= '</div>';
        
        // Day of month input (shown by default)
        $html .= '<div id="monthly-day-' . $calId . '" style="display:flex; align-items:center; gap:6px;">';
        $html .= '<span style="font-size:11px;">Day</span>';
        $html .= '<input type="number" id="event-month-day-' . $calId . '" name="monthDay" class="input-sleek input-compact" value="1" min="1" max="31" style="width:50px;">';
        $html .= '<span style="font-size:10px; color:var(--text-dim, #666);">of each month</span>';
        $html .= '</div>';
        
        // Ordinal weekday (hidden by default)
        $html .= '<div id="monthly-ordinal-' . $calId . '" style="display:none;">';
        $html .= '<div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">';
        $html .= '<select id="event-ordinal-' . $calId . '" name="ordinalWeek" class="input-sleek input-compact" style="width:auto;">';
        $html .= '<option value="1">First</option>';
        $html .= '<option value="2">Second</option>';
        $html .= '<option value="3">Third</option>';
        $html .= '<option value="4">Fourth</option>';
        $html .= '<option value="5">Fifth</option>';
        $html .= '<option value="-1">Last</option>';
        $html .= '</select>';
        $html .= '<select id="event-ordinal-day-' . $calId . '" name="ordinalDay" class="input-sleek input-compact" style="width:auto;">';
        $html .= '<option value="0">Sunday</option>';
        $html .= '<option value="1">Monday</option>';
        $html .= '<option value="2">Tuesday</option>';
        $html .= '<option value="3">Wednesday</option>';
        $html .= '<option value="4">Thursday</option>';
        $html .= '<option value="5">Friday</option>';
        $html .= '<option value="6">Saturday</option>';
        $html .= '</select>';
        $html .= '<span style="font-size:10px; color:var(--text-dim, #666);">of each month</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>'; // End monthly options
        
        // Row 4: End date
        $html .= '<div class="form-row-group">';
        $html .= '<div class="form-field">';
        $html .= '<label class="field-label-compact">Repeat Until (optional)</label>';
        $html .= '<input type="date" id="event-recurrence-end-' . $calId . '" name="recurrenceEnd" class="input-sleek input-date input-compact" placeholder="Optional">';
        $html .= '<div style="font-size:9px; color:var(--text-dim, #666); margin-top:2px;">Leave empty for 1 year of events</div>';
        $html .= '</div>';
        $html .= '</div>'; // End row 4
        
        $html .= '</div>'; // End recurring options
        
        // 5. TIME (Start & End) - COLOR (inline)
        $html .= '<div class="form-row-group">';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">🕐 Start Time</label>';
        $html .= '<div class="time-picker-wrapper">';
        $html .= '<select id="event-time-' . $calId . '" name="time" class="input-sleek input-compact time-select" onchange="updateEndTimeOptions(\'' . $calId . '\')">';
        $html .= '<option value="">All day</option>';
        
        // Generate time options grouped by period
        $periods = [
            'Morning' => [6, 7, 8, 9, 10, 11],
            'Afternoon' => [12, 13, 14, 15, 16, 17],
            'Evening' => [18, 19, 20, 21, 22, 23],
            'Night' => [0, 1, 2, 3, 4, 5]
        ];
        
        foreach ($periods as $periodName => $hours) {
            $html .= '<optgroup label="── ' . $periodName . ' ──">';
            foreach ($hours as $hour) {
                for ($minute = 0; $minute < 60; $minute += 15) {
                    $timeValue = sprintf('%02d:%02d', $hour, $minute);
                    $displayHour = $hour == 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);
                    $ampm = $hour < 12 ? 'AM' : 'PM';
                    $displayTime = sprintf('%d:%02d %s', $displayHour, $minute, $ampm);
                    $html .= '<option value="' . $timeValue . '">' . $displayTime . '</option>';
                }
            }
            $html .= '</optgroup>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="form-field form-field-half">';
        $html .= '<label class="field-label-compact">🕐 End Time</label>';
        $html .= '<div class="time-picker-wrapper">';
        $html .= '<select id="event-end-time-' . $calId . '" name="endTime" class="input-sleek input-compact time-select">';
        $html .= '<option value="">Same as start</option>';
        
        // Generate time options grouped by period (same as start time)
        foreach ($periods as $periodName => $hours) {
            $html .= '<optgroup label="── ' . $periodName . ' ──">';
            foreach ($hours as $hour) {
                for ($minute = 0; $minute < 60; $minute += 15) {
                    $timeValue = sprintf('%02d:%02d', $hour, $minute);
                    $displayHour = $hour == 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);
                    $ampm = $hour < 12 ? 'AM' : 'PM';
                    $displayTime = sprintf('%d:%02d %s', $displayHour, $minute, $ampm);
                    $html .= '<option value="' . $timeValue . '">' . $displayTime . '</option>';
                }
            }
            $html .= '</optgroup>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
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
    
    private function renderMonthPicker($calId, $year, $month, $namespace, $theme = 'matrix', $themeStyles = null) {
        // Fallback to default theme if not provided
        if ($themeStyles === null) {
            $themeStyles = $this->getSidebarThemeStyles($theme);
        }
        
        $themeClass = 'calendar-theme-' . $theme;
        
        $html = '<div class="month-picker-overlay ' . $themeClass . '" id="month-picker-overlay-' . $calId . '" style="display:none;" onclick="closeMonthPicker(\'' . $calId . '\')">';
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
    
    private function renderDescription($description, $themeStyles = null) {
        if (empty($description)) {
            return '';
        }
        
        // Get theme for link colors if not provided
        if ($themeStyles === null) {
            $theme = $this->getSidebarTheme();
            $themeStyles = $this->getSidebarThemeStyles($theme);
        }
        
        $linkColor = '';
        $linkStyle = ' class="cal-link"';
        
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
                $linkHtml = '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener noreferrer"' . $linkStyle . '>' . htmlspecialchars($text) . '</a>';
            } else {
                // Handle internal DokuWiki links with section anchors
                $parts = explode('#', $link, 2);
                $pagePart = $parts[0];
                $sectionPart = isset($parts[1]) ? '#' . $parts[1] : '';
                
                $wikiUrl = DOKU_BASE . 'doku.php?id=' . rawurlencode($pagePart) . $sectionPart;
                $linkHtml = '<a href="' . $wikiUrl . '"' . $linkStyle . '>' . htmlspecialchars($text) . '</a>';
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
                $linkHtml = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer"' . $linkStyle . '>' . htmlspecialchars($text) . '</a>';
            } else {
                $linkHtml = '<a href="' . htmlspecialchars($url) . '"' . $linkStyle . '>' . htmlspecialchars($text) . '</a>';
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
            $linkHtml = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer"' . $linkStyle . '>' . htmlspecialchars($url) . '</a>';
            
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
        $boldStyle = '';
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
    private function renderSidebarWidget($events, $namespace, $calId, $themeOverride = null) {
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
        
        // Get week start preference and calculate week range
        $weekStartDay = $this->getWeekStartDay();
        
        if ($weekStartDay === 'monday') {
            // Monday start
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        } else {
            // Sunday start (default - US/Canada standard)
            $today = date('w'); // 0 (Sun) to 6 (Sat)
            if ($today == 0) {
                // Today is Sunday
                $weekStart = date('Y-m-d');
            } else {
                // Monday-Saturday: go back to last Sunday
                $weekStart = date('Y-m-d', strtotime('-' . $today . ' days'));
            }
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        }
        
        // Group events by category
        $todayEvents = [];
        $tomorrowEvents = [];
        $importantEvents = [];
        $weekEvents = []; // For week grid
        
        // Process all events
        foreach ($events as $dateKey => $dayEvents) {
            // Detect conflicts for events on this day
            $eventsWithConflicts = $this->detectTimeConflicts($dayEvents);
            
            foreach ($eventsWithConflicts as $event) {
                // Always categorize Today and Tomorrow regardless of week boundaries
                if ($dateKey === $todayStr) {
                    $todayEvents[] = array_merge($event, ['date' => $dateKey]);
                }
                if ($dateKey === $tomorrowStr) {
                    $tomorrowEvents[] = array_merge($event, ['date' => $dateKey]);
                }
                
                // Process week grid events (only for current week)
                if ($dateKey >= $weekStart && $dateKey <= $weekEnd) {
                    // Initialize week grid day if not exists
                    if (!isset($weekEvents[$dateKey])) {
                        $weekEvents[$dateKey] = [];
                    }
                    
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
                
                // Check if this is an important namespace
                $eventNs = isset($event['namespace']) ? $event['namespace'] : '';
                $isImportant = false;
                foreach ($importantNsList as $impNs) {
                    if ($eventNs === $impNs || strpos($eventNs, $impNs . ':') === 0) {
                        $isImportant = true;
                        break;
                    }
                }
                
                // Important events: show from today through next 2 weeks
                if ($isImportant && $dateKey >= $todayStr) {
                    $importantEvents[] = array_merge($event, ['date' => $dateKey]);
                }
            }
        }
        
        // Sort Important Events by date (earliest first)
        usort($importantEvents, function($a, $b) {
            $dateA = isset($a['date']) ? $a['date'] : '';
            $dateB = isset($b['date']) ? $b['date'] : '';
            
            // Compare dates
            if ($dateA === $dateB) {
                // Same date - sort by time
                $timeA = isset($a['time']) ? $a['time'] : '';
                $timeB = isset($b['time']) ? $b['time'] : '';
                
                if (empty($timeA) && !empty($timeB)) return 1;  // All-day events last
                if (!empty($timeA) && empty($timeB)) return -1;
                if (empty($timeA) && empty($timeB)) return 0;
                
                // Both have times
                $aMinutes = $this->timeToMinutes($timeA);
                $bMinutes = $this->timeToMinutes($timeB);
                return $aMinutes - $bMinutes;
            }
            
            return strcmp($dateA, $dateB);
        });
        
        // Get theme - prefer override from syntax parameter, fall back to admin default
        $theme = !empty($themeOverride) ? $themeOverride : $this->getSidebarTheme();
        $themeStyles = $this->getSidebarThemeStyles($theme);
        $themeClass = 'sidebar-' . $theme;
        
        // Start building HTML - Dynamic width with default font (overflow:visible for tooltips)
        $html = '<div class="sidebar-widget ' . $themeClass . '" id="sidebar-widget-' . $calId . '" style="width:100%; max-width:100%; box-sizing:border-box; font-family:system-ui, sans-serif; background:' . $themeStyles['bg'] . '; border:2px solid ' . $themeStyles['border'] . '; border-radius:4px; overflow:visible; box-shadow:0 0 10px ' . $themeStyles['shadow'] . '; position:relative;">';
        
        // Inject CSS variables so the event dialog (shared component) picks up the theme
        $btnTextColor = ($theme === 'professional') ? '#fff' : $themeStyles['bg'];
        $html .= '<style>
        #sidebar-widget-' . $calId . ' {
            --background-site: ' . $themeStyles['bg'] . ';
            --background-alt: ' . $themeStyles['cell_bg'] . ';
            --background-header: ' . $themeStyles['header_bg'] . ';
            --text-primary: ' . $themeStyles['text_primary'] . ';
            --text-dim: ' . $themeStyles['text_dim'] . ';
            --text-bright: ' . $themeStyles['text_bright'] . ';
            --border-color: ' . $themeStyles['grid_border'] . ';
            --border-main: ' . $themeStyles['border'] . ';
            --cell-bg: ' . $themeStyles['cell_bg'] . ';
            --cell-today-bg: ' . $themeStyles['cell_today_bg'] . ';
            --shadow-color: ' . $themeStyles['shadow'] . ';
            --header-border: ' . $themeStyles['header_border'] . ';
            --header-shadow: ' . $themeStyles['header_shadow'] . ';
            --grid-bg: ' . $themeStyles['grid_bg'] . ';
            --btn-text: ' . $btnTextColor . ';
            --pastdue-color: ' . $themeStyles['pastdue_color'] . ';
            --pastdue-bg: ' . $themeStyles['pastdue_bg'] . ';
            --pastdue-bg-strong: ' . $themeStyles['pastdue_bg_strong'] . ';
            --pastdue-bg-light: ' . $themeStyles['pastdue_bg_light'] . ';
            --tomorrow-bg: ' . $themeStyles['tomorrow_bg'] . ';
            --tomorrow-bg-strong: ' . $themeStyles['tomorrow_bg_strong'] . ';
            --tomorrow-bg-light: ' . $themeStyles['tomorrow_bg_light'] . ';
        }
        </style>';
        
        // Add sparkle effect for pink theme
        if ($theme === 'pink') {
            $html .= '<style>
            @keyframes sparkle-' . $calId . ' {
                0% { 
                    opacity: 0; 
                    transform: translate(0, 0) scale(0) rotate(0deg);
                }
                50% { 
                    opacity: 1; 
                    transform: translate(var(--tx), var(--ty)) scale(1) rotate(180deg);
                }
                100% { 
                    opacity: 0; 
                    transform: translate(calc(var(--tx) * 2), calc(var(--ty) * 2)) scale(0) rotate(360deg);
                }
            }
            
            @keyframes pulse-glow-' . $calId . ' {
                0%, 100% { box-shadow: 0 0 10px rgba(255, 20, 147, 0.4); }
                50% { box-shadow: 0 0 25px rgba(255, 20, 147, 0.8), 0 0 40px rgba(255, 20, 147, 0.4); }
            }
            
            @keyframes shimmer-' . $calId . ' {
                0% { background-position: -200% center; }
                100% { background-position: 200% center; }
            }
            
            .sidebar-pink {
                animation: pulse-glow-' . $calId . ' 3s ease-in-out infinite;
            }
            
            .sidebar-pink:hover {
                box-shadow: 0 0 30px rgba(255, 20, 147, 0.9), 0 0 50px rgba(255, 20, 147, 0.5) !important;
            }
            
            .sparkle-' . $calId . ' {
                position: absolute;
                pointer-events: none;
                font-size: 20px;
                z-index: 1000;
                animation: sparkle-' . $calId . ' 1s ease-out forwards;
                filter: drop-shadow(0 0 3px rgba(255, 20, 147, 0.8));
            }
            </style>';
            
            $html .= '<script>
            (function() {
                const container = document.getElementById("sidebar-widget-' . $calId . '");
                const sparkles = ["✨", "💖", "💎", "⭐", "💕", "🌟", "💗", "💫", "🎀", "👑"];
                
                function createSparkle(x, y) {
                    const sparkle = document.createElement("div");
                    sparkle.className = "sparkle-' . $calId . '";
                    sparkle.textContent = sparkles[Math.floor(Math.random() * sparkles.length)];
                    sparkle.style.left = x + "px";
                    sparkle.style.top = y + "px";
                    
                    // Random direction
                    const angle = Math.random() * Math.PI * 2;
                    const distance = 30 + Math.random() * 40;
                    sparkle.style.setProperty("--tx", Math.cos(angle) * distance + "px");
                    sparkle.style.setProperty("--ty", Math.sin(angle) * distance + "px");
                    
                    container.appendChild(sparkle);
                    
                    setTimeout(() => sparkle.remove(), 1000);
                }
                
                // Click sparkles
                container.addEventListener("click", function(e) {
                    const rect = container.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    // Create LOTS of sparkles for maximum bling!
                    for (let i = 0; i < 8; i++) {
                        setTimeout(() => {
                            const offsetX = x + (Math.random() - 0.5) * 30;
                            const offsetY = y + (Math.random() - 0.5) * 30;
                            createSparkle(offsetX, offsetY);
                        }, i * 40);
                    }
                });
                
                // Random auto-sparkles for extra glamour
                setInterval(() => {
                    const x = Math.random() * container.offsetWidth;
                    const y = Math.random() * container.offsetHeight;
                    createSparkle(x, y);
                }, 3000);
            })();
            </script>';
        }
        
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
                content += "<div style=\\"margin-top:3px; padding-top:2px; border-top:1px solid ' . $themeStyles['text_bright'] . ';\\">Uptime: " + latestStats.uptime + "</div>";
            }
            tooltip.style.setProperty("border-color", "' . $themeStyles['text_bright'] . '", "important");
            tooltip.style.setProperty("color", "' . $themeStyles['text_bright'] . '", "important");
            tooltip.style.setProperty("-webkit-text-fill-color", "' . $themeStyles['text_bright'] . '", "important");
        } else if (color === "purple") {
            content = "<div class=\\"tooltip-title\\">CPU Load (Short-term)</div>";
            content += "<div>1 min: " + (latestStats.load["1min"] || "N/A") + "</div>";
            content += "<div>5 min: " + (latestStats.load["5min"] || "N/A") + "</div>";
            if (latestStats.top_processes && latestStats.top_processes.length > 0) {
                content += "<div style=\\"margin-top:3px; padding-top:2px; border-top:1px solid ' . $themeStyles['border'] . ';\\" class=\\"tooltip-title\\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.setProperty("border-color", "' . $themeStyles['border'] . '", "important");
            tooltip.style.setProperty("color", "' . $themeStyles['border'] . '", "important");
            tooltip.style.setProperty("-webkit-text-fill-color", "' . $themeStyles['border'] . '", "important");
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
                content += "<div style=\\"margin-top:3px; padding-top:2px; border-top:1px solid ' . $themeStyles['text_primary'] . ';\\" class=\\"tooltip-title\\">Top Processes</div>";
                latestStats.top_processes.slice(0, 5).forEach(proc => {
                    content += "<div>" + proc.cpu + " " + proc.command + "</div>";
                });
            }
            tooltip.style.setProperty("border-color", "' . $themeStyles['text_primary'] . '", "important");
            tooltip.style.setProperty("color", "' . $themeStyles['text_primary'] . '", "important");
            tooltip.style.setProperty("-webkit-text-fill-color", "' . $themeStyles['text_primary'] . '", "important");
        }
        
        tooltip.innerHTML = content;
        tooltip.style.setProperty("display", "block");
        tooltip.style.setProperty("background", "' . $themeStyles['bg'] . '", "important");
        
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
    
    // Weather - uses default location, click weather to get local
    var userLocationGranted = false;
    var userLat = 38.5816;  // Sacramento default
    var userLon = -121.4944;
    
    function fetchWeatherData(lat, lon) {
        fetch("https://api.open-meteo.com/v1/forecast?latitude=" + lat + "&longitude=" + lon + "&current_weather=true&temperature_unit=fahrenheit")
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
    }
    
    function updateWeather() {
        fetchWeatherData(userLat, userLon);
    }
    
    // Click weather icon to request local weather (user gesture required)
    function requestLocalWeather() {
        if (userLocationGranted) return;
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                userLat = position.coords.latitude;
                userLon = position.coords.longitude;
                userLocationGranted = true;
                fetchWeatherData(userLat, userLon);
            }, function(error) {
                console.log("Geolocation denied, using default location");
            });
        }
    }
    
    setTimeout(function() {
        var weatherEl = document.querySelector("#weather-icon-' . $calId . '");
        if (weatherEl) {
            weatherEl.style.cursor = "pointer";
            weatherEl.title = "Click for local weather";
            weatherEl.addEventListener("click", requestLocalWeather);
        }
    }, 100);
    
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
        
        $html .= '<div class="eventlist-today-header" style="background:' . $themeStyles['header_bg'] . '; border:2px solid ' . $themeStyles['header_border'] . '; box-shadow:' . $themeStyles['header_shadow'] . ';">';
        $html .= '<span class="eventlist-today-clock" id="clock-' . $calId . '" style="color:' . $themeStyles['text_bright'] . ';">' . $currentTime . '</span>';
        $html .= '<div class="eventlist-bottom-info">';
        $html .= '<span class="eventlist-weather"><span id="weather-icon-' . $calId . '">🌤️</span> <span id="weather-temp-' . $calId . '" style="color:' . $themeStyles['text_primary'] . ';">--°</span></span>';
        $html .= '<span class="eventlist-today-date" style="color:' . $themeStyles['text_dim'] . ';">' . $displayDate . '</span>';
        $html .= '</div>';
        
        // Three CPU/Memory bars (all update live) - only if enabled
        $showSystemLoad = $this->getShowSystemLoad();
        if ($showSystemLoad) {
            $html .= '<div class="eventlist-stats-container">';
            
            // 5-minute load average (green, updates every 2 seconds)
            $html .= '<div class="eventlist-cpu-bar" style="background:' . $themeStyles['cell_today_bg'] . ' !important;" onmouseover="showTooltip_' . $jsCalId . '(\'green\')" onmouseout="hideTooltip_' . $jsCalId . '(\'green\')">';
            $html .= '<div class="eventlist-cpu-fill" id="cpu-5min-' . $calId . '" style="width: 0%; background:' . $themeStyles['text_bright'] . ' !important;"></div>';
            $html .= '<div class="system-tooltip" id="tooltip-green-' . $calId . '" style="display:none;"></div>';
            $html .= '</div>';
            
            // Real-time CPU (purple, updates with 5-sec average)
            $html .= '<div class="eventlist-cpu-bar eventlist-cpu-realtime" style="background:' . $themeStyles['cell_today_bg'] . ' !important;" onmouseover="showTooltip_' . $jsCalId . '(\'purple\')" onmouseout="hideTooltip_' . $jsCalId . '(\'purple\')">';
            $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-purple" id="cpu-realtime-' . $calId . '" style="width: 0%; background:' . $themeStyles['border'] . ' !important;"></div>';
            $html .= '<div class="system-tooltip" id="tooltip-purple-' . $calId . '" style="display:none;"></div>';
            $html .= '</div>';
            
            // Real-time Memory (orange, updates)
            $html .= '<div class="eventlist-cpu-bar eventlist-mem-realtime" style="background:' . $themeStyles['cell_today_bg'] . ' !important;" onmouseover="showTooltip_' . $jsCalId . '(\'orange\')" onmouseout="hideTooltip_' . $jsCalId . '(\'orange\')">';
            $html .= '<div class="eventlist-cpu-fill eventlist-cpu-fill-orange" id="mem-realtime-' . $calId . '" style="width: 0%; background:' . $themeStyles['text_primary'] . ' !important;"></div>';
            $html .= '<div class="system-tooltip" id="tooltip-orange-' . $calId . '" style="display:none;"></div>';
            $html .= '</div>';
            
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // Get today's date for default event date
        $todayStr = date('Y-m-d');
        
        // Thin "Add Event" bar between header and week grid - theme-aware colors
        $addBtnBg = $themeStyles['cell_today_bg'];
        $addBtnHover = $themeStyles['grid_bg'];
        $addBtnTextColor = ($theme === 'professional' || $theme === 'wiki') ? 
                          $themeStyles['text_bright'] : $themeStyles['text_bright'];
        $addBtnShadow = ($theme === 'professional' || $theme === 'wiki') ? 
                       '0 2px 4px rgba(0,0,0,0.2)' : '0 0 8px ' . $themeStyles['shadow'];
        $addBtnHoverShadow = ($theme === 'professional' || $theme === 'wiki') ? 
                            '0 3px 6px rgba(0,0,0,0.3)' : '0 0 12px ' . $themeStyles['shadow'];
        
        $html .= '<div style="background:' . $addBtnBg . '; padding:0; margin:0; height:12px; line-height:10px; text-align:center; cursor:pointer; border-top:1px solid rgba(0, 0, 0, 0.1); border-bottom:1px solid rgba(0, 0, 0, 0.1); box-shadow:' . $addBtnShadow . '; transition:all 0.2s;" onclick="openAddEvent(\'' . $calId . '\', \'' . $namespace . '\', \'' . $todayStr . '\');" onmouseover="this.style.background=\'' . $addBtnHover . '\'; this.style.boxShadow=\'' . $addBtnHoverShadow . '\';" onmouseout="this.style.background=\'' . $addBtnBg . '\'; this.style.boxShadow=\'' . $addBtnShadow . '\';">';
        $addBtnTextShadow = ($theme === 'pink') ? '0 0 3px ' . $addBtnTextColor : 'none';
        $html .= '<span style="color:' . $addBtnTextColor . '; font-size:8px; font-weight:700; letter-spacing:0.4px; font-family:system-ui, sans-serif; text-shadow:' . $addBtnTextShadow . '; position:relative; top:-1px;">+ ADD EVENT</span>';
        $html .= '</div>';
        
        // Week grid (7 cells)
        $html .= $this->renderWeekGrid($weekEvents, $weekStart, $themeStyles, $theme);
        
        // Section colors - derived from theme palette
        // Today: brightest accent, Tomorrow: primary accent, Important: dim/secondary accent
        if ($theme === 'matrix') {
            $todayColor = '#00ff00';     // Bright green
            $tomorrowColor = '#00cc07';  // Standard green
            $importantColor = '#00aa00'; // Dim green
        } else if ($theme === 'purple') {
            $todayColor = '#d4a5ff';     // Bright purple
            $tomorrowColor = '#9b59b6';  // Standard purple
            $importantColor = '#8e7ab8'; // Dim purple
        } else if ($theme === 'pink') {
            $todayColor = '#ff1493';     // Hot pink
            $tomorrowColor = '#ff69b4';  // Medium pink
            $importantColor = '#ff85c1'; // Light pink
        } else if ($theme === 'professional') {
            $todayColor = '#4a90e2';     // Blue accent
            $tomorrowColor = '#5ba3e6';  // Lighter blue
            $importantColor = '#7fb8ec'; // Lightest blue
        } else {
            // Wiki - section header backgrounds from template colors
            $todayColor = $themeStyles['text_bright'];      // __link__
            $tomorrowColor = $themeStyles['header_bg'];     // __background_alt__
            $importantColor = $themeStyles['header_border'];// __border__
        }
        
        // Check if there are any itinerary items
        $hasItinerary = !empty($todayEvents) || !empty($tomorrowEvents) || !empty($importantEvents);
        
        // Itinerary bar (collapsible toggle) - styled like +Add bar
        $itineraryBg = $themeStyles['cell_today_bg'];
        $itineraryHover = $themeStyles['grid_bg'];
        $itineraryTextColor = ($theme === 'professional' || $theme === 'wiki') ? 
                              $themeStyles['text_bright'] : $themeStyles['text_bright'];
        $itineraryShadow = ($theme === 'professional' || $theme === 'wiki') ? 
                           '0 2px 4px rgba(0,0,0,0.2)' : '0 0 8px ' . $themeStyles['shadow'];
        $itineraryHoverShadow = ($theme === 'professional' || $theme === 'wiki') ? 
                                '0 3px 6px rgba(0,0,0,0.3)' : '0 0 12px ' . $themeStyles['shadow'];
        $itineraryTextShadow = ($theme === 'pink') ? '0 0 3px ' . $itineraryTextColor : 'none';
        
        // Sanitize calId for JavaScript
        $jsCalId = str_replace('-', '_', $calId);
        
        // Get itinerary default state from settings
        $itineraryDefaultCollapsed = $this->getItineraryCollapsed();
        $arrowDefaultStyle = $itineraryDefaultCollapsed ? 'transform:rotate(-90deg);' : '';
        $contentDefaultStyle = $itineraryDefaultCollapsed ? 'max-height:0px; opacity:0;' : '';
        
        $html .= '<div id="itinerary-bar-' . $calId . '" style="background:' . $itineraryBg . '; padding:0; margin:0; height:12px; line-height:10px; text-align:center; cursor:pointer; border-top:1px solid rgba(0, 0, 0, 0.1); border-bottom:1px solid rgba(0, 0, 0, 0.1); box-shadow:' . $itineraryShadow . '; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:4px;" onclick="toggleItinerary_' . $jsCalId . '();" onmouseover="this.style.background=\'' . $itineraryHover . '\'; this.style.boxShadow=\'' . $itineraryHoverShadow . '\';" onmouseout="this.style.background=\'' . $itineraryBg . '\'; this.style.boxShadow=\'' . $itineraryShadow . '\';">';
        $html .= '<span id="itinerary-arrow-' . $calId . '" style="color:' . $itineraryTextColor . '; font-size:6px; font-weight:700; font-family:system-ui, sans-serif; text-shadow:' . $itineraryTextShadow . '; position:relative; top:-1px; transition:transform 0.2s; ' . $arrowDefaultStyle . '">▼</span>';
        $html .= '<span style="color:' . $itineraryTextColor . '; font-size:8px; font-weight:700; letter-spacing:0.4px; font-family:system-ui, sans-serif; text-shadow:' . $itineraryTextShadow . '; position:relative; top:-1px;">ITINERARY</span>';
        $html .= '</div>';
        
        // Itinerary content container (collapsible)
        $html .= '<div id="itinerary-content-' . $calId . '" style="transition:max-height 0.3s ease-out, opacity 0.2s ease-out; overflow:hidden; ' . $contentDefaultStyle . '">';
        
        // Today section
        if (!empty($todayEvents)) {
            $html .= $this->renderSidebarSection('Today', $todayEvents, $todayColor, $calId, $themeStyles, $theme, $importantNsList);
        }
        
        // Tomorrow section
        if (!empty($tomorrowEvents)) {
            $html .= $this->renderSidebarSection('Tomorrow', $tomorrowEvents, $tomorrowColor, $calId, $themeStyles, $theme, $importantNsList);
        }
        
        // Important events section
        if (!empty($importantEvents)) {
            $html .= $this->renderSidebarSection('Important Events', $importantEvents, $importantColor, $calId, $themeStyles, $theme, $importantNsList);
        }
        
        // Empty state if no itinerary items
        if (!$hasItinerary) {
            $html .= '<div style="padding:8px; text-align:center; color:' . $themeStyles['text_dim'] . '; font-size:10px; font-family:system-ui, sans-serif;">No upcoming events</div>';
        }
        
        $html .= '</div>'; // Close itinerary-content
        
        // Get itinerary default state from settings
        $itineraryDefaultCollapsed = $this->getItineraryCollapsed();
        $itineraryExpandedDefault = $itineraryDefaultCollapsed ? 'false' : 'true';
        $itineraryArrowDefault = $itineraryDefaultCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        $itineraryContentDefault = $itineraryDefaultCollapsed ? 'max-height:0px; opacity:0;' : 'max-height:none;';
        
        // JavaScript for toggling itinerary
        $html .= '<script>
        (function() {
            let itineraryExpanded_' . $jsCalId . ' = ' . $itineraryExpandedDefault . ';
            
            window.toggleItinerary_' . $jsCalId . ' = function() {
                const content = document.getElementById("itinerary-content-' . $calId . '");
                const arrow = document.getElementById("itinerary-arrow-' . $calId . '");
                
                if (itineraryExpanded_' . $jsCalId . ') {
                    // Collapse
                    content.style.maxHeight = "0px";
                    content.style.opacity = "0";
                    arrow.style.transform = "rotate(-90deg)";
                    itineraryExpanded_' . $jsCalId . ' = false;
                } else {
                    // Expand
                    content.style.maxHeight = content.scrollHeight + "px";
                    content.style.opacity = "1";
                    arrow.style.transform = "rotate(0deg)";
                    itineraryExpanded_' . $jsCalId . ' = true;
                    
                    // After transition, set to auto for dynamic content
                    setTimeout(function() {
                        if (itineraryExpanded_' . $jsCalId . ') {
                            content.style.maxHeight = "none";
                        }
                    }, 300);
                }
            };
            
            // Initialize based on default state
            const content = document.getElementById("itinerary-content-' . $calId . '");
            const arrow = document.getElementById("itinerary-arrow-' . $calId . '");
            if (content && arrow) {
                if (' . $itineraryExpandedDefault . ') {
                    content.style.maxHeight = "none";
                    arrow.style.transform = "rotate(0deg)";
                } else {
                    content.style.maxHeight = "0px";
                    content.style.opacity = "0";
                    arrow.style.transform = "rotate(-90deg)";
                }
            }
        })();
        </script>';
        
        $html .= '</div>';
        
        // Add event dialog for sidebar widget
        $html .= $this->renderEventDialog($calId, $namespace, $theme);
        
        // Add JavaScript for positioning data-tooltip elements
        $html .= '<script>
        // Position data-tooltip elements to prevent cutoff (up and to the LEFT)
        document.addEventListener("DOMContentLoaded", function() {
            const tooltipElements = document.querySelectorAll("[data-tooltip]");
            const isPinkTheme = document.querySelector(".sidebar-pink") !== null;
            
            tooltipElements.forEach(function(element) {
                element.addEventListener("mouseenter", function() {
                    const rect = element.getBoundingClientRect();
                    const style = window.getComputedStyle(element, ":before");
                    
                    // Position above the element, aligned to LEFT (not right)
                    element.style.setProperty("--tooltip-left", (rect.left - 150) + "px");
                    element.style.setProperty("--tooltip-top", (rect.top - 30) + "px");
                    
                    // Pink theme: position heart to the right of tooltip
                    if (isPinkTheme) {
                        element.style.setProperty("--heart-left", (rect.left - 150 + 210) + "px");
                        element.style.setProperty("--heart-top", (rect.top - 30) + "px");
                    }
                });
            });
        });
        
        // Apply custom properties to position tooltips
        const style = document.createElement("style");
        style.textContent = `
            [data-tooltip]:hover:before {
                left: var(--tooltip-left, 0) !important;
                top: var(--tooltip-top, 0) !important;
            }
            .sidebar-pink [data-tooltip]:hover:after {
                left: var(--heart-left, 0) !important;
                top: var(--heart-top, 0) !important;
            }
        `;
        document.head.appendChild(style);
        </script>';
        
        return $html;
    }
    
    /**
     * Render compact week grid (7 cells with event bars) - Theme-aware
     */
    private function renderWeekGrid($weekEvents, $weekStart, $themeStyles, $theme) {
        // Generate unique ID for this calendar instance - sanitize for JavaScript
        $calId = 'cal_' . substr(md5($weekStart . microtime()), 0, 8);
        $jsCalId = str_replace('-', '_', $calId);  // Sanitize for JS variable names
        
        $html = '<div style="display:grid; grid-template-columns:repeat(7, 1fr); gap:1px; background:' . $themeStyles['grid_bg'] . '; border-bottom:2px solid ' . $themeStyles['grid_border'] . ';">';
        
        // Day names depend on week start setting
        $weekStartDay = $this->getWeekStartDay();
        if ($weekStartDay === 'monday') {
            $dayNames = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];  // Monday to Sunday
        } else {
            $dayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];  // Sunday to Saturday
        }
        $today = date('Y-m-d');
        
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($weekStart . ' +' . $i . ' days'));
            $dayNum = date('j', strtotime($date));
            $isToday = $date === $today;
            
            $events = isset($weekEvents[$date]) ? $weekEvents[$date] : [];
            $eventCount = count($events);
            
            $bgColor = $isToday ? $themeStyles['cell_today_bg'] : $themeStyles['cell_bg'];
            $textColor = $isToday ? $themeStyles['text_bright'] : $themeStyles['text_primary'];
            $fontWeight = $isToday ? '700' : '500';
            
            // Theme-aware text shadow
            if ($theme === 'pink') {
                $glowColor = $isToday ? $themeStyles['text_bright'] : $themeStyles['text_primary'];
                $textShadow = $isToday ? 'text-shadow:0 0 3px ' . $glowColor . ';' : 'text-shadow:0 0 2px ' . $glowColor . ';';
            } else if ($theme === 'matrix') {
                $glowColor = $isToday ? $themeStyles['text_bright'] : $themeStyles['text_primary'];
                $textShadow = $isToday ? 'text-shadow:0 0 2px ' . $glowColor . ';' : 'text-shadow:0 0 1px ' . $glowColor . ';';
            } else if ($theme === 'purple') {
                $glowColor = $isToday ? $themeStyles['text_bright'] : $themeStyles['text_primary'];
                $textShadow = $isToday ? 'text-shadow:0 0 2px ' . $glowColor . ';' : 'text-shadow:0 0 1px ' . $glowColor . ';';
            } else {
                $textShadow = '';  // No glow for professional/wiki
            }
            
            // Border color based on theme
            $borderColor = $themeStyles['grid_border'];
            
            $hasEvents = $eventCount > 0;
            $clickableStyle = $hasEvents ? 'cursor:pointer;' : '';
            $clickHandler = $hasEvents ? ' onclick="showDayEvents_' . $jsCalId . '(\'' . $date . '\')"' : '';
            
            $html .= '<div style="background:' . $bgColor . '; padding:4px 2px; text-align:center; min-height:45px; position:relative; border:1px solid ' . $borderColor . ' !important; ' . $clickableStyle . '" ' . $clickHandler . '>';
            
            // Day letter - theme color
            $dayLetterColor = $theme === 'professional' ? '#7f8c8d' : $themeStyles['text_primary'];
            $html .= '<div style="font-size:9px; color:' . $dayLetterColor . '; font-weight:500; font-family:system-ui, sans-serif;">' . $dayNames[$i] . '</div>';
            
            // Day number
            $html .= '<div style="font-size:12px; color:' . $textColor . '; font-weight:' . $fontWeight . '; margin:2px 0; font-family:system-ui, sans-serif; ' . $textShadow . '">' . $dayNum . '</div>';
            
            // Event bars (max 4 visible) with theme-aware glow
            if ($eventCount > 0) {
                $showCount = min($eventCount, 4);
                for ($j = 0; $j < $showCount; $j++) {
                    $event = $events[$j];
                    $color = isset($event['color']) ? $event['color'] : $themeStyles['text_primary'];
                    $barShadow = $theme === 'professional' ? '0 1px 2px rgba(0,0,0,0.2)' : '0 0 3px ' . htmlspecialchars($color);
                    $html .= '<div style="height:2px; background:' . htmlspecialchars($color) . '; margin:1px 0; border-radius:1px; box-shadow:' . $barShadow . ';"></div>';
                }
                
                // Show "+N more" if more than 4 - theme color
                if ($eventCount > 4) {
                    $moreTextColor = $theme === 'professional' ? '#7f8c8d' : $themeStyles['text_primary'];
                    $html .= '<div style="font-size:7px; color:' . $moreTextColor . '; margin-top:1px; font-family:system-ui, sans-serif;">+' . ($eventCount - 4) . '</div>';
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Add container for selected day events display (with unique ID) - theme-aware
        $panelBorderColor = $themeStyles['border'];
        $panelHeaderBg = $themeStyles['border'];
        $panelShadow = ($theme === 'professional' || $theme === 'wiki') ? 
                      '0 1px 3px rgba(0, 0, 0, 0.1)' : 
                      '0 0 5px ' . $themeStyles['shadow'];
        $panelContentBg = ($theme === 'professional') ? 'rgba(255, 255, 255, 0.95)' : 
                         ($theme === 'wiki' ? $themeStyles['cell_bg'] : 'rgba(36, 36, 36, 0.5)');
        $panelHeaderShadow = ($theme === 'professional' || $theme === 'wiki') ? '0 2px 4px rgba(0, 0, 0, 0.15)' : '0 0 8px ' . $panelHeaderBg;
        
        // Header text color - dark bg text for dark themes, white for light theme accent headers
        $panelHeaderColor = ($theme === 'matrix' || $theme === 'purple' || $theme === 'pink') ? $themeStyles['bg'] : 
                            (($theme === 'wiki') ? $themeStyles['text_primary'] : '#fff');
        
        $html .= '<div id="selected-day-events-' . $calId . '" style="display:none; margin:8px 4px; border-left:3px solid ' . $panelBorderColor . ($theme === 'wiki' ? '' : ' !important') . '; box-shadow:' . $panelShadow . ';">';
        if ($theme === 'wiki') {
            $html .= '<div style="background:' . $panelHeaderBg . '; color:' . $panelHeaderColor . '; padding:4px 6px; font-size:9px; font-weight:700; letter-spacing:0.3px; font-family:system-ui, sans-serif; box-shadow:' . $panelHeaderShadow . '; display:flex; justify-content:space-between; align-items:center;">';
            $html .= '<span id="selected-day-title-' . $calId . '"></span>';
            $html .= '<span onclick="document.getElementById(\'selected-day-events-' . $calId . '\').style.display=\'none\';" style="cursor:pointer; font-size:12px; padding:0 4px; font-weight:700; color:' . $panelHeaderColor . ';">✕</span>';
        } else {
            $html .= '<div style="background:' . $panelHeaderBg . ' !important; color:' . $panelHeaderColor . ' !important; -webkit-text-fill-color:' . $panelHeaderColor . ' !important; padding:4px 6px; font-size:9px; font-weight:700; letter-spacing:0.3px; font-family:system-ui, sans-serif; box-shadow:' . $panelHeaderShadow . '; display:flex; justify-content:space-between; align-items:center;">';
            $html .= '<span id="selected-day-title-' . $calId . '"></span>';
            $html .= '<span onclick="document.getElementById(\'selected-day-events-' . $calId . '\').style.display=\'none\';" style="cursor:pointer; font-size:12px; padding:0 4px; font-weight:700; color:' . $panelHeaderColor . ' !important; -webkit-text-fill-color:' . $panelHeaderColor . ' !important;">✕</span>';
        }
        $html .= '</div>';
        $html .= '<div id="selected-day-content-' . $calId . '" style="padding:4px 0; background:' . $panelContentBg . ';"></div>';
        $html .= '</div>';
        
        // Add JavaScript for day selection with event data
        $html .= '<script>';
        // Sanitize calId for JavaScript variable names
        $jsCalId = str_replace('-', '_', $calId);
        $html .= 'window.weekEventsData_' . $jsCalId . ' = ' . json_encode($weekEvents) . ';';
        
        // Pass theme colors to JavaScript
        $jsThemeColors = json_encode([
            'text_primary' => $themeStyles['text_primary'],
            'text_bright' => $themeStyles['text_bright'],
            'text_dim' => $themeStyles['text_dim'],
            'text_shadow' => ($theme === 'pink') ? 'text-shadow:0 0 2px ' . $themeStyles['text_primary'] : 
                             ((in_array($theme, ['matrix', 'purple'])) ? 'text-shadow:0 0 1px ' . $themeStyles['text_primary'] : ''),
            'event_bg' => $theme === 'professional' ? 'rgba(255, 255, 255, 0.5)' : 
                         ($theme === 'wiki' ? $themeStyles['cell_bg'] : 'rgba(36, 36, 36, 0.3)'),
            'border_color' => $theme === 'professional' ? 'rgba(0, 0, 0, 0.1)' : 
                             ($theme === 'purple' ? 'rgba(155, 89, 182, 0.2)' : 
                             ($theme === 'pink' ? 'rgba(255, 20, 147, 0.3)' : 
                             ($theme === 'wiki' ? $themeStyles['grid_border'] : 'rgba(0, 204, 7, 0.2)'))),
            'bar_shadow' => $theme === 'professional' ? '0 1px 2px rgba(0,0,0,0.2)' : 
                           ($theme === 'wiki' ? '0 1px 2px rgba(0,0,0,0.15)' : '0 0 3px')
        ]);
        $html .= 'window.themeColors_' . $jsCalId . ' = ' . $jsThemeColors . ';';
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
            
            // Build events HTML with single color bar (event color only) - theme-aware
            const themeColors = window.themeColors_' . $jsCalId . ';
            sortedEvents.forEach(event => {
                const eventColor = event.color || themeColors.text_primary;
                
                const eventDiv = document.createElement("div");
                eventDiv.style.cssText = "padding:4px 6px; border-bottom:1px solid " + themeColors.border_color + "; font-size:10px; display:flex; align-items:stretch; gap:6px; background:" + themeColors.event_bg + "; min-height:20px;";
                
                let eventHTML = "";
                
                // Event assigned color bar (single bar on left) - theme-aware shadow
                const barShadow = themeColors.bar_shadow + (themeColors.bar_shadow.includes("rgba") ? "" : " " + eventColor);
                eventHTML += "<div style=\\"width:3px; align-self:stretch; background:" + eventColor + "; border-radius:1px; flex-shrink:0; box-shadow:" + barShadow + ";\\"></div>";
                
                // Content wrapper
                eventHTML += "<div style=\\"flex:1; min-width:0; display:flex; justify-content:space-between; align-items:start; gap:4px;\\">";
                
                // Left side: event details
                eventHTML += "<div style=\\"flex:1; min-width:0;\\">";
                eventHTML += "<div style=\\"font-weight:600; color:" + themeColors.text_primary + "; word-wrap:break-word; font-family:system-ui, sans-serif; " + themeColors.text_shadow + ";\\">";
                
                // Time
                if (event.time) {
                    const timeParts = event.time.split(":");
                    let hours = parseInt(timeParts[0]);
                    const minutes = timeParts[1];
                    const ampm = hours >= 12 ? "PM" : "AM";
                    hours = hours % 12 || 12;
                    eventHTML += "<span style=\\"color:" + themeColors.text_bright + "; font-weight:500; font-size:9px;\\">" + hours + ":" + minutes + " " + ampm + "</span> ";
                }
                
                // Title - use HTML version if available
                const titleHTML = event.title_html || event.title || "Untitled";
                eventHTML += titleHTML;
                eventHTML += "</div>";
                
                // Description if present - use HTML version - theme-aware color
                if (event.description_html || event.description) {
                    const descHTML = event.description_html || event.description;
                    eventHTML += "<div style=\\"font-size:9px; color:" + themeColors.text_dim + "; margin-top:2px;\\">" + descHTML + "</div>";
                }
                
                eventHTML += "</div>"; // Close event details
                
                // Right side: conflict badge with tooltip
                if (event.conflict) {
                    let conflictList = [];
                    if (event.conflictingWith && event.conflictingWith.length > 0) {
                        event.conflictingWith.forEach(conf => {
                            const confTime = conf.time + (conf.end_time ? " - " + conf.end_time : "");
                            conflictList.push(conf.title + " (" + confTime + ")");
                        });
                    }
                    const conflictData = btoa(unescape(encodeURIComponent(JSON.stringify(conflictList))));
                    eventHTML += "<span class=\\"event-conflict-badge\\" style=\\"font-size:10px;\\" data-conflicts=\\"" + conflictData + "\\" onmouseenter=\\"showConflictTooltip(this)\\" onmouseleave=\\"hideConflictTooltip()\\">⚠️ " + (event.conflictingWith ? event.conflictingWith.length : 1) + "</span>";
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
    private function renderSidebarSection($title, $events, $accentColor, $calId, $themeStyles, $theme, $importantNsList = ['important']) {
        // Keep the original accent colors for borders
        $borderColor = $accentColor;
        
        // Show date for Important Events section
        $showDate = ($title === 'Important Events');
        
        // Sort events differently based on section
        if ($title === 'Important Events') {
            // Important Events: sort by date first, then by time
            usort($events, function($a, $b) {
                $aDate = isset($a['date']) ? $a['date'] : '';
                $bDate = isset($b['date']) ? $b['date'] : '';
                
                // Different dates - sort by date
                if ($aDate !== $bDate) {
                    return strcmp($aDate, $bDate);
                }
                
                // Same date - sort by time
                $aTime = isset($a['time']) && !empty($a['time']) ? $a['time'] : '';
                $bTime = isset($b['time']) && !empty($b['time']) ? $b['time'] : '';
                
                // All-day events last within same date
                if (empty($aTime) && !empty($bTime)) return 1;
                if (!empty($aTime) && empty($bTime)) return -1;
                if (empty($aTime) && empty($bTime)) return 0;
                
                // Both have times
                $aMinutes = $this->timeToMinutes($aTime);
                $bMinutes = $this->timeToMinutes($bTime);
                return $aMinutes - $bMinutes;
            });
        } else {
            // Today/Tomorrow: sort by time only (all same date)
            usort($events, function($a, $b) {
                $aTime = isset($a['time']) && !empty($a['time']) ? $a['time'] : '';
                $bTime = isset($b['time']) && !empty($b['time']) ? $b['time'] : '';
                
                // All-day events (no time) come first
                if (empty($aTime) && !empty($bTime)) return -1;
                if (!empty($aTime) && empty($bTime)) return 1;
                if (empty($aTime) && empty($bTime)) return 0;
                
                // Both have times - convert to minutes for proper chronological sort
                $aMinutes = $this->timeToMinutes($aTime);
                $bMinutes = $this->timeToMinutes($bTime);
                
                return $aMinutes - $bMinutes;
            });
        }
        
        // Theme-aware section shadow
        $sectionShadow = ($theme === 'professional' || $theme === 'wiki') ? 
                        '0 1px 3px rgba(0, 0, 0, 0.1)' : 
                        '0 0 5px ' . $themeStyles['shadow'];
        
        if ($theme === 'wiki') {
            // Wiki theme: use a background div for the left bar instead of border-left
            // Dark Reader maps border colors differently from background colors, causing mismatch
            $html = '<div style="display:flex; margin:8px 4px; box-shadow:' . $sectionShadow . '; background:' . $themeStyles['bg'] . ';">';
            $html .= '<div style="width:3px; flex-shrink:0; background:' . $borderColor . ';"></div>';
            $html .= '<div style="flex:1; min-width:0;">';
        } else {
            $html = '<div style="border-left:3px solid ' . $borderColor . ' !important; margin:8px 4px; box-shadow:' . $sectionShadow . ';">';
        }
        
        // Section header with accent color background - theme-aware
        $headerShadow = ($theme === 'professional' || $theme === 'wiki') ? '0 2px 4px rgba(0, 0, 0, 0.15)' : '0 0 8px ' . $accentColor;
        $headerTextColor = ($theme === 'matrix' || $theme === 'purple' || $theme === 'pink') ? $themeStyles['bg'] : 
                           (($theme === 'wiki') ? $themeStyles['text_primary'] : '#fff');
        if ($theme === 'wiki') {
            // Wiki theme: no !important — let Dark Reader adjust these
            $html .= '<div style="background:' . $accentColor . '; color:' . $headerTextColor . '; padding:4px 6px; font-size:9px; font-weight:700; letter-spacing:0.3px; font-family:system-ui, sans-serif; box-shadow:' . $headerShadow . ';">';
        } else {
            // Dark themes + professional: lock colors against Dark Reader
            $html .= '<div style="background:' . $accentColor . ' !important; color:' . $headerTextColor . ' !important; -webkit-text-fill-color:' . $headerTextColor . ' !important; padding:4px 6px; font-size:9px; font-weight:700; letter-spacing:0.3px; font-family:system-ui, sans-serif; box-shadow:' . $headerShadow . ';">';
        }
        $html .= htmlspecialchars($title);
        $html .= '</div>';
        
        // Events - no background (transparent)
        $html .= '<div style="padding:4px 0;">';
        
        foreach ($events as $event) {
            $html .= $this->renderSidebarEvent($event, $calId, $showDate, $accentColor, $themeStyles, $theme, $importantNsList);
        }
        
        $html .= '</div>';
        $html .= '</div>';
        if ($theme === 'wiki') {
            $html .= '</div>'; // Close flex wrapper
        }
        
        return $html;
    }
    
    /**
     * Render individual event in sidebar - Theme-aware
     */
    private function renderSidebarEvent($event, $calId, $showDate = false, $sectionColor = '#00cc07', $themeStyles = null, $theme = 'matrix', $importantNsList = ['important']) {
        $title = isset($event['title']) ? htmlspecialchars($event['title']) : 'Untitled';
        $time = isset($event['time']) ? $event['time'] : '';
        $endTime = isset($event['endTime']) ? $event['endTime'] : '';
        $eventColor = isset($event['color']) ? htmlspecialchars($event['color']) : ($themeStyles ? $themeStyles['text_primary'] : '#00cc07');
        $date = isset($event['date']) ? $event['date'] : '';
        $isTask = isset($event['isTask']) && $event['isTask'];
        $completed = isset($event['completed']) && $event['completed'];
        
        // Check if this is an important namespace event
        $eventNs = isset($event['namespace']) ? $event['namespace'] : '';
        $isImportantNs = false;
        foreach ($importantNsList as $impNs) {
            if ($eventNs === $impNs || strpos($eventNs, $impNs . ':') === 0) {
                $isImportantNs = true;
                break;
            }
        }
        
        // Theme-aware colors
        $titleColor = $themeStyles ? $themeStyles['text_primary'] : '#00cc07';
        $timeColor = $themeStyles ? $themeStyles['text_bright'] : '#00dd00';
        $textShadow = ($theme === 'pink') ? 'text-shadow:0 0 2px ' . $titleColor . ';' : 
                      ((in_array($theme, ['matrix', 'purple'])) ? 'text-shadow:0 0 1px ' . $titleColor . ';' : '');
        
        // Check for conflicts (using 'conflict' field set by detectTimeConflicts)
        $hasConflict = isset($event['conflict']) && $event['conflict'];
        $conflictingWith = isset($event['conflictingWith']) ? $event['conflictingWith'] : [];
        
        // Build conflict list for tooltip
        $conflictList = [];
        if ($hasConflict && !empty($conflictingWith)) {
            foreach ($conflictingWith as $conf) {
                $confTime = $this->formatTimeDisplay($conf['time'], isset($conf['end_time']) ? $conf['end_time'] : '');
                $conflictList[] = $conf['title'] . ' (' . $confTime . ')';
            }
        }
        
        // No background on individual events (transparent) - unless important namespace
        // Use theme grid_border with slight opacity for subtle divider
        $borderColor = $themeStyles['grid_border'];
        
        // Important namespace highlighting - subtle themed background
        $importantBg = '';
        $importantBorder = '';
        if ($isImportantNs) {
            // Theme-specific important highlighting
            switch ($theme) {
                case 'matrix':
                    $importantBg = 'background:rgba(0,204,7,0.08);';
                    $importantBorder = 'border-right:2px solid rgba(0,204,7,0.4);';
                    break;
                case 'purple':
                    $importantBg = 'background:rgba(156,39,176,0.08);';
                    $importantBorder = 'border-right:2px solid rgba(156,39,176,0.4);';
                    break;
                case 'pink':
                    $importantBg = 'background:rgba(255,105,180,0.1);';
                    $importantBorder = 'border-right:2px solid rgba(255,105,180,0.5);';
                    break;
                case 'professional':
                    $importantBg = 'background:rgba(33,150,243,0.08);';
                    $importantBorder = 'border-right:2px solid rgba(33,150,243,0.4);';
                    break;
                case 'wiki':
                    $importantBg = 'background:rgba(0,102,204,0.06);';
                    $importantBorder = 'border-right:2px solid rgba(0,102,204,0.3);';
                    break;
                default:
                    $importantBg = 'background:rgba(0,204,7,0.08);';
                    $importantBorder = 'border-right:2px solid rgba(0,204,7,0.4);';
            }
        }
        
        $html = '<div style="padding:4px 6px; border-bottom:1px solid ' . $borderColor . ' !important; font-size:10px; display:flex; align-items:stretch; gap:6px; min-height:20px; ' . $importantBg . $importantBorder . '">';
        
        // Event's assigned color bar (single bar on the left)
        $barShadow = ($theme === 'professional') ? '0 1px 2px rgba(0,0,0,0.2)' : '0 0 3px ' . $eventColor;
        $html .= '<div style="width:3px; align-self:stretch; background:' . $eventColor . '; border-radius:1px; flex-shrink:0; box-shadow:' . $barShadow . ';"></div>';
        
        // Content
        $html .= '<div style="flex:1; min-width:0;">';
        
        // Time + title
        $html .= '<div style="font-weight:600; color:' . $titleColor . '; word-wrap:break-word; font-family:system-ui, sans-serif; ' . $textShadow . '">';
        
        if ($time) {
            $displayTime = $this->formatTimeDisplay($time, $endTime);
            $html .= '<span style="color:' . $timeColor . '; font-weight:500; font-size:9px;">' . htmlspecialchars($displayTime) . '</span> ';
        }
        
        // Task checkbox
        if ($isTask) {
            $checkIcon = $completed ? '☑' : '☐';
            $checkColor = $themeStyles ? $themeStyles['text_bright'] : '#00ff00';
            $html .= '<span style="font-size:11px; color:' . $checkColor . ';">' . $checkIcon . '</span> ';
        }
        
        // Important indicator icon for important namespace events
        if ($isImportantNs) {
            $html .= '<span style="font-size:9px;" title="Important">⭐</span> ';
        }
        
        $html .= $title; // Already HTML-escaped on line 2625
        
        // Conflict badge using same system as main calendar
        if ($hasConflict && !empty($conflictList)) {
            $conflictJson = base64_encode(json_encode($conflictList));
            $html .= ' <span class="event-conflict-badge" style="font-size:10px;" data-conflicts="' . $conflictJson . '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' . count($conflictList) . '</span>';
        }
        
        $html .= '</div>';
        
        // Date display BELOW event name for Important events
        if ($showDate && $date) {
            $dateObj = new DateTime($date);
            $displayDate = $dateObj->format('D, M j'); // e.g., "Mon, Feb 10"
            $dateColor = $themeStyles ? $themeStyles['text_dim'] : '#00aa00';
            $dateShadow = ($theme === 'pink') ? 'text-shadow:0 0 2px ' . $dateColor . ';' : 
                          ((in_array($theme, ['matrix', 'purple'])) ? 'text-shadow:0 0 1px ' . $dateColor . ';' : '');
            $html .= '<div style="font-size:8px; color:' . $dateColor . '; font-weight:500; margin-top:2px; ' . $dateShadow . '">' . htmlspecialchars($displayDate) . '</div>';
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
     * Detect time conflicts among events on the same day
     * Returns events array with 'conflict' flag and 'conflictingWith' array
     */
    private function detectTimeConflicts($dayEvents) {
        if (empty($dayEvents)) {
            return $dayEvents;
        }
        
        // If only 1 event, no conflicts possible but still add the flag
        if (count($dayEvents) === 1) {
            return [array_merge($dayEvents[0], ['conflict' => false, 'conflictingWith' => []])];
        }
        
        $eventsWithFlags = [];
        
        foreach ($dayEvents as $i => $event) {
            $hasConflict = false;
            $conflictingWith = [];
            
            // Skip all-day events (no time)
            if (empty($event['time'])) {
                $eventsWithFlags[] = array_merge($event, ['conflict' => false, 'conflictingWith' => []]);
                continue;
            }
            
            // Get this event's time range
            $startTime = $event['time'];
            // Check both 'end_time' (snake_case) and 'endTime' (camelCase) for compatibility
            $endTime = '';
            if (isset($event['end_time']) && $event['end_time'] !== '') {
                $endTime = $event['end_time'];
            } elseif (isset($event['endTime']) && $event['endTime'] !== '') {
                $endTime = $event['endTime'];
            } else {
                // If no end time, use start time (zero duration) - matches main calendar logic
                $endTime = $startTime;
            }
            
            // Check against all other events
            foreach ($dayEvents as $j => $otherEvent) {
                if ($i === $j) continue; // Skip self
                if (empty($otherEvent['time'])) continue; // Skip all-day events
                
                $otherStart = $otherEvent['time'];
                // Check both field name formats
                $otherEnd = '';
                if (isset($otherEvent['end_time']) && $otherEvent['end_time'] !== '') {
                    $otherEnd = $otherEvent['end_time'];
                } elseif (isset($otherEvent['endTime']) && $otherEvent['endTime'] !== '') {
                    $otherEnd = $otherEvent['endTime'];
                } else {
                    $otherEnd = $otherStart;
                }
                
                // Check for overlap: convert to minutes and compare
                $start1Min = $this->timeToMinutes($startTime);
                $end1Min = $this->timeToMinutes($endTime);
                $start2Min = $this->timeToMinutes($otherStart);
                $end2Min = $this->timeToMinutes($otherEnd);
                
                // Overlap if: start1 < end2 AND start2 < end1
                // Note: Using < (not <=) so events that just touch at boundaries don't conflict
                // e.g., 1:00-2:00 and 2:00-3:00 are NOT in conflict
                if ($start1Min < $end2Min && $start2Min < $end1Min) {
                    $hasConflict = true;
                    $conflictingWith[] = [
                        'title' => isset($otherEvent['title']) ? $otherEvent['title'] : 'Untitled',
                        'time' => $otherStart,
                        'end_time' => $otherEnd
                    ];
                }
            }
            
            $eventsWithFlags[] = array_merge($event, [
                'conflict' => $hasConflict,
                'conflictingWith' => $conflictingWith
            ]);
        }
        
        return $eventsWithFlags;
    }
    
    /**
     * Add hours to a time string
     */
    private function addHoursToTime($time, $hours) {
        $totalMinutes = $this->timeToMinutes($time) + ($hours * 60);
        $h = floor($totalMinutes / 60) % 24;
        $m = $totalMinutes % 60;
        return sprintf('%02d:%02d', $h, $m);
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
    
    /**
     * Get current sidebar theme
     */
    private function getSidebarTheme() {
        $configFile = DOKU_INC . 'data/meta/calendar_theme.txt';
        if (file_exists($configFile)) {
            $theme = trim(file_get_contents($configFile));
            if (in_array($theme, ['matrix', 'purple', 'professional', 'pink', 'wiki'])) {
                return $theme;
            }
        }
        return 'matrix'; // Default
    }
    
    /**
     * Get colors from DokuWiki template's style.ini file
     */
    private function getWikiTemplateColors() {
        global $conf;
        
        // Get current template name
        $template = $conf['template'];
        
        // Try multiple possible locations for style.ini
        $possiblePaths = [
            DOKU_INC . 'conf/tpl/' . $template . '/style.ini',
            DOKU_INC . 'lib/tpl/' . $template . '/style.ini',
        ];
        
        $styleIni = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $styleIni = parse_ini_file($path, true);
                break;
            }
        }
        
        if (!$styleIni) {
            return null; // Fall back to CSS variables
        }
        
        // Extract color replacements
        $replacements = isset($styleIni['replacements']) ? $styleIni['replacements'] : [];
        
        // Map style.ini colors to our theme structure
        $bgSite = isset($replacements['__background_site__']) ? $replacements['__background_site__'] : '#f5f5f5';
        $background = isset($replacements['__background__']) ? $replacements['__background__'] : '#fff';
        $bgAlt = isset($replacements['__background_alt__']) ? $replacements['__background_alt__'] : '#e8e8e8';
        $bgNeu = isset($replacements['__background_neu__']) ? $replacements['__background_neu__'] : '#eee';
        $text = isset($replacements['__text__']) ? $replacements['__text__'] : '#333';
        $textAlt = isset($replacements['__text_alt__']) ? $replacements['__text_alt__'] : '#999';
        $textNeu = isset($replacements['__text_neu__']) ? $replacements['__text_neu__'] : '#666';
        $border = isset($replacements['__border__']) ? $replacements['__border__'] : '#ccc';
        $link = isset($replacements['__link__']) ? $replacements['__link__'] : '#2b73b7';
        $existing = isset($replacements['__existing__']) ? $replacements['__existing__'] : $link;
        
        // Build theme colors from template colors
        // ============================================
        // DokuWiki style.ini → Calendar CSS Variable Mapping
        // ============================================
        //   style.ini key         → CSS variable          → Used for
        //   __background_site__   → --background-site     → Container, panel backgrounds
        //   __background__        → --cell-bg             → Cell/input backgrounds (typically white)
        //   __background_alt__    → --background-alt      → Hover states, header backgrounds
        //                         → --background-header
        //   __background_neu__    → --cell-today-bg       → Today cell highlight
        //   __text__              → --text-primary        → Primary text, labels, titles
        //   __text_neu__          → --text-dim            → Secondary text, dates, descriptions
        //   __text_alt__          → (not mapped)          → Available for future use
        //   __border__            → --border-color        → Grid lines, input borders
        //                         → --border-main         → Accent color: buttons, badges, active elements, section headers
        //                         → --header-border
        //   __link__              → --text-bright         → Links, accent text
        //   __existing__          → (fallback to __link__)→ Available for future use
        //
        // To customize: edit your template's conf/style.ini [replacements]
        return [
            'bg' => $bgSite,
            'border' => $border,         // Accent color from template border
            'shadow' => 'rgba(0, 0, 0, 0.1)',
            'header_bg' => $bgAlt,       // Headers use alt background
            'header_border' => $border,
            'header_shadow' => '0 2px 4px rgba(0, 0, 0, 0.1)',
            'text_primary' => $text,
            'text_bright' => $link,
            'text_dim' => $textNeu,
            'grid_bg' => $bgSite,
            'grid_border' => $border,
            'cell_bg' => $background,    // Cells use __background__ (white/light)
            'cell_today_bg' => $bgNeu,
            'bar_glow' => '0 1px 2px',
            'pastdue_color' => '#e74c3c',
            'pastdue_bg' => '#ffe6e6',
            'pastdue_bg_strong' => '#ffd9d9',
            'pastdue_bg_light' => '#fff2f2',
            'tomorrow_bg' => '#fff9e6',
            'tomorrow_bg_strong' => '#fff4cc',
            'tomorrow_bg_light' => '#fffbf0',
        ];
    }
    
    /**
     * Get theme-specific color styles
     */
    private function getSidebarThemeStyles($theme) {
        // For wiki theme, try to read colors from template's style.ini
        if ($theme === 'wiki') {
            $wikiColors = $this->getWikiTemplateColors();
            if (!empty($wikiColors)) {
                return $wikiColors;
            }
            // Fall through to default wiki colors if reading fails
        }
        
        $themes = [
            'matrix' => [
                'bg' => '#242424',
                'border' => '#00cc07',
                'shadow' => 'rgba(0, 204, 7, 0.3)',
                'header_bg' => 'linear-gradient(180deg, #2a2a2a 0%, #242424 100%)',
                'header_border' => '#00cc07',
                'header_shadow' => '0 2px 8px rgba(0, 204, 7, 0.3)',
                'text_primary' => '#00cc07',
                'text_bright' => '#00ff00',
                'text_dim' => '#00aa00',
                'grid_bg' => '#1a3d1a',
                'grid_border' => '#00cc07',
                'cell_bg' => '#242424',
                'cell_today_bg' => '#2a4d2a',
                'bar_glow' => '0 0 3px',
                'pastdue_color' => '#e74c3c',
                'pastdue_bg' => '#3d1a1a',
                'pastdue_bg_strong' => '#4d2020',
                'pastdue_bg_light' => '#2d1515',
                'tomorrow_bg' => '#3d3d1a',
                'tomorrow_bg_strong' => '#4d4d20',
                'tomorrow_bg_light' => '#2d2d15',
            ],
            'purple' => [
                'bg' => '#2a2030',
                'border' => '#9b59b6',
                'shadow' => 'rgba(155, 89, 182, 0.3)',
                'header_bg' => 'linear-gradient(180deg, #2f2438 0%, #2a2030 100%)',
                'header_border' => '#9b59b6',
                'header_shadow' => '0 2px 8px rgba(155, 89, 182, 0.3)',
                'text_primary' => '#b19cd9',
                'text_bright' => '#d4a5ff',
                'text_dim' => '#8e7ab8',
                'grid_bg' => '#3d2b4d',
                'grid_border' => '#9b59b6',
                'cell_bg' => '#2a2030',
                'cell_today_bg' => '#3d2b4d',
                'bar_glow' => '0 0 3px',
                'pastdue_color' => '#e74c3c',
                'pastdue_bg' => '#3d1a2a',
                'pastdue_bg_strong' => '#4d2035',
                'pastdue_bg_light' => '#2d1520',
                'tomorrow_bg' => '#3d3520',
                'tomorrow_bg_strong' => '#4d4028',
                'tomorrow_bg_light' => '#2d2a18',
            ],
            'professional' => [
                'bg' => '#f5f7fa',
                'border' => '#4a90e2',
                'shadow' => 'rgba(74, 144, 226, 0.2)',
                'header_bg' => 'linear-gradient(180deg, #ffffff 0%, #f5f7fa 100%)',
                'header_border' => '#4a90e2',
                'header_shadow' => '0 2px 4px rgba(0, 0, 0, 0.1)',
                'text_primary' => '#2c3e50',
                'text_bright' => '#4a90e2',
                'text_dim' => '#7f8c8d',
                'grid_bg' => '#e8ecf1',
                'grid_border' => '#d0d7de',
                'cell_bg' => '#ffffff',
                'cell_today_bg' => '#dce8f7',
                'bar_glow' => '0 1px 2px',
                'pastdue_color' => '#e74c3c',
                'pastdue_bg' => '#ffe6e6',
                'pastdue_bg_strong' => '#ffd9d9',
                'pastdue_bg_light' => '#fff2f2',
                'tomorrow_bg' => '#fff9e6',
                'tomorrow_bg_strong' => '#fff4cc',
                'tomorrow_bg_light' => '#fffbf0',
            ],
            'pink' => [
                'bg' => '#1a0d14',
                'border' => '#ff1493',
                'shadow' => 'rgba(255, 20, 147, 0.4)',
                'header_bg' => 'linear-gradient(180deg, #2d1a24 0%, #1a0d14 100%)',
                'header_border' => '#ff1493',
                'header_shadow' => '0 0 12px rgba(255, 20, 147, 0.6)',
                'text_primary' => '#ff69b4',
                'text_bright' => '#ff1493',
                'text_dim' => '#ff85c1',
                'grid_bg' => '#2d1a24',
                'grid_border' => '#ff1493',
                'cell_bg' => '#1a0d14',
                'cell_today_bg' => '#3d2030',
                'bar_glow' => '0 0 5px',
                'pastdue_color' => '#e74c3c',
                'pastdue_bg' => '#3d1520',
                'pastdue_bg_strong' => '#4d1a28',
                'pastdue_bg_light' => '#2d1018',
                'tomorrow_bg' => '#3d3020',
                'tomorrow_bg_strong' => '#4d3a28',
                'tomorrow_bg_light' => '#2d2518',
            ],
            'wiki' => [
                'bg' => '#f5f5f5',
                'border' => '#ccc',          // Template __border__ color
                'shadow' => 'rgba(0, 0, 0, 0.1)',
                'header_bg' => '#e8e8e8',
                'header_border' => '#ccc',
                'header_shadow' => '0 2px 4px rgba(0, 0, 0, 0.1)',
                'text_primary' => '#333',
                'text_bright' => '#2b73b7',  // Template __link__ color
                'text_dim' => '#666',
                'grid_bg' => '#f5f5f5',
                'grid_border' => '#ccc',
                'cell_bg' => '#fff',
                'cell_today_bg' => '#eee',
                'bar_glow' => '0 1px 2px',
                'pastdue_color' => '#e74c3c',
                'pastdue_bg' => '#ffe6e6',
                'pastdue_bg_strong' => '#ffd9d9',
                'pastdue_bg_light' => '#fff2f2',
                'tomorrow_bg' => '#fff9e6',
                'tomorrow_bg_strong' => '#fff4cc',
                'tomorrow_bg_light' => '#fffbf0',
            ],
        ];
        
        return isset($themes[$theme]) ? $themes[$theme] : $themes['matrix'];
    }
    
    /**
     * Get week start day preference
     */
    private function getWeekStartDay() {
        $configFile = DOKU_INC . 'data/meta/calendar_week_start.txt';
        if (file_exists($configFile)) {
            $start = trim(file_get_contents($configFile));
            if (in_array($start, ['monday', 'sunday'])) {
                return $start;
            }
        }
        return 'sunday'; // Default to Sunday (US/Canada standard)
    }
    
    /**
     * Get itinerary collapsed default state
     */
    private function getItineraryCollapsed() {
        $configFile = DOKU_INC . 'data/meta/calendar_itinerary_collapsed.txt';
        if (file_exists($configFile)) {
            return trim(file_get_contents($configFile)) === 'yes';
        }
        return false; // Default to expanded
    }
    
    /**
     * Get system load bars visibility setting
     */
    private function getShowSystemLoad() {
        $configFile = DOKU_INC . 'data/meta/calendar_show_system_load.txt';
        if (file_exists($configFile)) {
            return trim(file_get_contents($configFile)) !== 'no';
        }
        return true; // Default to showing
    }
}
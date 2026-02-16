/**
 * DokuWiki Compact Calendar Plugin JavaScript
 * Loaded independently to avoid DokuWiki concatenation issues
 * @version 7.0.8
 */

// Debug mode - set to true for console logging
var CALENDAR_DEBUG = false;

// Debug logging helper
function calendarLog() {
    if (CALENDAR_DEBUG && console && console.log) {
        console.log.apply(console, ['[Calendar]'].concat(Array.prototype.slice.call(arguments)));
    }
}

function calendarError() {
    if (console && console.error) {
        console.error.apply(console, ['[Calendar]'].concat(Array.prototype.slice.call(arguments)));
    }
}

/**
 * Format a Date object as YYYY-MM-DD in LOCAL time (not UTC)
 * This avoids timezone issues where toISOString() shifts dates
 * For example: In Prague (UTC+1), midnight local = 23:00 UTC previous day
 * @param {Date} date - Date object to format
 * @returns {string} Date string in YYYY-MM-DD format
 */
function formatLocalDate(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

// Ensure DOKU_BASE is defined - check multiple sources
if (typeof DOKU_BASE === 'undefined') {
    // Try to get from global jsinfo object (DokuWiki standard)
    if (typeof window.jsinfo !== 'undefined' && window.jsinfo.dokubase) {
        window.DOKU_BASE = window.jsinfo.dokubase;
    } else {
        // Fallback: extract from script source path
        var scripts = document.getElementsByTagName('script');
        var pluginScriptPath = null;
        for (var i = 0; i < scripts.length; i++) {
            if (scripts[i].src && scripts[i].src.indexOf('calendar/script.js') !== -1) {
                pluginScriptPath = scripts[i].src;
                break;
            }
        }
        
        if (pluginScriptPath) {
            // Extract base path from: .../lib/plugins/calendar/script.js
            var match = pluginScriptPath.match(/^(.*?)lib\/plugins\//);
            window.DOKU_BASE = match ? match[1] : '/';
        } else {
            // Last resort: use root
            window.DOKU_BASE = '/';
        }
    }
}

// Shorthand for convenience  
var DOKU_BASE = window.DOKU_BASE || '/';

/**
 * Get DokuWiki security token from multiple possible sources
 * DokuWiki stores this in different places depending on version/config
 */
function getSecurityToken() {
    // Try JSINFO.sectok (standard location)
    if (typeof JSINFO !== 'undefined' && JSINFO.sectok) {
        return JSINFO.sectok;
    }
    // Try window.JSINFO
    if (typeof window.JSINFO !== 'undefined' && window.JSINFO.sectok) {
        return window.JSINFO.sectok;
    }
    // Try finding it in a hidden form field (some templates/plugins add this)
    var sectokInput = document.querySelector('input[name="sectok"]');
    if (sectokInput && sectokInput.value) {
        return sectokInput.value;
    }
    // Try meta tag (some DokuWiki setups)
    var sectokMeta = document.querySelector('meta[name="sectok"]');
    if (sectokMeta && sectokMeta.content) {
        return sectokMeta.content;
    }
    // Return empty string if not found
    console.warn('Calendar plugin: Security token not found');
    return '';
}

// Helper: propagate CSS variables from a calendar container to a target element
// This is needed for dialogs/popups that use position:fixed (they inherit CSS vars
// from DOM parents per spec, but some DokuWiki templates break this inheritance)
function propagateThemeVars(calId, targetEl) {
    if (!targetEl) return;
    // Find the calendar container (could be cal_, panel_, sidebar-widget-, etc.)
    const container = document.getElementById(calId) 
        || document.getElementById('sidebar-widget-' + calId)
        || document.querySelector('[id$="' + calId + '"]');
    if (!container) return;
    const cs = getComputedStyle(container);
    const vars = [
        '--background-site', '--background-alt', '--background-header',
        '--text-primary', '--text-bright', '--text-dim',
        '--border-color', '--border-main',
        '--cell-bg', '--cell-today-bg', '--grid-bg',
        '--shadow-color', '--header-border', '--header-shadow',
        '--btn-text'
    ];
    vars.forEach(v => {
        const val = cs.getPropertyValue(v).trim();
        if (val) targetEl.style.setProperty(v, val);
    });
}

// Filter calendar by namespace
window.filterCalendarByNamespace = function(calId, namespace) {
    // Get current year and month from calendar
    const container = document.getElementById(calId);
    if (!container) {
        console.error('Calendar container not found:', calId);
        return;
    }
    
    const year = parseInt(container.dataset.year) || new Date().getFullYear();
    const month = parseInt(container.dataset.month) || (new Date().getMonth() + 1);
    
    // Reload calendar with the filtered namespace
    navCalendar(calId, year, month, namespace);
};

// Navigate to different month
window.navCalendar = function(calId, year, month, namespace) {
    
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'load_month',
        year: year,
        month: month,
        namespace: namespace,
        _: new Date().getTime() // Cache buster
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            rebuildCalendar(calId, data.year, data.month, data.events, namespace);
        } else {
            console.error('Failed to load month:', data.error);
        }
    })
    .catch(err => {
        console.error('Error loading month:', err);
    });
};

// Jump to current month
window.jumpToToday = function(calId, namespace) {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1; // JavaScript months are 0-indexed
    navCalendar(calId, year, month, namespace);
};

// Jump to today for event panel
window.jumpTodayPanel = function(calId, namespace) {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    navEventPanel(calId, year, month, namespace);
};

// Open month picker dialog
window.openMonthPicker = function(calId, currentYear, currentMonth, namespace) {
    
    const overlay = document.getElementById('month-picker-overlay-' + calId);
    
    const monthSelect = document.getElementById('month-picker-month-' + calId);
    
    const yearSelect = document.getElementById('month-picker-year-' + calId);
    
    if (!overlay) {
        console.error('Month picker overlay not found! ID:', 'month-picker-overlay-' + calId);
        return;
    }
    
    if (!monthSelect || !yearSelect) {
        console.error('Select elements not found!');
        return;
    }
    
    // Set current values
    monthSelect.value = currentMonth;
    yearSelect.value = currentYear;
    
    // Show overlay
    overlay.style.display = 'flex';
};

// Open month picker dialog for event panel
window.openMonthPickerPanel = function(calId, currentYear, currentMonth, namespace) {
    openMonthPicker(calId, currentYear, currentMonth, namespace);
};

// Close month picker dialog
window.closeMonthPicker = function(calId) {
    const overlay = document.getElementById('month-picker-overlay-' + calId);
    overlay.style.display = 'none';
};

// Jump to selected month
window.jumpToSelectedMonth = function(calId, namespace) {
    const monthSelect = document.getElementById('month-picker-month-' + calId);
    const yearSelect = document.getElementById('month-picker-year-' + calId);
    
    const month = parseInt(monthSelect.value);
    const year = parseInt(yearSelect.value);
    
    closeMonthPicker(calId);
    
    // Check if this is a calendar or event panel
    const container = document.getElementById(calId);
    if (container && container.classList.contains('event-panel-standalone')) {
        navEventPanel(calId, year, month, namespace);
    } else {
        navCalendar(calId, year, month, namespace);
    }
};

// Rebuild calendar grid after navigation
window.rebuildCalendar = function(calId, year, month, events, namespace) {
    
    const container = document.getElementById(calId);
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    
    // Get theme data from container
    const theme = container.dataset.theme || 'matrix';
    let themeStyles = {};
    try {
        themeStyles = JSON.parse(container.dataset.themeStyles || '{}');
    } catch (e) {
        console.error('Failed to parse theme styles:', e);
        themeStyles = {};
    }
    
    // Preserve original namespace if not yet set
    if (!container.dataset.originalNamespace) {
        container.setAttribute('data-original-namespace', namespace || '');
    }
    
    // Update container data attributes for current month/year
    container.setAttribute('data-year', year);
    container.setAttribute('data-month', month);
    
    // Update embedded events data
    let eventsDataEl = document.getElementById('events-data-' + calId);
    if (eventsDataEl) {
        eventsDataEl.textContent = JSON.stringify(events);
    } else {
        eventsDataEl = document.createElement('script');
        eventsDataEl.type = 'application/json';
        eventsDataEl.id = 'events-data-' + calId;
        eventsDataEl.textContent = JSON.stringify(events);
        container.appendChild(eventsDataEl);
    }
    
    // Update header
    const header = container.querySelector('.calendar-compact-header h3');
    header.textContent = monthNames[month - 1] + ' ' + year;
    
    // Update or create namespace filter indicator
    let filterIndicator = container.querySelector('.calendar-namespace-filter');
    const shouldShowFilter = namespace && namespace !== '' && namespace !== '*' && 
                            namespace.indexOf('*') === -1 && namespace.indexOf(';') === -1;
    
    if (shouldShowFilter) {
        // Show/update filter indicator
        if (!filterIndicator) {
            // Create filter indicator if it doesn't exist
            const headerDiv = container.querySelector('.calendar-compact-header');
            if (headerDiv) {
                filterIndicator = document.createElement('div');
                filterIndicator.className = 'calendar-namespace-filter';
                filterIndicator.id = 'namespace-filter-' + calId;
                headerDiv.parentNode.insertBefore(filterIndicator, headerDiv.nextSibling);
            }
        }
        
        if (filterIndicator) {
            filterIndicator.innerHTML = 
                '<span class="namespace-filter-label">Filtering:</span>' +
                '<span class="namespace-filter-name">' + escapeHtml(namespace) + '</span>' +
                '<button class="namespace-filter-clear" onclick="clearNamespaceFilter(\'' + calId + '\')" title="Clear filter and show all namespaces">✕</button>';
            filterIndicator.style.display = 'flex';
        }
    } else {
        // Hide filter indicator
        if (filterIndicator) {
            filterIndicator.style.display = 'none';
        }
    }
    
    // Update container's namespace attribute
    container.setAttribute('data-namespace', namespace || '');
    
    // Update nav buttons
    let prevMonth = month - 1;
    let prevYear = year;
    if (prevMonth < 1) {
        prevMonth = 12;
        prevYear--;
    }
    
    let nextMonth = month + 1;
    let nextYear = year;
    if (nextMonth > 12) {
        nextMonth = 1;
        nextYear++;
    }
    
    const navBtns = container.querySelectorAll('.cal-nav-btn');
    navBtns[0].setAttribute('onclick', `navCalendar('${calId}', ${prevYear}, ${prevMonth}, '${namespace}')`);
    navBtns[1].setAttribute('onclick', `navCalendar('${calId}', ${nextYear}, ${nextMonth}, '${namespace}')`);
    
    // Rebuild calendar grid
    const tbody = container.querySelector('.calendar-compact-grid tbody');
    const firstDay = new Date(year, month - 1, 1);
    const daysInMonth = new Date(year, month, 0).getDate();
    const dayOfWeek = firstDay.getDay();
    
    // Calculate month boundaries
    const monthStart = new Date(year, month - 1, 1);
    const monthEnd = new Date(year, month - 1, daysInMonth);
    
    // Build a map of all events with their date ranges
    const eventRanges = {};
    for (const [dateKey, dayEvents] of Object.entries(events)) {
        // Defensive check: ensure dayEvents is an array
        if (!Array.isArray(dayEvents)) {
            console.error('dayEvents is not an array for dateKey:', dateKey, 'value:', dayEvents);
            continue;
        }
        
        // Only process events that could possibly overlap with this month/year
        const dateYear = parseInt(dateKey.split('-')[0]);
        
        // Skip events from completely different years (unless they're very long multi-day events)
        if (Math.abs(dateYear - year) > 1) {
            continue;
        }
        
        for (const evt of dayEvents) {
            const startDate = dateKey;
            const endDate = evt.endDate || dateKey;
            
            // Check if event overlaps with current month
            const eventStart = new Date(startDate + 'T00:00:00');
            const eventEnd = new Date(endDate + 'T00:00:00');
            
            // Skip if event doesn't overlap with current month
            if (eventEnd < monthStart || eventStart > monthEnd) {
                continue;
            }
            
            // Create entry for each day the event spans
            const start = new Date(startDate + 'T00:00:00');
            const end = new Date(endDate + 'T00:00:00');
            const current = new Date(start);
            
            while (current <= end) {
                // Use formatLocalDate to avoid timezone shift issues
                const currentKey = formatLocalDate(current);
                
                // Check if this date is in current month (use current Date object directly)
                if (current.getFullYear() === year && current.getMonth() === month - 1) {
                    if (!eventRanges[currentKey]) {
                        eventRanges[currentKey] = [];
                    }
                    
                    // Add event with span information
                    const eventCopy = {...evt};
                    eventCopy._span_start = startDate;
                    eventCopy._span_end = endDate;
                    eventCopy._is_first_day = (currentKey === startDate);
                    eventCopy._is_last_day = (currentKey === endDate);
                    eventCopy._original_date = dateKey;
                    
                    // Check if event continues from previous month or to next month
                    eventCopy._continues_from_prev = (eventStart < monthStart);
                    eventCopy._continues_to_next = (eventEnd > monthEnd);
                    
                    eventRanges[currentKey].push(eventCopy);
                }
                
                current.setDate(current.getDate() + 1);
            }
        }
    }
    
    let html = '';
    let currentDay = 1;
    const rowCount = Math.ceil((daysInMonth + dayOfWeek) / 7);
    
    for (let row = 0; row < rowCount; row++) {
        html += '<tr>';
        for (let col = 0; col < 7; col++) {
            if ((row === 0 && col < dayOfWeek) || currentDay > daysInMonth) {
                html += `<td class="cal-empty"></td>`;
            } else {
                const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(currentDay).padStart(2, '0')}`;
                
                // Get today's date in local timezone
                const todayObj = new Date();
                const today = `${todayObj.getFullYear()}-${String(todayObj.getMonth() + 1).padStart(2, '0')}-${String(todayObj.getDate()).padStart(2, '0')}`;
                
                const isToday = dateKey === today;
                const hasEvents = eventRanges[dateKey] && eventRanges[dateKey].length > 0;
                
                let classes = 'cal-day';
                if (isToday) classes += ' cal-today';
                if (hasEvents) classes += ' cal-has-events';
                
                const dayNumClass = isToday ? 'day-num day-num-today' : 'day-num';
                
                html += `<td class="${classes}" data-date="${dateKey}" onclick="showDayPopup('${calId}', '${dateKey}', '${namespace}')">`;
                html += `<span class="${dayNumClass}">${currentDay}</span>`;
                
                if (hasEvents) {
                    // Sort events by time (no time first, then by time)
                    const sortedEvents = [...eventRanges[dateKey]].sort((a, b) => {
                        const timeA = a.time || '';
                        const timeB = b.time || '';
                        if (!timeA && timeB) return -1;
                        if (timeA && !timeB) return 1;
                        if (!timeA && !timeB) return 0;
                        return timeA.localeCompare(timeB);
                    });
                    
                    // Get important namespaces
                    let importantNamespaces = ['important'];
                    if (container.dataset.importantNamespaces) {
                        try {
                            importantNamespaces = JSON.parse(container.dataset.importantNamespaces);
                        } catch (e) {}
                    }
                    
                    // Show colored stacked bars for each event
                    html += '<div class="event-indicators">';
                    for (const evt of sortedEvents) {
                        const eventId = evt.id || '';
                        const eventColor = evt.color || '#3498db';
                        const eventTitle = evt.title || 'Event';
                        const eventTime = evt.time || '';
                        const originalDate = evt._original_date || dateKey;
                        const isFirstDay = evt._is_first_day !== undefined ? evt._is_first_day : true;
                        const isLastDay = evt._is_last_day !== undefined ? evt._is_last_day : true;
                        
                        // Check if important namespace
                        let evtNs = evt.namespace || evt._namespace || '';
                        let isImportant = false;
                        for (const impNs of importantNamespaces) {
                            if (evtNs === impNs || evtNs.startsWith(impNs + ':')) {
                                isImportant = true;
                                break;
                            }
                        }
                        
                        let barClass = !eventTime ? 'event-bar-no-time' : 'event-bar-timed';
                        if (!isFirstDay) barClass += ' event-bar-continues';
                        if (!isLastDay) barClass += ' event-bar-continuing';
                        if (isImportant) {
                            barClass += ' event-bar-important';
                            if (isFirstDay) {
                                barClass += ' event-bar-has-star';
                            }
                        }
                        
                        html += `<span class="event-bar ${barClass}" `;
                        html += `style="background: ${eventColor};" `;
                        html += `title="${isImportant ? '⭐ ' : ''}${escapeHtml(eventTitle)}${eventTime ? ' @ ' + eventTime : ''}" `;
                        html += `onclick="event.stopPropagation(); highlightEvent('${calId}', '${eventId}', '${originalDate}');">`;
                        html += '</span>';
                    }
                    html += '</div>';
                }
                
                html += '</td>';
                currentDay++;
            }
        }
        html += '</tr>';
    }
    
    tbody.innerHTML = html;
    
    // Update Today button with current namespace
    const todayBtn = container.querySelector('.cal-today-btn');
    if (todayBtn) {
        todayBtn.setAttribute('onclick', `jumpToToday('${calId}', '${namespace}')`);
    }
    
    // Update month picker with current namespace
    const monthPicker = container.querySelector('.calendar-month-picker');
    if (monthPicker) {
        monthPicker.setAttribute('onclick', `openMonthPicker('${calId}', ${year}, ${month}, '${namespace}')`);
    }
    
    // Rebuild event list - server already filtered to current month
    const eventList = container.querySelector('.event-list-compact');
    eventList.innerHTML = renderEventListFromData(events, calId, namespace, year, month);
    
    // Auto-scroll to first future event (past events will be above viewport)
    setTimeout(() => {
        const firstFuture = eventList.querySelector('[data-first-future="true"]');
        if (firstFuture) {
            firstFuture.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 100);
    
    // Update title
    const title = container.querySelector('#eventlist-title-' + calId);
    title.textContent = 'Events';
};

// Render event list from data
window.renderEventListFromData = function(events, calId, namespace, year, month) {
    if (!events || Object.keys(events).length === 0) {
        return '<p class="no-events-msg">No events this month</p>';
    }
    
    // Get theme data from container
    const container = document.getElementById(calId);
    let themeStyles = {};
    if (container && container.dataset.themeStyles) {
        try {
            themeStyles = JSON.parse(container.dataset.themeStyles);
        } catch (e) {
            console.error('Failed to parse theme styles in renderEventListFromData:', e);
        }
    }
    
    // Check for time conflicts
    events = checkTimeConflicts(events, null);
    
    let pastHtml = '';
    let futureHtml = '';
    let pastCount = 0;
    
    const sortedDates = Object.keys(events).sort();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = formatLocalDate(today);
    
    // Helper function to check if event is past (with 15-minute grace period)
    const isEventPast = function(dateKey, time) {
        // If event is on a past date, it's definitely past
        if (dateKey < todayStr) {
            return true;
        }
        
        // If event is on a future date, it's definitely not past
        if (dateKey > todayStr) {
            return false;
        }
        
        // Event is today - check time with grace period
        if (time && time.trim() !== '') {
            try {
                const now = new Date();
                const eventDateTime = new Date(dateKey + 'T' + time);
                
                // Add 15-minute grace period
                const gracePeriodEnd = new Date(eventDateTime.getTime() + 15 * 60 * 1000);
                
                // Event is past if current time > event time + 15 minutes
                return now > gracePeriodEnd;
            } catch (e) {
                // If time parsing fails, treat as future
                return false;
            }
        }
        
        // No time specified for today's event, treat as future
        return false;
    };
    
    // Filter events to only current month if year/month provided
    const monthStart = year && month ? new Date(year, month - 1, 1) : null;
    const monthEnd = year && month ? new Date(year, month, 0, 23, 59, 59) : null;
    
    for (const dateKey of sortedDates) {
        // Skip events not in current month if filtering
        if (monthStart && monthEnd) {
            const eventDate = new Date(dateKey + 'T00:00:00');
            
            if (eventDate < monthStart || eventDate > monthEnd) {
                continue;
            }
        }
        
        // Sort events within this day by time (all-day events at top)
        const dayEvents = events[dateKey];
        dayEvents.sort((a, b) => {
            const timeA = a.time && a.time.trim() !== '' ? a.time : null;
            const timeB = b.time && b.time.trim() !== '' ? b.time : null;
            
            // All-day events (no time) go to the TOP
            if (timeA === null && timeB !== null) return -1; // A before B
            if (timeA !== null && timeB === null) return 1;  // A after B
            if (timeA === null && timeB === null) return 0;  // Both all-day, equal
            
            // Both have times, sort chronologically
            return timeA.localeCompare(timeB);
        });
        
        for (const event of dayEvents) {
            const isTask = event.isTask || false;
            const completed = event.completed || false;
            
            // Use helper function to determine if event is past (with grace period)
            const isPast = isEventPast(dateKey, event.time);
            const isPastDue = isPast && isTask && !completed;
            
            // Determine if this goes in past section
            const isPastOrCompleted = (isPast && (!isTask || completed)) || completed;
            
            const eventHtml = renderEventItem(event, dateKey, calId, namespace);
            
            if (isPastOrCompleted) {
                pastCount++;
                pastHtml += eventHtml;
            } else {
                futureHtml += eventHtml;
            }
        }
    }
    
    let html = '';
    
    // Add collapsible past events section if any exist
    if (pastCount > 0) {
        html += '<div class="past-events-section">';
        html += '<div class="past-events-toggle" onclick="togglePastEvents(\'' + calId + '\')">';
        html += '<span class="past-events-arrow" id="past-arrow-' + calId + '">▶</span> ';
        html += '<span class="past-events-label">Past Events (' + pastCount + ')</span>';
        html += '</div>';
        html += '<div class="past-events-content" id="past-events-' + calId + '" style="display:none;">';
        html += pastHtml;
        html += '</div>';
        html += '</div>';
    } else {
    }
    
    // Add future events
    html += futureHtml;
    
    
    if (!html) {
        return '<p class="no-events-msg">No events this month</p>';
    }
    
    return html;
};

// Show day popup with events when clicking a date
window.showDayPopup = function(calId, date, namespace) {
    // Get events for this calendar
    const eventsDataEl = document.getElementById('events-data-' + calId);
    let events = {};
    
    if (eventsDataEl) {
        try {
            events = JSON.parse(eventsDataEl.textContent);
        } catch (e) {
            console.error('Failed to parse events data:', e);
        }
    }
    
    const dayEvents = events[date] || [];
    
    // Check for conflicts on this day
    const dayEventsObj = {[date]: dayEvents};
    const checkedEvents = checkTimeConflicts(dayEventsObj, null);
    const dayEventsWithConflicts = checkedEvents[date] || dayEvents;
    
    // Sort events: all-day at top, then chronological by time
    dayEventsWithConflicts.sort((a, b) => {
        const timeA = a.time && a.time.trim() !== '' ? a.time : null;
        const timeB = b.time && b.time.trim() !== '' ? b.time : null;
        
        // All-day events (no time) go to the TOP
        if (timeA === null && timeB !== null) return -1; // A before B
        if (timeA !== null && timeB === null) return 1;  // A after B
        if (timeA === null && timeB === null) return 0;  // Both all-day, equal
        
        // Both have times, sort chronologically
        return timeA.localeCompare(timeB);
    });
    
    const dateObj = new Date(date + 'T00:00:00');
    const displayDate = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        month: 'long', 
        day: 'numeric',
        year: 'numeric'
    });
    
    // Create popup
    let popup = document.getElementById('day-popup-' + calId);
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'day-popup-' + calId;
        popup.className = 'day-popup';
        document.body.appendChild(popup);
    }
    
    // Get theme styles and important namespaces
    const container = document.getElementById(calId);
    const themeStyles = container ? JSON.parse(container.dataset.themeStyles || '{}') : {};
    const theme = container ? container.dataset.theme : 'matrix';
    
    // Get important namespaces
    let importantNamespaces = ['important'];
    if (container && container.dataset.importantNamespaces) {
        try {
            importantNamespaces = JSON.parse(container.dataset.importantNamespaces);
        } catch (e) {
            importantNamespaces = ['important'];
        }
    }
    
    let html = '<div class="day-popup-overlay" onclick="closeDayPopup(\'' + calId + '\')"></div>';
    html += '<div class="day-popup-content">';
    html += '<div class="day-popup-header">';
    html += '<h4>' + displayDate + '</h4>';
    html += '<button class="popup-close" onclick="closeDayPopup(\'' + calId + '\')">×</button>';
    html += '</div>';
    
    html += '<div class="day-popup-body">';
    
    if (dayEventsWithConflicts.length === 0) {
        html += '<p class="no-events-msg">No events on this day</p>';
    } else {
        html += '<div class="popup-events-list">';
        dayEventsWithConflicts.forEach(event => {
            const color = event.color || '#3498db';
            
            // Use individual event namespace if available (for multi-namespace support)
            const eventNamespace = event._namespace !== undefined ? event._namespace : namespace;
            
            // Check if this is an important namespace event
            let isImportant = false;
            if (eventNamespace) {
                for (const impNs of importantNamespaces) {
                    if (eventNamespace === impNs || eventNamespace.startsWith(impNs + ':')) {
                        isImportant = true;
                        break;
                    }
                }
            }
            
            // Check if this is a continuation (event started before this date)
            const originalStartDate = event.originalStartDate || event._dateKey || date;
            const isContinuation = originalStartDate < date;
            
            // Convert to 12-hour format and handle time ranges
            let displayTime = '';
            if (event.time) {
                displayTime = formatTimeRange(event.time, event.endTime);
            }
            
            // Multi-day indicator
            let multiDay = '';
            if (event.endDate && event.endDate !== date) {
                const endObj = new Date(event.endDate + 'T00:00:00');
                multiDay = ' → ' + endObj.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric' 
                });
            }
            
            // Continuation message
            if (isContinuation) {
                const startObj = new Date(originalStartDate + 'T00:00:00');
                const startDisplay = startObj.toLocaleDateString('en-US', { 
                    weekday: 'short',
                    month: 'short', 
                    day: 'numeric' 
                });
                html += '<div class="popup-continuation-notice">↪ Continues from ' + startDisplay + '</div>';
            }
            
            const importantClass = isImportant ? ' popup-event-important' : '';
            html += '<div class="popup-event-item' + importantClass + '" tabindex="0" role="listitem" aria-label="' + escapeHtml(event.title) + (displayTime ? ', ' + displayTime : '') + '">';
            html += '<div class="event-color-bar" style="background: ' + color + ';"></div>';
            html += '<div class="popup-event-content">';
            
            // Single line with title, time, date range, namespace, and actions
            html += '<div class="popup-event-main-row">';
            html += '<div class="popup-event-info-inline">';
            
            // Add star for important events
            if (isImportant) {
                html += '<span class="popup-event-star">⭐</span>';
            }
            
            html += '<span class="popup-event-title">' + escapeHtml(event.title) + '</span>';
            if (displayTime) {
                html += '<span class="popup-event-time">🕐 ' + displayTime + '</span>';
            }
            if (multiDay) {
                html += '<span class="popup-event-multiday">' + multiDay + '</span>';
            }
            if (eventNamespace) {
                html += '<span class="popup-event-namespace">' + escapeHtml(eventNamespace) + '</span>';
            }
            
            // Add conflict warning badge if event has conflicts
            if (event.hasConflict && event.conflictsWith && event.conflictsWith.length > 0) {
                // Build conflict list for tooltip
                let conflictList = [];
                event.conflictsWith.forEach(conflict => {
                    let conflictText = conflict.title;
                    if (conflict.time) {
                        conflictText += ' (' + formatTimeRange(conflict.time, conflict.endTime) + ')';
                    }
                    conflictList.push(conflictText);
                });
                
                html += '<span class="event-conflict-badge" data-conflicts="' + btoa(unescape(encodeURIComponent(JSON.stringify(conflictList)))) + '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' + event.conflictsWith.length + '</span>';
            }
            
            html += '</div>';
            html += '<div class="popup-event-actions">';
            html += '<button type="button" class="event-edit-btn" onclick="editEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + eventNamespace + '\'); closeDayPopup(\'' + calId + '\')">✏️</button>';
            html += '<button type="button" class="event-delete-btn" onclick="deleteEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + eventNamespace + '\'); closeDayPopup(\'' + calId + '\')">🗑️</button>';
            html += '</div>';
            html += '</div>';
            
            // Description on separate line if present
            if (event.description) {
                html += '<div class="popup-event-desc">' + renderDescription(event.description) + '</div>';
            }
            
            html += '</div></div>';
        });
        html += '</div>';
    }
    
    html += '</div>';
    
    html += '<div class="day-popup-footer">';
    html += '<button class="btn-add-event" onclick="openAddEvent(\'' + calId + '\', \'' + namespace + '\', \'' + date + '\'); closeDayPopup(\'' + calId + '\')">+ Add Event</button>';
    html += '</div>';
    
    html += '</div>';
    
    popup.innerHTML = html;
    popup.style.display = 'flex';
    
    // Propagate CSS vars from calendar container to popup (popup is outside container in DOM)
    if (container) {
        propagateThemeVars(calId, popup.querySelector('.day-popup-content'));
    }
    
    // Make popup draggable by header
    const popupContent = popup.querySelector('.day-popup-content');
    const popupHeader = popup.querySelector('.day-popup-header');
    
    if (popupContent && popupHeader) {
        // Reset position to center
        popupContent.style.position = 'relative';
        popupContent.style.left = '0';
        popupContent.style.top = '0';
        
        // Store drag state on the element itself
        popupHeader._isDragging = false;
        
        popupHeader.onmousedown = function(e) {
            // Ignore if clicking the close button
            if (e.target.classList.contains('popup-close')) return;
            
            popupHeader._isDragging = true;
            popupHeader._dragStartX = e.clientX;
            popupHeader._dragStartY = e.clientY;
            
            const rect = popupContent.getBoundingClientRect();
            const parentRect = popup.getBoundingClientRect();
            popupHeader._initialLeft = rect.left - parentRect.left - (parentRect.width / 2 - rect.width / 2);
            popupHeader._initialTop = rect.top - parentRect.top - (parentRect.height / 2 - rect.height / 2);
            
            popupContent.style.transition = 'none';
            e.preventDefault();
        };
        
        popup.onmousemove = function(e) {
            if (!popupHeader._isDragging) return;
            
            const deltaX = e.clientX - popupHeader._dragStartX;
            const deltaY = e.clientY - popupHeader._dragStartY;
            
            popupContent.style.left = (popupHeader._initialLeft + deltaX) + 'px';
            popupContent.style.top = (popupHeader._initialTop + deltaY) + 'px';
        };
        
        popup.onmouseup = function() {
            if (popupHeader._isDragging) {
                popupHeader._isDragging = false;
                popupContent.style.transition = '';
            }
        };
        
        popup.onmouseleave = function() {
            if (popupHeader._isDragging) {
                popupHeader._isDragging = false;
                popupContent.style.transition = '';
            }
        };
    }
};

// Close day popup
window.closeDayPopup = function(calId) {
    const popup = document.getElementById('day-popup-' + calId);
    if (popup) {
        popup.style.display = 'none';
    }
};

// Show events for a specific day (for event list panel)
window.showDayEvents = function(calId, date, namespace) {
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'load_month',
        year: date.split('-')[0],
        month: parseInt(date.split('-')[1]),
        namespace: namespace,
        _: new Date().getTime() // Cache buster
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const eventList = document.getElementById('eventlist-' + calId);
            const events = data.events;
            const title = document.getElementById('eventlist-title-' + calId);
            
            const dateObj = new Date(date + 'T00:00:00');
            const displayDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric' 
            });
            
            title.textContent = 'Events - ' + displayDate;
            
            // Filter events for this day
            const dayEvents = events[date] || [];
            
            if (dayEvents.length === 0) {
                eventList.innerHTML = '<p class="no-events-msg">No events on this day<br><button class="add-event-compact" onclick="openAddEvent(\'' + calId + '\', \'' + namespace + '\', \'' + date + '\')">+ Add Event</button></p>';
            } else {
                let html = '';
                dayEvents.forEach(event => {
                    html += renderEventItem(event, date, calId, namespace);
                });
                eventList.innerHTML = html;
            }
        }
    })
    .catch(err => console.error('Error:', err));
};

// Render a single event item
window.renderEventItem = function(event, date, calId, namespace) {
    // Get theme data from container
    const container = document.getElementById(calId);
    let themeStyles = {};
    let importantNamespaces = ['important']; // default
    if (container && container.dataset.themeStyles) {
        try {
            themeStyles = JSON.parse(container.dataset.themeStyles);
        } catch (e) {
            console.error('Failed to parse theme styles:', e);
        }
    }
    // Get important namespaces from container data attribute
    if (container && container.dataset.importantNamespaces) {
        try {
            importantNamespaces = JSON.parse(container.dataset.importantNamespaces);
        } catch (e) {
            importantNamespaces = ['important'];
        }
    }
    
    // Check if this event is in the past or today (with 15-minute grace period)
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = formatLocalDate(today);
    const eventDate = new Date(date + 'T00:00:00');
    
    // Helper to determine if event is past with grace period
    let isPast;
    if (date < todayStr) {
        isPast = true; // Past date
    } else if (date > todayStr) {
        isPast = false; // Future date
    } else {
        // Today - check time with grace period
        if (event.time && event.time.trim() !== '') {
            try {
                const now = new Date();
                const eventDateTime = new Date(date + 'T' + event.time);
                const gracePeriodEnd = new Date(eventDateTime.getTime() + 15 * 60 * 1000);
                isPast = now > gracePeriodEnd;
            } catch (e) {
                isPast = false;
            }
        } else {
            isPast = false; // No time, treat as future
        }
    }
    
    const isToday = eventDate.getTime() === today.getTime();
    
    // Check if this is an important namespace event
    let eventNamespace = event.namespace || '';
    if (!eventNamespace && event._namespace !== undefined) {
        eventNamespace = event._namespace;
    }
    let isImportantNs = false;
    if (eventNamespace) {
        for (const impNs of importantNamespaces) {
            if (eventNamespace === impNs || eventNamespace.startsWith(impNs + ':')) {
                isImportantNs = true;
                break;
            }
        }
    }
    
    // Format date display with day of week
    const displayDateKey = event.originalStartDate || date;
    const dateObj = new Date(displayDateKey + 'T00:00:00');
    const displayDate = dateObj.toLocaleDateString('en-US', { 
        weekday: 'short',
        month: 'short', 
        day: 'numeric' 
    });
    
    // Convert to 12-hour format and handle time ranges
    let displayTime = '';
    if (event.time) {
        displayTime = formatTimeRange(event.time, event.endTime);
    }
    
    // Multi-day indicator
    let multiDay = '';
    if (event.endDate && event.endDate !== displayDateKey) {
        const endObj = new Date(event.endDate + 'T00:00:00');
        multiDay = ' → ' + endObj.toLocaleDateString('en-US', { 
            weekday: 'short',
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    const completedClass = event.completed ? ' event-completed' : '';
    const isTask = event.isTask || false;
    const completed = event.completed || false;
    const isPastDue = isPast && isTask && !completed;
    const pastClass = (isPast && !isPastDue) ? ' event-past' : '';
    const pastDueClass = isPastDue ? ' event-pastdue' : '';
    const importantClass = isImportantNs ? ' event-important' : '';
    const color = event.color || '#3498db';
    
    // Only inline style needed: border-left-color for event color indicator
    let html = '<div class="event-compact-item' + completedClass + pastClass + pastDueClass + importantClass + '" data-event-id="' + event.id + '" data-date="' + date + '" style="border-left-color: ' + color + ' !important;" onclick="' + (isPast && !isPastDue ? 'togglePastEventExpand(this)' : '') + '">';
    
    html += '<div class="event-info">';
    html += '<div class="event-title-row">';
    // Add star for important namespace events
    if (isImportantNs) {
        html += '<span class="event-important-star" title="Important">⭐</span> ';
    }
    html += '<span class="event-title-compact">' + escapeHtml(event.title) + '</span>';
    html += '</div>';
    
    // Show meta and description for non-past events AND past due tasks
    if (!isPast || isPastDue) {
        html += '<div class="event-meta-compact">';
        html += '<span class="event-date-time">' + displayDate + multiDay;
        if (displayTime) {
            html += ' • ' + displayTime;
        }
        // Add PAST DUE or TODAY badge
        if (isPastDue) {
            html += ' <span class="event-pastdue-badge" style="background:var(--pastdue-color, #e74c3c) !important; color:white !important; -webkit-text-fill-color:white !important;">PAST DUE</span>';
        } else if (isToday) {
            html += ' <span class="event-today-badge" style="background:var(--border-main, #9b59b6) !important; color:var(--background-site, white) !important; -webkit-text-fill-color:var(--background-site, white) !important;">TODAY</span>';
        }
        // Add namespace badge
        if (eventNamespace) {
            html += ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' + calId + '\', \'' + escapeHtml(eventNamespace) + '\')" style="background:var(--text-bright, #008800) !important; color:var(--background-site, white) !important; -webkit-text-fill-color:var(--background-site, white) !important;" title="Click to filter by this namespace">' + escapeHtml(eventNamespace) + '</span>';
        }
        // Add conflict warning if event has time conflicts
        if (event.hasConflict && event.conflictsWith && event.conflictsWith.length > 0) {
            let conflictList = [];
            event.conflictsWith.forEach(conflict => {
                let conflictText = conflict.title;
                if (conflict.time) {
                    conflictText += ' (' + formatTimeRange(conflict.time, conflict.endTime) + ')';
                }
                conflictList.push(conflictText);
            });
            
            html += ' <span class="event-conflict-badge" data-conflicts="' + btoa(unescape(encodeURIComponent(JSON.stringify(conflictList)))) + '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' + event.conflictsWith.length + '</span>';
        }
        html += '</span>';
        html += '</div>';
        
        if (event.description) {
            html += '<div class="event-desc-compact">' + renderDescription(event.description) + '</div>';
        }
    } else {
        // For past events (not past due), store data in hidden divs for expand/collapse
        html += '<div class="event-meta-compact" style="display: none;">';
        html += '<span class="event-date-time">' + displayDate + multiDay;
        if (displayTime) {
            html += ' • ' + displayTime;
        }
        // Add namespace badge for past events too
        let eventNamespace = event.namespace || '';
        if (!eventNamespace && event._namespace !== undefined) {
            eventNamespace = event._namespace;
        }
        if (eventNamespace) {
            html += ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' + calId + '\', \'' + escapeHtml(eventNamespace) + '\')" style="background:var(--text-bright, #008800) !important; color:var(--background-site, white) !important; -webkit-text-fill-color:var(--background-site, white) !important;" title="Click to filter by this namespace">' + escapeHtml(eventNamespace) + '</span>';
        }
        // Add conflict warning for past events too
        if (event.hasConflict && event.conflictsWith && event.conflictsWith.length > 0) {
            let conflictList = [];
            event.conflictsWith.forEach(conflict => {
                let conflictText = conflict.title;
                if (conflict.time) {
                    conflictText += ' (' + formatTimeRange(conflict.time, conflict.endTime) + ')';
                }
                conflictList.push(conflictText);
            });
            
            html += ' <span class="event-conflict-badge" data-conflicts="' + btoa(unescape(encodeURIComponent(JSON.stringify(conflictList)))) + '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' + event.conflictsWith.length + '</span>';
        }
        html += '</span>';
        html += '</div>';
        
        if (event.description) {
            html += '<div class="event-desc-compact" style="display: none;">' + renderDescription(event.description) + '</div>';
        }
    }
    
    html += '</div>'; // event-info
    
    // Use stored namespace from event, fallback to _namespace, then passed namespace
    let buttonNamespace = event.namespace || '';
    if (!buttonNamespace && event._namespace !== undefined) {
        buttonNamespace = event._namespace;
    }
    if (!buttonNamespace) {
        buttonNamespace = namespace;
    }
    
    html += '<div class="event-actions-compact">';
    html += '<button class="event-action-btn" onclick="deleteEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + buttonNamespace + '\')">🗑️</button>';
    html += '<button class="event-action-btn" onclick="editEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + buttonNamespace + '\')">✏️</button>';
    html += '</div>';
    
    // Checkbox for tasks - ON THE FAR RIGHT
    if (isTask) {
        const checked = completed ? 'checked' : '';
        html += '<input type="checkbox" class="task-checkbox" ' + checked + ' onclick="toggleTaskComplete(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + buttonNamespace + '\', this.checked)">';
    }
    
    html += '</div>';
    
    return html;
};

// Render description with rich content support
window.renderDescription = function(description) {
    if (!description) return '';
    
    // First, convert DokuWiki/Markdown syntax to placeholder tokens (before escaping)
    // Use a format that won't be affected by HTML escaping: \x00TOKEN_N\x00
    
    let rendered = description;
    const tokens = [];
    let tokenIndex = 0;
    
    // Convert DokuWiki image syntax {{image.jpg}} to tokens
    rendered = rendered.replace(/\{\{([^}|]+?)(?:\|([^}]+))?\}\}/g, function(match, imagePath, alt) {
        imagePath = imagePath.trim();
        alt = alt ? alt.trim() : '';
        
        let imageHtml;
        // Handle external URLs
        if (imagePath.match(/^https?:\/\//)) {
            imageHtml = '<img src="' + imagePath + '" alt="' + escapeHtml(alt) + '" class="event-image" />';
        } else {
            // Handle internal DokuWiki images
            const imageUrl = DOKU_BASE + 'lib/exe/fetch.php?media=' + encodeURIComponent(imagePath);
            imageHtml = '<img src="' + imageUrl + '" alt="' + escapeHtml(alt) + '" class="event-image" />';
        }
        
        const token = '\x00TOKEN' + tokenIndex + '\x00';
        tokens[tokenIndex] = imageHtml;
        tokenIndex++;
        return token;
    });
    
    // Convert DokuWiki link syntax [[link|text]] to tokens
    rendered = rendered.replace(/\[\[([^|\]]+?)(?:\|([^\]]+))?\]\]/g, function(match, link, text) {
        link = link.trim();
        text = text ? text.trim() : link;
        
        let linkHtml;
        // Handle external URLs
        if (link.match(/^https?:\/\//)) {
            linkHtml = '<a href="' + escapeHtml(link) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(text) + '</a>';
        } else {
            // Handle internal DokuWiki links with section anchors
            const hashIndex = link.indexOf('#');
            let pagePart = link;
            let sectionPart = '';
            
            if (hashIndex !== -1) {
                pagePart = link.substring(0, hashIndex);
                sectionPart = link.substring(hashIndex); // Includes the #
            }
            
            const wikiUrl = DOKU_BASE + 'doku.php?id=' + encodeURIComponent(pagePart) + sectionPart;
            linkHtml = '<a href="' + wikiUrl + '">' + escapeHtml(text) + '</a>';
        }
        
        const token = '\x00TOKEN' + tokenIndex + '\x00';
        tokens[tokenIndex] = linkHtml;
        tokenIndex++;
        return token;
    });
    
    // Convert markdown-style links [text](url) to tokens
    rendered = rendered.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, text, url) {
        text = text.trim();
        url = url.trim();
        
        let linkHtml;
        if (url.match(/^https?:\/\//)) {
            linkHtml = '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(text) + '</a>';
        } else {
            linkHtml = '<a href="' + escapeHtml(url) + '">' + escapeHtml(text) + '</a>';
        }
        
        const token = '\x00TOKEN' + tokenIndex + '\x00';
        tokens[tokenIndex] = linkHtml;
        tokenIndex++;
        return token;
    });
    
    // Convert plain URLs to tokens
    rendered = rendered.replace(/(https?:\/\/[^\s<]+)/g, function(match, url) {
        const linkHtml = '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(url) + '</a>';
        const token = '\x00TOKEN' + tokenIndex + '\x00';
        tokens[tokenIndex] = linkHtml;
        tokenIndex++;
        return token;
    });
    
    // NOW escape the remaining text (tokens are protected with null bytes)
    rendered = escapeHtml(rendered);
    
    // Convert newlines to <br>
    rendered = rendered.replace(/\n/g, '<br>');
    
    // DokuWiki text formatting (on escaped text)
    // Bold: **text** or __text__
    rendered = rendered.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    rendered = rendered.replace(/__(.+?)__/g, '<strong>$1</strong>');
    
    // Italic: //text//
    rendered = rendered.replace(/\/\/(.+?)\/\//g, '<em>$1</em>');
    
    // Strikethrough: <del>text</del>
    rendered = rendered.replace(/&lt;del&gt;(.+?)&lt;\/del&gt;/g, '<del>$1</del>');
    
    // Monospace: ''text''
    rendered = rendered.replace(/&#39;&#39;(.+?)&#39;&#39;/g, '<code>$1</code>');
    
    // Subscript: <sub>text</sub>
    rendered = rendered.replace(/&lt;sub&gt;(.+?)&lt;\/sub&gt;/g, '<sub>$1</sub>');
    
    // Superscript: <sup>text</sup>
    rendered = rendered.replace(/&lt;sup&gt;(.+?)&lt;\/sup&gt;/g, '<sup>$1</sup>');
    
    // Restore tokens (replace with actual HTML)
    for (let i = 0; i < tokens.length; i++) {
        const tokenPattern = new RegExp('\x00TOKEN' + i + '\x00', 'g');
        rendered = rendered.replace(tokenPattern, tokens[i]);
    }
    
    return rendered;
}

// Open add event dialog
window.openAddEvent = function(calId, namespace, date) {
    const dialog = document.getElementById('dialog-' + calId);
    const form = document.getElementById('eventform-' + calId);
    const title = document.getElementById('dialog-title-' + calId);
    const dateField = document.getElementById('event-date-' + calId);
    
    if (!dateField) {
        console.error('Date field not found! ID: event-date-' + calId);
        return;
    }
    
    // Check if there's a filtered namespace active (only for regular calendars)
    const calendar = document.getElementById(calId);
    const filteredNamespace = calendar ? calendar.dataset.filteredNamespace : null;
    
    // Use filtered namespace if available, otherwise use the passed namespace
    const effectiveNamespace = filteredNamespace || namespace;
    
    
    // Reset form
    form.reset();
    document.getElementById('event-id-' + calId).value = '';
    
    // Store the effective namespace in a hidden field or data attribute
    form.dataset.effectiveNamespace = effectiveNamespace;
    
    // Set namespace dropdown to effective namespace
    const namespaceSelect = document.getElementById('event-namespace-' + calId);
    if (namespaceSelect) {
        if (effectiveNamespace && effectiveNamespace !== '*' && effectiveNamespace.indexOf(';') === -1) {
            // Set to specific namespace if not wildcard or multi-namespace
            namespaceSelect.value = effectiveNamespace;
        } else {
            // Default to empty (default namespace) for wildcard/multi views
            namespaceSelect.value = '';
        }
    }
    
    // Clear event namespace from previous edits
    delete form.dataset.eventNamespace;
    
    // Set date - use local date, not UTC
    let defaultDate = date;
    if (!defaultDate) {
        // Get the currently displayed month from the calendar container
        const container = document.getElementById(calId);
        const displayedYear = parseInt(container.getAttribute('data-year'));
        const displayedMonth = parseInt(container.getAttribute('data-month'));
        
        
        if (displayedYear && displayedMonth) {
            // Use first day of the displayed month
            const year = displayedYear;
            const month = String(displayedMonth).padStart(2, '0');
            defaultDate = `${year}-${month}-01`;
        } else {
            // Fallback to today if attributes not found
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            defaultDate = `${year}-${month}-${day}`;
        }
    }
    dateField.value = defaultDate;
    dateField.removeAttribute('data-original-date');
    
    // Also set the end date field to the same default (user can change it)
    const endDateField = document.getElementById('event-end-date-' + calId);
    if (endDateField) {
        endDateField.value = ''; // Empty by default (single-day event)
    }
    
    // Set default color
    document.getElementById('event-color-' + calId).value = '#3498db';
    
    // Reset time pickers to default state
    setTimePicker(calId, false, ''); // Start time = All day
    setTimePicker(calId, true, '');  // End time = Same as start
    
    // Set date pickers
    setDatePicker(calId, false, defaultDate); // Start date
    setDatePicker(calId, true, '');  // End date = Optional
    
    // Initialize namespace search
    initNamespaceSearch(calId);
    
    // Set title
    title.textContent = 'Add Event';
    
    // Show dialog
    dialog.style.display = 'flex';
    
    // Propagate CSS vars to dialog (position:fixed can break inheritance in some templates)
    propagateThemeVars(calId, dialog);
    
    // Initialize custom pickers
    initCustomTimePickers(calId);
    initCustomDatePickers(calId);
    
    // Make dialog draggable
    setTimeout(() => makeDialogDraggable(calId), 50);
    
    // Focus title field
    setTimeout(() => {
        const titleField = document.getElementById('event-title-' + calId);
        if (titleField) titleField.focus();
    }, 100);
};

// Edit event
window.editEvent = function(calId, eventId, date, namespace) {
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'get_event',
        namespace: namespace,
        date: date,
        eventId: eventId
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.event) {
            const event = data.event;
            const dialog = document.getElementById('dialog-' + calId);
            const title = document.getElementById('dialog-title-' + calId);
            const dateField = document.getElementById('event-date-' + calId);
            const form = document.getElementById('eventform-' + calId);
            
            if (!dateField) {
                console.error('Date field not found when editing!');
                return;
            }
            
            // Store the event's actual namespace for saving (important for namespace=* views)
            if (event.namespace !== undefined) {
                form.dataset.eventNamespace = event.namespace;
            }
            
            // Populate form
            document.getElementById('event-id-' + calId).value = event.id;
            dateField.value = date;
            dateField.setAttribute('data-original-date', date);
            
            const endDateField = document.getElementById('event-end-date-' + calId);
            endDateField.value = event.endDate || '';
            
            document.getElementById('event-title-' + calId).value = event.title;
            document.getElementById('event-color-' + calId).value = event.color || '#3498db';
            document.getElementById('event-desc-' + calId).value = event.description || '';
            document.getElementById('event-is-task-' + calId).checked = event.isTask || false;
            
            // Set time picker values using custom picker API
            setTimePicker(calId, false, event.time || '');
            setTimePicker(calId, true, event.endTime || '');
            
            // Set date picker values
            setDatePicker(calId, false, date);
            setDatePicker(calId, true, event.endDate || '');
            
            // Initialize namespace search
            initNamespaceSearch(calId);
            
            // Set namespace fields if available
            const namespaceHidden = document.getElementById('event-namespace-' + calId);
            const namespaceSearch = document.getElementById('event-namespace-search-' + calId);
            if (namespaceHidden && event.namespace !== undefined) {
                // Set the hidden input (this is what gets submitted)
                namespaceHidden.value = event.namespace || '';
                // Set the search input to display the namespace
                if (namespaceSearch) {
                    namespaceSearch.value = event.namespace || '(default)';
                }
            } else {
                // No namespace on event, set to default
                if (namespaceHidden) {
                    namespaceHidden.value = '';
                }
                if (namespaceSearch) {
                    namespaceSearch.value = '(default)';
                }
            }
            
            title.textContent = 'Edit Event';
            dialog.style.display = 'flex';
            
            // Propagate CSS vars to dialog
            propagateThemeVars(calId, dialog);
            
            // Initialize custom pickers
            initCustomTimePickers(calId);
            initCustomDatePickers(calId);
            
            // Make dialog draggable
            setTimeout(() => makeDialogDraggable(calId), 50);
        }
    })
    .catch(err => console.error('Error editing event:', err));
};

// Delete event
window.deleteEvent = function(calId, eventId, date, namespace) {
    if (!confirm('Delete this event?')) return;
    
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'delete_event',
        namespace: namespace,
        date: date,
        eventId: eventId,
        sectok: getSecurityToken()
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Announce to screen readers
            announceToScreenReader('Event deleted');
            
            // Extract year and month from date
            const [year, month] = date.split('-').map(Number);
            
            // Get the calendar's ORIGINAL namespace setting (not the deleted event's namespace)
            // This preserves wildcard/multi-namespace views
            const container = document.getElementById(calId);
            const calendarNamespace = container ? (container.dataset.namespace || '') : namespace;
            
            // Reload calendar data via AJAX with the calendar's original namespace
            reloadCalendarData(calId, year, month, calendarNamespace);
        }
    })
    .catch(err => console.error('Error:', err));
};

// Save event (add or edit)
window.saveEventCompact = function(calId, namespace) {
    const form = document.getElementById('eventform-' + calId);
    
    // Get namespace from dropdown - this is what the user selected
    const namespaceSelect = document.getElementById('event-namespace-' + calId);
    const selectedNamespace = namespaceSelect ? namespaceSelect.value : '';
    
    // ALWAYS use what the user selected in the dropdown
    // This allows changing namespace when editing
    const finalNamespace = selectedNamespace;
    
    const eventId = document.getElementById('event-id-' + calId).value;
    
    // eventNamespace is the ORIGINAL namespace (only used for finding/deleting old event)
    const originalNamespace = form.dataset.eventNamespace;
    
    
    const dateInput = document.getElementById('event-date-' + calId);
    const date = dateInput.value;
    const oldDate = dateInput.getAttribute('data-original-date') || date;
    const endDate = document.getElementById('event-end-date-' + calId).value;
    const title = document.getElementById('event-title-' + calId).value;
    const time = document.getElementById('event-time-' + calId).value;
    const endTime = document.getElementById('event-end-time-' + calId).value;
    const colorSelect = document.getElementById('event-color-' + calId);
    let color = colorSelect.value;
    
    // Handle custom color
    if (color === 'custom') {
        color = colorSelect.dataset.customColor || document.getElementById('event-color-custom-' + calId).value;
    }
    
    const description = document.getElementById('event-desc-' + calId).value;
    const isTask = document.getElementById('event-is-task-' + calId).checked;
    const completed = false; // New tasks are not completed
    const isRecurring = document.getElementById('event-recurring-' + calId).checked;
    const recurrenceType = document.getElementById('event-recurrence-type-' + calId).value;
    const recurrenceEnd = document.getElementById('event-recurrence-end-' + calId).value;
    
    // New recurrence options
    const recurrenceIntervalInput = document.getElementById('event-recurrence-interval-' + calId);
    const recurrenceInterval = recurrenceIntervalInput ? parseInt(recurrenceIntervalInput.value) || 1 : 1;
    
    // Weekly: collect selected days
    let weekDays = [];
    const weeklyOptions = document.getElementById('weekly-options-' + calId);
    if (weeklyOptions && recurrenceType === 'weekly') {
        const checkboxes = weeklyOptions.querySelectorAll('input[name="weekDays[]"]:checked');
        weekDays = Array.from(checkboxes).map(cb => cb.value);
    }
    
    // Monthly: collect day-of-month or ordinal weekday
    let monthDay = '';
    let monthlyType = 'dayOfMonth';
    let ordinalWeek = '';
    let ordinalDay = '';
    const monthlyOptions = document.getElementById('monthly-options-' + calId);
    if (monthlyOptions && recurrenceType === 'monthly') {
        const monthlyTypeRadio = monthlyOptions.querySelector('input[name="monthlyType"]:checked');
        monthlyType = monthlyTypeRadio ? monthlyTypeRadio.value : 'dayOfMonth';
        
        if (monthlyType === 'dayOfMonth') {
            const monthDayInput = document.getElementById('event-month-day-' + calId);
            monthDay = monthDayInput ? monthDayInput.value : '';
        } else {
            const ordinalSelect = document.getElementById('event-ordinal-' + calId);
            const ordinalDaySelect = document.getElementById('event-ordinal-day-' + calId);
            ordinalWeek = ordinalSelect ? ordinalSelect.value : '1';
            ordinalDay = ordinalDaySelect ? ordinalDaySelect.value : '0';
        }
    }
    
    if (!title) {
        alert('Please enter a title');
        return;
    }
    
    if (!date) {
        alert('Please select a date');
        return;
    }
    
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'save_event',
        namespace: finalNamespace,
        eventId: eventId,
        date: date,
        oldDate: oldDate,
        endDate: endDate,
        title: title,
        time: time,
        endTime: endTime,
        color: color,
        description: description,
        isTask: isTask ? '1' : '0',
        completed: completed ? '1' : '0',
        isRecurring: isRecurring ? '1' : '0',
        recurrenceType: recurrenceType,
        recurrenceInterval: recurrenceInterval,
        recurrenceEnd: recurrenceEnd,
        weekDays: weekDays.join(','),
        monthlyType: monthlyType,
        monthDay: monthDay,
        ordinalWeek: ordinalWeek,
        ordinalDay: ordinalDay,
        sectok: getSecurityToken()
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Announce to screen readers
            announceToScreenReader(eventId ? 'Event updated' : 'Event created');
            
            closeEventDialog(calId);
            
            // For recurring events, do a full page reload to show all occurrences
            if (isRecurring) {
                location.reload();
                return;
            }
            
            // Extract year and month from the NEW date (in case date was changed)
            const [year, month] = date.split('-').map(Number);
            
            // Get the calendar's ORIGINAL namespace setting from the container
            // This preserves wildcard/multi-namespace views after editing
            const container = document.getElementById(calId);
            const calendarNamespace = container ? (container.dataset.namespace || '') : namespace;
            
            // Reload calendar data via AJAX to the month of the event
            reloadCalendarData(calId, year, month, calendarNamespace);
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error saving event');
    });
};

// Reload calendar data without page refresh
window.reloadCalendarData = function(calId, year, month, namespace) {
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'load_month',
        year: year,
        month: month,
        namespace: namespace,
        _: new Date().getTime() // Cache buster
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById(calId);
            
            // Check if this is a full calendar or just event panel
            if (container.classList.contains('calendar-compact-container')) {
                rebuildCalendar(calId, data.year, data.month, data.events, namespace);
            } else if (container.classList.contains('event-panel-standalone')) {
                rebuildEventPanel(calId, data.year, data.month, data.events, namespace);
            }
        }
    })
    .catch(err => console.error('Error:', err));
};

// Close event dialog
window.closeEventDialog = function(calId) {
    const dialog = document.getElementById('dialog-' + calId);
    dialog.style.display = 'none';
};

// Escape HTML
window.escapeHtml = function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

// Highlight event when clicking on bar in calendar
window.highlightEvent = function(calId, eventId, date) {
    
    // Find the event item in the event list
    const eventList = document.querySelector('#' + calId + ' .event-list-compact');
    if (!eventList) {
        return;
    }
    
    const eventItem = eventList.querySelector('[data-event-id="' + eventId + '"][data-date="' + date + '"]');
    if (!eventItem) {
        return;
    }
    
    
    // Get theme
    const container = document.getElementById(calId);
    const theme = container ? container.dataset.theme : 'matrix';
    const themeStyles = container ? JSON.parse(container.dataset.themeStyles || '{}') : {};
    
    
    // Theme-specific highlight colors
    let highlightBg, highlightShadow;
    if (theme === 'matrix') {
        highlightBg = '#1a3d1a';  // Darker green
        highlightShadow = '0 0 20px rgba(0, 204, 7, 0.8), 0 0 40px rgba(0, 204, 7, 0.4)';
    } else if (theme === 'purple') {
        highlightBg = '#3d2b4d';  // Darker purple
        highlightShadow = '0 0 20px rgba(155, 89, 182, 0.8), 0 0 40px rgba(155, 89, 182, 0.4)';
    } else if (theme === 'professional') {
        highlightBg = '#e3f2fd';  // Light blue
        highlightShadow = '0 0 20px rgba(74, 144, 226, 0.4)';
    } else if (theme === 'pink') {
        highlightBg = '#3d2030';  // Darker pink
        highlightShadow = '0 0 20px rgba(255, 20, 147, 0.8), 0 0 40px rgba(255, 20, 147, 0.4)';
    } else if (theme === 'wiki') {
        highlightBg = themeStyles.header_bg || '#e8e8e8';  // __background_alt__
        highlightShadow = '0 0 10px rgba(0, 0, 0, 0.15)';
    }
    
    
    // Store original styles
    const originalBg = eventItem.style.background;
    const originalShadow = eventItem.style.boxShadow;
    
    // Remove previous highlights (restore their original styles)
    const previousHighlights = eventList.querySelectorAll('.event-highlighted');
    previousHighlights.forEach(el => {
        el.classList.remove('event-highlighted');
    });
    
    // Add highlight class and apply theme-aware glow
    eventItem.classList.add('event-highlighted');
    
    // Set CSS properties directly
    eventItem.style.setProperty('background', highlightBg, 'important');
    eventItem.style.setProperty('box-shadow', highlightShadow, 'important');
    eventItem.style.setProperty('transition', 'all 0.3s ease-in-out', 'important');
    
    
    // Scroll to event
    eventItem.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'nearest',
        inline: 'nearest'
    });
    
    // Remove highlight after 3 seconds and restore original styles
    setTimeout(() => {
        eventItem.classList.remove('event-highlighted');
        eventItem.style.setProperty('background', originalBg);
        eventItem.style.setProperty('box-shadow', originalShadow);
        eventItem.style.setProperty('transition', '');
    }, 3000);
};

// Toggle recurring event options
window.toggleRecurringOptions = function(calId) {
    const checkbox = document.getElementById('event-recurring-' + calId);
    const options = document.getElementById('recurring-options-' + calId);
    
    if (checkbox && options) {
        options.style.display = checkbox.checked ? 'block' : 'none';
        if (checkbox.checked) {
            // Initialize the sub-options based on current selection
            updateRecurrenceOptions(calId);
        }
    }
};

// Update visible recurrence options based on type (daily/weekly/monthly/yearly)
window.updateRecurrenceOptions = function(calId) {
    const typeSelect = document.getElementById('event-recurrence-type-' + calId);
    const weeklyOptions = document.getElementById('weekly-options-' + calId);
    const monthlyOptions = document.getElementById('monthly-options-' + calId);
    
    if (!typeSelect) return;
    
    const recurrenceType = typeSelect.value;
    
    // Hide all conditional options first
    if (weeklyOptions) weeklyOptions.style.display = 'none';
    if (monthlyOptions) monthlyOptions.style.display = 'none';
    
    // Show relevant options
    if (recurrenceType === 'weekly' && weeklyOptions) {
        weeklyOptions.style.display = 'block';
        // Auto-select today's day of week if nothing selected
        const checkboxes = weeklyOptions.querySelectorAll('input[type="checkbox"]');
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        if (!anyChecked) {
            const today = new Date().getDay();
            const todayCheckbox = weeklyOptions.querySelector('input[value="' + today + '"]');
            if (todayCheckbox) todayCheckbox.checked = true;
        }
    } else if (recurrenceType === 'monthly' && monthlyOptions) {
        monthlyOptions.style.display = 'block';
        // Set default day to current day of month
        const monthDayInput = document.getElementById('event-month-day-' + calId);
        if (monthDayInput && !monthDayInput.dataset.userSet) {
            monthDayInput.value = new Date().getDate();
        }
    }
};

// Toggle between day-of-month and ordinal weekday for monthly recurrence
window.updateMonthlyType = function(calId) {
    const dayOfMonthDiv = document.getElementById('monthly-day-' + calId);
    const ordinalDiv = document.getElementById('monthly-ordinal-' + calId);
    const monthlyOptions = document.getElementById('monthly-options-' + calId);
    
    if (!monthlyOptions) return;
    
    const selectedRadio = monthlyOptions.querySelector('input[name="monthlyType"]:checked');
    if (!selectedRadio) return;
    
    if (selectedRadio.value === 'dayOfMonth') {
        if (dayOfMonthDiv) dayOfMonthDiv.style.display = 'flex';
        if (ordinalDiv) ordinalDiv.style.display = 'none';
    } else {
        if (dayOfMonthDiv) dayOfMonthDiv.style.display = 'none';
        if (ordinalDiv) ordinalDiv.style.display = 'block';
        
        // Set defaults based on current date
        const now = new Date();
        const dayOfWeek = now.getDay();
        const weekOfMonth = Math.ceil(now.getDate() / 7);
        
        const ordinalSelect = document.getElementById('event-ordinal-' + calId);
        const ordinalDaySelect = document.getElementById('event-ordinal-day-' + calId);
        
        if (ordinalSelect && !ordinalSelect.dataset.userSet) {
            ordinalSelect.value = weekOfMonth;
        }
        if (ordinalDaySelect && !ordinalDaySelect.dataset.userSet) {
            ordinalDaySelect.value = dayOfWeek;
        }
    }
};

// ============================================================
// Document-level event delegation (guarded - only attach once)
// These use event delegation so they work for AJAX-rebuilt content.
// ============================================================
if (!window._calendarDelegationInit) {
    window._calendarDelegationInit = true;

    // Keyboard navigation for accessibility
    document.addEventListener('keydown', function(e) {
        // ESC closes dialogs, popups, tooltips, dropdowns
        if (e.key === 'Escape') {
            // Close dialogs
            document.querySelectorAll('.event-dialog-compact').forEach(function(d) {
                if (d.style.display === 'flex') d.style.display = 'none';
            });
            // Close day popups
            document.querySelectorAll('.day-popup').forEach(function(p) {
                p.style.display = 'none';
            });
            // Close custom pickers
            document.querySelectorAll('.time-dropdown.open, .date-dropdown.open').forEach(function(d) {
                d.classList.remove('open');
                d.innerHTML = '';
            });
            document.querySelectorAll('.custom-time-picker.open, .custom-date-picker.open').forEach(function(b) {
                b.classList.remove('open');
            });
            hideConflictTooltip();
            return;
        }
        
        // Calendar grid navigation with arrow keys
        var focusedDay = document.activeElement;
        if (focusedDay && focusedDay.classList.contains('calendar-day')) {
            var calGrid = focusedDay.closest('.calendar-grid');
            if (!calGrid) return;
            
            var days = Array.from(calGrid.querySelectorAll('.calendar-day:not(.empty)'));
            var currentIndex = days.indexOf(focusedDay);
            if (currentIndex === -1) return;
            
            var newIndex = currentIndex;
            
            if (e.key === 'ArrowRight') {
                newIndex = Math.min(currentIndex + 1, days.length - 1);
                e.preventDefault();
            } else if (e.key === 'ArrowLeft') {
                newIndex = Math.max(currentIndex - 1, 0);
                e.preventDefault();
            } else if (e.key === 'ArrowDown') {
                newIndex = Math.min(currentIndex + 7, days.length - 1);
                e.preventDefault();
            } else if (e.key === 'ArrowUp') {
                newIndex = Math.max(currentIndex - 7, 0);
                e.preventDefault();
            } else if (e.key === 'Enter' || e.key === ' ') {
                // Activate the day (click it)
                focusedDay.click();
                e.preventDefault();
                return;
            }
            
            if (newIndex !== currentIndex && days[newIndex]) {
                days[newIndex].focus();
            }
        }
        
        // Event item navigation with arrow keys
        var focusedEvent = document.activeElement;
        if (focusedEvent && focusedEvent.classList.contains('event-item')) {
            var eventList = focusedEvent.closest('.event-list-items, .day-popup-events');
            if (!eventList) return;
            
            var events = Array.from(eventList.querySelectorAll('.event-item'));
            var currentIdx = events.indexOf(focusedEvent);
            if (currentIdx === -1) return;
            
            if (e.key === 'ArrowDown') {
                var nextIdx = Math.min(currentIdx + 1, events.length - 1);
                events[nextIdx].focus();
                e.preventDefault();
            } else if (e.key === 'ArrowUp') {
                var prevIdx = Math.max(currentIdx - 1, 0);
                events[prevIdx].focus();
                e.preventDefault();
            } else if (e.key === 'Enter') {
                // Find and click the edit button
                var editBtn = focusedEvent.querySelector('.event-action-edit');
                if (editBtn) editBtn.click();
                e.preventDefault();
            } else if (e.key === 'Delete' || e.key === 'Backspace') {
                // Find and click the delete button
                var deleteBtn = focusedEvent.querySelector('.event-action-delete');
                if (deleteBtn) deleteBtn.click();
                e.preventDefault();
            }
        }
    });

    // Conflict tooltip delegation (capture phase for mouseenter/leave)
    document.addEventListener('mouseenter', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('event-conflict-badge')) {
            showConflictTooltip(e.target);
        }
    }, true);

    document.addEventListener('mouseleave', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('event-conflict-badge')) {
            hideConflictTooltip();
        }
    }, true);
} // end delegation guard

// Event panel navigation
window.navEventPanel = function(calId, year, month, namespace) {
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'load_month',
        year: year,
        month: month,
        namespace: namespace,
        _: new Date().getTime() // Cache buster
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            rebuildEventPanel(calId, data.year, data.month, data.events, namespace);
        }
    })
    .catch(err => console.error('Error:', err));
};

// Rebuild event panel only
window.rebuildEventPanel = function(calId, year, month, events, namespace) {
    const container = document.getElementById(calId);
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                       'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    // Update month title in new compact header
    const monthTitle = container.querySelector('.panel-month-title');
    if (monthTitle) {
        monthTitle.textContent = monthNames[month - 1] + ' ' + year;
        monthTitle.setAttribute('onclick', `openMonthPickerPanel('${calId}', ${year}, ${month}, '${namespace}')`);
        monthTitle.setAttribute('title', 'Click to jump to month');
    }
    
    // Fallback: Update old header format if exists
    const oldHeader = container.querySelector('.panel-standalone-header h3, .calendar-month-picker');
    if (oldHeader && !monthTitle) {
        oldHeader.textContent = monthNames[month - 1] + ' ' + year + ' Events';
        oldHeader.setAttribute('onclick', `openMonthPickerPanel('${calId}', ${year}, ${month}, '${namespace}')`);
    }
    
    // Update nav buttons
    let prevMonth = month - 1;
    let prevYear = year;
    if (prevMonth < 1) {
        prevMonth = 12;
        prevYear--;
    }
    
    let nextMonth = month + 1;
    let nextYear = year;
    if (nextMonth > 12) {
        nextMonth = 1;
        nextYear++;
    }
    
    // Update new compact nav buttons
    const navBtns = container.querySelectorAll('.panel-nav-btn');
    if (navBtns[0]) navBtns[0].setAttribute('onclick', `navEventPanel('${calId}', ${prevYear}, ${prevMonth}, '${namespace}')`);
    if (navBtns[1]) navBtns[1].setAttribute('onclick', `navEventPanel('${calId}', ${nextYear}, ${nextMonth}, '${namespace}')`);
    
    // Fallback for old nav buttons
    const oldNavBtns = container.querySelectorAll('.cal-nav-btn');
    if (oldNavBtns.length > 0 && navBtns.length === 0) {
        if (oldNavBtns[0]) oldNavBtns[0].setAttribute('onclick', `navEventPanel('${calId}', ${prevYear}, ${prevMonth}, '${namespace}')`);
        if (oldNavBtns[1]) oldNavBtns[1].setAttribute('onclick', `navEventPanel('${calId}', ${nextYear}, ${nextMonth}, '${namespace}')`);
    }
    
    // Update Today button (works for both old and new)
    const todayBtn = container.querySelector('.panel-today-btn, .cal-today-btn, .cal-today-btn-compact');
    if (todayBtn) {
        todayBtn.setAttribute('onclick', `jumpTodayPanel('${calId}', '${namespace}')`);
    }
    
    // Rebuild event list
    const eventList = container.querySelector('.event-list-compact');
    if (eventList) {
        eventList.innerHTML = renderEventListFromData(events, calId, namespace, year, month);
    }
};

// Open add event for panel
window.openAddEventPanel = function(calId, namespace) {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const localDate = `${year}-${month}-${day}`;
    openAddEvent(calId, namespace, localDate);
};

// Toggle task completion
window.toggleTaskComplete = function(calId, eventId, date, namespace, completed) {
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'toggle_task',
        namespace: namespace,
        date: date,
        eventId: eventId,
        completed: completed ? '1' : '0',
        sectok: getSecurityToken()
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Announce to screen readers
            announceToScreenReader(completed ? 'Task marked complete' : 'Task marked incomplete');
            
            const [year, month] = date.split('-').map(Number);
            
            // Get the calendar's ORIGINAL namespace setting from the container
            const container = document.getElementById(calId);
            const calendarNamespace = container ? (container.dataset.namespace || '') : namespace;
            
            reloadCalendarData(calId, year, month, calendarNamespace);
        }
    })
    .catch(err => console.error('Error toggling task:', err));
};

// Make dialog draggable
window.makeDialogDraggable = function(calId) {
    const dialog = document.getElementById('dialog-content-' + calId);
    const handle = document.getElementById('drag-handle-' + calId);
    
    if (!dialog || !handle) return;
    
    // Remove any existing drag setup to prevent duplicate listeners
    if (handle._dragCleanup) {
        handle._dragCleanup();
    }
    
    // Reset position when dialog opens
    dialog.style.transform = '';
    
    let isDragging = false;
    let currentX = 0;
    let currentY = 0;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;
    
    function dragStart(e) {
        // Only start drag if clicking on the handle itself, not buttons inside it
        if (e.target.tagName === 'BUTTON') return;
        
        initialX = e.clientX - xOffset;
        initialY = e.clientY - yOffset;
        isDragging = true;
        handle.style.cursor = 'grabbing';
    }
    
    function drag(e) {
        if (isDragging) {
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            xOffset = currentX;
            yOffset = currentY;
            dialog.style.transform = `translate(${currentX}px, ${currentY}px)`;
        }
    }
    
    function dragEnd(e) {
        if (isDragging) {
            initialX = currentX;
            initialY = currentY;
            isDragging = false;
            handle.style.cursor = 'move';
        }
    }
    
    // Add listeners
    handle.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', dragEnd);
    
    // Store cleanup function to remove listeners later
    handle._dragCleanup = function() {
        handle.removeEventListener('mousedown', dragStart);
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('mouseup', dragEnd);
    };
};

// Toggle expand/collapse for past events
window.togglePastEventExpand = function(element) {
    // Stop propagation to prevent any parent click handlers
    event.stopPropagation();
    
    const meta = element.querySelector(".event-meta-compact");
    const desc = element.querySelector(".event-desc-compact");
    
    // Toggle visibility
    if (meta.style.display === "none") {
        // Expand
        meta.style.display = "block";
        if (desc) desc.style.display = "block";
        element.classList.add("event-past-expanded");
    } else {
        // Collapse
        meta.style.display = "none";
        if (desc) desc.style.display = "none";
        element.classList.remove("event-past-expanded");
    }
};

// Filter calendar by namespace when clicking namespace badge (guarded)
if (!window._calendarClickDelegationInit) {
    window._calendarClickDelegationInit = true;
    document.addEventListener('click', function(e) {
    if (e.target.classList.contains('event-namespace-badge')) {
        const namespace = e.target.textContent;
        const calendar = e.target.closest('.calendar-compact-container');
        
        if (!calendar) return;
        
        const calId = calendar.id;
        
        // Use AJAX reload to filter both calendar grid and event list
        filterCalendarByNamespace(calId, namespace);
    }
    });
} // end click delegation guard

// Update the displayed filtered namespace in event list header
// Legacy badge removed - namespace filtering still works but badge no longer shown
window.updateFilteredNamespaceDisplay = function(calId, namespace) {
    const calendar = document.getElementById(calId);
    if (!calendar) return;
    
    const headerContent = calendar.querySelector('.event-list-header-content');
    if (!headerContent) return;
    
    // Remove any existing filter badge (cleanup)
    let filterBadge = headerContent.querySelector('.namespace-filter-badge');
    if (filterBadge) {
        filterBadge.remove();
    }
};

// Clear namespace filter
window.clearNamespaceFilter = function(calId) {
    
    const container = document.getElementById(calId);
    if (!container) {
        console.error('Calendar container not found:', calId);
        return;
    }
    
    // Immediately hide/remove the filter badge
    const filterBadge = container.querySelector('.calendar-namespace-filter');
    if (filterBadge) {
        filterBadge.style.display = 'none';
        filterBadge.remove();
    }
    
    // Get current year and month
    const year = parseInt(container.dataset.year) || new Date().getFullYear();
    const month = parseInt(container.dataset.month) || (new Date().getMonth() + 1);
    
    // Get original namespace (what the calendar was initialized with)
    const originalNamespace = container.dataset.originalNamespace || '';
    
    // Also check for sidebar widget
    const sidebarContainer = document.getElementById('sidebar-widget-' + calId);
    if (sidebarContainer) {
        // For sidebar widget, just reload the page without namespace filter
        // Remove the namespace from the URL and reload
        const url = new URL(window.location.href);
        url.searchParams.delete('namespace');
        window.location.href = url.toString();
        return;
    }
    
    // For regular calendar, reload calendar with original namespace
    navCalendar(calId, year, month, originalNamespace);
};

window.clearNamespaceFilterPanel = function(calId) {
    
    const container = document.getElementById(calId);
    if (!container) {
        console.error('Event panel container not found:', calId);
        return;
    }
    
    // Get current year and month from URL params or container
    const year = parseInt(container.dataset.year) || new Date().getFullYear();
    const month = parseInt(container.dataset.month) || (new Date().getMonth() + 1);
    
    // Get original namespace (what the panel was initialized with)
    const originalNamespace = container.dataset.originalNamespace || '';
    
    
    // Reload event panel with original namespace
    navEventPanel(calId, year, month, originalNamespace);
};

// Color picker functions
window.updateCustomColorPicker = function(calId) {
    const select = document.getElementById('event-color-' + calId);
    const picker = document.getElementById('event-color-custom-' + calId);
    
    if (select.value === 'custom') {
        // Show color picker
        picker.style.display = 'inline-block';
        picker.click(); // Open color picker
    } else {
        // Hide color picker and sync value
        picker.style.display = 'none';
        picker.value = select.value;
    }
};

function updateColorFromPicker(calId) {
    const select = document.getElementById('event-color-' + calId);
    const picker = document.getElementById('event-color-custom-' + calId);
    
    // Set select to custom and update its underlying value
    select.value = 'custom';
    // Store the actual color value in a data attribute
    select.dataset.customColor = picker.value;
}

// Toggle past events visibility
window.togglePastEvents = function(calId) {
    const content = document.getElementById('past-events-' + calId);
    const arrow = document.getElementById('past-arrow-' + calId);
    
    if (!content || !arrow) {
        console.error('Past events elements not found for:', calId);
        return;
    }
    
    // Check computed style instead of inline style
    const isHidden = window.getComputedStyle(content).display === 'none';
    
    if (isHidden) {
        content.style.display = 'block';
        arrow.textContent = '▼';
    } else {
        content.style.display = 'none';
        arrow.textContent = '▶';
    }
};

// Fuzzy match scoring function
window.fuzzyMatch = function(pattern, str) {
    pattern = pattern.toLowerCase();
    str = str.toLowerCase();
    
    let patternIdx = 0;
    let score = 0;
    let consecutiveMatches = 0;
    
    for (let i = 0; i < str.length; i++) {
        if (patternIdx < pattern.length && str[i] === pattern[patternIdx]) {
            score += 1 + consecutiveMatches;
            consecutiveMatches++;
            patternIdx++;
        } else {
            consecutiveMatches = 0;
        }
    }
    
    // Return null if not all characters matched
    if (patternIdx !== pattern.length) {
        return null;
    }
    
    // Bonus for exact match
    if (str === pattern) {
        score += 100;
    }
    
    // Bonus for starts with
    if (str.startsWith(pattern)) {
        score += 50;
    }
    
    return score;
};

// Initialize namespace search for a calendar
window.initNamespaceSearch = function(calId) {
    const searchInput = document.getElementById('event-namespace-search-' + calId);
    const hiddenInput = document.getElementById('event-namespace-' + calId);
    const dropdown = document.getElementById('event-namespace-dropdown-' + calId);
    const dataElement = document.getElementById('namespaces-data-' + calId);
    
    if (!searchInput || !hiddenInput || !dropdown || !dataElement) {
        return; // Elements not found
    }
    
    // PERFORMANCE FIX: Prevent re-binding event listeners on each dialog open
    if (searchInput.dataset.initialized === 'true') {
        return;
    }
    searchInput.dataset.initialized = 'true';
    
    let namespaces = [];
    try {
        namespaces = JSON.parse(dataElement.textContent);
    } catch (e) {
        console.error('Failed to parse namespaces data:', e);
        return;
    }
    
    let selectedIndex = -1;
    
    // Filter and show dropdown
    function filterNamespaces(query) {
        if (!query || query.trim() === '') {
            // Show all namespaces when empty
            hiddenInput.value = '';
            const results = namespaces.slice(0, 20); // Limit to 20
            showDropdown(results);
            return;
        }
        
        // Fuzzy match and score
        const matches = [];
        for (let i = 0; i < namespaces.length; i++) {
            const score = fuzzyMatch(query, namespaces[i]);
            if (score !== null) {
                matches.push({ namespace: namespaces[i], score: score });
            }
        }
        
        // Sort by score (descending)
        matches.sort((a, b) => b.score - a.score);
        
        // Take top 20 results
        const results = matches.slice(0, 20).map(m => m.namespace);
        showDropdown(results);
    }
    
    function showDropdown(results) {
        dropdown.innerHTML = '';
        selectedIndex = -1;
        
        if (results.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        // Add (default) option
        const defaultOption = document.createElement('div');
        defaultOption.className = 'namespace-option';
        defaultOption.textContent = '(default)';
        defaultOption.dataset.value = '';
        dropdown.appendChild(defaultOption);
        
        results.forEach(ns => {
            const option = document.createElement('div');
            option.className = 'namespace-option';
            option.textContent = ns;
            option.dataset.value = ns;
            dropdown.appendChild(option);
        });
        
        dropdown.style.display = 'block';
    }
    
    function hideDropdown() {
        dropdown.style.display = 'none';
        selectedIndex = -1;
    }
    
    function selectOption(namespace) {
        hiddenInput.value = namespace;
        searchInput.value = namespace || '(default)';
        hideDropdown();
    }
    
    // Event listeners - only bound once now
    searchInput.addEventListener('input', function(e) {
        filterNamespaces(e.target.value);
    });
    
    searchInput.addEventListener('focus', function(e) {
        filterNamespaces(e.target.value);
    });
    
    searchInput.addEventListener('blur', function(e) {
        // Delay to allow click on dropdown
        setTimeout(hideDropdown, 200);
    });
    
    searchInput.addEventListener('keydown', function(e) {
        const options = dropdown.querySelectorAll('.namespace-option');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, options.length - 1);
            updateSelection(options);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(options);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && options[selectedIndex]) {
                selectOption(options[selectedIndex].dataset.value);
            }
        } else if (e.key === 'Escape') {
            hideDropdown();
        }
    });
    
    function updateSelection(options) {
        options.forEach((opt, idx) => {
            if (idx === selectedIndex) {
                opt.classList.add('selected');
                opt.scrollIntoView({ block: 'nearest' });
            } else {
                opt.classList.remove('selected');
            }
        });
    }
    
    // Click on dropdown option
    dropdown.addEventListener('mousedown', function(e) {
        if (e.target.classList.contains('namespace-option')) {
            selectOption(e.target.dataset.value);
        }
    });
};

// Legacy function - kept for compatibility, now handled by custom pickers
window.updateEndTimeOptions = function(calId) {
    updateEndTimeButtonState(calId);
};

// ============================================================================
// CUSTOM TIME PICKER - Fast, lightweight time selection
// ============================================================================

// Time data - generated once, reused for all pickers
window._calendarTimeData = null;
window.getTimeData = function() {
    if (window._calendarTimeData) return window._calendarTimeData;
    
    const periods = [
        { name: 'Morning', hours: [6, 7, 8, 9, 10, 11] },
        { name: 'Afternoon', hours: [12, 13, 14, 15, 16, 17] },
        { name: 'Evening', hours: [18, 19, 20, 21, 22, 23] },
        { name: 'Night', hours: [0, 1, 2, 3, 4, 5] }
    ];
    
    const data = [];
    periods.forEach(period => {
        const times = [];
        period.hours.forEach(hour => {
            for (let minute = 0; minute < 60; minute += 15) {
                const value = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                const displayHour = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour);
                const ampm = hour < 12 ? 'AM' : 'PM';
                const display = displayHour + ':' + String(minute).padStart(2, '0') + ' ' + ampm;
                const minutes = hour * 60 + minute;
                times.push({ value, display, minutes });
            }
        });
        data.push({ name: period.name, times });
    });
    
    window._calendarTimeData = data;
    return data;
};

// Format time value to display string
window.formatTimeDisplay = function(value) {
    if (!value) return '';
    const [hour, minute] = value.split(':').map(Number);
    const displayHour = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour);
    const ampm = hour < 12 ? 'AM' : 'PM';
    return displayHour + ':' + String(minute).padStart(2, '0') + ' ' + ampm;
};

// Build dropdown HTML - called only when opening
window.buildTimeDropdown = function(calId, isEndTime, startTimeValue, isMultiDay) {
    const data = getTimeData();
    let html = '';
    
    // Calculate start time minutes for filtering end time options
    let startMinutes = -1;
    if (isEndTime && startTimeValue && !isMultiDay) {
        const [h, m] = startTimeValue.split(':').map(Number);
        startMinutes = h * 60 + m;
    }
    
    // Add "All day" / "Same as start" option
    const defaultText = isEndTime ? 'Same as start' : 'All day';
    html += '<div class="time-option" data-value="">' + defaultText + '</div>';
    
    data.forEach(period => {
        html += '<div class="time-dropdown-section">';
        html += '<div class="time-dropdown-header">' + period.name + '</div>';
        period.times.forEach(time => {
            const disabled = (isEndTime && !isMultiDay && startMinutes >= 0 && time.minutes <= startMinutes);
            const disabledClass = disabled ? ' disabled' : '';
            html += '<div class="time-option' + disabledClass + '" data-value="' + time.value + '" data-minutes="' + time.minutes + '">' + time.display + '</div>';
        });
        html += '</div>';
    });
    
    return html;
};

// Open time dropdown
window.openTimeDropdown = function(calId, isEndTime) {
    const btnId = isEndTime ? 'end-time-picker-btn-' + calId : 'time-picker-btn-' + calId;
    const dropdownId = isEndTime ? 'end-time-dropdown-' + calId : 'time-dropdown-' + calId;
    const btn = document.getElementById(btnId);
    const dropdown = document.getElementById(dropdownId);
    
    if (!btn || !dropdown) return;
    
    // Close any other open dropdowns first
    document.querySelectorAll('.time-dropdown.open').forEach(d => {
        if (d.id !== dropdownId) {
            d.classList.remove('open');
            d.innerHTML = '';
        }
    });
    document.querySelectorAll('.custom-time-picker.open').forEach(b => {
        if (b.id !== btnId) b.classList.remove('open');
    });
    
    // Toggle this dropdown
    if (dropdown.classList.contains('open')) {
        dropdown.classList.remove('open');
        btn.classList.remove('open');
        dropdown.innerHTML = '';
        return;
    }
    
    // Get current state
    const startTimeInput = document.getElementById('event-time-' + calId);
    const startDateInput = document.getElementById('event-date-' + calId);
    const endDateInput = document.getElementById('event-end-date-' + calId);
    
    const startTime = startTimeInput ? startTimeInput.value : '';
    const startDate = startDateInput ? startDateInput.value : '';
    const endDate = endDateInput ? endDateInput.value : '';
    const isMultiDay = endDate && endDate !== startDate;
    
    // Build and show dropdown
    dropdown.innerHTML = buildTimeDropdown(calId, isEndTime, startTime, isMultiDay);
    dropdown.classList.add('open');
    btn.classList.add('open');
    
    // Scroll to appropriate option
    const currentValue = isEndTime ? 
        document.getElementById('event-end-time-' + calId).value :
        document.getElementById('event-time-' + calId).value;
    
    if (currentValue) {
        // Scroll to selected option
        const selected = dropdown.querySelector('[data-value="' + currentValue + '"]');
        if (selected) {
            selected.classList.add('selected');
            selected.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
    } else if (isEndTime && startTime) {
        // For end time with no selection, scroll to first available option after start time
        const firstAvailable = dropdown.querySelector('.time-option:not(.disabled):not([data-value=""])');
        if (firstAvailable) {
            firstAvailable.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
    }
};

// Select time option
window.selectTimeOption = function(calId, isEndTime, value) {
    const inputId = isEndTime ? 'event-end-time-' + calId : 'event-time-' + calId;
    const btnId = isEndTime ? 'end-time-picker-btn-' + calId : 'time-picker-btn-' + calId;
    const dropdownId = isEndTime ? 'end-time-dropdown-' + calId : 'time-dropdown-' + calId;
    
    const input = document.getElementById(inputId);
    const btn = document.getElementById(btnId);
    const dropdown = document.getElementById(dropdownId);
    
    if (input) {
        input.value = value;
    }
    
    if (btn) {
        const display = btn.querySelector('.time-display');
        if (display) {
            if (value) {
                display.textContent = formatTimeDisplay(value);
            } else {
                display.textContent = isEndTime ? 'Same as start' : 'All day';
            }
        }
        btn.classList.remove('open');
    }
    
    if (dropdown) {
        dropdown.classList.remove('open');
        dropdown.innerHTML = '';
    }
    
    // If start time changed, update end time button state
    if (!isEndTime) {
        updateEndTimeButtonState(calId);
    }
};

// Update end time button enabled/disabled state
window.updateEndTimeButtonState = function(calId) {
    const startTimeInput = document.getElementById('event-time-' + calId);
    const endTimeBtn = document.getElementById('end-time-picker-btn-' + calId);
    const endTimeInput = document.getElementById('event-end-time-' + calId);
    
    if (!startTimeInput || !endTimeBtn) return;
    
    const startTime = startTimeInput.value;
    
    if (!startTime) {
        // All day - disable end time
        endTimeBtn.disabled = true;
        if (endTimeInput) endTimeInput.value = '';
        const display = endTimeBtn.querySelector('.time-display');
        if (display) display.textContent = 'Same as start';
    } else {
        endTimeBtn.disabled = false;
    }
};

// Initialize custom time pickers for a dialog
window.initCustomTimePickers = function(calId) {
    const startBtn = document.getElementById('time-picker-btn-' + calId);
    const endBtn = document.getElementById('end-time-picker-btn-' + calId);
    const startDropdown = document.getElementById('time-dropdown-' + calId);
    const endDropdown = document.getElementById('end-time-dropdown-' + calId);
    
    // Prevent re-initialization
    if (startBtn && startBtn.dataset.initialized) return;
    
    if (startBtn) {
        startBtn.dataset.initialized = 'true';
        startBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openTimeDropdown(calId, false);
        });
    }
    
    if (endBtn) {
        endBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!endBtn.disabled) {
                openTimeDropdown(calId, true);
            }
        });
    }
    
    // Handle clicks on time options
    if (startDropdown) {
        startDropdown.addEventListener('click', function(e) {
            const option = e.target.closest('.time-option');
            if (option && !option.classList.contains('disabled')) {
                e.stopPropagation();
                selectTimeOption(calId, false, option.dataset.value);
            }
        });
    }
    
    if (endDropdown) {
        endDropdown.addEventListener('click', function(e) {
            const option = e.target.closest('.time-option');
            if (option && !option.classList.contains('disabled')) {
                e.stopPropagation();
                selectTimeOption(calId, true, option.dataset.value);
            }
        });
    }
    
    // Handle date changes - update end time options when dates change
    const startDateInput = document.getElementById('event-date-' + calId);
    const endDateInput = document.getElementById('event-end-date-' + calId);
    
    if (startDateInput && !startDateInput.dataset.initialized) {
        startDateInput.dataset.initialized = 'true';
        startDateInput.addEventListener('change', function() {
            // Just close any open dropdowns - they'll rebuild with correct state when reopened
            const dropdown = document.getElementById('end-time-dropdown-' + calId);
            if (dropdown && dropdown.classList.contains('open')) {
                dropdown.classList.remove('open');
                dropdown.innerHTML = '';
            }
        });
    }
    
    if (endDateInput && !endDateInput.dataset.initialized) {
        endDateInput.dataset.initialized = 'true';
        endDateInput.addEventListener('change', function() {
            const dropdown = document.getElementById('end-time-dropdown-' + calId);
            if (dropdown && dropdown.classList.contains('open')) {
                dropdown.classList.remove('open');
                dropdown.innerHTML = '';
            }
        });
    }
};

// Close dropdowns when clicking outside
if (!window._calendarDropdownCloseInit) {
    window._calendarDropdownCloseInit = true;
    document.addEventListener('click', function(e) {
        // Don't close if clicking inside a picker button or dropdown
        if (e.target.closest('.custom-time-picker') || e.target.closest('.time-dropdown') ||
            e.target.closest('.custom-date-picker') || e.target.closest('.date-dropdown')) {
            return;
        }
        
        // Close all open time dropdowns
        document.querySelectorAll('.time-dropdown.open').forEach(d => {
            d.classList.remove('open');
            d.innerHTML = '';
        });
        document.querySelectorAll('.custom-time-picker.open').forEach(b => {
            b.classList.remove('open');
        });
        
        // Close all open date dropdowns
        document.querySelectorAll('.date-dropdown.open').forEach(d => {
            d.classList.remove('open');
            d.innerHTML = '';
        });
        document.querySelectorAll('.custom-date-picker.open').forEach(b => {
            b.classList.remove('open');
        });
    });
}

// Set time picker value programmatically (for edit mode)
window.setTimePicker = function(calId, isEndTime, value) {
    const inputId = isEndTime ? 'event-end-time-' + calId : 'event-time-' + calId;
    const btnId = isEndTime ? 'end-time-picker-btn-' + calId : 'time-picker-btn-' + calId;
    
    const input = document.getElementById(inputId);
    const btn = document.getElementById(btnId);
    
    if (input) {
        input.value = value || '';
    }
    
    if (btn) {
        const display = btn.querySelector('.time-display');
        if (display) {
            if (value) {
                display.textContent = formatTimeDisplay(value);
            } else {
                display.textContent = isEndTime ? 'Same as start' : 'All day';
            }
        }
        
        // Update disabled state for end time
        if (isEndTime) {
            const startTimeInput = document.getElementById('event-time-' + calId);
            btn.disabled = !startTimeInput || !startTimeInput.value;
        }
    }
};

// ============================================================================
// CUSTOM DATE PICKER - Fast, lightweight date selection
// ============================================================================

// Format date for display
window.formatDateDisplay = function(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('en-US', { 
        weekday: 'short',
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
};

// Build date picker calendar HTML
window.buildDateCalendar = function(calId, isEndDate, year, month, selectedDate, minDate) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDayOfWeek = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    let html = '<div class="date-picker-calendar">';
    
    // Header with navigation
    html += '<div class="date-picker-header">';
    html += '<button type="button" class="date-picker-nav" data-action="prev">◀</button>';
    html += '<span class="date-picker-title">' + monthNames[month] + ' ' + year + '</span>';
    html += '<button type="button" class="date-picker-nav" data-action="next">▶</button>';
    html += '</div>';
    
    // Weekday headers
    html += '<div class="date-picker-weekdays">';
    ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(d => {
        html += '<div class="date-picker-weekday">' + d + '</div>';
    });
    html += '</div>';
    
    // Days grid
    html += '<div class="date-picker-days">';
    
    // Previous month days
    const prevMonth = new Date(year, month, 0);
    const prevMonthDays = prevMonth.getDate();
    for (let i = startDayOfWeek - 1; i >= 0; i--) {
        const day = prevMonthDays - i;
        const dateStr = formatDateValue(year, month - 1, day);
        html += '<button type="button" class="date-picker-day other-month" data-date="' + dateStr + '">' + day + '</button>';
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = formatDateValue(year, month, day);
        const dateObj = new Date(year, month, day);
        dateObj.setHours(0, 0, 0, 0);
        
        let classes = 'date-picker-day';
        if (dateObj.getTime() === today.getTime()) classes += ' today';
        if (dateStr === selectedDate) classes += ' selected';
        
        // For end date, disable dates before start date
        if (isEndDate && minDate) {
            const minDateObj = new Date(minDate + 'T00:00:00');
            if (dateObj < minDateObj) classes += ' disabled';
        }
        
        html += '<button type="button" class="' + classes + '" data-date="' + dateStr + '">' + day + '</button>';
    }
    
    // Next month days to fill grid
    const totalCells = startDayOfWeek + daysInMonth;
    const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for (let i = 1; i <= remainingCells; i++) {
        const dateStr = formatDateValue(year, month + 1, i);
        html += '<button type="button" class="date-picker-day other-month" data-date="' + dateStr + '">' + i + '</button>';
    }
    
    html += '</div>';
    
    // Clear button for end date
    if (isEndDate) {
        html += '<button type="button" class="date-picker-clear" data-action="clear">Clear End Date</button>';
    }
    
    html += '</div>';
    return html;
};

// Format date value as YYYY-MM-DD
window.formatDateValue = function(year, month, day) {
    // Handle month overflow
    const date = new Date(year, month, day);
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
};

// Open date dropdown
window.openDateDropdown = function(calId, isEndDate) {
    const btnId = isEndDate ? 'end-date-picker-btn-' + calId : 'date-picker-btn-' + calId;
    const dropdownId = isEndDate ? 'end-date-dropdown-' + calId : 'date-dropdown-' + calId;
    const btn = document.getElementById(btnId);
    const dropdown = document.getElementById(dropdownId);
    
    if (!btn || !dropdown) return;
    
    // Close any other open dropdowns first
    document.querySelectorAll('.date-dropdown.open, .time-dropdown.open').forEach(d => {
        if (d.id !== dropdownId) {
            d.classList.remove('open');
            d.innerHTML = '';
        }
    });
    document.querySelectorAll('.custom-date-picker.open, .custom-time-picker.open').forEach(b => {
        if (b.id !== btnId) b.classList.remove('open');
    });
    
    // Toggle this dropdown
    if (dropdown.classList.contains('open')) {
        dropdown.classList.remove('open');
        btn.classList.remove('open');
        dropdown.innerHTML = '';
        return;
    }
    
    // Get current value and min date
    const inputId = isEndDate ? 'event-end-date-' + calId : 'event-date-' + calId;
    const input = document.getElementById(inputId);
    const selectedDate = input ? input.value : '';
    
    let minDate = null;
    if (isEndDate) {
        const startInput = document.getElementById('event-date-' + calId);
        minDate = startInput ? startInput.value : null;
    }
    
    // Determine which month to show
    let year, month;
    if (selectedDate) {
        // If there's a selected date, show that month
        const d = new Date(selectedDate + 'T00:00:00');
        year = d.getFullYear();
        month = d.getMonth();
    } else if (isEndDate && minDate) {
        // For end date with no value, start on the start date's month
        const d = new Date(minDate + 'T00:00:00');
        year = d.getFullYear();
        month = d.getMonth();
    } else {
        // Fallback to current month
        const now = new Date();
        year = now.getFullYear();
        month = now.getMonth();
    }
    
    // Store current view state
    dropdown.dataset.year = year;
    dropdown.dataset.month = month;
    dropdown.dataset.isEnd = isEndDate ? '1' : '0';
    dropdown.dataset.calId = calId;
    
    // Build and show
    dropdown.innerHTML = buildDateCalendar(calId, isEndDate, year, month, selectedDate, minDate);
    dropdown.classList.add('open');
    btn.classList.add('open');
};

// Select date
window.selectDate = function(calId, isEndDate, dateStr) {
    const inputId = isEndDate ? 'event-end-date-' + calId : 'event-date-' + calId;
    const btnId = isEndDate ? 'end-date-picker-btn-' + calId : 'date-picker-btn-' + calId;
    const dropdownId = isEndDate ? 'end-date-dropdown-' + calId : 'date-dropdown-' + calId;
    
    const input = document.getElementById(inputId);
    const btn = document.getElementById(btnId);
    const dropdown = document.getElementById(dropdownId);
    
    if (input) {
        input.value = dateStr || '';
    }
    
    if (btn) {
        const display = btn.querySelector('.date-display');
        if (display) {
            display.textContent = dateStr ? formatDateDisplay(dateStr) : (isEndDate ? 'Optional' : 'Select date');
        }
        btn.classList.remove('open');
    }
    
    if (dropdown) {
        dropdown.classList.remove('open');
        dropdown.innerHTML = '';
    }
};

// Navigate date picker month
window.navigateDatePicker = function(dropdown, direction) {
    let year = parseInt(dropdown.dataset.year);
    let month = parseInt(dropdown.dataset.month);
    const isEndDate = dropdown.dataset.isEnd === '1';
    const calId = dropdown.dataset.calId;
    
    month += direction;
    if (month < 0) { month = 11; year--; }
    if (month > 11) { month = 0; year++; }
    
    dropdown.dataset.year = year;
    dropdown.dataset.month = month;
    
    const inputId = isEndDate ? 'event-end-date-' + calId : 'event-date-' + calId;
    const input = document.getElementById(inputId);
    const selectedDate = input ? input.value : '';
    
    let minDate = null;
    if (isEndDate) {
        const startInput = document.getElementById('event-date-' + calId);
        minDate = startInput ? startInput.value : null;
    }
    
    dropdown.innerHTML = buildDateCalendar(calId, isEndDate, year, month, selectedDate, minDate);
};

// Initialize custom date pickers for a dialog
window.initCustomDatePickers = function(calId) {
    const startBtn = document.getElementById('date-picker-btn-' + calId);
    const endBtn = document.getElementById('end-date-picker-btn-' + calId);
    const startDropdown = document.getElementById('date-dropdown-' + calId);
    const endDropdown = document.getElementById('end-date-dropdown-' + calId);
    
    // Prevent re-initialization
    if (startBtn && startBtn.dataset.initialized) return;
    
    if (startBtn) {
        startBtn.dataset.initialized = 'true';
        startBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openDateDropdown(calId, false);
        });
    }
    
    if (endBtn) {
        endBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openDateDropdown(calId, true);
        });
    }
    
    // Handle clicks inside date dropdowns
    [startDropdown, endDropdown].forEach((dropdown, idx) => {
        if (!dropdown) return;
        const isEnd = idx === 1;
        
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const nav = e.target.closest('.date-picker-nav');
            if (nav) {
                const direction = nav.dataset.action === 'prev' ? -1 : 1;
                navigateDatePicker(dropdown, direction);
                return;
            }
            
            const clear = e.target.closest('.date-picker-clear');
            if (clear) {
                selectDate(calId, true, '');
                return;
            }
            
            const day = e.target.closest('.date-picker-day');
            if (day && !day.classList.contains('disabled')) {
                selectDate(calId, isEnd, day.dataset.date);
            }
        });
    });
};

// Set date picker value programmatically
window.setDatePicker = function(calId, isEndDate, value) {
    const inputId = isEndDate ? 'event-end-date-' + calId : 'event-date-' + calId;
    const btnId = isEndDate ? 'end-date-picker-btn-' + calId : 'date-picker-btn-' + calId;
    
    const input = document.getElementById(inputId);
    const btn = document.getElementById(btnId);
    
    if (input) {
        input.value = value || '';
    }
    
    if (btn) {
        const display = btn.querySelector('.date-display');
        if (display) {
            display.textContent = value ? formatDateDisplay(value) : (isEndDate ? 'Optional' : 'Select date');
        }
    }
};

// Check for time conflicts between events on the same date
window.checkTimeConflicts = function(events, currentEventId) {
    const conflicts = [];
    
    // Group events by date
    const eventsByDate = {};
    for (const [date, dateEvents] of Object.entries(events)) {
        if (!Array.isArray(dateEvents)) continue;
        
        dateEvents.forEach(evt => {
            if (!evt.time || evt.id === currentEventId) return; // Skip all-day events and current event
            
            if (!eventsByDate[date]) eventsByDate[date] = [];
            eventsByDate[date].push(evt);
        });
    }
    
    // Check for overlaps on each date
    for (const [date, dateEvents] of Object.entries(eventsByDate)) {
        for (let i = 0; i < dateEvents.length; i++) {
            for (let j = i + 1; j < dateEvents.length; j++) {
                const evt1 = dateEvents[i];
                const evt2 = dateEvents[j];
                
                if (eventsOverlap(evt1, evt2)) {
                    // Mark both events as conflicting
                    if (!evt1.hasConflict) evt1.hasConflict = true;
                    if (!evt2.hasConflict) evt2.hasConflict = true;
                    
                    // Store conflict info
                    if (!evt1.conflictsWith) evt1.conflictsWith = [];
                    if (!evt2.conflictsWith) evt2.conflictsWith = [];
                    
                    evt1.conflictsWith.push({id: evt2.id, title: evt2.title, time: evt2.time, endTime: evt2.endTime});
                    evt2.conflictsWith.push({id: evt1.id, title: evt1.title, time: evt1.time, endTime: evt1.endTime});
                }
            }
        }
    }
    
    return events;
};

// Check if two events overlap in time
function eventsOverlap(evt1, evt2) {
    if (!evt1.time || !evt2.time) return false; // All-day events don't conflict
    
    const start1 = evt1.time;
    const end1 = evt1.endTime || evt1.time; // If no end time, treat as same as start
    
    const start2 = evt2.time;
    const end2 = evt2.endTime || evt2.time;
    
    // Convert to minutes for easier comparison
    const start1Mins = timeToMinutes(start1);
    const end1Mins = timeToMinutes(end1);
    const start2Mins = timeToMinutes(start2);
    const end2Mins = timeToMinutes(end2);
    
    // Check for overlap
    // Events overlap if: start1 < end2 AND start2 < end1
    return start1Mins < end2Mins && start2Mins < end1Mins;
}

// Convert HH:MM time to minutes since midnight
function timeToMinutes(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}

// Format time range for display
window.formatTimeRange = function(startTime, endTime) {
    if (!startTime) return '';
    
    const formatTime = (timeStr) => {
        const [hour24, minute] = timeStr.split(':').map(Number);
        const hour12 = hour24 === 0 ? 12 : (hour24 > 12 ? hour24 - 12 : hour24);
        const ampm = hour24 < 12 ? 'AM' : 'PM';
        return hour12 + ':' + String(minute).padStart(2, '0') + ' ' + ampm;
    };
    
    if (!endTime || endTime === startTime) {
        return formatTime(startTime);
    }
    
    return formatTime(startTime) + ' - ' + formatTime(endTime);
};

// Track last known mouse position for tooltip positioning fallback
var _lastMouseX = 0, _lastMouseY = 0;
document.addEventListener('mousemove', function(e) {
    _lastMouseX = e.clientX;
    _lastMouseY = e.clientY;
});

// Show custom conflict tooltip
window.showConflictTooltip = function(badgeElement) {
    // Remove any existing tooltip
    hideConflictTooltip();
    
    // Get conflict data (base64-encoded JSON to avoid attribute quote issues)
    const conflictsRaw = badgeElement.getAttribute('data-conflicts');
    if (!conflictsRaw) return;
    
    let conflicts;
    try {
        conflicts = JSON.parse(decodeURIComponent(escape(atob(conflictsRaw))));
    } catch (e) {
        // Fallback: try parsing as plain JSON (for PHP-rendered badges)
        try {
            conflicts = JSON.parse(conflictsRaw);
        } catch (e2) {
            console.error('Failed to parse conflicts:', e2);
            return;
        }
    }
    
    // Get theme from the calendar container via CSS variables
    // Try closest ancestor first, then fall back to any calendar on the page
    let containerEl = badgeElement.closest('[id^="cal_"], [id^="panel_"], [id^="sidebar-widget-"], .calendar-compact-container, .event-panel-standalone');
    if (!containerEl) {
        // Badge might be inside a day popup (appended to body) - find any calendar container
        containerEl = document.querySelector('.calendar-compact-container, .event-panel-standalone, [id^="sidebar-widget-"]');
    }
    const cs = containerEl ? getComputedStyle(containerEl) : null;
    
    const bg = cs ? cs.getPropertyValue('--background-site').trim() || '#242424' : '#242424';
    const border = cs ? cs.getPropertyValue('--border-main').trim() || '#00cc07' : '#00cc07';
    const textPrimary = cs ? cs.getPropertyValue('--text-primary').trim() || '#00cc07' : '#00cc07';
    const textDim = cs ? cs.getPropertyValue('--text-dim').trim() || '#00aa00' : '#00aa00';
    const shadow = cs ? cs.getPropertyValue('--shadow-color').trim() || 'rgba(0, 204, 7, 0.3)' : 'rgba(0, 204, 7, 0.3)';
    
    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.id = 'conflict-tooltip';
    tooltip.className = 'conflict-tooltip';
    
    // Apply theme styles
    tooltip.style.background = bg;
    tooltip.style.borderColor = border;
    tooltip.style.color = textPrimary;
    tooltip.style.boxShadow = '0 4px 12px ' + shadow;
    
    // Build content with themed colors
    let html = '<div class="conflict-tooltip-header" style="background: ' + border + '; color: ' + bg + '; border-bottom: 1px solid ' + border + ';">⚠️ Time Conflicts</div>';
    html += '<div class="conflict-tooltip-body">';
    conflicts.forEach(conflict => {
        html += '<div class="conflict-item" style="color: ' + textDim + '; border-bottom-color: ' + border + ';">• ' + escapeHtml(conflict) + '</div>';
    });
    html += '</div>';
    
    tooltip.innerHTML = html;
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = badgeElement.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    // Position above the badge, centered
    let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
    let top = rect.top - tooltipRect.height - 8;
    
    // Keep tooltip within viewport
    if (left < 10) left = 10;
    if (left + tooltipRect.width > window.innerWidth - 10) {
        left = window.innerWidth - tooltipRect.width - 10;
    }
    if (top < 10) {
        // If not enough room above, show below
        top = rect.bottom + 8;
    }
    
    tooltip.style.left = left + 'px';
    tooltip.style.top = top + 'px';
    tooltip.style.opacity = '1';
};

// Hide conflict tooltip
window.hideConflictTooltip = function() {
    const tooltip = document.getElementById('conflict-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
};

// Fuzzy search helper for event filtering - normalizes text for matching
function eventSearchNormalize(text) {
    if (typeof text !== 'string') {
        console.log('[eventSearchNormalize] WARNING: text is not a string:', typeof text, text);
        return '';
    }
    return text
        .toLowerCase()
        .trim()
        // Remove common punctuation that might differ
        .replace(/[''\u2018\u2019]/g, '')  // Remove apostrophes/quotes
        .replace(/["""\u201C\u201D]/g, '') // Remove smart quotes
        .replace(/[-–—]/g, ' ')            // Dashes to spaces
        .replace(/[.,!?;:]/g, '')          // Remove punctuation
        .replace(/\s+/g, ' ')              // Normalize whitespace
        .trim();
}

// Check if search term matches text for event filtering
function eventSearchMatch(text, searchTerm) {
    const normalizedText = eventSearchNormalize(text);
    const normalizedSearch = eventSearchNormalize(searchTerm);
    
    // Direct match after normalization
    if (normalizedText.includes(normalizedSearch)) {
        return true;
    }
    
    // Split search into words and check if all words are present
    const searchWords = normalizedSearch.split(' ').filter(w => w.length > 0);
    if (searchWords.length > 1) {
        return searchWords.every(word => normalizedText.includes(word));
    }
    
    return false;
}

// Filter events by search term
window.filterEvents = function(calId, searchTerm) {
    const eventList = document.getElementById('eventlist-' + calId);
    const searchClear = document.getElementById('search-clear-' + calId);
    const searchMode = document.getElementById('search-mode-' + calId);
    
    if (!eventList) return;
    
    // Check if we're in "all dates" mode
    const isAllDatesMode = searchMode && searchMode.classList.contains('all-dates');
    
    // Show/hide clear button
    if (searchClear) {
        searchClear.style.display = searchTerm ? 'block' : 'none';
    }
    
    searchTerm = searchTerm.trim();
    
    // If all-dates mode and we have a search term, do AJAX search
    if (isAllDatesMode && searchTerm.length >= 2) {
        searchAllDates(calId, searchTerm);
        return;
    }
    
    // If all-dates mode but search cleared, restore normal view
    if (isAllDatesMode && !searchTerm) {
        // Remove search results container if exists
        const resultsContainer = eventList.querySelector('.all-dates-results');
        if (resultsContainer) {
            resultsContainer.remove();
        }
        // Show normal event items
        eventList.querySelectorAll('.event-compact-item').forEach(item => {
            item.style.display = '';
        });
        // Show past events toggle if it exists
        const pastToggle = eventList.querySelector('.past-events-toggle');
        if (pastToggle) pastToggle.style.display = '';
    }
    
    // Get all event items
    const eventItems = eventList.querySelectorAll('.event-compact-item');
    let visibleCount = 0;
    let hiddenPastCount = 0;
    
    eventItems.forEach(item => {
        const title = item.querySelector('.event-title-compact');
        const description = item.querySelector('.event-desc-compact');
        const dateTime = item.querySelector('.event-date-time');
        
        // Build searchable text
        let searchableText = '';
        if (title) searchableText += title.textContent + ' ';
        if (description) searchableText += description.textContent + ' ';
        if (dateTime) searchableText += dateTime.textContent + ' ';
        
        // Check if matches search using fuzzy matching
        const matches = !searchTerm || eventSearchMatch(searchableText, searchTerm);
        
        if (matches) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
            // Check if this is a past event
            if (item.classList.contains('event-past') || item.classList.contains('event-completed')) {
                hiddenPastCount++;
            }
        }
    });
    
    // Update past events toggle if it exists
    const pastToggle = eventList.querySelector('.past-events-toggle');
    const pastLabel = eventList.querySelector('.past-events-label');
    const pastContent = document.getElementById('past-events-' + calId);
    
    if (pastToggle && pastLabel && pastContent) {
        const visiblePastEvents = pastContent.querySelectorAll('.event-compact-item:not([style*="display: none"])');
        const totalPastVisible = visiblePastEvents.length;
        
        if (totalPastVisible > 0) {
            pastLabel.textContent = `Past Events (${totalPastVisible})`;
            pastToggle.style.display = '';
        } else {
            pastToggle.style.display = 'none';
        }
    }
    
    // Show "no results" message if nothing visible (only for month mode, not all-dates mode)
    let noResultsMsg = eventList.querySelector('.no-search-results');
    if (visibleCount === 0 && searchTerm && !isAllDatesMode) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('p');
            noResultsMsg.className = 'no-search-results no-events-msg';
            noResultsMsg.textContent = 'No events match your search';
            eventList.appendChild(noResultsMsg);
        }
        noResultsMsg.style.display = 'block';
    } else if (noResultsMsg) {
        noResultsMsg.style.display = 'none';
    }
};

// Toggle search mode between "this month" and "all dates"
window.toggleSearchMode = function(calId, namespace) {
    const searchMode = document.getElementById('search-mode-' + calId);
    const searchInput = document.getElementById('event-search-' + calId);
    
    if (!searchMode) return;
    
    const isAllDates = searchMode.classList.toggle('all-dates');
    
    // Update button icon and title
    if (isAllDates) {
        searchMode.innerHTML = '🌐';
        searchMode.title = 'Searching all dates';
        if (searchInput) {
            searchInput.placeholder = 'Search all dates...';
        }
    } else {
        searchMode.innerHTML = '📅';
        searchMode.title = 'Search this month only';
        if (searchInput) {
            searchInput.placeholder = searchInput.classList.contains('panel-search-input') ? 'Search this month...' : '🔍 Search...';
        }
    }
    
    // Re-run search with current term
    if (searchInput && searchInput.value) {
        filterEvents(calId, searchInput.value);
    } else {
        // Clear any all-dates results
        const eventList = document.getElementById('eventlist-' + calId);
        if (eventList) {
            const resultsContainer = eventList.querySelector('.all-dates-results');
            if (resultsContainer) {
                resultsContainer.remove();
            }
            // Show normal event items
            eventList.querySelectorAll('.event-compact-item').forEach(item => {
                item.style.display = '';
            });
            const pastToggle = eventList.querySelector('.past-events-toggle');
            if (pastToggle) pastToggle.style.display = '';
        }
    }
};

// Search all dates via AJAX
window.searchAllDates = function(calId, searchTerm) {
    const eventList = document.getElementById('eventlist-' + calId);
    if (!eventList) return;
    
    // Get namespace from container
    const container = document.getElementById(calId);
    const namespace = container ? (container.dataset.namespace || '') : '';
    
    // Hide normal event items
    eventList.querySelectorAll('.event-compact-item').forEach(item => {
        item.style.display = 'none';
    });
    const pastToggle = eventList.querySelector('.past-events-toggle');
    if (pastToggle) pastToggle.style.display = 'none';
    
    // Remove old results container
    let resultsContainer = eventList.querySelector('.all-dates-results');
    if (resultsContainer) {
        resultsContainer.remove();
    }
    
    // Create new results container
    resultsContainer = document.createElement('div');
    resultsContainer.className = 'all-dates-results';
    resultsContainer.innerHTML = '<p class="search-loading" style="text-align:center; padding:20px; color:var(--text-dim);">🔍 Searching all dates...</p>';
    eventList.appendChild(resultsContainer);
    
    // Make AJAX request
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'search_all',
        search: searchTerm,
        namespace: namespace,
        _: new Date().getTime()
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.results) {
            if (data.results.length === 0) {
                resultsContainer.innerHTML = '<p class="no-search-results" style="text-align:center; padding:20px; color:var(--text-dim); font-style:italic;">No events found matching "' + escapeHtml(searchTerm) + '"</p>';
            } else {
                let html = '<div class="all-dates-header" style="padding:4px 8px; background:var(--cell-today-bg, #e8f5e9); font-size:10px; font-weight:600; color:var(--text-bright, #00cc07); border-bottom:1px solid var(--border-color);">Found ' + data.results.length + ' event(s) across all dates</div>';
                
                data.results.forEach(event => {
                    const dateObj = new Date(event.date + 'T00:00:00');
                    const dateDisplay = dateObj.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                    const color = event.color || 'var(--text-bright, #00cc07)';
                    
                    html += '<div class="event-compact-item search-result-item" style="display:flex; border-bottom:1px solid var(--border-color, #e0e0e0); padding:6px 8px; gap:6px; cursor:pointer;" onclick="jumpToDate(\'' + calId + '\', \'' + event.date + '\', \'' + namespace + '\')">';
                    html += '<div style="width:3px; background:' + color + '; border-radius:1px; flex-shrink:0;"></div>';
                    html += '<div style="flex:1; min-width:0;">';
                    html += '<div class="event-title-compact" style="font-weight:600; color:var(--text-primary); font-size:11px;">' + escapeHtml(event.title) + '</div>';
                    html += '<div class="event-date-time" style="font-size:10px; color:var(--text-dim);">' + dateDisplay;
                    if (event.time) {
                        html += ' • ' + formatTimeRange(event.time, event.endTime);
                    }
                    html += '</div>';
                    if (event.namespace) {
                        html += '<span style="font-size:9px; background:var(--text-bright); color:var(--background-site); padding:1px 4px; border-radius:2px; margin-top:2px; display:inline-block;">' + escapeHtml(event.namespace) + '</span>';
                    }
                    html += '</div></div>';
                });
                
                resultsContainer.innerHTML = html;
            }
        } else {
            resultsContainer.innerHTML = '<p class="no-search-results" style="text-align:center; padding:20px; color:var(--text-dim);">Search failed. Please try again.</p>';
        }
    })
    .catch(err => {
        console.error('Search error:', err);
        resultsContainer.innerHTML = '<p class="no-search-results" style="text-align:center; padding:20px; color:var(--text-dim);">Search failed. Please try again.</p>';
    });
};

// Jump to a specific date (used by search results)
window.jumpToDate = function(calId, date, namespace) {
    const parts = date.split('-');
    const year = parseInt(parts[0]);
    const month = parseInt(parts[1]);
    
    // Get container to check current month
    const container = document.getElementById(calId);
    const currentYear = container ? parseInt(container.dataset.year) : year;
    const currentMonth = container ? parseInt(container.dataset.month) : month;
    
    // Get search elements
    const searchInput = document.getElementById('event-search-' + calId);
    const searchMode = document.getElementById('search-mode-' + calId);
    const searchClear = document.getElementById('search-clear-' + calId);
    const eventList = document.getElementById('eventlist-' + calId);
    
    // Remove the all-dates results container
    if (eventList) {
        const resultsContainer = eventList.querySelector('.all-dates-results');
        if (resultsContainer) {
            resultsContainer.remove();
        }
        // Show normal event items again
        eventList.querySelectorAll('.event-compact-item').forEach(item => {
            item.style.display = '';
        });
        const pastToggle = eventList.querySelector('.past-events-toggle');
        if (pastToggle) pastToggle.style.display = '';
        
        // Hide any no-results message
        const noResults = eventList.querySelector('.no-search-results');
        if (noResults) noResults.style.display = 'none';
    }
    
    // Clear search input
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Hide clear button
    if (searchClear) {
        searchClear.style.display = 'none';
    }
    
    // Switch back to month mode
    if (searchMode && searchMode.classList.contains('all-dates')) {
        searchMode.classList.remove('all-dates');
        searchMode.innerHTML = '📅';
        searchMode.title = 'Search this month only';
        if (searchInput) {
            searchInput.placeholder = searchInput.classList.contains('panel-search-input') ? 'Search this month...' : '🔍 Search...';
        }
    }
    
    // Check if we need to navigate to a different month
    if (year !== currentYear || month !== currentMonth) {
        // Navigate to the target month, then show popup
        navCalendar(calId, year, month, namespace);
        
        // After navigation completes, show the day popup
        setTimeout(() => {
            showDayPopup(calId, date, namespace);
        }, 400);
    } else {
        // Same month - just show the popup
        showDayPopup(calId, date, namespace);
    }
};

// Clear event search
window.clearEventSearch = function(calId) {
    const searchInput = document.getElementById('event-search-' + calId);
    if (searchInput) {
        searchInput.value = '';
        filterEvents(calId, '');
        searchInput.focus();
    }
};

// ============================================
// PINK THEME - GLOWING PARTICLE EFFECTS
// ============================================

// Create glowing pink particle effects for pink theme
(function() {
    let pinkThemeActive = false;
    let trailTimer = null;
    let pixelTimer = null;
    
    // Check if pink theme is active
    function checkPinkTheme() {
        const pinkCalendars = document.querySelectorAll('.calendar-theme-pink');
        pinkThemeActive = pinkCalendars.length > 0;
        return pinkThemeActive;
    }
    
    // Create trail particle
    function createTrailParticle(clientX, clientY) {
        if (!pinkThemeActive) return;
        
        const trail = document.createElement('div');
        trail.className = 'pink-cursor-trail';
        trail.style.left = clientX + 'px';
        trail.style.top = clientY + 'px';
        trail.style.animation = 'cursor-trail-fade 0.5s ease-out forwards';
        
        document.body.appendChild(trail);
        
        setTimeout(function() {
            trail.remove();
        }, 500);
    }
    
    // Create pixel sparkles
    function createPixelSparkles(clientX, clientY) {
        if (!pinkThemeActive || pixelTimer) return;
        
        const pixelCount = 3 + Math.floor(Math.random() * 4); // 3-6 pixels
        
        for (let i = 0; i < pixelCount; i++) {
            const pixel = document.createElement('div');
            pixel.className = 'pink-pixel-sparkle';
            
            // Random offset from cursor
            const offsetX = (Math.random() - 0.5) * 30;
            const offsetY = (Math.random() - 0.5) * 30;
            
            pixel.style.left = (clientX + offsetX) + 'px';
            pixel.style.top = (clientY + offsetY) + 'px';
            
            // Random color - bright neon pinks and whites
            const colors = ['#fff', '#ff1493', '#ff69b4', '#ffb6c1', '#ff85c1'];
            const color = colors[Math.floor(Math.random() * colors.length)];
            pixel.style.background = color;
            pixel.style.boxShadow = '0 0 2px ' + color + ', 0 0 4px ' + color + ', 0 0 6px #fff';
            
            // Random animation
            if (Math.random() > 0.5) {
                pixel.style.animation = 'pixel-twinkle 0.6s ease-out forwards';
            } else {
                pixel.style.animation = 'pixel-float-away 0.8s ease-out forwards';
            }
            
            document.body.appendChild(pixel);
            
            setTimeout(function() {
                pixel.remove();
            }, 800);
        }
        
        pixelTimer = setTimeout(function() {
            pixelTimer = null;
        }, 40);
    }
    
    // Create explosion
    function createExplosion(clientX, clientY) {
        if (!pinkThemeActive) return;
        
        const particleCount = 25;
        const colors = ['#ff1493', '#ff69b4', '#ff85c1', '#ffc0cb', '#fff'];
        
        // Add hearts to explosion (8-12 hearts)
        const heartCount = 8 + Math.floor(Math.random() * 5);
        for (let i = 0; i < heartCount; i++) {
            const heart = document.createElement('div');
            heart.textContent = '💖';
            heart.style.position = 'fixed';
            heart.style.left = clientX + 'px';
            heart.style.top = clientY + 'px';
            heart.style.pointerEvents = 'none';
            heart.style.zIndex = '9999999';
            heart.style.fontSize = (12 + Math.random() * 16) + 'px';
            
            // Random direction
            const angle = Math.random() * Math.PI * 2;
            const velocity = 60 + Math.random() * 80;
            const tx = Math.cos(angle) * velocity;
            const ty = Math.sin(angle) * velocity;
            
            heart.style.setProperty('--tx', tx + 'px');
            heart.style.setProperty('--ty', ty + 'px');
            
            const duration = 0.8 + Math.random() * 0.4;
            heart.style.animation = 'particle-explode ' + duration + 's ease-out forwards';
            
            document.body.appendChild(heart);
            
            setTimeout(function() {
                heart.remove();
            }, duration * 1000);
        }
        
        // Main explosion particles
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'pink-particle';
            
            const color = colors[Math.floor(Math.random() * colors.length)];
            particle.style.background = 'radial-gradient(circle, ' + color + ', transparent)';
            particle.style.boxShadow = '0 0 10px ' + color + ', 0 0 20px ' + color;
            
            particle.style.left = clientX + 'px';
            particle.style.top = clientY + 'px';
            
            const angle = (Math.PI * 2 * i) / particleCount;
            const velocity = 50 + Math.random() * 100;
            const tx = Math.cos(angle) * velocity;
            const ty = Math.sin(angle) * velocity;
            
            particle.style.setProperty('--tx', tx + 'px');
            particle.style.setProperty('--ty', ty + 'px');
            
            const size = 4 + Math.random() * 6;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            
            const duration = 0.6 + Math.random() * 0.4;
            particle.style.animation = 'particle-explode ' + duration + 's ease-out forwards';
            
            document.body.appendChild(particle);
            
            setTimeout(function() {
                particle.remove();
            }, duration * 1000);
        }
        
        // Pixel sparkles
        const pixelSparkleCount = 40;
        
        for (let i = 0; i < pixelSparkleCount; i++) {
            const pixel = document.createElement('div');
            pixel.className = 'pink-pixel-sparkle';
            
            const pixelColors = ['#fff', '#fff', '#ff1493', '#ff69b4', '#ffb6c1', '#ff85c1'];
            const pixelColor = pixelColors[Math.floor(Math.random() * pixelColors.length)];
            pixel.style.background = pixelColor;
            pixel.style.boxShadow = '0 0 3px ' + pixelColor + ', 0 0 6px ' + pixelColor + ', 0 0 9px #fff';
            
            const angle = Math.random() * Math.PI * 2;
            const distance = 30 + Math.random() * 80;
            const offsetX = Math.cos(angle) * distance;
            const offsetY = Math.sin(angle) * distance;
            
            pixel.style.left = clientX + 'px';
            pixel.style.top = clientY + 'px';
            pixel.style.setProperty('--tx', offsetX + 'px');
            pixel.style.setProperty('--ty', offsetY + 'px');
            
            const pixelSize = 1 + Math.random() * 2;
            pixel.style.width = pixelSize + 'px';
            pixel.style.height = pixelSize + 'px';
            
            const duration = 0.4 + Math.random() * 0.4;
            if (Math.random() > 0.5) {
                pixel.style.animation = 'pixel-twinkle ' + duration + 's ease-out forwards';
            } else {
                pixel.style.animation = 'particle-explode ' + duration + 's ease-out forwards';
            }
            
            document.body.appendChild(pixel);
            
            setTimeout(function() {
                pixel.remove();
            }, duration * 1000);
        }
        
        // Flash
        const flash = document.createElement('div');
        flash.style.position = 'fixed';
        flash.style.left = clientX + 'px';
        flash.style.top = clientY + 'px';
        flash.style.width = '40px';
        flash.style.height = '40px';
        flash.style.borderRadius = '50%';
        flash.style.background = 'radial-gradient(circle, rgba(255, 255, 255, 0.9), rgba(255, 20, 147, 0.6), transparent)';
        flash.style.boxShadow = '0 0 40px #fff, 0 0 60px #ff1493, 0 0 80px #ff69b4';
        flash.style.pointerEvents = 'none';
        flash.style.zIndex = '9999999';  // Above everything including dialogs
        flash.style.transform = 'translate(-50%, -50%)';
        flash.style.animation = 'cursor-trail-fade 0.3s ease-out forwards';
        
        document.body.appendChild(flash);
        
        setTimeout(function() {
            flash.remove();
        }, 300);
    }
    
    function initPinkParticles() {
        if (!checkPinkTheme()) return;
        
        // Use capture phase to catch events before stopPropagation
        document.addEventListener('mousemove', function(e) {
            if (!pinkThemeActive) return;
            
            createTrailParticle(e.clientX, e.clientY);
            createPixelSparkles(e.clientX, e.clientY);
        }, true); // Capture phase!
        
        // Throttle main trail
        document.addEventListener('mousemove', function(e) {
            if (!pinkThemeActive || trailTimer) return;
            
            trailTimer = setTimeout(function() {
                trailTimer = null;
            }, 30);
        }, true); // Capture phase!
        
        // Click explosion - use capture phase
        document.addEventListener('click', function(e) {
            if (!pinkThemeActive) return;
            
            createExplosion(e.clientX, e.clientY);
        }, true); // Capture phase!
    }
    
    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPinkParticles);
    } else {
        initPinkParticles();
    }
    
    // Re-check theme if calendar is dynamically added
    // Must wait for document.body to exist
    function setupMutationObserver() {
        if (typeof MutationObserver !== 'undefined' && document.body) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && node.classList && node.classList.contains('calendar-theme-pink')) {
                                checkPinkTheme();
                                initPinkParticles();
                            }
                        });
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }
    
    // Setup observer when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupMutationObserver);
    } else {
        setupMutationObserver();
    }
})();

// Mobile touch event delegation for edit/delete buttons
// This ensures buttons work on mobile where onclick may not fire reliably
(function() {
    function handleButtonTouch(e) {
        const btn = e.target.closest('.event-edit-btn, .event-delete-btn, .event-action-btn');
        if (!btn) return;
        
        // Prevent double-firing with onclick
        e.preventDefault();
        
        // Small delay to show visual feedback
        setTimeout(function() {
            btn.click();
        }, 10);
    }
    
    // Use touchend for more reliable mobile handling
    document.addEventListener('touchend', handleButtonTouch, { passive: false });
})();

// Static calendar navigation
window.navStaticCalendar = function(calId, direction) {
    const container = document.getElementById(calId);
    if (!container) return;
    
    let year = parseInt(container.dataset.year);
    let month = parseInt(container.dataset.month);
    const namespace = container.dataset.namespace || '';
    
    // Calculate new month
    month += direction;
    if (month < 1) {
        month = 12;
        year--;
    } else if (month > 12) {
        month = 1;
        year++;
    }
    
    // Fetch new calendar content via AJAX
    const params = new URLSearchParams({
        call: 'plugin_calendar',
        action: 'get_static_calendar',
        year: year,
        month: month,
        namespace: namespace
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.html) {
            // Replace the container content
            container.outerHTML = data.html;
        }
    })
    .catch(err => console.error('Static calendar navigation error:', err));
};

// Print static calendar - opens print dialog with only calendar content
window.printStaticCalendar = function(calId) {
    const container = document.getElementById(calId);
    if (!container) return;
    
    // Get the print view content
    const printView = container.querySelector('.static-print-view');
    if (!printView) return;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Build print document with inline margins for maximum compatibility
    const printContent = `
<!DOCTYPE html>
<html>
<head>
    <title>Calendar - ${container.dataset.year}-${String(container.dataset.month).padStart(2, '0')}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; color: #333; background: white; }
        table { border-collapse: collapse; font-size: 12px; }
        th { background: #2c3e50; color: white; padding: 8px; text-align: left; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        td { padding: 6px 8px; border-bottom: 1px solid #ccc; vertical-align: top; }
        tr:nth-child(even) { background: #f0f0f0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .static-itinerary-important { background: #fffde7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .static-itinerary-date { font-weight: bold; white-space: nowrap; }
        .static-itinerary-time { white-space: nowrap; color: #555; }
        .static-itinerary-title { font-weight: 500; }
        .static-itinerary-desc { color: #555; font-size: 11px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        h2 { font-size: 16px; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 2px solid #333; }
        p { font-size: 12px; color: #666; margin-bottom: 15px; }
    </style>
</head>
<body style="margin: 0; padding: 0;">
    <div style="padding: 50px 60px; margin: 0 auto; max-width: 800px;">
        ${printView.innerHTML}
    </div>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
            window.onafterprint = function() {
                window.close();
            };
        };
    </script>
</body>
</html>`;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
};

// ============================================================================
// ACCESSIBILITY - Screen reader announcements
// ============================================================================

// Create ARIA live region for announcements
if (!document.getElementById('calendar-aria-live')) {
    var ariaLive = document.createElement('div');
    ariaLive.id = 'calendar-aria-live';
    ariaLive.setAttribute('role', 'status');
    ariaLive.setAttribute('aria-live', 'polite');
    ariaLive.setAttribute('aria-atomic', 'true');
    ariaLive.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
    document.body.appendChild(ariaLive);
}

// Announce message to screen readers
window.announceToScreenReader = function(message) {
    var ariaLive = document.getElementById('calendar-aria-live');
    if (ariaLive) {
        ariaLive.textContent = '';
        // Small delay to ensure screen reader picks up the change
        setTimeout(function() {
            ariaLive.textContent = message;
        }, 100);
    }
};

// End of calendar plugin JavaScript

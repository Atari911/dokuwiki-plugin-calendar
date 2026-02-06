/**
 * DokuWiki Compact Calendar Plugin JavaScript
 * Loaded independently to avoid DokuWiki concatenation issues
 */

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
            if (!headerDiv) {
                console.error('Header div not found!');
            } else {
                filterIndicator = document.createElement('div');
                filterIndicator.className = 'calendar-namespace-filter';
                filterIndicator.id = 'namespace-filter-' + calId;
                headerDiv.parentNode.insertBefore(filterIndicator, headerDiv.nextSibling);
            }
        } else {
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
        const dateMonth = parseInt(dateKey.split('-')[1]);
        
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
                const currentKey = current.toISOString().split('T')[0];
                
                // Check if this date is in current month
                const currentDate = new Date(currentKey + 'T00:00:00');
                if (currentDate.getFullYear() === year && currentDate.getMonth() === month - 1) {
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
                html += '<td class="cal-empty"></td>';
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
                
                html += `<td class="${classes}" data-date="${dateKey}" onclick="showDayPopup('${calId}', '${dateKey}', '${namespace}')">`;
                html += `<span class="day-num">${currentDay}</span>`;
                
                if (hasEvents) {
                    // Sort events by time (no time first, then by time)
                    const sortedEvents = [...eventRanges[dateKey]].sort((a, b) => {
                        const timeA = a.time || '';
                        const timeB = b.time || '';
                        
                        // Events without time go first
                        if (!timeA && timeB) return -1;
                        if (timeA && !timeB) return 1;
                        if (!timeA && !timeB) return 0;
                        
                        // Sort by time
                        return timeA.localeCompare(timeB);
                    });
                    
                    // Show colored stacked bars for each event
                    html += '<div class="event-indicators">';
                    for (const evt of sortedEvents) {
                        const eventId = evt.id || '';
                        const eventColor = evt.color || '#3498db';
                        const eventTime = evt.time || '';
                        const eventTitle = evt.title || 'Event';
                        const originalDate = evt._original_date || dateKey;
                        const isFirstDay = evt._is_first_day !== undefined ? evt._is_first_day : true;
                        const isLastDay = evt._is_last_day !== undefined ? evt._is_last_day : true;
                        
                        let barClass = !eventTime ? 'event-bar-no-time' : 'event-bar-timed';
                        
                        // Add classes for multi-day spanning
                        if (!isFirstDay) barClass += ' event-bar-continues';
                        if (!isLastDay) barClass += ' event-bar-continuing';
                        
                        html += `<span class="event-bar ${barClass}" `;
                        html += `style="background: ${eventColor};" `;
                        html += `title="${escapeHtml(eventTitle)}${eventTime ? ' @ ' + eventTime : ''}" `;
                        html += `onclick="event.stopPropagation(); highlightEvent('${calId}', '${eventId}', '${originalDate}');"></span>`;
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
    
    // Check for time conflicts
    events = checkTimeConflicts(events, null);
    
    let pastHtml = '';
    let futureHtml = '';
    let pastCount = 0;
    
    const sortedDates = Object.keys(events).sort();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = today.toISOString().split('T')[0];
    
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
            
            html += '<div class="popup-event-item">';
            html += '<div class="event-color-bar" style="background: ' + color + ';"></div>';
            html += '<div class="popup-event-content">';
            
            // Single line with title, time, date range, namespace, and actions
            html += '<div class="popup-event-main-row">';
            html += '<div class="popup-event-info-inline">';
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
                
                html += '<span class="event-conflict-badge" data-conflicts="' + escapeHtml(JSON.stringify(conflictList)) + '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' + event.conflictsWith.length + '</span>';
            }
            
            html += '</div>';
            html += '<div class="popup-event-actions">';
            html += '<button class="event-edit-btn" onclick="editEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + eventNamespace + '\'); closeDayPopup(\'' + calId + '\')">✏️</button>';
            html += '<button class="event-delete-btn" onclick="deleteEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + eventNamespace + '\'); closeDayPopup(\'' + calId + '\')">🗑️</button>';
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
    // Check if this event is in the past or today (with 15-minute grace period)
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = today.toISOString().split('T')[0];
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
    
    // Format date display with day of week
    // Use originalStartDate if this is a multi-month event continuation
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
    const color = event.color || '#3498db';
    
    let html = '<div class="event-compact-item' + completedClass + pastClass + pastDueClass + '" data-event-id="' + event.id + '" data-date="' + date + '" style="border-left-color: ' + color + ';" onclick="' + (isPast && !isPastDue ? 'togglePastEventExpand(this)' : '') + '">';
    
    html += '<div class="event-info">';
    html += '<div class="event-title-row">';
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
            html += ' <span class="event-pastdue-badge">PAST DUE</span>';
        } else if (isToday) {
            html += ' <span class="event-today-badge">TODAY</span>';
        }
        // Add namespace badge (stored namespace or _namespace for multi-namespace)
        let eventNamespace = event.namespace || '';
        if (!eventNamespace && event._namespace !== undefined) {
            eventNamespace = event._namespace; // Fallback to _namespace for multi-namespace loading
        }
        if (eventNamespace) {
            html += ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' + calId + '\', \'' + escapeHtml(eventNamespace) + '\')" style="cursor:pointer;" title="Click to filter by this namespace">' + escapeHtml(eventNamespace) + '</span>';
        }
        // Add conflict warning if event has time conflicts
        if (event.hasConflict && event.conflictsWith && event.conflictsWith.length > 0) {
            // Build conflict list for data attribute
            let conflictList = [];
            event.conflictsWith.forEach(conflict => {
                let conflictText = conflict.title;
                if (conflict.time) {
                    conflictText += ' (' + formatTimeRange(conflict.time, conflict.endTime) + ')';
                }
                conflictList.push(conflictText);
            });
            
            html += ' <span class="event-conflict-badge" data-conflicts="' + escapeHtml(JSON.stringify(conflictList)) + '" onmouseenter="showConflictTooltip(this)" onmouseleave="hideConflictTooltip()">⚠️ ' + event.conflictsWith.length + '</span>';
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
            html += ' <span class="event-namespace-badge" onclick="filterCalendarByNamespace(\'' + calId + '\', \'' + escapeHtml(eventNamespace) + '\')" style="cursor:pointer;" title="Click to filter by this namespace">' + escapeHtml(eventNamespace) + '</span>';
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
    
    // Check if there's a filtered namespace active
    const calendar = document.getElementById(calId);
    const filteredNamespace = calendar.dataset.filteredNamespace;
    
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
        // Set min attribute to help the date picker open on the right month
        endDateField.setAttribute('min', defaultDate);
    }
    
    // Set default color
    document.getElementById('event-color-' + calId).value = '#3498db';
    
    // Initialize end time dropdown (disabled by default since no start time set)
    const endTimeField = document.getElementById('event-end-time-' + calId);
    if (endTimeField) {
        endTimeField.disabled = true;
        endTimeField.value = '';
    }
    
    // Initialize namespace search
    initNamespaceSearch(calId);
    
    // Set title
    title.textContent = 'Add Event';
    
    // Show dialog
    dialog.style.display = 'flex';
    
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
            // Set min attribute to help date picker open on the start date's month
            endDateField.setAttribute('min', date);
            
            document.getElementById('event-title-' + calId).value = event.title;
            document.getElementById('event-time-' + calId).value = event.time || '';
            document.getElementById('event-end-time-' + calId).value = event.endTime || '';
            document.getElementById('event-color-' + calId).value = event.color || '#3498db';
            document.getElementById('event-desc-' + calId).value = event.description || '';
            document.getElementById('event-is-task-' + calId).checked = event.isTask || false;
            
            // Update end time options based on start time
            if (event.time) {
                updateEndTimeOptions(calId);
            }
            
            // Initialize namespace search
            initNamespaceSearch(calId);
            
            // Set namespace fields if available
            const namespaceHidden = document.getElementById('event-namespace-' + calId);
            const namespaceSearch = document.getElementById('event-namespace-search-' + calId);
            if (namespaceHidden && event.namespace !== undefined) {
                namespaceHidden.value = event.namespace;
                if (namespaceSearch) {
                    namespaceSearch.value = event.namespace || '(default)';
                }
            }
            
            title.textContent = 'Edit Event';
            dialog.style.display = 'flex';
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
        eventId: eventId
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Extract year and month from date
            const [year, month] = date.split('-').map(Number);
            
            // Reload calendar data via AJAX
            reloadCalendarData(calId, year, month, namespace);
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
        recurrenceEnd: recurrenceEnd
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeEventDialog(calId);
            
            // For recurring events, do a full page reload to show all occurrences
            if (isRecurring) {
                location.reload();
                return;
            }
            
            // Extract year and month from the NEW date (in case date was changed)
            const [year, month] = date.split('-').map(Number);
            
            // Reload calendar data via AJAX to the month of the event
            reloadCalendarData(calId, year, month, namespace);
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
    if (!eventList) return;
    
    const eventItem = eventList.querySelector('[data-event-id="' + eventId + '"][data-date="' + date + '"]');
    if (!eventItem) return;
    
    // Remove previous highlights
    const previousHighlights = eventList.querySelectorAll('.event-highlighted');
    previousHighlights.forEach(el => el.classList.remove('event-highlighted'));
    
    // Add highlight
    eventItem.classList.add('event-highlighted');
    
    // Scroll to event
    eventItem.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'nearest',
        inline: 'nearest'
    });
    
    // Remove highlight after 3 seconds
    setTimeout(() => {
        eventItem.classList.remove('event-highlighted');
    }, 3000);
};

// Toggle recurring event options
window.toggleRecurringOptions = function(calId) {
    const checkbox = document.getElementById('event-recurring-' + calId);
    const options = document.getElementById('recurring-options-' + calId);
    
    if (checkbox && options) {
        options.style.display = checkbox.checked ? 'block' : 'none';
    }
};

// Close dialog on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const dialogs = document.querySelectorAll('.event-dialog-compact');
        dialogs.forEach(dialog => {
            if (dialog.style.display === 'flex') {
                dialog.style.display = 'none';
            }
        });
    }
});

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
        completed: completed ? '1' : '0'
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const [year, month] = date.split('-').map(Number);
            reloadCalendarData(calId, year, month, namespace);
        }
    })
    .catch(err => console.error('Error toggling task:', err));
};

// Make dialog draggable
window.makeDialogDraggable = function(calId) {
    const dialog = document.getElementById('dialog-content-' + calId);
    const handle = document.getElementById('drag-handle-' + calId);
    
    if (!dialog || !handle) return;
    
    let isDragging = false;
    let currentX;
    let currentY;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;
    
    handle.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', dragEnd);
    
    function dragStart(e) {
        initialX = e.clientX - xOffset;
        initialY = e.clientY - yOffset;
        isDragging = true;
    }
    
    function drag(e) {
        if (isDragging) {
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            xOffset = currentX;
            yOffset = currentY;
            setTranslate(currentX, currentY, dialog);
        }
    }
    
    function dragEnd(e) {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
    }
    
    function setTranslate(xPos, yPos, el) {
        el.style.transform = `translate(${xPos}px, ${yPos}px)`;
    }
};

// Initialize dialog draggability when opened (avoid duplicate declaration)
if (!window.calendarDraggabilityPatched) {
    window.calendarDraggabilityPatched = true;
    
    const originalOpenAddEvent = openAddEvent;
    openAddEvent = function(calId, namespace, date) {
        originalOpenAddEvent(calId, namespace, date);
        setTimeout(() => makeDialogDraggable(calId), 100);
    };

    const originalEditEvent = editEvent;
    editEvent = function(calId, eventId, date, namespace) {
        originalEditEvent(calId, eventId, date, namespace);
        setTimeout(() => makeDialogDraggable(calId), 100);
    };
}

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

// Filter calendar by namespace when clicking namespace badge
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('event-namespace-badge')) {
        const namespace = e.target.textContent;
        const eventItem = e.target.closest('.event-compact-item');
        const eventList = e.target.closest('.event-list-compact');
        const calendar = e.target.closest('.calendar-compact-container');
        
        if (!eventList || !calendar) return;
        
        const calId = calendar.id;
        
        // Check if already filtered
        const isFiltered = eventList.classList.contains('namespace-filtered');
        
        if (isFiltered && eventList.dataset.filterNamespace === namespace) {
            // Unfilter - show all
            eventList.classList.remove('namespace-filtered');
            delete eventList.dataset.filterNamespace;
            delete calendar.dataset.filteredNamespace;
            eventList.querySelectorAll('.event-compact-item').forEach(item => {
                item.style.display = '';
            });
            
            // Update header to show "all namespaces"
            updateFilteredNamespaceDisplay(calId, null);
        } else {
            // Filter by this namespace
            eventList.classList.add('namespace-filtered');
            eventList.dataset.filterNamespace = namespace;
            calendar.dataset.filteredNamespace = namespace;
            eventList.querySelectorAll('.event-compact-item').forEach(item => {
                const itemBadge = item.querySelector('.event-namespace-badge');
                if (itemBadge && itemBadge.textContent === namespace) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Update header to show filtered namespace
            updateFilteredNamespaceDisplay(calId, namespace);
        }
    }
});

// Update the displayed filtered namespace in event list header
window.updateFilteredNamespaceDisplay = function(calId, namespace) {
    const calendar = document.getElementById(calId);
    if (!calendar) return;
    
    const headerContent = calendar.querySelector('.event-list-header-content');
    if (!headerContent) return;
    
    // Remove existing filter badge
    let filterBadge = headerContent.querySelector('.namespace-filter-badge');
    if (filterBadge) {
        filterBadge.remove();
    }
    
    // Add new filter badge if filtering
    if (namespace) {
        filterBadge = document.createElement('span');
        filterBadge.className = 'namespace-badge namespace-filter-badge';
        filterBadge.innerHTML = escapeHtml(namespace) + ' <button class="filter-clear-inline" onclick="clearNamespaceFilter(\'' + calId + '\'); event.stopPropagation();">✕</button>';
        headerContent.appendChild(filterBadge);
    }
};

// Clear namespace filter
window.clearNamespaceFilter = function(calId) {
    
    const container = document.getElementById(calId);
    if (!container) {
        console.error('Calendar container not found:', calId);
        return;
    }
    
    // Get current year and month
    const year = parseInt(container.dataset.year) || new Date().getFullYear();
    const month = parseInt(container.dataset.month) || (new Date().getMonth() + 1);
    
    // Get original namespace (what the calendar was initialized with)
    const originalNamespace = container.dataset.originalNamespace || '';
    
    
    // Reload calendar with original namespace
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
    
    // Event listeners
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

// Update end time options based on start time selection
window.updateEndTimeOptions = function(calId) {
    const startTimeSelect = document.getElementById('event-time-' + calId);
    const endTimeSelect = document.getElementById('event-end-time-' + calId);
    
    if (!startTimeSelect || !endTimeSelect) return;
    
    const startTime = startTimeSelect.value;
    
    // If start time is empty (all day), disable end time
    if (!startTime) {
        endTimeSelect.disabled = true;
        endTimeSelect.value = '';
        return;
    }
    
    // Enable end time select
    endTimeSelect.disabled = false;
    
    // Convert start time to minutes
    const startMinutes = timeToMinutes(startTime);
    
    // Get current end time value (to preserve if valid)
    const currentEndTime = endTimeSelect.value;
    const currentEndMinutes = currentEndTime ? timeToMinutes(currentEndTime) : 0;
    
    // Filter options - show only times after start time
    const options = endTimeSelect.options;
    let firstValidOption = null;
    let currentStillValid = false;
    
    for (let i = 0; i < options.length; i++) {
        const option = options[i];
        const optionValue = option.value;
        
        if (optionValue === '') {
            // Keep "Same as start" option visible
            option.style.display = '';
            continue;
        }
        
        const optionMinutes = timeToMinutes(optionValue);
        
        if (optionMinutes > startMinutes) {
            // Show options after start time
            option.style.display = '';
            if (!firstValidOption) {
                firstValidOption = optionValue;
            }
            if (optionValue === currentEndTime) {
                currentStillValid = true;
            }
        } else {
            // Hide options before or equal to start time
            option.style.display = 'none';
        }
    }
    
    // If current end time is now invalid, set a new one
    if (!currentStillValid || currentEndMinutes <= startMinutes) {
        // Try to set to 1 hour after start
        const [startHour, startMinute] = startTime.split(':').map(Number);
        let endHour = startHour + 1;
        let endMinute = startMinute;
        
        if (endHour >= 24) {
            endHour = 23;
            endMinute = 45;
        }
        
        const suggestedEndTime = String(endHour).padStart(2, '0') + ':' + String(endMinute).padStart(2, '0');
        
        // Check if suggested time is in the list
        const suggestedExists = Array.from(options).some(opt => opt.value === suggestedEndTime);
        
        if (suggestedExists) {
            endTimeSelect.value = suggestedEndTime;
        } else if (firstValidOption) {
            // Use first valid option
            endTimeSelect.value = firstValidOption;
        } else {
            // No valid options (shouldn't happen, but just in case)
            endTimeSelect.value = '';
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

// Show custom conflict tooltip
window.showConflictTooltip = function(badgeElement) {
    // Remove any existing tooltip
    hideConflictTooltip();
    
    // Get conflict data
    const conflictsJson = badgeElement.getAttribute('data-conflicts');
    if (!conflictsJson) return;
    
    let conflicts;
    try {
        conflicts = JSON.parse(conflictsJson);
    } catch (e) {
        console.error('Failed to parse conflicts:', e);
        return;
    }
    
    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.id = 'conflict-tooltip';
    tooltip.className = 'conflict-tooltip';
    
    // Build content
    let html = '<div class="conflict-tooltip-header">⚠️ Time Conflicts</div>';
    html += '<div class="conflict-tooltip-body">';
    conflicts.forEach(conflict => {
        html += '<div class="conflict-item">• ' + escapeHtml(conflict) + '</div>';
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

// Filter events by search term
window.filterEvents = function(calId, searchTerm) {
    const eventList = document.getElementById('eventlist-' + calId);
    const searchClear = document.getElementById('search-clear-' + calId);
    
    if (!eventList) return;
    
    // Show/hide clear button
    if (searchClear) {
        searchClear.style.display = searchTerm ? 'block' : 'none';
    }
    
    searchTerm = searchTerm.toLowerCase().trim();
    
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
        if (title) searchableText += title.textContent.toLowerCase() + ' ';
        if (description) searchableText += description.textContent.toLowerCase() + ' ';
        if (dateTime) searchableText += dateTime.textContent.toLowerCase() + ' ';
        
        // Check if matches search
        const matches = !searchTerm || searchableText.includes(searchTerm);
        
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
    
    // Show "no results" message if nothing visible
    let noResultsMsg = eventList.querySelector('.no-search-results');
    if (visibleCount === 0 && searchTerm) {
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

// Clear event search
window.clearEventSearch = function(calId) {
    const searchInput = document.getElementById('event-search-' + calId);
    if (searchInput) {
        searchInput.value = '';
        filterEvents(calId, '');
        searchInput.focus();
    }
};

// End of calendar plugin JavaScript

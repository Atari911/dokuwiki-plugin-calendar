/**
 * DokuWiki Compact Calendar Plugin JavaScript
 */

// Navigate to different month
function navCalendar(calId, year, month, namespace) {
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
        console.log('=== navCalendar AJAX Response ===');
        console.log('Requested year:', year, 'month:', month);
        console.log('Response:', data);
        console.log('Response year:', data.year, 'month:', data.month);
        console.log('Event date keys:', Object.keys(data.events || {}));
        if (data.success) {
            console.log('Rebuilding calendar for', year, month, 'with', Object.keys(data.events || {}).length, 'date entries');
            rebuildCalendar(calId, data.year, data.month, data.events, namespace);
        } else {
            console.error('Failed to load month:', data.error);
        }
    })
    .catch(err => {
        console.error('Error loading month:', err);
    });
}

// Jump to current month
function jumpToToday(calId, namespace) {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1; // JavaScript months are 0-indexed
    navCalendar(calId, year, month, namespace);
}

// Jump to today for event panel
function jumpTodayPanel(calId, namespace) {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    navEventPanel(calId, year, month, namespace);
}

// Open month picker dialog
function openMonthPicker(calId, currentYear, currentMonth, namespace) {
    console.log('openMonthPicker called:', calId, currentYear, currentMonth, namespace);
    
    const overlay = document.getElementById('month-picker-overlay-' + calId);
    console.log('Overlay element:', overlay);
    
    const monthSelect = document.getElementById('month-picker-month-' + calId);
    console.log('Month select:', monthSelect);
    
    const yearSelect = document.getElementById('month-picker-year-' + calId);
    console.log('Year select:', yearSelect);
    
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
    console.log('Overlay display set to flex');
}

// Open month picker dialog for event panel
function openMonthPickerPanel(calId, currentYear, currentMonth, namespace) {
    openMonthPicker(calId, currentYear, currentMonth, namespace);
}

// Close month picker dialog
function closeMonthPicker(calId) {
    const overlay = document.getElementById('month-picker-overlay-' + calId);
    overlay.style.display = 'none';
}

// Jump to selected month
function jumpToSelectedMonth(calId, namespace) {
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
}

// Rebuild calendar grid after navigation
function rebuildCalendar(calId, year, month, events, namespace) {
    console.log('=== rebuildCalendar DEBUG ===');
    console.log('Requested:', {year, month, namespace});
    console.log('Event date keys received:', Object.keys(events));
    console.log('Events object:', events);
    
    const container = document.getElementById(calId);
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    
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
}

// Render event list from data
function renderEventListFromData(events, calId, namespace, year, month) {
    if (!events || Object.keys(events).length === 0) {
        return '<p class="no-events-msg">No events this month</p>';
    }
    
    let html = '';
    const sortedDates = Object.keys(events).sort();
    
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
        
        // Sort events within this day by time
        const dayEvents = events[dateKey];
        dayEvents.sort((a, b) => {
            const timeA = a.time || '00:00';
            const timeB = b.time || '00:00';
            return timeA.localeCompare(timeB);
        });
        
        for (const event of dayEvents) {
            html += renderEventItem(event, dateKey, calId, namespace);
        }
    }
    
    if (!html) {
        return '<p class="no-events-msg">No events this month</p>';
    }
    
    return html;
}

// Show day popup with events when clicking a date
function showDayPopup(calId, date, namespace) {
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
    
    if (dayEvents.length === 0) {
        html += '<p class="no-events-msg">No events on this day</p>';
    } else {
        html += '<div class="popup-events-list">';
        dayEvents.forEach(event => {
            const color = event.color || '#3498db';
            
            // Use individual event namespace if available (for multi-namespace support)
            const eventNamespace = event._namespace !== undefined ? event._namespace : namespace;
            
            // Convert to 12-hour format
            let displayTime = '';
            if (event.time) {
                const timeParts = event.time.split(':');
                if (timeParts.length === 2) {
                    let hour = parseInt(timeParts[0]);
                    const minute = timeParts[1];
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    hour = hour % 12 || 12;
                    displayTime = hour + ':' + minute + ' ' + ampm;
                } else {
                    displayTime = event.time;
                }
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
}

// Close day popup
function closeDayPopup(calId) {
    const popup = document.getElementById('day-popup-' + calId);
    if (popup) {
        popup.style.display = 'none';
    }
}

// Show events for a specific day (for event list panel)
function showDayEvents(calId, date, namespace) {
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
}

// Render a single event item
function renderEventItem(event, date, calId, namespace) {
    // Check if this event is in the past or today
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const eventDate = new Date(date + 'T00:00:00');
    const isPast = eventDate < today;
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
    
    // Convert to 12-hour format
    let displayTime = '';
    if (event.time) {
        const timeParts = event.time.split(':');
        if (timeParts.length === 2) {
            let hour = parseInt(timeParts[0]);
            const minute = timeParts[1];
            const ampm = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12 || 12;
            displayTime = hour + ':' + minute + ' ' + ampm;
        } else {
            displayTime = event.time;
        }
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
    const pastClass = isPast ? ' event-past' : '';
    const color = event.color || '#3498db';
    const isTask = event.isTask || false;
    const completed = event.completed || false;
    
    let html = '<div class="event-compact-item' + completedClass + pastClass + '" data-event-id="' + event.id + '" data-date="' + date + '" style="border-left-color: ' + color + ';" onclick="' + (isPast ? 'togglePastEventExpand(this)' : '') + '">';
    
    html += '<div class="event-info">';
    html += '<div class="event-title-row">';
    html += '<span class="event-title-compact">' + escapeHtml(event.title) + '</span>';
    html += '</div>';
    
    // Only show meta and description for non-past events (collapsed for past)
    if (!isPast) {
        html += '<div class="event-meta-compact">';
        html += '<span class="event-date-time">' + displayDate + multiDay;
        if (displayTime) {
            html += ' • ' + displayTime;
        }
        // Add TODAY badge for today's events
        if (isToday) {
            html += ' <span class="event-today-badge">TODAY</span>';
        }
        // Add namespace badge (stored namespace or _namespace for multi-namespace)
        let eventNamespace = event.namespace || '';
        if (!eventNamespace && event._namespace !== undefined) {
            eventNamespace = event._namespace; // Fallback to _namespace for multi-namespace loading
        }
        if (eventNamespace) {
            html += ' <span class="event-namespace-badge">' + escapeHtml(eventNamespace) + '</span>';
        }
        html += '</span>';
        html += '</div>';
        
        if (event.description) {
            html += '<div class="event-desc-compact">' + renderDescription(event.description) + '</div>';
        }
    } else {
        // For past events, store data in hidden divs for expand/collapse
        html += '<div class="event-meta-compact" style="display: none;">';
        html += '<span class="event-date-time">' + displayDate + multiDay;
        if (displayTime) {
            html += ' • ' + displayTime;
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
}

// Render description with rich content support
function renderDescription(description) {
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
function openAddEvent(calId, namespace, date) {
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
    
    console.log('Opening add event: filtered=' + filteredNamespace + ', passed=' + namespace + ', using=' + effectiveNamespace);
    
    // Reset form
    form.reset();
    document.getElementById('event-id-' + calId).value = '';
    
    // Store the effective namespace in a hidden field or data attribute
    form.dataset.effectiveNamespace = effectiveNamespace;
    
    // Set date - use local date, not UTC
    let defaultDate = date;
    if (!defaultDate) {
        // Get the currently displayed month from the calendar container
        const container = document.getElementById(calId);
        const displayedYear = parseInt(container.getAttribute('data-year'));
        const displayedMonth = parseInt(container.getAttribute('data-month'));
        
        console.log('Setting default date: year=' + displayedYear + ', month=' + displayedMonth);
        
        if (displayedYear && displayedMonth) {
            // Use first day of the displayed month
            const year = displayedYear;
            const month = String(displayedMonth).padStart(2, '0');
            defaultDate = `${year}-${month}-01`;
            console.log('Using displayed month:', defaultDate);
        } else {
            // Fallback to today if attributes not found
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            defaultDate = `${year}-${month}-${day}`;
            console.log('Fallback to today:', defaultDate);
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
    
    // Set title
    title.textContent = 'Add Event';
    
    // Show dialog
    dialog.style.display = 'flex';
    
    // Focus title field
    setTimeout(() => {
        const titleField = document.getElementById('event-title-' + calId);
        if (titleField) titleField.focus();
    }, 100);
}

// Edit event
function editEvent(calId, eventId, date, namespace) {
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
            
            if (!dateField) {
                console.error('Date field not found when editing!');
                return;
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
            document.getElementById('event-color-' + calId).value = event.color || '#3498db';
            document.getElementById('event-desc-' + calId).value = event.description || '';
            document.getElementById('event-is-task-' + calId).checked = event.isTask || false;
            
            title.textContent = 'Edit Event';
            dialog.style.display = 'flex';
        }
    })
    .catch(err => console.error('Error editing event:', err));
}

// Delete event
function deleteEvent(calId, eventId, date, namespace) {
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
}

// Save event (add or edit)
function saveEventCompact(calId, namespace) {
    const form = document.getElementById('eventform-' + calId);
    
    // Use the effective namespace (filtered namespace if active, otherwise passed namespace)
    const effectiveNamespace = form.dataset.effectiveNamespace || namespace;
    
    console.log('Saving event: passed namespace=' + namespace + ', effective=' + effectiveNamespace);
    
    const eventId = document.getElementById('event-id-' + calId).value;
    const dateInput = document.getElementById('event-date-' + calId);
    const date = dateInput.value;
    const oldDate = dateInput.getAttribute('data-original-date') || date;
    const endDate = document.getElementById('event-end-date-' + calId).value;
    const title = document.getElementById('event-title-' + calId).value;
    const time = document.getElementById('event-time-' + calId).value;
    const color = document.getElementById('event-color-' + calId).value;
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
        namespace: effectiveNamespace,
        eventId: eventId,
        date: date,
        oldDate: oldDate,
        endDate: endDate,
        title: title,
        time: time,
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
}

// Reload calendar data without page refresh
function reloadCalendarData(calId, year, month, namespace) {
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
}

// Close event dialog
function closeEventDialog(calId) {
    const dialog = document.getElementById('dialog-' + calId);
    dialog.style.display = 'none';
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Highlight event when clicking on bar in calendar
function highlightEvent(calId, eventId, date) {
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
}

// Toggle recurring event options
function toggleRecurringOptions(calId) {
    const checkbox = document.getElementById('event-recurring-' + calId);
    const options = document.getElementById('recurring-options-' + calId);
    
    if (checkbox && options) {
        options.style.display = checkbox.checked ? 'block' : 'none';
    }
}

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
function navEventPanel(calId, year, month, namespace) {
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
}

// Rebuild event panel only
function rebuildEventPanel(calId, year, month, events, namespace) {
    const container = document.getElementById(calId);
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    
    // Update header - preserve the onclick and classes
    const headerContent = container.querySelector('.panel-header-content');
    const header = container.querySelector('.panel-standalone-header h3');
    if (header) {
        header.textContent = monthNames[month - 1] + ' ' + year + ' Events';
        header.className = 'calendar-month-picker';
        header.setAttribute('onclick', `openMonthPickerPanel('${calId}', ${year}, ${month}, '${namespace}')`);
        header.setAttribute('title', 'Click to jump to month');
    }
    
    // Update namespace badge if needed (preserve existing one)
    // The namespace badge should already exist and doesn't need updating
    
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
    if (navBtns[0]) navBtns[0].setAttribute('onclick', `navEventPanel('${calId}', ${prevYear}, ${prevMonth}, '${namespace}')`);
    if (navBtns[1]) navBtns[1].setAttribute('onclick', `navEventPanel('${calId}', ${nextYear}, ${nextMonth}, '${namespace}')`);
    
    // Update Today button
    const todayBtn = container.querySelector('.cal-today-btn');
    if (todayBtn) {
        todayBtn.setAttribute('onclick', `jumpTodayPanel('${calId}', '${namespace}')`);
    }
    
    // Rebuild event list
    const eventList = container.querySelector('.event-list-compact');
    if (eventList) {
        eventList.innerHTML = renderEventListFromData(events, calId, namespace, year, month);
    }
}

// Open add event for panel
function openAddEventPanel(calId, namespace) {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const localDate = `${year}-${month}-${day}`;
    openAddEvent(calId, namespace, localDate);
}

// Toggle task completion
function toggleTaskComplete(calId, eventId, date, namespace, completed) {
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
}

// Make dialog draggable
function makeDialogDraggable(calId) {
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
}

// Initialize dialog draggability when opened
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

// Toggle expand/collapse for past events
function togglePastEventExpand(element) {
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
}

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
function updateFilteredNamespaceDisplay(calId, namespace) {
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
}

// Clear namespace filter
function clearNamespaceFilter(calId) {
    const calendar = document.getElementById(calId);
    if (!calendar) return;
    
    const eventList = calendar.querySelector('.event-list-compact');
    if (!eventList) return;
    
    // Clear filter
    eventList.classList.remove('namespace-filtered');
    delete eventList.dataset.filterNamespace;
    delete calendar.dataset.filteredNamespace;
    eventList.querySelectorAll('.event-compact-item').forEach(item => {
        item.style.display = '';
    });
    
    // Update header
    updateFilteredNamespaceDisplay(calId, null);
}

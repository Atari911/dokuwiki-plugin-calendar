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
        console.log('Month navigation data:', data);
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
    const container = document.getElementById(calId);
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    
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
    
    // Rebuild event list - show events that overlap with current month
    const currentMonthEvents = {};
    
    for (const [dateKey, dayEvents] of Object.entries(events)) {
        for (const event of dayEvents) {
            const startDate = dateKey;
            const endDate = event.endDate || dateKey;
            
            // Check if event overlaps with current month
            // Event starts before month ends AND ends after month starts
            const eventStartObj = new Date(startDate);
            const eventEndObj = new Date(endDate);
            const monthFirstDay = new Date(year, month - 1, 1);
            const monthLastDay = new Date(year, month - 1, daysInMonth);
            
            // Include if event overlaps this month at all
            if (eventEndObj >= monthFirstDay && eventStartObj <= monthLastDay) {
                if (!currentMonthEvents[dateKey]) {
                    currentMonthEvents[dateKey] = [];
                }
                currentMonthEvents[dateKey].push(event);
            }
        }
    }
    
    const eventList = container.querySelector('.event-list-compact');
    eventList.innerHTML = renderEventListFromData(currentMonthEvents, calId, namespace);
    
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
        
        const dayEvents = events[dateKey];
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
            html += '<div class="popup-event-item">';
            html += '<div class="event-color-bar" style="background: ' + color + ';"></div>';
            html += '<div class="popup-event-content">';
            html += '<div class="popup-event-title">' + escapeHtml(event.title) + '</div>';
            if (event.time) {
                html += '<div class="popup-event-time">🕐 ' + escapeHtml(event.time) + '</div>';
            }
            if (event.description) {
                html += '<div class="popup-event-desc">' + escapeHtml(event.description).replace(/\n/g, '<br>') + '</div>';
            }
            html += '<div class="popup-event-actions">';
            html += '<button class="event-edit-btn" onclick="editEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + namespace + '\'); closeDayPopup(\'' + calId + '\')">Edit</button>';
            html += '<button class="event-delete-btn" onclick="deleteEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + namespace + '\'); closeDayPopup(\'' + calId + '\')">Delete</button>';
            html += '</div>';
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
        namespace: namespace
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
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
    // Format date display with day of week
    const dateObj = new Date(date + 'T00:00:00');
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
    if (event.endDate && event.endDate !== date) {
        const endObj = new Date(event.endDate + 'T00:00:00');
        multiDay = ' → ' + endObj.toLocaleDateString('en-US', { 
            weekday: 'short',
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    const completedClass = event.completed ? ' event-completed' : '';
    const color = event.color || '#3498db';
    const isTask = event.isTask || false;
    const completed = event.completed || false;
    
    let html = '<div class="event-compact-item' + completedClass + '" data-event-id="' + event.id + '" data-date="' + date + '" style="border-left-color: ' + color + ';">';
    
    html += '<div class="event-info">';
    html += '<div class="event-title-row">';
    html += '<span class="event-title-compact">' + escapeHtml(event.title) + '</span>';
    html += '</div>';
    
    html += '<div class="event-meta-compact">';
    html += '<span class="event-date-time">' + displayDate + multiDay;
    if (displayTime) {
        html += ' • ' + displayTime;
    }
    html += '</span>';
    html += '</div>';
    
    if (event.description) {
        html += '<div class="event-desc-compact">' + renderDescription(event.description) + '</div>';
    }
    
    html += '</div>'; // event-info
    
    html += '<div class="event-actions-compact">';
    html += '<button class="event-action-btn" onclick="deleteEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + namespace + '\')">🗑️</button>';
    html += '<button class="event-action-btn" onclick="editEvent(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + namespace + '\')">✏️</button>';
    html += '</div>';
    
    // Checkbox for tasks - ON THE FAR RIGHT
    if (isTask) {
        const checked = completed ? 'checked' : '';
        html += '<input type="checkbox" class="task-checkbox" ' + checked + ' onclick="toggleTaskComplete(\'' + calId + '\', \'' + event.id + '\', \'' + date + '\', \'' + namespace + '\', this.checked)">';
    }
    
    html += '</div>';
    
    return html;
}

// Render description with rich content support
function renderDescription(description) {
    if (!description) return '';
    
    let rendered = escapeHtml(description);
    
    // Convert newlines to <br>
    rendered = rendered.replace(/\n/g, '<br>');
    
    // Convert DokuWiki image syntax {{image.jpg}} to HTML
    rendered = rendered.replace(/\{\{([^}|]+?)(?:\|([^}]+))?\}\}/g, function(match, imagePath, alt) {
        imagePath = imagePath.trim();
        alt = alt ? alt.trim() : '';
        
        // Handle external URLs
        if (imagePath.match(/^https?:\/\//)) {
            return '<img src="' + imagePath + '" alt="' + alt + '" class="event-image" />';
        }
        
        // Handle internal DokuWiki images
        const imageUrl = DOKU_BASE + 'lib/exe/fetch.php?media=' + encodeURIComponent(imagePath);
        return '<img src="' + imageUrl + '" alt="' + alt + '" class="event-image" />';
    });
    
    // Convert DokuWiki link syntax [[link|text]] to HTML
    rendered = rendered.replace(/\[\[([^|\]]+?)(?:\|([^\]]+))?\]\]/g, function(match, link, text) {
        link = link.trim();
        text = text ? text.trim() : link;
        
        // Handle external URLs
        if (link.match(/^https?:\/\//)) {
            return '<a href="' + link + '" target="_blank" rel="noopener noreferrer">' + text + '</a>';
        }
        
        // Handle internal DokuWiki links with section anchors
        // Split page and section (e.g., "page#section" or "namespace:page#section")
        const hashIndex = link.indexOf('#');
        let pagePart = link;
        let sectionPart = '';
        
        if (hashIndex !== -1) {
            pagePart = link.substring(0, hashIndex);
            sectionPart = link.substring(hashIndex); // Includes the #
        }
        
        // Build URL with properly encoded page and unencoded section anchor
        const wikiUrl = DOKU_BASE + 'doku.php?id=' + encodeURIComponent(pagePart) + sectionPart;
        return '<a href="' + wikiUrl + '">' + escapeHtml(text) + '</a>';
    });
    
    // Convert markdown-style links [text](url) to HTML
    rendered = rendered.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, text, url) {
        text = text.trim();
        url = url.trim();
        
        if (url.match(/^https?:\/\//)) {
            return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + text + '</a>';
        }
        
        return '<a href="' + url + '">' + text + '</a>';
    });
    
    // Convert plain URLs to clickable links
    rendered = rendered.replace(/(https?:\/\/[^\s<]+)/g, function(match, url) {
        return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a>';
    });
    
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
    
    // Reset form
    form.reset();
    document.getElementById('event-id-' + calId).value = '';
    
    // Set date - use local date, not UTC
    let defaultDate = date;
    if (!defaultDate) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        defaultDate = `${year}-${month}-${day}`;
    }
    dateField.value = defaultDate;
    dateField.removeAttribute('data-original-date');
    
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
            document.getElementById('event-end-date-' + calId).value = event.endDate || '';
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
        namespace: namespace,
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
        namespace: namespace
    });
    
    fetch(DOKU_BASE + 'lib/exe/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
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

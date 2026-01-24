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
            rebuildCalendar(calId, data.year, data.month, data.events, namespace);
        }
    })
    .catch(err => console.error('Error:', err));
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
                const today = new Date().toISOString().split('T')[0];
                const isToday = dateKey === today;
                const hasEvents = events[dateKey] && events[dateKey].length > 0;
                
                let classes = 'cal-day';
                if (isToday) classes += ' cal-today';
                if (hasEvents) classes += ' cal-has-events';
                
                html += `<td class="${classes}" data-date="${dateKey}" onclick="showDayPopup('${calId}', '${dateKey}', '${namespace}')">`;
                html += `<span class="day-num">${currentDay}</span>`;
                
                if (hasEvents) {
                    // Sort events by time (no time first, then by time)
                    const sortedEvents = [...events[dateKey]].sort((a, b) => {
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
                        const barClass = !eventTime ? 'event-bar-no-time' : 'event-bar-timed';
                        
                        html += `<span class="event-bar ${barClass}" `;
                        html += `style="background: ${eventColor};" `;
                        html += `title="${escapeHtml(eventTitle)}${eventTime ? ' @ ' + eventTime : ''}" `;
                        html += `onclick="event.stopPropagation(); highlightEvent('${calId}', '${eventId}', '${dateKey}');"></span>`;
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
    
    // Rebuild event list
    const eventList = container.querySelector('.event-list-compact');
    eventList.innerHTML = renderEventListFromData(events, calId, namespace);
    
    // Update title
    const title = container.querySelector('#eventlist-title-' + calId);
    title.textContent = 'Events';
}

// Render event list from data
function renderEventListFromData(events, calId, namespace) {
    if (!events || Object.keys(events).length === 0) {
        return '<p class="no-events-msg">No events this month</p>';
    }
    
    let html = '';
    const sortedDates = Object.keys(events).sort();
    
    for (const dateKey of sortedDates) {
        const dayEvents = events[dateKey];
        for (const event of dayEvents) {
            html += renderEventItem(event, dateKey, calId, namespace);
        }
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
    // Format date display
    const dateObj = new Date(date + 'T00:00:00');
    const displayDate = dateObj.toLocaleDateString('en-US', { 
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
        
        // Handle internal DokuWiki links
        const wikiUrl = DOKU_BASE + 'doku.php?id=' + encodeURIComponent(link);
        return '<a href="' + wikiUrl + '">' + text + '</a>';
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
    
    // Set date
    const defaultDate = date || new Date().toISOString().split('T')[0];
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
            closeEventDialog(calId);
            
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
    
    // Update header
    const header = container.querySelector('.panel-standalone-header h3');
    header.textContent = monthNames[month - 1] + ' ' + year + ' Events';
    
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
    navBtns[0].setAttribute('onclick', `navEventPanel('${calId}', ${prevYear}, ${prevMonth}, '${namespace}')`);
    navBtns[1].setAttribute('onclick', `navEventPanel('${calId}', ${nextYear}, ${nextMonth}, '${namespace}')`);
    
    // Rebuild event list
    const eventList = container.querySelector('.event-list-compact');
    eventList.innerHTML = renderEventListFromData(events, calId, namespace);
}

// Open add event for panel
function openAddEventPanel(calId, namespace) {
    openAddEvent(calId, namespace, new Date().toISOString().split('T')[0]);
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

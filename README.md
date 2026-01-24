# DokuWiki Compact Calendar Plugin

A sleek, space-efficient calendar plugin with integrated event list panel.

## Design Specifications

- **Dimensions**: 800x600 pixels (compact, fixed-size)
- **Layout**: Split view with calendar on left (500px) and event list on right (300px)
- **Cell Size**: Excel-like cells (~65px height, proportional width)
- **Style**: Clean, modern, professional

## Features

✨ **Compact Design**: Fits perfectly in 800x600 area  
📅 **Month View**: Excel-style grid with clean borders  
📋 **Integrated Event List**: View and manage events in right panel  
🎛️ **Event Panel Only**: Display just the event panel (320px width)  
🪟 **Day Popup**: Click any date to see/edit events in popup window  
➕ **Quick Add**: Add events from calendar, panel, or popup  
✏️ **Edit/Delete**: Full event management with date changes  
🎨 **Color Coding**: Custom colors for events  
⚡ **Real-time Updates**: AJAX-powered, no page reloads needed  
📱 **Responsive Event List**: Scrollable panel for many events  
🔍 **Day Filter**: Click any day to see events in popup  
📊 **Standalone Event Lists**: Display events independently

## Installation

1. Extract to `lib/plugins/calendar/`
2. Create data directory:
   ```bash
   mkdir -p data/meta/calendar
   chmod 775 data/meta/calendar
   ```
3. Plugin will be auto-detected by DokuWiki

## Usage

### Basic Calendar

```
{{calendar}}
```

Displays current month with event list panel.

### Specific Month

```
{{calendar year=2026 month=6}}
```

### With Namespace

```
{{calendar namespace=team}}
```

Events stored separately for different teams/projects.

### Event Panel Only (Right Panel)

Display just the event panel without the calendar grid:

```
{{eventpanel}}
```

Or with specific month:

```
{{eventpanel year=2026 month=6 namespace=team}}
```

Perfect for sidebars or when you want event management without the full calendar view.

### Standalone Event List

Display events in a list without the calendar:

```
{{eventlist date=2026-01-22}}
```

Or date range:

```
{{eventlist daterange=2026-01-01:2026-01-31}}
```

With namespace:

```
{{eventlist daterange=2026-01-01:2026-01-31 namespace=team}}
```

## How to Use

### Adding Events

1. Click **+ Add** button in event panel, OR
2. Click any day in the calendar to open day popup
3. Fill in event details:
   - Date (required - can be changed)
   - Title (required)
   - Time (optional)
   - Color (optional, default blue)
   - Description (optional)
4. Click **Save**

### Viewing Events

- **See all month events**: Scroll through event list panel
- **View day events**: Click any calendar day to see popup with that day's events
- **Orange dot**: Indicates day has events
- **Day popup**: Shows all events for clicked date with full details

### Editing Events

1. Click calendar day to open popup, OR find event in list panel
2. Click **Edit** button
3. Modify details (including date if you want to move the event)
4. Click **Save**

### Deleting Events

1. Click calendar day to open popup, OR find event in list panel
2. Click **Delete** button
3. Confirm deletion

## Design Details

### Calendar Grid
- **Cell size**: ~65px height (similar to Excel)
- **Grid lines**: Light gray borders
- **Today**: Blue highlight
- **Has events**: Yellow tint with orange dot
- **Hover**: Subtle highlight

### Event List Panel
- **Scrollable**: Handle many events efficiently
- **Color bar**: Left edge shows event color
- **Compact info**: Date, time, title, description
- **Action buttons**: Edit and Delete for each event

### Color Indicators
- **Blue background**: Today
- **Yellow background**: Days with events
- **Orange dot**: Event indicator
- **Gray**: Empty days

## Examples

### Team Calendar

```
====== Development Team Calendar ======

{{calendar namespace=team:dev}}

**Color Code**:
  * 🔵 Blue: Meetings
  * 🟢 Green: Deadlines
  * 🟡 Yellow: Code reviews
  * 🔴 Red: Releases
```

### Project Timeline

```
====== Project Alpha - January 2026 ======

{{calendar namespace=projects:alpha year=2026 month=1}}

**Milestones**:
  * Jan 5: Kickoff
  * Jan 15: Design complete
  * Jan 30: Development start
```

### Personal Schedule

```
====== My Schedule ======

{{calendar namespace=personal:john}}

**Regular Events**:
  * Monday: Team standup 9 AM
  * Wednesday: Client calls
  * Friday: Sprint review
```

### Event Panel Sidebar

```
====== Team Events ======

{{eventpanel namespace=team}}

Shows only the event management panel (320px wide) - perfect for page sidebars.
```

### Event Report

```
====== This Month's Events ======

{{eventlist daterange=2026-01-01:2026-01-31 namespace=team}}

**Summary**: 24 events scheduled
```

## File Storage

Events are stored in JSON format:

```
data/meta/calendar/2026-01.json
data/meta/calendar/2026-02.json
data/meta/[namespace]/calendar/2026-01.json
```

Each file contains all events for that month.

## Event Data Structure

```json
{
  "2026-01-22": [
    {
      "id": "abc123",
      "title": "Team Meeting",
      "time": "14:00",
      "description": "Weekly sync",
      "color": "#3498db",
      "created": "2026-01-20 10:00:00"
    }
  ]
}
```

## Troubleshooting

### Calendar not showing
- Check plugin is in `lib/plugins/calendar/`
- Verify all files present (syntax.php, action.php, style.css, script.js)
- Clear DokuWiki cache

### Events not saving
- Check `data/meta/calendar/` is writable
- Verify web server user has permissions
- Check browser console for errors

### Events not displaying
- Manually create test event file (see below)
- Check JSON is valid
- Verify date format is YYYY-MM-DD

### Test Event

Create `data/meta/calendar/2026-01.json`:

```json
{
  "2026-01-22": [
    {
      "id": "test1",
      "title": "Test Event",
      "time": "10:00",
      "description": "Testing",
      "color": "#e74c3c",
      "created": "2026-01-22 09:00:00"
    }
  ]
}
```

Then refresh calendar page.

## Technical Details

- **PHP**: 7.4+
- **DokuWiki**: 2020-07-29 "Hogfather" or newer
- **JavaScript**: ES6, uses Fetch API
- **CSS**: Modern flexbox layout
- **Size**: Exactly 800x600px

## Browser Support

- Chrome/Edge: ✅
- Firefox: ✅
- Safari: ✅
- Mobile: ⚠️ Fixed size may require horizontal scroll

## License

GPL 2.0

## Support

For issues or questions, refer to DokuWiki plugin documentation or forums.

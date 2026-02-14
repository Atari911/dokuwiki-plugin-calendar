# DokuWiki Calendar Plugin - Matrix Edition

A feature-rich calendar plugin for DokuWiki with multiple themes, Outlook sync, recurring events, and a static presentation mode.

## Features

### Calendar Views
- **Interactive Calendar** - Full-featured calendar with event management
- **Static Calendar** - Read-only presentation mode for public display
- **Sidebar Widget** - Compact upcoming events widget
- **Event Panel** - Standalone event list

### Event Management
- Create, edit, and delete events
- Recurring events (daily, weekly, monthly, yearly)
- Multi-day events with date ranges
- Time conflict detection
- Task mode with completion tracking
- Important event highlighting with ⭐

### Themes
- **Matrix** - Green on dark (default)
- **Pink** - Pink/magenta on dark
- **Purple** - Purple/violet on dark  
- **Professional** - Blue on white
- **Wiki** - Neutral gray (matches DokuWiki)
- **Dark** - Blue on dark gray
- **Light** - Clean white/gray

### Sync & Backup
- Outlook/ICS calendar sync
- Full event backup/restore
- Config import/export

### Localization
- English (en)
- German (de)

## Installation

1. Download the latest release
2. Extract to `lib/plugins/calendar/`
3. Access Admin > Calendar Management to configure

## Syntax

### Interactive Calendar
```
{{calendar}}
{{calendar namespace=work}}
{{calendar namespace=personal;work}}
{{calendar namespace=projects:*}}
```

### Static Calendar (Read-only)
```
{{calendar static}}
{{calendar namespace=meetings static}}
{{calendar month=2 static}}
{{calendar title="Club Events" static}}
{{calendar theme=professional static}}
{{calendar static noprint}}
```

#### Static Calendar Options

| Option | Description | Example |
|--------|-------------|---------|
| `static` | Enable read-only mode | `{{calendar static}}` |
| `namespace=X` | Filter by namespace | `namespace=meetings` |
| `month=X` | Lock to specific month (1-12) | `month=6` |
| `year=X` | Lock to specific year | `year=2026` |
| `title="X"` | Custom title (supports spaces) | `title="Team Events"` |
| `theme=X` | Apply theme | `theme=matrix` |
| `noprint` | Hide print button | `noprint` |

### Event Panel
```
{{eventpanel}}
{{eventpanel namespace=work height=400}}
```

### Event List
```
{{eventlist}}
{{eventlist namespace=meetings range=30}}
```

### Sidebar Widget
```
{{calendar sidebar}}
{{calendar sidebar namespace=important}}
```

## Admin Features

Access via **Admin > Calendar Management**:

- **Manage Events** - Browse, search, move events between namespaces
- **Recurring Events** - Manage series, extend, trim, pause/resume
- **Important Namespaces** - Configure which namespaces get ⭐ highlighting
- **Outlook Sync** - Configure ICS calendar synchronization
- **Backup/Restore** - Full event data backup
- **Themes** - Select and preview themes

## Event Description Formatting

Descriptions support DokuWiki-style formatting:

- `**bold**` or `__bold__` → **bold**
- `//italic//` → *italic*
- `[[page|text]]` → DokuWiki links
- `[text](url)` → Markdown links
- Line breaks preserved

## Keyboard Shortcuts

- `Escape` - Close dialogs
- `Enter` - Submit forms (when focused)

## Requirements

- DokuWiki (Hogfather or later recommended)
- PHP 7.4+
- Modern browser (Chrome, Firefox, Edge, Safari)

## License

GPL-2.0

## Author

atari911 (atari911@gmail.com)

## Links

- [DokuWiki Plugin Page](https://www.dokuwiki.org/plugin:calendar)
- [GitHub Repository](https://github.com/atari911/dokuwiki-plugin-calendar)
- [Issue Tracker](https://github.com/atari911/dokuwiki-plugin-calendar/issues)

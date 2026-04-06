# DokuWiki Calendar Plugin

A feature-rich calendar plugin for DokuWiki with multiple themes, namespace management, Outlook/Google sync, recurring events, DokuWiki farm compatibility, and ACL-enforced security.

## Features

### Calendar Views
- **Interactive Calendar** — Full-featured calendar with drag-friendly event management
- **Static Calendar** — Read-only presentation mode for public display, with print support
- **Event Panel** — Standalone scrollable event list with month navigation
- **Sidebar Widget** — Compact week-at-a-glance itinerary for the sidebar

### Event Management
- Create, edit, and delete events with an inline dialog
- Recurring events (daily, weekly, monthly, yearly) with interval control
- Multi-day events with date ranges
- Time conflict detection with visual badges
- Task mode with completion tracking
- Important event highlighting with ⭐
- Namespace-based organization (e.g., `work`, `personal`, `team:projects`)

### Namespace Filtering
- **Wildcard** — `namespace=*` loads events from all namespaces
- **Multi-namespace** — `namespace="work;personal"` loads from specific namespaces
- **Prefixed wildcard** — `namespace=team:*` loads all sub-namespaces under `team`
- **Exclude** — `exclude=journal` hides specific namespaces from wildcard views
- **Multiple excludes** — `exclude="journal;drafts"` hides multiple namespaces (semicolon-separated)
- Exclude matches by prefix: `exclude=journal` also hides `journal:daily`, `journal:notes:2026`, etc.

### Themes
- **Matrix** — Green on dark (default)
- **Purple** — Purple/violet on dark
- **Professional** — Blue on white
- **Pink** — Hot pink with particle effects ✨
- **Wiki** — Neutral gray (matches DokuWiki template)

### Search
- Real-time event filtering as you type
- Configurable default search scope (current month or all dates) via admin setting
- Toggle between month and all-dates search with the 📅/🌐 button

### Sync & Integration
- **Outlook/Office 365** — Delta sync via Microsoft Graph API (cron-based)
- **Google Calendar** — Two-way sync via OAuth 2.0
- Full event backup/restore with versioned ZIP archives

### Security
- DokuWiki ACL enforcement on all read and write operations
- CSRF token validation on all write actions
- Rate limiting (60 req/min read, 30 req/min write)
- Namespace path traversal prevention
- Audit logging for admin operations

### DokuWiki Farm Compatible
- All data stored via `$conf['metadir']` and `$conf['cachedir']` (per-animal in farm setups)
- Sync credentials can be stored per-animal in `data/meta/calendar/sync_config.php`
- Falls back to shared plugin directory for non-farm installations
- See [Farm Setup](#dokuwiki-farm-setup) for migration details

### Localization
- English (en)
- German (de)
- Czech (cs)

## Installation

1. Download the latest release ZIP
2. Extract to `lib/plugins/calendar/` (the folder must be named `calendar`)
3. Clear DokuWiki's cache: **Admin → Configuration Settings → Save**
4. Access **Admin → Calendar Management** to configure themes and settings

## Syntax

### Interactive Calendar
```
{{calendar}}
{{calendar namespace=work}}
{{calendar namespace="personal;work"}}
{{calendar namespace=projects:*}}
{{calendar namespace=* exclude=journal}}
{{calendar namespace=* exclude="journal;drafts"}}
```

### Static Calendar (Read-only)
```
{{calendar static}}
{{calendar namespace=meetings static}}
{{calendar month=6 year=2026 static}}
{{calendar title="Club Events" theme=professional static}}
{{calendar static noprint}}
```

### Event Panel
```
{{eventpanel}}
{{eventpanel namespace=work height=500px}}
{{eventpanel namespace=* exclude=archive}}
```

### Event List
```
{{eventlist}}
{{eventlist range=week}}
{{eventlist range=month namespace=meetings}}
{{eventlist namespace=* exclude="personal;drafts"}}
```

### Sidebar Widget
```
{{eventlist sidebar}}
{{eventlist sidebar namespace=important}}
```

### Parameter Reference

| Parameter | Description | Example |
|-----------|-------------|---------|
| `namespace=X` | Filter by namespace | `namespace=work` |
| `namespace=*` | Show all namespaces | `namespace=*` |
| `namespace="X;Y"` | Multiple namespaces | `namespace="work;personal"` |
| `exclude=X` | Exclude namespace(s) from wildcard | `exclude=journal` |
| `exclude="X;Y"` | Exclude multiple namespaces | `exclude="journal;drafts"` |
| `static` | Read-only calendar mode | `{{calendar static}}` |
| `month=X` | Lock to month (1-12) | `month=6` |
| `year=X` | Lock to year | `year=2026` |
| `title="X"` | Custom title | `title="Team Events"` |
| `theme=X` | Override theme | `theme=professional` |
| `height=X` | Panel height (eventpanel only) | `height=500px` |
| `range=X` | Date range (eventlist only) | `range=week` |
| `noprint` | Hide print button (static only) | `noprint` |
| `noheader` | Hide header (eventlist only) | `noheader` |
| `sidebar` | Sidebar widget mode | `{{eventlist sidebar}}` |

## Admin Features

Access via **Admin → Calendar Management**:

- **Manage Events** — Browse, search, filter, move, and bulk-delete events across namespaces
- **Recurring Events** — Manage series with extend, trim, pause/resume, and pattern editing
- **Namespace Management** — Create, rename, and delete namespaces
- **Outlook Sync** — Configure Microsoft Graph API credentials and run/schedule syncs
- **Google Sync** — OAuth 2.0 setup for Google Calendar integration
- **Themes** — Select visual theme, configure week start day, itinerary default state, and default search scope
- **Backup/Restore** — Create, download, and restore versioned backups
- **Update** — Upload new plugin versions with automatic backup

## DokuWiki Farm Setup

This plugin is fully compatible with DokuWiki farm (multi-wiki) installations.

### How It Works
- Event data is stored in each animal's `$conf['metadir']/calendar/` directory
- Plugin settings (theme, week start, etc.) are per-animal in `$conf['metadir']/calendar_*.txt`
- Cache and rate-limit data use `$conf['cachedir']` (per-animal)
- The plugin code itself is shared across the farm (standard DokuWiki behavior)

### Sync Credentials
Sync credentials (`sync_config.php`) can be stored per-animal:
- **Per-animal (recommended):** `{animal}/data/meta/calendar/sync_config.php`
- **Shared (fallback):** `lib/plugins/calendar/sync_config.php`

The plugin checks the per-animal path first and falls back to the shared location.

### Migration from Non-Farm
If you're moving from a single-wiki install to a farm, move calendar data to each animal:
```bash
# Move calendar events
mv /path/to/master/data/meta/calendar/ /path/to/animal/data/meta/calendar/

# Move settings
mv /path/to/master/data/meta/calendar_*.txt /path/to/animal/data/meta/
```

## Event Description Formatting

Descriptions support DokuWiki-style formatting:

- `**bold**` or `__bold__` → **bold**
- `//italic//` → *italic*
- `[[page|text]]` → DokuWiki links
- `[text](url)` → Markdown links
- Line breaks preserved

## Architecture

| Component | Purpose |
|-----------|---------|
| `syntax.php` | Wiki syntax parsing and HTML rendering |
| `action.php` | AJAX endpoint handling with ACL enforcement |
| `admin.php` | Admin panel UI and management operations |
| `calendar-main.js` | Client-side calendar logic and UI |
| `classes/FileHandler.php` | Atomic file operations with locking |
| `classes/EventCache.php` | Caching layer with TTL |
| `classes/RateLimiter.php` | AJAX rate limiting |
| `classes/EventManager.php` | Event CRUD operations |
| `classes/AuditLogger.php` | Admin audit trail |
| `classes/GoogleCalendarSync.php` | Google Calendar OAuth integration |
| `sync_outlook.php` | CLI Outlook sync script (cron) |

## Requirements

- DokuWiki (Hogfather or later)
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

## Version

7.5.1

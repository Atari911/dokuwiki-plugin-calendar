# DokuWiki Calendar Plugin — Matrix Edition

A full-featured calendar plugin for DokuWiki with five visual themes, Outlook sync, conflict detection, and an admin panel for managing events across namespaces.

**Version:** 6.0.0
**Author:** atari911 (atari911@gmail.com)
**License:** GPL 2
**Requires:** DokuWiki "Kaos" (2024) or newer

---

## Installation

1. Download the latest release ZIP.
2. Extract into `lib/plugins/` so the path is `lib/plugins/calendar/`.
3. Go to **Admin → Calendar Management** to verify.

Alternatively, place the ZIP in the DokuWiki plugin manager upload field.

---

## Quick Start

Add any of these to a wiki page:

```
{{calendar}}                          Full month calendar with event panel
{{eventlist sidebar}}                 Sidebar widget (week grid + today/tomorrow)
{{eventpanel}}                        Standalone event list
{{eventlist range=30}}                Upcoming events for the next 30 days
{{eventlist range=-7,30}}             Past 7 days through next 30 days
```

### Namespace-scoped calendars

```
{{calendar namespace=work}}           Only show events in the "work" namespace
{{eventlist sidebar namespace=personal}}
```

### Theme selection

```
{{calendar theme=matrix}}
{{calendar theme=purple}}
{{calendar theme=pink}}
{{calendar theme=professional}}
{{calendar theme=wiki}}
```

The `wiki` theme reads your DokuWiki template's `style.ini` colors automatically. The default theme for sidebar widgets can be set globally in **Admin → Calendar Management → Themes**.

---

## Features

### Calendar views

- **Full calendar** — Month grid with clickable day cells, integrated event panel, month picker, AJAX navigation.
- **Sidebar widget** — Compact week grid with expandable day events, Today / Tomorrow / Important Events sections, conflict badges.
- **Event panel** — Standalone chronological event list with past-event collapsing.
- **Event list** — Date-range-based display for dashboards and overview pages.

### Event management

- Create, edit, and delete events without page reload (AJAX).
- All-day and timed events with start/end times.
- Multi-day events with end date support.
- Recurring events (daily, weekly, biweekly, monthly, yearly) with series editing.
- Tasks with completion checkboxes and past-due badges.
- Event colors (8 presets + custom hex picker).
- Rich descriptions with full DokuWiki markup (bold, italic, links, etc.).
- Draggable event dialogs.
- Namespace-based organization.

### Conflict detection

Overlapping timed events on the same day display an ⚠️ badge with a tooltip listing all conflicts. Works across all views including after AJAX navigation.

### Five themes

| Theme | Style |
|---|---|
| **Matrix** | Green-on-dark with glow effects |
| **Purple** | Purple-on-dark with soft highlights |
| **Pink** | Hot pink neon on dark with particle effects |
| **Professional** | Clean blue-on-white, no glow |
| **Wiki** | Inherits colors from your DokuWiki template |

All themes are applied via CSS variables — no inline style overrides. The sidebar widget, full calendar, event panel, day popups, conflict tooltips, and event dialogs all inherit theme colors consistently.

### Outlook sync (one-way: DokuWiki → Outlook)

Push calendar events to Microsoft 365 / Outlook via the Graph API.

- **Delta sync** — Only new, modified, or deleted events hit the API. Unchanged events are skipped entirely using hash-based change tracking.
- **Category mapping** — Map DokuWiki namespaces or event colors to Outlook color categories.
- **Duplicate detection** — Automatic cleanup of duplicate events.
- **Dry-run mode** — Preview what would sync before committing.
- **Cron-friendly** — Run on a schedule; typical syncs with few changes complete in seconds.

Setup: copy `sync_config.php`, add your Azure app credentials, and run:

```bash
php lib/plugins/calendar/sync_outlook.php --dry-run
php lib/plugins/calendar/sync_outlook.php
```

See `OUTLOOK_SYNC_SETUP.md` and `CRON_SETUP.md` for full instructions.

---

## Admin panel

Access via **Admin → Calendar Management**. Four tabs:

### Manage Events

- **Event statistics** — Total events, namespaces, files, recurring series.
- **Re-scan / Export / Import** — Bulk operations across all namespaces.
- **Cleanup** — Delete events by age, status (completed tasks / past events), or date range. Automatic backup before deletion. Preview before committing.
- **Recurring events table** — View, edit, and delete recurring series. Sortable columns, search filter. Edit dialog lets you change title, times, interval, and namespace for all occurrences at once.
- **Namespace explorer** — Tree view of all events by namespace. Checkbox selection, drag-and-drop between namespaces, rename/delete namespaces, create new namespaces.

### Update Plugin

- Current version display.
- Upload a new ZIP to update in place (automatic backup).
- Paginated changelog viewer (all 150+ versions).
- Clear DokuWiki cache.

### Outlook Sync

- Azure credential configuration (encrypted storage).
- Category mapping editor.
- Live sync runner with real-time log output.
- Cron status detection.

### Themes

- Global sidebar widget theme selector with live preview.
- Week start day setting (Sunday or Monday).

---

## File structure

```
calendar/
├── syntax.php            Main plugin (calendar rendering, PHP event logic)
├── action.php            AJAX handlers (create, edit, delete, navigate)
├── admin.php             Admin panel (4 tabs, all management features)
├── calendar-main.js      Client-side JavaScript (2,800+ lines)
├── style.css             All CSS with theme variables (3,200+ lines)
├── script.js             Empty loader (avoids DokuWiki concatenation)
├── sync_outlook.php      Outlook sync script (delta-aware)
├── sync_config.php       Outlook sync credentials (edit this)
├── get_system_stats.php  System monitoring endpoint
├── plugin.info.txt       Plugin metadata
├── lang/en/lang.php      Language strings
├── CHANGELOG.md          Full version history
├── OUTLOOK_SYNC_SETUP.md Outlook sync setup guide
├── CRON_SETUP.md         Cron job setup guide
└── QUICK_REFERENCE.md    Syntax quick reference
```

---

## Event data storage

Events are stored as JSON files in DokuWiki's `data/meta/` directory:

```
data/meta/calendar/2026-02.json              Default namespace
data/meta/work/calendar/2026-02.json         "work" namespace
data/meta/personal/calendar/2026-02.json     "personal" namespace
```

Each file is keyed by date, with an array of events per date. Events are plain JSON — no database required.

---

## Syntax reference

### Full calendar

```
{{calendar}}
{{calendar namespace=work theme=purple}}
```

### Sidebar widget

```
{{eventlist sidebar}}
{{eventlist sidebar namespace=personal theme=wiki}}
```

### Event panel

```
{{eventpanel}}
{{eventpanel namespace=work}}
```

### Event list with date range

```
{{eventlist range=30}}              Next 30 days
{{eventlist range=-7,30}}           Past 7 days + next 30
{{eventlist range=90 namespace=work}}
```

---

## Week start day

By default the week grid starts on Sunday. To change to Monday, go to **Admin → Calendar Management → Themes** and select Monday. This applies globally to all sidebar widgets.

---

## Changelog

See `CHANGELOG.md` for the full version history. Recent highlights:

- **6.0.0** — Code audit, admin cleanup, fresh README.
- **5.5.8** — Delta sync for Outlook (hash-based change tracking).
- **5.5.0** — Full CSS refactor, CSS variables as single source of truth for all themes.
- **5.0.0** — Wiki theme with automatic template color inheritance.
- **4.0.0** — Sidebar widget, five themes, conflict detection, system monitoring.

---

## Version 6.0.0

# DokuWiki Calendar Plugin — Project History

**Version 6.6.0** — Complete development history from v1.0 through v6.6.0.

---

## Origins (v1.0–v4.x)

The Calendar Plugin started as a basic DokuWiki calendar allowing users to add, edit, and delete events on specific dates within wiki pages. Events were stored as JSON files in DokuWiki's `data/meta/calendar/` directory, organized by month (`YYYY-MM.json`). Early versions established the core architecture: a `syntax.php` parser for `{{calendar}}` wiki markup, an `action.php` AJAX handler for event CRUD operations, and a `style.css` for layout.

Key milestones in the early versions included namespace support (events scoped to wiki namespaces), multi-day events, task/checkbox support, event descriptions with wiki markup rendering, and a basic admin panel for management.

---

## v5.0–v5.4: Feature Expansion

### Sidebar Widget & Event Lists
- `{{eventlist sidebar}}` — compact week-grid widget for DokuWiki sidebars showing today, tomorrow, and upcoming events
- `{{eventpanel}}` — standalone scrollable event list
- `{{eventlist range=N}}` — upcoming events for the next N days
- `{{eventlist compact}}` — minimal event listing

### Recurring Events
- Events can be marked as recurring with a `recurring: true` flag and `recurringId`
- Admin panel section for viewing and managing recurring series

### Outlook Sync
- Two-way sync with Microsoft Outlook calendars via `sync_outlook.php`
- OAuth2 authentication flow, configurable sync intervals
- Admin configuration panel for credentials and sync settings

### Conflict Detection
- Overlapping events on the same date/time flagged with ⚠️ badge
- Tooltip showing conflicting event details

### Admin Panel
- Full admin interface at Admin → Calendar Management
- Tabs: Overview, Settings, Manage, Sync, About
- Event statistics, namespace management, import/export, cleanup tools

---

## v5.5–v5.5.x: CSS Refactor

Complete refactoring of the styling system from hardcoded colors to CSS custom properties (variables). Introduced the semantic color system with variables like `--text-primary`, `--text-bright`, `--text-dim`, `--bg-main`, `--cell-bg`, `--cell-today-bg`, `--border-main`, `--border-color`, etc. This laid the groundwork for the theming system.

---

## v6.0.0: GitHub Publication & Theme System

### Five Visual Themes
1. **Matrix** — Dark background, bright green text, green glow effects, monospace feel
2. **Purple** — Dark background, purple accents, violet glow
3. **Pink** — Dark background, hot pink accents, heart today indicator with pulse animation, firework button hover effects
4. **Professional** — Clean light theme, blue accents, no glow
5. **Wiki** — Inherits colors from the active DokuWiki template (reads `__text__`, `__background__`, `__link__`, `__border__`, etc. from `tpl_style.ini`)

### Theme Architecture
- PHP reads theme setting from `calendar_theme.txt`
- `getThemeStyles()` returns a color array per theme
- CSS variables injected inline into calendar, sidebar, and eventlist containers
- Wiki theme dynamically reads DokuWiki template style variables at render time

---

## v6.0.1–v6.0.9: Text, Badge & Glow Theming

Progressive theming of every UI element:

- **v6.0.1–v6.0.3**: Header text, day numbers, event titles, meta text, descriptions all converted from hardcoded colors to CSS variables
- **v6.0.4–v6.0.5**: Event panel (clicked-day detail view) fully themed — header, event items, descriptions, time displays, action buttons
- **v6.0.6**: Calendar header month/year, month picker, event list items, completed/past event states, scrollbars, namespace filter indicator
- **v6.0.7**: All badges (TODAY, namespace, conflict ⚠️), conflict tooltips, month picker buttons, header hover states
- **v6.0.8**: Links themed with `.cal-link` class, text glow consistency across dark themes, pink glow intensity toned down
- **v6.0.9**: Form input text visibility on dark themes, cell hover effects, button hover/active states, glow values fine-tuned

---

## v6.1.0–v6.1.6: Interactive Elements & Dark Reader

- **v6.1.0**: Today indicator (filled circle like Google Calendar), themed button hover/click with brightness filters, custom checkbox styling with theme accent colors, form input placeholder text
- **v6.1.1**: Complete CSS variable audit — 41 remaining hardcoded colors converted
- **v6.1.2–v6.1.5**: Section header theming (Today/Tomorrow/Important), semantic color variables (`--pastdue-color`, `--tomorrow-bg`), pink heart today indicator and firework effects, all-theme checkbox glow
- **v6.1.6**: System tooltips themed using `style.setProperty` with `!important`

---

## v6.2.0–v6.2.6: Dark Reader Compatibility

The Dark Reader browser extension was aggressively overriding theme colors, making dark themes unreadable. The solution evolved through several approaches:

- **v6.2.0–v6.2.1**: Initial Dark Reader protection attempts using meta tags and `data-darkreader-mode`
- **v6.2.2–v6.2.6**: Final approach — targeted inline `!important` styles and `-webkit-text-fill-color` overrides on specific elements. No page-wide locks, no filter manipulation. Protected: section headers, badges, event text, day numbers, nav buttons, status bars, tooltips, color indicator bars
- Wiki theme intentionally left unlocked so Dark Reader can adjust it (since wiki theme is already light-friendly)

---

## v6.3.0: Consolidated Dark Reader Release

Merged all Dark Reader compatibility work into a single stable release. Full protection for all three dark themes across calendar, sidebar widget, and eventlist. Wiki theme headers/badges unlocked for Dark Reader mapping.

---

## v6.3.1–v6.3.9: Wiki Theme Refinement

Fine-tuning the Wiki theme to correctly inherit DokuWiki template colors:

- **v6.3.1**: Eventlist containers receive theme class and CSS variable injection
- **v6.3.2**: Fixed caching — added `$renderer->nocache()` so theme changes take effect immediately
- **v6.3.3**: Section header text uses template's `__text__` instead of hardcoded white
- **v6.3.4**: Border/accent color remapping — `border` maps to `__border__`, `text_bright` to `__link__`
- **v6.3.5**: Allowed Dark Reader to freely adjust wiki theme headers
- **v6.3.6–v6.3.7**: Checkbox border color from template's `__border__`, fixed shorthand override
- **v6.3.8**: Button and section header colors from template palette
- **v6.3.9**: Section bar fallback color fix

---

## v6.4.0–v6.4.4: Wiki Theme Polish

- **v6.4.0**: Section bar uses `background` div instead of `border-left` so Dark Reader maps colors identically
- **v6.4.1**: Event highlight uses template's `__background_alt__` instead of hardcoded blue
- **v6.4.2–v6.4.3**: Day headers (SMTWTFS) use `__background_neu__` and `__text__`
- **v6.4.4**: Past events toggle background uses `__background_neu__`

---

## v6.4.5: Admin Version History Overhaul

- Replaced all purple accents with green in the version history viewer
- Enhanced changelog parser to handle `###` subsection headers and plain bullets
- Added "Current Release" button that jumps to the card matching the running version
- Running version card shows green "RUNNING" badge

---

## v6.4.6: Recurring Events Rescan & Detection Improvements

- Added green "🔍 Rescan" button to refresh recurring events table via AJAX
- Rewrote detection logic: two-phase approach (flagged events first, then pattern detection)
- New "Source" column: 🏷️ Flagged vs 🔍 Detected
- Median interval for robust pattern detection (Daily, Weekly, Bi-weekly, Monthly, Quarterly, Semi-annual, Yearly, custom)
- Recursive namespace scanning, date deduplication, alphabetical sorting

---

## v6.4.7: Recurring Events Management Controls

Added an orange "Manage" button per series opening a comprehensive dialog with five operations:

1. **📅 Extend Series** — Add N future occurrences at a chosen interval, using the last event as a template
2. **✂️ Trim Past Events** — Remove occurrences before a cutoff date
3. **🔄 Change Pattern** — Respace future events to a new interval (past untouched)
4. **📆 Change Start Date** — Shift all occurrences by offset between old and new start
5. **⏸ Pause/Resume** — Toggle ⏸ prefix on future events

All operations are AJAX-powered with inline status messages.

---

## v6.4.8–v6.4.9: Bug Fixes

- **v6.4.8**: Fixed PHP parse error — JS template literals (`${...}`) inside PHP echo blocks caused parse failures. Rewrote manage dialog using string concatenation.
- **v6.4.9**: Fixed recurring edit/delete returning "0 changes" — root cause was directory path mismatch between event's namespace field and filesystem location. Both handlers now search ALL calendar directories recursively via `findCalendarDirs()`.

---

## v6.5.0–v6.5.1: Bulk Trim All Past Recurring

- Red "✂️ Trim All Past" button in recurring events section header
- Removes all past recurring event occurrences across every namespace in one click
- Only events with `recurring` or `recurringId` flag are removed
- Dry-run count shown in confirmation dialog before deletion

---

## v6.5.2–v6.5.6: Namespace Cleanup & AJAX Routing Fix

- **v6.5.2**: "🧹 Cleanup" button to remove empty namespace calendar folders
  - Dry-run scan shows exactly what will be removed
  - Removes empty calendar directories and empty parent namespace directories
  - Root calendar directory is never removed
- **v6.5.3–v6.5.4**: Fixed PHP parse errors caused by unescaped single quotes in JS within PHP echo blocks. Added `adminColors` JS object for theme-aware runtime colors.
- **v6.5.5**: Fixed "Unknown action" error — AJAX calls route through `action.php`, not `admin.php`. Added `routeToAdmin()` bridge in `action.php` and public `handleAjaxAction()` in `admin.php`. Moved cleanup button inline next to "➕ New Namespace".
- **v6.5.6**: Cleanup results now display as standard message banner at page top instead of inline. Fixed button text flash.

---

## v6.6.0: Package Cleanup

- Removed all auxiliary documentation files (.md/.txt) except README.md
- Consolidated project history into this document
- Clean package containing only essential plugin files

---

## Architecture Summary

### Files
| File | Purpose |
|------|---------|
| `plugin.info.txt` | DokuWiki plugin metadata |
| `syntax.php` | Wiki markup parser — renders `{{calendar}}`, `{{eventlist}}`, `{{eventpanel}}` |
| `action.php` | AJAX handlers for event CRUD, month loading, task toggling, admin action routing |
| `admin.php` | Full admin panel — settings, namespace management, recurring events, statistics, import/export, version history |
| `style.css` | All CSS with theme-aware custom properties, Dark Reader protection, responsive design |
| `calendar-main.js` | Client-side calendar rendering, event dialogs, drag-and-drop, month navigation |
| `script.js` | Minimal bootstrap loader |
| `sync_outlook.php` | Outlook calendar sync via Microsoft Graph API |
| `sync_config.php` | Sync configuration management |
| `get_system_stats.php` | System statistics endpoint |
| `check_syntax.sh` | PHP syntax validation helper |

### Data Storage
- Events stored as JSON in `data/meta/[namespace]/calendar/YYYY-MM.json`
- Theme setting in `data/meta/calendar_theme.txt`
- Sync credentials in `data/meta/calendar_sync_config.json`

### Theme System
- 5 themes: Matrix, Purple, Pink, Professional, Wiki
- Colors defined in `getThemeStyles()` PHP method
- Injected as CSS custom properties at render time
- Wiki theme reads from DokuWiki template's `style.ini`
- Dark Reader compatibility via targeted `!important` overrides

### Admin Panel Capabilities
- **Overview**: Event statistics, calendar health, system info
- **Settings**: Theme selection, display options, sync configuration
- **Manage**: Namespace explorer (drag-and-drop event moving, bulk actions), recurring event management (edit, manage, delete, extend, trim, pause, change pattern/start date, bulk trim past, rescan), namespace cleanup
- **Sync**: Outlook sync status, manual sync trigger, log viewer
- **About**: Version history viewer with changelog browser

---

*This document covers the complete development from initial creation through version 6.6.0.*

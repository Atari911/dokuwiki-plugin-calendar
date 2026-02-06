# Calendar Plugin Changelog

## Version 3.10.5 (2026-02-06) - TIME SORTING & THINNER ADD BAR
- **Added:** Events now sorted by time when clicking week grid days
- **Changed:** Add Event bar now ultra-thin (6px height, down from 12px)
- **Improved:** Events with times appear first, sorted chronologically
- **Improved:** All-day events appear after timed events
- **Changed:** Add Event bar font size reduced to 7px (from 10px)
- **Changed:** Add Event bar now has 0 padding and fixed 6px height

### Sorting Logic:
- Events with times sorted by time (earliest first)
- All-day events (no time) appear at the end
- Sort algorithm: Convert time to minutes (HH:MM → total minutes) and compare
- Chronological order: 8:00 AM → 10:30 AM → 2:00 PM → All-day event

### Add Event Bar Changes:
- **Height**: 6px (was ~12px with padding)
- **Padding**: 0 (was 4px top/bottom)
- **Font Size**: 7px (was 10px)
- **Letter Spacing**: 0.3px (was 0.5px)
- **Line Height**: 6px to match height
- **Vertical Align**: Middle for text centering

## Version 3.10.4 (2026-02-06) - ADD EVENT BAR
- **Added:** Thin orange "Add Event" bar between header and week grid
- **Added:** Quick access to event creation from sidebar widget
- **Styled:** Sleek design with hover effects and glow
- **Interactive:** Clicks navigate to Manage Events tab in admin
- **Improved:** User workflow for adding events from sidebar

### Visual Design:
- Orange background (#ff9800) matching Today section color
- 4px top/bottom padding for thin, sleek appearance
- Black text with white text-shadow for visibility
- Hover effect: Darkens to #ff7700 with enhanced glow
- Orange glow effect (box-shadow) matching Matrix theme
- Centered "+ ADD EVENT" text (10px, bold, letter-spacing)

### Technical Changes:
- Added between header close and renderWeekGrid() call
- Inline onclick handler navigates to admin manage tab
- Inline onmouseover/onmouseout for hover effects
- Smooth 0.2s transition on all style changes

## Version 3.10.3 (2026-02-06) - UI IMPROVEMENTS & CACHE BUTTON RELOCATION
- **Changed:** Update Plugin tab is now the default tab when opening admin
- **Moved:** Clear Cache button relocated from Outlook Sync tab to Update Plugin tab
- **Improved:** Clear Cache button now larger and more prominent with helpful description
- **Improved:** Tab order reorganized: Update Plugin (default) → Outlook Sync → Manage Events
- **Removed:** Debug console.log statements from day event display
- **Fixed:** Cache clear now redirects back to Update Plugin tab instead of Config tab

### UI Changes:
- Update Plugin tab opens by default (was Config/Outlook Sync tab)
- Clear Cache button prominently displayed at top of Update Plugin tab
- Orange 🗑️ button (10px 20px padding) with confirmation dialog
- Help text: "Clear the DokuWiki cache if changes aren't appearing or after updating the plugin"
- Success/error messages display on Update Plugin tab after cache clear
- Tab navigation reordered to put Update first

### Technical Changes:
- Default tab changed from 'config' to 'update' in html() method
- Tab navigation HTML reordered to show Update Plugin tab first
- clearCache() method now redirects with 'update' tab parameter
- Removed Clear Cache button from renderConfigTab()
- Added Clear Cache button to renderUpdateTab() with message display

## Version 3.10.2 (2026-02-06) - EVENT HTML RENDERING FIX
- **Fixed:** Event formatting (bold, links, italic) now displays correctly when clicking week grid days
- **Added:** renderDokuWikiToHtml() helper function to convert DokuWiki syntax to HTML
- **Changed:** Events in weekEvents now pre-rendered with title_html and description_html fields
- **Improved:** DokuWiki syntax (**bold**, [[links]], //italic//, etc.) properly rendered in clicked day events

### Technical Changes:
- Added renderDokuWikiToHtml() private function using p_get_instructions() and p_render()
- Events added to weekEvents now include pre-rendered HTML versions
- title_html and description_html fields populated before json_encode()
- JavaScript now receives properly formatted HTML content

## Version 3.10.1 (2026-02-06) - TOOLTIP FIX & WEATHER & CACHE BUTTON
- **Fixed:** System tooltip functions now use sanitized calId (showTooltip_sidebar_abc123 instead of showTooltip_sidebar-abc123)
- **Fixed:** HTML event handlers now call correctly sanitized function names
- **Fixed:** Weather temperature now updates correctly in sidebar widget
- **Added:** Weather update function to sidebar widget JavaScript
- **Added:** "Clear Cache" button in admin panel for easy cache refresh
- **Added:** Default weather location set to Irvine, CA when geolocation unavailable
- **Improved:** All tooltip functions now work correctly on system status bars

### Technical Changes:
- Changed tooltip function names to use $jsCalId instead of $calId
- Changed HTML onmouseover/onmouseout to use $jsCalId
- Added updateWeather() function to sidebar widget
- Added getWeatherIcon() function to sidebar widget
- Added clearCache() method in admin.php
- Added recursiveDelete() helper method in admin.php
- Admin UI now has 🗑️ Clear Cache button alongside Export/Import

## Version 3.10.0 (2026-02-06) - JAVASCRIPT FIXES
- **Fixed:** JavaScript syntax error "Missing initializer in const declaration"
- **Fixed:** Event links and formatting not displaying in clicked day events
- **Fixed:** Sanitized calId to jsCalId by replacing dashes with underscores
- **Changed:** Event titles now use `title_html` field to preserve HTML formatting
- **Changed:** Event descriptions now use `description_html` field to preserve links and formatting
- **Improved:** All JavaScript variable names now use valid syntax
- **Improved:** Links, bold, italic, and other HTML formatting preserved in events

### Technical Changes:
- Added variable sanitization: `$jsCalId = str_replace('-', '_', $calId);`
- JavaScript variables now use underscores instead of dashes
- Event HTML rendering preserves DokuWiki formatting
- Fixed "showTooltip_sidebar is not defined" errors
- Fixed "showDayEvents_cal is not defined" errors

## Version 3.9.9 (2026-02-06) - JAVASCRIPT LOADING ORDER FIX
- **Fixed:** Critical JavaScript loading order issue causing ReferenceError
- **Fixed:** Functions now defined BEFORE HTML that uses them
- **Changed:** Consolidated all JavaScript into single comprehensive script block
- **Removed:** ~290 lines of duplicate JavaScript code
- **Added:** Shared state management with `sharedState_[calId]` object
- **Improved:** System tooltip functions now work correctly
- **Improved:** Week grid click events now work correctly

### Technical Changes:
- Moved all JavaScript to beginning of widget (before HTML)
- Removed duplicate script blocks
- Unified tooltip and stats functions
- Shared latestStats and cpuHistory state
- Fixed "Uncaught ReferenceError: showTooltip_sidebar is not defined"

## Version 3.9.8 (2026-02-05) - DUAL COLOR BARS & CLICK EVENTS
- **Added:** Dual color bars on events (section color + event color)
- **Added:** Click week grid days to view events (replaced hover tooltips)
- **Added:** Expandable section below week grid for selected day events
- **Added:** Blue theme for selected day section
- **Changed:** Week grid days now clickable instead of tooltips
- **Changed:** Section bar: 4px wide (left)
- **Changed:** Event bar: 3px wide (right)
- **Increased:** Gap between color bars from 3px to 6px
- **Improved:** Click is more reliable and mobile-friendly than hover tooltips

### Visual Changes:
- Each event shows TWO color bars side-by-side
- Left bar (4px): Section context (Today=Orange, Tomorrow=Green, Important=Purple, Selected=Blue)
- Right bar (3px): Individual event's assigned color
- Click any day in week grid to expand event list
- X button to close selected day events

## Version 3.9.7 (2026-02-05) - EVENT COLOR BAR VISIBILITY
- **Increased:** Event color bar width from 2px to 3px
- **Increased:** Gap between section and event bars from 3px to 6px
- **Improved:** Event color bars now more visible alongside section bars
- **Note:** Dual color bar system already in place from v3.9.6

## Version 3.9.6 (2026-02-05) - UI REFINEMENTS
- **Changed:** Date in Important Events moved below event name (was above)
- **Changed:** Section headers now 9px font size (was 10px)
- **Changed:** Section headers now normal case (was ALL CAPS)
- **Changed:** Letter spacing reduced from 0.8px to 0.3px
- **Improved:** More natural reading flow with date below event name
- **Improved:** Cleaner, more subtle section headers

### Header Changes:
- "TODAY" → "Today"
- "TOMORROW" → "Tomorrow"
- "IMPORTANT EVENTS" → "Important Events"

## Version 3.9.0 (2026-02-05) - SIDEBAR WIDGET REDESIGN
- **Redesigned:** Complete overhaul of `sidebar` parameter
- **Added:** Compact week-at-a-glance itinerary view (200px wide)
- **Added:** Live clock widget at top of sidebar
- **Added:** 7-cell week grid showing event bars
- **Added:** Today section with orange header and left border
- **Added:** Tomorrow section with green header and left border
- **Added:** Important Events section with purple header and left border
- **Added:** Admin setting to configure important namespaces
- **Added:** Time conflict badges in sidebar events
- **Added:** Task checkboxes in sidebar events
- **Changed:** Sidebar now optimized for narrow spaces (200px)
- **Improved:** Perfect for dashboards, page sidebars, and quick glance widgets

### New Features:
- Clock updates every second showing current time
- Week grid shows Mon-Sun with colored event bars
- Today/Tomorrow sections show full event details
- Important events highlighted in purple (configurable namespaces)
- All badges (conflict, time, etc.) shown in compact format
- Automatic time conflict detection

## Version 3.8.0 (2026-02-05) - PRODUCTION CLEANUP
- **Removed:** 16 unused/debug/backup files
- **Removed:** 69 console.log() debug statements
- **Removed:** 3 orphaned object literals from console.log removal
- **Removed:** Temporary comments and markers
- **Fixed:** JavaScript syntax errors from cleanup
- **Improved:** Code quality and maintainability
- **Improved:** Reduced plugin size by removing unnecessary files
- **Status:** Production-ready, fully cleaned codebase

### Files Removed:
- style.css.backup, script.js.backup
- admin_old_backup.php, admin_minimal.php, admin_new.php, admin_clean.php
- debug_events.php, debug_html.php, cleanup_events.php
- fix_corrupted_json.php, fix_wildcard_namespaces.php
- find_outlook_duplicates.php, update_namespace.php
- validate_calendar_json.php, admin.js
- test_date_field.html

## Version 3.7.5 (2026-02-05)
- **Fixed:** PHP syntax error (duplicate foreach loop removed)
- **Fixed:** Time variable handling in grace period logic

## Version 3.7.4 (2026-02-05)
- **Added:** 15-minute grace period for timed events
- **Changed:** Events with times now stay visible for 15 minutes after their start time
- **Changed:** Prevents events from immediately disappearing when they start
- **Improved:** Better user experience for ongoing events
- **Fixed:** Events from earlier today now properly handled with grace period

## Version 3.7.3 (2026-02-05)
- **Changed:** Complete redesign of cleanup section for compact, sleek layout
- **Changed:** Radio buttons now in single row at top
- **Changed:** All options visible with grayed-out inactive states (opacity 0.4)
- **Changed:** Inline controls - no more grid layout or wrapper boxes
- **Changed:** Namespace filter now compact single-line input
- **Changed:** Smaller buttons and tighter spacing throughout
- **Improved:** More professional, space-efficient design

## Version 3.7.2 (2026-02-04)
- **Fixed:** Strange boxes under cleanup options - now properly hidden
- **Changed:** Unified color scheme across all admin sections
- **Changed:** Green (#00cc07) - Primary actions and main theme
- **Changed:** Orange (#ff9800) - Warnings and cleanup features
- **Changed:** Purple (#7b1fa2) - Secondary actions and accents
- **Improved:** Consistent visual design throughout admin interface

## Version 3.7.1 (2026-02-04)
- **Fixed:** Cleanup section background changed from orange to white
- **Fixed:** Event cleanup now properly scans all calendar directories
- **Added:** Debug info display when preview finds no events
- **Improved:** Better directory scanning logic matching other features

## Version 3.7.0 (2026-02-04)
- **Added:** Event cleanup feature in Events Manager
- **Added:** Delete old events by age (months/years old)
- **Added:** Delete events by status (completed tasks, past events)
- **Added:** Delete events by date range
- **Added:** Namespace filter for targeted cleanup
- **Added:** Preview function to see what will be deleted
- **Added:** Automatic backup creation before cleanup
- **Changed:** Reduced changelog viewer height to 100px (was 400px)

## Version 3.6.3 (2026-02-04)
- **Fixed:** Conflict tooltips now work properly after navigating between months
- **Added:** Changelog display in Update Plugin tab
- **Added:** CHANGELOG.md file with version history
- **Improved:** Changelog shows last 10 versions with color-coded change types
- **Fixed:** Removed debug console.log statements

## Version 3.6.2 (2026-02-04)
- **Fixed:** Month title now updates correctly when navigating between months
- **Changed:** All eventpanel header elements reduced by 10% for more compact design
- **Changed:** Reduced header height from 78px to 70px

## Version 3.6.1 (2026-02-04)
- **Changed:** Complete redesign of eventpanel header with practical two-row layout
- **Fixed:** Improved layout for narrow widths (~500px)
- **Changed:** Simplified color scheme (removed purple gradient)

## Version 3.6.0 (2026-02-04)
- **Changed:** Redesigned eventpanel header with gradient background
- **Changed:** Consolidated multiple header rows into compact single-row design

## Version 3.5.1 (2026-02-04)
- **Changed:** Moved event search bar into header row next to + Add button
- **Improved:** More compact UI with search integrated into header

## Version 3.5.0 (2026-02-04)
- **Added:** Event search functionality in sidebar and eventpanel
- **Added:** Real-time filtering as you type
- **Added:** Clear button (✕) appears when searching
- **Added:** "No results" message when search returns nothing

## Version 3.4.7 (2026-02-04)
- **Changed:** Made conflict badges smaller and more subtle (9px font, less padding)
- **Fixed:** Removed debug logging from console
- **Changed:** Updated export version number to match plugin version

## Version 3.4.6 (2026-02-04)
- **Added:** Debug logging to diagnose conflict detection issues
- **Development:** Extensive console logging for troubleshooting

## Version 3.4.5 (2026-02-04)
- **Added:** Debug logging to showDayPopup and conflict detection
- **Development:** Added logging to trace conflict detection flow

## Version 3.4.4 (2026-02-04)
- **Fixed:** Conflict detection now persists across page refreshes (PHP-based)
- **Fixed:** Conflict tooltips now appear on hover
- **Added:** Dual conflict detection (PHP for initial load, JavaScript for navigation)
- **Added:** Conflict badges in both future and past events sections

## Version 3.4.3 (2026-02-04)
- **Added:** Custom styled conflict tooltips with hover functionality
- **Changed:** Conflict badge shows count of conflicts (e.g., ⚠️ 2)
- **Improved:** Beautiful tooltip design with orange header and clean formatting

## Version 3.4.2 (2026-02-04)
- **Fixed:** Attempted to fix tooltip newlines (reverted in 3.4.3)

## Version 3.4.1 (2026-02-04)
- **Fixed:** End time field now properly saves to database
- **Fixed:** End time dropdown now filters to show only valid times after start time
- **Added:** Smart dropdown behavior - expands on focus, filters invalid options
- **Improved:** End time auto-suggests +1 hour when start time selected

## Version 3.4.0 (2026-02-04)
- **Added:** End time support for events (start and end times)
- **Added:** Automatic time conflict detection
- **Added:** Conflict warning badges (⚠️) on events with overlapping times
- **Added:** Conflict tooltips showing which events conflict
- **Added:** Visual conflict indicators with pulse animation
- **Changed:** Time display now shows ranges (e.g., "2:00 PM - 4:00 PM")

## Version 3.3.77 (2026-02-04)
- **Fixed:** Namespace badge onclick handlers restored after clearing filter
- **Fixed:** Namespace filtering works infinitely (filter → clear → filter)

## Version 3.3.76 (2026-02-04)
- **Fixed:** Namespace badges now clickable after clearing namespace filter

## Version 3.3.75 (2026-02-04)
- **Fixed:** Form resubmission warnings eliminated
- **Improved:** Implemented proper POST-Redirect-GET pattern with HTTP 303
- **Changed:** All admin redirects now use absolute URLs

## Version 3.3.74 (2026-02-04)
- **Fixed:** Clearing namespace filter now restores original namespace instead of default
- **Added:** data-original-namespace attribute to preserve initial namespace setting
- **Improved:** Console logging for namespace filter debugging

## Version 3.3.73 (2026-02-03)
- **Added:** Dynamic namespace filtering banner with clear button
- **Fixed:** JavaScript function accessibility issues
- **Fixed:** Namespace badge click handlers in event lists
- **Improved:** Persistent namespace filtering across views

## Earlier Versions
See previous transcripts for complete history through v3.3.73, including:
- Recurring events with Outlook sync
- Multi-namespace support
- Event categories and mapping
- Backup/restore functionality
- System statistics bar
- Namespace selector with fuzzy search
- Events Manager with import/export
- And much more...

# Calendar Plugin Changelog

## Version 4.0.0 (2026-02-06) - MATRIX EDITION RELEASE 🎉

**Major Release**: Complete Matrix-themed calendar plugin with advanced features!

### 🌟 Major Features

#### Sidebar Widget
- **Week Grid**: Interactive 7-day calendar with click-to-view events
- **Live System Monitoring**: CPU load, real-time CPU, memory usage with tooltips
- **Live Clock**: Updates every second with date display
- **Real-time Weather**: Geolocation-based temperature with icon
- **Event Sections**: Today (orange), Tomorrow (green), Important (purple)
- **Add Event Button**: Dark green bar opens full event creation dialog
- **Matrix Theme**: Green glow effects throughout

#### Event Management
- **Single Color Bars**: Clean 3px bars showing event's assigned color
- **All-Day Events First**: Then sorted chronologically by time
- **Conflict Detection**: Orange ⚠ badge on overlapping events
- **Rich Content**: Full DokuWiki formatting (**bold**, [[links]], //italic//)
- **HTML Rendering**: Pre-rendered for JavaScript display
- **Click-to-View**: Click week grid days to expand event details

#### Admin Interface
- **Update Plugin Tab** (Default): Version info, changelog, prominent Clear Cache button
- **Outlook Sync Tab**: Microsoft Azure integration, category mapping, sync settings
- **Manage Events Tab**: Browse, edit, delete, move events across namespaces

#### Outlook Sync
- **Bi-directional Sync**: DokuWiki ↔ Microsoft Outlook
- **Category Mapping**: Map colors to Outlook categories
- **Conflict Resolution**: Time conflict detection
- **Import/Export Config**: Encrypted configuration files

### 🎨 Design
- **Matrix Theme**: Authentic green glow aesthetic
- **Dark Backgrounds**: #1a1a1a header, rgba(36, 36, 36) sections
- **Color Scheme**:
  - Today: Orange #ff9800
  - Tomorrow: Green #4caf50
  - Important: Purple #9b59b6
  - Add Event: Dark green #006400
  - System bars: Green/Purple/Orange

### 🔧 Technical Highlights
- **Zero-margin Design**: Perfect flush alignment throughout
- **Flexbox Layout**: Modern, responsive structure
- **AJAX Operations**: No page reloads needed
- **Smart Sorting**: All-day events first, then chronological
- **Tooltip System**: Detailed stats on hover (working correctly)
- **Event Dialog**: Full form with drag support
- **Cache Management**: One-click cache clearing

### 📝 Breaking Changes from v3.x
- Removed dual color bars (now single event color bar only)
- Add Event button moved to between header and week grid
- All-day events now appear FIRST (not last)
- Update Plugin tab is now the default admin tab

### 🐛 Bug Fixes (v3.10.x → v4.0.0)
- ✅ Fixed color bars not showing (align-self:stretch vs height:100%)
- ✅ Fixed tooltip function naming (sanitized calId for JavaScript)
- ✅ Fixed weather display (added updateWeather function)
- ✅ Fixed HTML rendering in events (title_html/description_html fields)
- ✅ Fixed Add Event dialog (null check for calendar element)
- ✅ Fixed text positioning in Add Event button
- ✅ Fixed spacing throughout sidebar widget

### 📦 Complete Feature List
- Full calendar view (month grid)
- Sidebar widget (week view)
- Event panel (standalone)
- Event list (date ranges)
- Namespace support
- Color coding
- Time conflict detection
- DokuWiki syntax in events
- Outlook synchronization
- System monitoring
- Weather display
- Live clock
- Admin interface
- Cache management
- Draggable dialogs
- AJAX save/edit/delete
- Import/export config

### 🎯 Usage

**Sidebar Widget**:
```
{{calendar sidebar}}
{{calendar sidebar namespace=team}}
```

**Full Calendar**:
```
{{calendar}}
{{calendar year=2026 month=6 namespace=team}}
```

**Event Panel**:
```
{{eventpanel}}
```

**Event List**:
```
{{eventlist daterange=2026-01-01:2026-01-31}}
```

### 📊 Stats
- **40+ versions** developed during v3.x iterations
- **3.10.0 → 3.11.4**: Polish and refinement
- **4.0.0**: Production-ready Matrix Edition

### 🙏 Credits
Massive iteration and refinement session resulting in a polished, feature-complete calendar plugin with authentic Matrix aesthetics and enterprise-grade Outlook integration.

---

## Previous Versions (v3.11.4 and earlier)

## Version 3.11.4 (2026-02-06) - RESTORE HEADER BOTTOM SPACING
- **Changed:** Restored 2px bottom padding to header (was 0px, now 2px)
- **Improved:** Small breathing room between system stats bars and Add Event button
- **Visual:** Better spacing for cleaner appearance

### CSS Change:
**eventlist-today-header**:
- `padding: 6px 10px 0 10px` → `padding: 6px 10px 2px 10px`

### Visual Result:
```
│  ▓▓▓░░ ▓▓░░░ ▓▓▓▓░  │  ← Stats bars
│                       │  ← 2px space (restored)
├───────────────────────┤
│  + ADD EVENT          │  ← Add Event bar
├───────────────────────┤
```

**Before (v3.11.3)**: No space, bars directly touch Add Event button
**After (v3.11.4)**: 2px breathing room for better visual hierarchy

## Version 3.11.3 (2026-02-06) - FIX ADD EVENT DIALOG & TEXT POSITION
- **Fixed:** openAddEvent() function now checks if calendar element exists before reading dataset
- **Fixed:** Add Event button no longer throws "Cannot read properties of null" error
- **Changed:** Add Event text moved up 1px (position:relative; top:-1px)
- **Changed:** Line-height reduced from 12px to 10px for better text centering
- **Improved:** openAddEvent() works for both regular calendars and sidebar widgets

### JavaScript Fix:
**Problem**: Line 1084-1085 in calendar-main.js
```javascript
const calendar = document.getElementById(calId);
const filteredNamespace = calendar.dataset.filteredNamespace; // ← Null error!
```

**Solution**: Added null check
```javascript
const calendar = document.getElementById(calId);
const filteredNamespace = calendar ? calendar.dataset.filteredNamespace : null;
```

**Why This Happened**:
- Regular calendar has element with id=calId
- Sidebar widget doesn't have this element (different structure)
- Code tried to read .dataset on null, causing error

### Text Position Fix:
**Before**: 
- line-height: 12px
- vertical-align: middle
- Text slightly low

**After**:
- line-height: 10px
- position: relative; top: -1px
- Text perfectly centered

### What Works Now:
✅ Click "+ ADD EVENT" in sidebar → Dialog opens
✅ No console errors
✅ Text properly centered vertically
✅ Form pre-filled with today's date
✅ Save works correctly

## Version 3.11.2 (2026-02-06) - ADD EVENT DIALOG IN SIDEBAR
- **Added:** Event dialog to sidebar widget (same as regular calendar)
- **Changed:** Add Event button now opens proper event form dialog
- **Added:** renderEventDialog() called in renderSidebarWidget()
- **Fixed:** Add Event button calls openAddEvent() with calId, namespace, and today's date
- **Improved:** Can now add events directly from sidebar widget

### Add Event Button Behavior:
**Before (v3.11.1)**: Showed alert with instructions
**After (v3.11.2)**: Opens full event creation dialog

**Dialog Features**:
- Date field (defaults to today)
- Title field (required)
- Time field (optional)
- End time field (optional)
- Color picker
- Category field
- Description field
- Save and Cancel buttons
- Draggable dialog

### Technical Changes:
- Added `$html .= $this->renderEventDialog($calId, $namespace);` at end of renderSidebarWidget()
- Changed Add Event onclick from alert to `openAddEvent('calId', 'namespace', 'YYYY-MM-DD')`
- Dialog uses same structure as regular calendar
- Uses existing openAddEvent() and saveEventCompact() JavaScript functions

### User Flow:
1. User clicks "+ ADD EVENT" green bar
2. Event dialog opens with today's date pre-filled
3. User fills in event details
4. User clicks Save
5. Event saved via AJAX
6. Dialog closes
7. Sidebar refreshes to show new event

## Version 3.11.1 (2026-02-06) - FLUSH HEADER & ADD EVENT DIALOG
- **Fixed:** Removed bottom padding from header (was 2px, now 0)
- **Fixed:** Removed margin from stats container (was margin-top:2px, now margin:0)
- **Fixed:** Add Event bar now flush against header with zero gap
- **Changed:** Add Event button now shows helpful alert dialog instead of navigating to admin
- **Improved:** Alert provides clear instructions on how to add events

### CSS Changes:
**eventlist-today-header**:
- `padding: 6px 10px 2px 10px` → `padding: 6px 10px 0 10px` (removed 2px bottom)

**eventlist-stats-container**:
- `margin-top: 2px` → `margin: 0` (removed all margins)

### Add Event Button Behavior:
**Before**: Clicked → Navigated to Admin → Manage Events tab
**After**: Clicked → Shows alert with instructions

**Alert Message**:
```
To add an event, go to:
Admin → Calendar Management → Manage Events tab
or use the full calendar view {{calendar}}
```

### Visual Result:
```
│  ▓▓▓░░ ▓▓░░░ ▓▓▓▓░  │  ← Stats (no margin-bottom)
├────────────────────────┤
│  + ADD EVENT           │  ← Perfectly flush!
├────────────────────────┤
```

No gaps, perfectly aligned!

## Version 3.11.0 (2026-02-06) - ADD EVENT BAR FINAL POSITION & SIZE
- **Moved:** Add Event bar back to original position (between header and week grid)
- **Changed:** Font size reduced from 9px to 8px (prevents text cutoff)
- **Changed:** Letter spacing reduced from 0.5px to 0.4px
- **Fixed:** Text now fully visible without being cut off
- **Final:** Optimal position and size determined

### Final Layout:
```
┌─────────────────────────────┐
│  Clock | Weather | Stats    │  ← Header
├─────────────────────────────┤
│  + ADD EVENT                 │  ← Bar (back here, smaller text)
├─────────────────────────────┤
│  M  T  W  T  F  S  S        │  ← Week Grid
│  3  4  5  6  7  8  9        │
├─────────────────────────────┤
│  Today                       │  ← Event sections
└─────────────────────────────┘
```

### Text Size Changes:
**v3.10.9**: 9px font, 0.5px letter-spacing → Text slightly cut off
**v3.11.0**: 8px font, 0.4px letter-spacing → Text fully visible

### Why This Position:
- Separates header from calendar
- Natural action point after viewing stats
- Users see stats → decide to add event → view calendar
- Consistent with original design intent

## Version 3.10.9 (2026-02-06) - ADD EVENT BAR MOVED BELOW WEEK GRID
- **Moved:** Add Event bar repositioned from between header/grid to below week grid
- **Improved:** Better visual flow - header → stats → grid → add button → events
- **Changed:** Add Event bar now acts as separator between calendar and event sections

### New Layout:
```
┌─────────────────────────────┐
│  Clock | Weather | Stats    │  ← Header
├─────────────────────────────┤
│  M  T  W  T  F  S  S        │  ← Week Grid
│  3  4  5  6  7  8  9        │
├─────────────────────────────┤
│  + ADD EVENT                 │  ← Add bar (moved here!)
├─────────────────────────────┤
│  Today                       │  ← Event sections
│  Tomorrow                    │
│  Important Events            │
└─────────────────────────────┘
```

### Visual Flow:
**Before (v3.10.8)**:
1. Header (clock, weather, stats)
2. **+ ADD EVENT** bar
3. Week grid
4. Event sections

**After (v3.10.9)**:
1. Header (clock, weather, stats)
2. Week grid (calendar days)
3. **+ ADD EVENT** bar
4. Event sections

### Benefits:
- Natural reading flow: View calendar → Add event → See events
- Add button positioned between calendar and event list
- Acts as visual separator
- More logical action placement

## Version 3.10.8 (2026-02-06) - SINGLE COLOR BAR & ZERO MARGIN ADD BAR
- **Removed:** Section color bar (blue/orange/green/purple) - now shows ONLY event color
- **Changed:** Events now display with single 3px color bar (event's assigned color only)
- **Fixed:** Add Event bar now has zero margin (margin:0) - touches header perfectly
- **Simplified:** Cleaner visual with one color bar instead of two
- **Improved:** More space for event content without extra bar

### Visual Changes:

**Before (v3.10.7)** - Dual color bars:
```
├─ [Orange][Green]  Event Title
├─ [Blue][Purple]   Event Title
```

**After (v3.10.8)** - Single color bar:
```
├─ [Green]  Event Title    ← Only event color!
├─ [Purple] Event Title    ← Only event color!
```

### Add Bar Changes:
- Added `margin:0` to eliminate gaps
- Now flush against header (no space above)
- Now flush against week grid (no space below)
- Perfect seamless connection

### Technical Changes:
**renderSidebarEvent()**: 
- Removed section color bar (4px)
- Kept only event color bar (3px)

**showDayEvents() JavaScript**:
- Removed section color bar (4px blue)
- Kept only event color bar (3px)

**Add Event bar**:
- Added `margin:0` inline style
- Removed all top/bottom margins

## Version 3.10.7 (2026-02-06) - COLOR BARS FIX FOR SECTIONS & DARK GREEN ADD BAR
- **Fixed:** Color bars now display in Today/Tomorrow/Important sections (was only showing in clicked day)
- **Fixed:** Changed Today/Tomorrow/Important event rendering to use `align-self:stretch` instead of `height:100%`
- **Changed:** Add Event bar color from orange to dark green (#006400)
- **Changed:** Add Event bar height increased from 6px to 12px (text no longer cut off)
- **Changed:** Add Event bar text now bright green (#00ff00) with green glow
- **Changed:** Add Event bar font size increased from 7px to 9px
- **Changed:** Add Event bar letter spacing increased to 0.5px
- **Improved:** Hover effect on Add Event bar now darker green (#004d00)

### Color Bar Fix Details:
**Problem**: Today/Tomorrow/Important sections still used `height:100%` on color bars
**Solution**: Applied same fix as clicked day events:
- Changed parent div: `align-items:start` → `align-items:stretch`
- Added `min-height:20px` to parent
- Changed bars: `height:100%` → `align-self:stretch`
- Bars now properly fill vertical space in ALL sections

### Add Event Bar Changes:
**Before**:
- Background: Orange (#ff9800)
- Text: Black (#000)
- Height: 6px (text cut off)
- Font: 7px

**After**:
- Background: Dark green (#006400)
- Text: Bright green (#00ff00) with green glow
- Height: 12px (text fully visible)
- Font: 9px
- Hover: Darker green (#004d00)
- Matrix-themed green aesthetic

## Version 3.10.6 (2026-02-06) - COLOR BARS FIX, SORTING REVERSAL, CONFLICT BADGE, README UPDATE
- **Fixed:** Event color bars now display correctly in clicked day events
- **Fixed:** Changed sorting - all-day events now appear FIRST, then timed events
- **Added:** Conflict badge (⚠) appears on right side of conflicting events
- **Updated:** Complete README.md rewrite with full Matrix theme documentation
- **Changed:** Color bars use `align-self:stretch` instead of `height:100%` (fixes rendering)
- **Changed:** Parent div uses `align-items:stretch` and `min-height:20px`
- **Improved:** Content wrapper now uses flexbox for proper conflict badge positioning

### Color Bar Fix:
**Problem**: Bars had `height:100%` but parent had no explicit height
**Solution**: 
- Changed to `align-self:stretch` on bars
- Parent uses `align-items:stretch` 
- Added `min-height:20px` to parent
- Bars now properly fill vertical space

### Sorting Change:
**Before**: Timed events first → All-day events last
**After**: All-day events FIRST → Timed events chronologically

**Example**:
```
Monday, Feb 5
├─ All Day - Project Deadline       ← All-day first
├─ 8:00 AM - Morning Standup        ← Earliest time
├─ 10:30 AM - Coffee with Bob       
└─ 2:00 PM - Team Meeting           ← Latest time
```

### Conflict Badge:
- Orange warning triangle (⚠) on right side
- 10px font size
- Only appears if `event.conflict` is true
- Title attribute shows "Time conflict detected"
- Small and unobtrusive

### README Update:
- Complete rewrite with Matrix theme focus
- Full usage instructions for all features
- Admin interface documentation
- Outlook sync setup guide
- System monitoring details
- Troubleshooting section
- Color scheme reference
- File structure documentation
- Performance tips
- Security notes
- Quick start examples

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

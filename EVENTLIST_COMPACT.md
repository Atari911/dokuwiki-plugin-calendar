# Compact Event List Widget

The `{{eventlist}}` tag has been redesigned as a compact, customizable widget perfect for sidebars, dashboards, and embedded displays.

## Syntax

```
{{eventlist [parameters]}}
```

## Parameters

### Size Parameters

**width** - Set the widget width (default: 300px)
```
{{eventlist width=250px}}
{{eventlist width=20em}}
{{eventlist width=100%}}
```

**height** - Set the maximum height before scrolling (default: 400px)
```
{{eventlist height=300px}}
{{eventlist height=50vh}}
{{eventlist height=600px}}
```

### Date Parameters

**today** - Show only today's events (auto-updates daily)
```
{{eventlist today}}
```

**date** - Show events for a specific date
```
{{eventlist date=2026-01-24}}
```

**daterange** - Show events in a date range
```
{{eventlist daterange=2026-01-20:2026-01-27}}
```

**namespace** - Filter events by namespace
```
{{eventlist namespace=team}}
{{eventlist namespace=team:projects}}
```

## Common Examples

### Today's Events (Sidebar)
```
{{eventlist today width=250px height=400px}}
```
Perfect for a sidebar showing what's happening today.

### This Week's Events
```
{{eventlist daterange=2026-01-20:2026-01-26 width=300px height=500px}}
```
Shows all events for a specific week.

### Team Events Today
```
{{eventlist today namespace=team width=280px height=350px}}
```
Today's events filtered to team namespace.

### Upcoming Events (Next 7 Days)
```
{{eventlist daterange=2026-01-24:2026-01-31 width=320px height=600px}}
```

### Single Day View
```
{{eventlist date=2026-01-25 width=300px height=400px}}
```
Shows all events for January 25, 2026.

### Dashboard Widget (Small)
```
{{eventlist today width=200px height=250px}}
```
Compact widget for dashboard overview.

### Full Sidebar (Large)
```
{{eventlist daterange=2026-01-01:2026-01-31 width=350px height=90vh}}
```
Shows entire month in a tall sidebar.

## Visual Design

### Compact Layout
```
┌─────────────────────────┐
│ 📅 Today's Events       │ ← Blue header
├─────────────────────────┤
│ Team Meeting            │ ← Event 1
│ 2:00 PM                 │
│ Weekly standup...       │
├─────────────────────────┤
│ Code Review             │ ← Event 2
│ 4:00 PM                 │
│ Review PR #123          │
├─────────────────────────┤
│ ...                     │ ↕ Scrolls
└─────────────────────────┘
```

### Features

- **Color-coded:** Each event has colored left border
- **Time display:** 12-hour format (2:00 PM, not 14:00)
- **Rich content:** Images, links, formatting supported
- **Compact spacing:** Minimal padding for maximum content
- **Auto-scroll:** Scrolls when content exceeds height
- **Hover effect:** Events highlight on hover

## Default Behavior

If no parameters specified:
```
{{eventlist}}
```

Defaults to:
- Width: 300px
- Height: 400px
- Shows: Current month's events
- Namespace: All namespaces

## Combining Parameters

You can combine any parameters:

```
{{eventlist today namespace=team width=280px height=400px}}
```
Shows today's team events in a 280x400px widget.

```
{{eventlist daterange=2026-01-24:2026-01-31 width=100% height=500px}}
```
Shows next week's events at full width, 500px tall.

```
{{eventlist date=2026-01-25 namespace=personal width=250px height=300px}}
```
Shows personal events on Jan 25 in compact 250x300px widget.

## Use Cases

### 1. Sidebar - Today's Agenda
```
{{eventlist today width=280px height=600px}}
```
Perfect for showing what's happening today in a sidebar.

### 2. Dashboard Widget - Team Events
```
{{eventlist today namespace=team width=300px height=400px}}
```
Team dashboard showing today's team activities.

### 3. Meeting Room Display
```
{{eventlist today namespace=room:conference width=400px height=90vh}}
```
Display today's conference room bookings on a screen.

### 4. Project Page - This Week
```
{{eventlist daterange=2026-01-20:2026-01-26 namespace=project:alpha width=350px height=500px}}
```
Show this week's project milestones.

### 5. Personal Calendar Sidebar
```
{{eventlist daterange=2026-01-24:2026-01-31 namespace=personal width=300px height=700px}}
```
Next 7 days of personal events.

### 6. Department Overview
```
{{eventlist today namespace=sales width=320px height=450px}}
```
Today's sales department events.

## "Today" Parameter - Special Behavior

The `today` parameter is **dynamic** - it always shows the current day's events:

**January 24, 2026:**
```
{{eventlist today}}
```
Shows events for Jan 24, 2026

**January 25, 2026 (next day):**
Same tag now shows events for Jan 25, 2026

This makes it perfect for:
- Persistent sidebar widgets
- Dashboard displays
- "What's happening today" sections
- Meeting room displays

## Responsive Sizing

### Fixed Size
```
{{eventlist today width=300px height=400px}}
```
Always 300x400px regardless of screen size.

### Percentage-Based
```
{{eventlist today width=100% height=400px}}
```
Full width of container, 400px tall.

### Viewport-Based
```
{{eventlist today width=25vw height=80vh}}
```
25% of viewport width, 80% of viewport height.

### Em-Based
```
{{eventlist today width=20em height=30em}}
```
Scales with font size.

## Content Features

### Time Conversion
Times automatically convert to 12-hour format:
- `14:00` → `2:00 PM`
- `09:30` → `9:30 AM`

### Rich Content Support
Descriptions support:
- **DokuWiki links:** `[[wiki:page|text]]`
- **Section anchors:** `[[wiki:page#section|text]]`
- **External links:** `[[https://example.com|text]]`
- **Images:** `{{image.jpg}}`
- **Markdown links:** `[text](url)`
- **HTML:** `<strong>`, `<em>`, `<code>`

### Multi-Day Ranges
When showing date ranges, dates are grouped:
```
Mon, Jan 24
  • Meeting (2:00 PM)
  • Review (4:00 PM)

Tue, Jan 25
  • Standup (9:00 AM)
  • Planning (3:00 PM)
```

### Today Mode
With `today` parameter, date header is hidden (redundant):
```
📅 Today's Events

• Team Meeting (2:00 PM)
• Code Review (4:00 PM)
• Sprint Planning (5:00 PM)
```

## Tips

1. **Use `today` for persistent displays** - Auto-updates daily
2. **Use `width=100%` for full-width widgets** - Adapts to container
3. **Use `height=80vh` for tall displays** - Uses most of screen
4. **Combine with namespace** - Filter to specific areas
5. **Test on mobile** - Make sure widget is usable on small screens

## Differences from Old EventList

**Old (verbose):**
- Large headers
- Full date display (Monday, January 24, 2026)
- Lots of spacing
- Fixed layout

**New (compact):**
- Minimal header
- Short date (Mon, Jan 24)
- Tight spacing
- Customizable size
- Blue header bar
- Better for sidebars

---

**Version:** 3.2  
**Feature:** Compact Event List Widget  
**Last Updated:** January 24, 2026

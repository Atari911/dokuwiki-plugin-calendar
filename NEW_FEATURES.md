# New Features - Quick Reference

## AJAX Updates (No Page Reload)

All calendar operations now happen without page reloads! ⚡

### What Works with AJAX:

✅ **Add Event** - Save and see it appear immediately  
✅ **Edit Event** - Changes show instantly  
✅ **Delete Event** - Removed in real-time  
✅ **Month Navigation** - Switch months smoothly  
✅ **Day Filter** - Click days to filter events instantly  

### How It Works:

1. **Add Event**: Click + Add → Fill form → Save → Calendar updates automatically
2. **Edit Event**: Click Edit → Modify → Save → Changes appear immediately
3. **Delete Event**: Click Delete → Confirm → Event disappears instantly
4. **Navigate**: Click ◄ ► arrows → New month loads without refresh

No more waiting for page reloads! Everything updates in real-time.

---

## Event Panel Only

Display just the event management panel (320px wide) without the calendar grid.

### Syntax:

```
{{eventpanel}}
```

### With Options:

```
{{eventpanel year=2026 month=6}}
{{eventpanel namespace=team}}
{{eventpanel year=2026 month=3 namespace=projects:alpha}}
```

### Perfect For:

- **Page sidebars** - 320px fits perfectly in side columns
- **Dashboard widgets** - Compact event management
- **Mobile layouts** - Smaller footprint
- **Focus on events** - When you don't need the calendar grid

### Example Sidebar Layout:

```wiki
<columns>
<column 70%>
===== Main Content =====
Your main page content here...
</column>

<column 30%>
{{eventpanel namespace=team}}
</column>
</columns>
```

### Features:

✅ Month navigation (◄ ►)  
✅ Add events (+ Add button)  
✅ Edit/Delete events (inline buttons)  
✅ Scrollable list  
✅ Color-coded events  
✅ Full event details  
✅ AJAX updates (no reload)  

---

## Comparison: Calendar vs Event Panel vs Event List

### Full Calendar (`{{calendar}}`)
- **Size**: 800x600 pixels
- **Layout**: Calendar grid (500px) + Event panel (300px)
- **Use for**: Full month overview with event management
- **Interactive**: Yes - click days, add/edit/delete events
- **AJAX**: Yes - all updates in real-time

### Event Panel Only (`{{eventpanel}}`)
- **Size**: 320x600 pixels
- **Layout**: Event panel only (no calendar grid)
- **Use for**: Sidebars, compact event management
- **Interactive**: Yes - add/edit/delete events, month navigation
- **AJAX**: Yes - all updates in real-time

### Event List (`{{eventlist}}`)
- **Size**: Variable width
- **Layout**: Chronological list of events
- **Use for**: Reports, print-friendly views, date ranges
- **Interactive**: No - read-only display
- **AJAX**: No - static content

---

## AJAX Technical Details

### No More Page Reloads

**Before** (old version):
```
Add Event → Submit → Page reloads → Calendar updates
Delete Event → Confirm → Page reloads → Event gone
Navigate month → Click → Page reloads → New month
```

**Now** (with AJAX):
```
Add Event → Submit → Calendar updates instantly ⚡
Delete Event → Confirm → Event disappears immediately ⚡
Navigate month → Click → New month loads smoothly ⚡
```

### How Calendar Rebuilds Work

When you perform an action (add/edit/delete/navigate):

1. **Action sent** to server via AJAX
2. **Server processes** and returns updated data
3. **Calendar rebuilds** on your screen
   - Grid cells update with new dates
   - Event dots appear/disappear
   - Event list refreshes
   - Navigation buttons update
4. **All happens** in under 1 second - no page flash!

### Benefits

⚡ **Faster** - No full page reload  
✨ **Smoother** - No screen flash or scroll reset  
💾 **Maintains state** - Your position on page stays  
🎯 **Better UX** - Instant feedback on actions  

---

## Quick Syntax Reference

```wiki
# Full calendar (800x600)
{{calendar}}
{{calendar year=2026 month=6}}
{{calendar namespace=team}}

# Event panel only (320px wide)
{{eventpanel}}
{{eventpanel year=2026 month=6}}
{{eventpanel namespace=team}}

# Event list (read-only)
{{eventlist date=2026-01-22}}
{{eventlist daterange=2026-01-01:2026-01-31}}
{{eventlist daterange=2026-01-01:2026-01-31 namespace=team}}
```

---

## Example: Dashboard with Event Panel

```wiki
====== My Dashboard ======

<columns>
<column 65%>
===== Today's Overview =====

**Date**: {{CURRENTDATE}}\\
**Tasks Completed**: 12/20\\
**Meetings Today**: 3

===== Quick Stats =====
  * Open Tasks: 8
  * In Progress: 5
  * Blocked: 2
  * Completed This Week: 15

===== Recent Activity =====
  * ✓ Completed design review
  * ✓ Merged PR #234
  * ⏳ Code review pending
  * 📧 Sent weekly report

[[dashboard:details|View Full Dashboard]]
</column>

<column 35%>
{{eventpanel namespace=personal:me}}
</column>
</columns>

===== This Week's Schedule =====

{{eventlist daterange=2026-01-22:2026-01-28 namespace=personal:me}}
```

Creates a dashboard with:
- Main content on left (65%)
- Event panel on right (35%) 
- Event list below for full week view
- All with AJAX updates!

---

## Tips for Best Performance

1. **Use namespaces** - Separate calendars by team/project
2. **Keep descriptions short** - Long text slows rendering
3. **Archive old events** - Remove events older than 6 months
4. **Use event panels** - For sidebars instead of full calendar
5. **Combine views** - Use eventpanel for management, eventlist for reports

---

## Browser Compatibility

AJAX features work on:
- Chrome/Edge 90+ ✅
- Firefox 88+ ✅
- Safari 14+ ✅
- Mobile browsers ✅

Requires JavaScript enabled (standard for DokuWiki).

---

## Troubleshooting AJAX

**Calendar not updating after save?**
- Check browser console for errors (F12)
- Verify JavaScript is enabled
- Clear browser cache

**"Access denied" errors?**
- Check file permissions on data/meta/calendar/
- Verify user has edit rights on page

**Slow updates?**
- Too many events? Archive old ones
- Server overloaded? Check server resources

**Still issues?**
- Disable other plugins temporarily
- Check DokuWiki error log
- Test with browser console open (F12)

# Testing Guide - Date Editing in Events

## How to Test Date Editing

### Test 1: Creating New Event with Date Selection

1. Open your calendar page: `{{calendar}}`
2. Click **+ Add** button
3. **Verify**: Date field should be visible and editable
4. Select a date (e.g., January 25, 2026)
5. Fill in Title: "Test Event"
6. Click **Save**
7. **Expected**: Event appears on January 25

### Test 2: Editing Event Date from Popup

1. Click on a calendar day that has an event
2. Popup opens showing the event
3. Click **Edit** button
4. **Verify**: Date field shows current event date and is editable
5. Change date to a different day (e.g., from Jan 25 to Jan 28)
6. Click **Save**
7. **Expected**: 
   - Event disappears from Jan 25
   - Event appears on Jan 28
   - Calendar shows event on new date

### Test 3: Editing Event Date from Event List Panel

1. Find an event in the right panel event list
2. Click **Edit** button
3. **Verify**: Date field is visible and shows event date
4. Change the date
5. Click **Save**
6. **Expected**: Event moves to new date

### Test 4: Moving Event to Different Month

1. Edit an event currently in January
2. Change date to February 15, 2026
3. Click **Save**
4. **Expected**:
   - Calendar automatically switches to February
   - Event appears on February 15
   - No longer visible in January

## What the Date Field Should Look Like

When editing an event, you should see:

```
┌─────────────────────────┐
│ Edit Event              │
├─────────────────────────┤
│ Date                    │
│ [2026-01-25] ▼         │  ← This should be editable!
│                         │
│ Title                   │
│ [Team Meeting]          │
│                         │
│ Time                    │
│ [14:00]                 │
│                         │
│ Color                   │
│ [🔵]                    │
│                         │
│ Description             │
│ [Weekly sync...]        │
│                         │
│ [Save] [Cancel]         │
└─────────────────────────┘
```

## Troubleshooting

### Issue: Date field not showing

**Check**:
1. View page source (Ctrl+U)
2. Search for: `type="date"`
3. Should find: `<input type="date" id="event-date-cal_XXXXX" name="date" required>`

**If not found**: Re-upload plugin files

### Issue: Date field showing but disabled/grayed out

**Check**:
1. Look at input element
2. Should NOT have: `readonly` or `disabled` attributes
3. Should have: `required` attribute only

**If disabled**: Clear browser cache and reload page

### Issue: Date changes but event doesn't move

**Check**:
1. Open browser console (F12)
2. Edit event and change date
3. Click Save
4. Look for errors in console
5. Should see: Network request to `ajax.php` with `oldDate` parameter

**If missing oldDate**: Check JavaScript console for errors

### Issue: Calendar doesn't switch to new month

**Check**:
1. Change event date to different month
2. Click Save
3. Check browser console
4. Should see: `reloadCalendarData` being called with new month

## Expected Behavior Summary

✅ **Date field IS visible** in add/edit dialog  
✅ **Date field IS editable** (can click and change)  
✅ **Calendar picker appears** when clicking date field  
✅ **Event moves** when date is changed  
✅ **Calendar switches** to new month automatically  
✅ **Old date is cleared** (event removed from original date)  
✅ **New date shows event** (appears with orange dot)  

## Code Verification

The date field is created in `syntax.php`:

```php
$html .= '<div class="form-row">';
$html .= '<label>Date</label>';
$html .= '<input type="date" id="event-date-' . $calId . '" name="date" required>';
$html .= '</div>';
```

The date is populated when editing in `script.js`:

```javascript
document.getElementById('event-date-' + calId).value = date;
document.getElementById('event-date-' + calId).setAttribute('data-original-date', date);
```

The date change is handled in `script.js`:

```javascript
const dateInput = document.getElementById('event-date-' + calId);
const date = dateInput.value;
const oldDate = dateInput.getAttribute('data-original-date') || date;
```

And processed in `action.php`:

```php
$date = $INPUT->str('date');
$oldDate = $INPUT->str('oldDate', '');

// If editing and date changed, remove from old date first
if ($eventId && $oldDate && $oldDate !== $date) {
    // ... code to remove from old date
}
```

## Browser Compatibility

The `<input type="date">` field is supported by:
- Chrome/Edge: ✅ Full support
- Firefox: ✅ Full support  
- Safari: ✅ Full support
- Mobile browsers: ✅ Full support

If using very old browser, the date field will fall back to text input where you can type date in YYYY-MM-DD format.

## Quick Test Script

Open browser console and run:

```javascript
// Check if date field exists and is editable
const dateField = document.querySelector('input[type="date"]');
if (dateField) {
    console.log('✅ Date field found');
    console.log('Readonly:', dateField.readOnly);
    console.log('Disabled:', dateField.disabled);
    console.log('Current value:', dateField.value);
} else {
    console.log('❌ Date field not found - check plugin installation');
}
```

Expected output:
```
✅ Date field found
Readonly: false
Disabled: false
Current value: 2026-01-25
```

## Still Having Issues?

1. **Clear browser cache** (Ctrl+Shift+Delete)
2. **Hard refresh** the page (Ctrl+F5)
3. **Check file permissions** on server
4. **Verify all plugin files** are uploaded
5. **Check PHP error log** for server-side issues
6. **Test in different browser** to rule out browser issues

## Success Indicators

When everything is working correctly:

1. ✅ You can click the date field
2. ✅ A date picker calendar appears
3. ✅ You can select any date
4. ✅ When you save, event moves to new date
5. ✅ Calendar automatically shows the new month
6. ✅ No JavaScript errors in console
7. ✅ Event appears on correct date immediately

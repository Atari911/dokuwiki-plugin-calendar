# Installation & Upgrade Guide

## 📦 Fresh Installation (New Plugin)

If you're installing the calendar plugin for the first time:

### Step 1: Upload Plugin Files
1. **Extract** the `calendar-compact.zip` file
2. **Upload** the entire `calendar` folder to:
   ```
   dokuwiki/lib/plugins/calendar/
   ```

### Step 2: Set Permissions
```bash
# Make sure web server can write to data directory
chmod 755 dokuwiki/lib/plugins/calendar
chmod -R 755 dokuwiki/data/meta

# Create calendar data directory
mkdir -p dokuwiki/data/meta/calendar
chmod 775 dokuwiki/data/meta/calendar
chown -R www-data:www-data dokuwiki/data/meta/calendar
```

### Step 3: Clear Cache
1. Go to DokuWiki Admin panel
2. Click **"Clear Cache"**
3. Or manually delete: `dokuwiki/data/cache/`

### Step 4: Use the Plugin
Add to any wiki page:
```wiki
{{calendar}}
```

---

## 🔄 Upgrading from Previous Version

If you already have an older version installed:

### ⚠️ IMPORTANT: Backup First!

**Before upgrading, backup your event data:**

```bash
# Backup all calendar data
cp -r dokuwiki/data/meta/calendar /backup/calendar-data-$(date +%Y%m%d)

# Or create archive
tar -czf calendar-backup-$(date +%Y%m%d).tar.gz dokuwiki/data/meta/calendar/
```

### Method 1: Overwrite (Recommended)

**Step 1:** Extract new version
```bash
unzip calendar-compact.zip
```

**Step 2:** Remove old plugin files (KEEP DATA!)
```bash
# Delete old plugin files only (NOT data!)
rm -rf dokuwiki/lib/plugins/calendar/*.php
rm -rf dokuwiki/lib/plugins/calendar/*.css
rm -rf dokuwiki/lib/plugins/calendar/*.js
rm -rf dokuwiki/lib/plugins/calendar/*.txt
rm -rf dokuwiki/lib/plugins/calendar/*.md
rm -rf dokuwiki/lib/plugins/calendar/*.html
```

**Step 3:** Upload new files
```bash
# Upload entire new calendar folder
cp -r calendar/* dokuwiki/lib/plugins/calendar/
```

**Step 4:** Set permissions
```bash
chmod 755 dokuwiki/lib/plugins/calendar
chmod -R 644 dokuwiki/lib/plugins/calendar/*
chmod 755 dokuwiki/lib/plugins/calendar
```

**Step 5:** Clear all caches
```bash
# Clear DokuWiki cache
rm -rf dokuwiki/data/cache/*

# Clear browser cache
# Press Ctrl+Shift+Delete in browser
# Or hard refresh: Ctrl+F5
```

**Step 6:** Verify data is intact
```bash
# Check your events are still there
ls -la dokuwiki/data/meta/calendar/
cat dokuwiki/data/meta/calendar/2026-01.json
```

### Method 2: Side-by-Side (Safer)

**Step 1:** Rename old plugin
```bash
mv dokuwiki/lib/plugins/calendar dokuwiki/lib/plugins/calendar-old
```

**Step 2:** Install new version
```bash
unzip calendar-compact.zip
cp -r calendar dokuwiki/lib/plugins/
```

**Step 3:** Copy data from old location (if needed)
```bash
# If you had data in the old plugin
cp -r dokuwiki/lib/plugins/calendar-old/data/* dokuwiki/data/meta/calendar/
```

**Step 4:** Clear cache
```bash
rm -rf dokuwiki/data/cache/*
```

**Step 5:** Test and remove old version
```bash
# After confirming everything works:
rm -rf dokuwiki/lib/plugins/calendar-old
```

---

## 🗂️ Data Migration

Your event data is stored separately from the plugin in:
```
dokuwiki/data/meta/calendar/
```

**The data WILL NOT be deleted** when you upgrade the plugin files.

### Data Location Check:
```bash
# Find all your event files
find dokuwiki/data/meta -name "*.json" -path "*/calendar/*"

# Example output:
# dokuwiki/data/meta/calendar/2026-01.json
# dokuwiki/data/meta/calendar/2026-02.json
# dokuwiki/data/meta/team/calendar/2026-01.json
```

### If Data is Missing After Upgrade:

1. **Check backup**
   ```bash
   ls -la /backup/calendar-data-*/
   ```

2. **Restore from backup**
   ```bash
   cp -r /backup/calendar-data-20260124/* dokuwiki/data/meta/calendar/
   ```

3. **Fix permissions**
   ```bash
   chmod -R 775 dokuwiki/data/meta/calendar
   chown -R www-data:www-data dokuwiki/data/meta/calendar
   ```

---

## ✅ Verification Checklist

After installation/upgrade, verify:

- [ ] Plugin files exist in `lib/plugins/calendar/`
- [ ] Can see `{{calendar}}` on wiki page
- [ ] Calendar displays correctly
- [ ] Can click "+ Add" button
- [ ] Can add new events
- [ ] Can edit existing events
- [ ] Can delete events
- [ ] Old events still visible
- [ ] Checkboxes work for tasks
- [ ] Edit/delete buttons in top right
- [ ] Time bars show in calendar cells
- [ ] No JavaScript errors (F12 console)

---

## 🔧 Troubleshooting Upgrade Issues

### Problem: Calendar not showing

**Solution:**
```bash
# Clear all caches
rm -rf dokuwiki/data/cache/*

# Hard refresh browser
# Ctrl+Shift+F5 (Windows/Linux)
# Cmd+Shift+R (Mac)
```

### Problem: Events disappeared

**Solution:**
```bash
# Check if data files exist
ls -la dokuwiki/data/meta/calendar/

# If empty, restore from backup
cp -r /backup/calendar-data-20260124/* dokuwiki/data/meta/calendar/

# Fix permissions
chmod -R 775 dokuwiki/data/meta/calendar
chown -R www-data:www-data dokuwiki/data/meta/calendar
```

### Problem: Can't add/edit events

**Solution:**
```bash
# Check write permissions
ls -la dokuwiki/data/meta/calendar/

# Should show: drwxrwxr-x (775)
# If not, fix it:
chmod -R 775 dokuwiki/data/meta/calendar
chown -R www-data:www-data dokuwiki/data/meta/calendar
```

### Problem: Old styling/features

**Solution:**
```bash
# Browser cache - clear it!
# In browser: Ctrl+Shift+Delete

# Also clear server cache
rm -rf dokuwiki/data/cache/*

# Force reload CSS/JS
# Add ?v=2 to end of calendar page URL
# Example: http://wiki.com/calendar?v=2
```

### Problem: JavaScript errors

**Solution:**
1. Open browser console (F12)
2. Look for errors
3. Common fix:
   ```bash
   # Make sure script.js is uploaded
   ls -la dokuwiki/lib/plugins/calendar/script.js
   
   # Should be ~25KB
   # If missing or wrong size, re-upload
   ```

---

## 📋 File List (Verify All Present)

After installation, you should have:

```
dokuwiki/lib/plugins/calendar/
├── action.php              (AJAX handler)
├── syntax.php              (Main rendering)
├── script.js               (JavaScript functions)
├── style.css               (All styling)
├── plugin.info.txt         (Plugin metadata)
├── README.md               (Documentation)
├── EXAMPLES_DOKUWIKI.txt   (Usage examples)
├── NEW_FEATURES.md         (Feature list)
├── TESTING_DATE_EDIT.md    (Testing guide)
├── debug_html.php          (Debug tool)
└── test_date_field.html    (Browser test)
```

**Verify files:**
```bash
cd dokuwiki/lib/plugins/calendar
ls -lh *.php *.js *.css *.txt *.md *.html
```

---

## 🆕 What's New in Latest Version

### New Features:
- ✅ **Super compact interface** - smaller fonts, tighter spacing
- ✅ **Task checkboxes on RIGHT** - positioned in top right corner
- ✅ **Edit/Delete buttons** - top right of each event
- ✅ **Colored time bars** - instead of dots on calendar
- ✅ **12-hour time format** - displays as "2:00 PM"
- ✅ **Multi-day events** - span across multiple days
- ✅ **Task management** - check off completed tasks
- ✅ **Namespace badges** - shows which namespace you're viewing
- ✅ **Draggable dialogs** - move popup windows
- ✅ **Mobile responsive** - works on phones/tablets

### Breaking Changes:
**None!** Your existing events will work with the new version.

### Data Format:
Still uses the same JSON format, but with optional new fields:
- `isTask` (boolean)
- `completed` (boolean)
- `endDate` (string)

Old events without these fields will work fine.

---

## 💾 Backup Strategy

### Before Every Upgrade:

```bash
#!/bin/bash
# Quick backup script

DATE=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="/backup/dokuwiki-calendar"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup event data
cp -r dokuwiki/data/meta/calendar $BACKUP_DIR/data-$DATE

# Backup plugin files
cp -r dokuwiki/lib/plugins/calendar $BACKUP_DIR/plugin-$DATE

echo "Backup saved to: $BACKUP_DIR"
ls -lh $BACKUP_DIR
```

### Automated Backups:

```bash
# Add to crontab for daily backups
0 2 * * * /path/to/backup-script.sh
```

---

## 🔄 Rollback (If Needed)

If the new version has issues:

```bash
# 1. Remove new version
rm -rf dokuwiki/lib/plugins/calendar

# 2. Restore from backup
cp -r /backup/dokuwiki-calendar/plugin-20260124 dokuwiki/lib/plugins/calendar

# 3. Clear cache
rm -rf dokuwiki/data/cache/*

# 4. Reload page
# Hard refresh: Ctrl+F5
```

---

## 📞 Support

### Debug Mode:

1. Open `debug_html.php` in your browser:
   ```
   http://yoursite.com/lib/plugins/calendar/debug_html.php
   ```

2. Check browser console (F12) for errors

3. Verify file permissions:
   ```bash
   ls -la dokuwiki/lib/plugins/calendar/
   ls -la dokuwiki/data/meta/calendar/
   ```

### Common Issues:

| Problem | Solution |
|---------|----------|
| Events not saving | Check write permissions on data/meta/calendar/ |
| Calendar not showing | Clear browser cache (Ctrl+Shift+Delete) |
| Old appearance | Clear server cache (rm data/cache/*) |
| JavaScript errors | Check script.js uploaded correctly |
| Buttons misaligned | Clear browser cache completely |

---

## ✨ Quick Upgrade Command

**For experienced users with SSH access:**

```bash
# One-line upgrade (be careful!)
cd dokuwiki/lib/plugins && \
  tar -czf calendar-backup-$(date +%Y%m%d).tar.gz calendar && \
  rm -rf calendar/*.php calendar/*.js calendar/*.css && \
  unzip /path/to/calendar-compact.zip && \
  cp -r calendar/* calendar/ && \
  rm -rf dokuwiki/data/cache/* && \
  echo "Upgrade complete! Clear browser cache now."
```

**Always backup first!**

---

## 📝 Post-Upgrade Testing

1. **View existing event**
   - Open calendar page
   - Check if old events display

2. **Add new event**
   - Click "+ Add"
   - Fill form
   - Save

3. **Edit event**
   - Click ✏️ on an event
   - Modify details
   - Save

4. **Delete event**
   - Click 🗑️ on an event
   - Confirm deletion

5. **Task checkbox**
   - Create task (check "📋 This is a task")
   - Click checkbox to complete
   - Verify strikethrough

6. **Multi-day event**
   - Create event with end date
   - Verify shows date range

All working? ✅ Upgrade successful!

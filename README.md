# DokuWiki Calendar Plugin - Matrix Edition v4.0.0

A powerful, feature-rich calendar plugin with **Matrix theme**, live system monitoring, real-time weather, **Outlook sync**, and advanced event management.

**Version**: 4.0.0  
**Release Date**: February 6, 2026  
**Codename**: Matrix Edition

---

## 🌟 Key Features

### 📅 Multiple Views
- **Sidebar Widget** - Compact week view with live stats (recommended)
- **Full Calendar** - Traditional month grid with event panel
- **Event Panel** - Standalone event management
- **Event List** - Flexible date range displays

### 🎨 Matrix Theme
- **Authentic Aesthetics** - Green glow effects throughout
- **Single Color Bars** - Clean 3px bars showing event color
- **Dark Backgrounds** - #1a1a1a, rgba(36, 36, 36)
- **Live Updates** - Clock, weather, system stats

### 💻 Sidebar Widget Features
- **Interactive Week Grid** - Click any day to view events
- **Live System Monitoring** - CPU load, real-time CPU, memory usage
- **Hover Tooltips** - Detailed stats (load averages, top processes)
- **Real-time Weather** - Geolocation-based temperature
- **Live Clock** - Updates every second
- **Event Sections** - Today (orange), Tomorrow (green), Important (purple)
- **Add Event Button** - Dark green bar opens full event dialog

### ⚡ Event Management
- **All-Day Events First** - Then sorted chronologically by time
- **Conflict Detection** - Orange ⚠ badge on overlapping events
- **Rich Content** - Full DokuWiki formatting (**bold**, [[links]], //italic//)
- **Single Color Bars** - Clean design with event's assigned color
- **AJAX Operations** - Create, edit, delete without page reload
- **Draggable Dialogs** - Professional event forms

### 🔄 Outlook Integration
- **Bi-directional Sync** - DokuWiki ↔ Microsoft Outlook calendars
- **Category Mapping** - Map DokuWiki colors to Outlook categories
- **Azure AD Authentication** - Secure OAuth 2.0
- **Import/Export Config** - Encrypted configuration files

### 🛠️ Admin Interface
- **Update Plugin Tab** (default) - Version info, changelog, Clear Cache button
- **Outlook Sync Tab** - Azure configuration, category mapping
- **Manage Events Tab** - Browse, edit, delete, move events

---

## 📥 Installation

### 1. Extract Plugin
```bash
cd /path/to/dokuwiki/lib/plugins/
unzip calendar-matrix-edition-v4.0.0.zip
```

### 2. Set Permissions
```bash
mkdir -p data/meta/calendar
chmod -R 775 data/meta/calendar
chown -R www-data:www-data data/meta/calendar
```

### 3. Clear Cache
1. Go to **Admin → Calendar Management**
2. Click **🗑️ Clear Cache** button (orange, prominent)
3. Refresh your wiki page

---

## 🎯 Usage

### Sidebar Widget (Recommended)

Display the Matrix-themed sidebar widget:

```
{{calendar sidebar}}
```

**Features**:
- Current week grid (7 days, clickable)
- Live system stats (CPU, memory)
- Real-time weather with temperature
- Live clock
- Today/Tomorrow/Important event sections
- Dark green Add Event button

**With Namespace**:
```
{{calendar sidebar namespace=team}}
```

### Full Calendar

Traditional month view with event panel:

```
{{calendar}}
```

**Specific Month**:
```
{{calendar year=2026 month=6}}
```

**With Namespace**:
```
{{calendar namespace=team}}
```

### Event Panel Only

Display just the event management panel (320px wide):

```
{{eventpanel}}
```

Perfect for page sidebars.

### Event List

Display events in a simple list:

```
{{eventlist date=2026-02-06}}
```

**Date Range**:
```
{{eventlist daterange=2026-01-01:2026-01-31}}
```

**With Namespace**:
```
{{eventlist daterange=2026-01-01:2026-01-31 namespace=team}}
```

---

## 📝 Creating Events

### Method 1: Sidebar Widget Add Event Button

1. Click the **+ ADD EVENT** dark green bar
2. Event dialog opens
3. Fill in event details
4. Click **Save**

### Method 2: Click Week Grid Day

1. Click any day in the week grid
2. View existing events
3. Click **+ Add** button if desired
4. Fill in event details
5. Click **Save**

### Method 3: Full Calendar

1. Click **+ Add** button in event panel
2. Fill in event details
3. Click **Save**

### Event Fields

**Required**:
- **Date** - YYYY-MM-DD format
- **Title** - Event name (supports **bold**, [[links]], //italic//)

**Optional**:
- **Time** - HH:MM format (24-hour) - leave blank for all-day
- **End Time** - HH:MM format (for duration)
- **Color** - Choose from picker or enter hex code
- **Category** - For organization
- **Description** - Full DokuWiki formatting supported

### DokuWiki Formatting

Events support full DokuWiki syntax:

```
**Meeting with [[team:bob|Bob]]**

Discuss:
  * Project timeline
  * //Budget review//
  * [[projects:alpha|Project Alpha]] status
```

Renders with proper HTML formatting including clickable links.

---

## 👀 Viewing Events

### Sidebar Widget

**Week Grid**:
- 7 days displayed (current week)
- Event count badges on days with events
- Click any day → View all events (expandable section)
- Events sorted: All-day first, then by time

**Today Section** (Orange):
- All events happening today
- Sorted: All-day first, then chronologically

**Tomorrow Section** (Green):
- All events happening tomorrow
- Same sorting as Today

**Important Events Section** (Purple):
- Future events from "important" namespace
- Configurable in Outlook Sync settings

### Clicked Day Events

When you click a day in the week grid:

```
Monday, Feb 5
├─ [Green]  All Day - Project Deadline
├─ [Blue]   8:00 AM - Morning Standup
├─ [Orange] 10:30 AM - Coffee with Bob ⚠
└─ [Purple] 2:00 PM - Team Meeting
```

**Features**:
- **Single color bar** (3px) - Event's assigned color
- **Conflict badge** - ⚠ appears on right if event overlaps
- **Sorting** - All-day events FIRST, then chronological

---

## ⚙️ System Monitoring

### Live Stats in Sidebar

**Green Bar** (5-min CPU Load):
```
Hover to see:
CPU Load Average
1-min: 2.45
5-min: 2.12
15-min: 1.98
Uptime: 5 days, 3 hours
```

**Purple Bar** (Real-time CPU):
```
Hover to see:
CPU Load (Short-term)
Current: 25.3%

Top Processes:
1. apache2 (8.2%)
2. mysql (6.1%)
3. php-fpm (4.5%)
```

**Orange Bar** (Memory Usage):
```
Hover to see:
Memory Usage
Total: 16.0 GB
Used: 8.2 GB (51%)
Available: 7.8 GB

Top Processes:
1. mysql (2.1 GB)
2. apache2 (1.3 GB)
3. php-fpm (845 MB)
```

**Update Frequency**:
- Stats: Every 2 seconds
- Weather: Every 10 minutes
- Clock: Every second

---

## 🌤️ Weather Display

- **Geolocation-based** temperature
- **Fallback**: Irvine, CA (33.6846, -117.8265)
- **Updates**: Every 10 minutes
- **Display**: Icon + temperature (e.g., "🌤️ 72°")

---

## 🔄 Outlook Sync Setup

### Prerequisites
1. Microsoft Azure account
2. Registered application in Azure Portal
3. Calendar permissions granted

### Configuration Steps

1. **Register Azure App**:
   - Go to https://portal.azure.com
   - Navigate to App Registrations
   - Create new registration
   - Note: Tenant ID, Client ID

2. **Create Client Secret**:
   - In your app, go to Certificates & secrets
   - Create new client secret
   - Copy the secret value (shown once!)

3. **Configure Permissions**:
   - Add API permissions:
     - Calendars.ReadWrite
     - Calendars.ReadWrite.Shared
   - Grant admin consent

4. **Enter in DokuWiki**:
   - Admin → Calendar Management → Outlook Sync
   - Enter Tenant ID, Client ID, Client Secret
   - Enter your email address
   - Select timezone
   - Configure sync settings
   - Click **Save Configuration**

5. **Test Sync**:
   - Create event in DokuWiki
   - Run sync (cron job or manual)
   - Check Outlook calendar
   - Create event in Outlook
   - Run sync
   - Check DokuWiki calendar

### Sync Behavior

**DokuWiki → Outlook**:
- New events created in Outlook
- Updates sync to existing events
- Deletes sync if "Delete Outlook events" enabled

**Outlook → DokuWiki**:
- New events created in DokuWiki
- Updates sync to existing events
- Category colors mapped to DokuWiki colors

**Conflict Resolution**: Last-write-wins

---

## 🎨 Color Scheme

### Section Colors

- **Today**: Orange #ff9800
- **Tomorrow**: Green #4caf50
- **Important Events**: Purple #9b59b6
- **Add Event Bar**: Dark green #006400

### System Bars

- **Green Bar**: 5-min CPU load average
- **Purple Bar**: Real-time CPU usage
- **Orange Bar**: Real-time memory usage

### Event Colors

- **Default**: Matrix Green #00cc07
- **Custom**: User-assigned color (via color picker)

---

## 🛠️ Admin Interface

### Access
Go to **Admin → Calendar Management**

### Tabs

#### 1. 📦 Update Plugin (Default Tab)

**Features**:
- Current version and date
- Author information
- Installation path
- Permission check
- **🗑️ Clear Cache** button (prominent orange)
- Recent changelog (last 10 versions)

**Clear Cache**:
- Click orange button
- Confirm dialog
- Clears all DokuWiki cache
- **Use after every plugin update!**

#### 2. ⚙️ Outlook Sync

**Azure Configuration**:
- Tenant ID
- Client ID
- Client Secret
- User Email
- Timezone

**Sync Settings**:
- Default category
- Reminder minutes
- Sync completed tasks (checkbox)
- Delete Outlook events (checkbox)
- Important namespaces (comma-separated)

**Category Mapping**:
- Map DokuWiki colors to Outlook categories
- Visual color picker

**Buttons**:
- **📤 Export Config** - Download encrypted config
- **📥 Import Config** - Upload encrypted config
- **Save Configuration**

#### 3. 📅 Manage Events

**Features**:
- Browse all events across all namespaces
- Filter by namespace
- Search events
- Edit event details
- Delete events
- Move events between dates/namespaces

---

## 📂 File Structure

### Event Storage
```
data/meta/calendar/2026-02.json
data/meta/team/calendar/2026-02.json
```

### Event JSON Format
```json
{
  "2026-02-06": [
    {
      "id": "evt_abc123",
      "title": "**Team Meeting**",
      "title_html": "<strong>Team Meeting</strong>",
      "time": "14:00",
      "end_time": "15:00",
      "description": "Discuss //timeline//",
      "description_html": "Discuss <em>timeline</em>",
      "color": "#3498db",
      "category": "Meetings",
      "namespace": "team",
      "created": "2026-02-05 10:00:00",
      "modified": "2026-02-05 10:30:00",
      "conflict": false
    }
  ]
}
```

### Fields Explained

- **id**: Unique identifier (auto-generated)
- **title**: Raw DokuWiki syntax
- **title_html**: Pre-rendered HTML for JavaScript
- **time**: Start time (HH:MM, 24-hour)
- **end_time**: End time (optional)
- **description**: Raw DokuWiki syntax
- **description_html**: Pre-rendered HTML
- **color**: Hex color code
- **category**: Category name
- **namespace**: Calendar namespace
- **created**: Timestamp
- **modified**: Last modified timestamp
- **conflict**: Boolean - time conflict detected

---

## 🐛 Troubleshooting

### Events Not Displaying

**Check 1**: Clear cache
- Admin → Calendar Management
- Click **🗑️ Clear Cache**
- Confirm and refresh page

**Check 2**: File permissions
```bash
ls -la data/meta/calendar/
# Should show www-data:www-data ownership
# Should show 775 permissions

# Fix if needed:
chown -R www-data:www-data data/meta/calendar/
chmod -R 775 data/meta/calendar/
```

**Check 3**: Check JSON validity
```bash
cat data/meta/calendar/2026-02.json
# Should be valid JSON
```

### Color Bars Not Showing

**Solution**: 
1. Clear browser cache (Ctrl+Shift+R)
2. Clear DokuWiki cache (admin button)
3. Verify plugin version is 4.0.0

### Tooltips Not Working

**Solution**:
1. Verify JavaScript is enabled
2. Clear cache
3. Check console for errors
4. Update to version 4.0.0

### Weather Shows "--°"

**Solution**:
1. Clear cache
2. Allow geolocation in browser
3. Wait 10 seconds for initial update
4. Check console for errors

### Add Event Button Doesn't Work

**Solution**:
1. Check browser console for errors
2. Verify calendar-main.js loaded
3. Clear cache
4. Update to version 4.0.0

### Outlook Sync Not Working

**Check 1**: Azure credentials
- Verify Tenant ID, Client ID, Client Secret
- Check app permissions in Azure Portal
- Ensure admin consent granted

**Check 2**: Cron job
```bash
# Check cron is running
crontab -l

# Should show:
*/15 * * * * /usr/bin/php /path/to/dokuwiki/lib/plugins/calendar/sync_outlook.php
```

---

## 📊 Performance

### Optimizations

- **Event Caching**: JSON files cached per month
- **Lazy Loading**: Events loaded on demand
- **AJAX Updates**: No full page reloads
- **Minimal DOM**: Only visible events rendered

### Recommended Limits

- **Events per month**: < 500 (no performance issues)
- **Events per day**: < 50 (UI remains clean)
- **Namespaces**: Unlimited (loaded separately)
- **Event description**: < 500 characters (for readability)

---

## 🔐 Security

### Data Storage
- Events stored server-side in JSON
- No client-side storage used
- File permissions protect data

### Outlook Sync
- Credentials encrypted in config file
- OAuth 2.0 authentication
- Secrets never logged or displayed

### Admin Access
- Requires DokuWiki admin permissions
- All actions logged
- CSRF protection on forms

---

## 📱 Browser Support

| Browser | Version | Status |
|---------|---------|--------|
| Chrome  | 90+     | ✅ Full |
| Firefox | 88+     | ✅ Full |
| Safari  | 14+     | ✅ Full |
| Edge    | 90+     | ✅ Full |
| Mobile  | Modern  | ⚠️ Limited (sidebar scrollable) |

**Required Features**:
- Flexbox
- Fetch API
- ES6 JavaScript
- CSS Grid

---

## 🆘 Support

### Documentation
- `CHANGELOG.md` - Full version history
- `RELEASE_NOTES_v4.0.0.txt` - v4.0 details
- `OUTLOOK_SYNC_SETUP.md` - Detailed sync guide
- `QUICK_REFERENCE.md` - Syntax quick reference

### Getting Help
1. Check this README first
2. Review CHANGELOG for recent changes
3. Clear cache after updates
4. Check browser console for errors
5. Verify file permissions

---

## 📄 License

GPL 2.0

---

## ✨ What's New in v4.0.0

### Major Changes from v3.x
- ✅ **Single color bars** (removed dual bars)
- ✅ **All-day events first** (reversed sorting)
- ✅ **Add Event dialog** in sidebar widget
- ✅ **Perfect spacing** throughout
- ✅ **Matrix Edition** official naming
- ✅ **Production ready** - all bugs resolved

### Breaking Changes
- Dual color bars removed (now single bar only)
- All-day events now appear FIRST (not last)
- Update Plugin tab is now default (not Config)

### Bug Fixes
- Fixed color bars rendering (align-self:stretch)
- Fixed tooltip function naming
- Fixed weather display
- Fixed HTML rendering in events
- Fixed Add Event dialog
- Fixed spacing throughout

---

## 🎯 Quick Start Examples

### Personal Calendar
```
====== My Schedule ======

{{calendar sidebar namespace=personal}}

**Quick Add**: Click the dark green "+ ADD EVENT" bar
```

### Team Dashboard
```
====== Development Team ======

{{calendar sidebar namespace=team:dev}}

**System Stats**: Hover over colored bars for details
**Today's Events**: Automatically displayed below calendar
```

### Project Timeline
```
====== Project Alpha ======

{{calendar namespace=projects:alpha year=2026 month=3}}

**Milestones**:
  * [[projects:alpha:kickoff|Mar 1 - Kickoff]]
  * [[projects:alpha:design|Mar 15 - Design Review]]
  * [[projects:alpha:launch|Mar 31 - Launch]]
```

---

## 🎉 Credits

**Author**: atari911  
**Email**: atari911@gmail.com  
**Version**: 4.0.0  
**Date**: February 6, 2026  

**Special Features**:
- Matrix theme design
- Outlook synchronization
- System monitoring integration
- Real-time weather display
- Advanced event conflict detection

---

## 🚀 Final Notes

**Version 4.0.0 - Matrix Edition** represents a complete, production-ready calendar plugin with:

- ✨ Beautiful Matrix-themed design
- 💻 Live system monitoring
- 🌤️ Real-time weather
- 📅 Advanced event management
- 🔄 Enterprise Outlook sync
- 🎨 Polished UI throughout

**Install it, clear cache, and enjoy your Matrix calendar!** 🎉

---

**Happy Calendaring! 🗓️✨**

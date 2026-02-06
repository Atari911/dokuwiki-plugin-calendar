# DokuWiki Calendar Plugin - Matrix Edition

A powerful, feature-rich calendar plugin with **Matrix theme**, integrated event management, **Outlook sync**, system monitoring, and advanced features.

**Current Version**: 3.10.6

---

## 🌟 Key Features

### 📅 Calendar Views
- **Full Calendar** - Traditional month grid with event panel
- **Sidebar Widget** - Compact week view with live system stats
- **Event Panel** - Standalone event management
- **Event List** - Flexible date range displays

### 🎨 Matrix Theme
- **Green Glow Effects** - Authentic Matrix-style aesthetics
- **Dual Color Bars** - Section color (4px) + Event color (3px) with 6px gap
- **Live Clock** - Updates every second
- **Weather Display** - Real-time temperature with geolocation
- **System Monitoring** - CPU load, memory usage with tooltips

### ⚡ Advanced Features
- **Outlook Sync** - Bi-directional sync with Microsoft Outlook calendars
- **Time Conflict Detection** - Automatic detection with warning badges (⚠)
- **DokuWiki Formatting** - Full support for **bold**, [[links]], //italic//, etc.
- **Event HTML Rendering** - Rich content in event titles and descriptions
- **Click-to-View** - Click week grid days to see all events
- **Quick Add** - Ultra-thin orange bar for instant event creation

### 🔧 Admin Interface
- **Update Plugin Tab** (default) - Version info, changelog, cache management
- **Outlook Sync Tab** - Configure Microsoft Azure integration
- **Manage Events Tab** - Browse, edit, delete, move events

---

## 📥 Installation

### 1. Extract Plugin
```bash
# Extract to DokuWiki plugins directory
cd /path/to/dokuwiki/lib/plugins/
unzip calendar-matrix-update-v3.10.6.zip
```

### 2. Set Permissions
```bash
# Create data directories
mkdir -p data/meta/calendar
chmod -R 775 data/meta/calendar
chown -R www-data:www-data data/meta/calendar
```

### 3. Clear Cache
1. Go to **Admin → Calendar Management**
2. Update Plugin tab opens automatically
3. Click **🗑️ Clear Cache** button
4. Refresh your wiki page

---

## 🎯 Usage

### Basic Calendar Syntax

#### Full Calendar (Month View)
```
{{calendar}}
```
Displays current month with event panel on the right.

#### Specific Month
```
{{calendar year=2026 month=6}}
```

#### With Namespace
```
{{calendar namespace=team}}
```
Separate calendars for different teams/projects.

### Sidebar Widget

#### Week View with System Stats
```
{{calendar sidebar}}
```

**Features**:
- Current week grid (7 days)
- Live clock (updates every second)
- Real-time weather with temperature
- System monitoring bars:
  - **Green bar**: 5-min CPU load average
  - **Purple bar**: Real-time CPU usage
  - **Orange bar**: Memory usage
- Hover tooltips with detailed stats
- Click days to view all events
- **+ ADD EVENT** bar for quick access
- Today/Tomorrow/Important event sections

#### With Namespace
```
{{calendar sidebar namespace=team}}
```

### Event Panel Only
```
{{eventpanel}}
```
320px wide panel - perfect for page sidebars.

### Event List
```
{{eventlist date=2026-01-22}}
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

### Method 1: Sidebar Widget
1. Click the **+ ADD EVENT** bar (thin orange line below header)
2. Opens Admin → Manage Events tab
3. Fill in event details
4. Click **Save**

### Method 2: Calendar Grid
1. Click **+ Add** button in event panel
2. Fill in event details
3. Click **Save**

### Method 3: Day Popup
1. Click any day in calendar grid
2. Click **+ Add** in popup
3. Fill in event details
4. Click **Save**

### Event Fields

**Required**:
- **Date** - YYYY-MM-DD format (can be changed to move events)
- **Title** - Event name (supports **bold**, [[links]], //italic//)

**Optional**:
- **Time** - HH:MM format (24-hour)
  - Leave blank for all-day events
- **End Time** - HH:MM format (for duration)
- **Color** - Choose from color picker or enter hex code
- **Category** - For organization
- **Description** - Full DokuWiki formatting supported

### DokuWiki Formatting Support

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
- Click any day → View all events in expandable section
- Events sorted: All-day first, then by time

**Today Section** (Orange):
- All events happening today
- Sorted: All-day events first, then chronologically

**Tomorrow Section** (Green):
- All events happening tomorrow
- Same sorting as Today

**Important Events Section** (Purple):
- Future events from "important" namespace
- Configurable in Outlook Sync settings

### Clicked Day Events

When you click a day in the week grid:

**Display**:
```
Monday, Feb 5
├─ [Blue][Green]  All Day - Project Deadline
├─ [Blue][Green]  8:00 AM - Morning Standup
├─ [Blue][Orange] 10:30 AM - Coffee with Bob ⚠
└─ [Blue][Purple] 2:00 PM - Team Meeting
```

**Color Bars**:
- **First bar** (4px, blue) - Section color (selected day = blue)
- **Second bar** (3px) - Event's assigned color
- 6px gap between bars

**Conflict Badge**:
- **⚠** appears if event overlaps with another
- Orange warning triangle on the right
- Small (10px) and unobtrusive

**Sorting Order**:
1. All-day events (no time) appear **first**
2. Timed events sorted chronologically (earliest → latest)

---

## ⚙️ Admin Interface

### Access
Go to **Admin → Calendar Management**

### Tabs

#### 1. 📦 Update Plugin (Default Tab)

**Features**:
- Current version and date
- Author information
- Installation path
- Permission check
- **🗑️ Clear Cache** button (prominent orange button)
- Recent changelog (last 10 versions)

**Clear Cache**:
- Click orange button
- Confirm dialog
- Clears all DokuWiki cache
- **Use after every plugin update!**
- Success message displays on same tab

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

**Namespace Selection**:
- Sync all namespaces (checkbox)
- Or select specific namespaces

**Category Mapping**:
- Map DokuWiki colors to Outlook categories
- Visual color picker
- Custom mappings

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
- Bulk operations

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

**Conflict Resolution**:
- Last-write-wins
- Sync timestamp tracked

---

## 🎨 Color Scheme

### Section Colors (Left Bar, 4px)

- **Today**: Orange `#ff9800`
- **Tomorrow**: Green `#4caf50`
- **Important Events**: Purple `#9b59b6`
- **Selected Day**: Blue `#3498db`

### Event Colors (Right Bar, 3px)

- **Default**: Matrix Green `#00cc07`
- **Custom**: User-assigned color
- **Gap**: 6px between section and event bars

### System Bars

- **Green**: 5-min CPU load average
- **Purple**: Real-time CPU usage (5-sec average)
- **Orange**: Real-time memory usage

### UI Elements

- **Add Event Bar**: Orange `#ff9800` (6px height)
- **Conflict Badge**: Orange `#ff9800` (⚠ symbol)
- **Clear Cache Button**: Orange `#ff9800`

---

## 🔍 System Monitoring

### Sidebar Widget Stats

**Green Bar Tooltip** (5-min CPU Load):
```
CPU Load Average
1-min: 2.45
5-min: 2.12
15-min: 1.98
Uptime: 5 days, 3 hours
```

**Purple Bar Tooltip** (Real-time CPU):
```
CPU Load (Short-term)
Current: 25.3%

Top Processes:
1. apache2 (8.2%)
2. mysql (6.1%)
3. php-fpm (4.5%)
```

**Orange Bar Tooltip** (Memory):
```
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

## 📂 File Structure

### Event Storage
```
data/meta/calendar/2026-01.json
data/meta/calendar/2026-02.json
data/meta/[namespace]/calendar/2026-01.json
data/meta/team/calendar/2026-03.json
```

### Event JSON Format
```json
{
  "2026-02-06": [
    {
      "id": "evt_abc123",
      "title": "**Team Meeting** with [[team:bob|Bob]]",
      "title_html": "<strong>Team Meeting</strong> with <a href=\"...\">Bob</a>",
      "time": "14:00",
      "end_time": "15:00",
      "description": "Discuss //project timeline//",
      "description_html": "Discuss <em>project timeline</em>",
      "color": "#3498db",
      "category": "Meetings",
      "namespace": "team",
      "created": "2026-02-05 10:00:00",
      "modified": "2026-02-05 10:30:00",
      "conflict": true
    }
  ]
}
```

### Fields Explained

- **id**: Unique identifier (auto-generated)
- **title**: Raw DokuWiki syntax
- **title_html**: Pre-rendered HTML for JavaScript display
- **time**: Start time (HH:MM, 24-hour)
- **end_time**: End time (optional)
- **description**: Raw DokuWiki syntax
- **description_html**: Pre-rendered HTML
- **color**: Hex color code
- **category**: Category name
- **namespace**: Calendar namespace
- **created**: Timestamp (YYYY-MM-DD HH:MM:SS)
- **modified**: Last modified timestamp
- **conflict**: Boolean - time conflict detected

---

## 🛠️ Troubleshooting

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
# Check for syntax errors
```

### Color Bars Not Showing

**Symptom**: No colored bars in clicked day events

**Solution**: 
1. Clear browser cache (Ctrl+Shift+R)
2. Clear DokuWiki cache (admin button)
3. Check browser console for errors
4. Verify plugin version is 3.10.6+

### Tooltips Not Working

**Symptom**: Hover over system bars shows no tooltip

**Solution**:
1. Verify JavaScript is enabled
2. Clear cache
3. Check console for "showTooltip_sidebar_* is not defined"
4. Update to version 3.10.6+

### Weather Shows "--°"

**Solution**:
1. Clear cache
2. Allow geolocation in browser (or uses Irvine, CA default)
3. Wait 10 seconds for initial update
4. Check console for weather API errors

### Outlook Sync Not Working

**Check 1**: Azure credentials
- Verify Tenant ID, Client ID, Client Secret
- Check app permissions in Azure Portal
- Ensure admin consent granted

**Check 2**: Sync logs
- Check `/lib/plugins/calendar/sync.log`
- Look for authentication errors
- Verify API call responses

**Check 3**: Cron job
```bash
# Check cron is running
crontab -l

# Should show:
*/15 * * * * /usr/bin/php /path/to/dokuwiki/lib/plugins/calendar/sync_outlook.php
```

### HTML Formatting Not Rendering

**Symptom**: Event shows `**bold**` instead of **bold**

**Solution**:
1. Clear cache (critical!)
2. Verify DokuWiki parser is working
3. Check `title_html` and `description_html` fields exist in JSON
4. Update to version 3.10.2+

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
| Mobile  | Modern  | ⚠️ Limited (sidebar may need scrolling) |

**Required Features**:
- Flexbox
- Fetch API
- ES6 JavaScript
- CSS Grid

---

## 🆘 Support

### Documentation
- `CHANGELOG.md` - Version history
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

## ✨ Credits

**Author**: atari911  
**Email**: atari911@gmail.com  
**Version**: 3.10.6  
**Date**: February 6, 2026

**Special Features**:
- Matrix theme design
- Outlook synchronization
- System monitoring integration
- Real-time weather display
- Advanced event conflict detection

---

## 🎯 Quick Start Examples

### Personal Calendar
```
====== My Schedule ======

{{calendar sidebar namespace=personal}}

**Quick Add**: Click the orange "+ ADD EVENT" bar
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

**🎉 Enjoy your Matrix-themed calendar with full Outlook integration!**

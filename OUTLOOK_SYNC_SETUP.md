# 🔄 DokuWiki → Outlook Sync Setup Guide

## 📋 Prerequisites

- PHP 7.4+ with cURL extension
- Office 365 / Outlook.com account
- Azure account (free tier works fine)

---

## 🔑 Step 1: Azure App Registration

### 1. Go to Azure Portal
https://portal.azure.com → **Azure Active Directory** → **App registrations**

### 2. Create New Registration
- Click **"New registration"**
- Name: `DokuWiki Calendar Sync`
- Supported account types: **"Accounts in this organizational directory only"**
- Redirect URI: Leave blank
- Click **Register**

### 3. Note Your IDs
**Copy these values** (you'll need them):
- **Application (client) ID** - e.g., `abc123-...`
- **Directory (tenant) ID** - e.g., `xyz789-...`

### 4. Create Client Secret
- Go to **Certificates & secrets** tab
- Click **"New client secret"**
- Description: `DokuWiki Sync`
- Expires: **24 months** (recommended)
- Click **Add**
- **⚠️ COPY THE SECRET VALUE NOW** - You can't see it again!

### 5. Grant API Permissions
- Go to **API permissions** tab
- Click **"Add a permission"**
- Choose **Microsoft Graph**
- Choose **Application permissions**
- Search and add:
  - `Calendars.ReadWrite` ✅
  - `User.Read.All` ✅
- Click **"Grant admin consent"** (requires admin)
  - If you're not admin, request consent from IT

---

## ⚙️ Step 2: Configure Sync Script

### 1. Edit Configuration File
```bash
cd /var/www/html/dokuwiki/lib/plugins/calendar
nano sync_config.php
```

### 2. Fill In Credentials
```php
'tenant_id' => 'YOUR_TENANT_ID_HERE',        // From Azure Portal
'client_id' => 'YOUR_CLIENT_ID_HERE',        // Application ID
'client_secret' => 'YOUR_CLIENT_SECRET_HERE', // Secret value
'user_email' => 'your@email.com',            // Your Office 365 email
```

### 3. Configure Category Mapping (Optional)
Map DokuWiki namespaces to Outlook categories:
```php
'category_mapping' => [
    'work' => 'Blue category',
    'personal' => 'Green category',
    'projects' => 'Yellow category',
],
```

**Available Outlook Categories:**
- Blue category
- Green category
- Orange category
- Red category
- Yellow category
- Purple category

### 4. Save Configuration
```bash
# Make sure permissions are secure
chmod 600 sync_config.php
```

---

## 🧪 Step 3: Test the Sync

### 1. Dry Run (No Changes)
```bash
php sync_outlook.php --dry-run
```

**Expected output:**
```
[2026-01-25 23:45:30] [INFO] === DokuWiki → Outlook Sync Started ===
[2026-01-25 23:45:30] [INFO] DRY RUN MODE - No changes will be made
[2026-01-25 23:45:31] [INFO] Authenticating with Microsoft Graph API...
[2026-01-25 23:45:32] [INFO] Authentication successful
[2026-01-25 23:45:32] [INFO] Loading DokuWiki calendar events...
[2026-01-25 23:45:32] [INFO] Found 25 events in DokuWiki
[2026-01-25 23:45:32] [INFO] Created: Meeting [work]
[2026-01-25 23:45:32] [INFO] Created: Dentist [personal]
...
[2026-01-25 23:45:35] [INFO] === Sync Complete ===
[2026-01-25 23:45:35] [INFO] Scanned: 25 events
[2026-01-25 23:45:35] [INFO] Created: 25
[2026-01-25 23:45:35] [INFO] Updated: 0
[2026-01-25 23:45:35] [INFO] Deleted: 0
```

### 2. Real Sync
If dry run looks good:
```bash
php sync_outlook.php
```

### 3. Check Outlook
Open Outlook and verify:
- Events appear in your calendar
- Categories are color-coded
- Reminders are set (15 minutes before)

---

## 🔄 Step 4: Automate with Cron

### 1. Add Cron Job
```bash
crontab -e
```

### 2. Add This Line
```bash
# Sync DokuWiki calendar to Outlook every 30 minutes
*/30 * * * * cd /var/www/html/dokuwiki/lib/plugins/calendar && php sync_outlook.php >> sync.log 2>&1
```

### 3. Alternative: Hourly Sync
```bash
# Every hour at :15 (e.g., 1:15, 2:15, 3:15)
15 * * * * cd /var/www/html/dokuwiki/lib/plugins/calendar && php sync_outlook.php >> sync.log 2>&1
```

---

## 🎯 Usage Examples

### Sync Everything
```bash
php sync_outlook.php
```

### Sync Specific Namespace
```bash
php sync_outlook.php --namespace=work
```

### Dry Run (Preview Changes)
```bash
php sync_outlook.php --dry-run
```

### Force Re-sync All
```bash
php sync_outlook.php --force
```

### Verbose Output
```bash
php sync_outlook.php --verbose
```

---

## 📁 Files Created

- **sync_config.php** - Your credentials (gitignore this!)
- **sync_state.json** - Mapping of DokuWiki IDs to Outlook IDs
- **sync.log** - Sync history and errors

---

## 🔍 Troubleshooting

### "Failed to get access token"
- Check your tenant_id, client_id, and client_secret
- Verify API permissions are granted in Azure
- Check if client secret has expired

### "Calendars.ReadWrite permission not granted"
- Go to Azure Portal → App registrations → Your app → API permissions
- Click "Grant admin consent"
- May need IT admin to approve

### Events not syncing
```bash
# Check the log
tail -f sync.log

# Test authentication
php sync_outlook.php --dry-run --verbose
```

### Wrong timezone
```bash
# Edit sync_config.php
'timezone' => 'America/Los_Angeles'  # Pacific Time
'timezone' => 'America/New_York'     # Eastern Time
'timezone' => 'Europe/London'        # GMT
```

### Categories not showing
- Categories must exist in Outlook first
- Use one of the 6 preset colors
- Or create custom categories in Outlook settings

---

## 🎨 Category Setup in Outlook

### Option 1: Use Presets (Easiest)
Just use the built-in colors:
- Blue category
- Green category
- Yellow category
- Orange category
- Red category
- Purple category

### Option 2: Rename Categories
1. Open Outlook
2. Go to **Calendar** view
3. Right-click any category
4. Choose **"All Categories"**
5. Rename categories to match your namespaces:
   - Blue category → Work
   - Green category → Personal
   - Yellow category → Projects

Then update sync_config.php:
```php
'category_mapping' => [
    'work' => 'Work',
    'personal' => 'Personal',
    'projects' => 'Projects',
],
```

---

## 🔒 Security Notes

- **Never commit sync_config.php** to git
- Add to .gitignore:
  ```
  sync_config.php
  sync_state.json
  sync.log
  ```
- File permissions: `chmod 600 sync_config.php`
- Store credentials securely

---

## 📊 What Gets Synced

✅ Event title  
✅ Date and time  
✅ Multi-day events  
✅ All-day events  
✅ Description  
✅ Category (based on namespace)  
✅ Reminders (15 min before)  

❌ Recurring events (expanded as individual occurrences)  
❌ Event colors (uses categories instead)  
❌ Task checkboxes (syncs as events)  

---

## 🚀 Advanced Features

### Skip Completed Tasks
```php
'sync_completed_tasks' => false,
```

### Disable Deletions
```php
'delete_outlook_events' => false,
```

### Custom Reminder Time
```php
'reminder_minutes' => 30,  // 30 minutes before
```

### Filter Namespaces
```bash
# Only sync work events
php sync_outlook.php --namespace=work
```

---

## 💡 Pro Tips

1. **Run dry-run first** - Always test with `--dry-run` before real sync
2. **Check logs** - Monitor `sync.log` for errors
3. **Backup Outlook** - Export calendar before first sync
4. **Test with one namespace** - Start small with `--namespace=test`
5. **Schedule during off-hours** - Run cron at night to avoid conflicts

---

## 🆘 Support

**Common Issues:**
- Check sync.log for detailed errors
- Verify Azure permissions are granted
- Test API credentials with --dry-run
- Ensure PHP has cURL extension

**Reset Sync State:**
```bash
# Start fresh (will re-sync everything)
rm sync_state.json
php sync_outlook.php --dry-run
```

---

**Version:** 1.0  
**Compatibility:** DokuWiki Calendar Plugin v3.3+  
**Tested:** Office 365, Outlook.com  

🎉 **Happy Syncing!** 🎉

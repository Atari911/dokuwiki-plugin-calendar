# Outlook Sync - Automated Scheduling

## ✅ CORRECT Cron Setup

```bash
# Every hour at :00 (recommended)
0 * * * * cd /var/www/html/dokuwiki/lib/plugins/calendar && php sync_outlook.php >> sync.log 2>&1

# Every 30 minutes (more frequent)
*/30 * * * * cd /var/www/html/dokuwiki/lib/plugins/calendar && php sync_outlook.php >> sync.log 2>&1

# Every 15 minutes (very frequent)
*/15 * * * * cd /var/www/html/dokuwiki/lib/plugins/calendar && php sync_outlook.php >> sync.log 2>&1
```

## ❌ WRONG - DO NOT USE --reset

```bash
# ❌ NEVER DO THIS - Will search Outlook every time and could create duplicates
0 * * * * cd /path && php sync_outlook.php --reset >> sync.log 2>&1
```

## Why No --reset?

**--reset** clears the mapping file and forces the script to search Outlook for every event.

- ✅ **Normal sync**: Uses mapping file → knows what's already synced → fast & reliable
- ❌ **--reset every hour**: Searches Outlook → slow → unnecessary → risky

## When to Use --reset

**Only use `--reset` for:**
1. Initial setup (first time)
2. After major Outlook cleanup
3. If sync_state.json gets corrupted
4. Troubleshooting duplicates

**How often:** Once, maybe twice ever. NOT in cron!

## Monitoring Your Sync

### Check Last Run
```bash
tail -20 sync.log
```

### Check for Errors
```bash
grep ERROR sync.log | tail -10
```

### Check Stats
```bash
tail sync.log | grep "Sync Complete" -A10
```

**Expected Output (hourly):**
```
Scanned: 321 events
Created: 0      ← Should be 0 after initial sync
Updated: 5      ← Only changed events
Recreated: 0
Deleted: 0
Skipped: 205    ← Recurring event occurrences
Errors: 0
```

## Log Rotation

Your sync.log will grow. Rotate it monthly:

```bash
# Add to cron
0 0 1 * * mv /path/sync.log /path/sync.log.$(date +\%Y\%m) && touch /path/sync.log
```

## Version 3.3

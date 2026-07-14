# Auth Live Log System — Complete Setup Guide
**NetPoint ISP | Zalpro + FreeRADIUS**  
**Maintained by: Hanzilah Waheed**

---

## Overview

This system shows real-time authentication + disconnect logs in two places:

1. **User Profile Page** → `auth-live-log.php` — shows logs for ONE specific user
2. **All Users Page** → "Live Logs" button → opens `userlog.php?username=all` — shows ALL users

**How it works:**
```
auth-live-log.php (profile widget)
    └── iframe → /userlog.php?username=john
                    ├── FreeRADIUS log file (/var/log/freeradius/radius.log)
                    │     → Login OK / Rejected events
                    └── MySQL radacct table
                          → Disconnect events (with cause + IP)
```

---

## Quick Reference — Change These If Anything Changes

| What | Current Value | Where to Change |
|------|--------------|-----------------|
| MySQL Host | `localhost` | `userlog.php` ($db_host) |
| MySQL DB Name | `zalpro` | `userlog.php` ($db_name) |
| MySQL Credentials | In credentials file | `/zalpro-optimization/credentials/db_config.php` |
| FreeRADIUS Log Path | `/var/log/freeradius/radius.log` | `userlog.php` ($log_path) |

---

## Files Overview

### Files to Upload via WinSCP

**File 1:** `userlog.php`
- Upload to: `/var/www/html/userlog.php`
- What it does: Main log viewer. Reads FreeRADIUS log + radacct table, merges and displays

**File 2:** `auth-live-log.php`
- Upload to: `/zalpro-optimization/auth-live-log.php`
- What it does: Profile page widget. Shows iframe of userlog.php for specific user

---

## Step-by-Step Setup

### Step 1: Upload Files via WinSCP

Connect to Zalpro server:
- Host: `103.111.39.174` (or private IP `172.17.21.2`)
- User: `root`

Upload:
```
userlog.php       → /var/www/html/userlog.php
auth-live-log.php → /zalpro-optimization/auth-live-log.php
```

---

### Step 2: Create Credentials File

This file stores MySQL credentials separately (security best practice).

```bash
# Create credentials directory
sudo mkdir -p /zalpro-optimization/credentials

# Create credentials file
sudo nano /zalpro-optimization/credentials/db_config.php
```

Paste this content:
```php
<?php
// MySQL Credentials for Zalpro Log System
// Addition By hanxill

define('DB_USER', 'root');          // <-- Change: MySQL username
define('DB_PASS', 'YOUR_PASSWORD'); // <-- Change: MySQL root password
```

> ⚠️ **Important:** Replace `YOUR_PASSWORD` with your actual MySQL root password!

Set permissions:
```bash
sudo chmod 600 /zalpro-optimization/credentials/db_config.php
sudo chown www-data:www-data /zalpro-optimization/credentials/db_config.php
```

---

### Step 3: Fix FreeRADIUS Log Permissions

`www-data` (Apache) needs to read the FreeRADIUS log file:

```bash
# Add www-data to freerad group
sudo usermod -aG freerad www-data

# Fix log file permissions
sudo chmod 644 /var/log/freeradius/radius.log
sudo chmod 755 /var/log/freeradius/

# Restart Apache to apply group change
sudo systemctl restart apache2

# Test that www-data can now read it
sudo -u www-data cat /var/log/freeradius/radius.log | tail -3
# Should show log lines, NOT "Permission denied"
```

Make permissions permanent after log rotation:
```bash
sudo nano /etc/logrotate.d/freeradius
```

Find first block and add `create 644 freerad freerad`:
```
/var/log/freeradius/radius.log {
    daily
    rotate 52
    missingok
    compress
    delaycompress
    notifempty
    copytruncate
    create 644 freerad freerad    # <-- Add this line
}
```

Also add to crontab for reboot persistence:
```bash
(crontab -l 2>/dev/null; echo "@reboot chmod 644 /var/log/freeradius/radius.log && chmod 755 /var/log/freeradius/") | crontab -
```

---

### Step 4: Add "Live Logs" Button to All Users Page

File to edit:
```
/var/www/html/application/views/themes/legacy/admin_portal/users/all.php
```

```bash
# Find where to add (look for checkAdminOrStaff and export button)
grep -n "checkAdminOrStaff\|export-user\|Live Logs" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/all.php | head -10
```

You need to add the "Live Logs" button JUST AFTER the existing `checkAdminOrStaff` export button block.

**Find this existing code:**
```php
if (checkAdminOrStaff()) {
    echo '... <button class="btn btn-zalpro text-white"><i class="fas fa-file-import"></i> Export U...
}
```

**Add this RIGHT AFTER it:**
```php
# Addition By hanxill
#--------------------------------------------------------------------------
#-----------------------------All-User-Auth-Log----------------------------
echo '                            <h2 class="right"><a href="/userlog.php?username=all&filter=all" target="_blank"><button class="btn btn-zalpro text-white"><i class="fas fa-terminal"></i> Live Logs</button></a></h2>' . "\n" . '                        ';
#--------------------------------------------------------------------------
```

Using sed (replace LINE_NUMBER with actual line number from grep output above):
```bash
# First find exact line number of the closing } after checkAdminOrStaff export block
grep -n "checkAdminOrStaff" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/all.php

# Then add after that line (replace 45 with actual line number)
sed -i '45a # Addition By hanxill\n#--------------------------------------------------------------------------\necho '"'"'                            <h2 class="right"><a href="/userlog.php?username=all&filter=all" target="_blank"><button class="btn btn-zalpro text-white"><i class="fas fa-terminal"></i> Live Logs<\/button><\/a><\/h2>'"'"' . "\\n" . '"'"'                        '"'"';\n#--------------------------------------------------------------------------' \
    /var/www/html/application/views/themes/legacy/admin_portal/users/all.php

# Verify it was added
grep -n "Live Logs\|userlog" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/all.php
```

---

### Step 5: Add Auth Log Widget to User Profile Page

File to edit:
```
/var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

```bash
# Check if already added
grep -n "auth-live-log" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

If NOT there, find where profileDocument.php is included:
```bash
grep -n "profileDocument" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

Add AFTER that line:
```php
# Addition By hanxill
# ------------------- AUTH LIVE LOG -----------------------------
echo '<div class="row">';
include '/zalpro-optimization/auth-live-log.php';
echo '</div>';
# -------------------------------------------------------------
```

Using sed (replace LINE_NUMBER with actual line from grep):
```bash
sed -i 'LINE_NUMBERa # ------------------- AUTH LIVE LOG -----------------------------\necho '"'"'<div class="row">'"'"';\ninclude '"'"'/zalpro-optimization/auth-live-log.php'"'"';\necho '"'"'</div>'"'"';\n# -------------------------------------------------------------' \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php

# Verify
grep -n "auth-live-log" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

---

### Step 6: Test Everything

```bash
# Test 1: Credentials file readable
sudo -u www-data php -r "require '/zalpro-optimization/credentials/db_config.php'; echo DB_USER . PHP_EOL;"
# Expected: root

# Test 2: Log file readable by www-data
sudo -u www-data cat /var/log/freeradius/radius.log | tail -3
# Expected: log lines

# Test 3: userlog.php responding
curl "http://103.111.39.174/userlog.php?username=juniper" | head -20
# Expected: HTML with table

# Test 4: All users log
curl "http://103.111.39.174/userlog.php?username=all" | head -20
# Expected: HTML with "LIVE ALL SUBSCRIBERS DASHBOARD"

# Test 5: Database disconnect logs working
mysql -u root -p zalpro -e "
SELECT username, acctstoptime, acctterminatecause
FROM radacct
WHERE acctstoptime IS NOT NULL
ORDER BY acctstoptime DESC
LIMIT 5;"
# Expected: rows with disconnect times and causes
```

---

## How userlog.php Works (Technical Details)

### Data Sources

**Source 1: FreeRADIUS Log File**
- Path: `/var/log/freeradius/radius.log`
- Events captured: `Login OK`, `Rejected`
- Method: `grep` command via `shell_exec()`

**Source 2: MySQL radacct Table**
- Database: `zalpro`
- Table: `radacct`
- Events captured: `Disconnect` (when `acctstoptime IS NOT NULL`)
- Fields used: `username`, `acctstoptime`, `acctterminatecause`, `framedipaddress`

**Merge Logic:**
1. Pull all auth logs from file
2. Pull all disconnect events from database
3. Merge both arrays
4. Sort by timestamp (newest on top)
5. Display top 1000 entries

### Event Types & Colors

| Badge | Color | Source | Meaning |
|-------|-------|--------|---------|
| Login OK | Green `#26B99A` | radius.log | Successful authentication |
| Rejected | Red `#d9534f` | radius.log | Failed authentication |
| Disconnect | Red `#d9534f` | radacct DB | Session ended |
| Info | Dark `#34495E` | radius.log | Other events |

### Features

- **Auto-refresh:** Every 5 seconds (reloads page)
- **Freeze button:** Stop auto-refresh temporarily
- **Row highlighting:** Click any row to highlight it (persists across refreshes via localStorage)
- **Filter:** `username=all` shows all users, `username=john` shows only john's logs

---

## Credentials File Reference

File: `/zalpro-optimization/credentials/db_config.php`

```php
<?php
define('DB_USER', 'root');           // MySQL username
define('DB_PASS', 'YOUR_PASSWORD'); // MySQL password
```

This file is included by `userlog.php` at the top:
```php
$config_path = '/zalpro-optimization/credentials/db_config.php';
require_once $config_path;
```

> **Why separate file?** Security. Credentials are not in the web-accessible file.
> Even if userlog.php code is exposed, credentials remain in a protected path.

---

## File Permissions Reference

```bash
# userlog.php - web accessible
chmod 644 /var/www/html/userlog.php
chown www-data:www-data /var/www/html/userlog.php

# auth-live-log.php - included by PHP, not web accessible
chmod 644 /zalpro-optimization/auth-live-log.php
chown www-data:www-data /zalpro-optimization/auth-live-log.php

# Credentials file - restricted
chmod 600 /zalpro-optimization/credentials/db_config.php
chown www-data:www-data /zalpro-optimization/credentials/db_config.php

# FreeRADIUS log - www-data needs read access
chmod 644 /var/log/freeradius/radius.log
chmod 755 /var/log/freeradius/
```

---

## Common Issues & Fixes

### "Configuration file missing"
**Cause:** `db_config.php` not created  
**Fix:** Follow Step 2 above

### "Log file path not readable by web server"
**Cause:** www-data doesn't have permission to read radius.log  
**Fix:**
```bash
sudo usermod -aG freerad www-data
sudo chmod 644 /var/log/freeradius/radius.log
sudo systemctl restart apache2
```

### Disconnect events not showing
**Cause:** radacct table empty or MySQL credentials wrong  
**Fix:**
```bash
# Check if disconnect data exists in DB
mysql -u root -p zalpro -e "SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NOT NULL;"

# Check credentials file
sudo -u www-data php -r "require '/zalpro-optimization/credentials/db_config.php'; echo DB_USER;"
```

### "Live Logs" button not showing in all users page
**Cause:** Code not added to all.php  
**Fix:** Follow Step 4, check exact line number and re-add

### No logs showing for specific user
**Cause:** Username mismatch between Zalpro and FreeRADIUS  
**Fix:**
```bash
# Check what FreeRADIUS actually logs for that user
grep -i "juniper" /var/log/freeradius/radius.log | tail -5
# Compare with Zalpro username exactly
```

---

## Reboot Checklist

```bash
# 1. FreeRADIUS log still readable?
sudo -u www-data cat /var/log/freeradius/radius.log | tail -2

# 2. Credentials file still there?
ls -la /zalpro-optimization/credentials/db_config.php

# 3. Test userlog
curl "http://103.111.39.174/userlog.php?username=juniper" | grep -c "<tr>"
# Should return number > 0 if user has logs
```

---

## Directory Structure Summary

```
/var/www/html/
└── userlog.php                          ← Main log viewer (web accessible)

/zalpro-optimization/
├── auth-live-log.php                    ← Profile widget
└── credentials/
    └── db_config.php                    ← MySQL credentials (protected)

/var/www/html/application/views/themes/legacy/admin_portal/users/
├── profile.php                          ← Modified: includes auth-live-log.php
└── all.php                              ← Modified: "Live Logs" button added

/var/log/freeradius/
└── radius.log                           ← FreeRADIUS log (needs 644 perms)
```

---

*Last updated: July 2026*

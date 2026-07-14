# USERAUTH — Complete Setup & Configuration Guide
**NetPoint ISP | Zalpro + FreeRADIUS Authentication Log System**  
**Maintained by: Hanzilah Waheed**

---

## What Is This System?

This system displays **live authentication + disconnect logs** inside Zalpro:

- **User Profile Page** → shows logs for ONE specific user (Login OK / Rejected / Disconnect)
- **All Users Page** → "Live Logs" button → opens dashboard for ALL users

**Data comes from two sources:**
```
userlog.php
├── /var/log/freeradius/radius.log   → Login OK, Rejected events
└── MySQL: radacct table             → Disconnect events (with cause + IP)
```

---

## ⚠️ IP/Credentials Reference — Change These First!

> Every time you set up on a **new server**, update these values BEFORE doing anything else.

| What | Current Value | File to Change | Exact Line |
|------|--------------|----------------|------------|
| Zalpro Public IP (WebSocket) | `103.111.39.174` | `traffic-chart.php` | `const WS_URL = "ws://103.111.39.174:8081..."` |
| MySQL Host | `localhost` | `userlog.php` | `$db_host = 'localhost';` |
| MySQL DB Name | `zalpro` | `userlog.php` | `$db_name = 'zalpro';` |
| MySQL Username | `root` | `db_config.php` | `define('DB_USER', 'root');` |
| MySQL Password | *(your password)* | `db_config.php` | `define('DB_PASS', 'YOUR_PASSWORD');` |
| FreeRADIUS Log Path | `/var/log/freeradius/radius.log` | `userlog.php` | `$log_path = '/var/log/freeradius/radius.log';` |
| Credentials File Path | `/zalpro-optimization/credentials/db_config.php` | `userlog.php` | `$config_path = '/zalpro-optimization/credentials/db_config.php';` |

---

## Files Overview

### Files You Need to Upload via WinSCP

```
WinSCP Upload Map:
─────────────────────────────────────────────────────────
Local File            →   Server Destination
─────────────────────────────────────────────────────────
userlog.php           →   /var/www/html/userlog.php
auth-live-log.php     →   /zalpro-optimization/auth-live-log.php
─────────────────────────────────────────────────────────

NOTE: db_config.php is created manually on server (see Step 3)
      Do NOT upload it via WinSCP to avoid accidental exposure
```

### What Each File Does

| File | Location | Purpose |
|------|----------|---------|
| `userlog.php` | `/var/www/html/` | Main log viewer. Reads radius.log + radacct DB, merges and displays table |
| `auth-live-log.php` | `/zalpro-optimization/` | Profile page widget. Embeds userlog.php in an iframe for specific user |
| `db_config.php` | `/zalpro-optimization/credentials/` | MySQL credentials. Created manually on server |

---

## Complete Directory Structure

```
/var/www/html/
└── userlog.php                    ← Web accessible log viewer

/zalpro-optimization/
├── auth-live-log.php              ← Profile widget (included by profile.php)
└── credentials/
    └── db_config.php              ← MySQL credentials (protected, 600 perms)

/var/www/html/application/views/themes/legacy/admin_portal/users/
├── profile.php                    ← Modified: add auth log widget here
└── all.php                        ← Modified: add "Live Logs" button here

/var/log/freeradius/
└── radius.log                     ← FreeRADIUS log (needs 644 permissions)
```

---

## Step-by-Step Setup

### Step 1: Create Main Directory

```bash
# Create directories
sudo mkdir -p /zalpro-optimization
sudo mkdir -p /zalpro-optimization/credentials

# Set ownership
sudo chown -R www-data:www-data /zalpro-optimization
sudo chmod -R 755 /zalpro-optimization
```

---

### Step 2: Upload Files via WinSCP

Connect to Zalpro server in WinSCP:
- **Host:** `103.111.39.174` (or private `172.17.21.2`)  ← Change if IP changes
- **Port:** `22`
- **User:** `root`

Upload files:
```
userlog.php       →   /var/www/html/userlog.php
auth-live-log.php →   /zalpro-optimization/auth-live-log.php
```

Set permissions after upload:
```bash
sudo chown www-data:www-data /var/www/html/userlog.php
sudo chmod 644 /var/www/html/userlog.php

sudo chown www-data:www-data /zalpro-optimization/auth-live-log.php
sudo chmod 644 /zalpro-optimization/auth-live-log.php
```

---

### Step 3: Create MySQL Credentials File

> ⚠️ Do this manually on the server — do NOT upload via WinSCP

```bash
sudo nano /zalpro-optimization/credentials/db_config.php
```

Paste this content (change password!):
```php
<?php
// MySQL Credentials for Zalpro Log System
// Addition By hanxill
// ← Change DB_USER if your MySQL user is different
// ← Change DB_PASS to your actual MySQL root password

define('DB_USER', 'root');
define('DB_PASS', 'YOUR_MYSQL_PASSWORD_HERE');
```

Save: `CTRL+O` → `ENTER` → `CTRL+X`

Set secure permissions:
```bash
sudo chmod 600 /zalpro-optimization/credentials/db_config.php
sudo chown www-data:www-data /zalpro-optimization/credentials/db_config.php
```

Test credentials work:
```bash
sudo -u www-data php -r "
require '/zalpro-optimization/credentials/db_config.php';
echo 'User: ' . DB_USER . PHP_EOL;
echo 'Connected OK' . PHP_EOL;
"
# Expected output:
# User: root
# Connected OK
```

---

### Step 4: Enable FreeRADIUS Authentication Logging

By default, FreeRADIUS may not log auth events. Enable it:

```bash
sudo nano /etc/freeradius/3.0/radiusd.conf
```

Find the `log { }` section and make sure these are set to `yes`:
```
log {
    auth = yes           # ← Must be yes (logs Login OK / Rejected)
    auth_badpass = yes   # ← Logs failed password attempts
    auth_goodpass = yes  # ← Logs successful logins
}
```

Save: `CTRL+O` → `ENTER` → `CTRL+X`

Restart FreeRADIUS:
```bash
sudo systemctl restart freeradius
sudo systemctl status freeradius
# Should show: active (running)
```

Verify logging is working:
```bash
# Have a user connect, then check
tail -20 /var/log/freeradius/radius.log
# Should see lines like:
# Mon Jul 13 01:39:11 2026 : Auth: (250) Login OK: [juniper/juniper] ...
```

---

### Step 5: Fix FreeRADIUS Log Permissions

Apache (`www-data`) needs to read the FreeRADIUS log:

```bash
# Add www-data to freerad group
sudo usermod -aG freerad www-data

# Fix permissions
sudo chmod 644 /var/log/freeradius/radius.log
sudo chmod 755 /var/log/freeradius/

# Restart Apache to apply group membership
sudo systemctl restart apache2

# Test that www-data can now read log
sudo -u www-data cat /var/log/freeradius/radius.log | tail -3
# Expected: actual log lines (NOT "Permission denied")
```

Make permissions survive log rotation:
```bash
sudo nano /etc/logrotate.d/freeradius
```

Find the first `{ }` block for `radius.log` and add `create 644 freerad freerad`:
```
/var/log/freeradius/radius.log {
    daily
    rotate 52
    missingok
    compress
    delaycompress
    notifempty
    copytruncate
    create 644 freerad freerad    # ← Add this line
}
```

Make permissions survive reboot:
```bash
(crontab -l 2>/dev/null; echo "@reboot chmod 644 /var/log/freeradius/radius.log && chmod 755 /var/log/freeradius/") | crontab -

# Verify crontab
crontab -l | grep freeradius
```

---

### Step 6: Add "Live Logs" Button to All Users Page

File to edit:
```
/var/www/html/application/views/themes/legacy/admin_portal/users/all.php
```

First, find the exact line number of `checkAdminOrStaff`:
```bash
grep -n "checkAdminOrStaff\|export-user" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/all.php | head -5
```

Open the file:
```bash
sudo nano /var/www/html/application/views/themes/legacy/admin_portal/users/all.php
```

Use `CTRL+W` to search for: `checkAdminOrStaff`

You will find existing code like:
```php
if (checkAdminOrStaff()) {
    echo '... <button ... >Export Users</button>...';
}
```

Add these lines **immediately after the closing `}` of that block:**
```php
# Addition By hanxill
#--------------------------------------------------------------------------
#-----------------------------All-User-Auth-Log----------------------------
echo '                            <h2 class="right"><a href="/userlog.php?username=all&filter=all" target="_blank"><button class="btn btn-zalpro text-white"><i class="fas fa-terminal"></i> Live Logs</button></a></h2>' . "\n" . '                        ';
#--------------------------------------------------------------------------
```

Save: `CTRL+O` → `ENTER` → `CTRL+X`

Verify:
```bash
grep -n "Live Logs\|userlog" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/all.php
# Should show the line you added
```

---

### Step 7: Add Auth Log Widget to User Profile Page

File to edit:
```
/var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

Check if already added:
```bash
grep -n "auth-live-log" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

If NOT there, find where `profileDocument.php` is included:
```bash
grep -n "profileDocument" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

Open file and search for `profileDocument.php`:
```bash
sudo nano /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
# CTRL+W → type profileDocument.php → ENTER
```

Add these lines **immediately below** the `profileDocument.php` include line:
```php
# Addition By hanxill
# ------------------- AUTH LIVE LOG -----------------------------
echo '<div class="row">';
include '/zalpro-optimization/auth-live-log.php';
echo '</div>';
# -------------------------------------------------------------
```

Save: `CTRL+O` → `ENTER` → `CTRL+X`

Verify:
```bash
grep -n "auth-live-log" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
# Should show the include line
```

---

### Step 8: Fix SQL Error (If Needed)

If you see this error anywhere in Zalpro:
```
Unknown column 'disable_pool' in 'field list'
```

Fix it:
```bash
mysql -u root -p
```
```sql
USE zalpro;

ALTER TABLE packages
ADD COLUMN disable_pool TINYINT(1) DEFAULT 0;

exit;
```

Then restart FreeRADIUS:
```bash
sudo systemctl restart freeradius
```

---

### Step 9: Test Everything

```bash
# Test 1: Credentials file works
sudo -u www-data php -r "require '/zalpro-optimization/credentials/db_config.php'; echo DB_USER;"
# Expected: root

# Test 2: FreeRADIUS log readable
sudo -u www-data cat /var/log/freeradius/radius.log | tail -3
# Expected: log lines

# Test 3: userlog.php responding for specific user
curl "http://103.111.39.174/userlog.php?username=juniper" | grep -c "Login\|Reject\|Disconnect"
# Expected: number > 0

# Test 4: All users log dashboard
curl "http://103.111.39.174/userlog.php?username=all" | grep "LIVE ALL"
# Expected: LIVE ALL SUBSCRIBERS DASHBOARD

# Test 5: Disconnect events in DB
mysql -u root -p zalpro -e "
SELECT username, acctstoptime, acctterminatecause
FROM radacct
WHERE acctstoptime IS NOT NULL
ORDER BY acctstoptime DESC
LIMIT 5;"
# Expected: rows with disconnect data

# Test 6: Open Zalpro → All Users page → "Live Logs" button visible?
# Test 7: Open Zalpro → User profile → Auth log widget showing?
```

---

## Features of userlog.php

| Feature | Details |
|---------|---------|
| Auto-refresh | Every 5 seconds (reloads page automatically) |
| Freeze button | Click to stop auto-refresh; click again to resume |
| Row highlighting | Click any row to highlight it (yellow). Persists across refreshes via localStorage |
| Mode: specific user | `?username=john` — shows only john's logs |
| Mode: all users | `?username=all` — shows all users' logs with full dashboard UI |
| Event badges | Login OK (green), Rejected (red), Disconnect (red), Info (dark) |
| Log limit | Shows latest 1000 entries, sorted newest-first |

---

## Event Types & Badge Colors

| Badge | Color | Data Source | When It Appears |
|-------|-------|-------------|-----------------|
| **Login OK** | 🟢 Green `#26B99A` | radius.log | User authenticated successfully |
| **Rejected** | 🔴 Red `#d9534f` | radius.log | Wrong password / auth failed |
| **Disconnect** | 🔴 Red `#d9534f` | radacct table | PPPoE session ended |
| **Info** | ⚫ Dark `#34495E` | radius.log | Other RADIUS events |

---

## Common Issues & Fixes

### "Configuration file missing"
**Cause:** `db_config.php` not created  
**Fix:** Follow Step 3

```bash
# Verify file exists
ls -la /zalpro-optimization/credentials/db_config.php
```

---

### "Log file path not readable by web server"
**Cause:** www-data cannot read `radius.log`  
**Fix:**
```bash
sudo usermod -aG freerad www-data
sudo chmod 644 /var/log/freeradius/radius.log
sudo systemctl restart apache2

# Test
sudo -u www-data cat /var/log/freeradius/radius.log | tail -3
```

---

### No logs appearing at all
**Cause:** FreeRADIUS auth logging not enabled  
**Fix:**
```bash
grep -A5 "^log {" /etc/freeradius/3.0/radiusd.conf | grep "auth"
# Should show: auth = yes

# If not, edit and set auth = yes
sudo nano /etc/freeradius/3.0/radiusd.conf
sudo systemctl restart freeradius
```

---

### Disconnect events not showing
**Cause 1:** radacct table empty  
**Cause 2:** MySQL credentials wrong in db_config.php  
**Fix:**
```bash
# Check disconnect data exists
mysql -u root -p zalpro -e "SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NOT NULL;"

# Check credentials file
sudo -u www-data php -r "
require '/zalpro-optimization/credentials/db_config.php';
\$conn = new mysqli('localhost', DB_USER, DB_PASS, 'zalpro');
echo \$conn->connect_error ? 'FAIL: '.\$conn->connect_error : 'DB OK';
"
```

---

### "Live Logs" button not showing in All Users page
**Cause:** Code not added to `all.php`  
**Fix:** Follow Step 6 again

```bash
# Verify code is there
grep -n "Live Logs" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/all.php
```

---

### Auth log widget not showing on User Profile
**Cause:** Code not added to `profile.php`  
**Fix:** Follow Step 7 again

```bash
# Verify code is there
grep -n "auth-live-log" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

---

### "Unknown column 'disable_pool'"
**Fix:** Follow Step 8 (SQL fix)

---

## Reboot Checklist

After every server reboot:

```bash
# 1. FreeRADIUS log still readable?
sudo -u www-data cat /var/log/freeradius/radius.log | tail -2

# 2. FreeRADIUS running?
sudo systemctl status freeradius | grep Active

# 3. Apache running?
sudo systemctl status apache2 | grep Active

# 4. Test userlog
curl -s "http://103.111.39.174/userlog.php?username=all" | grep -c "Login\|Disconnect"
```

---

## Installation Checklist

```
[ ] Step 1: Main directory created (/zalpro-optimization)
[ ] Step 2: Files uploaded via WinSCP (userlog.php + auth-live-log.php)
[ ] Step 3: db_config.php created with correct MySQL password
[ ] Step 4: FreeRADIUS auth logging enabled (auth = yes in radiusd.conf)
[ ] Step 5: Log file permissions fixed (www-data can read radius.log)
[ ] Step 6: "Live Logs" button added to all.php
[ ] Step 7: Auth log widget added to profile.php
[ ] Step 8: SQL disable_pool fix applied (if needed)
[ ] Step 9: All tests passing
```

---

*Last updated: July 2026*

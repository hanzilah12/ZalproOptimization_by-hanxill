# Live Traffic Monitor — Complete Setup Guide
**NetPoint ISP | Zalpro + Juniper MX104 BNG**  
**Maintained by: Hanzilah Waheed**

---

## Quick Reference — Change These If IPs/Credentials Change

| What | Current Value | Where to Change |
|------|--------------|-----------------|
| Zalpro Public IP | `103.111.39.174` | `monitor_server.py`, `traffic-chart.php` (WS_URL) |
| Zalpro Private IP | `172.17.21.2` | `/etc/netplan/00-installer-config.yaml` |
| Zalpro Gateway | `172.17.21.1` | `/etc/netplan/00-installer-config.yaml` |
| Juniper BNG IP | `103.170.179.40` | `get_live_traffic.php`, `get_user_interface.php` |
| Juniper SSH Port | `8877` | `get_user_interface.php` |
| Juniper REST API Port | `8080` | `get_live_traffic.php` |
| Juniper REST API User | `zalpro-api` | `get_live_traffic.php`, `get_user_interface.php` |
| Juniper REST API Pass | `zalpro123` | `get_live_traffic.php` |
| SSH Key Path | `/var/lib/zalpro/.ssh/juniper_key` | `get_user_interface.php` |
| WebSocket Port | `8081` | `monitor_server.py`, `traffic-chart.php` |
| RADIUS Secret | `11223344` | FreeRADIUS clients.conf, NAS table |

---

## Files Overview

Copy these 4 files via WinSCP to `/zalpro-optimization/new-user-traffic/` on Zalpro server:

```
/zalpro-optimization/new-user-traffic/
├── traffic-chart.php       ← Frontend UI (chart + WebSocket client)
├── monitor_server.py       ← WebSocket server (Python, runs on port 8081)
├── get_user_interface.php  ← SSH to Juniper: finds pp0.XXXXX for a user
└── get_live_traffic.php    ← REST API: gets bytes from Juniper interface
```

---

## File-by-File: What to Change

### 1. `get_live_traffic.php`
```php
$router_ip = "103.170.179.40";   // ← Change: Juniper BNG IP
$api_user  = "zalpro-api";       // ← Change: Juniper REST API username
$api_pass  = "zalpro123";        // ← Change: Juniper REST API password
$api_port  = "8080";             // ← Change: Juniper REST API port (check with: show configuration system services rest)
```

### 2. `get_user_interface.php`
```php
$router_ip   = "103.170.179.40";                     // ← Change: Juniper BNG IP
$router_user = "zalpro-api";                          // ← Change: SSH user on Juniper
$router_port = "8877";                                // ← Change: Juniper SSH port
$ssh_key     = "/var/lib/zalpro/.ssh/juniper_key";   // ← Change: SSH private key path
```

### 3. `monitor_server.py`
```python
BASE_URL = "http://103.111.39.174"  # ← Change: Zalpro public IP
```
WebSocket binds on port `8081` — change in `websockets.serve(handler, "0.0.0.0", 8081)` if needed.

### 4. `traffic-chart.php`
```javascript
const WS_URL = "ws://103.111.39.174:8081?username=" + ...
//                  ↑ Change: Zalpro public IP (port 8081 must match monitor_server.py)
```

---

## Step-by-Step Setup

### Step 1: Upload Files via WinSCP

Connect to Zalpro server via WinSCP (use private IP `172.17.21.2` or public `103.111.39.174`).

Upload all 4 files to: `/zalpro-optimization/new-user-traffic/`

---

### Step 2: Create Directory & Set Permissions

```bash
# Create directories
sudo mkdir -p /zalpro-optimization/new-user-traffic
sudo mkdir -p /var/lib/zalpro/.ssh

# Set ownership
sudo chown -R www-data:www-data /zalpro-optimization
sudo chmod -R 755 /zalpro-optimization
```

---

### Step 3: Generate SSH Key (Zalpro → Juniper)

```bash
# Generate new ED25519 key
sudo ssh-keygen -t ed25519 \
    -f /var/lib/zalpro/.ssh/juniper_key \
    -N "" \
    -C "zalpro-api@zalpro"

# IMPORTANT: private key must be 600, not 644!
sudo chmod 600 /var/lib/zalpro/.ssh/juniper_key
sudo chmod 755 /var/lib/zalpro/.ssh
sudo chown -R www-data:www-data /var/lib/zalpro/.ssh

# Show the public key (you need to add this to Juniper)
cat /var/lib/zalpro/.ssh/juniper_key.pub
```

---

### Step 4: Add SSH Public Key to Juniper

SSH into Juniper as admin user:
```bash
ssh -p 8877 hanxill@103.170.179.40
```

Then run:
```
configure

set system login user zalpro-api authentication ssh-ed25519 "PASTE_PUBLIC_KEY_HERE"

commit
exit
```

> **Note:** Replace `PASTE_PUBLIC_KEY_HERE` with the full line from `juniper_key.pub`  
> Example: `ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIK70K+... zalpro-api@zalpro`

**Test SSH key (should work WITHOUT password):**
```bash
sudo -u www-data ssh -i /var/lib/zalpro/.ssh/juniper_key \
    -o StrictHostKeyChecking=no \
    -p 8877 zalpro-api@103.170.179.40 \
    "show subscribers"
```

If it asks for password → key not added correctly to Juniper. Repeat Step 4.

---

### Step 5: Allow Zalpro IP in Juniper REST API

On Juniper:
```bash
ssh -p 8877 hanxill@103.170.179.40
configure

# Check current allowed sources
show configuration system services rest

# Add new Zalpro IP (change to your IP)
set system services rest control allowed-sources 103.111.39.174

# Remove old IP if needed
delete system services rest control allowed-sources OLD_IP

commit
exit
```

**Test REST API from Zalpro:**
```bash
# Find an active interface first
sudo -u www-data ssh -i /var/lib/zalpro/.ssh/juniper_key \
    -o StrictHostKeyChecking=no -p 8877 \
    zalpro-api@103.170.179.40 "show subscribers"

# Then test REST API (replace pp0.XXXXX with actual interface)
curl -u zalpro-api:zalpro123 \
    "http://103.170.179.40:8080/rpc/get-interface-information?interface-name=pp0.XXXXX&extensive=" | head -10
```

Should return XML with `<traffic-statistics>` data.

---

### Step 6: Create Symlinks in Webroot

```bash
sudo ln -sf /zalpro-optimization/new-user-traffic/get_live_traffic.php \
    /var/www/html/get_live_traffic.php

sudo ln -sf /zalpro-optimization/new-user-traffic/get_user_interface.php \
    /var/www/html/get_user_interface.php

# Verify
ls -la /var/www/html/get_live_traffic.php
ls -la /var/www/html/get_user_interface.php
```

---

### Step 7: Install Python Dependencies

```bash
# Install pip if missing
sudo apt install python3-pip -y

# Install required packages
pip3 install websockets requests
```

---

### Step 8: Start WebSocket Server

```bash
# Start in background
nohup python3 /zalpro-optimization/new-user-traffic/monitor_server.py \
    > /zalpro-optimization/monitor_server.log 2>&1 &

# Verify running
ps aux | grep monitor_server.py | grep -v grep

# Check logs
cat /zalpro-optimization/monitor_server.log

# Check port listening
netstat -tlnp | grep 8081
```

---

### Step 9: Auto-Start WebSocket on Reboot

```bash
# Add to crontab
(crontab -l 2>/dev/null; echo "@reboot nohup python3 /zalpro-optimization/new-user-traffic/monitor_server.py > /zalpro-optimization/monitor_server.log 2>&1 &") | crontab -

# Verify (should appear once)
crontab -l | grep monitor_server
```

---

### Step 10: Add to Zalpro User Profile Page

```bash
# Find the profile.php file
ls /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php

# Check if already added
grep -n "traffic-chart" /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

If NOT already there, find where to add it:
```bash
grep -n "profileDocument\|auth-live-log" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

Then add after the auth-live-log section:
```bash
# Find the line number of the closing comment after auth-live-log
grep -n "# -------------" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php

# Add traffic chart (replace LINE_NUMBER with actual line number)
sed -i 'LINE_NUMBERa include '"'"'/zalpro-optimization/new-user-traffic/traffic-chart.php'"'"';' \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php

# Verify
grep -n "traffic-chart" \
    /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

---

### Step 11: Test Everything

```bash
# Test 1: Interface detection API
curl "http://103.111.39.174/get_user_interface.php?username=YOUR_TEST_USER"
# Expected: {"status":"success","interface":"pp0.XXXXX","username":"YOUR_TEST_USER"}

# Test 2: Live traffic API
curl "http://103.111.39.174/get_live_traffic.php?interface=pp0.XXXXX"
# Expected: {"interface":"pp0.XXXXX","input_bytes":12345,"output_bytes":67890,"status":"success",...}

# Test 3: WebSocket running
ps aux | grep monitor_server | grep -v grep
# Expected: python3 process listed

# Test 4: Open Zalpro → User Profile → See Live Traffic Monitor panel
```

---

## Juniper Commands Reference

### Check REST API Configuration
```
show configuration system services rest
```
Should show:
```
http {
    port 8080;
    addresses 103.170.179.40;
}
control {
    allowed-sources 103.111.39.174;
    connection-limit 10;
}
```

### Check Active Subscribers
```
show subscribers
```

### Check Interface Traffic
```
show interfaces pp0.XXXXX extensive | match bytes
```

### Check zalpro-api User Config
```
show configuration system login user zalpro-api
```
Should show the `ssh-ed25519` key.

### Add Zalpro IP to REST API (if new IP)
```
configure
set system services rest control allowed-sources NEW_ZALPRO_IP
delete system services rest control allowed-sources OLD_ZALPRO_IP
commit
```

### Add SSH Key to Juniper (if new key generated)
```
configure
set system login user zalpro-api authentication ssh-ed25519 "ssh-ed25519 AAAA... zalpro-api@zalpro"
commit
```

---

## Netplan Configuration (if IP changes)

File: `/etc/netplan/00-installer-config.yaml`

```yaml
network:
  version: 2
  ethernets:
    ens160:
      addresses:
        - 172.17.21.2/24          # ← Private IP
        - 103.111.39.174/32       # ← Public IP
      gateway4: 172.17.21.1       # ← Gateway
      nameservers:
        addresses:
          - 8.8.8.8
          - 8.8.4.4
      routes:
        - to: 0.0.0.0/0
          via: 172.17.21.1        # ← Gateway
          table: 100
        - to: 103.170.179.40/32   # ← Juniper BNG IP (for CoA/disconnect routing)
          via: 172.17.21.1        # ← Gateway
      routing-policy:
        - from: 103.111.39.174    # ← Zalpro public IP
          table: 100
```

Apply changes:
```bash
sudo netplan apply
ip route show | grep 103.170.179.40   # Verify route exists
```

> **Why the route to Juniper?** When Zalpro sends disconnect/CoA requests to Juniper (port 3799),
> it must come from the correct source IP (`103.111.39.174`). This route ensures traffic
> to Juniper goes via the correct interface with the right source IP.

---

## Common Issues & Fixes

### "REST API failed: HTTP 0 - Failed to connect"
**Cause:** Wrong port or IP not in Juniper's allowed-sources  
**Fix:**
```bash
# Check which port actually works
curl -u zalpro-api:zalpro123 "http://103.170.179.40:8080/rpc/get-interface-information?interface-name=pp0.XXXXX&extensive=" | head -3
curl -u zalpro-api:zalpro123 "http://103.170.179.40:3000/rpc/get-interface-information?interface-name=pp0.XXXXX&extensive=" | head -3

# On Juniper, check actual port
show configuration system services rest
```

### "SSH connection failed" / "Permission denied"
**Cause:** SSH key not added to Juniper OR wrong key path  
**Fix:**
```bash
# Test manually
sudo -u www-data ssh -i /var/lib/zalpro/.ssh/juniper_key \
    -o StrictHostKeyChecking=no -p 8877 \
    zalpro-api@103.170.179.40 "show subscribers"

# Check key permissions (must be 600)
ls -la /var/lib/zalpro/.ssh/juniper_key

# Fix permissions if needed
sudo chmod 600 /var/lib/zalpro/.ssh/juniper_key
```

### "WebSocket Connecting..." forever / "Connection Lost"
**Cause:** monitor_server.py not running  
**Fix:**
```bash
# Check if running
ps aux | grep monitor_server | grep -v grep

# Start if not running
nohup python3 /zalpro-optimization/new-user-traffic/monitor_server.py \
    > /zalpro-optimization/monitor_server.log 2>&1 &

# Check logs for errors
cat /zalpro-optimization/monitor_server.log
```

### "User Offline" even though user is connected
**Cause:** Wrong username OR SSH key issue  
**Fix:**
```bash
# Check what Juniper shows
sudo -u www-data ssh -i /var/lib/zalpro/.ssh/juniper_key \
    -o StrictHostKeyChecking=no -p 8877 \
    zalpro-api@103.170.179.40 "show subscribers"

# Test interface API with exact username from Juniper output
curl "http://103.111.39.174/get_user_interface.php?username=EXACT_USERNAME"
```

### "Failed To Disconnect! Manual Disconnect Action Required"
**Cause:** Route to Juniper not via correct source IP  
**Fix:**
```bash
# Verify route exists
ip route show | grep 103.170.179.40

# Add if missing (temporary)
sudo ip route add 103.170.179.40/32 via 172.17.21.1

# Make permanent: add to netplan (see Netplan section above)
```

### WebSocket not auto-starting after reboot
**Fix:**
```bash
# Add to crontab
crontab -e
# Add this line:
@reboot nohup python3 /zalpro-optimization/new-user-traffic/monitor_server.py > /zalpro-optimization/monitor_server.log 2>&1 &
```

---

## Reboot Checklist

After every Zalpro server reboot, verify:

```bash
# 1. WebSocket server running?
ps aux | grep monitor_server | grep -v grep

# 2. Port 8081 listening?
netstat -tlnp | grep 8081

# 3. Route to Juniper exists?
ip route show | grep 103.170.179.40

# 4. SSH key still works?
sudo -u www-data ssh -i /var/lib/zalpro/.ssh/juniper_key \
    -o StrictHostKeyChecking=no -p 8877 \
    zalpro-api@103.170.179.40 "show subscribers" | head -5

# 5. APIs responding?
curl "http://103.111.39.174/get_user_interface.php?username=TEST_USER"
```

---

## How It Works (Architecture)

```
Browser (Admin viewing user profile)
    │
    │  WebSocket ws://103.111.39.174:8081?username=john
    ▼
monitor_server.py  (Python, port 8081)
    │
    ├─── HTTP GET /get_user_interface.php?username=john
    │         │
    │         └── SSH to Juniper (port 8877, key auth)
    │              "show subscribers"
    │              → finds pp0.3221225475 for user "john"
    │
    └─── HTTP GET /get_live_traffic.php?interface=pp0.3221225475
              │
              └── REST API to Juniper (port 8080, HTTP Basic Auth)
                   GET /rpc/get-interface-information?interface-name=pp0.3221225475&extensive=
                   → returns input_bytes, output_bytes

Browser JS calculates:
    Mbps = ((bytes_delta × 8) / 1,000,000) / time_diff_seconds
    Auto-scales: bps → Kbps → Mbps
```

---

*Last updated: July 2026*

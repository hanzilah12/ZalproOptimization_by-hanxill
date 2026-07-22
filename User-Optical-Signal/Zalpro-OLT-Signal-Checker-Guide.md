# Zalpro Multi-OLT Signal Checker — Integration Guide

## 📌 Project Overview

This project integrates a **live optical signal checker** into the Zalpro Admin Dashboard.

**How it works:**
1. Admin opens a user's profile page and clicks **"Check Live Signal"**.
2. The frontend widget calls a PHP endpoint with the Zalpro `username`.
3. The PHP endpoint runs a Python script in the background.
4. The Python script:
   - Looks up the user's active MAC address from the Zalpro RADIUS database (`radpostauth` table).
   - SSHes into the configured OLTs (Huawei and/or VSOL).
   - Locates the ONU by MAC and pulls live Rx/Tx optical power readings.
5. The result is returned as JSON and rendered live on the user's profile page.

**Supported OLT types:** Huawei (GPON), VSOL (GPON)

---

## ⚠️ Security Note (read before deploying)

The scripts use **hardcoded credentials** (DB user/pass, OLT SSH user/pass) directly in `olt_checker.py`. Keep this in mind:

- Set file permissions tightly: `chmod 750 /opt/olt_checker.py` and restrict ownership to `root:www-data` or similar — do **not** leave it world-readable.
- Never commit this file to a public/shared Git repo with real passwords in it.
- `get_olt_signal.php` builds a shell command from a GET parameter. It currently validates `username` with a regex (`^[a-zA-Z0-9_\-]+$`) before passing it to `escapeshellarg()` — **keep this validation in place**; do not relax it, since this endpoint executes a shell command.
- Consider moving DB/OLT credentials into a separate config file outside the web root, or into environment variables, instead of leaving them inline in the script.

---

## ✅ Prerequisites

Run on the server before uploading any files:

```bash
apt update
apt install python3 python3-pip -y
pip3 install paramiko pymysql
```

If you hit build errors on some systems, also install:

```bash
apt-get install -y build-essential libssl-dev libffi-dev python3-dev
```

---

## 🗂️ File Map

| Step | File | Target Directory (WinSCP) | Purpose |
|------|------|---------------------------|---------|
| 1 | `olt_checker.py` | `/opt/` | Python backend — DB lookup + SSH to OLTs |
| 2 | `get_olt_signal.php` | `/var/www/html/` | PHP bridge between frontend and Python |
| 3 | `olt-signal.php` | `/zalpro-optimization/` | Frontend widget (HTML/JS/UI box) |
| 4 | `profile.php` (edit) | `/var/www/html/application/views/themes/legacy/admin_portal/users/` | Include point for the widget |

---

## Step 1 — Python Backend (`/opt/olt_checker.py`)

Create the file and paste your script contents. Key things to configure at the top of the file:

```python
OLT_LIST = [
    {
        "name": "Huawei-Main",
        "type": "huawei",
        "ip": "<OLT_IP>",
        "user": "<OLT_SSH_USER>",
        "pass": "<OLT_SSH_PASS>"
    },
    {
        "name": "VSOL-KTA",
        "type": "vsol",
        "ip": "<OLT_IP>",
        "user": "<OLT_SSH_USER>",
        "pass": "<OLT_SSH_PASS>",
        "enable_pass": "<OLT_ENABLE_PASS>"
    }
]

DB_HOST = "localhost"
DB_USER = "<DB_USER>"
DB_PASS = "<DB_PASS>"
DB_NAME = "zalpro"
```

**What it does:**
- `get_mac_from_zalpro(username)` — queries `radpostauth` for the most recent MAC tied to a username.
- `query_huawei_olt()` / `query_vsol_olt()` — SSH into the matching OLT type, find the ONT by MAC, then pull optical readings (Rx ONT, Tx ONT, Rx OLT in dBm).
- `find_mac_across_olts()` — loops through `OLT_LIST` until a match is found.
- CLI usage:
  ```bash
  python3 /opt/olt_checker.py --user <username> --json
  python3 /opt/olt_checker.py --mac <mac_address> --json
  ```

**After uploading:**

```bash
chmod 755 /opt/olt_checker.py
```

> Tip: Since this file contains credentials, `chmod 750` with owner `root` (or the web server user only) is safer than `755` if other local users have shell access.

---

## Step 2 — PHP Helper Endpoint (`/var/www/html/get_olt_signal.php`)

Bridges the web frontend to the Python backend:

- Accepts `?user=<username>` via `GET`.
- Validates the username against `^[a-zA-Z0-9_\-]+$` before use — **do not remove this check**.
- Runs:
  ```php
  $cmd = "python3 /opt/olt_checker.py --user " . escapeshellarg($user) . " --json 2>&1";
  ```
- Returns the JSON output directly to the browser.

---

## Step 3 — Frontend Widget (`/zalpro-optimization/olt-signal.php`)

- Renders the **"Live OLT Optical Power"** box on the profile page.
- "Check Live Signal" button triggers `fetchOltSignal(username)`.
- Calls `/get_olt_signal.php?user=<username>` via `fetch()`.
- Color-codes the result based on Rx power:
  | Rx Power | Status | Color |
  |----------|--------|-------|
  | N/A or NaN | Device Offline / No Signal | Gray |
  | < -27.0 dBm | Critical Signal | Red |
  | -27.0 to -24.0 dBm | High Attenuation | Orange |
  | ≥ -24.0 dBm | Good Signal | Green |
- Displays: PON Location, ONU Rx Power, ONU Tx Power, OLT Rx Power.

---

## Step 4 — Include Widget in Zalpro Profile

Edit:
```
/var/www/html/application/views/themes/legacy/admin_portal/users/profile.php
```

Find where you want the box to appear, then add:

```php
# ------------------- LIVE OLT SIGNAL CHECKER -------------------
echo '<div class="row">';
include '/zalpro-optimization/olt-signal.php';
echo '</div>';
# ---------------------------------------------------------------
```

---

## 🧪 Verification / Testing Flow

1. **Test DB connectivity + MAC lookup:**
   ```bash
   mysql -u root -p -e "SELECT mac FROM zalpro.radpostauth WHERE username='<username>' ORDER BY id DESC LIMIT 1;"
   ```
2. **Test Python CLI directly:**
   ```bash
   python3 /opt/olt_checker.py --user <username>
   python3 /opt/olt_checker.py --user <username> --json
   ```
3. **Test PHP bridge directly (browser or curl):**
   ```
   https://<server>/get_olt_signal.php?user=<username>
   ```
4. **Test full flow:** Open the user's profile page → click "Check Live Signal" → confirm the widget populates.

---

## 🔧 Troubleshooting

| Symptom | Check |
|--------|-------|
| Widget shows "Failed to fetch signal from backend" | Confirm `get_olt_signal.php` is reachable and returns valid JSON |
| `"User MAC not found in DB"` | Run the `radpostauth` query manually for that username |
| `"MAC ... not found on any active OLT"` | Confirm the OLT SSH IP/credentials in `OLT_LIST`; verify the ONT is actually online |
| Python import errors | Re-run `pip3 install paramiko pymysql` |
| Permission denied running script | `chmod 755 /opt/olt_checker.py` (or `750` — see security note above) |
| SSH connects but no MAC match | Huawei/VSOL CLI output format may differ by firmware version — check the regex patterns in `query_huawei_olt` / `query_vsol_olt` match your device's actual command output |

**Quick diagnostic command:**
```bash
python3 /opt/olt_checker.py --user <username>
```

---

## 📋 Deployment Checklist

- [ ] Python + pip libraries installed (`paramiko`, `pymysql`)
- [ ] `olt_checker.py` uploaded to `/opt/`, credentials filled in, permissions locked down
- [ ] `get_olt_signal.php` uploaded to `/var/www/html/`
- [ ] `olt-signal.php` uploaded to `/zalpro-optimization/`
- [ ] Include block added to `profile.php`
- [ ] CLI test passes for a known username
- [ ] PHP endpoint test passes via browser/curl
- [ ] Widget renders and refreshes correctly on profile page

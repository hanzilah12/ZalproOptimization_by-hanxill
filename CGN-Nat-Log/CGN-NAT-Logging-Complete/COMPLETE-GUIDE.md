# CGN NAT Lawful-Intercept Logging — Complete Deployment Guide

**Project:** NetPoint-BNG-KTA (Juniper MX104) → Zalpro server
**Goal:** Record username + private IP:port + destination IP:port for
every subscriber's NAT'd traffic, for PTA / law-enforcement-agency
compliance — in a format matching the legacy DMA-RADIUS/Mikrotik
`conntrack` table style.

This guide covers the full pipeline end-to-end: Juniper MX config →
network delivery → RADIUS username correlation → MySQL storage →
dedicated disk for that storage. A new engineer should be able to
follow this top to bottom and deploy the whole thing from scratch.

---

## 1. Architecture overview

```
[PPPoE subscriber traffic]
        |
        v
[Juniper MX104 — MS-MIC card does NAT44 (CGN)]
        |  "then syslog" on NAT rule match
        v
[Syslog UDP packet, port 20514] ---(network)--->  [Zalpro server]
                                                         |
                                            [rsyslog receives on :20514]
                                                         |
                                     [omprog pipes each line to parser.php]
                                                         |
                              [parser.php parses line, looks up username
                               via RADIUS accounting (radacct table),
                               skips excluded destinations (e.g. DNS)]
                                                         |
                                                         v
                         [MySQL: conntrack.`<YYYY-MM-DD>` table]
                         columns: time, username, srcip, srcport,
                                  dstip, dstport, protocol
                                                         |
                                                         v
                          [Stored on dedicated 2TB disk, mounted at
                           /mnt/cts-storage, separate from OS disk]
```

Two independent logging mechanisms exist on the RADIUS/Zalpro side and
both are needed together:
- **RADIUS accounting (`radacct` table)** — already existed before this
  project — gives `username ↔ private IP ↔ session start/stop time`.
- **This new NAT syslog pipeline** — gives `private IP:port ↔
  destination IP:port ↔ time`.

`parser.php` joins these two at insert time, so the final table already
has the username filled in — no need to join at query time.

---

## 2. Part A — Juniper MX configuration

Full command list: see attached **`juniper/juniper-commands.txt`**.

Summary of what it does:
1. Points the NAT service-set's syslog output at the Zalpro server
   (`103.170.179.155:20514`, UDP).
2. Enables the `nat-logs` class — this is what actually turns on NAT
   event logging (severity alone is not enough).
3. Confirms/sets `then syslog;` on the main NAT rule term.
4. Adds an earlier-matching NAT rule term that excludes chosen
   destination IPs (currently: `8.8.8.8`, `8.8.4.4`, `192.168.255.53`,
   `192.168.255.54` — internal/public DNS resolvers) from logging
   entirely, to cut log volume. Traffic to these IPs still gets NAT'd
   normally — it's just not logged.
5. Commits.

**Two prerequisites that are easy to miss** (both already in place on
this router, but critical if setting up fresh):
- The syslog `source-address` (e.g. `103.170.179.40`) must be
  configured as a `/32` directly on the outside service interface
  (`ms-0/0/0.102` in this case) — without this, no syslog is ever sent,
  with no error shown anywhere.
- The `class nat-logs` statement must be present — `services any` in
  the syslog host stanza only controls severity level, not which log
  categories fire.

See the **Troubleshooting** section at the bottom of
`juniper-commands.txt` for the full story of how these two were found
and fixed, plus a third issue (`session-logs` overloading the PIC at
3000+ subscriber scale — never enable that class, only `nat-logs`).

**Verification (on the MX):**
```
run show services service-sets statistics syslog detail
```
Look for `NAT logs: Sent` incrementing as traffic flows. If it stays at
0, work through the Troubleshooting section.

---

## 3. Part B — Zalpro server setup

### 3.1 Prerequisites

- FreeRADIUS 3.0 with SQL module already configured, logging accounting
  to a `radacct` table (MySQL, database `zalpro` in this deployment) —
  this should already exist if RADIUS auth/accounting is working.
- MySQL/MariaDB server running.
- PHP CLI installed (`php -v` to confirm; `mysqli` extension required —
  usually included by default).
- Server timezone should be `Asia/Karachi` (PKT) — confirm with `date`.
  `radacct` timestamps are stored in this timezone, and `parser.php`
  converts the Juniper syslog's UTC timestamp to match it before
  comparing. If your server uses a different timezone, adjust the
  `LOCAL_TZ` constant in `parser.php`.

### 3.2 Files to deploy

From this package:
- `zalpro/parser.php` → deploy to `/opt/cgn-logger/parser.php`
- `zalpro/60-cgn.conf` → deploy to `/etc/rsyslog.d/60-cgn.conf`
- `zalpro/mysql-setup.sql` → run once against MySQL

### 3.3 Step-by-step

**1) Copy files:**
```bash
mkdir -p /opt/cgn-logger
cp parser.php /opt/cgn-logger/parser.php
cp 60-cgn.conf /etc/rsyslog.d/60-cgn.conf
chmod +x /opt/cgn-logger/parser.php
```

**2) Create the database and a dedicated MySQL user:**
```bash
mysql -u root -p < mysql-setup.sql
```
Edit the password inside `mysql-setup.sql` first (replace
`CHANGE_THIS_PASSWORD`), or run the statements manually with your own
password of choice.

**3) Fill in the DB credentials in `parser.php`:**
```bash
nano /opt/cgn-logger/parser.php
```
Set:
```php
const DB_USER = 'cgnlogger';
const DB_PASS = '<same password as in mysql-setup.sql>';
```

While you're in there, review `EXCLUDED_DST_IPS` near the top — this
is the list of destination IPs that get silently skipped (not logged).
Currently set to the DNS resolvers excluded on the Juniper side too, as
a backup filter — keep both in sync if you change this list.

**4) Syntax-check, then restart rsyslog:**
```bash
php -l /opt/cgn-logger/parser.php
sudo rsyslogd -N1
sudo systemctl restart rsyslog
```
`rsyslogd -N1` may print a warning like `module 'imudp' already in this
config, cannot be added` — this is harmless (imudp is already loaded
globally by the base rsyslog config); it's only a problem if there's a
FATAL error alongside it.

**5) Confirm rsyslog is listening and the parser can spawn:**
```bash
sudo ss -ulnp | grep 20514
```
Should show `rsyslogd` bound to UDP `20514`. The `parser.php` process
itself only spawns once the FIRST matching syslog message arrives
(this is normal, not a bug) — generate some subscriber traffic, then:
```bash
ps aux | grep parser.php
```
should show `/usr/bin/php /opt/cgn-logger/parser.php` running.

**6) Verify data is landing in MySQL:**
```bash
mysql -u root -p conntrack -e "SHOW TABLES;"
mysql -u root -p conntrack -e "SELECT * FROM \`$(date +%Y-%m-%d)\` ORDER BY id DESC LIMIT 10;"
```
You should see rows like:

| time     | username       | srcip      | srcport | dstip           | dstport | protocol |
|----------|----------------|------------|---------|-----------------|---------|----------|
| 16:30:27 | juniper-office | 10.20.0.13 | 55288   | 157.240.227.60  | 443     | 6        |

If `username` is showing `NULL`, double check server timezone (see
Prerequisites above) and that the RADIUS session was genuinely active
at that timestamp.

### 3.4 Enable services on boot (survive reboots)

```bash
sudo systemctl enable rsyslog
sudo systemctl enable mysql
```
`parser.php` needs no separate enabling — rsyslog spawns it
automatically as a child process whenever a matching message arrives,
so as long as rsyslog is running, the parser will start itself.

**Recommended:** do one controlled `sudo reboot` during a low-traffic
window after initial setup, then re-run the verification steps above,
to confirm the whole pipeline really is reboot-safe before relying on
it long-term.

---

## 4. Part C — Dedicated storage for the `conntrack` database

With 3000+ subscribers, `conntrack` data can grow significantly, so
it's kept on its own disk rather than the OS/boot disk.

### 4.1 On the ESXi host

1. **Datastores** tab → **New datastore** → *Create new VMFS
   datastore* → select the new physical disk → give it a name (e.g.
   `cts-storage-2tb`) → use full disk → Finish.
2. On the target VM → **Edit Settings** → **Add hard disk** → **New
   Hard Disk** → set size → **Thin Provision** → Location: browse to
   the new datastore (not the default one) → Save.

### 4.2 Inside the VM (Linux)

```bash
# Confirm the new disk is visible (e.g. /dev/sdb)
lsblk

# Partition + format
sudo parted /dev/sdb mklabel gpt
sudo parted /dev/sdb mkpart primary ext4 0% 100%
sudo mkfs.ext4 /dev/sdb1

# Mount
sudo mkdir -p /mnt/cts-storage
sudo mount /dev/sdb1 /mnt/cts-storage

# Make it permanent
sudo blkid /dev/sdb1        # copy the UUID
echo 'UUID=<paste-uuid-here>  /mnt/cts-storage  ext4  defaults  0  2' | sudo tee -a /etc/fstab

# Test the fstab entry before trusting it
sudo umount /mnt/cts-storage
sudo mount -a
df -h /mnt/cts-storage
```

### 4.3 Move the `conntrack` database onto it

Only the `conntrack` database is moved — RADIUS/`zalpro` DB and
everything else stays on the local disk.

```bash
sudo systemctl stop mysql

sudo mkdir -p /mnt/cts-storage/mysql-conntrack
sudo rsync -av /var/lib/mysql/conntrack/ /mnt/cts-storage/mysql-conntrack/

sudo mv /var/lib/mysql/conntrack /var/lib/mysql/conntrack.OLD_BACKUP
sudo ln -s /mnt/cts-storage/mysql-conntrack /var/lib/mysql/conntrack
sudo chown -R mysql:mysql /mnt/cts-storage/mysql-conntrack
```

**AppArmor must be told to allow the new path** (Ubuntu-specific — MySQL
runs under an AppArmor profile that blocks unlisted paths by default):
```bash
sudo nano /etc/apparmor.d/local/usr.sbin.mysqld
```
Add:
```
/mnt/cts-storage/mysql-conntrack/ r,
/mnt/cts-storage/mysql-conntrack/** rwk,
```
Then:
```bash
sudo systemctl reload apparmor
sudo systemctl start mysql
```

### 4.4 Verify the migration

```bash
sudo systemctl status mysql
mysql -u root -p conntrack -e "SHOW TABLES;"
mysql -u root -p conntrack -e "SELECT * FROM \`$(date +%Y-%m-%d)\` ORDER BY id DESC LIMIT 5;"
du -sh /mnt/cts-storage/mysql-conntrack
```
Wait a minute for live traffic, then re-check `du -sh` and the row
count — both should grow, confirming new data is landing on the new
disk.

Once stable for a few days, remove the backup:
```bash
sudo rm -rf /var/lib/mysql/conntrack.OLD_BACKUP
```

---

## 5. Retention / cleanup

Confirm your current PTA license conditions for the required retention
period (commonly cited minimum: 90 days — verify your specific
requirement). Example cron job to drop dated tables older than a given
window:

```bash
# Drops any dated table older than 180 days. Adjust the interval and
# schedule via cron as needed (e.g. daily, off-peak).
mysql -ucgnlogger -p'<password>' -N -e "
  SELECT table_name FROM information_schema.tables
  WHERE table_schema='conntrack'
    AND table_name REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
    AND STR_TO_DATE(table_name, '%Y-%m-%d') < DATE_SUB(CURDATE(), INTERVAL 180 DAY)
" | while read t; do
  mysql -ucgnlogger -p'<password>' -e "DROP TABLE conntrack.\`$t\`"
done
```

---

## 6. Quick troubleshooting index

| Symptom | Likely cause | Fix |
|---|---|---|
| `NAT logs: Sent: 0` on MX, even with traffic | Missing syslog source-address on service interface | See `juniper-commands.txt` §Troubleshooting #1 |
| Still `Sent: 0` after above | Missing `class nat-logs` on syslog host | See `juniper-commands.txt` §Troubleshooting #2 |
| NAT/internet stopped working after enabling logging | `session-logs` class enabled — overloads PIC at scale | Remove `session-logs`, keep only `nat-logs` |
| tcpdump shows packets arriving on Zalpro but nothing happens | rsyslog input not on its own ruleset — messages swallowed by other rules | Use the `ruleset(){}` + ruleset= pattern in `60-cgn.conf` (already applied) |
| `parser.php` never shows in `ps aux` | Normal until the first matching message arrives — it's spawned on demand by rsyslog | Generate traffic, then re-check |
| `username` is `NULL` in the table | Server timezone mismatch with `radacct`, or no RADIUS session was active at that IP/time | Check `date`, check `LOCAL_TZ` in parser.php, check `radacct` |
| `Table 'conntrack.<date>' doesn't exist` | A pre-existing table with a different schema is blocking auto-creation, or MySQL user lacks CREATE | Drop the old table / check grants in `mysql-setup.sql` |

---

## 7. Attached files reference

```
juniper/
  juniper-commands.txt      — all MX CLI commands, in order, plus troubleshooting notes

zalpro/
  parser.php                — the log parser/enricher daemon
  60-cgn.conf                — rsyslog config (deploy to /etc/rsyslog.d/)
  mysql-setup.sql            — DB + user creation
```

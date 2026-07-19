# CGN NAT Log Pipeline — Deployment Guide (Zalpro server)

Real-time pipeline: Juniper MX syslog (UDP 20514) → rsyslog → PHP parser →
`conntrack`.`<YYYY-MM-DD>` table, with username resolved from RADIUS
(`zalpro`.`radacct`) at insert time.

## 1. Files

Copy the two files to the server:

```bash
mkdir -p /opt/cgn-logger
cp parser.php /opt/cgn-logger/parser.php
cp 60-cgn.conf /etc/rsyslog.d/60-cgn.conf
chmod +x /opt/cgn-logger/parser.php
```

## 2. MySQL: create DB + dedicated user

```sql
CREATE DATABASE IF NOT EXISTS conntrack;

CREATE USER 'cgnlogger'@'localhost' IDENTIFIED BY 'CHOOSE_A_STRONG_PASSWORD';

-- full rights on conntrack (needs CREATE, since new tables are made daily)
GRANT ALL PRIVILEGES ON conntrack.* TO 'cgnlogger'@'localhost';

-- read-only on radacct for username lookups
GRANT SELECT ON zalpro.radacct TO 'cgnlogger'@'localhost';

FLUSH PRIVILEGES;
```

Then edit `/opt/cgn-logger/parser.php` and fill in:
```php
const DB_USER = 'cgnlogger';
const DB_PASS = 'CHOOSE_A_STRONG_PASSWORD';   // same as above
```

## 3. Enable and restart rsyslog

```bash
sudo systemctl restart rsyslog
sudo systemctl status rsyslog
```

rsyslog itself spawns `/opt/cgn-logger/parser.php` as a child process (via
`omprog`) and keeps it running, feeding it one line per NAT event. No
separate systemd service is needed for the PHP script.

## 4. Verify

Generate some traffic from an active PPPoE user, then:

```sql
SHOW TABLES FROM conntrack;
SELECT * FROM conntrack.`2026-07-19` ORDER BY id DESC LIMIT 10;
```

You should see rows like:

| time     | username        | srcip      | srcport | dstip         | dstport | protocol |
|----------|------------------|------------|---------|---------------|---------|----------|
| 21:13:02 | juniper-office   | 10.20.0.5  | 28760   | 162.4.32.33   | 53      | 17       |

If `username` is `NULL` for a real session, double check the server's
system timezone matches `Asia/Karachi` (or adjust `LOCAL_TZ` in
parser.php) and that `radacct.acctstarttime` is genuinely in PKT, not UTC.

## 5. Troubleshooting

- **No rows appearing at all:** check rsyslog is receiving packets —
  `sudo tcpdump -i any -n udp port 20514 -A`. If packets arrive but no
  rows land, check PHP errors: rsyslog logs omprog's stderr to
  `/var/log/syslog` (search for lines from parser.php's `logErr()`).
- **"Unparsed line" errors:** Junos log format changed or a different
  message type came through — share the exact line and the regex in
  `parser.php` can be adjusted.
- **Table not auto-created:** confirm the `cgnlogger` MySQL user has
  `CREATE` privilege on `conntrack` (see grants above).

## 6. Retention / cleanup

With ~47k+ rows/day per active user population (per your earlier
`conntrack` reference table), plan a periodic cleanup job (e.g. a nightly
cron) to `DROP TABLE` dated tables older than your required retention
window (PTA minimum is commonly cited as 90 days — confirm your current
license conditions). Example:

```bash
# Drops any dated table older than 180 days. Run daily via cron.
mysql -ucgnlogger -p'CHOOSE_A_STRONG_PASSWORD' -N -e "
  SELECT table_name FROM information_schema.tables
  WHERE table_schema='conntrack'
    AND table_name REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
    AND STR_TO_DATE(table_name, '%Y-%m-%d') < DATE_SUB(CURDATE(), INTERVAL 180 DAY)
" | while read t; do
  mysql -ucgnlogger -p'CHOOSE_A_STRONG_PASSWORD' -e "DROP TABLE conntrack.\`$t\`"
done
```

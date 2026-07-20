-- CGN NAT logging: MySQL setup
-- Run as: mysql -u root -p < mysql-setup.sql
-- (or paste into `mysql -u root -p` shell)

CREATE DATABASE IF NOT EXISTS conntrack;

-- Change this password, then use the SAME password in parser.php (DB_PASS)
CREATE USER IF NOT EXISTS 'cgnlogger'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';

-- Full rights on conntrack — needs CREATE, since a new table is auto-made each day
GRANT ALL PRIVILEGES ON conntrack.* TO 'cgnlogger'@'localhost';

-- Read-only on radacct, just for username lookups
GRANT SELECT ON zalpro.radacct TO 'cgnlogger'@'localhost';

FLUSH PRIVILEGES;

-- Note: daily tables (e.g. `2026-07-20`) are created automatically by
-- parser.php on first insert of the day — you do not need to create them
-- manually. Do NOT pre-create a table with a different schema; parser.php's
-- CREATE TABLE IF NOT EXISTS will silently skip creation if a table with
-- that name already exists, and inserts will then fail on column mismatch.

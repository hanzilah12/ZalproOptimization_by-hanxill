<?php
/**
 * CGN NAT log parser for NetPoint-BNG-KTA (Juniper MX104).
 *
 * Fed line-by-line via rsyslog's omprog (see 60-cgn.conf). For every
 * JSERVICES_NAT_RULE_MATCH line:
 *   1. Resolves the username active on that private IP at that time,
 *      by querying RADIUS accounting (radacct) in the `zalpro` DB.
 *   2. Inserts a row into the `conntrack`.`<YYYY-MM-DD>` table
 *      (auto-created on first use each day), matching the legacy
 *      DMA-RADIUS/Mikrotik log format: time, username, srcip, srcport,
 *      dstip, dstport, protocol.
 *
 * Deploy at: /opt/cgn-logger/parser.php
 */

declare(strict_types=1);

// ---- CONFIG: fill these in ------------------------------------------------
const DB_HOST        = 'localhost';
const DB_USER        = 'REPLACE_ME';
const DB_PASS         = 'REPLACE_ME';
const CONNTRACK_DB    = 'conntrack';
const RADIUS_DB       = 'zalpro';
const LOCAL_TZ        = 'Asia/Karachi'; // PKT, matches radacct's stored times

// Destination IPs to skip entirely (not logged at all) — e.g. DNS resolvers
// with no lawful-intercept value. Add/remove IPs here as needed.
const EXCLUDED_DST_IPS = [
    '8.8.8.8',
    '8.8.4.4',
    '192.168.255.53',
    '192.168.255.54',
];
// -----------------------------------------------------------------------------

$conntrackConn = null;
$radiusConn    = null;
$currentTableDate = null;

function logErr(string $msg): void {
    fwrite(STDERR, '[' . date('c') . '] ' . $msg . "\n");
}

/** Lazily (re)connect, auto-recovering from dropped MySQL connections. */
function getConn(?mysqli &$conn, string $db): ?mysqli {
    if ($conn instanceof mysqli) {
        // ping() detects a dead connection so we can reconnect cleanly
        if (@$conn->ping()) {
            return $conn;
        }
        $conn->close();
        $conn = null;
    }
    $c = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $db);
    if (!$c) {
        logErr("DB connect failed ($db): " . mysqli_connect_error());
        return null;
    }
    $conn = $c;
    return $conn;
}

function ensureTable(mysqli $conn, string $date): void {
    $table = $conn->real_escape_string($date);
    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        time TIME NOT NULL,
        username VARCHAR(64) DEFAULT NULL,
        srcip VARCHAR(15) NOT NULL,
        srcport INT UNSIGNED NOT NULL,
        dstip VARCHAR(15) NOT NULL,
        dstport INT UNSIGNED NOT NULL,
        protocol TINYINT UNSIGNED NOT NULL,
        INDEX idx_username (username),
        INDEX idx_srcip (srcip),
        INDEX idx_dstip_dstport (dstip, dstport),
        INDEX idx_time (time)
    ) ENGINE=InnoDB";
    if (!$conn->query($sql)) {
        logErr("CREATE TABLE failed for `$table`: " . $conn->error);
    }
}

function lookupUsername(mysqli $radiusConn, string $ip, string $localDateTime): ?string {
    $stmt = $radiusConn->prepare(
        "SELECT username FROM radacct
         WHERE framedipaddress = ?
           AND ? BETWEEN acctstarttime AND COALESCE(acctstoptime, NOW())
         ORDER BY acctstarttime DESC
         LIMIT 1"
    );
    if (!$stmt) {
        logErr("radacct prepare failed: " . $radiusConn->error);
        return null;
    }
    $stmt->bind_param('ss', $ip, $localDateTime);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? $row['username'] : null;
}

// Matches lines like:
// 2026-07-19T16:13:02.123456+00:00 <158>2026-07-19 16:13:02: CGN-KTA{CGN_VRF0_LOG}[jservices-nat]: JSERVICES_NAT_RULE_MATCH: proto 17 (UDP) application: any, xe-2/0/0.32767:10.20.0.5:28760 -> 162.4.32.33:53, Match NAT rule-set: (null), rule: CGN_PLUS_0, term: 1
$lineRegex = '/^(\S+)\s+.*JSERVICES_NAT_RULE_MATCH: proto (\d+) \([A-Z]+\) application: \S+, \S+:([\d.]+):(\d+) -> ([\d.]+):(\d+),/';

// Main loop: one syslog line per read, fed continuously by rsyslog.
while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if ($line === '' || strpos($line, 'JSERVICES_NAT_RULE_MATCH') === false) {
        // Ignores JSERVICES_NAT_PORT_BLOCK_ALLOC/RELEASE and anything else
        // for this table; extend here if you want to log those separately.
        continue;
    }

    if (!preg_match($lineRegex, $line, $m)) {
        logErr("Unparsed line: $line");
        continue;
    }

    [, $utcTs, $proto, $srcIp, $srcPort, $dstIp, $dstPort] = $m;

    if (in_array($dstIp, EXCLUDED_DST_IPS, true)) {
        continue; // e.g. DNS resolver traffic — not needed in these logs
    }

    try {
        $dt = new DateTime($utcTs, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(LOCAL_TZ));
    } catch (Exception $e) {
        logErr("Bad timestamp '$utcTs': " . $e->getMessage());
        continue;
    }

    $localDate     = $dt->format('Y-m-d');
    $localTime     = $dt->format('H:i:s');
    $localDateTime = $dt->format('Y-m-d H:i:s');

    $conntrackConn = getConn($conntrackConn, CONNTRACK_DB);
    $radiusConn    = getConn($radiusConn, RADIUS_DB);
    if (!$conntrackConn || !$radiusConn) {
        continue; // error already logged; drop this line rather than block the pipe
    }

    if ($currentTableDate !== $localDate) {
        ensureTable($conntrackConn, $localDate);
        $currentTableDate = $localDate;
    }

    $username = lookupUsername($radiusConn, $srcIp, $localDateTime);

    $srcPortInt = (int)$srcPort;
    $dstPortInt = (int)$dstPort;
    $protoInt   = (int)$proto;

    $table = $conntrackConn->real_escape_string($localDate);
    $stmt = $conntrackConn->prepare(
        "INSERT INTO `$table` (time, username, srcip, srcport, dstip, dstport, protocol)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        logErr("INSERT prepare failed: " . $conntrackConn->error);
        continue;
    }
    // Types in order: time(s) username(s) srcip(s) srcport(i) dstip(s) dstport(i) protocol(i)
    $stmt->bind_param(
        'sssisii',
        $localTime, $username, $srcIp, $srcPortInt, $dstIp, $dstPortInt, $protoInt
    );
    if (!$stmt->execute()) {
        logErr("INSERT failed: " . $stmt->error);
    }
    $stmt->close();
}

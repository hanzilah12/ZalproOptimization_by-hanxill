<?php
header('Content-Type: text/html; charset=utf-8');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (empty($username)) {
    die("<div style='padding:20px; color:#d9534f; text-align:center; font-family:sans-serif;'><b>No subscriber identity passed.</b></div>");
}

$log_path = '/var/log/freeradius/radius.log';
if (!file_exists($log_path)) { $log_path = '/var/log/radius/radius.log'; }

$combined_logs = [];

// =========================================================================
// 1. FETCH DISCONNECT LOGS FROM MYSQL (radacct table)
// =========================================================================
$config_path = '/zalpro-optimization/credentials/db_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
    $mysqli = new mysqli('localhost', DB_USER, DB_PASS, 'zalpro');
    
    if (!$mysqli->connect_error) {
        $safe_user = $mysqli->real_escape_string($username);
        
        if ($username === 'all') {
            $query = "SELECT acctstoptime AS timestamp, username, 'Disconnect' as type, 
                      CONCAT('IP: ', framedipaddress, ' | Cause: ', acctterminatecause) AS details 
                      FROM radacct WHERE acctstoptime IS NOT NULL 
                      ORDER BY acctstoptime DESC LIMIT 40";
        } else {
            $query = "SELECT acctstoptime AS timestamp, username, 'Disconnect' as type, 
                      CONCAT('IP: ', framedipaddress, ' | Cause: ', acctterminatecause) AS details 
                      FROM radacct WHERE username = '$safe_user' AND acctstoptime IS NOT NULL 
                      ORDER BY acctstoptime DESC LIMIT 20";
        }
        
        if ($res = $mysqli->query($query)) {
            while ($row = $res->fetch_assoc()) {
                $prefix = ($username === 'all') ? "[" . $row['username'] . "] " : "";
                $combined_logs[] = [
                    'timestamp' => $row['timestamp'], // Format: YYYY-MM-DD HH:MM:SS
                    'type' => 'Disconnect',
                    'details' => $prefix . $row['details']
                ];
            }
        }
        $mysqli->close();
    }
}

// =========================================================================
// 2. FETCH AUTH LOGS FROM TEXT FILE (radius.log) - WITH STRICT FILTERING
// =========================================================================
if (file_exists($log_path)) {
    if ($username === 'all') {
        // STRICT FILTER: Sirf Auth events uthayega, rlm_sql waghera ko skip kar dega
        $command = "grep -E 'Login OK|Login incorrect|Reject' " . escapeshellarg($log_path) . " | tail -n 60";
    } else {
        $raw_user = escapeshellarg($username);
        // STRICT FILTER: Us specific user ke sirf Auth events uthayega
        $command = "grep -i " . $raw_user . " " . escapeshellarg($log_path) . " | grep -E 'Login OK|Login incorrect|Reject' | tail -n 30";
    }
    
    $output = shell_exec($command);
    if (!empty($output)) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $raw_time = substr($line, 0, 24);
            $parsed_time = date('Y-m-d H:i:s', strtotime($raw_time));
            if (!$parsed_time || $parsed_time == '1970-01-01 00:00:00') {
                $parsed_time = $raw_time; 
            }

            $rest_of_log = substr($line, 27);
            $type = 'Info';
            
            if (stripos($line, 'Login OK') !== false) {
                $type = 'Login OK';
            } elseif (stripos($line, 'Login incorrect') !== false || stripos($line, 'Reject') !== false) {
                $type = 'Rejected';
            }
            
            // Agar type 'Info' hai, tou humein show hi nahi karna (bye bye rlm_sql spam)
            if ($type === 'Info') continue; 
            
            if (preg_replace('/.*Auth:\s+/i', '', $line)) {
                $rest_of_log = preg_replace('/.*Auth:\s+/i', '', $line);
            }
            
            $combined_logs[] = [
                'timestamp' => $parsed_time,
                'type' => $type,
                'details' => htmlspecialchars($rest_of_log)
            ];
        }
    }
}

// =========================================================================
// 3. SORT LOGS (NEWEST FIRST) AND RENDER HTML
// =========================================================================
usort($combined_logs, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

echo "<!DOCTYPE html>
<html>
<head>
    <meta http-equiv='refresh' content='5'>
    <style>
        body { background: #ffffff !important; font-family: 'Helvetica Neue', Roboto, Arial, sans-serif; margin: 0; padding: 0; color: #555; overflow-x: hidden; }
        table { width: 100%; border-collapse: collapse; margin: 0; background: #ffffff; }
        th { background-color: #f5f7fa; color: #2A3F54; font-weight: 600; text-align: left; padding: 10px 12px; font-size: 13px; border-bottom: 2px solid #dddddd; position: sticky; top: 0; z-index: 10; }
        td { padding: 8px 12px; font-size: 13px; border-bottom: 1px solid #eeeeee; color: #6f7b8a; }
        tr:hover { background-color: #f9f9f9; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; color: #fff; }
        .badge-success { background-color: #26B99A; }
        .badge-danger { background-color: #d9534f; }
        .badge-info { background-color: #34495E; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Event / Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>";

if (!empty($combined_logs)) {
    foreach ($combined_logs as $log) {
        $badge_class = 'badge-info';
        if ($log['type'] == 'Login OK') $badge_class = 'badge-success';
        if ($log['type'] == 'Rejected' || $log['type'] == 'Disconnect') $badge_class = 'badge-danger';
        
        $status_badge = "<span class='badge {$badge_class}'>{$log['type']}</span>";
        $safe_details = $log['details'];
        
        echo "<tr>
                <td style='color:#2A3F54; font-weight:500;'>{$log['timestamp']}</td>
                <td>{$status_badge}</td>
                <td style='font-family: monospace;'>{$safe_details}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='3' style='text-align:center; color:#999; font-style:italic; padding:30px;'>Waiting for active connection logs for <b>" . htmlspecialchars($username) . "</b>...</td></tr>";
}

echo "</tbody></table></body></html>";
?>
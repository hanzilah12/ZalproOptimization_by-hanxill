<?php
header('Content-Type: text/html; charset=utf-8');

// URL se username uthayega
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (empty($username)) {
    die("<div style='padding:20px; color:#d9534f; text-align:center; font-family:sans-serif;'><b>No subscriber identity passed.</b></div>");
}

$log_path = '/var/log/freeradius/radius.log';
if (!file_exists($log_path)) { $log_path = '/var/log/radius/radius.log'; }

echo "<!DOCTYPE html>
<html>
<head>
    <meta http-equiv='refresh' content='5'> <style>
body {
    background: #ffffff !important;
    font-family: 'Helvetica Neue', Roboto, Arial, sans-serif;
    margin: 0;
    padding: 0;
    color: #555555;
    overflow-x: hidden;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    background: #ffffff;
}
th {
    background-color: #f5f7fa;
    color: #2A3F54;
    font-weight: 600;
    text-align: left;
    padding: 10px 12px;
    font-size: 13px;
    border-bottom: 2px solid #dddddd;
    position: sticky;
    top: 0;
    z-index: 10;
}
td {
    padding: 8px 12px;
    font-size: 13px;
    border-bottom: 1px solid #eeeeee;
    color: #6f7b8a;
}
tr:hover {
    background-color: #f9f9f9;
}
.badge {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    color: #fff;
}
.badge-success { background-color: #26B99A; }
.badge-danger { background-color: #d9534f; }
.badge-info { background-color: #34495E; }
</style>
</head>
<body>";

if (file_exists($log_path)) {
    // # 100% FIXED ROBUST METHOD BY hanxill: 
    // Pehle pure logs me se standard username filter nikalenge fir router conflict bypass hoga
    $raw_user = escapeshellarg($username);
    $command = "grep -i \"\\[\"$raw_user\"\\]\" " . escapeshellarg($log_path) . " || grep -i \"\\[\"$raw_user\"/\" " . escapeshellarg($log_path) . " | tail -n 15";
    
    // Agar upar wala bypass na ho to backup plain log check chalega
    $output = shell_exec($command);
    if (empty($output)) {
        // Fallback robust check agar brackets string query error karein
        $command = "grep -i \"$username\" " . escapeshellarg($log_path) . " | grep -v \"client\" | tail -n 15";
        $output = shell_exec($command);
    }
    
    echo "<table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Event Type / Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>";
            
    if (!empty($output)) {
        $lines = explode("\n", trim($output));
        $lines = array_reverse($lines);
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $timestamp = substr($line, 0, 24);
            $rest_of_log = substr($line, 27);
            
            $status_badge = "<span class='badge badge-info'>Info</span>";
            $details = htmlspecialchars($rest_of_log);
            
            if (stripos($line, 'Login OK') !== false) {
                $status_badge = "<span class='badge badge-success'>Login OK</span>";
            } elseif (stripos($line, 'Login incorrect') !== false || stripos($line, 'Reject') !== false) {
                $status_badge = "<span class='badge badge-danger'>Rejected</span>";
            } elseif (stripos($line, 'Disconnect-Request') !== false) {
                $status_badge = "<span class='badge badge-danger'>Disconnect</span>";
            }
            
            if (preg_replace('/.*Auth:\s+/i', '', $line)) {
                $details = htmlspecialchars(preg_replace('/.*Auth:\s+/i', '', $line));
            }
            
            echo "<tr>
                    <td style='color:#2A3F54; font-weight:500;'>{$timestamp}</td>
                    <td>{$status_badge}</td>
                    <td style='font-family: monospace;'>{$details}</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='3' style='text-align:center; color:#999; font-style:italic; padding:30px;'>Waiting for active connection log hits for <b>" . htmlspecialchars($username) . "</b>...</td></tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div style='padding:20px; color:#d9534f; text-align:center;'>Log file path not readable by web server.</div>";
}

echo "</body></html>";
?>

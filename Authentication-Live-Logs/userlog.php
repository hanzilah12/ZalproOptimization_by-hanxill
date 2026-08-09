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
        
        // Limits increased to fetch enough data for the 1000 max logs display
        if ($username === 'all') {
            $query = "SELECT acctstoptime AS timestamp, username, 'Disconnect' as type, 
                      CONCAT('IP: ', framedipaddress, ' | Cause: ', acctterminatecause) AS details 
                      FROM radacct WHERE acctstoptime IS NOT NULL 
                      ORDER BY acctstoptime DESC LIMIT 1000";
        } else {
            $query = "SELECT acctstoptime AS timestamp, username, 'Disconnect' as type, 
                      CONCAT('IP: ', framedipaddress, ' | Cause: ', acctterminatecause) AS details 
                      FROM radacct WHERE username = '$safe_user' AND acctstoptime IS NOT NULL 
                      ORDER BY acctstoptime DESC LIMIT 1000";
        }
        
        if ($res = $mysqli->query($query)) {
            while ($row = $res->fetch_assoc()) {
                $prefix = ($username === 'all') ? "[" . $row['username'] . "] " : "";
                $combined_logs[] = [
                    'timestamp' => $row['timestamp'], 
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
        // Fetch up to 2000 raw matching lines so we have enough before sorting
        $command = "grep -E 'Login OK|Login incorrect|Reject' " . escapeshellarg($log_path) . " | tail -n 2000";
    } else {
        $raw_user = escapeshellarg($username);
        $command = "grep -i " . $raw_user . " " . escapeshellarg($log_path) . " | grep -E 'Login OK|Login incorrect|Reject' | tail -n 1000";
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
// 3. SORT LOGS & APPLY MAX 1000 LIMIT 
// =========================================================================
usort($combined_logs, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Strictly keep only the top 1000 newest logs, pushing oldest out
$combined_logs = array_slice($combined_logs, 0, 1000);

// =========================================================================
// 4. HTML RENDER, CSS & LOCALSTORAGE JS FOR ROW HIGHLIGHTING
// =========================================================================
echo "<!DOCTYPE html>
<html>
<head>
    <style>
        body { background: #ffffff !important; font-family: 'Helvetica Neue', Roboto, Arial, sans-serif; margin: 0; padding: 0; color: #555; overflow-x: hidden; }
        table { width: 100%; border-collapse: collapse; margin: 0; background: #ffffff; }
        th { background-color: #f5f7fa; color: #2A3F54; font-weight: 600; text-align: left; padding: 10px 12px; font-size: 13px; border-bottom: 2px solid #dddddd; position: sticky; top: 0; z-index: 10; }
        td { padding: 8px 12px; font-size: 13px; border-bottom: 1px solid #eeeeee; color: #6f7b8a; }
        tr.log-row { cursor: pointer; transition: background 0.2s; }
        tr.log-row:hover { background-color: #f1f5f9; }
        /* Highlighted class added on tap */
        .highlighted-row { background-color: #fff3cd !important; border-left: 3px solid #ffc107; }
        
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; color: #fff; }
        .badge-success { background-color: #26B99A; }
        .badge-danger { background-color: #d9534f; }
        .badge-info { background-color: #34495E; }

        .freeze-btn { float: right; padding: 4px 10px; font-size: 11px; background-color: #34495E; color: white; border: none; border-radius: 3px; cursor: pointer; font-weight: bold; }
        .freeze-btn.frozen { background-color: #d9534f; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Event / Status</th>
                <th>
                    Details 
                    <button id='freezeToggle' class='freeze-btn'>Pause Auto-Refresh</button>
                </th>
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
        
        // Use timestamp + raw text as unique ID for localStorage
        $row_id = md5($log['timestamp'] . $log['details']);
        
        echo "<tr class='log-row' data-rowid='{$row_id}'>
                <td style='color:#2A3F54; font-weight:500;'>{$log['timestamp']}</td>
                <td>{$status_badge}</td>
                <td style='font-family: monospace;'>{$safe_details}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='3' style='text-align:center; color:#999; font-style:italic; padding:30px;'>Waiting for active connection logs for <b>" . htmlspecialchars($username) . "</b>...</td></tr>";
}

echo "</tbody></table>

<script>
    // 1. Auto-refresh & Freeze Logic
    let isFrozen = localStorage.getItem('logsFrozen') === 'true';
    let freezeBtn = document.getElementById('freezeToggle');
    let refreshTimer;

    function applyFreezeUI() {
        if(isFrozen) {
            freezeBtn.classList.add('frozen');
            freezeBtn.innerText = 'Resume Auto-Refresh';
            clearTimeout(refreshTimer);
        } else {
            freezeBtn.classList.remove('frozen');
            freezeBtn.innerText = 'Pause Auto-Refresh';
            refreshTimer = setTimeout(() => { window.location.reload(); }, 5000);
        }
    }

    freezeBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // Stop row click
        isFrozen = !isFrozen;
        localStorage.setItem('logsFrozen', isFrozen);
        applyFreezeUI();
        if(!isFrozen) window.location.reload(); 
    });
    
    // Start refresh cycle
    applyFreezeUI();

    // 2. Row Highlighting Logic (Tapping)
    let selectedRows = JSON.parse(localStorage.getItem('selectedLogRows') || '[]');
    let rows = document.querySelectorAll('.log-row');

    rows.forEach(row => {
        let rowId = row.getAttribute('data-rowid');
        
        // Restore highlights on load
        if(selectedRows.includes(rowId)) {
            row.classList.add('highlighted-row');
        }

        // Tap/Click to toggle highlight
        row.addEventListener('click', function() {
            this.classList.toggle('highlighted-row');
            
            if(this.classList.contains('highlighted-row')) {
                if(!selectedRows.includes(rowId)) selectedRows.push(rowId);
            } else {
                selectedRows = selectedRows.filter(id => id !== rowId);
            }
            
            // Keep localStorage clean (limit saved highlights so it doesn't get massive)
            if(selectedRows.length > 100) selectedRows.shift();
            
            localStorage.setItem('selectedLogRows', JSON.stringify(selectedRows));
        });
    });
</script>
</body>
</html>";
?>
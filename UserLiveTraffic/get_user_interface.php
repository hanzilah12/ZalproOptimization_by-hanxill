<?php
header('Content-Type: application/json');
$router_ip   = "103.170.179.40";
$router_user = "zalpro-api";
$router_port = "8877";
$ssh_key     = "/var/lib/zalpro/.ssh/juniper_key";
$current_username = isset($_GET['username']) ? trim($_GET['username']) : '';
if (empty($current_username)) { echo json_encode(["error" => "Username required", "status" => "error"]); exit; }
$found_interface = null; $error_msg = null;
try {
    $cmd = sprintf("timeout 15 ssh -p %s -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null %s@%s 'show subscribers' 2>&1",
        escapeshellarg($router_port), escapeshellarg($ssh_key), escapeshellarg($router_user), escapeshellarg($router_ip));
    $output = shell_exec($cmd);
    if (empty($output)) throw new Exception("No output from SSH");
    if (stripos($output, 'Permission denied') !== false) throw new Exception("SSH auth failed");
    foreach (explode("\n", $output) as $line) {
        if (strpos($line, 'pp0') === false) continue;
        if (preg_match('/^(\S+)\s+\S+\s+(\S+)\s+/', trim($line), $m)) {
            if (trim($m[2]) === $current_username) { $found_interface = trim($m[1]); break; }
        }
    }
} catch (Exception $e) { $error_msg = $e->getMessage(); }
if ($found_interface) echo json_encode(["status" => "success", "interface" => $found_interface, "username" => $current_username]);
elseif ($error_msg) echo json_encode(["error" => $error_msg, "status" => "error"]);
else echo json_encode(["error" => "User offline", "status" => "offline", "username" => $current_username]);
?>

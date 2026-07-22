<?php
header('Content-Type: application/json');

$user = isset($_GET['user']) ? trim($_GET['user']) : '';

if (empty($user)) {
    echo json_encode(['status' => 'error', 'message' => 'Username parameter missing']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_\-]+$/',$user)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid username format']);
    exit;
}

$cmd = "python3 /opt/test_olt.py --user " . escapeshellarg($user) . " --json 2>&1";
$output = shell_exec($cmd);

if ($output) {
    echo $output;
} else {
    echo json_encode(['status' => 'error', 'message' => 'No response from Python background script']);
}
?>
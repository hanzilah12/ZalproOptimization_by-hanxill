<?php
require_once __DIR__ . '/session_guard.php';

// The guard verifies the session and refreshes last_activity.
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'timeout' => ZALPRO_SESSION_TIMEOUT]);

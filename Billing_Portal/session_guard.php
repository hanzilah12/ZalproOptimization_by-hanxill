<?php
/**
 * Zalpro staff portal session guard.
 * Automatically expires a logged-in session after 10 minutes of inactivity.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const ZALPRO_SESSION_TIMEOUT = 600; // 10 minutes

function zalpro_enforce_session_timeout(bool $api_request = false): void
{
    if (!isset($_SESSION['custom_logged_in']) || $_SESSION['custom_logged_in'] !== true) {
        if ($api_request) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Session expired. Please login again.', 'session_expired' => true]);
        } else {
            header('Location: login.php');
        }
        exit;
    }

    $last_activity = (int)($_SESSION['last_activity'] ?? 0);

    if ($last_activity > 0 && (time() - $last_activity) >= ZALPRO_SESSION_TIMEOUT) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();

        if ($api_request) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Session expired due to 10 minutes of inactivity.', 'session_expired' => true]);
        } else {
            header('Location: login.php?timeout=1');
        }
        exit;
    }

    $_SESSION['last_activity'] = time();
}

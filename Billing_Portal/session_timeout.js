(function () {
    'use strict';

    const IDLE_LIMIT = 10 * 60 * 1000;
    const PING_INTERVAL = 2 * 60 * 1000;
    let idleTimer = null;
    let lastActivitySent = 0;
    let loggingOut = false;

    function logoutNow() {
        if (loggingOut) return;
        loggingOut = true;

        // If this page is inside the dashboard iframe, navigate the top window.
        try {
            window.top.location.href = 'logout.php?timeout=1';
        } catch (e) {
            window.location.href = 'logout.php?timeout=1';
        }
    }

    function sendActivityToParent() {
        if (window.parent !== window) {
            try {
                window.parent.postMessage({ type: 'zalpro-activity' }, window.location.origin);
            } catch (e) {}
        }
    }

    function pingServer() {
        const now = Date.now();
        if (now - lastActivitySent < PING_INTERVAL || loggingOut) return;

        lastActivitySent = now;
        fetch('session_ping.php', {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (response.status === 401) logoutNow();
        }).catch(function () {
            // Do not log the user out merely because a ping failed.
        });
    }

    function resetIdleTimer(shouldPing) {
        if (loggingOut) return;

        clearTimeout(idleTimer);
        idleTimer = setTimeout(logoutNow, IDLE_LIMIT);

        sendActivityToParent();
        if (shouldPing !== false) pingServer();
    }

    ['mousedown', 'mousemove', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (eventName) {
        window.addEventListener(eventName, function () {
            resetIdleTimer(true);
        }, { passive: true });
    });

    // Child iframe pages tell the dashboard parent that the operator is active.
    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) return;
        if (event.data && event.data.type === 'zalpro-activity') {
            resetIdleTimer(true);
        }
    });

    resetIdleTimer(false);
})();

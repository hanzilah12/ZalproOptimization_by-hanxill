<?php
$CI =& get_instance();
if (!isset($user) || empty($user->username)) {
    echo '<div class="alert alert-danger">User information not available</div>';
    exit;
}
$username = htmlspecialchars($user->username);
?>

<div class="col-md-12 col-sm-12 col-xs-12 mt-20">
<style>
.tm-panel { background:#fff; border-radius:12px; border:1px solid #e8edf2; box-shadow:0 2px 12px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:20px; }
.tm-header { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid #f0f4f8; background:#f8fafc; flex-wrap:wrap; gap:10px; }
.tm-title { display:flex; align-items:center; gap:10px; }
.tm-title h4 { margin:0; font-size:15px; font-weight:600; color:#1a2332; }
.tm-title i { color:#3498DB; font-size:16px; }
.tm-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.tm-badge { display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:4px 12px; }
.tm-badge .dot { width:7px; height:7px; border-radius:50%; background:#94a3b8; }
.tm-badge.online .dot { background:#10b981; box-shadow:0 0 0 2px rgba(16,185,129,0.2); animation:pulse 2s infinite; }
.tm-badge.offline .dot { background:#ef4444; }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 2px rgba(16,185,129,0.2);}50%{box-shadow:0 0 0 4px rgba(16,185,129,0.1);} }
.tm-body { padding:20px 24px; }
.tm-stats { display:flex; gap:16px; margin-bottom:20px; }
.tm-stat-box { flex:1; border-radius:10px; padding:16px 20px; display:flex; align-items:center; gap:14px; }
.tm-stat-box.dl { background:linear-gradient(135deg,#EBF5FF 0%,#DBEAFE 100%); border:1px solid #BFDBFE; }
.tm-stat-box.ul { background:linear-gradient(135deg,#ECFDF5 0%,#D1FAE5 100%); border:1px solid #A7F3D0; }
.tm-stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.dl .tm-stat-icon { background:rgba(52,152,219,0.15); color:#2980b9; }
.ul .tm-stat-icon { background:rgba(16,185,129,0.15); color:#059669; }
.tm-stat-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.8px; color:#64748b; margin-bottom:2px; }
.tm-stat-value { font-size:26px; font-weight:700; line-height:1; }
.tm-stat-value small { font-size:12px; font-weight:500; color:#64748b; margin-left:3px; }
.tm-stat-raw { font-size:11px; color:#94a3b8; margin-top:3px; }
.dl .tm-stat-value { color:#2563EB; }
.ul .tm-stat-value { color:#059669; }
.tm-chart-wrap { position:relative; height:220px; }
.tm-alert { border-radius:8px; padding:14px 18px; display:flex; align-items:center; gap:10px; font-size:13px; }
.tm-alert.info { background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; }
.tm-alert.warn { background:#FFFBEB; color:#92400E; border:1px solid #FDE68A; }

/* ── START BUTTON + COUNTDOWN ── */
.tm-start-wrap {
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:40px 20px;
    gap:16px;
}
.tm-start-btn {
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:14px 36px;
    font-size:15px;
    font-weight:600;
    color:#fff;
    background:linear-gradient(135deg,#3B82F6,#2563EB);
    border:none;
    border-radius:50px;
    cursor:pointer;
    box-shadow:0 4px 15px rgba(59,130,246,0.4);
    transition:all 0.2s;
    letter-spacing:0.3px;
}
.tm-start-btn:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(59,130,246,0.5); }
.tm-start-btn:active { transform:translateY(0); }
.tm-start-btn.running {
    background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 4px 15px rgba(239,68,68,0.4);
}
.tm-countdown {
    font-size:13px;
    color:#64748b;
    display:flex;
    align-items:center;
    gap:8px;
}
.tm-countdown-bar-wrap {
    width:280px;
    height:6px;
    background:#e2e8f0;
    border-radius:99px;
    overflow:hidden;
}
.tm-countdown-bar {
    height:100%;
    background:linear-gradient(90deg,#3B82F6,#10B981);
    border-radius:99px;
    transition:width 1s linear;
}
.tm-timer-label {
    font-size:22px;
    font-weight:700;
    color:#1a2332;
    font-variant-numeric:tabular-nums;
}
@media(max-width:600px){ .tm-stats{flex-direction:column;} .tm-stat-value{font-size:22px;} }
</style>

<div class="tm-panel">
    <!-- Header -->
    <div class="tm-header">
        <div class="tm-title">
            <i class="fa fa-bar-chart"></i>
            <h4>Live Traffic Monitor</h4>
        </div>
        <div class="tm-meta">
            <div class="tm-badge" id="status-badge">
                <span class="dot"></span>
                <span id="status-text">Ready</span>
            </div>
            <div class="tm-badge" id="user-badge">
                <i class="fa fa-user" style="font-size:11px;color:#3498DB;"></i>
                <strong><?= $username ?></strong>
            </div>
            <div class="tm-badge" id="iface-badge" style="display:none;">
                <i class="fa fa-sitemap" style="font-size:11px;color:#8b5cf6;"></i>
                <span id="iface-text" style="font-family:monospace;font-size:11px;color:#1a2332;font-weight:600;"></span>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="tm-body">

        <!-- START SCREEN -->
        <div id="tm-start-screen" class="tm-start-wrap">
            <button class="tm-start-btn" id="tm-start-btn" onclick="startMonitor()">
                <i class="fa fa-play-circle"></i>
                Start Live Monitor
            </button>
            <div class="tm-countdown" id="tm-hint" style="color:#94a3b8;font-size:12px;">
                <i class="fa fa-info-circle"></i>
                Monitoring runs for 2 minutes per session
            </div>
        </div>

        <!-- COUNTDOWN BAR (shown while running) -->
        <div id="tm-countdown-wrap" style="display:none; margin-bottom:20px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:12px;color:#64748b;">Session time remaining</span>
                <span class="tm-timer-label" id="tm-timer">2:00</span>
            </div>
            <div class="tm-countdown-bar-wrap">
                <div class="tm-countdown-bar" id="tm-bar" style="width:100%;"></div>
            </div>
        </div>

        <!-- TRAFFIC CONTENT -->
        <div id="tm-content" style="display:none;">
            <div class="tm-stats">
                <div class="tm-stat-box dl">
                    <div class="tm-stat-icon"><i class="fa fa-arrow-down"></i></div>
                    <div class="tm-stat-info">
                        <div class="tm-stat-label">Download</div>
                        <div class="tm-stat-value"><span id="live-dl">0.00</span><small id="live-dl-unit">Mbps</small></div>
                        <div class="tm-stat-raw" id="live-dl-raw"></div>
                    </div>
                </div>
                <div class="tm-stat-box ul">
                    <div class="tm-stat-icon"><i class="fa fa-arrow-up"></i></div>
                    <div class="tm-stat-info">
                        <div class="tm-stat-label">Upload</div>
                        <div class="tm-stat-value"><span id="live-ul">0.00</span><small id="live-ul-unit">Mbps</small></div>
                        <div class="tm-stat-raw" id="live-ul-raw"></div>
                    </div>
                </div>
            </div>
            <div class="tm-chart-wrap">
                <canvas id="tmChart"></canvas>
            </div>
        </div>

        <!-- ALERTS -->
        <div id="tm-loading" class="tm-alert info" style="display:none;">
            <i class="fa fa-spinner fa-spin"></i>
            Finding active PPPoE interface...
        </div>
        <div id="tm-offline" class="tm-alert warn" style="display:none;">
            <i class="fa fa-exclamation-triangle"></i>
            <strong><?= $username ?></strong> is not connected. Cannot start monitor.
        </div>

        <!-- STOP BUTTON (shown while running) -->
        <div id="tm-stop-wrap" style="display:none; text-align:center; margin-top:16px;">
            <button class="tm-start-btn running" onclick="stopMonitor()">
                <i class="fa fa-stop-circle"></i>
                Stop
            </button>
        </div>

    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const USERNAME   = "<?= $username ?>";
    const WS_URL     = "ws://103.170.179.155:8081?username=" + encodeURIComponent(USERNAME);
    const MAX_PTS    = 60;
    const DURATION   = 120; // 2 minutes in seconds

    let chart    = null;
    let ws       = null;
    let timerInterval  = null;
    let secondsLeft    = DURATION;
    let prevIn   = null;
    let prevOut  = null;
    let prevTime = null;
    let isRunning = false;

    // ── Speed formatter: bps → Kbps → Mbps ──
    function formatSpeed(bps) {
        if (bps < 1000)       return { value: bps.toFixed(0),         unit: 'bps',  raw: '' };
        if (bps < 1000000)    return { value: (bps/1000).toFixed(2),  unit: 'Kbps', raw: bps.toFixed(0) + ' bps' };
        return { value: (bps/1000000).toFixed(2), unit: 'Mbps', raw: (bps/1000).toFixed(0) + ' Kbps' };
    }

    function setStatus(type, text) {
        const b = document.getElementById('status-badge');
        b.className = 'tm-badge ' + type;
        document.getElementById('status-text').textContent = text;
    }

    // ── Init Chart ──
    function initChart() {
        if (chart) { chart.destroy(); chart = null; }
        const ctx = document.getElementById('tmChart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    { label:'Download (Mbps)', borderColor:'#3B82F6', backgroundColor:'rgba(59,130,246,0.08)', data:[], fill:true, tension:0.4, borderWidth:2.5, pointRadius:0, pointHoverRadius:4 },
                    { label:'Upload (Mbps)',   borderColor:'#10B981', backgroundColor:'rgba(16,185,129,0.08)', data:[], fill:true, tension:0.4, borderWidth:2.5, pointRadius:0, pointHoverRadius:4 }
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false, animation:false,
                interaction:{ mode:'index', intersect:false },
                plugins:{
                    legend:{ position:'bottom', labels:{ boxWidth:12, boxHeight:12, font:{size:11}, color:'#64748b', padding:16 } },
                    tooltip:{ backgroundColor:'#1a2332', titleColor:'#94a3b8', bodyColor:'#fff', padding:10, cornerRadius:8,
                        callbacks:{ label: ctx => ' ' + ctx.dataset.label.split(' ')[0] + ': ' + parseFloat(ctx.raw).toFixed(2) + ' Mbps' } }
                },
                scales:{
                    x:{ grid:{color:'#f1f5f9', drawBorder:false}, ticks:{color:'#94a3b8', font:{size:10}, maxTicksLimit:8} },
                    y:{ beginAtZero:true, suggestedMax:1, grid:{color:'#f1f5f9', drawBorder:false},
                        ticks:{ color:'#94a3b8', font:{size:10},
                            callback: v => { const b=v*1000000; return formatSpeed(b).value+' '+formatSpeed(b).unit; }
                        }
                    }
                }
            }
        });
    }

    // ── Countdown Timer ──
    function startCountdown() {
        secondsLeft = DURATION;
        updateTimer();

        timerInterval = setInterval(function() {
            secondsLeft--;
            updateTimer();

            if (secondsLeft <= 0) {
                stopMonitor();
            }
        }, 1000);
    }

    function updateTimer() {
        const mins = Math.floor(secondsLeft / 60);
        const secs = secondsLeft % 60;
        document.getElementById('tm-timer').textContent =
            mins + ':' + String(secs).padStart(2, '0');

        // Progress bar
        const pct = (secondsLeft / DURATION) * 100;
        document.getElementById('tm-bar').style.width = pct + '%';

        // Color change when low time
        const bar = document.getElementById('tm-bar');
        if (secondsLeft <= 30) {
            bar.style.background = 'linear-gradient(90deg, #ef4444, #f97316)';
        } else if (secondsLeft <= 60) {
            bar.style.background = 'linear-gradient(90deg, #f97316, #eab308)';
        } else {
            bar.style.background = 'linear-gradient(90deg, #3B82F6, #10B981)';
        }
    }

    // ── Start Monitor ──
    window.startMonitor = function() {
        if (isRunning) return;
        isRunning = true;

        // Show loading
        document.getElementById('tm-start-screen').style.display = 'none';
        document.getElementById('tm-loading').style.display = '';
        document.getElementById('tm-offline').style.display = 'none';
        setStatus('', 'Connecting...');

        // Reset chart data
        prevIn = null; prevOut = null; prevTime = null;

        // Connect WebSocket
        connectWS();
    };

    // ── Stop Monitor ──
    window.stopMonitor = function() {
        isRunning = false;

        // Clear timer
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }

        // Close WebSocket
        if (ws) { ws.onclose = null; ws.close(); ws = null; }

        // Hide content, show start screen again
        document.getElementById('tm-content').style.display = 'none';
        document.getElementById('tm-countdown-wrap').style.display = 'none';
        document.getElementById('tm-stop-wrap').style.display = 'none';
        document.getElementById('tm-loading').style.display = 'none';
        document.getElementById('tm-iface-badge') && (document.getElementById('iface-badge').style.display = 'none');

        // Reset values
        document.getElementById('live-dl').textContent = '0.00';
        document.getElementById('live-ul').textContent = '0.00';
        document.getElementById('live-dl-unit').textContent = 'Mbps';
        document.getElementById('live-ul-unit').textContent = 'Mbps';
        document.getElementById('live-dl-raw').textContent = '';
        document.getElementById('live-ul-raw').textContent = '';

        // Show start button again
        document.getElementById('tm-start-screen').style.display = '';
        document.getElementById('tm-hint').innerHTML =
            '<i class="fa fa-check-circle" style="color:#10b981"></i> Session ended. Click to start again.';

        setStatus('offline', 'Stopped');
    };

    // ── WebSocket Connect ──
    function connectWS() {
        ws = new WebSocket(WS_URL);

        ws.onopen = function() {
            setStatus('', 'Connecting...');
        };

        ws.onmessage = function(event) {
            let data;
            try { data = JSON.parse(event.data); } catch(e) { return; }

            if (data.status === 'error') {
                setStatus('offline', 'Offline');
                document.getElementById('tm-loading').style.display = 'none';
                document.getElementById('tm-offline').style.display = '';
                isRunning = false;
                // Show start button
                setTimeout(function() {
                    document.getElementById('tm-offline').style.display = 'none';
                    document.getElementById('tm-start-screen').style.display = '';
                }, 3000);
                return;
            }

            if (data.status === 'success') {
                // First success - show chart and start timer
                if (!chart) {
                    document.getElementById('tm-loading').style.display = 'none';
                    document.getElementById('tm-content').style.display = '';
                    document.getElementById('tm-countdown-wrap').style.display = '';
                    document.getElementById('tm-stop-wrap').style.display = '';
                    setStatus('online', 'Online');

                    if (data.interface) {
                        document.getElementById('iface-badge').style.display = '';
                        document.getElementById('iface-text').textContent = data.interface;
                    }

                    initChart();
                    startCountdown();
                }

                // Calculate speed
                const now  = Date.now();
                const inB  = data.input_bytes;
                const outB = data.output_bytes;

                if (prevTime !== null) {
                    const dt     = (now - prevTime) / 1000;
                    const dlBps  = ((outB - prevOut) * 8) / dt;
                    const ulBps  = ((inB  - prevIn)  * 8) / dt;

                    if (dlBps >= 0 && dlBps < 1e9) {
                        const dl = formatSpeed(dlBps);
                        document.getElementById('live-dl').textContent = dl.value;
                        document.getElementById('live-dl-unit').textContent = dl.unit;
                        document.getElementById('live-dl-raw').textContent = dl.raw;
                        chart.data.datasets[0].data.push((dlBps/1000000).toFixed(4));
                    }
                    if (ulBps >= 0 && ulBps < 1e9) {
                        const ul = formatSpeed(ulBps);
                        document.getElementById('live-ul').textContent = ul.value;
                        document.getElementById('live-ul-unit').textContent = ul.unit;
                        document.getElementById('live-ul-raw').textContent = ul.raw;
                        chart.data.datasets[1].data.push((ulBps/1000000).toFixed(4));
                    }

                    const label = new Date().toLocaleTimeString('en-US', {
                        hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit'
                    });
                    chart.data.labels.push(label);

                    if (chart.data.labels.length > MAX_PTS) {
                        chart.data.labels.shift();
                        chart.data.datasets[0].data.shift();
                        chart.data.datasets[1].data.shift();
                    }
                    chart.update('none');
                }

                prevIn = inB; prevOut = outB; prevTime = now;
            }
        };

        ws.onerror = function() {
            if (isRunning) setStatus('offline', 'Connection Error');
        };

        ws.onclose = function() {
            if (isRunning) {
                setStatus('', 'Reconnecting...');
                setTimeout(connectWS, 3000);
            }
        };
    }

})();
</script>

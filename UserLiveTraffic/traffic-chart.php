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
.tm-title h4 { margin:0; font-size:15px; font-weight:600; color:#1a2332; letter-spacing:0.3px; }
.tm-title i { color:#3498DB; font-size:16px; }
.tm-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.tm-badge { display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:4px 12px; }
.tm-badge .dot { width:7px; height:7px; border-radius:50%; background:#94a3b8; }
.tm-badge.online .dot { background:#10b981; box-shadow:0 0 0 2px rgba(16,185,129,0.2); animation:pulse 2s infinite; }
.tm-badge.offline .dot { background:#ef4444; }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 2px rgba(16,185,129,0.2);} 50%{box-shadow:0 0 0 4px rgba(16,185,129,0.1);} }
.tm-badge strong { color:#1a2332; font-weight:600; }
.tm-body { padding:20px 24px; }
.tm-stats { display:flex; gap:16px; margin-bottom:20px; }
.tm-stat-box { flex:1; border-radius:10px; padding:16px 20px; display:flex; align-items:center; gap:14px; }
.tm-stat-box.dl { background:linear-gradient(135deg,#EBF5FF 0%,#DBEAFE 100%); border:1px solid #BFDBFE; }
.tm-stat-box.ul { background:linear-gradient(135deg,#ECFDF5 0%,#D1FAE5 100%); border:1px solid #A7F3D0; }
.tm-stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.dl .tm-stat-icon { background:rgba(52,152,219,0.15); color:#2980b9; }
.ul .tm-stat-icon { background:rgba(16,185,129,0.15); color:#059669; }
.tm-stat-info { flex:1; }
.tm-stat-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.8px; color:#64748b; margin-bottom:2px; }
.tm-stat-value { font-size:26px; font-weight:700; line-height:1; }
.tm-stat-value small { font-size:12px; font-weight:500; color:#64748b; margin-left:3px; }
.tm-stat-unit { font-size:11px; color:#94a3b8; margin-top:3px; }
.dl .tm-stat-value { color:#2563EB; }
.ul .tm-stat-value { color:#059669; }
.tm-chart-wrap { position:relative; height:220px; }
.tm-alert { border-radius:8px; padding:14px 18px; display:flex; align-items:center; gap:10px; font-size:13px; }
.tm-alert.info { background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; }
.tm-alert.warn { background:#FFFBEB; color:#92400E; border:1px solid #FDE68A; }
.tm-alert.err  { background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; }
@media(max-width:600px){ .tm-stats{flex-direction:column;} .tm-stat-value{font-size:22px;} }
</style>

<div class="tm-panel">
    <div class="tm-header">
        <div class="tm-title">
            <i class="fa fa-bar-chart"></i>
            <h4>Live Traffic Monitor</h4>
        </div>
        <div class="tm-meta">
            <div class="tm-badge" id="status-badge">
                <span class="dot"></span>
                <span id="status-text">Detecting...</span>
            </div>
            <div class="tm-badge" id="user-badge" style="display:none;">
                <i class="fa fa-user" style="font-size:11px;color:#3498DB;"></i>
                <strong><?= $username ?></strong>
            </div>
            <div class="tm-badge" id="iface-badge" style="display:none;">
                <i class="fa fa-sitemap" style="font-size:11px;color:#8b5cf6;"></i>
                <span id="iface-text" style="font-family:monospace;font-size:11px;color:#1a2332;font-weight:600;"></span>
            </div>
        </div>
    </div>

    <div class="tm-body">
        <div id="tm-loading" class="tm-alert info">
            <i class="fa fa-spinner fa-spin"></i>
            Finding active PPPoE session for <strong><?= $username ?></strong>...
        </div>
        <div id="tm-offline" class="tm-alert warn" style="display:none;">
            <i class="fa fa-exclamation-triangle"></i>
            <span><strong><?= $username ?></strong> is not connected. Auto-refreshing...</span>
        </div>
        <div id="tm-error" class="tm-alert err" style="display:none;">
            <i class="fa fa-times-circle"></i>
            <span id="tm-error-msg"></span>
        </div>
        <div id="tm-content" style="display:none;">
            <div class="tm-stats">
                <div class="tm-stat-box dl">
                    <div class="tm-stat-icon"><i class="fa fa-arrow-down"></i></div>
                    <div class="tm-stat-info">
                        <div class="tm-stat-label">Download</div>
                        <div class="tm-stat-value"><span id="live-dl">0.00</span><small id="live-dl-unit">Mbps</small></div>
                        <div class="tm-stat-unit" id="live-dl-raw"></div>
                    </div>
                </div>
                <div class="tm-stat-box ul">
                    <div class="tm-stat-icon"><i class="fa fa-arrow-up"></i></div>
                    <div class="tm-stat-info">
                        <div class="tm-stat-label">Upload</div>
                        <div class="tm-stat-value"><span id="live-ul">0.00</span><small id="live-ul-unit">Mbps</small></div>
                        <div class="tm-stat-unit" id="live-ul-raw"></div>
                    </div>
                </div>
            </div>
            <div class="tm-chart-wrap">
                <canvas id="tmChart"></canvas>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const USERNAME = "<?= $username ?>";
    const WS_URL   = "ws://103.111.39.174:8081?username=" + encodeURIComponent(USERNAME);
    const MAX_PTS  = 60;

    let chart = null, prevIn = null, prevOut = null, prevTime = null;

    // ============================================================
    // AUTO-SCALE: bps → Kbps → Mbps
    // ============================================================
    function formatSpeed(bitsPerSec) {
        if (bitsPerSec < 1000) {
            return { value: bitsPerSec.toFixed(0), unit: 'bps', raw: '' };
        } else if (bitsPerSec < 1000000) {
            const kbps = bitsPerSec / 1000;
            return { value: kbps.toFixed(2), unit: 'Kbps', raw: bitsPerSec.toFixed(0) + ' bps' };
        } else {
            const mbps = bitsPerSec / 1000000;
            const kbps = (bitsPerSec / 1000).toFixed(0);
            return { value: mbps.toFixed(2), unit: 'Mbps', raw: kbps + ' Kbps' };
        }
    }

    function setStatus(type, text) {
        const badge = document.getElementById('status-badge');
        badge.className = 'tm-badge ' + type;
        document.getElementById('status-text').textContent = text;
    }

    function showSection(id) {
        ['tm-loading','tm-offline','tm-error','tm-content'].forEach(function(s) {
            document.getElementById(s).style.display = (s === id) ? '' : 'none';
        });
    }

    function initChart() {
        const ctx = document.getElementById('tmChart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Download',
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59,130,246,0.08)',
                        data: [], fill: true, tension: 0.4,
                        borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4
                    },
                    {
                        label: 'Upload',
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16,185,129,0.08)',
                        data: [], fill: true, tension: 0.4,
                        borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, boxHeight: 12, font: { size: 11 }, color: '#64748b', padding: 16 }
                    },
                    tooltip: {
                        backgroundColor: '#1a2332',
                        titleColor: '#94a3b8',
                        bodyColor: '#fff',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                const v = parseFloat(ctx.raw);
                                return ' ' + ctx.dataset.label + ': ' + formatSpeed(v * 1000000).value + ' ' + formatSpeed(v * 1000000).unit;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#f1f5f9', drawBorder: false },
                        ticks: { color: '#94a3b8', font: { size: 10 }, maxTicksLimit: 8 }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: 1,
                        grid: { color: '#f1f5f9', drawBorder: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10 },
                            callback: function(v) {
                                const bps = v * 1000000;
                                return formatSpeed(bps).value + ' ' + formatSpeed(bps).unit;
                            }
                        }
                    }
                }
            }
        });
    }

    function connectWS() {
        const ws = new WebSocket(WS_URL);

        ws.onopen = function() { setStatus('', 'Connecting...'); };

        ws.onmessage = function(event) {
            let data;
            try { data = JSON.parse(event.data); } catch(e) { return; }

            if (data.status === 'error') {
                setStatus('offline', 'Offline');
                showSection('tm-offline');
                return;
            }

            if (data.status === 'success') {
                if (!chart) {
                    showSection('tm-content');
                    setStatus('online', 'Online');
                    document.getElementById('user-badge').style.display = '';
                    if (data.interface) {
                        document.getElementById('iface-badge').style.display = '';
                        document.getElementById('iface-text').textContent = data.interface;
                    }
                    initChart();
                }

                const now  = Date.now();
                const inB  = data.input_bytes;
                const outB = data.output_bytes;

                if (prevTime !== null) {
                    const dt      = (now - prevTime) / 1000;
                    const dlBps   = ((outB - prevOut) * 8) / dt;
                    const ulBps   = ((inB  - prevIn)  * 8) / dt;

                    if (dlBps >= 0 && dlBps < 1e9) {
                        const dl = formatSpeed(dlBps);
                        document.getElementById('live-dl').textContent = dl.value;
                        document.getElementById('live-dl-unit').textContent = dl.unit;
                        document.getElementById('live-dl-raw').textContent = dl.raw;
                        // Store in Mbps for chart (consistent unit)
                        chart.data.datasets[0].data.push((dlBps / 1000000).toFixed(4));
                    }

                    if (ulBps >= 0 && ulBps < 1e9) {
                        const ul = formatSpeed(ulBps);
                        document.getElementById('live-ul').textContent = ul.value;
                        document.getElementById('live-ul-unit').textContent = ul.unit;
                        document.getElementById('live-ul-raw').textContent = ul.raw;
                        chart.data.datasets[1].data.push((ulBps / 1000000).toFixed(4));
                    }

                    const label = new Date().toLocaleTimeString('en-US', {
                        hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit'
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

        ws.onerror = function() { setStatus('offline', 'Connection Lost'); };

        ws.onclose = function() {
            setStatus('', 'Reconnecting...');
            chart = null; prevIn = null; prevOut = null; prevTime = null;
            setTimeout(connectWS, 5000);
        };
    }

    connectWS();
})();
</script>

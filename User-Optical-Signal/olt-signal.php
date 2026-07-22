<?php
// Huawei & VSOL Multi-OLT Signal Checker Module
$current_username = isset($user->username) ?$user->username : (isset($username) ?$username : '');
?>

<div class="col-md-12" style="margin-top: 15px; margin-bottom: 15px;">
    <div style="background:#0f172a; color:#fff; padding:15px; border-radius:8px; border:1px solid #1e293b;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h5 style="margin:0; color:#38bdf8; font-weight:bold;">⚡ Live OLT Optical Power</h5>
            <button id="btn-check-olt-signal" onclick="fetchOltSignal('<?php echo $current_username; ?>')" class="btn btn-sm btn-info" style="padding:5px 12px; font-weight:bold; cursor:pointer;">
                Check Live Signal
            </button>
        </div>
        
        <div id="olt-signal-output" style="margin-top:12px; display:none;"></div>
    </div>
</div>

<script>
function fetchOltSignal(username) {
    var btn = document.getElementById('btn-check-olt-signal');
    var outputDiv = document.getElementById('olt-signal-output');
    
    if (!username) {
        var userField = document.querySelector('input[name="username"]') || document.querySelector('.username-holder');
        if (userField) username = userField.value || userField.innerText;
    }

    if (!username) {
        alert("Username detect nahi ho saka.");
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'Connecting OLT...';
    outputDiv.style.display = 'block';
    outputDiv.innerHTML = '<span style="color:#fde047; font-size:13px;">SSH Connecting to OLTs... Wait 4-8 seconds.</span>';

    fetch('/get_olt_signal.php?user=' + encodeURIComponent(username))
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = 'Refresh Signal';

            if (data.status === 'success') {
                var rxNum = parseFloat(data.rx_ont);
                var statusColor = '#22c55e'; // Green
                var statusLabel = 'Good Signal';

                // GPON (ITU-T G.984/G.987) international standard ONU Rx range: -8 dBm to -27/-28 dBm
                if (isNaN(rxNum) || data.rx_ont === 'N/A') {
                    statusColor = '#94a3b8'; // Offline
                    statusLabel = 'Device Offline / No Signal';
                } else if (rxNum > -8.0) {
                    statusColor = '#ef4444'; // Red - signal too strong, saturation risk
                    statusLabel = 'Signal Too High (Saturation Risk)';
                } else if (rxNum < -27.0) {
                    statusColor = '#ef4444'; // Red - signal too weak
                    statusLabel = 'Critical Signal (Too Low)';
                } else if (rxNum < -24.0) {
                    statusColor = '#eab308'; // Yellow - approaching low limit
                    statusLabel = 'Low Signal (Warning)';
                }

                outputDiv.innerHTML = `
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:10px; margin-top:8px; text-align:center;">
                        <div style="background:#1e293b; padding:8px; border-radius:6px; border:1px solid #334155;">
                            <small style="color:#94a3b8; display:block; font-size:11px;">PON Location</small>
                            <strong style="color:#f8fafc; font-size:12px;">${data.location}</strong>
                        </div>
                        <div style="background:#1e293b; padding:8px; border-radius:6px; border:1px solid ${statusColor};">
                            <small style="color:#94a3b8; display:block; font-size:11px;">ONU Rx Power</small>
                            <strong style="color:${statusColor}; font-size:15px;">${data.rx_ont} dBm</strong>
                            <small style="display:block; color:${statusColor}; font-size:10px;">(${statusLabel})</small>
                        </div>
                        <div style="background:#1e293b; padding:8px; border-radius:6px; border:1px solid #334155;">
                            <small style="color:#94a3b8; display:block; font-size:11px;">ONU Tx Power</small>
                            <strong style="color:#f8fafc; font-size:13px;">${data.tx_ont} dBm</strong>
                        </div>
                        <div style="background:#1e293b; padding:8px; border-radius:6px; border:1px solid #334155;">
                            <small style="color:#94a3b8; display:block; font-size:11px;">OLT Rx Power</small>
                            <strong style="color:#f8fafc; font-size:13px;">${data.rx_olt} dBm</strong>
                        </div>
                    </div>
                `;
            } else {
                outputDiv.innerHTML = '<span style="color:#ef4444; font-size:13px;">Error: ' + data.message + '</span>';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = 'Check Live Signal';
            outputDiv.innerHTML = '<span style="color:#ef4444; font-size:13px;">Failed to fetch signal from backend.</span>';
        });
}
</script>
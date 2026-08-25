<?php
session_start();
if (!isset($_SESSION['custom_logged_in']) || $_SESSION['custom_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$operator_name = $_SESSION['custom_name'] ?? $_SESSION['custom_username'] ?? 'Operator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zalpro Billing Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #1b204f;
            --blue: #2079b0;
            --bg: #f0f4f8;
            --muted: #718096;
            --border: #e8edf3;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: #27324a;
            font-family: 'Poppins', sans-serif;
        }
        .page { padding: 24px; max-width: 1500px; margin: 0 auto; }
        .hero {
            background: linear-gradient(135deg, var(--navy), var(--blue));
            color: #fff;
            border-radius: 18px;
            padding: 26px 28px;
            box-shadow: 0 8px 25px rgba(27,32,79,.12);
            position: relative;
            overflow: hidden;
        }
        .hero:after {
            content: '';
            position: absolute;
            width: 260px; height: 260px;
            border: 45px solid rgba(255,255,255,.06);
            border-radius: 50%;
            right: -80px; top: -120px;
        }
        .hero h2 { font-size: 24px; font-weight: 700; margin: 0 0 6px; }
        .hero p { margin: 0; opacity: .86; font-size: 13px; }
        .operator {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 18px; padding: 8px 12px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 10px; font-size: 12px;
        }
        .section-title { color: var(--navy); font-size: 17px; font-weight: 600; margin: 28px 0 14px; }
        .quick-card {
            display: block; height: 100%; text-decoration: none; color: inherit;
            background: #fff; border: 1px solid var(--border); border-radius: 15px;
            padding: 20px; transition: .2s ease; box-shadow: 0 4px 16px rgba(0,0,0,.025);
        }
        .quick-card:hover { transform: translateY(-3px); border-color: rgba(32,121,176,.28); box-shadow: 0 9px 24px rgba(32,121,176,.10); }
        .icon-box {
            width: 46px; height: 46px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 21px;
            background: #e9f2ff; color: var(--blue); margin-bottom: 15px;
        }
        .quick-card h5 { font-size: 15px; font-weight: 600; margin-bottom: 5px; color: var(--navy); }
        .quick-card p { font-size: 12px; color: var(--muted); margin: 0; line-height: 1.6; }
        .panel { background:#fff; border:1px solid var(--border); border-radius:15px; padding:20px; box-shadow:0 4px 16px rgba(0,0,0,.025); }
        .panel-head { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:16px; }
        .panel-head h5 { margin:0; color:var(--navy); font-size:15px; font-weight:600; }
        .status-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #f1f3f6; font-size:13px; }
        .status-row:last-child { border-bottom:0; padding-bottom:0; }
        .status-dot { width:9px; height:9px; border-radius:50%; background:#20a464; box-shadow:0 0 0 4px rgba(32,164,100,.10); flex:0 0 auto; }
        .status-label { color:#596579; }
        .status-value { margin-left:auto; font-weight:600; color:var(--navy); }
        .footer-note { text-align:center; color:#8a95a6; font-size:11px; margin-top:24px; }

        .expiry-panel { margin-top: 28px; }
        .expiry-toolbar {
            display:flex; flex-wrap:wrap; align-items:center; gap:10px;
            padding:14px; background:#f7f9fc; border:1px solid #edf1f5;
            border-radius:12px; margin-bottom:16px;
        }
        .expiry-toolbar .form-label { font-size:12px; color:#596579; margin:0 0 4px; }
        .expiry-date-wrap { min-width:210px; }
        .expiry-date-wrap input {
            border:1px solid #dfe6ee; border-radius:9px; padding:9px 11px;
            font-size:13px; color:#27324a; background:#fff; width:100%;
        }
        .expiry-btn {
            border:0; border-radius:9px; padding:9px 13px; font-size:12px;
            font-weight:600; cursor:pointer;
        }
        .expiry-btn-primary { background:var(--navy); color:#fff; }
        .expiry-btn-light { background:#e9f2ff; color:var(--blue); }
        .expiry-summary {
            display:flex; align-items:center; gap:10px; margin-left:auto;
            font-size:12px; color:#718096;
        }
        .expiry-count {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:32px; height:28px; padding:0 8px; border-radius:8px;
            background:#fff0f0; color:#c53030; font-weight:700;
        }
        .expiry-table-wrap { overflow-x:auto; }
        .expiry-table { width:100%; border-collapse:collapse; min-width:650px; }
        .expiry-table th {
            text-align:left; padding:11px 10px; background:#f7f9fc;
            color:#718096; font-size:11px; font-weight:600; text-transform:uppercase;
            border-bottom:1px solid #e8edf3;
        }
        .expiry-table td {
            padding:12px 10px; border-bottom:1px solid #f1f3f6;
            font-size:12px; color:#27324a;
        }
        .expiry-table tr:last-child td { border-bottom:0; }
        .expiry-user { font-weight:600; color:var(--navy); }
        .expiry-badge {
            display:inline-block; padding:4px 8px; border-radius:999px;
            font-size:10px; font-weight:600;
        }
        .expiry-badge.active { background:#e7f8ef; color:#16834f; }
        .expiry-badge.today {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .expiry-badge.expired {
            background: #fee2e2;
            color: #b91c1c;
        }
        .expiry-badge.manual-expired {
            background: #fef3c7;
            color: #92400e;
        }
        .expiry-badge.disabled { background:#edf0f4; color:#687386; }
        .expiry-empty {
            text-align:center; padding:28px 12px; color:#8a95a6; font-size:12px;
        }
        .expiry-loading { text-align:center; padding:24px 12px; color:#718096; font-size:12px; }
        .expiry-error {
            margin:0; padding:12px; border-radius:9px; background:#fff5f5;
            color:#c53030; font-size:12px; border:1px solid #fed7d7;
        }


        .history-panel { margin-top: 28px; }
        .history-toolbar {
            display:flex; flex-wrap:wrap; align-items:center; gap:10px;
            padding:14px; background:#f7f9fc; border:1px solid #edf1f5;
            border-radius:12px; margin-bottom:16px;
        }
        .history-toolbar input {
            border:1px solid #dfe6ee; border-radius:9px; padding:9px 11px;
            font-size:13px; color:#27324a; background:#fff; min-width:220px;
        }
        .history-btn {
            border:0; border-radius:9px; padding:9px 13px; font-size:12px;
            font-weight:600; cursor:pointer;
        }
        .history-btn-primary { background:var(--navy); color:#fff; }
        .history-btn-light { background:#e9f2ff; color:var(--blue); }
        .history-table-wrap { overflow-x:auto; }
        .history-table { width:100%; border-collapse:collapse; min-width:950px; }
        .history-table th {
            text-align:left; padding:11px 10px; background:#f7f9fc;
            color:#718096; font-size:11px; font-weight:600; text-transform:uppercase;
            border-bottom:1px solid #e8edf3;
        }
        .history-table td {
            padding:12px 10px; border-bottom:1px solid #f1f3f6;
            font-size:12px; color:#27324a; vertical-align:middle;
        }
        .history-table tr:last-child td { border-bottom:0; }
        .history-user { font-weight:600; color:var(--navy); }
        .history-old { color:#718096; }
        .history-new { color:var(--navy); font-weight:600; }
        .history-arrow { color:#2079b0; padding:0 5px; }
        .history-badge {
            display:inline-block; padding:4px 8px; border-radius:999px;
            font-size:10px; font-weight:700; text-transform:capitalize;
        }
        .history-badge.upgrade { background:#e7f8ef; color:#16834f; }
        .history-badge.downgrade { background:#fff4e5; color:#b35b00; }
        .history-badge.change { background:#e9f2ff; color:#2079b0; }
        .history-badge.initial { background:#f0eafe; color:#6941c6; }
        .history-badge.renewal { background:#e7f8ef; color:#16834f; }
        .history-badge.system { background:#edf0f4; color:#687386; }
        .history-empty, .history-loading {
            text-align:center; padding:28px 12px; color:#8a95a6; font-size:12px;
        }
        .history-error {
            margin:0; padding:12px; border-radius:9px; background:#fff5f5;
            color:#c53030; font-size:12px; border:1px solid #fed7d7;
        }
        .history-count {
            margin-left:auto; font-size:12px; color:#718096;
        }

        @media(max-width:768px){ .page{padding:14px;} .hero{padding:22px;} .hero h2{font-size:20px;} }

        /* Safe motion animation - CSS only, existing PHP/JS untouched */
        @keyframes npPageIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        @keyframes npCardIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        .hero { animation: npPageIn .45s ease-out both; }
        .section-title { animation: npPageIn .45s ease-out both; }
        .quick-card { animation: npCardIn .45s ease-out both; }
        .quick-card:nth-child(1) { animation-delay:.05s; }
        .quick-card:nth-child(2) { animation-delay:.10s; }
        .quick-card:nth-child(3) { animation-delay:.15s; }
        .quick-card:nth-child(4) { animation-delay:.20s; }
        .expiry-panel, .history-panel { animation: npCardIn .5s ease-out .15s both; }
        .status-row { animation: npPageIn .4s ease-out both; }
        .status-row:nth-child(2) { animation-delay:.05s; }
        .status-row:nth-child(3) { animation-delay:.10s; }
        .status-row:nth-child(4) { animation-delay:.15s; }
        .status-row:nth-child(5) { animation-delay:.20s; }
        .quick-card, .expiry-btn, .history-btn { transition: transform .2s ease, box-shadow .2s ease; }
        .quick-card:hover { transform: translateY(-3px); }
        .expiry-btn:hover, .history-btn:hover { transform: translateY(-1px); }
        .icon-box { transition: transform .25s ease; }
        .quick-card:hover .icon-box { transform: translateY(-2px) scale(1.04); }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation:none !important; transition:none !important; } }

    </style>
</head>
<body>
<div class="page">
    <section class="hero">
        <h2>Welcome to Zalpro Billing Portal</h2>
        <p>Manage subscribers, monthly billing, collections and financial records from one place.</p>
        <div class="operator"><i class="bi bi-person-circle"></i> Logged in as <strong><?= htmlspecialchars($operator_name) ?></strong></div>
    </section>

    <h4 class="section-title">Quick Actions</h4>
    <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
            <a class="quick-card" href="user_collection.php" target="contentFrame">
                <div class="icon-box"><i class="bi bi-people-fill"></i></div>
                <h5>User Collections</h5>
                <p>Find a customer, view dues, receive payments and manage billing.</p>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="quick-card" href="bill_creator.php" target="contentFrame">
                <div class="icon-box"><i class="bi bi-receipt-cutoff"></i></div>
                <h5>Bill Creator</h5>
                <p>Generate monthly bills for the selected billing period.</p>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="quick-card" href="collection_report.php" target="contentFrame">
                <div class="icon-box"><i class="bi bi-bar-chart-line-fill"></i></div>
                <h5>Financial Reports</h5>
                <p>Review collection activity and payment records.</p>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="quick-card" href="packages.php" target="contentFrame">
                <div class="icon-box"><i class="bi bi-box-seam-fill"></i></div>
                <h5>Internet Packages</h5>
                <p>Review available packages and their billing configuration.</p>
            </a>
        </div>
    </div>


    <section class="panel expiry-panel">
        <div class="panel-head">
            <h5><i class="bi bi-calendar2-event-fill me-2 text-danger"></i>Expiry Schedule</h5>
            <div class="expiry-summary d-flex align-items-center gap-2 flex-wrap">
                <span>Today's Expiry:</span>
                <span id="expiryCount" class="expiry-count">0</span>
                <span class="text-muted">•</span>
                <span>Expired:</span>
                <span id="expiryExpiredCount" class="expiry-count" style="background:#fee2e2;color:#b91c1c;">0</span>
            </div>
        </div>

        <div class="expiry-toolbar">
            <div class="expiry-date-wrap">
                <label class="form-label" for="expiryDate">Select expiry date</label>
                <input type="date" id="expiryDate">
            </div>
            <button type="button" class="expiry-btn expiry-btn-primary" id="expiryLoadBtn">
                <i class="bi bi-search me-1"></i> View Users
            </button>
            <button type="button" class="expiry-btn expiry-btn-light" id="expiryTodayBtn">
                <i class="bi bi-calendar-day me-1"></i> Today
            </button>
            <button type="button" class="expiry-btn expiry-btn-light" id="expiryTomorrowBtn">
                <i class="bi bi-calendar-plus me-1"></i> Tomorrow
            </button>
        </div>

        <div class="expiry-table-wrap">
            <div id="expiryResult" class="expiry-loading">Loading today's expiry list...</div>
        </div>
    </section>


    <section class="panel history-panel" id="packageHistorySection">
        <div class="panel-head">
            <h5><i class="bi bi-clock-history me-2 text-primary"></i>Package History</h5>
            <span id="historyCount" class="history-count">0 records</span>
        </div>

        <div class="history-toolbar">
            <input type="text" id="historyUsername" placeholder="Filter by username (optional)">
            <button type="button" class="history-btn history-btn-primary" id="historyLoadBtn">
                <i class="bi bi-search me-1"></i> Load History
            </button>
            <button type="button" class="history-btn history-btn-light" id="historyAllBtn">
                <i class="bi bi-list-ul me-1"></i> All Users
            </button>
        </div>

        <div class="history-table-wrap">
            <div id="historyResult" class="history-loading">Loading recent package history...</div>
        </div>
    </section>

    <h4 class="section-title">System Overview</h4>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel h-100">
                <div class="panel-head">
                    <h5><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Billing Workflow</h5>
                </div>
                <div class="status-row"><span class="status-dot"></span><span class="status-label">Customer search & account management</span><span class="status-value">Ready</span></div>
                <div class="status-row"><span class="status-dot"></span><span class="status-label">Monthly billing & ledger</span><span class="status-value">Ready</span></div>
                <div class="status-row"><span class="status-dot"></span><span class="status-label">Cash / EasyPaisa / Bank collections</span><span class="status-value">Ready</span></div>
                <div class="status-row"><span class="status-dot"></span><span class="status-label">Receipt & payment history</span><span class="status-value">Ready</span></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel h-100">
                <div class="panel-head">
                    <h5><i class="bi bi-shield-check me-2 text-success"></i>Session Security</h5>
                </div>
                <p class="mb-2" style="font-size:13px;color:#596579;line-height:1.7;">Your billing session is protected by the system's inactivity timeout. Keep the portal active while working.</p>
                <div class="mt-3 p-3 rounded-3" style="background:#f6f9fc;border:1px solid #edf1f5;">
                    <div style="font-size:12px;color:#8a95a6;">Portal</div>
                    <div style="font-weight:600;color:var(--navy);font-size:14px;">Zalpro Staff Billing</div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-note">Netpoint IT &amp; Communications Pvt. Ltd. &nbsp; &nbsp; Zalpro Billing System</div>
</div>

<script>
(function () {
    const dateInput = document.getElementById('expiryDate');
    const resultBox = document.getElementById('expiryResult');
    const countBox = document.getElementById('expiryCount');
    const loadBtn = document.getElementById('expiryLoadBtn');
    const todayBtn = document.getElementById('expiryTodayBtn');
    const tomorrowBtn = document.getElementById('expiryTomorrowBtn');

    if (!dateInput || !resultBox) return;

    function localDateString(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
        });
    }

    function niceDate(dateString) {
        const date = new Date(dateString + 'T00:00:00');
        return date.toLocaleDateString(undefined, {
            day: '2-digit', month: 'short', year: 'numeric'
        });
    }

    async function loadExpiryList() {
        const selectedDate = dateInput.value;
        if (!selectedDate) return;

        resultBox.innerHTML = '<div class="expiry-loading"><i class="bi bi-hourglass-split me-1"></i> Loading expiry users...</div>';
        countBox.textContent = '0';
        loadBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'expiry_list');
            formData.append('date', selectedDate);

            const response = await fetch('backend_api.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (!data.success) throw new Error(data.error || 'Unable to load expiry list.');

            countBox.textContent = data.count ?? 0;

            const expiredCountBox = document.getElementById('expiryExpiredCount');
            if (expiredCountBox) {
                const expiredCount = (data.users || []).filter(function (user) {
                    return user.status_class === 'expired' ||
                           user.status_class === 'manual-expired';
                }).length;
                expiredCountBox.textContent = expiredCount;
            }

            if (!data.users || data.users.length === 0) {
                resultBox.innerHTML =
                    '<div class="expiry-empty">' +
                    '<i class="bi bi-check-circle-fill text-success" style="font-size:24px;"></i>' +
                    '<div class="mt-2">No user is scheduled or marked expired on <strong>' +
                    escapeHtml(niceDate(selectedDate)) +
                    '</strong>.</div></div>';
                return;
            }

            const rows = data.users.map(function (user, index) {
                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td><span class="expiry-user">${escapeHtml(user.username)}</span></td>
                        <td>${escapeHtml(user.package_name)}</td>
                        <td>${escapeHtml(user.formatted_expiry)}</td>
                        <td>
                            <span class="expiry-badge ${escapeHtml(user.status_class)}">
                                ${escapeHtml(user.status_label)}
                            </span>
                        </td>
                    </tr>
                `;
            }).join('');

            resultBox.innerHTML = `
                <table class="expiry-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Package</th>
                            <th>Expiry Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        } catch (error) {
            resultBox.innerHTML =
                '<div class="expiry-error"><i class="bi bi-exclamation-triangle-fill me-1"></i>' +
                escapeHtml(error.message || 'Failed to load expiry list.') + '</div>';
        } finally {
            loadBtn.disabled = false;
        }
    }

    function setDateAndLoad(date) {
        dateInput.value = localDateString(date);
        loadExpiryList();
    }

    dateInput.value = localDateString(new Date());

    loadBtn.addEventListener('click', loadExpiryList);
    todayBtn.addEventListener('click', function () { setDateAndLoad(new Date()); });
    tomorrowBtn.addEventListener('click', function () {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        setDateAndLoad(tomorrow);
    });
    dateInput.addEventListener('change', loadExpiryList);


    async function loadPackageHistory(username) {
        const resultBox = document.getElementById('historyResult');
        const countBox = document.getElementById('historyCount');
        const loadBtn = document.getElementById('historyLoadBtn');

        if (!resultBox) return;

        resultBox.innerHTML = '<div class="history-loading"><i class="bi bi-hourglass-split me-1"></i> Loading package history...</div>';
        if (loadBtn) loadBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'package_history');
            formData.append('limit', '50');
            formData.append('username', username || '');

            const response = await fetch('backend_api.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (!data.success) throw new Error(data.error || 'Unable to load package history.');

            const rowsData = data.history || [];
            countBox.textContent = `${data.count ?? 0} record${(data.count ?? 0) === 1 ? '' : 's'}`;

            if (rowsData.length === 0) {
                resultBox.innerHTML =
                    '<div class="history-empty">' +
                    '<i class="bi bi-inbox" style="font-size:24px;"></i>' +
                    '<div class="mt-2">No package history records found.</div></div>';
                return;
            }

            const rows = rowsData.map(function (row) {
                const type = String(row.change_type || 'change').toLowerCase();
                const oldPkg = row.old_package_name
                    ? `${escapeHtml(row.old_package_name)} <small class="text-muted">(ID ${escapeHtml(row.old_package_id ?? '—')})</small>`
                    : '<span class="text-muted">—</span>';
                const newPkg = row.new_package_name
                    ? `${escapeHtml(row.new_package_name)} <small class="text-muted">(ID ${escapeHtml(row.new_package_id ?? '—')})</small>`
                    : '<span class="text-muted">—</span>';

                return `
                    <tr>
                        <td><span class="history-user">${escapeHtml(row.username)}</span></td>
                        <td>
                            <span class="history-old">${oldPkg}</span>
                            <span class="history-arrow">?</span>
                            <span class="history-new">${newPkg}</span>
                        </td>
                        <td>
                            Rs. ${Number(row.old_package_price || 0).toFixed(0)}
                            <span class="history-arrow">?</span>
                            Rs. ${Number(row.new_package_price || 0).toFixed(0)}
                        </td>
                        <td><span class="history-badge ${escapeHtml(type)}">${escapeHtml(type)}</span></td>
                        <td>${escapeHtml(row.changed_by || 'System')}</td>
                        <td>${escapeHtml(row.changed_at || '—')}</td>
                    </tr>
                `;
            }).join('');

            resultBox.innerHTML = `
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Package Change</th>
                            <th>Fee</th>
                            <th>Type</th>
                            <th>Changed By</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        } catch (error) {
            resultBox.innerHTML =
                '<div class="history-error"><i class="bi bi-exclamation-triangle-fill me-1"></i>' +
                escapeHtml(error.message || 'Failed to load package history.') + '</div>';
            if (countBox) countBox.textContent = '0 records';
        } finally {
            if (loadBtn) loadBtn.disabled = false;
        }
    }

    const historyLoadBtn = document.getElementById('historyLoadBtn');
    const historyAllBtn = document.getElementById('historyAllBtn');
    const historyUsername = document.getElementById('historyUsername');

    if (historyLoadBtn) {
        historyLoadBtn.addEventListener('click', function () {
            loadPackageHistory((historyUsername?.value || '').trim());
        });
    }

    if (historyAllBtn) {
        historyAllBtn.addEventListener('click', function () {
            if (historyUsername) historyUsername.value = '';
            loadPackageHistory('');
        });
    }

    loadPackageHistory('');
})();
</script>

</body>
</html>

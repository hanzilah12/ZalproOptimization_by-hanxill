<?php
session_start();
if (empty($_SESSION['custom_logged_in'])) {
    header('Location: login.php');
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

require_once '/zalpro-optimization/credentials/db_config.php';

$operator_name = $_SESSION['custom_username'] ?? 'Staff';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=zalpro;charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

$packages = [];
$total_users = 0;
$total_revenue = 0.0;

try {
    $stmt = $pdo->query("
        SELECT
            p.id,
            COALESCE(NULLIF(p.name,''), NULLIF(p.groupname,''), CONCAT('Package #', p.id)) AS package_name,
            COALESCE(fp.price, fp.cost, 0) AS price,
            COALESCE(u.user_count, 0) AS user_count
        FROM packages p
        LEFT JOIN f_packages fp ON fp.pkgid = p.id
        LEFT JOIN (
            SELECT package, COUNT(*) AS user_count
            FROM usersinfo
            WHERE package IS NOT NULL
              AND package <> 25
              AND (status IN (1,2) OR is_enabled = 1)
            GROUP BY package
        ) u ON u.package = p.id
        ORDER BY COALESCE(NULLIF(p.name,''), NULLIF(p.groupname,''), CONCAT('Package #', p.id))
    ");
    $packages = $stmt->fetchAll();

    foreach ($packages as &$pkg) {
        $pkg['price'] = (float)$pkg['price'];
        $pkg['user_count'] = (int)$pkg['user_count'];
        $pkg['monthly_revenue'] = $pkg['price'] * $pkg['user_count'];
        $total_users += $pkg['user_count'];
        $total_revenue += $pkg['monthly_revenue'];
    }
    unset($pkg);
} catch (Exception $e) {
    $packages = [];
}

$total_packages = count($packages);
$used_packages = count(array_filter($packages, fn($p) => $p['user_count'] > 0));

/* AJAX: package subscribers */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'users') {
    header('Content-Type: application/json; charset=utf-8');
    $pkgid = (int)($_GET['pkgid'] ?? 0);

    if ($pkgid <= 0) {
        echo json_encode(['success'=>false, 'error'=>'Invalid package.']);
        exit;
    }

    try {
        $s = $pdo->prepare("
            SELECT username, status, is_enabled, current_expiration_date
            FROM usersinfo
            WHERE package = ?
            ORDER BY username ASC
        ");
        $s->execute([$pkgid]);
        $users = $s->fetchAll();

        foreach ($users as &$u) {
            $u['status_label'] = ((int)$u['status'] === 1 || (int)$u['status'] === 2) && (int)$u['is_enabled'] === 1
                ? 'Active' : 'Disabled';
            $u['expiry'] = !empty($u['current_expiration_date'])
                ? date('d-M-Y', strtotime($u['current_expiration_date']))
                : 'N/A';
        }
        unset($u);

        echo json_encode(['success'=>true, 'users'=>$users]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'error'=>'Unable to load subscribers.']);
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Internet Packages | Zalpro</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#1b204f;--blue:#2079b0;--bg:#f0f4f8;--muted:#697586;--line:#e7ebf1}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Poppins,Arial,sans-serif;color:#202735}
.page{padding:26px;max-width:1500px;margin:auto}
.hero{background:linear-gradient(135deg,var(--navy),var(--blue));border-radius:18px;padding:28px;color:#fff;box-shadow:0 12px 28px rgba(27,32,79,.14)}
.hero h2{font-size:24px;font-weight:700;margin:0 0 5px}
.hero p{margin:0;color:rgba(255,255,255,.82);font-size:13px}
.hero .operator{display:inline-flex;gap:8px;align-items:center;margin-top:16px;padding:8px 12px;border-radius:10px;background:rgba(255,255,255,.12);font-size:12px}
.stats{margin-top:18px}
.stat{background:#fff;border:1px solid var(--line);border-radius:15px;padding:18px;box-shadow:0 5px 18px rgba(24,39,75,.05);height:100%}
.stat .icon{width:42px;height:42px;border-radius:12px;background:#e9f2ff;color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:20px}
.stat .label{font-size:12px;color:var(--muted);margin-top:12px}
.stat .value{font-size:24px;font-weight:700;color:var(--navy);margin-top:2px}
.toolbar{margin:24px 0 16px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.toolbar h4{margin:0;color:var(--navy);font-size:18px;font-weight:700}
.search{max-width:330px}
.search input{border-radius:11px;padding:11px 14px;border:1px solid #dbe2ea}
.pkg{background:#fff;border:1px solid var(--line);border-radius:17px;padding:20px;height:100%;box-shadow:0 6px 20px rgba(24,39,75,.045);transition:.2s}
.pkg:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(24,39,75,.08)}
.pkg-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.pkg-icon{width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,#e9f2ff,#f4f8ff);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:21px}
.badge-live{background:#eaf8f0;color:#16834d;border-radius:30px;padding:5px 9px;font-size:10px;font-weight:600}
.pkg-name{font-size:16px;font-weight:700;color:var(--navy);margin-top:15px}
.price{font-size:25px;font-weight:700;color:var(--blue);margin-top:4px}
.price small{font-size:11px;color:var(--muted);font-weight:500}
.metrics{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:17px}
.metric{background:#f7f9fc;border-radius:11px;padding:10px}
.metric span{display:block;color:#7b8797;font-size:10px}
.metric strong{display:block;color:var(--navy);font-size:14px;margin-top:2px}
.pkg-actions{display:flex;gap:8px;margin-top:17px}
.btn-view{border:1px solid #dce4ee;background:#fff;color:var(--navy);border-radius:9px;font-size:12px;font-weight:600;padding:9px 12px}
.btn-view:hover{background:#f5f8fc}
.btn-users{background:var(--navy);color:#fff;border:0;border-radius:9px;font-size:12px;font-weight:600;padding:9px 12px}
.empty{background:#fff;border:1px dashed #cfd7e2;border-radius:16px;padding:45px;text-align:center;color:var(--muted)}
.modal-content{border:0;border-radius:18px;overflow:hidden}
.modal-header{background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;border:0}
.user-row{display:flex;align-items:center;gap:12px;padding:12px 4px;border-bottom:1px solid #edf0f4}
.user-avatar{width:36px;height:36px;border-radius:10px;background:#e9f2ff;color:var(--blue);display:flex;align-items:center;justify-content:center}
.user-meta{flex:1}.user-meta strong{display:block;font-size:13px}.user-meta span{font-size:10px;color:#7c8795}
.user-status{font-size:10px;padding:5px 8px;border-radius:20px;background:#eaf8f0;color:#16834d}
.user-status.off{background:#f2f4f7;color:#687385}
@media(max-width:600px){.page{padding:14px}.hero{padding:22px}.hero h2{font-size:20px}}
</style>
</head>
<body>
<div class="page">
    <section class="hero">
        <h2><i class="bi bi-box-seam-fill me-2"></i>Internet Packages</h2>
        <p>Package Management Center — subscribers, pricing and expected monthly revenue.</p>
        <div class="operator"><i class="bi bi-person-circle"></i> Logged in as <strong><?=htmlspecialchars($operator_name)?></strong></div>
    </section>

    <div class="row g-3 stats">
        <div class="col-6 col-xl-3"><div class="stat"><div class="icon"><i class="bi bi-boxes"></i></div><div class="label">Total Packages</div><div class="value"><?=number_format($total_packages)?></div></div></div>
        <div class="col-6 col-xl-3"><div class="stat"><div class="icon"><i class="bi bi-people-fill"></i></div><div class="label">Active Subscribers</div><div class="value"><?=number_format($total_users)?></div></div></div>
        <div class="col-6 col-xl-3"><div class="stat"><div class="icon"><i class="bi bi-cash-stack"></i></div><div class="label">Expected Monthly Revenue</div><div class="value">Rs. <?=number_format($total_revenue)?></div></div></div>
        <div class="col-6 col-xl-3"><div class="stat"><div class="icon"><i class="bi bi-graph-up-arrow"></i></div><div class="label">Packages In Use</div><div class="value"><?=number_format($used_packages)?></div></div></div>
    </div>

    <div class="toolbar">
        <h4>Package Catalog</h4>
        <div class="search"><input id="pkgSearch" class="form-control" type="search" placeholder="Search package..."></div>
    </div>

    <?php if (!$packages): ?>
        <div class="empty"><i class="bi bi-box-seam fs-1 d-block mb-2"></i>No package records were found.</div>
    <?php else: ?>
    <div class="row g-3" id="packageGrid">
        <?php foreach ($packages as $p): ?>
        <div class="col-md-6 col-xl-4 package-card" data-name="<?=htmlspecialchars(strtolower($p['package_name']))?>">
            <div class="pkg">
                <div class="pkg-top">
                    <div class="pkg-icon"><i class="bi bi-wifi"></i></div>
                    <span class="badge-live"><?= $p['user_count'] > 0 ? 'In Use' : 'No Subscribers' ?></span>
                </div>
                <div class="pkg-name"><?=htmlspecialchars($p['package_name'])?></div>
                <div class="price">Rs. <?=number_format($p['price'],0)?> <small>/ month</small></div>
                <div class="metrics">
                    <div class="metric"><span>Subscribers</span><strong><?=number_format($p['user_count'])?></strong></div>
                    <div class="metric"><span>Expected Revenue</span><strong>Rs. <?=number_format($p['monthly_revenue'])?></strong></div>
                </div>
                <div class="pkg-actions">
                    <button class="btn-users flex-grow-1" onclick="showUsers(<?= (int)$p['id'] ?>, <?=htmlspecialchars(json_encode($p['package_name']))?>)"><i class="bi bi-people me-1"></i> View Users</button>
                    <button class="btn-view" title="Package ID <?= (int)$p['id'] ?>"><i class="bi bi-info-circle"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="text-center mt-4 text-muted" style="font-size:10px">
        Package names and prices are read directly from the existing Zalpro package tables. No billing records are modified by this page.
    </div>
</div>

<div class="modal fade" id="usersModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div><h5 class="modal-title mb-1" id="modalTitle">Package Subscribers</h5><div style="font-size:11px;opacity:.8" id="modalSub"></div></div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="usersBody">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('usersModal'));

document.getElementById('pkgSearch').addEventListener('input', function(){
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.package-card').forEach(card=>{
        card.style.display = card.dataset.name.includes(q) ? '' : 'none';
    });
});

async function showUsers(pkgid, name){
    document.getElementById('modalTitle').textContent = name;
    document.getElementById('modalSub').textContent = 'Subscribers assigned to this package';
    document.getElementById('usersBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    try{
        const r = await fetch('packages.php?ajax=users&pkgid=' + encodeURIComponent(pkgid));
        const data = await r.json();
        if(!data.success){ throw new Error(data.error || 'Unable to load users'); }

        if(!data.users.length){
            document.getElementById('usersBody').innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-people fs-1 d-block mb-2"></i>No subscribers found for this package.</div>';
            return;
        }

        document.getElementById('modalSub').textContent = data.users.length + ' subscriber(s)';
        document.getElementById('usersBody').innerHTML = data.users.map(u => `
            <div class="user-row">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-meta"><strong>${escapeHtml(u.username)}</strong><span>Expiry: ${escapeHtml(u.expiry)}</span></div>
                <span class="user-status ${u.status_label !== 'Active' ? 'off':''}">${escapeHtml(u.status_label)}</span>
            </div>
        `).join('');
    }catch(e){
        document.getElementById('usersBody').innerHTML = '<div class="alert alert-danger mb-0">Unable to load subscribers.</div>';
    }
}
function escapeHtml(v){
    return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>
</body>
</html>


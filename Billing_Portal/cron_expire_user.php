<?php
require_once '/zalpro-optimization/credentials/db_config.php';

$host = 'localhost';
$db   = 'zalpro';
$user = DB_USER;
$pass = DB_PASS;
$expired_pkg_id = 25; // Expired-JunOS Package ID

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Expired package (ID 25) ka groupname fetch karein (e.g. Expired_JunOS)
    $pkg_stmt = $pdo->prepare("SELECT name, groupname FROM packages WHERE id = ? LIMIT 1");
    $pkg_stmt->execute([$expired_pkg_id]);
    $pkg_data = $pkg_stmt->fetch(PDO::FETCH_ASSOC);
    $expired_group = !empty($pkg_data['groupname']) ? $pkg_data['groupname'] : 'Expired_JunOS';

    // NAS details fetch karein
    $stmt_nas = $pdo->query("SELECT nasname, secret FROM nas LIMIT 1");
    $nas = $stmt_nas->fetch(PDO::FETCH_ASSOC);
    $nas_ip = $nas ? $nas['nasname'] : '127.0.0.1';
    $secret = $nas ? $nas['secret'] : 'testing123';

    // 1. Scan expired users who are still on normal packages (Expiration date hit ho chuki hai)
    $stmt = $pdo->prepare("SELECT id, username, package FROM usersinfo WHERE current_expiration_date <= NOW() AND package != ? AND status = 2");
    $stmt->execute([$expired_pkg_id]);
    $expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expired_users as $u) {
        $username = $u['username'];
        $user_id  = $u['id'];

        // 2. Package ID = 25 update karein (current_expiration_date bilkul intact/same rahegi)
        /*
         * IMPORTANT:
         * Keep last_active_package_* untouched.
         * package is only switched to Expired (25).
         */
        $update = $pdo->prepare(
            "UPDATE usersinfo SET package = ? WHERE id = ?"
        );
        $update->execute([$expired_pkg_id, $user_id]);

        // 3. FreeRADIUS Expiration Check Bypass: radcheck se Expiration attribute hatayen
        $del_rad = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Expiration'");
        $del_rad->execute([$username]);

        // 4. RADIUS group shift karein Expired_JunOS par
        $del_grp = $pdo->prepare("DELETE FROM radusergroup WHERE username = ?");
        $del_grp->execute([$username]);

        $ins_grp = $pdo->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
        $ins_grp->execute([$username, $expired_group]);

        // 5. Juniper NAS ko disconnect packet (PoD) bhejen taake session Expired_JunOS profile par re-dial ho
        exec("echo 'User-Name=$username' | radclient -x $nas_ip:3799 disconnect '$secret' 2>&1");
    }
} catch (Exception $e) {
    file_put_contents('/var/log/zalpro_cron_error.log', date('Y-m-d H:i:s') . ' Error: ' . $e->getMessage() . "\n", FILE_APPEND);
}
?>

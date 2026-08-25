<?php
// Session Start for CodeIgniter / Zalpro Integration
session_start();

error_reporting(0);
ini_set('display_errors', 0);

require_once '/zalpro-optimization/credentials/db_config.php';

$host = 'localhost';
$db   = 'zalpro';
$user = DB_USER;
$pass = DB_PASS;

define('EXPIRED_PKG_ID', 25);

// LOGGED-IN STAFF / ADMIN IDENTIFICATION (Strictly from Custom Login)
$logged_user = $_SESSION['custom_username'] ?? 'System';
$logged_user_id = $_SESSION['custom_admin_id'] ?? 2;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database Connection Failed']);
    exit;
}

header('Content-Type: application/json');
$action = $_POST['action'] ?? '';

// ? SECURE FIX:
function triggerRadiusDisconnect($username, $pdo) {
    try {
        $stmt = $pdo->query("SELECT nasname, secret FROM nas LIMIT 1");
        $nas = $stmt->fetch(PDO::FETCH_ASSOC);
        $nas_ip = $nas ? $nas['nasname'] : '127.0.0.1';
        $secret = $nas ? $nas['secret'] : 'testing123';

        $safe_user = escapeshellarg("User-Name=" . $username);
        $safe_nas  = escapeshellarg($nas_ip . ":3799");
        $safe_sec  = escapeshellarg($secret);

        $command = "echo $safe_user | radclient -r 1 $safe_nas disconnect $safe_sec > /dev/null 2>&1 &";
        shell_exec($command);
    } catch (Exception $e) {}
}

function updateUserExpiry($pdo, $username, $new_date, $should_disconnect = true) {
    try {
        $now = date('Y-m-d H:i:s');
        $mysql_date = date('Y-m-d H:i:s', strtotime($new_date));
        $radius_date = date('d M Y H:i:s', strtotime($new_date));

        $stmt = $pdo->prepare("SELECT id, package, current_expiration_date, activation_date, last_active_package_id, last_active_package_name, last_active_package_price, last_active_package_updated_at FROM usersinfo WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return false;

        $target_pkg = (int)$user['package'];
        if ($target_pkg === EXPIRED_PKG_ID) {
            if (!empty($user['last_active_package_id']) && (int)$user['last_active_package_id'] !== EXPIRED_PKG_ID) {
                $target_pkg = (int)$user['last_active_package_id'];
            } else {
                $stmt_pkg = $pdo->prepare("SELECT pkgid FROM invoices WHERE userid = ? AND pkgid != ? ORDER BY invoiceID DESC LIMIT 1");
                $stmt_pkg->execute([$user['id'], EXPIRED_PKG_ID]);
                $last_pkg = $stmt_pkg->fetch(PDO::FETCH_ASSOC);
                if ($last_pkg && !empty($last_pkg['pkgid'])) $target_pkg = (int)$last_pkg['pkgid'];
            }
        }

        $target_pkg_name = null;
        $target_pkg_price = 0.0;
        $target_name_stmt = $pdo->prepare("SELECT name FROM packages WHERE id = ? LIMIT 1");
        $target_name_stmt->execute([$target_pkg]);
        $target_pkg_name = $target_name_stmt->fetchColumn();

        $target_price_stmt = $pdo->prepare(
            "SELECT customprice, price FROM f_packages
             WHERE fpkgid = (
                 SELECT MIN(f.fpkgid) FROM f_packages f
                 WHERE f.pkgid = ? AND (f.price IS NOT NULL OR f.customprice IS NOT NULL)
             ) LIMIT 1"
        );
        $target_price_stmt->execute([$target_pkg]);
        $target_price_data = $target_price_stmt->fetch(PDO::FETCH_ASSOC);
        if ($target_price_data) {
            $target_pkg_price = (float)$target_price_data['customprice'];
            if ($target_pkg_price <= 0) $target_pkg_price = (float)$target_price_data['price'];
        }

        $act_date = $user['activation_date'];
        if (empty($act_date) || $act_date == '0000-00-00 00:00:00') $act_date = $now;
        $last_exp = !empty($user['current_expiration_date']) ? $user['current_expiration_date'] : $now;

        if ((int)$target_pkg !== EXPIRED_PKG_ID) {
            $sql = "UPDATE usersinfo
                    SET package = ?,
                        last_active_package_id = ?,
                        last_active_package_name = ?,
                        last_active_package_price = ?,
                        last_active_package_updated_at = CASE
                            WHEN last_active_package_id IS NULL OR last_active_package_id <> ? THEN ?
                            ELSE last_active_package_updated_at END,
                        current_expiration_date = ?, last_expiration_date = ?, renew_date = ?, activation_date = ?,
                        status = 2, is_enabled = 1, connectionstatus = 1
                    WHERE username = ?";
            $stmt1 = $pdo->prepare($sql);
            $stmt1->execute([$target_pkg, $target_pkg, $target_pkg_name, $target_pkg_price,
                             $target_pkg, $now, $mysql_date, $last_exp, $now, $act_date, $username]);
        } else {
            $sql = "UPDATE usersinfo SET package = ?, current_expiration_date = ?, last_expiration_date = ?, renew_date = ?, activation_date = ?, status = 2, is_enabled = 1, connectionstatus = 1 WHERE username = ?";
            $stmt1 = $pdo->prepare($sql);
            $stmt1->execute([$target_pkg, $mysql_date, $last_exp, $now, $act_date, $username]);
        }

        $stmt2 = $pdo->prepare("UPDATE radcheck SET value = ? WHERE username = ? AND attribute = 'Expiration'");
        $stmt2->execute([$radius_date, $username]);
        if ($stmt2->rowCount() == 0) {
            $stmt3 = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)");
            $stmt3->execute([$username, $radius_date]);
        }

        $pkg_stmt = $pdo->prepare("SELECT name, groupname FROM packages WHERE id = ? LIMIT 1");
        $pkg_stmt->execute([$target_pkg]);
        $pkg_data = $pkg_stmt->fetch(PDO::FETCH_ASSOC);
        $real_group = !empty($pkg_data['groupname']) ? $pkg_data['groupname'] : $pkg_data['name'];

        if (!empty($real_group)) {
            $del_grp = $pdo->prepare("DELETE FROM radusergroup WHERE username = ?");
            $del_grp->execute([$username]);

            $ins_grp = $pdo->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
            $ins_grp->execute([$username, $real_group]);
        }

        if ($should_disconnect) triggerRadiusDisconnect($username, $pdo);
        return true;
    } catch (Exception $e) { return false; }
}

if ($action === 'autocomplete') {
    try {
        $term = $_POST['term'] ?? '';
        if (strlen(trim($term)) === 0) { echo json_encode([]); exit; }
        $search_term = "%" . trim($term) . "%";
        $stmt = $pdo->prepare("SELECT username FROM usersinfo WHERE username LIKE ? LIMIT 10");
        $stmt->execute([$search_term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

if ($action === 'search') {
    try {
        $username = $_POST['username'] ?? '';
        $stmt = $pdo->prepare("SELECT id, username, package, discount, current_expiration_date, status, is_enabled, last_active_package_id, last_active_package_name, last_active_package_price, last_active_package_updated_at FROM usersinfo WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['status'] == 2) $user['status'] = 1;
            $user['formatted_expiry'] = !empty($user['current_expiration_date']) ? date('d-M-Y H:i:s', strtotime($user['current_expiration_date'])) : 'N/A';

            $pkg_id = $user['package'];
            $is_currently_expired = ($pkg_id == EXPIRED_PKG_ID);

            if ($is_currently_expired) {
                if (!empty($user['last_active_package_id']) && (int)$user['last_active_package_id'] !== EXPIRED_PKG_ID) {
                    $pkg_id = (int)$user['last_active_package_id'];
                } else {
                    $stmt_pkg = $pdo->prepare("SELECT pkgid FROM invoices WHERE userid = ? AND pkgid != ? ORDER BY invoiceID DESC LIMIT 1");
                    $stmt_pkg->execute([$user['id'], EXPIRED_PKG_ID]);
                    $last_pkg = $stmt_pkg->fetch(PDO::FETCH_ASSOC);
                    $pkg_id = ($last_pkg && !empty($last_pkg['pkgid'])) ? (int)$last_pkg['pkgid'] : DEFAULT_PKG_ID;
                }
            }

            $pkg_name = "Package ID: " . $pkg_id;
            $pkg_price = 0;

            try {
                $pkg_stmt = $pdo->prepare("SELECT name, groupname FROM packages WHERE id = ? LIMIT 1");
                $pkg_stmt->execute([$pkg_id]);
                $pkg_data = $pkg_stmt->fetch(PDO::FETCH_ASSOC);
                if ($pkg_data) $pkg_name = !empty($pkg_data['name']) ? $pkg_data['name'] : $pkg_data['groupname'];
            } catch (Exception $e) {}

            try {
                $fpkg_stmt = $pdo->prepare("SELECT price, cost FROM f_packages WHERE pkgid = ? LIMIT 1");
                $fpkg_stmt->execute([$pkg_id]);
                $fpkg_data = $fpkg_stmt->fetch(PDO::FETCH_ASSOC);
                if ($fpkg_data) $pkg_price = ($fpkg_data['price'] > 0) ? $fpkg_data['price'] : $fpkg_data['cost'];
            } catch (Exception $e) {}

            if ($is_currently_expired) {
                $user['package_name'] = $pkg_name . ' <span class="badge bg-danger ms-1">Expired</span>';
            } else {
                $user['package_name'] = $pkg_name;
            }

            $discount = max(0.0, (float)($user['discount'] ?? 0));
            $discount = min($discount, (float)$pkg_price);
            $net_pkg_price = max(0.0, round((float)$pkg_price - $discount, 2));

            $user['gross_price'] = (float)$pkg_price;
            $user['discount'] = $discount;
            $user['price'] = $net_pkg_price;
            $user['actual_pkg_id'] = $pkg_id;
            $user['status_badge'] = ($user['status'] == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>';
            $user['logged_in_staff'] = $logged_user;

            try {
                $inv_stmt = $pdo->prepare("SELECT invoiceID as id, createdate as datetime, invtransid as transaction_id, totalcost as amount, status, added_by FROM invoices WHERE userid = ? ORDER BY invoiceID DESC LIMIT 15");
                $inv_stmt->execute([$user['id']]);
                $invoices = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($invoices as &$inv) {
                    if (!empty($inv['datetime'])) {
                        $inv['formatted_datetime'] = date('d-M-Y H:i:s', strtotime($inv['datetime']));
                        
                        if (preg_match('/(?:TRX|ADV|PRT)-([a-zA-Z]{3}-[0-9]{4})-/i', $inv['transaction_id'], $matches)) {
                            $inv['month_year'] = str_replace('-', ' ', $matches[1]);
                        } else {
                            $inv['month_year'] = date('M Y', strtotime($inv['datetime'])); 
                        }
                    } else {
                        $inv['formatted_datetime'] = 'N/A';
                        $inv['month_year'] = 'N/A';
                    }
                }

                $user['invoices'] = $invoices;
            } catch (Exception $ex) { $user['invoices'] = []; }

            try {
                $bill_stmt = $pdo->prepare("SELECT id, billing_month, amount, status FROM monthly_bills WHERE username = ? AND status IN ('unpaid', 'partial') ORDER BY id ASC");
                $bill_stmt->execute([$username]);
                $user['unpaid_bills'] = $bill_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $ex) { $user['unpaid_bills'] = []; }

            echo json_encode($user);
        } else { echo json_encode(['error' => 'User not found!']); }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

if ($action === 'generate_bill') {
    try {
        $username = trim($_POST['username'] ?? '');
        $month_str = trim($_POST['month'] ?? date('M Y'));

        if ($username === '') {
            echo json_encode(['success' => false, 'error' => 'Username is required.']);
            exit;
        }

        /*
         * IMPORTANT:
         * The monthly-bill form does NOT send an amount; it only sends the
         * billing month. Therefore the backend MUST calculate the real amount.
         *
         * For an expired user (package 25), use the saved last_active package.
         * For an active user, use usersinfo.package.
         */
        $user_stmt = $pdo->prepare(
            "SELECT id, package, discount, last_active_package_id, last_active_package_price
             FROM usersinfo
             WHERE username = ?
             LIMIT 1"
        );
        $user_stmt->execute([$username]);
        $bill_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bill_user) {
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }

        $pkgid = (int)($bill_user['package'] ?? 0);

        if ($pkgid === EXPIRED_PKG_ID) {
            if (!empty($bill_user['last_active_package_id']) &&
                (int)$bill_user['last_active_package_id'] !== EXPIRED_PKG_ID) {
                $pkgid = (int)$bill_user['last_active_package_id'];
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Previous active package is not available for this expired user.'
                ]);
                exit;
            }
        }

        /*
         * Prefer customprice, then price, then cost.
         * f_packages can contain duplicate rows for the same pkgid, so use
         * the first usable pricing row by fpkgid.
         */
        $price_stmt = $pdo->prepare(
            "SELECT customprice, price, cost
             FROM f_packages
             WHERE fpkgid = (
                 SELECT MIN(f.fpkgid)
                 FROM f_packages f
                 WHERE f.pkgid = ?
                   AND (f.price IS NOT NULL OR f.customprice IS NOT NULL OR f.cost IS NOT NULL)
             )
             LIMIT 1"
        );
        $price_stmt->execute([$pkgid]);
        $price_data = $price_stmt->fetch(PDO::FETCH_ASSOC);

        $amount = 0.0;

        if ($price_data) {
            $amount = (float)($price_data['customprice'] ?? 0);

            if ($amount <= 0) {
                $amount = (float)($price_data['price'] ?? 0);
            }

            if ($amount <= 0) {
                $amount = (float)($price_data['cost'] ?? 0);
            }
        }

        /*
         * Saved snapshot is the final fallback, especially useful for an
         * expired/legacy account whose package pricing row may be missing.
         */
        if ($amount <= 0 && !empty($bill_user['last_active_package_price'])) {
            $amount = (float)$bill_user['last_active_package_price'];
        }

        $discount = max(0.0, (float)($bill_user['discount'] ?? 0));
        $discount = min($discount, $amount);
        $amount = max(0.0, round($amount - $discount, 2));

        if ($amount <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Package amount could not be determined. Bill was NOT created.'
            ]);
            exit;
        }

        // One ledger row per user/month.
        $check_stmt = $pdo->prepare(
            "SELECT id, status FROM monthly_bills
             WHERE username = ? AND billing_month = ?
             LIMIT 1"
        );
        $check_stmt->execute([$username, $month_str]);
        $existing_bill = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_bill) {
            $status_label = $existing_bill['status'] ?? 'existing';
            echo json_encode([
                'success' => false,
                'error' => "A bill for this month already exists (status: {$status_label})."
            ]);
            exit;
        }

        $ins_stmt = $pdo->prepare(
            "INSERT INTO monthly_bills
             (username, billing_month, amount, collected_by)
             VALUES (?, ?, ?, ?)"
        );

        if ($ins_stmt->execute([$username, $month_str, $amount, $logged_user])) {
            echo json_encode([
                'success' => true,
                'amount' => $amount,
                'package_id' => $pkgid
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to generate bill.']);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate bill: ' . $e->getMessage()
        ]);
    }
    exit;
}


/*
 * ============================================================
 * ADD CUSTOM / OTHER AMOUNT
 * ============================================================
 *
 * user_collection.php sends:
 *   action      = add_custom_bill
 *   username
 *   amount
 *   description
 *
 * The previous backend had no add_custom_bill handler, so the
 * request fell through and the UI reported an invalid/error state.
 *
 * Custom bills are stored in monthly_bills using the existing
 * billing_month field with an OTHER: prefix. user_collection.php
 * already knows how to display this as "Other <description>".
 */
else if ($action === 'add_custom_bill') {
    try {
        $username = trim($_POST['username'] ?? '');
        $raw_amount = trim((string)($_POST['amount'] ?? ''));
        $description = trim($_POST['description'] ?? '');

        if ($username === '') {
            echo json_encode(['success' => false, 'error' => 'Username is required.']);
            exit;
        }

        // Accept normal numeric input plus values like "Rs. 2,500".
        $clean_amount = preg_replace('/[^0-9.\-]/', '', $raw_amount);
        if ($clean_amount === '' || !is_numeric($clean_amount)) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount. Enter a valid amount.']);
            exit;
        }

        $amount = round((float)$clean_amount, 2);

        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Amount must be greater than zero.']);
            exit;
        }

        if ($description === '') {
            echo json_encode(['success' => false, 'error' => 'Description is required.']);
            exit;
        }

        if (mb_strlen($description) > 150) {
            $description = mb_substr($description, 0, 150);
        }

        // Confirm the user exists.
        $user_stmt = $pdo->prepare("SELECT id FROM usersinfo WHERE username = ? LIMIT 1");
        $user_stmt->execute([$username]);

        if (!$user_stmt->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }

        // Keep custom bills distinguishable from normal month bills.
        // The frontend already detects OTHER: and displays the description.
        $billing_label = 'OTHER: ' . $description;

        $ins_stmt = $pdo->prepare(
            "INSERT INTO monthly_bills
             (username, billing_month, amount, status, collected_by)
             VALUES (?, ?, ?, 'unpaid', ?)"
        );

        if (!$ins_stmt->execute([$username, $billing_label, $amount, $logged_user])) {
            echo json_encode(['success' => false, 'error' => 'Failed to add custom amount.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Other amount added successfully.',
            'amount' => $amount
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to add custom amount: ' . $e->getMessage()
        ]);
    }
    exit;
}

else if ($action === 'bulk_generate_bills') {
    try {
        $raw_month = $_POST['raw_month'] ?? ''; 
        if (empty($raw_month)) {
            echo json_encode(['success' => false, 'error' => 'Month is required!']);
            exit;
        }
        
        $formatted_month = date('M Y', strtotime($raw_month . '-01'));

        // Fetch Active Users
        $stmt = $pdo->prepare("SELECT username, package, discount FROM usersinfo WHERE status IN (1, 2) OR is_enabled = 1");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        $skipped_advance = 0;
        $skipped_expired = 0;

        foreach ($users as $row) {
            $username = trim($row['username']);
            $pkgid = $row['package'];
            $user_discount = max(0.0, (float)($row['discount'] ?? 0));
            
            // 1. STRICT CHECK: Agar user ka package Expired (25) hai, toh bill na banayein
            if ((int)$pkgid === (int)EXPIRED_PKG_ID) {
                $skipped_expired++;
                continue;
            }
            
            // Package price fetch karein
            $pkg_price = 0;
            $fpkg_stmt = $pdo->prepare("SELECT price, cost FROM f_packages WHERE pkgid = ? LIMIT 1");
            $fpkg_stmt->execute([$pkgid]);
            $fpkg_data = $fpkg_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($fpkg_data) {
                $pkg_price = ($fpkg_data['price'] > 0) ? $fpkg_data['price'] : $fpkg_data['cost'];
            }

            $user_discount = min($user_discount, $pkg_price);
            $pkg_price = max(0.0, round($pkg_price - $user_discount, 2));

            // Never create a zero-value monthly bill.
            if ($pkg_price <= 0) {
                continue;
            }

            // 2. Duplicate / Advance Payment Check karein
            $check_bill = $pdo->prepare("SELECT id FROM monthly_bills WHERE username = ? AND billing_month = ? LIMIT 1");
            $check_bill->execute([$username, $formatted_month]);
            
            // Existing monthly bill includes already-paid advance bills, so never duplicate it.
            if ($check_bill->fetchColumn() !== false) {
                $skipped_advance++;
                continue; 
            }

            // 3. Naya Bill Insert karein
            $insert_sql = "INSERT INTO monthly_bills (username, billing_month, amount, collected_by) VALUES (?, ?, ?, ?)";
            $ins_stmt = $pdo->prepare($insert_sql);
            
            if ($ins_stmt->execute([$username, $formatted_month, $pkg_price, $logged_user])) {
                $count++;
            }
        }

        echo json_encode([
            'success' => true, 
            'count' => $count,
            'skipped' => $skipped_advance,
            'skipped_expired' => $skipped_expired
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database Query Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'custom_expiry') {
    try {
        $username = $_POST['username'] ?? '';
        $new_date = $_POST['new_date'] ?? ''; 

        if (empty($username) || empty($new_date)) {
            echo json_encode(['success' => false, 'error' => 'Missing username or date!']);
            exit;
        }

        $stmt_id = $pdo->prepare("SELECT id FROM usersinfo WHERE username = ? LIMIT 1");
        $stmt_id->execute([$username]);
        $u_data = $stmt_id->fetch(PDO::FETCH_ASSOC);

        if (!$u_data) { echo json_encode(['error' => 'User not found']); exit; }

        if (updateUserExpiry($pdo, $username, $new_date, true)) {
            echo json_encode(['success' => true, 'new_date' => date('d-M-Y H:i:s', strtotime($new_date))]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update custom expiry']);
        }
    } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}

if ($action === 'receive') {
    try {
        $pdo->query("SET SESSION sql_mode = ''");
        
        $username = $_POST['username'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $method_string = $_POST['method'] ?? 'Cash';
        $bill_id = $_POST['bill_id'] ?? null;
        
        $pkg_price = floatval($_POST['pkg_price'] ?? 0);
        $m_int = ($method_string === 'EasyPaisa') ? 2 : (($method_string === 'Bank') ? 3 : 1);
        
        $stmt_id = $pdo->prepare("SELECT id, package, discount, current_expiration_date, last_active_package_id FROM usersinfo WHERE username = ? LIMIT 1");
        $stmt_id->execute([$username]);
        $u_data = $stmt_id->fetch(PDO::FETCH_ASSOC);
        
        if (!$u_data) { echo json_encode(['error' => 'User not found']); exit; }
        
        $current_exp = $u_data['current_expiration_date'];
        $pkgid = (int)($u_data['package'] ?? 1);

        // Renewal of an expired account must use the last active package, not package 25.
        if ($pkgid === EXPIRED_PKG_ID) {
            $last_active_pkg = (int)($u_data['last_active_package_id'] ?? 0);
            if ($last_active_pkg > 0 && $last_active_pkg !== EXPIRED_PKG_ID) {
                $pkgid = $last_active_pkg;
            } else {
                $legacy_pkg_stmt = $pdo->prepare("SELECT pkgid FROM invoices WHERE userid = ? AND pkgid != ? ORDER BY invoiceID DESC LIMIT 1");
                $legacy_pkg_stmt->execute([$u_data['id'], EXPIRED_PKG_ID]);
                $legacy_pkg = $legacy_pkg_stmt->fetch(PDO::FETCH_ASSOC);
                if ($legacy_pkg && !empty($legacy_pkg['pkgid'])) $pkgid = (int)$legacy_pkg['pkgid'];
            }
        }
        
        /*
         * Calculate payable monthly package amount server-side.
         * The browser's pkg_price is not trusted.
         */
        $receive_price_stmt = $pdo->prepare(
            "SELECT customprice, price, cost
             FROM f_packages
             WHERE fpkgid = (
                 SELECT MIN(f.fpkgid)
                 FROM f_packages f
                 WHERE f.pkgid = ?
                   AND (f.price IS NOT NULL OR f.customprice IS NOT NULL OR f.cost IS NOT NULL)
             )
             LIMIT 1"
        );
        $receive_price_stmt->execute([$pkgid]);
        $receive_price_data = $receive_price_stmt->fetch(PDO::FETCH_ASSOC);

        $package_gross_price = 0.0;
        if ($receive_price_data) {
            $package_gross_price = (float)($receive_price_data['customprice'] ?? 0);
            if ($package_gross_price <= 0) $package_gross_price = (float)($receive_price_data['price'] ?? 0);
            if ($package_gross_price <= 0) $package_gross_price = (float)($receive_price_data['cost'] ?? 0);
        }

        $user_discount = max(0.0, (float)($u_data['discount'] ?? 0));
        $user_discount = min($user_discount, $package_gross_price);
        $pkg_price = max(0.0, round($package_gross_price - $user_discount, 2));

        if ($pkg_price <= 0) {
            echo json_encode(['success' => false, 'error' => 'Payable package amount is zero after discount.']);
            exit;
        }

        $amount_left = $amount;
        $last_invoice_id = null;

        // ============================================
        // SMART BULK / COMPILED LEDGER CLEARING & ADVANCE
        // ============================================
        if ($bill_id === 'bulk') {
            $stmt_bills = $pdo->prepare("SELECT id, billing_month, amount, status FROM monthly_bills WHERE username = ? AND status IN ('unpaid', 'partial') ORDER BY id ASC");
            $stmt_bills->execute([$username]);
            $pending_bills = $stmt_bills->fetchAll(PDO::FETCH_ASSOC);

            $months_extended = 0;

            // 1. Clear Pending / Unpaid / Partial Bills first
            foreach ($pending_bills as $pb) {
                if ($amount_left <= 0) break;

                $b_id = $pb['id'];
                $b_due = floatval($pb['amount']);
                $b_month = $pb['billing_month'];

                if ($amount_left >= $b_due) {
                    $upd = $pdo->prepare("UPDATE monthly_bills SET status = 'paid', paid_at = NOW(), collected_by = ? WHERE id = ?");
                    $upd->execute([$logged_user, $b_id]);

                    $safe_month = str_replace(' ', '-', $b_month);
                    $trx_id = 'TRX-' . $safe_month . '-' . rand(1000,9999);
                    $sql = "INSERT INTO invoices (userid, totalcost, createdate, invtransid, paidmethod, status, ispid, pkgid, added_by, saleby) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([$u_data['id'], $b_due, date('Y-m-d H:i:s'), $trx_id, $m_int, $pkgid, $logged_user, $logged_user_id]);
                    $last_invoice_id = $pdo->lastInsertId();

                    $amount_left -= $b_due;
                    if ($pb['status'] === 'unpaid' && stripos(trim((string)$b_month), 'OTHER:') !== 0) { $months_extended++; }
                } else {
                    $new_due = $b_due - $amount_left;
                    $upd = $pdo->prepare("UPDATE monthly_bills SET amount = ?, status = 'partial', collected_by = ? WHERE id = ?");
                    $upd->execute([$new_due, $logged_user, $b_id]);

                    $safe_month = str_replace(' ', '-', $b_month);
                    $trx_id = 'PRT-' . $safe_month . '-' . rand(1000,9999);
                    $sql = "INSERT INTO invoices (userid, totalcost, createdate, invtransid, paidmethod, status, ispid, pkgid, added_by, saleby) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([$u_data['id'], $amount_left, date('Y-m-d H:i:s'), $trx_id, $m_int, $pkgid, $logged_user, $logged_user_id]);
                    $last_invoice_id = $pdo->lastInsertId();

                    if ($pb['status'] === 'unpaid' && stripos(trim((string)$b_month), 'OTHER:') !== 0) { $months_extended++; }
                    $amount_left = 0;
                }
            }

            // ============================================
            // AUTO-GENERATE ADVANCE BILLS & SLIPS
            // ============================================
            
            $stmt_last_bill = $pdo->prepare("SELECT billing_month FROM monthly_bills WHERE username = ? ORDER BY STR_TO_DATE(CONCAT('01 ', billing_month), '%d %b %Y') DESC LIMIT 1");
            $stmt_last_bill->execute([$username]);
            $last_bill_row = $stmt_last_bill->fetch(PDO::FETCH_ASSOC); // Fixed typo here
            
            $last_month_str = $last_bill_row ? $last_bill_row['billing_month'] : date('M Y', strtotime('-1 month'));

            // Full package price ke barabar ya zyada advance bills generate karein.
            // Agar future month already exists (paid/unpaid/partial), duplicate
            // na banayein; payment ko aglay available month par apply karein.
            while ($pkg_price > 0 && $amount_left >= $pkg_price) {
                $candidate_month = date('M Y', strtotime("+1 month", strtotime("01 " . $last_month_str)));

                $exists_stmt = $pdo->prepare(
                    "SELECT id FROM monthly_bills WHERE username = ? AND billing_month = ? LIMIT 1"
                );
                $exists_stmt->execute([$username, $candidate_month]);

                if ($exists_stmt->fetchColumn() !== false) {
                    // This month is already billed/paid. Do not consume this
                    // payment amount for a duplicate row.
                    $last_month_str = $candidate_month;
                    continue;
                }

                $amount_left -= $pkg_price;
                $last_month_str = $candidate_month;

                $ins_adv_bill = $pdo->prepare("INSERT INTO monthly_bills (username, billing_month, amount, status, paid_at, collected_by) VALUES (?, ?, ?, 'paid', NOW(), ?)");
                $ins_adv_bill->execute([$username, $last_month_str, $pkg_price, $logged_user]);

                $safe_month = str_replace(' ', '-', $last_month_str);
                $trx_id = 'ADV-' . $safe_month . '-' . rand(1000,9999);
                $sql = "INSERT INTO invoices (userid, totalcost, createdate, invtransid, paidmethod, status, ispid, pkgid, added_by, saleby) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$u_data['id'], $pkg_price, date('Y-m-d H:i:s'), $trx_id, $m_int, $pkgid, $logged_user, $logged_user_id]);
                
                $last_invoice_id = $pdo->lastInsertId();
                $months_extended++;
            }

            // Agar kuch extra amount bach jaye jo poore package price se kam ho,
            // aglay AVAILABLE month ki partial slip bana dein.
            if ($pkg_price > 0 && $amount_left > 0 && $amount_left < $pkg_price) {
                do {
                    $last_month_str = date('M Y', strtotime("+1 month", strtotime("01 " . $last_month_str)));
                    $exists_partial = $pdo->prepare(
                        "SELECT id FROM monthly_bills WHERE username = ? AND billing_month = ? LIMIT 1"
                    );
                    $exists_partial->execute([$username, $last_month_str]);
                } while ($exists_partial->fetchColumn() !== false);

                $partial_due = $pkg_price - $amount_left;

                $ins_part_bill = $pdo->prepare("INSERT INTO monthly_bills (username, billing_month, amount, status, collected_by) VALUES (?, ?, ?, 'partial', ?)");
                $ins_part_bill->execute([$username, $last_month_str, $partial_due, $logged_user]);

                $safe_month = str_replace(' ', '-', $last_month_str);
                $trx_id = 'PRT-' . $safe_month . '-' . rand(1000,9999);
                $sql = "INSERT INTO invoices (userid, totalcost, createdate, invtransid, paidmethod, status, ispid, pkgid, added_by, saleby) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$u_data['id'], $amount_left, date('Y-m-d H:i:s'), $trx_id, $m_int, $pkgid, $logged_user, $logged_user_id]);
                
                $last_invoice_id = $pdo->lastInsertId();
                $amount_left = 0;
            }

// ============================================
            // UPDATE EXPIRY DATE
            // ============================================
            $new_expiry = $current_exp;
            if ($months_extended > 0) {
                if (empty($current_exp) || $current_exp == '0000-00-00 00:00:00' || $current_exp == null) {
                    $base_time = time();
                } else {
                    $old_ts = strtotime($current_exp);
                    $calculated_new_ts = strtotime("+$months_extended month", $old_ts);
                    
                    if ($calculated_new_ts < time()) {
                        // Agar dead account hai (payment ke baad bhi expiry past mein rehti) toh aaj se shuru karo
                        $base_time = time();
                    } else {
                        // Agar normal late payment ya advance hai toh purani date hi barqarar rakho
                        $base_time = $old_ts;
                    }
                }
                
                $new_expiry = date('Y-m-d H:i:s', strtotime("+$months_extended month", $base_time));
                updateUserExpiry($pdo, $username, $new_expiry, true);
            }

            echo json_encode(['success' => true, 'new_date' => date('d-M-Y H:i:s', strtotime($new_expiry)), 'invoice_id' => $last_invoice_id]);
            exit;
        }
    } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit; }
}

if ($action === 'delete_bill') {
    try {
        $bill_id = $_POST['bill_id'] ?? '';
        if (!$bill_id) { echo json_encode(['success' => false, 'error' => 'Bill ID is required!']); exit; }

        $stmt = $pdo->prepare("DELETE FROM monthly_bills WHERE id = ? AND status IN ('unpaid', 'partial')");
        $stmt->execute([$bill_id]);

        if ($stmt->rowCount() > 0) { echo json_encode(['success' => true]); } 
        else { echo json_encode(['success' => false, 'error' => 'Bill not found or already paid!']); }
    } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}

if ($action === 'revert_payment') {
    try {
        $username = $_POST['username'] ?? '';
        $invoice_id = $_POST['invoice_id'] ?? '';
        if (!$username || !$invoice_id) { echo json_encode(['success' => false, 'error' => 'Missing required data']); exit; }

        $stmt = $pdo->prepare("SELECT id, last_expiration_date FROM usersinfo WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo json_encode(['success' => false, 'error' => 'User not found']); exit; }

        $stmt_inv = $pdo->prepare("SELECT totalcost FROM invoices WHERE invoiceID = ?");
        $stmt_inv->execute([$invoice_id]);
        $inv_data = $stmt_inv->fetch(PDO::FETCH_ASSOC);
        $paid_amount = floatval($inv_data['totalcost']);

        $stmt_partial = $pdo->prepare("SELECT id, amount FROM monthly_bills WHERE username = ? AND status = 'partial' ORDER BY id DESC LIMIT 1");
        $stmt_partial->execute([$username]);
        $partial_bill = $stmt_partial->fetch(PDO::FETCH_ASSOC);

        if ($partial_bill) {
            $new_amount = $partial_bill['amount'] + $paid_amount;
            $stmt_upd_bill = $pdo->prepare("UPDATE monthly_bills SET status = 'unpaid', amount = ? WHERE id = ?");
            $stmt_upd_bill->execute([$new_amount, $partial_bill['id']]);
        } else {
            $stmt_bill = $pdo->prepare("UPDATE monthly_bills SET status = 'unpaid', paid_at = NULL WHERE username = ? AND status = 'paid' ORDER BY paid_at DESC LIMIT 1");
            $stmt_bill->execute([$username]);
        }

        $revert_date = $user['last_expiration_date'];
        $mysql_date = date('Y-m-d H:i:s', strtotime($revert_date));
        $radius_date = date('d M Y H:i:s', strtotime($revert_date));

        $stmt_upd = $pdo->prepare("UPDATE usersinfo SET current_expiration_date = ? WHERE username = ?");
        $stmt_upd->execute([$mysql_date, $username]);
        $stmt_rad = $pdo->prepare("UPDATE radcheck SET value = ? WHERE username = ? AND attribute = 'Expiration'");
        $stmt_rad->execute([$radius_date, $username]);

        $stmt_inv_del = $pdo->prepare("DELETE FROM invoices WHERE invoiceID = ?");
        $stmt_inv_del->execute([$invoice_id]);

        triggerRadiusDisconnect($username, $pdo);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}


// EXPIRY LIST / DATE FILTER
if ($action === 'expiry_list') {
    try {
        $selected_date = trim($_POST['date'] ?? date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
            echo json_encode(['success' => false, 'error' => 'Invalid date format.']);
            exit;
        }

        $check_date = DateTime::createFromFormat('Y-m-d', $selected_date);
        if (!$check_date || $check_date->format('Y-m-d') !== $selected_date) {
            echo json_encode(['success' => false, 'error' => 'Invalid date.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id, username, package, current_expiration_date, status, is_enabled
            FROM usersinfo
            WHERE current_expiration_date IS NOT NULL
              AND current_expiration_date != '0000-00-00 00:00:00'
              AND DATE(current_expiration_date) = ?
              AND package != ?
            ORDER BY current_expiration_date ASC, username ASC
        ");
        $stmt->execute([$selected_date, EXPIRED_PKG_ID]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$row) {
            $row['formatted_expiry'] = !empty($row['current_expiration_date'])
                ? date('d-M-Y h:i A', strtotime($row['current_expiration_date']))
                : 'N/A';

            $row['status_label'] = ((int)$row['status'] === 1 || (int)$row['is_enabled'] === 1)
                ? 'Active'
                : 'Disabled';

            $row['status_class'] = ((int)$row['status'] === 1 || (int)$row['is_enabled'] === 1)
                ? 'active'
                : 'disabled';

            $row['package_name'] = 'Package ID: ' . (int)$row['package'];
            try {
                $pkg_stmt = $pdo->prepare("SELECT name, groupname FROM packages WHERE id = ? LIMIT 1");
                $pkg_stmt->execute([$row['package']]);
                $pkg = $pkg_stmt->fetch(PDO::FETCH_ASSOC);

                if ($pkg) {
                    $row['package_name'] = !empty($pkg['name'])
                        ? $pkg['name']
                        : (!empty($pkg['groupname']) ? $pkg['groupname'] : $row['package_name']);
                }
            } catch (Exception $e) {}
        }
        unset($row);

        echo json_encode([
            'success' => true,
            'date' => $selected_date,
            'count' => count($users),
            'users' => $users
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database Query Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'package_history') {
    try {
        $username = trim($_POST['username'] ?? '');
        $limit = (int)($_POST['limit'] ?? 50);

        if ($limit <= 0) {
            $limit = 50;
        }

        if ($limit > 200) {
            $limit = 200;
        }

        if ($username !== '') {
            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    userid,
                    username,
                    old_package_id,
                    old_package_name,
                    old_package_price,
                    new_package_id,
                    new_package_name,
                    new_package_price,
                    change_type,
                    reason,
                    changed_by,
                    changed_at
                 FROM user_package_history
                 WHERE username = ?
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );

            $stmt->execute([$username]);
        } else {
            $stmt = $pdo->query(
                "SELECT
                    id,
                    userid,
                    username,
                    old_package_id,
                    old_package_name,
                    old_package_price,
                    new_package_id,
                    new_package_name,
                    new_package_price,
                    change_type,
                    reason,
                    changed_by,
                    changed_at
                 FROM user_package_history
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );
        }

        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'count' => count($history),
            'history' => $history
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Package history error: ' . $e->getMessage()
        ]);
    }

    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>

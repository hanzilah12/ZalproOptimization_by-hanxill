<?php
require_once __DIR__ . '/session_guard.php';
zalpro_enforce_session_timeout();

require_once '/zalpro-optimization/credentials/db_config.php';
$host = 'localhost';
$db   = 'zalpro';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Default filter values
$today = date('Y-m-d');
$from_date = $_GET['from_date'] ?? $today;
$to_date = $_GET['to_date'] ?? $today;
$operator_filter = $_GET['operator'] ?? 'all';
$method_filter = $_GET['method'] ?? 'all';
$username_filter = trim($_GET['username'] ?? '');

// Fetch all unique operators from invoices table for dropdown
$op_stmt = $pdo->query("SELECT DISTINCT added_by FROM invoices WHERE added_by IS NOT NULL AND added_by != '' ORDER BY added_by ASC");
$operators = $op_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all usernames for Auto-Search / Autocomplete suggestions list
$user_list_stmt = $pdo->query("SELECT username FROM usersinfo WHERE username IS NOT NULL ORDER BY username ASC");
$all_usernames = $user_list_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build the Search Query
$query = "SELECT i.invoiceID, i.totalcost, i.createdate, i.paidmethod, i.added_by, u.username 
          FROM invoices i 
          LEFT JOIN usersinfo u ON i.userid = u.id 
          WHERE DATE(i.createdate) >= ? AND DATE(i.createdate) <= ?";
$params = [$from_date, $to_date];

if ($operator_filter !== 'all') {
    $query .= " AND i.added_by = ?";
    $params[] = $operator_filter;
}

if ($method_filter !== 'all') {
    $query .= " AND i.paidmethod = ?";
    $params[] = $method_filter;
}

if (!empty($username_filter)) {
    $query .= " AND u.username LIKE ?";
    $params[] = "%" . $username_filter . "%";
}

$query .= " ORDER BY i.createdate DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals & Method Wise Breakdown
$total_collection = 0;
$total_cash = 0;
$total_easypaisa = 0;
$total_bank = 0;

foreach ($records as $row) {
    $amt = floatval($row['totalcost']);
    $total_collection += $amt;
    
    if ($row['paidmethod'] == 1) $total_cash += $amt;
    elseif ($row['paidmethod'] == 2) $total_easypaisa += $amt;
    elseif ($row['paidmethod'] == 3) $total_bank += $amt;
}

// Payment Method Helper
function getMethodBadge($id) {
    if ($id == 1) return '<span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Cash</span>';
    if ($id == 2) return '<span class="badge bg-warning bg-opacity-10 text-warning px-2 py-1">EasyPaisa</span>';
    if ($id == 3) return '<span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">Bank</span>';
    return '<span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1">Unknown</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Ledger & Reports - Netpoint IT & Communications</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body { 
            background-color: #f0f4f8; 
            font-family: 'Poppins', sans-serif; 
            padding: 30px;
        }
        
        .modern-card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.04); 
            background: #ffffff;
        }
        
        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: #1b204f;
        }

        .filter-label { 
            font-size: 13px; 
            font-weight: 600; 
            color: #4a5568; 
            margin-bottom: 5px; 
        }

        .stat-card { 
            background: white; 
            border-radius: 12px; 
            padding: 20px; 
            text-align: center; 
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border-left: 5px solid #1b204f;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.cash { border-left-color: #198754; }
        .stat-card.easypaisa { border-left-color: #ffc107; }
        .stat-card.bank { border-left-color: #0d6efd; }
        
        .stat-title { 
            font-size: 12px; 
            color: #6c757d; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        
        .stat-value { 
            font-size: 20px; 
            font-weight: 700; 
            color: #1b204f; 
            margin-top: 5px; 
        }

        .btn-gradient {
            background: linear-gradient(to right, #1b204f, #2079b0);
            color: white;
            border: none;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover { 
            background: linear-gradient(to right, #2079b0, #1b204f); 
            color: white; 
            box-shadow: 0 4px 12px rgba(32, 121, 176, 0.3);
        }

        .table-custom th {
            background-color: #f8fafc;
            color: #1b204f;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-custom td {
            font-size: 14px;
            color: #2d3748;
            vertical-align: middle;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border-color: #cbd5e1;
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #2079b0;
            box-shadow: 0 0 0 3px rgba(32, 121, 176, 0.15);
        }

        /* Smooth motion animations - visual only, no PHP/JS logic changed */
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes cardFadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes softPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.035); }
        }

        .container-fluid {
            animation: pageFadeIn .55s ease-out both;
        }

        .page-title {
            animation: pageFadeIn .5s ease-out .05s both;
        }

        .modern-card {
            animation: cardFadeUp .55s ease-out both;
        }

        .row.g-3.mb-4 .stat-card {
            animation: cardFadeUp .5s ease-out both;
        }
        .row.g-3.mb-4 .col-md-3:nth-child(1) .stat-card { animation-delay: .08s; }
        .row.g-3.mb-4 .col-md-3:nth-child(2) .stat-card { animation-delay: .14s; }
        .row.g-3.mb-4 .col-md-3:nth-child(3) .stat-card { animation-delay: .20s; }
        .row.g-3.mb-4 .col-md-3:nth-child(4) .stat-card { animation-delay: .26s; }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 9px 22px rgba(0,0,0,.07);
        }

        .btn-gradient:hover {
            transform: translateY(-1px);
        }

        .table-custom tbody tr {
            transition: transform .18s ease, background-color .18s ease, box-shadow .18s ease;
        }

        .table-custom tbody tr:hover {
            transform: translateX(3px);
        }

        .table-custom tbody tr td .badge {
            transition: transform .18s ease;
        }

        .table-custom tbody tr:hover td .badge {
            transform: scale(1.03);
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1">Collection Ledger & Reports</h3>
            <p class="text-muted mb-0" style="font-size: 13px;">Track and analyze financial collection records, operator transactions, and customer history.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="user_collection.php" class="btn btn-light border fw-semibold px-3 py-2" style="border-radius: 8px;">
                <i class="bi bi-arrow-left me-1"></i> Back to Billing
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card modern-card p-4 mb-4">
        <form method="GET" action="">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Customer Username</label>
                    <input type="text" name="username" class="form-control" list="usernamesList" placeholder="Type username..." value="<?= htmlspecialchars($username_filter) ?>">
                    <!-- Auto-Search Datalist Suggestions -->
                    <datalist id="usernamesList">
                        <?php foreach($all_usernames as $u): ?>
                            <option value="<?= htmlspecialchars($u) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Transaction Type</label>
                    <select name="method" class="form-select">
                        <option value="all" <?= ($method_filter === 'all') ? 'selected' : '' ?>>All Methods</option>
                        <option value="1" <?= ($method_filter === '1') ? 'selected' : '' ?>>Cash</option>
                        <option value="2" <?= ($method_filter === '2') ? 'selected' : '' ?>>EasyPaisa</option>
                        <option value="3" <?= ($method_filter === '3') ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Operator / Staff</label>
                    <select name="operator" class="form-select">
                        <option value="all">-- All Operators --</option>
                        <?php foreach($operators as $op): ?>
                            <option value="<?= htmlspecialchars($op) ?>" <?= ($operator_filter === $op) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($op) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-gradient w-100 py-2">
                        <i class="bi bi-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Stats Bar -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-title">Total Receipts</div>
                <div class="stat-value"><?= count($records) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card cash">
                <div class="stat-title">Cash Received</div>
                <div class="stat-value text-success">Rs. <?= number_format($total_cash, 2) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card easypaisa">
                <div class="stat-title">EasyPaisa Collection</div>
                <div class="stat-value text-warning">Rs. <?= number_format($total_easypaisa, 2) ?></div>
            </div>
        </div>
        <div class="col-md-3" style="border-left-color: #2079b0;">
            <div class="stat-card" style="border-left-color: #2079b0;">
                <div class="stat-title">Total Collection Amount</div>
                <div class="stat-value text-primary">Rs. <?= number_format($total_collection, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Detailed Transactions Table -->
    <div class="card modern-card p-4">
        <div class="table-responsive">
            <table class="table table-custom table-hover text-center align-middle mb-0">
                <thead>
                    <tr>
                        <th class="py-3">Invoice ID</th>
                        <th class="py-3">Date & Time</th>
                        <th class="py-3">Customer Username</th>
                        <th class="py-3">Amount Collected</th>
                        <th class="py-3">Transaction Type</th>
                        <th class="py-3">Collected By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php foreach($records as $row): ?>
                            <tr>
                                <td class="fw-bold text-primary">#<?= $row['invoiceID'] ?></td>
                                <td><?= date('d-M-Y h:i A', strtotime($row['createdate'])) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['username'] ?? 'N/A') ?></td>
                                <td class="text-success fw-bold">Rs. <?= number_format($row['totalcost'], 2) ?></td>
                                <td><?= getMethodBadge($row['paidmethod']) ?></td>
                                <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($row['added_by']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No collection records found for the selected filter criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <script src="session_timeout.js"></script>
</body>
</html>

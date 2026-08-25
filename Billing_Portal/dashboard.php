<?php
require_once __DIR__ . '/session_guard.php';
zalpro_enforce_session_timeout();
$operator_name = $_SESSION['custom_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netpoint IT & Communications - Admin Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4f8;
            margin: 0;
            overflow: hidden; /* Prevent body scroll, let iframe scroll */
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: #1b204f; /* Company Dark Blue */
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: width 0.28s ease;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
            user-select: none;
        }

        .sidebar-header img {
            max-width: 80%;
            height: auto;
            margin-bottom: 10px;
            background: white;
            padding: 5px;
            border-radius: 8px;
            transition: all 0.28s ease;
        }

        .sidebar-header h6 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
            color: #e9f2ff;
            transition: opacity 0.2s ease;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
            transition: padding 0.28s ease;
        }

        .sidebar-menu li {
            padding: 5px 20px;
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            color: #b0b5c9;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .sidebar-menu a .menu-text {
            transition: opacity 0.18s ease;
            white-space: nowrap;
        }

        .sidebar-menu a i {
            font-size: 18px;
            margin-right: 12px;
            transition: margin 0.28s ease;
        }

        /* Hover & Active States */
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #2079b0; /* Company Light Blue */
            color: white;
            box-shadow: 0 4px 10px rgba(32, 121, 176, 0.3);
        }

        .sidebar-footer {
            padding: 20px;
            transition: padding 0.28s ease;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .operator-badge {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 10px;
            transition: all 0.28s ease;
        }

        .logout-text {
            transition: opacity 0.18s ease;
            white-space: nowrap;
        }

        /* Collapsed sidebar */
        .sidebar.collapsed {
            width: 78px;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 14px 10px;
        }

        .sidebar.collapsed .sidebar-header img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            margin-bottom: 0;
            padding: 3px;
        }

        .sidebar.collapsed .sidebar-header h6 {
            display: none;
        }

        .sidebar.collapsed .sidebar-menu li {
            padding: 5px 10px;
        }

        .sidebar.collapsed .sidebar-menu a {
            justify-content: center;
            padding: 12px 10px;
        }

        .sidebar.collapsed .sidebar-menu a i {
            margin-right: 0;
        }

        .sidebar.collapsed .menu-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-footer {
            padding: 14px 10px;
        }

        .sidebar.collapsed .operator-badge {
            padding: 10px 5px;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .operator-name {
            display: none;
        }

        .sidebar.collapsed .logout-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-footer .btn {
            padding-left: 10px;
            padding-right: 10px;
        }

        /* Main Content Area */
        .main-content {
            margin-left: 260px; /* Same as sidebar width */
            height: 100vh;
            background-color: #f0f4f8;
            transition: margin-left 0.28s ease;
        }

        .main-content.expanded {
            margin-left: 78px;
        }

        /* Iframe setup */
        #contentFrame {
            width: 100%;
            height: 100%;
            border: none;
        }

    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <!-- Aap yahan apne logo ka correct path daal dein -->
            <img src="NP Logo-zoomed.png" alt="Netpoint Logo" title="Click to minimize / maximize sidebar">
            <h6>Staff Billing Portal</h6>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_home.php" target="contentFrame" class="menu-link">
                    <i class="bi bi-grid-1x2-fill"></i><span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li>
                <!-- Ye wohi page hai jo abhi humne design kiya -->
                <a href="user_collection.php" target="contentFrame" class="menu-link active">
                    <i class="bi bi-people-fill"></i><span class="menu-text">User Collections</span>
                </a>
            </li>
            <li>
                <a href="bill_creator.php" target="contentFrame" class="menu-link">
                    <i class="bi bi-receipt-cutoff"></i><span class="menu-text">Bill Creator</span>
                </a>
            </li>
            <li>
                <a href="packages.php" target="contentFrame" class="menu-link">
                    <i class="bi bi-box-seam-fill"></i><span class="menu-text">Internet Packages</span>
                </a>
            </li>
            <li>
                <a href="financial_report.php" target="contentFrame" class="menu-link">
                    <i class="bi bi-graph-up-arrow"></i><span class="menu-text">Monthly Collection</span>
                </a>
            </li>
                <a href="collection_report.php" target="contentFrame" class="menu-link">
                    <i class="bi bi-bar-chart-line-fill"></i><span class="menu-text">Financial Reports</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="operator-badge">
                <i class="bi bi-person-circle text-info"></i> <span class="operator-name"><?= htmlspecialchars($operator_name) ?></span>
            </div>
            <a href="logout.php" class="btn btn-danger w-100 fw-bold shadow-sm rounded-3">
                <i class="bi bi-box-arrow-left me-1"></i><span class="logout-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Default page jo open hogi wo src me set kar dein -->
        <iframe name="contentFrame" id="contentFrame" src="dashboard_home.php"></iframe>
    </div>

    <!-- jQuery for active link switching -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const $sidebar = $('.sidebar');
            const $mainContent = $('.main-content');
            const savedState = localStorage.getItem('zalproSidebarCollapsed');

            function applySidebarState(collapsed) {
                $sidebar.toggleClass('collapsed', collapsed);
                $mainContent.toggleClass('expanded', collapsed);
                localStorage.setItem('zalproSidebarCollapsed', collapsed ? '1' : '0');
            }

            if (savedState === '1') {
                applySidebarState(true);
            }

            // Click the company logo to minimize / maximize the sidebar.
            $('.sidebar-header').on('click', function() {
                applySidebarState(!$sidebar.hasClass('collapsed'));
            });

            $('.menu-link').click(function() {
                $('.menu-link').removeClass('active');
                $(this).addClass('active');
            });
        });
    </script>
    <script src="session_timeout.js"></script>
</body>
</html>

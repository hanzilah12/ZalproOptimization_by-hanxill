<?php
session_start();

// Agar pehle se login hai toh direct collection page par bhej dein
if (isset($_SESSION['custom_logged_in']) && $_SESSION['custom_logged_in'] === true) {
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) >= 600) {
        $_SESSION = [];
        session_destroy();
    } else {
        header("Location: dashboard.php");
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '/zalpro-optimization/credentials/db_config.php';
    $host = 'localhost';
    $db   = 'zalpro';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username = trim($_POST['username']);
        // Zalpro MD5 hash use karta hai
        $password = md5(trim($_POST['password'])); 

        $stmt = $pdo->prepare("SELECT adminid, username, name FROM admin WHERE username = ? AND password = ? AND status = 1 LIMIT 1");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Login Successful - Session set karein
            session_regenerate_id(true);
            $_SESSION['custom_logged_in'] = true;
            $_SESSION['custom_admin_id'] = $user['adminid'];
            $_SESSION['custom_username'] = $user['username'];
            $_SESSION['custom_name'] = $user['name'];
            $_SESSION['last_activity'] = time();
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid Username or Password, or Account Disabled!";
        }
    } catch (Exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netpoint IT & Communications Pvt. Ltd - Billing Panel</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body { 
            font-family: 'Poppins', sans-serif;
            /* Gradient Background matched with Netpoint Logo Theme */
            background: linear-gradient(135deg, #1b204f 0%, #2079b0 100%);
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            margin: 0;
        }
        
        .login-wrapper {
            width: 100%;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .login-card { 
            width: 100%; 
            max-width: 420px; 
            padding: 40px 30px; 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
            background: rgba(255, 255, 255, 0.98); 
            backdrop-filter: blur(10px);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        /* Logo Styling */
        .brand-logo {
            max-width: 130px;
            height: auto;
            margin: 0 auto 15px auto;
            display: block;
        }

        .login-header h4 {
            font-weight: 700;
            color: #1b204f; /* Dark Blue from Logo */
            margin-bottom: 2px;
            font-size: 20px;
            line-height: 1.3;
        }

        .login-header p {
            color: #2079b0; /* Light Blue from Logo */
            font-size: 13px;
            font-weight: 500;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .form-floating .form-control {
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 15px;
        }

        .form-floating .form-control:focus {
            border-color: #2079b0;
            box-shadow: 0 0 0 0.25rem rgba(32, 121, 176, 0.15);
        }

        .form-floating label {
            color: #7f8c8d;
        }

        .btn-primary {
            background: linear-gradient(to right, #1b204f, #2079b0);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(32, 121, 176, 0.3);
            background: linear-gradient(to right, #2079b0, #1b204f);
        }

        .alert-danger {
            border-radius: 12px;
            font-size: 14px;
            border-left: 4px solid #dc3545;
        }
    
        /* Animation only: PHP/authentication and form functionality untouched */
        @keyframes loginFadeUp {
            from { opacity: 0; transform: translateY(22px) scale(.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes loginLogoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .login-card {
            animation: loginFadeUp .65s cubic-bezier(.22,1,.36,1) both;
            transition: box-shadow .25s ease, transform .25s ease;
        }

        .login-card:hover {
            box-shadow: 0 24px 48px rgba(0,0,0,.32);
        }

        .brand-logo {
            animation: loginLogoFloat 3s ease-in-out 1s infinite;
        }

        .login-header h4 {
            animation: loginFadeUp .5s ease-out .15s both;
        }

        .login-header p {
            animation: loginFadeUp .5s ease-out .22s both;
        }

        .login-header + .alert-danger {
            animation: loginFadeUp .45s ease-out .12s both;
        }

        .login-card form {
            animation: loginFadeUp .5s ease-out .28s both;
        }

        .form-floating .form-control {
            transition: border-color .22s ease, box-shadow .22s ease, transform .22s ease;
        }

        .form-floating .form-control:focus {
            transform: translateY(-1px);
        }

        .btn-primary {
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.03);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .login-card,
            .brand-logo,
            .login-header h4,
            .login-header p,
            .login-header + .alert-danger,
            .login-card form {
                animation: none !important;
            }

            .login-card,
            .form-floating .form-control,
            .btn-primary {
                transition: none !important;
            }
        }

    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <!-- Company Logo -->
            <img src="NP Logo-zoomed.png" alt="Netpoint ISP Logo" class="brand-logo">
            
            <!-- Company Name -->
            <h4>Netpoint IT & Communications Pvt. Ltd</h4>
            <p>BILLING PORTAL</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger text-center py-3 fw-medium d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-floating mb-3">
                <input type="text" name="username" class="form-control" id="floatingInput" required autocomplete="off" placeholder="e.g. desk1">
                <label for="floatingInput"><i class="bi bi-person text-muted me-2"></i>Zalpro Username</label>
            </div>
            
            <div class="form-floating mb-4">
                <input type="password" name="password" class="form-control" id="floatingPassword" required placeholder="Password">
                <label for="floatingPassword"><i class="bi bi-key text-muted me-2"></i>Password</label>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-2">
                Secure Login <i class="bi bi-arrow-right-circle ms-1"></i>
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

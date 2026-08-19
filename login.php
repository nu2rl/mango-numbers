<?php
/**
 * Mango Number - User Login Page
 */

require_once __DIR__ . '/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header("Location: admin.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'CSRF verification failed. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
        $db = get_db_connection();
        if (!$db) {
            $error = 'Database connection failed. Please run <a href="db_init.php">db_init.php</a> first.';
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            try {
                $db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            } catch (PDOException $e) {}

            $failed_count = 0;
            try {
                $chk_stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE (ip_address = ? OR username = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $chk_stmt->execute([$ip_address, $username]);
                $failed_count = (int)$chk_stmt->fetchColumn();
            } catch (PDOException $e) {}

            if ($failed_count >= 5) {
                $error = 'Too many failed login attempts. Please try again after 15 minutes.';
            } else {
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                $auth_success = false;
                if ($user) {
                    if ($user['status'] === 'deleted') {
                        $reason = htmlspecialchars($user['deletion_reason'] ?? 'No reason specified by administrator.');
                        $error = 'Your account has been deleted by the administrator.<br><strong style="color: #f97316;">Reason:</strong> ' . $reason;
                    } elseif (password_verify($password, $user['password'])) {
                        if ($user['role'] !== 'admin' && get_system_setting('allow_website_usage', '1') === '0') {
                            $error = 'The website is currently under maintenance. Only administrators can log in at this time.';
                        } else {
                            $auth_success = true;
                            try {
                                $clear = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
                                $clear->execute([$ip_address, $username]);
                            } catch (PDOException $e) {}
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['status'] = $user['status'];
                            $_SESSION['show_welcome_crack_modal'] = true;
                            if ($user['role'] === 'admin') {
                                header("Location: admin.php");
                            } else {
                                header("Location: dashboard.php");
                            }
                            exit;
                        }
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
                if (!$auth_success && empty($error) === false) {
                    try {
                        $ins = $db->prepare("INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (?, ?, NOW())");
                        $ins->execute([$ip_address, $username]);
                    } catch (PDOException $e) {}
                }
            }
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Mango Number</title>
    <meta name="description" content="Log in to Mango Number to buy virtual SMS verification numbers for Telegram and WhatsApp.">
    <link rel="icon" type="image/png" href="assets/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #09090f;
            --surface: #111118;
            --elevated: #1a1a26;
            --border: rgba(255,255,255,0.07);
            --border-bright: rgba(249,115,22,0.4);
            --accent: #f97316;
            --accent-glow: rgba(249,115,22,0.18);
            --text: #f1f5f9;
            --muted: #64748b;
            --success: #22c55e;
            --danger: #ef4444;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* ── Left Panel ── */
        .left-panel {
            flex: 1;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse 80% 80% at 50% 0%, rgba(249,115,22,0.22) 0%, transparent 70%),
                        radial-gradient(ellipse 60% 60% at 80% 100%, rgba(249,115,22,0.1) 0%, transparent 60%);
        }
        /* Animated mesh grid */
        .mesh {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(249,115,22,0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(249,115,22,0.06) 1px, transparent 1px);
            background-size: 48px 48px;
            animation: meshMove 20s linear infinite;
        }
        @keyframes meshMove {
            0% { background-position: 0 0; }
            100% { background-position: 48px 48px; }
        }
        .left-content { position: relative; z-index: 2; text-align: center; max-width: 420px; }
        .left-logo {
            display: inline-flex; align-items: center; gap: 12px;
            margin-bottom: 48px; text-decoration: none;
        }
        .left-logo-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, #f97316, #fb923c);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 24px rgba(249,115,22,0.5);
        }
        .left-logo-icon img { width: 28px; height: 28px; object-fit: contain; }
        .left-logo-text {
            font-family: 'Sora', sans-serif;
            font-size: 22px; font-weight: 800;
            color: var(--text);
        }
        .left-logo-text span { color: var(--accent); }
        .left-headline {
            font-family: 'Sora', sans-serif;
            font-size: 38px; font-weight: 800; line-height: 1.15;
            letter-spacing: -1px;
            color: var(--text);
            margin-bottom: 18px;
        }
        .left-headline em { color: var(--accent); font-style: normal; }
        .left-sub {
            font-size: 15px; color: var(--muted); line-height: 1.7; margin-bottom: 40px;
        }
        .feature-pills { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 999px; padding: 6px 14px;
            font-size: 12.5px; color: #94a3b8;
        }
        .pill-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }

        /* ── Right Panel ── */
        .right-panel {
            width: 460px; flex-shrink: 0;
            background: var(--surface);
            border-left: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            padding: 48px 40px;
            position: relative;
        }
        .auth-box { width: 100%; }

        .auth-title {
            font-family: 'Sora', sans-serif;
            font-size: 26px; font-weight: 800; color: var(--text);
            margin-bottom: 6px;
        }
        .auth-sub { font-size: 14px; color: var(--muted); margin-bottom: 32px; }

        .maintenance-banner {
            background: rgba(249,115,22,0.08);
            border: 1px solid rgba(249,115,22,0.25);
            border-radius: 12px; padding: 14px 16px;
            margin-bottom: 24px; font-size: 13px; color: #fb923c;
            line-height: 1.5;
        }
        .alert-box {
            border-radius: 12px; padding: 13px 16px;
            margin-bottom: 24px; font-size: 13.5px; line-height: 1.5;
        }
        .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }

        .form-group { margin-bottom: 20px; }
        .form-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        label {
            display: block; font-size: 13px; font-weight: 600;
            color: #cbd5e1; margin-bottom: 8px;
        }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            background: var(--elevated);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 13px 16px;
            font-family: 'Inter', sans-serif;
            font-size: 14px; color: var(--text);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        input::placeholder { color: #334155; }

        .forgot-link {
            font-size: 12.5px; color: var(--accent); text-decoration: none; font-weight: 600;
        }
        .forgot-link:hover { text-decoration: underline; }

        .btn-primary {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #f97316, #fb923c);
            border: none; border-radius: 10px;
            font-family: 'Sora', sans-serif;
            font-size: 15px; font-weight: 700; color: #fff;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(249,115,22,0.3);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 8px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(249,115,22,0.4); }
        .btn-primary:active { transform: none; }

        .divider { text-align: center; color: var(--muted); font-size: 13px; margin: 24px 0; position: relative; }
        .divider::before, .divider::after {
            content: ''; position: absolute; top: 50%; width: 42%;
            height: 1px; background: var(--border);
        }
        .divider::before { left: 0; } .divider::after { right: 0; }

        .footer-note { text-align: center; font-size: 13.5px; color: var(--muted); margin-top: 24px; }
        .footer-note a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .footer-note a:hover { text-decoration: underline; }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 16px; height: 16px;
            border: 2.5px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: inline-block;
        }

        /* ── Mobile ── */
        @media (max-width: 900px) {
            body { flex-direction: column; overflow: auto; }
            .left-panel { display: none; }
            .right-panel { width: 100%; border-left: none; border-top: 1px solid var(--border); min-height: 100vh; padding: 48px 28px; }
            input[type="text"], input[type="password"], input[type="email"] { font-size: 16px !important; }
        }
        @media (max-width: 480px) {
            .right-panel { padding: 36px 20px; }
        }
    </style>
</head>
<body>
    <!-- Left Branding Panel -->
    <div class="left-panel">
        <div class="mesh"></div>
        <div class="left-content">
            <a href="index.php" class="left-logo">
                <div class="left-logo-icon">
                    <img src="assets/img/logo.png" alt="Logo">
                </div>
                <span class="left-logo-text">Mango<span>Number</span></span>
            </a>
            <h1 class="left-headline">Get verified.<br>Instantly. <em>Globally.</em></h1>
            <p class="left-sub">Buy virtual SMS verification numbers for Telegram, WhatsApp, and more — fast, affordable, and reliable.</p>
            <div class="feature-pills">
                <span class="pill"><span class="pill-dot"></span> Telegram Numbers</span>
                <span class="pill"><span class="pill-dot"></span> WhatsApp Numbers</span>
                <span class="pill"><span class="pill-dot"></span> 15+ Countries</span>
                <span class="pill"><span class="pill-dot"></span> Instant Delivery</span>
                <span class="pill"><span class="pill-dot"></span> UPI &amp; USDT</span>
            </div>
        </div>
    </div>

    <!-- Right Form Panel -->
    <div class="right-panel">
        <div class="auth-box">
            <h2 class="auth-title">Welcome back</h2>
            <p class="auth-sub">Sign in to your Mango Number account</p>

            <?php if (get_system_setting('allow_website_usage', '1') === '0'): ?>
                <div class="maintenance-banner">
                    ⚠️ <strong>Scheduled Maintenance</strong> — Public access is temporarily restricted. Only administrators can log in.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-box alert-error"><?= $error ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="login-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" name="username" id="username" placeholder="Enter username or email" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <div class="form-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label for="password" style="margin-bottom:0;">Password</label>
                        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                    </div>
                    <div style="position: relative;">
                        <input type="password" name="password" id="password" placeholder="Enter your password" required style="padding-right: 44px;">
                        <i class="bx bx-hide" id="pwd-eye-icon" onclick="toggleLoginPassword()" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 20px; cursor: pointer; transition: color 0.2s;" title="Toggle Password Visibility"></i>
                    </div>
                </div>
                <button type="submit" class="btn-primary" id="login-btn">Sign In</button>
            </form>

            <div class="footer-note">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>

    <script>
        function toggleLoginPassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('pwd-eye-icon');
            if (!input || !icon) return;
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            } else {
                input.type = 'password';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            }
        }

        document.getElementById('login-form').addEventListener('submit', function() {
            const btn = document.getElementById('login-btn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Signing In...';
        });
    </script>
    <script src="assets/js/anti-devtools.js"></script>
</body>
</html>

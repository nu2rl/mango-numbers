<?php
/**
 * Mango Number - SMTP Email Connection Testing Route Portal
 */

require_once __DIR__ . '/config.php';
require_login();
require_admin();
require_once __DIR__ . '/Mailer.php';

$error = '';
$success = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $error = 'CSRF token verification failed.';
    } else {
        $email_input = trim($_POST['email'] ?? '');
        
        if (empty($email_input) || !filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid recipient email address.';
        } else {
            try {
                $subject = "Test Email From Mango Numbers";
                $body = "Hello,<br><br>
                         Brevo SMTP is working successfully for Mango Numbers.<br><br>
                         Thanks,<br>
                         <strong>Mango Numbers</strong>";
                         
                // Trigger mailer socket send
                Mailer::send($email_input, $subject, $body);
                $success = 'Test email successfully dispatched using Brevo SMTP! Check your inbox.';
            } catch (Exception $e) {
                $error = 'Dispatched failed. SMTP Server response: ' . $e->getMessage();
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
    <title>SMTP Testing - Mango Number</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-bg: #fffdf9;
            --accent-orange: #FF8C00;
            --accent-yellow: #FFA726;
            --gradient-accent: linear-gradient(135deg, #FF8C00, #FFA726);
            --text-dark: #1A1208;
            --text-light: rgba(26,18,8,0.62);
            --card-glass: rgba(255, 255, 255, 0.85);
            --card-border: rgba(255, 255, 255, 0.95);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Premium Background Glow Blobs */
        .blob {
            position: absolute; border-radius: 50%; filter: blur(120px); z-index: -1; opacity: 0.55;
        }
        .blob-1 {
            width: 450px; height: 450px;
            background: radial-gradient(circle, rgba(255,140,0,0.4) 0%, rgba(255,255,255,0) 70%);
            top: -100px; right: -100px;
        }
        
        .auth-container { width: 100%; max-width: 480px; padding: 24px; position: relative; z-index: 10; }
        
        .auth-card {
            background: var(--card-glass);
            border: 1px solid var(--card-border);
            border-radius: 28px; padding: 44px 34px;
            box-shadow: 0 16px 40px rgba(0,0,0,0.06), inset 0 1px 0 #fff;
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            text-align: center;
        }
        
        .brand {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-bottom: 28px; text-decoration: none;
        }
        .brand-text {
            font-size: 24px; font-weight: 800; color: #1A1208; letter-spacing: -0.45px;
        }
        .brand-text b { color: #D97706; font-weight: 800; }
        
        h2 { font-size: 24px; font-weight: 900; color: #1A1208; margin-bottom: 8px; letter-spacing: -0.5px; }
        .subtitle { font-size: 13.5px; color: var(--text-light); margin-bottom: 30px; line-height: 1.5; }
        
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 700; color: #1A1208; margin-bottom: 8px; }
        .form-input {
            width: 100%; padding: 14px 18px; border-radius: 12px;
            border: 1px solid rgba(26,18,8,0.12); background: rgba(255, 255, 255, 0.8);
            font-family: inherit; font-size: 14px; color: #1A1208; transition: all 0.3s ease;
        }
        .form-input:focus {
            outline: none; border-color: var(--accent-orange); background: #ffffff;
            box-shadow: 0 0 0 4px rgba(255, 140, 0, 0.12);
        }

        /* Alert notifications */
        .alert {
            font-size: 13.5px; padding: 14px 18px; border-radius: 14px; margin-bottom: 24px;
            text-align: left; line-height: 1.5;
        }
        .alert-error { background-color: #ffebe5; color: #d12e00; border: 1px solid #ffd4cb; }
        .alert-success { background-color: #e5f6ed; color: #1e7e44; border: 1px solid #ccece5; }
        
        .btn-submit {
            width: 100%; padding: 15px; border: none; border-radius: 12px;
            background: var(--gradient-accent); color: #ffffff;
            font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 6px 20px rgba(255, 140, 0, 0.2); transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(255, 140, 0, 0.3); }
        
        .footer-text { margin-top: 28px; font-size: 13.5px; color: var(--text-light); }
        .footer-text a { color: var(--accent-orange); text-decoration: none; font-weight: 700; transition: color 0.2s ease; }
    </style>
</head>
<body>

    <div class="blob blob-1"></div>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Brand Logo -->
            <a href="index.php" class="brand">
                <img src="assets/img/logo.png" alt="Mango Number Logo" style="width: 34px; height: 34px; object-fit: contain; border-radius: 10px; box-shadow: 0 4px 14px rgba(255,140,0,0.3);">
                <span class="brand-text">Mango <b>Number</b></span>
            </a>
            
            <h2>Brevo SMTP Test</h2>
            <p class="subtitle">Test your email delivery configurations</p>
            
            <!-- Success/Error Alerts -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <form action="test_email.php" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Email field -->
                <div class="form-group">
                    <label class="form-label" for="email">Recipient Email Address</label>
                    <input class="form-input" type="email" name="email" id="email" placeholder="e.g. your-email@gmail.com" required value="<?= htmlspecialchars($email_input) ?>">
                </div>
                
                <button type="submit" class="btn-submit">Send Test Email</button>
            </form>
            
            <p class="footer-text">
                Back to <a href="index.php">Landing Page</a>
            </p>
        </div>
    </div>
</body>
</html>

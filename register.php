<?php
/**
 * Mango Number - User Registration with Secure Email OTP Verification
 */

require_once __DIR__ . '/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account – Mango Number</title>
    <meta name="description" content="Create a Mango Number account to buy virtual SMS verification numbers for Telegram and WhatsApp.">
    <link rel="icon" type="image/png" href="assets/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #09090f; --surface: #111118; --elevated: #1a1a26;
            --border: rgba(255,255,255,0.07); --accent: #f97316;
            --accent-glow: rgba(249,115,22,0.18);
            --text: #f1f5f9; --muted: #64748b;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; overflow: hidden;
        }
        .left-panel {
            flex: 1; position: relative;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 60px 48px; overflow: hidden;
        }
        .left-panel::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse 80% 80% at 50% 0%, rgba(249,115,22,0.2) 0%, transparent 70%),
                        radial-gradient(ellipse 60% 60% at 80% 100%, rgba(34,197,94,0.08) 0%, transparent 60%);
        }
        .mesh {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(249,115,22,0.06) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(249,115,22,0.06) 1px, transparent 1px);
            background-size: 48px 48px; animation: meshMove 20s linear infinite;
        }
        @keyframes meshMove { 0% { background-position: 0 0; } 100% { background-position: 48px 48px; } }
        .left-content { position: relative; z-index: 2; text-align: center; max-width: 400px; }
        .left-logo { display: inline-flex; align-items: center; gap: 12px; margin-bottom: 48px; text-decoration: none; }
        .left-logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg,#f97316,#fb923c); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 24px rgba(249,115,22,0.5); }
        .left-logo-icon img { width: 28px; height: 28px; object-fit: contain; }
        .left-logo-text { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800; color: var(--text); }
        .left-logo-text span { color: var(--accent); }
        .left-headline { font-family: 'Sora', sans-serif; font-size: 36px; font-weight: 800; line-height: 1.2; letter-spacing: -1px; color: var(--text); margin-bottom: 18px; }
        .left-headline em { color: var(--accent); font-style: normal; }
        .left-sub { font-size: 15px; color: var(--muted); line-height: 1.7; margin-bottom: 40px; }
        .steps { display: flex; flex-direction: column; gap: 14px; text-align: left; }
        .step { display: flex; align-items: flex-start; gap: 14px; }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: var(--accent); color: #fff; font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .step-text { font-size: 13.5px; color: #94a3b8; line-height: 1.5; }
        .step-text strong { color: var(--text); font-weight: 600; display: block; margin-bottom: 2px; }

        .right-panel { width: 500px; flex-shrink: 0; background: var(--surface); border-left: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: center; padding: 48px 40px; overflow-y: auto; }
        .auth-box { width: 100%; padding: 12px 0; }
        .auth-title { font-family: 'Sora', sans-serif; font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
        .auth-sub { font-size: 14px; color: var(--muted); margin-bottom: 28px; }

        .alert-box { border-radius: 12px; padding: 13px 16px; margin-bottom: 20px; font-size: 13.5px; line-height: 1.5; display: none; }
        .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }
        .alert-success { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2); color: #86efac; }
        .alert-static { display: block; background: rgba(249,115,22,0.08); border: 1px solid rgba(249,115,22,0.22); color: #fb923c; border-radius: 12px; padding: 16px; margin-bottom: 20px; font-size: 13.5px; text-align: center; line-height: 1.6; }

        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; }
        .input-row { display: flex; gap: 10px; }
        input[type="text"], input[type="password"], input[type="email"], input[type="tel"] {
            width: 100%; background: var(--elevated); border: 1px solid var(--border); border-radius: 10px;
            padding: 13px 16px; font-family: 'Inter', sans-serif; font-size: 14px; color: var(--text);
            transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
        input::placeholder { color: #334155; }
        input:disabled { opacity: 0.45; cursor: not-allowed; }

        .btn-green {
            padding: 13px 18px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #16a34a, #22c55e);
            color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
            white-space: nowrap; flex-shrink: 0;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(34,197,94,0.25);
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-green:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,197,94,0.35); }
        .btn-green:disabled { background: #1e293b; color: #334155; box-shadow: none; transform: none; cursor: not-allowed; }

        .otp-section { display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); }
        .timer-hint { font-size: 12px; color: var(--muted); margin-top: 8px; display: none; }

        .btn-primary {
            width: 100%; padding: 14px; background: linear-gradient(135deg,#f97316,#fb923c);
            border: none; border-radius: 10px; font-family: 'Sora', sans-serif;
            font-size: 15px; font-weight: 700; color: #fff; cursor: pointer;
            box-shadow: 0 4px 20px rgba(249,115,22,0.3); transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 8px; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(249,115,22,0.4); }
        .btn-primary:disabled { background: #1e293b; color: #334155; box-shadow: none; transform: none; cursor: not-allowed; }

        .btn-telegram {
            width: 100%; padding: 14px; background: linear-gradient(135deg,#0088cc,#29b6f6);
            border: none; border-radius: 10px; font-family: 'Sora', sans-serif;
            font-size: 15px; font-weight: 700; color: #fff; cursor: pointer;
            box-shadow: 0 4px 20px rgba(0,136,204,0.3); transition: transform 0.2s;
            text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .footer-note { text-align: center; font-size: 13.5px; color: var(--muted); margin-top: 24px; }
        .footer-note a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .footer-note a:hover { text-decoration: underline; }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { width: 15px; height: 15px; border: 2.5px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; }

        @media (max-width: 900px) { body { flex-direction: column; overflow: auto; } .left-panel { display: none; } .right-panel { width: 100%; border-left: none; min-height: 100vh; padding: 44px 24px; } }
        @media (max-width: 480px) { .right-panel { padding: 32px 18px; } .input-row { flex-direction: column; } .btn-green { width: 100%; } }
    </style>
</head>
<body>
    <!-- Left Branding Panel -->
    <div class="left-panel">
        <div class="mesh"></div>
        <div class="left-content">
            <a href="index.php" class="left-logo">
                <div class="left-logo-icon"><img src="assets/img/logo.png" alt="Logo"></div>
                <span class="left-logo-text">Mango<span>Number</span></span>
            </a>
            <h1 class="left-headline">Join <em>thousands</em> of verified users</h1>
            <p class="left-sub">Create your account in under 2 minutes. Email OTP verification keeps your account secure.</p>
            <div class="steps">
                <div class="step"><div class="step-num">1</div><div class="step-text"><strong>Enter your email</strong>Receive a 6-digit OTP for verification</div></div>
                <div class="step"><div class="step-num">2</div><div class="step-text"><strong>Set your password</strong>Create a secure account password</div></div>
                <div class="step"><div class="step-num">3</div><div class="step-text"><strong>Start buying numbers</strong>Browse catalog and get verified instantly</div></div>
            </div>
        </div>
    </div>

    <!-- Right Form Panel -->
    <div class="right-panel">
        <div class="auth-box">
            <h2 class="auth-title">Create account</h2>
            <p class="auth-sub">Join Mango Number — it's free to sign up</p>

            <div id="alert-error" class="alert-box alert-error"></div>
            <div id="alert-success" class="alert-box alert-success"></div>

            <?php if (get_system_setting('allow_signups', '1') === '0'): ?>
                <div class="alert-static">
                    ⚠️ <strong>Sign-ups are currently closed.</strong><br>
                    Contact the owner to get access.
                </div>
                <a href="https://t.me/nu2rl" target="_blank" class="btn-telegram">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="white"><path d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm5.56 8.16l-1.85 8.74c-.14.62-.51.77-1.03.48l-2.82-2.08-1.36 1.31c-.15.15-.28.28-.57.28l.2-2.86 5.21-4.71c.23-.2-.05-.31-.36-.1l-6.44 4.05-2.77-.87c-.6-.19-.61-.6.13-.89l10.82-4.17c.5-.18.94.12.77.72z"/></svg>
                    Contact Owner on Telegram
                </a>
            <?php else: ?>
            <form id="signup-form" onsubmit="event.preventDefault(); verifyAndSignup();">
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" name="name" id="name" placeholder="e.g. Rahul Sharma" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-row">
                        <input type="email" name="email" id="email" placeholder="e.g. rahul@example.com" required>
                        <button type="button" class="btn-green" id="btn-send-otp" onclick="sendOtpCode()">
                            <span id="btn-otp-text">Send OTP</span>
                        </button>
                    </div>
                </div>

                <div class="otp-section" id="otp-wrapper">
                    <div class="form-group">
                        <label for="otp">6-Digit OTP Code</label>
                        <div class="input-row">
                            <input type="text" name="otp" id="otp" placeholder="Enter code" maxlength="6" pattern="\d{6}" required disabled>
                            <button type="button" class="btn-green" id="btn-resend-otp" onclick="sendOtpCode(true)" disabled>Resend</button>
                        </div>
                        <span class="timer-hint" id="timer-label">Resend in <strong id="timer-sec">60</strong>s</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="mobile">Mobile Number</label>
                    <input type="tel" name="mobile" id="mobile" placeholder="e.g. +91 99999 99999" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="At least 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required>
                </div>

                <button type="submit" class="btn-primary" id="btn-signup" disabled>Verify &amp; Create Account</button>
            </form>
            <?php endif; ?>

            <div class="footer-note">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>

    <script>
        var timerInterval = null;
        var csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

        function showAlert(type, msg) {
            var errEl = document.getElementById('alert-error');
            var succEl = document.getElementById('alert-success');
            errEl.style.display = 'none'; succEl.style.display = 'none';
            if (type === 'error') { errEl.innerText = msg; errEl.style.display = 'block'; }
            else if (type === 'success') { succEl.innerText = msg; succEl.style.display = 'block'; }
        }

        function sendOtpCode(isResend = false) {
            var emailInput = document.getElementById('email');
            var email = emailInput.value.trim();
            var btnSend = document.getElementById('btn-send-otp');
            var btnResend = document.getElementById('btn-resend-otp');
            var btnOtpText = document.getElementById('btn-otp-text');
            if (!email) { showAlert('error', 'Please enter a valid email address first.'); return; }
            if (!isResend) { btnSend.disabled = true; btnOtpText.innerHTML = '<span class="spinner"></span>'; }
            else { btnResend.disabled = true; btnResend.innerHTML = '<span class="spinner"></span>'; }
            showAlert('none', '');
            var fd = new FormData();
            fd.append('email', email); fd.append('csrf_token', csrfToken);
            fetch('auth_handler.php?action=send-signup-otp', { method: 'POST', headers: {'X-CSRF-Token': csrfToken}, body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    document.getElementById('otp-wrapper').style.display = 'block';
                    var otpInput = document.getElementById('otp');
                    otpInput.disabled = false; otpInput.focus();
                    document.getElementById('btn-signup').disabled = false;
                    emailInput.disabled = true; btnSend.style.display = 'none';
                    startResendTimer();
                } else {
                    showAlert('error', data.error);
                    if (!isResend) { btnSend.disabled = false; btnOtpText.innerText = 'Send OTP'; }
                    else { btnResend.disabled = false; btnResend.innerText = 'Resend'; }
                }
            })
            .catch(() => {
                showAlert('error', 'Something went wrong. Check your connection.');
                if (!isResend) { btnSend.disabled = false; btnOtpText.innerText = 'Send OTP'; }
                else { btnResend.disabled = false; btnResend.innerText = 'Resend'; }
            });
        }

        function startResendTimer() {
            var btnResend = document.getElementById('btn-resend-otp');
            var timerLabel = document.getElementById('timer-label');
            var timerSec = document.getElementById('timer-sec');
            btnResend.disabled = true; btnResend.innerText = 'Resend';
            timerLabel.style.display = 'block';
            var count = 60; timerSec.innerText = count;
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                count--; timerSec.innerText = count;
                if (count <= 0) { clearInterval(timerInterval); btnResend.disabled = false; timerLabel.style.display = 'none'; }
            }, 1000);
        }

        function verifyAndSignup() {
            var btnSignup = document.getElementById('btn-signup');
            var name = document.getElementById('name').value.trim();
            var email = document.getElementById('email').value.trim();
            var mobile = document.getElementById('mobile').value.trim();
            var otp = document.getElementById('otp').value.trim();
            var password = document.getElementById('password').value;
            var confirmPassword = document.getElementById('confirm_password').value;
            if (password !== confirmPassword) { showAlert('error', 'Passwords do not match.'); return; }
            btnSignup.disabled = true;
            btnSignup.innerHTML = '<span class="spinner"></span> Verifying...';
            showAlert('none', '');
            var fd = new FormData();
            fd.append('name', name); fd.append('email', email); fd.append('mobile', mobile);
            fd.append('otp', otp); fd.append('password', password);
            fd.append('confirm_password', confirmPassword); fd.append('csrf_token', csrfToken);
            fetch('auth_handler.php?action=verify-signup-otp', { method: 'POST', headers: {'X-CSRF-Token': csrfToken}, body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showAlert('success', 'Account created! Redirecting...'); setTimeout(() => { window.location.href = 'dashboard.php'; }, 1200); }
                else { showAlert('error', data.error); btnSignup.disabled = false; btnSignup.innerHTML = 'Verify &amp; Create Account'; }
            })
            .catch(() => { showAlert('error', 'Verification failed. Please try again.'); btnSignup.disabled = false; btnSignup.innerHTML = 'Verify &amp; Create Account'; });
        }
    </script>
    <script src="assets/js/anti-devtools.js"></script>
</body>
</html>

<?php
/**
 * Mango Number - Forgot Password Secure Email OTP Verification
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
    <title>Reset Password – Mango Number</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#09090f; --surface:#111118; --elevated:#1a1a26; --border:rgba(255,255,255,0.07); --accent:#f97316; --accent-glow:rgba(249,115,22,0.18); --text:#f1f5f9; --muted:#64748b; }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        html{-webkit-font-smoothing:antialiased;}
        body{ font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; position:relative; overflow:hidden; }
        /* Glow blobs */
        .glow{ position:absolute; border-radius:50%; filter:blur(100px); pointer-events:none; }
        .glow-1{ width:500px; height:500px; background:radial-gradient(circle, rgba(249,115,22,0.15) 0%, transparent 70%); top:-150px; right:-150px; }
        .glow-2{ width:400px; height:400px; background:radial-gradient(circle, rgba(249,115,22,0.08) 0%, transparent 70%); bottom:-120px; left:-120px; }
        .auth-wrap{ width:100%; max-width:440px; position:relative; z-index:2; }
        .back-link{ display:inline-flex; align-items:center; gap:6px; color:var(--muted); font-size:13px; font-weight:600; text-decoration:none; margin-bottom:28px; transition:color 0.2s; }
        .back-link:hover{ color:var(--accent); }
        .back-link svg{ width:16px; height:16px; }
        .card{ background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:40px 36px; }
        .logo-row{ display:flex; align-items:center; gap:10px; margin-bottom:28px; text-decoration:none; }
        .logo-icon{ width:36px; height:36px; background:linear-gradient(135deg,#f97316,#fb923c); border-radius:10px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 18px rgba(249,115,22,0.4); }
        .logo-icon img{ width:22px; height:22px; object-fit:contain; }
        .logo-text{ font-family:'Sora',sans-serif; font-size:18px; font-weight:800; color:var(--text); }
        .logo-text span{ color:var(--accent); }
        .card-title{ font-family:'Sora',sans-serif; font-size:24px; font-weight:800; color:var(--text); margin-bottom:6px; }
        .card-sub{ font-size:13.5px; color:var(--muted); line-height:1.6; margin-bottom:28px; }
        .alert-box{ border-radius:10px; padding:12px 15px; margin-bottom:20px; font-size:13.5px; line-height:1.5; display:none; }
        .alert-error{ background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#fca5a5; }
        .alert-success{ background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.2); color:#86efac; }
        .form-group{ margin-bottom:18px; }
        label{ display:block; font-size:13px; font-weight:600; color:#cbd5e1; margin-bottom:8px; }
        input[type="text"],input[type="password"],input[type="email"]{
            width:100%; background:var(--elevated); border:1px solid var(--border); border-radius:10px;
            padding:13px 16px; font-family:'Inter',sans-serif; font-size:14px; color:var(--text);
            transition:border-color 0.2s,box-shadow 0.2s; outline:none;
        }
        input:focus{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-glow); }
        input::placeholder{ color:#334155; }
        input:disabled{ opacity:0.4; cursor:not-allowed; }
        .input-row{ display:flex; gap:10px; }
        .btn-green{ padding:13px 18px; border:none; border-radius:10px; background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; flex-shrink:0; transition:transform 0.2s; box-shadow:0 4px 14px rgba(34,197,94,0.25); display:inline-flex; align-items:center; justify-content:center; }
        .btn-green:hover{ transform:translateY(-1px); }
        .btn-green:disabled{ background:#1e293b; color:#334155; box-shadow:none; transform:none; cursor:not-allowed; }
        .otp-section{ display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border); }
        .password-section{ display:none; }
        .timer-hint{ font-size:12px; color:var(--muted); margin-top:8px; display:none; }
        .btn-primary{ width:100%; padding:14px; background:linear-gradient(135deg,#f97316,#fb923c); border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:15px; font-weight:700; color:#fff; cursor:pointer; box-shadow:0 4px 20px rgba(249,115,22,0.3); transition:transform 0.2s,box-shadow 0.2s; margin-top:8px; display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-primary:hover{ transform:translateY(-2px); box-shadow:0 8px 28px rgba(249,115,22,0.4); }
        .btn-primary:disabled{ background:#1e293b; color:#334155; box-shadow:none; transform:none; cursor:not-allowed; }
        .footer-note{ text-align:center; font-size:13.5px; color:var(--muted); margin-top:24px; }
        .footer-note a{ color:var(--accent); text-decoration:none; font-weight:600; }
        @keyframes spin{ to{ transform:rotate(360deg); } }
        .spinner{ width:15px; height:15px; border:2.5px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin 0.7s linear infinite; display:inline-block; }
        @media(max-width:480px){ .card{ padding:28px 20px; } .input-row{ flex-direction:column; } .btn-green{ width:100%; } }
    </style>
</head>
<body>
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    <div class="auth-wrap">
        <a href="login.php" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Login
        </a>
        <div class="card">
            <a href="index.php" class="logo-row">
                <div class="logo-icon"><img src="assets/img/logo.png" alt="Logo"></div>
                <span class="logo-text">Mango<span>Number</span></span>
            </a>

            <h2 class="card-title" id="flow-title">Reset password</h2>
            <p class="card-sub" id="flow-subtitle">Enter your registered email to receive a 6-digit OTP verification code.</p>

            <div id="alert-error" class="alert-box alert-error"></div>
            <div id="alert-success" class="alert-box alert-success"></div>

            <!-- Step 1 & 2: Email + OTP -->
            <form id="otp-form" onsubmit="event.preventDefault(); verifyOtpCode();">
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div id="email-verification-group">
                    <div class="form-group">
                        <label for="email">Registered Email Address</label>
                        <div class="input-row">
                            <input type="email" name="email" id="email" placeholder="Enter your email" required>
                            <button type="button" class="btn-green" id="btn-send-otp" onclick="sendOtpCode()">
                                <span id="btn-otp-text">Send OTP</span>
                            </button>
                        </div>
                    </div>
                    <div class="otp-section" id="otp-wrapper">
                        <div class="form-group">
                            <label for="otp">6-Digit OTP Code</label>
                            <div class="input-row">
                                <input type="text" name="otp" id="otp" placeholder="Enter OTP" maxlength="6" pattern="\d{6}" required disabled>
                                <button type="button" class="btn-green" id="btn-resend-otp" onclick="sendOtpCode(true)" disabled>Resend</button>
                            </div>
                            <span class="timer-hint" id="timer-label">Resend in <strong id="timer-sec">60</strong>s</span>
                        </div>
                        <button type="submit" class="btn-primary" id="btn-verify" disabled>Verify OTP</button>
                    </div>
                </div>
            </form>

            <!-- Step 3: New Password -->
            <div class="password-section" id="password-wrapper">
                <form id="password-form" onsubmit="event.preventDefault(); resetPassword();">
                    <div class="form-group">
                        <label for="password-field">New Password</label>
                        <input type="password" name="password" id="password-field" placeholder="At least 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password-field">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password-field" placeholder="Re-enter password" required>
                    </div>
                    <button type="submit" class="btn-primary" id="btn-reset">Update Password</button>
                </form>
            </div>

            <div class="footer-note">Remember it? <a href="login.php">Sign in</a></div>
        </div>
    </div>

    <script>
        var timerInterval = null;
        var csrfToken = document.getElementById('csrf_token').value;
        function showAlert(type, msg) {
            var e=document.getElementById('alert-error'), s=document.getElementById('alert-success');
            e.style.display='none'; s.style.display='none';
            if(type==='error'){e.innerText=msg;e.style.display='block';}
            else if(type==='success'){s.innerText=msg;s.style.display='block';}
        }
        function sendOtpCode(isResend=false){
            var emailInput=document.getElementById('email'), email=emailInput.value.trim();
            var btnSend=document.getElementById('btn-send-otp'), btnResend=document.getElementById('btn-resend-otp'), btnOtpText=document.getElementById('btn-otp-text');
            if(!email){showAlert('error','Please enter your registered email address.');return;}
            if(!isResend){btnSend.disabled=true;btnOtpText.innerHTML='<span class="spinner"></span>';}
            else{btnResend.disabled=true;btnResend.innerHTML='<span class="spinner"></span>';}
            showAlert('none','');
            var fd=new FormData(); fd.append('email',email); fd.append('csrf_token',csrfToken);
            fetch('auth_handler.php?action=send-forgot-password-otp',{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:fd})
            .then(r=>r.json()).then(data=>{
                if(data.success){
                    showAlert('success',data.message);
                    document.getElementById('otp-wrapper').style.display='block';
                    var otpInput=document.getElementById('otp'); otpInput.disabled=false; otpInput.focus();
                    document.getElementById('btn-verify').disabled=false;
                    emailInput.disabled=true; btnSend.style.display='none';
                    startResendTimer();
                } else {
                    showAlert('error',data.error);
                    if(!isResend){btnSend.disabled=false;btnOtpText.innerText='Send OTP';}
                    else{btnResend.disabled=false;btnResend.innerText='Resend';}
                }
            }).catch(()=>{showAlert('error','Something went wrong.');if(!isResend){btnSend.disabled=false;btnOtpText.innerText='Send OTP';}else{btnResend.disabled=false;btnResend.innerText='Resend';}});
        }
        function startResendTimer(){
            var btnResend=document.getElementById('btn-resend-otp'), timerLabel=document.getElementById('timer-label'), timerSec=document.getElementById('timer-sec');
            btnResend.disabled=true; btnResend.innerText='Resend'; timerLabel.style.display='block';
            var count=60; timerSec.innerText=count;
            if(timerInterval)clearInterval(timerInterval);
            timerInterval=setInterval(()=>{count--;timerSec.innerText=count;if(count<=0){clearInterval(timerInterval);btnResend.disabled=false;timerLabel.style.display='none';}},1000);
        }
        function verifyOtpCode(){
            var btnVerify=document.getElementById('btn-verify');
            var email=document.getElementById('email').value.trim(), otp=document.getElementById('otp').value.trim();
            btnVerify.disabled=true; btnVerify.innerHTML='<span class="spinner"></span> Verifying...'; showAlert('none','');
            var fd=new FormData(); fd.append('email',email); fd.append('otp',otp); fd.append('csrf_token',csrfToken);
            fetch('auth_handler.php?action=verify-forgot-password-otp',{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:fd})
            .then(r=>r.json()).then(data=>{
                if(data.success){
                    showAlert('success',data.message);
                    document.getElementById('otp-form').style.display='none';
                    document.getElementById('password-wrapper').style.display='block';
                    document.getElementById('flow-title').innerText='Set new password';
                    document.getElementById('flow-subtitle').innerText='Choose a strong password for your account';
                    document.getElementById('password-field').focus();
                } else {showAlert('error',data.error);btnVerify.disabled=false;btnVerify.innerText='Verify OTP';}
            }).catch(()=>{showAlert('error','Verification failed. Try again.');btnVerify.disabled=false;btnVerify.innerText='Verify OTP';});
        }
        function resetPassword(){
            var btnReset=document.getElementById('btn-reset');
            var password=document.getElementById('password-field').value, confirmPassword=document.getElementById('confirm_password-field').value;
            if(password!==confirmPassword){showAlert('error','Passwords do not match.');return;}
            btnReset.disabled=true; btnReset.innerHTML='<span class="spinner"></span> Updating...'; showAlert('none','');
            var fd=new FormData(); fd.append('password',password); fd.append('confirm_password',confirmPassword); fd.append('csrf_token',csrfToken);
            fetch('auth_handler.php?action=reset-password',{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:fd})
            .then(r=>r.json()).then(data=>{
                if(data.success){showAlert('success','Password updated! Redirecting...');setTimeout(()=>{window.location.href='login.php';},1400);}
                else{showAlert('error',data.error);btnReset.disabled=false;btnReset.innerText='Update Password';}
            }).catch(()=>{showAlert('error','Request failed. Try again.');btnReset.disabled=false;btnReset.innerText='Update Password';});
        }
    </script>
    <script src="assets/js/anti-devtools.js"></script>
</body>
</html>

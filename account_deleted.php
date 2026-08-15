<?php
/**
 * Mango Number - Account Terminated / Session Purged Page
 */
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deleted – Mango Number</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#09090f;--surface:#111118;--border:rgba(255,255,255,0.07);--accent:#f97316;--text:#f1f5f9;--muted:#64748b;}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        html{-webkit-font-smoothing:antialiased;}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden;}
        .glow{position:absolute;border-radius:50%;filter:blur(100px);pointer-events:none;}
        .glow-1{width:500px;height:500px;background:radial-gradient(circle,rgba(239,68,68,0.15)0%,transparent 70%);top:-150px;right:-150px;}
        .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(249,115,22,0.1)0%,transparent 70%);bottom:-100px;left:-100px;}
        .card{background:var(--surface);border:1px solid rgba(239,68,68,0.2);border-top:2px solid rgba(239,68,68,0.5);border-radius:20px;max-width:480px;width:100%;padding:44px 36px;text-align:center;position:relative;z-index:2;animation:fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) both;}
        @keyframes fadeUp{0%{opacity:0;transform:translateY(24px);}100%{opacity:1;transform:translateY(0);}}
        .icon{font-size:52px;margin-bottom:20px;animation:pulse 2.5s ease-in-out infinite;}
        @keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.06);filter:drop-shadow(0 0 12px rgba(239,68,68,0.4));}}
        h1{font-family:'Sora',sans-serif;font-size:26px;font-weight:800;color:#f87171;margin-bottom:12px;}
        p{font-size:14px;color:var(--muted);line-height:1.7;margin-bottom:28px;}
        p strong{color:#fca5a5;}
        .progress-track{height:4px;background:rgba(255,255,255,0.06);border-radius:99px;overflow:hidden;margin-bottom:20px;}
        .progress-fill{height:100%;background:linear-gradient(90deg,#ef4444,#f97316);border-radius:99px;width:100%;animation:shrink 15s linear forwards;}
        @keyframes shrink{0%{width:100%;}100%{width:0%;}}
        .timer-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:99px;padding:8px 18px;font-size:13.5px;font-weight:600;color:#fca5a5;}
        @media(max-width:480px){.card{padding:32px 20px;}}
    </style>
</head>
<body>
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>Account Terminated</h1>
        <p>Your account has been <strong>permanently deleted</strong> by the administrator. Your session has been purged and all security tokens have been reset.</p>
        <div class="progress-track"><div class="progress-fill"></div></div>
        <div class="timer-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Redirecting in <strong id="countdown-sec">15</strong>s
        </div>
    </div>
    <script>
        var count = 15;
        var el = document.getElementById('countdown-sec');
        var interval = setInterval(function() {
            count--; el.innerText = count;
            if (count <= 0) { clearInterval(interval); window.location.href = 'index.php'; }
        }, 1000);
    </script>
    <script src="assets/js/anti-devtools.js"></script>
</body>
</html>

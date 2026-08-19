<?php
/**
 * Mango Number - World-Class SaaS Landing Page
 */

require_once __DIR__ . '/config.php';

$db = get_db_connection();
$telegram_items = [];
$whatsapp_items = [];
$active_sections = [];
$db_error = false;

if ($db) {
    try {
        // Fetch active sections
        $sections_stmt = $db->query("SELECT * FROM sections WHERE status = 'active' ORDER BY display_order ASC, id ASC");
        $active_sections = $sections_stmt->fetchAll();

        // Fetch Telegram items
        $stmt = $db->prepare("SELECT p.*, p.stock_quantity as stock, s.name as service_type FROM products p JOIN sections s ON p.section_id = s.id WHERE s.name LIKE '%Telegram%' AND p.status = 'active' ORDER BY p.price_inr ASC");
        $stmt->execute();
        $tg_products = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM catalog WHERE service_type = 'Telegram' AND status = 'active' ORDER BY price_inr ASC");
        $stmt->execute();
        $telegram_items = array_merge($tg_products, $stmt->fetchAll());

        // Fetch WhatsApp items
        $stmt = $db->prepare("SELECT p.*, p.stock_quantity as stock, s.name as service_type FROM products p JOIN sections s ON p.section_id = s.id WHERE s.name LIKE '%WhatsApp%' AND p.status = 'active' ORDER BY p.price_inr ASC");
        $stmt->execute();
        $wa_products = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM catalog WHERE service_type = 'WhatsApp' AND status = 'active' ORDER BY price_inr ASC");
        $stmt->execute();
        $whatsapp_items = array_merge($wa_products, $stmt->fetchAll());
    } catch (PDOException $e) {
        $db_error = true;
    }
} else {
    $db_error = true;
}

function get_flag($country) {
    $country = strtolower($country);
    $flags = [
        'india' => '🇮🇳',
        'usa' => '🇺🇸',
        'myanmar' => '🇲🇲',
        'vietnam' => '🇻🇳',
        'canada' => '🇨🇦',
        'chile' => '🇨🇱',
        'afghanistan' => '🇦🇫',
        'greenland' => '🇬🇱',
        'united arab emirates' => '🇦🇪',
        'fiji' => '🇫🇯',
        'russia' => '🇷🇺',
        'france' => '🇫🇷',
        'china' => '🇨🇳',
        'turkey' => '🇹🇷',
        'germany' => '🇩🇪',
        'philippines' => '🇵🇭'
    ];
    return $flags[$country] ?? '🌐';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Unlock 100+ Premium Virtual Numbers &amp; Digital Services – Mango Number</title>
    
    <meta name="description" content="Access 100+ premium virtual numbers, Telegram & WhatsApp OTP verifications, Canva Premium, productivity suites and AI tools with Mango Number.">
    <meta name="keywords" content="virtual numbers, telegram otp, whatsapp verification, canva premium, free sms verification, Mango Number">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Unlock 100+ Premium Virtual Numbers &amp; AI Tools | Mango Number">
    <meta property="og:description" content="Instant virtual SMS numbers for Telegram & WhatsApp, Canva Premium, and digital tools with Mango Number.">
    <meta property="og:image" content="assets/img/logo.png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Mango Number">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    
    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <style>
    :root {
      --bg: #030712;
      --bg-elevated: rgba(17, 24, 39, 0.95);
      --primary: #ff5e36;
      --accent: #ff8a1f;
      --primary-soft: rgba(255, 94, 54, 0.4);
      --text-main: #f3f4f6;
      --text-muted: #9ca3af;
      --border-subtle: rgba(255, 138, 31, 0.25);
      --error-bg: rgba(248,113,113,.12);
      --error-border: rgba(248,113,113,.6);
      --error-text: #fecaca;
    }

    /* Reset */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    html, body {
      height: 100%;
      scroll-behavior: smooth;
    }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      color: var(--text-main);
      background:
        radial-gradient(circle at 0% 0%, rgba(255, 94, 54, 0.22) 0, transparent 55%),
        radial-gradient(circle at 100% 0%, rgba(255, 138, 31, 0.25) 0, transparent 55%),
        radial-gradient(circle at 50% 100%, rgba(124, 58, 237, 0.18) 0, transparent 55%),
        var(--bg);
      overflow-x: hidden;
    }

    /* Layout */
    .page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    main {
      flex: 1;
    }
    .container {
      width: 100%;
      max-width: 1160px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* Links */
    a {
      color: inherit;
      text-decoration: none;
    }
    a:hover {
      text-decoration: none;
    }

    /* Header */
    .site-header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: rgba(3, 7, 18, 0.88);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .header-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 14px 0;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .brand-logo-icon {
      width: 38px;
      height: 38px;
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 0 20px rgba(255, 94, 54, 0.4);
    }
    .brand-logo-icon img {
      width: 24px;
      height: 24px;
      object-fit: contain;
    }
    .brand-text {
      font-family: 'Outfit', sans-serif;
      font-size: 1.25rem;
      font-weight: 800;
      color: #ffffff;
      letter-spacing: -0.5px;
    }
    .brand-text span {
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .brand-tagline {
      font-size: .78rem;
      color: var(--text-muted);
    }
    .nav {
      display: flex;
      align-items: center;
      gap: 24px;
      font-size: .9rem;
      font-weight: 500;
    }
    .nav a {
      color: var(--text-muted);
      transition: color 0.2s;
    }
    .nav a:hover {
      color: #ffffff;
    }
    .header-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 999px;
      border: none;
      font-size: .88rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: all .2s ease;
    }
    .btn-primary {
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      color: #ffffff;
      box-shadow: 0 10px 30px rgba(255, 94, 54, 0.45);
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 40px rgba(255, 94, 54, 0.6);
      color: #ffffff;
    }
    .btn-ghost {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #f3f4f6;
    }
    .btn-ghost:hover {
      background: rgba(255, 255, 255, 0.12);
      color: #ffffff;
    }
    .btn-sm {
      padding: 8px 16px;
      font-size: .82rem;
    }
    .btn-full {
      width: 100%;
    }

    /* Hero */
    .hero {
      padding: 48px 0 60px;
      position: relative;
    }
    .hero-inner {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
      gap: 48px;
      align-items: center;
    }
    .hero-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      border: 1px solid var(--border-subtle);
      background: rgba(17, 24, 39, 0.9);
      font-size: .82rem;
      font-weight: 600;
      margin-bottom: 18px;
    }
    .hero-kicker-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: #22c55e;
      box-shadow: 0 0 10px #22c55e;
    }
    .hero-title {
      font-size: 2.85rem;
      line-height: 1.15;
      font-weight: 800;
      font-family: 'Outfit', sans-serif;
      margin-bottom: 16px;
      letter-spacing: -0.5px;
    }
    .hero-title span.highlight {
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .hero-sub {
      font-size: 1.05rem;
      color: #9ca3af;
      line-height: 1.65;
      max-width: 34rem;
      margin-bottom: 24px;
    }
    .hero-bullets {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 28px;
    }
    .hero-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      background: rgba(17, 24, 39, 0.8);
      font-size: .85rem;
      font-weight: 500;
      color: #e5e7eb;
    }

    /* Hero Card (Registration) */
    .hero-card {
      background: rgba(17, 24, 39, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-radius: 24px;
      border: 1.5px solid rgba(255, 138, 31, 0.3);
      box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.1);
      padding: 28px 24px;
    }
    .hero-card-title {
      font-size: 1.25rem;
      font-weight: 700;
      font-family: 'Outfit', sans-serif;
      margin-bottom: 6px;
      color: #ffffff;
    }
    .hero-card-sub {
      font-size: .86rem;
      color: var(--text-muted);
      margin-bottom: 20px;
    }
    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 14px;
    }
    .field label {
      font-size: .76rem;
      font-weight: 700;
      color: #9ca3af;
      letter-spacing: .05em;
      text-transform: uppercase;
    }
    .field input {
      width: 100%;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      background: rgba(3, 7, 18, 0.8);
      color: #f3f4f6;
      font-family: inherit;
      font-size: .9rem;
      transition: all .2s ease;
    }
    .field input::placeholder {
      color: #4b5563;
    }
    .field input:focus {
      outline: none;
      border-color: #ff5e36;
      background: rgba(3, 7, 18, 0.95);
      box-shadow: 0 0 0 4px rgba(255, 94, 54, 0.25);
    }
    .hero-card-footer {
      margin-top: 14px;
      font-size: .85rem;
      color: var(--text-muted);
      text-align: center;
    }
    .hero-card-footer a {
      color: #ff8a1f;
      font-weight: 600;
    }
    .hero-card-footer a:hover {
      text-decoration: underline;
    }

    /* Metrics Strip */
    .metrics-strip {
      margin: 40px auto 0;
      max-width: 1060px;
      background: rgba(17, 24, 39, 0.7);
      backdrop-filter: blur(16px);
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 16px 32px;
      display: flex;
      flex-wrap: wrap;
      gap: 24px;
      justify-content: space-between;
      align-items: center;
    }
    .metric {
      display: flex;
      flex-direction: column;
    }
    .metric-value {
      font-weight: 800;
      font-size: 1.15rem;
      font-family: 'Outfit', sans-serif;
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .metric-label {
      color: var(--text-muted);
      font-size: .82rem;
    }

    /* Sections */
    .section {
      padding: 64px 0;
    }
    .section-header {
      margin-bottom: 36px;
      text-align: center;
    }
    .section-kicker {
      font-size: .82rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      font-weight: 700;
      color: #ff8a1f;
      margin-bottom: 8px;
    }
    .section-title {
      font-size: 2rem;
      font-weight: 800;
      font-family: 'Outfit', sans-serif;
      margin-bottom: 10px;
      color: #ffffff;
    }
    .section-text {
      font-size: .95rem;
      color: var(--text-muted);
      max-width: 38rem;
      margin: 0 auto;
      line-height: 1.6;
    }

    /* Features Grid */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 24px;
    }
    .feature-card {
      background: rgba(17, 24, 39, 0.7);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      padding: 24px;
      transition: transform 0.2s, border-color 0.2s;
    }
    .feature-card:hover {
      transform: translateY(-4px);
      border-color: rgba(255, 94, 54, 0.4);
    }
    .feature-icon {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(255, 94, 54, 0.15), rgba(255, 138, 31, 0.15));
      color: #ff8a1f;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 14px;
      font-size: 1.25rem;
    }
    .feature-title {
      font-size: 1.1rem;
      font-weight: 700;
      font-family: 'Outfit', sans-serif;
      margin-bottom: 8px;
      color: #ffffff;
    }
    .feature-text {
      font-size: .88rem;
      color: var(--text-muted);
      line-height: 1.6;
    }

    /* Catalog Offer Cards Section */
    .catalog-card {
      background: rgba(17, 24, 39, 0.85);
      border-radius: 20px;
      border: 1.5px solid rgba(255, 255, 255, 0.08);
      padding: 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.25s ease;
    }
    .catalog-card:hover {
      border-color: rgba(255, 94, 54, 0.45);
      box-shadow: 0 16px 40px rgba(255, 94, 54, 0.15);
      transform: translateY(-3px);
    }

    /* Steps */
    .steps-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 24px;
    }
    .step-card {
      background: rgba(17, 24, 39, 0.7);
      border-radius: 20px;
      border: 1px dashed rgba(255, 138, 31, 0.4);
      padding: 24px;
    }
    .step-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      font-size: .95rem;
      font-weight: 800;
      border-radius: 999px;
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      color: #ffffff;
      margin-bottom: 14px;
    }
    .step-title {
      font-size: 1.05rem;
      font-weight: 700;
      font-family: 'Outfit', sans-serif;
      margin-bottom: 6px;
      color: #ffffff;
    }
    .step-text {
      font-size: .88rem;
      color: var(--text-muted);
      line-height: 1.6;
    }

    /* Testimonials */
    .testimonial-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 24px;
    }
    .testimonial-card {
      background: rgba(17, 24, 39, 0.7);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      padding: 24px;
    }
    .testimonial-quote {
      font-size: .9rem;
      color: #e5e7eb;
      line-height: 1.65;
      margin-bottom: 16px;
    }
    .testimonial-name {
      font-size: .9rem;
      font-weight: 700;
      color: #ffffff;
    }
    .testimonial-meta {
      font-size: .8rem;
      color: var(--text-muted);
    }

    /* Footer */
    .site-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      background: rgba(3, 7, 18, 0.95);
      padding: 48px 0 28px;
    }
    .footer-top {
      display: flex;
      flex-wrap: wrap;
      gap: 36px;
      justify-content: space-between;
    }
    .footer-brand {
      max-width: 320px;
    }
    .footer-text {
      font-size: .85rem;
      color: var(--text-muted);
      line-height: 1.65;
      margin-top: 12px;
    }
    .footer-links {
      display: flex;
      flex-wrap: wrap;
      gap: 48px;
    }
    .footer-column-title {
      font-size: .88rem;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 12px;
      font-family: 'Outfit', sans-serif;
    }
    .footer-list {
      list-style: none;
      font-size: .84rem;
      color: var(--text-muted);
    }
    .footer-list li + li {
      margin-top: 6px;
    }
    .footer-bottom {
      margin-top: 36px;
      padding-top: 20px;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      font-size: .8rem;
      color: var(--text-muted);
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 12px;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .hero-inner {
        grid-template-columns: 1fr;
        gap: 36px;
      }
      .features-grid, .steps-grid, .testimonial-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (max-width: 640px) {
      .hero-title {
        font-size: 2.1rem;
      }
      .nav {
        display: none;
      }
      .features-grid, .steps-grid, .testimonial-grid {
        grid-template-columns: 1fr;
      }
      .metrics-strip {
        border-radius: 20px;
        flex-direction: column;
        align-items: flex-start;
      }
    }
    </style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <header class="site-header">
    <div class="container">
      <div class="header-inner">
        <a href="index.php" class="brand">
          <div class="brand-logo-icon">
            <img src="assets/img/logo.png" alt="Mango Number Logo">
          </div>
          <div>
            <div class="brand-text">MANGO <span>NUMBER</span></div>
            <div class="brand-tagline">World-Class SMS Verification &amp; Digital Marketplace</div>
          </div>
        </a>
        <nav class="nav">
          <a href="#services">Services</a>
          <a href="#why-mango">Why Mango?</a>
          <a href="#how-it-works">How it works</a>
          <a href="#testimonials">Reviews</a>
        </nav>
        <div class="header-actions">
          <?php if (is_logged_in()): ?>
            <a href="dashboard.php" class="btn btn-primary btn-sm">
              <i class="bx bx-grid-alt"></i> Dashboard
            </a>
          <?php else: ?>
            <a href="login.php" class="btn btn-ghost btn-sm">Sign in</a>
            <a href="register.php" class="btn btn-primary btn-sm">Create Account</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero + Quick Account Signup -->
  <main>
    <section class="hero">
      <div class="container">
        <div class="hero-inner">
          <div class="hero-content">
            <div class="hero-kicker">
              <span class="hero-kicker-dot"></span>
              <span>Active users in 20+ countries</span>
            </div>
            <h1 class="hero-title">
              Unlock <span class="highlight">100+ Premium</span> Virtual Numbers &amp; Digital Services.
            </h1>
            <p class="hero-sub">
              Mango Number gives you instant access to dedicated Telegram &amp; WhatsApp virtual numbers, Canva Premium, productivity tools and AI subscriptions — securely without risking your private phone number.
            </p>
            <div class="hero-bullets">
              <span class="hero-pill">
                <span>✅</span> No credit card required
              </span>
              <span class="hero-pill">
                <span>⚡</span> Instant OTP activation in minutes
              </span>
              <span class="hero-pill">
                <span>🛡️</span> 100% Private &amp; Anonymous
              </span>
              <span class="hero-pill">
                <span>💳</span> Paytm / PhonePe UPI &amp; USDT
              </span>
            </div>
          </div>

          <!-- Registration / Login CTA Card -->
          <div class="hero-card" id="register">
            <div class="hero-card-title">Get Started with Mango Number</div>
            <div class="hero-card-sub">Takes less than 60 seconds. Start claiming your virtual numbers &amp; premium digital services today.</div>

            <?php if (is_logged_in()): ?>
              <div style="padding: 20px 0; text-align: center;">
                <div style="font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; color: #ffffff;">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</div>
                <p style="font-size: .88rem; color: #9ca3af; margin-bottom: 20px;">You are currently logged in to your account.</p>
                <a href="dashboard.php" class="btn btn-primary btn-full py-3" style="font-size: 1rem;">
                  Go to Dashboard <i class="bx bx-right-arrow-alt"></i>
                </a>
              </div>
            <?php else: ?>
              <form method="get" action="register.php">
                <div class="field">
                  <label for="reg_email">Email Address</label>
                  <input id="reg_email" type="email" name="email" placeholder="you@example.com" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full py-3" style="font-size: .95rem;">
                  Create Account with Email OTP <i class="bx bx-right-arrow-alt"></i>
                </button>
                <div class="hero-card-footer">
                  Already have an account? <a href="login.php">Sign in here</a>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- Metrics Strip -->
        <div class="metrics-strip">
          <div class="metric">
            <span class="metric-value">1,200+ Active Users</span>
            <span class="metric-label">saving on virtual numbers &amp; subscriptions</span>
          </div>
          <div class="metric">
            <span class="metric-value">100+ Services</span>
            <span class="metric-label">Telegram, WhatsApp, Canva &amp; AI tools</span>
          </div>
          <div class="metric">
            <span class="metric-value">95k+ Delivered</span>
            <span class="metric-label">instant OTP verifications worldwide</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Services / Offers Catalog -->
    <section class="section" id="services">
      <div class="container">
        <div class="section-header">
          <div class="section-kicker">Available Catalog</div>
          <h2 class="section-title">Popular Virtual Numbers &amp; Services</h2>
          <p class="section-text">Explore active virtual country numbers and premium digital subscriptions available right now.</p>
        </div>

        <div class="features-grid">
          <!-- Telegram OTP Numbers -->
          <div class="feature-card">
            <div class="feature-icon" style="background: linear-gradient(135deg, rgba(0, 136, 204, 0.2), rgba(0, 168, 255, 0.2)); color: #0088cc;">
              <i class="bxl-telegram"></i>
            </div>
            <h3 class="feature-title">Telegram OTP Numbers</h3>
            <p class="feature-text">Fresh, dedicated virtual numbers from India, USA, Canada, UK, and Russia for Telegram account &amp; channel creation.</p>
            <div style="margin-top: 16px;">
              <a href="dashboard.php?section=buy" class="btn btn-ghost btn-sm w-100" style="border-color: rgba(0, 136, 204, 0.5); color: #38bdf8;">
                Buy Telegram Numbers <i class="bx bx-chevron-right"></i>
              </a>
            </div>
          </div>

          <!-- WhatsApp Verification -->
          <div class="feature-card">
            <div class="feature-icon" style="background: linear-gradient(135deg, rgba(37, 211, 102, 0.2), rgba(18, 140, 126, 0.2)); color: #25d366;">
              <i class="bxl-whatsapp"></i>
            </div>
            <h3 class="feature-title">WhatsApp Verification</h3>
            <p class="feature-text">Establish secondary WhatsApp accounts without exposing your personal phone number. 100% private &amp; secure.</p>
            <div style="margin-top: 16px;">
              <a href="dashboard.php?section=buy" class="btn btn-ghost btn-sm w-100" style="border-color: rgba(37, 211, 102, 0.5); color: #4ade80;">
                Buy WhatsApp Numbers <i class="bx bx-chevron-right"></i>
              </a>
            </div>
          </div>

          <!-- Canva Premium Lifetime -->
          <div class="feature-card">
            <div class="feature-icon" style="background: linear-gradient(135deg, rgba(125, 42, 232, 0.2), rgba(0, 196, 204, 0.2)); color: #a855f7;">
              <i class="bx bx-paint"></i>
            </div>
            <h3 class="feature-title">Canva Premium Lifetime</h3>
            <p class="feature-text">Unlock full access to Canva Pro brand kits, premium stock templates, AI background remover, and cloud storage.</p>
            <div style="margin-top: 16px;">
              <a href="payment.php?id=1" class="btn btn-ghost btn-sm w-100" style="border-color: rgba(168, 85, 247, 0.5); color: #c084fc;">
                Get Canva Pro <i class="bx bx-chevron-right"></i>
              </a>
            </div>
          </div>

          <!-- Instant Manual Audit Desk -->
          <div class="feature-card">
            <div class="feature-icon">⚡</div>
            <h3 class="feature-title">Fast Manual Audit Desk</h3>
            <p class="feature-text">Our active verification operators audit Paytm/PhonePe UTR receipts and USDT transfers within minutes.</p>
          </div>

          <!-- AI Tools & Subscriptions -->
          <div class="feature-card">
            <div class="feature-icon">🤖</div>
            <h3 class="feature-title">AI Assistants &amp; Utilities</h3>
            <p class="feature-text">Discover productivity suites, chatbots, and AI tools to write, code, and automate your online workflow.</p>
          </div>

          <!-- Total Anonymity & Security -->
          <div class="feature-card">
            <div class="feature-icon">🔒</div>
            <h3 class="feature-title">100% Anonymity &amp; Privacy</h3>
            <p class="feature-text">No KYC or identity documents required. Generate dynamic verifications without leaving any traces or linking logs.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Why Mango Numbers -->
    <section class="section" id="why-mango">
      <div class="container">
        <div class="section-header">
          <div class="section-kicker">Why users choose Mango Number</div>
          <h2 class="section-title">One Dashboard. 100+ Premium Virtual Services.</h2>
          <p class="section-text">
            Instead of risking your private personal phone number or paying high monthly subscription rates, Mango Number aggregates instant SMS verification and digital perks into one easy-to-use client portal.
          </p>
        </div>

        <div class="features-grid">
          <article class="feature-card">
            <div class="feature-icon">📱</div>
            <h3 class="feature-title">Telegram &amp; WhatsApp OTP</h3>
            <p class="feature-text">Access fresh country numbers for audio, messaging, and account verifications instantly.</p>
          </article>
          <article class="feature-card">
            <div class="feature-icon">💼</div>
            <h3 class="feature-title">Productivity &amp; Creative</h3>
            <p class="feature-text">Upgrade your creative stack with Canva Pro, premium note-taking, and project tools.</p>
          </article>
          <article class="feature-card">
            <div class="feature-icon">☁️</div>
            <h3 class="feature-title">Cloud &amp; Storage</h3>
            <p class="feature-text">Store files and backups securely with generous cloud storage options in your dashboard.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- How It Works -->
    <section class="section" id="how-it-works">
      <div class="container">
        <div class="section-header">
          <div class="section-kicker">How it works</div>
          <h2 class="section-title">3 Simple Steps to Start</h2>
          <p class="section-text">Getting started with Mango Number is simple and takes less than 2 minutes.</p>
        </div>

        <div class="steps-grid">
          <article class="step-card">
            <div class="step-badge">1</div>
            <h3 class="step-title">Create Account</h3>
            <p class="step-text">Sign up with your email using instant OTP verification. No credit card or KYC required.</p>
          </article>
          <article class="step-card">
            <div class="step-badge">2</div>
            <h3 class="step-title">Select Service &amp; Pay</h3>
            <p class="step-text">Choose your virtual number or digital offer. Pay via Paytm UPI or USDT TRC-20 and submit the UTR.</p>
          </article>
          <article class="step-card">
            <div class="step-badge">3</div>
            <h3 class="step-title">Receive OTP &amp; Enjoy</h3>
            <p class="step-text">Our live verification operators approve your order and release your OTP code straight to your dashboard.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Testimonials -->
    <section class="section" id="testimonials">
      <div class="container">
        <div class="section-header">
          <div class="section-kicker">Client Reviews</div>
          <h2 class="section-title">Loved by Thousands Worldwide</h2>
        </div>

        <div class="testimonial-grid">
          <article class="testimonial-card">
            <p class="testimonial-quote">
              “Claimed dedicated Telegram virtual numbers and Canva Pro in minutes. Approval was fast and support was very helpful!”
            </p>
            <div class="testimonial-name">Arjun Mehta</div>
            <div class="testimonial-meta">India • Freelancer</div>
          </article>
          <article class="testimonial-card">
            <p class="testimonial-quote">
              “As a student creating social projects, keeping my personal phone number private was critical. Mango Number solved it effortlessly.”
            </p>
            <div class="testimonial-name">Sara Khan</div>
            <div class="testimonial-meta">UAE • Student</div>
          </article>
          <article class="testimonial-card">
            <p class="testimonial-quote">
              “Our digital agency uses Mango Number for WhatsApp verification and design upgrades. Huge savings every month!”
            </p>
            <div class="testimonial-name">David Chen</div>
            <div class="testimonial-meta">USA • Digital Marketer</div>
          </article>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-top">
        <div class="footer-brand">
          <a href="index.php" class="brand" style="margin-bottom: 8px;">
            <div class="brand-logo-icon">
              <img src="assets/img/logo.png" alt="Mango Number Logo">
            </div>
            <div class="brand-text">MANGO <span>NUMBER</span></div>
          </a>
          <p class="footer-text">
            Mango Number is a premium digital marketplace providing secure virtual SMS verification numbers for Telegram, WhatsApp, Canva Premium, and digital tools.
          </p>
        </div>

        <div class="footer-links">
          <div>
            <div class="footer-column-title">Popular Regions</div>
            <ul class="footer-list">
              <li>India 🇮🇳</li>
              <li>United States 🇺🇸</li>
              <li>United Arab Emirates 🇦🇪</li>
              <li>Canada 🇨🇦</li>
              <li>Russia 🇷🇺</li>
            </ul>
          </div>
          <div>
            <div class="footer-column-title">Top Categories</div>
            <ul class="footer-list">
              <li>Telegram Virtual Numbers</li>
              <li>WhatsApp Verification</li>
              <li>Canva Premium Lifetime</li>
              <li>AI Assistants &amp; Utilities</li>
            </ul>
          </div>
          <div>
            <div class="footer-column-title">Support &amp; Social</div>
            <ul class="footer-list">
              <li>Telegram: <a href="https://t.me/nu9rl" target="_blank" style="color: #38bdf8;">@nu9rl</a></li>
              <li>WhatsApp: <a href="https://wa.me/919303773240" target="_blank" style="color: #4ade80;">+91 9303773240</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <span>© <?= date('Y') ?> Mango Number. All rights reserved.</span>
        <span>Premium SMS Verification &amp; Digital Marketplace</span>
      </div>
    </div>
  </footer>

</div>
</body>
</html>

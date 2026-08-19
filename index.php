<?php
/**
 * Mango Number - Premium SaaS Landing Page
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Unlock 100+ Premium Virtual Numbers &amp; AI Tools | Mango Number">
    <meta property="og:description" content="Instant virtual SMS numbers for Telegram & WhatsApp, Canva Premium, and digital tools with Mango Number.">
    <meta property="og:image" content="assets/img/logo.png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Mango Number">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    
    <!-- Fonts -->
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
      width: 100%;
      max-width: 100%;
      overflow-x: hidden !important;
      scroll-behavior: smooth;
      touch-action: manipulation;
    }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      color: var(--text-main);
      background: var(--bg);
      position: relative;
    }

    /* Ambient Animated Mesh Canvas Background */
    .bg-mesh-canvas {
      position: fixed;
      inset: 0;
      z-index: -1;
      background:
        radial-gradient(circle at 10% 10%, rgba(255, 94, 54, 0.22) 0, transparent 45%),
        radial-gradient(circle at 90% 10%, rgba(255, 138, 31, 0.22) 0, transparent 45%),
        radial-gradient(circle at 50% 90%, rgba(124, 58, 237, 0.16) 0, transparent 55%),
        var(--bg);
      background-size: 140% 140%;
      animation: meshPulse 18s ease-in-out infinite alternate;
    }
    @keyframes meshPulse {
      0% { background-position: 0% 0%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 100%; }
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

    /* Live Activity Ticker Bar */
    .ticker-bar {
      background: rgba(17, 24, 39, 0.85);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      font-size: .8rem;
      padding: 8px 0;
      overflow: hidden;
      white-space: nowrap;
      position: relative;
      z-index: 60;
      backdrop-filter: blur(10px);
    }
    .ticker-content {
      display: inline-flex;
      gap: 32px;
      animation: tickerScroll 35s linear infinite;
    }
    .ticker-item {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #d1d5db;
    }
    .ticker-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 8px #22c55e;
      animation: pulseDot 2s infinite;
    }
    @keyframes tickerScroll {
      0% { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    /* Header */
    .site-header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: rgba(3, 7, 18, 0.88);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
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
      width: 44px;
      height: 44px;
      background: transparent;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: none;
      transition: transform 0.3s ease;
    }
    .brand:hover .brand-logo-icon {
      transform: scale(1.08) rotate(-3deg);
    }
    .brand-logo-icon img {
      width: 44px;
      height: 44px;
      object-fit: contain;
      border-radius: 50%;
      filter: drop-shadow(0 0 10px rgba(255, 94, 54, 0.4));
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
      position: relative;
    }
    .nav a::after {
      content: '';
      position: absolute;
      bottom: -4px;
      left: 0;
      right: 0;
      height: 2px;
      background: #ff5e36;
      border-radius: 2px;
      opacity: 0;
      transform: scaleX(0);
      transition: all 0.25s ease;
    }
    .nav a:hover {
      color: #ffffff;
    }
    .nav a:hover::after {
      opacity: 1;
      transform: scaleX(1);
    }
    .header-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Buttons with Sheen & Glow Animations */
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
      transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
    }
    .btn-primary {
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      color: #ffffff;
      box-shadow: 0 8px 24px rgba(255, 94, 54, 0.4);
    }
    .btn-primary::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        60deg,
        transparent 30%,
        rgba(255, 255, 255, 0.3) 50%,
        transparent 70%
      );
      transform: rotate(30deg) translateY(-100%);
      animation: sheenSweep 4s infinite;
    }
    @keyframes sheenSweep {
      0% { transform: rotate(30deg) translateY(-100%); }
      22% { transform: rotate(30deg) translateY(100%); }
      100% { transform: rotate(30deg) translateY(100%); }
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 36px rgba(255, 94, 54, 0.6);
      color: #ffffff;
    }
    .btn-primary:active {
      transform: scale(0.98);
    }
    .btn-ghost {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #f3f4f6;
    }
    .btn-ghost:hover {
      background: rgba(255, 255, 255, 0.12);
      border-color: rgba(255, 94, 54, 0.4);
      color: #ffffff;
      transform: translateY(-1px);
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
      padding: 56px 0 64px;
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
      box-shadow: 0 0 20px rgba(255, 94, 54, 0.15);
    }
    .hero-kicker-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: #22c55e;
      box-shadow: 0 0 10px #22c55e;
      animation: pulseDot 2s infinite;
    }
    @keyframes pulseDot {
      0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
      70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
      100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .hero-title {
      font-size: clamp(2.25rem, 5vw, 2.95rem);
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
      transition: all 0.25s ease;
    }
    .hero-pill:hover {
      border-color: rgba(255, 94, 54, 0.4);
      background: rgba(17, 24, 39, 0.95);
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(255, 94, 54, 0.15);
    }

    /* 3D Visual Depth & Micro-Physics */
    .hero-card, .feature-card, .step-card, .testimonial-card {
      transform-style: preserve-3d;
    }
    .feature-card:hover .feature-icon, .feature-card:hover .card-badge {
      transform: translateZ(28px) scale(1.08);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .hero-card:hover .hero-card-title, .hero-card:hover .btn-primary {
      transform: translateZ(20px);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .step-card:hover .step-badge {
      transform: translateZ(24px) scale(1.1);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .hero-3d-badge {
      position: absolute;
      top: -18px;
      right: 18px;
      background: rgba(17, 24, 39, 0.92);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 138, 31, 0.45);
      border-radius: 14px;
      padding: 8px 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.6), 0 0 20px rgba(255, 94, 54, 0.25);
      z-index: 20;
      animation: float3DBadge 5s ease-in-out infinite alternate;
      pointer-events: none;
    }
    .badge-icon {
      font-size: 1.2rem;
    }
    .badge-title {
      font-size: .78rem;
      font-weight: 700;
      color: #ffffff;
      line-height: 1.2;
    }
    .badge-sub {
      font-size: .68rem;
      color: #9ca3af;
    }
    @keyframes float3DBadge {
      0% { transform: translateY(0px) rotate(0deg); }
      100% { transform: translateY(-10px) rotate(2deg); }
    }

    /* Hero Card (Registration Form) */
    .hero-card {
      background: rgba(17, 24, 39, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-radius: 24px;
      border: 1.5px solid rgba(255, 138, 31, 0.35);
      box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6), 0 0 30px rgba(255, 94, 54, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.15);
      padding: 30px 26px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      position: relative;
    }
    .hero-card:hover {
      border-color: rgba(255, 94, 54, 0.6);
      box-shadow: 0 30px 90px rgba(255, 94, 54, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }
    .hero-card-title {
      font-size: 1.3rem;
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
      margin: 44px auto 0;
      max-width: 1060px;
      background: rgba(17, 24, 39, 0.75);
      backdrop-filter: blur(16px);
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 18px 36px;
      display: flex;
      flex-wrap: wrap;
      gap: 24px;
      justify-content: space-between;
      align-items: center;
      transition: border-color 0.3s, box-shadow 0.3s;
    }
    .metrics-strip:hover {
      border-color: rgba(255, 94, 54, 0.4);
      box-shadow: 0 10px 30px rgba(255, 94, 54, 0.15);
    }
    .metric {
      display: flex;
      flex-direction: column;
    }
    .metric-value {
      font-weight: 800;
      font-size: 1.3rem;
      font-family: 'Outfit', sans-serif;
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .metric-label {
      color: var(--text-muted);
      font-size: .82rem;
    }

    /* Filter Tabs for Catalog */
    .catalog-tabs {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-bottom: 28px;
      flex-wrap: wrap;
    }
    .tab-btn {
      padding: 8px 18px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: var(--text-muted);
      font-size: .86rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .tab-btn.active, .tab-btn:hover {
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      color: #ffffff;
      border-color: transparent;
      box-shadow: 0 4px 16px rgba(255, 94, 54, 0.35);
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
      font-size: clamp(1.65rem, 4vw, 2.25rem);
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

    /* Scroll Reveal Animation Classes */
    .reveal-on-scroll {
      opacity: 0;
      transform: translateY(24px);
      transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .reveal-on-scroll.revealed {
      opacity: 1;
      transform: translateY(0);
    }

    /* Features Grid Cards with Cursor Spotlight */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 24px;
    }
    .feature-card {
      background: rgba(17, 24, 39, 0.7);
      backdrop-filter: blur(12px);
      border-radius: 22px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      padding: 26px 24px;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(
        450px circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
        rgba(255, 94, 54, 0.14),
        transparent 40%
      );
      opacity: 0;
      transition: opacity 0.3s ease;
      pointer-events: none;
    }
    .feature-card:hover::before {
      opacity: 1;
    }
    .feature-card:hover {
      transform: translateY(-6px) scale(1.01);
      border-color: rgba(255, 94, 54, 0.45);
      box-shadow: 0 20px 45px rgba(255, 94, 54, 0.14);
    }
    .card-badge {
      position: absolute;
      top: 18px;
      right: 18px;
      font-size: .72rem;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(255, 94, 54, 0.15);
      border: 1px solid rgba(255, 94, 54, 0.35);
      color: #ff8a1f;
    }
    .feature-icon {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(255, 94, 54, 0.15), rgba(255, 138, 31, 0.15));
      color: #ff8a1f;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
      font-size: 1.4rem;
      transition: transform 0.3s ease;
    }
    .feature-card:hover .feature-icon {
      transform: translateY(-3px) scale(1.08);
    }
    .feature-title {
      font-size: 1.12rem;
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
      padding: 26px 24px;
      transition: all 0.3s ease;
    }
    .step-card:hover {
      border-color: rgba(255, 94, 54, 0.7);
      transform: translateY(-4px);
      background: rgba(17, 24, 39, 0.85);
    }
    .step-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      font-size: 1rem;
      font-weight: 800;
      border-radius: 999px;
      background: linear-gradient(135deg, #ff5e36, #ff8a1f);
      color: #ffffff;
      margin-bottom: 14px;
      box-shadow: 0 4px 14px rgba(255, 94, 54, 0.35);
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

    /* FAQ Accordion Section */
    .faq-grid {
      max-width: 800px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .faq-item {
      background: rgba(17, 24, 39, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 16px;
      overflow: hidden;
      transition: border-color 0.25s ease;
    }
    .faq-item:hover, .faq-item.active {
      border-color: rgba(255, 94, 54, 0.4);
    }
    .faq-header {
      padding: 18px 22px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
      font-family: 'Outfit', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      color: #ffffff;
      user-select: none;
    }
    .faq-chevron {
      font-size: 1.2rem;
      color: #ff8a1f;
      transition: transform 0.3s ease;
    }
    .faq-item.active .faq-chevron {
      transform: rotate(180deg);
    }
    .faq-body {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease, padding 0.3s ease;
      padding: 0 22px;
      font-size: .9rem;
      color: var(--text-muted);
      line-height: 1.6;
    }
    .faq-item.active .faq-body {
      max-height: 200px;
      padding: 0 22px 18px;
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
      transition: transform 0.3s ease, border-color 0.3s ease;
    }
    .testimonial-card:hover {
      transform: translateY(-4px);
      border-color: rgba(255, 94, 54, 0.35);
    }
    .rating-stars {
      color: #fbbf24;
      font-size: .95rem;
      margin-bottom: 12px;
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

    /* Floating Support Quick Pill Widget */
    .floating-support-pill {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 99;
      background: linear-gradient(135deg, #0088cc, #00a8ff);
      color: #ffffff;
      padding: 10px 18px;
      border-radius: 999px;
      font-size: .85rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 10px 30px rgba(0, 136, 204, 0.4);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .floating-support-pill:hover {
      transform: translateY(-3px) scale(1.03);
      box-shadow: 0 14px 40px rgba(0, 136, 204, 0.6);
      color: #ffffff;
    }
    .floating-support-pill i {
      font-size: 1.15rem;
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

    /* Mobile Drawer */
    .mobile-menu-toggle {
      display: none;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #ffffff;
      font-size: 1.4rem;
      padding: 8px;
      border-radius: 10px;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      min-width: 44px;
      min-height: 44px;
    }
    .mobile-drawer {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: rgba(3, 7, 18, 0.98);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding: 16px 20px 24px;
      flex-direction: column;
      gap: 14px;
      z-index: 100;
      box-shadow: 0 20px 40px rgba(0,0,0,0.8);
    }
    .mobile-drawer.active {
      display: flex;
    }
    .mobile-drawer a {
      color: #e5e7eb;
      font-size: 1rem;
      font-weight: 600;
      padding: 10px 14px;
      border-radius: 10px;
      background: rgba(255,255,255,0.03);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    /* Responsive & Mobile Enhancements */
    @media (max-width: 992px) {
      .hero-inner {
        grid-template-columns: 1fr;
        gap: 28px;
      }
      .features-grid, .steps-grid, .testimonial-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .metrics-strip {
        border-radius: 24px;
        padding: 20px 24px;
      }
    }
    @media (max-width: 991px) {
      .hero-inner {
        grid-template-columns: 1fr !important;
        gap: 28px !important;
      }
      .features-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (max-width: 768px) {
      .nav {
        display: none;
      }
      .mobile-menu-toggle {
        display: flex;
      }
      .brand-tagline {
        display: none;
      }
      .hero {
        padding: 20px 0 32px;
      }
      .btn {
        min-height: 40px;
        white-space: nowrap;
      }
      .btn-sm {
        padding: 6px 12px;
        font-size: .78rem;
      }
      .field input {
        min-height: 44px;
      }
    }
    @media (max-width: 640px) {
      .container {
        padding: 0 14px;
      }
      .hero-title {
        font-size: clamp(1.4rem, 5.2vw, 1.85rem);
        margin-bottom: 10px;
        line-height: 1.22;
      }
      .hero-sub {
        font-size: 0.85rem;
        line-height: 1.5;
        margin-bottom: 16px;
      }
      .hero-kicker {
        font-size: .74rem;
        padding: 4px 10px;
        margin-bottom: 12px;
      }
      .hero-card {
        padding: 20px 16px;
        border-radius: 18px;
      }
      .features-grid, .steps-grid, .testimonial-grid {
        grid-template-columns: 1fr;
        gap: 14px;
      }
      .metrics-strip {
        border-radius: 18px;
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 18px;
      }
      .hero-bullets {
        gap: 6px;
        margin-bottom: 18px;
      }
      .hero-pill {
        font-size: .75rem;
        padding: 5px 10px;
      }
      .floating-support-pill {
        bottom: 12px;
        right: 12px;
        padding: 7px 12px;
        font-size: .76rem;
        box-shadow: 0 6px 20px rgba(0, 136, 204, 0.5);
      }
      .floating-support-pill svg {
        width: 16px;
        height: 16px;
      }
      .header-actions .btn-ghost {
        padding: 6px 10px;
        font-size: .75rem;
      }
      .header-actions .btn-primary {
        padding: 6px 12px;
        font-size: .75rem;
      }
      .footer-top {
        flex-direction: column;
        gap: 28px;
      }
      .footer-links {
        flex-direction: column;
        gap: 24px;
      }
    }
    </style>
</head>
<body>

<!-- Ambient Animated Background Canvas Mesh -->
<div class="bg-mesh-canvas"></div>

<div class="page">

  <!-- Live Activity Ticker Bar -->
  <div class="ticker-bar">
    <div class="ticker-content">
      <div class="ticker-item"><span class="ticker-dot"></span> 🔥 Hum saare premium subscriptions aur apps ko saste me unlocked &amp; crack karke dete hain!</div>
      <div class="ticker-item"><span class="ticker-dot"></span> 🇺🇸 USA Telegram OTP — Delivered 12s ago</div>
      <div class="ticker-item"><span class="ticker-dot"></span> ⚡ Har premium app aur tool up to 70% sasta milega Mango Number pe!</div>
      <div class="ticker-item"><span class="ticker-dot"></span> 🇨🇦 Canada WhatsApp Number — Activated 34s ago</div>
      <div class="ticker-item"><span class="ticker-dot"></span> 🎨 Canva Pro Lifetime — Approved 1m ago</div>
      <div class="ticker-item"><span class="ticker-dot"></span> 🇮🇳 India WhatsApp OTP — Delivered 2m ago</div>
      <!-- Loop set -->
      <div class="ticker-item"><span class="ticker-dot"></span> 🔥 Hum saare premium subscriptions aur apps ko saste me unlocked &amp; crack karke dete hain!</div>
      <div class="ticker-item"><span class="ticker-dot"></span> 🇺🇸 USA Telegram OTP — Delivered 12s ago</div>
      <div class="ticker-item"><span class="ticker-dot"></span> ⚡ Har premium app aur tool up to 70% sasta milega Mango Number pe!</div>
      <div class="ticker-item"><span class="ticker-dot"></span> 🎨 Canva Pro Lifetime — Approved 1m ago</div>
    </div>
  </div>

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
            <div class="brand-tagline">Instant Virtual SMS &amp; Digital Marketplace</div>
          </div>
        </a>
        <nav class="nav">
          <a href="#services">Services</a>
          <a href="#why-mango">Why Mango?</a>
          <a href="#how-it-works">How it works</a>
          <a href="#faq">FAQ</a>
          <a href="#testimonials">Reviews</a>
        </nav>
        <div class="header-actions">
          <?php if (is_logged_in()): ?>
            <?php if (is_admin()): ?>
              <a href="admin.php" class="btn btn-primary btn-sm">
                <i class="bx bx-cog"></i> Admin Panel
              </a>
            <?php else: ?>
              <a href="dashboard.php" class="btn btn-primary btn-sm">
                <i class="bx bx-grid-alt"></i> Dashboard
              </a>
            <?php endif; ?>
          <?php else: ?>
            <a href="login.php" class="btn btn-ghost btn-sm">Sign in</a>
            <a href="register.php" class="btn btn-primary btn-sm d-none-xs">Create Account</a>
          <?php endif; ?>
          <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation Menu">
            <i class="bx bx-menu"></i>
          </button>
        </div>
      </div>
    </div>
    <!-- Mobile Navigation Drawer -->
    <div class="mobile-drawer" id="mobileDrawer">
      <a href="#services" onclick="closeMobileDrawer()"><i class="bx bx-store-alt"></i> Catalog Services <i class="bx bx-chevron-right"></i></a>
      <a href="#why-mango" onclick="closeMobileDrawer()"><i class="bx bx-shield-quarter"></i> Why Mango Number? <i class="bx bx-chevron-right"></i></a>
      <a href="#how-it-works" onclick="closeMobileDrawer()"><i class="bx bx-cog"></i> How It Works <i class="bx bx-chevron-right"></i></a>
      <a href="#faq" onclick="closeMobileDrawer()"><i class="bx bx-help-circle"></i> FAQ <i class="bx bx-chevron-right"></i></a>
      <a href="#testimonials" onclick="closeMobileDrawer()"><i class="bx bx-star"></i> Reviews <i class="bx bx-chevron-right"></i></a>
      <?php if (is_logged_in()): ?>
        <?php if (is_admin()): ?>
          <a href="admin.php" style="background: linear-gradient(135deg, #ff5e36, #ff8a1f); color: #fff;"><i class="bx bx-cog"></i> Admin Panel <i class="bx bx-right-arrow-alt"></i></a>
        <?php else: ?>
          <a href="dashboard.php" style="background: linear-gradient(135deg, #ff5e36, #ff8a1f); color: #fff;"><i class="bx bx-grid-alt"></i> Go to Dashboard <i class="bx bx-right-arrow-alt"></i></a>
        <?php endif; ?>
      <?php else: ?>
        <a href="login.php"><i class="bx bx-log-in"></i> Sign In <i class="bx bx-chevron-right"></i></a>
        <a href="register.php" style="background: linear-gradient(135deg, #ff5e36, #ff8a1f); color: #fff;"><i class="bx bx-user-plus"></i> Create Account <i class="bx bx-right-arrow-alt"></i></a>
      <?php endif; ?>
    </div>
  </header>

  <script>
    const toggleBtn = document.getElementById('mobileMenuToggle');
    const drawer = document.getElementById('mobileDrawer');
    if (toggleBtn && drawer) {
      toggleBtn.addEventListener('click', () => {
        drawer.classList.toggle('active');
        const icon = toggleBtn.querySelector('i');
        if (drawer.classList.contains('active')) {
          icon.className = 'bx bx-x';
        } else {
          icon.className = 'bx bx-menu';
        }
      });
    }
    function closeMobileDrawer() {
      if (drawer) {
        drawer.classList.remove('active');
        const icon = toggleBtn.querySelector('i');
        if (icon) icon.className = 'bx bx-menu';
      }
    }
  </script>

  <!-- Hero + Quick Account Signup -->
  <main>
    <section class="hero">
      <div class="container">
        <div class="hero-inner">
          <div class="hero-content reveal-on-scroll">
            <div class="hero-kicker">
              <span class="hero-kicker-dot"></span>
              <span>🏷️ Up to 70% Cheaper Than Original Retail Prices</span>
            </div>
            <h1 class="hero-title">
              Unlock <span class="highlight">100+ Premium</span> Virtual Numbers &amp; Services at Dirt-Cheap Rates.
            </h1>
            <p class="hero-sub">
              Why pay full price? Mango Number gives you instant access to dedicated Telegram &amp; WhatsApp virtual numbers, Canva Pro, productivity tools and AI subscriptions at heavily discounted rates — far cheaper than original retail prices.
            </p>
            <div class="hero-bullets">
              <span class="hero-pill">
                <span>🏷️</span> Up to 70% OFF Original Prices
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
          <div style="position: relative;">
            <div class="hero-3d-badge">
              <span class="badge-icon">⚡</span>
              <div>
                <div class="badge-title">Instant Delivery</div>
                <div class="badge-sub">OTP released &lt;45s</div>
              </div>
            </div>
            <div class="hero-card reveal-on-scroll" id="register">
              <div class="hero-card-title">Get Started &amp; Save Big Today</div>
              <div class="hero-card-sub">Takes less than 60 seconds. Claim virtual numbers &amp; premium digital services at unbeatable low prices.</div>

            <?php if (is_logged_in()): ?>
              <div style="padding: 20px 0; text-align: center;">
                <div style="font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; color: #ffffff;">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</div>
                <p style="font-size: .88rem; color: #9ca3af; margin-bottom: 20px;">You are currently logged in as <?= is_admin() ? 'an Administrator' : 'a Client' ?>.</p>
                <?php if (is_admin()): ?>
                  <a href="admin.php" class="btn btn-primary btn-full py-3" style="font-size: 1rem;">
                    Go to Admin Panel <i class="bx bx-right-arrow-alt"></i>
                  </a>
                <?php else: ?>
                  <a href="dashboard.php" class="btn btn-primary btn-full py-3" style="font-size: 1rem;">
                    Go to Dashboard <i class="bx bx-right-arrow-alt"></i>
                  </a>
                <?php endif; ?>
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
      </div>

      <!-- Metrics Strip -->
        <div class="metrics-strip reveal-on-scroll" id="metricsStrip">
          <div class="metric">
            <span class="metric-value" data-counter="70" data-suffix="% OFF">Up to 70% OFF</span>
            <span class="metric-label">cheaper than original market prices</span>
          </div>
          <div class="metric">
            <span class="metric-value" data-counter="280" data-suffix="+">280+</span>
            <span class="metric-label">active users saving on virtual numbers</span>
          </div>
          <div class="metric">
            <span class="metric-value" data-counter="1200" data-suffix="+ Delivered">1,200+ Delivered</span>
            <span class="metric-label">instant OTP verifications worldwide</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Services / Offers Catalog Section -->
    <section class="section" id="services">
      <div class="container">
        <div class="section-header reveal-on-scroll">
          <div class="section-kicker">Cheaper Than Original Price</div>
          <h2 class="section-title">Popular Virtual Numbers &amp; Services</h2>
          <p class="section-text">Explore active virtual country numbers and premium digital subscriptions available at dirt-cheap prices.</p>
        </div>

        <!-- Interactive Category Filter Tabs -->
        <div class="catalog-tabs reveal-on-scroll">
          <button class="tab-btn active" onclick="filterCatalog('all', this)">All Offers</button>
          <button class="tab-btn" onclick="filterCatalog('telegram', this)">Telegram Numbers</button>
          <button class="tab-btn" onclick="filterCatalog('whatsapp', this)">WhatsApp Verification</button>
          <button class="tab-btn" onclick="filterCatalog('digital', this)">Canva &amp; AI Tools</button>
        </div>

        <div class="features-grid" id="catalogGrid">
          <!-- Telegram OTP Numbers -->
          <div class="feature-card reveal-on-scroll" data-category="telegram">
            <span class="card-badge">⚡ INSTANT</span>
            <div class="feature-icon" style="background: linear-gradient(135deg, rgba(0, 136, 204, 0.2), rgba(0, 168, 255, 0.2)); color: #0088cc;">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 0 0-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
            </div>
            <h3 class="feature-title">Telegram OTP Numbers</h3>
            <p class="feature-text">Fresh, dedicated virtual numbers from India, USA, Canada, UK, and Russia for Telegram account &amp; channel creation at wholesale cheap rates.</p>
            <div style="margin-top: 18px;">
              <a href="dashboard.php?section=buy" class="btn btn-ghost btn-sm w-100" style="border-color: rgba(0, 136, 204, 0.5); color: #38bdf8;">
                Buy Telegram Numbers <i class="bx bx-chevron-right"></i>
              </a>
            </div>
          </div>

          <!-- WhatsApp Verification -->
          <div class="feature-card reveal-on-scroll" data-category="whatsapp">
            <span class="card-badge">🔥 70% OFF</span>
            <div class="feature-icon" style="background: linear-gradient(135deg, rgba(37, 211, 102, 0.2), rgba(18, 140, 126, 0.2)); color: #25d366;">
              <i class="bxl-whatsapp"></i>
            </div>
            <h3 class="feature-title">WhatsApp Verification</h3>
            <p class="feature-text">Establish secondary WhatsApp accounts without exposing your personal phone number. 100% private &amp; up to 70% cheaper.</p>
            <div style="margin-top: 18px;">
              <a href="dashboard.php?section=buy" class="btn btn-ghost btn-sm w-100" style="border-color: rgba(37, 211, 102, 0.5); color: #4ade80;">
                Buy WhatsApp Numbers <i class="bx bx-chevron-right"></i>
              </a>
            </div>
          </div>

          <!-- Canva Premium Lifetime -->
          <div class="feature-card reveal-on-scroll" data-category="digital">
            <span class="card-badge">⭐ POPULAR</span>
            <div class="feature-icon" style="background: linear-gradient(135deg, rgba(125, 42, 232, 0.2), rgba(0, 196, 204, 0.2)); color: #a855f7;">
              <i class="bx bx-paint"></i>
            </div>
            <h3 class="feature-title">Canva Premium Lifetime</h3>
            <p class="feature-text">Unlock full access to Canva Pro templates, brand kits, and AI tools for just ₹150 instead of the original ₹3,999/yr price.</p>
            <div style="margin-top: 18px;">
              <a href="payment.php?id=1" class="btn btn-ghost btn-sm w-100" style="border-color: rgba(168, 85, 247, 0.5); color: #c084fc;">
                Get Canva Pro <i class="bx bx-chevron-right"></i>
              </a>
            </div>
          </div>

          <!-- Instant Manual Audit Desk -->
          <div class="feature-card reveal-on-scroll" data-category="all">
            <div class="feature-icon">⚡</div>
            <h3 class="feature-title">Fast Manual Audit Desk</h3>
            <p class="feature-text">Our active verification operators audit Paytm/PhonePe UTR receipts and USDT transfers within minutes.</p>
          </div>

          <!-- AI Tools & Subscriptions -->
          <div class="feature-card reveal-on-scroll" data-category="digital">
            <div class="feature-icon">🤖</div>
            <h3 class="feature-title">AI Assistants &amp; Utilities</h3>
            <p class="feature-text">Discover productivity suites, chatbots, and AI tools to write, code, and automate at heavily discounted rates.</p>
          </div>

          <!-- Total Anonymity & Security -->
          <div class="feature-card reveal-on-scroll" data-category="all">
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
        <div class="section-header reveal-on-scroll">
          <div class="section-kicker">Why users choose Mango Number</div>
          <h2 class="section-title">Massive Savings. Unbeatable Cheap Prices.</h2>
          <p class="section-text">
            Instead of paying expensive full subscription costs or risking your private personal phone number, Mango Number provides authentic virtual numbers and digital perks at up to 70% cheaper than original costs.
          </p>
        </div>

        <div class="features-grid">
          <article class="feature-card reveal-on-scroll">
            <div class="feature-icon">📱</div>
            <h3 class="feature-title">Telegram &amp; WhatsApp OTP</h3>
            <p class="feature-text">Access fresh country numbers for audio, messaging, and account verifications instantly.</p>
          </article>
          <article class="feature-card reveal-on-scroll">
            <div class="feature-icon">💼</div>
            <h3 class="feature-title">Productivity &amp; Creative</h3>
            <p class="feature-text">Upgrade your creative stack with Canva Pro, premium note-taking, and project tools.</p>
          </article>
          <article class="feature-card reveal-on-scroll">
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
        <div class="section-header reveal-on-scroll">
          <div class="section-kicker">How it works</div>
          <h2 class="section-title">3 Simple Steps to Start</h2>
          <p class="section-text">Getting started with Mango Number is simple and takes less than 2 minutes.</p>
        </div>

        <div class="steps-grid">
          <article class="step-card reveal-on-scroll">
            <div class="step-badge">1</div>
            <h3 class="step-title">Create Account</h3>
            <p class="step-text">Sign up with your email using instant OTP verification. No credit card or KYC required.</p>
          </article>
          <article class="step-card reveal-on-scroll">
            <div class="step-badge">2</div>
            <h3 class="step-title">Select Service &amp; Pay</h3>
            <p class="step-text">Choose your virtual number or digital offer. Pay via Paytm UPI or USDT TRC-20 and submit the UTR.</p>
          </article>
          <article class="step-card reveal-on-scroll">
            <div class="step-badge">3</div>
            <h3 class="step-title">Receive OTP &amp; Enjoy</h3>
            <p class="step-text">Our live verification operators approve your order and release your OTP code straight to your dashboard.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Interactive FAQ Section -->
    <section class="section" id="faq">
      <div class="container">
        <div class="section-header reveal-on-scroll">
          <div class="section-kicker">Got Questions?</div>
          <h2 class="section-title">Frequently Asked Questions</h2>
          <p class="section-text">Everything you need to know about our virtual SMS numbers and digital services.</p>
        </div>

        <div class="faq-grid reveal-on-scroll">
          <div class="faq-item active" onclick="toggleFaq(this)">
            <div class="faq-header">
              <span>Are these virtual numbers safe for Telegram &amp; WhatsApp?</span>
              <i class="bx bx-chevron-down faq-chevron"></i>
            </div>
            <div class="faq-body">
              Yes, absolutely! Every virtual number provided by Mango Number is dedicated, fresh, isolated, and tested for high OTP delivery success.
            </div>
          </div>
          <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-header">
              <span>Why are your prices up to 70% cheaper than original costs?</span>
              <i class="bx bx-chevron-down faq-chevron"></i>
            </div>
            <div class="faq-body">
              We work directly with volume suppliers to aggregate bulk wholesale perks and rates, passing the massive savings straight to our users.
            </div>
          </div>
          <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-header">
              <span>How fast is the UTR verification and OTP delivery?</span>
              <i class="bx bx-chevron-down faq-chevron"></i>
            </div>
            <div class="faq-body">
              Our manual verification operators audit Paytm/PhonePe UTR receipts within minutes. Once approved, your OTP is updated live in your dashboard.
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonials -->
    <section class="section" id="testimonials">
      <div class="container">
        <div class="section-header reveal-on-scroll">
          <div class="section-kicker">Client Reviews</div>
          <h2 class="section-title">Loved by Thousands Worldwide</h2>
        </div>

        <div class="testimonial-grid">
          <article class="testimonial-card reveal-on-scroll">
            <div class="rating-stars">★★★★★</div>
            <p class="testimonial-quote">
              “Claimed dedicated Telegram virtual numbers and Canva Pro in minutes. Approval was fast and support was very helpful!”
            </p>
            <div class="testimonial-name">Arjun Mehta</div>
            <div class="testimonial-meta">India • Freelancer</div>
          </article>
          <article class="testimonial-card reveal-on-scroll">
            <div class="rating-stars">★★★★★</div>
            <p class="testimonial-quote">
              “As a student creating social projects, keeping my personal phone number private was critical. Mango Number solved it effortlessly.”
            </p>
            <div class="testimonial-name">Sara Khan</div>
            <div class="testimonial-meta">UAE • Student</div>
          </article>
          <article class="testimonial-card reveal-on-scroll">
            <div class="rating-stars">★★★★★</div>
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

  <!-- Floating Live Support Widget -->
  <a href="https://t.me/nu9rl" target="_blank" class="floating-support-pill" title="Need help? Chat on Telegram">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 0 0-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
    <span>Live Support</span>
  </a>

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

<!-- Interactive Scripts -->
<script>
// Mouse Spotlight Tracking Effect on Cards
document.addEventListener('mousemove', e => {
  document.querySelectorAll('.feature-card').forEach(card => {
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
  });
});

// 3D Parallax Tilt Physics Interaction on Cards
document.querySelectorAll('.hero-card, .feature-card, .testimonial-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left - rect.width / 2;
    const y = e.clientY - rect.top - rect.height / 2;
    const rotateX = (-y / rect.height) * 8;
    const rotateY = (x / rect.width) * 8;
    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
    card.style.transition = 'transform 0.1s ease-out';
  });

  card.addEventListener('mouseleave', () => {
    card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px)';
    card.style.transition = 'transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)';
  });
});

// Scroll Reveal Observer
const revealElements = document.querySelectorAll('.reveal-on-scroll');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('revealed');
    }
  });
}, { threshold: 0.15 });

revealElements.forEach(el => revealObserver.observe(el));

// Catalog Filter Functionality
function filterCatalog(category, btn) {
  const tabs = document.querySelectorAll('.tab-btn');
  tabs.forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');

  const cards = document.querySelectorAll('#catalogGrid .feature-card');
  cards.forEach(card => {
    const cat = card.getAttribute('data-category');
    if (category === 'all' || cat === category || cat === 'all') {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
}

// FAQ Accordion Functionality
function toggleFaq(element) {
  const isAlreadyActive = element.classList.contains('active');
  const allFaqs = document.querySelectorAll('.faq-item');
  allFaqs.forEach(item => item.classList.remove('active'));
  if (!isAlreadyActive) {
    element.classList.add('active');
  }
}

// Counter Animation on Scroll
let animatedCounters = false;
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting && !animatedCounters) {
      animatedCounters = true;
      animateNumbers();
    }
  });
}, { threshold: 0.5 });

const metricsStrip = document.getElementById('metricsStrip');
if (metricsStrip) observer.observe(metricsStrip);

function animateNumbers() {
  const counters = document.querySelectorAll('[data-counter]');
  counters.forEach(el => {
    const target = parseInt(el.getAttribute('data-counter'));
    const suffix = el.getAttribute('data-suffix') || '';
    let current = 0;
    const step = Math.max(1, Math.floor(target / 40));
    const timer = setInterval(() => {
      current += step;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      if (target === 70) {
        el.innerText = 'Up to ' + current + suffix;
      } else {
        el.innerText = current.toLocaleString() + suffix;
      }
    }, 30);
  });
}
</script>
</body>
</html>

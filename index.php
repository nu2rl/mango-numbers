<?php
/**
 * Mango Number - Premium SaaS-style Landing Page
 */

require_once __DIR__ . '/config.php';

$db = get_db_connection();
$telegram_items = [];
$whatsapp_items = [];
$db_error = false;

if ($db) {
    try {
        // Fetch Telegram items (from products table first, fallback/merge catalog)
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

// Flag utility mapper helper
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mango Number – Premium Virtual SMS Verification Numbers</title>
  <link rel="icon" type="image/png" href="assets/img/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #fffdf9;
      overflow-x: hidden;
      max-width: 100%;
      width: 100%;
      color: #1A1208;
    }
    ::selection { color: #1a1208; background: rgba(255,140,0,0.22); }

    /* ─── SHARED NAVBAR ─── */
    .gm-nav-wrap {
      position: fixed; z-index: 100; top: 0; left: 0; right: 0;
      padding: 22px 28px 0;
      display: flex; justify-content: center;
      pointer-events: none;
    }
    .gm-nav-pill {
      display: flex; align-items: center; justify-content: space-between; gap: 24px;
      background: rgba(255,255,255,0.65);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.4);
      border-radius: 999px;
      padding: 8px 10px 8px 20px;
      width: 100%; max-width: 1068px;
      pointer-events: auto;
      box-shadow: 0 8px 25px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.04), inset 0 1px 0 rgba(255,255,255,0.9);
      transition: background 0.3s, box-shadow 0.3s;
      position: relative;
    }
    .gm-nav-pill::before {
      content: ''; position: absolute; top: 0; left: 10%; right: 10%; height: 1.5px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,1), transparent);
      border-radius: 999px;
    }
    .gm-nav-pill.scrolled {
      background: rgba(255,255,255,0.92);
      box-shadow: 0 12px 30px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.9);
    }
    .gm-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; cursor: pointer; }
    .gm-logo-icon {
      width: 34px; height: 34px;
      background: linear-gradient(135deg, #FF8C00, #FFA726);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 14px rgba(255,140,0,0.40), inset 0 1px 0 rgba(255,255,255,0.35);
      flex-shrink: 0;
    }
    .gm-logo span { font-size: 16px; font-weight: 800; color: #1A1208; letter-spacing: -0.45px; }
    .gm-logo span b { color: #D97706; font-weight: 800; }
    
    .gm-nav-links { display: flex; align-items: center; gap: 2px; list-style: none; flex: 1; justify-content: center; }
    .gm-nav-links a {
      font-size: 14.5px; font-weight: 500; color: rgba(26,26,46,0.68);
      text-decoration: none; padding: 7px 14px; border-radius: 6px;
      letter-spacing: -0.12px; cursor: pointer;
      transition: transform 0.25s, background 0.25s, color 0.25s; display: inline-block;
    }
    .gm-nav-links a:hover { color: #1A1208; transform: translateY(-1.5px); background: rgba(0,0,0,0.03); }
    .gm-nav-links a.active { color: #D97706; font-weight: 600; }
    
    .gm-btn-nav-cta {
      display: inline-flex; align-items: center; gap: 7px;
      font-size: 14px; font-weight: 600; color: #fff; text-decoration: none;
      padding: 10px 22px; border-radius: 999px;
      background: linear-gradient(135deg, #2E7D32 0%, #43A047 100%);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12); cursor: pointer; border: none; font-family: inherit;
      transition: transform 0.25s, box-shadow 0.25s;
    }
    .gm-btn-nav-cta:hover { transform: translateY(-2px) scale(1.025); box-shadow: 0 12px 24px rgba(46,125,50,0.25); }
    
    .gm-hamburger { display: none; background: none; border: none; cursor: pointer; padding: 8px; }
    .mobile-menu {
      display: none; position: fixed; inset: 0; z-index: 1001;
      background: rgba(255,255,255,0.97); backdrop-filter: blur(20px);
      flex-direction: column; align-items: center; justify-content: center; gap: 32px;
    }
    .mobile-menu.open { display: flex; }
    .mobile-menu a {
      font-size: 22px; font-weight: 700; color: #1A1208; text-decoration: none;
      cursor: pointer;
      transition: color 0.2s;
    }
    .mobile-menu a:hover { color: #D97706; }
    .mobile-close {
      position: absolute; top: 28px; right: 28px;
      background: none; border: none; cursor: pointer; font-size: 28px; color: #1A1208;
    }

    /* ─── HERO & BACKGROUND ─── */
    .hero-container {
      min-height: 100vh; position: relative; overflow: hidden;
      perspective: 1000px;
      background: linear-gradient(158deg, #FF8C00 0%, #FFA726 14%, #FFB74D 28%, #FFCC80 42%, #FFD9A8 56%, #FFE8C8 70%, #FFF3E0 84%, #FFFDF9 100%);
      display: flex; flex-direction: column;
    }
    /* Deep layered radial glows for premium depth */
    .hero-bg-glow {
      position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden;
    }
    .hero-bg-glow::before {
      content: ''; position: absolute; top: -30%; left: -20%; width: 80%; height: 90%;
      background: radial-gradient(ellipse, rgba(255,255,255,0.55) 0%, rgba(255,200,80,0.22) 35%, transparent 70%);
    }
    .hero-bg-glow::after {
      content: ''; position: absolute; top: 15%; right: -10%; width: 65%; height: 75%;
      background: radial-gradient(ellipse, rgba(255,220,120,0.18) 0%, transparent 60%);
    }
    .hero-main {
      flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
      text-align: center; padding: clamp(48px, 8vh, 88px) 28px clamp(64px, 10vh, 110px);
      position: relative; z-index: 10; margin-top: 80px;
    }
    .gm-badge {
      display: inline-flex; align-items: center; gap: 9px;
      background: rgba(255,255,255,0.72);
      backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(255,255,255,0.92); border-radius: 999px;
      padding: 7px 17px; margin-bottom: 34px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.09), inset 0 1px 0 rgba(255,255,255,0.95);
      animation: slideUp 0.7s ease both; cursor: pointer;
      transform: rotate(-0.5deg);
      transition: background 0.3s, transform 0.3s, box-shadow 0.3s;
    }
    .gm-badge:hover { background: #1A1208; transform: translateY(-2px) rotate(-0.5deg); }
    .gm-badge:hover .badge-text { color: #fff; }
    .badge-dot {
      width: 7px; height: 7px; border-radius: 50%; background: #2E7D32; flex-shrink: 0;
      animation: pulseDot 2.2s ease-in-out infinite;
      box-shadow: 0 0 0 3px rgba(46,125,50,0.22);
    }
    .badge-text { font-size: 12.5px; font-weight: 600; color: #1A1208; letter-spacing: 0.3px; transition: color 0.3s; }
    
    .hero-headline {
      font-size: clamp(54px, 8.5vw, 96px); font-weight: 900; line-height: 1.06;
      letter-spacing: -3px; color: #1A1208; max-width: 900px; margin-bottom: 28px;
      animation: slideUp 0.8s ease 0.1s both;
      text-shadow: 0 4px 16px rgba(0,0,0,0.05);
    }
    .hero-hl {
      position: relative; display: inline-block;
      background: linear-gradient(135deg, #FF512F 0%, #F09819 100%);
      color: #fff; padding: 2px 18px; border-radius: 14px;
      transform: rotate(-1.5deg);
      box-shadow: 0 8px 30px rgba(255,81,47,0.4), inset 0 2px 4px rgba(255,255,255,0.3);
      margin-left: 8px;
      transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275);
      cursor: pointer;
    }
    .hero-hl:hover { transform: rotate(2deg) scale(1.05); }

    @keyframes floatSlow {
      0%, 100% { transform: translateY(0px) rotate(-1deg); }
      50% { transform: translateY(-5px) rotate(0.5deg); }
    }
    @keyframes floatSlowReverse {
      0%, 100% { transform: translateY(0px) rotate(0.8deg); }
      50% { transform: translateY(-5px) rotate(-0.8deg); }
    }

    /* ── Telegram Pill ── */
    .hero-telegram-pill {
      display: inline-flex; align-items: center; gap: 8px;
      background: linear-gradient(135deg, #54c5f8 0%, #0088cc 60%, #006daa 100%);
      color: #fff; font-weight: 900;
      padding: 4px 20px 4px 10px; border-radius: 999px;
      box-shadow: 0 8px 28px rgba(0,136,204,0.38), inset 0 2px 0 rgba(255,255,255,0.25);
      transform: rotate(-1deg); vertical-align: middle;
      transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.3s;
      cursor: default; position: relative; top: -4px;
      backdrop-filter: blur(4px);
      animation: floatSlow 4.5s ease-in-out infinite;
    }
    .hero-telegram-pill:hover { transform: rotate(1deg) scale(1.06); box-shadow: 0 12px 36px rgba(0,136,204,0.5); }
    .hero-telegram-pill svg { width: 0.72em; height: 0.72em; flex-shrink: 0; }

    /* ── WhatsApp Pill ── */
    .hero-whatsapp-pill {
      display: inline-flex; align-items: center; gap: 8px;
      background: linear-gradient(135deg, #57d770 0%, #25d366 50%, #128c7e 100%);
      color: #fff; font-weight: 900;
      padding: 4px 20px 4px 10px; border-radius: 999px;
      box-shadow: 0 8px 28px rgba(37,211,102,0.38), inset 0 2px 0 rgba(255,255,255,0.25);
      transform: rotate(0.8deg); vertical-align: middle;
      transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.3s;
      cursor: default; position: relative; top: -4px;
      backdrop-filter: blur(4px);
      animation: floatSlowReverse 5.2s ease-in-out infinite;
    }
    .hero-whatsapp-pill:hover { transform: rotate(-1deg) scale(1.06); box-shadow: 0 12px 36px rgba(37,211,102,0.5); }
    .hero-whatsapp-pill svg { width: 0.72em; height: 0.72em; flex-shrink: 0; }
    
    .hero-sub {
      font-size: clamp(17px, 2.1vw, 20px); font-weight: 400; color: rgba(26,18,8,0.62);
      line-height: 1.68; max-width: 550px; margin-bottom: 48px; letter-spacing: -0.2px;
      animation: slideUp 0.85s ease 0.18s both; transform: rotate(0.2deg);
    }
    
    .hero-ctas {
      display: flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: center;
      animation: slideUp 0.9s ease 0.28s both;
    }
    .btn-primary {
      display: inline-flex; align-items: center; gap: 10px;
      font-size: 15.5px; font-weight: 700; color: #fff; text-decoration: none;
      padding: 17px 32px; border-radius: 999px;
      background: linear-gradient(135deg, #2E7D32 0%, #43A047 100%);
      border: none; cursor: pointer; font-family: inherit; letter-spacing: -0.25px;
      position: relative; overflow: hidden;
      box-shadow: 0 12px 30px rgba(0,0,0,0.18), inset 0 1.5px 0 rgba(255,255,255,0.3);
      transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.3s;
      transform: rotate(-0.5deg);
    }
    .btn-primary::before, .btn-secondary::before {
      content: ''; position: absolute; top: 0; left: -150%; width: 100%; height: 100%;
      background: linear-gradient(55deg, transparent, rgba(255, 255, 255, 0.1) 30%, rgba(255, 255, 255, 0.5) 50%, rgba(255, 255, 255, 0.1) 70%, transparent);
      pointer-events: none; z-index: 1;
    }
    .btn-primary:hover { transform: translateY(-3px) scale(1.03) rotate(-0.5deg); box-shadow: 0 18px 40px rgba(46,125,50,0.25), 0 6px 16px rgba(0,0,0,0.12); }
    .btn-primary:hover::before, .btn-secondary:hover::before {
      animation: shineSweep 0.85s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-primary:active::before, .btn-secondary:active::before {
      animation: shineSweep 0.85s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .btn-secondary {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 15.5px; font-weight: 600; color: rgba(26,18,8,0.82); text-decoration: none;
      padding: 17px 32px; border-radius: 999px;
      background: rgba(255,255,255,0.35);
      backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
      border: 1.5px solid rgba(255,255,255,0.92);
      cursor: pointer; font-family: inherit; letter-spacing: -0.25px;
      position: relative; overflow: hidden;
      box-shadow: 0 10px 24px rgba(0,0,0,0.06), inset 0 1.5px 0 rgba(255,255,255,0.8);
      transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.3s, background 0.3s;
      transform: rotate(0.4deg);
    }
    .btn-secondary:hover { background: rgba(255,255,255,0.55); transform: translateY(-3px) scale(1.03) rotate(0.4deg); }
    
    .hero-proof {
      display: flex; align-items: center; gap: 20px; margin-top: 52px;
      flex-wrap: wrap; justify-content: center;
      animation: slideUp 0.95s ease 0.4s both;
    }
    .avatars { display: flex; align-items: center; }
    .avatar {
      width: 38px; height: 38px; border-radius: 50%;
      border: 2.5px solid rgba(255,255,255,0.95);
      margin-left: -11px; box-shadow: 0 2px 10px rgba(0,0,0,0.15);
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 800; color: #fff; flex-shrink: 0;
    }
    .avatar:first-child { margin-left: 0; }
    .divider-v { width: 1px; height: 34px; background: rgba(26,18,8,0.12); }
    .proof-text { display: flex; flex-direction: column; gap: 3px; text-align: left; }
    .stars { display: flex; gap: 2px; }
    .star { font-size: 14px; color: #D97706; }
    .proof-sub { font-size: 13px; font-weight: 500; color: rgba(26,18,8,0.58); }
    .proof-sub strong { color: #1A1208; font-weight: 700; }

    /* ── Trust badges row ── */
    /* ── Trust banners row ── */
    .trust-banners {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-top: 48px;
      width: 100%;
      max-width: 1000px;
      animation: slideUp 1s ease 0.5s both;
    }
    .trust-banner {
      display: flex;
      align-items: center;
      gap: 16px;
      background: rgba(255,255,255,0.45);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
      border: 1.5px solid rgba(255,255,255,0.7);
      border-radius: 20px; padding: 18px 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.04), inset 0 1px 0 rgba(255,255,255,0.8);
      transition: transform 0.3s, background 0.3s, box-shadow 0.3s;
      text-align: left;
    }
    .trust-banner:hover {
      transform: translateY(-4px);
      background: rgba(255,255,255,0.65);
      box-shadow: 0 16px 36px rgba(0,0,0,0.08);
    }
    .trust-banner-icon {
      font-size: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .trust-banner-content h4 {
      font-size: 15px; font-weight: 800; color: #1A1208; margin-bottom: 2px;
    }
    .trust-banner-content p {
      font-size: 12px; font-weight: 500; color: rgba(26,18,8,0.6); line-height: 1.3;
    }

    /* ─── ANIMATED FLOATING LEAVES ─── */
    .leaf-anim {
      position: absolute; z-index: 2; pointer-events: none; opacity: 0.22;
    }
    .leaf-1 { animation: leafFloat1 12s ease-in-out infinite alternate; }
    .leaf-2 { animation: leafFloat2 15s ease-in-out infinite alternate; animation-delay: 2s; }
    .leaf-3 { animation: leafFloat3 18s ease-in-out infinite alternate; animation-delay: 1s; }
    .leaf-4 { animation: leafFloat1 14s ease-in-out infinite alternate-reverse; animation-delay: 3s; }

    /* ─── STATS FLOATING CARD ─── */
    .stats-section {
      background: #fffdf9; padding: 110px 28px 10px 28px; position: relative; z-index: 10;
    }
    .stats-card {
      max-width: 1000px; margin: -50px auto 0 auto;
      background: rgba(255,255,255,0.96);
      border: 1px solid rgba(255,255,255,0.98);
      border-radius: 30px; padding: 44px 52px;
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
      box-shadow: 0 20px 60px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.04), inset 0 1px 0 #fff;
      position: relative; overflow: hidden;
      z-index: 20;
    }
    .stats-card::before {
      content: ''; position: absolute; top: 0; left: 8%; right: 8%; height: 2px;
      background: linear-gradient(90deg, transparent, rgba(255,140,0,0.6), rgba(255,81,47,0.4), transparent);
      border-radius: 999px;
    }
    .stat-item { text-align: center; padding: 0 20px; position: relative; }
    .stat-item:not(:last-child)::after {
      content: ''; position: absolute; right: 0; top: 15%; bottom: 15%;
      width: 1px; background: rgba(26,18,8,0.07);
    }
    .stat-icon { font-size: 24px; margin-bottom: 6px; }
    .stat-num {
      font-size: clamp(32px, 3.5vw, 48px); font-weight: 900; color: #1A1208;
      letter-spacing: -2px; line-height: 1;
      background: linear-gradient(135deg, #FF8C00, #E65100);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .stat-label { font-size: 13.5px; font-weight: 500; color: rgba(26,18,8,0.52); margin-top: 7px; letter-spacing: -0.1px; }
    .stat-divider { display: none; }
    .stats-section + .section { padding-top: 50px; }

    /* ─── SECTION STYLING ─── */
    .section { padding: 100px 28px; position: relative; }
    .section-inner { max-width: 1200px; margin: 0 auto; }
    .section-tag {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(255,140,0,0.08); border: 1px solid rgba(255,140,0,0.22);
      border-radius: 999px; padding: 6px 16px; margin-bottom: 20px;
      font-size: 12px; font-weight: 700; color: #B45309; letter-spacing: 0.8px; text-transform: uppercase;
    }
    .section-title {
      font-size: clamp(32px, 5vw, 48px); font-weight: 900; color: #1A1208;
      letter-spacing: -2px; line-height: 1.1; margin-bottom: 16px;
    }
    .section-sub {
      font-size: clamp(16px, 2vw, 18px); color: rgba(26,18,8,0.55);
      line-height: 1.65; max-width: 580px; margin-bottom: 60px;
    }
    .text-center { text-align: center; }
    .section-sub.centered { margin: 0 auto 60px; }
    .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }

    /* ─── PREMIUM GLASSMORPHIC CARD ─── */
    .card {
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 24px; padding: 36px 30px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.04), inset 0 1px 0 #fff;
      transition: transform 0.35s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.35s;
      position: relative; overflow: hidden;
    }
    .card::before {
      content: ''; position: absolute; top: 0; left: 8%; right: 8%; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.9), transparent);
    }
    .card:hover { transform: translateY(-6px); box-shadow: 0 20px 50px rgba(0,0,0,0.08); }
    .card-icon {
      width: 52px; height: 52px; border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; margin-bottom: 18px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.05);
    }
    .card h3 { font-size: 18px; font-weight: 700; color: #1A1208; letter-spacing: -0.4px; margin-bottom: 10px; }
    .card p { font-size: 14.5px; color: rgba(26,18,8,0.6); line-height: 1.65; }

    /* ─── TAB FILTER BUTTONS ─── */
    .tabs-container { display: flex; justify-content: center; margin-bottom: 50px; }
    .tabs {
      display: inline-flex; background: linear-gradient(135deg, #0e3020 0%, #17422f 100%);
      padding: 6px; border-radius: 40px; border: 1px solid rgba(46, 125, 50, 0.4);
      box-shadow: 0 8px 24px rgba(15, 54, 37, 0.15);
    }
    .tab-btn {
      background: transparent; border: none; outline: none;
      padding: 12px 28px; border-radius: 30px;
      font-family: inherit; font-size: 14.5px; font-weight: 700;
      color: rgba(255, 255, 255, 0.65); cursor: pointer;
      transition: all 0.3s ease;
    }
    .tab-btn:hover {
      color: #ffffff;
    }
    .tab-btn.active {
      background: linear-gradient(135deg, #FF8C00 0%, #FFA726 100%); color: #ffffff;
      box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
    }

    /* ─── CATALOG SECTION ─── */
    .catalog-panel { display: none; }
    .catalog-panel.active {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;
    }
    .catalog-card {
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 24px; padding: 32px 28px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.04), inset 0 1px 0 #fff;
      transition: transform 0.35s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.35s;
      position: relative; overflow: hidden;
      display: flex; flex-direction: column; justify-content: space-between;
    }
    .catalog-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(255,140,0,0.1); border-color: rgba(255,140,0,0.25); }
    .card-header-info { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .country-flag-name { display: flex; align-items: center; gap: 12px; }
    .flag { font-size: 26px; }
    .country-name { font-size: 16.5px; font-weight: 700; color: #1A1208; text-transform: capitalize; letter-spacing: -0.3px; }
    .stock-badge { font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 999px; }
    .stock-in { background-color: rgba(46,125,50,0.1); color: #2E7D32; }
    .stock-out { background-color: rgba(211,47,47,0.1); color: #C62828; }
    
    .product-name { font-size: 14.5px; color: rgba(26,18,8,0.6); margin-bottom: 24px; text-align: left; line-height: 1.5; }
    .product-name strong { color: #1A1208; }
    
    .card-footer {
      display: flex; align-items: center; justify-content: space-between;
      border-top: 1px dashed rgba(26,18,8,0.08); padding-top: 20px; margin-top: auto;
    }
    .price-box { text-align: left; }
    .price-inr {
      font-size: 24px; font-weight: 900; color: #1A1208; letter-spacing: -1px;
      background: linear-gradient(135deg, #FF8C00, #E65100);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .price-usd { font-size: 12px; color: rgba(26,18,8,0.45); font-weight: 600; margin-top: 2px; }
    .btn-card-buy {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 700; color: #fff; text-decoration: none;
      padding: 10px 20px; border-radius: 999px;
      background: linear-gradient(135deg, #2E7D32 0%, #43A047 100%);
      box-shadow: 0 4px 12px rgba(46,125,50,0.15); transition: all 0.3s ease;
    }
    .btn-card-buy:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(46,125,50,0.25); }
    .btn-card-buy.disabled {
      background: rgba(26,18,8,0.1); color: rgba(26,18,8,0.3);
      box-shadow: none; cursor: not-allowed; pointer-events: none;
    }

    /* ─── PROCESS STEPS ─── */
    .process-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; position: relative; }
    .process-steps::before {
      content: ''; position: absolute; top: 40px; left: 10%; right: 10%; height: 2px;
      background: linear-gradient(90deg, #FF8C00, #FFB74D, #FF8C00);
      opacity: 0.25; z-index: 0;
    }
    .process-step {
      background: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.9);
      border-radius: 28px; padding: 36px 28px; text-align: center;
      box-shadow: 0 8px 25px rgba(0,0,0,0.04); position: relative; z-index: 1;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .process-step:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(0,0,0,0.08); }
    .step-num {
      width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 18px;
      background: linear-gradient(135deg, #FF8C00, #FFA726);
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; font-weight: 900; color: #fff;
      box-shadow: 0 6px 20px rgba(255,140,0,0.3);
    }
    .process-step h3 { font-size: 17px; font-weight: 700; color: #1A1208; margin-bottom: 10px; }
    .process-step p { font-size: 14px; color: rgba(26,18,8,0.58); line-height: 1.6; }

    /* ─── FAQ ACCORDIONS ─── */
    .faq-list { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }
    .faq-item {
      background: rgba(255,255,255,0.8);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 20px; overflow: hidden; transition: box-shadow 0.3s;
    }
    .faq-item:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.05); }
    .faq-q {
      display: flex; justify-content: space-between; align-items: center;
      padding: 22px 26px; cursor: pointer; user-select: none;
      font-size: 16px; font-weight: 600; color: #1A1208;
      transition: background 0.2s;
    }
    .faq-q:hover { background: rgba(255,140,0,0.03); }
    .faq-chevron { flex-shrink: 0; transition: transform 0.3s; font-size: 14px; color: #D97706; }
    .faq-chevron.open { transform: rotate(180deg); }
    .faq-a {
      display: none; padding: 0 26px 22px;
      font-size: 14.5px; color: rgba(26,18,8,0.58); line-height: 1.7;
      border-top: 1px dashed rgba(0,0,0,0.04);
    }
    .faq-a.open { display: block; }

    /* ─── FINAL CALL TO ACTION ─── */
    .cta-section {
      background: linear-gradient(158deg, #1A1208 0%, #2D1F0E 50%, #1A1208 100%);
      padding: 90px 28px; text-align: center; position: relative; overflow: hidden;
      border-radius: 36px; margin: 40px auto; max-width: 1200px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    }
    .cta-section::before {
      content: ''; position: absolute; top: -50%; left: -20%; width: 60%; height: 200%;
      background: radial-gradient(ellipse, rgba(255,140,0,0.15) 0%, transparent 60%);
    }
    .cta-section::after {
      content: ''; position: absolute; bottom: -50%; right: -20%; width: 60%; height: 200%;
      background: radial-gradient(ellipse, rgba(255,81,47,0.1) 0%, transparent 60%);
    }
    .cta-inner { max-width: 680px; margin: 0 auto; position: relative; z-index: 2; }
    .cta-inner h2 {
      font-size: clamp(32px, 5vw, 52px); font-weight: 900; color: #fff;
      letter-spacing: -2px; line-height: 1.1; margin-bottom: 20px;
    }
    .cta-inner h2 span { color: #FFA726; }
    .cta-inner p { font-size: 17px; color: rgba(255,255,255,0.6); margin-bottom: 40px; line-height: 1.6; }
    
    .btn-cta-big {
      display: inline-flex; align-items: center; gap: 12px;
      font-size: 16px; font-weight: 700; color: #1A1208; text-decoration: none;
      padding: 18px 38px; border-radius: 999px;
      background: linear-gradient(135deg, #FF8C00 0%, #FFA726 100%);
      border: none; cursor: pointer; font-family: inherit;
      box-shadow: 0 0 0 0 rgba(255,140,0,0.4);
      animation: glowPulse 2.5s ease-in-out infinite;
      transition: transform 0.3s;
    }
    .btn-cta-big:hover { transform: translateY(-3px) scale(1.03); }
    .cta-trust { margin-top: 22px; font-size: 13px; color: rgba(255,255,255,0.35); letter-spacing: 0.2px; }

    /* ─── SYSTEM STATUS PENDING WARNING ─── */
    .setup-alert {
      background: rgba(255, 140, 0, 0.08); border: 1.5px solid rgba(255, 140, 0, 0.25);
      border-radius: 24px; padding: 24px 30px; margin: 40px auto 0 auto;
      max-width: 800px; text-align: center; backdrop-filter: blur(10px);
      box-shadow: 0 8px 30px rgba(255, 140, 0, 0.05);
    }
    .setup-alert h3 { font-size: 18px; font-weight: 800; color: #D97706; margin-bottom: 8px; }
    .setup-alert p { font-size: 14px; color: rgba(26,18,8,0.65); line-height: 1.6; }
    .setup-alert a { color: #D97706; font-weight: 700; text-decoration: underline; }

    /* ─── FOOTER ─── */
    .footer {
      background: #1A1208; color: rgba(255,255,255,0.65);
      padding: 80px 28px 36px 28px; position: relative; overflow: hidden;
    }
    .footer::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255,140,0,0.3), transparent);
    }
    .footer-inner { max-width: 1200px; margin: 0 auto; }
    .footer-grid {
      display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 60px; margin-bottom: 60px;
    }
    .footer-brand p { font-size: 14px; line-height: 1.7; margin-top: 16px; max-width: 280px; color: rgba(255,255,255,0.45); }
    .footer-col h4 { font-size: 13px; font-weight: 700; color: rgba(255,255,255,0.9); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 18px; }
    .footer-col a {
      display: block; font-size: 14px; color: rgba(255,255,255,0.55);
      text-decoration: none; margin-bottom: 12px; cursor: pointer;
      transition: color 0.25s;
    }
    .footer-col a:hover { color: #FFA726; }
    .footer-bottom {
      border-top: 1px solid rgba(255,255,255,0.08); padding-top: 28px;
      display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
      font-size: 13px; color: rgba(255,255,255,0.3);
    }

    /* ═══════════════════════════════════════════
       RESPONSIVE SYSTEM — Mobile First
    ═══════════════════════════════════════════ */

    /* ── Tablet (≤1024px) ── */
    @media (max-width: 1024px) {
      .grid-4 { grid-template-columns: repeat(2, 1fr); }
      .grid-3 { grid-template-columns: repeat(2, 1fr); }
      .process-steps { grid-template-columns: 1fr; }
      .process-steps::before { display: none; }
      .footer-grid { grid-template-columns: 1fr 1fr; gap: 40px; }
      .stats-card { grid-template-columns: repeat(2, 1fr); padding: 36px 32px; }
      .stat-item:nth-child(2)::after { display: none; }
    }

    /* ── Small Tablet / Large Phone (≤768px) ── */
    @media (max-width: 768px) {
      /* Prevent ALL horizontal overflow at root level */
      html, body { overflow-x: hidden; max-width: 100%; width: 100%; }


      /* ─────────────────────────────────────────
         MOBILE NAVBAR — Clean fixed pill
         Requirements: top:12px, calc(100%-24px),
         min-height:58px, logo left + burger right
      ───────────────────────────────────────── */

      /* 1. Hide all desktop nav items */
      .gm-nav-links,
      .gm-btn-nav-cta,
      .gm-nav-auth-link { display: none !important; }

      /* 2. Wrap: fixed, top 12px, no padding (pill handles its own margins) */
      .gm-nav-wrap {
        position: fixed;
        top: 12px;
        left: 0;
        right: 0;
        padding: 0;
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        pointer-events: none;
        width: 100%;
        box-sizing: border-box;
      }

      /* 3. Pill: exactly calc(100% - 24px) = 12px gap each side */
      .gm-nav-pill {
        width: calc(100% - 24px);
        max-width: calc(100% - 24px);
        min-height: 58px;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 18px;
        padding: 0 8px 0 16px;   /* right:8px gives hamburger button space */
        gap: 0;
        overflow: hidden;
        pointer-events: auto;
        margin: 0 auto;
      }

      /* 4. Logo */
      .gm-logo { gap: 10px; flex-shrink: 0; }
      .gm-logo span { font-size: 15px; white-space: nowrap; }
      .gm-logo-icon, .gm-logo img { width: 30px !important; height: 30px !important; }

      /* 5. Auth div — only hamburger left */
      .gm-nav-pill > div:last-child { gap: 0 !important; flex-shrink: 0; }

      /* 6. Hamburger — 40×40 tap target, 8px internal padding */
      .gm-hamburger {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        min-width: 40px;
        min-height: 40px;
        padding: 8px;
        flex-shrink: 0;
        cursor: pointer;
      }

      /* 7. Hero: start below fixed navbar (12px top + 58px height + 20px buffer = 90px) */
      .hero-main {
        padding: 100px 20px 52px;
        margin-top: 0;
      }

      .hero-orb-tl { width: 320px; height: 320px; filter: blur(60px); }
      .hero-orb-tr { width: 260px; height: 260px; filter: blur(70px); }
      .hero-orb-bc { width: 100%; max-width: 100%; filter: blur(70px); }
      .hero-orb-mid { display: none; }
      .hero-network { display: none; } /* too complex for mobile */
      .hero-network { display: none; }
      .hgc-1, .hgc-2, .hgc-3, .hgc-4 { display: none; }
      .hero-wm-telegram { width: 160px; height: 160px; }
      .hero-wm-whatsapp { width: 140px; height: 140px; }
      .hero-flag { font-size: 22px; }

      /* Hero layers: contain & clip on mobile */
      .hero-container { overflow: hidden; }

      .grid-3 { grid-template-columns: 1fr; }
      .section { padding: 60px 20px; }
      .section-sub { max-width: 100%; }
      .catalog-panel.active { grid-template-columns: 1fr; }
      #countrySearchInput { font-size: 16px; }

      /* Stats */
      .stats-section { padding: 75px 16px 10px 16px; }

      .stats-card {
        grid-template-columns: repeat(2, 1fr);
        padding: 24px 16px;
        margin: -35px auto 0 auto;
        border-radius: 24px;
      }
      .stat-item::after { display: none !important; }
      .stat-item { padding: 14px 10px; border-bottom: 1px solid rgba(26,18,8,0.05); }
      .stat-item:nth-child(3), .stat-item:nth-child(4) { border-bottom: none; }
      .stats-section + .section { padding-top: 30px; }

      /* Cards */
      .card { padding: 28px 22px; }

      /* Footer */
      .footer-grid { grid-template-columns: 1fr; gap: 32px; }
      .footer { padding: 64px 20px 32px; }
      .footer-bottom { flex-direction: column; align-items: flex-start; gap: 10px; }

      /* CTA */
      .cta-section {
        border-radius: 22px;
        padding: 56px 22px;
        margin: 24px 16px;
        max-width: calc(100% - 32px);
      }
      .cta-inner h2 { letter-spacing: -1.5px; }
      .cta-inner p { font-size: 15px; }
      .btn-cta-big { padding: 16px 28px; font-size: 15px; }

      /* Ticker */
      .ticker-item { font-size: 12px; padding: 0 18px; gap: 6px; }
      .ticker-label { font-size: 9.5px; padding: 0 14px 0 12px; }

      /* Trust Banners on Tablet */
      .trust-banners {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-top: 36px;
      }
      .trust-banner {
        padding: 12px 14px;
        gap: 10px;
        border-radius: 16px;
      }
      .trust-banner-icon {
        font-size: 22px;
      }
      .trust-banner-content h4 {
        font-size: 13px;
      }
      .trust-banner-content p {
        font-size: 10.5px;
      }
    }

    /* ── Phone (≤480px) — iPhone 14 Pro Max, most Androids ── */
    @media (max-width: 480px) {
      /* Absolute containment — no overflow possible */
      *, *::before, *::after { max-width: 100%; }
      html, body { overflow-x: hidden; max-width: 100%; width: 100%; }


      /* Navbar: tighten for ≤480px — same structure, smaller values */
      .gm-nav-wrap {
        position: fixed;
        top: 10px;
        left: 0;
        right: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        width: 100%;
        box-sizing: border-box;
      }
      .gm-nav-pill {
        width: calc(100% - 24px);
        max-width: calc(100% - 24px);
        min-height: 56px;
        border-radius: 16px;
        padding: 0 6px 0 14px;
        margin: 0 auto;
        box-sizing: border-box;
      }
      .gm-logo span { font-size: 14px; }
      .gm-logo-icon, .gm-logo img { width: 28px !important; height: 28px !important; }
      .gm-hamburger { width: 40px; height: 40px; min-width: 40px; min-height: 40px; padding: 8px; }

      /* Hero: clear the 10px + 56px navbar */
      .hero-main {
        padding: 95px 18px clamp(36px, 6vh, 60px);
        margin-top: 0;
        align-items: center;
      }

      .hero-orb-tl { width: 220px; height: 220px; top: -8%; left: -8%; filter: blur(50px); }
      .hero-orb-tr { width: 180px; height: 180px; top: -6%; right: -6%; filter: blur(55px); }
      .hero-orb-bc { display: none; }
      .hero-worldmap { opacity: 0.025; width: 100%; max-width: 100%; }
      .hero-wm-telegram, .hero-wm-whatsapp { display: none; }
      .hf5, .hf6 { display: none; }
      .hero-flag { font-size: 20px; }
      .hf1 { top: 14%; left: 4%; }
      .hf2 { top: 8%; right: 5%; }
      .hf3 { top: 62%; right: 4%; }
      .hf4 { bottom: 18%; left: 5%; }
      .hero-headline-glow { width: min(500px, 95vw); }

      /* Hero: clip all decorative elements */
      .hero-container { min-height: 100svh; overflow: hidden; }


      /* Badge */
      .gm-badge {
        margin-bottom: 18px;
        padding: 6px 14px;
        font-size: 11.5px;
        transform: none;
      }

      /* Headline */
      .hero-headline {
        font-size: clamp(42px, 10vw, 58px);
        letter-spacing: -1.8px;
        line-height: 1.1;
        margin-bottom: 16px;
        max-width: 100% !important;
        word-break: break-word;
      }

      /* Pills — scale down to fit inline */
      .hero-telegram-pill, .hero-whatsapp-pill {
        font-size: 0.75em;
        padding: 3px 10px 3px 7px;
        gap: 5px;
        top: -2px;
        box-shadow: 0 3px 12px rgba(0,0,0,0.2);
      }
      .hero-telegram-pill svg, .hero-whatsapp-pill svg { width: 0.68em; height: 0.68em; }
      .hero-hl { padding: 1px 12px; border-radius: 9px; margin-left: 4px; }

      /* Subtitle */
      .hero-sub {
        font-size: 14.5px;
        margin-bottom: 24px;
        line-height: 1.62;
        max-width: 100%;
        transform: none;
      }

      /* CTA buttons — stacked and centered */
      .hero-ctas { flex-direction: column; align-items: center; gap: 10px; width: 100%; }
      .btn-primary, .btn-secondary {
        justify-content: center;
        padding: 15px 32px;
        font-size: 15px;
        border-radius: 999px;
        width: 100%;
        max-width: 260px;
        transform: none !important;
      }

      /* Social proof — single row, no wrapping */
      .hero-proof {
        flex-wrap: nowrap;
        align-items: center;
        gap: 10px;
        margin-top: 26px;
        justify-content: center;
        width: 100%;
      }
      .avatars { flex-shrink: 0; }
      .avatar { width: 30px; height: 30px; margin-left: -8px; font-size: 10px; border-width: 2px; }
      .avatar:first-child { margin-left: 0; }
      .divider-v { height: 26px; flex-shrink: 0; }
      .proof-text { flex-shrink: 1; min-width: 0; text-align: left; }
      .stars { font-size: 11px; }
      .proof-sub { font-size: 11.5px; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

      /* Trust banners — stacked vertically on small screens */
      .trust-banners {
        grid-template-columns: 1fr;
        gap: 12px;
        width: 100%;
        max-width: 320px;
        margin: 28px auto 0;
      }
      .trust-banner {
        padding: 12px 18px;
        gap: 14px;
        border-radius: 16px;
      }
      .trust-banner-icon {
        font-size: 24px;
      }
      .trust-banner-content h4 {
        font-size: 13.5px;
      }
      .trust-banner-content p {
        font-size: 11px;
      }

      /* Stats card */
      .stats-section { padding: 65px 12px 10px 12px; }

      .stats-card {
        grid-template-columns: 1fr 1fr;
        padding: 20px 14px;
        border-radius: 20px;
        margin: -30px auto 0 auto;
        gap: 0;
      }
      .stat-icon { font-size: 18px; margin-bottom: 4px; }
      .stat-num { font-size: clamp(22px, 6.5vw, 34px); letter-spacing: -1.5px; }
      .stat-label { font-size: 11.5px; margin-top: 4px; }
      .stats-section + .section { padding-top: 25px; }

      /* Sections */
      .section { padding: 52px 16px; }
      .section-inner { padding: 0; }
      .section-title { font-size: clamp(24px, 7vw, 34px); letter-spacing: -1.2px; }
      .section-sub { font-size: 14px; margin-bottom: 36px; }
      .section-tag { font-size: 11px; padding: 5px 14px; }

      /* Cards */
      .card { padding: 24px 18px; border-radius: 20px; }
      .card h3 { font-size: 16px; }
      .card p { font-size: 13.5px; }
      .card-icon { width: 44px; height: 44px; font-size: 19px; border-radius: 13px; margin-bottom: 14px; }

      /* Catalog cards */
      .catalog-card { padding: 24px 20px; border-radius: 20px; }
      .country-name { font-size: 15px; }
      .flag { font-size: 22px; }
      .price-inr { font-size: 20px; }
      .btn-card-buy { font-size: 12px; padding: 9px 16px; }

      /* Tabs */
      .tabs-container { margin-bottom: 36px; }
      .tabs { padding: 5px; border-radius: 32px; }
      .tab-btn { padding: 10px 20px; font-size: 13.5px; border-radius: 26px; }

      /* Process steps */
      .process-step { padding: 28px 22px; border-radius: 22px; }
      .step-num { width: 48px; height: 48px; font-size: 18px; }
      .process-step h3 { font-size: 15.5px; }
      .process-step p { font-size: 13px; }

      /* FAQ */
      .faq-q { padding: 18px 20px; font-size: 14.5px; }
      .faq-a { padding: 0 20px 18px; font-size: 13.5px; }

      /* CTA */
      .cta-section { border-radius: 18px; padding: 48px 18px; margin: 20px 12px; max-width: calc(100% - 24px); }
      .cta-inner h2 { font-size: clamp(26px, 7vw, 36px); letter-spacing: -1.2px; }
      .cta-inner p { font-size: 14px; margin-bottom: 28px; }
      .btn-cta-big { padding: 14px 24px; font-size: 14.5px; gap: 10px; width: 100%; justify-content: center; }

      /* Footer */
      .footer { padding: 52px 16px 28px; }
      .footer-grid { grid-template-columns: 1fr; gap: 28px; margin-bottom: 40px; }
      .footer-brand p { max-width: 100%; }
      .footer-bottom { flex-direction: column; align-items: flex-start; gap: 8px; font-size: 12px; }
      .footer-col h4 { margin-bottom: 14px; }
      .footer-col a { margin-bottom: 10px; font-size: 13.5px; }

      /* Ticker */
      .ticker-track { animation-duration: 28s; padding-left: 100px; }
      .ticker-item { padding: 0 14px; font-size: 11.5px; gap: 5px; }
      .ticker-label { font-size: 9px; padding: 0 12px 0 10px; gap: 5px; }
    }

    /* ── Very small phones (≤360px — iPhone SE, Galaxy A series) ── */
    @media (max-width: 360px) {
      /* Navbar: ≤360px — even tighter */
      .gm-nav-wrap {
        position: fixed;
        top: 8px;
        left: 0;
        right: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        width: 100%;
        box-sizing: border-box;
      }
      .gm-nav-pill {
        width: calc(100% - 20px);
        max-width: calc(100% - 20px);
        min-height: 52px;
        border-radius: 14px;
        padding: 0 4px 0 12px;
        margin: 0 auto;
        box-sizing: border-box;
      }
      .gm-logo span { font-size: 13px; }
      .gm-logo img, .gm-logo-icon { width: 26px !important; height: 26px !important; }
      .gm-hamburger { width: 36px; height: 36px; min-width: 36px; min-height: 36px; padding: 6px; }

      /* Hero: clear 8px + 52px navbar */
      .hero-main { padding: 82px 14px 36px; margin-top: 0; }

      .hero-headline { font-size: clamp(34px, 9.5vw, 46px); letter-spacing: -1.2px; }
      .hero-telegram-pill, .hero-whatsapp-pill { font-size: 0.68em; padding: 2px 8px 2px 6px; }
      .hero-sub { font-size: 13.5px; }
      .btn-primary, .btn-secondary { padding: 13px 24px; font-size: 14px; border-radius: 999px; max-width: 240px; }
      .stats-section { padding: 55px 10px 10px 10px; }
      .stats-card { padding: 16px 10px; margin: -25px auto 0 auto; }
      .stats-section + .section { padding-top: 20px; }

      .stat-num { font-size: clamp(20px, 6vw, 28px); }
      .stat-label { font-size: 10.5px; }
      .section { padding: 44px 14px; }
      .section-title { font-size: clamp(22px, 6.5vw, 30px); }
      .card { padding: 20px 14px; }
      .tab-btn { padding: 9px 14px; font-size: 12.5px; }
      .cta-section { padding: 40px 14px; margin: 16px 10px; }
      .ticker-track { padding-left: 80px; }
      .ticker-item { padding: 0 10px; font-size: 11px; }
    }

    /* ═══════════════════════════════════════════
       PREMIUM HERO BACKGROUND LAYERS
    ═══════════════════════════════════════════ */

    /* L1 — Gradient Orbs */
    .hero-orb {
      position: absolute; border-radius: 50%;
      pointer-events: none; will-change: transform;
    }
    .hero-orb-tl {
      width: 700px; height: 700px; top: -20%; left: -12%; z-index: 1;
      background: radial-gradient(circle, rgba(255,140,0,0.38) 0%, rgba(255,160,60,0.12) 45%, transparent 70%);
      filter: blur(90px);
      animation: orbDrift1 22s ease-in-out infinite alternate;
    }
    .hero-orb-tr {
      width: 560px; height: 560px; top: -12%; right: -10%; z-index: 1;
      background: radial-gradient(circle, rgba(255,193,7,0.30) 0%, rgba(255,220,80,0.10) 50%, transparent 70%);
      filter: blur(110px);
      animation: orbDrift2 28s ease-in-out infinite alternate;
    }
    .hero-orb-bc {
      width: 800px; height: 440px; bottom: -8%; left: 50%; z-index: 1;
      transform: translateX(-50%);
      background: radial-gradient(ellipse, rgba(255,252,235,0.55) 0%, rgba(255,235,180,0.18) 50%, transparent 75%);
      filter: blur(80px);
    }
    .hero-orb-mid {
      width: 450px; height: 450px; top: 25%; left: 28%; z-index: 1;
      background: radial-gradient(circle, rgba(255,175,30,0.14) 0%, transparent 70%);
      filter: blur(130px);
      animation: orbDrift3 34s ease-in-out infinite alternate;
    }

    /* L3 — World Map */
    .hero-worldmap {
      position: absolute; z-index: 2; pointer-events: none;
      top: 50%; left: 50%;
      transform: translate(-50%, -54%);
      width: 95%; max-width: 1100px;
      opacity: 0.045;
      filter: blur(0.8px);
      will-change: auto;
    }

    /* L4 — Platform Watermarks */
    .hero-watermark {
      position: absolute; z-index: 2; pointer-events: none;
      will-change: transform;
    }
    .hero-wm-telegram {
      width: 300px; height: 300px; top: 4%; right: 2%;
      opacity: 0.032; filter: blur(3px);
      animation: wmFloat1 20s ease-in-out infinite alternate;
    }
    .hero-wm-whatsapp {
      width: 260px; height: 260px; bottom: 6%; left: 1%;
      opacity: 0.028; filter: blur(3px);
      animation: wmFloat2 24s ease-in-out infinite alternate;
    }

    /* L5 — Floating Flags */
    .hero-flag {
      position: absolute; z-index: 3; pointer-events: none;
      font-size: 30px; will-change: transform;
      filter: drop-shadow(0 4px 10px rgba(0,0,0,0.18));
      line-height: 1;
    }
    .hf1 { top: 16%; left: 5%;  opacity: 0.60; animation: flagFloat1 15s ease-in-out infinite alternate; animation-delay: 0s; }
    .hf2 { top: 10%; right: 7%; opacity: 0.55; animation: flagFloat2 17s ease-in-out infinite alternate; animation-delay: 1.2s; }
    .hf3 { top: 65%; right: 5%; opacity: 0.50; animation: flagFloat1 20s ease-in-out infinite alternate-reverse; animation-delay: 2.5s; }
    .hf4 { bottom: 20%; left: 7%; opacity: 0.55; animation: flagFloat2 16s ease-in-out infinite alternate; animation-delay: 0.8s; }
    .hf5 { top: 40%; left: 2%;  opacity: 0.45; animation: flagFloat3 22s ease-in-out infinite alternate; animation-delay: 1.8s; }
    .hf6 { top: 48%; right: 3%; opacity: 0.48; animation: flagFloat1 18s ease-in-out infinite alternate-reverse; animation-delay: 3.2s; }

    /* L6 — Network SVG */
    .hero-network {
      position: absolute; inset: 0; z-index: 2; pointer-events: none;
      width: 100%; height: 100%; overflow: visible;
      opacity: 0.9;
    }

    /* L7 — Floating Glass Cards */
    .hero-glass-card {
      position: absolute; z-index: 3; pointer-events: none;
      background: rgba(255,255,255,0.22);
      backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.55);
      border-radius: 20px; padding: 14px 18px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.07), 0 2px 8px rgba(0,0,0,0.04), inset 0 1px 0 rgba(255,255,255,0.8);
      min-width: 172px; will-change: transform;
    }
    .hgc-topline {
      position: absolute; top: 0; left: 15%; right: 15%; height: 1.5px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.95), transparent);
      border-radius: 999px;
    }
    .hgc-country { font-size: 12.5px; font-weight: 700; color: #1A1208; display: flex; align-items: center; gap: 6px; margin-bottom: 5px; }
    .hgc-service  { font-size: 11px; color: rgba(26,18,8,0.6); margin-bottom: 5px; font-weight: 500; }
    .hgc-status   { font-size: 10.5px; font-weight: 700; color: #2E7D32; display: flex; align-items: center; gap: 5px; }
    .hgc-dot      { width: 6px; height: 6px; background: #4CAF50; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 3px rgba(76,175,80,0.2); }

    .hgc-1 { top: 14%; left: 1.5%;   animation: cardFloat1 18s ease-in-out infinite alternate; }
    .hgc-2 { top: 20%; right: 1%;    animation: cardFloat2 21s ease-in-out infinite alternate; animation-delay: 2s; }
    .hgc-3 { bottom: 24%; right: 1.5%; animation: cardFloat1 23s ease-in-out infinite alternate-reverse; animation-delay: 1s; }
    .hgc-4 { bottom: 16%; left: 1%;   animation: cardFloat2 19s ease-in-out infinite alternate; animation-delay: 3s; }

    /* Headline area glow */
    .hero-headline-glow {
      position: absolute; pointer-events: none; z-index: 5;
      top: 48%; left: 50%; transform: translate(-50%, -58%);
      width: 700px; height: 320px;
      background: radial-gradient(ellipse, rgba(255,140,0,0.18) 0%, rgba(0,136,204,0.06) 45%, transparent 72%);
      filter: blur(50px);
    }

    /* ─── ACTIVITY TICKER ─── */
    .activity-ticker {
      position: relative; z-index: 15; overflow: hidden;
      background: rgba(255,255,255,0.88);
      backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      border-top: 1px solid rgba(255,255,255,0.95);
      border-bottom: 1px solid rgba(0,0,0,0.04);
      padding: 11px 0;
      box-shadow: 0 4px 20px rgba(0,0,0,0.035);
    }
    .ticker-label {
      position: absolute; left: 0; top: 0; bottom: 0; z-index: 3;
      display: flex; align-items: center; gap: 6px;
      padding: 0 20px 0 16px;
      background: linear-gradient(90deg, rgba(255,253,249,1) 70%, transparent);
      font-size: 10.5px; font-weight: 800; color: #D97706;
      letter-spacing: 0.8px; text-transform: uppercase; white-space: nowrap;
    }
    .ticker-label-dot {
      width: 7px; height: 7px; border-radius: 50%; background: #ef4444; flex-shrink: 0;
      box-shadow: 0 0 0 3px rgba(239,68,68,0.2);
      animation: tickerDotPulse 1.5s ease-in-out infinite;
    }
    .ticker-fade-r {
      position: absolute; right: 0; top: 0; bottom: 0; z-index: 3; width: 70px;
      background: linear-gradient(270deg, rgba(255,253,249,1) 50%, transparent);
      pointer-events: none;
    }
    .ticker-track {
      display: flex; align-items: center;
      animation: tickerScroll 38s linear infinite;
      will-change: transform;
      padding-left: 120px;
    }
    .ticker-track:hover { animation-play-state: paused; }
    .ticker-item {
      display: inline-flex; align-items: center; gap: 8px;
      white-space: nowrap; padding: 0 28px;
      font-size: 13px; font-weight: 500; color: rgba(26,18,8,0.68);
      border-right: 1px solid rgba(26,18,8,0.06);
      flex-shrink: 0;
    }
    .ticker-flag   { font-size: 15px; }
    .ticker-strong { font-weight: 700; color: #1A1208; }
    .ticker-ts     { font-size: 11px; color: rgba(26,18,8,0.38); font-weight: 500; }
    .ticker-dot    {
      width: 5px; height: 5px; border-radius: 50%; background: #4CAF50;
      box-shadow: 0 0 0 2px rgba(76,175,80,0.22); flex-shrink: 0;
      animation: tickerDotPulse 2.2s ease-in-out infinite;
    }

    /* ─── MOBILE: hide heavy elements ─── */
    @media (max-width: 768px) {
      .hgc-1, .hgc-2, .hgc-3, .hgc-4 { display: none; }
      .hero-network { opacity: 0.35; }
      .hero-wm-telegram { width: 180px; height: 180px; }
      .hero-wm-whatsapp { width: 160px; height: 160px; }
      .hero-flag { font-size: 22px; }
      .hero-orb-tl { width: 350px; height: 350px; }
      .hero-orb-tr { width: 280px; height: 280px; }
      .ticker-item { font-size: 12px; padding: 0 18px; gap: 6px; }
      .ticker-label { font-size: 9.5px; }
    }
    @media (max-width: 480px) {
      .hero-wm-telegram, .hero-wm-whatsapp { display: none; }
      .hf5, .hf6 { display: none; }
      .hero-flag { font-size: 20px; opacity: 0.5 !important; }
      .hero-orb-tl { width: 240px; height: 240px; filter: blur(60px); }
      .hero-orb-tr { width: 200px; height: 200px; filter: blur(70px); }
      .hero-worldmap { opacity: 0.025; }
      .ticker-track { animation-duration: 28s; }
      .ticker-item { padding: 0 14px; font-size: 11.5px; }
    }

    /* ─── NEW KEYFRAMES ─── */
    @keyframes orbDrift1 {
      from { transform: translate(0,0) scale(1); }
      to   { transform: translate(35px,-25px) scale(1.06); }
    }
    @keyframes orbDrift2 {
      from { transform: translate(0,0) scale(1); }
      to   { transform: translate(-30px,20px) scale(0.94); }
    }
    @keyframes orbDrift3 {
      from { transform: translate(0,0); }
      to   { transform: translate(-22px,-28px); }
    }
    @keyframes wmFloat1 {
      from { transform: translateY(0) rotate(0deg); }
      to   { transform: translateY(-22px) rotate(6deg); }
    }
    @keyframes wmFloat2 {
      from { transform: translateY(0) rotate(0deg); }
      to   { transform: translateY(18px) rotate(-5deg); }
    }
    @keyframes flagFloat1 {
      from { transform: translate(0,0) rotate(-3deg); }
      to   { transform: translate(9px,-15px) rotate(4deg); }
    }
    @keyframes flagFloat2 {
      from { transform: translate(0,0) rotate(2deg); }
      to   { transform: translate(-7px,-18px) rotate(-5deg); }
    }
    @keyframes flagFloat3 {
      from { transform: translate(0,0) scale(1); }
      to   { transform: translate(11px,-13px) scale(1.06); }
    }
    @keyframes cardFloat1 {
      from { transform: translateY(0); }
      to   { transform: translateY(-13px); }
    }
    @keyframes cardFloat2 {
      from { transform: translateY(0); }
      to   { transform: translateY(11px); }
    }
    @keyframes tickerScroll {
      from { transform: translateX(0); }
      to   { transform: translateX(-50%); }
    }
    @keyframes shineSweep {
      0% { left: -150%; }
      100% { left: 150%; }
    }
    @keyframes tickerDotPulse {
      0%,100% { transform: scale(1); opacity: 1; }
      50%      { transform: scale(1.4); opacity: 0.6; }
    }

    /* ─── KEYFRAME ANIMATIONS ─── */

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulseDot {
      0%, 100% { box-shadow: 0 0 0 3px rgba(46,125,50,0.22); }
      50%       { box-shadow: 0 0 0 6px rgba(46,125,50,0.08); }
    }
    @keyframes glowPulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(255,140,0,0.4); }
      50%       { box-shadow: 0 0 0 12px rgba(255,140,0,0); }
    }
    @keyframes leafFloat1 {
      0%   { transform: translate(0, 0) rotate(0deg); }
      33%  { transform: translate(6px, -12px) rotate(8deg); }
      66%  { transform: translate(-4px, -6px) rotate(-4deg); }
      100% { transform: translate(8px, -18px) rotate(12deg); }
    }
    @keyframes leafFloat2 {
      0%   { transform: translate(0, 0) rotate(0deg); }
      33%  { transform: translate(-8px, -14px) rotate(-10deg); }
      66%  { transform: translate(5px, -8px) rotate(5deg); }
      100% { transform: translate(-10px, -20px) rotate(-14deg); }
    }
    @keyframes leafFloat3 {
      0%   { transform: translate(0, 0) rotate(0deg) scale(1); }
      50%  { transform: translate(10px, -10px) rotate(6deg) scale(1.05); }
      100% { transform: translate(-5px, -18px) rotate(-8deg) scale(0.95); }
    }
    /* ─── PAGE PRELOADER ANIMATION ─── */
    #mn-page-preloader {
      position: fixed; inset: 0; z-index: 99999;
      background: #09090f;
      display: flex; align-items: center; justify-content: center;
      transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.4s;
    }
    #mn-page-preloader.loaded {
      opacity: 0; visibility: hidden; pointer-events: none;
    }
    .preloader-inner {
      display: flex; flex-direction: column; align-items: center; gap: 18px; text-align: center;
    }
    .preloader-logo-wrap {
      position: relative; width: 76px; height: 76px;
    }
    .preloader-logo-glow {
      position: absolute; inset: -12px; border-radius: 50%;
      background: radial-gradient(circle, rgba(249,115,22,0.65) 0%, transparent 70%);
      animation: mnPulseRing 1.8s ease-in-out infinite;
    }
    .preloader-logo-icon {
      position: relative; z-index: 2; width: 76px; height: 76px;
      background: linear-gradient(135deg, #f97316, #fb923c);
      border-radius: 22px; display: flex; align-items: center; justify-content: center;
      box-shadow: 0 12px 32px rgba(249,115,22,0.45);
      animation: mnLogoFloat 2.2s ease-in-out infinite;
    }
    .preloader-logo-icon img { width: 44px; height: 44px; object-fit: contain; }
    .preloader-title {
      font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 900; color: #f1f5f9;
      letter-spacing: -0.5px;
    }
    .preloader-title span { color: #f97316; }
    .preloader-bar {
      width: 150px; height: 4px; background: rgba(255,255,255,0.08);
      border-radius: 99px; overflow: hidden; position: relative;
    }
    .preloader-progress {
      height: 100%; width: 50%; background: linear-gradient(90deg, #f97316, #fb923c);
      border-radius: 99px; animation: mnBarFill 1.4s ease-in-out infinite alternate;
    }
    @keyframes mnPulseRing {
      0%, 100% { transform: scale(0.9); opacity: 0.4; }
      50% { transform: scale(1.35); opacity: 0.85; }
    }
    @keyframes mnLogoFloat {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-8px) rotate(4deg); }
    }
    @keyframes mnBarFill {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(200%); }
    }
  </style>
</head>
<body>
  <!-- Page Preloader Overlay -->
  <div id="mn-page-preloader">
    <div class="preloader-inner">
      <div class="preloader-logo-wrap">
        <div class="preloader-logo-glow"></div>
        <div class="preloader-logo-icon">
          <img src="assets/img/logo.png" alt="Mango Numbers">
        </div>
      </div>
      <div class="preloader-title">Mango<span>Number</span></div>
      <div class="preloader-bar">
        <div class="preloader-progress"></div>
      </div>
    </div>
  </div>
  <script>
    window.addEventListener('load', function() {
      const loader = document.getElementById('mn-page-preloader');
      if (loader) {
        loader.classList.add('loaded');
        setTimeout(() => loader.remove(), 450);
      }
    });
  </script>

  <!-- Background Blur Glows -->
  <div class="hero-bg-glow" aria-hidden="true"></div>

  <!-- ─── NAVBAR (shared across all pages) ─── -->
  <nav class="gm-nav-wrap" id="navbar">
    <div class="gm-nav-pill" id="navpill">
      <a class="gm-logo" href="index.php">
        <img src="assets/img/logo.png" alt="Mango Number Logo" style="width: 34px; height: 34px; object-fit: contain; border-radius: 10px; box-shadow: 0 4px 14px rgba(255,140,0,0.40);">
        <span>Mango <b>Number</b></span>
      </a>
      <ul class="gm-nav-links" id="navLinks">
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
        <?php if (is_logged_in()): ?>
            <a class="gm-btn-nav-cta" href="<?php echo is_admin() ? 'admin.php' : 'dashboard.php'; ?>">Dashboard</a>
            <a class="gm-nav-auth-link" href="logout.php" style="color: rgba(26,26,46,0.68); font-size: 14.5px; font-weight: 500; text-decoration: none; padding: 7px 14px; border-radius: 6px; display: inline-block;">Log Out</a>
        <?php else: ?>
            <a class="gm-nav-auth-link" href="login.php" style="color: rgba(26,26,46,0.68); font-size: 14.5px; font-weight: 600; text-decoration: none; padding: 7px 14px; border-radius: 6px; display: inline-block; cursor: pointer; transition: color 0.2s;">Login</a>
            <a class="gm-btn-nav-cta" href="register.php">Sign Up</a>
        <?php endif; ?>
        <button class="gm-hamburger" onclick="toggleMobileMenu()" aria-label="Menu">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1A1208" stroke-width="2" stroke-linecap="round"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
      </div>
    </div>
  </nav>

  <!-- ─── MOBILE MENU ─── -->
  <div class="mobile-menu" id="mobileMenu">
    <button class="mobile-close" onclick="toggleMobileMenu()">✕</button>
    <a href="index.php" onclick="toggleMobileMenu()">Home</a>
    <a href="#how-it-works" onclick="toggleMobileMenu()">How It Works</a>
    <a href="#faq" onclick="toggleMobileMenu()">FAQ</a>
    <?php if (is_logged_in()): ?>
        <a href="<?php echo is_admin() ? 'admin.php' : 'dashboard.php'; ?>" onclick="toggleMobileMenu()">Dashboard</a>
        <a href="logout.php" onclick="toggleMobileMenu()">Log Out</a>
    <?php else: ?>
        <a href="login.php" onclick="toggleMobileMenu()">Login</a>
        <a class="gm-btn-nav-cta" href="register.php" onclick="toggleMobileMenu()">Sign Up</a>
    <?php endif; ?>
  </div>

  <!-- ─── HERO SECTION ─── -->
  <div class="hero-container">

    <!-- L1: GRADIENT ORBS -->
    <div class="hero-orb hero-orb-tl" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-tr" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-bc" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-mid" aria-hidden="true"></div>

    <!-- L2: NOISE TEXTURE -->
    <div aria-hidden="true" style="position:absolute;inset:0;z-index:1;pointer-events:none;background-image:url('data:image/svg+xml,%3Csvg viewBox=%220 0 256 256%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22n%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23n)%22 opacity=%221%22/%3E%3C/svg%3E');opacity:0.025;mix-blend-mode:multiply"></div>

    <!-- L3: WORLD MAP SILHOUETTE -->
    <svg class="hero-worldmap" aria-hidden="true" viewBox="0 0 2000 1001" fill="white" xmlns="http://www.w3.org/2000/svg">
      <!-- North America -->
      <path d="M 310,80 L 400,65 L 480,72 L 540,90 L 570,118 L 580,155 L 560,195 L 530,230 L 490,258 L 450,272 L 400,268 L 360,252 L 325,228 L 300,200 L 285,170 L 285,135 Z"/>
      <!-- Greenland -->
      <path d="M 445,30 L 510,18 L 565,28 L 578,55 L 560,78 L 520,88 L 470,82 L 448,60 Z"/>
      <!-- South America -->
      <path d="M 390,298 L 462,285 L 510,302 L 538,338 L 548,380 L 542,422 L 525,465 L 495,502 L 460,525 L 425,518 L 398,492 L 384,455 L 382,412 L 385,368 Z"/>
      <!-- Caribbean -->
      <path d="M 540,252 L 560,248 L 568,256 L 560,264 L 546,262 Z"/>
      <!-- UK/Ireland -->
      <path d="M 618,102 L 638,95 L 652,104 L 648,122 L 632,128 L 618,118 Z"/>
      <!-- Europe -->
      <path d="M 658,88 L 738,78 L 790,90 L 820,112 L 818,140 L 800,158 L 772,168 L 742,165 L 712,152 L 688,138 L 668,120 Z"/>
      <!-- Scandinavia -->
      <path d="M 700,60 L 730,52 L 760,58 L 775,78 L 765,95 L 740,100 L 715,90 L 702,75 Z"/>
      <!-- Africa -->
      <path d="M 660,195 L 738,182 L 792,195 L 828,225 L 845,268 L 850,315 L 840,362 L 815,408 L 780,445 L 742,468 L 700,462 L 660,440 L 632,405 L 618,360 L 615,312 L 622,265 L 640,228 Z"/>
      <!-- Madagascar -->
      <path d="M 840,398 L 858,388 L 870,405 L 865,428 L 848,435 L 836,420 Z"/>
      <!-- Middle East -->
      <path d="M 840,168 L 890,158 L 925,168 L 940,190 L 932,215 L 908,228 L 878,222 L 852,205 L 842,188 Z"/>
      <!-- India -->
      <path d="M 950,198 L 1005,190 L 1042,210 L 1055,242 L 1045,278 L 1020,302 L 985,312 L 958,298 L 940,268 L 938,235 Z"/>
      <!-- Sri Lanka -->
      <path d="M 1008,318 L 1020,312 L 1028,322 L 1022,335 L 1010,330 Z"/>
      <!-- Southeast Asia -->
      <path d="M 1100,225 L 1160,215 L 1200,228 L 1215,252 L 1205,278 L 1178,292 L 1148,285 L 1118,268 L 1102,248 Z"/>
      <!-- Asia (main) -->
      <path d="M 838,78 L 980,60 L 1100,68 L 1200,85 L 1280,108 L 1320,138 L 1308,168 L 1272,188 L 1222,198 L 1165,195 L 1105,185 L 1052,178 L 995,168 L 945,155 L 900,138 L 862,118 Z"/>
      <!-- Japan -->
      <path d="M 1265,128 L 1290,118 L 1308,128 L 1305,148 L 1285,155 L 1265,142 Z"/>
      <!-- South Korea -->
      <path d="M 1238,148 L 1258,142 L 1268,152 L 1262,165 L 1244,165 Z"/>
      <!-- Philippines -->
      <path d="M 1225,268 L 1242,262 L 1252,272 L 1248,288 L 1232,290 L 1222,278 Z"/>
      <!-- Australia -->
      <path d="M 1138,418 L 1228,398 L 1318,408 L 1382,438 L 1402,478 L 1388,522 L 1355,555 L 1305,572 L 1248,568 L 1195,548 L 1152,515 L 1128,472 L 1128,435 Z"/>
      <!-- New Zealand -->
      <path d="M 1408,548 L 1428,538 L 1440,552 L 1435,572 L 1418,578 L 1408,562 Z"/>
      <!-- Russia (simplified) -->
      <path d="M 838,48 L 980,28 L 1180,18 L 1380,28 L 1500,48 L 1520,68 L 1480,80 L 1350,78 L 1180,62 L 1000,52 L 865,58 Z"/>
      <!-- Canada -->
      <path d="M 200,45 L 380,28 L 450,38 L 478,58 L 465,78 L 420,85 L 350,82 L 285,75 L 228,62 Z"/>
      <!-- Alaska -->
      <path d="M 108,68 L 165,55 L 205,62 L 215,82 L 195,98 L 158,100 L 122,88 Z"/>
    </svg>

    <!-- L4: PLATFORM WATERMARKS (huge blurred brand logos) -->
    <svg class="hero-watermark hero-wm-telegram" aria-hidden="true" viewBox="0 0 240 240">
      <circle cx="120" cy="120" r="120" fill="rgba(0,136,204,0.9)"/>
      <path d="M 50,117 L 175,68 L 152,177 L 115,148 L 100,162 L 96,138 L 158,88 L 82,132 Z" fill="white"/>
    </svg>
    <svg class="hero-watermark hero-wm-whatsapp" aria-hidden="true" viewBox="0 0 240 240">
      <circle cx="120" cy="120" r="120" fill="rgba(37,211,102,0.9)"/>
      <path d="M 120,48 C 80,48 48,80 48,120 C 48,133 52,145 58,155 L 44,196 L 87,182 C 97,187 108,190 120,190 C 160,190 192,158 192,120 C 192,80 160,48 120,48 Z M 152,140 C 150,145 143,150 137,151 C 133,152 128,153 105,143 C 78,132 62,105 61,103 C 60,101 50,88 50,75 C 50,62 57,56 60,53 C 63,50 66,50 68,50 C 70,50 72,50 74,50 L 79,62 C 80,65 79,68 77,71 L 72,77 C 78,89 88,99 100,106 L 107,100 C 110,98 113,98 116,99 L 128,104 C 131,106 133,110 132,114 C 131,120 154,135 152,140 Z" fill="white"/>
    </svg>

    <!-- L5: FLOATING COUNTRY FLAGS -->
    <div class="hero-flag hf1" aria-hidden="true">🇺🇸</div>
    <div class="hero-flag hf2" aria-hidden="true">🇬🇧</div>
    <div class="hero-flag hf3" aria-hidden="true">🇨🇦</div>
    <div class="hero-flag hf4" aria-hidden="true">🇦🇺</div>
    <div class="hero-flag hf5" aria-hidden="true">🇩🇪</div>
    <div class="hero-flag hf6" aria-hidden="true">🇫🇷</div>

    <!-- L6: NETWORK CONNECTION GRAPHIC -->
    <svg class="hero-network" aria-hidden="true" viewBox="0 0 1440 800" preserveAspectRatio="xMidYMid slice">
      <defs>
        <filter id="nodeGlow" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur stdDeviation="4" result="blur"/>
          <feComposite in="SourceGraphic" in2="blur" operator="over"/>
        </filter>
      </defs>
      <!-- USA → India -->
      <path d="M 215,320 Q 720,160 1060,420" stroke="rgba(255,140,0,0.28)" stroke-width="1.2" fill="none" stroke-dasharray="6 5">
        <animate attributeName="stroke-dashoffset" from="0" to="-110" dur="9s" repeatCount="indefinite"/>
      </path>
      <!-- UK → India -->
      <path d="M 470,215 Q 740,140 1060,420" stroke="rgba(0,136,204,0.22)" stroke-width="1" fill="none" stroke-dasharray="5 5">
        <animate attributeName="stroke-dashoffset" from="0" to="-110" dur="11s" repeatCount="indefinite"/>
      </path>
      <!-- Canada → India -->
      <path d="M 300,175 Q 660,110 1060,420" stroke="rgba(37,211,102,0.20)" stroke-width="1" fill="none" stroke-dasharray="5 6">
        <animate attributeName="stroke-dashoffset" from="0" to="-110" dur="13s" repeatCount="indefinite"/>
      </path>
      <!-- Australia → India -->
      <path d="M 1240,580 Q 1100,500 1060,420" stroke="rgba(255,193,7,0.18)" stroke-width="1" fill="none" stroke-dasharray="4 5">
        <animate attributeName="stroke-dashoffset" from="0" to="-90" dur="10s" repeatCount="indefinite"/>
      </path>
      <!-- Germany → India -->
      <path d="M 700,180 Q 880,200 1060,420" stroke="rgba(26,18,8,0.12)" stroke-width="0.8" fill="none" stroke-dasharray="4 4">
        <animate attributeName="stroke-dashoffset" from="0" to="-80" dur="14s" repeatCount="indefinite"/>
      </path>
      <!-- Nodes -->
      <circle cx="215" cy="320" r="5" fill="rgba(255,140,0,0.55)" filter="url(#nodeGlow)">
        <animate attributeName="opacity" values="0.4;0.8;0.4" dur="3s" repeatCount="indefinite"/>
      </circle>
      <circle cx="470" cy="215" r="4" fill="rgba(0,136,204,0.55)">
        <animate attributeName="opacity" values="0.35;0.75;0.35" dur="4s" repeatCount="indefinite"/>
      </circle>
      <circle cx="300" cy="175" r="4" fill="rgba(37,211,102,0.55)">
        <animate attributeName="opacity" values="0.4;0.8;0.4" dur="5s" repeatCount="indefinite"/>
      </circle>
      <circle cx="1240" cy="580" r="4" fill="rgba(255,193,7,0.55)">
        <animate attributeName="opacity" values="0.3;0.7;0.3" dur="4.5s" repeatCount="indefinite"/>
      </circle>
      <circle cx="700" cy="180" r="3.5" fill="rgba(26,18,8,0.30)">
        <animate attributeName="opacity" values="0.3;0.6;0.3" dur="6s" repeatCount="indefinite"/>
      </circle>
      <!-- India hub (pulsing) -->
      <circle cx="1060" cy="420" r="8" fill="rgba(255,140,0,0.4)" filter="url(#nodeGlow)">
        <animate attributeName="r" values="6;10;6" dur="3s" repeatCount="indefinite"/>
        <animate attributeName="opacity" values="0.3;0.6;0.3" dur="3s" repeatCount="indefinite"/>
      </circle>
      <circle cx="1060" cy="420" r="3" fill="rgba(255,140,0,0.9)"/>
      <!-- Labels -->
      <text x="182" y="314" font-size="9.5" fill="rgba(26,18,8,0.32)" font-family="Inter,sans-serif" font-weight="700">USA</text>
      <text x="438" y="209" font-size="9.5" fill="rgba(26,18,8,0.32)" font-family="Inter,sans-serif" font-weight="700">UK</text>
      <text x="268" y="169" font-size="9.5" fill="rgba(26,18,8,0.32)" font-family="Inter,sans-serif" font-weight="700">CAN</text>
      <text x="1066" y="415" font-size="9.5" fill="rgba(26,18,8,0.32)" font-family="Inter,sans-serif" font-weight="700">IND</text>
    </svg>

    <!-- L7: FLOATING GLASS CARDS -->
    <div class="hero-glass-card hgc-1" aria-hidden="true">
      <div class="hgc-topline"></div>
      <div class="hgc-country">🇺🇸 USA Number</div>
      <div class="hgc-service">WhatsApp Available</div>
      <div class="hgc-status"><span class="hgc-dot"></span> Instant Delivery</div>
    </div>
    <div class="hero-glass-card hgc-2" aria-hidden="true">
      <div class="hgc-topline"></div>
      <div class="hgc-country">🇬🇧 UK Number</div>
      <div class="hgc-service">Telegram Available</div>
      <div class="hgc-status"><span class="hgc-dot"></span> OTP Ready</div>
    </div>
    <div class="hero-glass-card hgc-3" aria-hidden="true">
      <div class="hgc-topline"></div>
      <div class="hgc-country">🇨🇦 Canada Number</div>
      <div class="hgc-service">Verified Activation</div>
      <div class="hgc-status"><span class="hgc-dot"></span> Live Stock</div>
    </div>
    <div class="hero-glass-card hgc-4" aria-hidden="true">
      <div class="hgc-topline"></div>
      <div class="hgc-country">🇦🇺 Australia</div>
      <div class="hgc-service">Telegram Number</div>
      <div class="hgc-status"><span class="hgc-dot"></span> In Stock</div>
    </div>

    <!-- Headline glow -->
    <div class="hero-headline-glow" aria-hidden="true"></div>

    <!-- Bottom fade gradient -->
    <div aria-hidden="true" style="position:absolute;bottom:0;left:0;right:0;height:200px;background:linear-gradient(to bottom,transparent,rgba(255,253,249,0.82));z-index:4;pointer-events:none;"></div>

    <!-- ─── EXISTING HERO CONTENT (unchanged) ─── -->
    <main class="hero-main">
      <div class="gm-badge" role="note" onclick="location.href='dashboard.php'">
        <span class="badge-dot" aria-hidden="true"></span>
        <span class="badge-text">Secure &amp; Instant OTP Verification</span>
      </div>
      <h1 class="hero-headline" style="max-width: 1000px;">
        Premium
        <span class="hero-telegram-pill">
          <svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
          Telegram
        </span>
        and
        <span class="hero-whatsapp-pill">
          <svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
          WhatsApp
        </span>
        <span class="hero-hl">Numbers.</span>
      </h1>
      <p class="hero-sub">Instant delivery of virtual numbers for Telegram and WhatsApp. Get secure OTP verification from 20+ countries instantly.</p>

      <div class="hero-ctas">
        <a class="btn-primary" href="register.php">
          <span>Create Account 🥭</span>
          <span style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;flex-shrink:0" aria-hidden="true">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>
        <a class="btn-secondary" href="login.php">
          Log In
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
        </a>
      </div>

      <div class="hero-proof" aria-label="Social proof">
        <div class="avatars" aria-hidden="true">
          <div class="avatar" style="background:linear-gradient(135deg,#667eea,#764ba2)">A</div>
          <div class="avatar" style="background:linear-gradient(135deg,#f093fb,#f5576c)">S</div>
          <div class="avatar" style="background:linear-gradient(135deg,#4facfe,#00f2fe)">M</div>
          <div class="avatar" style="background:linear-gradient(135deg,#43e97b,#38f9d7)">J</div>
          <div class="avatar" style="background:linear-gradient(135deg,#fa709a,#fee140)">R</div>
        </div>
        <div class="divider-v" aria-hidden="true"></div>
        <div class="proof-text">
          <div class="stars" aria-label="5 star rating">★★★★★</div>
          <p class="proof-sub"><strong>1,200+</strong> verified customers across 20+ countries</p>
        </div>
      </div>

      <!-- Trust Banners -->
      <div class="trust-banners" aria-label="Key features">
        <div class="trust-banner">
          <span class="trust-banner-icon">🔒</span>
          <div class="trust-banner-content">
            <h4>100% Anonymous</h4>
            <p>No phone or KYC required</p>
          </div>
        </div>
        <div class="trust-banner">
          <span class="trust-banner-icon">⚡</span>
          <div class="trust-banner-content">
            <h4>Instant Delivery</h4>
            <p>OTPs delivered in seconds</p>
          </div>
        </div>
        <div class="trust-banner">
          <span class="trust-banner-icon">💳</span>
          <div class="trust-banner-content">
            <h4>UPI &amp; USDT</h4>
            <p>Indian UPI and TRC-20 crypto</p>
          </div>
        </div>
      </div>
    </main>
  </div><!-- /hero-container -->

  <!-- ─── LIVE ACTIVITY TICKER ─── -->
  <div class="activity-ticker" role="region" aria-label="Live purchase activity">
    <div class="ticker-label"><span class="ticker-label-dot"></span> Live</div>
    <div class="ticker-fade-r"></div>
    <div class="ticker-track" id="tickerTrack">
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇺🇸</span><span><span class="ticker-strong">USA Number</span> purchased</span><span class="ticker-ts">just now</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇬🇧</span><span><span class="ticker-strong">UK Telegram</span> activated</span><span class="ticker-ts">25s ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇨🇦</span><span><span class="ticker-strong">Canada Number</span> delivered</span><span class="ticker-ts">1 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇦🇺</span><span><span class="ticker-strong">Australia OTP</span> verified</span><span class="ticker-ts">2 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇩🇪</span><span><span class="ticker-strong">Germany WhatsApp</span> purchased</span><span class="ticker-ts">3 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇫🇷</span><span><span class="ticker-strong">France Number</span> activated</span><span class="ticker-ts">4 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇷🇺</span><span><span class="ticker-strong">Russia Telegram</span> number sold</span><span class="ticker-ts">5 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇮🇳</span><span><span class="ticker-strong">India Number</span> purchased</span><span class="ticker-ts">7 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇻🇳</span><span><span class="ticker-strong">Vietnam Number</span> delivered</span><span class="ticker-ts">9 min ago</span></div>
      <!-- duplicate set for seamless loop -->
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇺🇸</span><span><span class="ticker-strong">USA Number</span> purchased</span><span class="ticker-ts">just now</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇬🇧</span><span><span class="ticker-strong">UK Telegram</span> activated</span><span class="ticker-ts">25s ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇨🇦</span><span><span class="ticker-strong">Canada Number</span> delivered</span><span class="ticker-ts">1 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇦🇺</span><span><span class="ticker-strong">Australia OTP</span> verified</span><span class="ticker-ts">2 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇩🇪</span><span><span class="ticker-strong">Germany WhatsApp</span> purchased</span><span class="ticker-ts">3 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇫🇷</span><span><span class="ticker-strong">France Number</span> activated</span><span class="ticker-ts">4 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇷🇺</span><span><span class="ticker-strong">Russia Telegram</span> number sold</span><span class="ticker-ts">5 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇮🇳</span><span><span class="ticker-strong">India Number</span> purchased</span><span class="ticker-ts">7 min ago</span></div>
      <div class="ticker-item"><span class="ticker-dot"></span><span class="ticker-flag">🇻🇳</span><span><span class="ticker-strong">Vietnam Number</span> delivered</span><span class="ticker-ts">9 min ago</span></div>
    </div>
  </div>


  <!-- ─── ANIMATED STATS CARD ─── -->
  <div class="stats-section">
    <div class="stats-card" id="statsCard">
      <div class="stat-item">
        <div class="stat-icon">🌍</div>
        <div class="stat-num" data-target="20" data-suffix="+">0</div>
        <div class="stat-label">Countries Available</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon">📱</div>
        <div class="stat-num" data-target="1200" data-suffix="+">0</div>
        <div class="stat-label">Happy Customers</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon">⚡</div>
        <div class="stat-num" data-target="99" data-suffix="%">0</div>
        <div class="stat-label">Success Rate</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon">🕐</div>
        <div class="stat-num" data-target="15" data-suffix="min">0</div>
        <div class="stat-label">Avg. Delivery Time</div>
      </div>
    </div>
  </div>

  <!-- ─── SYSTEM SETUP ALERT (If DB is missing) ─── -->
  <?php if ($db_error): ?>
      <div class="setup-alert container">
          <h3>⚠️ System Database Pending Setup</h3>
          <p>The system database is not fully configured yet. If you are the administrator, please click <a href="db_init.php"><strong>Initialize Database</strong></a> to seed default virtual number catalog records and default client/admin accounts.</p>
      </div>
  <?php endif; ?>

  <!-- ─── CARD EXCELLENCE ADVANTAGES ─── -->
  <section class="section" style="background: rgba(255, 255, 255, 0.4)">
    <div class="section-inner">
      <div class="text-center">
        <div class="section-tag">⚡ SaaS Advantage</div>
        <h2 class="section-title">SMS Services That Drive Speed & Privacy</h2>
        <p class="section-sub centered">We don't make you wait. Our manual UPI verifier team provides fully-active secure virtual numbers and deliver SMS OTP codes within minutes.</p>
      </div>
      <div class="grid-3">
        <!-- Telegram OTPs (Orange Theme Card) -->
        <div class="card" style="background: linear-gradient(135deg, #FFF9F2 0%, #FFEEDC 100%); border: 1.5px solid rgba(255, 140, 0, 0.25);">
          <div class="card-icon" style="background: linear-gradient(135deg, #FF8C00, #FFA726); box-shadow: 0 4px 14px rgba(255,140,0,0.3);">
            <img src="assets/img/telegram_icon.png" alt="Telegram OTP Verification Icon" style="width: 32px; height: 32px; object-fit: contain;">
          </div>
          <h3 style="color: #1A1208; font-weight: 800;">Telegram OTPs</h3>
          <p style="color: rgba(26,18,8,0.72); line-height: 1.65;">High-quality fresh numbers to create and verify Telegram channels, bots, and personal profiles instantly.</p>
        </div>

        <!-- WhatsApp Verification (Deep Green Theme Card with High Contrast Text) -->
        <div class="card" style="background: linear-gradient(135deg, #0e3020 0%, #17422f 100%); border: 1.5px solid rgba(46, 125, 50, 0.4); box-shadow: 0 12px 30px rgba(15,54,37,0.15);">
          <div class="card-icon" style="background: linear-gradient(135deg, #2E7D32, #43A047); box-shadow: 0 4px 14px rgba(46,125,50,0.3);">
            <img src="assets/img/whatsapp_icon.png" alt="WhatsApp Account Verification Icon" style="width: 32px; height: 32px; object-fit: contain;">
          </div>
          <h3 style="color: #FFFDF9; font-weight: 800;">WhatsApp Verification</h3>
          <p style="color: rgba(255, 255, 255, 0.88); line-height: 1.65;">Avoid exposing your private mobile number. Establish secure secondary WhatsApp accounts seamlessly.</p>
        </div>

        <!-- USDT & UPI Payments (Orange Theme Card) -->
        <div class="card" style="background: linear-gradient(135deg, #FFF9F2 0%, #FFEEDC 100%); border: 1.5px solid rgba(255, 140, 0, 0.25);">
          <div class="card-icon" style="background: linear-gradient(135deg, #2E7D32, #43A047); box-shadow: 0 4px 14px rgba(46,125,50,0.25);">
            <img src="assets/img/payment_icon.png" alt="USDT UPI Payments Icon" style="width: 32px; height: 32px; object-fit: contain;">
          </div>
          <h3 style="color: #1A1208; font-weight: 800;">USDT & UPI Payments</h3>
          <p style="color: rgba(26,18,8,0.72); line-height: 1.65;">We support all standard UPI payments like Paytm / PhonePe and USDT TRC-20 crypto for deposit privacy.</p>
        </div>
      </div>
    </div>
  </section>


  <!-- ─── FEATURES PILLARS GRID ─── -->
  <section class="section" style="background: rgba(255, 255, 255, 0.4); border-top: 1px solid rgba(255,255,255,0.7);">
    <div class="section-inner">
      <div class="text-center">
        <div class="section-tag">💎 Premium Pillars</div>
        <h2 class="section-title">Engineered For Dynamic Scale</h2>
        <p class="section-sub centered">Discover how our infrastructure protects your operations and delivers maximum efficiency.</p>
      </div>

      <div class="grid-4">
        <!-- 1. Total Anonymity -->
        <div class="card" style="padding: 30px 24px; border-radius: 24px; border: 1.5px solid rgba(46, 125, 50, 0.15);">
          <div class="card-icon" style="background: rgba(46,125,50,0.08); color: #2E7D32;">🔒</div>
          <h3 style="font-size: 16.5px; font-weight: 700; margin-bottom: 8px;">Total Anonymity</h3>
          <p style="font-size: 13.5px; color: rgba(26,18,8,0.6); line-height: 1.6;">No real mobile number required. Generate dynamic verifications without leaving any traces or linking logs.</p>
        </div>

        <!-- 2. Manual Audit Desk -->
        <div class="card" style="padding: 30px 24px; border-radius: 24px; border: 1.5px solid rgba(46, 125, 50, 0.15);">
          <div class="card-icon" style="background: rgba(46,125,50,0.08); color: #2E7D32;">⚡</div>
          <h3 style="font-size: 16.5px; font-weight: 700; margin-bottom: 8px;">Manual Audit Desk</h3>
          <p style="font-size: 13.5px; color: rgba(26,18,8,0.6); line-height: 1.6;">Our live operators verify Paytm/GPay UPI receipts and USDT deposits within seconds, keeping the queue moving fast.</p>
        </div>

        <!-- 3. Fresh Stock Cycles -->
        <div class="card" style="padding: 30px 24px; border-radius: 24px; border: 1.5px solid rgba(46, 125, 50, 0.15);">
          <div class="card-icon" style="background: rgba(46,125,50,0.08); color: #2E7D32;">🔄</div>
          <h3 style="font-size: 16.5px; font-weight: 700; margin-bottom: 8px;">Fresh Stock Cycles</h3>
          <p style="font-size: 13.5px; color: rgba(26,18,8,0.6); line-height: 1.6;">Stale numbers are instantly removed from the catalog daily, ensuring high success rates when creating Telegram or WhatsApp channels.</p>
        </div>

        <!-- 4. Active Support Portal -->
        <div class="card" style="padding: 30px 24px; border-radius: 24px; border: 1.5px solid rgba(46, 125, 50, 0.15);">
          <div class="card-icon" style="background: rgba(46,125,50,0.08); color: #2E7D32;">🎫</div>
          <h3 style="font-size: 16.5px; font-weight: 700; margin-bottom: 8px;">Active Support Portal</h3>
          <p style="font-size: 13.5px; color: rgba(26,18,8,0.6); line-height: 1.6;">File help tickets directly inside your dashboard. Our resolution operators address complaints and troubleshoot issues instantly.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── HOW IT WORKS STEPS ─── -->
  <section class="section" id="how-it-works" style="background: rgba(255, 255, 255, 0.5); border-top: 1px solid rgba(255,255,255,0.9); border-bottom: 1px solid rgba(255,255,255,0.9);">
    <div class="section-inner">
      <div class="text-center">
        <div class="section-tag">🛠️ Quick Setup</div>
        <h2 class="section-title">How It Works</h2>
        <p class="section-sub centered">Three simple steps to secure your first virtual SMS verification code.</p>
      </div>

      <div class="process-steps">
        <div class="process-step">
          <div class="step-num">1</div>
          <h3>1. Choose Number</h3>
          <p>Register/login and select an active virtual country number according to your project requirements.</p>
        </div>
        <div class="process-step">
          <div class="step-num">2</div>
          <h3>2. UPI/USDT Deposit</h3>
          <p>Scan our Paytm UPI QR code, insert the required funds, and submit the 12-digit transaction UTR number.</p>
        </div>
        <div class="process-step">
          <div class="step-num">3</div>
          <h3>3. Receive OTP Code</h3>
          <p>Submit your screenshot. Our verification operators approve the transfer and release the OTP straight to your dashboard.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── FAQ ACCORDIONS ─── -->
  <section class="section" id="faq">
    <div class="section-inner">
      <div class="text-center">
        <div class="section-tag">💬 Quick Help</div>
        <h2 class="section-title">Frequently Asked Questions</h2>
        <p class="section-sub centered">Everything you need to know about secure virtual SMS verification solutions.</p>
      </div>

      <div class="faq-list">
        <div class="faq-item">
          <div class="faq-q" onclick="toggleFaq(this)">
            <span>Are these virtual numbers safe for account creation?</span>
            <span class="faq-chevron">▼</span>
          </div>
          <div class="faq-a">
            Absolutely. Every virtual number seeded in our system is fully tested, isolated, fresh, and dedicated exclusively to Telegram or WhatsApp verifications.
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-q" onclick="toggleFaq(this)">
            <span>How long do verification approvals take?</span>
            <span class="faq-chevron">▼</span>
          </div>
          <div class="faq-a">
            Approval is incredibly fast. Once your screenshot or 12-digit transaction UTR number is uploaded, our active operator approves the ledger and populates your OTP code within minutes.
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-q" onclick="toggleFaq(this)">
            <span>What is the deposit discount offer?</span>
            <span class="faq-chevron">▼</span>
          </div>
          <div class="faq-a">
            If you deposit ₹1000 or more in a single transaction today, your profile tier is automatically promoted to VIP status, granting you massive discounts on all future verification stocks.
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── FINAL CALL TO ACTION ─── -->
  <section class="section">
    <div class="cta-section">
      <div class="cta-inner">
        <h2>Scale Your Accounts <span>Instantly.</span></h2>
        <p>Get secure, anonymous virtual numbers for WhatsApp and Telegram verifications today.</p>
        <a class="btn-cta-big" href="register.php">Get Started Now 🥭</a>
        <div class="cta-trust">Used by over 120+ SaaS brands worldwide.</div>
      </div>
    </div>
  </section>

  <!-- ─── FOOTER ─── -->
  <footer class="footer">
    <div class="footer-inner">
      <div class="footer-grid">
        <div class="footer-brand">
          <a class="gm-logo" href="index.php">
            <img src="assets/img/logo.png" alt="Mango Number Logo" style="width: 32px; height: 32px; object-fit: contain; border-radius: 8px;">
            <span style="color: #ffffff;">Mango <b>Number</b></span>
          </a>
          <p>Premium, high-speed virtual SMS OTP verification service. Secure, isolated numbers from over 20+ countries worldwide.</p>
        </div>
        <div class="footer-col">
          <h4>Platform Navigation</h4>
          <a href="dashboard.php">Buy Virtual Numbers</a>
          <a href="#how-it-works">How It Works</a>
          <a href="login.php">User Login</a>
          <a href="register.php">User Registration</a>
        </div>
        <div class="footer-col">
          <h4>Privacy & Deposits</h4>
          <span style="display:block; font-size:13.5px; color:rgba(255,255,255,0.45); margin-bottom:12px;">Instant Paytm / PhonePe UPI Ledger</span>
          <span style="display:block; font-size:13.5px; color:rgba(255,255,255,0.45); margin-bottom:12px;">Secure USDT TRC-20 transfers</span>
          <span style="display:block; font-size:13.5px; color:rgba(255,255,255,0.45); margin-bottom:12px;">Isolated Number privacy logs</span>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Mango Number. All rights reserved.</p>
        <p>Premium SaaS SMS Verification Service</p>
      </div>
    </div>
  </footer>

  <script>
    // Tab filtering catalog switching
    function switchTab(service, btn) {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.catalog-panel').forEach(panel => panel.classList.remove('active'));
      
      const activeBtn = btn || (event ? event.currentTarget : null);
      if (activeBtn) activeBtn.classList.add('active');
      
      var targetPanel = document.getElementById('panel-' + service);
      if (targetPanel) {
        targetPanel.classList.add('active');
      }

      // Clear search box on tab switch to reset visibility
      var inp = document.getElementById('countrySearchInput');
      if (inp) inp.value = '';
      var clearBtn = document.getElementById('searchClearBtn');
      if (clearBtn) clearBtn.style.display = 'none';
      filterCountryCatalog();
    }

    // Dynamic Country Search Filter Logic
    function filterCountryCatalog() {
      var query = document.getElementById('countrySearchInput') ? document.getElementById('countrySearchInput').value.toLowerCase().trim() : '';
      var clearBtn = document.getElementById('searchClearBtn');

      // Show/hide clear button
      if (clearBtn) clearBtn.style.display = query.length > 0 ? 'block' : 'none';

      var panels = document.querySelectorAll('.catalog-panel');
      
      panels.forEach(function(panel) {
        if (!panel) return;
        var panelId = panel.id;
        
        var cards = panel.querySelectorAll('.catalog-card');
        var visibleCount = 0;
        
        cards.forEach(function(card) {
          var countryNameEl = card.querySelector('.country-name');
          var productNameEl = card.querySelector('.product-name');
          
          var countryText = countryNameEl ? countryNameEl.innerText.toLowerCase() : '';
          var productText = productNameEl ? productNameEl.innerText.toLowerCase() : '';
          
          if (countryText.includes(query) || productText.includes(query)) {
            card.style.display = 'flex';
            visibleCount++;
          } else {
            card.style.display = 'none';
          }
        });
        
        // Handle "No results found" dynamic message if no card matches query
        var noResultId = 'no-results-' + panelId;
        var existingNoResult = document.getElementById(noResultId);
        
        if (visibleCount === 0) {
          if (!existingNoResult) {
            var div = document.createElement('div');
            div.id = noResultId;
            div.style.gridColumn = '1/-1';
            div.style.textAlign = 'center';
            div.style.color = 'rgba(26,18,8,0.5)';
            div.style.padding = '40px 0';
            div.style.fontWeight = '500';
            div.innerText = 'No matching country found in active stock catalog.';
            panel.appendChild(div);
          }
        } else {
          if (existingNoResult) {
            existingNoResult.remove();
          }
        }
      });
    }

    // Clear country search box and reset all cards
    function clearCountrySearch() {
      var inp = document.getElementById('countrySearchInput');
      if (inp) inp.value = '';
      filterCountryCatalog();
    }



    // Pure dynamic accordion FAQ handler
    function toggleFaq(el) {
      const parent = el.closest('.faq-item');
      const answer = parent.querySelector('.faq-a');
      const chevron = parent.querySelector('.faq-chevron');
      
      const isOpen = answer.classList.contains('open');
      
      // Reset all accordions first
      document.querySelectorAll('.faq-a').forEach(ans => ans.classList.remove('open'));
      document.querySelectorAll('.faq-chevron').forEach(chev => chev.classList.remove('open'));
      
      if (!isOpen) {
        answer.classList.add('open');
        chevron.classList.add('open');
      }
    }

    // Toggle responsive mobile sidebar menu
    function toggleMobileMenu() {
      const menu = document.getElementById('mobileMenu');
      menu.classList.toggle('open');
    }

    // Shadow on navbar scroll event
    window.addEventListener('scroll', () => {
      const nav = document.getElementById('navpill');
      if (window.scrollY > 20) {
        nav.classList.add('scrolled');
      } else {
        nav.classList.remove('scrolled');
      }
    });

    // Animated counter for stats on scroll into view
    function animateCounter(el) {
      const target = parseInt(el.getAttribute('data-target'), 10);
      const suffix = el.getAttribute('data-suffix') || '';
      const duration = 1800;
      const step = target / (duration / 16);
      let current = 0;
      const timer = setInterval(() => {
        current += step;
        if (current >= target) {
          current = target;
          clearInterval(timer);
        }
        el.textContent = (target >= 1000 ? Math.floor(current).toLocaleString() : Math.floor(current)) + suffix;
      }, 16);
    }

    // IntersectionObserver to trigger counter once in view
    const statsObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.querySelectorAll('.stat-num[data-target]').forEach(animateCounter);
          statsObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });

    const statsCard = document.getElementById('statsCard');
    if (statsCard) statsObserver.observe(statsCard);
  </script>
  <script src="assets/js/anti-devtools.js"></script>
</body>
</html>

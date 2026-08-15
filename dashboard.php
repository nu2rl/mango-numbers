<?php
/**
 * Mango Number - Premium Client Dashboard
 */

require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();
if (!$db) die("Database connection failed.");

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

$show_whatsapp_redirect_modal = $_SESSION['show_whatsapp_redirect_modal'] ?? false;
$show_whatsapp_url = $_SESSION['show_whatsapp_url'] ?? '';
$show_whatsapp_text = $_SESSION['show_whatsapp_text'] ?? '';
unset($_SESSION['show_whatsapp_redirect_modal'], $_SESSION['show_whatsapp_url'], $_SESSION['show_whatsapp_text']);

// Handle Purchase Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_purchase'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = 'CSRF verification failed.'; header("Location: dashboard.php?section=buy"); exit;
    }
    $catalog_id = (int)($_POST['catalog_id'] ?? 0);
    $utr_number = trim($_POST['utr_number'] ?? '');
    $stmt = $db->prepare("SELECT p.*, s.name as service_type FROM products p JOIN sections s ON p.section_id = s.id WHERE p.id = ? AND p.status = 'active'");
    $stmt->execute([$catalog_id]); $product = $stmt->fetch();
    if (!$product) {
        $stmt = $db->prepare("SELECT * FROM catalog WHERE id = ? AND status = 'active'");
        $stmt->execute([$catalog_id]); $product = $stmt->fetch();
    }
    $stock = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : (int)($product['stock'] ?? 0);
    if (!$product) { $_SESSION['error_msg'] = 'Invalid product.'; header("Location: dashboard.php?section=buy"); exit; }
    elseif ($stock <= 0) { $_SESSION['error_msg'] = 'Out of stock.'; header("Location: dashboard.php?section=buy"); exit; }
    elseif (empty($utr_number)) { $_SESSION['error_msg'] = 'UTR number is required.'; header("Location: dashboard.php?section=buy"); exit; }
    else {
        $chk_stmt = $db->prepare("SELECT id FROM purchases WHERE utr_number = ?"); $chk_stmt->execute([$utr_number]);
        if ($chk_stmt->fetch()) { $_SESSION['error_msg'] = 'This UTR has already been submitted.'; header("Location: dashboard.php?section=buy"); exit; }
        $screenshot_path = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['screenshot']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmp_name);
                finfo_close($finfo);
                if (in_array($mime, ['image/jpeg','image/jpg','image/png','image/x-png','image/webp'])) {
                    $new_name = 'ss_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    if (move_uploaded_file($tmp_name, UPLOAD_DIR . $new_name)) {
                        $screenshot_path = 'uploads/' . $new_name;
                    }
                }
            }
        }
        $insert = $db->prepare("INSERT INTO purchases (user_id, catalog_id, service_type, item_name, price_cost_inr, price_paid_inr, utr_number, screenshot_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        try {
            $insert->execute([$user_id,$product['id'],$product['service_type'],$product['name'],$product['price_cost_inr'],$product['price_inr'],$utr_number,$screenshot_path]);
            $_SESSION['success_msg'] = 'Payment submitted! Our team is verifying your UTR.';
            $_SESSION['show_whatsapp_redirect_modal'] = true;
            $_SESSION['show_whatsapp_url'] = "https://t.me/nu9rl";
            $_SESSION['show_whatsapp_text'] = "I have paid ".(int)$product['price_inr']." Rupees for ".$product['service_type']." (".$product['name'].") virtual number at ".date('d-M-Y h:i A').". My Transaction UTR is: ".$utr_number.". Please verify my order & provide virtual number/OTP.";
            header("Location: dashboard.php?section=history"); exit;
        } catch (PDOException $e) { $_SESSION['error_msg'] = 'Failed: '.$e->getMessage(); header("Location: dashboard.php?section=buy"); exit; }
    }
}

// Handle Support Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) { $_SESSION['error_msg'] = 'CSRF error.'; header("Location: dashboard.php?section=support"); exit; }
    $subject = trim($_POST['subject'] ?? ''); $message = trim($_POST['message'] ?? ''); $purchase_id = (int)($_POST['purchase_id'] ?? 0);
    if (empty($subject)||empty($message)) { $_SESSION['error_msg'] = 'Subject and message are required.'; header("Location: dashboard.php?section=support"); exit; }
    $ins = $db->prepare("INSERT INTO complaints (user_id, purchase_id, subject, message, status) VALUES (?, ?, ?, ?, 'open')");
    try {
        $ins->execute([$user_id, $purchase_id > 0 ? $purchase_id : null, $subject, $message]);
        
        // Send instant Telegram Bot Notification
        $user_name_txt = $_SESSION['username'] ?? 'User #'.$user_id;
        $notify_msg = "💬 <b>NEW SUPPORT TICKET FILED!</b>\n\n"
                    . "👤 <b>User:</b> <code>" . htmlspecialchars($user_name_txt) . "</code>\n"
                    . "📌 <b>Subject:</b> " . htmlspecialchars($subject) . "\n"
                    . "📝 <b>Message:</b> " . htmlspecialchars($message) . "\n"
                    . "⏰ <b>Time:</b> " . date('d M Y, h:i A');
        send_telegram_notification($notify_msg);

        $_SESSION['success_msg'] = 'Support ticket opened!'; header("Location: dashboard.php?section=support"); exit;
    }
    catch (PDOException $e) { $_SESSION['error_msg'] = 'Failed: '.$e->getMessage(); header("Location: dashboard.php?section=support"); exit; }
}

// Handle Support Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint_reply'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) { $_SESSION['error_msg'] = 'CSRF error.'; header("Location: dashboard.php?section=support"); exit; }
    $complaint_id = (int)$_POST['complaint_id']; $reply_message = trim($_POST['reply_message'] ?? '');
    if (empty($reply_message)) { $_SESSION['error_msg'] = 'Reply cannot be empty.'; header("Location: dashboard.php?section=support"); exit; }
    $chk = $db->prepare("SELECT id FROM complaints WHERE id = ? AND user_id = ?"); $chk->execute([$complaint_id, $user_id]);
    if (!$chk->fetch()) { $_SESSION['error_msg'] = 'Access denied.'; header("Location: dashboard.php?section=support"); exit; }
    $ins = $db->prepare("INSERT INTO complaint_messages (complaint_id, sender, message) VALUES (?, 'user', ?)");
    try {
        $ins->execute([$complaint_id, $reply_message]);
        $db->prepare("UPDATE complaints SET status = 'open' WHERE id = ?")->execute([$complaint_id]);
        
        // Send instant Telegram Bot Notification
        $user_name_txt = $_SESSION['username'] ?? 'User #'.$user_id;
        $notify_msg = "💬 <b>SUPPORT TICKET REPLY RECEIVED!</b>\n\n"
                    . "👤 <b>User:</b> <code>" . htmlspecialchars($user_name_txt) . "</code>\n"
                    . "🎟️ <b>Ticket ID:</b> #" . $complaint_id . "\n"
                    . "📝 <b>Reply:</b> " . htmlspecialchars($reply_message) . "\n"
                    . "⏰ <b>Time:</b> " . date('d M Y, h:i A');
        send_telegram_notification($notify_msg);

        $_SESSION['success_msg'] = 'Reply sent.'; header("Location: dashboard.php?section=support"); exit;
    } catch (PDOException $e) { $_SESSION['error_msg'] = 'Failed: '.$e->getMessage(); header("Location: dashboard.php?section=support"); exit; }
}

// Data fetch
$user_stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?"); $user_stmt->execute([$user_id]); $user_profile = $user_stmt->fetch();
$user_name = $user_profile['name'] ?? 'User'; $user_email = $user_profile['email'] ?? $username;
$spend_stmt = $db->prepare("SELECT SUM(price_paid_inr) FROM purchases WHERE user_id = ? AND status = 'approved'"); $spend_stmt->execute([$user_id]); $total_spending = $spend_stmt->fetchColumn() ?: 0;
$order_count = $db->prepare("SELECT COUNT(*) FROM purchases WHERE user_id = ? AND status = 'approved'"); $order_count->execute([$user_id]); $approved_count = $order_count->fetchColumn() ?: 0;
$stmt = $db->prepare("SELECT * FROM catalog WHERE status = 'active' ORDER BY service_type DESC, price_inr ASC"); $stmt->execute(); $catalog_items = $stmt->fetchAll();
$canva_stmt = $db->query("SELECT * FROM catalog WHERE name LIKE '%Canva Premium Lifetime%' AND status = 'active' LIMIT 1"); $canva_item = $canva_stmt ? $canva_stmt->fetch() : null;
$stmt = $db->prepare("SELECT * FROM purchases WHERE user_id = ? ORDER BY id DESC"); $stmt->execute([$user_id]); $purchases = $stmt->fetchAll();
$comp_stmt = $db->prepare("SELECT * FROM complaints WHERE user_id = ? ORDER BY id DESC"); $comp_stmt->execute([$user_id]); $complaints = $comp_stmt->fetchAll();
$active_sections = [];
try {
    $sections_stmt = $db->query("SELECT * FROM sections WHERE status = 'active' ORDER BY display_order ASC, id ASC");
    $active_sections = $sections_stmt->fetchAll();
} catch (Exception $e) {}

function get_flag_icon($country) {
    $country = strtolower($country);
    $flags = ['india'=>'🇮🇳','usa'=>'🇺🇸','myanmar'=>'🇲🇲','vietnam'=>'🇻🇳','canada'=>'🇨🇦','chile'=>'🇨🇱','afghanistan'=>'🇦🇫','greenland'=>'🇬🇱','united arab emirates'=>'🇦🇪','fiji'=>'🇫🇯','russia'=>'🇷🇺','france'=>'🇫🇷','china'=>'🇨🇳','turkey'=>'🇹🇷','germany'=>'🇩🇪','philippines'=>'🇵🇭'];
    return $flags[$country] ?? '🌐';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Dashboard – Mango Number</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <style>
        :root {
            --bg: #09090f; --surface: #111118; --elevated: #1a1a26;
            --border: rgba(255,255,255,0.07); --border-bright: rgba(249,115,22,0.3);
            --accent: #f97316; --accent-glow: rgba(249,115,22,0.15);
            --text: #f1f5f9; --muted: #64748b;
            --success: #22c55e; --warning: #eab308; --danger: #ef4444;
            --sidebar-w: 240px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w); flex-shrink: 0;
            background: var(--surface); border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            position: fixed; left: 0; top: 0; bottom: 0; z-index: 100;
            transition: transform 0.3s ease;
        }
        .sidebar-header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .sidebar-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .sidebar-logo-icon { width: 34px; height: 34px; background: linear-gradient(135deg,#f97316,#fb923c); border-radius: 9px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 16px rgba(249,115,22,0.4); }
        .sidebar-logo-icon img { width: 22px; height: 22px; object-fit: contain; }
        .sidebar-logo-text { font-family: 'Sora', sans-serif; font-size: 16px; font-weight: 800; color: var(--text); }
        .sidebar-logo-text span { color: var(--accent); }

        .sidebar-close { display: none; background: none; border: none; color: var(--muted); cursor: pointer; font-size: 20px; padding: 4px; }

        .sidebar-profile {
            padding: 16px 20px; border-bottom: 1px solid var(--border);
        }
        .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, #f97316, #fb923c);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 800; color: #fff;
            flex-shrink: 0;
        }
        .profile-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .profile-info { min-width: 0; }
        .profile-name { font-size: 13.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .profile-email { font-size: 11.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stats-row { display: flex; gap: 8px; }
        .stat-chip {
            flex: 1; background: var(--elevated); border-radius: 8px;
            padding: 8px 10px; text-align: center;
        }
        .stat-chip .val { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 800; color: var(--accent); }
        .stat-chip .lbl { font-size: 10px; color: var(--muted); margin-top: 1px; }

        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--muted); padding: 0 8px; margin-bottom: 8px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 12px; border-radius: 10px;
            font-size: 13.5px; font-weight: 600; color: var(--muted);
            cursor: pointer; transition: all 0.2s; margin-bottom: 2px;
            border: 1px solid transparent;
            background: none; width: 100%; text-align: left;
        }
        .nav-item i { font-size: 18px; }
        .nav-item:hover { background: var(--elevated); color: var(--text); }
        .nav-item.active { background: var(--accent-glow); color: var(--accent); border-color: var(--border-bright); }
        .nav-item.active i { color: var(--accent); }

        .sidebar-footer { padding: 12px; border-top: 1px solid var(--border); }
        .logout-btn {
            display: flex; align-items: center; gap: 10px;
            width: 100%; padding: 11px 12px; border-radius: 10px;
            font-size: 13.5px; font-weight: 600; color: var(--muted);
            text-decoration: none; transition: all 0.2s;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.1); color: #f87171; }
        .logout-btn i { font-size: 18px; }

        /* ── Main ── */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 14px 28px; display: flex; align-items: center; justify-content: space-between;
            gap: 16px; position: sticky; top: 0; z-index: 50; width: 100%;
        }
        .topbar-left { display: flex; align-items: center; gap: 14px; min-width: 0; flex: 1; }
        .hamburger { display: none; background: none; border: none; color: var(--muted); cursor: pointer; font-size: 22px; padding: 4px; }
        .topbar-greeting { font-size: 14px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .topbar-greeting strong { color: var(--text); font-weight: 700; }
        .topbar-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; margin-left: auto; }
        .tg-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 20px; background: linear-gradient(135deg, #0088cc 0%, #00a8ff 100%);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 99px;
            font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #fff; text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;
            box-shadow: 0 4px 16px rgba(0,136,204,0.35); position: relative; overflow: hidden;
        }
        .tg-btn::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            transition: left 0.5s ease;
        }
        .tg-btn:hover::before { left: 100%; }
        .tg-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,136,204,0.55); background: linear-gradient(135deg, #0099e6 0%, #1ab2ff 100%); color: #fff; }

        /* ── Content ── */
        .content { padding: 28px; flex: 1; }
        .dashboard-section { display: none; }
        .dashboard-section.active { display: block; }

        /* ── Alerts ── */
        .alert-bar { border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2); color: #86efac; }
        .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }

        /* ── Section header ── */
        .section-title { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 4px; }
        .section-sub { font-size: 13.5px; color: var(--muted); margin-bottom: 24px; }

        /* ── Service filter tabs ── */
        .filter-tabs { display: inline-flex; background: var(--elevated); border: 1px solid var(--border); border-radius: 12px; padding: 4px; gap: 4px; margin-bottom: 24px; }
        .filter-tab {
            padding: 9px 20px; border-radius: 8px; border: none; background: none;
            font-size: 13.5px; font-weight: 700; color: var(--muted); cursor: pointer;
            transition: all 0.2s; display: flex; align-items: center; gap: 7px;
        }
        .filter-tab:hover { color: var(--text); }
        .filter-tab.active { background: var(--accent); color: #fff; box-shadow: 0 4px 14px rgba(249,115,22,0.3); }
        .filter-tab.active.tg-tab { background: linear-gradient(135deg,#0088cc,#29b6f6); box-shadow: 0 4px 14px rgba(0,136,204,0.25); }

        /* ── Search bar ── */
        .search-wrap { position: relative; max-width: 400px; margin-bottom: 24px; }
        .search-wrap input {
            width: 100%; background: var(--elevated); border: 1px solid var(--border); border-radius: 10px;
            padding: 11px 16px 11px 40px; font-family: 'Inter', sans-serif; font-size: 14px;
            color: var(--text); outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
        .search-wrap input::placeholder { color: #334155; }
        .search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 16px; }
        .search-clear { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); cursor: pointer; display: none; font-size: 14px; background: none; border: none; }

        /* ── Catalog grid ── */
        .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .catalog-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
            padding: 20px; display: flex; flex-direction: column; justify-content: space-between;
            transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden;
        }
        .catalog-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg,transparent,var(--accent),transparent); opacity: 0; transition: opacity 0.2s; }
        .catalog-card:hover { border-color: var(--border-bright); transform: translateY(-3px); box-shadow: 0 12px 32px rgba(249,115,22,0.1); }
        .catalog-card:hover::before { opacity: 1; }
        .catalog-card.hidden, .catalog-card.hidden-by-search { display: none !important; }
        .c-flag { font-size: 28px; margin-bottom: 8px; }
        .c-country { font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .c-platform { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
        .c-platform.tg { color: #38bdf8; }
        .c-platform.wa { color: #22c55e; }
        .stock-badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 99px; display: inline-block; }
        .stock-in { background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.2); }
        .stock-low { background: rgba(234,179,8,0.15); color: #fde047; border: 1px solid rgba(234,179,8,0.35); animation: pulseLow 2s infinite; }
        .stock-out { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
        @keyframes pulseLow { 0%, 100% { opacity: 1; } 50% { opacity: 0.65; } }
        .btn-copy-sm { background: var(--elevated); border: 1px solid var(--border); color: var(--muted); border-radius: 6px; padding: 2px 6px; font-size: 11px; cursor: pointer; transition: all 0.2s; margin-left: 5px; }
        .btn-copy-sm:hover { color: var(--accent); border-color: var(--border-bright); }
        .card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border); }
        .price-inr { font-family: 'Sora', sans-serif; font-size: 20px; font-weight: 800; color: var(--accent); }
        .price-usd { font-size: 11.5px; color: var(--muted); }
        .btn-buy {
            padding: 9px 18px; border: none; border-radius: 8px;
            background: linear-gradient(135deg,#f97316,#fb923c);
            font-size: 13px; font-weight: 700; color: #fff; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(249,115,22,0.25);
        }
        .btn-buy:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(249,115,22,0.4); }
        .btn-buy.disabled { background: var(--elevated); color: var(--muted); cursor: not-allowed; box-shadow: none; transform: none; pointer-events: none; }

        /* ── Order History Table Rebuild ── */
        .table-wrap {
            background: rgba(18, 18, 26, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-top: 3px solid var(--accent);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; }
        thead th {
            background: #141420;
            padding: 16px 18px;
            text-align: left;
            font-family: 'Sora', sans-serif;
            font-size: 11.5px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.8px;
            color: #94a3b8;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            white-space: nowrap;
        }
        tbody td {
            padding: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
            color: #f8fafc;
        }
        tbody tr { transition: background 0.2s; }
        tbody tr:hover td { background: rgba(255, 255, 255, 0.03); }
        tbody tr:last-child td { border-bottom: none; }

        .service-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 800;
            letter-spacing: 0.3px;
        }
        .service-telegram { background: rgba(0, 136, 204, 0.15); color: #38bdf8; border: 1px solid rgba(0, 136, 204, 0.3); }
        .service-whatsapp { background: rgba(37, 211, 102, 0.15); color: #4ade80; border: 1px solid rgba(37, 211, 102, 0.3); }
        .service-other { background: rgba(249, 115, 22, 0.15); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.3); }

        .price-tag {
            font-family: 'Sora', sans-serif; font-size: 16px; font-weight: 800;
            color: #f97316; letter-spacing: -0.5px;
        }

        .utr-code-box {
            display: inline-flex; flex-direction: column; gap: 4px;
        }
        .utr-code {
            font-family: monospace; font-size: 12px; font-weight: 700;
            background: #12121c; border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 4px 9px; border-radius: 7px; color: #cbd5e1;
        }
        .proof-link-btn {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; font-weight: 700; color: #fb923c;
            text-decoration: none; padding: 2px 6px; border-radius: 4px;
            background: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.2);
            transition: all 0.2s;
        }
        .proof-link-btn:hover { background: rgba(249, 115, 22, 0.25); color: #fff; }

        .badge { font-size: 12px; font-weight: 800; padding: 6px 12px; border-radius: 99px; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
        .badge-pending { background: rgba(234, 179, 8, 0.15); color: #fef08a; border: 1px solid rgba(234, 179, 8, 0.3); }
        .badge-approved { background: rgba(34, 197, 94, 0.15); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-rejected { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }

        .waiting-pulse {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 700; color: #94a3b8;
            background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 5px 12px; border-radius: 8px;
        }

        .vnum-box {
            display: inline-flex; align-items: center; gap: 8px;
            background: #141424; border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 5px 12px; border-radius: 9px;
        }
        .vnum-val { font-family: monospace; font-size: 14px; font-weight: 800; color: #f8fafc; letter-spacing: 0.5px; }

        .otp-box {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 6px 14px; border-radius: 10px;
        }
        .otp-val { font-family: monospace; font-size: 15px; font-weight: 900; color: #4ade80; letter-spacing: 2px; }

        .btn-copy-sm {
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f8fafc; border-radius: 6px; padding: 3px 8px; font-size: 11px; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-copy-sm:hover { background: #f97316; border-color: #f97316; color: #fff; }

        .btn-tg-sm {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 9px;
            background: linear-gradient(135deg, #0088cc 0%, #00a8ff 100%);
            color: #fff; font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 800;
            text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(0, 136, 204, 0.3);
        }
        .btn-tg-sm:hover { transform: translateY(-1.5px); box-shadow: 0 6px 18px rgba(0, 136, 204, 0.5); }

        .btn-again {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 9px;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            color: #fff; font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 800;
            text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3);
        }
        .btn-again:hover { transform: translateY(-1.5px); box-shadow: 0 6px 18px rgba(249, 115, 22, 0.5); }
        .empty-row { text-align: center; color: #64748b; padding: 40px 0; font-size: 14px; font-weight: 500; }        /* ── Support Center Redesign ── */
        .support-grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; align-items: start; }
        .support-form-card, .support-log-card {
            background: rgba(18, 18, 26, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-top: 3px solid var(--accent);
            border-radius: 20px; padding: 28px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }
        .card-title { font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 20px; letter-spacing: -0.3px; display: flex; align-items: center; justify-content: space-between; }
        label { display: block; font-size: 12.5px; font-weight: 700; color: #cbd5e1; margin-bottom: 8px; letter-spacing: 0.2px; }
        input[type="text"], select, textarea {
            width: 100%; background: #141420;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 13px 16px; font-family: 'Inter', sans-serif; font-size: 14px; color: #f8fafc;
            outline: none; transition: border-color 0.25s, box-shadow 0.25s, background 0.25s; margin-bottom: 18px;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #f97316;
            background: #181826;
            box-shadow: 0 0 0 3.5px rgba(249, 115, 22, 0.2);
        }
        input::placeholder, textarea::placeholder { color: #64748b; }
        select option { background: #181826; color: #f8fafc; }
        textarea { resize: vertical; min-height: 110px; line-height: 1.6; }
        .form-submit {
            width: 100%; padding: 14px 20px;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            border: none; border-radius: 12px;
            font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 800; color: #fff;
            cursor: pointer; transition: transform 0.25s, box-shadow 0.25s;
            box-shadow: 0 8px 24px rgba(249, 115, 22, 0.35);
        }
        .form-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(249, 115, 22, 0.5); }
        .form-note { font-size: 12px; color: #64748b; text-align: center; margin-top: 12px; font-weight: 500; }

        .complaint-item {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px; margin-bottom: 16px; overflow: hidden;
            background: #12121c; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .complaint-item:hover { border-color: rgba(249, 115, 22, 0.3); }
        .complaint-header {
            padding: 16px 20px; background: rgba(255, 255, 255, 0.02);
            display: flex; align-items: center; justify-content: space-between; cursor: pointer; gap: 12px;
            transition: background 0.2s;
        }
        .complaint-header:hover { background: rgba(255, 255, 255, 0.04); }
        .complaint-subject { font-size: 15px; font-weight: 700; color: #f1f5f9; flex: 1; letter-spacing: -0.2px; }
        .complaint-body { display: none; padding: 20px; background: #0c0c14; border-top: 1px solid rgba(255, 255, 255, 0.06); }
        .complaint-item.open-item .complaint-body { display: block; }
        .complaint-date { font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 16px; display: flex; align-items: center; gap: 6px; }
        .chat-thread {
            display: flex; flex-direction: column; gap: 14px;
            max-height: 380px; overflow-y: auto;
            background: #12121e; border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px; padding: 18px; margin-bottom: 18px;
        }
        .bubble { max-width: 82%; padding: 14px 18px; border-radius: 16px; font-size: 13.5px; line-height: 1.6; color: #f8fafc; position: relative; box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
        .bubble-user {
            align-self: flex-start;
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.18) 0%, rgba(251, 146, 60, 0.12) 100%);
            border: 1px solid rgba(249, 115, 22, 0.35);
            border-radius: 16px 16px 16px 3px;
        }
        .bubble-admin {
            align-self: flex-end;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.18) 0%, rgba(74, 222, 128, 0.12) 100%);
            border: 1px solid rgba(34, 197, 94, 0.35);
            border-radius: 16px 16px 3px 16px;
        }
        .bubble-sender { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 6px; display: flex; align-items: center; gap: 5px; }
        .bubble-user .bubble-sender { color: #fb923c; }
        .bubble-admin .bubble-sender { color: #4ade80; }
        .bubble-time { font-size: 10.5px; color: rgba(241, 245, 249, 0.5); text-align: right; margin-top: 6px; font-weight: 500; }
        .reply-form textarea { margin-bottom: 12px; background: #141420; }
        .reply-submit {
            padding: 12px 24px;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            border: none; border-radius: 10px;
            font-family: 'Sora', sans-serif; font-size: 13.5px; font-weight: 800; color: #fff;
            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(249, 115, 22, 0.3);
        }
        .reply-submit:hover { transform: translateY(-1.5px); box-shadow: 0 8px 22px rgba(249, 115, 22, 0.45); }
        .closed-notice { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 12px; padding: 14px 18px; font-size: 13.5px; font-weight: 600; color: #fca5a5; display: flex; align-items: center; gap: 10px; }
        .empty-state { text-align: center; color: #64748b; padding: 50px 20px; font-size: 14.5px; font-weight: 500; }nt-size: 13px; color: #fca5a5; display: flex; align-items: center; gap: 8px; }
        .empty-state { text-align: center; color: var(--muted); padding: 40px 20px; font-size: 14px; }

        /* ── Mobile sidebar overlay ── */
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 99; }
        .overlay.visible { display: block; }

        /* ── Mobile bottom nav ── */
        .bottom-nav { display: none; }

        /* ── Post-payment modal ── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-card { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 32px 28px; max-width: 480px; width: 100%; text-align: center; }
        .modal-icon { font-size: 52px; margin-bottom: 16px; }
        .modal-title { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 16px; }
        .msg-copy-box { background: var(--elevated); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; text-align: left; font-size: 13px; line-height: 1.6; color: #94a3b8; font-family: monospace; margin-bottom: 10px; word-break: break-word; }
        .btn-copy { padding: 7px 16px; background: var(--elevated); border: 1px solid var(--border); border-radius: 7px; font-size: 12px; font-weight: 600; color: var(--muted); cursor: pointer; margin-bottom: 16px; transition: border-color 0.2s; }
        .btn-copy:hover { border-color: var(--accent); color: var(--accent); }
        .warn-box { background: rgba(239,68,68,0.06); border: 1.5px dashed rgba(239,68,68,0.3); border-radius: 10px; padding: 12px 16px; font-size: 13px; line-height: 1.6; color: #fca5a5; text-align: left; margin-bottom: 18px; }
        .btn-tg-modal { display: block; width: 100%; padding: 14px; background: linear-gradient(135deg,#0088cc,#29b6f6); border: none; border-radius: 12px; font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 700; color: #fff; cursor: pointer; text-decoration: none; transition: transform 0.2s; box-shadow: 0 4px 18px rgba(0,136,204,0.3); }
        .btn-tg-modal:hover { transform: translateY(-1px); }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .support-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: 24px 0 48px rgba(0,0,0,0.6); }
            .sidebar-close { display: block; }
            .main { margin-left: 0; }
            .hamburger { display: block; }
            .content { padding: 16px; padding-bottom: 80px; }
            .bottom-nav { display: flex; position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; background: var(--surface); border-top: 1px solid var(--border); padding: 8px 0 max(8px, env(safe-area-inset-bottom)); }
            .bnav-btn { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 6px 0; border: none; background: none; color: var(--muted); cursor: pointer; font-size: 10.5px; font-weight: 600; transition: color 0.2s; }
            .bnav-btn i { font-size: 22px; }
            .bnav-btn.active { color: var(--accent); }
            .catalog-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
            .topbar { padding: 12px 16px; }
            .tg-btn span { display: none; }
            .tg-btn { padding: 9px; }
            input[type="text"], input[type="password"], select, textarea { font-size: 16px !important; }
        }
        @media (max-width: 480px) {
            .catalog-grid { grid-template-columns: 1fr 1fr; }
            .price-inr { font-size: 17px; }
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

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-logo">
                <div class="sidebar-logo-icon"><img src="assets/img/logo.png" alt="Logo"></div>
                <span class="sidebar-logo-text">Mango<span>Number</span></span>
            </a>
            <button class="sidebar-close" onclick="closeSidebar()"><i class="bx bx-x"></i></button>
        </div>

        <div class="sidebar-profile">
            <div class="profile-row">
                <div class="avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="profile-email"><?= htmlspecialchars($user_email) ?></div>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-chip">
                    <div class="val">₹<?= number_format($total_spending, 0) ?></div>
                    <div class="lbl">Total Spent</div>
                </div>
                <div class="stat-chip">
                    <div class="val"><?= $approved_count ?></div>
                    <div class="lbl">Orders</div>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Navigation</div>
            <?php if (!empty($active_sections)): ?>
                <?php foreach ($active_sections as $sec): ?>
                    <button class="nav-item" id="menu-section-<?= $sec['id'] ?>" onclick="switchSection('section-<?= $sec['id'] ?>')">
                        <?php if (!empty($sec['icon']) && str_contains($sec['icon'], 'http')): ?>
                            <img src="<?= htmlspecialchars($sec['icon']) ?>" style="width:18px; height:18px; object-fit:contain; border-radius:3px;">
                        <?php else: ?>
                            <i class="bx <?= htmlspecialchars(!empty($sec['icon']) ? $sec['icon'] : 'bx-layer') ?>"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($sec['name']) ?>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>

            <button class="nav-item active" id="menu-history" onclick="switchSection('history')">
                <i class="bx bx-history"></i> Order History
            </button>
            <button class="nav-item" id="menu-support" onclick="switchSection('support')">
                <i class="bx bx-support"></i> Support Center
            </button>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><i class="bx bx-power-off"></i> Log Out</a>
        </div>
    </aside>

    <!-- Main -->
    <div class="main">
        <!-- Topbar -->
        <header class="topbar" style="display: flex !important; align-items: center !important; justify-content: space-between !important; width: 100% !important;">
            <div class="topbar-left" style="display: flex; align-items: center; gap: 14px;">
                <button class="hamburger" onclick="openSidebar()"><i class="bx bx-menu"></i></button>
                <span class="topbar-greeting">👋 Welcome, <strong><?= htmlspecialchars($username) ?></strong></span>
            </div>
            <div class="topbar-right" style="margin-left: auto !important; flex-shrink: 0 !important; display: flex !important; align-items: center !important;">
                <a href="https://t.me/nu9rl" target="_blank" class="tg-btn">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white"><path d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm5.56 8.16l-1.85 8.74c-.14.62-.51.77-1.03.48l-2.82-2.08-1.36 1.31c-.15.15-.28.28-.57.28l.2-2.86 5.21-4.71c.23-.2-.05-.31-.36-.1l-6.44 4.05-2.77-.87c-.6-.19-.61-.6.13-.89l10.82-4.17c.5-.18.94.12.77.72z"/></svg>
                    <span>Customer Support</span>
                </a>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <?php if (!empty($success_msg)): ?>
                <div class="alert-bar alert-success"><i class="bx bx-check-circle" style="font-size:18px;"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert-bar alert-error"><i class="bx bx-error-circle" style="font-size:18px;"></i> <?= $error_msg ?></div>
            <?php endif; ?>

            <!-- DYNAMIC CATALOG SECTIONS -->
            <?php foreach ($active_sections as $sec): 
                $prod_stmt = $db->prepare("SELECT * FROM products WHERE section_id = ? ORDER BY display_order ASC, id DESC");
                $prod_stmt->execute([$sec['id']]);
                $sec_products = $prod_stmt->fetchAll();
            ?>
                <div id="section-section-<?= $sec['id'] ?>" class="dashboard-section">
                    <h1 class="section-title"><?= htmlspecialchars($sec['name']) ?></h1>
                    <p class="section-sub"><?= htmlspecialchars(!empty($sec['description']) ? $sec['description'] : 'Browse available offers and services below.') ?></p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <?php if (!empty($sec_products)): ?>
                            <?php foreach ($sec_products as $item): ?>
                                <div class="card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <?php 
                                                    $item_has_img = (!empty($item['icon']) && (str_contains($item['icon'], 'uploads/') || str_contains($item['icon'], 'http')));
                                                ?>
                                                <?php if ($item_has_img): ?>
                                                    <img src="<?= htmlspecialchars($item['icon']) ?>" style="width:28px; height:28px; object-fit:contain; border-radius:6px;">
                                                <?php else: ?>
                                                    <i class="bx <?= htmlspecialchars(!empty($item['icon']) ? $item['icon'] : 'bx-package') ?>" style="font-size:24px; color: var(--accent);"></i>
                                                <?php endif; ?>
                                                <span style="font-family:'Sora', sans-serif; font-weight:700; font-size:15px; color: var(--text);"><?= htmlspecialchars($item['name']) ?></span>
                                            </div>
                                            <?php if (!empty($item['badge'])): ?>
                                                <span style="background: rgba(249,115,22,0.15); color: #f97316; font-size: 11px; padding: 3px 8px; border-radius: 6px; font-weight: 700;"><?= htmlspecialchars($item['badge']) ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div style="font-size: 13px; color: var(--muted); margin-bottom: 15px;">
                                            Region / Country: <strong style="color: var(--text);"><?= htmlspecialchars($item['country']) ?></strong>
                                        </div>
                                    </div>

                                    <div class="card-footer" style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                                        <div>
                                            <div class="price-inr">₹<?= number_format($item['price_inr'], 0) ?></div>
                                            <?php if ($item['price_usd'] > 0): ?>
                                                <div class="price-usd">$<?= number_format($item['price_usd'], 2) ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <?php 
                                            $is_item_disabled = ($item['status'] === 'inactive' || $item['availability_status'] === 'disabled');
                                        ?>
                                        <?php if ($is_item_disabled): ?>
                                            <button class="btn-buy disabled" disabled style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.25); font-weight: 700; cursor: not-allowed;">Not available right now</button>
                                        <?php elseif ($item['stock_quantity'] > 0): ?>
                                            <a href="payment.php?id=<?= $item['id'] ?>" class="btn-buy">Buy Now</a>
                                        <?php else: ?>
                                            <button class="btn-buy disabled" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="card p-4 text-center text-muted" style="grid-column: 1 / -1; background: var(--surface); border: 1px dashed var(--border); border-radius: 16px;">
                                No items currently available in this category. Check back soon!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 2. HISTORY SECTION -->
            <div id="section-history" class="dashboard-section active">
                <h1 class="section-title">Order History</h1>
                <p class="section-sub">Track your purchases and verification status.</p>

                <div class="table-wrap">
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>UTR</th>
                                    <th>Status</th>
                                    <th>ID / Number</th>
                                    <th>Support</th>
                                    <th>Re-order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($purchases)): ?>
                                    <?php foreach ($purchases as $p): ?>
                                        <tr data-purchase-id="<?= $p['id'] ?>">
                                            <td style="white-space:nowrap;">
                                                <div style="font-weight:700; color:#f8fafc; font-size:13.5px;"><?= date('d M Y', strtotime($p['created_at'])) ?></div>
                                                <div style="font-size:11.5px; color:#94a3b8; font-weight:500; margin-top:2px;"><?= date('h:i A', strtotime($p['created_at'])) ?></div>
                                            </td>
                                            <td>
                                                <?php 
                                                    $stype = strtolower($p['service_type']);
                                                    $sclass = str_contains($stype, 'telegram') ? 'service-telegram' : (str_contains($stype, 'whatsapp') ? 'service-whatsapp' : 'service-other');
                                                ?>
                                                <span class="service-badge <?= $sclass ?>"><?= htmlspecialchars($p['service_type']) ?></span>
                                                <div style="font-size:13px; font-weight:700; color:#f1f5f9; margin-top:4px;"><?= htmlspecialchars($p['item_name']) ?></div>
                                            </td>
                                            <td>
                                                <span class="price-tag">₹<?= number_format($p['price_paid_inr'], 0) ?></span>
                                            </td>
                                            <td>
                                                <div class="utr-code-box">
                                                    <code class="utr-code"><?= htmlspecialchars($p['utr_number']) ?></code>
                                                    <?php if ($p['status'] === 'pending' && !empty($p['screenshot_path'])): ?>
                                                        <a href="<?= htmlspecialchars($p['screenshot_path']) ?>" target="_blank" class="proof-link-btn">👁️ Proof</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="col-status">
                                                <?php if ($p['status'] === 'pending'): ?>
                                                    <span class="badge badge-pending">⏳ Pending</span>
                                                <?php elseif ($p['status'] === 'approved'): ?>
                                                    <span class="badge badge-approved">✅ Approved</span>
                                                <?php else: ?>
                                                    <span class="badge badge-rejected">❌ Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-vnum">
                                                <?php if ($p['status'] === 'approved' && !empty($p['virtual_number_provided'])): ?>
                                                    <div class="vnum-box">
                                                        <span class="vnum-val"><?= htmlspecialchars($p['virtual_number_provided']) ?></span>
                                                        <button class="btn-copy-sm" onclick="copyText('<?= htmlspecialchars($p['virtual_number_provided']) ?>', this)" title="Copy Number">📋 Copy</button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="waiting-pulse">⏳ Processing...</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="https://t.me/nu9rl" target="_blank" class="btn-tg-sm">✈️ Telegram</a>
                                            </td>
                                            <td>
                                                <?php if (!empty($p['catalog_id'])): ?>
                                                    <a href="payment.php?id=<?= (int)$p['catalog_id'] ?>" class="btn-again">🔄 Re-order</a>
                                                <?php else: ?>
                                                    <span style="color:#64748b; font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="empty-row">No orders yet. Go buy a number! 🚀</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 3. SUPPORT SECTION -->
            <div id="section-support" class="dashboard-section">
                <h1 class="section-title">Support Center</h1>
                <p class="section-sub">File a complaint or check your support tickets.</p>

                <div class="support-grid">
                    <div class="support-form-card">
                        <h2 class="card-title">Open a Ticket</h2>
                        <form action="dashboard.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div>
                                <label for="comp-purchase">Related Order UTR (Optional)</label>
                                <select name="purchase_id" id="comp-purchase">
                                    <option value="0">General Support</option>
                                    <?php foreach ($purchases as $p): ?>
                                        <option value="<?= $p['id'] ?>">UTR: <?= htmlspecialchars($p['utr_number']) ?> (₹<?= number_format($p['price_paid_inr'], 0) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="comp-subject">Subject</label>
                                <input type="text" name="subject" id="comp-subject" placeholder="e.g. Verification delay" required>
                            </div>
                            <div>
                                <label for="comp-msg">Message</label>
                                <textarea name="message" id="comp-msg" rows="4" placeholder="Describe your issue..." required></textarea>
                            </div>
                            <button type="submit" name="submit_complaint" class="form-submit">Submit Ticket</button>
                            <p class="form-note">Resolved tickets are auto-deleted after 3 days.</p>
                        </form>
                    </div>

                    <div class="support-log-card">
                        <h2 class="card-title">Your Tickets</h2>
                        <?php if (!empty($complaints)): ?>
                            <?php foreach ($complaints as $index => $c): ?>
                                <div class="complaint-item <?= $index === 0 ? 'open-item' : '' ?>">
                                    <div class="complaint-header" onclick="toggleComplaint(this)">
                                        <span class="complaint-subject"><?= htmlspecialchars($c['subject']) ?></span>
                                        <?php if (!empty($c['admin_deleted_at'])): ?>
                                            <span class="badge badge-rejected">Closed</span>
                                        <?php elseif ($c['status'] === 'open'): ?>
                                            <span class="badge badge-pending">Open</span>
                                        <?php else: ?>
                                            <span class="badge badge-approved">Resolved</span>
                                        <?php endif; ?>
                                        <i class="bx bx-chevron-down" style="font-size:18px;color:var(--muted);flex-shrink:0;transition:transform 0.2s;"></i>
                                    </div>
                                    <div class="complaint-body">
                                        <div class="complaint-date">Filed on: <?= date('d M Y, h:i A', strtotime($c['created_at'])) ?></div>
                                        <div class="chat-thread">
                                            <div style="align-self:flex-start;">
                                                <div class="bubble bubble-user">
                                                    <div class="bubble-sender">👤 YOU (Original)</div>
                                                    <?= nl2br(htmlspecialchars($c['message'])) ?>
                                                    <div class="bubble-time"><?= date('d M, h:i A', strtotime($c['created_at'])) ?></div>
                                                </div>
                                            </div>
                                            <?php
                                            $msg_stmt = $db->prepare("SELECT * FROM complaint_messages WHERE complaint_id = ? ORDER BY id ASC");
                                            $msg_stmt->execute([$c['id']]); $msgs = $msg_stmt->fetchAll();
                                            foreach ($msgs as $m):
                                            ?>
                                                <div style="align-self:<?= $m['sender']==='admin'?'flex-end':'flex-start' ?>;">
                                                    <div class="bubble <?= $m['sender']==='admin'?'bubble-admin':'bubble-user' ?>">
                                                        <div class="bubble-sender"><?= $m['sender']==='admin'?'🛡️ Mango Support':'👤 YOU' ?></div>
                                                        <?= nl2br(htmlspecialchars($m['message'])) ?>
                                                        <div class="bubble-time"><?= date('d M, h:i A', strtotime($m['created_at'])) ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (!empty($c['admin_deleted_at'])): ?>
                                            <div class="closed-notice"><i class="bx bx-lock-alt"></i> This ticket has been resolved and closed.</div>
                                        <?php else: ?>
                                            <div class="reply-form">
                                                <form action="dashboard.php" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                                    <textarea name="reply_message" rows="2" placeholder="Send a reply..." required></textarea>
                                                    <button type="submit" name="submit_complaint_reply" class="reply-submit">Send Reply ✈️</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No support tickets yet. We're here if you need us! 🙂</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <button class="bnav-btn active" id="bnav-buy" onclick="switchSection('buy')">
            <i class="bx bx-phone-call"></i><span>Buy</span>
        </button>
        <button class="bnav-btn" id="bnav-history" onclick="switchSection('history')">
            <i class="bx bx-history"></i><span>History</span>
        </button>
        <button class="bnav-btn" id="bnav-support" onclick="switchSection('support')">
            <i class="bx bx-support"></i><span>Support</span>
        </button>
    </nav>

    <?php if ($show_whatsapp_redirect_modal): ?>
    <div class="modal-overlay" id="payModal">
        <div class="modal-card">
            <div class="modal-icon">✅</div>
            <h2 class="modal-title">Payment Submitted!</h2>
            <div class="msg-copy-box" id="copy-msg"><?= htmlspecialchars($show_whatsapp_text) ?></div>
            <button class="btn-copy" onclick="copyMsg()">📋 Copy Message</button>
            <div class="warn-box">
                ⚠️ <strong>Send this screenshot + text to the Admin on Telegram!</strong><br>
                ⚠️ <strong>यह स्क्रीनशॉट और टेक्स्ट टेलीग्राम पर एडमिन को भेजें!</strong>
            </div>
            <a href="<?= $show_whatsapp_url ?>" target="_blank" class="btn-tg-modal" onclick="document.getElementById('payModal').style.display='none'">
                ✈️ Send Screenshot on Telegram
            </a>
        </div>
    </div>
    <script>
        function copyMsg() {
            const text = document.getElementById('copy-msg').innerText;
            navigator.clipboard.writeText(text).then(() => alert('Copied!')).catch(() => {
                const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); alert('Copied!');
            });
        }
    </script>
    <?php endif; ?>

    <script>
        // Sidebar
        function openSidebar() { document.getElementById('sidebar').classList.add('open'); document.getElementById('overlay').classList.add('visible'); }
        function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('visible'); }

        // Section switch
        function switchSection(section) {
            document.querySelectorAll('.dashboard-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.bnav-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('section-' + section).classList.add('active');
            const si = document.getElementById('menu-' + section); if (si) si.classList.add('active');
            const bi = document.getElementById('bnav-' + section); if (bi) bi.classList.add('active');
            if (window.innerWidth <= 768) { closeSidebar(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
        }

        // Filter by service
        function filterService(service, el) {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('search-input').value = ''; document.getElementById('search-clear').style.display = 'none';
            document.querySelectorAll('.catalog-card').forEach(card => {
                card.classList.remove('hidden-by-search');
                card.getAttribute('data-service') === service ? card.classList.remove('hidden') : card.classList.add('hidden');
            });
        }

        function searchCatalog() {
            const q = document.getElementById('search-input').value.toLowerCase().trim();
            document.getElementById('search-clear').style.display = q ? 'block' : 'none';
            const activeTab = document.querySelector('.filter-tab.active');
            let activeService = 'Telegram';
            if (activeTab) {
                if (activeTab.textContent.includes('WhatsApp')) activeService = 'WhatsApp';
                else if (activeTab.textContent.includes('Canva')) activeService = 'Canva';
            }
            document.querySelectorAll('.catalog-card').forEach(card => {
                if (card.getAttribute('data-service') === activeService) {
                    const country = card.querySelector('.c-country').innerText.toLowerCase();
                    country.includes(q) ? card.classList.remove('hidden-by-search') : card.classList.add('hidden-by-search');
                }
            });
        }
        function clearSearch() { document.getElementById('search-input').value = ''; searchCatalog(); }

        // Complaint accordion
        function toggleComplaint(header) {
            const item = header.closest('.complaint-item');
            const wasOpen = item.classList.contains('open-item');
            document.querySelectorAll('.complaint-item').forEach(i => { i.classList.remove('open-item'); const ico = i.querySelector('.complaint-header .bx-chevron-down'); if (ico) ico.style.transform = ''; });
            if (!wasOpen) {
                item.classList.add('open-item');
                const ico = header.querySelector('.bx-chevron-down'); if (ico) ico.style.transform = 'rotate(180deg)';
                // Scroll chat to bottom
                const thread = item.querySelector('.chat-thread'); if (thread) thread.scrollTop = thread.scrollHeight;
            }
        }

        // Restore section from URL
        document.addEventListener('DOMContentLoaded', () => {
            const p = new URLSearchParams(window.location.search).get('section');
            if (p && ['buy','history','support'].includes(p)) switchSection(p);
            else switchSection('buy');
        });

        // Copy text helper
        function copyText(val, btn) {
            if (!val) return;
            navigator.clipboard.writeText(val).then(() => {
                const orig = btn.innerText; btn.innerText = '✓';
                setTimeout(() => btn.innerText = orig, 1500);
            }).catch(() => {
                const t = document.createElement('textarea'); t.value = val; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t);
                const orig = btn.innerText; btn.innerText = '✓';
                setTimeout(() => btn.innerText = orig, 1500);
            });
        }

        // Live Real-Time Purchase Status Polling (Auto Update Approval / OTP without refresh)
        setInterval(() => {
            fetch('auth_handler.php?action=get-user-purchases')
                .then(r => r.json())
                .then(d => {
                    if (d.success && Array.isArray(d.purchases)) {
                        d.purchases.forEach(p => {
                            const row = document.querySelector(`tr[data-purchase-id="${p.id}"]`);
                            if (row) {
                                // Update Status Badge
                                const statusTd = row.querySelector('.col-status');
                                if (statusTd) {
                                    if (p.status === 'approved') statusTd.innerHTML = '<span class="badge badge-approved">✅ Approved</span>';
                                    else if (p.status === 'rejected') statusTd.innerHTML = '<span class="badge badge-rejected">❌ Rejected</span>';
                                    else statusTd.innerHTML = '<span class="badge badge-pending">🕒 Pending</span>';
                                }
                                // Update Virtual Number
                                const vnumTd = row.querySelector('.col-vnum');
                                if (vnumTd && p.status === 'approved' && p.virtual_number_provided) {
                                    vnumTd.innerHTML = `<span class="vnum-val">${p.virtual_number_provided}</span> <button class="btn-copy-sm" onclick="copyText('${p.virtual_number_provided}', this)" title="Copy Number">📋</button>`;
                                }
                                // Update OTP
                                const otpTd = row.querySelector('.col-otp');
                                if (otpTd && p.status === 'approved' && p.otp_provided) {
                                    otpTd.innerHTML = `<span class="otp-val">${p.otp_provided}</span> <button class="btn-copy-sm" onclick="copyText('${p.otp_provided}', this)" title="Copy OTP">📋</button>`;
                                }
                            }
                        });
                    }
                }).catch(() => {});
        }, 5000);

        // Auto-check if user was deleted
        setInterval(() => {
            fetch('auth_handler.php?action=check-status').then(r => r.json()).then(d => { if (d.logged_in === false) window.location.href = 'account_deleted.php'; }).catch(() => {});
        }, 3000);
    </script>
    <script src="assets/js/anti-devtools.js"></script>
</body>
</html>

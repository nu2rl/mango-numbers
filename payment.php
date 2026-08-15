<?php
/**
 * Mango Number - Dedicated Checkout & Payment Page
 */
include 'config.php';
require_login();

$db = get_db_connection();
if (!$db) die("Database connection failed.");

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$catalog_id = (int)($_GET['product_id'] ?? $_GET['id'] ?? 0);
if ($catalog_id <= 0) {
    $_SESSION['error_msg'] = 'Invalid product selection.';
    header("Location: dashboard.php"); exit;
}

$stmt = $db->prepare("SELECT p.*, s.name as service_type FROM products p JOIN sections s ON p.section_id = s.id WHERE p.id = ? AND p.status = 'active'");
$stmt->execute([$catalog_id]);
$product = $stmt->fetch();

if (!$product) {
    $stmt = $db->prepare("SELECT * FROM catalog WHERE id = ? AND status = 'active'");
    $stmt->execute([$catalog_id]);
    $product = $stmt->fetch();
}

if (!$product) {
    $_SESSION['error_msg'] = 'The selected service is not available.';
    header("Location: dashboard.php"); exit;
}
$stock = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : (int)($product['stock'] ?? 0);
if ($stock <= 0) {
    $_SESSION['error_msg'] = 'This item is currently out of stock.';
    header("Location: dashboard.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_purchase'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = 'CSRF verification failed.';
        header("Location: payment.php?id=" . $catalog_id); exit;
    }
    $utr_number = trim($_POST['utr_number'] ?? '');
    if (empty($utr_number)) { $_SESSION['error_msg'] = 'Transaction UTR is required.'; header("Location: payment.php?id=" . $catalog_id); exit; }
    if (!preg_match('/^\d{12}$/', $utr_number)) { $_SESSION['error_msg'] = 'Please enter a valid 12-digit numeric UTR.'; header("Location: payment.php?id=" . $catalog_id); exit; }
    $chk_stmt = $db->prepare("SELECT id FROM purchases WHERE utr_number = ?");
    $chk_stmt->execute([$utr_number]);
    if ($chk_stmt->fetch()) { $_SESSION['error_msg'] = 'This UTR has already been submitted.'; header("Location: payment.php?id=" . $catalog_id); exit; }

    if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_msg'] = 'Payment screenshot proof is required.'; header("Location: payment.php?id=" . $catalog_id); exit;
    }
    $tmp_name = $_FILES['payment_screenshot']['tmp_name'];
    $orig_name = $_FILES['payment_screenshot']['name'];
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) { $_SESSION['error_msg'] = 'Only JPG, JPEG, PNG, and WEBP images are allowed.'; header("Location: payment.php?id=" . $catalog_id); exit; }
    $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $tmp_name); finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg','image/jpg','image/png','image/x-png','image/webp'])) { $_SESSION['error_msg'] = 'Invalid image format.'; header("Location: payment.php?id=" . $catalog_id); exit; }
    ini_set('memory_limit', '256M');
    $has_gd = function_exists('imagecreatefromjpeg');
    if (!$has_gd) {
        $new_filename = 'ss_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $dest_path = UPLOAD_DIR . $new_filename;
        if (move_uploaded_file($tmp_name, $dest_path)) { $screenshot_path = 'uploads/' . $new_filename; }
        else { $_SESSION['error_msg'] = 'Failed to save screenshot.'; header("Location: payment.php?id=" . $catalog_id); exit; }
    } else {
        $src_img = ($mime==='image/png'||$mime==='image/x-png') ? @imagecreatefrompng($tmp_name) : @imagecreatefromjpeg($tmp_name);
        if (!$src_img) { $_SESSION['error_msg'] = 'Failed to process image.'; header("Location: payment.php?id=" . $catalog_id); exit; }
        $width=imagesx($src_img); $height=imagesy($src_img); $max_dim=1200;
        if ($width>$max_dim||$height>$max_dim) {
            if($width>$height){$new_width=$max_dim;$new_height=(int)floor($height*($max_dim/$width));}
            else{$new_height=$max_dim;$new_width=(int)floor($width*($max_dim/$height));}
            $dst_img=imagecreatetruecolor($new_width,$new_height);
            imagealphablending($dst_img,false); imagesavealpha($dst_img,true);
            imagecopyresampled($dst_img,$src_img,0,0,0,0,$new_width,$new_height,$width,$height);
            @imagedestroy($src_img); $src_img=$dst_img;
        }
        $has_webp=function_exists('imagewebp'); $ext_out=$has_webp?'webp':'jpg';
        $new_filename='ss_'.bin2hex(random_bytes(16)).'.'.$ext_out; $dest_path=UPLOAD_DIR.$new_filename;
        $quality=80; $saved=false;
        while($quality>=20){ob_start();$ok=$has_webp?@imagewebp($src_img,null,$quality):@imagejpeg($src_img,null,$quality);$img_data=ob_get_clean();if($ok&&strlen($img_data)<=400*1024){if(file_put_contents($dest_path,$img_data)!==false){$saved=true;break;}}$quality-=15;}
        if(!$saved){if($has_webp)@imagewebp($src_img,$dest_path,20);else @imagejpeg($src_img,$dest_path,20);}
        @imagedestroy($src_img); $screenshot_path='uploads/'.$new_filename;
    }

    $insert = $db->prepare("INSERT INTO purchases (user_id, catalog_id, service_type, item_name, price_cost_inr, price_paid_inr, utr_number, screenshot_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    try {
        $insert->execute([$user_id,$product['id'],$product['service_type'],$product['name'],$product['price_cost_inr'],$product['price_inr'],$utr_number,$screenshot_path]);
        $_SESSION['success_msg'] = 'Payment submitted! Verification is in progress.';
        $_SESSION['show_whatsapp_redirect_modal'] = true;
        $_SESSION['show_whatsapp_url'] = "https://t.me/nu2rl";
        $_SESSION['show_whatsapp_text'] = "I have paid ".(int)$product['price_inr']." Rupees for ".$product['service_type']." (".$product['name'].") virtual number at ".date('d-M-Y h:i A').". My Transaction UTR is: ".$utr_number.". Please verify my order & provide virtual number/OTP.";
        header("Location: dashboard.php?section=history"); exit;
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'Failed to submit purchase: ' . $e->getMessage();
        header("Location: payment.php?id=" . $catalog_id); exit;
    }
}

function get_country_flag($country) {
    $country = strtolower($country);
    $flags = ['india'=>'🇮🇳','usa'=>'🇺🇸','myanmar'=>'🇲🇲','vietnam'=>'🇻🇳','canada'=>'🇨🇦','chile'=>'🇨🇱','afghanistan'=>'🇦🇫','greenland'=>'🇬🇱','united arab emirates'=>'🇦🇪','fiji'=>'🇫🇯','russia'=>'🇷🇺','france'=>'🇫🇷','china'=>'🇨🇳','turkey'=>'🇹🇷','germany'=>'🇩🇪','philippines'=>'🇵🇭'];
    return $flags[$country] ?? '🌐';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout – Mango Number</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#09090f;--surface:#111118;--elevated:#1a1a26;--border:rgba(255,255,255,0.07);--accent:#f97316;--accent-glow:rgba(249,115,22,0.18);--text:#f1f5f9;--muted:#64748b;--success:#22c55e;--danger:#ef4444;}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        html{-webkit-font-smoothing:antialiased;}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:32px 20px;position:relative;overflow-x:hidden;}
        .glow{position:absolute;border-radius:50%;filter:blur(100px);pointer-events:none;}
        .glow-1{width:500px;height:500px;background:radial-gradient(circle,rgba(249,115,22,0.12)0%,transparent 70%);top:-100px;right:-100px;}
        .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(249,115,22,0.06)0%,transparent 70%);bottom:-100px;left:-100px;}
        .checkout-wrap{width:100%;max-width:500px;position:relative;z-index:2;}
        .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:13px;font-weight:600;text-decoration:none;margin-bottom:20px;transition:color 0.2s;}
        .back-link:hover{color:var(--accent);}
        .back-link svg{width:16px;height:16px;}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px 28px;}
        .card-header{text-align:center;margin-bottom:24px;}
        .logo-icon{width:48px;height:48px;background:linear-gradient(135deg,#f97316,#fb923c);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 0 24px rgba(249,115,22,0.4);}
        .logo-icon img{width:30px;height:30px;object-fit:contain;}
        .card-title{font-family:'Sora',sans-serif;font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px;}
        .card-sub{font-size:13px;color:var(--muted);}
        .alert-box{border-radius:10px;padding:12px 15px;margin-bottom:20px;font-size:13.5px;line-height:1.5;}
        .alert-error{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#fca5a5;}
        .product-summary{background:var(--elevated);border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:24px;}
        .product-row{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;color:var(--muted);padding:4px 0;}
        .product-row:not(:last-child){border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:8px;}
        .product-row strong{color:var(--text);font-weight:600;}
        .price-highlight{color:var(--accent);font-size:18px;font-weight:700;font-family:'Sora',sans-serif;}
        .section-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:8px;text-align:center;}
        .section-sub{font-size:13px;color:var(--muted);text-align:center;margin-bottom:20px;}
        .method-btn{width:100%;padding:16px;border-radius:12px;border:1px solid var(--border);background:var(--elevated);color:var(--text);font-family:'Sora',sans-serif;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:12px;transition:border-color 0.2s,background 0.2s,transform 0.2s;margin-bottom:12px;}
        .method-btn:hover{border-color:var(--accent);background:rgba(249,115,22,0.06);transform:translateY(-1px);}
        .method-btn.primary-method{background:linear-gradient(135deg,rgba(249,115,22,0.15),rgba(249,115,22,0.05));border-color:rgba(249,115,22,0.4);}
        .method-btn .method-icon{font-size:22px;flex-shrink:0;}
        .method-btn .method-text{text-align:left;}
        .method-btn .method-text span{display:block;font-size:11.5px;color:var(--muted);font-weight:400;margin-top:2px;}
        .loading-state{text-align:center;padding:40px 20px;}
        .spinner-ring{width:48px;height:48px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;}
        @keyframes spin{to{transform:rotate(360deg);}}
        .loading-state h4{font-family:'Sora',sans-serif;font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px;}
        .loading-state p{font-size:13px;color:var(--muted);}
        .qr-section{text-align:center;margin-bottom:20px;}
        .qr-frame{background:#fff;border-radius:16px;padding:16px;display:inline-block;margin-bottom:12px;box-shadow:0 0 32px rgba(249,115,22,0.15);}
        .qr-frame img{display:block;width:180px;height:180px;}
        .upi-id-badge{display:inline-block;background:var(--elevated);border:1px solid var(--border);border-radius:8px;padding:6px 14px;font-size:12px;color:var(--muted);font-family:monospace;margin-bottom:16px;}
        .upi-id-badge strong{color:var(--accent);}
        .pay-app-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;border:1.5px solid rgba(249,115,22,0.4);background:rgba(249,115,22,0.06);border-radius:12px;font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:var(--accent);text-decoration:none;margin-bottom:20px;transition:background 0.2s;}
        .pay-app-btn:hover{background:rgba(249,115,22,0.12);}
        .divider{height:1px;background:var(--border);margin:20px 0;}
        label{display:block;font-size:13px;font-weight:600;color:#cbd5e1;margin-bottom:8px;}
        input[type="text"],input[type="file"]{width:100%;background:var(--elevated);border:1px solid var(--border);border-radius:10px;padding:13px 16px;font-family:'Inter',sans-serif;font-size:14px;color:var(--text);outline:none;transition:border-color 0.2s,box-shadow 0.2s;margin-bottom:16px;}
        input[type="text"]:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
        input::placeholder{color:#334155;}
        .file-hint{font-size:11.5px;color:var(--muted);margin-top:-12px;margin-bottom:16px;}
        .btn-submit{width:100%;padding:15px;background:linear-gradient(135deg,#f97316,#fb923c);border:none;border-radius:12px;font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 4px 20px rgba(249,115,22,0.3);transition:transform 0.2s,box-shadow 0.2s;display:flex;align-items:center;justify-content:center;gap:8px;}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(249,115,22,0.4);}
        .intl-card{text-align:center;background:var(--elevated);border:1px dashed rgba(249,115,22,0.3);border-radius:14px;padding:28px 20px;margin-bottom:20px;}
        .intl-icon{font-size:40px;margin-bottom:12px;}
        .intl-card h5{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:8px;}
        .intl-card p{font-size:13px;color:var(--muted);line-height:1.6;}
        .btn-tg{width:100%;padding:14px;background:linear-gradient(135deg,#0088cc,#29b6f6);border:none;border-radius:12px;font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#fff;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;transition:transform 0.2s;box-shadow:0 4px 18px rgba(0,136,204,0.3);}
        .btn-tg:hover{transform:translateY(-1px);}
        @media(max-width:540px){body{padding:20px 14px;}.card{padding:24px 18px;}}
    </style>
</head>
<body>
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    <div class="checkout-wrap">
        <a href="dashboard.php?section=buy" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Dashboard
        </a>

        <div class="card">
            <div class="card-header">
                <div class="logo-icon"><img src="assets/img/logo.png" alt="Logo"></div>
                <h1 class="card-title">Secure Checkout</h1>
                <p class="card-sub">Complete payment to receive your virtual number.</p>
            </div>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert-box alert-error"><?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
            <?php endif; ?>

            <!-- Product Summary -->
            <div class="product-summary">
                <div class="product-row">
                    <span>Country</span>
                    <strong><?= get_country_flag($product['country']) ?> <?= htmlspecialchars($product['country']) ?></strong>
                </div>
                <div class="product-row">
                    <span>Platform</span>
                    <strong><?= htmlspecialchars($product['service_type']) ?> (<?= htmlspecialchars($product['name']) ?>)</strong>
                </div>
                <div class="product-row">
                    <span>Amount Due</span>
                    <span class="price-highlight">₹<?= number_format($product['price_inr'], 0) ?> <span style="font-size:12px;color:var(--muted);">/ $<?= number_format($product['price_usd'], 2) ?></span></span>
                </div>
            </div>

            <!-- Step 1: Payment Method -->
            <div id="payment-step-1">
                <h2 class="section-title">Choose Payment Method</h2>
                <p class="section-sub">Select your region to load payment instructions.</p>
                <button type="button" class="method-btn primary-method" onclick="selectRegion('india')">
                    <span class="method-icon">🇮🇳</span>
                    <div class="method-text">Pay via UPI <span>Inside India — PhonePe, Paytm, GPay</span></div>
                </button>
                <button type="button" class="method-btn" onclick="selectRegion('outside')">
                    <span class="method-icon">🌐</span>
                    <div class="method-text">International Payment <span>Wise transfer or Crypto/USDT</span></div>
                </button>
            </div>

            <!-- Step 2: Loading -->
            <div id="payment-step-india-loading" style="display:none;">
                <div class="loading-state">
                    <div class="spinner-ring"></div>
                    <h4>Generating UPI QR Code...</h4>
                    <p>Preparing your payment securely.</p>
                </div>
            </div>

            <!-- Step 3: UPI Payment -->
            <div id="payment-step-india-content" style="display:none;">
                <h2 class="section-title">Scan &amp; Pay via UPI</h2>
                <p class="section-sub">Scan the QR code, pay exactly ₹<?= number_format($product['price_inr'], 0) ?>, then paste the 12-digit UTR below.</p>

                <div class="qr-section">
                    <div class="qr-frame">
                        <img id="modal-qr-img" src="" alt="UPI QR Code">
                    </div>
                    <div class="upi-id-badge">UPI: <strong><?= UPI_ID ?></strong></div>
                    <a id="modal-upi-pay-btn" href="#" target="_blank" class="pay-app-btn">
                        ⚡ Tap to open UPI App
                    </a>
                </div>

                <div class="divider"></div>

                <form action="payment.php?id=<?= $product['id'] ?>" method="POST" enctype="multipart/form-data" onsubmit="return validatePaymentForm()">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div>
                        <label for="utr_number">Transaction UTR / Ref Number (12 Digits)</label>
                        <input type="text" name="utr_number" id="utr_number" placeholder="Enter 12-digit UPI UTR" required maxlength="12" pattern="\d{12}">
                    </div>
                    <div>
                        <label for="payment_screenshot">Payment Screenshot Proof (JPG, PNG, WEBP)</label>
                        <input type="file" name="payment_screenshot" id="payment_screenshot" accept="image/jpeg,image/jpg,image/png,image/webp" required>
                        <p class="file-hint">Max 10MB. Auto-compressed to WebP.</p>
                    </div>
                    <button type="submit" name="submit_purchase" class="btn-submit" id="submit-btn">
                        🔒 Confirm &amp; Submit UTR
                    </button>
                </form>
            </div>

            <!-- Step 4: International -->
            <div id="payment-step-outside" style="display:none;">
                <h2 class="section-title">International Payment</h2>
                <div class="intl-card">
                    <div class="intl-icon">💸</div>
                    <h5>Wise Transfer or Crypto Deposit</h5>
                    <p>Contact the owner directly on Telegram to get Wise or USDT/Crypto payment details and complete your order instantly.</p>
                </div>
                <a href="https://t.me/nu2rl" target="_blank" class="btn-tg">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="white"><path d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm5.56 8.16l-1.85 8.74c-.14.62-.51.77-1.03.48l-2.82-2.08-1.36 1.31c-.15.15-.28.28-.57.28l.2-2.86 5.21-4.71c.23-.2-.05-.31-.36-.1l-6.44 4.05-2.77-.87c-.6-.19-.61-.6.13-.89l10.82-4.17c.5-.18.94.12.77.72z"/></svg>
                    Contact Owner on Telegram
                </a>
            </div>
        </div>
    </div>

    <script>
        const activePriceInr = <?= (int)$product['price_inr'] ?>;
        function selectRegion(region) {
            document.getElementById('payment-step-1').style.display = 'none';
            if (region === 'outside') {
                document.getElementById('payment-step-outside').style.display = 'block';
            } else {
                document.getElementById('payment-step-india-loading').style.display = 'block';
                setTimeout(() => {
                    document.getElementById('payment-step-india-loading').style.display = 'none';
                    const upiUri = "upi://pay?pa=" + encodeURIComponent("<?= UPI_ID ?>") + "&pn=" + encodeURIComponent("MANGO NUMBER") + "&mc=0000&mode=02&purpose=00&am=" + activePriceInr + "&cu=INR";
                    const qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" + encodeURIComponent(upiUri);
                    document.getElementById('modal-qr-img').src = qrUrl;
                    document.getElementById('modal-upi-pay-btn').href = upiUri;
                    document.getElementById('payment-step-india-content').style.display = 'block';
                }, 1500);
            }
        }
        function validatePaymentForm() {
            const utr = document.getElementById('utr_number').value.trim();
            if (!/^\d{12}$/.test(utr)) { alert("Please enter a valid 12-digit numeric UTR number."); return false; }
            const fileInput = document.getElementById('payment_screenshot');
            if (fileInput.files.length === 0) { alert("Please select your payment screenshot."); return false; }
            const file = fileInput.files[0];
            if (!['jpg','jpeg','png','webp'].includes(file.name.split('.').pop().toLowerCase())) { alert("Only JPG, JPEG, PNG, and WEBP files are allowed."); return false; }
            if (file.size > 10 * 1024 * 1024) { alert("File size exceeds 10MB limit."); return false; }
            const btn = document.getElementById('submit-btn');
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<div style="width:16px;height:16px;border:2.5px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;display:inline-block;margin-right:8px;"></div> Submitting...';
            return true;
        }
    </script>
    <script src="assets/js/anti-devtools.js"></script>
</body>
</html>

<?php
/**
 * Mango Number - Core Configuration & Database Connection Helper
 */

// Set default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Production Error Security: Log errors internally, hide stack traces from public users
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
ini_set('error_log', $log_dir . '/php_errors.log');

// Determine if HTTPS is active or running locally
$is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
             || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Enforce security headers (XSS, CSP, HSTS clickjacking mitigation)
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy (allows local styles, scripts, fonts, Google Fonts, and Boxicons CDN)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' data: https://fonts.gstatic.com https://unpkg.com; img-src 'self' data: https:; connect-src 'self'; frame-src 'self';");

if ($is_secure) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Polyfill for getallheaders() on non-Apache/Nginx/FPM environments
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Configure secure session cookie options (XSS & CSRF protection)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
                 
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Session Hijacking Safeguard: Bind active session to browser User-Agent signature
if (isset($_SESSION['user_id'])) {
    $ua_sig = md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    if (!isset($_SESSION['user_agent_sig'])) {
        $_SESSION['user_agent_sig'] = $ua_sig;
    } elseif ($_SESSION['user_agent_sig'] !== $ua_sig) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Load .env variables natively
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Strip quotes
            $value = trim($value, '"\'');
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Seed CSRF token for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Credentials (configured for Hostinger mangonumbers.bond)
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'u266502536_mangouser');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '@Sidhu890');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'u266502536_mango');

// Business Configurations
define('USDT_RATE', 89.0);               // 1 USDT = 89 INR
define('UPI_ID', 'BHARATPE2V0K0Q7J2W84840@unitype'); // UPI Address for payment QR
define('ADMIN_WHATSAPP', '919303773240'); // Owner's WhatsApp number for SMS OTP delivery
define('UPLOAD_DIR', __DIR__ . '/uploads/'); // Screenshot storage directory

// Create screenshots upload directory if it doesn't exist (strict 0755 permissions)
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}


/**
 * Establish a PDO database connection
 * @return PDO|null
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $host = DB_HOST;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    try {
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass, $options);
    } catch (PDOException $e) {
        // Fallback for local XAMPP development environment
        try {
            $pdo = new PDO("mysql:host=127.0.0.1;dbname={$dbname};charset=utf8mb4", 'root', '', $options);
        } catch (PDOException $e2) {
            try {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=mango_number;charset=utf8mb4", 'root', '', $options);
            } catch (PDOException $e3) {
                try {
                    $pdo = new PDO("mysql:host=127.0.0.1;dbname=uclrhzsi_number;charset=utf8mb4", 'root', '', $options);
                } catch (PDOException $e4) {
                    return null;
                }
            }
        }
    }
        
        // Auto-migration & Schema verification (runs only once per process)
        static $migrated = false;
        if (!$migrated) {
            $migrated = true;
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS sections (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    slug VARCHAR(150) NOT NULL UNIQUE,
                    description TEXT DEFAULT NULL,
                    icon VARCHAR(255) DEFAULT 'bx-layer',
                    display_order INT DEFAULT 0,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB;");
            } catch (PDOException $e) {}

            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    section_id INT NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    country VARCHAR(100) DEFAULT 'Global',
                    price_cost_usd DECIMAL(10,2) DEFAULT 0.00,
                    price_cost_inr DECIMAL(10,2) DEFAULT 0.00,
                    price_usd DECIMAL(10,2) DEFAULT 0.00,
                    price_inr DECIMAL(10,2) DEFAULT 0.00,
                    stock_quantity INT DEFAULT 0,
                    availability_status VARCHAR(50) DEFAULT 'available',
                    icon VARCHAR(255) DEFAULT NULL,
                    badge VARCHAR(100) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
                ) ENGINE=InnoDB;");
            } catch (PDOException $e) {}

            try { $pdo->exec("ALTER TABLE users ADD COLUMN mobile VARCHAR(20) DEFAULT NULL AFTER username"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL AFTER mobile"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER role"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE users ADD COLUMN deletion_reason TEXT DEFAULT NULL AFTER status"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE complaints ADD COLUMN admin_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER admin_response"); } catch (PDOException $e) {}
            try { $pdo->exec("DELETE FROM complaints WHERE admin_deleted_at IS NOT NULL AND admin_deleted_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)"); } catch (PDOException $e) {}

            // Add performance indexes on purchases & users
            try { $pdo->exec("CREATE INDEX idx_purchases_user_status ON purchases (user_id, status)"); } catch (PDOException $e) {}
            try { $pdo->exec("CREATE INDEX idx_purchases_utr ON purchases (utr_number)"); } catch (PDOException $e) {}
            try { $pdo->exec("CREATE INDEX idx_users_email ON users (email)"); } catch (PDOException $e) {}

            // Migrate service_type columns to VARCHAR(50) to support Canva Premium
            try { $pdo->exec("ALTER TABLE catalog MODIFY COLUMN service_type VARCHAR(50) NOT NULL"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE products MODIFY COLUMN availability_status VARCHAR(50) NOT NULL DEFAULT 'available'"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE purchases MODIFY COLUMN service_type VARCHAR(50) NOT NULL"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE purchases ADD COLUMN product_id INT DEFAULT NULL AFTER catalog_id"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE purchases MODIFY COLUMN catalog_id INT DEFAULT NULL"); } catch (PDOException $e) {}

            // Auto-seed Canva Premium Lifetime if missing
            try {
                $stmt = $pdo->query("SELECT id FROM catalog WHERE name = 'Canva Premium Lifetime' LIMIT 1");
                if ($stmt->fetchColumn() == 0) {
                    $inst = $pdo->prepare("INSERT INTO catalog (service_type, name, country, price_cost_usd, price_cost_inr, price_usd, price_inr, stock, status) VALUES ('Canva', 'Canva Premium Lifetime', 'Global', 0.50, 40.00, 1.80, 150.00, 999, 'active')");
                    $inst->execute();
                }
            } catch (PDOException $e) {}

            // Auto-cleanup: Delete old screenshot files for approved purchases after 7 days
            try {
                $old_ss = $pdo->query("SELECT id, screenshot_path FROM purchases WHERE status = 'approved' AND screenshot_path IS NOT NULL AND created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $to_clean = $old_ss->fetchAll();
                foreach ($to_clean as $ss) {
                    $filepath = __DIR__ . '/' . $ss['screenshot_path'];
                    if (is_file($filepath)) @unlink($filepath);
                    $pdo->exec("UPDATE purchases SET screenshot_path = NULL WHERE id = " . (int)$ss['id']);
                }
            } catch (PDOException $e) {}
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS complaint_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    complaint_id INT NOT NULL,
                    sender ENUM('user', 'admin') NOT NULL,
                    message TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
                ) ENGINE=InnoDB;");

                // Migration: Transfer existing admin responses to complaint_messages table
                $pdo->exec("INSERT INTO complaint_messages (complaint_id, sender, message, created_at)
                            SELECT id, 'admin', admin_response, created_at FROM complaints 
                            WHERE admin_response IS NOT NULL AND admin_response != '' AND id NOT IN (
                                SELECT DISTINCT complaint_id FROM complaint_messages WHERE sender = 'admin'
                            )");
            } catch (PDOException $e) {}

            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key VARCHAR(50) PRIMARY KEY,
                    setting_value VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB;");
                
                $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('allow_signups', '1')");
                $stmt->execute();
                
                $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('allow_website_usage', '1')");
                $stmt->execute();
            } catch (PDOException $e) {}

            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                    ip_address VARCHAR(45) NOT NULL,
                    username VARCHAR(150) NOT NULL,
                    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY ip_time_idx (ip_address, attempted_at),
                    KEY user_time_idx (username, attempted_at)
                ) ENGINE=InnoDB;");
            } catch (PDOException $e) {}

            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    host VARCHAR(255) DEFAULT NULL,
                    port INT DEFAULT NULL,
                    username VARCHAR(255) DEFAULT NULL,
                    password TEXT DEFAULT NULL,
                    encryption VARCHAR(20) DEFAULT NULL,
                    from_email VARCHAR(255) DEFAULT NULL,
                    from_name VARCHAR(255) DEFAULT NULL,
                    active TINYINT DEFAULT 1
                ) ENGINE=InnoDB;");
            } catch (PDOException $e) {}

            try {
                $env_host = $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com';
                $env_port = (int)($_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?: 587);
                $env_user = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: 'ad166f001@smtp-brevo.com';
                $env_pass = $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: '';
                $env_enc = $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?: 'tls';
                $env_from = $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'deepakboy144@gmail.com';
                $env_name = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'Mango Numbers';

                $stmt = $pdo->query("SELECT id FROM smtp_settings WHERE active = 1 LIMIT 1");
                $existing_smtp = $stmt->fetch();
                if ($existing_smtp) {
                    if (!empty($env_user) && !empty($env_pass)) {
                        $up_stmt = $pdo->prepare("UPDATE smtp_settings SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ? WHERE id = ?");
                        $up_stmt->execute([$env_host, $env_port, $env_user, $env_pass, $env_enc, $env_from, $env_name, $existing_smtp['id']]);
                    }
                } else {
                    $inst = $pdo->prepare("INSERT INTO smtp_settings (host, port, username, password, encryption, from_email, from_name, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $inst->execute([$env_host, $env_port, $env_user, $env_pass, $env_enc, $env_from, $env_name]);
                }
            } catch (PDOException $e) {}
        }
        
        return $pdo;
}

function is_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Force logout for non-admins if website usage is disabled
    if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
        if (get_system_setting('allow_website_usage', '1') === '0') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['website_maintenance_flag'] = true;
            unset($_SESSION['user_id']);
            unset($_SESSION['username']);
            unset($_SESSION['role']);
            return false;
        }
    }
    
    // Verify user record still exists in the database
    $db = get_db_connection();
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT id, status FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if (!$user || $user['status'] === 'deleted') {
                // User has been deleted!
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                // Mark deleted flag for redirection handling
                $_SESSION['account_deleted_flag'] = true;
                
                // Clear active user session identifiers
                unset($_SESSION['user_id']);
                unset($_SESSION['username']);
                unset($_SESSION['role']);
                return false;
            }
        } catch (PDOException $e) {
            // DB error fallback to keep session alive during glitches
        }
    }
    return true;
}

/**
 * Utility to verify if user is admin
 * @return bool
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require login helper
 */
function require_login() {
    if (!is_logged_in()) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['account_deleted_flag'])) {
            unset($_SESSION['account_deleted_flag']);
            header("Location: account_deleted.php");
            exit;
        }
        if (isset($_SESSION['website_maintenance_flag'])) {
            unset($_SESSION['website_maintenance_flag']);
            header("Location: login.php?maintenance=1");
            exit;
        }
        header("Location: login.php");
        exit;
    }
}

/**
 * Require admin helper
 */
function require_admin() {
    if (!is_admin()) {
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Get a system setting value
 * @param string $key
 * @param string $default
 * @return string
 */
function get_system_setting($key, $default = '') {
    $db = get_db_connection();
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : $default;
        } catch (PDOException $e) {}
    }
    return $default;
}

/**
 * Set a system setting value
 * @param string $key
 * @param string $value
 * @return bool
 */
function set_system_setting($key, $value) {
    $db = get_db_connection();
    if ($db) {
        try {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = ?");
            return $stmt->execute([$key, $value, $value]);
        } catch (PDOException $e) {}
    }
    return false;
}

/**
 * Send instant Telegram Bot notification for payments, orders, signups & tickets
 * @param string $message
 * @return bool
 */
function send_telegram_notification($message) {
    $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '8787330129:AAH2IbQgbDvEBi1p4BSOLmEAo6mJWgkP2BU';
    $chat_id = $_ENV['TELEGRAM_CHAT_ID'] ?? getenv('TELEGRAM_CHAT_ID') ?: '8861462825';
    
    if (empty($token) || empty($chat_id)) return false;

    // Convert literal '\n' sequences into actual newlines for perfect Telegram formatting
    $message = str_replace('\n', "\n", $message);

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    @curl_exec($ch);
    @curl_close($ch);
    return true;
}


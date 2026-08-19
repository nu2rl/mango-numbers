<?php
/**
 * Mango Number - Database Initialization & PRD Schema Migration Script
 * Supports 2-Level Dynamic Catalog (Sections -> Houses/Products)
 */

require_once __DIR__ . '/config.php';

try {
    // 1. Connect to MySQL server
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    echo "<h3>Mango Number Database Initialization & PRD Migration</h3>";
    echo "Connecting to MySQL server... Connected.<br>";

    // 2. Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "Database `" . DB_NAME . "` checked/created.<br>";

    // 3. Switch to database
    $pdo->exec("USE `" . DB_NAME . "`;");
    echo "Switched to database `" . DB_NAME . "`.<br>";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Core Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) DEFAULT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        username VARCHAR(150) NOT NULL UNIQUE,
        mobile VARCHAR(20) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        avatar_path VARCHAR(255) DEFAULT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        status ENUM('active', 'disabled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL;");
    } catch (PDOException $e) {}

    echo "- Users table verified.<br>";

    // Email OTPs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        otp VARCHAR(255) NOT NULL,
        purpose ENUM('signup', 'forgot_password') NOT NULL,
        expires_at DATETIME NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        attempts INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    echo "- Email OTPs table verified.<br>";

    // LEVEL 1: SECTIONS Table
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
    echo "- Sections table verified.<br>";

    // LEVEL 2: HOUSES / PRODUCTS Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        slug VARCHAR(150) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        country VARCHAR(100) DEFAULT 'Global',
        price_cost_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_cost_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        stock_type ENUM('finite', 'unlimited') DEFAULT 'finite',
        stock_quantity INT NOT NULL DEFAULT 0,
        availability_status ENUM('available', 'out_of_stock', 'disabled') DEFAULT 'available',
        status ENUM('active', 'inactive') DEFAULT 'active',
        display_order INT DEFAULT 0,
        badge VARCHAR(50) DEFAULT NULL,
        icon VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "- Products / Houses table verified.<br>";

    // Legacy catalog table for backwards safety
    $pdo->exec("CREATE TABLE IF NOT EXISTS catalog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_type VARCHAR(100) NOT NULL,
        name VARCHAR(100) NOT NULL,
        country VARCHAR(100) NOT NULL,
        price_cost_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_cost_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_usd DECIMAL(10,2) NOT NULL,
        price_inr DECIMAL(10,2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active'
    ) ENGINE=InnoDB;");

    // Purchases Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        catalog_id INT DEFAULT NULL,
        product_id INT DEFAULT NULL,
        service_type VARCHAR(100) NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        price_cost_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_paid_inr DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'UPI',
        utr_number VARCHAR(100) NOT NULL,
        screenshot_path VARCHAR(255) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        virtual_number_provided VARCHAR(100) DEFAULT NULL,
        otp_provided VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "- Purchases table verified.<br>";

    // Complaints Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        purchase_id INT DEFAULT NULL,
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('open', 'resolved') DEFAULT 'open',
        admin_response TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS complaint_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complaint_id INT NOT NULL,
        sender ENUM('user', 'admin') NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // System Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // SMTP Settings Table
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

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Seed default users if empty
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $adminPassword = password_hash('muslimhu108', PASSWORD_DEFAULT);
        $userPassword = password_hash('user123', PASSWORD_DEFAULT);
        
        $insertUser = $pdo->prepare("INSERT INTO users (name, email, username, password, role) VALUES (?, ?, ?, ?, ?)");
        $insertUser->execute(['Administrator', 'deepakboy144@gmail.com', 'nutrl786', $adminPassword, 'admin']);
        $insertUser->execute(['Standard User', 'user@mangonumbers.com', 'user', $userPassword, 'user']);
    }

    // Seed Sections if empty
    $secCount = $pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
    if ($secCount == 0) {
        $secStmt = $pdo->prepare("INSERT INTO sections (name, slug, description, icon, display_order) VALUES (?, ?, ?, ?, ?)");
        $secStmt->execute(['Telegram Numbers', 'telegram-numbers', 'Instant Telegram OTP virtual numbers', 'bxl-telegram', 1]);
        $tgSecId = $pdo->lastInsertId();

        $secStmt->execute(['WhatsApp Numbers', 'whatsapp-numbers', 'High quality WhatsApp verification numbers', 'bxl-whatsapp', 2]);
        $waSecId = $pdo->lastInsertId();

        // Migrate items from legacy catalog or seed default products
        $catItems = $pdo->query("SELECT * FROM catalog")->fetchAll();
        $prodStmt = $pdo->prepare("INSERT INTO products (section_id, name, country, price_cost_usd, price_cost_inr, price_usd, price_inr, stock_quantity, availability_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!empty($catItems)) {
            foreach ($catItems as $ci) {
                $targetSecId = ($ci['service_type'] === 'WhatsApp') ? $waSecId : $tgSecId;
                $status = ($ci['stock'] > 0) ? 'available' : 'out_of_stock';
                $prodStmt->execute([$targetSecId, $ci['name'], $ci['country'], $ci['price_cost_usd'], $ci['price_cost_inr'], $ci['price_usd'], $ci['price_inr'], $ci['stock'], $status]);
            }
        } else {
            // Default seed
            $prodStmt->execute([$tgSecId, 'India Telegram', 'India', 0.28, 25.00, 0.51, 45.00, 112, 'available']);
            $prodStmt->execute([$waSecId, 'USA WhatsApp', 'USA', 1.69, 150.00, 2.81, 250.00, 10, 'available']);
        }
    }

    echo "<br><strong style='color:green;'>Success! Database & PRD schema initialized.</strong><br>";

} catch (PDOException $e) {
    echo "<br><strong style='color:red;'>Initialization Failed:</strong> " . $e->getMessage() . "<br>";
}

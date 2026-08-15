<?php
/**
 * Mango Number - Database Initialization & Seeding Script
 */

require_once __DIR__ . '/config.php';

try {
    // 1. Connect to MySQL server (without selecting database to create it)
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    echo "<h3>Mango Number Database Initialization</h3>";
    echo "Connecting to MySQL server... Connected.<br>";

    // 2. Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "Database `" . DB_NAME . "` checked/created.<br>";

    // 3. Switch to database
    $pdo->exec("USE `" . DB_NAME . "`;");
    echo "Switched to database `" . DB_NAME . "`.<br>";

    // 4. Create Tables
    echo "Creating schemas...<br>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DROP TABLE IF EXISTS complaint_messages;");
    $pdo->exec("DROP TABLE IF EXISTS complaints;");
    $pdo->exec("DROP TABLE IF EXISTS purchases;");
    $pdo->exec("DROP TABLE IF EXISTS users;");
    $pdo->exec("DROP TABLE IF EXISTS catalog;");
    $pdo->exec("DROP TABLE IF EXISTS email_otps;");
    $pdo->exec("DROP TABLE IF EXISTS smtp_settings;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) DEFAULT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        username VARCHAR(150) NOT NULL UNIQUE,
        mobile VARCHAR(20) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    echo "- Users table created.<br>";

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
    echo "- Email OTPs table created.<br>";

    // Numbers Catalog Table (Includes Cost price/Rate Bought columns)
    $pdo->exec("CREATE TABLE IF NOT EXISTS catalog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_type ENUM('Telegram', 'WhatsApp') NOT NULL,
        name VARCHAR(100) NOT NULL,
        country VARCHAR(100) NOT NULL,
        price_cost_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_cost_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_usd DECIMAL(10,2) NOT NULL,
        price_inr DECIMAL(10,2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active'
    ) ENGINE=InnoDB;");
    echo "- Catalog table created.<br>";

    // Purchases/Orders Table (Stores specific cost price at moment of purchase)
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        catalog_id INT NOT NULL,
        service_type ENUM('Telegram', 'WhatsApp') NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        price_cost_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_paid_inr DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(20) DEFAULT 'UPI',
        utr_number VARCHAR(50) NOT NULL,
        screenshot_path VARCHAR(255) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        virtual_number_provided VARCHAR(50) DEFAULT NULL,
        otp_provided VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (catalog_id) REFERENCES catalog(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "- Purchases table created.<br>";

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
    echo "- Complaints table created.<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS complaint_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complaint_id INT NOT NULL,
        sender ENUM('user', 'admin') NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "- Complaint messages table created.<br>";

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
    echo "- SMTP settings table created.<br>";

    // Seed default SMTP settings
    $env_host = $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com';
    $env_port = $_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?: 587;
    $env_user = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: '';
    $env_pass = $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: '';
    $env_enc = $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?: 'tls';
    $env_from = $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@mangonumbers.com';
    $env_name = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'Mango Numbers';

    $inst = $pdo->prepare("INSERT INTO smtp_settings (host, port, username, password, encryption, from_email, from_name, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $inst->execute([$env_host, (int)$env_port, $env_user, $env_pass, $env_enc, $env_from, $env_name]);
    echo "- Seeded default SMTP settings from environment configuration.<br>";

    // 5. Seed Users (Default Admin and Default Test User)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        echo "Seeding default users...<br>";
        
        $adminPassword = password_hash('muslimhu108', PASSWORD_DEFAULT);
        $userPassword = password_hash('user123', PASSWORD_DEFAULT);
        
        $insertUser = $pdo->prepare("INSERT INTO users (name, email, username, password, role) VALUES (?, ?, ?, ?, ?)");
        $insertUser->execute(['Administrator', 'admin@mangonumbers.com', 'nutrl786', $adminPassword, 'admin']);
        $insertUser->execute(['Standard User', 'user@mangonumbers.com', 'user', $userPassword, 'user']);
        
        echo "- Default admin created (username: <strong>nutrl786</strong>, password: <strong>muslimhu108</strong>)<br>";
        echo "- Default user created (username: <strong>user</strong>, password: <strong>user123</strong>)<br>";
    }

    // 6. Seed Catalog Data (Your custom countries and stock list!)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM catalog");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        echo "Seeding number catalogs...<br>";
        
        $items = [
            // Service, Name, Country, Cost USD, Cost INR, Sale USD, Sale INR, Stock
            ['Telegram', 'India', 'India', 0.28, 25, 0.51, 45, 112],
            ['Telegram', 'india-best-quality', 'India', 0.34, 30, 0.56, 50, 48],
            ['Telegram', 'india-free-as-bird', 'India', 0.17, 15, 0.28, 25, 122],
            ['Telegram', 'india-spam-acc', 'India', 0.11, 10, 0.18, 16, 98],
            ['Telegram', 'indian-new-acc', 'India', 0.14, 12, 0.25, 22, 339],
            ['Telegram', 'indian-old-2020', 'India', 0.67, 60, 1.12, 100, 34],
            ['Telegram', 'indian-old-2021', 'India', 0.45, 40, 0.79, 70, 51],
            ['Telegram', 'indian-old-2022', 'India', 0.43, 38, 0.73, 65, 123],
            ['Telegram', 'indian-old-2023', 'India', 0.39, 35, 0.67, 60, 135],
            ['Telegram', 'indian-old-2024', 'India', 0.34, 30, 0.56, 50, 78],
            ['Telegram', 'myanmar', 'Myanmar', 0.20, 18, 0.34, 30, 13],
            ['Telegram', 'usa', 'USA', 0.22, 20, 0.39, 35, 17],
            ['Telegram', 'Vietnam', 'Vietnam', 0.43, 38, 0.70, 62, 42],
            ['Telegram', 'Canada', 'Canada', 0.39, 35, 0.65, 58, 30],
            ['Telegram', 'Chile', 'Chile', 0.51, 45, 0.81, 72, 33],
            ['Telegram', 'Afghanistan', 'Afghanistan', 0.51, 45, 0.81, 72, 33],
            ['Telegram', 'Greenland', 'Greenland', 0.90, 80, 1.51, 134, 42],
            ['Telegram', 'United Arab Emirates', 'United Arab Emirates', 1.35, 120, 2.15, 191, 32],
            ['Telegram', 'Fiji', 'Fiji', 0.79, 70, 1.29, 115, 40],
            ['Telegram', 'Russia', 'Russia', 1.35, 120, 2.25, 200, 39],
            ['Telegram', 'France', 'France', 1.01, 90, 1.72, 153, 38],
            ['Telegram', 'China', 'China', 1.07, 95, 1.72, 153, 42],
            ['Telegram', 'Turkey', 'Turkey', 0.84, 75, 1.39, 124, 48],
            ['Telegram', 'Germany', 'Germany', 0.90, 80, 1.55, 138, 36],
            
            // WhatsApp Services (Premium)
            ['WhatsApp', 'USA WhatsApp', 'USA', 1.69, 150, 2.81, 250, 10],
            ['WhatsApp', 'Philippines WhatsApp', 'Philippines', 0.67, 60, 1.12, 100, 8]
        ];
        
        $insertItem = $pdo->prepare("INSERT INTO catalog (service_type, name, country, price_cost_usd, price_cost_inr, price_usd, price_inr, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $insertItem->execute($item);
        }
        echo "- Seeded " . count($items) . " virtual number catalog items successfully!<br>";
    }
    
    echo "<br><strong style='color:green;'>Success! Database successfully initialized.</strong><br>";
    echo "<a href='index.php'>Go to Landing Page</a>";

} catch (PDOException $e) {
    echo "<br><strong style='color:red;'>Initialization Failed:</strong> " . $e->getMessage() . "<br>";
    echo "Check if your local MySQL service (XAMPP/MAMP) is active and running.";
}

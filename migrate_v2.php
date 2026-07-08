<?php
// Idempotent v2 migration — safe to include from db.php

// Helper to add column if missing
if (!function_exists('addColumnIfMissing')) {
function addColumnIfMissing($pdo, $table, $column, $definition) {
    try {
        $pdo->query("SELECT $column FROM $table LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $definition");
    }
}
}

// ALTER users (always runs, idempotent)
addColumnIfMissing($pdo, 'users', 'role', "role ENUM('provider','public') NOT NULL DEFAULT 'provider' AFTER id");
addColumnIfMissing($pdo, 'users', 'tier_id', "tier_id INT DEFAULT NULL AFTER email");
addColumnIfMissing($pdo, 'users', 'is_verified', "is_verified TINYINT(1) DEFAULT 0 AFTER tier_id");
addColumnIfMissing($pdo, 'users', 'business_name', "business_name VARCHAR(255) DEFAULT NULL AFTER is_verified");
addColumnIfMissing($pdo, 'users', 'bio', "bio TEXT DEFAULT NULL AFTER business_name");
addColumnIfMissing($pdo, 'users', 'totp_secret', "totp_secret VARCHAR(64) DEFAULT NULL AFTER bio");
addColumnIfMissing($pdo, 'users', 'totp_enabled', "totp_enabled TINYINT(1) DEFAULT 0 AFTER totp_secret");
addColumnIfMissing($pdo, 'users', 'recovery_codes', "recovery_codes TEXT DEFAULT NULL AFTER totp_enabled");
addColumnIfMissing($pdo, 'users', 'google_id', "google_id VARCHAR(255) DEFAULT NULL AFTER recovery_codes");
addColumnIfMissing($pdo, 'users', 'google_email', "google_email VARCHAR(255) DEFAULT NULL AFTER google_id");

// ALTER services (always runs, idempotent)
addColumnIfMissing($pdo, 'services', 'physical_address', "physical_address TEXT DEFAULT NULL AFTER description");
addColumnIfMissing($pdo, 'services', 'latitude', "latitude DECIMAL(10,8) DEFAULT NULL AFTER physical_address");
addColumnIfMissing($pdo, 'services', 'longitude', "longitude DECIMAL(11,8) DEFAULT NULL AFTER physical_address");
addColumnIfMissing($pdo, 'services', 'status', "status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active' AFTER views");
addColumnIfMissing($pdo, 'services', 'is_featured', "is_featured TINYINT(1) DEFAULT 0 AFTER status");
addColumnIfMissing($pdo, 'services', 'featured_until', "featured_until DATE DEFAULT NULL AFTER is_featured");

// New tables (IF NOT EXISTS = idempotent)
$pdo->exec("CREATE TABLE IF NOT EXISTS pricing_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL,
    max_posts INT DEFAULT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0,
    duration_days INT DEFAULT NULL, is_subscription TINYINT(1) DEFAULT 0,
    auto_approve TINYINT(1) DEFAULT 0, sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    tier_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1, payment_ref VARCHAR(255) DEFAULT NULL,
    renewal_count INT DEFAULT 0, cancelled_at DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES pricing_tiers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS featured_listings (
    id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL,
    set_by INT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (set_by) REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS service_images (
    id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL, is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY, from_user_id INT NOT NULL,
    to_user_id INT NOT NULL, service_id INT DEFAULT NULL,
    rating TINYINT NOT NULL, comment TEXT,
    is_approved TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY, `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    subscription_id INT DEFAULT NULL, invoice_no VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL, status ENUM('paid','unpaid','cancelled','refunded') NOT NULL DEFAULT 'paid',
    items_json TEXT DEFAULT NULL, issued_at DATETIME DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ALTER user_subscriptions (idempotent)
addColumnIfMissing($pdo, 'user_subscriptions', 'renewal_count', "renewal_count INT DEFAULT 0");
addColumnIfMissing($pdo, 'user_subscriptions', 'cancelled_at', "cancelled_at DATE DEFAULT NULL");

// Seed pricing tiers (only if empty)
$stmtCheck = $pdo->query("SELECT COUNT(*) FROM pricing_tiers");
if ($stmtCheck->fetchColumn() == 0) {
    $tiers = [
        ['Free', 3, 0, null, 0, 0, 1],
        ['One-Time', 20, 5000, null, 0, 0, 2],
        ['Monthly', null, 500, 30, 1, 1, 3],
        ['Yearly', null, 3500, 365, 1, 1, 4],
    ];
    $stmt = $pdo->prepare("INSERT INTO pricing_tiers (name, max_posts, price, duration_days, is_subscription, auto_approve, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($tiers as $t) $stmt->execute($t);
}

// Seed settings
$settings = [
    ['google_maps_api_key', ''],
    ['site_name', 'RDL Service Directory'],
    ['contact_email', 'info@servicedirectory.lk'],
];
$stmtS = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
foreach ($settings as $s) $stmtS->execute($s);

// Set existing users to provider role + free tier
$freeTierId = $pdo->query("SELECT id FROM pricing_tiers WHERE name = 'Free' LIMIT 1")->fetchColumn();
if ($freeTierId) {
    $pdo->exec("UPDATE users SET role = 'provider' WHERE role IS NULL OR role = ''");
    $pdo->exec("UPDATE users SET tier_id = $freeTierId WHERE tier_id IS NULL");
}

$pdo->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('migration_v2', '1')");

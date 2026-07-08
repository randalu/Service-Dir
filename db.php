<?php
require_once __DIR__ . '/includes/config.php';

if (APP_ENV === 'development') {
    $dbPath = __DIR__ . '/../db.sqlite';
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA journal_mode=WAL");
    $pdo->exec("PRAGMA foreign_keys=ON");

    // Idempotent migration for SQLite — add columns if missing
    $colMigrations = [
        ['user_subscriptions', 'renewal_count', 'INTEGER DEFAULT 0'],
        ['user_subscriptions', 'cancelled_at', 'TEXT DEFAULT NULL'],
    ];
    foreach ($colMigrations as $cm) {
        try {
            $pdo->query("SELECT {$cm[1]} FROM {$cm[0]} LIMIT 1");
        } catch (Exception $e) {
            $pdo->exec("ALTER TABLE {$cm[0]} ADD COLUMN {$cm[1]} {$cm[2]}");
        }
    }

    // Idempotent migration for SQLite — add new columns/tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS pricing_tiers (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,
        max_posts INTEGER DEFAULT NULL, price REAL NOT NULL DEFAULT 0,
        duration_days INTEGER DEFAULT NULL, is_subscription INTEGER DEFAULT 0,
        auto_approve INTEGER DEFAULT 0, sort_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
        tier_id INTEGER NOT NULL, start_date TEXT NOT NULL, end_date TEXT DEFAULT NULL,
        is_active INTEGER DEFAULT 1, payment_ref TEXT DEFAULT NULL,
        renewal_count INTEGER DEFAULT 0, cancelled_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (tier_id) REFERENCES pricing_tiers(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS featured_listings (
        id INTEGER PRIMARY KEY AUTOINCREMENT, service_id INTEGER NOT NULL,
        set_by INTEGER NOT NULL, start_date TEXT NOT NULL, end_date TEXT NOT NULL,
        amount_paid REAL DEFAULT 0, created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (set_by) REFERENCES admins(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT, service_id INTEGER NOT NULL,
        image_path TEXT NOT NULL, is_primary INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT, from_user_id INTEGER NOT NULL,
        to_user_id INTEGER NOT NULL, service_id INTEGER DEFAULT NULL,
        rating INTEGER NOT NULL, comment TEXT,
        is_approved INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT, \"key\" TEXT NOT NULL UNIQUE,
        \"value\" TEXT, created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
        subscription_id INTEGER DEFAULT NULL, invoice_no TEXT NOT NULL UNIQUE,
        amount REAL NOT NULL, status TEXT NOT NULL DEFAULT 'paid',
        items_json TEXT DEFAULT NULL, issued_at TEXT DEFAULT NULL,
        paid_at TEXT DEFAULT NULL, created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL
    )");
    return;
}

// Production/MySQL mode — auto-setup if needed
$maxAttempts = 3;
for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        break;
    } catch (PDOException $e) {
        if ($attempt < $maxAttempts && $e->getCode() == 1049) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            $schemaFile = __DIR__ . '/../schema.mysql.sql';
            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                $statements = array_filter(array_map('trim', explode(';', $schema)));
                foreach ($statements as $stmt) {
                    if (stripos($stmt, 'CREATE TABLE') === 0) {
                        $pdo->exec($stmt . ';');
                    }
                }
            }
            $count = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            if ($count == 0) {
                $cats = ['Plumbing','Electrical','Carpentry','Cleaning','Painting','Gardening','AC Repair','Moving','Tutoring','Photography','Catering','Transport','Medical','IT Support','Beauty'];
                $s = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                foreach ($cats as $c) $s->execute([$c]);
                $areas = ['Raddoluwa','Seeduwa','Kandana','Katunayake','Negombo','Ja-Ela'];
                $s = $pdo->prepare("INSERT INTO areas (name) VALUES (?)");
                foreach ($areas as $a) $s->execute([$a]);
                $pdo->prepare("INSERT INTO admins (username,password,role,full_name) VALUES (?,?,?,?)")
                    ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'superadmin', 'Super Admin']);
                $pdo->prepare("INSERT INTO users (first_name,last_name,mobile,email,password) VALUES (?,?,?,?,?)")
                    ->execute(['John','Doe','+94771234567','john@example.com', password_hash('test123', PASSWORD_DEFAULT)]);
                $uid = $pdo->lastInsertId();
                $svcs = [['Expert Plumbing Repairs',1,1,'Fast and reliable plumbing.',150],['Home Electrical Wiring',2,2,'Professional electrical installation.',85],['Garden Landscaping',6,1,'Complete garden design.',42]];
                $s = $pdo->prepare("INSERT INTO services (user_id,title,category_id,area_id,description,views) VALUES (?,?,?,?,?,?)");
                foreach ($svcs as $svc) $s->execute([$uid, $svc[0], $svc[1], $svc[2], $svc[3], $svc[4]]);
            }
            continue;
        }
        throw $e;
    }
}

// Auto-create v1 tables if missing (empty database)
try {
    $pdo->query("SELECT COUNT(*) FROM categories");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS areas (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, role ENUM('superadmin','moderator') NOT NULL DEFAULT 'moderator', full_name VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, mobile VARCHAR(20) NOT NULL UNIQUE, email VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, profile_img VARCHAR(255) DEFAULT 'default.jpg', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, category_id INT NOT NULL, area_id INT NOT NULL, description TEXT, views INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (category_id) REFERENCES categories(id), FOREIGN KEY (area_id) REFERENCES areas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed data only if categories table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($count == 0) {
        $cats = ['Plumbing','Electrical','Carpentry','Cleaning','Painting','Gardening','AC Repair','Moving','Tutoring','Photography','Catering','Transport','Medical','IT Support','Beauty'];
        $s = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        foreach ($cats as $c) $s->execute([$c]);
        $areas = ['Raddoluwa','Seeduwa','Kandana','Katunayake','Negombo','Ja-Ela'];
        $s = $pdo->prepare("INSERT INTO areas (name) VALUES (?)");
        foreach ($areas as $a) $s->execute([$a]);
        $pdo->prepare("INSERT INTO admins (username,password,role,full_name) VALUES (?,?,?,?)")
            ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'superadmin', 'Super Admin']);
        $pdo->prepare("INSERT INTO users (first_name,last_name,mobile,email,password) VALUES (?,?,?,?,?)")
            ->execute(['John','Doe','+94771234567','john@example.com', password_hash('test123', PASSWORD_DEFAULT)]);
        $uid = $pdo->lastInsertId();
        $svcs = [['Expert Plumbing Repairs',1,1,'Fast and reliable plumbing.',150],['Home Electrical Wiring',2,2,'Professional electrical installation.',85],['Garden Landscaping',6,1,'Complete garden design.',42]];
        $s = $pdo->prepare("INSERT INTO services (user_id,title,category_id,area_id,description,views) VALUES (?,?,?,?,?,?)");
        foreach ($svcs as $svc) $s->execute([$uid, $svc[0], $svc[1], $svc[2], $svc[3], $svc[4]]);
    }
}

// Auto-run v2 migration (idempotent — safe on every request)
if (APP_ENV !== 'development') {
    try { include_once __DIR__ . '/migrate_v2.php'; } catch (Exception $e) {}
}

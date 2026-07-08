<?php
require_once __DIR__ . '/includes/config.php';

// Force SQLite for setup
$dbPath = __DIR__ . '/../db.sqlite';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA journal_mode=WAL");
$pdo->exec("PRAGMA foreign_keys=ON");

// Run schema
$schema = file_get_contents(__DIR__ . '/../schema.sql');
$pdo->exec($schema);

// Check if already seeded
$count = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
if ($count > 0) {
    echo "Database already seeded. Found $count categories.\n";
    exit;
}

// Seed categories
$categories = [
    'Plumbing', 'Electrical', 'Carpentry', 'Cleaning', 'Painting',
    'Gardening', 'AC Repair', 'Moving', 'Tutoring', 'Photography',
    'Catering', 'Transport', 'Medical', 'IT Support', 'Beauty'
];
$stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
foreach ($categories as $cat) {
    $stmt->execute([$cat]);
}
echo "Seeded " . count($categories) . " categories.\n";

// Seed areas
$areas = ['Raddoluwa', 'Seeduwa', 'Kandana', 'Katunayake', 'Negombo', 'Ja-Ela'];
$stmt = $pdo->prepare("INSERT INTO areas (name) VALUES (?)");
foreach ($areas as $area) {
    $stmt->execute([$area]);
}
echo "Seeded " . count($areas) . " areas.\n";

// Create admin user
$adminPass = password_hash('admin123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO admins (username, password, role, full_name) VALUES (?, ?, ?, ?)")
    ->execute(['admin', $adminPass, 'superadmin', 'Super Admin']);
echo "Created admin user (username: admin, password: admin123)\n";

// Seed pricing tiers
$tiers = [
    ['Free', 3, 0, null, 0, 0, 1],
    ['One-Time', 20, 5000, null, 0, 0, 2],
    ['Monthly', null, 500, 30, 1, 1, 3],
    ['Yearly', null, 3500, 365, 1, 1, 4],
];
$stmt = $pdo->prepare("INSERT OR IGNORE INTO pricing_tiers (name, max_posts, price, duration_days, is_subscription, auto_approve, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($tiers as $t) {
    $stmt->execute($t);
}
echo "Seeded " . count($tiers) . " pricing tiers.\n";

// Create test user + services
$testPass = password_hash('test123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (first_name, last_name, mobile, email, password) VALUES (?, ?, ?, ?, ?)")
    ->execute(['John', 'Doe', '+94771234567', 'john@example.com', $testPass]);
$userId = $pdo->lastInsertId();

$services = [
    ['Expert Plumbing Repairs', 1, 1, 'Fast and reliable plumbing services for your home.'],
    ['Home Electrical Wiring', 2, 2, 'Professional electrical installation and repairs.'],
    ['Garden Landscaping', 6, 1, 'Complete garden design and maintenance.'],
];
$stmt = $pdo->prepare("INSERT INTO services (user_id, title, category_id, area_id, description, views) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($services as $i => $svc) {
    $stmt->execute([$userId, $svc[0], $svc[1], $svc[2], $svc[3], rand(10, 200)]);
}
echo "Created test user (mobile: 0771234567, password: test123)\n";
echo "Created " . count($services) . " sample service ads.\n\n";
echo "Setup complete! Run: .\start.ps1\n";

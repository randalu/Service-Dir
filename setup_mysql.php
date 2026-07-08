<?php
/**
 * MySQL auto-setup: creates database, tables, and seeds sample data.
 * Run via browser or CLI: php setup_mysql.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'srv';

try {
    // Connect without database first to create it
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    $isCli = php_sapi_name() === 'cli';
    $echo = function($msg) use ($isCli) {
        echo $msg . ($isCli ? PHP_EOL : "<br>");
    };

    $echo("=== Service Directory - Database Setup ===");

    // Import schema
    $schemaFile = __DIR__ . '/../schema.mysql.sql';
    if (!file_exists($schemaFile)) {
        $echo("ERROR: schema.mysql.sql not found at $schemaFile");
        exit(1);
    }

    $schema = file_get_contents($schemaFile);
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if (stripos($stmt, 'CREATE TABLE') === 0) {
            $pdo->exec($stmt . ';');
        }
    }
    $echo("Tables created.");

    // Check if already seeded
    $count = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($count > 0) {
        $echo("Database already seeded ($count categories found).");
        exit(0);
    }

    // Seed categories
    $categories = [
        'Plumbing', 'Electrical', 'Carpentry', 'Cleaning', 'Painting',
        'Gardening', 'AC Repair', 'Moving', 'Tutoring', 'Photography',
        'Catering', 'Transport', 'Medical', 'IT Support', 'Beauty'
    ];
    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
    foreach ($categories as $cat) $stmt->execute([$cat]);
    $echo("Seeded " . count($categories) . " categories.");

    // Seed areas
    $areas = ['Raddoluwa', 'Seeduwa', 'Kandana', 'Katunayake', 'Negombo', 'Ja-Ela'];
    $stmt = $pdo->prepare("INSERT INTO areas (name) VALUES (?)");
    foreach ($areas as $area) $stmt->execute([$area]);
    $echo("Seeded " . count($areas) . " areas.");

    // Admin user
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins (username, password, role, full_name) VALUES (?, ?, ?, ?)")
        ->execute(['admin', $adminPass, 'superadmin', 'Super Admin']);
    $echo("Admin created: admin / admin123");

    // Test user
    $testPass = password_hash('test123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (first_name, last_name, mobile, email, password) VALUES (?, ?, ?, ?, ?)")
        ->execute(['John', 'Doe', '+94771234567', 'john@example.com', $testPass]);
    $userId = $pdo->lastInsertId();
    $echo("User created: 0771234567 / test123");

    // Seed services
    $services = [
        ['Expert Plumbing Repairs', 1, 1, 'Fast and reliable plumbing services for your home.', 150],
        ['Home Electrical Wiring', 2, 2, 'Professional electrical installation and repairs.', 85],
        ['Garden Landscaping', 6, 1, 'Complete garden design and maintenance services.', 42],
    ];
    $stmt = $pdo->prepare("INSERT INTO services (user_id, title, category_id, area_id, description, views) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($services as $s) $stmt->execute([$userId, $s[0], $s[1], $s[2], $s[3], $s[4]]);
    $echo("Seeded " . count($services) . " sample service ads.");

    $echo("Setup complete! Visit http://localhost/srv/");

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit(1);
}

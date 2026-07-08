<?php
/**
 * Daily cron: processes expired subscriptions.
 * Run daily via: php srv/cron/subscriptions.php
 * Or schedule: 0 0 * * * php /path/to/srv/cron/subscriptions.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';

$today = date('Y-m-d');
$log = [];

// 1) Expire subscriptions where end_date has passed
$stmt = $pdo->prepare("
    SELECT us.id, us.user_id, us.tier_id, t.name AS tier_name, t.is_subscription
    FROM user_subscriptions us
    JOIN pricing_tiers t ON us.tier_id = t.id
    WHERE us.is_active = 1 AND us.end_date IS NOT NULL AND us.end_date < ?
");
$stmt->execute([$today]);
$expired = $stmt->fetchAll();

$freeTierId = $pdo->query("SELECT id FROM pricing_tiers WHERE name = 'Free' LIMIT 1")->fetchColumn();

foreach ($expired as $sub) {
    $pdo->beginTransaction();
    try {
        // Mark subscription inactive
        $pdo->prepare("UPDATE user_subscriptions SET is_active = 0 WHERE id = ?")->execute([$sub['id']]);

        // Revert user to Free tier
        if ($freeTierId) {
            $pdo->prepare("UPDATE users SET tier_id = ?, is_verified = 0 WHERE id = ?")->execute([$freeTierId, $sub['user_id']]);
        }

        $pdo->commit();
        $log[] = "Expired subscription #{$sub['id']} ({$sub['tier_name']}) for user #{$sub['user_id']}";
    } catch (Exception $e) {
        $pdo->rollBack();
        $log[] = "FAILED to expire subscription #{$sub['id']}: " . $e->getMessage();
    }
}

// 2) Auto-expire featured listings where featured_until has passed
$pdo->exec("UPDATE services SET is_featured = 0, featured_until = NULL WHERE featured_until IS NOT NULL AND featured_until < CURDATE()");

// Log results
foreach ($log as $entry) {
    error_log("[cron/subscriptions] $entry");
}

echo count($log) . " subscription(s) processed.\n";
if ($log) {
    echo implode("\n", $log) . "\n";
}
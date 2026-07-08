<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

$action = $_GET['action'] ?? '';
$subId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Manual expiry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'expire' && $subId) {
    $stmt = $pdo->prepare("SELECT user_id, tier_id FROM user_subscriptions WHERE id = ? AND is_active = 1");
    $stmt->execute([$subId]);
    $sub = $stmt->fetch();
    if ($sub) {
        $freeTierId = $pdo->query("SELECT id FROM pricing_tiers WHERE name = 'Free' LIMIT 1")->fetchColumn();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE user_subscriptions SET is_active = 0, cancelled_at = ? WHERE id = ?")->execute([date('Y-m-d'), $subId]);
            if ($freeTierId) {
                $pdo->prepare("UPDATE users SET tier_id = ?, is_verified = 0 WHERE id = ?")->execute([$freeTierId, $sub['user_id']]);
            }
            $pdo->commit();
            header('Location: manage_subscriptions.php?msg=Subscription expired');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Failed: " . $e->getMessage();
        }
    }
}

// Manual renew — creates a new subscription period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'renew' && $subId) {
    $stmt = $pdo->prepare("SELECT us.*, t.duration_days, t.is_subscription, t.price, t.name AS tier_name FROM user_subscriptions us JOIN pricing_tiers t ON us.tier_id = t.id WHERE us.id = ?");
    $stmt->execute([$subId]);
    $sub = $stmt->fetch();
    if ($sub && $sub['is_subscription'] && $sub['duration_days']) {
        $pdo->beginTransaction();
        try {
            $start = date('Y-m-d');
            $end = date('Y-m-d', strtotime("+{$sub['duration_days']} days"));
            $payment_ref = 'RENEW-' . strtoupper(bin2hex(random_bytes(6)));
            $stmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, tier_id, start_date, end_date, is_active, payment_ref, renewal_count) VALUES (?, ?, ?, ?, 1, ?, 1)");
            $stmt->execute([$sub['user_id'], $sub['tier_id'], $start, $end, $payment_ref]);
            $newSubId = $pdo->lastInsertId();

            // Expire old
            $pdo->prepare("UPDATE user_subscriptions SET is_active = 0 WHERE id = ?")->execute([$subId]);

            // Update user tier
            $pdo->prepare("UPDATE users SET tier_id = ?, is_verified = 1 WHERE id = ?")->execute([$sub['tier_id'], $sub['user_id']]);

            // Generate invoice
            $invNo = 'INV-' . date('Y') . '-' . str_pad($newSubId, 5, '0', STR_PAD_LEFT);
            $items = json_encode([['name' => $sub['tier_name'] . ' Plan (Renewal)', 'amount' => $sub['price']]]);
            $stmt = $pdo->prepare("INSERT INTO invoices (user_id, subscription_id, invoice_no, amount, status, items_json, issued_at) VALUES (?, ?, ?, ?, 'paid', ?, NOW())");
            $stmt->execute([$sub['user_id'], $newSubId, $invNo, $sub['price'], $items]);

            $pdo->commit();
            header('Location: manage_subscriptions.php?msg=Subscription renewed, invoice generated');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Renewal failed: " . $e->getMessage();
        }
    }
}

$statusFilter = $_GET['status'] ?? 'all';

$where = '';
$params = [];
if ($statusFilter === 'active') {
    $where = "WHERE us.is_active = 1 AND (us.end_date IS NULL OR us.end_date >= CURDATE())";
} elseif ($statusFilter === 'expired') {
    $where = "WHERE (us.is_active = 0 OR (us.end_date IS NOT NULL AND us.end_date < CURDATE()))";
}

$stmt = $pdo->prepare("
    SELECT us.*, t.name AS tier_name, t.price, u.first_name, u.last_name, u.mobile
    FROM user_subscriptions us
    JOIN pricing_tiers t ON us.tier_id = t.id
    JOIN users u ON us.user_id = u.id
    $where
    ORDER BY us.id DESC
    LIMIT 100
");
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

if (isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);

$role = $_SESSION['admin_role'] ?? 'moderator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Subscriptions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root { --forest: #275D2B; --lime: #82D148; --green: #49B849; --navy: #0B1021; --off-white: #F8F9F8; --gray-100: #E8EDE8; --gray-500: #6B7E6B; --radius: 12px; --radius-lg: 16px; --shadow: 0 4px 12px rgba(11,16,33,0.1); }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--off-white); margin: 0; }
    .dashboard { display: flex; min-height: 100vh; }
    .sidebar { width: 250px; background: var(--navy); padding: 0; flex-shrink: 0; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; overflow-y: auto; }
    .sidebar .brand { padding: 1.2rem; color: #fff; font-weight: 800; font-size: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .sidebar .brand small { font-weight: 400; opacity: 0.5; font-size: 0.7rem; display: block; }
    .sidebar .nav { list-style: none; padding: 0.5rem 0; margin: 0; }
    .sidebar .nav a { display: flex; align-items: center; gap: 0.6rem; padding: 0.65rem 1.2rem; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; border-left: 3px solid transparent; }
    .sidebar .nav a:hover, .sidebar .nav a.active { color: #fff; background: rgba(255,255,255,0.08); border-left-color: var(--lime); }
    .sidebar .nav a.text-danger { color: #f87171; }
    .main { flex: 1; margin-left: 250px; padding: 2rem; }
    .page-title { font-weight: 700; font-size: 1.3rem; margin-bottom: 1.5rem; color: var(--navy); }
    .table-modern { border-collapse: separate; border-spacing: 0 4px; }
    .table-modern thead th { background: var(--off-white); color: var(--gray-500); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; border: none; padding: 0.75rem 1rem; }
    .table-modern tbody td { background: #fff; border: none; padding: 0.75rem 1rem; vertical-align: middle; }
    .table-modern tbody tr { box-shadow: 0 1px 2px rgba(0,0,0,0.05); border-radius: var(--radius); }
    .table-modern tbody tr:hover td { background: #E8F5E8; }
    .btn-sm-custom { border-radius: 8px; font-weight: 500; font-size: 0.8rem; padding: 0.35rem 0.9rem; }
    .alert-modern { border: none; border-radius: var(--radius); padding: 0.8rem 1.2rem; font-size: 0.85rem; }
    .alert-modern.alert-info { background: #E8F5E8; color: var(--forest); }
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-expired { background: #fef3c7; color: #92400e; }
    .badge-renewed { background: #dbeafe; color: #1e40af; }
    @media (max-width: 991px) {
      .sidebar { width: 60px; }
      .sidebar .brand span, .sidebar .nav a span { display: none; }
      .sidebar .nav a { justify-content: center; padding: 0.8rem; }
      .main { margin-left: 60px; padding: 1rem; }
    }
  </style>
</head>
<body>
<div class="dashboard">
  <div class="sidebar">
    <div class="brand"><span>Admin Panel</span><small>Subscriptions</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
      <a href="manage_tiers.php"><i class="bi bi-tags"></i><span>Pricing Tiers</span></a>
      <a href="manage_reviews.php"><i class="bi bi-star"></i><span>Reviews</span></a>
      <a href="manage_featured.php"><i class="bi bi-star-fill"></i><span>Featured</span></a>
      <a href="manage_subscriptions.php" class="active"><i class="bi bi-credit-card"></i><span>Subscriptions</span></a>
      <a href="manage_invoices.php"><i class="bi bi-receipt"></i><span>Invoices</span></a>
      <a href="manage_settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
      <?php if ($role === 'superadmin'): ?>
        <a href="manage_users.php"><i class="bi bi-people"></i><span>Users</span></a>
        <a href="manage_moderators.php"><i class="bi bi-shield"></i><span>Moderators</span></a>
      <?php endif; ?>
      <a href="logout.php" class="text-danger" style="margin-top:2rem"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
  </div>
  <div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="page-title mb-0">Subscriptions</h1>
      <div class="d-flex gap-2">
        <a href="?status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
        <a href="?status=active" class="btn btn-sm <?= $statusFilter === 'active' ? 'btn-success' : 'btn-outline-secondary' ?>">Active</a>
        <a href="?status=expired" class="btn btn-sm <?= $statusFilter === 'expired' ? 'btn-warning' : 'btn-outline-secondary' ?>">Expired</a>
      </div>
    </div>
    <?php if ($message): ?>
      <div class="alert alert-modern alert-info mb-4"><?= $message ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead>
          <tr><th>ID</th><th>User</th><th>Mobile</th><th>Plan</th><th>Start</th><th>End</th><th>Status</th><th>Ref</th><th>Renewals</th><th style="width:160px">Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$subscriptions): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">No subscriptions found.</td></tr>
          <?php else: ?>
            <?php foreach ($subscriptions as $s):
              $isActive = $s['is_active'] && (!$s['end_date'] || $s['end_date'] >= date('Y-m-d'));
              $isExpired = !$isActive && $s['end_date'] && $s['end_date'] < date('Y-m-d');
              $badge = $isActive ? 'badge-active' : ($isExpired ? 'badge-expired' : 'badge-renewed');
              $label = $isActive ? 'Active' : ($isExpired ? 'Expired' : 'Cancelled');
            ?>
            <tr>
              <td class="fw-bold"><?= $s['id'] ?></td>
              <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
              <td><?= htmlspecialchars($s['mobile']) ?></td>
              <td><?= htmlspecialchars($s['tier_name']) ?> (Rs. <?= number_format($s['price'], 0) ?>)</td>
              <td><?= $s['start_date'] ?></td>
              <td><?= $s['end_date'] ?? 'Lifetime' ?></td>
              <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
              <td style="font-size:0.75rem"><?= htmlspecialchars($s['payment_ref'] ?? '-') ?></td>
              <td><?= $s['renewal_count'] ?? 0 ?></td>
              <td>
                <?php if ($isActive && $s['end_date']): ?>
                  <form method="POST" action="?action=expire&id=<?= $s['id'] ?>" class="d-inline" onsubmit="return confirm('Expire this subscription? User will be reverted to Free tier.');">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-warning btn-sm-custom">Expire</button>
                  </form>
                <?php elseif ($isExpired): ?>
                  <form method="POST" action="?action=renew&id=<?= $s['id'] ?>" class="d-inline" onsubmit="return confirm('Renew this subscription and generate invoice?');">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-success btn-sm-custom">Renew</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
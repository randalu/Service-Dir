<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, trim($value)]);
    }
    $message = "Settings saved successfully.";
}

$allSettings = $pdo->query("SELECT * FROM settings ORDER BY `key`")->fetchAll();
$settingsMap = [];
foreach ($allSettings as $s) $settingsMap[$s['key']] = $s['value'];

$role = $_SESSION['admin_role'] ?? 'moderator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root { --forest: #275D2B; --lime: #82D148; --navy: #0B1021; --off-white: #F8F9F8; --radius: 12px; --radius-lg: 16px; --shadow: 0 4px 12px rgba(11,16,33,0.1); }
    body { font-family: 'Inter', sans-serif; background: var(--off-white); margin: 0; }
    .dashboard { display: flex; min-height: 100vh; }
    .sidebar { width: 250px; background: var(--navy); padding: 0; flex-shrink: 0; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; }
    .sidebar .brand { padding: 1.2rem; color: #fff; font-weight: 800; font-size: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .sidebar .brand small { font-weight: 400; opacity: 0.5; font-size: 0.7rem; display: block; }
    .sidebar .nav { list-style: none; padding: 0.5rem 0; margin: 0; }
    .sidebar .nav a { display: flex; align-items: center; gap: 0.6rem; padding: 0.65rem 1.2rem; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.85rem; font-weight: 500; border-left: 3px solid transparent; }
    .sidebar .nav a:hover, .sidebar .nav a.active { color: #fff; background: rgba(255,255,255,0.08); border-left-color: var(--lime); }
    .sidebar .nav a.text-danger { color: #f87171; }
    .main { flex: 1; margin-left: 250px; padding: 2rem; }
    .page-title { font-weight: 700; font-size: 1.3rem; color: var(--navy); }
    .card-settings { background: #fff; border: 1px solid var(--gray-100); border-radius: var(--radius-lg); padding: 2rem; }
    .alert-modern { border: none; border-radius: var(--radius); padding: 0.8rem 1.2rem; }
    .alert-modern.alert-info { background: #E8F5E8; color: var(--forest); }
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
    <div class="brand"><span>Admin Panel</span><small>Settings</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
      <a href="manage_tiers.php"><i class="bi bi-tags"></i><span>Pricing Tiers</span></a>
      <a href="manage_reviews.php"><i class="bi bi-star"></i><span>Reviews</span></a>
      <a href="manage_featured.php"><i class="bi bi-star-fill"></i><span>Featured</span></a>
      <a href="manage_subscriptions.php"><i class="bi bi-credit-card"></i><span>Subscriptions</span></a>
      <a href="manage_invoices.php"><i class="bi bi-receipt"></i><span>Invoices</span></a>
      <a href="manage_settings.php" class="active"><i class="bi bi-gear"></i><span>Settings</span></a>
      <a href="manage_users.php"><i class="bi bi-people"></i><span>Users</span></a>
      <a href="manage_moderators.php"><i class="bi bi-shield"></i><span>Moderators</span></a>
      <a href="logout.php" class="text-danger" style="margin-top:2rem"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
  </div>
  <div class="main">
    <h1 class="page-title mb-4">Settings</h1>
    <?php if ($message): ?><div class="alert alert-modern alert-info mb-4"><?= $message ?></div><?php endif; ?>
    <div class="card-settings">
      <form method="POST">
        <?= csrfField() ?>
        <div class="mb-4">
          <label class="form-label fw-semibold">Google Maps API Key</label>
          <input type="text" name="settings[google_maps_api_key]" class="form-control" style="border:1.5px solid var(--gray-100);border-radius:var(--radius);padding:0.6rem 1rem" value="<?= htmlspecialchars($settingsMap['google_maps_api_key'] ?? '') ?>" placeholder="Enter your Google Maps API key">
          <div class="form-text text-muted small">Used for location embedding on service detail pages.</div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Site Name</label>
          <input type="text" name="settings[site_name]" class="form-control" style="border:1.5px solid var(--gray-100);border-radius:var(--radius);padding:0.6rem 1rem" value="<?= htmlspecialchars($settingsMap['site_name'] ?? 'RDL Service Directory') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Contact Email</label>
          <input type="email" name="settings[contact_email]" class="form-control" style="border:1.5px solid var(--gray-100);border-radius:var(--radius);padding:0.6rem 1rem" value="<?= htmlspecialchars($settingsMap['contact_email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600;padding:0.6rem 2rem">Save Settings</button>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

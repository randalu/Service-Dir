<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../db.php';

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) >= ?");
$stmt->execute([$weekStart]);
$weeklyUsers = $stmt->fetchColumn();
$totalAds = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$totalViews = $pdo->query("SELECT SUM(views) FROM services")->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(views) FROM services WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$viewsToday = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT SUM(views) FROM services WHERE DATE(created_at) >= ?");
$stmt->execute([$weekStart]);
$viewsWeek = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$adsToday = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE DATE(created_at) >= ?");
$stmt->execute([$weekStart]);
$adsThisWeek = $stmt->fetchColumn();

// v2 stats
try {
    $pendingServices = $pdo->query("SELECT COUNT(*) FROM services WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) { $pendingServices = 0; }
try {
    $pendingReviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn();
} catch (Exception $e) { $pendingReviews = 0; }
try {
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM featured_listings")->fetchColumn();
} catch (Exception $e) { $totalRevenue = 0; }
try {
    $providerCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'provider'")->fetchColumn();
} catch (Exception $e) { $providerCount = $totalUsers; }
try {
    $activeSubs = $pdo->query("SELECT COUNT(*) FROM user_subscriptions WHERE is_active = 1 AND (end_date IS NULL OR end_date >= CURDATE())")->fetchColumn();
} catch (Exception $e) { $activeSubs = 0; }
try {
    $expiredSubs = $pdo->query("SELECT COUNT(*) FROM user_subscriptions WHERE is_active = 1 AND end_date IS NOT NULL AND end_date < CURDATE()")->fetchColumn();
} catch (Exception $e) { $expiredSubs = 0; }
try {
    $totalInvoices = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
    $totalRevenueInvoices = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid'")->fetchColumn();
} catch (Exception $e) { $totalInvoices = 0; $totalRevenueInvoices = 0; }

$role = $_SESSION['admin_role'] ?? 'moderator';
$username = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root { --forest: #275D2B; --lime: #82D148; --green: #49B849; --navy: #0B1021; --off-white: #F8F9F8; --gray-100: #E8EDE8; --gray-500: #6B7E6B; --radius: 12px; --radius-lg: 16px; --shadow: 0 4px 12px rgba(11,16,33,0.1); --shadow-lg: 0 10px 30px rgba(11,16,33,0.12); }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--off-white); margin: 0; }
    .dashboard { display: flex; min-height: 100vh; }
    .sidebar { width: 250px; background: var(--navy); padding: 0; flex-shrink: 0; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; }
    .sidebar .brand { padding: 1.2rem; color: #fff; font-weight: 800; font-size: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .sidebar .brand small { font-weight: 400; opacity: 0.5; font-size: 0.7rem; display: block; }
    .sidebar .nav { list-style: none; padding: 0.5rem 0; margin: 0; }
    .sidebar .nav a { display: flex; align-items: center; gap: 0.6rem; padding: 0.65rem 1.2rem; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; border-left: 3px solid transparent; }
    .sidebar .nav a:hover, .sidebar .nav a.active { color: #fff; background: rgba(255,255,255,0.08); border-left-color: var(--lime); }
    .sidebar .nav a i { width: 20px; text-align: center; }
    .sidebar .nav a.text-danger { color: #f87171; }
    .main { flex: 1; margin-left: 250px; padding: 2rem; }
    .page-title { font-weight: 700; font-size: 1.3rem; margin-bottom: 1.5rem; color: var(--navy); }
    .stat-card { background: #fff; border: 1px solid var(--gray-100); border-radius: var(--radius); padding: 1.2rem; transition: all 0.2s; }
    .stat-card:hover { box-shadow: var(--shadow); }
    .stat-card .stat-icon { width: 44px; height: 44px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 0.8rem; }
    .stat-card .stat-value { font-weight: 800; font-size: 1.6rem; color: var(--navy); line-height: 1.2; }
    .stat-card .stat-label { font-size: 0.8rem; color: var(--gray-500); font-weight: 500; }
    .nav-card { background: #fff; border: 1px solid var(--gray-100); border-radius: var(--radius-lg); padding: 1.5rem; transition: all 0.2s; text-decoration: none; display: block; height: 100%; }
    .nav-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); border-color: transparent; }
    .nav-card .icon { font-size: 1.8rem; margin-bottom: 0.8rem; }
    .nav-card h5 { font-weight: 700; font-size: 1rem; color: var(--navy); margin-bottom: 0.3rem; }
    .nav-card p { font-size: 0.8rem; color: var(--gray-500); margin: 0; }
    .admin-badge { font-size: 0.7rem; background: var(--lime); color: var(--navy); padding: 0.2rem 0.6rem; border-radius: 999px; font-weight: 700; }
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
    <div class="brand">
      <span>Admin Panel</span>
      <small><?= $role === 'superadmin' ? 'Super Admin' : 'Moderator' ?></small>
    </div>
    <div class="nav">
      <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
      <a href="manage_tiers.php"><i class="bi bi-tags"></i><span>Pricing Tiers</span></a>
      <a href="manage_reviews.php"><i class="bi bi-star"></i><span>Reviews</span></a>
      <a href="manage_featured.php"><i class="bi bi-star-fill"></i><span>Featured</span></a>
      <a href="manage_subscriptions.php"><i class="bi bi-credit-card"></i><span>Subscriptions</span></a>
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
      <h1 class="page-title mb-0">Welcome, <?= htmlspecialchars($username) ?></h1>
      <span class="admin-badge"><?= $role === 'superadmin' ? 'Super Admin' : 'Moderator' ?></span>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-5">
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E8;color:var(--forest)"><i class="bi bi-people"></i></div>
          <div class="stat-value"><?= $totalUsers ?></div>
          <div class="stat-label">Total Users &middot; <?= $providerCount ?> providers</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E8;color:var(--green)"><i class="bi bi-megaphone"></i></div>
          <div class="stat-value"><?= $totalAds ?></div>
          <div class="stat-label">Service Ads &middot; <?= $pendingServices ?> pending</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E8;color:var(--lime)"><i class="bi bi-eye"></i></div>
          <div class="stat-value"><?= $totalViews ?: 0 ?></div>
          <div class="stat-label">Total Views &middot; <?= $viewsToday ?> today</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E8;color:var(--navy)"><i class="bi bi-star"></i></div>
          <div class="stat-value"><?= $pendingReviews ?></div>
          <div class="stat-label">Pending Reviews &middot; Rs. <?= number_format($totalRevenue, 0) ?> featured revenue</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E8;color:var(--navy)"><i class="bi bi-credit-card"></i></div>
          <div class="stat-value"><?= $activeSubs ?> / <?= $expiredSubs ?></div>
          <div class="stat-label">Active / Expired Subscriptions</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E8;color:var(--forest)"><i class="bi bi-receipt"></i></div>
          <div class="stat-value">Rs. <?= number_format($totalRevenueInvoices, 0) ?></div>
          <div class="stat-label"><?= $totalInvoices ?> Invoices &middot; Total revenue</div>
        </div>
      </div>
    </div>

    <!-- Navigation Cards -->
    <h5 class="fw-bold mb-3">Quick Management</h5>
    <div class="row g-4">
      <div class="col-md-4 col-sm-6">
        <a href="manage_ads.php" class="nav-card" style="border-top:3px solid var(--green)">
          <div class="icon">📢</div>
          <h5>Manage Ads</h5>
          <p>View, approve and manage service listings</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_reviews.php" class="nav-card" style="border-top:3px solid var(--lime)">
          <div class="icon">⭐</div>
          <h5>Reviews <?= $pendingReviews > 0 ? "<span class=\"badge bg-warning text-dark ms-1\">$pendingReviews</span>" : '' ?></h5>
          <p>Moderate user reviews and ratings</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_categories.php" class="nav-card" style="border-top:3px solid var(--forest)">
          <div class="icon">📂</div>
          <h5>Categories</h5>
          <p>Create and manage service categories</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_areas.php" class="nav-card" style="border-top:3px solid var(--lime)">
          <div class="icon">🌍</div>
          <h5>Areas</h5>
          <p>Manage location areas and towns</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_tiers.php" class="nav-card" style="border-top:3px solid var(--green)">
          <div class="icon">🏷️</div>
          <h5>Pricing Tiers</h5>
          <p>Configure subscription plans and pricing</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_featured.php" class="nav-card" style="border-top:3px solid #f59e0b">
          <div class="icon">⭐</div>
          <h5>Featured</h5>
          <p>Set featured listings and durations</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_subscriptions.php" class="nav-card" style="border-top:3px solid var(--green)">
          <div class="icon">💳</div>
          <h5>Subscriptions</h5>
          <p>View and manage user subscriptions</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_invoices.php" class="nav-card" style="border-top:3px solid var(--navy)">
          <div class="icon">🧾</div>
          <h5>Invoices</h5>
          <p>View invoices and revenue</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_settings.php" class="nav-card" style="border-top:3px solid var(--navy)">
          <div class="icon">⚙️</div>
          <h5>Settings</h5>
          <p>Configure Google Maps API key and site info</p>
        </a>
      </div>
      <?php if ($role === 'superadmin'): ?>
      <div class="col-md-4 col-sm-6">
        <a href="manage_users.php" class="nav-card" style="border-top:3px solid var(--green)">
          <div class="icon">👥</div>
          <h5>Users</h5>
          <p>Manage registered user accounts and roles</p>
        </a>
      </div>
      <div class="col-md-4 col-sm-6">
        <a href="manage_moderators.php" class="nav-card" style="border-top:3px solid var(--forest)">
          <div class="icon">🛡️</div>
          <h5>Moderators</h5>
          <p>Manage moderator accounts and roles</p>
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
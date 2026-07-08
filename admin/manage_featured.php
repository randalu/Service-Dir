<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

$action = $_GET['action'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_featured') {
    $service_id = (int)$_POST['service_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $amount_paid = (float)$_POST['amount_paid'];

    if (!$service_id || !$start_date || !$end_date) {
        $message = "All fields required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO featured_listings (service_id, set_by, start_date, end_date, amount_paid) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$service_id, $_SESSION['admin_id'] ?? 1, $start_date, $end_date, $amount_paid]);
        $pdo->prepare("UPDATE services SET is_featured = 1, featured_until = ? WHERE id = ?")->execute([$end_date, $service_id]);
        header('Location: manage_featured.php?msg=Featured listing added');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT service_id FROM featured_listings WHERE id = ?");
    $stmt->execute([$id]);
    $fl = $stmt->fetch();
    if ($fl) {
        $pdo->prepare("UPDATE services SET is_featured = 0, featured_until = NULL WHERE id = ?")->execute([$fl['service_id']]);
        $pdo->prepare("DELETE FROM featured_listings WHERE id = ?")->execute([$id]);
    }
    header('Location: manage_featured.php?msg=Featured listing removed');
    exit;
}

// Auto-expire past featured
$pdo->exec("UPDATE services SET is_featured = 0, featured_until = NULL WHERE featured_until IS NOT NULL AND featured_until < CURDATE()");

$services = $pdo->query("SELECT id, title FROM services ORDER BY title")->fetchAll();
$featured = $pdo->query("
    SELECT fl.*, s.title AS service_title
    FROM featured_listings fl
    JOIN services s ON fl.service_id = s.id
    ORDER BY fl.end_date DESC
")->fetchAll();

if (isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);

$role = $_SESSION['admin_role'] ?? 'moderator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Featured Listings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root { --forest: #275D2B; --lime: #82D148; --navy: #0B1021; --off-white: #F8F9F8; --gray-100: #E8EDE8; --gray-500: #6B7E6B; --radius: 12px; --shadow: 0 4px 12px rgba(11,16,33,0.1); }
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
    <div class="brand"><span>Admin Panel</span><small>Featured</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
      <a href="manage_tiers.php"><i class="bi bi-tags"></i><span>Pricing Tiers</span></a>
      <a href="manage_reviews.php"><i class="bi bi-star"></i><span>Reviews</span></a>
      <a href="manage_featured.php" class="active"><i class="bi bi-star-fill"></i><span>Featured</span></a>
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
      <h1 class="page-title mb-0">Featured Listings</h1>
      <button class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600" data-bs-toggle="modal" data-bs-target="#featuredModal">+ Add Featured</button>
    </div>
    <?php if ($message): ?><div class="alert alert-modern alert-info mb-4"><?= $message ?></div><?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead><tr><th>Service</th><th>Start</th><th>End</th><th>Amount</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($featured as $f): ?>
          <tr>
            <td><?= htmlspecialchars($f['service_title']) ?></td>
            <td><?= $f['start_date'] ?></td>
            <td><?= $f['end_date'] ?></td>
            <td>Rs. <?= number_format($f['amount_paid'], 0) ?></td>
            <td>
              <form method="POST" action="?action=remove&id=<?= $f['id'] ?>" onsubmit="return confirm('Remove featured status?');" class="d-inline">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="featuredModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="?action=set_featured" class="modal-content">
      <?= csrfField() ?>
      <div class="modal-header"><h5 class="modal-title fw-bold">Add Featured Listing</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label fw-semibold small">Service</label>
          <select name="service_id" class="form-select" required>
            <option value="">-- Select --</option>
            <?php foreach ($services as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?> (ID: <?= $s['id'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label fw-semibold small">Start Date</label><input type="date" name="start_date" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label fw-semibold small">End Date</label><input type="date" name="end_date" class="form-control" required></div>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold small">Amount Paid (Rs.)</label><input type="number" step="0.01" name="amount_paid" class="form-control" value="0"></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600">Add</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
    </form>
  </div>
</div>

<style>
.table-modern { border-collapse: separate; border-spacing: 0 4px; }
.table-modern thead th { background: var(--off-white); color: var(--gray-500); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; border: none; padding: 0.75rem 1rem; }
.table-modern tbody td { background: #fff; border: none; padding: 0.75rem 1rem; vertical-align: middle; }
.table-modern tbody tr { box-shadow: 0 1px 2px rgba(0,0,0,0.05); border-radius: var(--radius); }
.table-modern tbody tr:hover td { background: #E8F5E8; }
.alert-modern { border: none; border-radius: var(--radius); padding: 0.8rem 1.2rem; font-size: 0.85rem; }
.alert-modern.alert-info { background: #E8F5E8; color: var(--forest); }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

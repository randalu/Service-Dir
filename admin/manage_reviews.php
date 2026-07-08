<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

$action = $_GET['action'] ?? '';
$reviewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve' && $reviewId) {
    $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?")->execute([$reviewId]);
    header('Location: manage_reviews.php?msg=Review approved');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $reviewId) {
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$reviewId]);
    header('Location: manage_reviews.php?msg=Review deleted');
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'pending') $where = 'WHERE r.is_approved = 0';
elseif ($filter === 'approved') $where = 'WHERE r.is_approved = 1';

$reviews = $pdo->query("
    SELECT r.*, u1.first_name AS from_first, u1.last_name AS from_last, u2.first_name AS to_first, u2.last_name AS to_last, s.title AS service_title
    FROM reviews r
    JOIN users u1 ON r.from_user_id = u1.id
    JOIN users u2 ON r.to_user_id = u2.id
    LEFT JOIN services s ON r.service_id = s.id
    $where
    ORDER BY r.created_at DESC
")->fetchAll();

if (isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);

$role = $_SESSION['admin_role'] ?? 'moderator';

// Simplified admin header
$pageTitle = 'Manage Reviews';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Reviews</title>
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
    .table-modern { border-collapse: separate; border-spacing: 0 4px; }
    .table-modern thead th { background: var(--off-white); color: var(--gray-500); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; border: none; padding: 0.75rem 1rem; }
    .table-modern tbody td { background: #fff; border: none; padding: 0.75rem 1rem; vertical-align: middle; }
    .table-modern tbody tr { box-shadow: 0 1px 2px rgba(0,0,0,0.05); border-radius: var(--radius); }
    .table-modern tbody tr:hover td { background: #E8F5E8; }
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
    <div class="brand"><span>Admin Panel</span><small>Reviews</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
      <a href="manage_tiers.php"><i class="bi bi-tags"></i><span>Pricing Tiers</span></a>
      <a href="manage_reviews.php" class="active"><i class="bi bi-star"></i><span>Reviews</span></a>
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
      <h1 class="page-title mb-0">Manage Reviews (<?= count($reviews) ?>)</h1>
      <div class="d-flex gap-2">
        <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">All</a>
        <a href="?filter=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">Pending</a>
        <a href="?filter=approved" class="btn btn-sm <?= $filter === 'approved' ? 'btn-success' : 'btn-outline-success' ?>">Approved</a>
      </div>
    </div>
    <?php if ($message): ?><div class="alert alert-modern alert-info mb-4"><?= $message ?></div><?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead><tr><th>From</th><th>To</th><th>Service</th><th>Rating</th><th>Comment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (!$reviews): ?><tr><td colspan="8" class="text-center text-muted py-4">No reviews found.</td></tr><?php endif; ?>
          <?php foreach ($reviews as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['from_first'] . ' ' . $r['from_last']) ?></td>
            <td><?= htmlspecialchars($r['to_first'] . ' ' . $r['to_last']) ?></td>
            <td><?= htmlspecialchars($r['service_title'] ?? '-') ?></td>
            <td><?= str_repeat('⭐', (int)$r['rating']) ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars(substr($r['comment'], 0, 80)) ?></td>
            <td><span class="badge" style="background:<?= $r['is_approved'] ? 'var(--green)' : 'var(--gray-500)' ?>"><?= $r['is_approved'] ? 'Approved' : 'Pending' ?></span></td>
            <td style="white-space:nowrap;font-size:0.8rem"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
            <td>
              <?php if (!$r['is_approved']): ?>
                <form method="POST" action="manage_reviews.php?action=approve&id=<?= $r['id'] ?>" class="d-inline">
                  <?= csrfField() ?>
                  <button type="submit" class="btn btn-success btn-sm">Approve</button>
                </form>
              <?php endif; ?>
              <form method="POST" action="manage_reviews.php?action=delete&id=<?= $r['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this review?');">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

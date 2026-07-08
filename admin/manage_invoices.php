<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

$action = $_GET['action'] ?? '';
$invId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Update invoice status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status' && $invId) {
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['paid', 'unpaid', 'cancelled', 'refunded'])) {
        $pdo->prepare("UPDATE invoices SET status = ?, paid_at = IF(? = 'paid', NOW(), paid_at) WHERE id = ?")
            ->execute([$newStatus, $newStatus, $invId]);
        header('Location: manage_invoices.php?msg=Invoice status updated');
        exit;
    }
}

$statusFilter = $_GET['status'] ?? 'all';

$where = '';
$params = [];
if ($statusFilter === 'paid') {
    $where = "WHERE i.status = 'paid'";
} elseif ($statusFilter === 'unpaid') {
    $where = "WHERE i.status = 'unpaid'";
} elseif ($statusFilter === 'cancelled') {
    $where = "WHERE i.status = 'cancelled'";
} elseif ($statusFilter === 'refunded') {
    $where = "WHERE i.status = 'refunded'";
}

$stmt = $pdo->prepare("
    SELECT i.*, u.first_name, u.last_name, u.mobile
    FROM invoices i
    JOIN users u ON i.user_id = u.id
    $where
    ORDER BY i.id DESC
    LIMIT 100
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Stats
$totalAmount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid'")->fetchColumn();
$totalUnpaid = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'unpaid'")->fetchColumn();

if (isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);

$role = $_SESSION['admin_role'] ?? 'moderator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Invoices</title>
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
    .stat-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
    .stat-box { background: #fff; border-radius: var(--radius); padding: 1rem 1.5rem; box-shadow: var(--shadow); flex: 1; }
    .stat-box .num { font-size: 1.5rem; font-weight: 800; color: var(--navy); }
    .stat-box .lbl { font-size: 0.8rem; color: var(--gray-500); }
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
    <div class="brand"><span>Admin Panel</span><small>Invoices</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
      <a href="manage_tiers.php"><i class="bi bi-tags"></i><span>Pricing Tiers</span></a>
      <a href="manage_reviews.php"><i class="bi bi-star"></i><span>Reviews</span></a>
      <a href="manage_featured.php"><i class="bi bi-star-fill"></i><span>Featured</span></a>
      <a href="manage_subscriptions.php"><i class="bi bi-credit-card"></i><span>Subscriptions</span></a>
      <a href="manage_invoices.php" class="active"><i class="bi bi-receipt"></i><span>Invoices</span></a>
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
      <h1 class="page-title mb-0">Invoices</h1>
      <div class="d-flex gap-2">
        <a href="?status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
        <a href="?status=paid" class="btn btn-sm <?= $statusFilter === 'paid' ? 'btn-success' : 'btn-outline-secondary' ?>">Paid</a>
        <a href="?status=unpaid" class="btn btn-sm <?= $statusFilter === 'unpaid' ? 'btn-warning' : 'btn-outline-secondary' ?>">Unpaid</a>
        <a href="?status=cancelled" class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Cancelled</a>
        <a href="?status=refunded" class="btn btn-sm <?= $statusFilter === 'refunded' ? 'btn-danger' : 'btn-outline-secondary' ?>">Refunded</a>
      </div>
    </div>

    <div class="stat-row">
      <div class="stat-box"><div class="num">Rs. <?= number_format($totalAmount, 0) ?></div><div class="lbl">Collected Revenue</div></div>
      <div class="stat-box"><div class="num">Rs. <?= number_format($totalUnpaid, 0) ?></div><div class="lbl">Outstanding</div></div>
      <div class="stat-box"><div class="num"><?= count($invoices) ?></div><div class="lbl">Invoices (current filter)</div></div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-modern alert-info mb-4"><?= $message ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead>
          <tr><th>Invoice #</th><th>User</th><th>Amount</th><th>Status</th><th>Issued</th><th>Paid At</th><th style="width:200px">Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$invoices): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr>
          <?php else: ?>
            <?php foreach ($invoices as $inv): ?>
            <tr>
              <td class="fw-bold" style="font-size:0.85rem"><?= htmlspecialchars($inv['invoice_no']) ?></td>
              <td><?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?><br><small class="text-muted"><?= htmlspecialchars($inv['mobile']) ?></small></td>
              <td class="fw-bold">Rs. <?= number_format($inv['amount'], 0) ?></td>
              <td><span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'unpaid' ? 'warning text-dark' : ($inv['status'] === 'refunded' ? 'danger' : 'secondary')) ?>"><?= ucfirst($inv['status']) ?></span></td>
              <td style="font-size:0.8rem"><?= $inv['issued_at'] ? date('M d, Y', strtotime($inv['issued_at'])) : '-' ?></td>
              <td style="font-size:0.8rem"><?= $inv['paid_at'] ? date('M d, Y', strtotime($inv['paid_at'])) : '-' ?></td>
              <td>
                <form method="POST" action="?action=update_status&id=<?= $inv['id'] ?>" class="d-inline">
                  <?= csrfField() ?>
                  <select name="status" class="form-select form-select-sm d-inline" style="width:auto;display:inline!important" onchange="this.form.submit()">
                    <option value="paid" <?= $inv['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="unpaid" <?= $inv['status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="cancelled" <?= $inv['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="refunded" <?= $inv['status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                  </select>
                </form>
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
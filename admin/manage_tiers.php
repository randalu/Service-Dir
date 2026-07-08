<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

$action = $_GET['action'] ?? '';
$tierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $max_posts = $_POST['max_posts'] !== '' ? (int)$_POST['max_posts'] : null;
    $price = (float)$_POST['price'];
    $duration_days = $_POST['duration_days'] !== '' ? (int)$_POST['duration_days'] : null;
    $is_subscription = isset($_POST['is_subscription']) ? 1 : 0;
    $auto_approve = isset($_POST['auto_approve']) ? 1 : 0;
    $sort_order = (int)$_POST['sort_order'];

    if (!$name) {
        $message = "Please enter tier name.";
    } else {
        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO pricing_tiers (name, max_posts, price, duration_days, is_subscription, auto_approve, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $max_posts, $price, $duration_days, $is_subscription, $auto_approve, $sort_order]);
            header('Location: manage_tiers.php?msg=Tier added');
            exit;
        } elseif ($action === 'edit' && $tierId) {
            $stmt = $pdo->prepare("UPDATE pricing_tiers SET name=?, max_posts=?, price=?, duration_days=?, is_subscription=?, auto_approve=?, sort_order=? WHERE id=?");
            $stmt->execute([$name, $max_posts, $price, $duration_days, $is_subscription, $auto_approve, $sort_order, $tierId]);
            header('Location: manage_tiers.php?msg=Tier updated');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $tierId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE tier_id = ?");
    $stmt->execute([$tierId]);
    if ($stmt->fetchColumn() > 0) {
        $message = "Cannot delete: users are subscribed to this tier.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM pricing_tiers WHERE id = ?");
        $stmt->execute([$tierId]);
        header('Location: manage_tiers.php?msg=Tier deleted');
        exit;
    }
}

$tiers = $pdo->query("SELECT * FROM pricing_tiers ORDER BY sort_order")->fetchAll();

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

$role = $_SESSION['admin_role'] ?? 'moderator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Pricing Tiers</title>
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
    .sidebar .nav a i { width: 20px; text-align: center; }
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
    <div class="brand"><span>Admin Panel</span><small>Tiers</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
      <a href="manage_tiers.php" class="active"><i class="bi bi-tags"></i><span>Pricing Tiers</span></a>
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
      <h1 class="page-title mb-0">Pricing Tiers</h1>
      <button class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600;font-size:0.85rem;padding:0.5rem 1.2rem" data-bs-toggle="modal" data-bs-target="#createTierModal">+ Add Tier</button>
    </div>
    <?php if ($message): ?>
      <div class="alert alert-modern alert-info mb-4"><?= $message ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead>
          <tr><th>ID</th><th>Name</th><th>Max Posts</th><th>Price (Rs.)</th><th>Duration</th><th>Subscription</th><th>Auto-Approve</th><th>Order</th><th style="width:150px">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tiers as $t): ?>
          <tr>
            <td class="fw-bold"><?= $t['id'] ?></td>
            <td><?= htmlspecialchars($t['name']) ?></td>
            <td><?= $t['max_posts'] ?? '∞' ?></td>
            <td>Rs. <?= number_format($t['price'], 0) ?></td>
            <td><?= $t['duration_days'] ? $t['duration_days'] . ' days' : 'Lifetime' ?></td>
            <td><?= $t['is_subscription'] ? '✅' : '—' ?></td>
            <td><?= $t['auto_approve'] ? '✅' : '—' ?></td>
            <td><?= $t['sort_order'] ?></td>
            <td>
              <button class="btn btn-primary btn-sm-custom" data-bs-toggle="modal" data-bs-target="#editTierModal" data-tier='<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><i class="bi bi-pencil"></i></button>
              <form method="POST" action="manage_tiers.php?action=delete&id=<?= $t['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this tier?');">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger btn-sm-custom"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createTierModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="?action=create" class="modal-content" style="border:none;border-radius:var(--radius-lg)">
      <?= csrfField() ?>
      <div class="modal-header"><h5 class="modal-title fw-bold">Add Tier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label fw-semibold small">Name</label><input type="text" name="name" class="form-control" required></div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label fw-semibold small">Max Posts (leave empty = ∞)</label><input type="number" name="max_posts" class="form-control"></div>
          <div class="col-md-6"><label class="form-label fw-semibold small">Price (Rs.)</label><input type="number" step="0.01" name="price" class="form-control" value="0"></div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label fw-semibold small">Duration Days (empty = lifetime)</label><input type="number" name="duration_days" class="form-control"></div>
          <div class="col-md-6"><label class="form-label fw-semibold small">Sort Order</label><input type="number" name="sort_order" class="form-control" value="0"></div>
        </div>
        <div class="form-check mb-2"><input type="checkbox" name="is_subscription" class="form-check-input" id="createSub"><label class="form-check-label" for="createSub">Subscription (recurring)</label></div>
        <div class="form-check"><input type="checkbox" name="auto_approve" class="form-check-input" id="createAuto"><label class="form-check-label" for="createAuto">Auto-Approve entries</label></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600">Add Tier</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editTierModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" id="editTierForm" class="modal-content" style="border:none;border-radius:var(--radius-lg)">
      <?= csrfField() ?>
      <div class="modal-header"><h5 class="modal-title fw-bold">Edit Tier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editTierId">
        <div class="mb-3"><label class="form-label fw-semibold small">Name</label><input type="text" name="name" id="editTierName" class="form-control" required></div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label fw-semibold small">Max Posts</label><input type="number" name="max_posts" id="editMaxPosts" class="form-control"></div>
          <div class="col-md-6"><label class="form-label fw-semibold small">Price (Rs.)</label><input type="number" step="0.01" name="price" id="editPrice" class="form-control"></div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label fw-semibold small">Duration Days</label><input type="number" name="duration_days" id="editDuration" class="form-control"></div>
          <div class="col-md-6"><label class="form-label fw-semibold small">Sort Order</label><input type="number" name="sort_order" id="editSortOrder" class="form-control"></div>
        </div>
        <div class="form-check mb-2"><input type="checkbox" name="is_subscription" class="form-check-input" id="editSub"><label class="form-check-label" for="editSub">Subscription</label></div>
        <div class="form-check"><input type="checkbox" name="auto_approve" class="form-check-input" id="editAuto"><label class="form-check-label" for="editAuto">Auto-Approve</label></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600">Update</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editTierModal')?.addEventListener('show.bs.modal', event => {
  const t = JSON.parse(event.relatedTarget.getAttribute('data-tier'));
  document.getElementById('editTierId').value = t.id;
  document.getElementById('editTierName').value = t.name;
  document.getElementById('editMaxPosts').value = t.max_posts ?? '';
  document.getElementById('editPrice').value = t.price;
  document.getElementById('editDuration').value = t.duration_days ?? '';
  document.getElementById('editSortOrder').value = t.sort_order;
  document.getElementById('editSub').checked = t.is_subscription == 1;
  document.getElementById('editAuto').checked = t.auto_approve == 1;
  document.getElementById('editTierForm').action = 'manage_tiers.php?action=edit&id=' + t.id;
});
</script>
</body>
</html>

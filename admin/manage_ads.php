<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

$action = $_GET['action'] ?? '';
$adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $title = trim($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $area_id = (int)$_POST['area_id'];
    $description = trim($_POST['description']);
    $user_id = (int)$_POST['user_id'];
    if (!$title || !$category_id || !$area_id || !$user_id) {
        $message = "All fields are required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO services (title, category_id, area_id, description, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $category_id, $area_id, $description, $user_id]);
        header('Location: manage_ads.php?msg=Ad added successfully');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $adId) {
    $title = trim($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $area_id = (int)$_POST['area_id'];
    $description = trim($_POST['description']);
    if (!$title || !$category_id || !$area_id) {
        $message = "Please fill all required fields.";
    } else {
        $stmt = $pdo->prepare("UPDATE services SET title=?, category_id=?, area_id=?, description=? WHERE id=?");
        $stmt->execute([$title, $category_id, $area_id, $description, $adId]);
        header('Location: manage_ads.php?msg=Ad updated successfully');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve' && $adId) {
    $stmt = $pdo->prepare("UPDATE services SET status = 'active' WHERE id = ?");
    $stmt->execute([$adId]);
    header('Location: manage_ads.php?msg=Ad approved');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'suspend' && $adId) {
    $stmt = $pdo->prepare("UPDATE services SET status = 'suspended' WHERE id = ?");
    $stmt->execute([$adId]);
    header('Location: manage_ads.php?msg=Ad suspended');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $adId) {
    $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
    $stmt->execute([$adId]);
    header('Location: manage_ads.php?msg=Ad deleted successfully');
    exit;
}

$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$stmtAds = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, c.name AS category_name, a.name AS area_name
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    ORDER BY s.id DESC
    LIMIT ? OFFSET ?
");
$stmtAds->bindValue(1, $perPage, PDO::PARAM_INT);
$stmtAds->bindValue(2, $offset, PDO::PARAM_INT);
$stmtAds->execute();
$ads = $stmtAds->fetchAll();

$totalAds = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$totalPages = ceil($totalAds / $perPage);

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$areas = $pdo->query("SELECT id, name FROM areas ORDER BY name")->fetchAll();
$role = $_SESSION['admin_role'] ?? 'moderator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Ads</title>
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
    .pagination-custom .page-link { border: none; color: var(--gray-500); font-weight: 500; padding: 0.5rem 1rem; margin: 0 2px; border-radius: var(--radius); }
    .pagination-custom .page-link:hover { background: #E8F5E8; color: var(--forest); }
    .pagination-custom .page-item.active .page-link { background: var(--forest); color: #fff; }
    .pagination-custom .page-item.disabled .page-link { color: #cdd6cd; background: transparent; }
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
    <div class="brand"><span>Admin Panel</span><small>Ads</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php" class="active"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
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
      <h1 class="page-title mb-0">Manage Ads</h1>
      <button class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600;font-size:0.85rem;padding:0.5rem 1.2rem" data-bs-toggle="modal" data-bs-target="#createAdModal">+ Add Ad</button>
    </div>
    <?php if ($message): ?>
      <div class="alert alert-modern alert-info mb-4"><?= $message ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead>
          <tr><th>ID</th><th>Title</th><th>User</th><th>Category</th><th>Area</th><th>Status</th><th>Views</th><th>Created</th><th style="width:200px">Actions</th></tr>
        </thead>
        <tbody>
            <?php if (!$ads): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No ads found.</td></tr>
          <?php else: ?>
            <?php foreach ($ads as $ad): ?>
              <tr>
                <td class="fw-bold"><?= $ad['id'] ?></td>
                <td><?= htmlspecialchars($ad['title']) ?></td>
                <td><?= htmlspecialchars($ad['first_name'] . ' ' . $ad['last_name']) ?></td>
                <td><?= htmlspecialchars($ad['category_name']) ?></td>
                <td><?= htmlspecialchars($ad['area_name']) ?></td>
                <td><span class="badge rounded-pill px-3" style="background:<?= $ad['status'] === 'active' ? 'var(--green)' : ($ad['status'] === 'pending' ? '#f59e0b' : '#ef4444') ?>!important"><?= htmlspecialchars($ad['status']) ?></span></td>
                <td><?= $ad['views'] ?></td>
                <td style="font-size:0.8rem;white-space:nowrap"><?= date('M d, Y', strtotime($ad['created_at'])) ?></td>
                <td>
                  <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-primary btn-sm-custom" data-bs-toggle="modal" data-bs-target="#editAdModal" data-ad='<?= json_encode($ad, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><i class="bi bi-pencil"></i></button>
                    <?php if ($ad['status'] !== 'active'): ?>
                      <form method="POST" action="manage_ads.php?action=approve&id=<?= $ad['id'] ?>" class="d-inline">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-success btn-sm-custom"><i class="bi bi-check-lg"></i></button>
                      </form>
                    <?php endif; ?>
                    <?php if ($ad['status'] !== 'suspended'): ?>
                      <form method="POST" action="manage_ads.php?action=suspend&id=<?= $ad['id'] ?>" class="d-inline">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-warning btn-sm-custom"><i class="bi bi-pause"></i></button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" action="manage_ads.php?action=delete&id=<?= $ad['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this ad?');">
                      <?= csrfField() ?>
                      <button type="submit" class="btn btn-danger btn-sm-custom"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center pagination-custom">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a></li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?= ($p === $page) ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a></li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>

<!-- Edit Ad Modal -->
<div class="modal fade" id="editAdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" id="editAdForm" class="modal-content" style="border:none;border-radius:var(--radius-lg)">
      <?= csrfField() ?>
      <div class="modal-header" style="border-bottom:1px solid var(--gray-border)">
        <h5 class="modal-title fw-bold">Edit Ad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editAdId">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Title</label>
          <input type="text" name="title" id="editTitle" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Category</label>
            <select name="category_id" id="editCategory" class="form-select" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
              <option value="">-- Select --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Area</label>
            <select name="area_id" id="editArea" class="form-select" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
              <option value="">-- Select --</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Description</label>
          <textarea name="description" id="editDescription" class="form-control" rows="3" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem"></textarea>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--gray-border)">
        <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;border-radius:var(--radius);font-weight:600">Update Ad</button>
        <button type="button" class="btn btn-secondary" style="border-radius:var(--radius)" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Create Ad Modal -->
<div class="modal fade" id="createAdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="manage_ads.php?action=create" class="modal-content" style="border:none;border-radius:var(--radius-lg)">
      <?= csrfField() ?>
      <div class="modal-header" style="border-bottom:1px solid var(--gray-border)">
        <h5 class="modal-title fw-bold">Create Ad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Title</label>
          <input type="text" name="title" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Category</label>
            <select name="category_id" class="form-select" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
              <option value="">-- Select --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Area</label>
            <select name="area_id" class="form-select" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
              <option value="">-- Select --</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Description</label>
          <textarea name="description" class="form-control" rows="3" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">User ID</label>
          <input type="number" name="user_id" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required placeholder="Enter User ID">
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--gray-border)">
        <button type="submit" class="btn btn-success" style="background:#10b981;border:none;border-radius:var(--radius);font-weight:600">Create Ad</button>
        <button type="button" class="btn btn-secondary" style="border-radius:var(--radius)" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editAdModal')?.addEventListener('show.bs.modal', event => {
  const ad = JSON.parse(event.relatedTarget.getAttribute('data-ad'));
  document.getElementById('editAdId').value = ad.id;
  document.getElementById('editTitle').value = ad.title;
  document.getElementById('editCategory').value = ad.category_id;
  document.getElementById('editArea').value = ad.area_id;
  document.getElementById('editDescription').value = ad.description || '';
  document.getElementById('editAdForm').action = 'manage_ads.php?action=edit&id=' + ad.id;
});
</script>
</body>
</html>
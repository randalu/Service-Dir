<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

$action = $_GET['action'] ?? '';
$areaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if (!$name) {
        $message = "Please enter area name.";
    } else {
        if ($action === 'create') {
            $stmt = $pdo->prepare("SELECT id FROM areas WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                $message = "Area already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO areas (name) VALUES (?)");
                $stmt->execute([$name]);
                header('Location: manage_areas.php?msg=Area added');
                exit;
            }
        } elseif ($action === 'edit' && $areaId) {
            $stmt = $pdo->prepare("SELECT id FROM areas WHERE name = ? AND id != ?");
            $stmt->execute([$name, $areaId]);
            if ($stmt->fetch()) {
                $message = "Another area with the same name exists.";
            } else {
                $stmt = $pdo->prepare("UPDATE areas SET name = ? WHERE id = ?");
                $stmt->execute([$name, $areaId]);
                header('Location: manage_areas.php?msg=Area updated');
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $areaId) {
    $stmt = $pdo->prepare("DELETE FROM areas WHERE id = ?");
    $stmt->execute([$areaId]);
    header('Location: manage_areas.php?msg=Area deleted');
    exit;
}

$areas = $pdo->query("SELECT * FROM areas ORDER BY id DESC")->fetchAll();

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
  <title>Manage Areas</title>
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
    <div class="brand"><span>Admin Panel</span><small>Areas</small></div>
    <div class="nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
      <a href="manage_ads.php"><i class="bi bi-megaphone"></i><span>Manage Ads</span></a>
      <a href="manage_categories.php"><i class="bi bi-folder"></i><span>Categories</span></a>
      <a href="manage_areas.php" class="active"><i class="bi bi-geo-alt"></i><span>Areas</span></a>
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
      <h1 class="page-title mb-0">Manage Areas</h1>
      <button class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600;font-size:0.85rem;padding:0.5rem 1.2rem" data-bs-toggle="modal" data-bs-target="#createAreaModal">+ Add Area</button>
    </div>
    <?php if ($message): ?>
      <div class="alert alert-modern alert-info mb-4"><?= $message ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead>
          <tr><th>ID</th><th>Area Name</th><th style="width:180px">Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$areas): ?>
            <tr><td colspan="3" class="text-center text-muted py-4">No areas found.</td></tr>
          <?php else: ?>
            <?php foreach ($areas as $area): ?>
              <tr>
                <td class="fw-bold"><?= $area['id'] ?></td>
                <td><?= htmlspecialchars($area['name']) ?></td>
                <td>
                  <button class="btn btn-primary btn-sm-custom" data-bs-toggle="modal" data-bs-target="#editAreaModal" data-area='<?= json_encode($area, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><i class="bi bi-pencil"></i> Edit</button>
                  <form method="POST" action="?action=delete&id=<?= $area['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this area?');">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-danger btn-sm-custom"><i class="bi bi-trash"></i> Delete</button>
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

<div class="modal fade" id="createAreaModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="?action=create" class="modal-content" style="border:none;border-radius:var(--radius-lg)">
      <?= csrfField() ?>
      <div class="modal-header" style="border-bottom:1px solid var(--gray-border)">
        <h5 class="modal-title fw-bold">Add Area</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label fw-semibold small">Area Name</label>
        <input type="text" name="name" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--gray-border)">
        <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;border-radius:var(--radius);font-weight:600">Add Area</button>
        <button type="button" class="btn btn-secondary" style="border-radius:var(--radius)" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editAreaModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" id="editAreaForm" class="modal-content" style="border:none;border-radius:var(--radius-lg)">
      <?= csrfField() ?>
      <div class="modal-header" style="border-bottom:1px solid var(--gray-border)">
        <h5 class="modal-title fw-bold">Edit Area</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editAreaId">
        <label class="form-label fw-semibold small">Area Name</label>
        <input type="text" name="name" id="editAreaName" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--gray-border)">
        <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;border-radius:var(--radius);font-weight:600">Update</button>
        <button type="button" class="btn btn-secondary" style="border-radius:var(--radius)" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editAreaModal')?.addEventListener('show.bs.modal', event => {
  const area = JSON.parse(event.relatedTarget.getAttribute('data-area'));
  document.getElementById('editAreaId').value = area.id;
  document.getElementById('editAreaName').value = area.name;
  document.getElementById('editAreaForm').action = 'manage_areas.php?action=edit&id=' + area.id;
});
</script>
</body>
</html>
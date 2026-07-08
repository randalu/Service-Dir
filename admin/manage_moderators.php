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
$modId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'] ?? 'moderator';
        if (!$username || !$password) {
            $message = "Please fill all required fields.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $message = "Username already exists.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $role]);
                header('Location: manage_moderators.php?msg=Moderator created');
                exit;
            }
        }
    } elseif ($action === 'edit' && $modId) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'] ?? 'moderator';
        if (!$username) {
            $message = "Please fill all required fields.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
            $stmt->execute([$username, $modId]);
            if ($stmt->fetch()) {
                $message = "Username already exists.";
            } else {
                if ($password) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admins SET username=?, password=?, role=? WHERE id=?");
                    $stmt->execute([$username, $hashedPassword, $role, $modId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE admins SET username=?, role=? WHERE id=?");
                    $stmt->execute([$username, $role, $modId]);
                }
                header('Location: manage_moderators.php?msg=Moderator updated');
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $modId) {
    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->execute([$modId]);
    header('Location: manage_moderators.php?msg=Moderator deleted');
    exit;
}

$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$stmtMods = $pdo->prepare("SELECT * FROM admins ORDER BY id DESC LIMIT ? OFFSET ?");
$stmtMods->bindValue(1, $perPage, PDO::PARAM_INT);
$stmtMods->bindValue(2, $offset, PDO::PARAM_INT);
$stmtMods->execute();
$moderators = $stmtMods->fetchAll();

$totalMods = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$totalPages = ceil($totalMods / $perPage);

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
  <title>Manage Moderators</title>
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
    .modal-content { border:none; border-radius:var(--radius-lg); }
  </style>
</head>
<body>
<div class="dashboard">
  <div class="sidebar">
    <div class="brand"><span>Admin Panel</span><small>Moderators</small></div>
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
      <a href="manage_settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
      <a href="manage_users.php"><i class="bi bi-people"></i><span>Users</span></a>
      <a href="manage_moderators.php" class="active"><i class="bi bi-shield"></i><span>Moderators</span></a>
      <a href="logout.php" class="text-danger" style="margin-top:2rem"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
  </div>
  <div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="page-title mb-0">Manage Moderators</h1>
      <button class="btn btn-primary" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600;font-size:0.85rem;padding:0.5rem 1.2rem" data-bs-toggle="modal" data-bs-target="#createModeratorModal">+ Add Moderator</button>
    </div>
    <?php if ($message): ?>
      <div class="alert alert-modern alert-info mb-4"><?= $message ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-modern">
        <thead>
          <tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th><th style="width:150px">Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$moderators): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No moderators found.</td></tr>
          <?php else: ?>
            <?php foreach ($moderators as $mod): ?>
              <tr>
                <td class="fw-bold"><?= $mod['id'] ?></td>
                <td><?= htmlspecialchars($mod['username']) ?></td>
                <td><span class="badge" style="background:<?= $mod['role'] === 'superadmin' ? '#f59e0b' : '#6366f1' ?>;border-radius:999px"><?= htmlspecialchars($mod['role']) ?></span></td>
                <td style="font-size:0.8rem;white-space:nowrap"><?= date('M d, Y', strtotime($mod['created_at'])) ?></td>
                <td>
                  <button class="btn btn-primary btn-sm-custom" data-bs-toggle="modal" data-bs-target="#editModeratorModal" data-mod='<?= json_encode($mod, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><i class="bi bi-pencil"></i></button>
                  <form method="POST" action="manage_moderators.php?action=delete&id=<?= $mod['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this moderator?');">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-danger btn-sm-custom"><i class="bi bi-trash"></i></button>
                  </form>
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

<!-- Create Modal -->
<div class="modal fade" id="createModeratorModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="?action=create" class="modal-content">
      <?= csrfField() ?>
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Create Moderator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label fw-semibold small">Username</label><input type="text" name="username" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required></div>
        <div class="mb-3"><label class="form-label fw-semibold small">Password</label><input type="password" name="password" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required></div>
        <div class="mb-3"><label class="form-label fw-semibold small">Role</label>
          <select name="role" class="form-select" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
            <option value="moderator">Moderator</option>
            <option value="superadmin">Superadmin</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success" style="background:#10b981;border:none;border-radius:var(--radius);font-weight:600">Create</button>
        <button type="button" class="btn btn-secondary" style="border-radius:var(--radius)" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModeratorModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" id="editModeratorForm" class="modal-content">
      <?= csrfField() ?>
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Moderator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editModId">
        <div class="mb-3"><label class="form-label fw-semibold small">Username</label><input type="text" name="username" id="editUsername" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required></div>
        <div class="mb-3"><label class="form-label fw-semibold small">Password (leave blank to keep)</label><input type="password" name="password" class="form-control" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem"></div>
        <div class="mb-3"><label class="form-label fw-semibold small">Role</label>
          <select name="role" id="editRole" class="form-select" style="border:1.5px solid var(--gray-border);border-radius:var(--radius);padding:0.6rem 1rem" required>
            <option value="moderator">Moderator</option>
            <option value="superadmin">Superadmin</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;border-radius:var(--radius);font-weight:600">Update</button>
        <button type="button" class="btn btn-secondary" style="border-radius:var(--radius)" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editModeratorModal')?.addEventListener('show.bs.modal', event => {
  const mod = JSON.parse(event.relatedTarget.getAttribute('data-mod'));
  document.getElementById('editModId').value = mod.id;
  document.getElementById('editUsername').value = mod.username;
  document.getElementById('editRole').value = mod.role;
  document.getElementById('editModeratorForm').action = 'manage_moderators.php?action=edit&id=' + mod.id;
});
</script>
</body>
</html>
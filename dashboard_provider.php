<?php
$pageTitle = 'Provider Dashboard - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'provider') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireCsrf();
    $delete_id = (int)$_POST['delete_id'];
    $stmtCheck = $pdo->prepare("SELECT id FROM services WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$delete_id, $_SESSION['user_id']]);
    if ($stmtCheck->fetch()) {
        $stmtDel = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmtDel->execute([$delete_id]);
        $_SESSION['message'] = "Service deleted successfully.";
    } else {
        $_SESSION['error'] = "Invalid service ID or permission denied.";
    }
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$tier = $pdo->prepare("SELECT * FROM pricing_tiers WHERE id = ?");
$tier->execute([$user['tier_id']]);
$tier = $tier->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE user_id = ?");
$stmt->execute([$user_id]);
$serviceCount = $stmt->fetchColumn();
$maxPosts = $tier['max_posts'] ?? 3;
$canAdd = $maxPosts === null || $serviceCount < $maxPosts;

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS category, a.name AS area 
    FROM services s
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC
");
$stmt->execute([$user_id]);
$services = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
    FROM reviews r
    WHERE r.to_user_id = ? AND r.is_approved = 1
");
$stmt->execute([$user_id]);
$reviewStats = $stmt->fetch();

include 'header.php';
?>

<div class="container mt-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($user['first_name']) ?>!</h3>
    <div class="d-flex gap-2">
      <a href="pricing.php" class="btn btn-outline-success btn-sm btn-rounded"><?= htmlspecialchars($tier['name'] ?? 'Free') ?> Plan</a>
      <a href="logout.php" class="btn btn-outline-danger btn-sm btn-rounded">Logout</a>
    </div>
  </div>

  <!-- Profile Card -->
  <div class="card mb-4" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow)">
    <div class="card-body p-4">
      <div class="row align-items-center">
        <div class="col-auto">
          <img src="uploads/<?= htmlspecialchars($user['profile_img']) ?>" alt="Profile" width="80" height="80" style="border-radius:var(--radius);object-fit:cover" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22><rect fill=%22%23e2e8f0%22 width=%2280%22 height=%2280%22/><text x=%2240%22 y=%2245%22 text-anchor=%22middle%22 fill=%22%2394a3b8%22 font-size=%2232%22>👤</text></svg>'">
        </div>
        <div class="col">
          <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
          <?php if (!empty($user['business_name'])): ?>
            <p class="text-muted mb-1 small">🏢 <?= htmlspecialchars($user['business_name']) ?></p>
          <?php endif; ?>
          <p class="text-muted mb-1 small"><?= htmlspecialchars($user['mobile']) ?></p>
          <?php if (!empty($user['email'])): ?>
            <p class="text-muted mb-0 small"><?= htmlspecialchars($user['email']) ?></p>
          <?php endif; ?>
        </div>
        <div class="col-auto d-flex gap-2 flex-wrap">
          <a href="invoices.php" class="btn btn-outline-success btn-sm btn-rounded">🧾 Invoices</a>
          <a href="edit_profile.php" class="btn btn-outline-primary btn-sm btn-rounded">Edit Profile</a>
          <a href="change_password.php" class="btn btn-outline-secondary btn-sm btn-rounded">Change Password</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-value"><?= $serviceCount ?> / <?= $maxPosts ?? '∞' ?></div>
        <div class="stat-label">Services Posted</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-value"><?= number_format($reviewStats['avg_rating'] ?? 0, 1) ?> ⭐</div>
        <div class="stat-label">Average Rating (<?= (int)($reviewStats['review_count'] ?? 0) ?> reviews)</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-value"><?= htmlspecialchars($tier['name'] ?? 'Free') ?></div>
        <div class="stat-label">Current Plan</div>
      </div>
    </div>
  </div>

  <?php
  // Check subscription status
  $stmt = $pdo->prepare("SELECT end_date, is_active FROM user_subscriptions WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
  $stmt->execute([$user_id]);
  $activeSub = $stmt->fetch();
  if ($activeSub && $activeSub['end_date']):
    $daysLeft = max(0, (strtotime($activeSub['end_date']) - time()) / 86400);
    if ($daysLeft > 0 && $daysLeft <= 7):
  ?>
    <div class="alert alert-warning" style="border-radius:var(--radius);font-size:0.9rem">
      <strong>⚠️ Subscription expiring soon!</strong> Your <?= htmlspecialchars($tier['name']) ?> plan ends on <?= date('M d, Y', strtotime($activeSub['end_date'])) ?> (<?= ceil($daysLeft) ?> day<?= $daysLeft > 1 ? 's' : '' ?> remaining).
      <a href="pricing.php" class="alert-link">Renew now</a>
    </div>
  <?php elseif ($daysLeft <= 0): ?>
    <div class="alert alert-danger" style="border-radius:var(--radius);font-size:0.9rem">
      <strong>⚠️ Subscription expired.</strong> Your <?= htmlspecialchars($tier['name']) ?> plan has ended. <a href="pricing.php" class="alert-link">Subscribe again</a>
    </div>
  <?php endif; endif; ?>

  <!-- Services -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Your Services (<?= count($services) ?>)</h4>
    <?php if ($canAdd): ?>
      <a href="add_service.php" class="btn btn-primary-custom btn-sm">+ Add Service</a>
    <?php else: ?>
      <button class="btn btn-secondary btn-sm" disabled>Limit Reached</button>
    <?php endif; ?>
  </div>

  <?php
  $pendingCount = 0;
  foreach ($services as $s) { if ($s['status'] === 'pending') $pendingCount++; }
  if ($pendingCount > 0): ?>
    <div class="alert alert-modern alert-warning mb-3">
      <strong>⏳ <?= $pendingCount ?> service(s) pending approval.</strong> Once approved by an admin, they will be visible to everyone.
    </div>
  <?php endif; ?>

  <?php if (count($services) === 0): ?>
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <h5>No services yet</h5>
      <p class="text-muted">Add your first service to get started.</p>
      <a href="add_service.php" class="btn btn-primary-custom">Add Service</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($services as $s): ?>
        <div class="col-md-6">
          <div class="dashboard-card">
            <div class="card-header-custom">
              <h5><?= htmlspecialchars($s['title']) ?></h5>
              <span class="badge rounded-pill px-3" style="background:var(--green)!important">
                <?= htmlspecialchars($s['status']) ?>
              </span>
            </div>
            <div class="card-body-custom p-3">
              <p class="text-muted small mb-2">
                📍 <?= htmlspecialchars($s['area']) ?> &middot; 👁️ <?= $s['views'] ?> views &middot; 📅 <?= date('M d, Y', strtotime($s['created_at'])) ?>
              </p>
              <p class="small mb-3"><?= nl2br(htmlspecialchars(substr($s['description'], 0, 150))) ?><?= strlen($s['description']) > 150 ? '...' : '' ?></p>
              <div class="d-flex gap-2">
                <a href="edit_service.php?id=<?= $s['id'] ?>" class="btn btn-outline-primary btn-sm btn-rounded">Edit</a>
                <form method="POST" onsubmit="return confirm('Delete this service?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm btn-rounded">Delete</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Reviews Received -->
  <?php
  $reviews = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    JOIN users u ON r.from_user_id = u.id
    WHERE r.to_user_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC LIMIT 5
  ");
  $reviews->execute([$user_id]);
  $reviews = $reviews->fetchAll();
  ?>
  <!-- Security Section -->
  <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
    <h4 class="fw-bold mb-0">🔒 Security</h4>
  </div>
  <div class="dashboard-card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <strong>Two-Factor Authentication</strong>
        <p class="text-muted small mb-0">Add an extra layer of security to your account</p>
      </div>
      <div>
        <?php if (!empty($user['totp_enabled'])): ?>
          <span class="badge bg-success rounded-pill px-3 py-2 me-2">✅ Enabled</span>
        <?php else: ?>
          <span class="badge bg-secondary rounded-pill px-3 py-2 me-2">Disabled</span>
        <?php endif; ?>
        <a href="setup_2fa.php" class="btn btn-outline-primary btn-sm btn-rounded">Manage</a>
      </div>
    </div>
    <?php if (!empty($user['google_id'])): ?>
    <hr>
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <strong>Linked Accounts</strong>
        <p class="text-muted small mb-0">Google: <?= htmlspecialchars($user['google_email'] ?? 'Connected') ?></p>
      </div>
      <form method="POST" action="google_unlink.php" onsubmit="return confirm('Unlink Google account?');" class="d-inline">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm btn-rounded">Unlink</button>
      </form>
    </div>
    <?php elseif (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID): ?>
    <hr>
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <strong>Linked Accounts</strong>
        <p class="text-muted small mb-0">Link your Google account for quick login</p>
      </div>
      <a href="google_login.php?link=1" class="btn btn-outline-primary btn-sm btn-rounded">Link Google</a>
    </div>
    <?php endif; ?>
  </div>

  <?php if (count($reviews) > 0): ?>
  <h4 class="fw-bold mb-3 mt-4">Recent Reviews</h4>
  <div class="row g-3">
    <?php foreach ($reviews as $rev): ?>
    <div class="col-md-6">
      <div class="dashboard-card">
        <div class="card-body-custom p-3">
          <div class="d-flex justify-content-between mb-1">
            <strong><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></strong>
            <span class="small"><?= str_repeat('⭐', (int)$rev['rating']) ?></span>
          </div>
          <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

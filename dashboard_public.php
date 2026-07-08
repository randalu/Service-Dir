<?php
$pageTitle = 'My Reviews - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'public') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, s.title AS service_title
    FROM reviews r
    LEFT JOIN users u ON r.to_user_id = u.id
    LEFT JOIN services s ON r.service_id = s.id
    WHERE r.from_user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$myReviews = $stmt->fetchAll();

include 'header.php';
?>

<div class="container mt-4">
  <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-modern alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($user['first_name']) ?>!</h3>
    <div class="d-flex gap-2">
      <a href="invoices.php" class="btn btn-outline-success btn-sm btn-rounded">🧾 Invoices</a>
      <a href="edit_profile.php" class="btn btn-outline-primary btn-sm btn-rounded">Edit Profile</a>
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
          <p class="text-muted mb-0 small"><?= htmlspecialchars($user['mobile']) ?></p>
          <?php if (!empty($user['email'])): ?>
            <p class="text-muted mb-0 small"><?= htmlspecialchars($user['email']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Security Section -->
  <div class="d-flex justify-content-between align-items-center mb-3">
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

  <h4 class="fw-bold mb-3">My Reviews (<?= count($myReviews) ?>)</h4>

  <?php if (count($myReviews) === 0): ?>
    <div class="empty-state">
      <div class="empty-icon">✍️</div>
      <h5>No reviews yet</h5>
      <p class="text-muted">Browse services and leave a review for providers.</p>
      <a href="index.php" class="btn btn-primary-custom">Browse Services</a>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($myReviews as $rev): ?>
      <div class="col-md-6">
        <div class="dashboard-card">
          <div class="card-body-custom p-3">
            <div class="d-flex justify-content-between mb-1">
              <strong>To: <?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></strong>
              <span class="small"><?= str_repeat('⭐', (int)$rev['rating']) ?></span>
            </div>
            <?php if (!empty($rev['service_title'])): ?>
              <p class="small text-muted mb-1">Service: <?= htmlspecialchars($rev['service_title']) ?></p>
            <?php endif; ?>
            <p class="small mb-1"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
            <span class="badge rounded-pill px-3" style="background:<?= $rev['is_approved'] ? 'var(--green)' : 'var(--gray-500)' ?>!important">
              <?= $rev['is_approved'] ? 'Approved' : 'Pending' ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

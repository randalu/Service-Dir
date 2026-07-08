<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$userId) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { header('Location: index.php'); exit; }

$pageTitle = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ' - Service Provider Profile';
$metaDesc = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ' - ' . (!empty($user['business_name']) ? htmlspecialchars($user['business_name']) . ' | ' : '') . 'Service Provider in Raddoluwa/Seeduwa area';
$canonicalUrl = rtrim(APP_URL, '/') . '/provider_profile.php?id=' . $userId;
$ogImage = rtrim(APP_URL, '/') . '/uploads/' . htmlspecialchars($user['profile_img']);

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS category, a.name AS area, u.first_name, u.last_name, u.mobile
    FROM services s
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.id DESC
");
$stmt->execute([$userId]);
$services = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS count FROM reviews WHERE to_user_id = ? AND is_approved = 1");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON r.from_user_id = u.id WHERE r.to_user_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$reviews = $stmt->fetchAll();

include 'header.php';
?>
<div class="container mt-4">
  <div class="card mb-4" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow)">
    <div class="card-body p-4">
      <div class="row align-items-center">
        <div class="col-auto">
          <img src="uploads/<?= htmlspecialchars($user['profile_img']) ?>" alt="" width="100" height="100" style="border-radius:var(--radius);object-fit:cover" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%23e2e8f0%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%2394a3b8%22 font-size=%2240%22>👤</text></svg>'">
        </div>
        <div class="col">
          <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
          <?php if (!empty($user['business_name'])): ?>
            <p class="text-muted mb-1">🏢 <?= htmlspecialchars($user['business_name']) ?></p>
          <?php endif; ?>
          <?php if (!empty($user['bio'])): ?>
            <p class="mb-2"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
          <?php endif; ?>
          <div class="d-flex gap-3 small text-muted">
            <span>⭐ <?= number_format($stats['avg_rating'] ?? 0, 1) ?> (<?= (int)$stats['count'] ?> reviews)</span>
            <span>📋 <?= count($services) ?> service(s)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <h4 class="fw-bold mb-3">Services</h4>
  <?php if (count($services) === 0): ?>
    <div class="empty-state"><div class="empty-icon">📋</div><h5>No services listed yet</h5></div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($services as $s) { echo renderCard($s); } ?>
    </div>
  <?php endif; ?>

  <h4 class="fw-bold mb-3 mt-4">Reviews (<?= count($reviews) ?>)</h4>
  <?php if (count($reviews) === 0): ?>
    <p class="text-muted">No reviews yet.</p>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
    <div class="card mb-2" style="border:none;border-radius:var(--radius);box-shadow:0 1px 3px rgba(0,0,0,0.08)">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between">
          <strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
          <span><?= str_repeat('⭐', (int)$r['rating']) ?></span>
        </div>
        <p class="small text-muted mb-0 mt-1"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>

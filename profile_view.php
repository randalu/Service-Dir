<?php
$pageTitle = 'Profile - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    die("Invalid user ID.");
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    die("User not found.");
}

// Get rating stats
$stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE to_user_id = ? AND is_approved = 1");
$stmt->execute([$user_id]);
$ratingStats = $stmt->fetch();

// Get reviews received
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    JOIN users u ON r.from_user_id = u.id
    WHERE r.to_user_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$reviews = $stmt->fetchAll();

// Check if current user can review this user
$canReview = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE from_user_id = ? AND to_user_id = ? AND service_id IS NULL");
    $stmt->execute([$_SESSION['user_id'], $user_id]);
    $canReview = !$stmt->fetch();
}

include 'header.php';
?>

<div class="container mt-4" style="max-width:640px">
  <!-- Profile Card -->
  <div class="card mb-4" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)">
    <div class="card-body p-4 text-center">
      <img src="uploads/<?= htmlspecialchars($profile['profile_img']) ?>" alt="Profile"
           width="100" height="100"
           style="border-radius:50%;object-fit:cover;margin-bottom:1rem"
           onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%23e2e8f0%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%2394a3b8%22 font-size=%2236%22>👤</text></svg>'">
      <h4 class="fw-bold mb-1"><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h4>
      <?php if (!empty($profile['business_name'])): ?>
        <p class="text-muted mb-1">🏢 <?= htmlspecialchars($profile['business_name']) ?></p>
      <?php endif; ?>
      <?php if (!empty($profile['bio'])): ?>
        <p class="text-muted mb-2"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
      <?php endif; ?>
      <div class="mb-2">
        <span class="tier-badge"><?= htmlspecialchars($profile['role'] === 'provider' ? 'Provider' : 'Public') ?></span>
      </div>
      <div class="stars-display mb-2" style="font-size:1.2rem">
        <?= str_repeat('⭐', (int)round($ratingStats['avg_rating'] ?? 0)) ?>
        <span class="text-muted small"><?= number_format($ratingStats['avg_rating'] ?? 0, 1) ?> (<?= (int)($ratingStats['review_count'] ?? 0) ?> reviews)</span>
      </div>
      <?php if (!empty($profile['mobile'])): ?>
        <a href="tel:<?= htmlspecialchars($profile['mobile']) ?>" class="btn btn-success btn-sm btn-rounded">📞 <?= htmlspecialchars($profile['mobile']) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Review Form -->
  <?php if ($canReview): ?>
  <div class="card mb-4" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow)">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-3">Write a Review for <?= htmlspecialchars($profile['first_name']) ?></h5>
      <form action="submit_review.php" method="POST" class="form-modern">
        <?= csrfField() ?>
        <input type="hidden" name="to_user_id" value="<?= $user_id ?>">
        <div class="mb-3">
          <label class="form-label">Rating</label>
          <div class="star-rating">
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" name="rating" value="<?= $i ?>" id="pstar<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
            <label for="pstar<?= $i ?>" style="font-size:1.5rem;cursor:pointer;color:#ddd">&#9733;</label>
            <?php endfor; ?>
          </div>
          <style>
            .star-rating { direction: rtl; display: inline-flex; gap: 2px; }
            .star-rating input { display: none; }
            .star-rating label:hover,
            .star-rating label:hover ~ label,
            .star-rating input:checked ~ label { color: #f59e0b !important; }
          </style>
        </div>
        <div class="mb-3">
          <label class="form-label">Review</label>
          <textarea name="comment" class="form-control" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary-custom">Submit Review</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Reviews Display -->
  <h5 class="fw-bold mb-3">Reviews Received (<?= count($reviews) ?>)</h5>
  <?php if (count($reviews) === 0): ?>
    <div class="empty-state">
      <div class="empty-icon">💬</div>
      <h5>No reviews yet</h5>
    </div>
  <?php else: ?>
    <?php foreach ($reviews as $rev): ?>
    <div class="card mb-3" style="border:none;border-radius:var(--radius);box-shadow:var(--shadow-sm)">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between mb-1">
          <strong><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></strong>
          <span class="stars-display"><?= str_repeat('⭐', (int)$rev['rating']) ?></span>
        </div>
        <p class="small text-muted mb-1"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
        <small class="text-muted"><?= date('M d, Y', strtotime($rev['created_at'])) ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

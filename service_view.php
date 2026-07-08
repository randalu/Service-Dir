<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid service ID.");
}

$service_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.mobile, u.email, u.profile_img,
           u.business_name, u.id AS provider_id, c.name AS category, a.name AS area
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    WHERE s.id = ?
");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    die("Service not found.");
}

$pdo->prepare("UPDATE services SET views = views + 1 WHERE id = ?")->execute([$service_id]);

// Get images
$stmt = $pdo->prepare("SELECT * FROM service_images WHERE service_id = ? ORDER BY is_primary DESC, sort_order");
$stmt->execute([$service_id]);
$images = $stmt->fetchAll();

// Get average rating
$stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE to_user_id = ? AND is_approved = 1");
$stmt->execute([$service['provider_id']]);
$ratingStats = $stmt->fetch();

// Get approved reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    JOIN users u ON r.from_user_id = u.id
    WHERE r.service_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
");
$stmt->execute([$service_id]);
$reviews = $stmt->fetchAll();

// Check if current user can review
$canReview = false;
$userReview = null;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $service['provider_id']) {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE from_user_id = ? AND to_user_id = ? AND service_id = ?");
    $stmt->execute([$_SESSION['user_id'], $service['provider_id'], $service_id]);
    $userReview = $stmt->fetch();
    $canReview = !$userReview;
}

// Get map key
$googleMapsKey = '';
$stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'google_maps_api_key'");
$stmt->execute();
$row = $stmt->fetch();
if ($row) $googleMapsKey = $row['value'];

$isFeatured = $service['is_featured'] && $service['featured_until'] && $service['featured_until'] >= date('Y-m-d');

$pageTitle = htmlspecialchars($service['title']) . ' in ' . htmlspecialchars($service['area']) . ' | ' . htmlspecialchars($service['category']) . ' Service';

$desc = htmlspecialchars(substr(strip_tags($service['description']), 0, 150));
$imgUrl = 'https://lka.ovh/srv/uploads/' . htmlspecialchars($service['profile_img']);
$pageUrl = 'https://lka.ovh/srv/service_view.php?id=' . $service_id;

$pageHead = <<<HEAD
<meta name="description" content="{$desc}">
<link rel="canonical" href="{$pageUrl}" />
<meta property="og:type" content="article">
<meta property="og:title" content="{$pageTitle}">
<meta property="og:description" content="{$desc}">
<meta property="og:url" content="{$pageUrl}">
<meta property="og:image" content="{$imgUrl}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$pageTitle}">
<meta name="twitter:description" content="{$desc}">
<meta name="twitter:image" content="{$imgUrl}">
<meta name="keywords" content="{$service['category']}, {$service['area']}, services in {$service['area']}">
HEAD;

include 'header.php';
?>

<div class="container mt-4 detail-page">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
      <li class="breadcrumb-item"><a href="category.php?id=<?= $service['category_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($service['category']) ?></a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($service['title']) ?></li>
    </ol>
  </nav>

  <div class="detail-card card">
    <div class="card-body">
      <div class="detail-header">
        <?php if ($isFeatured): ?>
        <span class="badge rounded-pill px-3 py-2 mb-2" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-weight:700">⭐ Featured</span>
        <?php endif; ?>
        <span class="badge bg-primary rounded-pill px-3 py-2 mb-2" style="background:var(--primary)!important"><?= htmlspecialchars($service['category']) ?></span>
        <h2><?= htmlspecialchars($service['title']) ?></h2>
      </div>

      <div class="detail-meta">
        <span class="meta-item">📍 <?= htmlspecialchars($service['area']) ?></span>
        <span class="meta-item">📅 <?= date('M d, Y', strtotime($service['created_at'])) ?></span>
        <span class="meta-item">👁️ <?= $service['views'] + 1 ?> views</span>
        <span class="meta-item">⭐ <?= number_format($ratingStats['avg_rating'] ?? 0, 1) ?> (<?= (int)($ratingStats['review_count'] ?? 0) ?> reviews)</span>
      </div>

      <!-- Image Gallery -->
      <?php if (count($images) > 0): ?>
      <div class="gallery-grid">
        <?php
        $mainImg = $images[0];
        $sideImgs = array_slice($images, 1, 2);
        ?>
        <div class="gallery-main">
          <img src="uploads/<?= htmlspecialchars($mainImg['image_path']) ?>" alt="<?= htmlspecialchars($service['title']) ?>" data-lightbox="uploads/<?= htmlspecialchars($mainImg['image_path']) ?>">
        </div>
        <?php if (count($sideImgs) > 0): ?>
        <div class="gallery-side">
          <?php foreach ($sideImgs as $img): ?>
          <img src="uploads/<?= htmlspecialchars($img['image_path']) ?>" alt="Image" data-lightbox="uploads/<?= htmlspecialchars($img['image_path']) ?>">
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Description -->
      <div class="detail-description">
        <?= nl2br(htmlspecialchars($service['description'])) ?>
      </div>

      <!-- Map -->
      <?php if (!empty($service['latitude']) && !empty($service['longitude']) && !empty($googleMapsKey)): ?>
      <div class="mb-4">
        <h5 class="fw-bold mb-3">📍 Location</h5>
        <?php if (!empty($service['physical_address'])): ?>
        <p class="text-muted small mb-2"><?= htmlspecialchars($service['physical_address']) ?></p>
        <?php endif; ?>
        <div style="border-radius:var(--radius);overflow:hidden;max-width:100%">
          <iframe
            width="100%"
            height="300"
            style="border:0;display:block"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps/embed/v1/place?key=<?= htmlspecialchars($googleMapsKey) ?>&q=<?= $service['latitude'] ?>,<?= $service['longitude'] ?>&zoom=15">
          </iframe>
        </div>
      </div>
      <?php endif; ?>

      <!-- Contact Card -->
      <div class="contact-card">
        <a href="profile_view.php?id=<?= $service['provider_id'] ?>">
          <img src="uploads/<?= htmlspecialchars($service['profile_img']) ?>" alt="Profile" class="contact-avatar" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22><rect fill=%22%23e2e8f0%22 width=%2260%22 height=%2260%22/><text x=%2230%22 y=%2235%22 text-anchor=%22middle%22 fill=%22%2394a3b8%22 font-size=%2224%22>👤</text></svg>'">
        </a>
        <div class="contact-info">
          <div class="name">
            <a href="profile_view.php?id=<?= $service['provider_id'] ?>" class="text-decoration-none" style="color:var(--navy)">
              <?= htmlspecialchars($service['first_name'] . ' ' . $service['last_name']) ?>
            </a>
          </div>
          <?php if (!empty($service['business_name'])): ?>
            <div class="detail">🏢 <?= htmlspecialchars($service['business_name']) ?></div>
          <?php endif; ?>
          <div class="detail"><?= htmlspecialchars($service['area']) ?></div>
          <div class="stars-display mb-2"><?= str_repeat('⭐', (int)round($ratingStats['avg_rating'] ?? 0)) ?></div>
          <div class="mt-2 d-flex gap-2 flex-wrap">
            <a href="tel:<?= htmlspecialchars($service['mobile']) ?>" class="btn btn-success btn-sm btn-rounded">
              📞 <?= htmlspecialchars($service['mobile']) ?>
            </a>
            <?php if (!empty($service['email'])): ?>
              <a href="mailto:<?= htmlspecialchars($service['email']) ?>" class="btn btn-outline-primary btn-sm btn-rounded">
                ✉️ <?= htmlspecialchars($service['email']) ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Review Form -->
      <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $service['provider_id']): ?>
        <?php if ($canReview): ?>
        <div class="mt-4">
          <h5 class="fw-bold mb-3">Write a Review</h5>
          <form action="submit_review.php" method="POST" class="form-modern">
            <?= csrfField() ?>
            <input type="hidden" name="to_user_id" value="<?= $service['provider_id'] ?>">
            <input type="hidden" name="service_id" value="<?= $service_id ?>">
            <div class="mb-3">
              <label class="form-label">Rating</label>
              <div class="star-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                <label for="star<?= $i ?>" style="font-size:1.5rem;cursor:pointer;color:#ddd;transition:color 0.2s">&#9733;</label>
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
              <label class="form-label">Your Review</label>
              <textarea name="comment" class="form-control" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary-custom">Submit Review</button>
          </form>
        </div>
        <?php elseif ($userReview): ?>
          <div class="mt-4 alert alert-modern alert-info">You have already reviewed this service.</div>
        <?php endif; ?>
      <?php elseif (!isset($_SESSION['user_id'])): ?>
        <div class="mt-4 text-center">
          <a href="login.php" class="btn btn-outline-primary btn-rounded">Sign in to leave a review</a>
        </div>
      <?php endif; ?>

      <!-- Reviews Display -->
      <?php if (count($reviews) > 0): ?>
      <div class="mt-5">
        <h5 class="fw-bold mb-3">Reviews (<?= count($reviews) ?>)</h5>
        <div class="row g-3">
          <?php foreach ($reviews as $rev): ?>
          <div class="col-md-6">
            <div class="p-3" style="background:var(--gray-50);border-radius:var(--radius)">
              <div class="d-flex justify-content-between mb-1">
                <strong><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></strong>
                <span class="stars-display"><?= str_repeat('⭐', (int)$rev['rating']) ?></span>
              </div>
              <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
              <small class="text-muted"><?= date('M d, Y', strtotime($rev['created_at'])) ?></small>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="index.php" class="btn btn-outline-secondary btn-rounded px-4">&larr; Back to Home</a>
      </div>
    </div>
  </div>
</div>

<script src="includes/lightbox.js"></script>
<?php include 'footer.php'; ?>

<?php
$pageTitle = 'Service Directory - Find Local Services in Raddoluwa/Seeduwa';
$metaDesc = 'Find trusted local services in Raddoluwa/Seeduwa — plumbing, electrical, cleaning, and more. Free directory for community service providers.';
$canonicalUrl = rtrim(APP_URL, '/');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

$pageHead = '<meta name="keywords" content="Raddoluwa services, Seeduwa services, local service directory, Sri Lanka service providers, Raddoluwa Seeduwa">';
include 'header.php';

$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'settings'");
$hasV2 = $stmt->fetchColumn() > 0;

// Auto-expire past featured
if ($hasV2) {
    try {
        $pdo->exec("UPDATE services SET is_featured = 0, featured_until = NULL WHERE featured_until IS NOT NULL AND featured_until < CURDATE()");
    } catch (Exception $e) {}
}

$categoriesWithAds = $pdo->query("
    SELECT c.id, c.name, COUNT(s.id) AS ad_count
    FROM categories c
    JOIN services s ON c.id = s.category_id
    WHERE s.status = 'active'
    GROUP BY c.id
    HAVING ad_count > 0
    ORDER BY c.name
")->fetchAll();

// Featured listings
$featuredAds = [];
if ($hasV2) {
    $featuredAds = $pdo->query("
        SELECT s.*, u.first_name, u.last_name, u.mobile, c.name AS category, a.name AS area
        FROM services s
        JOIN users u ON s.user_id = u.id
        JOIN categories c ON s.category_id = c.id
        JOIN areas a ON s.area_id = a.id
        WHERE s.is_featured = 1 AND s.featured_until >= CURDATE() AND s.status = 'active'
        ORDER BY s.featured_until ASC LIMIT 6
    ")->fetchAll();
}

$mostViewed = $pdo->query("
    SELECT s.*, u.first_name, u.last_name, u.mobile, c.name AS category, a.name AS area
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    WHERE s.status = 'active'
    ORDER BY s.views DESC LIMIT 6
")->fetchAll();

$latestAds = $pdo->query("
    SELECT s.*, u.first_name, u.last_name, u.mobile, c.name AS category, a.name AS area
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    WHERE s.status = 'active'
    ORDER BY s.id DESC LIMIT 10
")->fetchAll();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$catFilter = isset($_GET['category']) ? trim($_GET['category']) : '';

$where = ["s.status = 'active'"];
$params = [];
if ($search) {
    $where[] = "(s.title LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catFilter) {
    $where[] = "c.name = ?";
    $params[] = $catFilter;
}
$whereClause = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM services s JOIN categories c ON s.category_id = c.id $whereClause");
$countStmt->execute($params);
$totalAds = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalAds / $perPage));

$allAds = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.mobile, c.name AS category, a.name AS area
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    $whereClause
    ORDER BY s.id DESC
    LIMIT $perPage OFFSET $offset
");
$allAds->execute($params);
$allAds = $allAds->fetchAll();
?>

<?php if (empty($search) && empty($catFilter)): ?>
<!-- Hero -->
<section class="hero-section">
  <div class="container">
    <div class="hero-badge">✨ Raddoluwa &amp; Seeduwa Area</div>
    <div class="hero-logo">RDL <span>Service Directory</span></div>
    <p class="hero-sub">Discover trusted local service providers in your community</p>
    <form method="GET" class="hero-search">
      <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search services..." value="<?= htmlspecialchars($search) ?>">
        <select name="category" class="form-select d-none d-md-flex" style="max-width:160px;border-left:1px solid #e2e8f0">
          <option value="">All</option>
          <?php foreach ($categoriesWithAds as $cat): ?>
            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Search</button>
      </div>
    </form>
  </div>
</section>

<!-- Category Pills -->
<div class="container category-pills">
  <?php foreach ($categoriesWithAds as $cat): ?>
    <a href="category.php?id=<?= $cat['id'] ?>" class="category-pill">
      <?= htmlspecialchars($cat['name']) ?>
      <span class="count"><?= $cat['ad_count'] ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (count($featuredAds) > 0): ?>
<div class="container mt-4">
  <div class="section-header">
    <h3>⭐ Featured Services</h3>
  </div>
  <div class="featured-carousel">
    <?php foreach ($featuredAds as $ad): ?>
      <div class="featured-item">
        <?= renderCard($ad, false) ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="container mt-4">

  <?php if (!empty($search) || !empty($catFilter)): ?>
  <!-- Search results header -->
  <form method="GET" class="row mb-4 g-2">
    <div class="col-md-5">
      <input type="text" name="search" class="form-control form-modern" placeholder="Search services..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3">
      <select name="category" class="form-select form-modern">
        <option value="">All Categories</option>
        <?php foreach ($categoriesWithAds as $cat): ?>
          <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $catFilter === $cat['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary-custom w-100">Search</button>
    </div>
  </form>

  <div class="section-header">
    <h3><?= $totalAds ?> service<?= $totalAds !== 1 ? 's' : '' ?> found</h3>
  </div>
  <?php endif; ?>

  <!-- Ads Grid -->
  <?php if (count($allAds) === 0): ?>
    <div class="empty-state">
      <div class="empty-icon">🔍</div>
      <h5>No services found</h5>
      <p class="text-muted">Try adjusting your search or browse categories above.</p>
      <a href="index.php" class="btn btn-primary-custom">Browse All</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($allAds as $ad): ?>
        <?= renderCard($ad) ?>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center pagination-modern">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($catFilter) ?>">Previous</a>
        </li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($catFilter) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($catFilter) ?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (empty($search) && empty($catFilter)): ?>
  <!-- Latest Ads -->
  <div class="section-header mt-5">
    <h3>Latest Services</h3>
    <?php if (count($latestAds) >= 10): ?>
      <a href="?page=2" class="see-all">See all &rarr;</a>
    <?php endif; ?>
  </div>
  <div class="row g-4 mb-5">
    <?php foreach ($latestAds as $ad): ?>
      <?= renderCard($ad) ?>
    <?php endforeach; ?>
  </div>

  <!-- Most Viewed -->
  <div class="section-header">
    <h3>Most Viewed Services</h3>
  </div>
  <div class="row g-4 mb-5">
    <?php foreach ($mostViewed as $ad): ?>
      <?= renderCard($ad) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
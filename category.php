<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($categoryId <= 0) {
    die("Invalid category ID.");
}

$adsPerPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $adsPerPage;

$stmtCat = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
$stmtCat->execute([$categoryId]);
$category = $stmtCat->fetch();
if (!$category) {
    die("Category not found.");
}

$pageTitle = htmlspecialchars($category['name']) . ' - Service Directory';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id = ? AND status = 'active'");
$stmtCount->execute([$categoryId]);
$totalAds = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalAds / $adsPerPage));

$stmtAds = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.mobile, c.name AS category, a.name AS area
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    JOIN areas a ON s.area_id = a.id
    WHERE s.category_id = ? AND s.status = 'active'
    ORDER BY s.id DESC
    LIMIT ? OFFSET ?
");
$stmtAds->bindValue(1, $categoryId, PDO::PARAM_INT);
$stmtAds->bindValue(2, $adsPerPage, PDO::PARAM_INT);
$stmtAds->bindValue(3, $offset, PDO::PARAM_INT);
$stmtAds->execute();
$ads = $stmtAds->fetchAll();

include 'header.php';
?>

<div class="container mb-5 mt-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($category['name']) ?></li>
    </ol>
  </nav>

  <div class="section-header">
    <h3><?= htmlspecialchars($category['name']) ?></h3>
    <span class="text-muted small"><?= $totalAds ?> service<?= $totalAds !== 1 ? 's' : '' ?></span>
  </div>

  <?php if (count($ads) === 0): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <h5>No services in this category yet</h5>
      <a href="index.php" class="btn btn-primary-custom">Browse all categories</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($ads as $ad): ?>
        <?= renderCard($ad) ?>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center pagination-modern">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?id=<?= $categoryId ?>&page=<?= $page - 1 ?>">Previous</a>
        </li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
            <a class="page-link" href="?id=<?= $categoryId ?>&page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?id=<?= $categoryId ?>&page=<?= $page + 1 ?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
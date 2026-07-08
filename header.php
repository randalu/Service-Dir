<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

configureSession();
require_once __DIR__ . '/db.php';

$allCategories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? getSetting('site_name', 'Service Directory')) ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDesc ?? getSetting('site_name', 'Local Service Directory for Raddoluwa/Seeduwa area')) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? APP_URL) ?>" />
  <meta property="og:type" content="<?= $ogType ?? 'website' ?>">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? getSetting('site_name', 'Service Directory')) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDesc ?? getSetting('site_name', 'Local Service Directory for Raddoluwa/Seeduwa area')) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl ?? APP_URL) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($ogImage ?? rtrim(APP_URL, '/') . '/default-og.png') ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars(getSetting('site_name', 'Service Directory')) ?>">
  <meta name="twitter:card" content="<?= $twitterCard ?? 'summary_large_image' ?>">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? getSetting('site_name', 'Service Directory')) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($metaDesc ?? getSetting('site_name', 'Local Service Directory for Raddoluwa/Seeduwa area')) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImage ?? rtrim(APP_URL, '/') . '/default-og.png') ?>">
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <?= $pageHead ?? '' ?>
<?php
// JSON-LD Structured Data — Organization
$orgLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => getSetting('site_name', 'Service Directory'),
    'url' => APP_URL,
    'description' => 'Local Service Directory for Raddoluwa/Seeduwa area',
    'areaServed' => ['Raddoluwa', 'Seeduwa', 'Kandana', 'Katunayake', 'Negombo', 'Ja-Ela'],
];
echo '<script type="application/ld+json">' . json_encode($orgLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

// JSON-LD — WebSite with SearchAction
$siteLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'url' => APP_URL,
    'name' => getSetting('site_name', 'Service Directory'),
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => rtrim(APP_URL, '/') . '/?search={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
echo '<script type="application/ld+json">' . json_encode($siteLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-modern">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <?= htmlspecialchars(getSetting('site_name', 'RDL Service Directory')) ?>
      <small>Raddoluwa / Seeduwa</small>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Categories</a>
          <ul class="dropdown-menu">
            <?php foreach ($allCategories as $cat): ?>
              <li><a class="dropdown-item" href="category.php?id=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
      </ul>
      <div class="d-flex gap-2 align-items-center">
        <?php if (!isset($_SESSION['user_id'])): ?>
          <a href="login.php" class="btn btn-outline-light btn-sm">Login</a>
          <a href="register.php" class="btn btn-light btn-sm">Register</a>
        <?php else: ?>
          <a href="dashboard.php" class="btn btn-light btn-sm">Dashboard</a>
          <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<?php foreach (getFlashes() as $flash): ?>
  <div class="container mt-3">
    <div class="alert alert-modern alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
<?php endforeach; ?>
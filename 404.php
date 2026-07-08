<?php
http_response_code(404);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
$pageTitle = '404 - Page Not Found';
include 'header.php';
?>

<div class="container text-center mt-5">
  <h1 class="display-1 fw-bold" style="color:var(--primary);opacity:0.3">404</h1>
  <h3 class="fw-bold mb-2">Page Not Found</h3>
  <p class="text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
  <a href="index.php" class="btn btn-primary-custom">Back to Home</a>
</div>

<?php include 'footer.php'; ?>
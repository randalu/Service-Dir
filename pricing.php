<?php
$pageTitle = 'Pricing Plans - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

$tiers = $pdo->query("SELECT * FROM pricing_tiers ORDER BY sort_order")->fetchAll();

include 'header.php';
?>

<div class="container mt-5 mb-5">
  <div class="text-center mb-5">
    <h1 class="fw-bold display-6" style="color:var(--navy)">Choose Your Plan</h1>
    <p class="text-muted" style="font-size:1.1rem">Unlock more features as your business grows</p>
  </div>

  <div class="row g-4 justify-content-center">
    <?php foreach ($tiers as $t): ?>
    <div class="col-md-3 col-sm-6">
      <div class="card h-100" style="border:1px solid var(--gray-100);border-radius:var(--radius-lg);box-shadow:var(--shadow);transition:all 0.3s;overflow:hidden">
        <?php if ($t['name'] === 'Monthly' || $t['name'] === 'Yearly'): ?>
        <div style="background:var(--lime);color:var(--navy);text-align:center;padding:4px 0;font-size:0.75rem;font-weight:700;letter-spacing:0.05em">POPULAR</div>
        <?php endif; ?>
        <div class="card-body p-4 text-center">
          <h5 class="fw-bold mb-1" style="color:var(--navy)"><?= htmlspecialchars($t['name']) ?></h5>
          <p class="text-muted small mb-3">
            <?= $t['max_posts'] ? 'Up to ' . $t['max_posts'] . ' services' : 'Unlimited services' ?>
          </p>
          <div class="mb-3">
            <span style="font-size:2.5rem;font-weight:800;color:var(--forest)">Rs. <?= number_format($t['price'], 0) ?></span>
            <?php if ($t['is_subscription'] && $t['duration_days']): ?>
              <span class="text-muted d-block small">/ <?= $t['duration_days'] === 30 ? 'month' : ($t['duration_days'] === 365 ? 'year' : $t['duration_days'] . ' days') ?></span>
            <?php else: ?>
              <span class="text-muted d-block small">one-time</span>
            <?php endif; ?>
          </div>
          <ul class="list-unstyled text-start small mb-4" style="color:var(--gray-500)">
            <li class="mb-2">✅ <?= $t['max_posts'] ? $t['max_posts'] . ' service listings' : 'Unlimited listings' ?></li>
            <li class="mb-2"><?= $t['auto_approve'] ? '✅ Auto-approved listings' : '❌ Manual approval required' ?></li>
            <li class="mb-2">✅ Profile & business page</li>
            <li class="mb-2">✅ Customer reviews</li>
          </ul>
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="subscribe.php?tier_id=<?= $t['id'] ?>" class="btn w-100" style="background:var(--lime);color:var(--navy);font-weight:600;border-radius:var(--radius);padding:0.6rem"><?= $t['price'] > 0 ? 'Subscribe Now' : 'Free' ?></a>
          <?php else: ?>
            <a href="register.php" class="btn w-100" style="background:var(--lime);color:var(--navy);font-weight:600;border-radius:var(--radius);padding:0.6rem">Get Started</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include 'footer.php'; ?>

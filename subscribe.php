<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/db.php';

$tier_id = isset($_GET['tier_id']) ? (int)$_GET['tier_id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM pricing_tiers WHERE id = ?");
$stmt->execute([$tier_id]);
$tier = $stmt->fetch();

if (!$tier) {
    echo "<script>alert('Invalid tier.'); window.location='pricing.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Check existing active subscription
$stmt = $pdo->prepare("SELECT * FROM user_subscriptions WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$existingSub = $stmt->fetch();

$hasActiveSub = $existingSub && (!$existingSub['end_date'] || $existingSub['end_date'] >= date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // If user already has an active subscription, cancel it first
    if ($hasActiveSub && $existingSub['tier_id'] != $tier_id) {
        $pdo->prepare("UPDATE user_subscriptions SET is_active = 0, cancelled_at = ? WHERE id = ?")
            ->execute([date('Y-m-d'), $existingSub['id']]);
    }

    $payment_ref = 'MANUAL-' . strtoupper(bin2hex(random_bytes(6)));

    if ($tier['is_subscription'] && $tier['duration_days']) {
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime("+{$tier['duration_days']} days"));
    } else {
        $start = date('Y-m-d');
        $end = null;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, tier_id, start_date, end_date, is_active, payment_ref) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute([$user_id, $tier_id, $start, $end, $payment_ref]);
        $subId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("UPDATE users SET tier_id = ?, is_verified = 1 WHERE id = ?");
        $stmt->execute([$tier_id, $user_id]);

        $_SESSION['tier_id'] = $tier_id;

        // Generate invoice
        $invNo = 'INV-' . date('Y') . '-' . str_pad($subId, 5, '0', STR_PAD_LEFT);
        $items = json_encode([['name' => $tier['name'] . ' Plan', 'amount' => $tier['price']]]);
        $stmt = $pdo->prepare("INSERT INTO invoices (user_id, subscription_id, invoice_no, amount, status, items_json, issued_at) VALUES (?, ?, ?, ?, 'paid', ?, NOW())");
        $stmt->execute([$user_id, $subId, $invNo, $tier['price'], $items]);

        $pdo->commit();

        echo "<script>alert('Subscription successful! Reference: $payment_ref\\nInvoice: $invNo'); window.location='dashboard.php';</script>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Subscription failed: " . $e->getMessage());
        echo "<script>alert('Subscription failed. Please try again.'); window.location='pricing.php';</script>";
        exit;
    }
}

$pageTitle = 'Subscribe - ' . htmlspecialchars($tier['name']) . ' Plan';
include 'header.php';
?>

<div class="container mt-5" style="max-width:500px">
  <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <h3 class="fw-bold">Confirm Subscription</h3>
        <p class="text-muted">You are subscribing to the <strong><?= htmlspecialchars($tier['name']) ?></strong> plan</p>
      </div>

      <?php if ($hasActiveSub): ?>
      <div class="alert alert-warning" style="border-radius:var(--radius);font-size:0.9rem">
        <strong>Note:</strong> You already have an active subscription. Switching to this plan will cancel the existing one.
      </div>
      <?php endif; ?>

      <div style="background:var(--gray-50);border-radius:var(--radius);padding:1.2rem;margin-bottom:1.5rem">
        <div class="d-flex justify-content-between mb-2">
          <span>Plan</span>
          <strong><?= htmlspecialchars($tier['name']) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>Max Services</span>
          <strong><?= $tier['max_posts'] ?? 'Unlimited' ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>Auto-Approve</span>
          <strong><?= $tier['auto_approve'] ? 'Yes' : 'No' ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>Duration</span>
          <strong><?= $tier['duration_days'] ? $tier['duration_days'] . ' days' : 'Lifetime' ?></strong>
        </div>
        <?php if ($tier['is_subscription'] && $tier['duration_days']): ?>
        <div class="d-flex justify-content-between mb-2">
          <span>Expires On</span>
          <strong><?= date('M d, Y', strtotime('+' . $tier['duration_days'] . ' days')) ?></strong>
        </div>
        <?php endif; ?>
        <hr>
        <div class="d-flex justify-content-between">
          <span class="fw-bold">Amount</span>
          <span class="fw-bold" style="font-size:1.3rem;color:var(--forest)">Rs. <?= number_format($tier['price'], 0) ?></span>
        </div>
      </div>

      <form method="POST">
        <?= csrfField() ?>
        <p class="text-muted small mb-3 text-center">Payment is processed manually. Click confirm to generate a payment reference and invoice.</p>
        <button type="submit" class="btn btn-primary-custom w-100">Confirm & Subscribe</button>
        <a href="pricing.php" class="btn btn-modern btn-outline-primary w-100 mt-2">Cancel</a>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
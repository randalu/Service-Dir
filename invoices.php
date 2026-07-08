<?php
$pageTitle = 'My Invoices - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT i.*, t.name AS tier_name
    FROM invoices i
    LEFT JOIN user_subscriptions us ON i.subscription_id = us.id
    LEFT JOIN pricing_tiers t ON us.tier_id = t.id
    WHERE i.user_id = ?
    ORDER BY i.id DESC
");
$stmt->execute([$user_id]);
$invoices = $stmt->fetchAll();

include 'header.php';
?>

<div class="container mt-4 mb-5" style="max-width:800px">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">My Invoices</h3>
    <a href="dashboard.php" class="btn btn-outline-primary btn-sm btn-rounded">Back to Dashboard</a>
  </div>

  <?php if (!$invoices): ?>
    <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow);padding:3rem;text-align:center">
      <div style="font-size:3rem;margin-bottom:1rem">🧾</div>
      <h5 class="fw-bold">No invoices yet</h5>
      <p class="text-muted">Invoices will appear here when you subscribe to a plan.</p>
      <a href="pricing.php" class="btn btn-primary-custom">View Plans</a>
    </div>
  <?php else: ?>
    <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden">
      <div class="table-responsive">
        <table class="table mb-0" style="border-collapse:separate;border-spacing:0">
          <thead>
            <tr style="background:var(--off-white)">
              <th class="px-4 py-3" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--gray-500);font-weight:600">Invoice</th>
              <th class="px-4 py-3" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--gray-500);font-weight:600">Plan</th>
              <th class="px-4 py-3" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--gray-500);font-weight:600">Amount</th>
              <th class="px-4 py-3" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--gray-500);font-weight:600">Status</th>
              <th class="px-4 py-3" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--gray-500);font-weight:600">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoices as $inv): ?>
            <tr style="border-top:1px solid var(--gray-100)">
              <td class="px-4 py-3 fw-bold" style="font-size:0.85rem"><?= htmlspecialchars($inv['invoice_no']) ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($inv['tier_name'] ?? '-') ?></td>
              <td class="px-4 py-3 fw-bold" style="color:var(--forest)">Rs. <?= number_format($inv['amount'], 0) ?></td>
              <td class="px-4 py-3">
                <span class="badge rounded-pill px-3 py-2 bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'unpaid' ? 'warning text-dark' : 'secondary') ?>">
                  <?= ucfirst($inv['status']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-muted" style="font-size:0.85rem"><?= date('M d, Y', strtotime($inv['issued_at'] ?? $inv['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
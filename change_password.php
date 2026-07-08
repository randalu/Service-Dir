<?php
$pageTitle = 'Change Password - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'header.php';
?>

<div class="container mt-4" style="max-width: 500px;">
  <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)">
    <div class="card-body p-4 p-md-5">
      <div class="auth-header mb-4">
        <h2>Change Password</h2>
        <p>Update your account password</p>
      </div>

      <form action="change_password_action.php" method="POST" class="form-modern">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Current Password</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary-custom" style="background:var(--secondary);border:none">Update Password</button>
          <a href="dashboard.php" class="btn btn-modern btn-outline-primary">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
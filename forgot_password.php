<?php
$pageTitle = 'Forgot Password - Service Directory';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';
include 'header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $mobile = '+94' . preg_replace("/[^0-9]/", "", trim($_POST['mobile'] ?? ''));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
    $stmt->execute([$mobile]);
    $user = $stmt->fetch();
    if ($user) {
        // Generate new password
        $nums = '';
        for ($i = 0; $i < 6; $i++) $nums .= random_int(0, 9);
        $chars = str_split($nums . 'R' . 'D' . 'L');
        shuffle($chars);
        $newPass = implode('', $chars);
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);
        // Send SMS
        $smsSent = sendSMS($mobile, "RandaluWebs: Your new login password is: $newPass");
        $message = $smsSent ? 'A new password has been sent to your mobile.' : 'Password reset but SMS delivery failed. Please contact support.';
    } else {
        $error = 'No account found with that mobile number.';
    }
}
?>
<div class="auth-card">
  <div class="card">
    <div class="card-body">
      <div class="auth-header">
        <h2>Forgot Password</h2>
        <p>Enter your mobile number to receive a new password</p>
      </div>
      <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <a href="login.php" class="btn btn-primary-custom w-100">Back to Login</a>
      <?php else: ?>
        <form method="POST" class="form-modern">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Mobile Number</label>
            <div class="input-group">
              <span class="input-group-text">+94</span>
              <input type="text" name="mobile" class="form-control" pattern="[0-9]{9}" maxlength="9" placeholder="771234567" required>
            </div>
          </div>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary-custom w-100">Reset Password</button>
        </form>
        <div class="auth-divider"><span>Remember your password?</span></div>
        <a href="login.php" class="btn btn-modern btn-outline-primary w-100">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>

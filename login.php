<?php
$pageTitle = 'Login - Service Directory';
$metaDesc = 'Sign in to your Raddoluwa/Seeduwa Service Directory account. Access your dashboard and manage your service listings.';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();

include 'header.php';
?>

<div class="auth-card">
  <div class="card">
    <div class="card-body">
      <div class="auth-header">
        <h2>Welcome Back</h2>
        <p>Sign in to manage your services</p>
      </div>

      <form action="login_action.php" method="POST" class="form-modern">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Mobile Number</label>
          <div class="input-group">
            <span class="input-group-text">+94</span>
            <input type="text" name="mobile" class="form-control" pattern="[0-9]{9}" maxlength="9" placeholder="77XXXXXXX" required>
          </div>
          <div class="form-text">Enter the 9-digit number after +94</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary-custom w-100">Sign In</button>
        <div class="text-center mt-2">
          <a href="forgot_password.php" class="text-decoration-none small text-muted">Forgot Password?</a>
        </div>
      </form>

      <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID): ?>
      <div class="auth-divider"><span>or</span></div>
      <a href="google_login.php" class="btn btn-modern btn-outline-danger w-100 mb-3">
        <i class="bi bi-google"></i> Sign in with Google
      </a>
      <?php endif; ?>

      <div class="auth-divider">
        <span>Don't have an account?</span>
      </div>
      <a href="register.php" class="btn btn-modern btn-outline-primary w-100">Create Account</a>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
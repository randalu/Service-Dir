<?php
$pageTitle = 'Register - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$areas = $pdo->query("SELECT name FROM areas ORDER BY name")->fetchAll();

include 'header.php';
?>

<div class="container mt-4" style="max-width: 720px;">
  <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)">
    <div class="card-body p-4 p-md-5">
      <div class="auth-header mb-4">
        <h2>Create Your Account</h2>
        <p>Join the Raddoluwa/Seeduwa Service Directory</p>
      </div>

      <form action="register_action.php" method="POST" enctype="multipart/form-data" class="form-modern">
        <?= csrfField() ?>

        <div class="mb-4">
          <label class="form-label fw-semibold">I want to...</label>
          <div class="d-flex gap-3">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="role" id="roleProvider" value="provider" checked>
              <label class="form-check-label fw-semibold" for="roleProvider">📢 List My Services</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="role" id="rolePublic" value="public">
              <label class="form-check-label fw-semibold" for="rolePublic">👤 Browse & Review</label>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Mobile Number</label>
            <div class="input-group">
              <span class="input-group-text">+94</span>
              <input type="text" name="mobile" pattern="[0-9]{9}" maxlength="9" class="form-control" required placeholder="771234567">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Email (optional)</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Profile Picture (optional)</label>
            <input type="file" name="profile_img" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
          </div>
        </div>

        <!-- Provider-only fields -->
        <div id="providerFields">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Business Name (optional)</label>
              <input type="text" name="business_name" class="form-control" placeholder="Your business or trade name">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Service Title</label>
              <input type="text" name="service_title" class="form-control" placeholder="e.g. Expert Plumbing Repairs">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Service Category</label>
              <select name="category_id" class="form-select">
                <option value="">-- Select --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Service Area</label>
              <select name="area_id" class="form-select">
                <option value="">-- Select --</option>
                <?php foreach ($areas as $area): ?>
                  <option value="<?= htmlspecialchars($area['name']) ?>"><?= htmlspecialchars($area['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 mb-3">
              <label class="form-label">Service Description</label>
              <textarea name="service_description" class="form-control" rows="4" placeholder="Describe your service (optional)"></textarea>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary-custom w-100">Create Account</button>
      </form>

      <div class="auth-divider">
        <span>Already have an account?</span>
      </div>
      <a href="login.php" class="btn btn-modern btn-outline-primary w-100">Sign In</a>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('input[name="role"]').forEach(el => {
  el.addEventListener('change', function() {
    document.getElementById('providerFields').style.display = this.value === 'provider' ? 'block' : 'none';
  });
});
</script>

<?php include 'footer.php'; ?>

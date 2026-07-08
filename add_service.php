<?php
$pageTitle = 'Add Service - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

if (($_SESSION['user_role'] ?? '') !== 'provider') {
    setFlash('error', 'Only providers can list services.');
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's tier
$stmt = $pdo->prepare("SELECT u.*, t.max_posts, t.auto_approve FROM users u LEFT JOIN pricing_tiers t ON u.tier_id = t.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE user_id = ?");
$stmt->execute([$user_id]);
$serviceCount = $stmt->fetchColumn();
$maxPosts = $user['max_posts'] ?? 3;

if ($maxPosts !== null && $serviceCount >= $maxPosts) {
    setFlash('error', "You have reached your plan limit of $maxPosts services. Upgrade to add more.");
    header("Location: pricing.php");
    exit;
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$areas = $pdo->query("SELECT id, name FROM areas ORDER BY name")->fetchAll();

include 'header.php';
?>

<div class="container mt-4" style="max-width: 720px;">
  <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)">
    <div class="card-body p-4 p-md-5">
      <div class="auth-header mb-4">
        <h2>Add New Service</h2>
        <p>List your service on the directory</p>
      </div>

      <form action="add_service_action.php" method="POST" enctype="multipart/form-data" class="form-modern">
        <?= csrfField() ?>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Service Title</label>
            <input type="text" name="service_title" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Business Name</label>
            <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($user['business_name'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Area</label>
            <select name="area_id" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 mb-3">
            <label class="form-label">Physical Address</label>
            <input type="text" name="physical_address" class="form-control" placeholder="Street address, city">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Latitude <small class="text-muted">(for Google Maps)</small></label>
            <input type="text" name="latitude" class="form-control" placeholder="e.g. 7.123456">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Longitude <small class="text-muted">(for Google Maps)</small></label>
            <input type="text" name="longitude" class="form-control" placeholder="e.g. 79.876543">
          </div>
          <div class="col-12 mb-3">
            <label class="form-label">Description</label>
            <textarea name="service_description" class="form-control" rows="5" required></textarea>
          </div>
          <div class="col-12 mb-3">
            <label class="form-label">Images <small class="text-muted">(max 3, first selected = default)</small></label>
            <input type="file" name="images[]" class="form-control mb-2" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImages(this, 0)">
            <input type="file" name="images[]" class="form-control mb-2" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImages(this, 1)">
            <input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImages(this, 2)">
            <div class="d-flex gap-2 mt-2" id="imagePreviews"></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary-custom w-100">Add Service</button>
      </form>
    </div>
  </div>
</div>

<script>
function previewImages(input, idx) {
  const container = document.getElementById('imagePreviews');
  const existing = container.querySelectorAll('img');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      if (existing[idx]) {
        existing[idx].src = e.target.result;
      } else {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.width = '80px';
        img.style.height = '80px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '8px';
        img.style.border = idx === 0 ? '3px solid var(--lime)' : '1px solid var(--gray-200)';
        img.title = idx === 0 ? 'Default image' : '';
        container.appendChild(img);
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php include 'footer.php'; ?>

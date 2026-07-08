<?php
$pageTitle = 'Edit Profile - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

include 'header.php';
?>

<div class="container mt-4" style="max-width: 640px;">
  <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)">
    <div class="card-body p-4 p-md-5">
      <div class="auth-header mb-4">
        <h2>Edit Profile</h2>
        <p>Update your personal information</p>
      </div>

      <form action="edit_profile_action.php" method="POST" enctype="multipart/form-data" class="form-modern">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Last Name</label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Profile Picture</label>
          <div class="d-flex align-items-center gap-3 mb-2">
            <img src="uploads/<?= $user['profile_img'] ?>" width="64" height="64" style="border-radius:var(--radius);object-fit:cover" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2264%22 height=%2264%22><rect fill=%22%23e2e8f0%22/></svg>'">
            <span class="small text-muted">Current photo</span>
          </div>
          <input type="file" name="profile_img" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary-custom">Update Profile</button>
          <a href="dashboard.php" class="btn btn-modern btn-outline-primary">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
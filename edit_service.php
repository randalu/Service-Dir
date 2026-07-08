<?php
$pageTitle = 'Edit Service - Service Directory';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

$user_id = $_SESSION['user_id'];
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT s.* FROM services s WHERE s.id = ? AND s.user_id = ?");
$stmt->execute([$service_id, $user_id]);
$service = $stmt->fetch();

if (!$service) {
    setFlash('error', 'Service not found or access denied.');
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $title = trim($_POST['service_title']);
    $category_id = (int)$_POST['category_id'];
    $area_id = (int)$_POST['area_id'];
    $description = trim($_POST['service_description']);
    $physical_address = trim($_POST['physical_address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE services SET title=?, category_id=?, area_id=?, description=?, physical_address=?, latitude=?, longitude=? WHERE id=? AND user_id=?");
        $stmt->execute([$title, $category_id, $area_id, $description, $physical_address ?: null, $latitude ?: null, $longitude ?: null, $service_id, $user_id]);

        // Handle new image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_images WHERE service_id = ?");
            $stmt->execute([$service_id]);
            $existingCount = $stmt->fetchColumn();

            $uploadDir = __DIR__ . '/uploads/';
            $sortOrder = $existingCount;
            $isFirst = $existingCount === 0;

            foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($sortOrder >= 3) break;

                $file = [
                    'name' => $_FILES['images']['name'][$i],
                    'tmp_name' => $tmpName,
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i],
                ];

                $validation = validateUpload($file);
                if (!$validation['valid']) continue;

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $imgName = uniqid('svc_') . '.' . $ext;
                $destPath = $uploadDir . $imgName;
                if (move_uploaded_file($tmpName, $destPath)) {
                    resizeAndSaveImage($destPath, $destPath, 800, 800);
                    $isPrimary = ($isFirst && $sortOrder === 0) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO service_images (service_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$service_id, $imgName, $isPrimary, $sortOrder]);
                    $sortOrder++;
                }
            }
        }

        // Set primary image
        if (isset($_POST['primary_image'])) {
            $primaryId = (int)$_POST['primary_image'];
            $pdo->prepare("UPDATE service_images SET is_primary = 0 WHERE service_id = ?")->execute([$service_id]);
            $pdo->prepare("UPDATE service_images SET is_primary = 1 WHERE id = ? AND service_id = ?")->execute([$primaryId, $service_id]);
        }

        $pdo->commit();
        setFlash('success', 'Service updated successfully.');
        header("Location: dashboard.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to update service.');
        header("Location: edit_service.php?id=$service_id");
        exit;
    }
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$areas = $pdo->query("SELECT id, name FROM areas ORDER BY name")->fetchAll();
$images = $pdo->prepare("SELECT * FROM service_images WHERE service_id = ? ORDER BY sort_order");
$images->execute([$service_id]);
$images = $images->fetchAll();

include 'header.php';
?>

<div class="container mt-4" style="max-width: 720px;">
  <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)">
    <div class="card-body p-4 p-md-5">
      <div class="auth-header mb-4">
        <h2>Edit Service</h2>
        <p>Update your service listing</p>
      </div>

      <form action="edit_service.php?id=<?= $service_id ?>" method="POST" enctype="multipart/form-data" class="form-modern">
        <?= csrfField() ?>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Service Title</label>
            <input type="text" name="service_title" class="form-control" value="<?= htmlspecialchars($service['title']) ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $service['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Area</label>
            <select name="area_id" class="form-select" required>
              <?php foreach ($areas as $area): ?>
                <option value="<?= $area['id'] ?>" <?= $area['id'] == $service['area_id'] ? 'selected' : '' ?>><?= htmlspecialchars($area['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 mb-3">
            <label class="form-label">Physical Address</label>
            <input type="text" name="physical_address" class="form-control" value="<?= htmlspecialchars($service['physical_address'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Latitude</label>
            <input type="text" name="latitude" class="form-control" value="<?= htmlspecialchars($service['latitude'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Longitude</label>
            <input type="text" name="longitude" class="form-control" value="<?= htmlspecialchars($service['longitude'] ?? '') ?>">
          </div>
          <div class="col-12 mb-3">
            <label class="form-label">Description</label>
            <textarea name="service_description" class="form-control" rows="5" required><?= htmlspecialchars($service['description']) ?></textarea>
          </div>

          <?php if (count($images) > 0): ?>
          <div class="col-12 mb-3">
            <label class="form-label fw-semibold">Current Images</label>
            <div class="d-flex gap-3 flex-wrap">
              <?php foreach ($images as $img): ?>
              <div style="position:relative;width:100px">
                <img src="uploads/<?= htmlspecialchars($img['image_path']) ?>" style="width:100px;height:100px;object-fit:cover;border-radius:8px;border:<?= $img['is_primary'] ? '3px solid var(--lime)' : '1px solid var(--gray-200)' ?>">
                <label class="small d-block mt-1">
                  <input type="radio" name="primary_image" value="<?= $img['id'] ?>" <?= $img['is_primary'] ? 'checked' : '' ?>> Default
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (count($images) < 3): ?>
          <div class="col-12 mb-3">
            <label class="form-label">Add More Images <small class="text-muted">(<?= 3 - count($images) ?> remaining)</small></label>
            <input type="file" name="images[]" class="form-control mb-2" accept="image/jpeg,image/png,image/gif,image/webp">
            <?php if (count($images) < 2): ?>
            <input type="file" name="images[]" class="form-control mb-2" accept="image/jpeg,image/png,image/gif,image/webp">
            <?php endif; ?>
            <?php if (count($images) < 1): ?>
            <input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary-custom w-100">Update Service</button>
        <a href="dashboard.php" class="btn btn-modern btn-outline-primary w-100 mt-2">Cancel</a>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

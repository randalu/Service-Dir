<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';
requireCsrf();

if (($_SESSION['user_role'] ?? '') !== 'provider') {
    setFlash('error', 'Only providers can list services.');
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check tier limit
$stmt = $pdo->prepare("SELECT u.*, t.max_posts, t.auto_approve FROM users u LEFT JOIN pricing_tiers t ON u.tier_id = t.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE user_id = ?");
$stmt->execute([$user_id]);
$count = $stmt->fetchColumn();
$maxPosts = $user['max_posts'] ?? 3;

if ($maxPosts !== null && $count >= $maxPosts) {
    setFlash('error', 'Plan limit reached. Upgrade to add more services.');
    header("Location: pricing.php");
    exit;
}

$title = trim($_POST['service_title']);
$category_id = (int)$_POST['category_id'];
$area_id = (int)$_POST['area_id'];
$description = trim($_POST['service_description']);
$physical_address = trim($_POST['physical_address'] ?? '');
$latitude = trim($_POST['latitude'] ?? '');
$longitude = trim($_POST['longitude'] ?? '');
$business_name = trim($_POST['business_name'] ?? '');

// Determine status based on tier
$status = ($user['auto_approve'] ?? 0) ? 'active' : 'pending';

$pdo->beginTransaction();
try {
    // Update business name on user profile
    if ($business_name) {
        $pdo->prepare("UPDATE users SET business_name = ? WHERE id = ?")->execute([$business_name, $user_id]);
    }

    $stmt = $pdo->prepare("INSERT INTO services (user_id, title, category_id, area_id, description, physical_address, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $category_id, $area_id, $description, $physical_address ?: null, $latitude ?: null, $longitude ?: null, $status]);
    $service_id = $pdo->lastInsertId();

    // Handle image uploads (max 3)
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/';
        $sortOrder = 0;
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
                $isPrimary = ($sortOrder === 0) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO service_images (service_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$service_id, $imgName, $isPrimary, $sortOrder]);
                $sortOrder++;
            }
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Add service failed: " . $e->getMessage());
    setFlash('error', 'Failed to add service. Please try again.');
    header("Location: add_service.php");
    exit;
}

$msg = $status === 'pending' ? 'Service added and is pending approval.' : 'Service added successfully!';
setFlash('success', $msg);
$redirect = ($_SESSION['user_role'] ?? '') === 'public' ? 'index.php' : 'dashboard.php';
header("Location: $redirect");

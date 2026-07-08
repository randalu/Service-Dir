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

$user_id = $_SESSION['user_id'];
$first = trim($_POST['first_name']);
$last = trim($_POST['last_name']);
$email = trim($_POST['email']);

// Handle image if uploaded
if (!empty($_FILES['profile_img']['name'])) {
    $validation = validateUpload($_FILES['profile_img']);
    if (!$validation['valid']) {
        setFlash('error', htmlspecialchars($validation['error']));
        header("Location: edit_profile.php");
        exit;
    }
    $ext = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
    $imgName = uniqid() . "." . $ext;
    $destPath = __DIR__ . "/uploads/$imgName";
    move_uploaded_file($_FILES['profile_img']['tmp_name'], $destPath);
    resizeAndSaveImage($destPath, $destPath, 800, 800);

    $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, profile_img=? WHERE id=?");
    $stmt->execute([$first, $last, $email, $imgName, $user_id]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    $stmt->execute([$first, $last, $email, $user_id]);
}

setFlash('success', 'Profile updated successfully');
header("Location: dashboard.php");

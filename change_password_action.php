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
$current = $_POST['current_password'];
$new = $_POST['new_password'];
$confirm = $_POST['confirm_password'];

$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!password_verify($current, $user['password'])) {
    echo "<script>alert('Current password is incorrect'); window.location='change_password.php';</script>";
    exit;
}

if ($new !== $confirm) {
    echo "<script>alert('New passwords do not match'); window.location='change_password.php';</script>";
    exit;
}

$new_hash = password_hash($new, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->execute([$new_hash, $user_id]);

echo "<script>alert('Password updated successfully'); window.location='dashboard.php';</script>";

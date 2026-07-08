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
    setFlash('error', 'Current password is incorrect');
    header("Location: change_password.php");
    exit;
}

if ($new !== $confirm) {
    setFlash('error', 'New passwords do not match');
    header("Location: change_password.php");
    exit;
}

$new_hash = password_hash($new, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->execute([$new_hash, $user_id]);

setFlash('success', 'Password updated successfully');
header("Location: dashboard.php");

<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: login.php"); exit; }
require_once __DIR__ . '/db.php';
requireCsrf();

if (!rateLimitCheck('login_' . $_SERVER['REMOTE_ADDR'], 5, 300)) {
    echo "<script>alert('Too many attempts. Try again later.'); window.location='login.php';</script>";
    exit;
}

$mobile = '+94' . preg_replace("/[^0-9]/", "", trim($_POST['mobile'] ?? ''));
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ?");
$stmt->execute([$mobile]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    if (!empty($user['totp_enabled'])) {
        $_SESSION['2fa_user_id'] = $user['id'];
        header("Location: verify_2fa.php");
        exit;
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['mobile'] = $user['mobile'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['tier_id'] = $user['tier_id'];
    header("Location: dashboard.php");
    exit;
} else {
    echo "<script>alert('Invalid login credentials'); window.location='login.php';</script>";
}

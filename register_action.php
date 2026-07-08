<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';
requireCsrf();

function formatMobile($num) {
    $clean = preg_replace("/[^0-9]/", "", $num);
    if (strlen($clean) === 10) $clean = substr($clean, 1);
    elseif (strlen($clean) === 11 && substr($clean, 0, 2) === '94') $clean = substr($clean, 2);
    return '+94' . $clean;
}

function generatePassword() {
    $nums = '';
    for ($i = 0; $i < 6; $i++) $nums .= random_int(0, 9);
    $letters = ['R', 'D', 'L'];
    $chars = str_split($nums . 'R' . 'D' . 'L');
    shuffle($chars);
    return implode('', $chars);
}

$role = $_POST['role'] ?? 'provider';
$first = trim($_POST['first_name']);
$last = trim($_POST['last_name']);
$mobile = formatMobile($_POST['mobile']);
$email = trim($_POST['email'] ?? '');
$business_name = trim($_POST['business_name'] ?? '');

// Check for duplicate mobile
$stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
$stmt->execute([$mobile]);
if ($stmt->fetch()) {
    echo "<script>alert('This mobile number is already registered.'); window.location='register.php';</script>";
    exit;
}

$password_plain = generatePassword();
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

// Profile image with validation and resize
$imgName = "default.jpg";
if (!empty($_FILES['profile_img']['name'])) {
    $validation = validateUpload($_FILES['profile_img']);
    if (!$validation['valid']) {
        echo "<script>alert('" . htmlspecialchars($validation['error'], ENT_QUOTES) . "'); window.location='register.php';</script>";
        exit;
    }
    $ext = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
    $imgName = uniqid() . "." . $ext;
    $destPath = __DIR__ . "/uploads/$imgName";
    move_uploaded_file($_FILES['profile_img']['tmp_name'], $destPath);
    resizeAndSaveImage($destPath, $destPath, 800, 800);
}

// Get free tier ID
$freeTierId = $pdo->query("SELECT id FROM pricing_tiers WHERE name = 'Free' LIMIT 1")->fetchColumn();
if (!$freeTierId) {
    echo "<script>alert('System error: pricing not configured.'); window.location='register.php';</script>";
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO users (role, first_name, last_name, mobile, email, tier_id, business_name, password, profile_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$role, $first, $last, $mobile, $email ?: null, $freeTierId, $business_name ?: null, $password_hash, $imgName]);
    $user_id = $pdo->lastInsertId();

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Registration failed: " . $e->getMessage());
    echo "<script>alert('Registration failed. Please try again.'); window.location='register.php';</script>";
    exit;
}

// Send SMS with password
$message = "Welcome to RandaluWebs Service Directory!\nYour login password is: $password_plain";
$smsSent = sendSMS($mobile, $message);

// Auto-login
session_regenerate_id(true);
$_SESSION['user_id'] = $user_id;
$_SESSION['mobile'] = $mobile;
$_SESSION['user_role'] = $role;
$_SESSION['tier_id'] = $freeTierId;
$_SESSION['just_registered'] = true;

$alert = $smsSent ? 'Registration successful! Password sent to your mobile.' : 'Registration successful but SMS delivery failed. Save your password: ' . $password_plain;
echo "<script>alert('" . htmlspecialchars($alert, ENT_QUOTES) . "'); window.location='dashboard.php';</script>";

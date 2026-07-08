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

$from_user_id = $_SESSION['user_id'];
$to_user_id = (int)$_POST['to_user_id'];
$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
$rating = (int)$_POST['rating'];
$comment = trim($_POST['comment']);
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';

if ($rating < 1 || $rating > 5) {
    setFlash('error', 'Rating must be between 1 and 5.');
    header("Location: $referer");
    exit;
}

if (empty($comment)) {
    setFlash('error', 'Please write a comment.');
    header("Location: $referer");
    exit;
}

if ($from_user_id == $to_user_id) {
    setFlash('error', 'You cannot review yourself.');
    header("Location: $referer");
    exit;
}

// Check for duplicate
$stmt = $pdo->prepare("SELECT id FROM reviews WHERE from_user_id = ? AND to_user_id = ? AND (service_id = ? OR (service_id IS NULL AND ? IS NULL))");
$stmt->execute([$from_user_id, $to_user_id, $service_id, $service_id]);
if ($stmt->fetch()) {
    setFlash('error', 'You have already reviewed this user/service.');
    header("Location: $referer");
    exit;
}

// Auto-approve if reviewer is on a paid plan
$stmt = $pdo->prepare("SELECT t.auto_approve FROM users u LEFT JOIN pricing_tiers t ON u.tier_id = t.id WHERE u.id = ?");
$stmt->execute([$from_user_id]);
$user = $stmt->fetch();
$is_approved = ($user && $user['auto_approve']) ? 1 : 0;

$stmt = $pdo->prepare("INSERT INTO reviews (from_user_id, to_user_id, service_id, rating, comment, is_approved) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$from_user_id, $to_user_id, $service_id, $rating, $comment, $is_approved]);

$msg = $is_approved ? 'Review submitted successfully!' : 'Review submitted and pending approval.';
$redirect = $service_id ? "service_view.php?id=$service_id" : "profile_view.php?id=$to_user_id";
setFlash('success', $msg);
header("Location: $redirect");

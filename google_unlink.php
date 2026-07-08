<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';
requireCsrf();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE users SET google_id = NULL, google_email = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: dashboard.php?msg=google_unlinked');
    exit;
}

header('Location: dashboard.php');

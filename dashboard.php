<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Route to role-specific dashboard
$role = $_SESSION['user_role'] ?? 'provider';
if ($role === 'public') {
    require __DIR__ . '/dashboard_public.php';
} else {
    require __DIR__ . '/dashboard_provider.php';
}

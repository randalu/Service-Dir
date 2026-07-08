<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

if (!defined('GOOGLE_CLIENT_ID') || !GOOGLE_CLIENT_ID) {
    header('Location: login.php');
    exit;
}

$linkMode = isset($_GET['link']) && isset($_SESSION['user_id']);

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $linkMode ? 'link_' . ($_SESSION['user_id'] ?? 0) : 'login_0',
    'access_type' => 'online',
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $url);
exit;

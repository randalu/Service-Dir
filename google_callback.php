<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
require_once __DIR__ . '/db.php';

if (!defined('GOOGLE_CLIENT_ID') || !GOOGLE_CLIENT_ID || !isset($_GET['code'])) {
    header('Location: login.php');
    exit;
}

$code = $_GET['code'];
$state = $_GET['state'] ?? '';

// Determine mode from state
$linkMode = str_starts_with($state, 'link_');

// Exchange code for token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code',
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    header('Location: login.php?error=google_auth_failed');
    exit;
}

$tokenInfo = json_decode($response, true);
$accessToken = $tokenInfo['access_token'] ?? null;

if (!$accessToken) {
    header('Location: login.php?error=google_auth_failed');
    exit;
}

// Fetch user info
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
$userInfo = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$userInfo || !isset($userInfo['id'])) {
    header('Location: login.php?error=google_auth_failed');
    exit;
}

$googleId = $userInfo['id'];
$googleEmail = $userInfo['email'] ?? '';
$googleName = $userInfo['name'] ?? '';

if ($linkMode) {
    // Link mode — user is already logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // Check if this Google account is already linked to another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE google_id = ? AND id != ?");
    $stmt->execute([$googleId, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        setFlash('error', 'This Google account is already linked to another user.');
        header("Location: dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET google_id = ?, google_email = ? WHERE id = ?");
    $stmt->execute([$googleId, $googleEmail, $_SESSION['user_id']]);
    setFlash('success', 'Google account linked successfully.');
    header('Location: dashboard.php');
    exit;
} else {
    // Login mode — find or error
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if ($user) {
        if (!empty($user['totp_enabled'])) {
            session_regenerate_id(true);
            $_SESSION['2fa_user_id'] = $user['id'];
            header('Location: verify_2fa.php');
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['mobile'] = $user['mobile'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['tier_id'] = $user['tier_id'];
        header('Location: dashboard.php');
        exit;
    } else {
        setFlash('info', 'No account linked to this Google account. Please register with a mobile number first, then link your Google account from the dashboard.');
        header("Location: login.php");
        exit;
    }
}

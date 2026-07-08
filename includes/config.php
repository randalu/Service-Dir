<?php

function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

loadEnv(__DIR__ . '/../.env');

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return $value;
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'learnitc_serdir'));
define('DB_USER', env('DB_USER', 'learnitc_serdir'));
define('DB_PASS', env('DB_PASS', 'ant90210SL@@'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_URL', env('APP_URL', 'https://lka.ovh/srv'));
define('SMS_API_USER_ID', env('SMS_API_USER_ID', '190'));
define('SMS_API_KEY', env('SMS_API_KEY', ''));
define('SMS_SENDER_ID', env('SMS_SENDER_ID', 'PeachTreeLK'));
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,webp');
define('UPLOAD_ALLOWED_MIMES', 'image/jpeg,image/png,image/gif,image/webp');
define('GOOGLE_MAPS_API_KEY', env('GOOGLE_MAPS_API_KEY', ''));
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', env('GOOGLE_REDIRECT_URI', APP_URL . '/google_callback.php'));

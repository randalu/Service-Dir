<?php

if (!function_exists('configureSession')) {
    function configureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }
}

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrfField')) {
    function csrfField() {
        $token = generateCsrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken() {
        if (empty($_POST['_csrf_token']) || empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $_POST['_csrf_token']);
    }
}

if (!function_exists('requireCsrf')) {
    function requireCsrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken()) {
            die('Invalid or missing CSRF token.');
        }
    }
}

if (!function_exists('validateUpload')) {
    function validateUpload($file) {
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload failed.'];
        }

        if ($file['size'] > UPLOAD_MAX_SIZE) {
            return ['valid' => false, 'error' => 'File too large. Max 5MB.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = explode(',', UPLOAD_ALLOWED_EXTENSIONS);
        if (!in_array($ext, $allowedExts)) {
            return ['valid' => false, 'error' => 'File type not allowed.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = explode(',', UPLOAD_ALLOWED_MIMES);
        if (!in_array($mime, $allowedMimes)) {
            return ['valid' => false, 'error' => 'File MIME type not allowed.'];
        }

        return ['valid' => true];
    }
}

if (!function_exists('resizeAndSaveImage')) {
    function resizeAndSaveImage($sourcePath, $destPath, $maxWidth = 800, $maxHeight = 800) {
        list($origWidth, $origHeight, $type) = getimagesize($sourcePath);
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
        $newWidth = (int)round($origWidth * $ratio);
        $newHeight = (int)round($origHeight * $ratio);

        $src = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $src = imagecreatefromwebp($sourcePath);
                }
                break;
        }
        if (!$src) {
            return copy($sourcePath, $destPath);
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($dst, $destPath, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($dst, $destPath, 7);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($dst, $destPath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    $result = imagewebp($dst, $destPath, 85);
                }
                break;
        }

        imagedestroy($src);
        imagedestroy($dst);
        return $result;
    }
}

if (!function_exists('rateLimitCheck')) {
    function rateLimitCheck($key, $maxAttempts = 5, $windowSeconds = 300) {
        $storageKey = '_rate_limit_' . $key;
        $now = time();

        if (!isset($_SESSION[$storageKey])) {
            $_SESSION[$storageKey] = ['count' => 1, 'first_attempt' => $now];
            return true;
        }

        $data = $_SESSION[$storageKey];
        if ($now - $data['first_attempt'] > $windowSeconds) {
            $_SESSION[$storageKey] = ['count' => 1, 'first_attempt' => $now];
            return true;
        }

        if ($data['count'] >= $maxAttempts) {
            return false;
        }

        $_SESSION[$storageKey]['count'] = $data['count'] + 1;
        return true;
    }
}

    if (!function_exists('sendSMS')) {
    function sendSMS($to, $message) {
        $payload = [
            'user_id' => SMS_API_USER_ID,
            'api_key' => SMS_API_KEY,
            'sender_id' => SMS_SENDER_ID,
            'contact' => $to,
            'message' => $message
        ];

        $ch = curl_init('https://smslenz.lk/api/send-sms');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("SMS send failed to $to: HTTP $httpCode, curl error: $error, response: " . ($response ?: 'none'));
            return false;
        }
        return true;
    }
}

    if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $pdo;
        static $cache = [];
        if (!isset($cache[$key])) {
            try {
                $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
                $stmt->execute([$key]);
                $cache[$key] = $stmt->fetchColumn() ?: $default;
            } catch (Exception $e) {
                return $default;
            }
        }
        return $cache[$key];
    }
}

// Flash message helpers
if (!function_exists('setFlash')) {
    function setFlash(string $type, string $message): void {
        $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('getFlashes')) {
    function getFlashes(): array {
        $flashes = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);
        return $flashes;
    }
}

if (!function_exists('setFlash')) {
    function setFlash($type, $message) {
        if (session_status() === PHP_SESSION_NONE) {
            configureSession();
        }
        if (!isset($_SESSION['_flashes'])) {
            $_SESSION['_flashes'] = [];
        }
        $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('getFlashes')) {
    function getFlashes() {
        if (session_status() === PHP_SESSION_NONE) {
            configureSession();
        }
        $flashes = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);
        return $flashes;
    }
}

if (!function_exists('renderCard')) {
    function renderCard($ad, $wrap = true) {
        $catIcons = [
            'AC Repair' => '❄️', 'Beauty' => '💇', 'Carpentry' => '🪚', 'Catering' => '🍽️',
            'Cleaning' => '🧹', 'Electrical' => '⚡', 'Gardening' => '🌿', 'IT Support' => '💻',
            'Medical' => '🏥', 'Moving' => '📦', 'Painting' => '🎨', 'Photography' => '📷',
            'Plumbing' => '🔧', 'Transport' => '🚚', 'Tutoring' => '📚',
        ];
        $cat = $ad['category'] ?? '';
        $icon = $catIcons[$cat] ?? '🛠️';
        $isFeatured = !empty($ad['is_featured']) && (!isset($ad['featured_until']) || $ad['featured_until'] >= date('Y-m-d'));
        $primaryImg = '';
        if (isset($ad['id'])) {
            global $pdo;
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT image_path FROM service_images WHERE service_id = ? AND is_primary = 1 LIMIT 1");
                $stmt->execute([$ad['id']]);
                $img = $stmt->fetch();
                if (!$img) {
                    $stmt = $pdo->prepare("SELECT image_path FROM service_images WHERE service_id = ? ORDER BY sort_order LIMIT 1");
                    $stmt->execute([$ad['id']]);
                    $img = $stmt->fetch();
                }
                if ($img) $primaryImg = 'uploads/' . $img['image_path'];
            }
        }
        ob_start();
        if ($wrap) echo '<div class="col-md-4 col-sm-6">';
        ?>
        <div class="service-card">
            <div class="card-img" style="background:linear-gradient(135deg, #275D2B, #1a4020)">
                <?php if ($isFeatured): ?>
                <div class="featured-badge">⭐ Featured</div>
                <?php endif; ?>
                <?php if ($primaryImg): ?>
                <img src="<?= htmlspecialchars($primaryImg) ?>" alt="<?= htmlspecialchars($ad['title']) ?>"
                     style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0"
                     onerror="this.style.display='none'">
                <?php endif; ?>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;z-index:1">
                    <div style="font-size:2rem;line-height:1;margin-bottom:4px"><?= $icon ?></div>
                    <div style="font-size:0.65rem;font-weight:800;color:rgba(255,255,255,0.5);letter-spacing:0.15em"><?= htmlspecialchars(getSetting('site_name', 'RDL')) ?></div>
                </div>
                <span class="category-badge"><?= htmlspecialchars($cat) ?></span>
            </div>
            <div class="card-body">
                <h5 class="card-title"><a href="service_view.php?id=<?= $ad['id'] ?>"><?= htmlspecialchars($ad['title']) ?></a></h5>
                <p class="card-text"><?= htmlspecialchars(substr(strip_tags($ad['description']), 0, 120)) ?>...</p>
                <div class="card-meta">
                    <span>📍 <?= htmlspecialchars($ad['area']) ?></span>
                    <span>📅 <?= date('M d, Y', strtotime($ad['created_at'])) ?></span>
                </div>
                <div class="card-footer-info">
                    <a href="provider_profile.php?id=<?= $ad['user_id'] ?>" class="name text-decoration-none"><?= htmlspecialchars($ad['first_name'] . ' ' . $ad['last_name']) ?></a>
                    <div class="d-flex gap-2">
                        <a href="tel:<?= htmlspecialchars($ad['mobile']) ?>" class="btn-call">📞 Call</a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ad['mobile']) ?>" target="_blank" class="btn-call" style="background:#25D366">💬 WhatsApp</a>
                        <a href="service_view.php?id=<?= $ad['id'] ?>" class="btn-view">View</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        if ($wrap) echo '</div>';
        return ob_get_clean();
    }
}

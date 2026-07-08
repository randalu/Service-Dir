<?php
$pageTitle = 'Two-Factor Authentication Setup';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/totp.php';
configureSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$message = '';
$error = '';

// Disable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disable') {
    requireCsrf();
    $code = trim($_POST['code']);
    if (empty($code)) {
        $error = 'Please enter the current 6-digit code from your authenticator app.';
    } elseif (!TOTP::verifyCode($user['totp_secret'], $code)) {
        $error = 'Invalid code. Please try again.';
    } else {
        $pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0, recovery_codes = NULL WHERE id = ?")->execute([$user_id]);
        $message = 'Two-factor authentication has been disabled.';
        $user['totp_enabled'] = 0;
    }
}

// Enable 2FA - Step 1: Generate secret
if (!isset($_SESSION['2fa_setup_secret'])) {
    $_SESSION['2fa_setup_secret'] = TOTP::generateSecret();
}

// Enable 2FA - Step 2: Verify code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_setup') {
    requireCsrf();
    $code = trim($_POST['code']);
    if (empty($code)) {
        $error = 'Please enter the 6-digit code from your authenticator app.';
    } elseif (!TOTP::verifyCode($_SESSION['2fa_setup_secret'], $code)) {
        $error = 'Invalid code. Please try again. Make sure your authenticator app is set up correctly.';
    } else {
        $recoveryCodes = TOTP::generateRecoveryCodes(5);
        $recoveryHashed = array_map(function($c) { return password_hash($c, PASSWORD_DEFAULT); }, $recoveryCodes);
        $pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1, recovery_codes = ? WHERE id = ?")
            ->execute([$_SESSION['2fa_setup_secret'], json_encode($recoveryHashed), $user_id]);
        unset($_SESSION['2fa_setup_secret']);
        $message = 'Two-factor authentication has been enabled!';
        $user['totp_enabled'] = 1;
        $showRecoveryCodes = $recoveryCodes;
    }
}

include 'header.php';
?>

<div class="container mt-4" style="max-width: 600px;">
    <div class="card" style="border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow)">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-4">🔒 Two-Factor Authentication</h4>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($user['totp_enabled']): ?>
                <!-- Currently enabled -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-success rounded-pill px-3 py-2" style="font-size:0.9rem">✅ Enabled</span>
                    </div>
                    <p class="text-muted small">Your account is protected with two-factor authentication. Each time you log in, you'll need to enter a 6-digit code from your authenticator app.</p>
                </div>

                <hr>
                <h6 class="fw-bold mb-3">Disable Two-Factor Authentication</h6>
                <p class="text-muted small mb-3">Enter a code from your authenticator app to disable 2FA.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="disable">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Authenticator Code</label>
                        <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required>
                    </div>
                    <button type="submit" class="btn btn-danger">Disable 2FA</button>
                </form>

                <?php if (isset($showRecoveryCodes)): ?>
                    <hr>
                    <h6 class="fw-bold mb-3">Recovery Codes</h6>
                    <p class="text-muted small">Save these codes in a safe place. Each code can be used once to access your account if you lose access to your authenticator app.</p>
                    <div class="bg-light p-3 rounded" style="font-family:monospace;font-size:1.1rem">
                        <?php foreach ($showRecoveryCodes as $rc): ?>
                            <div><?= htmlspecialchars($rc) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif (isset($_GET['step']) && $_GET['step'] === 'verify'): ?>
                <!-- Step 2: Verify setup -->
                <h6 class="fw-bold mb-3">Step 2: Verify Setup</h6>
                <p class="text-muted small mb-3">Enter the 6-digit code from your authenticator app to confirm everything is working.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="verify_setup">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Authenticator Code</label>
                        <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-success">Verify & Enable 2FA</button>
                    <a href="setup_2fa.php" class="btn btn-secondary">Cancel</a>
                </form>

            <?php else: ?>
                <!-- Step 1: Scan QR Code -->
                <h6 class="fw-bold mb-3">Step 1: Scan QR Code</h6>
                <p class="text-muted small mb-3">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.), or enter the secret key manually.</p>

                <div class="text-center mb-4">
                    <img src="<?= TOTP::getQRCodeImage($user['first_name'] . $user['id'], $_SESSION['2fa_setup_secret'], htmlspecialchars(getSetting('site_name', 'RandaluWebs'))) ?>" alt="QR Code" style="width:200px;height:200px;border-radius:12px;border:2px solid #e2e8f0;padding:8px">
                </div>

                <div class="bg-light p-3 rounded mb-4">
                    <label class="fw-semibold small">Manual Setup Key</label>
                    <div class="d-flex align-items-center gap-2">
                        <code style="font-size:1rem;word-break:break-all"><?= htmlspecialchars($_SESSION['2fa_setup_secret']) ?></code>
                    </div>
                </div>

                <a href="setup_2fa.php?step=verify" class="btn btn-primary w-100" style="background:var(--forest);border:none;border-radius:var(--radius);font-weight:600;padding:0.7rem">
                    I've scanned the QR code — Next
                </a>
                <a href="setup_2fa.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="text-center mt-3">
        <a href="dashboard.php" class="text-decoration-none small">&larr; Back to Dashboard</a>
    </div>
</div>

<?php include 'footer.php'; ?>

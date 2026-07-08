<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/totp.php';
configureSession();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$user_id = $_SESSION['2fa_user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !$user['totp_enabled']) {
    unset($_SESSION['2fa_user_id']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $code = trim($_POST['code'] ?? '');
    $isRecovery = isset($_POST['recovery_mode']);

    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } elseif ($isRecovery) {
        $recoveryCodes = json_decode($user['recovery_codes'] ?? '[]', true);
        $valid = false;
        foreach ($recoveryCodes as $i => $hash) {
            if (password_verify($code, $hash)) {
                array_splice($recoveryCodes, $i, 1);
                $pdo->prepare("UPDATE users SET recovery_codes = ? WHERE id = ?")->execute([json_encode($recoveryCodes), $user_id]);
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $error = 'Invalid recovery code.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['mobile'] = $user['mobile'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['tier_id'] = $user['tier_id'];
            unset($_SESSION['2fa_user_id']);
            header('Location: dashboard.php');
            exit;
        }
    } else {
        if (TOTP::verifyCode($user['totp_secret'], $code)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['mobile'] = $user['mobile'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['tier_id'] = $user['tier_id'];
            unset($_SESSION['2fa_user_id']);
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid code. Please try again.';
    }
}

$recoveryMode = isset($_GET['recovery']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    :root { --forest: #275D2B; --lime: #82D148; --navy: #0B1021; --radius: 12px; --radius-lg: 16px; --shadow-lg: 0 10px 30px rgba(11,16,33,0.15); }
    body { background: linear-gradient(135deg, #0B1021 0%, #1a4020 50%, #275D2B 100%); min-height: 100vh; display: flex; align-items: center; }
    .auth-card { max-width: 420px; margin: 0 auto; width: 100%; }
    .auth-card .card { border: none; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); }
    .auth-card .card-body { padding: 2rem; }
    .auth-header { text-align: center; margin-bottom: 1.5rem; }
    .auth-header h2 { font-weight: 800; font-size: 1.4rem; color: #0B1021; }
    .auth-header p { color: #6B7E6B; font-size: 0.85rem; margin: 0; }
    .form-control { border: 1.5px solid #E8EDE8; border-radius: 12px; padding: 0.65rem 1rem; font-size: 1.2rem; text-align: center; letter-spacing: 0.3em; }
    .form-control:focus { border-color: var(--forest); box-shadow: 0 0 0 3px rgba(39,93,43,0.15); }
    .btn-primary { background: var(--lime); border: none; border-radius: 12px; font-weight: 600; padding: 0.65rem; color: var(--navy); }
    .btn-primary:hover { background: #6fba3a; }
    .alert { border: none; border-radius: 12px; font-size: 0.85rem; }
  </style>
</head>
<body>
  <div class="auth-card">
    <div class="card">
      <div class="card-body">
        <div class="auth-header">
          <h2>🔒 Two-Factor Auth</h2>
          <p>Enter the 6-digit code from your authenticator app</p>
        </div>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($recoveryMode): ?>
          <div class="alert alert-warning small">Enter one of your recovery codes. Each code can only be used once.</div>
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="recovery_mode" value="1">
            <div class="mb-3">
              <label class="form-label fw-semibold small">Recovery Code</label>
              <input type="text" name="code" class="form-control" placeholder="XXXXXXXX" style="letter-spacing:0.1em;font-size:1rem" autocomplete="off" required autofocus>
            </div>
            <button class="btn btn-primary w-100">Verify Recovery Code</button>
            <a href="verify_2fa.php" class="btn btn-outline-secondary w-100 mt-2">&larr; Use authenticator app instead</a>
          </form>
        <?php else: ?>
          <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Authentication Code</label>
              <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            </div>
            <button class="btn btn-primary w-100">Verify</button>
            <div class="text-center mt-3">
              <a href="verify_2fa.php?recovery=1" class="text-decoration-none small text-muted">Use a recovery code instead</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>

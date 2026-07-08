<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

configureSession();
require_once __DIR__ . '/../db.php';

$error = '';

if (!empty($_SESSION['admin_logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

requireCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    if (!rateLimitCheck('admin_login_' . $_SERVER['REMOTE_ADDR'], 5, 300)) {
        $error = "Too many attempts. Try again later.";
    } else {
        $u = trim($_POST['username']);
        $p = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$u]);
        $user = $stmt->fetch();

        if ($user && password_verify($p, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        }

        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    :root { --forest: #275D2B; --lime: #82D148; --navy: #0B1021; --radius: 12px; --radius-lg: 16px; --shadow-lg: 0 10px 30px rgba(11,16,33,0.15); }
    body { background: linear-gradient(135deg, #0B1021 0%, #1a4020 50%, #275D2B 100%); min-height: 100vh; display: flex; align-items: center; }
    .auth-card { max-width: 400px; margin: 0 auto; width: 100%; }
    .auth-card .card { border: none; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); }
    .auth-card .card-body { padding: 2rem; }
    .auth-header { text-align: center; margin-bottom: 1.5rem; }
    .auth-header h2 { font-weight: 800; font-size: 1.4rem; color: #0B1021; }
    .auth-header p { color: #6B7E6B; font-size: 0.85rem; margin: 0; }
    .form-control { border: 1.5px solid #E8EDE8; border-radius: 12px; padding: 0.65rem 1rem; font-size: 0.9rem; }
    .form-control:focus { border-color: var(--forest); box-shadow: 0 0 0 3px rgba(39,93,43,0.15); }
    .form-label { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.3rem; }
    .btn-primary { background: var(--lime); border: none; border-radius: 12px; font-weight: 600; padding: 0.65rem; color: var(--navy); transition: all 0.2s; }
    .btn-primary:hover { background: #6fba3a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(130,209,72,0.3); }
    .alert { border: none; border-radius: 12px; font-size: 0.85rem; }
  </style>
</head>
<body>
  <div class="auth-card">
    <div class="card">
      <div class="card-body">
        <div class="auth-header">
          <h2>Admin Panel</h2>
          <p>Sign in to manage the directory</p>
        </div>
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input name="username" class="form-control" required autofocus value="<?= isset($u) ? htmlspecialchars($u) : '' ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Sign In</button>
        </form>
        <div class="text-center mt-3">
          <a href="../index.php" class="text-decoration-none small" style="color:var(--primary)">&larr; Back to site</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';
requireCsrf();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, full_name, password, role) VALUES (?, ?, ?, 'moderator')");
        try {
            $stmt->execute([$username, $fullname, $hash]);
            header("Location: moderators.php");
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Username already exists.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Moderator</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width: 500px;">
  <h3>Add New Moderator</h3>

  <?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrfField() ?>
    <div class="mb-3">
      <label>Username</label>
      <input name="username" class="form-control" required autofocus />
    </div>
    <div class="mb-3">
      <label>Full Name</label>
      <input name="full_name" class="form-control" required />
    </div>
    <div class="mb-3">
      <label>Password</label>
      <input name="password" type="password" class="form-control" required />
    </div>
    <div class="mb-3">
      <label>Confirm Password</label>
      <input name="password_confirm" type="password" class="form-control" required />
    </div>
    <button class="btn btn-success w-100">Add Moderator</button>
    <a href="moderators.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
  </form>
</div>
</body>
</html>

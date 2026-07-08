<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db.php';

// Fetch moderators
$stmt = $pdo->prepare("SELECT * FROM admins WHERE role = 'moderator' ORDER BY id DESC");
$stmt->execute();
$moderators = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Moderators</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="dashboard.php">Admin Panel</a>
    <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a href="add_moderator.php" class="btn btn-success nav-link">Add Moderator</a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger">Logout</a></li>
    </ul>
</nav>

<div class="container mt-4">
    <h2>Moderators</h2>
    <?php if (count($moderators) === 0): ?>
        <p>No moderators found.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($moderators as $mod): ?>
                    <tr>
                        <td><?= $mod['id'] ?></td>
                        <td><?= htmlspecialchars($mod['username']) ?></td>
                        <td><?= htmlspecialchars($mod['full_name']) ?></td>
                        <td>
                            <a href="edit_moderator.php?id=<?= $mod['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_moderator.php?id=<?= $mod['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this moderator?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>

</body>
</html>

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/helpers.php';

configureSession();

if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_role'])) {
    header("Location: index.php");
    exit;
}

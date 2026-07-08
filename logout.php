<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
setFlash('success', 'You have been logged out successfully.');
$_SESSION = [];
session_destroy();
header("Location: index.php");

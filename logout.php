<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
configureSession();
$_SESSION = [];
session_destroy();
header("Location: index.php");

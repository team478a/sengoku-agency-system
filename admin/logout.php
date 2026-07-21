<?php
// logout.php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
session_destroy();
header('Location: /admin/login.php');
exit;

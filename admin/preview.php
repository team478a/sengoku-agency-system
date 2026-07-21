<?php
// admin/preview.php → lp.phpのプレビューモードにリダイレクト
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();
$id = (int)($_GET['id'] ?? 0);
header('Location: /lp.php?preview=1&template_id=' . $id);
exit;

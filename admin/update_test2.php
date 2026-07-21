<?php
// header.phpのincludeテスト
$pageTitle = 'テスト';
require_once __DIR__ . '/header.php';
?>
<p style="color:green;font-size:1.5rem;padding:2rem;">✓ header.phpのinclude成功！update.phpも動くはずです。</p>
<p><a href="/admin/update.php" style="color:#C9A84C;">update.phpを開く →</a></p>
<?php require_once __DIR__ . '/footer.php'; ?>

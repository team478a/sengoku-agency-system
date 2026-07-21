<?php
/**
 * ディレクトリ一括作成スクリプト
 * 実行後は削除してください
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$base = dirname(__DIR__);

$dirs = [
    'uploads',
    'uploads/lp',
    'uploads/lp/nft',
    'uploads/lp/oshi',
    'uploads/lp/sengoku',
    'uploads/lp/influencer',
    'uploads/lp/evaluator',
    'uploads/lp/samurai',
    'uploads/profile',
    'uploads/materials',
    'uploads/materials/images',
    'uploads/materials/videos',
    'uploads/materials/files',
    'backups',
];

$results = [];
foreach ($dirs as $dir) {
    $path = $base . '/' . $dir;
    if (is_dir($path)) {
        $results[] = ['skip', $dir, '既に存在'];
    } elseif (mkdir($path, 0755, true)) {
        $results[] = ['ok', $dir, '作成成功'];
    } else {
        $results[] = ['err', $dir, '作成失敗'];
    }
}

// backupsに.htaccessを設置
$htpath = $base . '/backups/.htaccess';
if (!file_exists($htpath)) {
    file_put_contents($htpath, "deny from all\n");
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>ディレクトリ作成</title>
<style>
body{font-family:'Noto Sans JP',sans-serif;background:#13100D;color:#E8E0CC;padding:2rem;}
h1{color:#C9A84C;font-size:1.2rem;margin-bottom:1.5rem;}
table{border-collapse:collapse;width:100%;max-width:600px;}
td{padding:.5rem .75rem;border-bottom:1px solid rgba(201,168,76,.15);font-size:.88rem;}
.ok{color:#5ecb9b;}.err{color:#e08080;}.skip{color:rgba(245,240,232,.4);}
.btn{display:inline-block;margin-top:1.5rem;padding:.65rem 1.5rem;background:linear-gradient(135deg,#C9A84C,#E2C87A);color:#13100D;font-weight:700;border-radius:3px;text-decoration:none;}
</style>
</head>
<body>
<h1>📁 ディレクトリ一括作成</h1>
<table>
<?php foreach ($results as [$status, $dir, $msg]): ?>
<tr>
    <td class="<?= $status ?>"><?= $status === 'ok' ? '✓' : ($status === 'err' ? '✗' : '—') ?></td>
    <td><?= h($dir) ?></td>
    <td class="<?= $status ?>"><?= h($msg) ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php
$errCount = count(array_filter($results, fn($r) => $r[0] === 'err'));
$okCount  = count(array_filter($results, fn($r) => $r[0] === 'ok'));
?>
<p style="margin-top:1.25rem;font-size:.9rem;">
    作成: <strong style="color:#5ecb9b;"><?= $okCount ?>件</strong>　
    スキップ: <?= count($results) - $okCount - $errCount ?>件
    <?= $errCount ? '　<strong style="color:#e08080;">失敗: ' . $errCount . '件</strong>' : '' ?>
</p>

<?php if ($errCount === 0): ?>
<p style="margin-top:.75rem;color:#5ecb9b;font-size:.88rem;">✓ すべて完了しました。このファイルは削除してください。</p>
<?php endif; ?>

<a href="/admin/dashboard.php" class="btn">管理画面に戻る</a>
</body>
</html>

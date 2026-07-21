<?php
$pageTitle = 'お知らせ管理';
require_once __DIR__ . '/header.php';

$db      = getDB();
$csrf    = getCsrfToken();
$message = '';
$msgType = 'success';

// 削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $db->prepare("DELETE FROM notices WHERE id=?")->execute([(int)$_POST['id']]);
        $message = 'お知らせを削除しました。';
    }
}

// 公開/非公開切替
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $db->prepare("UPDATE notices SET status=IF(status='active','inactive','active') WHERE id=?")
           ->execute([(int)$_POST['id']]);
        $message = 'ステータスを変更しました。';
    }
}

// 登録・更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','update'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $title    = trim($_POST['title'] ?? '');
        $body     = trim($_POST['body']  ?? '');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        if (!$title || !$body) {
            $message = 'タイトルと本文は必須です。'; $msgType = 'error';
        } elseif (($_POST['action'] ?? '') === 'create') {
            $db->prepare("INSERT INTO notices (title, body, is_pinned) VALUES (?,?,?)")
               ->execute([$title, $body, $isPinned]);
            $message = 'お知らせを作成しました。';
        } else {
            $db->prepare("UPDATE notices SET title=?, body=?, is_pinned=? WHERE id=?")
               ->execute([$title, $body, $isPinned, (int)$_POST['id']]);
            $message = 'お知らせを更新しました。';
        }
    }
}

$editNotice = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM notices WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editNotice = $s->fetch() ?: null;
}

$notices = $db->query("SELECT * FROM notices ORDER BY is_pinned DESC, created_at DESC")->fetchAll();
?>

<?php if ($message): ?><div class="alert alert-<?= $msgType ?>"><?= h($message) ?></div><?php endif; ?>

<div class="card">
    <p class="card-title"><?= $editNotice ? 'お知らせを編集' : '新規お知らせ作成' ?></p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="<?= $editNotice ? 'update' : 'create' ?>">
        <?php if ($editNotice): ?><input type="hidden" name="id" value="<?= $editNotice['id'] ?>"><?php endif; ?>

        <div class="form-group">
            <label>タイトル *</label>
            <input type="text" name="title" value="<?= h($editNotice['title'] ?? '') ?>" required placeholder="例：テンプレートを追加しました">
        </div>
        <div class="form-group">
            <label>本文 *</label>
            <textarea name="body" rows="5" required placeholder="お知らせの内容を入力..."><?= h($editNotice['body'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label class="form-check">
                <input type="checkbox" name="is_pinned" <?= ($editNotice['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                📌 上部に固定表示
            </label>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-gold"><?= $editNotice ? '更新する' : '作成する' ?></button>
            <?php if ($editNotice): ?><a href="/admin/notices.php" class="btn btn-outline">キャンセル</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <table>
        <thead><tr><th>タイトル</th><th>固定</th><th>作成日</th><th>状態</th><th>操作</th></tr></thead>
        <tbody>
        <?php if ($notices): foreach ($notices as $n): ?>
        <tr>
            <td>
                <p style="font-weight:700;font-size:.88rem;"><?= h($n['title']) ?></p>
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:.2rem;"><?= h(mb_strimwidth($n['body'],0,60,'…')) ?></p>
            </td>
            <td style="text-align:center;"><?= $n['is_pinned'] ? '📌' : '—' ?></td>
            <td style="font-size:.78rem;color:var(--text-muted);"><?= date('Y/m/d', strtotime($n['created_at'])) ?></td>
            <td><span class="badge badge-<?= $n['status']==='active'?'active':'inactive' ?>"><?= $n['status']==='active'?'公開中':'非公開' ?></span></td>
            <td style="white-space:nowrap;">
                <a href="/admin/notices.php?edit=<?= $n['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button class="btn btn-outline btn-sm"><?= $n['status']==='active'?'非公開':'公開' ?></button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('削除しますか？')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button class="btn btn-danger btn-sm">削除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2.5rem;">お知らせはまだありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php
$pageTitle = '管理スタッフ';
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);

function adminStaffTableReady(PDO $db): bool {
    return tableHasColumn('admins', 'role') && tableHasColumn('admins', 'status') && tableHasColumn('admins', 'display_name');
}

function adminStaffLog(PDO $db, string $action, int $targetId, array $details = []): void {
    try {
        $db->prepare("INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_hash) VALUES (?, ?, 'admin_staff', ?, ?, ?)")
           ->execute([
               (int)($_SESSION['admin_id'] ?? 0) ?: null,
               $action,
               $targetId,
               json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
               hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
           ]);
    } catch (Throwable $e) {
        error_log('admin staff log failed: ' . $e->getMessage());
    }
}

$ready = adminStaffTableReady($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } elseif (!$ready) {
        $message = '管理スタッフ機能のDBマイグレーションが未適用です。アップデート画面でDBマイグレーションを適用してください。';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create') {
                $username = sanitizeInput($_POST['username'] ?? '');
                $displayName = sanitizeInput($_POST['display_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = ($_POST['role'] ?? 'staff') === 'super_admin' ? 'super_admin' : 'staff';

                if ($username === '' || $displayName === '' || $password === '') {
                    throw new RuntimeException('ユーザー名、表示名、初期パスワードを入力してください。');
                }
                if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
                    throw new RuntimeException('ユーザー名は半角英数字・記号（_.-）3〜50文字で入力してください。');
                }
                if (strlen($password) < 8) {
                    throw new RuntimeException('初期パスワードは8文字以上で入力してください。');
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('メールアドレスの形式が正しくありません。');
                }

                $stmt = $db->prepare("INSERT INTO admins (username, display_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$username, $displayName, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
                $newId = (int)$db->lastInsertId();
                adminStaffLog($db, 'create_admin_staff', $newId, ['username' => $username, 'role' => $role]);
                $message = '管理スタッフを追加しました。';
            } elseif ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $displayName = sanitizeInput($_POST['display_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = ($_POST['role'] ?? 'staff') === 'super_admin' ? 'super_admin' : 'staff';
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

                if ($id <= 0 || $displayName === '') {
                    throw new RuntimeException('更新内容が正しくありません。');
                }
                if ($id === $currentAdminId && $status !== 'active') {
                    throw new RuntimeException('自分自身を停止することはできません。');
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('メールアドレスの形式が正しくありません。');
                }

                $db->prepare("UPDATE admins SET display_name=?, email=?, role=?, status=?, updated_at=NOW() WHERE id=?")
                   ->execute([$displayName, $email, $role, $status, $id]);
                adminStaffLog($db, 'update_admin_staff', $id, ['role' => $role, 'status' => $status]);
                $message = '管理スタッフを更新しました。';
            } elseif ($action === 'reset_password') {
                $id = (int)($_POST['id'] ?? 0);
                $password = $_POST['password'] ?? '';
                if ($id <= 0 || strlen($password) < 8) {
                    throw new RuntimeException('新しいパスワードは8文字以上で入力してください。');
                }
                $db->prepare("UPDATE admins SET password=?, updated_at=NOW() WHERE id=?")
                   ->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
                adminStaffLog($db, 'reset_admin_staff_password', $id);
                $message = 'パスワードを変更しました。';
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('削除対象が正しくありません。');
                }
                if ($id === $currentAdminId) {
                    throw new RuntimeException('自分自身を削除することはできません。');
                }
                $db->prepare("DELETE FROM admins WHERE id=?")->execute([$id]);
                adminStaffLog($db, 'delete_admin_staff', $id);
                $message = '管理スタッフを削除しました。';
            }
        } catch (Throwable $e) {
            $message = 'エラー: ' . $e->getMessage();
            $msgType = 'error';
        }
    }
}

$staff = [];
if ($ready) {
    $staff = $db->query("SELECT * FROM admins ORDER BY FIELD(role, 'super_admin', 'staff'), status ASC, id ASC")->fetchAll();
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$ready): ?>
<div class="alert alert-error">
    管理スタッフ機能のDBマイグレーションが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。
</div>
<?php else: ?>

<div class="card">
    <p class="card-title">管理スタッフを追加</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
            <div class="form-group">
                <label>ユーザー名（ログインID）</label>
                <input type="text" name="username" required placeholder="staff001" pattern="[A-Za-z0-9_.-]{3,50}">
            </div>
            <div class="form-group">
                <label>表示名</label>
                <input type="text" name="display_name" required placeholder="山田 太郎">
            </div>
            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" placeholder="staff@example.com">
            </div>
            <div class="form-group">
                <label>権限</label>
                <select name="role">
                    <option value="staff">スタッフ</option>
                    <option value="super_admin">オーナー管理者</option>
                </select>
            </div>
            <div class="form-group">
                <label>初期パスワード</label>
                <input type="password" name="password" required minlength="8" placeholder="8文字以上">
            </div>
        </div>
        <button type="submit" class="btn btn-gold">スタッフを追加</button>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
        <p class="card-title" style="margin:0;">管理スタッフ一覧</p>
    </div>
    <div class="table-scroll">
        <table style="min-width:980px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ユーザー名</th>
                    <th>表示名</th>
                    <th>メール</th>
                    <th>権限</th>
                    <th>状態</th>
                    <th>作成日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($staff as $row): ?>
                <tr>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <td><?= (int)$row['id'] ?></td>
                        <td>
                            <strong><?= h($row['username']) ?></strong>
                            <?php if ((int)$row['id'] === $currentAdminId): ?>
                                <span class="badge badge-new">自分</span>
                            <?php endif; ?>
                        </td>
                        <td><input type="text" name="display_name" value="<?= h($row['display_name'] ?? $row['username']) ?>" required></td>
                        <td><input type="email" name="email" value="<?= h($row['email'] ?? '') ?>"></td>
                        <td>
                            <select name="role">
                                <option value="staff" <?= ($row['role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>>スタッフ</option>
                                <option value="super_admin" <?= ($row['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>オーナー管理者</option>
                            </select>
                        </td>
                        <td>
                            <select name="status" <?= (int)$row['id'] === $currentAdminId ? 'disabled' : '' ?>>
                                <option value="active" <?= ($row['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>有効</option>
                                <option value="inactive" <?= ($row['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>停止</option>
                            </select>
                            <?php if ((int)$row['id'] === $currentAdminId): ?>
                                <input type="hidden" name="status" value="active">
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.78rem;color:var(--text-muted);"><?= h(date('Y/m/d', strtotime($row['created_at'] ?? 'now'))) ?></td>
                        <td>
                            <button type="submit" class="btn btn-gold btn-sm">保存</button>
                    </form>
                            <form method="post" style="display:inline-flex;gap:.35rem;align-items:center;margin-top:.35rem;" onsubmit="return confirm('このスタッフのパスワードを変更します。よろしいですか？');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <input type="password" name="password" required minlength="8" placeholder="新PW" style="width:130px;">
                                <button type="submit" class="btn btn-outline btn-sm">PW変更</button>
                            </form>
                            <?php if ((int)$row['id'] !== $currentAdminId): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('この管理スタッフを削除します。よろしいですか？');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">削除</button>
                            </form>
                            <?php endif; ?>
                        </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

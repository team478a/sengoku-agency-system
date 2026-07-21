<?php
$pageTitle = '操作ログ';
require_once __DIR__ . '/header.php';

$db = getDB();

$actionLabels = [
    'role_update'             => '権限変更',
    'toggle_status'           => '状態変更',
    'safe_delete_to_inactive' => '削除を停止に変更',
    'delete'                  => '削除',
    'reset_password'          => 'PW再発行',
    'create_admin_staff'      => '管理スタッフ追加',
    'update_admin_staff'      => '管理スタッフ更新',
    'reset_admin_staff_password' => '管理スタッフPW変更',
    'delete_admin_staff'      => '管理スタッフ削除',
    'common_id_mapping_update' => '共通IDサービス紐づけ更新',
    'common_id_relation_update' => '共通ID紹介関係更新',
    'common_id_merge' => '共通ID統合',
];

$action = sanitizeInput($_GET['action_type'] ?? '');
$q      = sanitizeInput($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where = [];
$params = [];

if ($action !== '' && isset($actionLabels[$action])) {
    $where[] = 'l.action = ?';
    $params[] = $action;
}

if ($q !== '') {
    $where[] = '(a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR ad.username LIKE ? OR l.action LIKE ?)';
    $kw = '%' . $q . '%';
    array_push($params, $kw, $kw, $kw, $kw, $kw);
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM admin_action_logs l
    LEFT JOIN admins ad ON l.admin_id = ad.id
    LEFT JOIN agents a ON l.target_type = 'agent' AND l.target_id = a.id
    $whereSql
");
$countStmt->execute($params);
$pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);

$stmt = $db->prepare("
    SELECT l.*, ad.username AS admin_username, a.agent_name, a.person_name, a.agent_code
    FROM admin_action_logs l
    LEFT JOIN admins ad ON l.admin_id = ad.id
    LEFT JOIN agents a ON l.target_type = 'agent' AND l.target_id = a.id
    $whereSql
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET {$pag['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

function actionLogLabel(string $action, array $labels): string {
    return $labels[$action] ?? $action;
}

function actionLogDetails(?string $json): string {
    if (!$json) return '—';
    $data = json_decode($json, true);
    if (!is_array($data) || !$data) return $json;
    $parts = [];
    foreach ($data as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'あり' : 'なし';
        } elseif ($value === null || $value === '') {
            $value = '—';
        }
        $parts[] = $key . ': ' . $value;
    }
    return implode(' / ', $parts);
}

$baseQuery = ['action_type' => $action, 'q' => $q];
?>

<div class="card">
    <p class="card-title">管理者操作ログ</p>
    <form method="get" style="display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,220px) auto;gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>検索</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="メンバー名・担当者名・コード・管理者">
        </div>
        <div class="form-group" style="margin:0;">
            <label>操作種別</label>
            <select name="action_type">
                <option value="">すべて</option>
                <?php foreach ($actionLabels as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $action === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:.5rem;">
            <button type="submit" class="btn btn-gold">表示</button>
            <?php if ($q !== '' || $action !== ''): ?><a href="/admin/action_logs.php" class="btn btn-outline">クリア</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">最新ログ</p>
        <span style="font-size:.78rem;color:var(--text-muted);">全 <?= number_format((int)$pag['total']) ?> 件</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>日時</th>
                <th>管理者</th>
                <th>操作</th>
                <th>対象</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($logs): foreach ($logs as $log): ?>
            <tr>
                <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                    <?= h(date('Y/m/d H:i', strtotime($log['created_at']))) ?>
                </td>
                <td style="font-size:.82rem;">
                    <?= h($log['admin_username'] ?? '—') ?>
                </td>
                <td>
                    <span style="font-size:.72rem;font-weight:700;padding:.15rem .5rem;border-radius:2px;background:rgba(201,168,76,.16);color:var(--gold);">
                        <?= h(actionLogLabel($log['action'], $actionLabels)) ?>
                    </span>
                </td>
                <td style="font-size:.82rem;">
                    <?php if (!empty($log['agent_code'])): ?>
                        <a href="/admin/agents.php?edit=<?= (int)$log['target_id'] ?>" style="color:var(--gold);text-decoration:none;">
                            <?= h($log['agent_name'] ?: $log['person_name']) ?>
                        </a>
                        <span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($log['agent_code']) ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-muted);"><?= h($log['target_type']) ?> #<?= h($log['target_id']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.78rem;color:rgba(245,240,232,.7);max-width:420px;word-break:break-word;">
                    <?= h(actionLogDetails($log['details'] ?? '')) ?>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2.5rem;">操作ログはまだありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
        <?php $baseQuery['page'] = $i; ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?<?= h(http_build_query($baseQuery)) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

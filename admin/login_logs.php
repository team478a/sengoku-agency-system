<?php
$pageTitle = 'ログイン記録';
require_once __DIR__ . '/header.php';

$db = getDB();

$userType = sanitizeInput($_GET['user_type'] ?? '');
$result   = sanitizeInput($_GET['result'] ?? '');
$q        = sanitizeInput($_GET['q'] ?? '');
$from     = sanitizeInput($_GET['from'] ?? '');
$to       = sanitizeInput($_GET['to'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;

$where = [];
$params = [];

if (in_array($userType, ['admin', 'agent'], true)) {
    $where[] = 'l.user_type = ?';
    $params[] = $userType;
}

if ($result === 'success') {
    $where[] = 'l.success = 1';
} elseif ($result === 'failed') {
    $where[] = 'l.success = 0';
}

if ($q !== '') {
    $where[] = '(l.email LIKE ? OR a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR ad.username LIKE ? OR ad.display_name LIKE ?)';
    $kw = '%' . $q . '%';
    array_push($params, $kw, $kw, $kw, $kw, $kw, $kw);
}

if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'l.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}

if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'l.created_at <= ?';
    $params[] = $to . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM login_logs l
    LEFT JOIN agents a ON l.user_type='agent' AND l.user_id=a.id
    LEFT JOIN admins ad ON l.user_type='admin' AND l.user_id=ad.id
    $whereSql
");
$countStmt->execute($params);
$pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);

$stmt = $db->prepare("
    SELECT
        l.*,
        a.agent_name,
        a.person_name,
        a.agent_code,
        a.level AS agent_level,
        ad.username AS admin_username,
        ad.display_name AS admin_display_name,
        ad.role AS admin_role
    FROM login_logs l
    LEFT JOIN agents a ON l.user_type='agent' AND l.user_id=a.id
    LEFT JOIN admins ad ON l.user_type='admin' AND l.user_id=ad.id
    $whereSql
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET {$pag['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN l.success=1 THEN 1 ELSE 0 END) AS success_count,
        SUM(CASE WHEN l.success=0 THEN 1 ELSE 0 END) AS failed_count,
        SUM(CASE WHEN l.user_type='admin' THEN 1 ELSE 0 END) AS admin_count,
        SUM(CASE WHEN l.user_type='agent' THEN 1 ELSE 0 END) AS agent_count
    FROM login_logs l
    LEFT JOIN agents a ON l.user_type='agent' AND l.user_id=a.id
    LEFT JOIN admins ad ON l.user_type='admin' AND l.user_id=ad.id
    $whereSql
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch() ?: [];

function loginLogUserName(array $log): string {
    if (($log['user_type'] ?? '') === 'admin') {
        return (string)(($log['admin_display_name'] ?? '') ?: ($log['admin_username'] ?? '') ?: ($log['email'] ?? ''));
    }
    return (string)(($log['agent_name'] ?? '') ?: ($log['person_name'] ?? '') ?: ($log['email'] ?? ''));
}

function loginLogUserMeta(array $log): string {
    if (($log['user_type'] ?? '') === 'admin') {
        return trim((string)($log['admin_role'] ?? ''));
    }
    $parts = [];
    if (!empty($log['agent_code'])) $parts[] = $log['agent_code'];
    if (!empty($log['agent_level'])) $parts[] = $log['agent_level'];
    return implode(' / ', $parts);
}

$csvQuery = $_GET;
$csvQuery['type'] = 'login_logs';
$csvUrl = '/admin/export_csv.php?' . http_build_query($csvQuery);
$baseQuery = ['user_type' => $userType, 'result' => $result, 'q' => $q, 'from' => $from, 'to' => $to];
?>

<div class="card">
    <p class="card-title">ログイン記録</p>
    <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>検索</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="メール・名前・コード">
        </div>
        <div class="form-group" style="margin:0;">
            <label>種別</label>
            <select name="user_type">
                <option value="">すべて</option>
                <option value="admin" <?= $userType === 'admin' ? 'selected' : '' ?>>管理者</option>
                <option value="agent" <?= $userType === 'agent' ? 'selected' : '' ?>>代理店</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>結果</label>
            <select name="result">
                <option value="">すべて</option>
                <option value="success" <?= $result === 'success' ? 'selected' : '' ?>>成功</option>
                <option value="failed" <?= $result === 'failed' ? 'selected' : '' ?>>失敗</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>開始日</label>
            <input type="date" name="from" value="<?= h($from) ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>終了日</label>
            <input type="date" name="to" value="<?= h($to) ?>">
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-gold">表示</button>
            <a href="/admin/login_logs.php" class="btn btn-outline">クリア</a>
            <a href="<?= h($csvUrl) ?>" class="btn btn-outline">CSV出力</a>
        </div>
    </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.25rem;">
    <div class="stat-card"><div class="stat-label">総数</div><div class="stat-num"><?= number_format((int)($stats['total_count'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="stat-label">成功</div><div class="stat-num"><?= number_format((int)($stats['success_count'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="stat-label">失敗</div><div class="stat-num"><?= number_format((int)($stats['failed_count'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="stat-label">管理者</div><div class="stat-num"><?= number_format((int)($stats['admin_count'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="stat-label">代理店</div><div class="stat-num"><?= number_format((int)($stats['agent_count'] ?? 0)) ?></div></div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">記録一覧</p>
        <span style="font-size:.78rem;color:var(--text-muted);">全 <?= number_format((int)$pag['total']) ?> 件</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>日時</th>
                    <th>種別</th>
                    <th>ユーザー</th>
                    <th>ログインID</th>
                    <th>結果</th>
                    <th>IPハッシュ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs): foreach ($logs as $log): ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i:s', strtotime($log['created_at']))) ?></td>
                    <td>
                        <span style="font-size:.72rem;font-weight:700;padding:.15rem .5rem;border-radius:2px;background:<?= $log['user_type'] === 'admin' ? 'rgba(201,168,76,.16)' : 'rgba(94,203,155,.12)' ?>;color:<?= $log['user_type'] === 'admin' ? 'var(--gold)' : '#5ecb9b' ?>;">
                            <?= $log['user_type'] === 'admin' ? '管理者' : '代理店' ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= h(loginLogUserName($log) ?: '—') ?></strong>
                        <?php $meta = loginLogUserMeta($log); ?>
                        <?php if ($meta !== ''): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($meta) ?></span><?php endif; ?>
                    </td>
                    <td style="word-break:break-all;"><?= h($log['email'] ?? '') ?></td>
                    <td>
                        <span style="color:<?= !empty($log['success']) ? '#5ecb9b' : '#e08080' ?>;font-weight:700;font-size:.82rem;">
                            <?= !empty($log['success']) ? '成功' : '失敗' ?>
                        </span>
                    </td>
                    <td><code style="font-size:.72rem;"><?= h(substr((string)($log['ip_hash'] ?? ''), 0, 16)) ?></code></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2.5rem;">ログイン記録はありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
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

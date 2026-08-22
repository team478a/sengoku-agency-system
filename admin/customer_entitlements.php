<?php
$pageTitle = '購入権限管理';
require_once __DIR__ . '/header.php';

$db = getDB();
$hasTable = customerEntitlementTablesReady();
$q = sanitizeInput($_GET['q'] ?? '');
$systemKey = sanitizeInput($_GET['system_key'] ?? '');
$projectKey = sanitizeInput($_GET['project_key'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$export = ($_GET['export'] ?? '') === 'csv';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;

function entitlementAdminStatusLabel(string $status): string {
    $labels = [
        'active' => '有効',
        'inactive' => '無効',
        'expired' => '期限切れ',
        'revoked' => '取消',
        'canceled' => 'キャンセル',
    ];
    return $labels[$status] ?? $status;
}

function entitlementAdminBadge(string $status): string {
    $colors = [
        'active' => 'background:rgba(44,143,99,.18);color:#2c8f63;',
        'expired' => 'background:rgba(160,132,90,.18);color:#8a6d3b;',
        'revoked' => 'background:rgba(180,55,55,.18);color:#b43737;',
        'canceled' => 'background:rgba(180,55,55,.18);color:#b43737;',
        'inactive' => 'background:rgba(100,100,100,.16);color:#666;',
    ];
    $style = $colors[$status] ?? $colors['inactive'];
    return '<span style="display:inline-block;padding:.25rem .65rem;border-radius:999px;font-size:.76rem;font-weight:700;' . $style . '">' . h(entitlementAdminStatusLabel($status)) . '</span>';
}

function entitlementAdminDate(?string $value): string {
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    return date('Y/m/d H:i', strtotime($value));
}

$rows = [];
$stats = ['total' => 0, 'active' => 0, 'expires_soon' => 0, 'inactive' => 0];
$systemOptions = [];
$projectOptions = [];
$pag = paginate(0, $perPage, $page);

if ($hasTable) {
    $systemOptions = $db->query("SELECT DISTINCT system_key FROM customer_entitlements WHERE system_key<>'' ORDER BY system_key")->fetchAll(PDO::FETCH_COLUMN);
    $projectOptions = $db->query("SELECT DISTINCT project_key FROM customer_entitlements WHERE project_key IS NOT NULL AND project_key<>'' ORDER BY project_key")->fetchAll(PDO::FETCH_COLUMN);
    $stats = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status='active' AND expires_at IS NOT NULL AND expires_at >= NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expires_soon,
            SUM(CASE WHEN status<>'active' THEN 1 ELSE 0 END) AS inactive
        FROM customer_entitlements
    ")->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(e.common_user_id LIKE ? OR e.external_user_id LIKE ? OR e.product_code LIKE ? OR e.order_id LIKE ? OR e.order_item_id LIKE ?)';
        $kw = '%' . $q . '%';
        array_push($params, $kw, $kw, $kw, $kw, $kw);
    }
    if ($systemKey !== '') {
        $where[] = 'e.system_key = ?';
        $params[] = $systemKey;
    }
    if ($projectKey !== '') {
        $where[] = 'e.project_key = ?';
        $params[] = $projectKey;
    }
    if ($status !== '') {
        $where[] = 'e.status = ?';
        $params[] = $status;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    if ($export) {
        $stmt = $db->prepare("
            SELECT e.*, p.name AS project_name
            FROM customer_entitlements e
            LEFT JOIN projects p ON e.project_id = p.id
            $whereSql
            ORDER BY e.updated_at DESC, e.id DESC
        ");
        $stmt->execute($params);
        $exportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="customer_entitlements_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['状態', '共通顧客ID', '外部システム', '外部ユーザーID', 'プロジェクト', '商品コード', '注文ID', '注文明細ID', '開始', '終了', '更新日']);
        foreach ($exportRows as $row) {
            fputcsv($out, [
                entitlementAdminStatusLabel((string)$row['status']),
                $row['common_user_id'],
                $row['system_key'],
                $row['external_user_id'],
                $row['project_key'] ?: $row['project_name'],
                $row['product_code'],
                $row['order_id'],
                $row['order_item_id'],
                entitlementAdminDate($row['starts_at'] ?? null),
                entitlementAdminDate($row['expires_at'] ?? null),
                entitlementAdminDate($row['updated_at'] ?? null),
            ]);
        }
        exit;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM customer_entitlements e $whereSql");
    $countStmt->execute($params);
    $pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);
    $stmt = $db->prepare("
        SELECT e.*, p.name AS project_name
        FROM customer_entitlements e
        LEFT JOIN projects p ON e.project_id = p.id
        $whereSql
        ORDER BY e.updated_at DESC, e.id DESC
        LIMIT $perPage OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$baseQuery = [
    'q' => $q,
    'system_key' => $systemKey,
    'project_key' => $projectKey,
    'status' => $status,
];
?>

<?php if (!$hasTable): ?>
<div class="alert alert-error">購入権限管理のDBマイグレーションが未適用です。アップデート画面からDBマイグレーションを適用してください。</div>
<?php else: ?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-number"><?= number_format((int)$stats['total']) ?></div><div class="stat-label">購入権限</div></div>
    <div class="stat-card"><div class="stat-number"><?= number_format((int)$stats['active']) ?></div><div class="stat-label">有効</div></div>
    <div class="stat-card"><div class="stat-number"><?= number_format((int)$stats['expires_soon']) ?></div><div class="stat-label">30日以内に期限</div></div>
    <div class="stat-card"><div class="stat-number"><?= number_format((int)$stats['inactive']) ?></div><div class="stat-label">無効・停止</div></div>
</div>

<div class="card">
    <p class="card-title">購入権限を検索</p>
    <form method="get" style="display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>キーワード</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="共通ID・商品・注文ID">
        </div>
        <div class="form-group" style="margin:0;">
            <label>外部システム</label>
            <select name="system_key">
                <option value="">すべて</option>
                <?php foreach ($systemOptions as $option): ?>
                    <option value="<?= h($option) ?>" <?= $systemKey === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>プロジェクト</label>
            <select name="project_key">
                <option value="">すべて</option>
                <?php foreach ($projectOptions as $option): ?>
                    <option value="<?= h($option) ?>" <?= $projectKey === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>状態</label>
            <select name="status">
                <option value="">すべて</option>
                <?php foreach (['active','inactive','expired','revoked','canceled'] as $option): ?>
                    <option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h(entitlementAdminStatusLabel($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-gold">表示</button>
            <a href="/admin/customer_entitlements.php" class="btn btn-outline">リセット</a>
            <a href="/admin/customer_entitlements.php?<?= h(http_build_query(array_filter($baseQuery, fn($v) => $v !== '')) . '&export=csv') ?>" class="btn btn-outline">CSV出力</a>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">購入権限一覧</p>
        <span style="font-size:.78rem;color:var(--text-muted);">全 <?= number_format((int)$pag['total']) ?> 件</span>
    </div>
    <div class="table-scroll">
        <table style="min-width:1100px;">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>共通顧客ID</th>
                    <th>外部システム</th>
                    <th>プロジェクト</th>
                    <th>商品</th>
                    <th>注文</th>
                    <th>外部ユーザー</th>
                    <th>有効期間</th>
                    <th>更新日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
                <tr>
                    <td><?= entitlementAdminBadge((string)$row['status']) ?></td>
                    <td><code><?= h($row['common_user_id']) ?></code></td>
                    <td><?= h($row['system_key']) ?></td>
                    <td><?= h($row['project_key'] ?: ($row['project_name'] ?? '-')) ?></td>
                    <td><strong><?= h($row['product_code']) ?></strong></td>
                    <td style="font-size:.82rem;">
                        <?= h($row['order_id'] ?: '-') ?><br>
                        <span style="color:var(--text-muted);"><?= h($row['order_item_id'] ?: '-') ?></span>
                    </td>
                    <td><?= h($row['external_user_id'] ?: '-') ?></td>
                    <td style="font-size:.82rem;white-space:nowrap;">
                        <?= h(entitlementAdminDate($row['starts_at'] ?? null)) ?><br>
                        <span style="color:var(--text-muted);">〜 <?= h(entitlementAdminDate($row['expires_at'] ?? null)) ?></span>
                    </td>
                    <td style="font-size:.82rem;white-space:nowrap;"><?= h(entitlementAdminDate($row['updated_at'] ?? null)) ?></td>
                    <td>
                        <?php if (!empty($row['common_user_id'])): ?>
                            <a class="btn btn-outline btn-sm" href="/admin/common_hub.php?common_user_id=<?= urlencode((string)$row['common_user_id']) ?>">顧客HUB</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:2rem;">購入権限はありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
        <?php $baseQuery['page'] = $i; ?>
        <a class="<?= $i === $pag['page'] ? 'active' : '' ?>" href="?<?= h(http_build_query(array_filter($baseQuery, fn($v) => $v !== ''))) ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$pageTitle = '共通顧客HUB';
require_once __DIR__ . '/header.php';

$db = getDB();
$requiredTables = ['common_users'];
$optionalTables = [
    'service_user_mappings',
    'system_account_links',
    'user_identities',
    'agency_customer_relations',
    'agent_touchpoints',
    'account_merge_logs',
    'integration_event_logs',
];
$tableReady = [];
foreach (array_merge($requiredTables, $optionalTables) as $table) {
    $tableReady[$table] = !empty(tableColumns($table));
}
$ready = $tableReady['common_users'];

function hubShort(?string $value, int $length = 80): string {
    $value = trim((string)$value);
    if ($value === '') return '-';
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
        return mb_substr($value, 0, $length, 'UTF-8') . '...';
    }
    return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
}

function hubStatusBadge(?string $status): string {
    $status = (string)($status ?: 'active');
    $isGood = in_array($status, ['active', 'confirmed', 'success'], true);
    $isWarn = in_array($status, ['merged', 'pending', 'none'], true);
    $bg = $isGood ? 'rgba(94,203,155,.14)' : ($isWarn ? 'rgba(201,168,76,.16)' : 'rgba(224,128,128,.16)');
    $color = $isGood ? '#5ecb9b' : ($isWarn ? 'var(--gold)' : '#e08080');
    return '<span style="display:inline-block;padding:.18rem .55rem;border-radius:999px;font-size:.72rem;font-weight:700;background:' . $bg . ';color:' . $color . ';">' . h($status) . '</span>';
}

function hubDate(?string $date): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date('Y/m/d H:i', $ts) : $date;
}

$q = sanitizeInput($_GET['q'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$systemKey = sanitizeInput($_GET['system_key'] ?? '');
$commonUserId = sanitizeInput($_GET['common_user_id'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$rows = [];
$pag = paginate(0, $perPage, $page);
$systemOptions = [];
$detail = [
    'user' => null,
    'identities' => [],
    'system_links' => [],
    'legacy_mappings' => [],
    'relations' => [],
    'touchpoints' => [],
    'merge_from' => [],
    'merge_to' => [],
    'logs' => [],
];

if ($ready) {
    if ($tableReady['system_account_links']) {
        $systemOptions = array_merge($systemOptions, $db->query("SELECT DISTINCT system_key FROM system_account_links WHERE system_key<>'' ORDER BY system_key")->fetchAll(PDO::FETCH_COLUMN));
    }
    if ($tableReady['service_user_mappings']) {
        $systemOptions = array_merge($systemOptions, $db->query("SELECT DISTINCT service_key FROM service_user_mappings WHERE service_key<>'' ORDER BY service_key")->fetchAll(PDO::FETCH_COLUMN));
    }
    $systemOptions = array_values(array_unique(array_filter($systemOptions)));
    sort($systemOptions);

    if ($commonUserId !== '') {
        $stmt = $db->prepare("SELECT * FROM common_users WHERE common_user_id=? LIMIT 1");
        $stmt->execute([$commonUserId]);
        $detail['user'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($tableReady['user_identities']) {
            $stmt = $db->prepare("SELECT * FROM user_identities WHERE common_user_id=? ORDER BY last_seen_at DESC, id DESC");
            $stmt->execute([$commonUserId]);
            $detail['identities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($tableReady['system_account_links']) {
            $stmt = $db->prepare("
                SELECT l.*, a.agent_code, a.agent_name, a.person_name
                FROM system_account_links l
                LEFT JOIN agents a ON l.agent_id=a.id
                WHERE l.common_user_id=?
                ORDER BY l.updated_at DESC, l.id DESC
            ");
            $stmt->execute([$commonUserId]);
            $detail['system_links'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($tableReady['service_user_mappings']) {
            $stmt = $db->prepare("
                SELECT m.*, a.agent_code, a.agent_name, a.person_name
                FROM service_user_mappings m
                LEFT JOIN agents a ON m.agent_id=a.id
                WHERE m.common_user_id=?
                ORDER BY m.updated_at DESC, m.id DESC
            ");
            $stmt->execute([$commonUserId]);
            $detail['legacy_mappings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($tableReady['agency_customer_relations']) {
            $stmt = $db->prepare("
                SELECT r.*, a.agent_code, a.agent_name, a.person_name, p.name AS project_name
                FROM agency_customer_relations r
                LEFT JOIN agents a ON r.agent_id=a.id
                LEFT JOIN projects p ON r.project_id=p.id
                WHERE r.common_user_id=?
                ORDER BY r.updated_at DESC, r.id DESC
            ");
            $stmt->execute([$commonUserId]);
            $detail['relations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($tableReady['agent_touchpoints']) {
            $stmt = $db->prepare("
                SELECT t.*, a.agent_code, a.agent_name, a.person_name, p.name AS project_name
                FROM agent_touchpoints t
                LEFT JOIN agents a ON t.agent_id=a.id
                LEFT JOIN projects p ON t.project_id=p.id
                WHERE t.common_user_id=?
                ORDER BY t.occurred_at DESC, t.id DESC
                LIMIT 80
            ");
            $stmt->execute([$commonUserId]);
            $detail['touchpoints'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($tableReady['account_merge_logs']) {
            $stmt = $db->prepare("SELECT * FROM account_merge_logs WHERE from_common_user_id=? ORDER BY created_at DESC, id DESC LIMIT 30");
            $stmt->execute([$commonUserId]);
            $detail['merge_from'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->prepare("SELECT * FROM account_merge_logs WHERE to_common_user_id=? ORDER BY created_at DESC, id DESC LIMIT 30");
            $stmt->execute([$commonUserId]);
            $detail['merge_to'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($tableReady['integration_event_logs']) {
            $stmt = $db->prepare("SELECT * FROM integration_event_logs WHERE common_user_id=? ORDER BY created_at DESC, id DESC LIMIT 30");
            $stmt->execute([$commonUserId]);
            $detail['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = "(u.common_user_id LIKE ? OR u.primary_wallet_address LIKE ?)";
        $kw = '%' . $q . '%';
        array_push($params, $kw, $kw);
        if ($tableReady['system_account_links']) {
            $where[count($where) - 1] = "(" . $where[count($where) - 1] . " OR EXISTS (SELECT 1 FROM system_account_links sx WHERE sx.common_user_id=u.common_user_id AND (sx.external_user_id LIKE ? OR sx.display_name LIKE ? OR sx.wallet_address LIKE ?)))";
            array_push($params, $kw, $kw, $kw);
        }
        if ($tableReady['user_identities']) {
            $where[count($where) - 1] = "(" . $where[count($where) - 1] . " OR EXISTS (SELECT 1 FROM user_identities ix WHERE ix.common_user_id=u.common_user_id AND ix.identity_masked LIKE ?))";
            $params[] = $kw;
        }
    }
    if ($status !== '') {
        $where[] = "u.status = ?";
        $params[] = $status;
    }
    if ($commonUserId !== '') {
        $where[] = "u.common_user_id = ?";
        $params[] = $commonUserId;
    }
    if ($systemKey !== '') {
        $systemClauses = [];
        if ($tableReady['system_account_links']) {
            $systemClauses[] = "EXISTS (SELECT 1 FROM system_account_links sl WHERE sl.common_user_id=u.common_user_id AND sl.system_key=?)";
            $params[] = $systemKey;
        }
        if ($tableReady['service_user_mappings']) {
            $systemClauses[] = "EXISTS (SELECT 1 FROM service_user_mappings sm WHERE sm.common_user_id=u.common_user_id AND sm.service_key=?)";
            $params[] = $systemKey;
        }
        if ($systemClauses) {
            $where[] = '(' . implode(' OR ', $systemClauses) . ')';
        }
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $db->prepare("SELECT COUNT(*) FROM common_users u $whereSql");
    $countStmt->execute($params);
    $pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);

    $selectParts = [
        "u.*",
        $tableReady['user_identities'] ? "(SELECT COUNT(*) FROM user_identities i WHERE i.common_user_id=u.common_user_id) AS identity_count" : "0 AS identity_count",
        $tableReady['system_account_links'] ? "(SELECT COUNT(*) FROM system_account_links l WHERE l.common_user_id=u.common_user_id) AS system_link_count" : "0 AS system_link_count",
        $tableReady['service_user_mappings'] ? "(SELECT COUNT(*) FROM service_user_mappings m WHERE m.common_user_id=u.common_user_id) AS legacy_mapping_count" : "0 AS legacy_mapping_count",
        $tableReady['agency_customer_relations'] ? "(SELECT COUNT(*) FROM agency_customer_relations r WHERE r.common_user_id=u.common_user_id) AS relation_count" : "0 AS relation_count",
        $tableReady['agent_touchpoints'] ? "(SELECT COUNT(*) FROM agent_touchpoints t WHERE t.common_user_id=u.common_user_id) AS touchpoint_count" : "0 AS touchpoint_count",
    ];
    $stmt = $db->prepare("
        SELECT " . implode(",\n               ", $selectParts) . "
        FROM common_users u
        $whereSql
        ORDER BY u.updated_at DESC, u.id DESC
        LIMIT $perPage OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$baseQuery = [
    'q' => $q,
    'status' => $status,
    'system_key' => $systemKey,
    'common_user_id' => $commonUserId,
];
?>

<?php if (!$ready): ?>
<div class="alert alert-error">共通顧客HUBのDBマイグレーションが未適用です。アップデート画面でDBマイグレーションを適用してください。</div>
<?php else: ?>

<div class="card">
    <p class="card-title">共通顧客HUB</p>
    <p style="font-size:.86rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        ショッピングカート、戦国パスポート、LP、外部サイトなどから入ったユーザーを、共通顧客IDで横断確認する画面です。
    </p>
    <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>検索</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="共通ID・外部ID・表示名・メール・電話">
        </div>
        <div class="form-group" style="margin:0;">
            <label>共通顧客ID</label>
            <input type="text" name="common_user_id" value="<?= h($commonUserId) ?>" placeholder="cu_...">
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
            <label>状態</label>
            <select name="status">
                <option value="">すべて</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>active</option>
                <option value="merged" <?= $status === 'merged' ? 'selected' : '' ?>>merged</option>
                <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>disabled</option>
            </select>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-gold">絞り込み</button>
            <a href="/admin/common_hub.php" class="btn btn-outline">リセット</a>
        </div>
    </form>
</div>

<?php if ($commonUserId !== ''): ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem;">
        <p class="card-title" style="margin:0;">顧客詳細</p>
        <a href="/admin/common_id_mappings.php?common_user_id=<?= urlencode($commonUserId) ?>" class="btn btn-outline btn-sm">既存の修正画面へ</a>
    </div>
    <?php if (!$detail['user']): ?>
        <p style="color:var(--text-muted);">指定された共通顧客IDは見つかりません。</p>
    <?php else: ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">共通顧客ID</div><div style="word-break:break-all;"><code><?= h($detail['user']['common_user_id']) ?></code></div></div>
            <div class="stat-card"><div class="stat-label">状態</div><?= hubStatusBadge($detail['user']['status'] ?? 'active') ?></div>
            <div class="stat-card"><div class="stat-label">獲得元</div><div><?= h(($detail['user']['acquisition_channel'] ?? '') ?: '-') ?></div></div>
            <div class="stat-card"><div class="stat-label">最終接点</div><div><?= h(hubDate($detail['user']['last_touch_at'] ?? null)) ?></div></div>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <p class="card-title">ID・連絡先の候補</p>
    <div class="table-scroll"><table>
        <thead><tr><th>種別</th><th>プロバイダ</th><th>表示値</th><th>確認</th><th>信頼度</th><th>元システム</th><th>状態</th><th>最終確認</th></tr></thead>
        <tbody>
        <?php if ($detail['identities']): foreach ($detail['identities'] as $identity): ?>
            <tr>
                <td><?= h($identity['identity_type']) ?></td>
                <td><?= h($identity['provider'] ?: '-') ?></td>
                <td><?= h($identity['identity_masked'] ?: '-') ?></td>
                <td><?= !empty($identity['verified']) ? '済' : '-' ?></td>
                <td><?= h((string)($identity['confidence_score'] ?? '-')) ?></td>
                <td><?= h(trim((string)($identity['source_system_key'] ?? '') . ' / ' . (string)($identity['source_external_user_id'] ?? ''), ' /') ?: '-') ?></td>
                <td><?= hubStatusBadge($identity['status'] ?? 'active') ?></td>
                <td><?= h(hubDate($identity['last_seen_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:1.5rem;">ID候補はまだありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <p class="card-title">外部システムアカウント</p>
    <div class="table-scroll"><table>
        <thead><tr><th>システム</th><th>外部ユーザーID</th><th>表示名</th><th>権限</th><th>担当代理店</th><th>状態</th><th>最終同期</th></tr></thead>
        <tbody>
        <?php if ($detail['system_links']): foreach ($detail['system_links'] as $link): ?>
            <tr>
                <td><?= h($link['system_key']) ?></td>
                <td style="word-break:break-all;"><?= h($link['external_user_id']) ?></td>
                <td><?= h($link['display_name'] ?: '-') ?></td>
                <td><?= h($link['role_name'] ?: '-') ?></td>
                <td><?= h(($link['agent_name'] ?? '') ?: ($link['person_name'] ?? '') ?: '-') ?><?php if (!empty($link['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($link['agent_code']) ?></span><?php endif; ?></td>
                <td><?= hubStatusBadge($link['status'] ?? 'active') ?></td>
                <td><?= h(hubDate($link['last_synced_at'] ?? $link['updated_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:1.5rem;">外部システムアカウントはまだありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <p class="card-title">紹介・代理店関係</p>
    <div class="table-scroll"><table>
        <thead><tr><th>プロジェクト</th><th>代理店</th><th>関係</th><th>元システム</th><th>ロック</th><th>状態</th><th>更新</th></tr></thead>
        <tbody>
        <?php if ($detail['relations']): foreach ($detail['relations'] as $rel): ?>
            <tr>
                <td><?= h($rel['project_name'] ?? ('#' . ($rel['project_id'] ?? '-'))) ?></td>
                <td><?= h(($rel['agent_name'] ?? '') ?: ($rel['person_name'] ?? '') ?: '-') ?><?php if (!empty($rel['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($rel['agent_code']) ?></span><?php endif; ?></td>
                <td><?= h($rel['relation_type'] ?? '-') ?></td>
                <td><?= h(trim((string)($rel['source_service_key'] ?? '') . ' / ' . (string)($rel['source_service_user_id'] ?? ''), ' /') ?: '-') ?></td>
                <td><?= !empty($rel['locked']) ? '固定' : '-' ?></td>
                <td><?= hubStatusBadge($rel['status'] ?? 'active') ?></td>
                <td><?= h(hubDate($rel['updated_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:1.5rem;">紹介・代理店関係はまだありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <p class="card-title">接点履歴</p>
    <div class="table-scroll"><table>
        <thead><tr><th>日時</th><th>種別</th><th>プロジェクト</th><th>代理店</th><th>元システム</th><th>LP / 流入URL</th><th>確定</th></tr></thead>
        <tbody>
        <?php if ($detail['touchpoints']): foreach ($detail['touchpoints'] as $tp): ?>
            <tr>
                <td><?= h(hubDate($tp['occurred_at'] ?? null)) ?></td>
                <td><?= h($tp['touchpoint_type'] ?? '-') ?></td>
                <td><?= h($tp['project_name'] ?? ('#' . ($tp['project_id'] ?? '-'))) ?></td>
                <td><?= h(($tp['agent_name'] ?? '') ?: ($tp['person_name'] ?? '') ?: '-') ?><?php if (!empty($tp['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($tp['agent_code']) ?></span><?php endif; ?></td>
                <td><?= h(trim((string)($tp['source_system_key'] ?? '') . ' / ' . (string)($tp['source_external_user_id'] ?? ''), ' /') ?: '-') ?></td>
                <td style="word-break:break-all;"><?= h(hubShort(trim((string)($tp['landing_url'] ?? '') . ' / ' . (string)($tp['source_url'] ?? ''), ' /'), 120)) ?></td>
                <td><?= h(hubDate($tp['confirmed_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:1.5rem;">接点履歴はまだありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php if ($detail['merge_from'] || $detail['merge_to'] || $detail['logs']): ?>
<div class="card">
    <p class="card-title">統合・連携ログ</p>
    <div class="table-scroll"><table>
        <thead><tr><th>日時</th><th>種別</th><th>内容</th><th>結果</th></tr></thead>
        <tbody>
        <?php foreach ($detail['merge_from'] as $log): ?>
            <tr><td><?= h(hubDate($log['created_at'] ?? null)) ?></td><td>統合元</td><td><?= h($log['from_common_user_id'] . ' -> ' . $log['to_common_user_id']) ?></td><td><?= hubStatusBadge($log['status'] ?? 'completed') ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($detail['merge_to'] as $log): ?>
            <tr><td><?= h(hubDate($log['created_at'] ?? null)) ?></td><td>統合先</td><td><?= h($log['from_common_user_id'] . ' -> ' . $log['to_common_user_id']) ?></td><td><?= hubStatusBadge($log['status'] ?? 'completed') ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($detail['logs'] as $log): ?>
            <tr><td><?= h(hubDate($log['created_at'] ?? null)) ?></td><td><?= h($log['direction'] ?? '-') ?></td><td><?= h(($log['site_key'] ?? '-') . ' / ' . ($log['event_type'] ?? '-')) ?></td><td><?= !empty($log['success']) ? 'success' : h(hubShort($log['error_message'] ?? 'failed', 80)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">共通顧客一覧</p>
        <span style="font-size:.78rem;color:var(--text-muted);">全 <?= number_format((int)$pag['total']) ?> 件</span>
    </div>
    <div class="table-scroll"><table>
        <thead><tr><th>共通顧客ID</th><th>状態</th><th>ID候補</th><th>外部アカウント</th><th>旧紐づけ</th><th>代理店関係</th><th>接点</th><th>獲得元</th><th>最終更新</th><th>操作</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $row): ?>
            <tr>
                <td><code><?= h($row['common_user_id']) ?></code></td>
                <td><?= hubStatusBadge($row['status'] ?? 'active') ?></td>
                <td><?= number_format((int)$row['identity_count']) ?></td>
                <td><?= number_format((int)$row['system_link_count']) ?></td>
                <td><?= number_format((int)$row['legacy_mapping_count']) ?></td>
                <td><?= number_format((int)$row['relation_count']) ?></td>
                <td><?= number_format((int)$row['touchpoint_count']) ?></td>
                <td><?= h(($row['acquisition_channel'] ?? '') ?: '-') ?></td>
                <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(hubDate($row['updated_at'] ?? null)) ?></td>
                <td><a class="btn btn-outline btn-sm" href="/admin/common_hub.php?common_user_id=<?= urlencode((string)$row['common_user_id']) ?>">詳細</a></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:2.5rem;">該当する共通顧客はありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
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

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

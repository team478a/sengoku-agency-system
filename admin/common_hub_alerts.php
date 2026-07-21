<?php
$pageTitle = '共通HUB確認';
require_once __DIR__ . '/header.php';

$db = getDB();
$tables = [
    'common_users',
    'system_account_links',
    'service_user_mappings',
    'user_identities',
    'agency_customer_relations',
    'agent_touchpoints',
];
$ready = [];
foreach ($tables as $table) {
    $ready[$table] = !empty(tableColumns($table));
}
$hubReady = $ready['common_users'];

function hubAlertDate(?string $date): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date('Y/m/d H:i', $ts) : $date;
}

function hubAlertLink(string $commonUserId): string {
    if ($commonUserId === '') return '-';
    return '<a href="/admin/common_hub.php?common_user_id=' . urlencode($commonUserId) . '" style="color:var(--gold);"><code>' . h($commonUserId) . '</code></a>';
}

function hubAlertCsv(?string $value, int $limit = 140): string {
    $value = trim((string)$value);
    if ($value === '') return '-';
    if (strlen($value) <= $limit) return $value;
    return substr($value, 0, $limit) . '...';
}

$duplicateEmail = [];
$duplicatePhone = [];
$duplicateWallet = [];
$unassignedLinks = [];
$relationConflicts = [];
$orphanUsers = [];
$openTouchpoints = [];
$stats = [
    'duplicate_email' => 0,
    'duplicate_phone' => 0,
    'duplicate_wallet' => 0,
    'unassigned_links' => 0,
    'relation_conflicts' => 0,
    'orphan_users' => 0,
    'open_touchpoints' => 0,
];

if ($hubReady) {
    if ($ready['system_account_links']) {
        $duplicateEmail = $db->query("
            SELECT email_hash, COUNT(DISTINCT common_user_id) AS user_count, GROUP_CONCAT(DISTINCT common_user_id ORDER BY common_user_id SEPARATOR ', ') AS common_user_ids, MAX(updated_at) AS last_seen
            FROM system_account_links
            WHERE email_hash IS NOT NULL AND email_hash<>''
            GROUP BY email_hash
            HAVING COUNT(DISTINCT common_user_id) > 1
            ORDER BY last_seen DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);
        $duplicatePhone = $db->query("
            SELECT phone_hash, COUNT(DISTINCT common_user_id) AS user_count, GROUP_CONCAT(DISTINCT common_user_id ORDER BY common_user_id SEPARATOR ', ') AS common_user_ids, MAX(updated_at) AS last_seen
            FROM system_account_links
            WHERE phone_hash IS NOT NULL AND phone_hash<>''
            GROUP BY phone_hash
            HAVING COUNT(DISTINCT common_user_id) > 1
            ORDER BY last_seen DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);
        $duplicateWallet = $db->query("
            SELECT wallet_address, COUNT(DISTINCT common_user_id) AS user_count, GROUP_CONCAT(DISTINCT common_user_id ORDER BY common_user_id SEPARATOR ', ') AS common_user_ids, MAX(updated_at) AS last_seen
            FROM system_account_links
            WHERE wallet_address IS NOT NULL AND wallet_address<>''
            GROUP BY wallet_address
            HAVING COUNT(DISTINCT common_user_id) > 1
            ORDER BY last_seen DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);
        $unassignedLinks = $db->query("
            SELECT l.*, a.agent_code, a.agent_name, a.person_name
            FROM system_account_links l
            LEFT JOIN agents a ON l.agent_id=a.id
            WHERE l.status='active' AND l.agent_id IS NULL
            ORDER BY l.updated_at DESC, l.id DESC
            LIMIT 80
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stats['duplicate_email'] = count($duplicateEmail);
        $stats['duplicate_phone'] = count($duplicatePhone);
        $stats['duplicate_wallet'] = count($duplicateWallet);
        $stats['unassigned_links'] = count($unassignedLinks);
    }

    if ($ready['agency_customer_relations']) {
        $relationConflicts = $db->query("
            SELECT r.common_user_id, r.project_id, p.name AS project_name, COUNT(DISTINCT r.agent_id) AS agent_count,
                   GROUP_CONCAT(DISTINCT COALESCE(a.agent_code, r.agent_id) ORDER BY a.agent_code SEPARATOR ', ') AS agents,
                   MAX(r.updated_at) AS last_seen
            FROM agency_customer_relations r
            LEFT JOIN agents a ON r.agent_id=a.id
            LEFT JOIN projects p ON r.project_id=p.id
            WHERE r.status='active' AND r.agent_id IS NOT NULL
            GROUP BY r.common_user_id, r.project_id
            HAVING COUNT(DISTINCT r.agent_id) > 1
            ORDER BY last_seen DESC
            LIMIT 80
        ")->fetchAll(PDO::FETCH_ASSOC);
        $stats['relation_conflicts'] = count($relationConflicts);
    }

    $orphanWhere = [];
    if ($ready['system_account_links']) {
        $orphanWhere[] = "NOT EXISTS (SELECT 1 FROM system_account_links l WHERE l.common_user_id=u.common_user_id)";
    }
    if ($ready['service_user_mappings']) {
        $orphanWhere[] = "NOT EXISTS (SELECT 1 FROM service_user_mappings m WHERE m.common_user_id=u.common_user_id)";
    }
    if ($ready['user_identities']) {
        $orphanWhere[] = "NOT EXISTS (SELECT 1 FROM user_identities i WHERE i.common_user_id=u.common_user_id)";
    }
    if ($orphanWhere) {
        $orphanUsers = $db->query("
            SELECT u.*
            FROM common_users u
            WHERE u.status='active' AND " . implode(' AND ', $orphanWhere) . "
            ORDER BY u.updated_at DESC, u.id DESC
            LIMIT 80
        ")->fetchAll(PDO::FETCH_ASSOC);
        $stats['orphan_users'] = count($orphanUsers);
    }

    if ($ready['agent_touchpoints']) {
        $openTouchpoints = $db->query("
            SELECT t.*, a.agent_code, a.agent_name, a.person_name, p.name AS project_name
            FROM agent_touchpoints t
            LEFT JOIN agents a ON t.agent_id=a.id
            LEFT JOIN projects p ON t.project_id=p.id
            WHERE t.confirmed_at IS NULL
            ORDER BY t.occurred_at DESC, t.id DESC
            LIMIT 80
        ")->fetchAll(PDO::FETCH_ASSOC);
        $stats['open_touchpoints'] = count($openTouchpoints);
    }
}
?>

<?php if (!$hubReady): ?>
<div class="alert alert-error">共通顧客HUBのDBマイグレーションが未適用です。アップデート画面でDBマイグレーションを適用してください。</div>
<?php else: ?>

<div class="card">
    <p class="card-title">共通HUB確認</p>
    <p style="font-size:.86rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        共通顧客HUBの運用で確認が必要になりやすいデータをまとめています。ここでは削除や統合は行わず、詳細画面で状況確認してから修正します。
    </p>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
        <a href="/admin/common_hub_fix.php" class="btn btn-gold">HUB修正へ</a>
        <a href="/admin/common_hub.php" class="btn btn-outline">共通顧客HUBへ</a>
        <a href="/admin/export_csv.php?type=common_hub_alerts&alert=all" class="btn btn-outline">CSV出力</a>
    </div>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">メール重複候補</div><div class="stat-val"><?= number_format($stats['duplicate_email']) ?></div></div>
        <div class="stat-card"><div class="stat-label">電話重複候補</div><div class="stat-val"><?= number_format($stats['duplicate_phone']) ?></div></div>
        <div class="stat-card"><div class="stat-label">ウォレット重複候補</div><div class="stat-val"><?= number_format($stats['duplicate_wallet']) ?></div></div>
        <div class="stat-card"><div class="stat-label">担当未設定</div><div class="stat-val"><?= number_format($stats['unassigned_links']) ?></div></div>
        <div class="stat-card"><div class="stat-label">担当矛盾</div><div class="stat-val"><?= number_format($stats['relation_conflicts']) ?></div></div>
        <div class="stat-card"><div class="stat-label">未確定接点</div><div class="stat-val"><?= number_format($stats['open_touchpoints']) ?></div></div>
    </div>
</div>

<div class="card">
    <p class="card-title">重複候補</p>
    <div class="table-scroll"><table>
        <thead><tr><th>種別</th><th>件数</th><th>共通顧客ID</th><th>最終更新</th></tr></thead>
        <tbody>
        <?php foreach ([['メール', $duplicateEmail], ['電話', $duplicatePhone], ['ウォレット', $duplicateWallet]] as [$label, $items]): ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($label) ?></td>
                    <td><?= number_format((int)$item['user_count']) ?></td>
                    <td style="word-break:break-all;"><?= h(hubAlertCsv($item['common_user_ids'] ?? '')) ?></td>
                    <td><?= h(hubAlertDate($item['last_seen'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (!$duplicateEmail && !$duplicatePhone && !$duplicateWallet): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:1.5rem;">重複候補はありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <p class="card-title">担当未設定の外部アカウント</p>
    <div class="table-scroll"><table>
        <thead><tr><th>共通顧客ID</th><th>システム</th><th>外部ユーザーID</th><th>表示名</th><th>状態</th><th>更新</th></tr></thead>
        <tbody>
        <?php if ($unassignedLinks): foreach ($unassignedLinks as $link): ?>
            <tr>
                <td><?= hubAlertLink((string)$link['common_user_id']) ?></td>
                <td><?= h($link['system_key'] ?? '-') ?></td>
                <td style="word-break:break-all;"><?= h($link['external_user_id'] ?? '-') ?></td>
                <td><?= h($link['display_name'] ?: '-') ?></td>
                <td><?= h($link['status'] ?? '-') ?></td>
                <td><?= h(hubAlertDate($link['updated_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:1.5rem;">担当未設定の外部アカウントはありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <p class="card-title">担当代理店の矛盾候補</p>
    <div class="table-scroll"><table>
        <thead><tr><th>共通顧客ID</th><th>プロジェクト</th><th>代理店数</th><th>代理店</th><th>最終更新</th></tr></thead>
        <tbody>
        <?php if ($relationConflicts): foreach ($relationConflicts as $row): ?>
            <tr>
                <td><?= hubAlertLink((string)$row['common_user_id']) ?></td>
                <td><?= h($row['project_name'] ?? ('#' . ($row['project_id'] ?? '-'))) ?></td>
                <td><?= number_format((int)$row['agent_count']) ?></td>
                <td><?= h($row['agents'] ?: '-') ?></td>
                <td><?= h(hubAlertDate($row['last_seen'] ?? null)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem;">担当代理店の矛盾候補はありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <p class="card-title">未確定の接点履歴</p>
    <div class="table-scroll"><table>
        <thead><tr><th>日時</th><th>共通顧客ID</th><th>種別</th><th>プロジェクト</th><th>代理店</th><th>元システム</th><th>LP</th></tr></thead>
        <tbody>
        <?php if ($openTouchpoints): foreach ($openTouchpoints as $tp): ?>
            <tr>
                <td><?= h(hubAlertDate($tp['occurred_at'] ?? null)) ?></td>
                <td><?= hubAlertLink((string)($tp['common_user_id'] ?? '')) ?></td>
                <td><?= h($tp['touchpoint_type'] ?? '-') ?></td>
                <td><?= h($tp['project_name'] ?? ('#' . ($tp['project_id'] ?? '-'))) ?></td>
                <td><?= h(($tp['agent_name'] ?? '') ?: ($tp['person_name'] ?? '') ?: '-') ?><?php if (!empty($tp['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($tp['agent_code']) ?></span><?php endif; ?></td>
                <td><?= h(trim((string)($tp['source_system_key'] ?? '') . ' / ' . (string)($tp['source_external_user_id'] ?? ''), ' /') ?: '-') ?></td>
                <td style="word-break:break-all;"><?= h(hubAlertCsv($tp['landing_url'] ?? '', 100)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:1.5rem;">未確定の接点履歴はありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <p class="card-title">孤立した共通顧客ID</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.7;margin-bottom:1rem;">
        外部アカウント、旧紐づけ、ID候補のいずれにも紐づいていない共通顧客IDです。テストデータや途中失敗の可能性があります。
    </p>
    <div class="table-scroll"><table>
        <thead><tr><th>共通顧客ID</th><th>獲得元</th><th>状態</th><th>作成</th><th>更新</th></tr></thead>
        <tbody>
        <?php if ($orphanUsers): foreach ($orphanUsers as $user): ?>
            <tr>
                <td><?= hubAlertLink((string)$user['common_user_id']) ?></td>
                <td><?= h(($user['acquisition_channel'] ?? '') ?: '-') ?></td>
                <td><?= h($user['status'] ?? '-') ?></td>
                <td><?= h(hubAlertDate($user['created_at'] ?? null)) ?></td>
                <td><?= h(hubAlertDate($user['updated_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem;">孤立した共通顧客IDはありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

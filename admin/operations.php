<?php
$pageTitle = '運用チェック';
require_once __DIR__ . '/header.php';

$db = getDB();
$now = date('Y-m-d H:i:s');

function opsTableReady(string $table): bool {
    return !empty(tableColumns($table));
}

function opsCount(string $sql, array $params = []): int {
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function opsRows(string $sql, array $params = []): array {
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function opsDate(?string $value): string {
    if (!$value) return '-';
    $ts = strtotime($value);
    return $ts ? date('Y/m/d H:i', $ts) : '-';
}

function opsStatusBadge(string $label, string $type = 'ok'): string {
    $styles = [
        'ok' => 'background:rgba(44,143,99,.16);color:#2c8f63;',
        'warn' => 'background:rgba(201,168,76,.18);color:#8B6914;',
        'ng' => 'background:rgba(180,55,55,.16);color:#b43737;',
    ];
    return '<span style="display:inline-block;padding:.25rem .6rem;border-radius:999px;font-weight:700;font-size:.78rem;' . ($styles[$type] ?? $styles['ok']) . '">' . h($label) . '</span>';
}

$hasPartners = opsTableReady('external_partner_sites');
$hasIntegrationLogs = opsTableReady('integration_event_logs');
$hasOutbox = opsTableReady('integration_outbox_events');
$hasAgents = opsTableReady('agents');
$hasLeads = opsTableReady('leads');
$hasLoginLogs = opsTableReady('login_logs');
$hasAccessLogs = opsTableReady('access_logs');

$partnerHealth = [];
if ($hasPartners) {
    if ($hasIntegrationLogs) {
        $partnerHealth = opsRows("\n            SELECT\n                s.site_key, s.name, s.status, s.endpoint_url,\n                MAX(CASE WHEN l.success=1 THEN l.created_at ELSE NULL END) AS last_success_at,\n                MAX(CASE WHEN l.success=0 THEN l.created_at ELSE NULL END) AS last_failed_at,\n                SUM(CASE WHEN l.success=0 THEN 1 ELSE 0 END) AS failed_count,\n                SUM(CASE WHEN l.direction='outbound' AND l.success=0 THEN 1 ELSE 0 END) AS retryable_count,\n                SUM(CASE WHEN l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND l.success=0 THEN 1 ELSE 0 END) AS failed_24h\n            FROM external_partner_sites s\n            LEFT JOIN integration_event_logs l ON l.site_key=s.site_key\n            GROUP BY s.id, s.site_key, s.name, s.status, s.endpoint_url\n            ORDER BY failed_24h DESC, retryable_count DESC, s.sort_order ASC, s.id ASC\n        ");
    } else {
        $partnerHealth = opsRows("SELECT site_key, name, status, endpoint_url FROM external_partner_sites ORDER BY sort_order ASC, id ASC");
    }
}

$failedOutbound = $hasIntegrationLogs ? opsCount("SELECT COUNT(*) FROM integration_event_logs WHERE direction='outbound' AND success=0") : 0;
$failedOutbound24h = $hasIntegrationLogs ? opsCount("SELECT COUNT(*) FROM integration_event_logs WHERE direction='outbound' AND success=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)") : 0;
$lastIntegrationEvent = $hasIntegrationLogs ? (opsRows("SELECT MAX(created_at) AS last_at FROM integration_event_logs")[0]['last_at'] ?? null) : null;
$outboxPending = $hasOutbox ? opsCount("SELECT COUNT(*) FROM integration_outbox_events WHERE status IN ('pending','failed')") : 0;
$outboxDue = $hasOutbox ? opsCount("SELECT COUNT(*) FROM integration_outbox_events WHERE status IN ('pending','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())") : 0;
$outboxDlq = $hasOutbox ? opsCount("SELECT COUNT(*) FROM integration_outbox_events WHERE status='dlq'") : 0;

$inactiveAgents = $hasAgents ? opsRows("\n    SELECT a.id, a.agent_code, a.agent_name, a.person_name, a.email, MAX(l.created_at) AS last_login\n    FROM agents a\n    LEFT JOIN login_logs l ON l.user_type='agent' AND l.user_id=a.id AND l.success=1\n    WHERE a.status='active'\n    GROUP BY a.id, a.agent_code, a.agent_name, a.person_name, a.email\n    HAVING last_login IS NULL OR last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)\n    ORDER BY last_login IS NULL DESC, last_login ASC\n    LIMIT 20\n") : [];

$unhandledLeads = $hasLeads ? opsRows("\n    SELECT l.id, l.name, l.email, l.status, l.created_at, a.agent_name, a.agent_code\n    FROM leads l\n    LEFT JOIN agents a ON a.id=l.agent_id\n    WHERE l.status IN ('new','unhandled','pending') OR (l.status IS NULL OR l.status='')\n    ORDER BY l.created_at ASC\n    LIMIT 20\n") : [];

$staleLeads = $hasLeads ? opsCount("\n    SELECT COUNT(*) FROM leads\n    WHERE (status IN ('new','unhandled','pending') OR status IS NULL OR status='')\n      AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)\n") : 0;

$failedLogins24h = $hasLoginLogs ? opsCount("SELECT COUNT(*) FROM login_logs WHERE success=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)") : 0;
$activeAgents = $hasAgents ? opsCount("SELECT COUNT(*) FROM agents WHERE status='active'") : 0;
$totalAgents = $hasAgents ? opsCount("SELECT COUNT(*) FROM agents") : 0;
$pv24h = $hasAccessLogs ? opsCount("SELECT COUNT(*) FROM access_logs WHERE type='pv' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)") : 0;
$leads24h = $hasLeads ? opsCount("SELECT COUNT(*) FROM leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)") : 0;

$backupDir = dirname(__DIR__) . '/backups';
$backupCount = 0;
$lastBackup = null;
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*') ?: [];
    $backupCount = count($files);
    foreach ($files as $file) {
        $mtime = @filemtime($file);
        if ($mtime && (!$lastBackup || $mtime > $lastBackup)) {
            $lastBackup = $mtime;
        }
    }
}

$criticalCount = 0;
if ($failedOutbound24h > 0) $criticalCount++;
if ($outboxDlq > 0) $criticalCount++;
if ($staleLeads > 0) $criticalCount++;
if ($failedLogins24h >= 10) $criticalCount++;
$warnCount = count($inactiveAgents) + ($backupCount === 0 ? 1 : 0);
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-sub">外部連携Outbox</div>
        <div class="stat-val"><?= number_format($outboxPending) ?></div>
        <div class="stat-sub">要確認 <?= number_format($outboxDlq) ?> 件 / 今すぐ再送 <?= number_format($outboxDue) ?> 件</div>
    </div>
    <div class="stat-card">
        <div class="stat-sub">要確認</div>
        <div class="stat-val"><?= number_format($criticalCount) ?></div>
        <div class="stat-sub">連携失敗・未対応・ログイン失敗</div>
    </div>
    <div class="stat-card">
        <div class="stat-sub">外部送信失敗 24h</div>
        <div class="stat-val"><?= number_format($failedOutbound24h) ?></div>
        <div class="stat-sub">全失敗 <?= number_format($failedOutbound) ?> 件</div>
    </div>
    <div class="stat-card">
        <div class="stat-sub">未対応問い合わせ</div>
        <div class="stat-val"><?= number_format(count($unhandledLeads)) ?></div>
        <div class="stat-sub">3日超過 <?= number_format($staleLeads) ?> 件</div>
    </div>
    <div class="stat-card">
        <div class="stat-sub">代理店</div>
        <div class="stat-val"><?= number_format($activeAgents) ?></div>
        <div class="stat-sub">総数 <?= number_format($totalAgents) ?> 件</div>
    </div>
</div>

<div class="card">
    <p class="card-title">外部連携ヘルスチェック</p>
    <p style="color:var(--text-muted);margin-top:-.25rem;">連携先ごとの最終成功、失敗、再送対象を確認します。</p>
    <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>状態</th><th>連携先</th><th>サイトキー</th><th>最終成功</th><th>最終失敗</th><th>失敗24h</th><th>再送対象</th><th>操作</th></tr></thead>
            <tbody>
            <?php if ($partnerHealth): foreach ($partnerHealth as $p): ?>
                <?php
                $failed24 = (int)($p['failed_24h'] ?? 0);
                $retryable = (int)($p['retryable_count'] ?? 0);
                $badge = $failed24 > 0 ? opsStatusBadge('要確認', 'ng') : ($retryable > 0 ? opsStatusBadge('再送待ち', 'warn') : opsStatusBadge('正常', 'ok'));
                ?>
                <tr>
                    <td><?= $badge ?></td>
                    <td><strong><?= h($p['name'] ?? '') ?></strong></td>
                    <td><?= h($p['site_key'] ?? '') ?></td>
                    <td><?= opsDate($p['last_success_at'] ?? null) ?></td>
                    <td><?= opsDate($p['last_failed_at'] ?? null) ?></td>
                    <td><?= number_format($failed24) ?></td>
                    <td><?= number_format($retryable) ?></td>
                    <td><a class="btn btn-outline btn-sm" href="/admin/integration_logs.php?site_key=<?= urlencode((string)($p['site_key'] ?? '')) ?>">ログ</a></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" style="color:var(--text-muted);">外部連携先はまだ登録されていません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">代理店活動アラート</p>
    <div class="stats-grid" style="margin-top:1rem;">
        <div class="stat-card"><div class="stat-sub">PV 24h</div><div class="stat-val"><?= number_format($pv24h) ?></div></div>
        <div class="stat-card"><div class="stat-sub">問い合わせ 24h</div><div class="stat-val"><?= number_format($leads24h) ?></div></div>
        <div class="stat-card"><div class="stat-sub">ログイン失敗 24h</div><div class="stat-val"><?= number_format($failedLogins24h) ?></div></div>
    </div>
    <p class="card-title" style="margin-top:1.25rem;">30日以上ログインがない代理店</p>
    <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>コード</th><th>代理店名</th><th>担当者</th><th>メール</th><th>最終ログイン</th></tr></thead>
            <tbody>
            <?php if ($inactiveAgents): foreach ($inactiveAgents as $a): ?>
                <tr>
                    <td><?= h($a['agent_code'] ?? '') ?></td>
                    <td><strong><?= h($a['agent_name'] ?? '') ?></strong></td>
                    <td><?= h($a['person_name'] ?? '') ?></td>
                    <td><?= h($a['email'] ?? '') ?></td>
                    <td><?= opsDate($a['last_login'] ?? null) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" style="color:var(--text-muted);">対象はありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">問い合わせ対応チェック</p>
    <p style="color:var(--text-muted);margin-top:-.25rem;">未対応または保留状態の問い合わせを古い順に表示します。</p>
    <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>日時</th><th>名前</th><th>メール</th><th>担当代理店</th><th>状態</th><th>操作</th></tr></thead>
            <tbody>
            <?php if ($unhandledLeads): foreach ($unhandledLeads as $lead): ?>
                <tr>
                    <td><?= opsDate($lead['created_at'] ?? null) ?></td>
                    <td><strong><?= h($lead['name'] ?? '') ?></strong></td>
                    <td><?= h($lead['email'] ?? '') ?></td>
                    <td><?= h(($lead['agent_name'] ?? '') . ' ' . ($lead['agent_code'] ?? '')) ?></td>
                    <td><?= h($lead['status'] ?? '') ?></td>
                    <td><a class="btn btn-outline btn-sm" href="/admin/leads.php?id=<?= (int)($lead['id'] ?? 0) ?>">詳細</a></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="color:var(--text-muted);">未対応の問い合わせはありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">バックアップ・復旧チェック</p>
    <div class="stats-grid" style="margin-top:1rem;">
        <div class="stat-card"><div class="stat-sub">バックアップ数</div><div class="stat-val"><?= number_format($backupCount) ?></div></div>
        <div class="stat-card"><div class="stat-sub">最終バックアップ</div><div style="font-weight:700;margin-top:.5rem;"><?= $lastBackup ? h(date('Y/m/d H:i', $lastBackup)) : '-' ?></div></div>
    </div>
    <p style="color:var(--text-muted);">復旧が必要な場合は、まずサーバーの `backups/` とDBバックアップの有無を確認し、作業前に現在の `config/database.php` を退避してください。</p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;">
        <a class="btn btn-outline" href="/admin/update.php">アップデート画面</a>
        <a class="btn btn-outline" href="/admin/action_logs.php">操作ログ</a>
        <a class="btn btn-outline" href="/admin/login_logs.php">ログイン記録</a>
    </div>
</div>

<div class="card">
    <p class="card-title">毎日の運用チェックリスト</p>
    <ol style="line-height:1.9;margin-left:1.25rem;">
        <li>外部連携ヘルスチェックで「要確認」または「再送待ち」がないか確認する。</li>
        <li>未対応問い合わせと3日超過の問い合わせを確認する。</li>
        <li>ログイン失敗が急増していないか確認する。</li>
        <li>30日以上ログインがない代理店に連絡またはフォローを行う。</li>
        <li>アップデート前後はバックアップ数と最終バックアップ日時を確認する。</li>
    </ol>
</div>

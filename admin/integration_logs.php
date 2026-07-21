<?php
$pageTitle = '外部連携ログ';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$hasLogsTable = !empty(tableColumns('integration_event_logs'));
$message = '';
$error = '';

function integrationLogShort(?string $value, int $length = 120): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
        return mb_substr($value, 0, $length, 'UTF-8') . '...';
    }
    return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
}

if ($hasLogsTable && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '操作トークンが無効です。ページを再読み込みしてからもう一度お試しください。';
    } elseif (($_POST['action'] ?? '') === 'regenerate_retry_cron_token') {
        try {
            $token = 'eir_' . bin2hex(random_bytes(24));
            saveSystemSettingValue('external_integration_retry_cron_token', $token);
            $message = '自動再送URLを発行しました。';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif (($_POST['action'] ?? '') === 'retry_log') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("SELECT * FROM integration_event_logs WHERE id=? AND direction='outbound' LIMIT 1");
            $stmt->execute([$id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$log) {
                throw new RuntimeException('再送対象のログが見つかりません。');
            }
            $result = retryExternalIntegrationLogRow($log);

            if (!empty($result['ok'])) {
                $message = '再送に成功しました。HTTP ' . (int)($result['status'] ?? 0);
            } else {
                $error = '再送に失敗しました。HTTP ' . (int)($result['status'] ?? 0) . ' ' . (string)($result['error'] ?? '');
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif (($_POST['action'] ?? '') === 'retry_failed_batch') {
        $targetSiteKey = trim((string)($_POST['site_key'] ?? ''));
        $limit = min(50, max(1, (int)($_POST['limit'] ?? 10)));
        try {
            $summary = retryFailedExternalIntegrationLogs($targetSiteKey, $limit);
            if ((int)$summary['target_count'] === 0) {
                throw new RuntimeException('再送対象の失敗ログはありません。');
            }
            $message = '一括再送が完了しました。成功: ' . (int)$summary['success_count'] . ' / 失敗: ' . (int)$summary['failed_count'];
            if (!empty($summary['errors'])) {
                $error = implode("\n", array_slice($summary['errors'], 0, 5));
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$direction = sanitizeInput($_GET['direction'] ?? '');
$success = sanitizeInput($_GET['success'] ?? '');
$siteKey = sanitizeInput($_GET['site_key'] ?? '');
$eventType = sanitizeInput($_GET['event_type'] ?? '');
$status = sanitizeInput($_GET['http_status'] ?? '');
$q = sanitizeInput($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$logs = [];
$partnerStats = [];
$pag = paginate(0, $perPage, $page);
$siteOptions = $hasLogsTable ? $db->query("SELECT DISTINCT site_key FROM integration_event_logs WHERE site_key IS NOT NULL AND site_key<>'' ORDER BY site_key")->fetchAll(PDO::FETCH_COLUMN) : [];
$eventOptions = $hasLogsTable ? $db->query("SELECT DISTINCT event_type FROM integration_event_logs WHERE event_type IS NOT NULL AND event_type<>'' ORDER BY event_type")->fetchAll(PDO::FETCH_COLUMN) : [];

if ($hasLogsTable) {
    if (!empty(tableColumns('external_partner_sites'))) {
        $partnerStats = $db->query("
            SELECT
                s.site_key,
                s.name,
                s.status,
                MAX(l.created_at) AS last_event_at,
                SUM(CASE WHEN l.success=1 THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN l.success=0 THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN l.direction='outbound' AND l.success=0 THEN 1 ELSE 0 END) AS retryable_count
            FROM external_partner_sites s
            LEFT JOIN integration_event_logs l ON l.site_key = s.site_key
            GROUP BY s.id, s.site_key, s.name, s.status
            ORDER BY s.sort_order ASC, s.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $partnerStats = $db->query("
            SELECT
                site_key,
                site_key AS name,
                'active' AS status,
                MAX(created_at) AS last_event_at,
                SUM(CASE WHEN success=1 THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN success=0 THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN direction='outbound' AND success=0 THEN 1 ELSE 0 END) AS retryable_count
            FROM integration_event_logs
            WHERE site_key IS NOT NULL AND site_key<>''
            GROUP BY site_key
            ORDER BY site_key ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $where = [];
    $params = [];

    if (in_array($direction, ['inbound', 'outbound'], true)) {
        $where[] = 'l.direction = ?';
        $params[] = $direction;
    }
    if ($success === '1' || $success === '0') {
        $where[] = 'l.success = ?';
        $params[] = (int)$success;
    }
    if ($siteKey !== '') {
        $where[] = 'l.site_key = ?';
        $params[] = $siteKey;
    }
    if ($eventType !== '') {
        $where[] = 'l.event_type = ?';
        $params[] = $eventType;
    }
    if ($status !== '' && ctype_digit($status)) {
        $where[] = 'l.http_status = ?';
        $params[] = (int)$status;
    }
    if ($q !== '') {
        $where[] = '(l.endpoint LIKE ? OR l.common_user_id LIKE ? OR l.error_message LIKE ? OR l.request_body LIKE ? OR l.response_body LIKE ? OR a.agent_code LIKE ? OR a.agent_name LIKE ? OR a.person_name LIKE ?)';
        $kw = '%' . $q . '%';
        array_push($params, $kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM integration_event_logs l
        LEFT JOIN agents a ON l.agent_id = a.id
        $whereSql
    ");
    $countStmt->execute($params);
    $pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);

    $stmt = $db->prepare("
        SELECT l.*, a.agent_code, a.agent_name, a.person_name
        FROM integration_event_logs l
        LEFT JOIN agents a ON l.agent_id = a.id
        $whereSql
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT $perPage OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$baseQuery = [
    'direction' => $direction,
    'success' => $success,
    'site_key' => $siteKey,
    'event_type' => $eventType,
    'http_status' => $status,
    'q' => $q,
];
$retryCronToken = getSystemSettingValue('external_integration_retry_cron_token', '');
$retryCronUrl = $retryCronToken !== ''
    ? getSiteBaseUrl() . '/cron/external_integration_retry.php?token=' . rawurlencode($retryCronToken) . '&limit=10&notify=1'
    : '';
?>

<?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

<?php if (!$hasLogsTable): ?>
<div class="alert alert-error">螟夜Κ騾｣謳ｺ繝ｭ繧ｰ縺ｮDB繝槭う繧ｰ繝ｬ繝ｼ繧ｷ繝ｧ繝ｳ縺梧悴驕ｩ逕ｨ縺ｧ縺吶ゅい繝・・繝・・繝育判髱｢縺九ｉDB繝槭う繧ｰ繝ｬ繝ｼ繧ｷ繝ｧ繝ｳ繧帝←逕ｨ縺励※縺上□縺輔＞縲・/div>
<?php else: ?>
<div class="card">
    <p class="card-title">自動再送URL</p>
    <p style="color:var(--text-muted);margin-top:-.25rem;">サーバーのcronからこのURLを定期実行すると、失敗した外部送信ログを最大10件ずつ再送できます。</p>
    <?php if ($retryCronUrl !== ''): ?>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <input type="text" value="<?= h($retryCronUrl) ?>" readonly style="flex:1;min-width:260px;">
            <a class="btn btn-outline" href="<?= h($retryCronUrl) ?>" target="_blank" rel="noopener">実行テスト</a>
        </div>
        <p style="color:var(--text-muted);font-size:.82rem;margin-top:.6rem;">通知は管理者メール（admin_email）へ送信されます。失敗が残った場合のみ通知します。</p>
    <?php else: ?>
        <div class="alert alert-error">自動再送URLはまだ発行されていません。</div>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('自動再送URLのトークンを再発行します。既存のcron設定URLは使えなくなります。よろしいですか？')" style="margin-top:1rem;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="regenerate_retry_cron_token">
        <button type="submit" class="btn btn-gold"><?= $retryCronUrl !== '' ? '自動再送URLを再発行' : '自動再送URLを発行' ?></button>
    </form>
</div>
<div class="card">
    <p class="card-title">外部連携の状態</p>
    <p style="color:var(--text-muted);margin-top:-.25rem;">連携先ごとの送信結果と、再送が必要な失敗ログを確認できます。</p>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>連携先</th>
                    <th>状態</th>
                    <th>最終ログ</th>
                    <th>成功</th>
                    <th>失敗</th>
                    <th>再送対象</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($partnerStats): foreach ($partnerStats as $stat): ?>
                <?php
                    $retryable = (int)($stat['retryable_count'] ?? 0);
                    $statSiteKey = (string)($stat['site_key'] ?? '');
                ?>
                <tr>
                    <td>
                        <strong><?= h($stat['name'] ?: $statSiteKey) ?></strong>
                        <span style="display:block;color:var(--text-muted);font-size:.78rem;"><?= h($statSiteKey ?: '-') ?></span>
                    </td>
                    <td>
                        <span style="display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.72rem;font-weight:700;<?= ($stat['status'] ?? '') === 'active' ? 'background:rgba(44,143,99,.18);color:#2c8f63;' : 'background:rgba(180,55,55,.18);color:#b43737;' ?>">
                            <?= ($stat['status'] ?? '') === 'active' ? '有効' : '停止' ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.82rem;">
                        <?= !empty($stat['last_event_at']) ? h(date('Y/m/d H:i', strtotime($stat['last_event_at']))) : '-' ?>
                    </td>
                    <td><?= number_format((int)($stat['success_count'] ?? 0)) ?></td>
                    <td><?= number_format((int)($stat['failed_count'] ?? 0)) ?></td>
                    <td><strong style="<?= $retryable > 0 ? 'color:#b43737;' : 'color:var(--text-muted);' ?>"><?= number_format($retryable) ?></strong></td>
                    <td style="white-space:nowrap;">
                        <?php if ($retryable > 0 && $statSiteKey !== ''): ?>
                            <form method="post" onsubmit="return confirm('この連携先の失敗ログを最大10件まとめて再送します。よろしいですか？')" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="retry_failed_batch">
                                <input type="hidden" name="site_key" value="<?= h($statSiteKey) ?>">
                                <input type="hidden" name="limit" value="10">
                                <button type="submit" class="btn btn-gold btn-sm">10件再送</button>
                            </form>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:.78rem;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:1.5rem;">連携先またはログがまだありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <form method="post" onsubmit="return confirm('全連携先の失敗ログを最大10件まとめて再送します。よろしいですか？')" style="margin-top:1rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="retry_failed_batch">
        <input type="hidden" name="limit" value="10">
        <button type="submit" class="btn btn-outline">失敗ログをまとめて10件再送</button>
        <span style="color:var(--text-muted);font-size:.82rem;">一度に大量送信しないよう、最大10件ずつ再送します。</span>
    </form>
</div>
<div class="card">
    <p class="card-title">螟夜Κ騾｣謳ｺ繝ｭ繧ｰ讀懃ｴ｢</p>
    <form method="get" style="display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>譁ｹ蜷・/label>
            <select name="direction">
                <option value="">縺吶∋縺ｦ</option>
                <option value="outbound" <?= $direction === 'outbound' ? 'selected' : '' ?>>騾∽ｿ｡</option>
                <option value="inbound" <?= $direction === 'inbound' ? 'selected' : '' ?>>蜿嶺ｿ｡</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>邨先棡</label>
            <select name="success">
                <option value="">縺吶∋縺ｦ</option>
                <option value="1" <?= $success === '1' ? 'selected' : '' ?>>謌仙粥</option>
                <option value="0" <?= $success === '0' ? 'selected' : '' ?>>螟ｱ謨・/option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>騾｣謳ｺ蜈・/label>
            <select name="site_key">
                <option value="">縺吶∋縺ｦ</option>
                <?php foreach ($siteOptions as $option): ?>
                    <option value="<?= h($option) ?>" <?= $siteKey === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>繧､繝吶Φ繝・/label>
            <select name="event_type">
                <option value="">縺吶∋縺ｦ</option>
                <?php foreach ($eventOptions as $option): ?>
                    <option value="<?= h($option) ?>" <?= $eventType === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>HTTP</label>
            <input type="text" name="http_status" value="<?= h($status) ?>" placeholder="萓・ 500">
        </div>
        <div class="form-group" style="margin:0;">
            <label>讀懃ｴ｢</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="莉｣逅・ｺ励・蜈ｱ騾唔D繝ｻ繧ｨ繝ｩ繝ｼ">
        </div>
        <div style="grid-column:1/-1;display:flex;gap:.5rem;">
            <button type="submit" class="btn btn-gold">邨槭ｊ霎ｼ縺ｿ</button>
            <a href="/admin/integration_logs.php" class="btn btn-outline">繝ｪ繧ｻ繝・ヨ</a>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">繝ｭ繧ｰ荳隕ｧ</p>
        <span style="font-size:.78rem;color:var(--text-muted);">蜈ｨ <?= number_format((int)$pag['total']) ?> 莉ｶ</span>
    </div>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>譌･譎・/th>
                    <th>譁ｹ蜷・/th>
                    <th>騾｣謳ｺ蜈・/th>
                    <th>繧､繝吶Φ繝・/th>
                    <th>HTTP</th>
                    <th>邨先棡</th>
                    <th>莉｣逅・ｺ・/ 蜈ｱ騾唔D</th>
                    <th>隧ｳ邏ｰ</th>
                    <th>謫堺ｽ・/th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs): foreach ($logs as $log): ?>
                <?php
                    $isSuccess = (int)($log['success'] ?? 0) === 1;
                    $canRetry = !$isSuccess
                        && (string)($log['direction'] ?? '') === 'outbound'
                        && trim((string)($log['endpoint'] ?? '')) !== ''
                        && trim((string)($log['request_body'] ?? '')) !== '';
                ?>
                <tr>
                    <td style="white-space:nowrap;font-size:.78rem;color:var(--text-muted);"><?= h(date('Y/m/d H:i', strtotime($log['created_at']))) ?></td>
                    <td><?= (string)$log['direction'] === 'outbound' ? '騾∽ｿ｡' : '蜿嶺ｿ｡' ?></td>
                    <td style="font-size:.82rem;"><?= h($log['site_key'] ?: '-') ?></td>
                    <td style="font-size:.82rem;"><?= h($log['event_type'] ?: '-') ?></td>
                    <td style="white-space:nowrap;"><?= h($log['http_status'] ?: '-') ?></td>
                    <td>
                        <span style="display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.72rem;font-weight:700;<?= $isSuccess ? 'background:rgba(44,143,99,.18);color:#2c8f63;' : 'background:rgba(180,55,55,.18);color:#b43737;' ?>">
                            <?= $isSuccess ? 'OK' : 'NG' ?>
                        </span>
                    </td>
                    <td style="font-size:.8rem;min-width:160px;">
                        <?php if (!empty($log['agent_id'])): ?>
                            <a href="/admin/agents.php?edit=<?= (int)$log['agent_id'] ?>" style="color:var(--gold);text-decoration:none;">
                                <?= h($log['agent_name'] ?: $log['person_name'] ?: ('#' . $log['agent_id'])) ?>
                            </a>
                            <?php if (!empty($log['agent_code'])): ?><span style="display:block;color:var(--text-muted);font-size:.72rem;"><?= h($log['agent_code']) ?></span><?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($log['common_user_id'])): ?><span style="display:block;color:var(--text-muted);font-size:.72rem;"><?= h($log['common_user_id']) ?></span><?php endif; ?>
                        <?php if (empty($log['agent_id']) && empty($log['common_user_id'])): ?><span style="color:var(--text-muted);">-</span><?php endif; ?>
                    </td>
                    <td style="font-size:.78rem;max-width:420px;word-break:break-word;">
                        <?php if (!empty($log['error_message'])): ?>
                            <div style="color:#b43737;"><?= h(integrationLogShort($log['error_message'], 120)) ?></div>
                        <?php else: ?>
                            <div style="color:var(--text-muted);"><?= h(integrationLogShort($log['endpoint'] ?? '', 120)) ?></div>
                        <?php endif; ?>
                        <details style="margin-top:.35rem;">
                            <summary style="cursor:pointer;color:var(--gold);">JSON隧ｳ邏ｰ</summary>
                            <div style="margin-top:.4rem;">
                                <p style="margin:.35rem 0;color:var(--text-muted);">騾∽ｿ｡蜈・/p>
                                <pre style="white-space:pre-wrap;word-break:break-word;font-size:.72rem;background:rgba(0,0,0,.05);padding:.6rem;"><?= h($log['endpoint'] ?? '') ?></pre>
                                <p style="margin:.35rem 0;color:var(--text-muted);">繝ｪ繧ｯ繧ｨ繧ｹ繝・/p>
                                <pre style="white-space:pre-wrap;word-break:break-word;font-size:.72rem;background:rgba(0,0,0,.05);padding:.6rem;"><?= h($log['request_body'] ?? '') ?></pre>
                                <p style="margin:.35rem 0;color:var(--text-muted);">繝ｬ繧ｹ繝昴Φ繧ｹ</p>
                                <pre style="white-space:pre-wrap;word-break:break-word;font-size:.72rem;background:rgba(0,0,0,.05);padding:.6rem;"><?= h($log['response_body'] ?? '') ?></pre>
                            </div>
                        </details>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if ($canRetry): ?>
                            <form method="post" onsubmit="return confirm('この外部送信ログを再送します。よろしいですか？')" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="retry_log">
                                <input type="hidden" name="id" value="<?= (int)$log['id'] ?>">
                                <button type="submit" class="btn btn-gold btn-sm">再送</button>
                            </form>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:.78rem;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2.5rem;">螟夜Κ騾｣謳ｺ繝ｭ繧ｰ縺ｯ縺ゅｊ縺ｾ縺帙ｓ縲・/td></tr>
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
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>




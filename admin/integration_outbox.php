<?php
$pageTitle = '外部連携Outbox';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$hasOutbox = !empty(tableColumns('integration_outbox_events'));
$hasAttempts = !empty(tableColumns('integration_event_attempts'));
$message = '';
$error = '';

function outboxDate(?string $value): string {
    if (!$value) return '-';
    $ts = strtotime($value);
    return $ts ? date('Y/m/d H:i', $ts) : '-';
}

function outboxStatusBadge(string $status): string {
    $labels = getIntegrationOutboxStatusLabels();
    $label = $labels[$status] ?? $status;
    $class = 'badge-new';
    if ($status === 'succeeded') $class = 'badge-active';
    if ($status === 'failed') $class = 'badge-contacted';
    if ($status === 'dlq') $class = 'badge-inactive';
    return '<span class="badge ' . h($class) . '">' . h($label) . '</span>';
}

function outboxShort(?string $value, int $length = 120): string {
    $value = trim((string)$value);
    if ($value === '') return '-';
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
        return mb_substr($value, 0, $length, 'UTF-8') . '...';
    }
    return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
}

if ($hasOutbox && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '操作トークンが無効です。ページを再読み込みしてからもう一度お試しください。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($action === 'retry_one') {
                $stmt = $db->prepare("SELECT * FROM integration_outbox_events WHERE id=? LIMIT 1");
                $stmt->execute([$id]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$event) {
                    throw new RuntimeException('対象のOutboxイベントが見つかりません。');
                }
                $result = retryIntegrationOutboxEventRow($event);
                if (!empty($result['ok'])) {
                    $message = '再送に成功しました。HTTP ' . (int)($result['status'] ?? 0);
                } else {
                    $error = '再送に失敗しました。HTTP ' . (int)($result['status'] ?? 0) . ' ' . (string)($result['error'] ?? '');
                }
            } elseif ($action === 'retry_due_batch') {
                $targetSiteKey = trim((string)($_POST['site_key'] ?? ''));
                $limit = min(50, max(1, (int)($_POST['limit'] ?? 10)));
                $includeDlq = !empty($_POST['include_dlq']);
                $summary = retryDueIntegrationOutboxEvents($targetSiteKey, $limit, $includeDlq);
                $message = '一括再送が完了しました。対象 ' . (int)$summary['target_count'] . ' / 成功 ' . (int)$summary['success_count'] . ' / 失敗 ' . (int)$summary['failed_count'];
                if (!empty($summary['errors'])) {
                    $error = implode("\n", array_slice($summary['errors'], 0, 5));
                }
            } elseif ($action === 'reset_for_retry') {
                resetIntegrationOutboxEventForRetry($id);
                $message = '再送待ちに戻しました。';
            } elseif ($action === 'move_dlq') {
                moveIntegrationOutboxEventToDlq($id, 'Moved to DLQ manually by admin.');
                $message = 'DLQ（要確認）へ移動しました。';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$status = sanitizeInput($_GET['status'] ?? '');
$siteKey = sanitizeInput($_GET['site_key'] ?? '');
$eventType = sanitizeInput($_GET['event_type'] ?? '');
$q = sanitizeInput($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$rows = [];
$pag = paginate(0, $perPage, $page);
$stats = [];
$siteOptions = [];
$eventOptions = [];
$attemptCounts = [];

if ($hasOutbox) {
    $stats = $db->query("SELECT status, COUNT(*) AS cnt FROM integration_outbox_events GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $siteOptions = $db->query("SELECT DISTINCT target_site_key FROM integration_outbox_events WHERE target_site_key IS NOT NULL AND target_site_key<>'' ORDER BY target_site_key")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $eventOptions = $db->query("SELECT DISTINCT event_type FROM integration_outbox_events WHERE event_type IS NOT NULL AND event_type<>'' ORDER BY event_type")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $where = [];
    $params = [];
    if (in_array($status, ['pending', 'failed', 'succeeded', 'dlq'], true)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if ($siteKey !== '') {
        $where[] = 'target_site_key = ?';
        $params[] = $siteKey;
    }
    if ($eventType !== '') {
        $where[] = 'event_type = ?';
        $params[] = $eventType;
    }
    if ($q !== '') {
        $where[] = '(event_id LIKE ? OR correlation_id LIKE ? OR last_error LIKE ? OR payload_json LIKE ?)';
        $kw = '%' . $q . '%';
        array_push($params, $kw, $kw, $kw, $kw);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $db->prepare("SELECT COUNT(*) FROM integration_outbox_events $whereSql");
    $countStmt->execute($params);
    $pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);

    $stmt = $db->prepare("
        SELECT *
        FROM integration_outbox_events
        $whereSql
        ORDER BY
            CASE status WHEN 'dlq' THEN 0 WHEN 'failed' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,
            COALESCE(next_attempt_at, updated_at, created_at) ASC,
            id DESC
        LIMIT $perPage OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($hasAttempts && $rows) {
        $eventIds = array_values(array_filter(array_map(fn($r) => (string)($r['event_id'] ?? ''), $rows)));
        if ($eventIds) {
            $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
            $stmt = $db->prepare("SELECT event_id, COUNT(*) AS cnt FROM integration_event_attempts WHERE event_id IN ($placeholders) GROUP BY event_id");
            $stmt->execute($eventIds);
            $attemptCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        }
    }
}
?>

<?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" style="white-space:pre-wrap;"><?= h($error) ?></div><?php endif; ?>

<?php if (!$hasOutbox): ?>
<div class="alert alert-error">Outbox管理のDBマイグレーションが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。</div>
<?php else: ?>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-sub">送信待ち</div><div class="stat-val"><?= number_format((int)($stats['pending'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="stat-sub">再送待ち</div><div class="stat-val"><?= number_format((int)($stats['failed'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="stat-sub">要確認（DLQ）</div><div class="stat-val"><?= number_format((int)($stats['dlq'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="stat-sub">送信済み</div><div class="stat-val"><?= number_format((int)($stats['succeeded'] ?? 0)) ?></div></div>
</div>

<div class="card">
    <p class="card-title">一括再送</p>
    <p style="color:var(--text-muted);margin-top:-.25rem;">送信待ち・再送待ちのイベントを、次回再送時刻が来ているものから順に送信します。DLQは通常の自動再送からは外します。</p>
    <form method="post" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="retry_due_batch">
        <div class="form-group" style="min-width:220px;margin-bottom:0;">
            <label>連携先</label>
            <select name="site_key">
                <option value="">すべて</option>
                <?php foreach ($siteOptions as $opt): ?>
                    <option value="<?= h($opt) ?>" <?= $siteKey === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="width:120px;margin-bottom:0;">
            <label>件数</label>
            <input type="number" name="limit" value="10" min="1" max="50">
        </div>
        <label class="form-check" style="margin-bottom:.35rem;">
            <input type="checkbox" name="include_dlq" value="1"> DLQも含める
        </label>
        <button type="submit" class="btn btn-gold">一括再送</button>
        <a class="btn btn-outline" href="/admin/integration_logs.php">連携ログを見る</a>
    </form>
</div>

<div class="card">
    <p class="card-title">Outboxイベント</p>
    <form method="get" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;">
        <div class="form-group" style="min-width:180px;margin-bottom:0;">
            <label>状態</label>
            <select name="status">
                <option value="">すべて</option>
                <?php foreach (getIntegrationOutboxStatusLabels() as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:200px;margin-bottom:0;">
            <label>連携先</label>
            <select name="site_key">
                <option value="">すべて</option>
                <?php foreach ($siteOptions as $opt): ?>
                    <option value="<?= h($opt) ?>" <?= $siteKey === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:220px;margin-bottom:0;">
            <label>イベント</label>
            <select name="event_type">
                <option value="">すべて</option>
                <?php foreach ($eventOptions as $opt): ?>
                    <option value="<?= h($opt) ?>" <?= $eventType === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:260px;margin-bottom:0;flex:1;">
            <label>検索</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="event_id・correlation_id・エラー内容">
        </div>
        <button type="submit" class="btn btn-gold">絞り込み</button>
        <a class="btn btn-outline" href="/admin/integration_outbox.php">リセット</a>
    </form>

    <div class="table-scroll">
        <table style="min-width:1260px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>状態</th>
                    <th>連携先</th>
                    <th>イベント</th>
                    <th>試行</th>
                    <th>次回再送</th>
                    <th>最終試行</th>
                    <th>作成</th>
                    <th>エラー</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
                <?php $canRetry = in_array((string)($row['status'] ?? ''), ['pending', 'failed', 'dlq'], true); ?>
                <tr>
                    <td>
                        <strong>#<?= (int)$row['id'] ?></strong><br>
                        <span style="color:var(--text-muted);font-size:.78rem;"><?= h(outboxShort($row['event_id'] ?? '', 48)) ?></span>
                    </td>
                    <td><?= outboxStatusBadge((string)($row['status'] ?? '')) ?></td>
                    <td><?= h($row['target_site_key'] ?? '-') ?></td>
                    <td>
                        <strong><?= h($row['event_type'] ?? '-') ?></strong><br>
                        <span style="color:var(--text-muted);font-size:.78rem;">v<?= h($row['event_version'] ?? '1.0') ?></span>
                    </td>
                    <td>
                        <?= number_format((int)($row['attempt_count'] ?? 0)) ?> / <?= number_format((int)($row['max_attempts'] ?? 0)) ?><br>
                        <span style="color:var(--text-muted);font-size:.78rem;">履歴 <?= number_format((int)($attemptCounts[$row['event_id']] ?? 0)) ?></span>
                    </td>
                    <td><?= outboxDate($row['next_attempt_at'] ?? null) ?></td>
                    <td><?= outboxDate($row['last_attempt_at'] ?? null) ?></td>
                    <td><?= outboxDate($row['created_at'] ?? null) ?></td>
                    <td style="max-width:280px;white-space:normal;"><?= h(outboxShort($row['last_error'] ?? '', 180)) ?></td>
                    <td>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <?php if ($canRetry): ?>
                            <form method="post" onsubmit="return confirm('このイベントを再送します。よろしいですか？');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="retry_one">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-gold btn-sm">再送</button>
                            </form>
                            <?php endif; ?>
                            <?php if (($row['status'] ?? '') === 'dlq'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="reset_for_retry">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">再送待ちへ</button>
                            </form>
                            <?php elseif (($row['status'] ?? '') !== 'succeeded'): ?>
                            <form method="post" onsubmit="return confirm('DLQ（要確認）へ移動します。よろしいですか？');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="move_dlq">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">DLQへ</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10" style="color:var(--text-muted);">対象のOutboxイベントはありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pag['total_pages'] ?? 1) > 1): ?>
        <?php
        $baseQuery = ['status' => $status, 'site_key' => $siteKey, 'event_type' => $eventType, 'q' => $q];
        ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= (int)$pag['total_pages']; $i++): ?>
                <?php $baseQuery['page'] = $i; ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= h(http_build_query($baseQuery)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

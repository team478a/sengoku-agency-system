<?php
$pageTitle = '共通HUB修正';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';

$tables = [
    'common_users',
    'system_account_links',
    'service_user_mappings',
    'user_identities',
    'agency_customer_relations',
    'agent_touchpoints',
    'integration_event_logs',
    'account_merge_logs',
    'admin_action_logs',
];
$ready = [];
foreach ($tables as $table) {
    $ready[$table] = !empty(tableColumns($table));
}
$hubReady = $ready['common_users'];

function hubFixDate(?string $date): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date('Y/m/d H:i', $ts) : $date;
}

function hubFixCommonUser(PDO $db, string $commonUserId): ?array {
    $stmt = $db->prepare("SELECT * FROM common_users WHERE common_user_id=? LIMIT 1");
    $stmt->execute([$commonUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hubFixAdminLog(PDO $db, string $action, array $details): void {
    if (empty(tableColumns('admin_action_logs'))) {
        return;
    }
    try {
        $stmt = $db->prepare("INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_hash) VALUES (?, ?, 'common_hub', NULL, ?, ?)");
        $stmt->execute([
            (int)($_SESSION['admin_id'] ?? 0) ?: null,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('common hub admin log failed: ' . $e->getMessage());
    }
}

function hubFixUpdateCommonUser(PDO $db, string $source, string $target, array $sourceUser, array $targetUser): void {
    $columns = tableColumns('common_users');
    $sets = ["status='merged'", "updated_at=NOW()"];
    $params = [];
    if (in_array('merged_into_common_user_id', $columns, true)) {
        $sets[] = 'merged_into_common_user_id=?';
        $params[] = $target;
    }
    $stmt = $db->prepare("UPDATE common_users SET " . implode(', ', $sets) . " WHERE common_user_id=?");
    $params[] = $source;
    $stmt->execute($params);

    $copySets = [];
    $copyParams = [];
    foreach (['primary_email_hash', 'primary_phone_hash', 'primary_wallet_address', 'acquisition_channel', 'acquisition_source', 'campaign_id', 'first_touch_at', 'last_touch_at'] as $field) {
        if (in_array($field, $columns, true) && empty($targetUser[$field]) && !empty($sourceUser[$field])) {
            $copySets[] = "$field=?";
            $copyParams[] = $sourceUser[$field];
        }
    }
    if ($copySets) {
        $copySets[] = 'updated_at=NOW()';
        $copyParams[] = $target;
        $stmt = $db->prepare("UPDATE common_users SET " . implode(', ', $copySets) . " WHERE common_user_id=?");
        $stmt->execute($copyParams);
    }
}

function hubFixMergeCommonUsers(PDO $db, string $source, string $target, string $reason): array {
    $sourceUser = hubFixCommonUser($db, $source);
    $targetUser = hubFixCommonUser($db, $target);
    if (!$sourceUser) {
        throw new RuntimeException('統合元の共通顧客IDが見つかりません。');
    }
    if (!$targetUser) {
        throw new RuntimeException('統合先の共通顧客IDが見つかりません。');
    }
    if ($source === $target) {
        throw new RuntimeException('統合元と統合先は別の共通顧客IDを指定してください。');
    }
    if (($sourceUser['status'] ?? '') === 'merged') {
        throw new RuntimeException('統合元はすでに統合済みです。');
    }

    $counts = [
        'system_links' => 0,
        'legacy_mappings' => 0,
        'identities' => 0,
        'relations_moved' => 0,
        'relations_inactivated' => 0,
        'touchpoints' => 0,
        'logs' => 0,
    ];

    $db->beginTransaction();
    try {
        if (!empty(tableColumns('system_account_links'))) {
            $stmt = $db->prepare("UPDATE system_account_links SET common_user_id=?, updated_at=NOW() WHERE common_user_id=?");
            $stmt->execute([$target, $source]);
            $counts['system_links'] = $stmt->rowCount();
        }
        if (!empty(tableColumns('service_user_mappings'))) {
            $stmt = $db->prepare("UPDATE service_user_mappings SET common_user_id=?, status='active', updated_at=NOW() WHERE common_user_id=?");
            $stmt->execute([$target, $source]);
            $counts['legacy_mappings'] = $stmt->rowCount();
        }
        if (!empty(tableColumns('user_identities'))) {
            $stmt = $db->prepare("UPDATE user_identities SET common_user_id=?, updated_at=NOW() WHERE common_user_id=?");
            $stmt->execute([$target, $source]);
            $counts['identities'] = $stmt->rowCount();
        }
        if (!empty(tableColumns('agency_customer_relations'))) {
            $stmt = $db->prepare("SELECT * FROM agency_customer_relations WHERE common_user_id=?");
            $stmt->execute([$source]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $relation) {
                $check = $db->prepare("SELECT id FROM agency_customer_relations WHERE common_user_id=? AND relation_type=? AND project_id=? LIMIT 1");
                $check->execute([$target, $relation['relation_type'], (int)$relation['project_id']]);
                if ($check->fetchColumn()) {
                    $upd = $db->prepare("UPDATE agency_customer_relations SET status='inactive', updated_at=NOW() WHERE id=?");
                    $upd->execute([(int)$relation['id']]);
                    $counts['relations_inactivated']++;
                } else {
                    $upd = $db->prepare("UPDATE agency_customer_relations SET common_user_id=?, updated_at=NOW() WHERE id=?");
                    $upd->execute([$target, (int)$relation['id']]);
                    $counts['relations_moved']++;
                }
            }
        }
        if (!empty(tableColumns('agent_touchpoints'))) {
            $stmt = $db->prepare("UPDATE agent_touchpoints SET common_user_id=? WHERE common_user_id=?");
            $stmt->execute([$target, $source]);
            $counts['touchpoints'] = $stmt->rowCount();
        }
        if (!empty(tableColumns('integration_event_logs'))) {
            $stmt = $db->prepare("UPDATE integration_event_logs SET common_user_id=? WHERE common_user_id=?");
            $stmt->execute([$target, $source]);
            $counts['logs'] = $stmt->rowCount();
        }
        hubFixUpdateCommonUser($db, $source, $target, $sourceUser, $targetUser);
        if (!empty(tableColumns('account_merge_logs'))) {
            $stmt = $db->prepare("INSERT INTO account_merge_logs (from_common_user_id, to_common_user_id, merge_reason, confidence_score, status, operated_by_type, operated_by_id, before_json, after_json) VALUES (?, ?, ?, ?, 'completed', 'admin', ?, ?, ?)");
            $stmt->execute([
                $source,
                $target,
                $reason !== '' ? $reason : 'admin_manual_merge',
                null,
                (int)($_SESSION['admin_id'] ?? 0) ?: null,
                json_encode(['source' => $sourceUser, 'target' => $targetUser], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($counts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
        hubFixAdminLog($db, 'common_hub_merge', ['source' => $source, 'target' => $target, 'counts' => $counts, 'reason' => $reason]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return $counts;
}

function hubFixAssignAgent(PDO $db, string $commonUserId, int $agentId, int $projectId, string $systemKey): array {
    if (!hubFixCommonUser($db, $commonUserId)) {
        throw new RuntimeException('共通顧客IDが見つかりません。');
    }
    $stmt = $db->prepare("SELECT id, agent_code, agent_name, person_name FROM agents WHERE id=? LIMIT 1");
    $stmt->execute([$agentId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('代理店が見つかりません。');
    }

    $counts = ['common_user' => 0, 'system_links' => 0, 'legacy_mappings' => 0, 'relations' => 0, 'touchpoints' => 0];
    $db->beginTransaction();
    try {
        $commonColumns = tableColumns('common_users');
        $sets = [];
        $params = [];
        if (in_array('assigned_agent_id', $commonColumns, true)) {
            $sets[] = 'assigned_agent_id=?';
            $params[] = $agentId;
        }
        if (in_array('agent_link_status', $commonColumns, true)) {
            $sets[] = "agent_link_status='assigned'";
        }
        if ($sets) {
            $sets[] = 'updated_at=NOW()';
            $params[] = $commonUserId;
            $stmt = $db->prepare("UPDATE common_users SET " . implode(', ', $sets) . " WHERE common_user_id=?");
            $stmt->execute($params);
            $counts['common_user'] = $stmt->rowCount();
        }

        if (!empty(tableColumns('system_account_links'))) {
            $params = [$agentId, $commonUserId];
            $where = 'common_user_id=?';
            if ($systemKey !== '') {
                $where .= ' AND system_key=?';
                $params[] = $systemKey;
            }
            $stmt = $db->prepare("UPDATE system_account_links SET agent_id=?, updated_at=NOW() WHERE $where");
            $stmt->execute($params);
            $counts['system_links'] = $stmt->rowCount();
        }
        if (!empty(tableColumns('service_user_mappings'))) {
            $params = [$agentId, $commonUserId];
            $where = 'common_user_id=?';
            if ($systemKey !== '') {
                $where .= ' AND service_key=?';
                $params[] = $systemKey;
            }
            $stmt = $db->prepare("UPDATE service_user_mappings SET agent_id=?, updated_at=NOW() WHERE $where");
            $stmt->execute($params);
            $counts['legacy_mappings'] = $stmt->rowCount();
        }
        if (!empty(tableColumns('agency_customer_relations'))) {
            $params = [$agentId, $commonUserId];
            $where = 'common_user_id=?';
            if ($projectId > 0) {
                $where .= ' AND project_id=?';
                $params[] = $projectId;
            }
            $stmt = $db->prepare("UPDATE agency_customer_relations SET agent_id=?, locked=1, status='active', updated_at=NOW() WHERE $where");
            $stmt->execute($params);
            $counts['relations'] = $stmt->rowCount();
        }
        if (!empty(tableColumns('agent_touchpoints'))) {
            $params = [$agentId, $commonUserId];
            $where = 'common_user_id=?';
            if ($projectId > 0) {
                $where .= ' AND project_id=?';
                $params[] = $projectId;
            }
            $stmt = $db->prepare("UPDATE agent_touchpoints SET agent_id=? WHERE $where");
            $stmt->execute($params);
            $counts['touchpoints'] = $stmt->rowCount();
        }
        hubFixAdminLog($db, 'common_hub_assign_agent', ['common_user_id' => $commonUserId, 'agent_id' => $agentId, 'project_id' => $projectId, 'system_key' => $systemKey, 'counts' => $counts]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return $counts;
}

$prefillCommonUserId = sanitizeInput($_GET['common_user_id'] ?? '');
$prefillTargetCommonUserId = sanitizeInput($_GET['target_common_user_id'] ?? '');
$prefillSystemKey = sanitizeInput($_GET['system_key'] ?? '');
$prefillProjectId = (int)($_GET['project_id'] ?? 0);

if ($hubReady && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isSuperAdmin()) {
        $message = 'この操作はオーナー管理者のみ実行できます。';
        $msgType = 'error';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。ページを再読み込みしてもう一度お試しください。';
        $msgType = 'error';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'merge_common_user') {
                $source = trim((string)($_POST['source_common_user_id'] ?? ''));
                $target = trim((string)($_POST['target_common_user_id'] ?? ''));
                $reason = trim((string)($_POST['merge_reason'] ?? ''));
                $counts = hubFixMergeCommonUsers($db, $source, $target, $reason);
                $notified = syncCommonUserHubEventToExternalPartners('common_user.merged', $target, [
                    'source_common_user_id' => $source,
                    'target_common_user_id' => $target,
                    'reason' => $reason,
                    'counts' => $counts,
                    'operated_by_type' => 'admin',
                    'operated_by_id' => (int)($_SESSION['admin_id'] ?? 0),
                ]);
                $message = '共通顧客IDを統合しました。移動: 外部アカウント ' . $counts['system_links'] . ' 件、旧紐づけ ' . $counts['legacy_mappings'] . ' 件、ID候補 ' . $counts['identities'] . ' 件、代理店関係 ' . $counts['relations_moved'] . ' 件。';
                $message .= $notified ? ' External sync OK.' : ' External sync queued/logged with errors.';
                $prefillCommonUserId = $target;
                $prefillTargetCommonUserId = '';
            } elseif ($action === 'assign_agent') {
                $commonUserId = trim((string)($_POST['common_user_id'] ?? ''));
                $agentId = (int)($_POST['agent_id'] ?? 0);
                $projectId = (int)($_POST['project_id'] ?? 0);
                $systemKey = trim((string)($_POST['system_key'] ?? ''));
                $counts = hubFixAssignAgent($db, $commonUserId, $agentId, $projectId, $systemKey);
                $notified = syncCommonUserHubEventToExternalPartners('common_user.assigned_agent.updated', $commonUserId, [
                    'agent_id' => $agentId,
                    'project_id' => $projectId,
                    'system_key' => $systemKey,
                    'counts' => $counts,
                    'operated_by_type' => 'admin',
                    'operated_by_id' => (int)($_SESSION['admin_id'] ?? 0),
                ]);
                $message = '担当代理店を反映しました。外部アカウント ' . $counts['system_links'] . ' 件、旧紐づけ ' . $counts['legacy_mappings'] . ' 件、代理店関係 ' . $counts['relations'] . ' 件、接点履歴 ' . $counts['touchpoints'] . ' 件。';
                $message .= $notified ? ' External sync OK.' : ' External sync queued/logged with errors.';
                $prefillCommonUserId = $commonUserId;
                $prefillProjectId = $projectId;
                $prefillSystemKey = $systemKey;
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $msgType = 'error';
        }
    }
}

$agents = $db->query("SELECT id, agent_code, agent_name, person_name, level, status FROM agents ORDER BY level DESC, agent_name ASC, person_name ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$projects = !empty(tableColumns('projects')) ? $db->query("SELECT id, name, slug, status FROM projects ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) : [];
$systems = [];
if (!empty(tableColumns('system_account_links'))) {
    $systems = array_merge($systems, $db->query("SELECT DISTINCT system_key FROM system_account_links WHERE system_key<>'' ORDER BY system_key")->fetchAll(PDO::FETCH_COLUMN));
}
if (!empty(tableColumns('service_user_mappings'))) {
    $systems = array_merge($systems, $db->query("SELECT DISTINCT service_key FROM service_user_mappings WHERE service_key<>'' ORDER BY service_key")->fetchAll(PDO::FETCH_COLUMN));
}
$systems = array_values(array_unique(array_filter($systems)));
sort($systems);

$previewUser = $prefillCommonUserId !== '' && $hubReady ? hubFixCommonUser($db, $prefillCommonUserId) : null;
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$hubReady): ?>
<div class="alert alert-error">共通顧客HUBのDBマイグレーションが未適用です。アップデート画面でDBマイグレーションを適用してください。</div>
<?php else: ?>

<div class="card">
    <p class="card-title">共通HUB修正</p>
    <p style="font-size:.86rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        重複候補や担当未設定を確認した後に使う修正画面です。統合・担当変更は操作ログに残ります。
    </p>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/admin/common_hub_alerts.php" class="btn btn-outline">HUB確認へ</a>
        <a href="/admin/common_hub.php<?= $prefillCommonUserId !== '' ? '?common_user_id=' . urlencode($prefillCommonUserId) : '' ?>" class="btn btn-outline">HUB詳細へ</a>
    </div>
</div>

<?php if ($previewUser): ?>
<div class="card">
    <p class="card-title">現在選択中の共通顧客</p>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">共通顧客ID</div><div style="word-break:break-all;"><code><?= h($previewUser['common_user_id']) ?></code></div></div>
        <div class="stat-card"><div class="stat-label">状態</div><div><?= h($previewUser['status'] ?? '-') ?></div></div>
        <div class="stat-card"><div class="stat-label">獲得元</div><div><?= h(($previewUser['acquisition_channel'] ?? '') ?: '-') ?></div></div>
        <div class="stat-card"><div class="stat-label">更新</div><div><?= h(hubFixDate($previewUser['updated_at'] ?? null)) ?></div></div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <p class="card-title">1. 共通顧客IDを統合する</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.7;margin-bottom:1rem;">
        同じ人が複数の共通顧客IDになっている場合に、統合元を統合先へまとめます。統合元は merged 状態になります。
    </p>
    <form method="post" onsubmit="return confirm('共通顧客IDを統合します。統合元はmergedになります。よろしいですか？');" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem;align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="merge_common_user">
        <div class="form-group" style="margin:0;">
            <label>統合元 common_user_id</label>
            <input type="text" name="source_common_user_id" value="<?= h($prefillCommonUserId) ?>" placeholder="cu_...">
        </div>
        <div class="form-group" style="margin:0;">
            <label>統合先 common_user_id</label>
            <input type="text" name="target_common_user_id" value="<?= h($prefillTargetCommonUserId) ?>" placeholder="cu_...">
        </div>
        <div class="form-group" style="margin:0;">
            <label>統合理由</label>
            <input type="text" name="merge_reason" value="" placeholder="例: 同一メール・本人確認済み">
        </div>
        <button type="submit" class="btn btn-gold">統合する</button>
    </form>
</div>

<div class="card">
    <p class="card-title">2. 担当代理店を修正する</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.7;margin-bottom:1rem;">
        担当未設定や担当矛盾がある場合に、共通顧客IDへ担当代理店を反映します。プロジェクトや外部システムを指定すると対象を絞れます。
    </p>
    <form method="post" onsubmit="return confirm('担当代理店を反映します。よろしいですか？');" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.85rem;align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="assign_agent">
        <div class="form-group" style="margin:0;">
            <label>common_user_id</label>
            <input type="text" name="common_user_id" value="<?= h($prefillCommonUserId) ?>" placeholder="cu_...">
        </div>
        <div class="form-group" style="margin:0;">
            <label>担当代理店</label>
            <select name="agent_id" required>
                <option value="">選択してください</option>
                <?php foreach ($agents as $agent): ?>
                    <option value="<?= (int)$agent['id'] ?>">
                        <?= h(($agent['agent_code'] ?? '') . ' / ' . (($agent['agent_name'] ?? '') ?: ($agent['person_name'] ?? '')) . ' / Lv' . ($agent['level'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>プロジェクト</label>
            <select name="project_id">
                <option value="0">すべてのプロジェクト</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int)$project['id'] ?>" <?= $prefillProjectId === (int)$project['id'] ? 'selected' : '' ?>>
                        <?= h(($project['name'] ?? '') . ' / ' . ($project['slug'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>外部システム</label>
            <select name="system_key">
                <option value="">すべてのシステム</option>
                <?php foreach ($systems as $system): ?>
                    <option value="<?= h($system) ?>" <?= $prefillSystemKey === $system ? 'selected' : '' ?>><?= h($system) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-gold">担当を反映する</button>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

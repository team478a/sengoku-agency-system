<?php
$pageTitle = '共通ID検索';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$postCommonUserIdOverride = null;
$requiredTables = ['common_users', 'service_user_mappings', 'agency_customer_relations'];
$tablesReady = true;
foreach ($requiredTables as $table) {
    if (empty(tableColumns($table))) {
        $tablesReady = false;
        break;
    }
}

function commonIdBadge(string $status): string {
    $status = $status ?: 'active';
    $color = $status === 'active' ? '#2c8f63' : ($status === 'merged' ? '#b8860b' : '#b43737');
    $bg = $status === 'active' ? 'rgba(44,143,99,.16)' : ($status === 'merged' ? 'rgba(201,168,76,.18)' : 'rgba(180,55,55,.16)');
    return '<span style="display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.72rem;font-weight:700;background:' . $bg . ';color:' . $color . ';">' . h($status) . '</span>';
}

function commonIdShort(?string $value, int $length = 80): string {
    $value = trim((string)$value);
    if ($value === '') return '-';
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
        return mb_substr($value, 0, $length, 'UTF-8') . '...';
    }
    return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
}

function adminCommonIdLog(PDO $db, string $action, array $details = []): void {
    try {
        $stmt = $db->prepare("INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_hash) VALUES (?, ?, 'common_id', NULL, ?, ?)");
        $stmt->execute([
            (int)($_SESSION['admin_id'] ?? 0) ?: null,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('admin common id log failed: ' . $e->getMessage());
    }
}

if ($tablesReady && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isSuperAdmin()) {
        $message = 'Common ID operations are available only for super administrators.';
        $msgType = 'error';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid operation token. Reload the page and try again.';
        $msgType = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'update_mapping') {
                $id = (int)($_POST['id'] ?? 0);
                $newStatus = (string)($_POST['status'] ?? 'active');
                $newAgentId = (int)($_POST['agent_id'] ?? 0);
                if (!in_array($newStatus, ['active', 'merged', 'disabled'], true)) {
                    throw new RuntimeException('Invalid service mapping status.');
                }
                $stmt = $db->prepare("SELECT * FROM service_user_mappings WHERE id=? LIMIT 1");
                $stmt->execute([$id]);
                $before = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$before) {
                    throw new RuntimeException('Service mapping was not found.');
                }
                $stmt = $db->prepare("UPDATE service_user_mappings SET status=?, agent_id=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$newStatus, $newAgentId > 0 ? $newAgentId : null, $id]);
                adminCommonIdLog($db, 'common_id_mapping_update', [
                    'id' => $id,
                    'common_user_id' => $before['common_user_id'] ?? '',
                    'service_key' => $before['service_key'] ?? '',
                    'service_user_id' => $before['service_user_id'] ?? '',
                    'before_status' => $before['status'] ?? '',
                    'after_status' => $newStatus,
                    'before_agent_id' => $before['agent_id'] ?? null,
                    'after_agent_id' => $newAgentId > 0 ? $newAgentId : null,
                ]);
                $message = 'Service mapping updated.';
            } elseif ($action === 'update_relation') {
                $id = (int)($_POST['id'] ?? 0);
                $newStatus = (string)($_POST['status'] ?? 'active');
                $newAgentId = (int)($_POST['agent_id'] ?? 0);
                $locked = !empty($_POST['locked']) ? 1 : 0;
                if (!in_array($newStatus, ['active', 'inactive'], true)) {
                    throw new RuntimeException('Invalid relation status.');
                }
                $stmt = $db->prepare("SELECT * FROM agency_customer_relations WHERE id=? LIMIT 1");
                $stmt->execute([$id]);
                $before = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$before) {
                    throw new RuntimeException('Agency relation was not found.');
                }
                $stmt = $db->prepare("UPDATE agency_customer_relations SET status=?, agent_id=?, locked=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$newStatus, $newAgentId > 0 ? $newAgentId : null, $locked, $id]);
                adminCommonIdLog($db, 'common_id_relation_update', [
                    'id' => $id,
                    'common_user_id' => $before['common_user_id'] ?? '',
                    'project_id' => $before['project_id'] ?? null,
                    'relation_type' => $before['relation_type'] ?? '',
                    'before_status' => $before['status'] ?? '',
                    'after_status' => $newStatus,
                    'before_agent_id' => $before['agent_id'] ?? null,
                    'after_agent_id' => $newAgentId > 0 ? $newAgentId : null,
                    'before_locked' => $before['locked'] ?? null,
                    'after_locked' => $locked,
                ]);
                $message = 'Agency relation updated.';
            } elseif ($action === 'merge_common_id') {
                $source = trim((string)($_POST['source_common_user_id'] ?? ''));
                $target = trim((string)($_POST['target_common_user_id'] ?? ''));
                if ($source === '' || $target === '' || $source === $target) {
                    throw new RuntimeException('Enter different source and target common IDs.');
                }
                $stmt = $db->prepare("SELECT common_user_id FROM common_users WHERE common_user_id=? LIMIT 1");
                $stmt->execute([$source]);
                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Source common ID was not found.');
                }
                $stmt->execute([$target]);
                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Target common ID was not found.');
                }

                $db->beginTransaction();
                $movedRelations = 0;
                $inactiveRelations = 0;
                $stmt = $db->prepare("UPDATE service_user_mappings SET common_user_id=?, status='active', updated_at=NOW() WHERE common_user_id=?");
                $stmt->execute([$target, $source]);
                $movedMappings = $stmt->rowCount();

                $relStmt = $db->prepare("SELECT * FROM agency_customer_relations WHERE common_user_id=?");
                $relStmt->execute([$source]);
                foreach ($relStmt->fetchAll(PDO::FETCH_ASSOC) as $relation) {
                    $check = $db->prepare("SELECT id FROM agency_customer_relations WHERE common_user_id=? AND relation_type=? AND project_id=? LIMIT 1");
                    $check->execute([$target, $relation['relation_type'], (int)$relation['project_id']]);
                    if ($check->fetchColumn()) {
                        $upd = $db->prepare("UPDATE agency_customer_relations SET status='inactive', updated_at=NOW() WHERE id=?");
                        $upd->execute([(int)$relation['id']]);
                        $inactiveRelations++;
                    } else {
                        $upd = $db->prepare("UPDATE agency_customer_relations SET common_user_id=?, updated_at=NOW() WHERE id=?");
                        $upd->execute([$target, (int)$relation['id']]);
                        $movedRelations++;
                    }
                }
                if (!empty(tableColumns('integration_event_logs'))) {
                    $stmt = $db->prepare("UPDATE integration_event_logs SET common_user_id=? WHERE common_user_id=?");
                    $stmt->execute([$target, $source]);
                }
                $stmt = $db->prepare("UPDATE common_users SET status='merged', updated_at=NOW() WHERE common_user_id=?");
                $stmt->execute([$source]);
                $db->commit();

                adminCommonIdLog($db, 'common_id_merge', [
                    'source_common_user_id' => $source,
                    'target_common_user_id' => $target,
                    'moved_mappings' => $movedMappings,
                    'moved_relations' => $movedRelations,
                    'inactive_relations' => $inactiveRelations,
                ]);
                $message = 'Common IDs merged. Mappings moved: ' . $movedMappings . ', relations moved: ' . $movedRelations . '.';
                $postCommonUserIdOverride = $target;
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = $e->getMessage();
            $msgType = 'error';
        }
    }
}

$mode = sanitizeInput($_GET['mode'] ?? 'mappings');
if (!in_array($mode, ['users', 'mappings', 'relations'], true)) {
    $mode = 'mappings';
}
$q = sanitizeInput($_GET['q'] ?? '');
$serviceKey = sanitizeInput($_GET['service_key'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$commonUserId = sanitizeInput($_GET['common_user_id'] ?? '');
if ($postCommonUserIdOverride !== null) {
    $commonUserId = $postCommonUserIdOverride;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;

$serviceOptions = [];
$agentOptions = [];
$rows = [];
$pag = paginate(0, $perPage, $page);
$detail = [
    'user' => null,
    'mappings' => [],
    'relations' => [],
    'logs' => [],
];

if ($tablesReady) {
    $serviceOptions = $db->query("SELECT DISTINCT service_key FROM service_user_mappings WHERE service_key<>'' ORDER BY service_key")->fetchAll(PDO::FETCH_COLUMN);
    $agentOptions = $db->query("SELECT id, agent_code, agent_name, person_name FROM agents ORDER BY level DESC, agent_name ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($commonUserId !== '') {
        $stmt = $db->prepare("SELECT * FROM common_users WHERE common_user_id=? LIMIT 1");
        $stmt->execute([$commonUserId]);
        $detail['user'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $db->prepare("
            SELECT m.*, a.agent_code, a.agent_name, a.person_name
            FROM service_user_mappings m
            LEFT JOIN agents a ON m.agent_id=a.id
            WHERE m.common_user_id=?
            ORDER BY m.updated_at DESC, m.id DESC
        ");
        $stmt->execute([$commonUserId]);
        $detail['mappings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        if (!empty(tableColumns('integration_event_logs'))) {
            $stmt = $db->prepare("
                SELECT *
                FROM integration_event_logs
                WHERE common_user_id=?
                ORDER BY created_at DESC, id DESC
                LIMIT 20
            ");
            $stmt->execute([$commonUserId]);
            $detail['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $where = [];
    $params = [];

    if ($mode === 'users') {
        if ($q !== '') {
            $where[] = '(u.common_user_id LIKE ? OR u.primary_wallet_address LIKE ?)';
            $kw = '%' . $q . '%';
            array_push($params, $kw, $kw);
        }
        if ($status !== '') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($serviceKey !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM service_user_mappings mx WHERE mx.common_user_id=u.common_user_id AND mx.service_key=?)';
            $params[] = $serviceKey;
        }
        if ($commonUserId !== '') {
            $where[] = 'u.common_user_id = ?';
            $params[] = $commonUserId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $db->prepare("SELECT COUNT(*) FROM common_users u $whereSql");
        $countStmt->execute($params);
        $pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);
        $stmt = $db->prepare("
            SELECT
                u.*,
                COUNT(DISTINCT m.id) AS mapping_count,
                COUNT(DISTINCT r.id) AS relation_count,
                MAX(GREATEST(COALESCE(m.updated_at, u.updated_at), COALESCE(r.updated_at, u.updated_at))) AS last_linked_at
            FROM common_users u
            LEFT JOIN service_user_mappings m ON m.common_user_id=u.common_user_id
            LEFT JOIN agency_customer_relations r ON r.common_user_id=u.common_user_id
            $whereSql
            GROUP BY u.id
            ORDER BY u.updated_at DESC, u.id DESC
            LIMIT $perPage OFFSET {$pag['offset']}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($mode === 'relations') {
        if ($q !== '') {
            $where[] = '(r.common_user_id LIKE ? OR r.source_service_key LIKE ? OR r.source_service_user_id LIKE ? OR a.agent_code LIKE ? OR a.agent_name LIKE ? OR a.person_name LIKE ?)';
            $kw = '%' . $q . '%';
            array_push($params, $kw, $kw, $kw, $kw, $kw, $kw);
        }
        if ($serviceKey !== '') {
            $where[] = 'r.source_service_key = ?';
            $params[] = $serviceKey;
        }
        if ($status !== '') {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($commonUserId !== '') {
            $where[] = 'r.common_user_id = ?';
            $params[] = $commonUserId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM agency_customer_relations r
            LEFT JOIN agents a ON r.agent_id=a.id
            $whereSql
        ");
        $countStmt->execute($params);
        $pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);
        $stmt = $db->prepare("
            SELECT r.*, a.agent_code, a.agent_name, a.person_name, p.name AS project_name
            FROM agency_customer_relations r
            LEFT JOIN agents a ON r.agent_id=a.id
            LEFT JOIN projects p ON r.project_id=p.id
            $whereSql
            ORDER BY r.updated_at DESC, r.id DESC
            LIMIT $perPage OFFSET {$pag['offset']}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if ($q !== '') {
            $where[] = '(m.common_user_id LIKE ? OR m.service_key LIKE ? OR m.service_user_id LIKE ? OR m.wallet_address LIKE ? OR a.agent_code LIKE ? OR a.agent_name LIKE ? OR a.person_name LIKE ?)';
            $kw = '%' . $q . '%';
            array_push($params, $kw, $kw, $kw, $kw, $kw, $kw, $kw);
        }
        if ($serviceKey !== '') {
            $where[] = 'm.service_key = ?';
            $params[] = $serviceKey;
        }
        if ($status !== '') {
            $where[] = 'm.status = ?';
            $params[] = $status;
        }
        if ($commonUserId !== '') {
            $where[] = 'm.common_user_id = ?';
            $params[] = $commonUserId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM service_user_mappings m
            LEFT JOIN agents a ON m.agent_id=a.id
            $whereSql
        ");
        $countStmt->execute($params);
        $pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);
        $stmt = $db->prepare("
            SELECT m.*, u.status AS common_status, a.agent_code, a.agent_name, a.person_name
            FROM service_user_mappings m
            LEFT JOIN common_users u ON m.common_user_id=u.common_user_id
            LEFT JOIN agents a ON m.agent_id=a.id
            $whereSql
            ORDER BY m.updated_at DESC, m.id DESC
            LIMIT $perPage OFFSET {$pag['offset']}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$baseQuery = [
    'mode' => $mode,
    'q' => $q,
    'service_key' => $serviceKey,
    'status' => $status,
    'common_user_id' => $commonUserId,
];
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$tablesReady): ?>
<div class="alert alert-error">共通ID連携のDBマイグレーションが未適用です。アップデート画面からDBマイグレーションを適用してください。</div>
<?php else: ?>

<div class="card">
    <p class="card-title">共通ID検索</p>
    <p style="font-size:.86rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        外部サイト側のユーザーID、共通ID、代理店コード、ウォレットアドレスから紐づけ状況を確認できます。
    </p>
    <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>表示</label>
            <select name="mode">
                <option value="mappings" <?= $mode === 'mappings' ? 'selected' : '' ?>>サービス別ユーザー</option>
                <option value="relations" <?= $mode === 'relations' ? 'selected' : '' ?>>紹介関係</option>
                <option value="users" <?= $mode === 'users' ? 'selected' : '' ?>>共通ID</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>検索</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="共通ID・外部ID・代理店・ウォレット">
        </div>
        <div class="form-group" style="margin:0;">
            <label>共通ID指定</label>
            <input type="text" name="common_user_id" value="<?= h($commonUserId) ?>" placeholder="cmn_...">
        </div>
        <div class="form-group" style="margin:0;">
            <label>サービス</label>
            <select name="service_key">
                <option value="">すべて</option>
                <?php foreach ($serviceOptions as $option): ?>
                    <option value="<?= h($option) ?>" <?= $serviceKey === $option ? 'selected' : '' ?>><?= h($option) ?></option>
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
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>inactive</option>
            </select>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-gold">検索</button>
            <a href="/admin/common_id_mappings.php" class="btn btn-outline">リセット</a>
            <a href="/admin/common_id.php" class="btn btn-outline">概要へ</a>
        </div>
    </form>
</div>

<?php if ($commonUserId !== ''): ?>
<div class="card">
    <p class="card-title">共通ID詳細</p>
    <?php if (!$detail['user']): ?>
        <p style="color:var(--text-muted);">指定された共通IDは `common_users` に見つかりません。外部サービス側の紐づけのみ残っている可能性があります。</p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1rem;">
            <div><label>共通ID</label><code><?= h($detail['user']['common_user_id']) ?></code></div>
            <div><label>状態</label><?= commonIdBadge((string)$detail['user']['status']) ?></div>
            <div><label>ウォレット</label><span style="word-break:break-all;"><?= h($detail['user']['primary_wallet_address'] ?: '-') ?></span></div>
            <div><label>更新日時</label><?= h($detail['user']['updated_at'] ?? '-') ?></div>
        </div>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;">
            <strong>サービス別ユーザー</strong>
            <p class="stat-val" style="margin:.5rem 0;"><?= number_format(count($detail['mappings'])) ?></p>
        </div>
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;">
            <strong>紹介関係</strong>
            <p class="stat-val" style="margin:.5rem 0;"><?= number_format(count($detail['relations'])) ?></p>
        </div>
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;">
            <strong>連携ログ</strong>
            <p class="stat-val" style="margin:.5rem 0;"><?= number_format(count($detail['logs'])) ?></p>
        </div>
    </div>
</div>

<?php if (isSuperAdmin()): ?>
<div class="card">
    <p class="card-title">Common ID operations</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        Use this panel to correct common ID links. Changes are saved to the admin action log.
    </p>
    <form method="post" onsubmit="return confirm(&quot;Merge this common ID into the target common ID?&quot;)" style="display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto;gap:.75rem;align-items:end;margin-bottom:1.25rem;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="merge_common_id">
        <div class="form-group" style="margin:0;">
            <label>Source common ID</label>
            <input type="text" name="source_common_user_id" value="<?= h($commonUserId) ?>" readonly>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Target common ID</label>
            <input type="text" name="target_common_user_id" placeholder="Enter target common ID">
        </div>
        <button type="submit" class="btn btn-gold">Merge</button>
    </form>

    <p class="card-title" style="margin-top:1rem;">Service mappings</p>
    <div class="table-scroll" style="margin-bottom:1.25rem;">
        <table>
            <thead><tr><th>Service</th><th>Service user ID</th><th>Agent / Status / Operation</th></tr></thead>
            <tbody>
            <?php if ($detail['mappings']): foreach ($detail['mappings'] as $mapping): ?>
                <tr>
                    <td><?= h($mapping['service_key']) ?></td>
                    <td style="word-break:break-all;"><?= h($mapping['service_user_id']) ?></td>
                    <td>
                        <form method="post" style="display:grid;grid-template-columns:minmax(180px,1fr) minmax(120px,160px) auto;gap:.5rem;align-items:center;">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="update_mapping">
                            <input type="hidden" name="id" value="<?= (int)$mapping['id'] ?>">
                            <select name="agent_id">
                                <option value="">No agent</option>
                                <?php foreach ($agentOptions as $agent): ?>
                                    <option value="<?= (int)$agent['id'] ?>" <?= (int)($mapping['agent_id'] ?? 0) === (int)$agent['id'] ? 'selected' : '' ?>>
                                        <?= h(($agent['agent_code'] ?? '') . ' ' . (($agent['agent_name'] ?? '') ?: ($agent['person_name'] ?? ''))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <option value="active" <?= ($mapping['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option>
                                <option value="merged" <?= ($mapping['status'] ?? '') === 'merged' ? 'selected' : '' ?>>merged</option>
                                <option value="disabled" <?= ($mapping['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>disabled</option>
                            </select>
                            <button type="submit" class="btn btn-outline btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:1.5rem;">No service mappings.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="card-title" style="margin-top:1rem;">Agency relations</p>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Project</th><th>Source</th><th>Agent / Status</th></tr></thead>
            <tbody>
            <?php if ($detail['relations']): foreach ($detail['relations'] as $relation): ?>
                <tr>
                    <td><?= h($relation['project_name'] ?? ('#' . ($relation['project_id'] ?? ''))) ?></td>
                    <td><?= h(trim((string)($relation['source_service_key'] ?? '') . ' / ' . (string)($relation['source_service_user_id'] ?? ''), ' /')) ?></td>
                    <td>
                        <form method="post" style="display:grid;grid-template-columns:minmax(180px,1fr) minmax(120px,150px) auto auto;gap:.5rem;align-items:center;">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="update_relation">
                            <input type="hidden" name="id" value="<?= (int)$relation['id'] ?>">
                            <select name="agent_id">
                                <option value="">No agent</option>
                                <?php foreach ($agentOptions as $agent): ?>
                                    <option value="<?= (int)$agent['id'] ?>" <?= (int)($relation['agent_id'] ?? 0) === (int)$agent['id'] ? 'selected' : '' ?>>
                                        <?= h(($agent['agent_code'] ?? '') . ' ' . (($agent['agent_name'] ?? '') ?: ($agent['person_name'] ?? ''))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <option value="active" <?= ($relation['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option>
                                <option value="inactive" <?= ($relation['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>inactive</option>
                            </select>
                            <label style="white-space:nowrap;font-size:.8rem;color:var(--text-muted);"><input type="checkbox" name="locked" value="1" <?= !empty($relation['locked']) ? 'checked' : '' ?>> locked</label>
                            <button type="submit" class="btn btn-outline btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:1.5rem;">No agency relations.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">
            <?= $mode === 'users' ? '共通ID一覧' : ($mode === 'relations' ? '紹介関係一覧' : 'サービス別ユーザー一覧') ?>
        </p>
        <span style="font-size:.78rem;color:var(--text-muted);">全 <?= number_format((int)$pag['total']) ?> 件</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <?php if ($mode === 'users'): ?>
                <tr>
                    <th>共通ID</th>
                    <th>状態</th>
                    <th>サービス数</th>
                    <th>紹介関係数</th>
                    <th>ウォレット</th>
                    <th>更新日時</th>
                    <th>操作</th>
                </tr>
            <?php elseif ($mode === 'relations'): ?>
                <tr>
                    <th>共通ID</th>
                    <th>代理店</th>
                    <th>プロジェクト</th>
                    <th>登録元</th>
                    <th>紹介種別</th>
                    <th>状態</th>
                    <th>更新日時</th>
                </tr>
            <?php else: ?>
                <tr>
                    <th>共通ID</th>
                    <th>サービス</th>
                    <th>サービス側ID</th>
                    <th>代理店</th>
                    <th>ウォレット</th>
                    <th>状態</th>
                    <th>更新日時</th>
                </tr>
            <?php endif; ?>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
                <?php if ($mode === 'users'): ?>
                <tr>
                    <td><code><?= h($row['common_user_id']) ?></code></td>
                    <td><?= commonIdBadge((string)$row['status']) ?></td>
                    <td><?= number_format((int)$row['mapping_count']) ?></td>
                    <td><?= number_format((int)$row['relation_count']) ?></td>
                    <td style="word-break:break-all;"><?= h(commonIdShort($row['primary_wallet_address'] ?? '', 64)) ?></td>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i', strtotime($row['updated_at']))) ?></td>
                    <td><a class="btn btn-outline btn-sm" href="/admin/common_id_mappings.php?common_user_id=<?= urlencode((string)$row['common_user_id']) ?>">詳細</a></td>
                </tr>
                <?php elseif ($mode === 'relations'): ?>
                <tr>
                    <td><a href="/admin/common_id_mappings.php?common_user_id=<?= urlencode((string)$row['common_user_id']) ?>" style="color:var(--gold);"><code><?= h($row['common_user_id']) ?></code></a></td>
                    <td>
                        <?= h($row['agent_name'] ?: $row['person_name'] ?: '-') ?>
                        <?php if (!empty($row['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($row['agent_code']) ?></span><?php endif; ?>
                    </td>
                    <td><?= h($row['project_name'] ?? '-') ?></td>
                    <td><?= h(trim((string)($row['source_service_key'] ?? '') . ' / ' . (string)($row['source_service_user_id'] ?? ''), ' /')) ?></td>
                    <td><?= h($row['relation_type'] ?? '-') ?></td>
                    <td><?= commonIdBadge((string)($row['status'] ?? 'active')) ?></td>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i', strtotime($row['updated_at']))) ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td><a href="/admin/common_id_mappings.php?common_user_id=<?= urlencode((string)$row['common_user_id']) ?>" style="color:var(--gold);"><code><?= h($row['common_user_id']) ?></code></a></td>
                    <td><?= h($row['service_key']) ?></td>
                    <td style="word-break:break-all;"><?= h($row['service_user_id']) ?></td>
                    <td>
                        <?= h($row['agent_name'] ?: $row['person_name'] ?: '-') ?>
                        <?php if (!empty($row['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($row['agent_code']) ?></span><?php endif; ?>
                    </td>
                    <td style="word-break:break-all;"><?= h(commonIdShort($row['wallet_address'] ?? '', 64)) ?></td>
                    <td><?= commonIdBadge((string)($row['status'] ?? 'active')) ?></td>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i', strtotime($row['updated_at']))) ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; else: ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2.5rem;">該当するデータはありません。</td></tr>
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

<?php if ($commonUserId !== ''): ?>
<div class="card">
    <p class="card-title">詳細ログ</p>
    <div class="table-scroll">
        <table>
            <thead><tr><th>日時</th><th>方向</th><th>連携先</th><th>イベント</th><th>結果</th><th>エラー</th></tr></thead>
            <tbody>
            <?php if ($detail['logs']): foreach ($detail['logs'] as $log): ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i', strtotime($log['created_at']))) ?></td>
                    <td><?= (string)$log['direction'] === 'outbound' ? '送信' : '受信' ?></td>
                    <td><?= h($log['site_key'] ?: '-') ?></td>
                    <td><?= h($log['event_type'] ?: '-') ?></td>
                    <td><?= !empty($log['success']) ? '成功' : '失敗' ?><?= $log['http_status'] ? ' / HTTP ' . h($log['http_status']) : '' ?></td>
                    <td style="word-break:break-word;"><?= h(commonIdShort($log['error_message'] ?? '', 120)) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">この共通IDの連携ログはありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

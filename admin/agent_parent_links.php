<?php
$pageTitle = '親子紐づけ変更';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$labels = getLevelLabels();
$message = '';
$msgType = 'success';
$changedAgentCode = '';

function parentLinkDescendantIds(PDO $db, int $agentId): array {
    $ids = [];
    $queue = [$agentId];
    while ($queue) {
        $parentId = array_shift($queue);
        $stmt = $db->prepare('SELECT id FROM agents WHERE parent_id = ?');
        $stmt->execute([$parentId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $childId = (int)$childId;
            if (!in_array($childId, $ids, true)) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }
    }
    return $ids;
}

function parentLinkAllowedParentLevels(int $level, ?string $positionType = null): array {
    if ($level === 3 && normalizeAgentPosition($positionType) === 'agent_candidate') {
        return [3];
    }
    if ($level === 2) {
        return [3];
    }
    if ($level === 1) {
        return [2, 3];
    }
    return [];
}

function parentLinkBriefName(PDO $db, ?int $agentId): ?string {
    if (!$agentId) {
        return null;
    }
    $stmt = $db->prepare('SELECT agent_name, person_name, agent_code FROM agents WHERE id = ?');
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();
    if (!$agent) {
        return null;
    }
    $parts = array_filter([
        $agent['agent_name'] ?? '',
        $agent['person_name'] ?? '',
        $agent['agent_code'] ?? '',
    ], static fn($value) => trim((string)$value) !== '');
    return implode(' / ', $parts);
}

function parentLinkImpactStats(PDO $db, int $agentId, int $directChildren): array {
    $stats = [
        'direct_children' => $directChildren,
        'descendants' => 0,
        'pv' => 0,
        'leads' => 0,
        'new_leads' => 0,
    ];

    $ids = array_merge([$agentId], parentLinkDescendantIds($db, $agentId));
    $stats['descendants'] = max(0, count($ids) - 1);
    if (!$ids) {
        return $stats;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE type = 'pv' AND agent_id IN ($placeholders)");
        $stmt->execute($ids);
        $stats['pv'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('parent link impact pv failed: ' . $e->getMessage());
    }

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_total
            FROM leads
            WHERE agent_id IN ($placeholders)
        ");
        $stmt->execute($ids);
        $row = $stmt->fetch() ?: [];
        $stats['leads'] = (int)($row['total'] ?? 0);
        $stats['new_leads'] = (int)($row['new_total'] ?? 0);
    } catch (Throwable $e) {
        error_log('parent link impact leads failed: ' . $e->getMessage());
    }

    return $stats;
}

function parentLinkCandidates(PDO $db, int $agentId, int $level, ?string $positionType = null): array {
    $allowedLevels = parentLinkAllowedParentLevels($level, $positionType);
    if (!$allowedLevels) {
        return [];
    }

    $blockedIds = $agentId > 0 ? array_merge([$agentId], parentLinkDescendantIds($db, $agentId)) : [];
    $levelPlaceholders = implode(',', array_fill(0, count($allowedLevels), '?'));
    $params = $allowedLevels;
    $sql = "
        SELECT id, agent_code, agent_name, person_name, level, position_type, position_label
        FROM agents
        WHERE status = 'active'
          AND level IN ($levelPlaceholders)
    ";
    if ($level === 3 && normalizeAgentPosition($positionType) === 'agent_candidate') {
        $sql .= " AND (position_type IS NULL OR position_type <> 'agent_candidate')";
    }

    if ($blockedIds) {
        $blockedPlaceholders = implode(',', array_fill(0, count($blockedIds), '?'));
        $sql .= " AND id NOT IN ($blockedPlaceholders)";
        $params = array_merge($params, $blockedIds);
    }

    $sql .= ' ORDER BY level DESC, agent_name ASC, person_name ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function parentLinkValidate(PDO $db, int $agentId, int $level, ?int $parentId, array $labels, ?string $positionType = null): array {
    if (!in_array($level, [1, 2, 3], true)) {
        return ['区分が不正です。'];
    }
    if ($level === 3) {
        if (normalizeAgentPosition($positionType) !== 'agent_candidate') {
            return $parentId ? ['エージェントは本部直属にしてください。'] : [];
        }
        if (!$parentId) {
            return ['エージェント候補の上位にはエージェントを選択してください。'];
        }
    }
    if (!$parentId) {
        return ['新しい上位を選択してください。'];
    }
    if ($parentId === $agentId) {
        return ['自分自身を上位に設定できません。'];
    }
    if (in_array($parentId, parentLinkDescendantIds($db, $agentId), true)) {
        return ['配下メンバーを上位に設定できません。'];
    }

    $stmt = $db->prepare("SELECT level, position_type FROM agents WHERE id = ? AND status = 'active'");
    $stmt->execute([$parentId]);
    $parent = $stmt->fetch() ?: [];
    $parentLevel = (int)($parent['level'] ?? 0);
    $allowedLevels = parentLinkAllowedParentLevels($level, $positionType);
    if (!in_array($parentLevel, $allowedLevels, true)) {
        $allowedLabels = array_map(static fn($lv) => $labels[$lv] ?? ('Lv.' . $lv), $allowedLevels);
        return [($labels[$level] ?? '対象') . 'の上位には' . implode('または', $allowedLabels) . 'を選択してください。'];
    }
    if ($level === 3 && normalizeAgentPosition($positionType) === 'agent_candidate' && normalizeAgentPosition($parent['position_type'] ?? null) === 'agent_candidate') {
        return ['エージェント候補の上位には、候補ではないエージェントを選択してください。'];
    }

    return [];
}

function parentLinkLog(PDO $db, int $agentId, ?int $oldParentId, ?int $newParentId, int $level, string $reason): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_hash)
            VALUES (?, 'parent_update', 'agent', ?, ?, ?)
        ");
        $stmt->execute([
            (int)($_SESSION['admin_id'] ?? 0) ?: null,
            $agentId,
            json_encode([
                'old_parent_id' => $oldParentId,
                'old_parent_name' => parentLinkBriefName($db, $oldParentId),
                'new_parent_id' => $newParentId,
                'new_parent_name' => parentLinkBriefName($db, $newParentId),
                'level' => $level,
                'reason' => $reason,
            ], JSON_UNESCAPED_UNICODE),
            hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('parent link log failed: ' . $e->getMessage());
    }
}

function parentLinkIntegritySummary(PDO $db): array {
    $summary = [
        'orphans' => 0,
        'invalid_levels' => 0,
        'cycles' => 0,
    ];

    try {
        $summary['orphans'] = (int)$db->query("
            SELECT COUNT(*)
            FROM agents a
            LEFT JOIN agents p ON p.id = a.parent_id
            WHERE a.parent_id IS NOT NULL AND p.id IS NULL
        ")->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $summary['invalid_levels'] = (int)$db->query("
            SELECT COUNT(*)
            FROM agents a
            LEFT JOIN agents p ON p.id = a.parent_id
            WHERE
                (a.level = 3 AND (a.position_type IS NULL OR a.position_type <> 'agent_candidate') AND a.parent_id IS NOT NULL)
                OR (a.level = 3 AND a.position_type = 'agent_candidate' AND (p.level <> 3 OR p.position_type = 'agent_candidate' OR p.id IS NULL))
                OR (a.level = 2 AND COALESCE(p.level, 0) <> 3)
                OR (a.level = 1 AND COALESCE(p.level, 0) NOT IN (2, 3))
        ")->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $rows = $db->query('SELECT id, parent_id FROM agents')->fetchAll(PDO::FETCH_ASSOC);
        $parents = [];
        foreach ($rows as $row) {
            $parents[(int)$row['id']] = !empty($row['parent_id']) ? (int)$row['parent_id'] : null;
        }
        foreach (array_keys($parents) as $id) {
            $seen = [];
            $current = $id;
            while (!empty($parents[$current])) {
                $current = (int)$parents[$current];
                if (isset($seen[$current])) {
                    $summary['cycles']++;
                    break;
                }
                $seen[$current] = true;
            }
        }
    } catch (Throwable $e) {}

    return $summary;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT id, agent_name, person_name, agent_code, level, parent_id, position_type, position_label FROM agents WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                $message = '対象メンバーが見つかりません。';
                $msgType = 'error';
            } else {
                $level = (int)($target['level'] ?? 1);
                $roleKey = function_exists('getAgentRoleKey') ? getAgentRoleKey($target) : '';
                $newParentId = ($level === 3 && $roleKey !== 'agent_candidate') ? null : ((int)($_POST['parent_id'] ?? 0) ?: null);
                $errors = parentLinkValidate($db, $id, $level, $newParentId, $labels, $target['position_type'] ?? null);

                if ($errors) {
                    $message = implode(' ', $errors);
                    $msgType = 'error';
                } else {
                    $oldParentId = !empty($target['parent_id']) ? (int)$target['parent_id'] : null;
                    $reason = trim((string)($_POST['change_reason'] ?? ''));
                    if ($reason !== '') {
                        $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 200) : substr($reason, 0, 200);
                    }

                    if ($oldParentId === $newParentId) {
                        $message = '変更はありませんでした。';
                    } else {
                        $db->prepare('UPDATE agents SET parent_id = ? WHERE id = ?')->execute([$newParentId, $id]);
                        parentLinkLog($db, $id, $oldParentId, $newParentId, $level, $reason);
                        $syncOk = syncAgentToExternalPartner($id, 'parent_updated');
                        $changedAgentCode = (string)($target['agent_code'] ?? '');
                        $message = '親子紐づけを変更しました。' . ($syncOk ? '' : ' 外部連携先への送信は未完了の可能性があります。外部連携ログを確認してください。');
                        $msgType = $syncOk ? 'success' : 'warning';
                    }
                }
            }
        } catch (Throwable $e) {
            $message = '親子紐づけの変更に失敗しました: ' . $e->getMessage();
            $msgType = 'error';
        }
    }
}

$q = sanitizeInput($_GET['q'] ?? '');
$levelFilter = (int)($_GET['level'] ?? 0);
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR a.email LIKE ? OR p.agent_name LIKE ? OR p.person_name LIKE ?)';
    $kw = '%' . $q . '%';
    array_push($params, $kw, $kw, $kw, $kw, $kw, $kw);
}
if (in_array($levelFilter, [1, 2, 3], true)) {
    $where[] = 'a.level = ?';
    $params[] = $levelFilter;
}
if (in_array($statusFilter, ['active', 'inactive'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM agents a LEFT JOIN agents p ON a.parent_id = p.id $whereSql");
$countStmt->execute($params);
$pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);

$stmt = $db->prepare("
    SELECT a.id, a.agent_code, a.agent_name, a.person_name, a.email, a.level, a.parent_id, a.status, a.position_type, a.position_label,
           p.agent_name AS parent_name, p.person_name AS parent_person_name, p.agent_code AS parent_code, p.level AS parent_level, p.position_type AS parent_position_type, p.position_label AS parent_position_label,
           (SELECT COUNT(*) FROM agents c WHERE c.parent_id = a.id) AS child_count
    FROM agents a
    LEFT JOIN agents p ON a.parent_id = p.id
    $whereSql
    ORDER BY a.level DESC, a.agent_name ASC, a.person_name ASC
    LIMIT $perPage OFFSET {$pag['offset']}
");
$stmt->execute($params);
$agents = $stmt->fetchAll();
$integrity = parentLinkIntegritySummary($db);
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php if ($changedAgentCode !== ''): ?>
<div class="card" style="background:rgba(63,191,127,.08);">
    <p class="card-title">変更後の確認</p>
    <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;margin:0 0 .75rem;">
        組織図で対象者の位置が正しく変わっているか確認してください。
    </p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="/admin/organization_map.php?q=<?= h(rawurlencode($changedAgentCode)) ?>" class="btn btn-outline">組織図で確認する</a>
        <a href="/admin/integration_logs.php?event_type=parent_updated&q=<?= h(rawurlencode($changedAgentCode)) ?>" class="btn btn-outline">外部連携ログを見る</a>
        <a href="/admin/integration_logs.php?event_type=parent_updated&direction=outbound&success=0&q=<?= h(rawurlencode($changedAgentCode)) ?>" class="btn btn-outline">失敗ログだけ見る</a>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
        <div>
            <p class="card-title">親子紐づけ変更</p>
            <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;margin:0;">
                「BさんがAさん配下になっているが、実際はCさん配下だった」というケースを修正します。
                権限やLP設定は変更せず、上位の紐づけだけを変更します。
                エージェント候補はエージェントと同じく、ディレクターの上位に設定できます。
            </p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a href="/admin/organization_map.php" class="btn btn-outline">組織図を見る</a>
            <a href="/admin/action_logs.php?action_type=parent_update" class="btn btn-outline">変更履歴を見る</a>
            <a href="/admin/integration_logs.php?event_type=parent_updated" class="btn btn-outline">外部連携ログを見る</a>
            <a href="/admin/integration_logs.php?event_type=parent_updated&direction=outbound&success=0" class="btn btn-outline">外部連携の失敗確認</a>
        </div>
    </div>
</div>

<div class="card" style="background:rgba(201,168,76,.07);">
    <p class="card-title">外部連携の確認ポイント</p>
    <ul style="margin:0;color:var(--text-muted);line-height:1.9;font-size:.9rem;">
        <li>親子紐づけを変更すると、外部連携先へ <code>parent_updated</code> イベントを送信します。</li>
        <li>エージェント候補はエージェント配下に置き、ディレクターを配下に持てます。</li>
        <li>送信に失敗した場合は、外部連携ログ画面の「再送」または「10件再送」から再送できます。</li>
        <li>外部サービス側にも階層が反映されるため、変更前に影響範囲を確認してください。</li>
    </ul>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
        <div>
            <p class="card-title">紐づけ整合性チェック</p>
            <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;margin:0;">
                親子関係の孤立・区分不一致・循環がないかを確認します。
            </p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <span class="badge <?= ((int)$integrity['orphans'] === 0) ? 'badge-active' : 'badge-inactive' ?>">孤立 <?= (int)$integrity['orphans'] ?></span>
            <span class="badge <?= ((int)$integrity['invalid_levels'] === 0) ? 'badge-active' : 'badge-inactive' ?>">区分不一致 <?= (int)$integrity['invalid_levels'] ?></span>
            <span class="badge <?= ((int)$integrity['cycles'] === 0) ? 'badge-active' : 'badge-inactive' ?>">循環 <?= (int)$integrity['cycles'] ?></span>
        </div>
    </div>
</div>

<div class="card">
    <form method="get" style="display:grid;grid-template-columns:minmax(220px,1fr) minmax(150px,220px) minmax(150px,220px) auto;gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>検索</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="名前・担当者・コード・メール・現在の上位で検索">
        </div>
        <div class="form-group" style="margin:0;">
            <label>区分</label>
            <select name="level">
                <option value="0">すべて</option>
                <option value="3" <?= $levelFilter === 3 ? 'selected' : '' ?>><?= h($labels[3] ?? 'エージェント') ?></option>
                <option value="2" <?= $levelFilter === 2 ? 'selected' : '' ?>><?= h($labels[2] ?? 'ディレクター') ?></option>
                <option value="1" <?= $levelFilter === 1 ? 'selected' : '' ?>><?= h($labels[1] ?? 'アドバイザー') ?></option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>状態</label>
            <select name="status">
                <option value="">すべて</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>公開中</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>停止中</option>
            </select>
        </div>
        <div style="display:flex;gap:.5rem;">
            <button type="submit" class="btn btn-gold">表示</button>
            <?php if ($q !== '' || $levelFilter || $statusFilter !== ''): ?><a href="/admin/agent_parent_links.php" class="btn btn-outline">クリア</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card" style="padding:0;">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>対象メンバー</th>
                    <th>区分</th>
                    <th>現在の上位</th>
                    <th>影響範囲</th>
                    <th>新しい上位</th>
                    <th>理由</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($agents): foreach ($agents as $agent): ?>
                <?php
                $level = (int)($agent['level'] ?? 1);
                $roleKey = function_exists('getAgentRoleKey') ? getAgentRoleKey($agent) : '';
                $roleLabel = function_exists('getAgentRoleLabel') ? getAgentRoleLabel($agent) : ($labels[$level] ?? ('Lv.' . $level));
                $candidates = parentLinkCandidates($db, (int)$agent['id'], $level, $agent['position_type'] ?? null);
                $currentParentLabel = !empty($agent['parent_id'])
                    ? trim(($agent['parent_name'] ?? '') . ' / ' . ($agent['parent_person_name'] ?? '') . ' / ' . ($agent['parent_code'] ?? ''))
                    : '本部直属';
                $impact = parentLinkImpactStats($db, (int)$agent['id'], (int)($agent['child_count'] ?? 0));
                $canChangeParent = !($level === 3 && $roleKey !== 'agent_candidate');
                ?>
                <tr>
                    <td>
                        <strong><?= h($agent['agent_name']) ?></strong><br>
                        <span style="font-size:.82rem;color:var(--text-muted);"><?= h($agent['person_name']) ?> / <?= h($agent['agent_code']) ?></span>
                    </td>
                    <td><?= h($roleLabel) ?></td>
                    <td><?= h($currentParentLabel) ?></td>
                    <td style="min-width:190px;">
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                            <span class="badge">直下 <?= (int)$impact['direct_children'] ?>名</span>
                            <span class="badge">全配下 <?= (int)$impact['descendants'] ?>名</span>
                            <span class="badge">PV <?= number_format((int)$impact['pv']) ?></span>
                            <span class="badge">問合せ <?= number_format((int)$impact['leads']) ?></span>
                            <?php if ((int)$impact['new_leads'] > 0): ?>
                                <span class="badge badge-inactive">未対応 <?= number_format((int)$impact['new_leads']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if (!$canChangeParent): ?>
                            <span style="color:var(--text-muted);">本部直属</span>
                        <?php else: ?>
                            <form id="parent-link-form-<?= (int)$agent['id'] ?>" method="post" onsubmit="return confirm('親子紐づけを変更します。配下表示・活動集計・外部連携先の階層にも反映されます。よろしいですか？')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$agent['id'] ?>">
                                <select name="parent_id" required>
                                    <option value="">選択してください</option>
                                    <?php foreach ($candidates as $candidate): ?>
                                    <option value="<?= (int)$candidate['id'] ?>" <?= ((int)($agent['parent_id'] ?? 0) === (int)$candidate['id']) ? 'selected' : '' ?>>
                                        [<?= h(function_exists('getAgentRoleLabel') ? getAgentRoleLabel($candidate) : ($labels[(int)$candidate['level']] ?? ('Lv.' . $candidate['level']))) ?>] <?= h($candidate['agent_name']) ?> / <?= h($candidate['person_name']) ?> / <?= h($candidate['agent_code']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$canChangeParent): ?>
                            -
                        <?php else: ?>
                            <input form="parent-link-form-<?= (int)$agent['id'] ?>" type="text" name="change_reason" maxlength="200" placeholder="例: 登録時の紹介者修正" style="min-width:210px;">
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$canChangeParent): ?>
                            <span style="color:var(--text-muted);font-size:.85rem;">変更不要</span>
                        <?php else: ?>
                            <button form="parent-link-form-<?= (int)$agent['id'] ?>" type="submit" class="btn btn-gold btn-sm">変更する</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2.5rem;">対象メンバーが見つかりません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
        <?php
        $query = ['page' => $i, 'q' => $q, 'level' => $levelFilter, 'status' => $statusFilter];
        $href = '/admin/agent_parent_links.php?' . http_build_query($query);
        ?>
        <?= $i === $page ? '<span class="current">' . $i . '</span>' : '<a href="' . h($href) . '">' . $i . '</a>' ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<div class="card" style="background:rgba(201,168,76,.07);">
    <p class="card-title">変更ルール</p>
    <ul style="margin:0;color:var(--text-muted);line-height:1.9;font-size:.9rem;">
        <li><?= h($labels[2] ?? 'ディレクター') ?>の上位は<?= h($labels[3] ?? 'エージェント') ?>のみ選択できます。</li>
        <li><?= h($labels[1] ?? 'アドバイザー') ?>の上位は<?= h($labels[2] ?? 'ディレクター') ?>または<?= h($labels[3] ?? 'エージェント') ?>を選択できます。</li>
        <li>自分自身や自分の配下メンバーを上位にすることはできません。</li>
        <li>一覧の「影響範囲」で、変更対象の直下人数・全配下人数・PV・問い合わせ数を確認してから変更してください。</li>
        <li>変更内容は操作ログに記録され、外部連携先へも親子紐づけ変更イベントを送信します。</li>
    </ul>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

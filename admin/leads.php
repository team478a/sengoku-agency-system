<?php
$pageTitle = '問い合わせ管理';
require_once __DIR__ . '/header.php';

$db   = getDB();
$csrf = getCsrfToken();
$msg  = '';

function adminLeadColumns(PDO $db): array {
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('Admin lead column check failed: ' . $e->getMessage());
    }
    return $columns;
}

function adminLeadStatusLabels(): array {
    return [
        'new' => '新規',
        'contacted' => '対応中',
        'prospect' => '成約見込み',
        'won' => '成約',
        'lost' => '失注',
        'closed' => '対応済',
    ];
}

function adminLeadBadgeClass(string $status): string {
    if (in_array($status, ['contacted', 'prospect', 'won'], true)) {
        return 'contacted';
    }
    if (in_array($status, ['lost', 'closed'], true)) {
        return 'closed';
    }
    return 'new';
}

function adminLeadLog(PDO $db, string $action, int $leadId, array $details = []): void {
    try {
        $stmt = $db->prepare("INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_hash) VALUES (?, ?, 'lead', ?, ?, ?)");
        $stmt->execute([
            (int)($_SESSION['admin_id'] ?? 0) ?: null,
            $action,
            $leadId,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('admin lead log failed: ' . $e->getMessage());
    }
}

$leadColumns = adminLeadColumns($db);
$leadHasProject = !empty($leadColumns['project_id']);
$projects = getProjects(true);
$statusLabels = adminLeadStatusLabels();
$allowedStatuses = array_keys($statusLabels);

// ── ステータス更新 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $id     = (int)$_POST['id'];
        $status = $_POST['status'] ?? '';
        if (in_array($status, $allowedStatuses, true)) {
            $sets = ['status=?'];
            $params = [$status];
            if (!empty($leadColumns['internal_note'])) {
                $sets[] = 'internal_note=?';
                $params[] = trim((string)($_POST['internal_note'] ?? ''));
            }
            if (!empty($leadColumns['next_action_at'])) {
                $nextActionAt = trim((string)($_POST['next_action_at'] ?? ''));
                $sets[] = 'next_action_at=?';
                $params[] = $nextActionAt !== '' ? $nextActionAt : null;
            }
            $params[] = $id;
            $db->prepare("UPDATE leads SET " . implode(',', $sets) . " WHERE id = ?")->execute($params);
            adminLeadLog($db, 'update_status', $id, ['status' => $status]);
            $msg = '問い合わせ情報を更新しました。';
        }
    }
}

// ── 削除 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT l.id, l.name, l.email, l.agent_id, a.agent_name
            FROM leads l
            LEFT JOIN agents a ON l.agent_id = a.id
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        $leadForDelete = $stmt->fetch();
        if ($leadForDelete) {
            $db->prepare("DELETE FROM leads WHERE id = ?")->execute([$id]);
            adminLeadLog($db, 'delete', $id, [
                'name' => $leadForDelete['name'] ?? '',
                'email' => $leadForDelete['email'] ?? '',
                'agent_id' => $leadForDelete['agent_id'] ?? null,
                'agent_name' => $leadForDelete['agent_name'] ?? '',
            ]);
            header('Location: /admin/leads.php?deleted=1');
            exit;
        }
        $msg = '対象の問い合わせが見つかりません。';
    } else {
        $msg = '不正なリクエストです。';
    }
}

if (isset($_GET['deleted'])) {
    $msg = '問い合わせを削除しました。';
}

// ── 詳細表示 ──
$detailLead = null;
if (isset($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT l.*, a.agent_name, a.person_name, a.email AS agent_email" . ($leadHasProject ? ", p.name AS project_name" : "") . "
        FROM leads l
        JOIN agents a ON l.agent_id = a.id
        " . ($leadHasProject ? "LEFT JOIN projects p ON l.project_id = p.id" : "") . "
        WHERE l.id = ?
    ");
    $stmt->execute([(int)$_GET['id']]);
    $detailLead = $stmt->fetch() ?: null;
}

// ── フィルタ・一覧 ──
$filterStatus = $_GET['status'] ?? '';
$filterAgent  = (int)($_GET['agent_id'] ?? 0);
$filterProject = (int)($_GET['project_id'] ?? 0);
$search       = sanitizeInput($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$wheres = [];
$params = [];
if ($filterStatus && in_array($filterStatus, $allowedStatuses, true)) {
    $wheres[] = "l.status = ?"; $params[] = $filterStatus;
}
if ($filterAgent) {
    $wheres[] = "l.agent_id = ?"; $params[] = $filterAgent;
}
if ($leadHasProject && $filterProject) {
    $wheres[] = "l.project_id = ?"; $params[] = $filterProject;
}
if ($search) {
    $wheres[] = "(l.name LIKE ? OR l.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$whereClause = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM leads l $whereClause");
$countStmt->execute($params);
$pag = paginate($countStmt->fetchColumn(), $perPage, $page);

$listStmt = $db->prepare("
    SELECT l.*, a.agent_name, a.person_name" . ($leadHasProject ? ", p.name AS project_name" : "") . "
    FROM leads l JOIN agents a ON l.agent_id = a.id
    " . ($leadHasProject ? "LEFT JOIN projects p ON l.project_id = p.id" : "") . "
    $whereClause
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET {$pag['offset']}
");
$listStmt->execute($params);
$leads = $listStmt->fetchAll();

$agentList = $db->query("SELECT id, agent_name FROM agents ORDER BY agent_name")->fetchAll();

?>

<?php if ($msg): ?>
<div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>

<!-- 詳細モーダル風 -->
<?php if ($detailLead): ?>
<div class="card" style="border-color:var(--gold);">
    <p class="card-title">問い合わせ詳細 #<?= $detailLead['id'] ?></p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div><p style="font-size:.78rem;color:var(--text-muted);">氏名</p><p><?= h($detailLead['name']) ?></p></div>
        <div><p style="font-size:.78rem;color:var(--text-muted);">メール</p><p><a href="mailto:<?= h($detailLead['email']) ?>" style="color:var(--gold);"><?= h($detailLead['email']) ?></a></p></div>
        <div><p style="font-size:.78rem;color:var(--text-muted);">電話</p><p><?= h($detailLead['phone'] ?: '—') ?></p></div>
        <div><p style="font-size:.78rem;color:var(--text-muted);">担当アドバイザー</p><p><?= h($detailLead['agent_name']) ?>（<?= h($detailLead['person_name']) ?>）</p></div>
        <?php if ($leadHasProject): ?>
        <div><p style="font-size:.78rem;color:var(--text-muted);">プロジェクト</p><p><?= h($detailLead['project_name'] ?? '未設定') ?></p></div>
        <?php endif; ?>
        <div><p style="font-size:.78rem;color:var(--text-muted);">受付日時</p><p><?= h($detailLead['created_at']) ?></p></div>
        <div><p style="font-size:.78rem;color:var(--text-muted);">状態</p>
            <form method="post" style="display:inline-flex;gap:.5rem;align-items:center;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?= $detailLead['id'] ?>">
                <select name="status" style="padding:.3rem .6rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:4px;color:var(--paper);font-family:inherit;font-size:.85rem;">
                    <?php foreach ($statusLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $detailLead['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-gold btn-sm">更新</button>
            </form>
        </div>
        <?php if (!empty($leadColumns['next_action_at'])): ?>
        <div><p style="font-size:.78rem;color:var(--text-muted);">次回対応日</p>
            <input type="date" name="next_action_at" form="lead-detail-form" value="<?= h($detailLead['next_action_at'] ?? '') ?>"
                   style="padding:.35rem .55rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:4px;color:var(--paper);font-family:inherit;font-size:.85rem;">
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($leadColumns['internal_note'])): ?>
    <form id="lead-detail-form" method="post" style="margin-bottom:1rem;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= $detailLead['id'] ?>">
        <input type="hidden" name="status" value="<?= h($detailLead['status']) ?>">
        <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem;">管理メモ</p>
        <textarea name="internal_note" style="min-height:90px;"><?= h($detailLead['internal_note'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-gold btn-sm" style="margin-top:.6rem;">メモを保存</button>
    </form>
    <?php endif; ?>
    <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem;">お問い合わせ内容</p>
    <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:4px;padding:1rem;font-size:.9rem;white-space:pre-wrap;line-height:1.8;"><?= h($detailLead['message']) ?></div>
    <div style="margin-top:1rem;">
        <a href="/admin/leads.php" class="btn btn-outline btn-sm">← 一覧に戻る</a>
        <form method="post" style="display:inline-block;margin-left:.5rem;" onsubmit="return confirm('この問い合わせを削除しますか？この操作は元に戻せません。');">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$detailLead['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">削除</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- フィルタバー -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center;">
    <form method="get" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="氏名・メールで検索"
            style="padding:.45rem .8rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:4px;color:var(--paper);font-family:inherit;font-size:.85rem;">
        <select name="status" style="padding:.45rem .7rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:4px;color:var(--paper);font-family:inherit;font-size:.85rem;">
            <option value="">すべての状態</option>
            <?php foreach ($statusLabels as $k => $v): ?>
            <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <select name="agent_id" style="padding:.45rem .7rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:4px;color:var(--paper);font-family:inherit;font-size:.85rem;">
            <option value="">すべてのアドバイザー</option>
            <?php foreach ($agentList as $ag): ?>
            <option value="<?= $ag['id'] ?>" <?= $filterAgent === (int)$ag['id'] ? 'selected' : '' ?>><?= h($ag['agent_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($leadHasProject): ?>
        <select name="project_id" style="padding:.45rem .7rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:4px;color:var(--paper);font-family:inherit;font-size:.85rem;">
            <option value="">すべてのプロジェクト</option>
            <?php foreach ($projects as $project): ?>
            <option value="<?= (int)$project['id'] ?>" <?= $filterProject === (int)$project['id'] ? 'selected' : '' ?>><?= h($project['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="btn btn-outline btn-sm">絞り込み</button>
        <a href="/admin/leads.php" class="btn btn-outline btn-sm">リセット</a>
        <a href="/admin/export_csv.php?type=leads&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&agent_id=<?= (int)$filterAgent ?>&project_id=<?= (int)$filterProject ?>" class="btn btn-outline btn-sm">CSV出力</a>
    </form>
    <span style="font-size:.82rem;color:var(--text-muted);margin-left:auto;">全<?= $pag['total'] ?>件</span>
</div>

<!-- 一覧テーブル -->
<div class="card table-scroll" style="padding:0;">
    <table>
        <thead>
            <tr>
                <th>受付日時</th>
                <th>氏名</th>
                <th>メール</th>
                <th>電話</th>
                <?php if ($leadHasProject): ?><th>プロジェクト</th><?php endif; ?>
                <th>担当アドバイザー</th>
                <th>状態</th>
                <th>次回対応</th>
                <th>管理メモ</th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($leads): ?>
        <?php foreach ($leads as $lead): ?>
            <tr>
                <td style="font-size:.82rem;color:var(--text-muted);white-space:nowrap;"><?= h(date('m/d H:i', strtotime($lead['created_at']))) ?></td>
                <td><?= h($lead['name']) ?></td>
                <td style="font-size:.85rem;"><a href="mailto:<?= h($lead['email']) ?>" style="color:var(--gold);text-decoration:none;"><?= h($lead['email']) ?></a></td>
                <td style="font-size:.85rem;"><?= h($lead['phone'] ?: '—') ?></td>
                <?php if ($leadHasProject): ?><td style="font-size:.82rem;color:var(--gold);"><?= h($lead['project_name'] ?? '未設定') ?></td><?php endif; ?>
                <td style="font-size:.85rem;"><?= h($lead['agent_name']) ?></td>
                <td><span class="badge badge-<?= h(adminLeadBadgeClass((string)$lead['status'])) ?>"><?= h($statusLabels[$lead['status']] ?? $lead['status']) ?></span></td>
                <td style="font-size:.82rem;color:var(--text-muted);"><?= !empty($lead['next_action_at']) ? h(date('m/d', strtotime($lead['next_action_at']))) : '—' ?></td>
                <td style="font-size:.82rem;color:var(--text-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h(mb_strimwidth($lead['internal_note'] ?? '', 0, 40, '…')) ?></td>
                <td><a href="/admin/leads.php?id=<?= $lead['id'] ?>" class="btn btn-outline btn-sm">詳細</a></td>
                <td>
                    <form method="post" onsubmit="return confirm('この問い合わせを削除しますか？この操作は元に戻せません。');">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">削除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="<?= $leadHasProject ? 11 : 10 ?>" style="text-align:center;color:var(--text-muted);padding:3rem;">該当する問い合わせはありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ページネーション -->
<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
        <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
        <?php else: ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&agent_id=<?= $filterAgent ?>&project_id=<?= $filterProject ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

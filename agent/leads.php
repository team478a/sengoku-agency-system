<?php
$pageTitle = '問い合わせ一覧';
require_once __DIR__ . '/header.php';

$db = getDB();
$aid = (int)$currentAgent['id'];
$myLv = (int)($currentAgent['level'] ?? 1);

$visibleAgentIds = [$aid];
if ($myLv >= 2) {
    foreach (getAllDescendants($aid) as $descendant) {
        $visibleAgentIds[] = (int)$descendant['id'];
    }
}
$visibleAgentIds = array_values(array_unique($visibleAgentIds));
$visiblePlaceholders = implode(',', array_fill(0, count($visibleAgentIds), '?'));

function agentLeadColumns(PDO $db): array {
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('Lead column check failed: ' . $e->getMessage());
    }
    return $columns;
}

function leadStatusLabels(): array {
    return [
        'new' => '新着',
        'contacted' => '対応中',
        'prospect' => '成約見込み',
        'won' => '成約',
        'lost' => '失注',
        'closed' => '完了',
    ];
}

function leadBadgeClass(string $status): string {
    if (in_array($status, ['won', 'prospect', 'contacted'], true)) {
        return 'badge-contacted';
    }
    if (in_array($status, ['lost', 'closed'], true)) {
        return 'badge-closed';
    }
    return 'badge-new';
}

$leadColumns = agentLeadColumns($db);
$leadHasProject = !empty($leadColumns['project_id']);
$projects = getProjects(true);
$statusLabels = leadStatusLabels();
$allowedStatuses = array_keys($statusLabels);
$csrf = getCsrfToken();

function leadListUrl(array $override = []): string {
    global $selectedAgentProjectId;
    $params = array_merge([
        'status' => $_GET['status'] ?? 'all',
        'project_id' => $_GET['project_id'] ?? (!empty($selectedAgentProjectId) ? (int)$selectedAgentProjectId : ''),
        'sort' => $_GET['sort'] ?? '',
        'dir' => $_GET['dir'] ?? '',
    ], $override);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 'all');
    $query = http_build_query($params);
    return '/agent/leads.php' . ($query !== '' ? '?' . $query : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if ($action === 'delete' && !empty($_POST['lead_id'])) {
            $params = array_merge([(int)$_POST['lead_id']], $visibleAgentIds);
            $db->prepare("DELETE FROM leads WHERE id=? AND agent_id IN ($visiblePlaceholders)")
               ->execute($params);
        } elseif ($action === 'update' && !empty($_POST['lead_id'])) {
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, $allowedStatuses, true)) {
                $sets = ['status=?'];
                $params = [$newStatus];
                if (!empty($leadColumns['internal_note'])) {
                    $sets[] = 'internal_note=?';
                    $params[] = trim((string)($_POST['internal_note'] ?? ''));
                }
                if (!empty($leadColumns['next_action_at'])) {
                    $nextActionAt = trim((string)($_POST['next_action_at'] ?? ''));
                    $sets[] = 'next_action_at=?';
                    $params[] = $nextActionAt !== '' ? $nextActionAt : null;
                }
                $params = array_merge($params, [(int)$_POST['lead_id']], $visibleAgentIds);
                $db->prepare("UPDATE leads SET " . implode(',', $sets) . " WHERE id=? AND agent_id IN ($visiblePlaceholders)")
                   ->execute($params);
            }
        }
    }
    header('Location: ' . leadListUrl());
    exit;
}

$allowedFilters = array_merge(['all'], $allowedStatuses);
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, $allowedFilters, true)) $statusFilter = 'all';
$sort = $_GET['sort'] ?? '';
$dir = strtolower((string)($_GET['dir'] ?? 'asc'));
if ($sort !== 'status') $sort = '';
if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'asc';

$params = $visibleAgentIds;
$whereParts = [];
if ($statusFilter !== 'all') {
    $whereParts[] = 'l.status = ?';
    $params[] = $statusFilter;
}
$filterProject = (int)($_GET['project_id'] ?? (!empty($selectedAgentProjectId) ? (int)$selectedAgentProjectId : 0));
if ($leadHasProject && $filterProject) {
    $whereParts[] = 'l.project_id = ?';
    $params[] = $filterProject;
}
$where = $whereParts ? 'AND ' . implode(' AND ', $whereParts) : '';
$orderBy = 'l.created_at DESC';
if ($sort === 'status') {
    $statusOrder = "FIELD(l.status, 'new', 'contacted', 'prospect', 'won', 'lost', 'closed')";
    $orderBy = $statusOrder . ' ' . strtoupper($dir) . ', l.created_at DESC';
}
$leads = $db->prepare("
    SELECT l.*, a.agent_name AS source_agent_name, a.agent_code AS source_agent_code, a.person_name AS source_person_name" . ($leadHasProject ? ", p.name AS project_name" : "") . "
    FROM leads l
    LEFT JOIN agents a ON l.agent_id = a.id
    " . ($leadHasProject ? "LEFT JOIN projects p ON l.project_id = p.id" : "") . "
    WHERE l.agent_id IN ($visiblePlaceholders) $where
    ORDER BY $orderBy
");
$leads->execute($params);
$leads = $leads->fetchAll();

$countProjectSql = ($leadHasProject && $filterProject) ? ' AND project_id=?' : '';
$countProjectParams = ($leadHasProject && $filterProject) ? [$filterProject] : [];
$counts = $db->prepare("SELECT status, COUNT(*) FROM leads WHERE agent_id IN ($visiblePlaceholders) $countProjectSql GROUP BY status");
$counts->execute(array_merge($visibleAgentIds, $countProjectParams));
$counts = $counts->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- フィルタ -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
  <?php if ($leadHasProject): ?>
  <a href="<?= h(leadListUrl(['project_id' => ''])) ?>"
     style="padding:.4rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
            background:<?= $filterProject===0?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
            border:1px solid <?= $filterProject===0?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
            color:<?= $filterProject===0?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">全プロジェクト</a>
  <?php foreach ($projects as $project): ?>
  <a href="<?= h(leadListUrl(['project_id' => (int)$project['id']])) ?>"
     style="padding:.4rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
            background:<?= $filterProject===(int)$project['id']?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
            border:1px solid <?= $filterProject===(int)$project['id']?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
            color:<?= $filterProject===(int)$project['id']?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;"><?= h($project['name']) ?></a>
  <?php endforeach; ?>
  <span style="flex-basis:100%;height:0;"></span>
  <?php endif; ?>
  <?php foreach (['all'=>'すべて'] + $statusLabels as $s=>$l): ?>
  <a href="<?= h(leadListUrl(['status' => $s])) ?>"
     style="padding:.4rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
            background:<?= $statusFilter===$s?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
            border:1px solid <?= $statusFilter===$s?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
            color:<?= $statusFilter===$s?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">
    <?= $l ?><?= isset($counts[$s]) ? " ({$counts[$s]})" : ($s==='all' ? ' ('.array_sum($counts).')' : '') ?>
  </a>
  <?php endforeach; ?>
  <a href="/agent/export_csv.php?type=leads&status=<?= h($statusFilter) ?>&project_id=<?= (int)$filterProject ?>" class="btn btn-outline" style="font-size:.78rem;padding:.4rem .9rem;margin-left:auto;">CSV出力</a>
</div>

<?php
$nextStatusDir = ($sort === 'status' && $dir === 'asc') ? 'desc' : 'asc';
$statusSortMark = $sort === 'status' ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
?>
<div class="card table-scroll" style="padding:0;">
  <table>
    <thead><tr><th>流入LP</th><?php if ($leadHasProject): ?><th>プロジェクト</th><?php endif; ?><th>名前</th><th>メール</th><th>電話</th><th>内容</th><th>メモ</th><th>次回対応</th><th>日時</th><th><a href="<?= h(leadListUrl(['sort' => 'status', 'dir' => $nextStatusDir])) ?>" style="color:inherit;text-decoration:none;">状態<?= h($statusSortMark) ?></a></th><th>操作</th></tr></thead>
    <tbody>
    <?php if ($leads): foreach ($leads as $l): ?>
    <tr>
      <td style="font-size:.78rem;">
        <div style="font-weight:700;color:var(--gold-lt);"><?= h($l['source_agent_name'] ?: '-') ?></div>
        <?php if (!empty($l['source_agent_code'])): ?>
        <a href="/a/<?= h($l['source_agent_code']) ?>" target="_blank" style="color:var(--gold);text-decoration:none;">/a/<?= h($l['source_agent_code']) ?> ↗</a>
        <?php endif; ?>
      </td>
      <?php if ($leadHasProject): ?><td style="font-size:.78rem;color:var(--gold);"><?= h($l['project_name'] ?? '未設定') ?></td><?php endif; ?>
      <td style="font-weight:700;"><?= h($l['name']) ?></td>
      <td style="font-size:.8rem;"><a href="mailto:<?= h($l['email']) ?>" style="color:var(--gold);text-decoration:none;"><?= h($l['email']) ?></a></td>
      <td style="font-size:.8rem;"><?= h($l['phone'] ?: '—') ?></td>
      <td style="font-size:.8rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= h($l['message']) ?>"><?= h(mb_strimwidth($l['message'] ?? '', 0, 40, '…')) ?></td>
      <td style="font-size:.78rem;min-width:180px;">
        <?php if (!empty($leadColumns['internal_note'])): ?>
        <textarea name="internal_note" form="lead-form-<?= (int)$l['id'] ?>" style="min-height:54px;padding:.45rem .55rem;font-size:.75rem;"><?= h($l['internal_note'] ?? '') ?></textarea>
        <?php else: ?>
        <span style="color:var(--text-muted);">未対応</span>
        <?php endif; ?>
      </td>
      <td style="font-size:.78rem;min-width:130px;">
        <?php if (!empty($leadColumns['next_action_at'])): ?>
        <input type="date" name="next_action_at" form="lead-form-<?= (int)$l['id'] ?>" value="<?= h($l['next_action_at'] ?? '') ?>" style="padding:.35rem .45rem;font-size:.75rem;">
        <?php else: ?>
        <span style="color:var(--text-muted);">未対応</span>
        <?php endif; ?>
      </td>
      <td style="font-size:.75rem;color:var(--text-muted);"><?= date('m/d H:i', strtotime($l['created_at'])) ?></td>
      <td>
        <form id="lead-form-<?= (int)$l['id'] ?>" method="post" style="display:flex;gap:.3rem;align-items:center;flex-wrap:wrap;">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
          <select name="status"
                  style="padding:.3rem .5rem;font-size:.75rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:2px;color:var(--cream);">
            <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $l['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-gold" style="font-size:.72rem;padding:.32rem .55rem;">保存</button>
        </form>
      </td>
      <td>
        <form method="post" onsubmit="return confirm('この問い合わせを削除しますか？この操作は元に戻せません。');">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
          <button class="btn btn-danger" style="font-size:.72rem;padding:.32rem .55rem;">削除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; else: ?>
    <tr><td colspan="<?= $leadHasProject ? 11 : 10 ?>" style="text-align:center;color:var(--text-muted);padding:3rem;">問い合わせはまだありません。</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

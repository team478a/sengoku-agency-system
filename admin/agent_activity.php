<?php
$pageTitle = '代理店活動';
require_once __DIR__ . '/header.php';

$db = getDB();
$labels = getLevelLabels();
$projects = getProjects(false);

$allowedPeriods = ['today' => 0, '7d' => 7, '30d' => 30, '90d' => 90, 'all' => null];
$period = $_GET['period'] ?? '30d';
if (!array_key_exists($period, $allowedPeriods)) $period = '30d';
$periodLabels = ['today' => '今日', '7d' => '7日', '30d' => '30日', '90d' => '90日', 'all' => '全期間'];
$days = $allowedPeriods[$period];

$q = sanitizeInput($_GET['q'] ?? '');
$level = (int)($_GET['level'] ?? 0);
$status = sanitizeInput($_GET['status'] ?? '');
$projectId = (int)($_GET['project_id'] ?? 0);
$sort = sanitizeInput($_GET['sort'] ?? 'leads');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$selectedProject = null;
foreach ($projects as $project) {
    if ((int)$project['id'] === $projectId) {
        $selectedProject = $project;
        break;
    }
}

$accessHasProject = tableHasColumn('access_logs', 'project_id');
$leadHasProject = tableHasColumn('leads', 'project_id');

$dateSql = '';
$dateParams = [];
if ($period === 'today') {
    $dateSql = ' AND created_at >= CURDATE()';
} elseif ($days !== null) {
    $dateSql = ' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $dateParams[] = $days;
}

$accessProjectSql = ($accessHasProject && $projectId > 0) ? ' AND project_id=?' : '';
$accessProjectParams = ($accessHasProject && $projectId > 0) ? [$projectId] : [];
$leadProjectSql = ($leadHasProject && $projectId > 0) ? ' AND project_id=?' : '';
$leadProjectParams = ($leadHasProject && $projectId > 0) ? [$projectId] : [];

$where = [];
$whereParams = [];
$joinProject = '';
if ($projectId > 0) {
    $joinProject = 'LEFT JOIN agent_project_templates apt_filter ON apt_filter.agent_id=a.id AND apt_filter.project_id=? LEFT JOIN lp_templates t_filter ON t_filter.id=a.default_template_id';
    $whereParams[] = $projectId;
    $where[] = '(apt_filter.project_id IS NOT NULL OR t_filter.project_id=?)';
    $whereParams[] = $projectId;
}
if ($q !== '') {
    $where[] = '(a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR a.email LIKE ? OR a.login_email LIKE ?)';
    $kw = '%' . $q . '%';
    array_push($whereParams, $kw, $kw, $kw, $kw, $kw);
}
if (isset($labels[$level])) {
    $where[] = 'a.level=?';
    $whereParams[] = $level;
}
if (in_array($status, ['active', 'inactive'], true)) {
    $where[] = 'a.status=?';
    $whereParams[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sortMap = [
    'leads' => 'leads DESC, pv DESC',
    'pv' => 'pv DESC, leads DESC',
    'line' => 'line_clicks DESC, pv DESC',
    'new' => 'new_leads DESC, leads DESC',
    'cv' => 'conversion DESC, leads DESC',
    'last_access' => 'last_access IS NULL ASC, last_access DESC',
    'last_login' => 'last_login IS NULL ASC, last_login DESC',
    'name' => 'a.agent_name ASC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['leads'];

$pvSub = "SELECT agent_id, COUNT(*) AS pv, MAX(created_at) AS last_access FROM access_logs WHERE type='pv' $accessProjectSql $dateSql GROUP BY agent_id";
$lineSub = "SELECT agent_id, COUNT(*) AS line_clicks FROM access_logs WHERE type='line_click' $accessProjectSql $dateSql GROUP BY agent_id";
$leadSub = "SELECT agent_id, COUNT(*) AS leads, SUM(status='new') AS new_leads, SUM(status='prospect') AS prospects, SUM(status='won') AS won, MAX(created_at) AS last_lead FROM leads WHERE 1=1 $leadProjectSql $dateSql GROUP BY agent_id";
$loginSub = "SELECT user_id AS agent_id, MAX(created_at) AS last_login FROM login_logs WHERE user_type='agent' AND success=1 GROUP BY user_id";

$countStmt = $db->prepare("SELECT COUNT(DISTINCT a.id) FROM agents a $joinProject $whereSql");
$countStmt->execute($whereParams);
$pag = paginate((int)$countStmt->fetchColumn(), $perPage, $page);

$params = array_merge(
    $accessProjectParams, $dateParams,
    $accessProjectParams, $dateParams,
    $leadProjectParams, $dateParams,
    $whereParams
);
$stmt = $db->prepare("
    SELECT
        a.*,
        parent.agent_name AS parent_name,
        parent.agent_code AS parent_code,
        COALESCE(pv.pv, 0) AS pv,
        COALESCE(lc.line_clicks, 0) AS line_clicks,
        COALESCE(ld.leads, 0) AS leads,
        COALESCE(ld.new_leads, 0) AS new_leads,
        COALESCE(ld.prospects, 0) AS prospects,
        COALESCE(ld.won, 0) AS won,
        pv.last_access,
        ld.last_lead,
        lg.last_login,
        CASE WHEN COALESCE(pv.pv,0) > 0 THEN ROUND((COALESCE(ld.leads,0) / COALESCE(pv.pv,0)) * 100, 2) ELSE NULL END AS conversion
    FROM agents a
    LEFT JOIN agents parent ON parent.id=a.parent_id
    LEFT JOIN ($pvSub) pv ON pv.agent_id=a.id
    LEFT JOIN ($lineSub) lc ON lc.agent_id=a.id
    LEFT JOIN ($leadSub) ld ON ld.agent_id=a.id
    LEFT JOIN ($loginSub) lg ON lg.agent_id=a.id
    $joinProject
    $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET {$pag['offset']}
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$statsParams = array_merge(
    $accessProjectParams, $dateParams,
    $accessProjectParams, $dateParams,
    $leadProjectParams, $dateParams,
    $whereParams
);
$statsStmt = $db->prepare("
    SELECT
        COUNT(DISTINCT a.id) AS agent_count,
        SUM(COALESCE(pv.pv,0)) AS pv_total,
        SUM(COALESCE(lc.line_clicks,0)) AS line_total,
        SUM(COALESCE(ld.leads,0)) AS lead_total,
        SUM(COALESCE(ld.new_leads,0)) AS new_total
    FROM agents a
    LEFT JOIN ($pvSub) pv ON pv.agent_id=a.id
    LEFT JOIN ($lineSub) lc ON lc.agent_id=a.id
    LEFT JOIN ($leadSub) ld ON ld.agent_id=a.id
    $joinProject
    $whereSql
");
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch() ?: [];

$csvQuery = $_GET;
$csvQuery['type'] = 'agent_activity';
$csvUrl = '/admin/export_csv.php?' . http_build_query($csvQuery);
$baseQuery = ['period' => $period, 'q' => $q, 'level' => $level, 'status' => $status, 'project_id' => $projectId, 'sort' => $sort];

function adminActivityDate(?string $date): string {
    if (!$date) return '—';
    return date('Y/m/d H:i', strtotime($date));
}
?>

<div class="card">
    <p class="card-title">代理店活動</p>
    <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>検索</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="代理店名・担当者・コード">
        </div>
        <div class="form-group" style="margin:0;">
            <label>期間</label>
            <select name="period">
                <?php foreach ($periodLabels as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $period === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>プロジェクト</label>
            <select name="project_id">
                <option value="0">すべて</option>
                <?php foreach ($projects as $project): ?>
                <option value="<?= (int)$project['id'] ?>" <?= $projectId === (int)$project['id'] ? 'selected' : '' ?>><?= h($project['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>区分</label>
            <select name="level">
                <option value="0">すべて</option>
                <?php foreach ($labels as $lv => $label): ?>
                <option value="<?= (int)$lv ?>" <?= $level === (int)$lv ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>状態</label>
            <select name="status">
                <option value="">すべて</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>公開中</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>停止</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>並び順</label>
            <select name="sort">
                <option value="leads" <?= $sort === 'leads' ? 'selected' : '' ?>>問い合わせ順</option>
                <option value="pv" <?= $sort === 'pv' ? 'selected' : '' ?>>PV順</option>
                <option value="line" <?= $sort === 'line' ? 'selected' : '' ?>>LINE順</option>
                <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>未対応順</option>
                <option value="cv" <?= $sort === 'cv' ? 'selected' : '' ?>>CV率順</option>
                <option value="last_access" <?= $sort === 'last_access' ? 'selected' : '' ?>>最終アクセス順</option>
                <option value="last_login" <?= $sort === 'last_login' ? 'selected' : '' ?>>最終ログイン順</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>名前順</option>
            </select>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-gold">表示</button>
            <a href="/admin/agent_activity.php" class="btn btn-outline">クリア</a>
            <a href="<?= h($csvUrl) ?>" class="btn btn-outline">CSV出力</a>
        </div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card"><p class="stat-label">代理店数</p><p class="stat-val"><?= number_format((int)($stats['agent_count'] ?? 0)) ?></p></div>
    <div class="stat-card"><p class="stat-label">PV</p><p class="stat-val"><?= number_format((int)($stats['pv_total'] ?? 0)) ?></p></div>
    <div class="stat-card"><p class="stat-label">LINE</p><p class="stat-val"><?= number_format((int)($stats['line_total'] ?? 0)) ?></p></div>
    <div class="stat-card"><p class="stat-label">問い合わせ</p><p class="stat-val"><?= number_format((int)($stats['lead_total'] ?? 0)) ?></p></div>
    <div class="stat-card"><p class="stat-label">未対応</p><p class="stat-val"><?= number_format((int)($stats['new_total'] ?? 0)) ?></p></div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">活動一覧</p>
        <span style="font-size:.78rem;color:var(--text-muted);">全 <?= number_format((int)$pag['total']) ?> 件</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>代理店</th><th>区分</th><th>上位</th><th>PV</th><th>LINE</th><th>問い合わせ</th><th>未対応</th><th>CV率</th><th>最終アクセス</th><th>最終問い合わせ</th><th>最終ログイン</th><th>状態</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <strong><?= h($row['agent_name']) ?></strong>
                        <span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($row['person_name']) ?> / <?= h($row['agent_code']) ?></span>
                    </td>
                    <td><?= h($labels[(int)$row['level']] ?? ('Lv.' . (int)$row['level'])) ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);">
                        <?= h($row['parent_name'] ?? '本部直属') ?>
                        <?php if (!empty($row['parent_code'])): ?><span style="display:block;"><?= h($row['parent_code']) ?></span><?php endif; ?>
                    </td>
                    <td><?= number_format((int)$row['pv']) ?></td>
                    <td><?= number_format((int)$row['line_clicks']) ?></td>
                    <td style="font-weight:700;color:var(--gold-lt);"><?= number_format((int)$row['leads']) ?></td>
                    <td style="font-weight:700;color:<?= (int)$row['new_leads'] > 0 ? '#e0a040' : 'inherit' ?>;"><?= number_format((int)$row['new_leads']) ?></td>
                    <td><?= $row['conversion'] === null ? '—' : h($row['conversion'] . '%') ?></td>
                    <td style="white-space:nowrap;font-size:.76rem;color:var(--text-muted);"><?= h(adminActivityDate($row['last_access'] ?? null)) ?></td>
                    <td style="white-space:nowrap;font-size:.76rem;color:var(--text-muted);"><?= h(adminActivityDate($row['last_lead'] ?? null)) ?></td>
                    <td style="white-space:nowrap;font-size:.76rem;color:var(--text-muted);"><?= h(adminActivityDate($row['last_login'] ?? null)) ?></td>
                    <td><span class="badge <?= $row['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= $row['status'] === 'active' ? '公開中' : '停止' ?></span></td>
                    <td style="white-space:nowrap;">
                        <a href="<?= h(buildAgentProjectLpUrl($row['agent_code'], $selectedProject)) ?>" target="_blank" class="btn btn-outline btn-sm">LP</a>
                        <a href="/admin/agents.php?edit=<?= (int)$row['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                        <a href="/admin/leads.php?agent_id=<?= (int)$row['id'] ?>" class="btn btn-outline btn-sm">問い合わせ</a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="13" style="text-align:center;color:var(--text-muted);padding:2.5rem;">該当する活動データはありません。</td></tr>
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

<?php require_once __DIR__ . '/footer.php'; ?>

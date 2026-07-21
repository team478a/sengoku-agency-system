<?php
$pageTitle = '活動レポート';
require_once __DIR__ . '/header.php';

$db = getDB();
$aid = (int)$currentAgent['id'];
$myLv = (int)($currentAgent['level'] ?? 1);
$labels = getLevelLabels();

$allowedPeriods = ['today' => 0, '7d' => 7, '30d' => 30, 'all' => null];
$period = $_GET['period'] ?? '30d';
if (!array_key_exists($period, $allowedPeriods)) $period = '30d';
$days = $allowedPeriods[$period];
$periodLabels = ['today' => '今日', '7d' => '7日', '30d' => '30日', 'all' => '全期間'];

$baseUrl = function_exists('getSiteBaseUrl')
    ? getSiteBaseUrl()
    : rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''), '/');
$lpUrl = $selectedAgentProjectLpUrl ?: buildAgentProjectLpUrl((string)$currentAgent['agent_code'], null);
$reportProjectId = !empty($selectedAgentProjectId) ? (int)$selectedAgentProjectId : 0;
$reportAccessHasProject = tableHasColumn('access_logs', 'project_id');
$reportLeadHasProject = tableHasColumn('leads', 'project_id');
$accessProjectWhere = ($reportAccessHasProject && $reportProjectId > 0) ? 'AND project_id=?' : '';
$accessProjectParams = ($reportAccessHasProject && $reportProjectId > 0) ? [$reportProjectId] : [];
$leadProjectWhere = ($reportLeadHasProject && $reportProjectId > 0) ? 'AND project_id=?' : '';
$leadProjectParams = ($reportLeadHasProject && $reportProjectId > 0) ? [$reportProjectId] : [];

$descendants = $myLv >= 2 ? getAllDescendants($aid) : [];
$visibleAgentIds = array_values(array_unique(array_merge([$aid], array_map(static fn($a) => (int)$a['id'], $descendants))));
$ph = implode(',', array_fill(0, count($visibleAgentIds), '?'));

$dateSql = '';
$dateParams = [];
if ($period === 'today') {
    $dateSql = ' AND created_at >= CURDATE()';
} elseif ($days !== null) {
    $dateSql = ' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $dateParams[] = $days;
}

function reportCount(PDO $db, string $table, array $agentIds, string $extraWhere = '', array $extraParams = [], string $dateSql = '', array $dateParams = []): int {
    $ph = implode(',', array_fill(0, count($agentIds), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE agent_id IN ($ph) {$extraWhere} {$dateSql}");
    $stmt->execute(array_merge($agentIds, $extraParams, $dateParams));
    return (int)$stmt->fetchColumn();
}

function reportTableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Report table check failed: ' . $e->getMessage());
        return false;
    }
}

function reportCountForIds(PDO $db, string $table, array $agentIds, string $extraWhere = '', array $extraParams = [], string $dateSql = '', array $dateParams = []): int {
    if (!$agentIds) return 0;
    return reportCount($db, $table, $agentIds, $extraWhere, $extraParams, $dateSql, $dateParams);
}

$myIds = [$aid];
$myPv = reportCount($db, 'access_logs', $myIds, trim("AND type='pv' $accessProjectWhere"), $accessProjectParams, $dateSql, $dateParams);
$myLine = reportCount($db, 'access_logs', $myIds, trim("AND type='line_click' $accessProjectWhere"), $accessProjectParams, $dateSql, $dateParams);
$myLeads = reportCount($db, 'leads', $myIds, $leadProjectWhere, $leadProjectParams, $dateSql, $dateParams);
$myNewLeads = reportCount($db, 'leads', $myIds, trim("AND status='new' $leadProjectWhere"), $leadProjectParams, $dateSql, $dateParams);

$totalPv = reportCount($db, 'access_logs', $visibleAgentIds, trim("AND type='pv' $accessProjectWhere"), $accessProjectParams, $dateSql, $dateParams);
$totalLine = reportCount($db, 'access_logs', $visibleAgentIds, trim("AND type='line_click' $accessProjectWhere"), $accessProjectParams, $dateSql, $dateParams);
$totalLeads = reportCount($db, 'leads', $visibleAgentIds, $leadProjectWhere, $leadProjectParams, $dateSql, $dateParams);
$totalNewLeads = reportCount($db, 'leads', $visibleAgentIds, trim("AND status='new' $leadProjectWhere"), $leadProjectParams, $dateSql, $dateParams);

$subRows = [];
if ($myLv >= 2 && $descendants) {
    $descIds = array_map(static fn($a) => (int)$a['id'], $descendants);
    $descPh = implode(',', array_fill(0, count($descIds), '?'));

    $pvStmt = $db->prepare("
        SELECT agent_id, COUNT(*) AS cnt, MAX(created_at) AS last_at
        FROM access_logs
        WHERE agent_id IN ($descPh) AND type='pv' $accessProjectWhere $dateSql
        GROUP BY agent_id
    ");
    $pvStmt->execute(array_merge($descIds, $accessProjectParams, $dateParams));
    $pvMap = [];
    foreach ($pvStmt->fetchAll() as $row) $pvMap[(int)$row['agent_id']] = $row;

    $lineStmt = $db->prepare("
        SELECT agent_id, COUNT(*) AS cnt
        FROM access_logs
        WHERE agent_id IN ($descPh) AND type='line_click' $accessProjectWhere $dateSql
        GROUP BY agent_id
    ");
    $lineStmt->execute(array_merge($descIds, $accessProjectParams, $dateParams));
    $lineMap = [];
    foreach ($lineStmt->fetchAll() as $row) $lineMap[(int)$row['agent_id']] = (int)$row['cnt'];

    $leadStmt = $db->prepare("
        SELECT agent_id, COUNT(*) AS cnt, SUM(status='new') AS new_cnt, MAX(created_at) AS last_at
        FROM leads
        WHERE agent_id IN ($descPh) $leadProjectWhere $dateSql
        GROUP BY agent_id
    ");
    $leadStmt->execute(array_merge($descIds, $leadProjectParams, $dateParams));
    $leadMap = [];
    foreach ($leadStmt->fetchAll() as $row) $leadMap[(int)$row['agent_id']] = $row;

    foreach ($descendants as $agent) {
        $id = (int)$agent['id'];
        $pv = (int)($pvMap[$id]['cnt'] ?? 0);
        $leads = (int)($leadMap[$id]['cnt'] ?? 0);
        $subRows[] = [
            'id' => $id,
            'level' => (int)($agent['level'] ?? 1),
            'agent_name' => $agent['agent_name'] ?? '',
            'person_name' => $agent['person_name'] ?? '',
            'agent_code' => $agent['agent_code'] ?? '',
            'status' => $agent['status'] ?? '',
            'pv' => $pv,
            'line' => (int)($lineMap[$id] ?? 0),
            'leads' => $leads,
            'new_leads' => (int)($leadMap[$id]['new_cnt'] ?? 0),
            'last_access' => $pvMap[$id]['last_at'] ?? null,
            'last_lead' => $leadMap[$id]['last_at'] ?? null,
            'conversion' => $pv > 0 ? round(($leads / $pv) * 100, 1) : null,
        ];
    }

    usort($subRows, static fn($a, $b) => [$b['leads'], $b['pv']] <=> [$a['leads'], $a['pv']]);
}

$needsFollow = array_values(array_filter($subRows, static function($row) {
    return $row['new_leads'] > 0 || $row['pv'] === 0 || ($row['pv'] >= 20 && $row['leads'] === 0);
}));

$rankByPv = $subRows;
usort($rankByPv, static fn($a, $b) => $b['pv'] <=> $a['pv']);
$rankByLeads = $subRows;
usort($rankByLeads, static fn($a, $b) => $b['leads'] <=> $a['leads']);

$directBranchRows = [];
$recruitmentRows = [];
if ($myLv >= 2 && $descendants) {
    $childrenByParent = [];
    foreach ($descendants as $agent) {
        $childrenByParent[(int)($agent['parent_id'] ?? 0)][] = $agent;
    }
    $collectBranchIds = static function(int $rootId) use (&$collectBranchIds, &$childrenByParent): array {
        $ids = [$rootId];
        foreach ($childrenByParent[$rootId] ?? [] as $child) {
            $ids = array_merge($ids, $collectBranchIds((int)$child['id']));
        }
        return $ids;
    };

    foreach ($childrenByParent[$aid] ?? [] as $direct) {
        $branchIds = array_values(array_unique($collectBranchIds((int)$direct['id'])));
        $branchPv = reportCountForIds($db, 'access_logs', $branchIds, trim("AND type='pv' $accessProjectWhere"), $accessProjectParams, $dateSql, $dateParams);
        $branchLeads = reportCountForIds($db, 'leads', $branchIds, $leadProjectWhere, $leadProjectParams, $dateSql, $dateParams);
        $branchNewLeads = reportCountForIds($db, 'leads', $branchIds, trim("AND status='new' $leadProjectWhere"), $leadProjectParams, $dateSql, $dateParams);
        $branchApplicants = 0;
        $branchPendingApplicants = 0;
        try {
            $branchPh = implode(',', array_fill(0, count($branchIds), '?'));
            $appStmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE agent_id IN ($branchPh) $dateSql");
            $appStmt->execute(array_merge($branchIds, $dateParams));
            $branchApplicants = (int)$appStmt->fetchColumn();
            $pendingStmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE agent_id IN ($branchPh) AND status='pending' $dateSql");
            $pendingStmt->execute(array_merge($branchIds, $dateParams));
            $branchPendingApplicants = (int)$pendingStmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Branch applicant summary failed: ' . $e->getMessage());
        }
        $directBranchRows[] = [
            'id' => (int)$direct['id'],
            'level' => (int)($direct['level'] ?? 1),
            'agent_name' => $direct['agent_name'] ?? '',
            'person_name' => $direct['person_name'] ?? '',
            'agent_code' => $direct['agent_code'] ?? '',
            'member_count' => count($branchIds),
            'pv' => $branchPv,
            'leads' => $branchLeads,
            'new_leads' => $branchNewLeads,
            'applicants' => $branchApplicants,
            'pending_applicants' => $branchPendingApplicants,
            'conversion' => $branchPv > 0 ? round(($branchLeads / $branchPv) * 100, 1) : null,
        ];
    }
    usort($directBranchRows, static fn($a, $b) => [$b['new_leads'], $b['leads'], $b['pv']] <=> [$a['new_leads'], $a['leads'], $a['pv']]);
}

if ($myLv >= 2 && reportTableExists($db, 'recruitment_links')) {
    $rlPh = implode(',', array_fill(0, count($visibleAgentIds), '?'));
    $rlStmt = $db->prepare("
        SELECT rl.*,
               a.agent_name AS owner_name,
               a.person_name AS owner_person_name,
               a.level AS owner_level,
               COUNT(ap.id) AS applicant_count,
               SUM(CASE WHEN ap.status='pending' THEN 1 ELSE 0 END) AS pending_count,
               SUM(CASE WHEN ap.status='approved' THEN 1 ELSE 0 END) AS approved_count
        FROM recruitment_links rl
        INNER JOIN agents a ON a.id = rl.agent_id
        LEFT JOIN applicants ap ON ap.recruitment_link_id = rl.id
        WHERE rl.agent_id IN ($rlPh)
        GROUP BY rl.id
        ORDER BY rl.created_at DESC
        LIMIT 30
    ");
    $rlStmt->execute($visibleAgentIds);
    $recruitmentRows = $rlStmt->fetchAll();
}
?>

<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <?php foreach ($periodLabels as $key => $label): ?>
    <a href="?period=<?= h($key) ?>" class="btn <?= $period === $key ? 'btn-gold' : 'btn-outline' ?>" style="font-size:.78rem;padding:.45rem .85rem;"><?= h($label) ?></a>
    <?php endforeach; ?>
</div>
<?php if (!empty($selectedAgentProject)): ?>
<div class="card" style="padding:.8rem 1rem;margin-bottom:1rem;">
    <p style="font-size:.8rem;color:var(--text-muted);">表示中プロジェクト: <strong style="color:var(--gold-lt);"><?= h($selectedAgentProject['name'] ?? '') ?></strong></p>
</div>
<?php endif; ?>

<div class="card">
    <p class="card-title">自分のLP URL</p>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
        <code style="background:rgba(201,168,76,.08);border:1px solid var(--border);padding:.55rem .85rem;border-radius:3px;font-size:.86rem;color:var(--gold-lt);flex:1;min-width:0;word-break:break-all;"><?= h($lpUrl) ?></code>
        <a href="<?= h($lpUrl) ?>" target="_blank" class="btn btn-outline" style="font-size:.82rem;padding:.55rem .9rem;">確認</a>
        <button type="button" onclick="navigator.clipboard.writeText('<?= h($lpUrl) ?>').then(()=>{this.textContent='コピー済';setTimeout(()=>this.textContent='コピー',1600)})" class="btn btn-outline" style="font-size:.82rem;padding:.55rem .9rem;">コピー</button>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.25rem;">
    <?php foreach ([
        ['自分のPV', $myPv, '👁', ''],
        ['自分の問い合わせ', $myLeads, '📩', ''],
        ['自分のLINEクリック', $myLine, '💬', ''],
        ['自分の未対応', $myNewLeads, '🔔', $myNewLeads > 0 ? 'color:#e0a040' : ''],
    ] as [$label, $value, $icon, $style]): ?>
    <div class="card" style="text-align:center;padding:1.15rem .75rem;margin-bottom:0;">
        <p style="font-size:1.45rem;"><?= $icon ?></p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.75rem;font-weight:900;color:var(--gold-lt);margin:.25rem 0;<?= $style ?>"><?= number_format((int)$value) ?></p>
        <p style="font-size:.72rem;color:var(--text-muted);"><?= h($label) ?></p>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($myLv >= 2): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.25rem;">
    <?php foreach ([
        ['全体PV', $totalPv, '👁', ''],
        ['全体問い合わせ', $totalLeads, '📩', ''],
        ['全体LINEクリック', $totalLine, '💬', ''],
        ['全体未対応', $totalNewLeads, '🔔', $totalNewLeads > 0 ? 'color:#e0a040' : ''],
    ] as [$label, $value, $icon, $style]): ?>
    <div class="card" style="text-align:center;padding:1.15rem .75rem;margin-bottom:0;">
        <p style="font-size:1.45rem;"><?= $icon ?></p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.75rem;font-weight:900;color:var(--gold-lt);margin:.25rem 0;<?= $style ?>"><?= number_format((int)$value) ?></p>
        <p style="font-size:.72rem;color:var(--text-muted);"><?= h($label) ?></p>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($needsFollow): ?>
<div class="card">
    <p class="card-title">要フォロー</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;">
        <?php foreach (array_slice($needsFollow, 0, 6) as $row): ?>
        <div style="border:1px solid var(--border);border-radius:4px;padding:.85rem;background:rgba(201,168,76,.05);">
            <p style="font-weight:700;color:var(--gold-lt);font-size:.88rem;"><?= h($row['agent_name']) ?></p>
            <p style="font-size:.75rem;color:var(--text-muted);margin-top:.15rem;"><?= h($labels[$row['level']] ?? '') ?> / <?= h($row['person_name']) ?></p>
            <p style="font-size:.78rem;margin-top:.5rem;line-height:1.7;">
                <?php if ($row['new_leads'] > 0): ?><span style="color:#e0a040;">未対応 <?= (int)$row['new_leads'] ?>件</span><?php endif; ?>
                <?php if ($row['pv'] === 0): ?><span style="color:#e08080;">アクセスなし</span><?php endif; ?>
                <?php if ($row['pv'] >= 20 && $row['leads'] === 0): ?><span style="color:#e0a040;">PVあり・問い合わせなし</span><?php endif; ?>
            </p>
            <div style="display:flex;gap:.4rem;margin-top:.65rem;">
                <a href="/a/<?= h($row['agent_code']) ?>" target="_blank" class="btn btn-outline" style="font-size:.74rem;padding:.35rem .65rem;">LP</a>
                <a href="/agent/leads.php" class="btn btn-outline" style="font-size:.74rem;padding:.35rem .65rem;">問い合わせ</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($directBranchRows): ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <p class="card-title" style="margin:0;border:none;padding:0;">直下グループ別サマリー</p>
        <span style="font-size:.75rem;color:var(--text-muted);"><?= h($periodLabels[$period]) ?>集計</span>
    </div>
    <table>
        <thead>
            <tr><th>責任者</th><th>区分</th><th>配下数</th><th>PV</th><th>問い合わせ</th><th>未対応</th><th>応募</th><th>未承認応募</th><th>CV率</th><th>LP</th></tr>
        </thead>
        <tbody>
        <?php foreach ($directBranchRows as $row): ?>
            <tr>
                <td>
                    <div style="font-weight:700;color:var(--gold-lt);"><?= h($row['agent_name']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted);"><?= h($row['person_name']) ?></div>
                </td>
                <td style="font-size:.75rem;color:var(--text-muted);"><?= h($labels[$row['level']] ?? '') ?></td>
                <td><?= number_format((int)$row['member_count']) ?></td>
                <td><?= number_format((int)$row['pv']) ?></td>
                <td><?= number_format((int)$row['leads']) ?></td>
                <td style="font-weight:700;color:<?= $row['new_leads'] > 0 ? '#e0a040' : 'inherit' ?>;"><?= number_format((int)$row['new_leads']) ?></td>
                <td><?= number_format((int)$row['applicants']) ?></td>
                <td style="font-weight:700;color:<?= $row['pending_applicants'] > 0 ? '#e0a040' : 'inherit' ?>;"><?= number_format((int)$row['pending_applicants']) ?></td>
                <td><?= $row['conversion'] === null ? '―' : h($row['conversion'] . '%') ?></td>
                <td style="font-size:.76rem;"><a href="/a/<?= h($row['agent_code']) ?>" target="_blank" style="color:var(--gold);text-decoration:none;">確認</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($recruitmentRows): ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <p class="card-title" style="margin:0;border:none;padding:0;">招待URL別成果</p>
        <a href="/agent/recruitment_links.php" class="btn btn-outline" style="font-size:.75rem;padding:.35rem .75rem;">招待URL管理</a>
    </div>
    <table>
        <thead>
            <tr><th>所有者</th><th>募集名</th><th>対象</th><th>クリック</th><th>応募</th><th>未承認</th><th>承認済</th><th>状態</th><th>期限</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recruitmentRows as $row):
            $targetLabel = (int)$row['target_level'] === 1
                ? getAdvisorPositionLabel($row['position_type'] ?? null, $row['position_label'] ?? null)
                : ($labels[(int)$row['target_level']] ?? 'ディレクター');
            $isExpired = !empty($row['expires_at']) && strtotime($row['expires_at']) < time();
        ?>
            <tr>
                <td>
                    <div style="font-weight:700;color:var(--gold-lt);"><?= h($row['owner_name']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted);"><?= h($labels[(int)$row['owner_level']] ?? '') ?></div>
                </td>
                <td style="font-weight:700;"><?= h($row['name']) ?></td>
                <td style="font-size:.78rem;color:var(--gold-lt);"><?= h($targetLabel) ?></td>
                <td><?= number_format((int)$row['click_count']) ?></td>
                <td><?= number_format((int)$row['applicant_count']) ?></td>
                <td style="font-weight:700;color:<?= (int)$row['pending_count'] > 0 ? '#e0a040' : 'inherit' ?>;"><?= number_format((int)$row['pending_count']) ?></td>
                <td><?= number_format((int)$row['approved_count']) ?></td>
                <td>
                    <?php if ($isExpired): ?>
                    <span class="badge" style="background:rgba(139,26,26,.2);color:#e08080;">期限切れ</span>
                    <?php elseif ($row['status'] === 'active'): ?>
                    <span class="badge badge-contacted">有効</span>
                    <?php else: ?>
                    <span class="badge badge-closed">停止中</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.75rem;color:var(--text-muted);"><?= $row['expires_at'] ? h(date('Y/m/d', strtotime($row['expires_at']))) : '無期限' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:1.25rem;">
    <div class="card" style="margin-bottom:0;">
        <p class="card-title">PVランキング</p>
        <?php foreach (array_slice($rankByPv, 0, 5) as $idx => $row): ?>
        <div style="display:flex;justify-content:space-between;gap:.75rem;padding:.55rem 0;border-bottom:1px solid var(--border);">
            <span style="font-size:.82rem;"><?= $idx + 1 ?>. <?= h($row['agent_name']) ?></span>
            <strong style="color:var(--gold-lt);"><?= number_format($row['pv']) ?></strong>
        </div>
        <?php endforeach; ?>
        <?php if (!$rankByPv): ?><p style="font-size:.82rem;color:var(--text-muted);">配下データはまだありません。</p><?php endif; ?>
    </div>
    <div class="card" style="margin-bottom:0;">
        <p class="card-title">問い合わせランキング</p>
        <?php foreach (array_slice($rankByLeads, 0, 5) as $idx => $row): ?>
        <div style="display:flex;justify-content:space-between;gap:.75rem;padding:.55rem 0;border-bottom:1px solid var(--border);">
            <span style="font-size:.82rem;"><?= $idx + 1 ?>. <?= h($row['agent_name']) ?></span>
            <strong style="color:var(--gold-lt);"><?= number_format($row['leads']) ?></strong>
        </div>
        <?php endforeach; ?>
        <?php if (!$rankByLeads): ?><p style="font-size:.82rem;color:var(--text-muted);">配下データはまだありません。</p><?php endif; ?>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">配下活動一覧</p>
        <span style="font-size:.75rem;color:var(--text-muted);"><?= h($periodLabels[$period]) ?>集計</span>
    </div>
    <table>
        <thead>
            <tr><th>区分</th><th>名前</th><th>LP</th><th>PV</th><th>LINE</th><th>問い合わせ</th><th>未対応</th><th>CV率</th><th>最終アクセス</th><th>最終問い合わせ</th><th>状態</th></tr>
        </thead>
        <tbody>
        <?php if ($subRows): foreach ($subRows as $row): ?>
            <tr>
                <td style="font-size:.75rem;color:var(--text-muted);"><?= h($labels[$row['level']] ?? '') ?></td>
                <td>
                    <div style="font-weight:700;color:var(--gold-lt);"><?= h($row['agent_name']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted);"><?= h($row['person_name']) ?></div>
                </td>
                <td style="font-size:.76rem;"><a href="/a/<?= h($row['agent_code']) ?>" target="_blank" style="color:var(--gold);text-decoration:none;">/a/<?= h($row['agent_code']) ?></a></td>
                <td><?= number_format($row['pv']) ?></td>
                <td><?= number_format($row['line']) ?></td>
                <td><?= number_format($row['leads']) ?></td>
                <td style="color:<?= $row['new_leads'] > 0 ? '#e0a040' : 'inherit' ?>;font-weight:700;"><?= number_format($row['new_leads']) ?></td>
                <td><?= $row['conversion'] === null ? '—' : h($row['conversion'] . '%') ?></td>
                <td style="font-size:.75rem;color:var(--text-muted);"><?= $row['last_access'] ? h(date('m/d H:i', strtotime($row['last_access']))) : '—' ?></td>
                <td style="font-size:.75rem;color:var(--text-muted);"><?= $row['last_lead'] ? h(date('m/d H:i', strtotime($row['last_lead']))) : '—' ?></td>
                <td><span class="badge badge-<?= $row['status'] === 'active' ? 'contacted' : 'closed' ?>"><?= $row['status'] === 'active' ? '公開中' : '停止中' ?></span></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:2.5rem;">配下メンバーはまだいません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php
$pageTitle = 'LP成果分析';
require_once __DIR__ . '/header.php';

$db = getDB();

$allowedPeriods = ['7d' => 7, '30d' => 30, '90d' => 90, 'all' => null];
$period = $_GET['period'] ?? '30d';
if (!array_key_exists($period, $allowedPeriods)) {
    $period = '30d';
}
$days = $allowedPeriods[$period];
$periodLabels = ['7d' => '7日', '30d' => '30日', '90d' => '90日', 'all' => '全期間'];

function adminTemplateReportColumns(PDO $db, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('Template report column check failed: ' . $e->getMessage());
    }
    return $cache[$table] = $columns;
}

$accessColumns = adminTemplateReportColumns($db, 'access_logs');
$leadColumns = adminTemplateReportColumns($db, 'leads');
$accessTemplateExpr = !empty($accessColumns['template_id']) ? 'COALESCE(al.template_id, a.default_template_id)' : 'a.default_template_id';
$leadTemplateExpr = !empty($leadColumns['template_id']) ? 'COALESCE(l.template_id, a.default_template_id)' : 'a.default_template_id';

$dateSqlAccess = '';
$dateSqlLead = '';
$dateParamsAccess = [];
$dateParamsLead = [];
if ($days !== null) {
    $dateSqlAccess = ' AND al.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $dateSqlLead = ' AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $dateParamsAccess[] = $days;
    $dateParamsLead[] = $days;
}

$templates = $db->query("
    SELECT t.*,
           COUNT(DISTINCT a.id) AS active_agent_count
    FROM lp_templates t
    LEFT JOIN agents a ON a.default_template_id = t.id AND a.status='active'
    GROUP BY t.id
    ORDER BY t.sort_order ASC, t.id ASC
")->fetchAll();

$pvMap = [];
$pvStmt = $db->prepare("
    SELECT {$accessTemplateExpr} AS template_id, COUNT(*) AS cnt
    FROM access_logs al
    LEFT JOIN agents a ON a.id = al.agent_id
    WHERE al.type='pv' {$dateSqlAccess}
    GROUP BY {$accessTemplateExpr}
");
$pvStmt->execute($dateParamsAccess);
foreach ($pvStmt->fetchAll() as $row) {
    $pvMap[(int)$row['template_id']] = (int)$row['cnt'];
}

$lineMap = [];
$lineStmt = $db->prepare("
    SELECT {$accessTemplateExpr} AS template_id, COUNT(*) AS cnt
    FROM access_logs al
    LEFT JOIN agents a ON a.id = al.agent_id
    WHERE al.type='line_click' {$dateSqlAccess}
    GROUP BY {$accessTemplateExpr}
");
$lineStmt->execute($dateParamsAccess);
foreach ($lineStmt->fetchAll() as $row) {
    $lineMap[(int)$row['template_id']] = (int)$row['cnt'];
}

$leadMap = [];
$leadStmt = $db->prepare("
    SELECT {$leadTemplateExpr} AS template_id,
           COUNT(*) AS cnt,
           SUM(CASE WHEN l.status='new' THEN 1 ELSE 0 END) AS new_cnt,
           SUM(CASE WHEN l.status='prospect' THEN 1 ELSE 0 END) AS prospect_cnt,
           SUM(CASE WHEN l.status='won' THEN 1 ELSE 0 END) AS won_cnt
    FROM leads l
    LEFT JOIN agents a ON a.id = l.agent_id
    WHERE 1=1 {$dateSqlLead}
    GROUP BY {$leadTemplateExpr}
");
$leadStmt->execute($dateParamsLead);
foreach ($leadStmt->fetchAll() as $row) {
    $leadMap[(int)$row['template_id']] = $row;
}

$rows = [];
foreach ($templates as $tpl) {
    $id = (int)$tpl['id'];
    $pv = $pvMap[$id] ?? 0;
    $leads = (int)($leadMap[$id]['cnt'] ?? 0);
    $rows[] = [
        'template' => $tpl,
        'agents' => (int)$tpl['active_agent_count'],
        'pv' => $pv,
        'line' => $lineMap[$id] ?? 0,
        'leads' => $leads,
        'new_leads' => (int)($leadMap[$id]['new_cnt'] ?? 0),
        'prospects' => (int)($leadMap[$id]['prospect_cnt'] ?? 0),
        'won' => (int)($leadMap[$id]['won_cnt'] ?? 0),
        'conversion' => $pv > 0 ? round(($leads / $pv) * 100, 2) : null,
    ];
}
usort($rows, static fn($a, $b) => [$b['leads'], $b['pv']] <=> [$a['leads'], $a['pv']]);

$totalPv = array_sum(array_column($rows, 'pv'));
$totalLeads = array_sum(array_column($rows, 'leads'));
$totalWon = array_sum(array_column($rows, 'won'));
?>

<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <?php foreach ($periodLabels as $key => $label): ?>
    <a href="?period=<?= h($key) ?>" class="btn <?= $period === $key ? 'btn-gold' : 'btn-outline' ?>" style="font-size:.78rem;padding:.45rem .85rem;"><?= h($label) ?></a>
    <?php endforeach; ?>
    <a href="/admin/export_csv.php?type=template_reports&period=<?= h($period) ?>" class="btn btn-outline" style="font-size:.78rem;padding:.45rem .85rem;margin-left:auto;">CSV出力</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <p class="stat-label">PV</p>
        <p class="stat-val"><?= number_format($totalPv) ?></p>
        <p class="stat-sub"><?= h($periodLabels[$period]) ?>集計</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">問い合わせ</p>
        <p class="stat-val"><?= number_format($totalLeads) ?></p>
        <p class="stat-sub">CV率 <?= $totalPv > 0 ? h(round(($totalLeads / $totalPv) * 100, 2) . '%') : '—' ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-label">成約</p>
        <p class="stat-val"><?= number_format($totalWon) ?></p>
        <p class="stat-sub">問い合わせから <?= $totalLeads > 0 ? h(round(($totalWon / $totalLeads) * 100, 2) . '%') : '—' ?></p>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <p class="card-title" style="margin:0;border:none;padding:0;">テンプレート別成果</p>
        <span style="font-size:.75rem;color:var(--text-muted);">LPを切り替えた後の成果は、切り替え時点のテンプレートに記録されます。</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>テンプレート</th><th>使用中</th><th>PV</th><th>LINE</th><th>問い合わせ</th><th>未対応</th><th>見込み</th><th>成約</th><th>CV率</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $row): $tpl = $row['template']; ?>
            <tr>
                <td>
                    <div style="font-weight:700;color:var(--gold-lt);"><?= h($tpl['name']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted);"><?= h($tpl['slug']) ?> / <?= h($tpl['html_file']) ?></div>
                </td>
                <td><?= number_format($row['agents']) ?></td>
                <td><?= number_format($row['pv']) ?></td>
                <td><?= number_format($row['line']) ?></td>
                <td style="font-weight:700;color:var(--gold-lt);"><?= number_format($row['leads']) ?></td>
                <td style="color:<?= $row['new_leads'] > 0 ? '#e0a040' : 'inherit' ?>;font-weight:700;"><?= number_format($row['new_leads']) ?></td>
                <td><?= number_format($row['prospects']) ?></td>
                <td style="font-weight:700;color:<?= $row['won'] > 0 ? '#5ecb9b' : 'inherit' ?>;"><?= number_format($row['won']) ?></td>
                <td><?= $row['conversion'] === null ? '—' : h($row['conversion'] . '%') ?></td>
                <td style="white-space:nowrap;">
                    <a href="/lp.php?preview=1&template_id=<?= (int)$tpl['id'] ?>" target="_blank" class="btn btn-outline btn-sm">プレビュー</a>
                    <a href="/admin/templates.php?edit=<?= (int)$tpl['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:2.5rem;">テンプレートがありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

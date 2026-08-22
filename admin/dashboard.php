<?php
$pageTitle = 'ダッシュボード';
require_once __DIR__ . '/header.php';

$db = getDB();

function dashboardCount(PDO $db, string $sql): int {
    try {
        return (int)$db->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('Admin dashboard count failed: ' . $e->getMessage());
        return 0;
    }
}

function dashboardRows(PDO $db, string $sql): array {
    try {
        return $db->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('Admin dashboard rows failed: ' . $e->getMessage());
        return [];
    }
}

// 集計
$stats = [
    'agents'      => dashboardCount($db, "SELECT COUNT(*) FROM agents WHERE status='active'"),
    'templates'   => dashboardCount($db, "SELECT COUNT(*) FROM lp_templates WHERE status='active'"),
    'leads'       => dashboardCount($db, "SELECT COUNT(*) FROM leads"),
    'new_leads'   => dashboardCount($db, "SELECT COUNT(*) FROM leads WHERE status='new'"),
    'today_pv'    => dashboardCount($db, "SELECT COUNT(*) FROM access_logs WHERE type='pv' AND DATE(created_at)=CURDATE()"),
    'line_clicks' => dashboardCount($db, "SELECT COUNT(*) FROM access_logs WHERE type='line_click' AND DATE(created_at)=CURDATE()"),
];

// 最新問い合わせ5件
$recentLeads = dashboardRows($db, "
    SELECT l.*, a.agent_name, a.person_name
    FROM leads l
    JOIN agents a ON l.agent_id = a.id
    ORDER BY l.created_at DESC LIMIT 5
");

$statusLabels = ['new'=>'新規', 'contacted'=>'対応中', 'prospect'=>'成約見込み', 'won'=>'成約', 'lost'=>'失注', 'closed'=>'対応済'];
?>

<div class="stats-grid">
    <div class="stat-card">
        <p class="stat-label">アクティブアドバイザー</p>
        <p class="stat-val"><?= $stats['agents'] ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-label">累計問い合わせ</p>
        <p class="stat-val"><?= $stats['leads'] ?></p>
        <p class="stat-sub">未対応：<?= $stats['new_leads'] ?>件</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">本日PV</p>
        <p class="stat-val"><?= $stats['today_pv'] ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-label">本日LINEクリック</p>
        <p class="stat-val"><?= $stats['line_clicks'] ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-label">テンプレート数</p>
        <p class="stat-val"><?= $stats['templates'] ?></p>
    </div>
</div>

<div class="card">
    <p class="card-title">最新の問い合わせ</p>
    <?php if ($recentLeads): ?>
    <table>
        <thead>
            <tr>
                <th>日時</th>
                <th>顧客名</th>
                <th>担当アドバイザー</th>
                <th>状態</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentLeads as $lead): ?>
            <tr>
                <td><?= h(date('m/d H:i', strtotime($lead['created_at']))) ?></td>
                <td><?= h($lead['name']) ?></td>
                <td><?= h($lead['agent_name']) ?></td>
                <td>
                    <span class="badge badge-<?= h($lead['status']) ?>">
                        <?= h($statusLabels[$lead['status']] ?? $lead['status']) ?>
                    </span>
                </td>
                <td><a href="/admin/leads.php?id=<?= $lead['id'] ?>" class="btn btn-outline btn-sm">詳細</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color:var(--text-muted);font-size:.9rem;">問い合わせはまだありません。</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

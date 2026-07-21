<?php
$pageTitle = 'ダッシュボード';
require_once __DIR__ . '/header.php';

$db = getDB();

// 集計
$stats = [
    'agents'    => $db->query("SELECT COUNT(*) FROM agents WHERE status='active'")->fetchColumn(),
    'templates' => $db->query("SELECT COUNT(*) FROM lp_templates WHERE status='active'")->fetchColumn(),
    'leads'     => $db->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
    'new_leads' => $db->query("SELECT COUNT(*) FROM leads WHERE status='new'")->fetchColumn(),
    'today_pv'  => $db->query("SELECT COUNT(*) FROM access_logs WHERE type='pv' AND DATE(created_at)=CURDATE()")->fetchColumn(),
    'line_clicks'=> $db->query("SELECT COUNT(*) FROM access_logs WHERE type='line_click' AND DATE(created_at)=CURDATE()")->fetchColumn(),
];

// 最新問い合わせ5件
$recentLeads = $db->query("
    SELECT l.*, a.agent_name, a.person_name
    FROM leads l
    JOIN agents a ON l.agent_id = a.id
    ORDER BY l.created_at DESC LIMIT 5
")->fetchAll();

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

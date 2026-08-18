<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
if (empty($_SESSION['agent_id'])) {
    header('Location: /agent/login.php');
    exit;
}

$db = getDB();
$currentAgent = getAgentById((int)$_SESSION['agent_id']);
if (!$currentAgent || ($currentAgent['status'] ?? '') !== 'active') {
    header('Location: /agent/logout.php');
    exit;
}

$labels = getLevelLabels();
$positionLabels = function_exists('getAdvisorPositionLabels') ? getAdvisorPositionLabels() : [
    'advisor' => 'アドバイザー',
    'super_advisor' => 'スーパーアドバイザー',
    'influencer' => 'インフルエンサー',
];

function agentOrgH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function agentOrgLabel(array $agent, array $labels, array $positionLabels): string {
    $level = (int)($agent['level'] ?? 1);
    if ($level === 1) {
        $type = (string)($agent['position_type'] ?? 'advisor');
        $custom = trim((string)($agent['position_label'] ?? ''));
        return $custom !== '' ? $custom : ($positionLabels[$type] ?? ($labels[1] ?? 'アドバイザー'));
    }
    return $labels[$level] ?? ('Lv.' . $level);
}

function agentOrgStats(PDO $db, array $agentIds): array {
    if (!$agentIds) return [];
    $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
    $stats = [];
    foreach ($agentIds as $id) $stats[(int)$id] = ['pv' => 0, 'leads' => 0, 'new_leads' => 0];
    try {
        $stmt = $db->prepare("SELECT agent_id, COUNT(*) AS cnt FROM access_logs WHERE type='pv' AND agent_id IN ($placeholders) GROUP BY agent_id");
        $stmt->execute($agentIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $stats[(int)$row['agent_id']]['pv'] = (int)$row['cnt'];
    } catch (Throwable $e) {}
    try {
        $stmt = $db->prepare("SELECT agent_id, COUNT(*) AS cnt, SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) AS new_cnt FROM leads WHERE agent_id IN ($placeholders) GROUP BY agent_id");
        $stmt->execute($agentIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[(int)$row['agent_id']]['leads'] = (int)$row['cnt'];
            $stats[(int)$row['agent_id']]['new_leads'] = (int)$row['new_cnt'];
        }
    } catch (Throwable $e) {}
    return $stats;
}

function agentOrgStatusClass(array $agent, array $stats): string {
    if (($agent['status'] ?? '') !== 'active') return 'is-stopped';
    if (($stats['new_leads'] ?? 0) > 0) return 'needs-follow';
    if (($stats['pv'] ?? 0) > 0 || ($stats['leads'] ?? 0) > 0) return 'is-active';
    return 'is-quiet';
}

function agentOrgStatusLabel(array $agent, array $stats): string {
    if (($agent['status'] ?? '') !== 'active') return '停止中';
    if (($stats['new_leads'] ?? 0) > 0) return '未対応あり';
    if (($stats['pv'] ?? 0) > 0 || ($stats['leads'] ?? 0) > 0) return '活動あり';
    return '活動少なめ';
}

function agentOrgBuildTree(array $root, array $agents): array {
    $byParent = [];
    foreach ($agents as $agent) $byParent[(int)($agent['parent_id'] ?? 0)][] = $agent;
    $walk = function (array $agent) use (&$walk, &$byParent): array {
        $children = [];
        foreach ($byParent[(int)$agent['id']] ?? [] as $child) {
            $child['children'] = $walk($child);
            $children[] = $child;
        }
        return $children;
    };
    $root['children'] = $walk($root);
    return [$root];
}

function agentOrgRender(array $nodes, array $stats, array $labels, array $positionLabels): void {
    if (!$nodes) return;
    echo '<ul class="org-tree">';
    foreach ($nodes as $agent) {
        $id = (int)$agent['id'];
        $s = $stats[$id] ?? ['pv' => 0, 'leads' => 0, 'new_leads' => 0];
        $class = agentOrgStatusClass($agent, $s);
        $label = agentOrgLabel($agent, $labels, $positionLabels);
        $childCount = count($agent['children'] ?? []);
        $displayName = trim((string)($agent['person_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string)($agent['agent_name'] ?? '');
        }
        $detail = [
            'name' => $displayName,
            'agencyName' => (string)($agent['agent_name'] ?? ''),
            'code' => (string)($agent['agent_code'] ?? ''),
            'label' => $label,
            'person' => (string)($agent['person_name'] ?? ''),
            'email' => (string)($agent['email'] ?? ''),
            'phone' => (string)($agent['phone'] ?? ''),
            'status' => agentOrgStatusLabel($agent, $s),
            'pv' => (int)$s['pv'],
            'leads' => (int)$s['leads'],
            'newLeads' => (int)$s['new_leads'],
            'children' => $childCount,
            'lpUrl' => '/a/' . (string)($agent['agent_code'] ?? ''),
            'manageUrl' => ((int)$agent['id'] === (int)($_SESSION['agent_id'] ?? 0)) ? '/agent/settings.php' : '/agent/sub_agents.php',
        ];
        $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '<li><details open class="' . ($childCount > 0 ? 'has-children' : 'is-leaf') . '"><summary>';
        echo '<span class="agent-card ' . agentOrgH($class) . '" role="button" tabindex="0" data-agent=\'' . agentOrgH($detailJson) . '\'>';
        echo '<span class="agent-main"><strong>' . agentOrgH($displayName) . '</strong>';
        if ($childCount > 0) {
            echo '<span class="child-count">配下' . (int)$childCount . '名</span>';
        }
        echo '<small>' . agentOrgH($label) . ' / ' . agentOrgH($agent['agent_code'] ?? '') . '</small></span>';
        echo '<span class="agent-stats"><em>PV ' . (int)$s['pv'] . '</em><em>問合せ ' . (int)$s['leads'] . '</em><em>未対応 ' . (int)$s['new_leads'] . '</em></span>';
        echo '</span></summary>';
        if (!empty($agent['children'])) agentOrgRender($agent['children'], $stats, $labels, $positionLabels);
        echo '</details></li>';
    }
    echo '</ul>';
}

$descendants = getAllDescendants((int)$currentAgent['id']);
$allAgents = array_merge([$currentAgent], $descendants);
$agentIds = array_map(static fn($a) => (int)$a['id'], $allAgents);
$stats = agentOrgStats($db, $agentIds);
$tree = agentOrgBuildTree($currentAgent, $descendants);
$totalPv = array_sum(array_column($stats, 'pv'));
$totalLeads = array_sum(array_column($stats, 'leads'));
$totalNewLeads = array_sum(array_column($stats, 'new_leads'));
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>傘下組織図 | 戦国経済圏</title>
<style>
body{margin:0;background:#100d09;color:#f8f1df;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans JP",sans-serif}
.page{max-width:1180px;margin:0 auto;padding:28px 16px 56px}
.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:18px}h1{font-size:25px;margin:0}.muted{color:#a99d8d;font-size:14px}.back{color:#d6ad3d;text-decoration:none;font-weight:700}
.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}.metric{background:#18130e;border:1px solid #3a2c18;border-radius:8px;padding:16px}.metric b{display:block;font-size:24px;color:#e5c766}.metric span{font-size:13px;color:#a99d8d}
.legend{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 18px}.legend span{font-size:12px;background:#18130e;border:1px solid #3a2c18;border-radius:999px;padding:6px 10px}.dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px}
.org-tree{list-style:none;margin:0 auto;padding-left:0;display:block;width:max-content;max-width:100%}.org-tree .org-tree{margin:12px 0 0 30px;padding-left:24px;display:block;border-left:3px solid #8f6d2d;width:auto}.org-tree li{position:relative;min-width:0;margin:12px 0 16px}.org-tree .org-tree li:before{content:"";position:absolute;left:-24px;top:22px;width:24px;height:0;border-bottom:3px solid #8f6d2d;z-index:0}
details>summary{list-style:none;cursor:pointer}details>summary::-webkit-details-marker{display:none}
.agent-card{position:relative;z-index:2;display:inline-flex;align-items:center;justify-content:space-between;gap:12px;min-height:0;background:#18130e;border:1px solid #3a2c18;border-left:7px solid #918776;border-radius:8px;padding:11px 16px;cursor:pointer;min-width:220px;max-width:min(420px,100%)}.agent-card.is-active{border-left-color:#50c78b}.agent-card.needs-follow{border-left-color:#d6ad3d}.agent-card.is-stopped{border-left-color:#c94d4d}.agent-main{min-width:0}.agent-main strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:17px;line-height:1.35}.agent-main small,.agent-stats{display:none}.child-count{display:block;margin-top:3px;color:#e5c766;font-size:12px;font-weight:700}.has-children>summary .agent-card:after{content:"−";display:inline-grid;place-items:center;flex:0 0 24px;width:24px;height:24px;border-radius:50%;background:#21190f;border:1px solid #3a2c18;color:#e5c766;font-weight:900}.has-children:not([open])>summary .agent-card:after{content:"+"}
.detail-panel{position:fixed;right:18px;top:82px;width:min(380px,calc(100vw - 36px));max-height:calc(100vh - 110px);overflow:auto;background:#18130e;border:1px solid #3a2c18;border-radius:10px;box-shadow:0 18px 46px rgba(0,0,0,.45);padding:18px;z-index:20;transform:translateX(calc(100% + 28px));transition:transform .18s ease}.detail-panel.is-open{transform:translateX(0)}.detail-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border-bottom:1px solid #3a2c18;padding-bottom:12px;margin-bottom:12px}.detail-head h2{font-size:20px;margin:0}.detail-head p{margin:5px 0 0;color:#a99d8d}.detail-close{background:#21190f;color:#f8f1df;border-color:#3a2c18;padding:6px 10px}.detail-row{display:grid;grid-template-columns:110px 1fr;gap:10px;border-bottom:1px solid #2a2116;padding:9px 0}.detail-row b{color:#e5c766}.detail-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.detail-actions a{border:1px solid #3a2c18;border-radius:6px;padding:9px 11px;color:#e5c766;text-decoration:none;font-weight:700}
@media(max-width:760px){.top{display:block}.summary{grid-template-columns:repeat(2,1fr)}.org-tree{width:100%;margin:0}.org-tree .org-tree{margin-left:18px;padding-left:18px}.org-tree .org-tree li:before{left:-18px;width:18px}.agent-card{min-width:0;width:100%;padding:11px 13px}.page{padding:18px 12px 42px}}
</style>
</head>
<body>
<main class="page">
  <div class="top">
    <div>
      <h1>傘下組織図</h1>
      <p class="muted">自分を起点に、配下メンバーの活動状況を確認できます。</p>
    </div>
    <a class="back" href="/agent/dashboard.php">マイページに戻る</a>
  </div>
  <section class="summary">
    <div class="metric"><b><?= count($descendants) ?></b><span>傘下メンバー</span></div>
    <div class="metric"><b><?= (int)$totalPv ?></b><span>合計PV</span></div>
    <div class="metric"><b><?= (int)$totalLeads ?></b><span>合計問い合わせ</span></div>
    <div class="metric"><b><?= (int)$totalNewLeads ?></b><span>未対応問い合わせ</span></div>
  </section>
  <div class="legend">
    <span><i class="dot" style="background:#50c78b"></i>活動あり</span>
    <span><i class="dot" style="background:#d6ad3d"></i>未対応あり</span>
    <span><i class="dot" style="background:#918776"></i>活動少なめ</span>
    <span><i class="dot" style="background:#c94d4d"></i>停止中</span>
  </div>
  <?php agentOrgRender($tree, $stats, $labels, $positionLabels); ?>
  <aside class="detail-panel" id="orgDetailPanel" aria-live="polite" aria-label="代理店詳細">
    <div class="detail-head">
      <div>
        <h2 id="detailName">代理店詳細</h2>
        <p id="detailSub">カードを選択すると詳細を表示します</p>
      </div>
      <button type="button" class="detail-close" id="detailClose">閉じる</button>
    </div>
    <div class="detail-row"><b>担当者</b><span id="detailPerson">-</span></div>
    <div class="detail-row"><b>名称</b><span id="detailAgencyName">-</span></div>
    <div class="detail-row"><b>メール</b><span id="detailEmail">-</span></div>
    <div class="detail-row"><b>電話</b><span id="detailPhone">-</span></div>
    <div class="detail-row"><b>状態</b><span id="detailStatus">-</span></div>
    <div class="detail-row"><b>実績</b><span id="detailStats">-</span></div>
    <div class="detail-row"><b>配下</b><span id="detailChildren">-</span></div>
    <div class="detail-actions">
      <a href="#" id="detailLp" target="_blank" rel="noopener">LPを開く</a>
      <a href="#" id="detailManage">管理で確認</a>
    </div>
  </aside>
</main>
<script>
(function(){
  var panel = document.getElementById('orgDetailPanel');
  if (!panel) return;
  var setText = function(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value || '-';
  };
  var openDetail = function(card) {
    var data = {};
    try { data = JSON.parse(card.getAttribute('data-agent') || '{}'); } catch (e) {}
    setText('detailName', data.name);
    setText('detailSub', (data.label || '-') + ' / ' + (data.code || '-'));
    setText('detailPerson', data.person);
    setText('detailAgencyName', data.agencyName);
    setText('detailEmail', data.email);
    setText('detailPhone', data.phone);
    setText('detailStatus', data.status);
    setText('detailStats', 'PV ' + (data.pv || 0) + ' / 問合せ ' + (data.leads || 0) + ' / 未対応 ' + (data.newLeads || 0));
    setText('detailChildren', (data.children || 0) + '件');
    var lp = document.getElementById('detailLp');
    var manage = document.getElementById('detailManage');
    if (lp) lp.href = data.lpUrl || '#';
    if (manage) manage.href = data.manageUrl || '#';
    panel.classList.add('is-open');
  };
  document.querySelectorAll('.agent-card[data-agent]').forEach(function(card){
    card.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); openDetail(card); });
    card.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDetail(card); }
    });
  });
  document.getElementById('detailClose').addEventListener('click', function(){ panel.classList.remove('is-open'); });
})();
</script>
</body>
</html>

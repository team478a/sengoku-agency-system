<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();
$labels = getLevelLabels();
$positionLabels = function_exists('getAdvisorPositionLabels') ? getAdvisorPositionLabels() : [
    'advisor' => 'アドバイザー',
    'super_advisor' => 'スーパーアドバイザー',
    'influencer' => 'インフルエンサー',
];

function orgMapH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function orgMapHasColumn(string $table, string $column): bool {
    return function_exists('tableHasColumn') ? tableHasColumn($table, $column) : false;
}

function orgMapAgentLabel(array $agent, array $labels, array $positionLabels): string {
    $level = (int)($agent['level'] ?? 1);
    if ($level === 1) {
        $type = (string)($agent['position_type'] ?? 'advisor');
        $custom = trim((string)($agent['position_label'] ?? ''));
        return $custom !== '' ? $custom : ($positionLabels[$type] ?? ($labels[1] ?? 'アドバイザー'));
    }
    return $labels[$level] ?? ('Lv.' . $level);
}

function orgMapActivityStats(PDO $db, array $agentIds): array {
    if (!$agentIds) return [];
    $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
    $stats = [];
    foreach ($agentIds as $id) {
        $stats[(int)$id] = ['pv' => 0, 'leads' => 0, 'new_leads' => 0, 'last_login' => null];
    }

    try {
        $stmt = $db->prepare("SELECT agent_id, COUNT(*) AS cnt FROM access_logs WHERE type='pv' AND agent_id IN ($placeholders) GROUP BY agent_id");
        $stmt->execute($agentIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[(int)$row['agent_id']]['pv'] = (int)$row['cnt'];
        }
    } catch (Throwable $e) {}

    try {
        $stmt = $db->prepare("SELECT agent_id, COUNT(*) AS cnt, SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) AS new_cnt FROM leads WHERE agent_id IN ($placeholders) GROUP BY agent_id");
        $stmt->execute($agentIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[(int)$row['agent_id']]['leads'] = (int)$row['cnt'];
            $stats[(int)$row['agent_id']]['new_leads'] = (int)$row['new_cnt'];
        }
    } catch (Throwable $e) {}

    try {
        $stmt = $db->prepare("SELECT user_id, MAX(created_at) AS last_login FROM login_logs WHERE user_type='agent' AND success=1 AND user_id IN ($placeholders) GROUP BY user_id");
        $stmt->execute($agentIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[(int)$row['user_id']]['last_login'] = $row['last_login'];
        }
    } catch (Throwable $e) {}

    return $stats;
}

function orgMapStatusClass(array $agent, array $stats): string {
    if (($agent['status'] ?? '') !== 'active') return 'is-stopped';
    if (($stats['new_leads'] ?? 0) > 0) return 'needs-follow';
    if (($stats['pv'] ?? 0) > 0 || ($stats['leads'] ?? 0) > 0) return 'is-active';
    return 'is-quiet';
}

function orgMapStatusLabel(array $agent, array $stats): string {
    if (($agent['status'] ?? '') !== 'active') return '停止中';
    if (($stats['new_leads'] ?? 0) > 0) return '未対応あり';
    if (($stats['pv'] ?? 0) > 0 || ($stats['leads'] ?? 0) > 0) return '活動あり';
    return '活動少なめ';
}

function orgMapBuildTree(array $agents): array {
    $byParent = [];
    foreach ($agents as $agent) {
        $parentId = (int)($agent['parent_id'] ?? 0);
        $byParent[$parentId][] = $agent;
    }
    $walk = function (int $parentId) use (&$walk, &$byParent): array {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $agent) {
            $agent['children'] = $walk((int)$agent['id']);
            $nodes[] = $agent;
        }
        return $nodes;
    };
    return $walk(0);
}

function orgMapDescendantCount(array $agent): int {
    $count = 0;
    foreach (($agent['children'] ?? []) as $child) {
        $count++;
        $count += orgMapDescendantCount($child);
    }
    return $count;
}

function orgMapRenderNodes(array $nodes, array $stats, array $labels, array $positionLabels): void {
    if (!$nodes) return;
    echo '<ul class="org-tree">';
    foreach ($nodes as $agent) {
        $id = (int)$agent['id'];
        $s = $stats[$id] ?? ['pv' => 0, 'leads' => 0, 'new_leads' => 0, 'last_login' => null];
        $class = orgMapStatusClass($agent, $s);
        $label = orgMapAgentLabel($agent, $labels, $positionLabels);
        $childCount = count($agent['children'] ?? []);
        $descendantCount = orgMapDescendantCount($agent);
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
            'status' => orgMapStatusLabel($agent, $s),
            'statusRaw' => (string)($agent['status'] ?? ''),
            'pv' => (int)$s['pv'],
            'leads' => (int)$s['leads'],
            'newLeads' => (int)$s['new_leads'],
            'lastLogin' => (string)($s['last_login'] ?? ''),
            'children' => $childCount,
            'descendants' => $descendantCount,
            'lpUrl' => '/a/' . (string)($agent['agent_code'] ?? ''),
            'editUrl' => '/admin/agents.php?edit=' . $id,
            'leadsUrl' => '/admin/leads.php?agent_id=' . $id,
            'parentLinkUrl' => '/admin/agent_parent_links.php?q=' . rawurlencode((string)($agent['agent_code'] ?? '')),
        ];
        $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '<li>';
        echo '<details open class="' . ($childCount > 0 ? 'has-children' : 'is-leaf') . '">';
        echo '<summary>';
        echo '<span class="agent-card ' . orgMapH($class) . '" role="button" tabindex="0" data-agent=\'' . orgMapH($detailJson) . '\'>';
        echo '<span class="agent-main">';
        echo '<strong>' . orgMapH($displayName) . '</strong>';
        if ($childCount > 0) {
            echo '<span class="child-count">配下' . (int)$childCount . '名</span>';
        }
        echo '<small>' . orgMapH($label) . ' / ' . orgMapH($agent['agent_code'] ?? '') . '</small>';
        echo '</span>';
        echo '<span class="agent-stats">';
        echo '<em>PV ' . (int)$s['pv'] . '</em>';
        echo '<em>問合せ ' . (int)$s['leads'] . '</em>';
        echo '<em>未対応 ' . (int)$s['new_leads'] . '</em>';
        echo '</span>';
        echo '</span>';
        echo '</summary>';
        if (!empty($agent['children'])) {
            orgMapRenderNodes($agent['children'], $stats, $labels, $positionLabels);
        }
        echo '</details>';
        echo '</li>';
    }
    echo '</ul>';
}

$levelFilter = (int)($_GET['level'] ?? 0);
$statusFilter = trim((string)($_GET['status'] ?? ''));
$keyword = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($levelFilter > 0) {
    $where[] = 'a.level = ?';
    $params[] = $levelFilter;
}
if ($statusFilter !== '') {
    $where[] = 'a.status = ?';
    $params[] = $statusFilter;
}
if ($keyword !== '') {
    $where[] = '(a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR a.email LIKE ?)';
    array_push($params, "%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%");
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare("
    SELECT a.*
    FROM agents a
    $whereSql
    ORDER BY COALESCE(a.parent_id, 0), a.level DESC, a.created_at ASC
");
$stmt->execute($params);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$agentIds = array_map(static fn($a) => (int)$a['id'], $agents);
$stats = orgMapActivityStats($db, $agentIds);
$tree = $where ? $agents : orgMapBuildTree($agents);

$totalPv = array_sum(array_column($stats, 'pv'));
$totalLeads = array_sum(array_column($stats, 'leads'));
$totalNewLeads = array_sum(array_column($stats, 'new_leads'));
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>代理店組織図 | 戦国経済圏</title>
<style>
body{margin:0;background:#f6f1e8;color:#16120d;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans JP",sans-serif}
.page{max-width:1280px;margin:0 auto;padding:28px 18px 56px}
.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:18px}
h1{font-size:26px;margin:0}.muted{color:#82776b;font-size:14px}
.back{color:#9b6b00;text-decoration:none;font-weight:700}
.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}
.metric{background:#fff;border:1px solid #e4d7bd;border-radius:8px;padding:16px}.metric b{display:block;font-size:24px;color:#c69b2c}.metric span{font-size:13px;color:#82776b}
.filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;background:#fff;border:1px solid #e4d7bd;border-radius:8px;padding:14px;margin-bottom:18px}
input,select,button{font:inherit;padding:10px;border:1px solid #d9c7a6;border-radius:6px;background:#fff}button{background:#d6ad3d;color:#111;font-weight:700;cursor:pointer}
.legend{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 18px}.legend span{font-size:12px;background:#fff;border:1px solid #e4d7bd;border-radius:999px;padding:6px 10px}.dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px}
.org-tree{list-style:none;margin:0 auto;padding-left:0;position:relative;display:block;width:max-content;max-width:100%}.org-tree .org-tree{margin:12px 0 0 30px;padding-left:24px;display:block;border-left:3px solid #c9a85a;width:auto}.org-tree li{position:relative;min-width:0;margin:12px 0 16px}.org-tree .org-tree li:before{content:"";position:absolute;left:-24px;top:22px;width:24px;height:0;border-bottom:3px solid #c9a85a;z-index:0}
details>summary{list-style:none;cursor:pointer}details>summary::-webkit-details-marker{display:none}
.agent-card{position:relative;z-index:2;display:inline-flex;align-items:center;justify-content:space-between;gap:12px;min-height:0;background:#fff;border:1px solid #e3d5b8;border-left:7px solid #b9b0a3;border-radius:8px;padding:11px 16px;box-shadow:0 4px 14px rgba(44,34,15,.04);cursor:pointer;min-width:220px;max-width:min(420px,100%)}
.agent-card.is-active{border-left-color:#3fbf7f}.agent-card.needs-follow{border-left-color:#d49a23}.agent-card.is-stopped{border-left-color:#c94d4d}.agent-card.is-quiet{border-left-color:#9c958a}
.agent-main{min-width:0}.agent-main strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:17px;line-height:1.35}.agent-main small,.agent-stats{display:none}.child-count{display:block;margin-top:3px;color:#8a6400;font-size:12px;font-weight:700}.has-children>summary .agent-card:after{content:"−";display:inline-grid;place-items:center;flex:0 0 24px;width:24px;height:24px;border-radius:50%;background:#f8f3e9;border:1px solid #eadbbd;color:#8a6400;font-weight:900}.has-children:not([open])>summary .agent-card:after{content:"+"}
.flat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px}
.detail-panel{position:fixed;right:18px;top:82px;width:min(380px,calc(100vw - 36px));max-height:calc(100vh - 110px);overflow:auto;background:#fff;border:1px solid #d9c7a6;border-radius:10px;box-shadow:0 18px 46px rgba(44,34,15,.22);padding:18px;z-index:20;transform:translateX(calc(100% + 28px));transition:transform .18s ease}
.detail-panel.is-open{transform:translateX(0)}
.detail-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border-bottom:1px solid #eadbbd;padding-bottom:12px;margin-bottom:12px}
.detail-head h2{font-size:20px;margin:0}.detail-head p{margin:5px 0 0;color:#82776b}.detail-close{background:#fff;color:#8a6400;border-color:#d9c7a6;padding:6px 10px}
.detail-row{display:grid;grid-template-columns:110px 1fr;gap:10px;border-bottom:1px solid #f0e6d5;padding:9px 0}.detail-row b{color:#7b642f}.detail-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.detail-actions a{border:1px solid #d9c7a6;border-radius:6px;padding:9px 11px;color:#8a6400;text-decoration:none;font-weight:700}
.detail-actions button{border:1px solid #d9c7a6;border-radius:6px;padding:9px 11px;color:#8a6400;text-decoration:none;font-weight:700;background:#fff}
.detail-url{display:block;word-break:break-all;color:#8a6400;font-size:12px;line-height:1.5}
@media(max-width:760px){.top{display:block}.summary{grid-template-columns:repeat(2,1fr)}.filters{grid-template-columns:1fr}.org-tree{width:100%;margin:0}.org-tree .org-tree{margin-left:18px;padding-left:18px}.org-tree .org-tree li:before{left:-18px;width:18px}.agent-card{min-width:0;width:100%;padding:11px 13px}.page{padding:18px 12px 42px}}
</style>
</head>
<body>
<main class="page">
  <div class="top">
    <div>
      <h1>代理店組織図</h1>
      <p class="muted">階層・活動状況・未対応問い合わせを目で見て確認できます。</p>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
      <a class="back" href="/admin/agent_parent_links.php">親子紐づけ変更</a>
      <a class="back" href="/admin/dashboard.php">管理画面に戻る</a>
    </div>
  </div>

  <section class="summary">
    <div class="metric"><b><?= count($agents) ?></b><span>表示メンバー</span></div>
    <div class="metric"><b><?= (int)$totalPv ?></b><span>合計PV</span></div>
    <div class="metric"><b><?= (int)$totalLeads ?></b><span>合計問い合わせ</span></div>
    <div class="metric"><b><?= (int)$totalNewLeads ?></b><span>未対応問い合わせ</span></div>
  </section>

  <form class="filters" method="get">
    <input type="search" name="q" value="<?= orgMapH($keyword) ?>" placeholder="名前・担当者・コード・メールで検索">
    <select name="level">
      <option value="0">すべての区分</option>
      <?php foreach ($labels as $level => $label): ?>
        <option value="<?= (int)$level ?>" <?= $levelFilter === (int)$level ? 'selected' : '' ?>><?= orgMapH($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">すべての状態</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>公開中</option>
      <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>停止中</option>
    </select>
    <button type="submit">表示</button>
  </form>

  <div class="legend">
    <span><i class="dot" style="background:#3fbf7f"></i>活動あり</span>
    <span><i class="dot" style="background:#d49a23"></i>未対応あり</span>
    <span><i class="dot" style="background:#9c958a"></i>活動少なめ</span>
    <span><i class="dot" style="background:#c94d4d"></i>停止中</span>
  </div>

  <?php if ($where): ?>
    <div class="flat-grid">
      <?php foreach ($agents as $agent): ?>
        <?php orgMapRenderNodes([$agent], $stats, $labels, $positionLabels); ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <?php orgMapRenderNodes($tree, $stats, $labels, $positionLabels); ?>
  <?php endif; ?>

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
    <div class="detail-row"><b>LP URL</b><span><span class="detail-url" id="detailLpUrl">-</span></span></div>
    <div class="detail-row"><b>最終ログイン</b><span id="detailLogin">-</span></div>
    <div class="detail-actions">
      <a href="#" id="detailLp" target="_blank" rel="noopener">LPを開く</a>
      <button type="button" id="detailCopyLp">LP URLコピー</button>
      <a href="#" id="detailLeads">問い合わせ</a>
      <a href="#" id="detailEdit">編集する</a>
      <a href="#" id="detailParentLink">紐づけ変更</a>
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
    setText('detailChildren', '直下 ' + (data.children || 0) + '名 / 全配下 ' + (data.descendants || 0) + '名');
    setText('detailLpUrl', data.lpUrl);
    setText('detailLogin', data.lastLogin);
    var lp = document.getElementById('detailLp');
    var edit = document.getElementById('detailEdit');
    var leads = document.getElementById('detailLeads');
    var parentLink = document.getElementById('detailParentLink');
    if (lp) lp.href = data.lpUrl || '#';
    if (edit) edit.href = data.editUrl || '#';
    if (leads) leads.href = data.leadsUrl || '#';
    if (parentLink) parentLink.href = data.parentLinkUrl || ('/admin/agent_parent_links.php?q=' + encodeURIComponent(data.code || ''));
    panel.setAttribute('data-lp-url', data.lpUrl || '');
    panel.classList.add('is-open');
  };
  document.querySelectorAll('.agent-card[data-agent]').forEach(function(card){
    card.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); openDetail(card); });
    card.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDetail(card); }
    });
  });
  document.getElementById('detailClose').addEventListener('click', function(){ panel.classList.remove('is-open'); });
  var copyButton = document.getElementById('detailCopyLp');
  if (copyButton) {
    copyButton.addEventListener('click', function(){
      var url = panel.getAttribute('data-lp-url') || '';
      if (!url) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function(){
          copyButton.textContent = 'コピーしました';
          setTimeout(function(){ copyButton.textContent = 'LP URLコピー'; }, 1400);
        });
      }
    });
  }
})();
</script>
</body>
</html>

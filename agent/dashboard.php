<?php
$pageTitle = 'ダッシュボード';
require_once __DIR__ . '/header.php';

$db  = getDB();
$aid = $currentAgent['id'];
$myLv = (int)($currentAgent['level'] ?? 1);
$levelLabels = getLevelLabels();
$managedLevel = $myLv >= 3 ? 2 : 1;
$managedLabel = $levelLabels[$managedLevel] ?? ($managedLevel === 2 ? 'ディレクター' : 'アドバイザー');
$tokenMessage = '';
$tokenMsgType = 'success';
$advisorPositionLabels = getAdvisorPositionLabels();
$advisorInviteTargetsText = implode('・', array_values($advisorPositionLabels));
$ssoSettings = function_exists('getAgencySsoSettings') ? getAgencySsoSettings() : ['private_key' => ''];
$ssoClients = function_exists('getSsoClients') ? getSsoClients(true) : [];
$ssoPortalEnabled = !empty($ssoSettings['private_key']) && !empty($ssoClients);

if (!function_exists('getSiteBaseUrl')) {
    function getSiteBaseUrl(): string {
        $baseUrl = '';
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT value FROM system_settings WHERE key_name='site_url' LIMIT 1");
            $stmt->execute();
            $baseUrl = trim((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            $baseUrl = '';
        }
        if ($baseUrl === '') {
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        } else {
            $parts = parse_url($baseUrl);
            if (!empty($parts['host'])) {
                $scheme = $parts['scheme'] ?? (isset($_SERVER['HTTPS']) ? 'https' : 'http');
                $port = !empty($parts['port']) ? ':' . $parts['port'] : '';
                $baseUrl = $scheme . '://' . $parts['host'] . $port;
            }
        }
        return rtrim($baseUrl, '/');
    }
}

if (!function_exists('buildAgentProjectLpUrl')) {
    function buildAgentProjectLpUrl(string $agentCode, ?array $project = null): string {
        $url = getSiteBaseUrl() . '/a/' . rawurlencode($agentCode);
        if (!empty($project['slug'])) {
            $url .= '?project=' . rawurlencode((string)$project['slug']);
        }
        return $url;
    }
}

if (!function_exists('getProjects')) {
    function getProjects(bool $activeOnly = false): array {
        try {
            $db = getDB();
            $where = $activeOnly ? "WHERE status='active'" : '';
            return $db->query("SELECT * FROM projects $where ORDER BY sort_order ASC, id ASC")->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('getAgentProjectLpUrls')) {
    function getAgentProjectLpUrls(array $agent): array {
        $urls = [];
        foreach (getProjects(true) as $project) {
            $urls[] = [
                'project' => $project,
                'url' => buildAgentProjectLpUrl((string)($agent['agent_code'] ?? ''), $project),
            ];
        }
        if (!$urls && !empty($agent['agent_code'])) {
            $urls[] = [
                'project' => ['name' => 'Default', 'slug' => ''],
                'url' => buildAgentProjectLpUrl((string)$agent['agent_code'], null),
            ];
        }
        return $urls;
    }
}

function buildAdvisorJoinUrl(string $baseUrl, string $token, string $positionType, bool $needsTargetParam): string {
    $params = ['position' => $positionType];
    if ($needsTargetParam) {
        $params = ['target' => 'advisor'] + $params;
    }
    return $baseUrl . '/join/' . $token . '?' . http_build_query($params);
}

function getRecruitmentTokenColumns(PDO $db): array {
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM agents")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('Recruitment token column check failed: ' . $e->getMessage());
    }
    return $columns;
}

$tokenColumns = getRecruitmentTokenColumns($db);

$visibleAgentIds = [$aid];
if ($myLv >= 2) {
    $descendants = getAllDescendants($aid);
    foreach ($descendants as $descendant) {
        $visibleAgentIds[] = (int)$descendant['id'];
    }
}
$visibleAgentIds = array_values(array_unique($visibleAgentIds));
$visiblePlaceholders = implode(',', array_fill(0, count($visibleAgentIds), '?'));
$dashboardProjectId = !empty($selectedAgentProjectId) ? (int)$selectedAgentProjectId : 0;
$dashboardLeadHasProject = tableHasColumn('leads', 'project_id');
$dashboardAccessHasProject = tableHasColumn('access_logs', 'project_id');
$leadProjectSql = ($dashboardLeadHasProject && $dashboardProjectId > 0) ? ' AND project_id=?' : '';
$leadProjectParams = ($dashboardLeadHasProject && $dashboardProjectId > 0) ? [$dashboardProjectId] : [];
$leadProjectSqlAlias = ($dashboardLeadHasProject && $dashboardProjectId > 0) ? ' AND l.project_id=?' : '';
$accessProjectSql = ($dashboardAccessHasProject && $dashboardProjectId > 0) ? ' AND project_id=?' : '';
$accessProjectParams = ($dashboardAccessHasProject && $dashboardProjectId > 0) ? [$dashboardProjectId] : [];

// ── 統計 ──
$totalLeads = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id IN ($visiblePlaceholders) $leadProjectSql");
$totalLeads->execute(array_merge($visibleAgentIds, $leadProjectParams));
$cntLeads = (int)$totalLeads->fetchColumn();

$newLeads = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id IN ($visiblePlaceholders) AND status='new' $leadProjectSql");
$newLeads->execute(array_merge($visibleAgentIds, $leadProjectParams));
$cntNew = (int)$newLeads->fetchColumn();

$pvStmt = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE agent_id=? AND type='pv' $accessProjectSql");
$pvStmt->execute(array_merge([$aid], $accessProjectParams));
$cntPv = (int)$pvStmt->fetchColumn();

$lineStmt = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE agent_id=? AND type='line_click' $accessProjectSql");
$lineStmt->execute(array_merge([$aid], $accessProjectParams));
$cntLine = (int)$lineStmt->fetchColumn();

// ── エージェントの場合は傘下統計も取得 ──
$isAgentLevel = $myLv >= 2;
$subAgentCount = 0;
$subTotalLeads = 0;
$subNewLeads   = 0;
if ($isAgentLevel) {
    $r = $db->prepare("SELECT COUNT(*) FROM agents WHERE parent_id=? AND status='active'");
    $r->execute([$aid]); $subAgentCount = (int)$r->fetchColumn();
    $r = $db->prepare("SELECT COUNT(*) FROM leads l JOIN agents a ON l.agent_id=a.id WHERE a.parent_id=? $leadProjectSqlAlias");
    $r->execute(array_merge([$aid], $leadProjectParams)); $subTotalLeads = (int)$r->fetchColumn();
    $r = $db->prepare("SELECT COUNT(*) FROM leads l JOIN agents a ON l.agent_id=a.id WHERE a.parent_id=? AND l.status='new' $leadProjectSqlAlias");
    $r->execute(array_merge([$aid], $leadProjectParams)); $subNewLeads = (int)$r->fetchColumn();
}

// ── お知らせ取得 ──
$noticesStmt = $db->query("SELECT * FROM notices WHERE status='active' ORDER BY is_pinned DESC, created_at DESC LIMIT 5");
$notices = $noticesStmt->fetchAll();

// ── 申請トークン生成 ──
if (isset($_GET['regen_token']) && $myLv >= 2) {
    try {
        $tokenType = $_GET['token_type'] ?? ($myLv >= 3 ? 'director' : 'advisor');
        if ($myLv < 3 || !in_array($tokenType, ['director', 'advisor'], true)) {
            $tokenType = 'advisor';
        }
        $token = bin2hex(random_bytes(24));
        $exp   = date('Y-m-d H:i:s', strtotime('+90 days'));
        if ($tokenType === 'director') {
            if (!empty($tokenColumns['apply_token_director']) && !empty($tokenColumns['apply_token_director_exp'])) {
                $sets = ['apply_token_director=?', 'apply_token_director_exp=?'];
                $params = [$token, $exp];
                if (!empty($tokenColumns['apply_token']) && !empty($tokenColumns['apply_token_exp'])) {
                    $sets[] = 'apply_token=?';
                    $sets[] = 'apply_token_exp=?';
                    $params[] = $token;
                    $params[] = $exp;
                }
                $params[] = $aid;
                $db->prepare("UPDATE agents SET " . implode(',', $sets) . " WHERE id=?")->execute($params);
            } elseif (!empty($tokenColumns['apply_token']) && !empty($tokenColumns['apply_token_exp'])) {
                $db->prepare("UPDATE agents SET apply_token=?, apply_token_exp=? WHERE id=?")
                   ->execute([$token, $exp, $aid]);
            } else {
                throw new RuntimeException('Recruitment token columns are missing.');
            }
            $tokenMessage = ($levelLabels[2] ?? 'ディレクター') . '招待URLを生成しました。';
        } else {
            if (!empty($tokenColumns['apply_token_advisor']) && !empty($tokenColumns['apply_token_advisor_exp'])) {
                $db->prepare("UPDATE agents SET apply_token_advisor=?, apply_token_advisor_exp=? WHERE id=?")
                   ->execute([$token, $exp, $aid]);
            } elseif (!empty($tokenColumns['apply_token']) && !empty($tokenColumns['apply_token_exp'])) {
                $db->prepare("UPDATE agents SET apply_token=?, apply_token_exp=? WHERE id=?")
                   ->execute([$token, $exp, $aid]);
            } else {
                throw new RuntimeException('Recruitment token columns are missing.');
            }
            $tokenMessage = ($advisorInviteTargetsText ?: ($levelLabels[1] ?? 'アドバイザー')) . 'の招待URLを生成しました。';
        }
        $stmt2 = $db->prepare("SELECT * FROM agents WHERE id=?");
        $stmt2->execute([$aid]);
        $currentAgent = $stmt2->fetch();
    } catch (Throwable $e) {
        error_log('Recruitment token generation failed: ' . $e->getMessage());
        $tokenMessage = '招待URLの生成に失敗しました。アップデート画面でDBマイグレーションを適用してください。';
        $tokenMsgType = 'error';
    }
}

// ── 過去30日のPV・問い合わせ推移（グラフ用） ──
$pvDaily = $db->prepare("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt
    FROM access_logs
    WHERE agent_id=? AND type='pv' $accessProjectSql AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d ASC
");
$pvDaily->execute(array_merge([$aid], $accessProjectParams));
$pvData = $pvDaily->fetchAll(\PDO::FETCH_KEY_PAIR);

$leadDaily = $db->prepare("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt
    FROM leads
    WHERE agent_id IN ($visiblePlaceholders) $leadProjectSql AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d ASC
");
$leadDaily->execute(array_merge($visibleAgentIds, $leadProjectParams));
$leadData = $leadDaily->fetchAll(\PDO::FETCH_KEY_PAIR);

// 過去30日の日付配列を生成
$labels = [];
$pvVals  = [];
$leadVals = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $labels[]  = date('m/d', strtotime($date));
    $pvVals[]   = (int)($pvData[$date]  ?? 0);
    $leadVals[] = (int)($leadData[$date] ?? 0);
}

// ── 最新問い合わせ5件 ──
$recentLeads = $db->prepare("
    SELECT l.*, a.agent_name AS source_agent_name, a.agent_code AS source_agent_code
    FROM leads l
    LEFT JOIN agents a ON l.agent_id = a.id
    WHERE l.agent_id IN ($visiblePlaceholders) $leadProjectSqlAlias
    ORDER BY l.created_at DESC
    LIMIT 5
");
$recentLeads->execute(array_merge($visibleAgentIds, $leadProjectParams));
$leads = $recentLeads->fetchAll();
?>

<?php if ($notices): ?>
<div style="margin-bottom:1.25rem;">
<?php foreach ($notices as $ntc): ?>
<div style="background:<?= $ntc['is_pinned'] ? 'rgba(201,168,76,.1)' : 'var(--ink)' ?>;
            border:1px solid <?= $ntc['is_pinned'] ? 'rgba(201,168,76,.35)' : 'var(--border)' ?>;
            border-radius:4px;padding:.85rem 1.1rem;margin-bottom:.5rem;display:flex;gap:.75rem;align-items:flex-start;">
    <?php if ($ntc['is_pinned']): ?><span style="flex-shrink:0;font-size:1rem;">📌</span><?php endif; ?>
    <div>
        <p style="font-weight:700;font-size:.88rem;color:var(--gold-lt);"><?= h($ntc['title']) ?></p>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:.2rem;line-height:1.7;"><?= nl2br(h($ntc['body'])) ?></p>
        <p style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem;"><?= date('Y/m/d', strtotime($ntc['created_at'])) ?></p>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['welcome'])): ?>
<div class="alert alert-success">🎉 ようこそ！マイページへのアクセスが完了しました。</div>
<?php endif; ?>

<?php if ($tokenMessage): ?>
<div class="alert alert-<?= h($tokenMsgType) ?>"><?= h($tokenMessage) ?></div>
<?php endif; ?>

<?php if (!empty($selectedAgentProject)): ?>
<div class="card" style="padding:.8rem 1rem;margin-bottom:1rem;">
    <p style="font-size:.8rem;color:var(--text-muted);">表示中プロジェクト: <strong style="color:var(--gold-lt);"><?= h($selectedAgentProject['name'] ?? '') ?></strong></p>
</div>
<?php endif; ?>

<?php if ($ssoPortalEnabled): ?>
<div class="card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;">
    <div>
        <p class="card-title" style="margin:0 0 .3rem;border:none;padding:0;">外部ポータル連携</p>
        <p style="font-size:.82rem;color:var(--text-muted);line-height:1.7;margin:0;">
            この代理店情報で外部ポータルへログインします。連携先が複数ある場合は移動先を選んでください。
        </p>
    </div>
    <div style="display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1rem;">
        <?php foreach ($ssoClients as $ssoClient): ?>
            <a href="/agent/sso_launch.php?client=<?= h(rawurlencode($ssoClient['client_key'])) ?>" class="btn btn-gold" style="white-space:nowrap;">
                <?= h($ssoClient['name']) ?>を開く ↗
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 統計カード -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.25rem;">
<?php
$stats = [
    ['問い合わせ総数', $cntLeads, '📩', ''],
    ['未対応',         $cntNew,   '🔔', $cntNew > 0 ? 'color:#e0a040' : ''],
    ['PV数',          $cntPv,    '👁',  ''],
    ['LINEクリック',  $cntLine,  '💬',  ''],
];
foreach ($stats as [$label, $val, $icon, $style]):
?>
<div class="card" style="text-align:center;padding:1.25rem .75rem;margin-bottom:0;">
    <p style="font-size:1.6rem;"><?= $icon ?></p>
    <p style="font-family:'Noto Serif JP',serif;font-size:1.8rem;font-weight:900;color:var(--gold-lt);margin:.3rem 0;<?= $style ?>"><?= number_format($val) ?></p>
    <p style="font-size:.72rem;color:var(--text-muted);"><?= $label ?></p>
</div>
<?php endforeach; ?>
</div>

<?php if ($isAgentLevel): ?>
<!-- エージェント傘下統計 -->
<div class="card" style="margin-bottom:1.25rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <p class="card-title" style="margin:0;border:none;padding:0;">👥 傘下<?= h($managedLabel) ?></p>
        <a href="/agent/sub_agents.php?mode=<?= $managedLevel === 2 ? 'directors' : 'advisors' ?>" class="btn btn-outline" style="font-size:.78rem;padding:.4rem .85rem;">管理する →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">
        <div style="text-align:center;padding:.75rem;background:rgba(201,168,76,.06);border-radius:4px;border:1px solid var(--border);">
            <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.25rem;"><?= h($managedLabel) ?>数</p>
            <p style="font-family:'Noto Serif JP',serif;font-size:1.6rem;font-weight:900;color:var(--gold-lt);"><?= $subAgentCount ?></p>
        </div>
        <div style="text-align:center;padding:.75rem;background:rgba(201,168,76,.06);border-radius:4px;border:1px solid var(--border);">
            <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.25rem;">傘下問い合わせ</p>
            <p style="font-family:'Noto Serif JP',serif;font-size:1.6rem;font-weight:900;color:var(--gold-lt);"><?= $subTotalLeads ?></p>
        </div>
        <div style="text-align:center;padding:.75rem;background:rgba(201,168,76,.06);border-radius:4px;border:1px solid var(--border);">
            <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.25rem;">傘下未対応</p>
            <p style="font-family:'Noto Serif JP',serif;font-size:1.6rem;font-weight:900;color:<?= $subNewLeads>0?'#e0a040':'var(--gold-lt)' ?>;"><?= $subNewLeads ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ④ グラフ -->
<div class="card" style="margin-bottom:1.25rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <p class="card-title" style="margin:0;border:none;padding:0;">過去30日の推移</p>
        <div style="display:flex;gap:1rem;font-size:.75rem;">
            <span style="display:flex;align-items:center;gap:.35rem;"><span style="width:12px;height:3px;background:#C9A84C;border-radius:2px;display:inline-block;"></span>PV</span>
            <span style="display:flex;align-items:center;gap:.35rem;"><span style="width:12px;height:3px;background:#5ecb9b;border-radius:2px;display:inline-block;"></span>問い合わせ</span>
        </div>
    </div>
    <canvas id="trendChart" style="width:100%;height:220px;"></canvas>
</div>

<!-- LP情報 -->
<div class="card" style="margin-bottom:1.25rem;">
    <p class="card-title">あなたのLP</p>
    <?php
    $projectLpRows = getAgentProjectLpUrls($currentAgent);
    if (!empty($selectedAgentProjectId) && count($projectLpRows) > 1) {
        usort($projectLpRows, function($a, $b) use ($selectedAgentProjectId) {
            $aSelected = (int)($a['project']['id'] ?? 0) === (int)$selectedAgentProjectId ? 0 : 1;
            $bSelected = (int)($b['project']['id'] ?? 0) === (int)$selectedAgentProjectId ? 0 : 1;
            return $aSelected <=> $bSelected;
        });
    }
    $lpUrl = $projectLpRows[0]['url'] ?? buildAgentProjectLpUrl((string)$currentAgent['agent_code'], null);
    $qrImageUrl = 'https://quickchart.io/qr?size=360&margin=2&text=' . rawurlencode($lpUrl);
    ?>
    <?php if (!empty($projectLpRows[0]['project']['name'])): ?>
    <p style="font-size:.78rem;color:var(--text-muted);margin:-.35rem 0 .7rem;">選択中: <?= h($projectLpRows[0]['project']['name']) ?></p>
    <?php endif; ?>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;">
        <code style="background:rgba(201,168,76,.08);border:1px solid var(--border);padding:.5rem .85rem;border-radius:3px;font-size:.85rem;color:var(--gold-lt);flex:1;min-width:0;word-break:break-all;"><?= h($lpUrl) ?></code>
        <a href="<?= h($lpUrl) ?>" target="_blank" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">確認 ↗</a>
        <button onclick="navigator.clipboard.writeText('<?= h($lpUrl) ?>').then(()=>{this.textContent='✓ コピー済';setTimeout(()=>this.textContent='コピー',2000)})" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">コピー</button>
        <button onclick="toggleQR()" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">QR表示</button>
        <button onclick="shareLP()" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">共有</button>
    </div>

    <!-- ⑫ QRコード -->
    <?php if (!empty($projectLpRows)): ?>
    <div style="display:grid;gap:.65rem;margin:1rem 0;">
        <?php foreach ($projectLpRows as $row): ?>
        <?php $project = $row['project']; $url = $row['url']; ?>
        <?php $isSelectedProjectRow = !empty($selectedAgentProjectId) && (int)($project['id'] ?? 0) === (int)$selectedAgentProjectId; ?>
        <div style="display:grid;grid-template-columns:minmax(110px,200px) 1fr auto auto;gap:.55rem;align-items:center;border:1px solid <?= $isSelectedProjectRow ? 'var(--gold)' : 'var(--border)' ?>;border-radius:4px;padding:.65rem;background:<?= $isSelectedProjectRow ? 'rgba(201,168,76,.12)' : 'rgba(255,255,255,.03)' ?>;">
            <strong style="color:var(--cream);font-size:.84rem;"><?= h($project['name']) ?></strong>
            <code style="color:var(--gold-lt);word-break:break-all;font-size:.8rem;"><?= h($url) ?></code>
            <a href="<?= h($url) ?>" target="_blank" class="btn btn-outline" style="font-size:.76rem;padding:.4rem .7rem;">Open</a>
            <button type="button" onclick="navigator.clipboard.writeText(<?= h(json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>).then(()=>{this.textContent='Copied';setTimeout(()=>this.textContent='Copy',1600)})" class="btn btn-outline" style="font-size:.76rem;padding:.4rem .7rem;">Copy</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div id="qrWrap" style="display:none;margin-top:.75rem;text-align:center;">
        <div style="display:inline-block;background:#fff;padding:1rem;border-radius:6px;border:1px solid var(--border);max-width:100%;">
            <img id="qrImage" src="<?= h($qrImageUrl) ?>" alt="LP QRコード" style="display:block;width:min(260px,70vw);height:auto;">
        </div>
        <div style="margin-top:.75rem;display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
            <a href="<?= h($qrImageUrl) ?>" target="_blank" class="btn btn-gold" style="font-size:.82rem;padding:.5rem 1.1rem;">画像を開く</a>
            <button onclick="printQR()" class="btn btn-outline" style="font-size:.82rem;padding:.5rem 1.1rem;">印刷</button>
            <button onclick="toggleQR()" class="btn btn-outline" style="font-size:.82rem;padding:.5rem 1.1rem;">閉じる</button>
        </div>
        <p style="font-size:.72rem;color:var(--text-muted);margin-top:.5rem;">画像を開いて保存すると、チラシやSNS投稿に利用できます。</p>
    </div>
</div>

<?php if ($myLv >= 2): ?>
<!-- エージェント専用申請URL -->
<div class="card" style="margin-bottom:1.25rem;">
    <p class="card-title">🔗 <?= $managedLevel === 1 ? '招待URL（' . h($advisorInviteTargetsText) . '）' : h($managedLabel) . '招待URL' ?></p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;line-height:1.8;">
        <?php if ($managedLevel === 1): ?>
        <?= h($advisorInviteTargetsText) ?>として招待するためのURLです。共有する相手の区分に合わせてURLをコピーしてください。<br>
        <?php else: ?>
        このURLを共有すると、あなたの傘下<?= h($managedLabel) ?>として申請フォームに誘導できます。<br>
        <?php endif; ?>
        申請が届いたら「傘下管理」の「申請一覧」から承認してください。
    </p>
    <?php
    $baseUrl   = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];
    $applyToken = $myLv >= 3
        ? ($currentAgent['apply_token_director'] ?? ($currentAgent['apply_token'] ?? ''))
        : ($currentAgent['apply_token_advisor'] ?? '');
    $applyTokenExp = $myLv >= 3
        ? ($currentAgent['apply_token_director_exp'] ?? ($currentAgent['apply_token_exp'] ?? ''))
        : ($currentAgent['apply_token_advisor_exp'] ?? '');
    $tokenValid = $applyToken && $applyTokenExp && strtotime($applyTokenExp) > time();
    $applyUrl  = $tokenValid ? $baseUrl.'/join/'.$applyToken : '';
    ?>
    <?php if ($tokenValid): ?>
    <?php if ($managedLevel === 1): ?>
    <?php foreach ($advisorPositionLabels as $posKey => $posLabel): $positionApplyUrl = buildAdvisorJoinUrl($baseUrl, $applyToken, $posKey, false); ?>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem;">
        <span style="font-size:.78rem;color:var(--gold-lt);min-width:8.5rem;"><?= h($posLabel) ?></span>
        <code style="background:rgba(201,168,76,.08);border:1px solid var(--border);padding:.5rem .85rem;border-radius:3px;font-size:.82rem;color:var(--gold-lt);flex:1;min-width:0;word-break:break-all;"><?= h($positionApplyUrl) ?></code>
        <button onclick="navigator.clipboard.writeText('<?= h($positionApplyUrl) ?>').then(()=>{this.textContent='✓ コピー済';setTimeout(()=>this.textContent='コピー',2000)})" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">コピー</button>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem;">
        <code style="background:rgba(201,168,76,.08);border:1px solid var(--border);padding:.5rem .85rem;border-radius:3px;font-size:.82rem;color:var(--gold-lt);flex:1;min-width:0;word-break:break-all;"><?= h($applyUrl) ?></code>
        <button onclick="navigator.clipboard.writeText('<?= h($applyUrl) ?>').then(()=>{this.textContent='✓ コピー済';setTimeout(()=>this.textContent='コピー',2000)})" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">コピー</button>
    </div>
    <?php endif; ?>
    <p style="font-size:.75rem;color:var(--text-muted);">有効期限：<?= date('Y/m/d', strtotime($applyTokenExp)) ?>　<a href="/agent/dashboard.php?regen_token=1&token_type=<?= $myLv >= 3 ? 'director' : 'advisor' ?>" style="color:var(--gold);">再生成</a></p>
    <?php else: ?>
    <a href="/agent/dashboard.php?regen_token=1&token_type=<?= $myLv >= 3 ? 'director' : 'advisor' ?>" class="btn btn-gold" style="font-size:.88rem;">招待URLを生成する</a>
    <?php endif; ?>
</div>
<?php
$advisorApplyToken = $currentAgent['apply_token_advisor'] ?? '';
$advisorApplyTokenExp = $currentAgent['apply_token_advisor_exp'] ?? '';
if (!$advisorApplyToken && empty($tokenColumns['apply_token_advisor']) && !empty($currentAgent['apply_token'])) {
    $advisorApplyToken = $currentAgent['apply_token'];
    $advisorApplyTokenExp = $currentAgent['apply_token_exp'] ?? '';
}
$advisorTokenValid = $advisorApplyToken && $advisorApplyTokenExp && strtotime($advisorApplyTokenExp) > time();
?>
<?php if ($myLv >= 3): ?>
<div class="card" style="margin-bottom:1.25rem;">
    <p class="card-title">🔗 招待URL（<?= h($advisorInviteTargetsText) ?>）</p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;line-height:1.8;">
        エージェント自身がディレクターを兼ねて、直下に<?= h($advisorInviteTargetsText) ?>を招待するURLです。<br>
        共有する相手の区分に合わせてURLをコピーしてください。<br>
        申請が届いたら「<?= h($levelLabels[1] ?? 'アドバイザー') ?>管理」から承認してください。
    </p>
    <?php $advisorApplyUrl = $advisorTokenValid ? $baseUrl . '/join/' . $advisorApplyToken . (empty($tokenColumns['apply_token_advisor']) ? '?target=advisor' : '') : ''; ?>
    <?php if ($advisorTokenValid): ?>
    <?php foreach ($advisorPositionLabels as $posKey => $posLabel): $positionApplyUrl = buildAdvisorJoinUrl($baseUrl, $advisorApplyToken, $posKey, empty($tokenColumns['apply_token_advisor'])); ?>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem;">
        <span style="font-size:.78rem;color:var(--gold-lt);min-width:8.5rem;"><?= h($posLabel) ?></span>
        <code style="background:rgba(201,168,76,.08);border:1px solid var(--border);padding:.5rem .85rem;border-radius:3px;font-size:.82rem;color:var(--gold-lt);flex:1;min-width:0;word-break:break-all;"><?= h($positionApplyUrl) ?></code>
        <button onclick="navigator.clipboard.writeText('<?= h($positionApplyUrl) ?>').then(()=>{this.textContent='✓ コピー済';setTimeout(()=>this.textContent='コピー',2000)})" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">コピー</button>
    </div>
    <?php endforeach; ?>
    <div style="margin-bottom:.75rem;">
        <a href="/agent/sub_agents.php?mode=advisors" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.5rem .9rem;">管理</a>
    </div>
    <p style="font-size:.75rem;color:var(--text-muted);">有効期限：<?= date('Y/m/d', strtotime($advisorApplyTokenExp)) ?>　<a href="/agent/dashboard.php?regen_token=1&token_type=advisor" style="color:var(--gold);">再生成</a></p>
    <?php else: ?>
    <a href="/agent/dashboard.php?regen_token=1&token_type=advisor" class="btn btn-gold" style="font-size:.88rem;">招待URLを生成する</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- 最新問い合わせ -->
<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">最新の問い合わせ</p>
        <a href="/agent/leads.php" class="btn btn-outline" style="padding:.35rem .8rem;font-size:.75rem;">すべて見る</a>
    </div>
    <table>
        <thead><tr><th>名前</th><th>メール</th><th>日時</th><th>状態</th></tr></thead>
        <tbody>
        <?php if ($leads): foreach ($leads as $l): ?>
        <tr>
            <td><?= h($l['name']) ?></td>
            <td style="font-size:.78rem;"><a href="mailto:<?= h($l['email']) ?>" style="color:var(--gold);text-decoration:none;"><?= h($l['email']) ?></a></td>
            <td style="font-size:.75rem;color:var(--text-muted);"><?= date('m/d H:i', strtotime($l['created_at'])) ?></td>
            <td><span class="badge badge-<?= in_array($l['status'], ['contacted','prospect','won'], true) ? 'contacted' : (in_array($l['status'], ['lost','closed'], true) ? 'closed' : 'new') ?>"><?= ['new'=>'新着','contacted'=>'対応中','prospect'=>'成約見込み','won'=>'成約','lost'=>'失注','closed'=>'完了'][$l['status']] ?? $l['status'] ?></span></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem;">まだ問い合わせはありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// ⑫ 専用LP URLのQR表示・共有
const LP_URL = '<?= h($lpUrl) ?>';
const LP_QR_URL = '<?= h($qrImageUrl) ?>';

function toggleQR() {
    const wrap = document.getElementById('qrWrap');
    if (wrap.style.display === 'none') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
    }
}

function shareLP() {
    if (navigator.share) {
        navigator.share({ title: '専用LP', text: '専用LPはこちらです', url: LP_URL }).catch(function(){});
        return;
    }
    navigator.clipboard.writeText(LP_URL).then(function() {
        alert('専用URLをコピーしました。');
    });
}

function printQR() {
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>LP QRコード</title><style>body{font-family:sans-serif;text-align:center;padding:32px;}img{width:260px;height:260px;}p{word-break:break-all;}</style></head><body><h1>専用LP QRコード</h1><img src="' + LP_QR_URL + '" alt="QR"><p>' + LP_URL + '</p><script>window.onload=function(){window.print();}<\/script></body></html>');
    w.document.close();
}

// ④ Chart.js でグラフ描画
(function() {
    const labels   = <?= json_encode($labels) ?>;
    const pvVals   = <?= json_encode($pvVals) ?>;
    const leadVals = <?= json_encode($leadVals) ?>;

    const canvas = document.getElementById('trendChart');
    const ctx    = canvas.getContext('2d');

    // DPR対応
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.parentElement.getBoundingClientRect();
    canvas.width  = rect.width * dpr;
    canvas.height = 220 * dpr;
    canvas.style.width  = rect.width + 'px';
    canvas.style.height = '220px';
    ctx.scale(dpr, dpr);

    const W = rect.width, H = 220;
    const padL = 36, padR = 16, padT = 16, padB = 36;
    const chartW = W - padL - padR;
    const chartH = H - padT - padB;
    const n = labels.length;

    const maxVal = Math.max(...pvVals, ...leadVals, 1);

    // グリッド描画
    ctx.strokeStyle = 'rgba(201,168,76,.1)';
    ctx.lineWidth = 1;
    const gridLines = 4;
    for (let i = 0; i <= gridLines; i++) {
        const y = padT + chartH * (1 - i / gridLines);
        ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(padL + chartW, y); ctx.stroke();
        // Y軸ラベル
        ctx.fillStyle = 'rgba(245,240,232,.35)';
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxVal * i / gridLines), padL - 4, y + 4);
    }

    // X軸ラベル（5個おき）
    ctx.fillStyle = 'rgba(245,240,232,.35)';
    ctx.font = '10px sans-serif';
    ctx.textAlign = 'center';
    for (let i = 0; i < n; i++) {
        if (i % 5 === 0 || i === n-1) {
            const x = padL + chartW * i / (n - 1);
            ctx.fillText(labels[i], x, H - padB + 14);
        }
    }

    // 折れ線を描く関数
    function drawLine(vals, color, fill) {
        ctx.beginPath();
        for (let i = 0; i < n; i++) {
            const x = padL + chartW * i / (n - 1);
            const y = padT + chartH * (1 - vals[i] / maxVal);
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        }
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.stroke();

        // 塗りつぶし
        ctx.lineTo(padL + chartW, padT + chartH);
        ctx.lineTo(padL, padT + chartH);
        ctx.closePath();
        ctx.fillStyle = fill;
        ctx.fill();

        // データポイント
        ctx.fillStyle = color;
        for (let i = 0; i < n; i++) {
            if (vals[i] > 0) {
                const x = padL + chartW * i / (n - 1);
                const y = padT + chartH * (1 - vals[i] / maxVal);
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }

    drawLine(pvVals,   '#C9A84C', 'rgba(201,168,76,.08)');
    drawLine(leadVals, '#5ecb9b', 'rgba(94,203,155,.08)');
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

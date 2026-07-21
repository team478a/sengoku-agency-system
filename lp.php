<?php
require_once __DIR__ . '/includes/functions.php';

// ── プレビューモード（管理者・代理店） ──
if (!empty($_GET['preview']) && !empty($_GET['template_id'])) {
    // セッションを先に開始してから認証チェック
    startSecureSession();

    $isAdminPreview = !empty($_SESSION['admin_id']);
    $previewAgentId = (int)($_SESSION['agent_id'] ?? 0);

    if (!$isAdminPreview && $previewAgentId <= 0) {
        header('Location: /agent/login.php');
        exit;
    }

    $db  = getDB();
    $id  = (int)$_GET['template_id'];
    $stmt = $db->prepare("SELECT * FROM lp_templates WHERE id = ?");
    $stmt->execute([$id]);
    $tpl = $stmt->fetch();

    if (!$tpl) {
        echo '<p style="padding:2rem;color:#e08080;background:#111;">テンプレートが見つかりません（ID:' . $id . '）</p>';
        exit;
    }

    $tplFile = __DIR__ . '/templates/' . $tpl['slug'] . '/' . $tpl['html_file'];

    if (!file_exists($tplFile)) {
        echo '<p style="padding:2rem;color:#e08080;background:#111;">ファイルが見つかりません: ' . htmlspecialchars($tplFile) . '</p>';
        exit;
    }

    $agent = null;

    if (!$isAdminPreview) {
        $agentStmt = $db->prepare("SELECT * FROM agents WHERE id = ? AND status = 'active' LIMIT 1");
        $agentStmt->execute([$previewAgentId]);
        $agent = $agentStmt->fetch() ?: null;

        if (!$agent) {
            header('Location: /agent/login.php?err=inactive');
            exit;
        }

        $agent['default_template_id'] = $tpl['id'];
        $agent['template_slug'] = $tpl['slug'];
        $agent['template_name'] = $tpl['name'];
        $agent['html_file'] = $tpl['html_file'];
    }

    if (!$agent) {
        // 管理者プレビューでは実在代理店に依存しないサンプル情報で表示する
        $agent = [
            'id'                  => 0,
            'agent_code'          => 'preview',
            'agent_name'          => 'サンプル商事',
            'person_name'         => '山田 太郎',
            'email'               => 'sample@example.com',
            'phone'               => '090-0000-0000',
            'line_url'            => 'https://lin.ee/example',
            'profile_image'       => '',
            'profile_text'        => '戦国経済圏の専任担当として、参入から運用まで丁寧にサポートします。',
            'show_form'           => 1,
            'show_line_btn'       => 1,
            'default_template_id' => $tpl['id'],
            'status'              => 'active',
            'template_slug'       => $tpl['slug'],
            'template_name'       => $tpl['name'],
            'html_file'           => $tpl['html_file'],
        ];
    }

    $csrfToken = getCsrfToken();
    $previewModeLabel = $isAdminPreview ? 'ダミーデータで表示中' : 'あなたの情報で表示中';
    $backUrl = $isAdminPreview ? '/admin/templates.php' : '/agent/settings.php';
    $backLabel = $isAdminPreview ? '← 管理画面に戻る' : '← 設定に戻る';

    // プレビューバー
    $previewBar = '<style>
#__pvBar{position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#0F0D0A,#1a1510);border-bottom:2px solid #C9A84C;padding:.55rem 1.25rem;display:flex;align-items:center;justify-content:space-between;font-family:sans-serif;font-size:.8rem;box-shadow:0 2px 20px rgba(0,0,0,.5);}
#__pvBar .badge{background:rgba(201,168,76,.2);border:1px solid rgba(201,168,76,.5);color:#E2C87A;padding:.18rem .6rem;border-radius:2px;font-size:.7rem;font-weight:700;}
#__pvBar a{padding:.38rem .9rem;border-radius:3px;font-size:.76rem;font-weight:700;text-decoration:none;background:rgba(255,255,255,.08);color:rgba(245,240,232,.75);border:1px solid rgba(255,255,255,.12);}
#__pvBar select{padding:.3rem .55rem;background:rgba(255,255,255,.06);border:1px solid rgba(201,168,76,.3);color:rgba(245,240,232,.8);border-radius:3px;font-size:.74rem;}
body{padding-top:40px!important;}
</style>
<div id="__pvBar">
  <div style="display:flex;align-items:center;gap:.7rem;">
    <span class="badge">PREVIEW</span>
    <span style="color:rgba(245,240,232,.85);">' . htmlspecialchars($tpl['name'], ENT_QUOTES) . '</span>
    <span style="color:rgba(245,240,232,.3);font-size:.73rem;">' . htmlspecialchars($previewModeLabel, ENT_QUOTES) . '</span>
  </div>
  <div style="display:flex;align-items:center;gap:.5rem;">
    <select onchange="var w=this.value;document.body.style.cssText=w===\'full\'?\'\':\'max-width:\'+w+\';margin:40px auto 0;box-shadow:0 0 40px rgba(0,0,0,.6)\'">
      <option value="full">🖥 PC</option>
      <option value="768px">📱 タブレット</option>
      <option value="390px">📱 スマホ</option>
    </select>
    <a href="' . htmlspecialchars($backUrl, ENT_QUOTES) . '">' . htmlspecialchars($backLabel, ENT_QUOTES) . '</a>
  </div>
</div>';

    // テンプレート出力
    ob_start();
    include $tplFile;
    $output = ob_get_clean();
    $output = applyLpTemplateTokens($output, $agent);

    // <body>直後にプレビューバーを注入
    $withPreviewBar = preg_replace('/(<body[^>]*>)/i', '$1' . $previewBar, $output, 1, $replaceCount);
    echo $replaceCount > 0 ? $withPreviewBar : $previewBar . $output;
    exit;
}

// ── 通常LP表示 ──
$agentCode = trim($_GET['agent_code'] ?? '');

if (empty($agentCode) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $agentCode)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$agent = getAgentByCode($agentCode);

if (!$agent) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$selectedTemplateId = !empty($agent['default_template_id']) ? (int)$agent['default_template_id'] : null;
$tplSlug = trim((string)($_GET['tpl'] ?? $_GET['template'] ?? ''));
$projectSlug = trim((string)($_GET['project'] ?? ''));
if ($tplSlug !== '' && preg_match('/^[a-zA-Z0-9_\-]+$/', $tplSlug)) {
    $db = getDB();
    $tplStmt = $db->prepare("SELECT * FROM lp_templates WHERE slug=? AND status='active' LIMIT 1");
    $tplStmt->execute([$tplSlug]);
    $selectedTpl = $tplStmt->fetch();
    if ($selectedTpl) {
        $agent['default_template_id'] = (int)$selectedTpl['id'];
        $agent['template_slug'] = $selectedTpl['slug'];
        $agent['template_name'] = $selectedTpl['name'];
        $agent['html_file'] = $selectedTpl['html_file'];
        $agent['template_project_id'] = $selectedTpl['project_id'] ?? null;
        $selectedTemplateId = (int)$selectedTpl['id'];
    }
} elseif ($projectSlug !== '' && preg_match('/^[a-zA-Z0-9_\-]+$/', $projectSlug)) {
    $db = getDB();
    $projectStmt = $db->prepare("SELECT * FROM projects WHERE slug=? AND status='active' LIMIT 1");
    $projectStmt->execute([$projectSlug]);
    $project = $projectStmt->fetch();
    if ($project) {
        $selectedTpl = getProjectTemplateForAgent($agent, (int)$project['id']);
        if ($selectedTpl) {
            $agent['default_template_id'] = (int)$selectedTpl['id'];
            $agent['template_slug'] = $selectedTpl['slug'];
            $agent['template_name'] = $selectedTpl['name'];
            $agent['html_file'] = $selectedTpl['html_file'];
            $agent['template_project_id'] = $selectedTpl['project_id'] ?? null;
            $selectedTemplateId = (int)$selectedTpl['id'];
        }
    }
}

$selectedProjectId = getLpProjectIdFromTemplate($selectedTemplateId);
$referralContext = resolveLpReferralContext($agent, $selectedProjectId);
$agent = array_merge($agent, $referralContext);

logAccess((int)$agent['id'], 'pv', $selectedTemplateId, $referralContext);

$templateFile = __DIR__ . '/templates/' . $agent['template_slug'] . '/' . $agent['html_file'];

if (!$agent['template_slug'] || !file_exists($templateFile)) {
    $templateFile = __DIR__ . '/templates/samurai/samurai.php';
}

$csrfToken = getCsrfToken();
ob_start();
include $templateFile;
$output = ob_get_clean();
echo applyLpTemplateTokens($output, $agent);

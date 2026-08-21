<?php
require_once __DIR__ . '/includes/functions.php';

function renderLpEmergencyFallback(array $agent): string {
    $siteName = defined('SITE_NAME') ? SITE_NAME : '千ノ国代理店システム';
    $agentName = (string)($agent['person_name'] ?? $agent['agent_name'] ?? '担当者');
    $profile = (string)($agent['profile_text'] ?? '');
    $lineUrl = trim((string)($agent['line_url'] ?? ''));
    $contactUrl = '/contact.php?agent=' . rawurlencode((string)($agent['agent_code'] ?? ''));
    $lpTitle = (string)($agent['template_name'] ?? 'ご案内ページ');
    $lineButton = $lineUrl !== ''
        ? '<a class="primary" href="' . h($lineUrl) . '">LINEで相談する</a>'
        : '';

    return '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . h($lpTitle) . ' | ' . h($siteName) . '</title>'
        . '<meta name="robots" content="noindex,follow">'
        . '<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#100d09;color:#f8f2df;line-height:1.8}.wrap{max-width:760px;margin:0 auto;padding:48px 20px}.card{border:1px solid rgba(218,178,72,.35);background:#18130d;border-radius:12px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.32)}h1{font-size:clamp(28px,7vw,48px);line-height:1.25;margin:0 0 14px;color:#e6c567}.lead{color:#d7c8a8}.buttons{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.primary,.secondary{display:inline-block;padding:12px 18px;border-radius:8px;font-weight:700;text-decoration:none}.primary{background:#06c755;color:#fff}.secondary{border:1px solid #d6b24b;color:#f8f2df}.note{margin-top:22px;color:#b7aa91;font-size:14px}</style>'
        . '</head><body><main class="wrap"><section class="card">'
        . '<p class="lead">' . h($siteName) . '</p>'
        . '<h1>' . h($lpTitle) . '</h1>'
        . '<p>担当：' . h($agentName) . '</p>'
        . ($profile !== '' ? '<p class="lead">' . nl2br(h($profile)) . '</p>' : '')
        . '<div class="buttons">' . $lineButton . '<a class="secondary" href="' . h($contactUrl) . '">問い合わせる</a></div>'
        . '<p class="note">現在、通常テンプレートの読み込みに失敗したため、簡易表示に切り替えています。</p>'
        . '</section></main></body></html>';
}

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

    $id  = (int)$_GET['template_id'];
    $tpl = lpTemplateRepository()->find($id);

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
        $db = getDB();
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
    $output = landingPageRenderer()->renderFile($tplFile, $agent, getCsrfToken());

    // <body>直後にプレビューバーを注入
    echo landingPageRenderer()->injectPreviewBar($output, $previewBar);
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
    $selectedTpl = lpTemplateRepository()->activeBySlug($tplSlug);
    if ($selectedTpl) {
        $agent['default_template_id'] = (int)$selectedTpl['id'];
        $agent['template_slug'] = $selectedTpl['slug'];
        $agent['template_name'] = $selectedTpl['name'];
        $agent['html_file'] = $selectedTpl['html_file'];
        $agent['template_project_id'] = $selectedTpl['project_id'] ?? null;
        $selectedTemplateId = (int)$selectedTpl['id'];
    }
} elseif ($projectSlug !== '' && preg_match('/^[a-zA-Z0-9_\-]+$/', $projectSlug)) {
    $project = null;
    try {
        $db = getDB();
        $projectStmt = $db->prepare("SELECT * FROM projects WHERE slug=? AND status='active' LIMIT 1");
        $projectStmt->execute([$projectSlug]);
        $project = $projectStmt->fetch();
    } catch (Throwable $e) {
        error_log('LP project resolve failed: ' . $e->getMessage());
    }
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

try {
    $selectedProjectId = getLpProjectIdFromTemplate($selectedTemplateId);
} catch (Throwable $e) {
    error_log('LP project id resolve failed: ' . $e->getMessage());
    $selectedProjectId = null;
}

try {
    $referralContext = resolveLpReferralContext($agent, $selectedProjectId);
} catch (Throwable $e) {
    error_log('LP referral context failed: ' . $e->getMessage());
    $referralContext = [];
}
$agent = array_merge($agent, $referralContext);

try {
    logAccess((int)$agent['id'], 'pv', $selectedTemplateId, $referralContext);
} catch (Throwable $e) {
    error_log('LP access log failed: ' . $e->getMessage());
}

try {
    $templateFile = landingPageRenderer()->templateFile($agent);
    $output = landingPageRenderer()->renderFile($templateFile, $agent, getCsrfToken());
    if ($output === '') {
        throw new RuntimeException('LP template returned empty output.');
    }
} catch (Throwable $e) {
    error_log('LP render failed: ' . $e->getMessage());
    $output = renderLpEmergencyFallback($agent);
}

try {
    if (isLpAiReadableRequest()) {
        respondLpAiReadable($output, $agent);
    }
} catch (Throwable $e) {
    error_log('LP AI readable response failed: ' . $e->getMessage());
}
echo $output;

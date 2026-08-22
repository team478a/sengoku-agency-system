<?php
require_once __DIR__ . '/includes/functions.php';

$agentCode = trim((string)($_GET['agent'] ?? $_GET['agent_code'] ?? $_GET['code'] ?? ''));

if ($agentCode === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $agentCode)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Agent not found.\n";
    exit;
}

$agent = getAgentByCode($agentCode);
if (!$agent) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Agent not found.\n";
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

$templateFile = __DIR__ . '/templates/' . ($agent['template_slug'] ?? '') . '/' . ($agent['html_file'] ?? '');
if (empty($agent['template_slug']) || !file_exists($templateFile)) {
    $templateFile = __DIR__ . '/templates/samurai/samurai.php';
}

$csrfToken = getCsrfToken();
ob_start();
include $templateFile;
$output = ob_get_clean();
$output = applyLpTemplateTokens($output, $agent);

respondLpAiReadable($output, $agent);

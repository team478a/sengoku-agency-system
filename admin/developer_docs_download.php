<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$docs = [
    'handoff' => [
        'path' => __DIR__ . '/../docs/integration/EXTERNAL_DEVELOPER_HANDOFF.md',
        'filename' => 'sengoku-ai-external-developer-handoff.md',
    ],
    'setup-flow' => [
        'path' => __DIR__ . '/../docs/integration/EXTERNAL_INTEGRATION_SETUP_FLOW_20260803.md',
        'filename' => 'sengoku-ai-external-integration-setup-flow.md',
    ],
    'full-guide' => [
        'path' => __DIR__ . '/../docs/integration/EXTERNAL_DEVELOPER_GUIDE.md',
        'filename' => 'sengoku-ai-external-developer-guide.md',
    ],
];

$doc = (string)($_GET['doc'] ?? 'handoff');
if (!isset($docs[$doc])) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$path = $docs[$doc]['path'];
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    echo 'Document file is not available.';
    exit;
}

$filename = $docs[$doc]['filename'];
$content = (string)file_get_contents($path);

header('Content-Type: text/markdown; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . strlen($content));
header('X-Content-Type-Options: nosniff');
echo $content;
exit;

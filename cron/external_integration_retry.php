<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$savedToken = getSystemSettingValue('external_integration_retry_cron_token', '');

header('Content-Type: application/json; charset=UTF-8');

if ($savedToken === '' || $token === '' || !hash_equals($savedToken, $token)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'invalid_token',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$siteKey = trim((string)($_GET['site_key'] ?? $_POST['site_key'] ?? ''));
$limit = min(50, max(1, (int)($_GET['limit'] ?? $_POST['limit'] ?? 10)));
$notify = (string)($_GET['notify'] ?? $_POST['notify'] ?? '') === '1';

$summary = retryFailedExternalIntegrationLogs($siteKey, $limit);
$ok = (int)$summary['failed_count'] === 0;

if ($notify && (int)$summary['failed_count'] > 0) {
    $adminEmail = getSystemSettingValue('admin_email', '');
    if ($adminEmail !== '') {
        $subject = '[Sengoku] External integration retry still has failures';
        $body = "External integration retry result.\n\n"
            . 'Target: ' . (int)$summary['target_count'] . "\n"
            . 'Success: ' . (int)$summary['success_count'] . "\n"
            . 'Failed: ' . (int)$summary['failed_count'] . "\n\n"
            . implode("\n", array_slice($summary['errors'] ?? [], 0, 20));
        @mail($adminEmail, $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\n");
    }
}

echo json_encode([
    'ok' => $ok,
    'summary' => $summary,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
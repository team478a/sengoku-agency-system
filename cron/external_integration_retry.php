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
$source = trim((string)($_GET['source'] ?? $_POST['source'] ?? 'both'));

$summary = [
    'outbox' => [
        'target_count' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'dlq_count' => 0,
        'errors' => [],
    ],
    'legacy_logs' => [
        'target_count' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'errors' => [],
    ],
];

if ($source === 'outbox' || $source === 'both' || $source === '') {
    $summary['outbox'] = retryDueIntegrationOutboxEvents($siteKey, $limit, false);
}

$legacyLimit = $limit;
if ($source === 'both') {
    $legacyLimit = max(1, $limit - (int)($summary['outbox']['target_count'] ?? 0));
}
if (($source === 'legacy' || $source === 'both') && $legacyLimit > 0) {
    $summary['legacy_logs'] = retryFailedExternalIntegrationLogs($siteKey, $legacyLimit);
}

$failedCount = (int)($summary['outbox']['failed_count'] ?? 0) + (int)($summary['legacy_logs']['failed_count'] ?? 0);
$ok = $failedCount === 0 && (int)($summary['outbox']['dlq_count'] ?? 0) === 0;

if ($notify && (!$ok || $failedCount > 0)) {
    $adminEmail = getSystemSettingValue('admin_email', '');
    if ($adminEmail !== '') {
        $subject = '[Sengoku] External integration retry still has failures';
        $body = "External integration retry result.\n\n"
            . 'Outbox Target: ' . (int)($summary['outbox']['target_count'] ?? 0) . "\n"
            . 'Outbox Success: ' . (int)($summary['outbox']['success_count'] ?? 0) . "\n"
            . 'Outbox Failed: ' . (int)($summary['outbox']['failed_count'] ?? 0) . "\n"
            . 'Outbox DLQ: ' . (int)($summary['outbox']['dlq_count'] ?? 0) . "\n"
            . 'Legacy Target: ' . (int)($summary['legacy_logs']['target_count'] ?? 0) . "\n"
            . 'Legacy Success: ' . (int)($summary['legacy_logs']['success_count'] ?? 0) . "\n"
            . 'Legacy Failed: ' . (int)($summary['legacy_logs']['failed_count'] ?? 0) . "\n\n"
            . implode("\n", array_slice(array_merge($summary['outbox']['errors'] ?? [], $summary['legacy_logs']['errors'] ?? []), 0, 20));
        @mail($adminEmail, $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\n");
    }
}

echo json_encode([
    'ok' => $ok,
    'summary' => $summary,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

<?php
require_once __DIR__ . '/../../v2/bootstrap.php';

$auth = apiV2Authenticate();
apiV2RequireScope($auth, 'sso:issue');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);
}
if (!commonIdTablesReady()) {
    apiV2Error('COMMON_ID_SCHEMA_NOT_READY', 'Common ID tables are not migrated.', 503);
}

$idempotencyKey = apiV2IdempotencyKey();
if ($idempotencyKey !== '') {
    $stored = apiV2IdempotencyLookup($idempotencyKey);
    if ($stored) {
        http_response_code((int)($stored['response_status'] ?? 200));
        echo (string)($stored['response_body'] ?? '{}');
        exit;
    }
}

$data = apiV2ReadJson();
$clientKey = trim((string)($data['client_key'] ?? $data['site_key'] ?? ''));
$audience = trim((string)($data['aud'] ?? $data['audience'] ?? ''));
$client = $clientKey !== '' ? getSsoClientByKey($clientKey) : null;
if (!$client && $audience !== '') {
    $client = getSsoClientByAudience($audience);
}
if (!$client) {
    apiV2Error('SSO_CLIENT_NOT_FOUND', 'SSO client was not found.', 404);
}

$commonUserId = trim((string)($data['common_user_id'] ?? ''));
if ($commonUserId === '') {
    $systemKey = trim((string)($data['service_code'] ?? $data['system_key'] ?? $data['service_key'] ?? $auth['site_key'] ?? ''));
    $externalUserId = trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? $data['user_id'] ?? ''));
    if ($systemKey !== '' && $externalUserId !== '') {
        $link = findSystemAccountLink($systemKey, $externalUserId) ?: findCommonUserMapping($systemKey, $externalUserId);
        if ($link) {
            $commonUserId = (string)$link['common_user_id'];
        }
    }
}
if ($commonUserId === '') {
    apiV2Error('VALIDATION_ERROR', 'common_user_id or external user key is required.', 422);
}

$profile = loadCommonUserHubProfile($commonUserId);
if (!$profile) {
    apiV2Error('COMMON_USER_NOT_FOUND', 'Common user was not found.', 404);
}

try {
    $token = buildCustomerSsoJwt($profile, $client, trim((string)($data['return_to'] ?? '')) ?: null, [
        'source_system_key' => $auth['site_key'] ?? '',
        'target_system_key' => $client['client_key'] ?? '',
    ]);
} catch (Throwable $e) {
    logIntegrationEvent([
        'direction' => 'inbound',
        'site_key' => $auth['site_key'] ?? null,
        'event_type' => 'customer_sso.issue_failed',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'http_status' => 500,
        'success' => 0,
        'common_user_id' => $commonUserId,
        'request_body' => $data,
        'error_message' => $e->getMessage(),
    ]);
    apiV2Error('SSO_TOKEN_ISSUE_FAILED', 'Failed to issue customer SSO token.', 500);
}

$response = [
    'ok' => true,
    'token_type' => 'Bearer',
    'expires_in' => 120,
    'sso_token' => $token,
    'common_user_id' => $commonUserId,
    'client_key' => $client['client_key'] ?? null,
    'aud' => $client['audience'] ?? null,
    'jwks_url' => getSiteBaseUrl() . '/api/sso/jwks.php',
];

logIntegrationEvent([
    'direction' => 'inbound',
    'site_key' => $auth['site_key'] ?? null,
    'event_type' => 'customer_sso.issued',
    'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
    'http_status' => 200,
    'success' => 1,
    'common_user_id' => $commonUserId,
    'request_body' => $data,
    'response_body' => [
        'common_user_id' => $commonUserId,
        'client_key' => $client['client_key'] ?? null,
        'aud' => $client['audience'] ?? null,
        'expires_in' => 120,
    ],
]);

apiV2RespondWithIdempotency($idempotencyKey, $response);

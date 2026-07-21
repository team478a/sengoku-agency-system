<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = apiV2Authenticate();
apiV2RequireTables();
apiV2RequireFlag('common_id_enabled');
apiV2RequireFlag('referral_token_api_enabled');

if (!referralTokenTablesReady()) {
    apiV2Error('REFERRAL_TOKEN_SCHEMA_NOT_READY', 'Referral token tables are not migrated.', 503);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $tail = apiV2RequestPathTail('/api/v2/referral-tokens');
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '' && preg_match('#^([^/]+)/validate$#', $tail, $m)) {
        $token = rawurldecode($m[1]);
    } elseif ($token === '' && preg_match('#^validate/([^/]+)$#', $tail, $m)) {
        $token = rawurldecode($m[1]);
    }
    if ($token === '') {
        apiV2Error('VALIDATION_ERROR', 'token is required.', 422);
    }

    $result = validateReferralToken($token);
    $row = $result['token'] ?? null;
    if (empty($result['valid']) || !is_array($row)) {
        apiV2Json([
            'ok' => true,
            'valid' => false,
            'reason' => (string)($result['reason'] ?? 'invalid'),
        ]);
    }

    apiV2Json([
        'ok' => true,
        'valid' => true,
        'token' => [
            'id' => (int)$row['id'],
            'token' => (string)$row['token'],
            'token_type' => (string)$row['token_type'],
            'agent' => [
                'id' => (int)$row['agent_id'],
                'code' => (string)($row['agent_code'] ?? ''),
                'name' => (string)($row['agent_name'] ?? ''),
                'person_name' => (string)($row['person_name'] ?? ''),
                'level' => isset($row['agent_level']) ? (int)$row['agent_level'] : null,
            ],
            'project' => [
                'id' => (int)($row['project_id'] ?? 0),
                'slug' => (string)($row['project_slug'] ?? ''),
                'name' => (string)($row['project_name'] ?? ''),
            ],
            'destination_service_key' => (string)($row['destination_service_key'] ?? ''),
            'destination_url' => (string)($row['destination_url'] ?? ''),
            'expires_at' => $row['expires_at'] ?? null,
        ],
    ]);
}

if ($method === 'POST') {
    apiV2RequireFlag('external_registration_capture_enabled');
    $data = apiV2ReadJson();
    $agentId = apiV2AgentIdFromInput($data);
    if (!$agentId) {
        apiV2Error('VALIDATION_ERROR', 'agent_id or agent_code is required.', 422);
    }
    $projectId = apiV2ProjectIdFromInput($data) ?: 0;
    $token = ensureReferralToken([
        'agent_id' => $agentId,
        'project_id' => $projectId,
        'token_type' => $data['token_type'] ?? 'lp',
        'destination_service_key' => $data['destination_service_key'] ?? ($auth['site_key'] ?? ''),
        'destination_url' => $data['destination_url'] ?? null,
        'expires_at' => $data['expires_at'] ?? null,
        'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
    ]);

    logIntegrationEvent([
        'direction' => 'inbound',
        'site_key' => $auth['site_key'] ?? null,
        'event_type' => 'referral_token.ensure',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'http_status' => 200,
        'success' => 1,
        'agent_id' => $agentId,
        'request_body' => $data,
        'response_body' => ['token' => $token],
    ]);

    apiV2Json([
        'ok' => true,
        'token' => $token,
        'validate_url' => getSiteBaseUrl() . '/api/v2/referral-tokens/' . rawurlencode((string)$token['token']) . '/validate',
    ]);
}

apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);

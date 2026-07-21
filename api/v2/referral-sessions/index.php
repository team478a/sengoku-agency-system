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
$idempotencyKey = $method === 'POST' ? apiV2IdempotencyKey() : '';
if ($idempotencyKey !== '') {
    $stored = apiV2IdempotencyLookup($idempotencyKey);
    if ($stored) {
        http_response_code((int)($stored['response_status'] ?? 200));
        echo (string)($stored['response_body'] ?? '{}');
        exit;
    }
}

if ($method === 'POST') {
    apiV2RequireFlag('external_registration_capture_enabled');
    $data = apiV2ReadJson();
    $token = trim((string)($data['token'] ?? $data['referral_token'] ?? ''));
    if ($token === '') {
        apiV2Error('VALIDATION_ERROR', 'token is required.', 422);
    }
    $validation = validateReferralToken($token);
    if (empty($validation['valid'])) {
        apiV2Error('INVALID_REFERRAL_TOKEN', 'Referral token is invalid: ' . (string)($validation['reason'] ?? 'invalid'), 422);
    }

    $session = recordReferralSession([
        'token_row' => $validation['token'],
        'token' => $token,
        'session_key' => $data['session_key'] ?? null,
        'service_key' => $data['service_key'] ?? ($auth['site_key'] ?? ''),
        'service_user_id' => $data['service_user_id'] ?? null,
        'common_user_id' => $data['common_user_id'] ?? null,
        'landing_url' => $data['landing_url'] ?? null,
        'destination_url' => $data['destination_url'] ?? null,
        'referrer_url' => $data['referrer_url'] ?? null,
        'user_agent' => $data['user_agent'] ?? null,
        'ip_address' => $data['ip_address'] ?? null,
        'event_type' => $data['event_type'] ?? 'click',
        'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
    ]);

    logIntegrationEvent([
        'direction' => 'inbound',
        'site_key' => $auth['site_key'] ?? null,
        'event_type' => 'referral_session.record',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'http_status' => 200,
        'success' => 1,
        'common_user_id' => $session['common_user_id'] ?? null,
        'agent_id' => $session['agent_id'] ?? null,
        'request_body' => $data,
        'response_body' => ['session' => $session],
    ]);

    apiV2RespondWithIdempotency($idempotencyKey, [
        'ok' => true,
        'session' => [
            'session_key' => (string)($session['session_key'] ?? ''),
            'token' => (string)($session['token'] ?? ''),
            'agent_id' => isset($session['agent_id']) ? (int)$session['agent_id'] : null,
            'project_id' => isset($session['project_id']) ? (int)$session['project_id'] : null,
            'service_key' => (string)($session['service_key'] ?? ''),
            'service_user_id' => (string)($session['service_user_id'] ?? ''),
            'common_user_id' => (string)($session['common_user_id'] ?? ''),
            'event_type' => (string)($session['event_type'] ?? ''),
            'created_at' => $session['created_at'] ?? null,
        ],
    ]);
}

if ($method === 'GET') {
    $sessionKey = trim((string)($_GET['session_key'] ?? ''));
    if ($sessionKey === '') {
        apiV2Error('VALIDATION_ERROR', 'session_key is required.', 422);
    }
    $stmt = getDB()->prepare("
        SELECT rs.*, a.agent_code, a.agent_name, p.slug AS project_slug, p.name AS project_name
        FROM referral_sessions rs
        LEFT JOIN agents a ON rs.agent_id=a.id
        LEFT JOIN projects p ON rs.project_id=p.id
        WHERE rs.session_key=?
        LIMIT 1
    ");
    $stmt->execute([$sessionKey]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        apiV2Error('SESSION_NOT_FOUND', 'Referral session was not found.', 404);
    }
    apiV2Json([
        'ok' => true,
        'session' => $session,
    ]);
}

apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);

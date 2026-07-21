<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = apiV2Authenticate();
apiV2RequireTables();
apiV2RequireFlag('common_id_enabled');

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
    $serviceKey = trim((string)($data['service_key'] ?? ''));
    if ($serviceKey === '') {
        $serviceKey = (string)($auth['site_key'] ?? '');
    }
    $serviceUserId = trim((string)($data['service_user_id'] ?? ''));
    if ($serviceUserId === '') {
        apiV2Error('VALIDATION_ERROR', 'service_user_id is required.', 422);
    }

    $agentId = apiV2AgentIdFromInput($data);
    $payload = [
        'common_user_id' => trim((string)($data['common_user_id'] ?? '')),
        'service_key' => $serviceKey,
        'service_user_id' => $serviceUserId,
        'agent_id' => $agentId,
        'email' => $data['email'] ?? null,
        'phone' => $data['phone'] ?? null,
        'wallet_address' => $data['wallet_address'] ?? null,
        'profile' => is_array($data['profile'] ?? null) ? $data['profile'] : [],
    ];

    try {
        $mapping = saveServiceUserMapping($payload);
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'user_mapping.upsert',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 200,
            'success' => 1,
            'common_user_id' => $mapping['common_user_id'] ?? null,
            'agent_id' => $agentId,
            'request_body' => $data,
            'response_body' => ['mapping' => $mapping],
        ]);
        apiV2RespondWithIdempotency($idempotencyKey, [
            'ok' => true,
            'mapping' => [
                'common_user_id' => (string)($mapping['common_user_id'] ?? ''),
                'service_key' => (string)($mapping['service_key'] ?? ''),
                'service_user_id' => (string)($mapping['service_user_id'] ?? ''),
                'agent_id' => isset($mapping['agent_id']) ? (int)$mapping['agent_id'] : null,
                'status' => (string)($mapping['status'] ?? 'active'),
                'updated_at' => $mapping['updated_at'] ?? null,
            ],
        ]);
    } catch (Throwable $e) {
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'user_mapping.upsert',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 500,
            'success' => 0,
            'agent_id' => $agentId,
            'request_body' => $data,
            'error_message' => $e->getMessage(),
        ]);
        apiV2Error('SERVER_ERROR', 'Failed to save user mapping.', 500);
    }
}

if ($method === 'GET') {
    $tail = apiV2RequestPathTail('/api/v2/user-mappings');
    $commonUserId = trim((string)($_GET['common_user_id'] ?? ''));
    if ($commonUserId === '' && preg_match('#^by-common-user/([^/]+)$#', $tail, $m)) {
        $commonUserId = rawurldecode($m[1]);
    }

    $db = getDB();
    if ($commonUserId !== '') {
        $stmt = $db->prepare("
            SELECT m.*, a.agent_code, a.agent_name
            FROM service_user_mappings m
            LEFT JOIN agents a ON m.agent_id=a.id
            WHERE m.common_user_id=?
            ORDER BY m.service_key ASC, m.id ASC
        ");
        $stmt->execute([$commonUserId]);
        apiV2Json([
            'ok' => true,
            'common_user_id' => $commonUserId,
            'mappings' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    $serviceKey = trim((string)($_GET['service_key'] ?? ''));
    if ($serviceKey === '') {
        $serviceKey = (string)($auth['site_key'] ?? '');
    }
    $serviceUserId = trim((string)($_GET['service_user_id'] ?? ''));
    if ($serviceUserId === '') {
        apiV2Error('VALIDATION_ERROR', 'common_user_id or service_user_id is required.', 422);
    }
    $mapping = findCommonUserMapping($serviceKey, $serviceUserId);
    if (!$mapping) {
        apiV2Error('MAPPING_NOT_FOUND', 'User mapping was not found.', 404);
    }
    apiV2Json([
        'ok' => true,
        'mapping' => $mapping,
    ]);
}

apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);

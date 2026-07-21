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

function apiV2LoadRelationsByCommonUser(string $commonUserId): array {
    $stmt = getDB()->prepare("
        SELECT
            r.*,
            a.agent_code,
            a.agent_name,
            a.person_name,
            a.level AS agent_level,
            p.slug AS project_slug,
            p.name AS project_name
        FROM agency_customer_relations r
        LEFT JOIN agents a ON r.agent_id=a.id
        LEFT JOIN projects p ON r.project_id=p.id
        WHERE r.common_user_id=?
        ORDER BY r.updated_at DESC, r.id DESC
    ");
    $stmt->execute([$commonUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($method === 'POST') {
    apiV2RequireFlag('referral_v2_enabled');
    apiV2RequireFlag('external_registration_capture_enabled');
    $data = apiV2ReadJson();

    $serviceKey = trim((string)($data['source_service_key'] ?? $data['service_key'] ?? ''));
    if ($serviceKey === '') {
        $serviceKey = (string)($auth['site_key'] ?? '');
    }
    $serviceUserId = trim((string)($data['source_service_user_id'] ?? $data['service_user_id'] ?? ''));
    $commonUserId = trim((string)($data['common_user_id'] ?? ''));

    if ($commonUserId === '' && $serviceUserId !== '') {
        $existingMapping = findCommonUserMapping($serviceKey, $serviceUserId);
        if ($existingMapping) {
            $commonUserId = (string)$existingMapping['common_user_id'];
        }
    }
    if ($commonUserId === '' && $serviceUserId === '') {
        apiV2Error('VALIDATION_ERROR', 'common_user_id or service_user_id is required.', 422);
    }

    $agentId = apiV2AgentIdFromInput($data);
    if (!$agentId) {
        apiV2Error('VALIDATION_ERROR', 'agent_id or agent_code is required.', 422);
    }

    $projectId = apiV2ProjectIdFromInput($data);

    try {
        if ($serviceUserId !== '') {
            $mapping = saveServiceUserMapping([
                'common_user_id' => $commonUserId,
                'service_key' => $serviceKey,
                'service_user_id' => $serviceUserId,
                'agent_id' => $agentId,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'wallet_address' => $data['wallet_address'] ?? null,
                'profile' => is_array($data['profile'] ?? null) ? $data['profile'] : [],
            ]);
            $commonUserId = (string)$mapping['common_user_id'];
        } else {
            $commonUserId = ensureCommonUser($commonUserId, $data);
        }

        $relation = saveAgencyCustomerRelation([
            'common_user_id' => $commonUserId,
            'agent_id' => $agentId,
            'project_id' => $projectId,
            'relation_type' => trim((string)($data['relation_type'] ?? 'referral')) ?: 'referral',
            'source_service_key' => $serviceKey,
            'source_service_user_id' => $serviceUserId,
            'referral_token_id' => !empty($data['referral_token_id']) ? (int)$data['referral_token_id'] : null,
            'referral_source' => $data['referral_source'] ?? null,
            'locked' => array_key_exists('locked', $data) ? !empty($data['locked']) : true,
        ]);

        $relations = apiV2LoadRelationsByCommonUser($commonUserId);
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'referral_relation.upsert',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 200,
            'success' => 1,
            'common_user_id' => $commonUserId,
            'agent_id' => $agentId,
            'request_body' => $data,
            'response_body' => ['relation' => $relation],
        ]);

        apiV2RespondWithIdempotency($idempotencyKey, [
            'ok' => true,
            'common_user_id' => $commonUserId,
            'relation' => $relation,
            'relations' => $relations,
        ]);
    } catch (Throwable $e) {
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'referral_relation.upsert',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 500,
            'success' => 0,
            'common_user_id' => $commonUserId ?: null,
            'agent_id' => $agentId,
            'request_body' => $data,
            'error_message' => $e->getMessage(),
        ]);
        apiV2Error('SERVER_ERROR', 'Failed to save referral relation.', 500);
    }
}

if ($method === 'GET') {
    $tail = apiV2RequestPathTail('/api/v2/referral-relations');
    $commonUserId = trim((string)($_GET['common_user_id'] ?? ''));
    if ($commonUserId === '' && preg_match('#^by-common-user/([^/]+)$#', $tail, $m)) {
        $commonUserId = rawurldecode($m[1]);
    }

    if ($commonUserId === '') {
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
        $commonUserId = (string)$mapping['common_user_id'];
    }

    $relations = apiV2LoadRelationsByCommonUser($commonUserId);
    apiV2Json([
        'ok' => true,
        'common_user_id' => $commonUserId,
        'relations' => $relations,
    ]);
}

apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);

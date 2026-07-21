<?php
require_once __DIR__ . '/../v2/bootstrap.php';

$auth = apiV2Authenticate();
apiV2RequireTables();

if (!commonHubTablesReady()) {
    apiV2Error('COMMON_HUB_SCHEMA_NOT_READY', 'Common customer HUB tables are not migrated.', 503);
}
apiV2RequireFlag('common_hub_enabled');

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

function commonUsersApiTail(): string {
    return apiV2RequestPathTail('/api/common-users');
}

function commonUsersApiInputSystemKey(array $data, array $auth): string {
    $systemKey = trim((string)($data['system_key'] ?? $data['service_key'] ?? ''));
    if ($systemKey === '') {
        $systemKey = (string)($auth['site_key'] ?? '');
    }
    return $systemKey;
}

function commonUsersApiResolve(array $data, array $auth): array {
    $db = getDB();
    $systemKey = commonUsersApiInputSystemKey($data, $auth);
    $externalUserId = trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? ''));
    $commonUserId = trim((string)($data['common_user_id'] ?? ''));
    $created = false;
    $matchedBy = null;

    if ($commonUserId !== '') {
        $stmt = $db->prepare("SELECT common_user_id FROM common_users WHERE common_user_id=? LIMIT 1");
        $stmt->execute([$commonUserId]);
        if ($stmt->fetchColumn()) {
            $matchedBy = 'common_user_id';
        }
    }

    if ($matchedBy === null && $systemKey !== '' && $externalUserId !== '') {
        $link = findSystemAccountLink($systemKey, $externalUserId);
        if ($link) {
            $commonUserId = (string)$link['common_user_id'];
            $matchedBy = 'system_account_link';
        } else {
            $mapping = findCommonUserMapping($systemKey, $externalUserId);
            if ($mapping) {
                $commonUserId = (string)$mapping['common_user_id'];
                $matchedBy = 'service_user_mapping';
            }
        }
    }

    $identityChecks = [
        ['line', trim((string)($data['line_user_id'] ?? '')), ''],
        ['email', trim((string)($data['email'] ?? '')), ''],
        ['email', trim((string)($data['login_email'] ?? '')), 'login'],
        ['phone', trim((string)($data['phone'] ?? '')), ''],
        ['wallet', trim((string)($data['wallet_address'] ?? '')), ''],
    ];
    foreach ($identityChecks as [$type, $value, $provider]) {
        if ($matchedBy !== null || $value === '') {
            continue;
        }
        $identity = findCommonUserByIdentity($type, $value, $provider);
        if ($identity && !empty($identity['common_user_id'])) {
            $commonUserId = (string)$identity['common_user_id'];
            $matchedBy = 'identity:' . $type;
        }
    }

    $createIfMissing = array_key_exists('create_if_missing', $data) ? !empty($data['create_if_missing']) : true;
    if ($matchedBy === null) {
        if (!$createIfMissing) {
            apiV2Error('COMMON_USER_NOT_FOUND', 'No matching common user was found.', 404);
        }
        $commonUserId = ensureCommonUser($commonUserId ?: null, $data);
        $created = true;
        $matchedBy = 'created';
    } else {
        ensureCommonUser($commonUserId, $data);
    }

    $agentId = apiV2AgentIdFromInput($data);
    updateCommonUserHubFields($commonUserId, [
        'acquisition_channel' => $data['acquisition_channel'] ?? null,
        'acquisition_source' => $data['acquisition_source'] ?? $systemKey,
        'campaign_id' => $data['campaign_id'] ?? null,
        'registration_referrer_agent_id' => $data['registration_referrer_agent_id'] ?? $agentId,
        'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
        'agent_link_status' => $data['agent_link_status'] ?? null,
        'management_status' => $data['management_status'] ?? null,
        'first_touch_at' => $data['first_touch_at'] ?? null,
        'last_touch_at' => $data['last_touch_at'] ?? null,
        'metadata_json' => is_array($data['metadata'] ?? null) ? $data['metadata'] : null,
    ]);

    if ($systemKey !== '' && $externalUserId !== '') {
        saveSystemAccountLink(array_merge($data, [
            'common_user_id' => $commonUserId,
            'system_key' => $systemKey,
            'external_user_id' => $externalUserId,
            'agent_id' => $agentId,
        ]));
    } else {
        foreach ($identityChecks as [$type, $value, $provider]) {
            if ($value === '') {
                continue;
            }
            saveUserIdentity([
                'common_user_id' => $commonUserId,
                'identity_type' => $type,
                'provider' => $provider,
                'identity_value' => $value,
                'verified' => in_array($type, ['line'], true) || !empty($data[$type . '_verified']),
                'source_system_key' => $systemKey ?: null,
                'source_external_user_id' => $externalUserId ?: null,
            ]);
        }
    }

    $profile = loadCommonUserHubProfile($commonUserId);
    return [
        'ok' => true,
        'common_user_id' => $commonUserId,
        'created' => $created,
        'matched_by' => $matchedBy,
        'common_user' => $profile['common_user'] ?? null,
        'system_links' => $profile['system_links'] ?? [],
        'identities' => $profile['identities'] ?? [],
        'agency_relations' => $profile['agency_relations'] ?? [],
    ];
}

function commonUsersApiSaveSystemLink(string $commonUserId, array $data, array $auth): array {
    if ($commonUserId === '') {
        apiV2Error('VALIDATION_ERROR', 'common_user_id is required.', 422);
    }
    $systemKey = commonUsersApiInputSystemKey($data, $auth);
    $externalUserId = trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? ''));
    if ($systemKey === '' || $externalUserId === '') {
        apiV2Error('VALIDATION_ERROR', 'system_key and external_user_id are required.', 422);
    }
    $agentId = apiV2AgentIdFromInput($data);
    $link = saveSystemAccountLink(array_merge($data, [
        'common_user_id' => $commonUserId,
        'system_key' => $systemKey,
        'external_user_id' => $externalUserId,
        'agent_id' => $agentId,
    ]));
    $profile = loadCommonUserHubProfile($commonUserId);
    return [
        'ok' => true,
        'common_user_id' => $commonUserId,
        'system_link' => $link,
        'common_user' => $profile['common_user'] ?? null,
        'system_links' => $profile['system_links'] ?? [],
        'identities' => $profile['identities'] ?? [],
    ];
}

$tail = commonUsersApiTail();

if ($method === 'POST' && ($tail === 'resolve' || (($_GET['action'] ?? '') === 'resolve'))) {
    apiV2RequireFlag('common_hub_write_enabled');
    $data = apiV2ReadJson();
    try {
        $response = commonUsersApiResolve($data, $auth);
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'common_user.resolved',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 200,
            'success' => 1,
            'common_user_id' => $response['common_user_id'] ?? null,
            'agent_id' => apiV2AgentIdFromInput($data),
            'request_body' => $data,
            'response_body' => ['common_user_id' => $response['common_user_id'] ?? null, 'matched_by' => $response['matched_by'] ?? null],
        ]);
        apiV2RespondWithIdempotency($idempotencyKey, $response);
    } catch (Throwable $e) {
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'common_user.resolve_failed',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 500,
            'success' => 0,
            'request_body' => $data ?? [],
            'error_message' => $e->getMessage(),
        ]);
        apiV2Error('SERVER_ERROR', 'Failed to resolve common user.', 500);
    }
}

if ($method === 'POST' && (preg_match('#^([^/]+)/system-links$#', $tail, $m) || (($_GET['action'] ?? '') === 'system-links'))) {
    apiV2RequireFlag('common_hub_write_enabled');
    $data = apiV2ReadJson();
    $commonUserId = isset($m[1]) ? rawurldecode($m[1]) : trim((string)($_GET['common_user_id'] ?? $data['common_user_id'] ?? ''));
    try {
        $response = commonUsersApiSaveSystemLink($commonUserId, $data, $auth);
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'common_user.system_linked',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 200,
            'success' => 1,
            'common_user_id' => $commonUserId,
            'agent_id' => apiV2AgentIdFromInput($data),
            'request_body' => $data,
            'response_body' => ['common_user_id' => $commonUserId],
        ]);
        apiV2RespondWithIdempotency($idempotencyKey, $response);
    } catch (Throwable $e) {
        apiV2Error('SERVER_ERROR', 'Failed to save system link.', 500);
    }
}

if ($method === 'POST' && ($tail === '' || $tail === '/')) {
    apiV2RequireFlag('common_hub_write_enabled');
    $data = apiV2ReadJson();
    $data['create_if_missing'] = true;
    $response = commonUsersApiResolve($data, $auth);
    apiV2RespondWithIdempotency($idempotencyKey, $response, !empty($response['created']) ? 201 : 200);
}

if ($method === 'GET') {
    apiV2RequireFlag('common_hub_read_enabled');
    $commonUserId = trim((string)($_GET['common_user_id'] ?? ''));
    if ($commonUserId === '' && $tail !== '' && !str_contains($tail, '/')) {
        $commonUserId = rawurldecode($tail);
    }
    if ($commonUserId === '') {
        $systemKey = trim((string)($_GET['system_key'] ?? $_GET['service_key'] ?? ''));
        if ($systemKey === '') {
            $systemKey = (string)($auth['site_key'] ?? '');
        }
        $externalUserId = trim((string)($_GET['external_user_id'] ?? $_GET['service_user_id'] ?? ''));
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
    apiV2Json(array_merge(['ok' => true, 'common_user_id' => $commonUserId], $profile));
}

apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);

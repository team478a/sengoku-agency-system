<?php
require_once __DIR__ . '/../v2/bootstrap.php';

$auth = apiV2Authenticate();
apiV2RequireTables();

if (!commonHubTablesReady()) {
    apiV2Error('COMMON_HUB_SCHEMA_NOT_READY', 'Common customer HUB tables are not migrated.', 503);
}
apiV2RequireFlag('common_hub_enabled');
if (!referralTokenTablesReady()) {
    apiV2Error('REFERRAL_SCHEMA_NOT_READY', 'Referral tables are not migrated.', 503);
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

function referralsApiTail(): string {
    return apiV2RequestPathTail('/api/referrals');
}

function referralsApiLoadSession(string $sessionKey): ?array {
    if ($sessionKey === '') {
        return null;
    }
    $stmt = getDB()->prepare("SELECT * FROM referral_sessions WHERE session_key=? LIMIT 1");
    $stmt->execute([$sessionKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function referralsApiResolveCommonUser(array $data, array $auth): string {
    $commonUserId = trim((string)($data['common_user_id'] ?? ''));
    if ($commonUserId !== '') {
        return ensureCommonUser($commonUserId, $data);
    }
    $systemKey = trim((string)($data['system_key'] ?? $data['service_key'] ?? ''));
    if ($systemKey === '') {
        $systemKey = (string)($auth['site_key'] ?? '');
    }
    $externalUserId = trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? ''));
    if ($systemKey !== '' && $externalUserId !== '') {
        $link = findSystemAccountLink($systemKey, $externalUserId) ?: findCommonUserMapping($systemKey, $externalUserId);
        if ($link) {
            return (string)$link['common_user_id'];
        }
        $saved = saveSystemAccountLink(array_merge($data, [
            'system_key' => $systemKey,
            'external_user_id' => $externalUserId,
        ]));
        return (string)$saved['common_user_id'];
    }
    return ensureCommonUser(null, $data);
}

function referralsApiRecordTouchpoint(array $data): array {
    if (empty(tableColumns('agent_touchpoints'))) {
        return [];
    }
    $metadataJson = null;
    if (is_array($data['metadata'] ?? null)) {
        $metadataJson = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $ip = trim((string)($data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
    $ipHash = $ip !== '' ? hash('sha256', 'ip:' . $ip) : null;
    $ua = trim((string)($data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $uaHash = $ua !== '' ? hash('sha256', 'ua:' . $ua) : null;
    $stmt = getDB()->prepare("
        INSERT INTO agent_touchpoints
            (common_user_id, agent_id, project_id, referral_token_id, referral_session_key, touchpoint_type, source_system_key, source_external_user_id, source_url, landing_url, user_agent_hash, ip_hash, locked, occurred_at, confirmed_at, metadata_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt->execute([
        trim((string)($data['common_user_id'] ?? '')) ?: null,
        !empty($data['agent_id']) ? (int)$data['agent_id'] : null,
        !empty($data['project_id']) ? (int)$data['project_id'] : 0,
        !empty($data['referral_token_id']) ? (int)$data['referral_token_id'] : null,
        trim((string)($data['referral_session_key'] ?? '')) ?: null,
        trim((string)($data['touchpoint_type'] ?? 'visit')) ?: 'visit',
        trim((string)($data['source_system_key'] ?? '')) ?: null,
        trim((string)($data['source_external_user_id'] ?? '')) ?: null,
        trim((string)($data['source_url'] ?? $data['referrer_url'] ?? '')) ?: null,
        trim((string)($data['landing_url'] ?? '')) ?: null,
        $uaHash,
        $ipHash,
        !empty($data['locked']) ? 1 : 0,
        !empty($data['confirmed_at']) ? $data['confirmed_at'] : null,
        $metadataJson,
    ]);
    $id = (int)getDB()->lastInsertId();
    $load = getDB()->prepare("SELECT * FROM agent_touchpoints WHERE id=? LIMIT 1");
    $load->execute([$id]);
    return $load->fetch(PDO::FETCH_ASSOC) ?: [];
}

$tail = referralsApiTail();

if ($method === 'POST' && ($tail === 'capture' || (($_GET['action'] ?? '') === 'capture'))) {
    apiV2RequireFlag('referral_v2_enabled');
    apiV2RequireScope($auth, 'referrals:write');
    $data = apiV2ReadJson();
    $tokenValue = trim((string)($data['referral_token'] ?? $data['token'] ?? $data['ref'] ?? $data['referral_code'] ?? $data['invite_token'] ?? ''));
    $aliasType = trim((string)($data['alias_type'] ?? ''));
    $validation = resolveReferralTokenInput($tokenValue, $aliasType);
    if (empty($validation['valid'])) {
        apiV2Error('INVALID_REFERRAL_TOKEN', 'Referral token is invalid: ' . ($validation['reason'] ?? 'unknown'), 422);
    }
    $tokenRow = $validation['token'];
    $session = recordReferralSession([
        'token_row' => $tokenRow,
        'session_key' => $data['referral_session_key'] ?? $data['session_key'] ?? '',
        'service_key' => $data['system_key'] ?? $data['service_key'] ?? ($auth['site_key'] ?? ''),
        'service_user_id' => $data['external_user_id'] ?? $data['service_user_id'] ?? '',
        'common_user_id' => $data['common_user_id'] ?? '',
        'landing_url' => $data['landing_url'] ?? '',
        'destination_url' => $data['destination_url'] ?? '',
        'referrer_url' => $data['referrer_url'] ?? '',
        'event_type' => $data['event_type'] ?? 'capture',
        'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
    ]);
    $touchpoint = referralsApiRecordTouchpoint([
        'common_user_id' => $data['common_user_id'] ?? null,
        'agent_id' => (int)$tokenRow['agent_id'],
        'project_id' => (int)($tokenRow['project_id'] ?? 0),
        'referral_token_id' => (int)$tokenRow['id'],
        'referral_session_key' => $session['session_key'] ?? null,
        'touchpoint_type' => $data['touchpoint_type'] ?? 'capture',
        'source_system_key' => $data['system_key'] ?? $data['service_key'] ?? ($auth['site_key'] ?? ''),
        'source_external_user_id' => $data['external_user_id'] ?? $data['service_user_id'] ?? '',
        'source_url' => $data['referrer_url'] ?? '',
        'landing_url' => $data['landing_url'] ?? '',
        'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
    ]);
    $response = [
        'ok' => true,
        'referral_token' => $tokenRow['token'],
        'canonical_referral_token' => $tokenRow['token'],
        'referral_session_key' => $session['session_key'] ?? null,
        'agent_id' => (int)$tokenRow['agent_id'],
        'agency_id' => $tokenRow['agent_code'] ?? null,
        'agent_code' => $tokenRow['agent_code'] ?? null,
        'project_id' => (int)($tokenRow['project_id'] ?? 0),
        'project_slug' => $tokenRow['project_slug'] ?? null,
        'status' => 'captured',
        'expires_at' => $tokenRow['expires_at'] ?? null,
        'resolved_by' => $validation['resolved_by'] ?? 'canonical_token',
        'touchpoint' => $touchpoint,
    ];
    logIntegrationEvent([
        'direction' => 'inbound',
        'site_key' => $auth['site_key'] ?? null,
        'event_type' => 'referral.captured',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'http_status' => 200,
        'success' => 1,
        'common_user_id' => $data['common_user_id'] ?? null,
        'agent_id' => (int)$tokenRow['agent_id'],
        'request_body' => $data,
        'response_body' => $response,
    ]);
    apiV2RespondWithIdempotency($idempotencyKey, $response);
}

if ($method === 'POST' && ($tail === 'confirm' || (($_GET['action'] ?? '') === 'confirm'))) {
    apiV2RequireFlag('referral_v2_enabled');
    apiV2RequireFlag('common_hub_write_enabled');
    apiV2RequireScope($auth, 'referrals:write');
    $data = apiV2ReadJson();
    $sessionKey = trim((string)($data['referral_session_key'] ?? $data['session_key'] ?? ''));
    $session = referralsApiLoadSession($sessionKey);
    $tokenRow = null;
    if ($session) {
        $tokenRow = findReferralToken((string)$session['token']);
    }
    if (!$tokenRow) {
        $tokenValue = trim((string)($data['referral_token'] ?? $data['token'] ?? $data['ref'] ?? $data['referral_code'] ?? $data['invite_token'] ?? ''));
        $validation = resolveReferralTokenInput($tokenValue, trim((string)($data['alias_type'] ?? '')));
        if (empty($validation['valid'])) {
            apiV2Error('INVALID_REFERRAL_TOKEN', 'Referral token is invalid: ' . ($validation['reason'] ?? 'unknown'), 422);
        }
        $tokenRow = $validation['token'];
    }

    $systemKey = trim((string)($data['system_key'] ?? $data['service_key'] ?? ''));
    if ($systemKey === '') {
        $systemKey = (string)($auth['site_key'] ?? '');
    }
    $externalUserId = trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? ''));

    $db = getDB();
    try {
        $db->beginTransaction();
        $commonUserId = referralsApiResolveCommonUser($data, $auth);

        if ($sessionKey !== '') {
            $db->prepare("UPDATE referral_sessions SET common_user_id=COALESCE(common_user_id, ?), service_user_id=COALESCE(service_user_id, ?), event_type='confirmed' WHERE session_key=?")
                ->execute([$commonUserId, $externalUserId ?: null, $sessionKey]);
        }

        $relation = saveAgencyCustomerRelation([
            'common_user_id' => $commonUserId,
            'agent_id' => (int)$tokenRow['agent_id'],
            'project_id' => (int)($tokenRow['project_id'] ?? 0),
            'relation_type' => $data['relation_type'] ?? 'referral',
            'source_service_key' => $systemKey,
            'source_service_user_id' => $externalUserId,
            'referral_token_id' => (int)$tokenRow['id'],
            'referral_source' => $data['referral_source'] ?? 'referral_confirm',
            'locked' => array_key_exists('locked', $data) ? !empty($data['locked']) : true,
        ]);
        updateCommonUserHubFields($commonUserId, [
            'registration_referrer_agent_id' => (int)$tokenRow['agent_id'],
            'agent_link_status' => 'linked',
            'management_status' => 'agency_referred',
            'last_touch_at' => date('Y-m-d H:i:s'),
        ]);
        $touchpoint = referralsApiRecordTouchpoint([
            'common_user_id' => $commonUserId,
            'agent_id' => (int)$tokenRow['agent_id'],
            'project_id' => (int)($tokenRow['project_id'] ?? 0),
            'referral_token_id' => (int)$tokenRow['id'],
            'referral_session_key' => $sessionKey,
            'touchpoint_type' => $data['touchpoint_type'] ?? 'confirm',
            'source_system_key' => $systemKey,
            'source_external_user_id' => $externalUserId,
            'source_url' => $data['referrer_url'] ?? '',
            'landing_url' => $data['landing_url'] ?? '',
            'locked' => true,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        ]);
        $transaction = saveCustomerTransaction([
            'common_user_id' => $commonUserId,
            'source_system_key' => $systemKey,
            'source_user_id' => $externalUserId,
            'order_id' => $data['order_id'] ?? $data['transaction_id'] ?? '',
            'order_item_id' => $data['order_item_id'] ?? '',
            'product_code' => $data['product_code'] ?? '',
            'registration_referrer_agency_id' => $tokenRow['agent_code'] ?? '',
            'assigned_agency_id' => $data['assigned_agency_id'] ?? $data['agency_id'] ?? ($tokenRow['agent_code'] ?? ''),
            'sales_agent_id' => $data['sales_agent_id'] ?? '',
            'closing_agent_id' => $data['closing_agent_id'] ?? '',
            'referral_session_key' => $sessionKey,
            'payment_status' => $data['payment_status'] ?? '',
            'entitlement_status' => $data['entitlement_status'] ?? '',
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? 'JPY',
            'occurred_at' => $data['occurred_at'] ?? date('Y-m-d H:i:s'),
            'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        logIntegrationEvent([
            'direction' => 'inbound',
            'site_key' => $auth['site_key'] ?? null,
            'event_type' => 'referral.confirm_failed',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'http_status' => 500,
            'success' => 0,
            'agent_id' => (int)$tokenRow['agent_id'],
            'request_body' => $data,
            'error_message' => $e->getMessage(),
        ]);
        apiV2Error('SERVER_ERROR', 'Failed to confirm referral.', 500);
    }

    $profile = loadCommonUserHubProfile($commonUserId);
    $response = [
        'ok' => true,
        'common_user_id' => $commonUserId,
        'agency_id' => $tokenRow['agent_code'] ?? null,
        'canonical_referral_token' => $tokenRow['token'] ?? null,
        'referral_session_key' => $sessionKey ?: null,
        'relation' => $relation,
        'transaction' => $transaction,
        'touchpoint' => $touchpoint,
        'common_user' => $profile['common_user'] ?? null,
        'agency_relations' => $profile['agency_relations'] ?? [],
    ];
    logIntegrationEvent([
        'direction' => 'inbound',
        'site_key' => $auth['site_key'] ?? null,
        'event_type' => 'referral.confirmed',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'http_status' => 200,
        'success' => 1,
        'common_user_id' => $commonUserId,
        'agent_id' => (int)$tokenRow['agent_id'],
        'request_body' => $data,
        'response_body' => $response,
    ]);
    apiV2RespondWithIdempotency($idempotencyKey, $response);
}

apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);

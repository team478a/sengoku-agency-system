<?php
require_once __DIR__ . '/../../v2/bootstrap.php';

$auth = apiV2Authenticate();
apiV2RequireScope($auth, 'events:write');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);
}

if (!commonIdTablesReady()) {
    apiV2Error('COMMON_ID_SCHEMA_NOT_READY', 'Common ID tables are not migrated.', 503);
}

$data = apiV2ReadJson();
$idempotencyKey = eventsApiIdempotencyKey($data);
if ($idempotencyKey !== '') {
    $stored = apiV2IdempotencyLookup($idempotencyKey);
    if ($stored) {
        http_response_code((int)($stored['response_status'] ?? 200));
        echo (string)($stored['response_body'] ?? '{}');
        exit;
    }
}

function eventsApiIdempotencyKey(array $data): string
{
    $headerKey = apiV2IdempotencyKey();
    if ($headerKey !== '') {
        return $headerKey;
    }
    return trim((string)($data['idempotency_key'] ?? ''));
}

function eventsApiString(array $data, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data) && trim((string)$data[$key]) !== '') {
            return trim((string)$data[$key]);
        }
    }
    return $default;
}

function eventsApiNestedString(array $data, array $payload, array $keys, string $default = ''): string
{
    return eventsApiString($data, $keys, eventsApiString($payload, $keys, $default));
}

function eventsApiPayload(array $data): array
{
    return is_array($data['payload'] ?? null) ? $data['payload'] : [];
}

function eventsApiSystemKey(array $data, array $auth): string
{
    return eventsApiString($data, ['service_code', 'system_key', 'service_key'], (string)($auth['site_key'] ?? ''));
}

function eventsApiEventName(array $data): string
{
    $event = eventsApiString($data, ['event', 'event_type']);
    if ($event === '' || !preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $event)) {
        apiV2Error('VALIDATION_ERROR', 'event must be a dot-separated event name such as order.completed.', 400);
    }
    return $event;
}

function eventsApiResolveAgent(?string $agentCode): ?array
{
    $agentCode = trim((string)$agentCode);
    if ($agentCode === '') {
        return null;
    }
    return getAgentByCode($agentCode);
}

function eventsApiResolveCommonUser(array $data, string $systemKey, ?int $agentId): string
{
    $commonUserId = eventsApiString($data, ['common_user_id']);
    if ($commonUserId !== '') {
        return ensureCommonUser($commonUserId, $data);
    }

    $externalUserId = eventsApiString($data, ['external_user_id', 'service_user_id', 'user_id']);
    if ($systemKey !== '' && $externalUserId !== '') {
        $link = findSystemAccountLink($systemKey, $externalUserId) ?: findCommonUserMapping($systemKey, $externalUserId);
        if ($link) {
            return (string)$link['common_user_id'];
        }
        $saved = saveSystemAccountLink(array_merge($data, [
            'system_key' => $systemKey,
            'external_user_id' => $externalUserId,
            'agent_id' => $agentId,
            'display_name' => eventsApiString($data, ['display_name', 'name']),
        ]));
        return (string)$saved['common_user_id'];
    }

    return '';
}

function eventsApiTransactionEvent(string $event): bool
{
    return in_array($event, [
        'sale.completed',
        'sale.refunded',
        'sale.cancelled',
        'order.created',
        'order.completed',
        'purchase.completed',
        'payment.succeeded',
        'payment.failed',
        'payment.refunded',
        'entitlement.granted',
        'entitlement.revoked',
        'application.completed',
    ], true);
}

function eventsApiSaleEvent(string $event): bool
{
    return in_array($event, ['sale.completed', 'sale.refunded', 'sale.cancelled'], true);
}

function eventsApiPaymentStatus(string $event, array $data, array $payload): string
{
    $explicit = eventsApiString($data, ['payment_status'], eventsApiString($payload, ['payment_status']));
    if ($explicit !== '') {
        return $explicit;
    }
    return match ($event) {
        'sale.completed' => 'completed',
        'sale.refunded' => 'refunded',
        'sale.cancelled' => 'cancelled',
        'payment.succeeded' => 'paid',
        'payment.failed' => 'failed',
        'payment.refunded' => 'refunded',
        'order.completed', 'purchase.completed', 'application.completed' => 'completed',
        default => '',
    };
}

function eventsApiEventVersion(array $data, array $payload): string
{
    return eventsApiNestedString($data, $payload, ['event_version'], '1.0') ?: '1.0';
}

function eventsApiCorrelationId(array $data, array $payload): string
{
    return eventsApiNestedString($data, $payload, ['correlation_id', 'request_id']);
}

function eventsApiOccurredAt(array $data, array $payload): string
{
    return eventsApiNestedString($data, $payload, ['occurred_at'], date('Y-m-d H:i:s'));
}

function eventsApiOrderId(array $data, array $payload): string
{
    return eventsApiNestedString($data, $payload, ['order_id', 'transaction_id', 'application_id']);
}

function eventsApiOrderItemId(array $data, array $payload): string
{
    return eventsApiNestedString($data, $payload, ['order_item_id'], 'default') ?: 'default';
}

function eventsApiProductCode(array $data, array $payload): string
{
    return eventsApiNestedString($data, $payload, ['product_code', 'product_id']);
}

function eventsApiExternalUserId(array $data, array $payload): string
{
    return eventsApiNestedString($data, $payload, ['external_user_id', 'service_user_id', 'user_id']);
}

function eventsApiQuantity(array $data, array $payload): int
{
    $value = eventsApiNestedString($data, $payload, ['quantity'], '1');
    return max(1, (int)$value);
}

function eventsApiAmountMinor(array $data, array $payload): ?int
{
    $raw = eventsApiNestedString($data, $payload, ['amount_minor']);
    if ($raw !== '') {
        return (int)$raw;
    }
    $amount = eventsApiNestedString($data, $payload, ['amount']);
    return $amount !== '' ? (int)round(((float)$amount) * 100) : null;
}

function eventsApiEligibilityStatus(string $event, array $data, array $payload): string
{
    $explicit = eventsApiNestedString($data, $payload, ['eligibility_status']);
    if ($explicit !== '') {
        return normalizeRewardEligibilityStatus($explicit);
    }
    if (eventsApiSaleEvent($event) || in_array($event, ['order.completed', 'purchase.completed'], true)) {
        return 'UNKNOWN';
    }
    if ($event === 'application.completed') {
        return 'NOT_ELIGIBLE';
    }
    return 'UNKNOWN';
}

function eventsApiProductRuleEvent(string $event): bool
{
    return eventsApiSaleEvent($event) || in_array($event, ['order.completed', 'purchase.completed', 'application.completed'], true);
}

function eventsApiProductRuleEligibility(string $event, array $data, array $payload, string $systemKey): array
{
    if (!eventsApiProductRuleEvent($event)) {
        return [
            'status' => eventsApiEligibilityStatus($event, $data, $payload),
            'reason' => 'not_product_rule_event',
            'rule' => null,
        ];
    }
    return resolveExternalProductRuleEligibility($systemKey, eventsApiProductCode($data, $payload));
}

function eventsApiEventId(array $data, array $payload, string $event, string $systemKey): string
{
    $eventId = eventsApiNestedString($data, $payload, ['event_id']);
    if ($eventId !== '') {
        return $eventId;
    }

    $orderId = eventsApiOrderId($data, $payload);
    if ($orderId !== '') {
        return $event . ':' . $orderId . ':' . eventsApiOrderItemId($data, $payload);
    }

    return $event . ':' . integrationPayloadHashValue([
        'source_system_key' => $systemKey,
        'payload' => $payload,
    ]);
}

function eventsApiReferralSnapshot(array $data, array $payload, ?array $agent, string $projectKey): array
{
    $provided = $data['referral_snapshot'] ?? $payload['referral_snapshot'] ?? null;
    if (is_array($provided)) {
        return $provided;
    }
    return [
        'agent_code' => (string)($agent['agent_code'] ?? eventsApiNestedString($data, $payload, ['agent_code', 'agency_id', 'assigned_agency_id', 'referrer_agent_code'])),
        'project_key' => $projectKey,
        'referral_session_key' => eventsApiNestedString($data, $payload, ['referral_session_key', 'session_key']),
        'captured_at' => eventsApiOccurredAt($data, $payload),
    ];
}

function eventsApiSaveInbox(string $event, array $data, array $payload, string $systemKey, string $commonUserId, ?array $agent): array
{
    $projectKey = eventsApiNestedString($data, $payload, ['project_key', 'project_slug']);
    $productEligibility = eventsApiProductRuleEligibility($event, $data, $payload, $systemKey);
    $productRule = $productEligibility['rule'] ?? null;
    $processingStatus = $commonUserId === '' ? 'unresolved' : 'received';
    if ($commonUserId !== '' && eventsApiProductRuleEvent($event) && ($productEligibility['status'] ?? 'UNKNOWN') === 'UNKNOWN') {
        $processingStatus = 'blocked_unknown_product';
    }

    return saveIntegrationInboxEvent([
        'source_system_key' => $systemKey,
        'event_id' => eventsApiEventId($data, $payload, $event, $systemKey),
        'event_version' => eventsApiEventVersion($data, $payload),
        'event_type' => $event,
        'occurred_at' => eventsApiOccurredAt($data, $payload),
        'common_user_id' => $commonUserId,
        'external_user_id' => eventsApiExternalUserId($data, $payload),
        'order_id' => eventsApiOrderId($data, $payload),
        'order_item_id' => eventsApiOrderItemId($data, $payload),
        'product_code' => eventsApiProductCode($data, $payload),
        'quantity' => eventsApiQuantity($data, $payload),
        'amount_minor' => eventsApiAmountMinor($data, $payload),
        'currency' => eventsApiNestedString($data, $payload, ['currency'], 'JPY'),
        'product_rule_id' => isset($productRule['id']) ? (int)$productRule['id'] : null,
        'eligibility_status' => $productEligibility['status'] ?? 'UNKNOWN',
        'eligibility_reason' => $productEligibility['reason'] ?? null,
        'referral_snapshot' => eventsApiReferralSnapshot($data, $payload, $agent, $projectKey),
        'correlation_id' => eventsApiCorrelationId($data, $payload),
        'payload_hash' => eventsApiNestedString($data, $payload, ['payload_hash']),
        'payload' => $payload ?: $data,
        'processing_status' => $processingStatus,
    ]);
}

function eventsApiEntitlementStatus(string $event, array $data, array $payload): string
{
    $explicit = eventsApiString($data, ['entitlement_status'], eventsApiString($payload, ['entitlement_status']));
    if ($explicit !== '') {
        return $explicit;
    }
    return match ($event) {
        'entitlement.granted' => 'active',
        'entitlement.revoked' => 'revoked',
        'payment.refunded' => 'revoked',
        'payment.succeeded', 'order.completed', 'purchase.completed', 'application.completed' => 'active',
        default => '',
    };
}

function eventsApiSaveTransaction(string $event, array $data, array $payload, string $systemKey, string $commonUserId, ?array $agent): array
{
    if (!eventsApiTransactionEvent($event) || $commonUserId === '') {
        return [];
    }

    $orderId = eventsApiOrderId($data, $payload);
    if ($orderId === '') {
        $orderId = eventsApiNestedString($data, $payload, ['event_id']);
    }
    if ($orderId === '') {
        return [];
    }

    $agentCode = (string)($agent['agent_code'] ?? eventsApiString($data, ['agent_code', 'agency_id', 'assigned_agency_id']));
    $productEligibility = eventsApiProductRuleEligibility($event, $data, $payload, $systemKey);
    return saveCustomerTransaction([
        'common_user_id' => $commonUserId,
        'source_system_key' => $systemKey,
        'source_user_id' => eventsApiExternalUserId($data, $payload),
        'order_id' => $orderId,
        'order_item_id' => eventsApiOrderItemId($data, $payload),
        'product_code' => eventsApiProductCode($data, $payload),
        'registration_referrer_agency_id' => eventsApiString($data, ['registration_referrer_agency_id', 'referrer_agent_code'], $agentCode),
        'assigned_agency_id' => eventsApiString($data, ['assigned_agency_id', 'agent_code', 'agency_id'], $agentCode),
        'sales_agent_id' => eventsApiString($data, ['sales_agent_id'], eventsApiString($payload, ['sales_agent_id'])),
        'closing_agent_id' => eventsApiString($data, ['closing_agent_id'], eventsApiString($payload, ['closing_agent_id'])),
        'referral_session_key' => eventsApiString($data, ['referral_session_key', 'session_key'], eventsApiString($payload, ['referral_session_key', 'session_key'])),
        'payment_status' => eventsApiPaymentStatus($event, $data, $payload),
        'entitlement_status' => eventsApiEntitlementStatus($event, $data, $payload),
        'amount' => $data['amount'] ?? $payload['amount'] ?? (eventsApiAmountMinor($data, $payload) !== null ? eventsApiAmountMinor($data, $payload) / 100 : null),
        'currency' => eventsApiNestedString($data, $payload, ['currency'], 'JPY'),
        'occurred_at' => eventsApiOccurredAt($data, $payload),
        'metadata' => [
            'event' => $event,
            'event_version' => eventsApiEventVersion($data, $payload),
            'project_key' => eventsApiNestedString($data, $payload, ['project_key', 'project_slug']),
            'amount_minor' => eventsApiAmountMinor($data, $payload),
            'eligibility_status' => $productEligibility['status'] ?? eventsApiEligibilityStatus($event, $data, $payload),
            'eligibility_reason' => $productEligibility['reason'] ?? null,
            'product_rule_id' => isset($productEligibility['rule']['id']) ? (int)$productEligibility['rule']['id'] : null,
            'correlation_id' => eventsApiCorrelationId($data, $payload),
            'referral_snapshot' => eventsApiReferralSnapshot($data, $payload, $agent, eventsApiNestedString($data, $payload, ['project_key', 'project_slug'])),
            'idempotency_key' => eventsApiIdempotencyKey($data),
            'payload' => maskIntegrationPayloadForLog($payload),
        ],
    ]);
}

function eventsApiSaveEntitlement(string $event, array $data, array $payload, string $systemKey, string $commonUserId): array
{
    if (eventsApiSaleEvent($event)) {
        return [];
    }
    if ($commonUserId === '') {
        return [];
    }
    $status = eventsApiEntitlementStatus($event, $data, $payload);
    $productCode = eventsApiString($data, ['product_code', 'product_id'], eventsApiString($payload, ['product_code', 'product_id']));
    $orderId = eventsApiString($data, ['order_id', 'transaction_id', 'application_id'], eventsApiString($payload, ['order_id', 'transaction_id', 'application_id']));
    $shouldSave = $status !== '' || $productCode !== '' || str_starts_with($event, 'entitlement.');
    if (!$shouldSave || ($productCode === '' && $orderId === '')) {
        return [];
    }
    return saveCustomerEntitlement([
        'common_user_id' => $commonUserId,
        'system_key' => $systemKey,
        'external_user_id' => eventsApiString($data, ['external_user_id', 'service_user_id', 'user_id'], eventsApiString($payload, ['external_user_id', 'service_user_id', 'user_id'])),
        'project_key' => eventsApiString($data, ['project_key', 'project_slug'], eventsApiString($payload, ['project_key', 'project_slug'])),
        'project_id' => $data['project_id'] ?? $payload['project_id'] ?? null,
        'product_code' => $productCode,
        'order_id' => $orderId,
        'order_item_id' => eventsApiString($data, ['order_item_id'], eventsApiString($payload, ['order_item_id'], 'default')),
        'status' => $status ?: 'active',
        'starts_at' => eventsApiString($data, ['starts_at', 'entitlement_starts_at'], eventsApiString($payload, ['starts_at', 'entitlement_starts_at'])),
        'expires_at' => eventsApiString($data, ['expires_at', 'entitlement_expires_at'], eventsApiString($payload, ['expires_at', 'entitlement_expires_at'])),
        'source_event' => $event,
        'metadata' => [
            'event' => $event,
            'idempotency_key' => eventsApiIdempotencyKey($data),
            'payload' => $payload,
        ],
    ]);
}

$event = eventsApiEventName($data);
$payload = eventsApiPayload($data);
$systemKey = eventsApiSystemKey($data, $auth);
if ($systemKey === '') {
    apiV2Error('VALIDATION_ERROR', 'system_key is required.', 400);
}

$agentCode = eventsApiString($data, ['agent_code', 'agency_id', 'assigned_agency_id', 'referrer_agent_code']);
$agent = eventsApiResolveAgent($agentCode);
$commonUserId = '';
$transaction = [];
$entitlement = [];
$inboxEvent = [];
$warnings = [];

try {
    $commonUserId = eventsApiResolveCommonUser($data, $systemKey, $agent ? (int)$agent['id'] : null);
    if ($commonUserId !== '' && $agent) {
        updateCommonUserHubFields($commonUserId, [
            'assigned_agent_id' => (int)$agent['id'],
            'agent_link_status' => 'linked',
            'last_touch_at' => date('Y-m-d H:i:s'),
        ]);
    }
    $inboxEvent = eventsApiSaveInbox($event, $data, $payload, $systemKey, $commonUserId, $agent);
    if (empty($inboxEvent)) {
        $warnings[] = 'Integration inbox was not stored because migration 3.6.163 is not applied.';
    }
    $transaction = eventsApiSaveTransaction($event, $data, $payload, $systemKey, $commonUserId, $agent);
    $entitlement = eventsApiSaveEntitlement($event, $data, $payload, $systemKey, $commonUserId);
    if (eventsApiTransactionEvent($event) && empty($transaction)) {
        $warnings[] = 'Transaction was not stored because common_user_id or order_id was missing, or customer_transactions is not migrated.';
    }
    if (eventsApiEntitlementStatus($event, $data, $payload) !== '' && empty($entitlement)) {
        $warnings[] = 'Entitlement was not stored because common_user_id, product_code/order_id, or customer_entitlements is missing.';
    }
} catch (Throwable $e) {
    $status = (int)$e->getCode() === 409 ? 409 : 500;
    logIntegrationEvent([
        'direction' => 'inbound',
        'site_key' => $auth['site_key'] ?? null,
        'event_type' => $event,
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'http_status' => $status,
        'success' => 0,
        'common_user_id' => $commonUserId ?: null,
        'agent_id' => $agent ? (int)$agent['id'] : null,
        'request_body' => $data,
        'error_message' => $e->getMessage(),
    ]);
    apiV2Error($status === 409 ? 'EVENT_PAYLOAD_CONFLICT' : 'SERVER_ERROR', $status === 409 ? $e->getMessage() : 'Failed to process integration event.', $status);
}

$response = [
    'ok' => true,
    'event' => $event,
    'system_key' => $systemKey,
    'project_key' => eventsApiNestedString($data, $payload, ['project_key', 'project_slug']) ?: null,
    'product_code' => eventsApiProductCode($data, $payload) ?: null,
    'common_user_id' => $commonUserId ?: null,
    'agent_code' => $agent['agent_code'] ?? ($agentCode ?: null),
    'inbox_event' => $inboxEvent ? [
        'id' => (int)($inboxEvent['id'] ?? 0),
        'event_id' => $inboxEvent['event_id'] ?? null,
        'processing_status' => $inboxEvent['processing_status'] ?? null,
        'idempotent' => !empty($inboxEvent['_idempotent']),
    ] : null,
    'transaction' => $transaction ?: null,
    'entitlement' => $entitlement ?: null,
    'warnings' => $warnings,
];

logIntegrationEvent([
    'direction' => 'inbound',
    'site_key' => $auth['site_key'] ?? null,
    'event_type' => $event,
    'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
    'http_status' => 202,
    'success' => 1,
    'common_user_id' => $commonUserId ?: null,
    'agent_id' => $agent ? (int)$agent['id'] : null,
    'request_body' => $data,
    'response_body' => $response,
]);

apiV2RespondWithIdempotency($idempotencyKey, $response, 202);

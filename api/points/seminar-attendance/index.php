<?php
require_once __DIR__ . '/../../../v2/bootstrap.php';

$auth = apiV2Authenticate();
apiV2RequireTables();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    apiV2Error('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);
}

apiV2RequireScope($auth, 'points:write');
apiV2RequireFlag('orly_point_campaign_enabled');
apiV2RequireFlag('orly_point_award_enabled');
apiV2RequireFlag('orly_seminar_point_award_enabled');

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
$seminarId = trim((string)($data['seminar_id'] ?? ''));
$commonUserId = trim((string)($data['common_user_id'] ?? $data['target_common_user_id'] ?? ''));
if ($seminarId === '') {
    apiV2Error('INVALID_REQUEST', 'seminar_id is required.', 400);
}
if ($commonUserId === '') {
    apiV2Error('INVALID_REQUEST', 'common_user_id is required.', 400);
}

try {
    $result = (new \SenNoKuni\Point\OrlySeminarAttendancePointAwardService(getDB()))->recordAttendance([
        'seminar_id' => $seminarId,
        'common_user_id' => $commonUserId,
        'direct_referrer_agent_id' => (int)($data['direct_referrer_agent_id'] ?? $data['agent_id'] ?? 0),
        'project_key' => $data['project_key'] ?? $data['project_slug'] ?? 'orly',
        'source_system_key' => $data['source_system_key'] ?? $data['service_code'] ?? ($auth['site_key'] ?? 'AGENCY_SYSTEM'),
        'occurred_at' => $data['occurred_at'] ?? '',
        'correlation_id' => trim((string)($_SERVER['HTTP_X_CORRELATION_ID'] ?? ($data['correlation_id'] ?? ''))),
    ]);
} catch (Throwable $e) {
    logIntegrationEvent([
        'direction' => 'inbound',
        'site_key' => $auth['site_key'] ?? null,
        'event_type' => 'seminar.attendance_failed',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'http_status' => 500,
        'success' => 0,
        'request_body' => [
            'seminar_id' => $seminarId,
            'common_user_id' => $commonUserId,
        ],
        'error_message' => $e->getMessage(),
    ]);
    apiV2Error('SERVER_ERROR', 'Failed to record seminar attendance.', 500);
}

if (empty($result['ok']) && ($result['status'] ?? '') === 'conflict') {
    apiV2Error('POINT_AWARD_CONFLICT', 'Seminar attendance point award conflicts with an existing record.', 409, [
        'attendance_key' => $result['attendance_key'] ?? null,
        'saved' => $result['saved'] ?? [],
    ]);
}

$response = [
    'ok' => true,
    'status' => $result['status'] ?? 'recorded',
    'attendance_key' => $result['attendance_key'] ?? null,
    'saved' => $result['saved'] ?? [],
];

logIntegrationEvent([
    'direction' => 'inbound',
    'site_key' => $auth['site_key'] ?? null,
    'event_type' => 'seminar.attended',
    'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
    'http_status' => 200,
    'success' => 1,
    'common_user_id' => $commonUserId,
    'request_body' => [
        'seminar_id' => $seminarId,
        'common_user_id' => $commonUserId,
        'project_key' => $data['project_key'] ?? $data['project_slug'] ?? 'orly',
    ],
    'response_body' => $response,
]);

apiV2RespondWithIdempotency($idempotencyKey, $response);

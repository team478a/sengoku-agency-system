<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: x-api-key, X-API-Key, Authorization, Content-Type, Idempotency-Key');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function apiV2RawBody(): string {
    static $raw = null;
    if ($raw === null) {
        $raw = file_get_contents('php://input') ?: '';
    }
    return $raw;
}

function apiV2Json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function apiV2Error(string $code, string $message, int $status = 400, array $extra = []): void {
    apiV2Json(array_merge([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], $extra), $status);
}

function apiV2ReadJson(): array {
    $raw = apiV2RawBody();
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        apiV2Error('INVALID_JSON', 'Request body must be a JSON object.', 400);
    }
    return $data;
}

function apiV2RequestKey(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $apiKey = $headers['x-api-key'] ?? $headers['X-API-Key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if (trim((string)$apiKey) !== '') {
        return trim((string)$apiKey);
    }
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', (string)$auth, $m)) {
        return trim($m[1]);
    }
    return '';
}

function apiV2Authenticate(): array {
    $key = apiV2RequestKey();
    if ($key === '') {
        apiV2Error('API_KEY_REQUIRED', 'x-api-key or Authorization: Bearer header is required.', 401);
    }

    try {
        if (tableHasColumn('external_partner_sites', 'inbound_api_key')) {
            $stmt = getDB()->prepare("
                SELECT *
                FROM external_partner_sites
                WHERE status='active' AND COALESCE(inbound_api_key, '') <> ''
                ORDER BY id ASC
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $site) {
                if (hash_equals((string)$site['inbound_api_key'], $key)) {
                    return [
                        'auth_type' => 'partner_inbound_key',
                        'site_key' => (string)($site['site_key'] ?? ''),
                        'site_name' => (string)($site['name'] ?? ''),
                        'partner' => $site,
                    ];
                }
            }
        }

        $legacy = trim(getSystemSettingValue('external_api_token', ''));
        if ($legacy !== '' && hash_equals($legacy, $key)) {
            return [
                'auth_type' => 'legacy_external_api_token',
                'site_key' => 'legacy',
                'site_name' => 'legacy',
                'partner' => null,
            ];
        }
    } catch (Throwable $e) {
        apiV2Error('AUTH_CHECK_FAILED', 'Failed to verify API key.', 500);
    }

    apiV2Error('INVALID_API_KEY', 'API key is invalid.', 401);
}

function apiV2RequireTables(): void {
    if (!commonIdTablesReady()) {
        apiV2Error('COMMON_ID_SCHEMA_NOT_READY', 'Common ID tables are not migrated.', 503);
    }
}

function apiV2RequireFlag(string $flag): void {
    $flags = getCommonIdFeatureFlags();
    if (empty($flags[$flag])) {
        apiV2Error('FEATURE_DISABLED', $flag . ' is disabled.', 403);
    }
}

function apiV2RequestPathTail(string $base): string {
    $pathInfo = trim((string)($_SERVER['PATH_INFO'] ?? ''), '/');
    if ($pathInfo !== '') {
        return $pathInfo;
    }
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $pos = strpos($uriPath, $base);
    if ($pos === false) {
        return '';
    }
    return trim(substr($uriPath, $pos + strlen($base)), '/');
}

function apiV2IdempotencyKey(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    return trim((string)($headers['Idempotency-Key'] ?? $headers['idempotency-key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')));
}

function apiV2IdempotencyLookup(string $key): ?array {
    if ($key === '' || empty(tableColumns('integration_idempotency_keys'))) {
        return null;
    }
    $hash = hash('sha256', $key);
    $stmt = getDB()->prepare("
        SELECT *
        FROM integration_idempotency_keys
        WHERE key_hash=? AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function apiV2IdempotencyStore(string $key, int $status, array $response): void {
    if ($key === '' || empty(tableColumns('integration_idempotency_keys'))) {
        return;
    }
    try {
        $hash = hash('sha256', $key);
        $requestHash = hash('sha256', apiV2RawBody());
        $body = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = getDB()->prepare("
            INSERT INTO integration_idempotency_keys
                (key_hash, endpoint, method, request_hash, response_status, response_body, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ON DUPLICATE KEY UPDATE
                response_status=VALUES(response_status),
                response_body=VALUES(response_body),
                updated_at=NOW()
        ");
        $stmt->execute([
            $hash,
            substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
            substr((string)($_SERVER['REQUEST_METHOD'] ?? ''), 0, 12),
            $requestHash,
            $status,
            $body,
        ]);
    } catch (Throwable $e) {
        error_log('Idempotency store failed: ' . $e->getMessage());
    }
}

function apiV2RespondWithIdempotency(string $idempotencyKey, array $response, int $status = 200): void {
    apiV2IdempotencyStore($idempotencyKey, $status, $response);
    apiV2Json($response, $status);
}

function apiV2AgentIdFromInput(array $data): ?int {
    if (!empty($data['agent_id'])) {
        return (int)$data['agent_id'];
    }
    $agentCode = trim((string)($data['agent_code'] ?? ''));
    if ($agentCode === '') {
        return null;
    }
    $agent = getAgentByCode($agentCode);
    return $agent ? (int)$agent['id'] : null;
}

function apiV2ProjectIdFromInput(array $data): ?int {
    if (!empty($data['project_id'])) {
        return (int)$data['project_id'];
    }
    $slug = trim((string)($data['project_slug'] ?? ''));
    if ($slug === '') {
        return null;
    }
    $stmt = getDB()->prepare("SELECT id FROM projects WHERE slug=? LIMIT 1");
    $stmt->execute([$slug]);
    $id = (int)$stmt->fetchColumn();
    return $id > 0 ? $id : null;
}

<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/mailer.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: x-api-key, X-API-Key, Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function agencyApiJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function agencyApiError(string $code, string $message, int $status): void
{
    agencyApiJson(['error' => ['code' => $code, 'message' => $message]], $status);
}

function agencyApiSetting(string $key): string
{
    try {
        $stmt = getDB()->prepare("SELECT value FROM system_settings WHERE key_name=? LIMIT 1");
        $stmt->execute([$key]);
        return trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function agencyApiKey(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $apiKey = $headers['x-api-key'] ?? $headers['X-API-Key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (trim((string)$apiKey) !== '') {
        return trim((string)$apiKey);
    }
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', (string)$auth, $m)) {
        return trim($m[1]);
    }
    return '';
}

function agencyApiHasConfiguredKey(): bool
{
    if (agencyApiSetting('external_api_token') !== '') {
        return true;
    }
    try {
        if (!function_exists('tableHasColumn') || !tableHasColumn('external_partner_sites', 'inbound_api_key')) {
            return false;
        }
        $count = (int)getDB()->query("SELECT COUNT(*) FROM external_partner_sites WHERE status='active' AND COALESCE(inbound_api_key, '') <> ''")->fetchColumn();
        return $count > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function agencyApiKeyIsValid(string $requestKey): bool
{
    if ($requestKey === '') {
        return false;
    }
    $legacyKey = agencyApiSetting('external_api_token');
    if ($legacyKey !== '' && hash_equals($legacyKey, $requestKey)) {
        return true;
    }
    try {
        if (!function_exists('tableHasColumn') || !tableHasColumn('external_partner_sites', 'inbound_api_key')) {
            return false;
        }
        $rows = getDB()->query("SELECT inbound_api_key FROM external_partner_sites WHERE status='active' AND COALESCE(inbound_api_key, '') <> ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $key) {
            if (hash_equals((string)$key, $requestKey)) {
                return true;
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    return false;
}

function agencyApiColumnExists(PDO $db, string $table, string $column): bool
{
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function agencyApiEnsureSchema(PDO $db): void
{
    if (!agencyApiColumnExists($db, 'agents', 'external_id')) {
        $db->exec("ALTER TABLE agents ADD COLUMN external_id VARCHAR(191) DEFAULT NULL AFTER id");
    }
    if (!agencyApiColumnExists($db, 'agents', 'default_commission_rate')) {
        $db->exec("ALTER TABLE agents ADD COLUMN default_commission_rate DECIMAL(5,2) DEFAULT NULL AFTER parent_id");
    }
    if (!agencyApiColumnExists($db, 'agents', 'login_email')) {
        $db->exec("ALTER TABLE agents ADD COLUMN login_email VARCHAR(255) DEFAULT NULL AFTER email");
    }
}

function agencyApiReadBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        agencyApiError('VALIDATION_ERROR', 'Request body must be a JSON object.', 400);
    }
    return $data;
}

function agencyApiExternalIdFromPath(): string
{
    $pathInfo = trim((string)($_SERVER['PATH_INFO'] ?? ''), '/');
    if ($pathInfo !== '') {
        return rawurldecode(explode('/', $pathInfo)[0]);
    }
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $prefix = '/api/integrations/agencies/';
    $pos = strpos($uriPath, $prefix);
    if ($pos !== false) {
        $tail = trim(substr($uriPath, $pos + strlen($prefix)), '/');
        if ($tail !== '') {
            return rawurldecode(explode('/', $tail)[0]);
        }
    }
    return trim((string)($_GET['external_id'] ?? ''));
}

function agencyApiGenerateCode(PDO $db): string
{
    for ($i = 0; $i < 30; $i++) {
        $code = 'AG' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT id FROM agents WHERE agent_code=? LIMIT 1");
        $stmt->execute([$code]);
        if (!$stmt->fetchColumn()) {
            return $code;
        }
    }
    return 'AG' . bin2hex(random_bytes(4));
}

function agencyApiDefaultTemplateId(PDO $db): ?int
{
    try {
        $id = $db->query("SELECT id FROM lp_templates WHERE status='active' ORDER BY sort_order ASC, id ASC LIMIT 1")->fetchColumn();
        return $id ? (int)$id : null;
    } catch (Throwable $e) {
        return null;
    }
}

function agencyApiMapAgent(array $agent, bool $withChildren = false): array
{
    $mapped = [
        'id' => (string)$agent['id'],
        'external_id' => (string)($agent['external_id'] ?? ''),
        'name' => (string)($agent['agent_name'] ?? ''),
        'code' => (string)($agent['agent_code'] ?? ''),
        'status' => (string)($agent['status'] ?? 'active'),
        'default_commission_rate' => isset($agent['default_commission_rate']) && $agent['default_commission_rate'] !== null
            ? (float)$agent['default_commission_rate']
            : null,
        'contact_name' => (string)($agent['person_name'] ?? ''),
        'contact_email' => (string)($agent['email'] ?? ''),
        'login_email' => (string)($agent['login_email'] ?? ''),
        'parent_external_id' => $agent['parent_external_id'] ?? null,
    ];
    if ($withChildren) {
        $mapped['child_external_ids'] = $agent['child_external_ids'] ?? [];
    }
    return $mapped;
}

function agencyApiFindByExternalId(PDO $db, string $externalId): ?array
{
    $stmt = $db->prepare("
        SELECT a.*, p.external_id AS parent_external_id
        FROM agents a
        LEFT JOIN agents p ON a.parent_id = p.id
        WHERE a.external_id=?
        LIMIT 1
    ");
    $stmt->execute([$externalId]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    return $agent ?: null;
}

if (!agencyApiHasConfiguredKey()) {
    agencyApiError('API_KEY_NOT_CONFIGURED', 'API key is not configured.', 503);
}
$requestKey = agencyApiKey();
if ($requestKey === '') {
    agencyApiError('API_KEY_REQUIRED', 'x-api-key or Authorization: Bearer header is required.', 401);
}
if (!agencyApiKeyIsValid($requestKey)) {
    agencyApiError('INVALID_API_KEY', 'API key is invalid.', 401);
}

$db = getDB();
agencyApiEnsureSchema($db);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $data = agencyApiReadBody();
    $externalId = trim((string)($data['external_id'] ?? ''));
    $name = trim((string)($data['name'] ?? ''));
    $parentSpecified = array_key_exists('parent_external_id', $data);
    $parentExternalId = $parentSpecified ? trim((string)($data['parent_external_id'] ?? '')) : null;
    $rate = array_key_exists('default_commission_rate', $data) && $data['default_commission_rate'] !== null && $data['default_commission_rate'] !== ''
        ? (float)$data['default_commission_rate']
        : null;
    $contactName = trim((string)($data['contact_name'] ?? ''));
    $contactEmail = trim((string)($data['contact_email'] ?? ''));
    $loginEmail = trim((string)($data['login_email'] ?? ''));
    $status = trim((string)($data['status'] ?? ''));

    if ($externalId === '') agencyApiError('VALIDATION_ERROR', 'external_id is required.', 400);
    if ($name === '') agencyApiError('VALIDATION_ERROR', 'name is required.', 400);
    if ($parentSpecified && $parentExternalId === $externalId) agencyApiError('VALIDATION_ERROR', 'parent_external_id cannot be the same as external_id.', 400);
    if ($rate !== null && ($rate < 0 || $rate > 100)) agencyApiError('VALIDATION_ERROR', 'default_commission_rate must be between 0 and 100.', 400);
    if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) agencyApiError('VALIDATION_ERROR', 'status must be active or inactive.', 400);
    if ($loginEmail !== '' && !filter_var($loginEmail, FILTER_VALIDATE_EMAIL)) agencyApiError('VALIDATION_ERROR', 'login_email is invalid.', 400);
    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) agencyApiError('VALIDATION_ERROR', 'contact_email is invalid.', 400);

    $existing = agencyApiFindByExternalId($db, $externalId);
    $parentId = $existing ? (!empty($existing['parent_id']) ? (int)$existing['parent_id'] : null) : null;
    $level = $existing ? (int)($existing['level'] ?? 3) : 3;
    if ($parentSpecified && $parentExternalId === '') {
        $parentId = null;
        $level = 3;
    } elseif ($parentSpecified && $parentExternalId !== null && $parentExternalId !== '') {
        $parent = agencyApiFindByExternalId($db, $parentExternalId);
        if (!$parent) {
            agencyApiError('PARENT_AGENCY_NOT_FOUND', 'Parent agency was not found.', 404);
        }
        $parentId = (int)$parent['id'];
        $level = max(1, (int)($parent['level'] ?? 3) - 1);
    }
    if ($existing && $parentId) {
        foreach (getAllDescendants((int)$existing['id']) as $descendant) {
            if ((int)$descendant['id'] === $parentId) {
                agencyApiError('VALIDATION_ERROR', 'parent_external_id cannot point to a descendant agency.', 400);
            }
        }
    }

    $contactEmailForAgent = $contactEmail !== '' ? $contactEmail : $loginEmail;
    if ($loginEmail !== '') {
        $emailStmt = $db->prepare("
            SELECT id FROM agents
            WHERE (login_email=? OR ((login_email IS NULL OR login_email='') AND email=?))
              AND (external_id IS NULL OR external_id<>?)
            LIMIT 1
        ");
        $emailStmt->execute([$loginEmail, $loginEmail, $externalId]);
        if ($emailStmt->fetchColumn()) {
            agencyApiError('LOGIN_EMAIL_ALREADY_EXISTS', 'login_email is already used by another account.', 409);
        }
    }

    $db->beginTransaction();
    try {
        $loginProvisioned = false;
        $setupUrl = '';
        if ($existing) {
            $sets = [
                'agent_name=?',
                'person_name=?',
                'parent_id=?',
                'level=?',
                'default_commission_rate=?',
                'status=?',
            ];
            $params = [
                $name,
                $contactName !== '' ? $contactName : $name,
                $parentId,
                $level,
                $rate,
                $status !== '' ? $status : (string)($existing['status'] ?? 'active'),
            ];
            if ($contactEmailForAgent !== '') {
                $sets[] = 'email=?';
                $params[] = $contactEmailForAgent;
            }
            if ($loginEmail !== '') {
                $sets[] = 'login_email=?';
                $params[] = $loginEmail;
            }
            if ($loginEmail !== '' && empty($existing['password']) && empty($existing['setup_token'])) {
                $token = bin2hex(random_bytes(32));
                $exp = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $sets[] = 'setup_token=?';
                $sets[] = 'setup_token_exp=?';
                $params[] = $token;
                $params[] = $exp;
                $setupUrl = getSiteBaseUrl() . '/agent/setup.php?token=' . $token;
                $loginProvisioned = true;
            }
            $params[] = $externalId;
            $db->prepare("UPDATE agents SET " . implode(',', $sets) . " WHERE external_id=?")->execute($params);
        } else {
            $token = $loginEmail !== '' ? bin2hex(random_bytes(32)) : null;
            $exp = $token ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null;
            $code = agencyApiGenerateCode($db);
            $templateId = agencyApiDefaultTemplateId($db);
            $db->prepare("
                INSERT INTO agents
                (external_id, agent_code, level, parent_id, default_commission_rate, agent_name, person_name, email, login_email, default_template_id,
                 show_form, show_line_btn, notify_email, setup_token, setup_token_exp, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, ?, ?, ?)
            ")->execute([
                $externalId,
                $code,
                $level,
                $parentId,
                $rate,
                $name,
                $contactName !== '' ? $contactName : $name,
                $contactEmailForAgent,
                $loginEmail !== '' ? $loginEmail : null,
                $templateId,
                $token,
                $exp,
                $status !== '' ? $status : 'active',
            ]);
            if ($token) {
                $setupUrl = getSiteBaseUrl() . '/agent/setup.php?token=' . $token;
                $loginProvisioned = true;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        agencyApiError('SERVER_ERROR', 'Failed to save agency.', 500);
    }

    $saved = agencyApiFindByExternalId($db, $externalId);
    if (!$saved) {
        agencyApiError('SERVER_ERROR', 'Saved agency could not be loaded.', 500);
    }
    if ($loginProvisioned && $setupUrl !== '') {
        try {
            $mailTarget = $saved;
            if ($loginEmail !== '') {
                $mailTarget['email'] = $loginEmail;
            }
            (new Mailer())->sendApprovalNotice($mailTarget, $setupUrl);
        } catch (Throwable $e) {
            error_log('Agency integration approval mail failed: ' . $e->getMessage());
        }
    }
    $payload = agencyApiMapAgent($saved);
    $payload['login_provisioned'] = $loginProvisioned;
    agencyApiJson(['agency' => $payload], $existing ? 200 : 201);
}

if ($method === 'GET') {
    $externalId = agencyApiExternalIdFromPath();
    if ($externalId !== '') {
        $agent = agencyApiFindByExternalId($db, $externalId);
        if (!$agent) {
            agencyApiError('AGENCY_NOT_FOUND', 'Agency was not found.', 404);
        }
        $childStmt = $db->prepare("SELECT external_id FROM agents WHERE parent_id=? AND external_id IS NOT NULL ORDER BY id ASC");
        $childStmt->execute([(int)$agent['id']]);
        $agent['child_external_ids'] = array_values(array_filter($childStmt->fetchAll(PDO::FETCH_COLUMN)));
        agencyApiJson(['agency' => agencyApiMapAgent($agent, true)]);
    }

    $rows = $db->query("
        SELECT a.*, p.external_id AS parent_external_id
        FROM agents a
        LEFT JOIN agents p ON a.parent_id = p.id
        WHERE a.external_id IS NOT NULL
        ORDER BY a.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $agencies = array_map(static fn($agent) => agencyApiMapAgent($agent), $rows);
    agencyApiJson(['agencies' => $agencies]);
}

agencyApiError('METHOD_NOT_ALLOWED', 'Method is not allowed.', 405);

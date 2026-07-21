<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, X-API-Token, X-API-Key, x-api-key, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function apiJson(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function apiSetting(string $key): string {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT value FROM system_settings WHERE key_name=? LIMIT 1");
        $stmt->execute([$key]);
        return trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function apiRequestToken(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', (string)$auth, $m)) {
        return trim($m[1]);
    }
    $apiKey = $headers['x-api-key'] ?? $headers['X-API-Key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (trim((string)$apiKey) !== '') {
        return trim((string)$apiKey);
    }
    return trim((string)($_SERVER['HTTP_X_API_TOKEN'] ?? ($_GET['token'] ?? '')));
}

function apiHasConfiguredToken(): bool {
    if (apiSetting('external_api_token') !== '') {
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

function apiTokenIsValid(string $requestToken): bool {
    if ($requestToken === '') {
        return false;
    }
    $legacyToken = apiSetting('external_api_token');
    if ($legacyToken !== '' && hash_equals($legacyToken, $requestToken)) {
        return true;
    }
    try {
        if (!function_exists('tableHasColumn') || !tableHasColumn('external_partner_sites', 'inbound_api_key')) {
            return false;
        }
        $rows = getDB()->query("SELECT inbound_api_key FROM external_partner_sites WHERE status='active' AND COALESCE(inbound_api_key, '') <> ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $token) {
            if (hash_equals((string)$token, $requestToken)) {
                return true;
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    return false;
}

function apiAgentPayload(array $agent, array $projects, array $labels, bool $includeContact, bool $includeSso): array {
    $level = (int)($agent['level'] ?? 1);
    $payload = [
        'id' => (int)$agent['id'],
        'agency_id' => (string)($agent['agent_code'] ?? ''),
        'internal_agent_id' => (int)$agent['id'],
        'code' => (string)($agent['agent_code'] ?? ''),
        'name' => (string)($agent['agent_name'] ?? ''),
        'person_name' => (string)($agent['person_name'] ?? ''),
        'level' => $level,
        'role_label' => $level === 1
            ? getAdvisorPositionLabel($agent['position_type'] ?? null, $agent['position_label'] ?? null)
            : ($labels[$level] ?? 'メンバー'),
        'position_type' => (string)($agent['position_type'] ?? ''),
        'position_label' => (string)($agent['position_label'] ?? ''),
        'parent_id' => !empty($agent['parent_id']) ? (int)$agent['parent_id'] : null,
        'parent_agency_id' => $agent['parent_code'] ?? null,
        'parent_code' => $agent['parent_code'] ?? null,
        'status' => (string)($agent['status'] ?? ''),
        'lp_urls' => [],
        'created_at' => $agent['created_at'] ?? null,
        'updated_at' => $agent['updated_at'] ?? null,
    ];

    foreach ($projects as $project) {
        $payload['lp_urls'][] = [
            'project_id' => (int)$project['id'],
            'project_slug' => (string)$project['slug'],
            'project_name' => (string)$project['name'],
            'url' => buildAgentProjectLpUrl((string)($agent['agent_code'] ?? ''), $project),
        ];
    }

    if ($includeContact) {
        $payload['contact'] = [
            'email' => (string)($agent['email'] ?? ''),
            'phone' => (string)($agent['phone'] ?? ''),
            'line_url' => (string)($agent['line_url'] ?? ''),
        ];
    }
    if ($includeSso) {
        $payload['sso_urls'] = buildSsoLaunchUrlPayload();
    }

    return $payload;
}

if (!apiHasConfiguredToken()) {
    apiJson([
        'ok' => false,
        'error' => 'external_api_disabled',
        'message' => '外部連携APIキーが未設定です。',
    ], 403);
}

$requestToken = apiRequestToken();
if (!apiTokenIsValid($requestToken)) {
    apiJson([
        'ok' => false,
        'error' => 'unauthorized',
        'message' => 'APIキーが正しくありません。',
    ], 401);
}

$format = strtolower((string)($_GET['format'] ?? 'tree'));
if (!in_array($format, ['tree', 'flat'], true)) {
    $format = 'tree';
}
$includeInactive = ($_GET['include_inactive'] ?? '') === '1';
$includeContact = ($_GET['include_contact'] ?? '') === '1';
$includeSso = ($_GET['include_sso'] ?? '') === '1';
$rootCode = trim((string)($_GET['root_code'] ?? ''));

$db = getDB();
$projects = getProjects(true);
$labels = getLevelLabels();

$params = [];
$where = $includeInactive ? '1=1' : "a.status='active'";
if ($rootCode !== '') {
    $rootStmt = $db->prepare("SELECT id FROM agents WHERE agent_code=? LIMIT 1");
    $rootStmt->execute([$rootCode]);
    $rootId = (int)$rootStmt->fetchColumn();
    if ($rootId <= 0) {
        apiJson([
            'ok' => false,
            'error' => 'root_not_found',
            'message' => '指定されたroot_codeが見つかりません。',
        ], 404);
    }
    $ids = [$rootId];
    foreach (getAllDescendants($rootId) as $descendant) {
        $ids[] = (int)$descendant['id'];
    }
    $ids = array_values(array_unique($ids));
    $where .= ' AND a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $params = array_merge($params, $ids);
}

$stmt = $db->prepare("
    SELECT a.*, p.agent_code AS parent_code
    FROM agents a
    LEFT JOIN agents p ON a.parent_id = p.id
    WHERE $where
    ORDER BY a.level DESC, a.parent_id ASC, a.id ASC
");
$stmt->execute($params);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flat = [];
foreach ($agents as $agent) {
    $flat[] = apiAgentPayload($agent, $projects, $labels, $includeContact, $includeSso);
}

$response = [
    'ok' => true,
    'generated_at' => date('c'),
    'format' => $format,
    'filters' => [
        'root_code' => $rootCode !== '' ? $rootCode : null,
        'include_inactive' => $includeInactive,
        'include_contact' => $includeContact,
        'include_sso' => $includeSso,
    ],
    'labels' => [
        'level1' => $labels[1] ?? 'アドバイザー',
        'level2' => $labels[2] ?? 'ディレクター',
        'level3' => $labels[3] ?? 'エージェント',
        'positions' => getAdvisorPositionLabels(),
    ],
    'projects' => array_map(static fn($project) => [
        'id' => (int)$project['id'],
        'slug' => (string)$project['slug'],
        'name' => (string)$project['name'],
        'status' => (string)($project['status'] ?? ''),
        'sort_order' => (int)($project['sort_order'] ?? 0),
    ], $projects),
    'count' => count($flat),
];

if ($format === 'flat') {
    $response['agents'] = $flat;
    apiJson($response);
}

$byId = [];
$childrenByParent = [];
foreach ($flat as $agent) {
    $agent['children'] = [];
    $byId[$agent['id']] = $agent;
    $childrenByParent[$agent['parent_id'] ?? 0][] = $agent['id'];
}

$buildTree = function(int $id) use (&$buildTree, &$byId, &$childrenByParent): array {
    $node = $byId[$id];
    foreach ($childrenByParent[$id] ?? [] as $childId) {
        $node['children'][] = $buildTree($childId);
    }
    return $node;
};

$roots = [];
foreach ($byId as $id => $agent) {
    $parentId = $agent['parent_id'] ?? 0;
    if (!$parentId || !isset($byId[$parentId])) {
        $roots[] = $buildTree((int)$id);
    }
}

$response['tree'] = $roots;
apiJson($response);

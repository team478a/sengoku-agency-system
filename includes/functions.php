<?php
require_once __DIR__ . '/../config/database.php';

// =============================
// 繧ｻ繝・す繝ｧ繝ｳ繝ｻ隱崎ｨｼ
// =============================
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
        }
        session_start();
    }
}

function isAdminLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['admin_id']);
}

function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function getCurrentAdmin(): ?array {
    if (!isAdminLoggedIn()) {
        return null;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE id=? LIMIT 1");
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function isSuperAdmin(): bool {
    $admin = getCurrentAdmin();
    if (!$admin) {
        return false;
    }
    if (!array_key_exists('role', $admin)) {
        return true;
    }
    return (string)($admin['role'] ?? 'super_admin') === 'super_admin';
}

function requireSuperAdmin(): void {
    requireAdminLogin();
    if (!isSuperAdmin()) {
        http_response_code(403);
        exit('権限がありません。');
    }
}

// =============================
// 蜈･蜉帙し繝九ち繧､繧ｺ
// =============================
function h($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitizeInput(string $input): string {
    return trim(strip_tags($input));
}

function tableColumns(string $table): array {
    $db = getDB();
    $cols = [];
    try {
        foreach ($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $cols[$col['Field']] = true;
        }
    } catch (Throwable $e) {
        return [];
    }
    return $cols;
}

function tableHasColumn(string $table, string $column): bool {
    $cols = tableColumns($table);
    return !empty($cols[$column]);
}

function commonIdTablesReady(): bool {
    return !empty(tableColumns('common_users'))
        && !empty(tableColumns('service_user_mappings'))
        && !empty(tableColumns('agency_customer_relations'))
        && !empty(tableColumns('integration_idempotency_keys'))
        && !empty(tableColumns('integration_event_logs'));
}

function commonHubTablesReady(): bool {
    return commonIdTablesReady()
        && !empty(tableColumns('user_identities'))
        && !empty(tableColumns('system_account_links'))
        && !empty(tableColumns('agent_touchpoints'))
        && !empty(tableColumns('account_merge_logs'));
}

function getCommonIdFeatureFlags(): array {
    return [
        'common_id_enabled' => getSystemSettingValue('common_id_enabled', '0') === '1',
        'common_hub_enabled' => getSystemSettingValue('common_hub_enabled', getSystemSettingValue('common_id_enabled', '0')) === '1',
        'common_hub_read_enabled' => getSystemSettingValue('common_hub_read_enabled', '0') === '1',
        'common_hub_write_enabled' => getSystemSettingValue('common_hub_write_enabled', '0') === '1',
        'referral_v2_enabled' => getSystemSettingValue('referral_v2_enabled', '0') === '1',
        'external_registration_capture_enabled' => getSystemSettingValue('external_registration_capture_enabled', '0') === '1',
        'referral_token_api_enabled' => getSystemSettingValue('referral_token_api_enabled', '0') === '1',
        'passport_integration_enabled' => getSystemSettingValue('passport_integration_enabled', '0') === '1',
        'shopping_integration_enabled' => getSystemSettingValue('shopping_integration_enabled', '0') === '1',
        'wallet_integration_enabled' => getSystemSettingValue('wallet_integration_enabled', '0') === '1',
        'ai_art_integration_enabled' => getSystemSettingValue('ai_art_integration_enabled', '0') === '1',
        'common_hub_verified_identity_only' => getSystemSettingValue('common_hub_verified_identity_only', '1') === '1',
        'external_partner_outbox_enabled' => getSystemSettingValue('external_partner_outbox_enabled', '1') === '1',
        'external_partner_hmac_enabled' => getSystemSettingValue('external_partner_hmac_enabled', '1') === '1',
    ];
}

function generateCommonUserId(): string {
    return 'cu_' . bin2hex(random_bytes(16));
}

function commonIdHashValue(?string $value, string $type = 'text'): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if ($type === 'email') {
        $value = mb_strtolower($value);
    } elseif ($type === 'phone') {
        $value = preg_replace('/\D+/', '', $value) ?? '';
        if ($value === '') {
            return null;
        }
    }
    return hash('sha256', $type . ':' . $value);
}

function ensureCommonUser(?string $commonUserId = null, array $profile = []): string {
    if (!commonIdTablesReady()) {
        throw new RuntimeException('共通ID連携テーブルが未適用です。');
    }
    $commonUserId = trim((string)$commonUserId);
    if ($commonUserId === '') {
        $commonUserId = generateCommonUserId();
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $commonUserId)) {
        throw new InvalidArgumentException('common_user_id の形式が不正です。');
    }

    $emailHash = commonIdHashValue($profile['email'] ?? null, 'email');
    $phoneHash = commonIdHashValue($profile['phone'] ?? null, 'phone');
    $wallet = trim((string)($profile['wallet_address'] ?? '')) ?: null;

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO common_users (common_user_id, primary_email_hash, primary_phone_hash, primary_wallet_address)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            primary_email_hash = COALESCE(primary_email_hash, VALUES(primary_email_hash)),
            primary_phone_hash = COALESCE(primary_phone_hash, VALUES(primary_phone_hash)),
            primary_wallet_address = COALESCE(primary_wallet_address, VALUES(primary_wallet_address)),
            updated_at = NOW()
    ");
    $stmt->execute([$commonUserId, $emailHash, $phoneHash, $wallet]);
    return $commonUserId;
}

function updateCommonUserHubFields(string $commonUserId, array $data): void {
    if ($commonUserId === '' || empty(tableColumns('common_users'))) {
        return;
    }
    $cols = tableColumns('common_users');
    $allowed = [
        'acquisition_channel',
        'acquisition_source',
        'campaign_id',
        'registration_referrer_agent_id',
        'assigned_agent_id',
        'agent_link_status',
        'management_status',
        'first_touch_at',
        'last_touch_at',
        'metadata_json',
    ];
    $sets = [];
    $params = [];
    foreach ($allowed as $field) {
        if (empty($cols[$field]) || !array_key_exists($field, $data)) {
            continue;
        }
        $value = $data[$field];
        if ($field === 'metadata_json' && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (in_array($field, ['registration_referrer_agent_id', 'assigned_agent_id'], true)) {
            $value = $value !== null && $value !== '' ? (int)$value : null;
        }
        $sets[] = "$field = COALESCE(?, $field)";
        $params[] = $value !== '' ? $value : null;
    }
    if (!$sets) {
        return;
    }
    $params[] = $commonUserId;
    $sql = "UPDATE common_users SET " . implode(', ', $sets) . ", updated_at=NOW() WHERE common_user_id=?";
    getDB()->prepare($sql)->execute($params);
}

function findCommonUserMapping(string $serviceKey, string $serviceUserId): ?array {
    if (!commonIdTablesReady()) {
        return null;
    }
    $serviceKey = trim($serviceKey);
    $serviceUserId = trim($serviceUserId);
    if ($serviceKey === '' || $serviceUserId === '') {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM service_user_mappings WHERE service_key=? AND service_user_id=? LIMIT 1");
    $stmt->execute([$serviceKey, $serviceUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function saveServiceUserMapping(array $data): array {
    if (!commonIdTablesReady()) {
        throw new RuntimeException('共通ID連携テーブルが未適用です。');
    }
    $serviceKey = trim((string)($data['service_key'] ?? ''));
    $serviceUserId = trim((string)($data['service_user_id'] ?? ''));
    if ($serviceKey === '' || !preg_match('/^[a-zA-Z0-9_\-]{2,100}$/', $serviceKey)) {
        throw new InvalidArgumentException('service_key の形式が不正です。');
    }
    if ($serviceUserId === '') {
        throw new InvalidArgumentException('service_user_id は必須です。');
    }

    $commonUserId = ensureCommonUser($data['common_user_id'] ?? null, $data);
    $agentId = !empty($data['agent_id']) ? (int)$data['agent_id'] : null;
    $emailHash = commonIdHashValue($data['email'] ?? null, 'email');
    $phoneHash = commonIdHashValue($data['phone'] ?? null, 'phone');
    $wallet = trim((string)($data['wallet_address'] ?? '')) ?: null;
    $profileJson = null;
    if (!empty($data['profile']) && is_array($data['profile'])) {
        $profileJson = json_encode($data['profile'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO service_user_mappings
            (common_user_id, service_key, service_user_id, agent_id, email_hash, phone_hash, wallet_address, profile_json, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            common_user_id = VALUES(common_user_id),
            agent_id = COALESCE(VALUES(agent_id), agent_id),
            email_hash = COALESCE(VALUES(email_hash), email_hash),
            phone_hash = COALESCE(VALUES(phone_hash), phone_hash),
            wallet_address = COALESCE(VALUES(wallet_address), wallet_address),
            profile_json = COALESCE(VALUES(profile_json), profile_json),
            status = 'active',
            updated_at = NOW()
    ");
    $stmt->execute([$commonUserId, $serviceKey, $serviceUserId, $agentId, $emailHash, $phoneHash, $wallet, $profileJson]);

    return findCommonUserMapping($serviceKey, $serviceUserId) ?: [
        'common_user_id' => $commonUserId,
        'service_key' => $serviceKey,
        'service_user_id' => $serviceUserId,
    ];
}

function findSystemAccountLink(string $systemKey, string $externalUserId): ?array {
    if (empty(tableColumns('system_account_links'))) {
        return null;
    }
    $systemKey = trim($systemKey);
    $externalUserId = trim($externalUserId);
    if ($systemKey === '' || $externalUserId === '') {
        return null;
    }
    $stmt = getDB()->prepare("SELECT * FROM system_account_links WHERE system_key=? AND external_user_id=? LIMIT 1");
    $stmt->execute([$systemKey, $externalUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function findCommonUserByIdentity(string $identityType, string $identityValue, string $provider = '', ?bool $verifiedOnly = null): ?array {
    if (empty(tableColumns('user_identities'))) {
        return null;
    }
    $hash = commonIdHashValue($identityValue, $identityType);
    if ($hash === null) {
        return null;
    }
    if ($verifiedOnly === null) {
        $verifiedOnly = getSystemSettingValue('common_hub_verified_identity_only', '1') === '1';
    }
    $verifiedSql = $verifiedOnly ? " AND i.verified=1" : "";
    $stmt = getDB()->prepare("
        SELECT u.*, i.identity_type, i.provider, i.verified, i.confidence_score
        FROM user_identities i
        LEFT JOIN common_users u ON u.common_user_id=i.common_user_id
        WHERE i.identity_type=? AND i.provider=? AND i.identity_hash=? AND i.status='active' $verifiedSql
        LIMIT 1
    ");
    $stmt->execute([$identityType, $provider, $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function saveUserIdentity(array $data): array {
    if (empty(tableColumns('user_identities'))) {
        throw new RuntimeException('user_identities table is not migrated.');
    }
    $commonUserId = ensureCommonUser($data['common_user_id'] ?? null, $data);
    $identityType = trim((string)($data['identity_type'] ?? ''));
    $provider = trim((string)($data['provider'] ?? ''));
    $identityValue = trim((string)($data['identity_value'] ?? ''));
    if ($identityType === '' || $identityValue === '') {
        throw new InvalidArgumentException('identity_type and identity_value are required.');
    }
    $hash = commonIdHashValue($identityValue, $identityType);
    if ($hash === null) {
        throw new InvalidArgumentException('identity_value is invalid.');
    }
    $masked = trim((string)($data['identity_masked'] ?? ''));
    if ($masked === '') {
        if ($identityType === 'email') {
            $masked = preg_replace('/(^.).*(@.*$)/', '$1***$2', $identityValue) ?: null;
        } elseif ($identityType === 'phone') {
            $digits = preg_replace('/\D+/', '', $identityValue) ?? '';
            $masked = strlen($digits) > 4 ? '***' . substr($digits, -4) : '***';
        } else {
            $masked = substr($identityValue, 0, 4) . '***';
        }
    }
    $stmt = getDB()->prepare("
        INSERT INTO user_identities
            (common_user_id, identity_type, provider, identity_hash, identity_masked, verified, confidence_score, source_system_key, source_external_user_id, status, first_seen_at, last_seen_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            common_user_id=VALUES(common_user_id),
            identity_masked=COALESCE(VALUES(identity_masked), identity_masked),
            verified=GREATEST(verified, VALUES(verified)),
            confidence_score=GREATEST(confidence_score, VALUES(confidence_score)),
            source_system_key=COALESCE(source_system_key, VALUES(source_system_key)),
            source_external_user_id=COALESCE(source_external_user_id, VALUES(source_external_user_id)),
            status='active',
            last_seen_at=NOW(),
            updated_at=NOW()
    ");
    $stmt->execute([
        $commonUserId,
        $identityType,
        $provider,
        $hash,
        $masked ?: null,
        !empty($data['verified']) ? 1 : 0,
        isset($data['confidence_score']) ? max(0, min(100, (int)$data['confidence_score'])) : 100,
        trim((string)($data['source_system_key'] ?? '')) ?: null,
        trim((string)($data['source_external_user_id'] ?? '')) ?: null,
    ]);

    $load = getDB()->prepare("SELECT * FROM user_identities WHERE identity_type=? AND provider=? AND identity_hash=? LIMIT 1");
    $load->execute([$identityType, $provider, $hash]);
    return $load->fetch(PDO::FETCH_ASSOC) ?: [];
}

function saveSystemAccountLink(array $data): array {
    if (empty(tableColumns('system_account_links'))) {
        throw new RuntimeException('system_account_links table is not migrated.');
    }
    $systemKey = trim((string)($data['system_key'] ?? $data['service_key'] ?? ''));
    $externalUserId = trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? ''));
    if ($systemKey === '' || !preg_match('/^[a-zA-Z0-9_\-]{2,100}$/', $systemKey)) {
        throw new InvalidArgumentException('system_key is invalid.');
    }
    if ($externalUserId === '') {
        throw new InvalidArgumentException('external_user_id is required.');
    }

    $commonUserId = ensureCommonUser($data['common_user_id'] ?? null, $data);
    $agentId = !empty($data['agent_id']) ? (int)$data['agent_id'] : null;
    $emailHash = commonIdHashValue($data['email'] ?? null, 'email');
    $loginEmailHash = commonIdHashValue($data['login_email'] ?? null, 'email');
    $phoneHash = commonIdHashValue($data['phone'] ?? null, 'phone');
    $wallet = trim((string)($data['wallet_address'] ?? '')) ?: null;
    $profileJson = null;
    if (!empty($data['profile']) && is_array($data['profile'])) {
        $profileJson = json_encode($data['profile'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $stmt = getDB()->prepare("
        INSERT INTO system_account_links
            (common_user_id, system_key, external_user_id, agent_id, email_hash, phone_hash, wallet_address, login_email_hash, display_name, role_name, profile_json, status, linked_at, last_synced_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            common_user_id=VALUES(common_user_id),
            agent_id=COALESCE(VALUES(agent_id), agent_id),
            email_hash=COALESCE(VALUES(email_hash), email_hash),
            phone_hash=COALESCE(VALUES(phone_hash), phone_hash),
            wallet_address=COALESCE(VALUES(wallet_address), wallet_address),
            login_email_hash=COALESCE(VALUES(login_email_hash), login_email_hash),
            display_name=COALESCE(VALUES(display_name), display_name),
            role_name=COALESCE(VALUES(role_name), role_name),
            profile_json=COALESCE(VALUES(profile_json), profile_json),
            status='active',
            last_synced_at=NOW(),
            updated_at=NOW()
    ");
    $stmt->execute([
        $commonUserId,
        $systemKey,
        $externalUserId,
        $agentId,
        $emailHash,
        $phoneHash,
        $wallet,
        $loginEmailHash,
        trim((string)($data['display_name'] ?? $data['name'] ?? '')) ?: null,
        trim((string)($data['role_name'] ?? $data['role'] ?? '')) ?: null,
        $profileJson,
    ]);

    saveServiceUserMapping([
        'common_user_id' => $commonUserId,
        'service_key' => $systemKey,
        'service_user_id' => $externalUserId,
        'agent_id' => $agentId,
        'email' => $data['email'] ?? null,
        'phone' => $data['phone'] ?? null,
        'wallet_address' => $wallet,
        'profile' => is_array($data['profile'] ?? null) ? $data['profile'] : [],
    ]);

    $email = trim((string)($data['email'] ?? ''));
    if ($email !== '') {
        saveUserIdentity([
            'common_user_id' => $commonUserId,
            'identity_type' => 'email',
            'identity_value' => $email,
            'verified' => !empty($data['email_verified']),
            'source_system_key' => $systemKey,
            'source_external_user_id' => $externalUserId,
        ]);
    }
    $loginEmail = trim((string)($data['login_email'] ?? ''));
    if ($loginEmail !== '' && $loginEmail !== $email) {
        saveUserIdentity([
            'common_user_id' => $commonUserId,
            'identity_type' => 'email',
            'provider' => 'login',
            'identity_value' => $loginEmail,
            'verified' => !empty($data['login_email_verified']),
            'source_system_key' => $systemKey,
            'source_external_user_id' => $externalUserId,
        ]);
    }
    $phone = trim((string)($data['phone'] ?? ''));
    if ($phone !== '') {
        saveUserIdentity([
            'common_user_id' => $commonUserId,
            'identity_type' => 'phone',
            'identity_value' => $phone,
            'verified' => !empty($data['phone_verified']),
            'source_system_key' => $systemKey,
            'source_external_user_id' => $externalUserId,
        ]);
    }
    $lineUserId = trim((string)($data['line_user_id'] ?? ''));
    if ($lineUserId !== '') {
        saveUserIdentity([
            'common_user_id' => $commonUserId,
            'identity_type' => 'line',
            'identity_value' => $lineUserId,
            'verified' => true,
            'source_system_key' => $systemKey,
            'source_external_user_id' => $externalUserId,
        ]);
    }
    if ($wallet) {
        saveUserIdentity([
            'common_user_id' => $commonUserId,
            'identity_type' => 'wallet',
            'identity_value' => $wallet,
            'verified' => !empty($data['wallet_verified']),
            'source_system_key' => $systemKey,
            'source_external_user_id' => $externalUserId,
        ]);
    }

    return findSystemAccountLink($systemKey, $externalUserId) ?: [
        'common_user_id' => $commonUserId,
        'system_key' => $systemKey,
        'external_user_id' => $externalUserId,
    ];
}

function loadCommonUserHubProfile(string $commonUserId): ?array {
    if (trim($commonUserId) === '' || empty(tableColumns('common_users'))) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM common_users WHERE common_user_id=? LIMIT 1");
    $stmt->execute([$commonUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }
    $links = [];
    if (!empty(tableColumns('system_account_links'))) {
        $linkStmt = $db->prepare("SELECT * FROM system_account_links WHERE common_user_id=? ORDER BY system_key ASC, id ASC");
        $linkStmt->execute([$commonUserId]);
        $links = $linkStmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (!empty(tableColumns('service_user_mappings'))) {
        $linkStmt = $db->prepare("SELECT * FROM service_user_mappings WHERE common_user_id=? ORDER BY service_key ASC, id ASC");
        $linkStmt->execute([$commonUserId]);
        $links = $linkStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $identities = [];
    if (!empty(tableColumns('user_identities'))) {
        $identityStmt = $db->prepare("SELECT id, common_user_id, identity_type, provider, identity_masked, verified, confidence_score, source_system_key, source_external_user_id, status, first_seen_at, last_seen_at, created_at, updated_at FROM user_identities WHERE common_user_id=? ORDER BY identity_type ASC, id ASC");
        $identityStmt->execute([$commonUserId]);
        $identities = $identityStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $relations = [];
    if (!empty(tableColumns('agency_customer_relations'))) {
        $relationStmt = $db->prepare("
            SELECT r.*, a.agent_code, a.agent_name, a.person_name, p.slug AS project_slug, p.name AS project_name
            FROM agency_customer_relations r
            LEFT JOIN agents a ON r.agent_id=a.id
            LEFT JOIN projects p ON r.project_id=p.id
            WHERE r.common_user_id=?
            ORDER BY r.updated_at DESC, r.id DESC
        ");
        $relationStmt->execute([$commonUserId]);
        $relations = $relationStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [
        'common_user' => $user,
        'system_links' => $links,
        'identities' => $identities,
        'agency_relations' => $relations,
    ];
}

function saveAgencyCustomerRelation(array $data): array {
    if (!commonIdTablesReady()) {
        throw new RuntimeException('共通ID連携テーブルが未適用です。');
    }
    $commonUserId = ensureCommonUser($data['common_user_id'] ?? null, $data);
    $agentId = !empty($data['agent_id']) ? (int)$data['agent_id'] : null;
    $projectId = !empty($data['project_id']) ? (int)$data['project_id'] : 0;
    $relationType = trim((string)($data['relation_type'] ?? 'referral')) ?: 'referral';
    $sourceServiceKey = trim((string)($data['source_service_key'] ?? '')) ?: null;
    $sourceServiceUserId = trim((string)($data['source_service_user_id'] ?? '')) ?: null;
    $referralTokenId = !empty($data['referral_token_id']) ? (int)$data['referral_token_id'] : null;
    $referralSource = trim((string)($data['referral_source'] ?? '')) ?: null;
    $locked = array_key_exists('locked', $data) ? ((int)!empty($data['locked'])) : 1;

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO agency_customer_relations
            (common_user_id, agent_id, project_id, relation_type, source_service_key, source_service_user_id, referral_token_id, referral_source, locked, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            agent_id = IF(locked=1 AND agent_id IS NOT NULL, agent_id, VALUES(agent_id)),
            project_id = VALUES(project_id),
            source_service_key = COALESCE(source_service_key, VALUES(source_service_key)),
            source_service_user_id = COALESCE(source_service_user_id, VALUES(source_service_user_id)),
            referral_token_id = COALESCE(referral_token_id, VALUES(referral_token_id)),
            referral_source = COALESCE(referral_source, VALUES(referral_source)),
            locked = GREATEST(locked, VALUES(locked)),
            status = 'active',
            updated_at = NOW()
    ");
    $stmt->execute([
        $commonUserId,
        $agentId,
        $projectId,
        $relationType,
        $sourceServiceKey,
        $sourceServiceUserId,
        $referralTokenId,
        $referralSource,
        $locked,
    ]);

    $load = $db->prepare("SELECT * FROM agency_customer_relations WHERE common_user_id=? AND relation_type=? AND project_id=? LIMIT 1");
    $load->execute([$commonUserId, $relationType, $projectId]);
    return $load->fetch(PDO::FETCH_ASSOC) ?: [];
}

function maskSensitiveScalarForLog(string $key, $value) {
    if ($value === null || $value === '') {
        return $value;
    }
    $lowerKey = strtolower($key);
    $stringValue = (string)$value;
    $sensitiveKeyPatterns = [
        'password',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'token',
        'secret',
        'signature',
        'authorization',
        'jwt',
        'line_user_id',
        'wallet_address',
    ];
    foreach ($sensitiveKeyPatterns as $pattern) {
        if (strpos($lowerKey, $pattern) !== false) {
            return strlen($stringValue) > 10
                ? substr($stringValue, 0, 4) . '***' . substr($stringValue, -4)
                : '***';
        }
    }
    if (strpos($lowerKey, 'email') !== false || filter_var($stringValue, FILTER_VALIDATE_EMAIL)) {
        return preg_replace('/(^.).*(@.*$)/', '$1***$2', $stringValue) ?: '***';
    }
    if (strpos($lowerKey, 'phone') !== false || strpos($lowerKey, 'tel') !== false) {
        $digits = preg_replace('/\D+/', '', $stringValue) ?? '';
        return strlen($digits) > 4 ? '***' . substr($digits, -4) : '***';
    }
    if (in_array($lowerKey, ['name', 'full_name', 'contact_name', 'person_name', 'display_name'], true)) {
        return mb_substr($stringValue, 0, 1, 'UTF-8') . '***';
    }
    return $value;
}

function maskIntegrationPayloadForLog($payload, string $parentKey = '') {
    if (is_array($payload)) {
        $masked = [];
        foreach ($payload as $key => $value) {
            $masked[$key] = maskIntegrationPayloadForLog($value, (string)$key);
        }
        return $masked;
    }
    if (is_object($payload)) {
        return maskIntegrationPayloadForLog((array)$payload, $parentKey);
    }
    return maskSensitiveScalarForLog($parentKey, $payload);
}

function encodeMaskedIntegrationBodyForLog($body): ?string {
    if ($body === null) {
        return null;
    }
    if (is_array($body) || is_object($body)) {
        $encoded = json_encode(maskIntegrationPayloadForLog($body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }
    $stringBody = (string)$body;
    $decoded = json_decode($stringBody, true);
    if (is_array($decoded)) {
        $encoded = json_encode(maskIntegrationPayloadForLog($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? $stringBody : $encoded;
    }
    return $stringBody;
}

function logIntegrationEvent(array $data): void {
    if (empty(tableColumns('integration_event_logs'))) {
        return;
    }
    try {
        $requestBody = encodeMaskedIntegrationBodyForLog($data['request_body'] ?? null);
        $responseBody = encodeMaskedIntegrationBodyForLog($data['response_body'] ?? null);
        $stmt = getDB()->prepare("
            INSERT INTO integration_event_logs
                (direction, site_key, event_type, endpoint, http_status, success, common_user_id, agent_id, request_body, response_body, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            in_array(($data['direction'] ?? ''), ['inbound', 'outbound'], true) ? $data['direction'] : 'inbound',
            $data['site_key'] ?? null,
            (string)($data['event_type'] ?? 'unknown'),
            $data['endpoint'] ?? null,
            isset($data['http_status']) ? (int)$data['http_status'] : null,
            !empty($data['success']) ? 1 : 0,
            $data['common_user_id'] ?? null,
            isset($data['agent_id']) ? (int)$data['agent_id'] : null,
            $requestBody !== null ? substr((string)$requestBody, 0, 65535) : null,
            $responseBody !== null ? substr((string)$responseBody, 0, 65535) : null,
            $data['error_message'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Integration event log failed: ' . $e->getMessage());
    }
}

function getCommonIdStats(): array {
    $defaults = [
        'common_users' => 0,
        'service_mappings' => 0,
        'customer_relations' => 0,
        'integration_logs' => 0,
        'last_event_at' => null,
    ];
    if (!commonIdTablesReady()) {
        return $defaults;
    }
    try {
        $db = getDB();
        $defaults['common_users'] = (int)$db->query("SELECT COUNT(*) FROM common_users")->fetchColumn();
        $defaults['service_mappings'] = (int)$db->query("SELECT COUNT(*) FROM service_user_mappings")->fetchColumn();
        $defaults['customer_relations'] = (int)$db->query("SELECT COUNT(*) FROM agency_customer_relations")->fetchColumn();
        $defaults['integration_logs'] = (int)$db->query("SELECT COUNT(*) FROM integration_event_logs")->fetchColumn();
        $defaults['last_event_at'] = $db->query("SELECT MAX(created_at) FROM integration_event_logs")->fetchColumn() ?: null;
    } catch (Throwable $e) {
        return $defaults;
    }
    return $defaults;
}

function referralTokenTablesReady(): bool {
    return !empty(tableColumns('referral_tokens'))
        && !empty(tableColumns('referral_sessions'));
}

function generateReferralTokenValue(): string {
    return 'rt_' . bin2hex(random_bytes(18));
}

function generateReferralSessionKey(): string {
    return 'rs_' . bin2hex(random_bytes(18));
}

function findReferralToken(string $token): ?array {
    if (!referralTokenTablesReady()) {
        return null;
    }
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    $stmt = getDB()->prepare("
        SELECT
            rt.*,
            a.agent_code,
            a.agent_name,
            a.person_name,
            a.level AS agent_level,
            p.slug AS project_slug,
            p.name AS project_name
        FROM referral_tokens rt
        LEFT JOIN agents a ON rt.agent_id=a.id
        LEFT JOIN projects p ON rt.project_id=p.id
        WHERE rt.token=?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function ensureReferralToken(array $data): array {
    if (!referralTokenTablesReady()) {
        throw new RuntimeException('紹介トークンテーブルが未適用です。');
    }
    $agentId = !empty($data['agent_id']) ? (int)$data['agent_id'] : 0;
    if ($agentId <= 0) {
        throw new InvalidArgumentException('agent_id は必須です。');
    }
    $projectId = !empty($data['project_id']) ? (int)$data['project_id'] : 0;
    $tokenType = trim((string)($data['token_type'] ?? 'lp')) ?: 'lp';
    $destinationServiceKey = trim((string)($data['destination_service_key'] ?? ''));
    $destinationUrl = trim((string)($data['destination_url'] ?? '')) ?: null;
    $expiresAt = trim((string)($data['expires_at'] ?? '')) ?: null;
    $metadataJson = null;
    if (!empty($data['metadata']) && is_array($data['metadata'])) {
        $metadataJson = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $existing = getDB()->prepare("
        SELECT *
        FROM referral_tokens
        WHERE agent_id=? AND project_id=? AND token_type=? AND destination_service_key=?
        LIMIT 1
    ");
    $existing->execute([$agentId, $projectId, $tokenType, $destinationServiceKey]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stmt = getDB()->prepare("
            UPDATE referral_tokens
            SET destination_url=COALESCE(?, destination_url),
                metadata_json=COALESCE(?, metadata_json),
                expires_at=?,
                status='active',
                updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$destinationUrl, $metadataJson, $expiresAt, (int)$row['id']]);
        return findReferralToken((string)$row['token']) ?: $row;
    }

    do {
        $token = generateReferralTokenValue();
        $check = getDB()->prepare("SELECT id FROM referral_tokens WHERE token=? LIMIT 1");
        $check->execute([$token]);
    } while ($check->fetchColumn());

    $stmt = getDB()->prepare("
        INSERT INTO referral_tokens
            (token, agent_id, project_id, token_type, destination_service_key, destination_url, metadata_json, expires_at, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$token, $agentId, $projectId, $tokenType, $destinationServiceKey, $destinationUrl, $metadataJson, $expiresAt]);
    return findReferralToken($token) ?: [];
}

function validateReferralToken(string $token): array {
    $row = findReferralToken($token);
    if (!$row) {
        return ['valid' => false, 'reason' => 'not_found'];
    }
    if (($row['status'] ?? '') !== 'active') {
        return ['valid' => false, 'reason' => 'inactive', 'token' => $row];
    }
    if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time()) {
        return ['valid' => false, 'reason' => 'expired', 'token' => $row];
    }
    return ['valid' => true, 'reason' => 'ok', 'token' => $row];
}

function referralAliasHash(string $aliasType, string $aliasValue): string {
    return hash('sha256', mb_strtolower(trim($aliasType)) . ':' . trim($aliasValue));
}

function maskReferralAliasValue(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) <= 10) {
        return substr($value, 0, 2) . '***';
    }
    return substr($value, 0, 6) . '***' . substr($value, -4);
}

function findReferralAlias(string $aliasType, string $aliasValue): ?array {
    if (empty(tableColumns('referral_aliases'))) {
        return null;
    }
    $aliasType = trim($aliasType);
    $aliasValue = trim($aliasValue);
    if ($aliasType === '' || $aliasValue === '') {
        return null;
    }
    $stmt = getDB()->prepare("
        SELECT ra.*, rt.token AS canonical_token
        FROM referral_aliases ra
        INNER JOIN referral_tokens rt ON rt.id=ra.canonical_token_id
        WHERE ra.alias_type=? AND ra.alias_value_hash=? AND ra.status='active'
        LIMIT 1
    ");
    $stmt->execute([$aliasType, referralAliasHash($aliasType, $aliasValue)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function saveReferralAlias(array $data): array {
    if (empty(tableColumns('referral_aliases'))) {
        throw new RuntimeException('referral_aliases table is not migrated.');
    }
    $aliasType = trim((string)($data['alias_type'] ?? ''));
    $aliasValue = trim((string)($data['alias_value'] ?? ''));
    $canonicalTokenId = (int)($data['canonical_token_id'] ?? 0);
    if ($aliasType === '' || $aliasValue === '' || $canonicalTokenId <= 0) {
        throw new InvalidArgumentException('alias_type, alias_value and canonical_token_id are required.');
    }
    $metadataJson = null;
    if (is_array($data['metadata'] ?? null)) {
        $metadataJson = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $stmt = getDB()->prepare("
        INSERT INTO referral_aliases
            (alias_type, alias_value_hash, alias_value_masked, canonical_token_id, source_system_key, status, metadata_json)
        VALUES (?, ?, ?, ?, ?, 'active', ?)
        ON DUPLICATE KEY UPDATE
            canonical_token_id=VALUES(canonical_token_id),
            source_system_key=COALESCE(VALUES(source_system_key), source_system_key),
            status='active',
            metadata_json=COALESCE(VALUES(metadata_json), metadata_json),
            updated_at=NOW()
    ");
    $stmt->execute([
        $aliasType,
        referralAliasHash($aliasType, $aliasValue),
        maskReferralAliasValue($aliasValue),
        $canonicalTokenId,
        trim((string)($data['source_system_key'] ?? '')) ?: null,
        $metadataJson,
    ]);
    return findReferralAlias($aliasType, $aliasValue) ?: [];
}

function resolveReferralTokenInput(string $value, string $aliasType = ''): array {
    $value = trim($value);
    if ($value === '') {
        return ['valid' => false, 'reason' => 'empty'];
    }
    $direct = validateReferralToken($value);
    if (!empty($direct['valid'])) {
        $direct['resolved_by'] = 'canonical_token';
        $direct['canonical_referral_token'] = $direct['token']['token'] ?? $value;
        return $direct;
    }

    $types = [];
    if (trim($aliasType) !== '') {
        $types[] = trim($aliasType);
    }
    foreach (['ref', 'referral_code', 'shopping_referral_code', 'wallet_invite_token', 'passport_ref'] as $type) {
        if (!in_array($type, $types, true)) {
            $types[] = $type;
        }
    }
    foreach ($types as $type) {
        $alias = findReferralAlias($type, $value);
        if (!$alias || empty($alias['canonical_token'])) {
            continue;
        }
        $validation = validateReferralToken((string)$alias['canonical_token']);
        if (!empty($validation['valid'])) {
            $validation['resolved_by'] = 'alias:' . $type;
            $validation['referral_alias'] = $alias;
            $validation['canonical_referral_token'] = $validation['token']['token'] ?? $alias['canonical_token'];
            return $validation;
        }
        return $validation + ['resolved_by' => 'alias:' . $type, 'referral_alias' => $alias];
    }
    return ['valid' => false, 'reason' => $direct['reason'] ?? 'not_found'];
}

function recordReferralSession(array $data): array {
    if (!referralTokenTablesReady()) {
        throw new RuntimeException('紹介トークンテーブルが未適用です。');
    }
    $tokenRow = $data['token_row'] ?? null;
    if (!is_array($tokenRow)) {
        $tokenRow = findReferralToken((string)($data['token'] ?? ''));
    }
    if (!$tokenRow) {
        throw new InvalidArgumentException('紹介トークンが見つかりません。');
    }

    $sessionKey = trim((string)($data['session_key'] ?? ''));
    if ($sessionKey === '') {
        $sessionKey = generateReferralSessionKey();
    }
    $metadataJson = null;
    if (!empty($data['metadata']) && is_array($data['metadata'])) {
        $metadataJson = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $ip = trim((string)($data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
    $ipHash = $ip !== '' ? hash('sha256', 'ip:' . $ip) : null;

    $stmt = getDB()->prepare("
        INSERT INTO referral_sessions
            (session_key, referral_token_id, token, agent_id, project_id, service_key, service_user_id, common_user_id, landing_url, destination_url, referrer_url, user_agent, ip_hash, event_type, metadata_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            service_user_id=COALESCE(VALUES(service_user_id), service_user_id),
            common_user_id=COALESCE(VALUES(common_user_id), common_user_id),
            destination_url=COALESCE(VALUES(destination_url), destination_url),
            event_type=VALUES(event_type)
    ");
    $stmt->execute([
        $sessionKey,
        (int)$tokenRow['id'],
        (string)$tokenRow['token'],
        (int)$tokenRow['agent_id'],
        (int)($tokenRow['project_id'] ?? 0),
        trim((string)($data['service_key'] ?? '')) ?: ($tokenRow['destination_service_key'] ?? null),
        trim((string)($data['service_user_id'] ?? '')) ?: null,
        trim((string)($data['common_user_id'] ?? '')) ?: null,
        trim((string)($data['landing_url'] ?? '')) ?: null,
        trim((string)($data['destination_url'] ?? '')) ?: ($tokenRow['destination_url'] ?? null),
        trim((string)($data['referrer_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''))) ?: null,
        substr(trim((string)($data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))), 0, 500) ?: null,
        $ipHash,
        trim((string)($data['event_type'] ?? 'click')) ?: 'click',
        $metadataJson,
    ]);

    getDB()->prepare("UPDATE referral_tokens SET click_count=click_count+1, last_used_at=NOW() WHERE id=?")
        ->execute([(int)$tokenRow['id']]);

    $load = getDB()->prepare("SELECT * FROM referral_sessions WHERE session_key=? LIMIT 1");
    $load->execute([$sessionKey]);
    return $load->fetch(PDO::FETCH_ASSOC) ?: ['session_key' => $sessionKey];
}

function saveCustomerTransaction(array $data): array {
    if (empty(tableColumns('customer_transactions'))) {
        return [];
    }
    $commonUserId = trim((string)($data['common_user_id'] ?? ''));
    $systemKey = trim((string)($data['source_system_key'] ?? $data['system_key'] ?? $data['service_key'] ?? ''));
    $orderId = trim((string)($data['order_id'] ?? $data['transaction_id'] ?? ''));
    if ($commonUserId === '' || $systemKey === '' || $orderId === '') {
        return [];
    }
    $orderItemId = trim((string)($data['order_item_id'] ?? '')) ?: 'default';
    $metadataJson = null;
    if (is_array($data['metadata'] ?? null)) {
        $metadataJson = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $stmt = getDB()->prepare("
        INSERT INTO customer_transactions
            (common_user_id, source_system_key, source_user_id, order_id, order_item_id, product_code,
             registration_referrer_agency_id, assigned_agency_id, sales_agent_id, closing_agent_id,
             referral_session_key, payment_status, entitlement_status, amount, currency, occurred_at, metadata_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            common_user_id=VALUES(common_user_id),
            source_user_id=COALESCE(VALUES(source_user_id), source_user_id),
            product_code=COALESCE(VALUES(product_code), product_code),
            registration_referrer_agency_id=COALESCE(registration_referrer_agency_id, VALUES(registration_referrer_agency_id)),
            assigned_agency_id=COALESCE(VALUES(assigned_agency_id), assigned_agency_id),
            sales_agent_id=COALESCE(VALUES(sales_agent_id), sales_agent_id),
            closing_agent_id=COALESCE(VALUES(closing_agent_id), closing_agent_id),
            referral_session_key=COALESCE(VALUES(referral_session_key), referral_session_key),
            payment_status=COALESCE(VALUES(payment_status), payment_status),
            entitlement_status=COALESCE(VALUES(entitlement_status), entitlement_status),
            amount=COALESCE(VALUES(amount), amount),
            currency=COALESCE(VALUES(currency), currency),
            metadata_json=COALESCE(VALUES(metadata_json), metadata_json),
            updated_at=NOW()
    ");
    $stmt->execute([
        $commonUserId,
        $systemKey,
        trim((string)($data['source_user_id'] ?? $data['external_user_id'] ?? $data['service_user_id'] ?? '')) ?: null,
        $orderId,
        $orderItemId,
        trim((string)($data['product_code'] ?? '')) ?: null,
        trim((string)($data['registration_referrer_agency_id'] ?? '')) ?: null,
        trim((string)($data['assigned_agency_id'] ?? '')) ?: null,
        trim((string)($data['sales_agent_id'] ?? '')) ?: null,
        trim((string)($data['closing_agent_id'] ?? '')) ?: null,
        trim((string)($data['referral_session_key'] ?? $data['session_key'] ?? '')) ?: null,
        trim((string)($data['payment_status'] ?? '')) ?: null,
        trim((string)($data['entitlement_status'] ?? '')) ?: null,
        array_key_exists('amount', $data) && $data['amount'] !== '' ? (float)$data['amount'] : null,
        trim((string)($data['currency'] ?? 'JPY')) ?: 'JPY',
        trim((string)($data['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s'),
        $metadataJson,
    ]);
    $load = getDB()->prepare("SELECT * FROM customer_transactions WHERE source_system_key=? AND order_id=? AND order_item_id=? LIMIT 1");
    $load->execute([$systemKey, $orderId, $orderItemId]);
    return $load->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getProjects(bool $activeOnly = false): array {
    $db = getDB();
    try {
        $where = $activeOnly ? "WHERE status='active'" : '';
        return $db->query("SELECT * FROM projects $where ORDER BY sort_order ASC, id ASC")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getDefaultProjectId(): ?int {
    $projects = getProjects(true);
    return !empty($projects[0]['id']) ? (int)$projects[0]['id'] : null;
}

function getSiteBaseUrl(): string {
    $baseUrl = '';
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT value FROM system_settings WHERE key_name='site_url' LIMIT 1");
        $stmt->execute();
        $baseUrl = trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        $baseUrl = '';
    }
    if ($baseUrl === '') {
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    } else {
        $parts = parse_url($baseUrl);
        if (!empty($parts['host'])) {
            $scheme = $parts['scheme'] ?? (isset($_SERVER['HTTPS']) ? 'https' : 'http');
            $port = !empty($parts['port']) ? ':' . $parts['port'] : '';
            $baseUrl = $scheme . '://' . $parts['host'] . $port;
        }
    }
    return rtrim($baseUrl, '/');
}

function buildAgentProjectLpUrl(string $agentCode, ?array $project = null): string {
    $url = getSiteBaseUrl() . '/a/' . rawurlencode($agentCode);
    if (!empty($project['slug'])) {
        $url .= '?project=' . rawurlencode((string)$project['slug']);
    }
    return $url;
}

function getProjectById(int $projectId): ?array {
    if ($projectId <= 0) {
        return null;
    }
    try {
        $stmt = getDB()->prepare("SELECT * FROM projects WHERE id=? LIMIT 1");
        $stmt->execute([$projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function getProjectBySlug(string $slug): ?array {
    $slug = trim($slug);
    if ($slug === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
        return null;
    }
    try {
        $stmt = getDB()->prepare("SELECT * FROM projects WHERE slug=? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function currentRequestUrl(): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($host === '') {
        return getSiteBaseUrl() . $uri;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . $uri;
}

function appendUrlQueryParams(string $url, array $params): string {
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    if (!$params) {
        return $url;
    }
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
}

function lpReferralFeaturesEnabled(): bool {
    $flags = getCommonIdFeatureFlags();
    return $flags['common_id_enabled'] && $flags['referral_token_api_enabled'];
}

function getLpProjectIdFromTemplate(?int $templateId): int {
    if (!$templateId || !tableHasColumn('lp_templates', 'project_id')) {
        return 0;
    }
    try {
        $stmt = getDB()->prepare("SELECT project_id FROM lp_templates WHERE id=? LIMIT 1");
        $stmt->execute([$templateId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function ensureLpReferralTokenForAgent(array $agent, int $projectId = 0): ?array {
    if (!lpReferralFeaturesEnabled() || !referralTokenTablesReady()) {
        return null;
    }
    $agentId = (int)($agent['id'] ?? 0);
    $agentCode = (string)($agent['agent_code'] ?? '');
    if ($agentId <= 0 || $agentCode === '' || $agentCode === 'preview') {
        return null;
    }
    $project = $projectId > 0 ? getProjectById($projectId) : null;
    try {
        return ensureReferralToken([
            'agent_id' => $agentId,
            'project_id' => $projectId,
            'token_type' => 'lp',
            'destination_service_key' => 'lp',
            'destination_url' => buildAgentProjectLpUrl($agentCode, $project),
            'metadata' => [
                'source' => 'lp',
                'agent_code' => $agentCode,
                'project_slug' => $project['slug'] ?? null,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('LP referral token error: ' . $e->getMessage());
        return null;
    }
}

function resolveLpReferralContext(array $agent, int $projectId = 0): array {
    $context = [
        'referral_token_id' => null,
        'referral_token' => '',
        'referral_session_key' => '',
        'referral_query' => '',
        'referral_hidden_fields' => '',
        'referral_lp_url' => '',
    ];
    if (!lpReferralFeaturesEnabled() || !referralTokenTablesReady()) {
        return $context;
    }

    $tokenRow = null;
    $requestToken = trim((string)($_GET['rt'] ?? $_GET['referral_token'] ?? ''));
    if ($requestToken !== '') {
        $validated = validateReferralToken($requestToken);
        if (!empty($validated['valid']) && !empty($validated['token'])) {
            $tokenRow = $validated['token'];
        }
    }
    if (!$tokenRow) {
        $tokenRow = ensureLpReferralTokenForAgent($agent, $projectId);
    }
    if (!$tokenRow) {
        return $context;
    }

    $sessionKey = trim((string)($_GET['rs'] ?? $_GET['referral_session_key'] ?? ''));
    if ($sessionKey === '') {
        $sessionKey = generateReferralSessionKey();
    }

    try {
        recordReferralSession([
            'token_row' => $tokenRow,
            'session_key' => $sessionKey,
            'event_type' => 'lp_view',
            'landing_url' => currentRequestUrl(),
            'referrer_url' => $_SERVER['HTTP_REFERER'] ?? null,
            'metadata' => ['source' => 'lp_view'],
        ]);
    } catch (Throwable $e) {
        error_log('LP referral session error: ' . $e->getMessage());
    }

    $query = http_build_query(['rt' => $tokenRow['token'], 'rs' => $sessionKey]);
    $project = $projectId > 0 ? getProjectById($projectId) : null;
    $baseLpUrl = buildAgentProjectLpUrl((string)($agent['agent_code'] ?? ''), $project);

    $context['referral_token_id'] = (int)$tokenRow['id'];
    $context['referral_token'] = (string)$tokenRow['token'];
    $context['referral_session_key'] = $sessionKey;
    $context['referral_query'] = $query;
    $context['referral_lp_url'] = appendUrlQueryParams($baseLpUrl, ['rt' => $tokenRow['token'], 'rs' => $sessionKey]);
    $context['referral_hidden_fields'] =
        '<input type="hidden" name="referral_token" value="' . h($tokenRow['token']) . '">' .
        '<input type="hidden" name="referral_session_key" value="' . h($sessionKey) . '">';

    return $context;
}

// =============================
// 繧｢繝峨ヰ繧､繧ｶ繝ｼ蜿門ｾ・// =============================
function getAgentByCode(string $code): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT a.*, t.html_file, t.slug AS template_slug, t.name AS template_name,
               t.project_id AS template_project_id
        FROM agents a
        LEFT JOIN lp_templates t ON a.default_template_id = t.id AND t.status = 'active'
        WHERE a.agent_code = ? AND a.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

function getAgentById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM agents WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// =============================
// 繝・Φ繝励Ξ繝ｼ繝亥叙蠕・// =============================
function getActiveTemplates(): array {
    $db = getDB();
    if (tableHasColumn('lp_templates', 'project_id')) {
        $stmt = $db->query("
            SELECT t.*, p.name AS project_name
            FROM lp_templates t
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.status = 'active'
            ORDER BY COALESCE(p.sort_order, 9999) ASC, t.sort_order ASC
        ");
        return $stmt->fetchAll();
    }
    $stmt = $db->query("SELECT * FROM lp_templates WHERE status = 'active' ORDER BY sort_order ASC");
    return $stmt->fetchAll();
}

function getTemplateById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM lp_templates WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getActiveTemplatesByProject(): array {
    $grouped = [];
    foreach (getActiveTemplates() as $template) {
        $projectId = (int)($template['project_id'] ?? 0);
        $grouped[$projectId][] = $template;
    }
    return $grouped;
}

function getAgentProjectTemplateMap(int $agentId): array {
    if ($agentId <= 0) return [];
    try {
        $db = getDB();
        $db->query("SELECT 1 FROM agent_project_templates LIMIT 1");
        $stmt = $db->prepare("SELECT project_id, template_id FROM agent_project_templates WHERE agent_id=?");
        $stmt->execute([$agentId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['project_id']] = (int)$row['template_id'];
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function getProjectTemplateForAgent(array $agent, int $projectId): ?array {
    if ($projectId <= 0) return null;
    $db = getDB();
    $agentId = (int)($agent['id'] ?? 0);
    $map = getAgentProjectTemplateMap($agentId);
    if (!empty($map[$projectId])) {
        $stmt = $db->prepare("SELECT * FROM lp_templates WHERE id=? AND project_id=? AND status='active' LIMIT 1");
        $stmt->execute([(int)$map[$projectId], $projectId]);
        $template = $stmt->fetch();
        if ($template) return $template;
    }
    if ((int)($agent['template_project_id'] ?? 0) === $projectId && !empty($agent['default_template_id'])) {
        $stmt = $db->prepare("SELECT * FROM lp_templates WHERE id=? AND project_id=? AND status='active' LIMIT 1");
        $stmt->execute([(int)$agent['default_template_id'], $projectId]);
        $template = $stmt->fetch();
        if ($template) return $template;
    }
    $stmt = $db->prepare("SELECT * FROM lp_templates WHERE project_id=? AND status='active' ORDER BY sort_order ASC, id ASC LIMIT 1");
    $stmt->execute([$projectId]);
    return $stmt->fetch() ?: null;
}

function getAgentProjectLpUrls(array $agent): array {
    $projects = getProjects(true);
    $urls = [];
    foreach ($projects as $project) {
        $urls[] = [
            'project' => $project,
            'url' => buildAgentProjectLpUrl((string)($agent['agent_code'] ?? ''), $project),
        ];
    }
    return $urls;
}

function getLpTemplateFields(int $templateId): array {
    if ($templateId <= 0) return [];
    $db = getDB();
    try {
        $db->query("SELECT 1 FROM lp_template_fields LIMIT 1");
    } catch (Throwable $e) {
        return [];
    }
    $stmt = $db->prepare("SELECT * FROM lp_template_fields WHERE template_id=?");
    $stmt->execute([$templateId]);
    $fields = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fields[$row['field_key']] = $row;
    }
    return $fields;
}

function getLpTemplateFieldValue(array $agent, string $key, string $default = ''): string {
    $templateId = (int)($agent['default_template_id'] ?? 0);
    $fields = getLpTemplateFields($templateId);
    if (empty($fields[$key])) {
        return $default;
    }
    $row = $fields[$key];
    $value = (string)($row['value_file'] ?: $row['value_text'] ?: '');
    return $value !== '' ? $value : $default;
}

function lpText(array $agent, string $key, string $default = ''): string {
    return h(getLpTemplateFieldValue($agent, $key, $default));
}

function lpImage(array $agent, string $key, string $default = ''): string {
    return h(getLpTemplateFieldValue($agent, $key, $default));
}

function lpResponsiveImage(array $agent, string $pcKey = 'hero_image_pc', string $spKey = 'hero_image_sp', string $alt = '', string $class = ''): string {
    $pc = getLpTemplateFieldValue($agent, $pcKey, getLpTemplateFieldValue($agent, 'hero_image', ''));
    $sp = getLpTemplateFieldValue($agent, $spKey, $pc);
    if ($pc === '' && $sp === '') {
        return '';
    }
    $img = '<picture>';
    if ($sp !== '') {
        $img .= '<source media="(max-width: 768px)" srcset="' . h($sp) . '">';
    }
    $img .= '<img src="' . h($pc ?: $sp) . '" alt="' . h($alt) . '"' . ($class !== '' ? ' class="' . h($class) . '"' : '') . '>';
    $img .= '</picture>';
    return $img;
}

function lpPlainText(string $value, int $maxLength = 160): string {
    $value = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: '');
    if ($maxLength > 0 && function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $maxLength) {
        return mb_substr($value, 0, $maxLength - 1, 'UTF-8') . '窶ｦ';
    }
    if ($maxLength > 0 && !function_exists('mb_strlen') && strlen($value) > $maxLength) {
        return substr($value, 0, $maxLength - 3) . '...';
    }
    return $value;
}

function lpAbsoluteUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('/^https?:\/\//i', $url)) return $url;
    if ($url[0] !== '/') $url = '/' . $url;
    return getSiteBaseUrl() . $url;
}

function getLpTemplateSeoSource(int $templateId): array {
    if ($templateId <= 0) return [];
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT t.*, p.slug AS project_slug, p.name AS project_name, p.description AS project_description
            FROM lp_templates t
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.id=?
            LIMIT 1
        ");
        $stmt->execute([$templateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function buildLpSeoMeta(array $agent, array $fields): array {
    $templateId = (int)($agent['default_template_id'] ?? 0);
    $template = getLpTemplateSeoSource($templateId);
    $templateName = lpPlainText((string)($template['name'] ?? $agent['template_name'] ?? ''), 70);
    $projectName = lpPlainText((string)($template['project_name'] ?? ''), 70);
    $heroTitle = lpPlainText((string)($fields['hero_title']['value_text'] ?? ''), 70);
    $seoTitle = lpPlainText((string)($fields['seo_title']['value_text'] ?? ''), 70);
    $title = $seoTitle ?: ($heroTitle ?: ($templateName ?: ($projectName ?: 'LP')));
    if ($projectName !== '' && stripos($title, $projectName) === false) {
        $title .= ' | ' . $projectName;
    }

    $description = lpPlainText((string)($fields['seo_description']['value_text'] ?? ''), 160);
    if ($description === '') {
        $description = lpPlainText((string)($fields['hero_body']['value_text'] ?? ''), 160);
    }
    if ($description === '') {
        $description = lpPlainText((string)($template['description'] ?? $template['project_description'] ?? ''), 160);
    }
    if ($description === '') {
        $description = $title . ' information page. Please check the details and contact us from LINE or the inquiry form.';
    }

    $agentCode = (string)($agent['agent_code'] ?? '');
    $project = [];
    if (!empty($template['project_slug'])) {
        $project = ['slug' => $template['project_slug']];
    }
    $canonical = $agentCode !== '' && $agentCode !== 'preview'
        ? buildAgentProjectLpUrl($agentCode, $project)
        : getSiteBaseUrl() . ($_SERVER['REQUEST_URI'] ?? '/');

    $image = '';
    foreach (['og_image', 'hero_image_pc', 'hero_image', 'background_image', 'hero_image_sp'] as $key) {
        if (!empty($fields[$key]['value_file'])) {
            $image = lpAbsoluteUrl((string)$fields[$key]['value_file']);
            break;
        }
        if (!empty($fields[$key]['value_text'])) {
            $image = lpAbsoluteUrl((string)$fields[$key]['value_text']);
            break;
        }
    }
    if ($image === '' && !empty($template['thumbnail_url'])) {
        $image = lpAbsoluteUrl((string)$template['thumbnail_url']);
    }

    return [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'image' => $image,
        'project_name' => $projectName,
        'template_name' => $templateName,
    ];
}

function injectLpSeoHead(string $html, array $agent, array $fields): string {
    $seo = buildLpSeoMeta($agent, $fields);
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $seo['title'],
        'description' => $seo['description'],
        'url' => $seo['canonical'],
        'inLanguage' => 'ja',
        'about' => [
            '@type' => 'Service',
            'name' => $seo['project_name'] ?: $seo['template_name'] ?: $seo['title'],
            'description' => $seo['description'],
        ],
        'potentialAction' => [
            '@type' => 'ContactAction',
            'target' => $seo['canonical'],
        ],
    ];
    if ($seo['image'] !== '') {
        $jsonLd['image'] = $seo['image'];
        $jsonLd['primaryImageOfPage'] = [
            '@type' => 'ImageObject',
            'url' => $seo['image'],
        ];
    }

    $head = "\n" .
        '<title>' . h($seo['title']) . "</title>\n" .
        '<meta name="description" content="' . h($seo['description']) . "\">\n" .
        '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">' . "\n" .
        '<link rel="canonical" href="' . h($seo['canonical']) . "\">\n" .
        '<meta property="og:type" content="website">' . "\n" .
        '<meta property="og:locale" content="ja_JP">' . "\n" .
        '<meta property="og:title" content="' . h($seo['title']) . "\">\n" .
        '<meta property="og:description" content="' . h($seo['description']) . "\">\n" .
        '<meta property="og:url" content="' . h($seo['canonical']) . "\">\n" .
        ($seo['image'] !== '' ? '<meta property="og:image" content="' . h($seo['image']) . "\">\n" : '') .
        '<meta name="twitter:card" content="' . ($seo['image'] !== '' ? 'summary_large_image' : 'summary') . "\">\n" .
        '<meta name="twitter:title" content="' . h($seo['title']) . "\">\n" .
        '<meta name="twitter:description" content="' . h($seo['description']) . "\">\n" .
        ($seo['image'] !== '' ? '<meta name="twitter:image" content="' . h($seo['image']) . "\">\n" : '') .
        '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";

    $patterns = [
        '/<title\b[^>]*>.*?<\/title>\s*/is',
        '/<meta\s+name=["\']description["\'][^>]*>\s*/i',
        '/<meta\s+name=["\']robots["\'][^>]*>\s*/i',
        '/<link\s+rel=["\']canonical["\'][^>]*>\s*/i',
        '/<meta\s+property=["\']og:[^"\']+["\'][^>]*>\s*/i',
        '/<meta\s+name=["\']twitter:[^"\']+["\'][^>]*>\s*/i',
        '/<script\s+type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>\s*/is',
    ];
    $html = preg_replace($patterns, '', $html);
    $html = preg_replace('/(<head\b[^>]*>)/i', '$1' . $head, $html, 1, $count);
    return $count ? $html : $head . $html;
}

function applyLpTemplateTokens(string $html, array $agent): string {
    $templateId = (int)($agent['default_template_id'] ?? 0);
    $fields = getLpTemplateFields($templateId);
    if (!isset($fields['hero_image_pc']) && isset($fields['hero_image'])) {
        $fields['hero_image_pc'] = $fields['hero_image'];
    }
    if (!isset($fields['hero_image_sp']) && isset($fields['hero_image_pc'])) {
        $fields['hero_image_sp'] = $fields['hero_image_pc'];
    }
    $replacements = [];
    foreach ($fields as $key => $row) {
        $value = (string)($row['value_file'] ?: $row['value_text'] ?: '');
        $replacements['{{' . $key . '}}'] = h($value);
    }
    $replacements['{{hero_picture}}'] = lpResponsiveImage($agent, 'hero_image_pc', 'hero_image_sp', (string)($agent['template_name'] ?? ''));
    $replacements['{{referral_token}}'] = h((string)($agent['referral_token'] ?? ''));
    $replacements['{{referral_session_key}}'] = h((string)($agent['referral_session_key'] ?? ''));
    $replacements['{{referral_query}}'] = h((string)($agent['referral_query'] ?? ''));
    $replacements['{{referral_lp_url}}'] = h((string)($agent['referral_lp_url'] ?? ''));
    $replacements['{{referral_hidden_fields}}'] = (string)($agent['referral_hidden_fields'] ?? '');
    $html = strtr($html, $replacements);

    if (!empty($agent['referral_hidden_fields']) && stripos($html, 'name="referral_token"') === false) {
        $html = preg_replace('/<\/form>/i', (string)$agent['referral_hidden_fields'] . '</form>', $html);
    }

    if (!empty($agent['referral_token']) && !empty($agent['referral_session_key'])) {
        $html = preg_replace_callback(
            '/href=(["\'])(\/line_click\.php\?[^"\']*)\1/i',
            static function ($matches) use ($agent) {
                $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = appendUrlQueryParams($url, [
                    'rt' => (string)$agent['referral_token'],
                    'rs' => (string)$agent['referral_session_key'],
                ]);
                return 'href=' . $matches[1] . h($url) . $matches[1];
            },
            $html
        );
    }

    return injectLpSeoHead($html, $agent, $fields);
}

// =============================
// 繧｢繧ｯ繧ｻ繧ｹ繝ｭ繧ｰ
// =============================
function logAccess(int $agentId, string $type = 'pv', ?int $templateId = null, array $context = []): void {
    try {
        $db = getDB();
        $cols = tableColumns('access_logs');
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $projectId = null;
        if (!empty($cols['project_id']) && $templateId && tableHasColumn('lp_templates', 'project_id')) {
            $projectStmt = $db->prepare("SELECT project_id FROM lp_templates WHERE id=?");
            $projectStmt->execute([$templateId]);
            $projectId = (int)$projectStmt->fetchColumn() ?: null;
        }

        $insertColumns = ['agent_id', 'type', 'ip_hash', 'user_agent'];
        $insertValues = [$agentId, $type, $ipHash, $ua];
        if (!empty($cols['template_id'])) {
            $insertColumns[] = 'template_id';
            $insertValues[] = $templateId ?: null;
        }
        if (!empty($cols['project_id'])) {
            $insertColumns[] = 'project_id';
            $insertValues[] = $projectId;
        }
        if (!empty($cols['referral_token_id'])) {
            $insertColumns[] = 'referral_token_id';
            $insertValues[] = !empty($context['referral_token_id']) ? (int)$context['referral_token_id'] : null;
        }
        if (!empty($cols['referral_session_key'])) {
            $insertColumns[] = 'referral_session_key';
            $insertValues[] = trim((string)($context['referral_session_key'] ?? '')) ?: null;
        }

        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $stmt = $db->prepare("INSERT INTO access_logs (" . implode(',', $insertColumns) . ") VALUES ($placeholders)");
        $stmt->execute($insertValues);
    } catch (Exception $e) {
        error_log('Access log error: ' . $e->getMessage());
    }
}

// =============================
// 騾夂衍繧ｯ繝ｩ繧ｹ
// =============================
class Notifier {
    private array $agent;
    private array $lead;

    public function __construct(array $agent, array $lead) {
        $this->agent = $agent;
        $this->lead  = $lead;
    }

    public function send(): array {
        $results = [];

        if ($this->agent['notify_email']) {
            $results['email'] = $this->sendEmail();
        }
        if ($this->agent['notify_line'] && $this->agent['line_messaging_token'] && $this->agent['line_user_id']) {
            $results['line'] = $this->sendLine();
        }
        if ($this->agent['notify_chatwork'] && $this->agent['chatwork_webhook']) {
            $results['chatwork'] = $this->sendChatwork();
        }
        if ($this->agent['notify_slack'] && $this->agent['slack_webhook']) {
            $results['slack'] = $this->sendSlack();
        }

        return $results;
    }

    private function buildMessage(): string {
        $a = $this->agent;
        $l = $this->lead;
        $sourceName = $l['source_agent_name'] ?? $a['agent_name'];
        $sourceCode = $l['source_agent_code'] ?? ($a['agent_code'] ?? '');
        $lines = [
            "[Sengoku] New lead received",
            "------------------------------",
            "Customer",
            "Name: {$l['name']}",
            "Email: {$l['email']}",
            "Phone: " . ($l['phone'] ?: 'N/A'),
            "Message",
            $l['message'],
            "------------------------------",
            "Received: " . date('Y-m-d H:i'),
            "Source LP: {$sourceName}" . ($sourceCode ? " (/a/{$sourceCode})" : ""),
            "Notify to: {$a['agent_name']}",
        ];
        return implode("\n", $lines);
    }
    private function sendEmail(): bool {
        $to      = $this->agent['email'];
        $subject = "[Sengoku] New lead - " . $this->lead['name'];
        $body    = $this->buildMessage();
        $headers = implode("\r\n", [
            'From: noreply@' . ($_SERVER['HTTP_HOST'] ?? 'sengoku.example.com'),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . PHP_VERSION,
        ]);
        $result = @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B'), $body, $headers);
        if (!$result) {
            error_log("Email send failed to: $to");
        }
        return $result;
    }

    private function sendLine(): bool {
        // LINE Messaging API (Push Message)
        $body = json_encode([
            'to' => $this->agent['line_user_id'],
            'messages' => [[
                'type' => 'text',
                'text' => $this->buildMessage(),
            ]],
        ]);
        return $this->postJson(
            'https://api.line.me/v2/bot/message/push',
            $body,
            ['Authorization: Bearer ' . $this->agent['line_messaging_token']]
        );
    }

    private function sendChatwork(): bool {
        $msg = urlencode($this->buildMessage());
        $ch = curl_init($this->agent['chatwork_webhook']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => "payload=" . $msg,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $result = curl_exec($ch);
        $ok = ($result !== false);
        curl_close($ch);
        return $ok;
    }

    private function sendSlack(): bool {
        $body = json_encode(['text' => $this->buildMessage()]);
        return $this->postJson($this->agent['slack_webhook'], $body);
    }

    private function postJson(string $url, string $body, array $headers = []): bool {
        $defaultHeaders = ['Content-Type: application/json'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $result = curl_exec($ch);
        $ok = ($result !== false);
        if (!$ok) {
            error_log("postJson failed to $url: " . curl_error($ch));
        }
        curl_close($ch);
        return $ok;
    }
}

// =============================
// CSRF繝医・繧ｯ繝ｳ
// =============================
function getCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    startSecureSession();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// =============================
// 繝壹・繧ｸ繝阪・繧ｷ繝ｧ繝ｳ
// =============================
function paginate(int $total, int $perPage, int $current): array {
    $totalPages = (int)ceil($total / $perPage);
    $offset     = ($current - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $current,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

// =============================
// 竭｡ 繝悶Ν繝ｼ繝医ヵ繧ｩ繝ｼ繧ｹ蟇ｾ遲・// =============================
function checkLoginThrottle(string $ipHash, string $userType, int $maxAttempts = 5, int $windowMinutes = 15): bool {
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_hash=? AND user_type=?
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ipHash, $userType, $windowMinutes]);
        return (int)$stmt->fetchColumn() >= $maxAttempts;
    } catch (Exception $e) { return false; }
}

function recordLoginAttempt(string $ipHash, string $userType): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO login_attempts (ip_hash, user_type) VALUES (?,?)")
           ->execute([$ipHash, $userType]);
        $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")
           ->execute();
    } catch (Exception $e) {}
}
function clearLoginAttempts(string $ipHash, string $userType): void {
    try {
        $db = getDB();
        $db->prepare("DELETE FROM login_attempts WHERE ip_hash=? AND user_type=?")
           ->execute([$ipHash, $userType]);
    } catch (Exception $e) {}
}

// 竭ｨ 繝ｭ繧ｰ繧､繝ｳ螻･豁ｴ險倬鹸
function recordLoginLog(string $userType, ?int $userId, string $email, bool $success): void {
    try {
        $db     = getDB();
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $db->prepare("INSERT INTO login_logs (user_type, user_id, email, ip_hash, success) VALUES (?,?,?,?,?)")
           ->execute([$userType, $userId, $email, $ipHash, $success ? 1 : 0]);
    } catch (Exception $e) {}
}

// =============================
// 繧ｨ繝ｼ繧ｸ繧ｧ繝ｳ繝磯嚴螻､
// =============================
function getAgentChildren(int $parentId): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT a.*, t.name AS template_name, t.html_file, t.slug AS template_slug
        FROM agents a
        LEFT JOIN lp_templates t ON a.default_template_id = t.id
        WHERE a.parent_id = ? ORDER BY a.created_at DESC
    ");
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

function isAgent(array $agent): bool {
    return (int)($agent['level'] ?? 1) >= 2;
}

function getLevelLabel(int $level): string {
    $labels = getLevelLabels();
    return $labels[$level] ?? ('Lv.' . $level);
}

// =============================
// 髫主ｱ､蜷咲ｧｰ
// =============================
function getLevelLabels(): array {
    try {
        $db   = getDB();
        $rows = $db->query("SELECT key_name, value FROM system_settings WHERE key_name IN ('label_level1','label_level2','label_level3')")->fetchAll();
        $map  = [];
        foreach ($rows as $r) $map[$r['key_name']] = $r['value'];
        return [
            1 => $map['label_level1'] ?? 'Advisor',
            2 => $map['label_level2'] ?? 'Director',
            3 => $map['label_level3'] ?? 'Agent',
        ];
    } catch (Exception $e) {
        return [
            1 => 'Advisor',
            2 => 'Director',
            3 => 'Agent',
        ];
    }
}
function getAdvisorPositionLabels(): array {
    $defaults = [
        'advisor' => 'Advisor',
        'super_advisor' => 'Super Advisor',
        'influencer' => 'Influencer',
    ];

    try {
        $db = getDB();
        $rows = $db->query("
            SELECT key_name, value
            FROM system_settings
            WHERE key_name IN (
                'label_position_advisor',
                'label_position_super_advisor',
                'label_position_influencer'
            )
        ")->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['key_name']] = trim((string)$row['value']);
        }

        return [
            'advisor' => !empty($map['label_position_advisor']) ? $map['label_position_advisor'] : $defaults['advisor'],
            'super_advisor' => !empty($map['label_position_super_advisor']) ? $map['label_position_super_advisor'] : $defaults['super_advisor'],
            'influencer' => !empty($map['label_position_influencer']) ? $map['label_position_influencer'] : $defaults['influencer'],
        ];
    } catch (Exception $e) {
        return $defaults;
    }
}
function normalizeAdvisorPosition(string $position): string {
    $position = trim($position);
    return array_key_exists($position, getAdvisorPositionLabels()) ? $position : 'advisor';
}

function getAdvisorPositionLabel(?string $positionType, ?string $positionLabel = null): string {
    if ($positionLabel) {
        return $positionLabel;
    }
    $labels = getAdvisorPositionLabels();
    $key = normalizeAdvisorPosition((string)$positionType);
    return $labels[$key] ?? $labels['advisor'];
}

// =============================
// 3髫主ｱ､蟇ｾ蠢・// =============================
function getAllDescendants(int $agentId): array {
    $db     = getDB();
    $result = [];
    $queue  = [$agentId];
    while ($queue) {
        $pid  = array_shift($queue);
        $stmt = $db->prepare("SELECT * FROM agents WHERE parent_id=?");
        $stmt->execute([$pid]);
        $children = $stmt->fetchAll();
        foreach ($children as $c) {
            $result[] = $c;
            $queue[]  = $c['id'];
        }
    }
    return $result;
}
function getPendingPromotionCount(int $agentId): int {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM promotion_requests WHERE approver_id=? AND status='pending'");
        $stmt->execute([$agentId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

function getSystemSettingValue(string $key, string $default = ''): string {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT value FROM system_settings WHERE key_name=? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}

function getExternalPartnerSyncSettings(): array {
    return [
        'enabled' => getSystemSettingValue('external_partner_sync_enabled', '0') === '1',
        'base_url' => rtrim(trim(getSystemSettingValue('external_partner_base_url', '')), '/'),
        'api_key' => trim(getSystemSettingValue('external_partner_api_key', '')),
    ];
}

function getExternalPartnerSites(bool $activeOnly = true): array {
    $db = getDB();
    if (empty(tableColumns('external_partner_sites'))) {
        $legacy = getExternalPartnerSyncSettings();
        if (!$legacy['enabled'] || $legacy['base_url'] === '' || $legacy['api_key'] === '') {
            return [];
        }
        return [[
            'id' => 0,
            'site_key' => 'legacy',
            'name' => 'Legacy external partner',
            'base_url' => $legacy['base_url'],
            'api_key' => $legacy['api_key'],
            'status' => 'active',
            'sort_order' => 0,
        ]];
    }

    $where = $activeOnly ? "WHERE status='active'" : '';
    return $db->query("SELECT * FROM external_partner_sites $where ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function getExternalPartnerSiteById(int $id): ?array {
    if ($id <= 0 || empty(tableColumns('external_partner_sites'))) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM external_partner_sites WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function buildExternalPartnerEndpoint(string $url): string {
    $url = rtrim(trim($url), '/');
    if ($url === '') {
        return '';
    }
    if (preg_match('#/api/integrations/agencies$#', $url) === 1) {
        return $url;
    }
    return $url . '/api/integrations/agencies';
}

function buildExternalPartnerAgencyPayload(array $agent, string $event = 'upsert'): array {
    $db = getDB();
    $parentCode = null;
    if (!empty($agent['parent_id'])) {
        $stmt = $db->prepare("SELECT agent_code FROM agents WHERE id=? LIMIT 1");
        $stmt->execute([(int)$agent['parent_id']]);
        $parentCode = $stmt->fetchColumn() ?: null;
    }

    $lpUrls = [];
    try {
        foreach (getAgentProjectLpUrls($agent) as $row) {
            $project = $row['project'] ?? [];
            $lpUrls[] = [
                'project_slug' => (string)($project['slug'] ?? ''),
                'project_name' => (string)($project['name'] ?? ''),
                'url' => (string)($row['url'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $lpUrls[] = [
            'project_slug' => '',
            'project_name' => 'Default',
            'url' => buildAgentProjectLpUrl((string)($agent['agent_code'] ?? ''), null),
        ];
    }

    $level = (int)($agent['level'] ?? 1);
    $status = (string)($agent['status'] ?? 'active');
    if (strpos($event, 'delete') !== false) {
        $status = 'inactive';
    }

    return [
        'event' => $event,
        'source' => 'sengoku-ai',
        'source_agent_id' => (int)($agent['id'] ?? 0),
        'internal_agent_id' => (int)($agent['id'] ?? 0),
        'agency_id' => (string)($agent['agent_code'] ?? ''),
        'external_id' => (string)($agent['agent_code'] ?? ''),
        'parent_agency_id' => $parentCode,
        'parent_external_id' => $parentCode,
        'name' => (string)($agent['agent_name'] ?? ''),
        'contact_name' => (string)($agent['person_name'] ?? ''),
        'contact_email' => (string)($agent['email'] ?? ''),
        'login_email' => (string)($agent['email'] ?? ''),
        'phone' => (string)($agent['phone'] ?? ''),
        'line_url' => (string)($agent['line_url'] ?? ''),
        'status' => $status === 'active' ? 'active' : 'inactive',
        'role_level' => $level,
        'role_label' => getLevelLabel($level),
        'position_type' => (string)($agent['position_type'] ?? ''),
        'position_label' => getAdvisorPositionLabel($agent['position_type'] ?? null, $agent['position_label'] ?? null),
        'lp_urls' => $lpUrls,
        'sso_urls' => buildSsoLaunchUrlPayload(),
        'updated_at' => date('c'),
    ];
}

function buildExternalPartnerHmacHeaders(string $body, array $options = []): array {
    $secret = trim((string)($options['hmac_secret'] ?? ''));
    if ($secret === '') {
        return [];
    }
    $timestamp = (string)time();
    $nonce = bin2hex(random_bytes(12));
    $keyId = trim((string)($options['hmac_key_id'] ?? $options['site_key'] ?? ''));
    $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);
    return [
        'X-SenNoKuni-Key-Id: ' . $keyId,
        'X-SenNoKuni-Timestamp: ' . $timestamp,
        'X-SenNoKuni-Nonce: ' . $nonce,
        'X-SenNoKuni-Signature: sha256=' . $signature,
    ];
}

function normalizeExternalPartnerEventPayload(string $eventType, array $payload, array $options = []): array {
    $payload['event'] = $payload['event'] ?? $eventType;
    $payload['event_type'] = $payload['event_type'] ?? $eventType;
    $payload['event_version'] = $payload['event_version'] ?? '1.0';
    $payload['event_id'] = $payload['event_id'] ?? ('evt_' . bin2hex(random_bytes(16)));
    $payload['source_system_key'] = $payload['source_system_key'] ?? 'agency-system';
    $payload['source'] = $payload['source'] ?? 'sengoku-ai';
    $payload['correlation_id'] = $payload['correlation_id'] ?? ($options['correlation_id'] ?? ('corr_' . bin2hex(random_bytes(12))));
    $payload['occurred_at'] = $payload['occurred_at'] ?? date('c');
    $payload['updated_at'] = $payload['updated_at'] ?? date('c');
    return $payload;
}

function recordIntegrationOutboxEvent(array $data): void {
    if (empty(tableColumns('integration_outbox_events'))) {
        return;
    }
    try {
        $payloadJson = is_string($data['payload_json'] ?? null)
            ? (string)$data['payload_json']
            : json_encode($data['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadJson = $payloadJson ?: '{}';
        $eventId = trim((string)($data['event_id'] ?? '')) ?: ('evt_' . bin2hex(random_bytes(16)));
        $stmt = getDB()->prepare("
            INSERT INTO integration_outbox_events
                (event_id, event_type, event_version, source_system_key, target_site_key, endpoint, payload_json, payload_hash,
                 hmac_key_id, status, attempt_count, max_attempts, next_attempt_at, last_attempt_at, last_error, correlation_id, idempotency_key, processed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status=VALUES(status),
                attempt_count=VALUES(attempt_count),
                last_attempt_at=VALUES(last_attempt_at),
                last_error=VALUES(last_error),
                processed_at=VALUES(processed_at),
                updated_at=NOW()
        ");
        $stmt->execute([
            $eventId,
            trim((string)($data['event_type'] ?? 'unknown')),
            trim((string)($data['event_version'] ?? '1.0')),
            trim((string)($data['source_system_key'] ?? 'agency-system')),
            trim((string)($data['target_site_key'] ?? '')) ?: null,
            trim((string)($data['endpoint'] ?? '')) ?: null,
            $payloadJson,
            hash('sha256', $payloadJson),
            trim((string)($data['hmac_key_id'] ?? '')) ?: null,
            trim((string)($data['status'] ?? 'pending')) ?: 'pending',
            (int)($data['attempt_count'] ?? 0),
            (int)($data['max_attempts'] ?? 8),
            $data['next_attempt_at'] ?? null,
            $data['last_attempt_at'] ?? null,
            trim((string)($data['last_error'] ?? '')) ?: null,
            trim((string)($data['correlation_id'] ?? '')) ?: null,
            trim((string)($data['idempotency_key'] ?? '')) ?: null,
            $data['processed_at'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Integration outbox record failed: ' . $e->getMessage());
    }
}

function recordIntegrationEventAttempt(array $data): void {
    if (empty(tableColumns('integration_event_attempts'))) {
        return;
    }
    try {
        $stmt = getDB()->prepare("
            INSERT INTO integration_event_attempts
                (event_id, site_key, endpoint, http_status, success, request_headers_json, response_body, error_message, attempted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $headersJson = json_encode(maskIntegrationPayloadForLog($data['headers'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->execute([
            trim((string)($data['event_id'] ?? '')) ?: null,
            trim((string)($data['site_key'] ?? '')) ?: null,
            trim((string)($data['endpoint'] ?? '')) ?: null,
            isset($data['http_status']) ? (int)$data['http_status'] : null,
            !empty($data['success']) ? 1 : 0,
            $headersJson ?: null,
            encodeMaskedIntegrationBodyForLog($data['response_body'] ?? null),
            trim((string)($data['error_message'] ?? '')) ?: null,
        ]);
    } catch (Throwable $e) {
        error_log('Integration event attempt record failed: ' . $e->getMessage());
    }
}

function postJsonToExternalPartnerWithResult(string $endpoint, string $apiKey, array $payload, array $options = []): array {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        error_log('External agency sync failed: json_encode failed');
        return [
            'ok' => false,
            'status' => 0,
            'endpoint' => $endpoint,
            'error' => 'json_encode failed',
            'response' => '',
        ];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-api-key: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
    ];
    if (!empty($payload['event_version'])) {
        $headers[] = 'X-Event-Version: ' . (string)$payload['event_version'];
    }
    if (!empty($payload['correlation_id'])) {
        $headers[] = 'X-Correlation-Id: ' . (string)$payload['correlation_id'];
    }
    $idempotencyKey = trim((string)($options['idempotency_key'] ?? $payload['event_id'] ?? ''));
    if ($idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    $headers = array_merge($headers, buildExternalPartnerHmacHeaders($body, $options));

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $ok = $response !== false && $status >= 200 && $status < 300;
        if ($response === false || $status < 200 || $status >= 300) {
            error_log('External agency sync failed: HTTP ' . $status . ' ' . $error . ' ' . (string)$response);
        }
        return [
            'ok' => $ok,
            'status' => $status,
            'endpoint' => $endpoint,
            'error' => $error,
            'response' => substr((string)$response, 0, 1000),
            'request_headers' => $headers,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($endpoint, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    $ok = preg_match('/\s2\d\d\s/', $statusLine) === 1;
    if (!$ok) {
        error_log('External agency sync failed: ' . $statusLine . ' ' . (string)$response);
    }
    $status = 0;
    if (preg_match('/\s(\d{3})\s/', $statusLine, $m) === 1) {
        $status = (int)$m[1];
    }
    return [
        'ok' => $ok,
        'status' => $status,
        'endpoint' => $endpoint,
        'error' => $ok ? '' : $statusLine,
        'response' => substr((string)$response, 0, 1000),
        'request_headers' => $headers,
    ];
}

function postJsonToExternalPartner(string $endpoint, string $apiKey, array $payload): bool {
    $result = postJsonToExternalPartnerWithResult($endpoint, $apiKey, $payload);
    return !empty($result['ok']);
}

function findExternalPartnerSiteByKey(?string $siteKey): ?array {
    $siteKey = trim((string)$siteKey);
    if ($siteKey === '') {
        return null;
    }
    foreach (getExternalPartnerSites(false) as $site) {
        if ((string)($site['site_key'] ?? '') === $siteKey) {
            return $site;
        }
    }
    return null;
}

function retryExternalIntegrationLogRow(array $log): array {
    $endpoint = trim((string)($log['endpoint'] ?? ''));
    if ($endpoint === '') {
        throw new RuntimeException('The endpoint is missing, so this log cannot be retried.');
    }
    $payload = json_decode((string)($log['request_body'] ?? ''), true);
    if (!is_array($payload)) {
        throw new RuntimeException('The saved request JSON could not be parsed.');
    }
    $site = findExternalPartnerSiteByKey($log['site_key'] ?? null);
    $apiKey = trim((string)($site['api_key'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('The partner API key is missing.');
    }

    $payload['_retry'] = [
        'source_log_id' => (int)($log['id'] ?? 0),
        'retried_at' => date('c'),
    ];
    $result = postJsonToExternalPartnerWithResult($endpoint, $apiKey, $payload, [
        'site_key' => (string)($log['site_key'] ?? ''),
        'hmac_secret' => (getSystemSettingValue('external_partner_hmac_enabled', '1') === '1') ? trim((string)($site['hmac_secret'] ?? '')) : '',
        'hmac_key_id' => trim((string)($site['hmac_key_id'] ?? $log['site_key'] ?? '')),
        'idempotency_key' => (string)($payload['event_id'] ?? ('retry_' . (int)($log['id'] ?? 0))),
    ]);
    logIntegrationEvent([
        'direction' => 'outbound',
        'site_key' => $log['site_key'] ?? null,
        'event_type' => (string)($log['event_type'] ?? 'retry'),
        'endpoint' => $endpoint,
        'http_status' => $result['status'] ?? null,
        'success' => !empty($result['ok']),
        'common_user_id' => $log['common_user_id'] ?? null,
        'agent_id' => $log['agent_id'] ?? null,
        'request_body' => $payload,
        'response_body' => $result['response'] ?? '',
        'error_message' => $result['error'] ?? '',
    ]);
    return $result;
}

function retryFailedExternalIntegrationLogs(string $siteKey = '', int $limit = 10): array {
    $summary = [
        'target_count' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'errors' => [],
    ];
    if (empty(tableColumns('integration_event_logs'))) {
        $summary['errors'][] = 'integration_event_logs table is not ready.';
        return $summary;
    }

    $limit = min(50, max(1, $limit));
    $where = "direction='outbound' AND success=0 AND endpoint IS NOT NULL AND endpoint<>'' AND request_body IS NOT NULL AND request_body<>''";
    $params = [];
    $siteKey = trim($siteKey);
    if ($siteKey !== '') {
        $where .= " AND site_key=?";
        $params[] = $siteKey;
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT *
        FROM integration_event_logs
        WHERE $where
        ORDER BY created_at ASC, id ASC
        LIMIT $limit
    ");
    $stmt->execute($params);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $summary['target_count'] = count($targets);

    foreach ($targets as $log) {
        try {
            $result = retryExternalIntegrationLogRow($log);
            if (!empty($result['ok'])) {
                $summary['success_count']++;
            } else {
                $summary['failed_count']++;
                $summary['errors'][] = 'Log #' . (int)($log['id'] ?? 0) . ': HTTP ' . (int)($result['status'] ?? 0) . ' ' . (string)($result['error'] ?? '');
            }
        } catch (Throwable $e) {
            $summary['failed_count']++;
            $summary['errors'][] = 'Log #' . (int)($log['id'] ?? 0) . ': ' . $e->getMessage();
            logIntegrationEvent([
                'direction' => 'outbound',
                'site_key' => $log['site_key'] ?? null,
                'event_type' => (string)($log['event_type'] ?? 'retry'),
                'endpoint' => $log['endpoint'] ?? null,
                'http_status' => null,
                'success' => false,
                'common_user_id' => $log['common_user_id'] ?? null,
                'agent_id' => $log['agent_id'] ?? null,
                'request_body' => $log['request_body'] ?? '',
                'response_body' => '',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    return $summary;
}

function dispatchExternalPartnerEvent(string $eventType, array $payload, array $options = []): array {
    $sites = getExternalPartnerSites(true);
    $results = [];
    if (!$sites) {
        return $results;
    }

    $payload = normalizeExternalPartnerEventPayload($eventType, $payload, $options);
    $agentId = isset($options['agent_id']) ? (int)$options['agent_id'] : (isset($payload['source_agent_id']) ? (int)$payload['source_agent_id'] : null);
    $commonUserId = trim((string)($options['common_user_id'] ?? $payload['common_user_id'] ?? '')) ?: null;

    foreach ($sites as $site) {
        $endpoint = buildExternalPartnerEndpoint((string)($site['base_url'] ?? ''));
        $apiKey = trim((string)($site['api_key'] ?? ''));
        $siteKey = (string)($site['site_key'] ?? $site['id'] ?? 'unknown');
        if ($endpoint === '' || $apiKey === '') {
            continue;
        }
        $sendOptions = [
            'site_key' => $siteKey,
            'hmac_secret' => (getSystemSettingValue('external_partner_hmac_enabled', '1') === '1') ? trim((string)($site['hmac_secret'] ?? '')) : '',
            'hmac_key_id' => trim((string)($site['hmac_key_id'] ?? $siteKey)),
            'idempotency_key' => (string)($payload['event_id'] ?? ''),
            'correlation_id' => (string)($payload['correlation_id'] ?? ''),
        ];
        $result = postJsonToExternalPartnerWithResult($endpoint, $apiKey, $payload, $sendOptions);
        $result['site_key'] = $siteKey;
        $results[] = $result;

        recordIntegrationOutboxEvent([
            'event_id' => substr((string)($payload['event_id'] ?? '') . ':' . $siteKey, 0, 100),
            'event_type' => $eventType,
            'event_version' => (string)($payload['event_version'] ?? '1.0'),
            'source_system_key' => (string)($payload['source_system_key'] ?? 'agency-system'),
            'target_site_key' => $siteKey,
            'endpoint' => $endpoint,
            'payload' => $payload,
            'hmac_key_id' => $sendOptions['hmac_key_id'],
            'status' => !empty($result['ok']) ? 'succeeded' : 'failed',
            'attempt_count' => 1,
            'next_attempt_at' => !empty($result['ok']) ? null : date('Y-m-d H:i:s', strtotime('+5 minutes')),
            'last_attempt_at' => date('Y-m-d H:i:s'),
            'last_error' => $result['error'] ?? '',
            'correlation_id' => (string)($payload['correlation_id'] ?? ''),
            'idempotency_key' => (string)($payload['event_id'] ?? ''),
            'processed_at' => !empty($result['ok']) ? date('Y-m-d H:i:s') : null,
        ]);
        recordIntegrationEventAttempt([
            'event_id' => (string)($payload['event_id'] ?? ''),
            'site_key' => $siteKey,
            'endpoint' => $endpoint,
            'http_status' => $result['status'] ?? null,
            'success' => !empty($result['ok']),
            'headers' => $result['request_headers'] ?? [],
            'response_body' => $result['response'] ?? '',
            'error_message' => $result['error'] ?? '',
        ]);

        logIntegrationEvent([
            'direction' => 'outbound',
            'site_key' => $siteKey,
            'event_type' => $eventType,
            'endpoint' => $endpoint,
            'http_status' => $result['status'] ?? null,
            'success' => !empty($result['ok']),
            'common_user_id' => $commonUserId,
            'agent_id' => $agentId,
            'request_body' => $payload,
            'response_body' => $result['response'] ?? '',
            'error_message' => $result['error'] ?? '',
        ]);
    }

    return $results;
}

function externalPartnerResultsAllOk(array $results): bool {
    foreach ($results as $result) {
        if (empty($result['ok'])) {
            return false;
        }
    }
    return true;
}

function testExternalPartnerConnection(): array {
    $settings = getExternalPartnerSyncSettings();
    if ($settings['base_url'] === '') {
        return [
            'ok' => false,
            'status' => 0,
            'endpoint' => '',
            'error' => '送信先URLが未設定です。',
            'response' => '',
        ];
    }
    if ($settings['api_key'] === '') {
        return [
            'ok' => false,
            'status' => 0,
            'endpoint' => buildExternalPartnerEndpoint($settings['base_url']),
            'error' => 'sengoku-rr.com 受信用APIキーが未設定です。',
            'response' => '',
        ];
    }

    $endpoint = buildExternalPartnerEndpoint($settings['base_url']);
    $payload = [
        'event' => 'connection_test',
        'dry_run' => true,
        'source' => 'sengoku-ai',
        'external_id' => '__connection_test__',
        'parent_external_id' => null,
        'name' => '接続テスト',
        'contact_name' => '接続テスト',
        'contact_email' => '',
        'login_email' => '',
        'phone' => '',
        'line_url' => '',
        'status' => 'inactive',
        'role_level' => 1,
        'role_label' => '接続テスト',
        'position_type' => '',
        'position_label' => '',
        'lp_urls' => [],
        'updated_at' => date('c'),
    ];

    return postJsonToExternalPartnerWithResult($endpoint, $settings['api_key'], $payload);
}

function syncAgentArrayToExternalPartner(array $agent, string $event = 'upsert'): bool {
    try {
        $settings = getExternalPartnerSyncSettings();
        if (!$settings['enabled'] || $settings['base_url'] === '' || $settings['api_key'] === '') {
            return true;
        }
        if (empty($agent['agent_code'])) {
            return false;
        }
        $endpoint = buildExternalPartnerEndpoint($settings['base_url']);
        if ($endpoint === '') {
            return true;
        }
        return postJsonToExternalPartner($endpoint, $settings['api_key'], buildExternalPartnerAgencyPayload($agent, $event));
    } catch (Throwable $e) {
        error_log('External agency sync exception: ' . $e->getMessage());
        return false;
    }
}

function testExternalPartnerSiteConnection(int $siteId): array {
    $site = getExternalPartnerSiteById($siteId);
    if (!$site) {
        return [
            'ok' => false,
            'status' => 0,
            'endpoint' => '',
            'error' => '連携先サイトが見つかりません。',
            'response' => '',
            'site_id' => $siteId,
            'site_name' => '',
        ];
    }
    $baseUrl = rtrim(trim((string)($site['base_url'] ?? '')), '/');
    $apiKey = trim((string)($site['api_key'] ?? ''));
    if ($baseUrl === '' || $apiKey === '') {
        return [
            'ok' => false,
            'status' => 0,
            'endpoint' => $baseUrl !== '' ? buildExternalPartnerEndpoint($baseUrl) : '',
            'error' => '送信先URLまたはAPIキーが未設定です。',
            'response' => '',
            'site_id' => $siteId,
            'site_name' => (string)($site['name'] ?? ''),
        ];
    }

    $payload = [
        'event' => 'connection_test',
        'dry_run' => true,
        'source' => 'sengoku-ai',
        'external_id' => '__connection_test__',
        'parent_external_id' => null,
        'name' => '接続テスト',
        'contact_name' => '接続テスト',
        'contact_email' => '',
        'login_email' => '',
        'phone' => '',
        'line_url' => '',
        'status' => 'inactive',
        'role_level' => 1,
        'role_label' => '接続テスト',
        'position_type' => '',
        'position_label' => '',
        'lp_urls' => [],
        'sso_urls' => buildSsoLaunchUrlPayload(),
        'updated_at' => date('c'),
    ];
    $result = postJsonToExternalPartnerWithResult(buildExternalPartnerEndpoint($baseUrl), $apiKey, $payload);
    $result['site_id'] = $siteId;
    $result['site_name'] = (string)($site['name'] ?? '');
    return $result;
}

function syncAgentArrayToExternalPartnerSites(array $agent, string $event = 'upsert'): bool {
    try {
        if (empty($agent['agent_code'])) {
            return false;
        }
        $payload = buildExternalPartnerAgencyPayload($agent, $event);
        $results = dispatchExternalPartnerEvent($event, $payload, [
            'agent_id' => (int)($agent['id'] ?? 0),
        ]);
        return externalPartnerResultsAllOk($results);
    } catch (Throwable $e) {
        error_log('External agency multi-site sync exception: ' . $e->getMessage());
        return false;
    }
}

function buildExternalPartnerLeadPayload(array $lead, array $agent, string $event = 'lead_created'): array {
    $project = null;
    if (!empty($lead['project_id'])) {
        $project = getProjectById((int)$lead['project_id']);
    }
    return [
        'event' => $event,
        'source' => 'sengoku-ai',
        'lead_id' => (int)($lead['id'] ?? 0),
        'common_user_id' => (string)($lead['common_user_id'] ?? ''),
        'referral_token_id' => !empty($lead['referral_token_id']) ? (int)$lead['referral_token_id'] : null,
        'referral_session_key' => (string)($lead['referral_session_key'] ?? ''),
        'referral_source' => (string)($lead['referral_source'] ?? 'lp_contact'),
        'source_agent_id' => (int)($agent['id'] ?? $lead['agent_id'] ?? 0),
        'external_id' => (string)($agent['agent_code'] ?? ''),
        'agent_name' => (string)($agent['agent_name'] ?? ''),
        'contact_name' => (string)($agent['person_name'] ?? ''),
        'project_id' => !empty($lead['project_id']) ? (int)$lead['project_id'] : null,
        'project_slug' => (string)($project['slug'] ?? ''),
        'project_name' => (string)($project['name'] ?? ''),
        'template_id' => !empty($lead['template_id']) ? (int)$lead['template_id'] : null,
        'customer' => [
            'name' => (string)($lead['name'] ?? ''),
            'email' => (string)($lead['email'] ?? ''),
            'phone' => (string)($lead['phone'] ?? ''),
            'message' => (string)($lead['message'] ?? ''),
        ],
        'source_url' => (string)($lead['source_url'] ?? ''),
        'status' => (string)($lead['status'] ?? 'new'),
        'created_at' => (string)($lead['created_at'] ?? date('c')),
        'updated_at' => date('c'),
    ];
}

function syncLeadToExternalPartnerSites(array $lead, array $agent, string $event = 'lead_created'): bool {
    try {
        $payload = buildExternalPartnerLeadPayload($lead, $agent, $event);
        $results = dispatchExternalPartnerEvent($event, $payload, [
            'agent_id' => (int)($agent['id'] ?? $lead['agent_id'] ?? 0),
            'common_user_id' => (string)($lead['common_user_id'] ?? ''),
        ]);
        return externalPartnerResultsAllOk($results);
    } catch (Throwable $e) {
        error_log('External lead sync exception: ' . $e->getMessage());
        return false;
    }
}

function buildExternalPartnerCommonUserPayload(string $commonUserId, string $event = 'common_user.updated', array $details = []): array {
    $profile = loadCommonUserHubProfile($commonUserId) ?: [];
    $commonUser = $profile['common_user'] ?? [];
    $relations = $profile['agency_relations'] ?? [];

    foreach ($relations as &$relation) {
        if (!empty($relation['agent_id'])) {
            $agent = getAgentById((int)$relation['agent_id']);
            if ($agent) {
                $relation['agent_code'] = (string)($agent['agent_code'] ?? '');
                $relation['agent_name'] = (string)($agent['agent_name'] ?? '');
                $relation['agent_role_level'] = (int)($agent['level'] ?? 0);
            }
        }
    }
    unset($relation);

    return [
        'event' => $event,
        'entity' => 'common_user',
        'source' => 'sengoku-ai',
        'common_user_id' => $commonUserId,
        'common_user' => $commonUser,
        'identities' => $profile['identities'] ?? [],
        'system_links' => $profile['system_links'] ?? [],
        'legacy_mappings' => $profile['legacy_mappings'] ?? [],
        'agency_relations' => $relations,
        'details' => $details,
        'updated_at' => date('c'),
    ];
}

function syncCommonUserHubEventToExternalPartners(string $event, string $commonUserId, array $details = []): bool {
    try {
        if ($commonUserId === '') {
            return false;
        }
        $payload = buildExternalPartnerCommonUserPayload($commonUserId, $event, $details);
        $results = dispatchExternalPartnerEvent($event, $payload, [
            'common_user_id' => $commonUserId,
        ]);
        return externalPartnerResultsAllOk($results);
    } catch (Throwable $e) {
        error_log('External common user sync exception: ' . $e->getMessage());
        return false;
    }
}

function syncAgentToExternalPartner(int $agentId, string $event = 'upsert'): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM agents WHERE id=? LIMIT 1");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        return $agent ? syncAgentArrayToExternalPartnerSites($agent, $event) : false;
    } catch (Throwable $e) {
        error_log('External agency sync lookup failed: ' . $e->getMessage());
        return false;
    }
}

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function getAgencySsoSettings(): array {
    return [
        'enabled' => getSystemSettingValue('sso_rr_enabled', '0') === '1',
        'audience' => trim(getSystemSettingValue('sso_rr_audience', 'sengoku-rr')) ?: 'sengoku-rr',
        'callback_url' => trim(getSystemSettingValue('sso_rr_callback_url', '')),
        'issuer' => trim(getSystemSettingValue('sso_issuer', getSiteBaseUrl())) ?: getSiteBaseUrl(),
        'key_id' => trim(getSystemSettingValue('sso_key_id', '')),
        'private_key' => (string)getSystemSettingValue('sso_private_key', ''),
        'public_key' => (string)getSystemSettingValue('sso_public_key', ''),
    ];
}

function normalizeSsoClientKey(string $key): string {
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9_-]+/', '-', $key) ?? '';
    $key = trim($key, '-_');
    return $key;
}

function getSsoClients(bool $activeOnly = false): array {
    try {
        $db = getDB();
        $where = $activeOnly ? " WHERE status='active'" : '';
        return $db->query("SELECT * FROM sso_clients{$where} ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('SSO clients fetch failed: ' . $e->getMessage());
        return [];
    }
}

function getSsoClientByKey(string $clientKey): ?array {
    $clientKey = normalizeSsoClientKey($clientKey);
    if ($clientKey === '') {
        return null;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM sso_clients WHERE client_key=? LIMIT 1");
        $stmt->execute([$clientKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('SSO client fetch failed: ' . $e->getMessage());
        return null;
    }
}

function getSsoClientByAudience(string $audience): ?array {
    $audience = trim($audience);
    if ($audience === '') {
        return null;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM sso_clients WHERE audience=? LIMIT 1");
        $stmt->execute([$audience]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('SSO client by audience fetch failed: ' . $e->getMessage());
        return null;
    }
}

function buildSsoLaunchUrlPayload(): array {
    $items = [];
    try {
        $baseUrl = getSiteBaseUrl();
        foreach (getSsoClients(true) as $client) {
            $clientKey = (string)($client['client_key'] ?? '');
            if ($clientKey === '') {
                continue;
            }
            $items[] = [
                'client_key' => $clientKey,
                'client_name' => (string)($client['name'] ?? ''),
                'audience' => (string)($client['audience'] ?? ''),
                'url' => $baseUrl . '/agent/sso_launch.php?client=' . rawurlencode($clientKey),
            ];
        }
    } catch (Throwable $e) {
        error_log('SSO launch URL payload build failed: ' . $e->getMessage());
    }
    return $items;
}

function buildSsoCallbackEndpoint(string $url): string {
    $url = rtrim(trim($url), '/');
    if ($url === '') {
        return '';
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (is_string($path) && trim($path, '/') !== '') {
        return $url;
    }
    return $url . '/agency/sso';
}

function generateAgencySsoKeyPair(): array {
    if (!function_exists('openssl_pkey_new')) {
        throw new RuntimeException('OpenSSL extension is not available.');
    }
    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);
    if (!$resource) {
        throw new RuntimeException('SSO key generation failed.');
    }
    $privateKey = '';
    if (!openssl_pkey_export($resource, $privateKey)) {
        throw new RuntimeException('SSO private key export failed.');
    }
    $details = openssl_pkey_get_details($resource);
    $publicKey = (string)($details['key'] ?? '');
    if ($publicKey === '') {
        throw new RuntimeException('SSO public key export failed.');
    }
    return [
        'kid' => 'sso-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)),
        'private_key' => $privateKey,
        'public_key' => $publicKey,
    ];
}

function saveSystemSettingValue(string $key, string $value): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO system_settings (key_name, value) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $stmt->execute([$key, $value]);
}

function pemPublicKeyToJwk(string $publicKey, string $kid): ?array {
    if (!function_exists('openssl_pkey_get_public')) {
        return null;
    }
    $resource = openssl_pkey_get_public($publicKey);
    if (!$resource) {
        return null;
    }
    $details = openssl_pkey_get_details($resource);
    if (empty($details['rsa']['n']) || empty($details['rsa']['e'])) {
        return null;
    }
    return [
        'kty' => 'RSA',
        'use' => 'sig',
        'kid' => $kid,
        'alg' => 'RS256',
        'n' => base64UrlEncode($details['rsa']['n']),
        'e' => base64UrlEncode($details['rsa']['e']),
    ];
}

function buildAgencySsoJwt(array $agent, ?string $returnTo = null, ?array $client = null): string {
    $settings = getAgencySsoSettings();
    if ($client === null && !$settings['enabled']) {
        throw new RuntimeException('SSO is disabled.');
    }
    if ($settings['private_key'] === '' || $settings['key_id'] === '') {
        throw new RuntimeException('SSO key pair is not configured.');
    }
    if (empty($agent['agent_code'])) {
        throw new RuntimeException('Agent code is empty.');
    }
    if ($client !== null && (($client['status'] ?? '') !== 'active')) {
        throw new RuntimeException('SSO client is inactive.');
    }
    $audience = $client !== null ? trim((string)($client['audience'] ?? '')) : $settings['audience'];
    if ($audience === '') {
        throw new RuntimeException('SSO audience is empty.');
    }

    $now = time();
    $payload = [
        'iss' => $settings['issuer'],
        'sub' => (string)$agent['agent_code'],
        'external_id' => (string)$agent['agent_code'],
        'aud' => $audience,
        'iat' => $now,
        'exp' => $now + 60,
        'jti' => bin2hex(random_bytes(24)),
        'role_level' => (int)($agent['level'] ?? 1),
        'role_label' => getLevelLabel((int)($agent['level'] ?? 1)),
        'agency_name' => (string)($agent['agent_name'] ?? ''),
        'contact_name' => (string)($agent['person_name'] ?? ''),
        'contact_email' => (string)($agent['email'] ?? ''),
        'actor_id' => (string)($agent['agent_code'] ?? ''),
        'actor_name' => (string)($agent['person_name'] ?? ''),
        'actor_email' => (string)($agent['email'] ?? ''),
    ];
    if ($client !== null) {
        $payload['client_key'] = (string)($client['client_key'] ?? '');
        $payload['client_name'] = (string)($client['name'] ?? '');
    }
    if ($returnTo && strpos($returnTo, '/') === 0 && strpos($returnTo, '//') !== 0) {
        $payload['return_to'] = $returnTo;
    }

    $header = [
        'typ' => 'JWT',
        'alg' => 'RS256',
        'kid' => $settings['key_id'],
    ];

    $segments = [
        base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ];
    $signingInput = implode('.', $segments);
    $signature = '';
    $ok = openssl_sign($signingInput, $signature, $settings['private_key'], OPENSSL_ALGO_SHA256);
    if (!$ok) {
        throw new RuntimeException('SSO JWT signing failed.');
    }
    $segments[] = base64UrlEncode($signature);
    return implode('.', $segments);
}

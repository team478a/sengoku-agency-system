<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

// POSTのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// CSRFトークン検証
$csrfToken = $input['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '不正なリクエストです。']);
    exit;
}

// 入力値取得・検証
$agentId = (int)($input['agent_id'] ?? 0);
$name    = sanitizeInput($input['name'] ?? '');
$email   = sanitizeInput($input['email'] ?? '');
$phone   = sanitizeInput($input['phone'] ?? '');
$message = sanitizeInput($input['message'] ?? '');

$errors = [];
if (!$agentId) $errors[] = 'アドバイザーIDが不正です。';
if (empty($name)) $errors[] = 'お名前を入力してください。';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '有効なメールアドレスを入力してください。';
if (empty($message)) $errors[] = 'お問い合わせ内容を入力してください。';
if (mb_strlen($message) > 2000) $errors[] = 'お問い合わせ内容は2000文字以内で入力してください。';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// アドバイザー情報取得
$agent = getAgentById($agentId);
if (!$agent || $agent['status'] !== 'active') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'アドバイザーが見つかりません。']);
    exit;
}

$notifyAgent = $agent;
if ((int)($agent['level'] ?? 1) === 1 && !empty($agent['parent_id'])) {
    $parentAgent = getAgentById((int)$agent['parent_id']);
    if ($parentAgent && $parentAgent['status'] === 'active' && (int)($parentAgent['level'] ?? 1) >= 2) {
        $notifyAgent = $parentAgent;
    }
}

// スパム簡易チェック（同一メール・同一agent_idで5分以内の重複送信）
$db = getDB();
$spamCheck = $db->prepare("
    SELECT COUNT(*) FROM leads
    WHERE agent_id = ? AND email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$spamCheck->execute([$agentId, $email]);
if ($spamCheck->fetchColumn() > 0) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => '短時間に複数回送信しています。しばらく経ってから再度お試しください。']);
    exit;
}

// leadsテーブルへ保存
try {
    $leadColumns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $leadColumns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        $leadColumns = [];
    }
    $sourceUrl = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);
    $refParams = [];
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $refQuery = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
        if ($refQuery) {
            parse_str($refQuery, $refParams);
        }
    }
    $templateId = (int)($input['template_id'] ?? 0);
    if (!$templateId && $refParams) {
        $refTpl = trim((string)($refParams['tpl'] ?? $refParams['template'] ?? ''));
        if ($refTpl !== '' && preg_match('/^[a-zA-Z0-9_\-]+$/', $refTpl)) {
            $tplStmt = $db->prepare("SELECT id FROM lp_templates WHERE slug=? AND status='active' LIMIT 1");
            $tplStmt->execute([$refTpl]);
            $templateId = (int)$tplStmt->fetchColumn();
        }
    }
    if (!$templateId) {
        $templateId = !empty($agent['default_template_id']) ? (int)$agent['default_template_id'] : 0;
    }
    $projectId = null;
    if ($templateId && !empty($leadColumns['project_id']) && tableHasColumn('lp_templates', 'project_id')) {
        $projectStmt = $db->prepare("SELECT project_id FROM lp_templates WHERE id=?");
        $projectStmt->execute([$templateId]);
        $projectId = (int)$projectStmt->fetchColumn() ?: null;
    }

    $referralToken = trim((string)($input['referral_token'] ?? $input['rt'] ?? $refParams['rt'] ?? $refParams['referral_token'] ?? ''));
    $referralSessionKey = trim((string)($input['referral_session_key'] ?? $input['rs'] ?? $refParams['rs'] ?? $refParams['referral_session_key'] ?? ''));
    $commonUserId = trim((string)($input['common_user_id'] ?? ''));
    $referralTokenRow = null;
    $referralTokenId = null;

    if ($referralToken !== '' && lpReferralFeaturesEnabled() && referralTokenTablesReady()) {
        $validatedReferral = validateReferralToken($referralToken);
        if (!empty($validatedReferral['valid']) && !empty($validatedReferral['token'])) {
            $referralTokenRow = $validatedReferral['token'];
            $referralTokenId = (int)$referralTokenRow['id'];
        }
    }

    $flags = getCommonIdFeatureFlags();
    if ($commonUserId === '' && $flags['common_id_enabled'] && commonIdTablesReady()) {
        try {
            $commonUserId = ensureCommonUser(null, [
                'email' => $email,
                'phone' => $phone,
                'profile' => ['name' => $name],
            ]);
        } catch (Throwable $e) {
            error_log('Lead common user error: ' . $e->getMessage());
            $commonUserId = '';
        }
    }

    if ($commonUserId !== '' && $flags['referral_v2_enabled'] && commonIdTablesReady()) {
        try {
            saveAgencyCustomerRelation([
                'common_user_id' => $commonUserId,
                'agent_id' => $agentId,
                'project_id' => $projectId ?: 0,
                'relation_type' => 'lead',
                'referral_token_id' => $referralTokenId,
                'referral_source' => 'lp_contact',
                'email' => $email,
                'phone' => $phone,
                'profile' => ['name' => $name],
                'locked' => 1,
            ]);
        } catch (Throwable $e) {
            error_log('Lead relation error: ' . $e->getMessage());
        }
    }

    if ($referralTokenRow && $referralSessionKey !== '') {
        try {
            recordReferralSession([
                'token_row' => $referralTokenRow,
                'session_key' => $referralSessionKey,
                'common_user_id' => $commonUserId,
                'event_type' => 'lead',
                'landing_url' => $sourceUrl,
                'destination_url' => currentRequestUrl(),
                'metadata' => ['source' => 'lp_contact'],
            ]);
        } catch (Throwable $e) {
            error_log('Lead referral session error: ' . $e->getMessage());
        }
    }

    $insertColumns = ['agent_id', 'name', 'email', 'phone', 'message', 'source_url', 'status'];
    $insertValues = [$agentId, $name, $email, $phone, $message, $sourceUrl, 'new'];
    if (!empty($leadColumns['project_id'])) {
        $insertColumns[] = 'project_id';
        $insertValues[] = $projectId;
    }
    if (!empty($leadColumns['template_id'])) {
        $insertColumns[] = 'template_id';
        $insertValues[] = $templateId ?: null;
    }
    if (!empty($leadColumns['common_user_id'])) {
        $insertColumns[] = 'common_user_id';
        $insertValues[] = $commonUserId ?: null;
    }
    if (!empty($leadColumns['referral_token_id'])) {
        $insertColumns[] = 'referral_token_id';
        $insertValues[] = $referralTokenId;
    }
    if (!empty($leadColumns['referral_session_key'])) {
        $insertColumns[] = 'referral_session_key';
        $insertValues[] = $referralSessionKey ?: null;
    }
    if (!empty($leadColumns['referral_source'])) {
        $insertColumns[] = 'referral_source';
        $insertValues[] = 'lp_contact';
    }
    $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
    $stmt = $db->prepare("
        INSERT INTO leads (" . implode(',', $insertColumns) . ")
        VALUES ($placeholders)
    ");
    $stmt->execute($insertValues);
    $leadId = $db->lastInsertId();

    syncLeadToExternalPartnerSites([
        'id' => $leadId,
        'agent_id' => $agentId,
        'project_id' => $projectId,
        'template_id' => $templateId ?: null,
        'common_user_id' => $commonUserId,
        'referral_token_id' => $referralTokenId,
        'referral_session_key' => $referralSessionKey,
        'referral_source' => 'lp_contact',
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'message' => $message,
        'source_url' => $sourceUrl,
        'status' => 'new',
        'created_at' => date('c'),
    ], $agent, 'lead_created');
} catch (Exception $e) {
    error_log('Lead insert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '送信中にエラーが発生しました。']);
    exit;
}

// 通知送信
$leadData = [
    'name'               => $name,
    'email'              => $email,
    'phone'              => $phone,
    'message'            => $message,
    'source_agent_id'    => $agent['id'] ?? null,
    'source_agent_name'  => $agent['agent_name'] ?? '',
    'source_person_name' => $agent['person_name'] ?? '',
    'source_agent_code'  => $agent['agent_code'] ?? '',
];

$notifier = new Notifier($notifyAgent, $leadData);
$notifier->send();

echo json_encode([
    'success' => true,
    'message' => 'お問い合わせを受け付けました。担当者よりご連絡いたします。',
]);

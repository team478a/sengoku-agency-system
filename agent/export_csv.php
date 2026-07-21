<?php
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
if (empty($_SESSION['agent_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM agents WHERE id=? AND status='active'");
$stmt->execute([$_SESSION['agent_id']]);
$currentAgent = $stmt->fetch();
if (!$currentAgent) {
    http_response_code(403);
    exit('Forbidden');
}

$aid = (int)$currentAgent['id'];
$myLv = (int)($currentAgent['level'] ?? 1);
$type = $_GET['type'] ?? '';

function csvOutput(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function visibleAgentIdsForExport(int $agentId, int $level): array {
    $ids = [$agentId];
    if ($level >= 2) {
        foreach (getAllDescendants($agentId) as $descendant) {
            $ids[] = (int)$descendant['id'];
        }
    }
    return array_values(array_unique($ids));
}

function tableColumnsForExport(PDO $db, string $table): array {
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('CSV column check failed: ' . $e->getMessage());
    }
    return $columns;
}

$visibleAgentIds = visibleAgentIdsForExport($aid, $myLv);
$ph = implode(',', array_fill(0, count($visibleAgentIds), '?'));
$labels = getLevelLabels();

if ($type === 'leads') {
    $statusLabels = [
        'new' => '新着',
        'contacted' => '対応中',
        'prospect' => '成約見込み',
        'won' => '成約',
        'lost' => '失注',
        'closed' => '完了',
    ];
    $status = $_GET['status'] ?? 'all';
    $params = $visibleAgentIds;
    $where = '';
    if ($status !== 'all' && array_key_exists($status, $statusLabels)) {
        $where = 'AND l.status=?';
        $params[] = $status;
    }
    $cols = tableColumnsForExport($db, 'leads');
    $stmt = $db->prepare("
        SELECT l.*, a.agent_name AS source_agent_name, a.agent_code AS source_agent_code, a.person_name AS source_person_name
        FROM leads l
        LEFT JOIN agents a ON a.id = l.agent_id
        WHERE l.agent_id IN ($ph) $where
        ORDER BY l.created_at DESC
    ");
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $lead) {
        $rows[] = [
            $lead['created_at'] ?? '',
            $lead['source_agent_name'] ?? '',
            $lead['source_agent_code'] ?? '',
            $lead['name'] ?? '',
            $lead['email'] ?? '',
            $lead['phone'] ?? '',
            $lead['message'] ?? '',
            $statusLabels[$lead['status'] ?? ''] ?? ($lead['status'] ?? ''),
            !empty($cols['next_action_at']) ? ($lead['next_action_at'] ?? '') : '',
            !empty($cols['internal_note']) ? ($lead['internal_note'] ?? '') : '',
        ];
    }
    csvOutput('leads_' . date('Ymd_His') . '.csv', ['日時','流入LP','コード','名前','メール','電話','内容','状態','次回対応日','管理メモ'], $rows);
}

if ($type === 'sub_agents') {
    $mode = $_GET['mode'] ?? ($myLv >= 3 ? 'directors' : 'advisors');
    if ($myLv < 3 || !in_array($mode, ['directors', 'advisors', 'all_advisors'], true)) {
        $mode = 'advisors';
    }
    $managedLevel = ($myLv >= 3 && $mode === 'directors') ? 2 : 1;
    if ($mode === 'all_advisors') {
        $descIds = array_values(array_filter($visibleAgentIds, static fn($id) => $id !== $GLOBALS['aid']));
        if (!$descIds) {
            csvOutput('sub_agents_' . date('Ymd_His') . '.csv', ['区分','コード','名称','担当者','メール','電話','上位','PV','問い合わせ','状態','登録日'], []);
        }
        $descPh = implode(',', array_fill(0, count($descIds), '?'));
        $stmt = $db->prepare("
            SELECT a.*, p.agent_name AS parent_name
            FROM agents a
            LEFT JOIN agents p ON p.id = a.parent_id
            WHERE a.id IN ($descPh) AND a.level=1
            ORDER BY p.agent_name, a.created_at DESC
        ");
        $stmt->execute($descIds);
    } else {
        $stmt = $db->prepare("
            SELECT a.*, p.agent_name AS parent_name
            FROM agents a
            LEFT JOIN agents p ON p.id = a.parent_id
            WHERE a.parent_id=? AND a.level=?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$aid, $managedLevel]);
    }
    $rows = [];
    foreach ($stmt->fetchAll() as $agent) {
        $pv = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE agent_id=? AND type='pv'");
        $pv->execute([$agent['id']]);
        $leadCount = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id=?");
        $leadCount->execute([$agent['id']]);
        $rows[] = [
            $labels[(int)($agent['level'] ?? 1)] ?? '',
            $agent['agent_code'] ?? '',
            $agent['agent_name'] ?? '',
            $agent['person_name'] ?? '',
            $agent['email'] ?? '',
            $agent['phone'] ?? '',
            $agent['parent_name'] ?? '',
            (int)$pv->fetchColumn(),
            (int)$leadCount->fetchColumn(),
            $agent['status'] ?? '',
            $agent['created_at'] ?? '',
        ];
    }
    csvOutput('sub_agents_' . date('Ymd_His') . '.csv', ['区分','コード','名称','担当者','メール','電話','上位','PV','問い合わせ','状態','登録日'], $rows);
}

if ($type === 'recruitment_links') {
    if ($myLv < 2) {
        http_response_code(403);
        exit('Forbidden');
    }
    $stmt = $db->prepare("
        SELECT rl.*,
               COUNT(ap.id) AS applicant_count,
               SUM(CASE WHEN ap.status='pending' THEN 1 ELSE 0 END) AS pending_count,
               SUM(CASE WHEN ap.status='approved' THEN 1 ELSE 0 END) AS approved_count
        FROM recruitment_links rl
        LEFT JOIN applicants ap ON ap.recruitment_link_id = rl.id
        WHERE rl.agent_id=?
        GROUP BY rl.id
        ORDER BY rl.created_at DESC
    ");
    $stmt->execute([$aid]);
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $rows = [];
    foreach ($stmt->fetchAll() as $link) {
        $target = (int)$link['target_level'] === 1
            ? getAdvisorPositionLabel($link['position_type'] ?? null, $link['position_label'] ?? null)
            : ($labels[(int)$link['target_level']] ?? '');
        $rows[] = [
            $link['name'] ?? '',
            $target,
            $baseUrl . '/join/' . ($link['token'] ?? ''),
            (int)($link['click_count'] ?? 0),
            (int)($link['applicant_count'] ?? 0),
            (int)($link['pending_count'] ?? 0),
            (int)($link['approved_count'] ?? 0),
            $link['status'] ?? '',
            $link['expires_at'] ?? '',
            $link['created_at'] ?? '',
        ];
    }
    csvOutput('recruitment_links_' . date('Ymd_His') . '.csv', ['募集名','対象','URL','クリック','応募','未承認','承認済','状態','期限','作成日'], $rows);
}

if ($type === 'downline_activity') {
    if ($myLv < 2) {
        http_response_code(403);
        exit('Forbidden');
    }

    $descendants = getAllDescendants($aid);
    $descendantIds = array_values(array_unique(array_map(static fn($agent) => (int)$agent['id'], $descendants)));
    if (!$descendantIds) {
        csvOutput('downline_activity_' . date('Ymd_His') . '.csv', ['会員','区分','コード','担当者','直属上位','PV','LINEクリック','問い合わせ','未対応','見込み','成約','CV率','最終アクセス','最終問い合わせ','最終ログイン','状態'], []);
    }

    $allowedPeriods = ['today' => 0, '7d' => 7, '30d' => 30, '90d' => 90, 'all' => null];
    $period = $_GET['period'] ?? '30d';
    if (!array_key_exists($period, $allowedPeriods)) $period = '30d';
    $days = $allowedPeriods[$period];
    $q = sanitizeInput($_GET['q'] ?? '');
    $level = (int)($_GET['level'] ?? 0);
    $status = sanitizeInput($_GET['status'] ?? '');
    $projectId = (int)($_GET['project_id'] ?? 0);
    $sort = sanitizeInput($_GET['sort'] ?? 'leads');

    $accessHasProject = tableHasColumn('access_logs', 'project_id');
    $leadHasProject = tableHasColumn('leads', 'project_id');
    $loginLogTableExists = false;
    try {
        $loginTableStmt = $db->prepare('SHOW TABLES LIKE ?');
        $loginTableStmt->execute(['login_logs']);
        $loginLogTableExists = (bool)$loginTableStmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Downline activity CSV table check failed: ' . $e->getMessage());
    }

    $dateSql = '';
    $dateParams = [];
    if ($period === 'today') {
        $dateSql = ' AND created_at >= CURDATE()';
    } elseif ($days !== null) {
        $dateSql = ' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $dateParams[] = $days;
    }

    $accessProjectSql = ($accessHasProject && $projectId > 0) ? ' AND project_id=?' : '';
    $accessProjectParams = ($accessHasProject && $projectId > 0) ? [$projectId] : [];
    $leadProjectSql = ($leadHasProject && $projectId > 0) ? ' AND project_id=?' : '';
    $leadProjectParams = ($leadHasProject && $projectId > 0) ? [$projectId] : [];

    $where = ['a.id IN (' . implode(',', array_fill(0, count($descendantIds), '?')) . ')'];
    $whereParams = $descendantIds;
    if ($q !== '') {
        $where[] = '(a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR a.email LIKE ? OR a.login_email LIKE ?)';
        $kw = '%' . $q . '%';
        array_push($whereParams, $kw, $kw, $kw, $kw, $kw);
    }
    if (isset($labels[$level])) {
        $where[] = 'a.level=?';
        $whereParams[] = $level;
    }
    if (in_array($status, ['active', 'inactive'], true)) {
        $where[] = 'a.status=?';
        $whereParams[] = $status;
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $sortMap = [
        'leads' => 'leads DESC, pv DESC',
        'pv' => 'pv DESC, leads DESC',
        'line' => 'line_clicks DESC, pv DESC',
        'new' => 'new_leads DESC, leads DESC',
        'cv' => 'conversion DESC, leads DESC',
        'last_access' => 'last_access DESC',
        'last_login' => 'last_login DESC',
        'name' => 'a.agent_name ASC',
    ];
    $orderSql = $sortMap[$sort] ?? $sortMap['leads'];

    $pvSub = "SELECT agent_id, COUNT(*) AS pv, MAX(created_at) AS last_access FROM access_logs WHERE type='pv' $accessProjectSql $dateSql GROUP BY agent_id";
    $lineSub = "SELECT agent_id, COUNT(*) AS line_clicks FROM access_logs WHERE type='line_click' $accessProjectSql $dateSql GROUP BY agent_id";
    $leadSub = "SELECT agent_id, COUNT(*) AS leads, SUM(status='new') AS new_leads, SUM(status='prospect') AS prospects, SUM(status='won') AS won, MAX(created_at) AS last_lead FROM leads WHERE 1=1 $leadProjectSql $dateSql GROUP BY agent_id";
    $loginSub = $loginLogTableExists
        ? "SELECT user_id AS agent_id, MAX(created_at) AS last_login FROM login_logs WHERE user_type='agent' AND success=1 GROUP BY user_id"
        : "SELECT NULL AS agent_id, NULL AS last_login WHERE 1=0";

    $params = array_merge(
        $accessProjectParams, $dateParams,
        $accessProjectParams, $dateParams,
        $leadProjectParams, $dateParams,
        $whereParams
    );
    $stmt = $db->prepare("
        SELECT
            a.*,
            parent.agent_name AS parent_name,
            COALESCE(pv.pv, 0) AS pv,
            COALESCE(lc.line_clicks, 0) AS line_clicks,
            COALESCE(ld.leads, 0) AS leads,
            COALESCE(ld.new_leads, 0) AS new_leads,
            COALESCE(ld.prospects, 0) AS prospects,
            COALESCE(ld.won, 0) AS won,
            pv.last_access,
            ld.last_lead,
            lg.last_login,
            CASE WHEN COALESCE(pv.pv,0) > 0 THEN ROUND((COALESCE(ld.leads,0) / COALESCE(pv.pv,0)) * 100, 2) ELSE NULL END AS conversion
        FROM agents a
        LEFT JOIN agents parent ON parent.id=a.parent_id
        LEFT JOIN ($pvSub) pv ON pv.agent_id=a.id
        LEFT JOIN ($lineSub) lc ON lc.agent_id=a.id
        LEFT JOIN ($leadSub) ld ON ld.agent_id=a.id
        LEFT JOIN ($loginSub) lg ON lg.agent_id=a.id
        $whereSql
        ORDER BY $orderSql
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $agent) {
        $rows[] = [
            $agent['agent_name'] ?? '',
            $labels[(int)($agent['level'] ?? 1)] ?? '',
            $agent['agent_code'] ?? '',
            $agent['person_name'] ?? '',
            $agent['parent_name'] ?? '',
            (int)($agent['pv'] ?? 0),
            (int)($agent['line_clicks'] ?? 0),
            (int)($agent['leads'] ?? 0),
            (int)($agent['new_leads'] ?? 0),
            (int)($agent['prospects'] ?? 0),
            (int)($agent['won'] ?? 0),
            $agent['conversion'] === null ? '' : ($agent['conversion'] . '%'),
            $agent['last_access'] ?? '',
            $agent['last_lead'] ?? '',
            $agent['last_login'] ?? '',
            ($agent['status'] ?? '') === 'active' ? '公開中' : '停止',
        ];
    }
    csvOutput('downline_activity_' . date('Ymd_His') . '.csv', ['会員','区分','コード','担当者','直属上位','PV','LINEクリック','問い合わせ','未対応','見込み','成約','CV率','最終アクセス','最終問い合わせ','最終ログイン','状態'], $rows);
}

http_response_code(404);
exit('Not found');

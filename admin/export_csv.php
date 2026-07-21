<?php
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin();

$db = getDB();
$type = $_GET['type'] ?? '';

function adminCsvOutput(string $filename, array $headers, array $rows): void {
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

function adminCsvColumns(PDO $db, string $table): array {
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('Admin CSV column check failed: ' . $e->getMessage());
    }
    return $columns;
}

if ($type === 'leads') {
    $statusLabels = [
        'new' => '新規',
        'contacted' => '対応中',
        'prospect' => '成約見込み',
        'won' => '成約',
        'lost' => '失注',
        'closed' => '対応済',
    ];
    $filterStatus = $_GET['status'] ?? '';
    $filterAgent = (int)($_GET['agent_id'] ?? 0);
    $search = sanitizeInput($_GET['q'] ?? '');
    $wheres = [];
    $params = [];
    if ($filterStatus && array_key_exists($filterStatus, $statusLabels)) {
        $wheres[] = 'l.status=?';
        $params[] = $filterStatus;
    }
    if ($filterAgent) {
        $wheres[] = 'l.agent_id=?';
        $params[] = $filterAgent;
    }
    if ($search) {
        $wheres[] = '(l.name LIKE ? OR l.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
    $stmt = $db->prepare("
        SELECT l.*, a.agent_name, a.person_name, a.agent_code
        FROM leads l
        JOIN agents a ON a.id = l.agent_id
        $where
        ORDER BY l.created_at DESC
    ");
    $stmt->execute($params);
    $cols = adminCsvColumns($db, 'leads');
    $rows = [];
    foreach ($stmt->fetchAll() as $lead) {
        $rows[] = [
            $lead['created_at'] ?? '',
            $lead['agent_name'] ?? '',
            $lead['agent_code'] ?? '',
            $lead['name'] ?? '',
            $lead['email'] ?? '',
            $lead['phone'] ?? '',
            $lead['message'] ?? '',
            $statusLabels[$lead['status'] ?? ''] ?? ($lead['status'] ?? ''),
            !empty($cols['next_action_at']) ? ($lead['next_action_at'] ?? '') : '',
            !empty($cols['internal_note']) ? ($lead['internal_note'] ?? '') : '',
        ];
    }
    adminCsvOutput('admin_leads_' . date('Ymd_His') . '.csv', ['日時','担当代理店','コード','名前','メール','電話','内容','状態','次回対応日','管理メモ'], $rows);
}

if ($type === 'template_reports') {
    $allowedPeriods = ['7d' => 7, '30d' => 30, '90d' => 90, 'all' => null];
    $period = $_GET['period'] ?? '30d';
    if (!array_key_exists($period, $allowedPeriods)) {
        $period = '30d';
    }
    $days = $allowedPeriods[$period];
    $accessColumns = adminCsvColumns($db, 'access_logs');
    $leadColumns = adminCsvColumns($db, 'leads');
    $accessTemplateExpr = !empty($accessColumns['template_id']) ? 'COALESCE(al.template_id, a.default_template_id)' : 'a.default_template_id';
    $leadTemplateExpr = !empty($leadColumns['template_id']) ? 'COALESCE(l.template_id, a.default_template_id)' : 'a.default_template_id';
    $dateSqlAccess = '';
    $dateSqlLead = '';
    $dateParamsAccess = [];
    $dateParamsLead = [];
    if ($days !== null) {
        $dateSqlAccess = ' AND al.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $dateSqlLead = ' AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $dateParamsAccess[] = $days;
        $dateParamsLead[] = $days;
    }

    $templates = $db->query("
        SELECT t.*, COUNT(DISTINCT a.id) AS active_agent_count
        FROM lp_templates t
        LEFT JOIN agents a ON a.default_template_id=t.id AND a.status='active'
        GROUP BY t.id
        ORDER BY t.sort_order ASC, t.id ASC
    ")->fetchAll();

    $pvMap = [];
    $stmt = $db->prepare("
        SELECT {$accessTemplateExpr} AS template_id, COUNT(*) AS cnt
        FROM access_logs al
        LEFT JOIN agents a ON a.id=al.agent_id
        WHERE al.type='pv' {$dateSqlAccess}
        GROUP BY {$accessTemplateExpr}
    ");
    $stmt->execute($dateParamsAccess);
    foreach ($stmt->fetchAll() as $row) $pvMap[(int)$row['template_id']] = (int)$row['cnt'];

    $lineMap = [];
    $stmt = $db->prepare("
        SELECT {$accessTemplateExpr} AS template_id, COUNT(*) AS cnt
        FROM access_logs al
        LEFT JOIN agents a ON a.id=al.agent_id
        WHERE al.type='line_click' {$dateSqlAccess}
        GROUP BY {$accessTemplateExpr}
    ");
    $stmt->execute($dateParamsAccess);
    foreach ($stmt->fetchAll() as $row) $lineMap[(int)$row['template_id']] = (int)$row['cnt'];

    $leadMap = [];
    $stmt = $db->prepare("
        SELECT {$leadTemplateExpr} AS template_id,
               COUNT(*) AS cnt,
               SUM(CASE WHEN l.status='new' THEN 1 ELSE 0 END) AS new_cnt,
               SUM(CASE WHEN l.status='prospect' THEN 1 ELSE 0 END) AS prospect_cnt,
               SUM(CASE WHEN l.status='won' THEN 1 ELSE 0 END) AS won_cnt
        FROM leads l
        LEFT JOIN agents a ON a.id=l.agent_id
        WHERE 1=1 {$dateSqlLead}
        GROUP BY {$leadTemplateExpr}
    ");
    $stmt->execute($dateParamsLead);
    foreach ($stmt->fetchAll() as $row) $leadMap[(int)$row['template_id']] = $row;

    $rows = [];
    foreach ($templates as $tpl) {
        $id = (int)$tpl['id'];
        $pv = $pvMap[$id] ?? 0;
        $leads = (int)($leadMap[$id]['cnt'] ?? 0);
        $rows[] = [
            $tpl['name'] ?? '',
            $tpl['slug'] ?? '',
            $tpl['html_file'] ?? '',
            (int)($tpl['active_agent_count'] ?? 0),
            $pv,
            $lineMap[$id] ?? 0,
            $leads,
            (int)($leadMap[$id]['new_cnt'] ?? 0),
            (int)($leadMap[$id]['prospect_cnt'] ?? 0),
            (int)($leadMap[$id]['won_cnt'] ?? 0),
            $pv > 0 ? round(($leads / $pv) * 100, 2) . '%' : '',
        ];
    }
    adminCsvOutput('template_reports_' . date('Ymd_His') . '.csv', ['テンプレート','スラッグ','ファイル','使用中','PV','LINE','問い合わせ','未対応','成約見込み','成約','CV率'], $rows);
}

if ($type === 'login_logs') {
    $userType = sanitizeInput($_GET['user_type'] ?? '');
    $result   = sanitizeInput($_GET['result'] ?? '');
    $search   = sanitizeInput($_GET['q'] ?? '');
    $from     = sanitizeInput($_GET['from'] ?? '');
    $to       = sanitizeInput($_GET['to'] ?? '');

    $wheres = [];
    $params = [];
    if (in_array($userType, ['admin', 'agent'], true)) {
        $wheres[] = 'l.user_type=?';
        $params[] = $userType;
    }
    if ($result === 'success') {
        $wheres[] = 'l.success=1';
    } elseif ($result === 'failed') {
        $wheres[] = 'l.success=0';
    }
    if ($search !== '') {
        $wheres[] = '(l.email LIKE ? OR a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR ad.username LIKE ? OR ad.display_name LIKE ?)';
        $kw = '%' . $search . '%';
        array_push($params, $kw, $kw, $kw, $kw, $kw, $kw);
    }
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $wheres[] = 'l.created_at >= ?';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $wheres[] = 'l.created_at <= ?';
        $params[] = $to . ' 23:59:59';
    }
    $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
    $stmt = $db->prepare("
        SELECT l.*, a.agent_name, a.person_name, a.agent_code, a.level AS agent_level,
               ad.username AS admin_username, ad.display_name AS admin_display_name, ad.role AS admin_role
        FROM login_logs l
        LEFT JOIN agents a ON l.user_type='agent' AND l.user_id=a.id
        LEFT JOIN admins ad ON l.user_type='admin' AND l.user_id=ad.id
        $where
        ORDER BY l.created_at DESC
    ");
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $log) {
        $displayName = $log['user_type'] === 'admin'
            ? (($log['admin_display_name'] ?? '') ?: ($log['admin_username'] ?? ''))
            : (($log['agent_name'] ?? '') ?: ($log['person_name'] ?? ''));
        $rows[] = [
            $log['created_at'] ?? '',
            $log['user_type'] === 'admin' ? '管理者' : '代理店',
            $displayName,
            $log['email'] ?? '',
            $log['user_type'] === 'admin' ? ($log['admin_role'] ?? '') : ($log['agent_code'] ?? ''),
            !empty($log['success']) ? '成功' : '失敗',
            $log['ip_hash'] ?? '',
        ];
    }
    adminCsvOutput('login_logs_' . date('Ymd_His') . '.csv', ['日時','種別','ユーザー','ログインID','権限/コード','結果','IPハッシュ'], $rows);
}

if ($type === 'agent_activity') {
    $labels = getLevelLabels();
    $projects = getProjects(false);
    $allowedPeriods = ['today' => 0, '7d' => 7, '30d' => 30, '90d' => 90, 'all' => null];
    $period = $_GET['period'] ?? '30d';
    if (!array_key_exists($period, $allowedPeriods)) $period = '30d';
    $days = $allowedPeriods[$period];
    $q = sanitizeInput($_GET['q'] ?? '');
    $level = (int)($_GET['level'] ?? 0);
    $status = sanitizeInput($_GET['status'] ?? '');
    $projectId = (int)($_GET['project_id'] ?? 0);
    $sort = sanitizeInput($_GET['sort'] ?? 'leads');
    $selectedProject = null;
    foreach ($projects as $project) {
        if ((int)$project['id'] === $projectId) {
            $selectedProject = $project;
            break;
        }
    }

    $accessHasProject = tableHasColumn('access_logs', 'project_id');
    $leadHasProject = tableHasColumn('leads', 'project_id');

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

    $where = [];
    $whereParams = [];
    $joinProject = '';
    if ($projectId > 0) {
        $joinProject = 'LEFT JOIN agent_project_templates apt_filter ON apt_filter.agent_id=a.id AND apt_filter.project_id=? LEFT JOIN lp_templates t_filter ON t_filter.id=a.default_template_id';
        $whereParams[] = $projectId;
        $where[] = '(apt_filter.project_id IS NOT NULL OR t_filter.project_id=?)';
        $whereParams[] = $projectId;
    }
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
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sortMap = [
        'leads' => 'leads DESC, pv DESC',
        'pv' => 'pv DESC, leads DESC',
        'line' => 'line_clicks DESC, pv DESC',
        'new' => 'new_leads DESC, leads DESC',
        'cv' => 'conversion DESC, leads DESC',
        'last_access' => 'last_access IS NULL ASC, last_access DESC',
        'last_login' => 'last_login IS NULL ASC, last_login DESC',
        'name' => 'a.agent_name ASC',
    ];
    $orderSql = $sortMap[$sort] ?? $sortMap['leads'];

    $pvSub = "SELECT agent_id, COUNT(*) AS pv, MAX(created_at) AS last_access FROM access_logs WHERE type='pv' $accessProjectSql $dateSql GROUP BY agent_id";
    $lineSub = "SELECT agent_id, COUNT(*) AS line_clicks FROM access_logs WHERE type='line_click' $accessProjectSql $dateSql GROUP BY agent_id";
    $leadSub = "SELECT agent_id, COUNT(*) AS leads, SUM(status='new') AS new_leads, SUM(status='prospect') AS prospects, SUM(status='won') AS won, MAX(created_at) AS last_lead FROM leads WHERE 1=1 $leadProjectSql $dateSql GROUP BY agent_id";
    $loginSub = "SELECT user_id AS agent_id, MAX(created_at) AS last_login FROM login_logs WHERE user_type='agent' AND success=1 GROUP BY user_id";

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
            parent.agent_code AS parent_code,
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
        $joinProject
        $whereSql
        ORDER BY $orderSql
    ");
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $agent) {
        $rows[] = [
            $agent['agent_code'] ?? '',
            $agent['agent_name'] ?? '',
            $agent['person_name'] ?? '',
            $labels[(int)($agent['level'] ?? 0)] ?? ('Lv.' . (int)($agent['level'] ?? 0)),
            $agent['parent_name'] ?? '本部直属',
            $agent['status'] === 'active' ? '公開中' : '停止',
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
            buildAgentProjectLpUrl((string)$agent['agent_code'], $selectedProject),
        ];
    }
    adminCsvOutput('agent_activity_' . date('Ymd_His') . '.csv', ['コード','代理店名','担当者','区分','上位','状態','PV','LINE','問い合わせ','未対応','見込み','成約','CV率','最終アクセス','最終問い合わせ','最終ログイン','LP URL'], $rows);
}

if ($type === 'common_hub_alerts') {
    $alert = sanitizeInput($_GET['alert'] ?? 'all');
    $allowed = [
        'all',
        'duplicate_email',
        'duplicate_phone',
        'duplicate_wallet',
        'unassigned_links',
        'relation_conflicts',
        'open_touchpoints',
        'orphan_users',
    ];
    if (!in_array($alert, $allowed, true)) {
        $alert = 'all';
    }

    $ready = [];
    foreach (['common_users', 'system_account_links', 'service_user_mappings', 'user_identities', 'agency_customer_relations', 'agent_touchpoints'] as $table) {
        $ready[$table] = !empty(tableColumns($table));
    }
    if (empty($ready['common_users'])) {
        adminCsvOutput('common_hub_alerts_' . date('Ymd_His') . '.csv', ['種別', '共通顧客ID', '件数', 'システム', '外部ID', 'プロジェクト', '代理店', '内容', '日時'], []);
    }

    $rows = [];
    $addDuplicateRows = function (string $column, string $label) use ($db, &$rows): void {
        $stmt = $db->query("
            SELECT {$column} AS value_key, COUNT(DISTINCT common_user_id) AS user_count,
                   GROUP_CONCAT(DISTINCT common_user_id ORDER BY common_user_id SEPARATOR ', ') AS common_user_ids,
                   MAX(updated_at) AS last_seen
            FROM system_account_links
            WHERE {$column} IS NOT NULL AND {$column}<>''
            GROUP BY {$column}
            HAVING COUNT(DISTINCT common_user_id) > 1
            ORDER BY last_seen DESC
            LIMIT 500
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [$label, (string)($row['common_user_ids'] ?? ''), (int)($row['user_count'] ?? 0), '', '', '', '', (string)($row['value_key'] ?? ''), (string)($row['last_seen'] ?? '')];
        }
    };

    if ($ready['system_account_links'] && ($alert === 'all' || $alert === 'duplicate_email')) {
        $addDuplicateRows('email_hash', '重複メール');
    }
    if ($ready['system_account_links'] && ($alert === 'all' || $alert === 'duplicate_phone')) {
        $addDuplicateRows('phone_hash', '重複電話');
    }
    if ($ready['system_account_links'] && ($alert === 'all' || $alert === 'duplicate_wallet')) {
        $addDuplicateRows('wallet_address', '重複ウォレット');
    }

    if ($ready['system_account_links'] && ($alert === 'all' || $alert === 'unassigned_links')) {
        $stmt = $db->query("
            SELECT l.*, a.agent_code, a.agent_name, a.person_name
            FROM system_account_links l
            LEFT JOIN agents a ON l.agent_id=a.id
            WHERE l.status='active' AND l.agent_id IS NULL
            ORDER BY l.updated_at DESC, l.id DESC
            LIMIT 1000
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = ['担当未設定', (string)($row['common_user_id'] ?? ''), '', (string)($row['system_key'] ?? ''), (string)($row['external_user_id'] ?? ''), '', '', (string)($row['display_name'] ?? ''), (string)($row['updated_at'] ?? '')];
        }
    }

    if ($ready['agency_customer_relations'] && ($alert === 'all' || $alert === 'relation_conflicts')) {
        $stmt = $db->query("
            SELECT r.common_user_id, r.project_id, p.name AS project_name, COUNT(DISTINCT r.agent_id) AS agent_count,
                   GROUP_CONCAT(DISTINCT COALESCE(a.agent_code, r.agent_id) ORDER BY a.agent_code SEPARATOR ', ') AS agents,
                   MAX(r.updated_at) AS last_seen
            FROM agency_customer_relations r
            LEFT JOIN agents a ON r.agent_id=a.id
            LEFT JOIN projects p ON r.project_id=p.id
            WHERE r.status='active' AND r.agent_id IS NOT NULL
            GROUP BY r.common_user_id, r.project_id
            HAVING COUNT(DISTINCT r.agent_id) > 1
            ORDER BY last_seen DESC
            LIMIT 1000
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = ['代理店紐づけ競合', (string)($row['common_user_id'] ?? ''), (int)($row['agent_count'] ?? 0), '', '', (string)($row['project_name'] ?? ('#' . ($row['project_id'] ?? ''))), (string)($row['agents'] ?? ''), '', (string)($row['last_seen'] ?? '')];
        }
    }

    if ($ready['agent_touchpoints'] && ($alert === 'all' || $alert === 'open_touchpoints')) {
        $stmt = $db->query("
            SELECT t.*, a.agent_code, a.agent_name, a.person_name, p.name AS project_name
            FROM agent_touchpoints t
            LEFT JOIN agents a ON t.agent_id=a.id
            LEFT JOIN projects p ON t.project_id=p.id
            WHERE t.confirmed_at IS NULL
            ORDER BY t.occurred_at DESC, t.id DESC
            LIMIT 1000
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $agentName = ($row['agent_name'] ?? '') ?: ($row['person_name'] ?? '') ?: ($row['agent_code'] ?? '');
            $rows[] = ['未確定接点', (string)($row['common_user_id'] ?? ''), '', (string)($row['source_system_key'] ?? ''), (string)($row['source_external_user_id'] ?? ''), (string)($row['project_name'] ?? ('#' . ($row['project_id'] ?? ''))), (string)$agentName, (string)($row['landing_url'] ?? ''), (string)($row['occurred_at'] ?? '')];
        }
    }

    if ($alert === 'all' || $alert === 'orphan_users') {
        $orphanWhere = [];
        if ($ready['system_account_links']) $orphanWhere[] = "NOT EXISTS (SELECT 1 FROM system_account_links l WHERE l.common_user_id=u.common_user_id)";
        if ($ready['service_user_mappings']) $orphanWhere[] = "NOT EXISTS (SELECT 1 FROM service_user_mappings m WHERE m.common_user_id=u.common_user_id)";
        if ($ready['user_identities']) $orphanWhere[] = "NOT EXISTS (SELECT 1 FROM user_identities i WHERE i.common_user_id=u.common_user_id)";
        if ($orphanWhere) {
            $stmt = $db->query("
                SELECT u.*
                FROM common_users u
                WHERE u.status='active' AND " . implode(' AND ', $orphanWhere) . "
                ORDER BY u.updated_at DESC, u.id DESC
                LIMIT 1000
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = ['孤立共通顧客ID', (string)($row['common_user_id'] ?? ''), '', '', '', '', '', (string)($row['acquisition_channel'] ?? ''), (string)($row['updated_at'] ?? '')];
            }
        }
    }

    adminCsvOutput('common_hub_alerts_' . $alert . '_' . date('Ymd_His') . '.csv', ['種別', '共通顧客ID', '件数', 'システム', '外部ID', 'プロジェクト', '代理店', '内容', '日時'], $rows);
}

http_response_code(404);
exit('Not found');

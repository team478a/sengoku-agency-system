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

$visibleAgentIds = visibleAgentIdsForExport($aid, $myLv);
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
    $leadCsvService = new \SenNoKuni\Lead\LeadCsvExportService($db);
    $rows = $leadCsvService->agentRows($visibleAgentIds, (string)$status, $statusLabels);
    csvOutput('leads_' . date('Ymd_His') . '.csv', ['日時','流入LP','コード','名前','メール','電話','内容','状態','次回対応日','管理メモ'], $rows);
}

if ($type === 'sub_agents') {
    $mode = $_GET['mode'] ?? ($myLv >= 3 ? 'directors' : 'advisors');
    $subAgentCsvService = new \SenNoKuni\Agency\SubAgentCsvExportService($db);
    $rows = $subAgentCsvService->rows($aid, $myLv, (string)$mode, $visibleAgentIds, $labels);
    csvOutput('sub_agents_' . date('Ymd_His') . '.csv', ['区分','コード','名称','担当者','メール','電話','上位','PV','問い合わせ','状態','登録日'], $rows);
}

if ($type === 'recruitment_links') {
    if ($myLv < 2) {
        http_response_code(403);
        exit('Forbidden');
    }
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $recruitmentLinkCsvService = new \SenNoKuni\Agency\RecruitmentLinkCsvExportService($db);
    $rows = $recruitmentLinkCsvService->rows(
        $aid,
        $baseUrl,
        $labels,
        static fn($positionType, $positionLabel): string => getAdvisorPositionLabel($positionType, $positionLabel)
    );
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

    $activityService = new \SenNoKuni\Activity\ActivityQueryService($db);
    $activityResult = $activityService->search([
        'admin_mode' => false,
        'agent_ids' => $descendantIds,
        'all' => true,
        'period' => $period,
        'days' => $days,
        'project_id' => $projectId,
        'q' => $q,
        'level' => $level,
        'status' => $status,
        'sort' => $sort,
        'labels' => $labels,
    ]);

    $rows = [];
    foreach ($activityResult['rows'] as $agent) {
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

<?php

declare(strict_types=1);

namespace SenNoKuni\Activity;

use PDO;
use Throwable;

final class ActivityQueryService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{rows: list<array<string, mixed>>, stats: array<string, mixed>, total: int}
     */
    public function search(array $criteria): array
    {
        $projectId = max(0, (int)($criteria['project_id'] ?? 0));
        $days = $criteria['days'] ?? null;
        $period = (string)($criteria['period'] ?? '30d');
        $page = max(1, (int)($criteria['page'] ?? 1));
        $perPage = max(1, (int)($criteria['per_page'] ?? 50));
        $offset = ($page - 1) * $perPage;
        $labels = is_array($criteria['labels'] ?? null) ? $criteria['labels'] : [];
        $sort = (string)($criteria['sort'] ?? 'leads');
        $adminMode = !empty($criteria['admin_mode']);
        $filterProjectAssignments = !empty($criteria['filter_project_assignments']);

        [$dateSql, $dateParams] = $this->buildDateFilter($period, is_int($days) ? $days : null);

        $accessProjectSql = ($this->columnExists('access_logs', 'project_id') && $projectId > 0) ? ' AND project_id=?' : '';
        $accessProjectParams = $accessProjectSql !== '' ? [$projectId] : [];
        $leadProjectSql = ($this->columnExists('leads', 'project_id') && $projectId > 0) ? ' AND project_id=?' : '';
        $leadProjectParams = $leadProjectSql !== '' ? [$projectId] : [];

        [$whereSql, $whereParams, $joinProject] = $this->buildAgentFilter($criteria, $labels, $projectId, $filterProjectAssignments);
        $orderSql = $this->orderSql($sort, $adminMode);

        $pvSub = "SELECT agent_id, COUNT(*) AS pv, MAX(created_at) AS last_access FROM access_logs WHERE type='pv' $accessProjectSql $dateSql GROUP BY agent_id";
        $lineSub = "SELECT agent_id, COUNT(*) AS line_clicks FROM access_logs WHERE type='line_click' $accessProjectSql $dateSql GROUP BY agent_id";
        $leadSub = "SELECT agent_id, COUNT(*) AS leads, SUM(status='new') AS new_leads, SUM(status='prospect') AS prospects, SUM(status='won') AS won, MAX(created_at) AS last_lead FROM leads WHERE 1=1 $leadProjectSql $dateSql GROUP BY agent_id";
        $loginSub = $this->tableExists('login_logs')
            ? "SELECT user_id AS agent_id, MAX(created_at) AS last_login FROM login_logs WHERE user_type='agent' AND success=1 GROUP BY user_id"
            : "SELECT NULL AS agent_id, NULL AS last_login WHERE 1=0";

        $countStmt = $this->db->prepare("SELECT COUNT(DISTINCT a.id) FROM agents a $joinProject $whereSql");
        $countStmt->execute($whereParams);
        $total = (int)$countStmt->fetchColumn();

        $params = array_merge(
            $accessProjectParams,
            $dateParams,
            $accessProjectParams,
            $dateParams,
            $leadProjectParams,
            $dateParams,
            $whereParams
        );

        $stmt = $this->db->prepare("
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
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $statsStmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT a.id) AS agent_count,
                SUM(COALESCE(pv.pv,0)) AS pv_total,
                SUM(COALESCE(lc.line_clicks,0)) AS line_total,
                SUM(COALESCE(ld.leads,0)) AS lead_total,
                SUM(COALESCE(ld.new_leads,0)) AS new_total
            FROM agents a
            LEFT JOIN ($pvSub) pv ON pv.agent_id=a.id
            LEFT JOIN ($lineSub) lc ON lc.agent_id=a.id
            LEFT JOIN ($leadSub) ld ON ld.agent_id=a.id
            $joinProject
            $whereSql
        ");
        $statsStmt->execute($params);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'stats' => $stats,
            'total' => $total,
        ];
    }

    public function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: list<int>}
     */
    private function buildDateFilter(string $period, ?int $days): array
    {
        if ($period === 'today') {
            return [' AND created_at >= CURDATE()', []];
        }

        if ($days !== null) {
            return [' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]];
        }

        return ['', []];
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<int|string, string> $labels
     * @return array{0: string, 1: list<mixed>, 2: string}
     */
    private function buildAgentFilter(array $criteria, array $labels, int $projectId, bool $filterProjectAssignments): array
    {
        $where = [];
        $params = [];
        $joinProject = '';

        if ($filterProjectAssignments && $projectId > 0) {
            $joinProject = 'LEFT JOIN agent_project_templates apt_filter ON apt_filter.agent_id=a.id AND apt_filter.project_id=? LEFT JOIN lp_templates t_filter ON t_filter.id=a.default_template_id';
            $params[] = $projectId;
            $where[] = '(apt_filter.project_id IS NOT NULL OR t_filter.project_id=?)';
            $params[] = $projectId;
        }

        $agentIds = $criteria['agent_ids'] ?? null;
        if (is_array($agentIds)) {
            $agentIds = array_values(array_unique(array_map('intval', $agentIds)));
            if ($agentIds) {
                $where[] = 'a.id IN (' . implode(',', array_fill(0, count($agentIds), '?')) . ')';
                $params = array_merge($params, $agentIds);
            } else {
                $where[] = '1=0';
            }
        }

        $q = trim((string)($criteria['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR a.email LIKE ? OR a.login_email LIKE ?)';
            $kw = '%' . $q . '%';
            array_push($params, $kw, $kw, $kw, $kw, $kw);
        }

        $level = (int)($criteria['level'] ?? 0);
        if (isset($labels[$level])) {
            $where[] = 'a.level=?';
            $params[] = $level;
        }

        $status = (string)($criteria['status'] ?? '');
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'a.status=?';
            $params[] = $status;
        }

        return [
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $params,
            $joinProject,
        ];
    }

    private function orderSql(string $sort, bool $adminMode): string
    {
        $sortMap = [
            'leads' => 'leads DESC, pv DESC',
            'pv' => 'pv DESC, leads DESC',
            'line' => 'line_clicks DESC, pv DESC',
            'new' => 'new_leads DESC, leads DESC',
            'cv' => 'conversion DESC, leads DESC',
            'last_access' => $adminMode ? 'last_access IS NULL ASC, last_access DESC' : 'last_access DESC',
            'last_login' => $adminMode ? 'last_login IS NULL ASC, last_login DESC' : 'last_login DESC',
            'name' => 'a.agent_name ASC',
        ];

        return $sortMap[$sort] ?? $sortMap['leads'];
    }
}

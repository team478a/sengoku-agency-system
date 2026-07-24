<?php

declare(strict_types=1);

namespace SenNoKuni\Activity;

use PDO;
use Throwable;

final class ActivityTrendService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param list<int> $visibleAgentIds
     * @return array{labels: list<string>, pv: list<int>, leads: list<int>}
     */
    public function dailySeries(int $agentId, array $visibleAgentIds, int $projectId = 0, int $days = 30): array
    {
        $days = max(1, $days);
        $visibleAgentIds = array_values(array_unique(array_map('intval', $visibleAgentIds)));
        if (!$visibleAgentIds) {
            $visibleAgentIds = [$agentId];
        }

        $accessProjectSql = ($this->columnExists('access_logs', 'project_id') && $projectId > 0) ? ' AND project_id=?' : '';
        $accessProjectParams = $accessProjectSql !== '' ? [$projectId] : [];
        $leadProjectSql = ($this->columnExists('leads', 'project_id') && $projectId > 0) ? ' AND project_id=?' : '';
        $leadProjectParams = $leadProjectSql !== '' ? [$projectId] : [];

        $pvStmt = $this->db->prepare("
            SELECT DATE(created_at) AS d, COUNT(*) AS cnt
            FROM access_logs
            WHERE agent_id=? AND type='pv' $accessProjectSql AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY d ASC
        ");
        $pvStmt->execute(array_merge([$agentId], $accessProjectParams, [$days]));
        $pvData = $pvStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $visiblePlaceholders = implode(',', array_fill(0, count($visibleAgentIds), '?'));
        $leadStmt = $this->db->prepare("
            SELECT DATE(created_at) AS d, COUNT(*) AS cnt
            FROM leads
            WHERE agent_id IN ($visiblePlaceholders) $leadProjectSql AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY d ASC
        ");
        $leadStmt->execute(array_merge($visibleAgentIds, $leadProjectParams, [$days]));
        $leadData = $leadStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $labels = [];
        $pvValues = [];
        $leadValues = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('m/d', strtotime($date));
            $pvValues[] = (int)($pvData[$date] ?? 0);
            $leadValues[] = (int)($leadData[$date] ?? 0);
        }

        return [
            'labels' => $labels,
            'pv' => $pvValues,
            'leads' => $leadValues,
        ];
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
}

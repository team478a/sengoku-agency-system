<?php

declare(strict_types=1);

namespace SenNoKuni\Activity;

use PDO;

final class ActivityDownlineRankingService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param list<array<string, mixed>> $descendants
     * @param list<mixed> $accessProjectParams
     * @param list<mixed> $leadProjectParams
     * @param list<mixed> $dateParams
     * @return list<array<string, mixed>>
     */
    public function rows(
        array $descendants,
        string $accessProjectWhere,
        array $accessProjectParams,
        string $leadProjectWhere,
        array $leadProjectParams,
        string $dateSql,
        array $dateParams
    ): array {
        if (!$descendants) {
            return [];
        }

        $descendantIds = array_map(static fn($agent): int => (int)$agent['id'], $descendants);
        $descendantPlaceholders = implode(',', array_fill(0, count($descendantIds), '?'));

        $pvStmt = $this->db->prepare("
            SELECT agent_id, COUNT(*) AS cnt, MAX(created_at) AS last_at
            FROM access_logs
            WHERE agent_id IN ($descendantPlaceholders) AND type='pv' $accessProjectWhere $dateSql
            GROUP BY agent_id
        ");
        $pvStmt->execute(array_merge($descendantIds, $accessProjectParams, $dateParams));
        $pvMap = [];
        foreach ($pvStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $pvMap[(int)$row['agent_id']] = $row;
        }

        $lineStmt = $this->db->prepare("
            SELECT agent_id, COUNT(*) AS cnt
            FROM access_logs
            WHERE agent_id IN ($descendantPlaceholders) AND type='line_click' $accessProjectWhere $dateSql
            GROUP BY agent_id
        ");
        $lineStmt->execute(array_merge($descendantIds, $accessProjectParams, $dateParams));
        $lineMap = [];
        foreach ($lineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $lineMap[(int)$row['agent_id']] = (int)$row['cnt'];
        }

        $leadStmt = $this->db->prepare("
            SELECT agent_id, COUNT(*) AS cnt, SUM(status='new') AS new_cnt, MAX(created_at) AS last_at
            FROM leads
            WHERE agent_id IN ($descendantPlaceholders) $leadProjectWhere $dateSql
            GROUP BY agent_id
        ");
        $leadStmt->execute(array_merge($descendantIds, $leadProjectParams, $dateParams));
        $leadMap = [];
        foreach ($leadStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $leadMap[(int)$row['agent_id']] = $row;
        }

        $rows = [];
        foreach ($descendants as $agent) {
            $id = (int)$agent['id'];
            $pv = (int)($pvMap[$id]['cnt'] ?? 0);
            $leads = (int)($leadMap[$id]['cnt'] ?? 0);
            $rows[] = [
                'id' => $id,
                'level' => (int)($agent['level'] ?? 1),
                'agent_name' => $agent['agent_name'] ?? '',
                'person_name' => $agent['person_name'] ?? '',
                'agent_code' => $agent['agent_code'] ?? '',
                'status' => $agent['status'] ?? '',
                'pv' => $pv,
                'line' => (int)($lineMap[$id] ?? 0),
                'leads' => $leads,
                'new_leads' => (int)($leadMap[$id]['new_cnt'] ?? 0),
                'last_access' => $pvMap[$id]['last_at'] ?? null,
                'last_lead' => $leadMap[$id]['last_at'] ?? null,
                'conversion' => $pv > 0 ? round(($leads / $pv) * 100, 1) : null,
            ];
        }

        usort($rows, static fn($a, $b) => [$b['leads'], $b['pv']] <=> [$a['leads'], $a['pv']]);
        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{pv: list<array<string, mixed>>, leads: list<array<string, mixed>>}
     */
    public function rankings(array $rows): array
    {
        $rankByPv = $rows;
        usort($rankByPv, static fn($a, $b) => $b['pv'] <=> $a['pv']);

        $rankByLeads = $rows;
        usort($rankByLeads, static fn($a, $b) => $b['leads'] <=> $a['leads']);

        return [
            'pv' => $rankByPv,
            'leads' => $rankByLeads,
        ];
    }
}

<?php

declare(strict_types=1);

namespace SenNoKuni\Agency;

use PDO;

final class SubAgentCsvExportService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<int, int> $visibleAgentIds
     * @param array<int|string, string> $levelLabels
     * @return array<int, array<int, mixed>>
     */
    public function rows(int $ownerId, int $ownerLevel, string $mode, array $visibleAgentIds, array $levelLabels): array
    {
        if ($ownerLevel < 3 || !in_array($mode, ['directors', 'advisors', 'all_advisors'], true)) {
            $mode = 'advisors';
        }

        $managedLevel = ($ownerLevel >= 3 && $mode === 'directors') ? 2 : 1;
        $agents = $mode === 'all_advisors'
            ? $this->allAdvisorRows($ownerId, $visibleAgentIds)
            : $this->directRows($ownerId, $managedLevel);

        return $this->formatRows($agents, $levelLabels);
    }

    /**
     * @param array<int, int> $visibleAgentIds
     * @return array<int, array<string, mixed>>
     */
    private function allAdvisorRows(int $ownerId, array $visibleAgentIds): array
    {
        $descendantIds = array_values(array_filter(
            array_values(array_unique(array_map('intval', $visibleAgentIds))),
            static fn(int $id): bool => $id !== $ownerId
        ));

        if ($descendantIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($descendantIds), '?'));
        $stmt = $this->db->prepare("
            SELECT a.*, p.agent_name AS parent_name
            FROM agents a
            LEFT JOIN agents p ON p.id = a.parent_id
            WHERE a.id IN ($placeholders) AND a.level=1
            ORDER BY p.agent_name, a.created_at DESC
        ");
        $stmt->execute($descendantIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directRows(int $ownerId, int $managedLevel): array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, p.agent_name AS parent_name
            FROM agents a
            LEFT JOIN agents p ON p.id = a.parent_id
            WHERE a.parent_id=? AND a.level=?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$ownerId, $managedLevel]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array<string, mixed>> $agents
     * @param array<int|string, string> $levelLabels
     * @return array<int, array<int, mixed>>
     */
    private function formatRows(array $agents, array $levelLabels): array
    {
        $rows = [];

        foreach ($agents as $agent) {
            $agentId = (int)($agent['id'] ?? 0);
            $rows[] = [
                $levelLabels[(int)($agent['level'] ?? 1)] ?? '',
                $agent['agent_code'] ?? '',
                $agent['agent_name'] ?? '',
                $agent['person_name'] ?? '',
                $agent['email'] ?? '',
                $agent['phone'] ?? '',
                $agent['parent_name'] ?? '',
                $this->countAccessLogs($agentId),
                $this->countLeads($agentId),
                $agent['status'] ?? '',
                $agent['created_at'] ?? '',
            ];
        }

        return $rows;
    }

    private function countAccessLogs(int $agentId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM access_logs WHERE agent_id=? AND type='pv'");
        $stmt->execute([$agentId]);

        return (int)$stmt->fetchColumn();
    }

    private function countLeads(int $agentId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id=?");
        $stmt->execute([$agentId]);

        return (int)$stmt->fetchColumn();
    }
}

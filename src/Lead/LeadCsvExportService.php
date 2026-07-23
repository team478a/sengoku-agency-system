<?php

declare(strict_types=1);

namespace SenNoKuni\Lead;

use PDO;
use Throwable;

final class LeadCsvExportService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, string> $statusLabels
     * @return array<int, array<int, mixed>>
     */
    public function adminRows(array $filters, array $statusLabels): array
    {
        $filterStatus = (string)($filters['status'] ?? '');
        $filterAgent = (int)($filters['agent_id'] ?? 0);
        $search = (string)($filters['q'] ?? '');

        $wheres = [];
        $params = [];

        if ($filterStatus !== '' && array_key_exists($filterStatus, $statusLabels)) {
            $wheres[] = 'l.status=?';
            $params[] = $filterStatus;
        }
        if ($filterAgent > 0) {
            $wheres[] = 'l.agent_id=?';
            $params[] = $filterAgent;
        }
        if ($search !== '') {
            $wheres[] = '(l.name LIKE ? OR l.email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $stmt = $this->db->prepare("
            SELECT l.*, a.agent_name, a.person_name, a.agent_code
            FROM leads l
            JOIN agents a ON a.id = l.agent_id
            $where
            ORDER BY l.created_at DESC
        ");
        $stmt->execute($params);

        return $this->leadRows($stmt->fetchAll(), $statusLabels, [
            'agent_name' => 'agent_name',
            'agent_code' => 'agent_code',
        ]);
    }

    /**
     * @param array<int, int> $visibleAgentIds
     * @param array<string, string> $statusLabels
     * @return array<int, array<int, mixed>>
     */
    public function agentRows(array $visibleAgentIds, string $status, array $statusLabels): array
    {
        $visibleAgentIds = array_values(array_unique(array_map('intval', $visibleAgentIds)));
        if ($visibleAgentIds === []) {
            return [];
        }

        $params = $visibleAgentIds;
        $where = '';
        if ($status !== 'all' && array_key_exists($status, $statusLabels)) {
            $where = 'AND l.status=?';
            $params[] = $status;
        }

        $placeholders = implode(',', array_fill(0, count($visibleAgentIds), '?'));
        $stmt = $this->db->prepare("
            SELECT l.*, a.agent_name AS source_agent_name, a.agent_code AS source_agent_code, a.person_name AS source_person_name
            FROM leads l
            LEFT JOIN agents a ON a.id = l.agent_id
            WHERE l.agent_id IN ($placeholders) $where
            ORDER BY l.created_at DESC
        ");
        $stmt->execute($params);

        return $this->leadRows($stmt->fetchAll(), $statusLabels, [
            'agent_name' => 'source_agent_name',
            'agent_code' => 'source_agent_code',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $leads
     * @param array<string, string> $statusLabels
     * @param array{agent_name: string, agent_code: string} $agentColumns
     * @return array<int, array<int, mixed>>
     */
    private function leadRows(array $leads, array $statusLabels, array $agentColumns): array
    {
        $columns = $this->tableColumns('leads');
        $rows = [];

        foreach ($leads as $lead) {
            $rows[] = [
                $lead['created_at'] ?? '',
                $lead[$agentColumns['agent_name']] ?? '',
                $lead[$agentColumns['agent_code']] ?? '',
                $lead['name'] ?? '',
                $lead['email'] ?? '',
                $lead['phone'] ?? '',
                $lead['message'] ?? '',
                $statusLabels[$lead['status'] ?? ''] ?? ($lead['status'] ?? ''),
                !empty($columns['next_action_at']) ? ($lead['next_action_at'] ?? '') : '',
                !empty($columns['internal_note']) ? ($lead['internal_note'] ?? '') : '',
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, bool>
     */
    private function tableColumns(string $table): array
    {
        $columns = [];

        try {
            $rows = $this->db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns[(string)$row['Field']] = true;
            }
        } catch (Throwable $e) {
            error_log('Lead CSV column check failed: ' . $e->getMessage());
        }

        return $columns;
    }
}

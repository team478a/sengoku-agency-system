<?php

declare(strict_types=1);

namespace SenNoKuni\Point;

use PDO;
use RuntimeException;

final class OrlySeminarAttendancePointAwardService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?OrlyReferralPointCampaignRepository $campaigns = null,
        private readonly ?OrlyReferralPointAwardPlanner $planner = null,
        private readonly ?PointAwardEventRepository $events = null
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function recordAttendance(array $context): array
    {
        $seminarId = $this->stringValue($context, 'seminar_id');
        $targetCommonUserId = $this->stringValue($context, 'target_common_user_id', $this->stringValue($context, 'common_user_id'));
        if ($seminarId === '') {
            throw new RuntimeException('seminar_id is required.');
        }
        if ($targetCommonUserId === '') {
            throw new RuntimeException('common_user_id is required.');
        }

        $campaigns = $this->campaigns ?? new OrlyReferralPointCampaignRepository($this->pdo);
        if (!$campaigns->tablesReady()) {
            return ['ok' => true, 'status' => 'schema_not_ready', 'saved' => []];
        }

        $activeCampaign = $campaigns->loadActiveSeminarAttendanceCampaign();
        if ($activeCampaign === null) {
            return ['ok' => true, 'status' => 'campaign_inactive', 'saved' => []];
        }

        $directReferrer = $this->loadDirectReferrer($context, $targetCommonUserId);
        $ancestorAgents = $this->loadAncestors($directReferrer !== null ? (int)($directReferrer['parent_id'] ?? 0) : 0);
        $projectKey = $this->stringValue($context, 'project_key', 'orly');
        $triggerEventId = $this->buildAttendanceTriggerId($seminarId, $targetCommonUserId);
        $sourceSystemKey = $this->stringValue($context, 'source_system_key', 'AGENCY_SYSTEM');

        $plan = ($this->planner ?? new OrlyReferralPointAwardPlanner())->plan([
            'campaign' => $activeCampaign['campaign'],
            'campaign_version' => $activeCampaign['campaign_version'],
            'direct_referrer_agent' => $directReferrer,
            'ancestor_agents' => $ancestorAgents,
            'target_recipient_type' => 'attendee',
            'trigger_event_type' => 'seminar.attended',
            'trigger_event_id' => $triggerEventId,
            'target_common_user_id' => $targetCommonUserId,
            'source_system_key' => $sourceSystemKey,
            'project_key' => $projectKey,
        ]);

        $plan = $this->removeDuplicateRecipients($plan);
        $directReferrerId = $directReferrer !== null ? (int)$directReferrer['id'] : null;
        $upperDirectorId = $this->findRecipientAgentId($plan, 'upper_director');
        $occurredAt = $this->normalizeOccurredAt($this->stringValue($context, 'occurred_at'));
        $awards = [];

        foreach ($plan as $award) {
            $award['point_code'] = $award['currency_code'] ?? 'orly';
            $award['target_common_user_id'] = $targetCommonUserId;
            $award['direct_referrer_agent_id'] = $directReferrerId;
            $award['upper_director_agent_id'] = $upperDirectorId;
            $award['status'] = 'pending';
            $award['referral_snapshot'] = [
                'campaign_key' => (string)($activeCampaign['campaign']['campaign_key'] ?? 'orly_seminar_attendance'),
                'campaign_version_no' => (int)($activeCampaign['campaign_version']['version_no'] ?? 1),
                'seminar_id' => $seminarId,
                'attendance_key' => $triggerEventId,
                'direct_referrer' => $directReferrer ? [
                    'agent_id' => (int)$directReferrer['id'],
                    'agent_code' => (string)($directReferrer['agent_code'] ?? $directReferrer['code'] ?? ''),
                    'role' => (string)($directReferrer['position_type'] ?? ''),
                    'level' => (int)($directReferrer['level'] ?? 0),
                ] : null,
                'upper_director_agent_id' => $upperDirectorId,
                'project_key' => $projectKey,
                'source_system_key' => $sourceSystemKey,
            ];
            $award['correlation_id'] = $this->stringValue($context, 'correlation_id') ?: null;
            $award['occurred_at'] = $occurredAt;
            $awards[] = $award;
        }

        $saved = ($this->events ?? new PointAwardEventRepository($this->pdo))->savePlannedAwards($awards);
        foreach ($saved as $result) {
            if (($result['status'] ?? '') === 'conflict') {
                return [
                    'ok' => false,
                    'status' => 'conflict',
                    'attendance_key' => $triggerEventId,
                    'saved' => $saved,
                ];
            }
        }

        return [
            'ok' => true,
            'status' => empty($saved) ? 'no_awards' : 'recorded',
            'attendance_key' => $triggerEventId,
            'saved' => $saved,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return ?array<string, mixed>
     */
    private function loadDirectReferrer(array $context, string $targetCommonUserId): ?array
    {
        $agentId = (int)($context['direct_referrer_agent_id'] ?? $context['agent_id'] ?? 0);
        if ($agentId <= 0) {
            $agentId = $this->loadLinkedAgentId($targetCommonUserId);
        }

        return $this->loadAgent($agentId);
    }

    private function loadLinkedAgentId(string $commonUserId): int
    {
        if ($commonUserId === '' || !$this->tableExists('agency_customer_relations')) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT agent_id
            FROM agency_customer_relations
            WHERE common_user_id=?
              AND (status IS NULL OR status IN ('active', 'linked'))
            ORDER BY locked DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$commonUserId]);

        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return ?array<string, mixed>
     */
    private function loadAgent(int $agentId): ?array
    {
        if ($agentId <= 0 || !$this->tableExists('agents')) {
            return null;
        }

        $columns = function_exists('tableColumns') ? tableColumns('agents') : [];
        $select = ['id', 'agent_code', 'agent_code AS code', 'level', 'parent_id', 'agent_name', 'person_name', 'status'];
        foreach (['position_type', 'position_label', 'common_user_id'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $stmt = $this->pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM agents WHERE id=? LIMIT 1');
        $stmt->execute([$agentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadAncestors(int $parentId): array
    {
        $ancestors = [];
        $seen = [];

        for ($depth = 0; $depth < 20 && $parentId > 0; $depth++) {
            if (isset($seen[$parentId])) {
                break;
            }
            $seen[$parentId] = true;

            $agent = $this->loadAgent($parentId);
            if ($agent === null) {
                break;
            }
            $ancestors[] = $agent;
            $parentId = (int)($agent['parent_id'] ?? 0);
        }

        return $ancestors;
    }

    /**
     * @param list<array<string, mixed>> $awards
     * @return list<array<string, mixed>>
     */
    private function removeDuplicateRecipients(array $awards): array
    {
        $seen = [];
        $filtered = [];
        foreach ($awards as $award) {
            $recipientKey = (string)($award['recipient_common_user_id'] ?? '');
            if ($recipientKey === '') {
                $recipientKey = 'agent:' . (string)($award['recipient_agent_id'] ?? '');
            }
            if ($recipientKey !== '' && isset($seen[$recipientKey])) {
                continue;
            }
            $seen[$recipientKey] = true;
            $filtered[] = $award;
        }

        return $filtered;
    }

    /**
     * @param list<array<string, mixed>> $awards
     */
    private function findRecipientAgentId(array $awards, string $recipientType): ?int
    {
        foreach ($awards as $award) {
            if (($award['recipient_type'] ?? '') !== $recipientType) {
                continue;
            }
            $agentId = (int)($award['recipient_agent_id'] ?? 0);
            return $agentId > 0 ? $agentId : null;
        }

        return null;
    }

    private function buildAttendanceTriggerId(string $seminarId, string $commonUserId): string
    {
        return 'seminar_attendance:' . hash('sha256', $seminarId . '|' . $commonUserId);
    }

    private function normalizeOccurredAt(string $occurredAt): string
    {
        if ($occurredAt === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($occurredAt);
        return $timestamp === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $timestamp);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        return trim((string)($values[$key] ?? $default));
    }
}

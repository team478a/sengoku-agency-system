<?php

declare(strict_types=1);

namespace SenNoKuni\Point;

use PDO;

final class OrlyReferralPointAwardService
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
    public function recordReferralConfirmed(array $context): array
    {
        $campaigns = $this->campaigns ?? new OrlyReferralPointCampaignRepository($this->pdo);
        if (!$campaigns->tablesReady()) {
            return ['ok' => true, 'status' => 'schema_not_ready', 'saved' => []];
        }

        $activeCampaign = $campaigns->loadActiveReferralSignupCampaign();
        if ($activeCampaign === null) {
            return ['ok' => true, 'status' => 'campaign_inactive', 'saved' => []];
        }

        $directReferrer = $this->loadAgent((int)($context['direct_referrer_agent_id'] ?? 0));
        $ancestorAgents = $this->loadAncestors($directReferrer !== null ? (int)($directReferrer['parent_id'] ?? 0) : 0);
        $projectKey = trim((string)($context['project_key'] ?? ''));
        if ($projectKey === '') {
            $projectKey = $this->loadProjectKey((int)($context['project_id'] ?? 0));
        }

        $triggerEventId = trim((string)($context['trigger_event_id'] ?? ''));
        if ($triggerEventId === '') {
            $triggerEventId = $this->buildFallbackTriggerId($context);
        }

        $plan = ($this->planner ?? new OrlyReferralPointAwardPlanner())->plan([
            'campaign' => $activeCampaign['campaign'],
            'campaign_version' => $activeCampaign['campaign_version'],
            'direct_referrer_agent' => $directReferrer,
            'ancestor_agents' => $ancestorAgents,
            'trigger_event_type' => 'referral.confirmed',
            'trigger_event_id' => $triggerEventId,
            'target_common_user_id' => (string)($context['target_common_user_id'] ?? ''),
            'source_system_key' => (string)($context['source_system_key'] ?? ''),
            'project_key' => $projectKey,
        ]);

        $directReferrerId = $directReferrer !== null ? (int)$directReferrer['id'] : null;
        $upperDirectorId = $this->findRecipientAgentId($plan, 'upper_director');
        $awards = [];
        foreach ($plan as $award) {
            $snapshot = [
                'campaign_key' => (string)($activeCampaign['campaign']['campaign_key'] ?? 'orly_referral_signup'),
                'campaign_version_no' => (int)($activeCampaign['campaign_version']['version_no'] ?? 1),
                'direct_referrer' => $directReferrer ? [
                    'agent_id' => (int)$directReferrer['id'],
                    'agent_code' => (string)($directReferrer['agent_code'] ?? $directReferrer['code'] ?? ''),
                    'role' => (string)($directReferrer['position_type'] ?? ''),
                    'level' => (int)($directReferrer['level'] ?? 0),
                ] : null,
                'upper_director_agent_id' => $upperDirectorId,
                'project_key' => $projectKey,
                'source_system_key' => (string)($context['source_system_key'] ?? ''),
                'trigger_event_id' => $triggerEventId,
            ];

            $award['point_code'] = $award['currency_code'] ?? 'orly';
            $award['target_common_user_id'] = (string)($context['target_common_user_id'] ?? '');
            $award['direct_referrer_agent_id'] = $directReferrerId;
            $award['upper_director_agent_id'] = $upperDirectorId;
            $award['status'] = 'pending';
            $award['referral_snapshot'] = $snapshot;
            $award['correlation_id'] = trim((string)($context['correlation_id'] ?? '')) ?: null;
            $award['occurred_at'] = trim((string)($context['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s');
            $awards[] = $award;
        }

        $saved = ($this->events ?? new PointAwardEventRepository($this->pdo))->savePlannedAwards($awards);
        foreach ($saved as $result) {
            if (($result['status'] ?? '') === 'conflict') {
                return ['ok' => false, 'status' => 'conflict', 'saved' => $saved];
            }
        }

        return ['ok' => true, 'status' => empty($saved) ? 'no_awards' : 'recorded', 'saved' => $saved];
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

    private function loadProjectKey(int $projectId): string
    {
        if ($projectId <= 0 || !$this->tableExists('projects')) {
            return '';
        }

        $columns = function_exists('tableColumns') ? tableColumns('projects') : [];
        if (!in_array('slug', $columns, true)) {
            return '';
        }

        $stmt = $this->pdo->prepare('SELECT slug FROM projects WHERE id=? LIMIT 1');
        $stmt->execute([$projectId]);

        return trim((string)($stmt->fetchColumn() ?: ''));
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

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFallbackTriggerId(array $context): string
    {
        return 'referral-confirm:' . hash('sha256', implode('|', [
            'session:' . trim((string)($context['referral_session_key'] ?? '')),
            'token:' . (string)(int)($context['referral_token_id'] ?? 0),
            'common:' . trim((string)($context['target_common_user_id'] ?? '')),
            'source:' . trim((string)($context['source_system_key'] ?? '')),
            'project:' . (string)(int)($context['project_id'] ?? 0),
        ]));
    }
}

<?php

declare(strict_types=1);

namespace SenNoKuni\Point;

final class OrlyReferralPointAwardPlanner
{
    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    public function plan(array $context): array
    {
        $campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : [];
        $version = is_array($context['campaign_version'] ?? null) ? $context['campaign_version'] : [];
        $directReferrer = is_array($context['direct_referrer_agent'] ?? null) ? $context['direct_referrer_agent'] : null;
        $ancestors = is_array($context['ancestor_agents'] ?? null) ? $context['ancestor_agents'] : [];

        $campaignKey = $this->stringValue($campaign, 'campaign_key', 'orly_referral_signup');
        $currencyCode = $this->stringValue($campaign, 'currency_code', 'orly');
        $campaignId = $this->intValue($campaign, 'id');
        $campaignVersionId = $this->intValue($version, 'id');
        $versionNo = max(1, $this->intValue($version, 'version_no', 1));
        $triggerEventType = trim((string)($context['trigger_event_type'] ?? 'referral.confirmed'));
        $triggerEventId = trim((string)($context['trigger_event_id'] ?? ''));
        $targetCommonUserId = trim((string)($context['target_common_user_id'] ?? ''));
        $projectKey = trim((string)($context['project_key'] ?? ''));
        $sourceSystemKey = trim((string)($context['source_system_key'] ?? ''));

        $awards = [];
        $targetRecipientType = trim((string)($context['target_recipient_type'] ?? 'registrant'));
        if ($targetRecipientType === '') {
            $targetRecipientType = 'registrant';
        }
        $targetPoints = array_key_exists('target_points', $context)
            ? (int)$context['target_points']
            : $this->intValue($version, 'registrant_points');
        $registrantPoints = max(0, $targetPoints);
        if ($targetCommonUserId !== '' && $registrantPoints > 0) {
            $awards[] = $this->buildAward([
                'campaign_id' => $campaignId,
                'campaign_version_id' => $campaignVersionId,
                'campaign_key' => $campaignKey,
                'campaign_version_no' => $versionNo,
                'currency_code' => $currencyCode,
                'recipient_type' => $targetRecipientType,
                'recipient_common_user_id' => $targetCommonUserId,
                'recipient_agent_id' => null,
                'points' => $registrantPoints,
                'trigger_event_type' => $triggerEventType,
                'trigger_event_id' => $triggerEventId,
                'source_system_key' => $sourceSystemKey,
                'project_key' => $projectKey,
            ]);
        }

        $directPoints = max(0, $this->intValue($version, 'direct_referrer_points'));
        if ($directReferrer !== null && $directPoints > 0 && $this->isAwardableAgent($directReferrer)) {
            $awards[] = $this->buildAward([
                'campaign_id' => $campaignId,
                'campaign_version_id' => $campaignVersionId,
                'campaign_key' => $campaignKey,
                'campaign_version_no' => $versionNo,
                'currency_code' => $currencyCode,
                'recipient_type' => 'direct_referrer',
                'recipient_common_user_id' => $this->nullableStringValue($directReferrer, 'common_user_id'),
                'recipient_agent_id' => $this->intValue($directReferrer, 'id'),
                'recipient_agent_code' => $this->stringValue($directReferrer, 'code'),
                'points' => $directPoints,
                'trigger_event_type' => $triggerEventType,
                'trigger_event_id' => $triggerEventId,
                'source_system_key' => $sourceSystemKey,
                'project_key' => $projectKey,
            ]);
        }

        $upperDirector = $this->findUpperDirector($ancestors, $directReferrer);
        $upperDirectorPoints = max(0, $this->intValue($version, 'upper_director_points'));
        if ($upperDirector !== null && $upperDirectorPoints > 0) {
            $awards[] = $this->buildAward([
                'campaign_id' => $campaignId,
                'campaign_version_id' => $campaignVersionId,
                'campaign_key' => $campaignKey,
                'campaign_version_no' => $versionNo,
                'currency_code' => $currencyCode,
                'recipient_type' => 'upper_director',
                'recipient_common_user_id' => $this->nullableStringValue($upperDirector, 'common_user_id'),
                'recipient_agent_id' => $this->intValue($upperDirector, 'id'),
                'recipient_agent_code' => $this->stringValue($upperDirector, 'code'),
                'points' => $upperDirectorPoints,
                'trigger_event_type' => $triggerEventType,
                'trigger_event_id' => $triggerEventId,
                'source_system_key' => $sourceSystemKey,
                'project_key' => $projectKey,
            ]);
        }

        return $awards;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function buildAward(array $values): array
    {
        return [
            'award_event_key' => $this->awardEventKey(
                (string)$values['campaign_key'],
                (int)$values['campaign_version_no'],
                (string)$values['trigger_event_type'],
                (string)$values['trigger_event_id'],
                (string)$values['recipient_type'],
                (string)($values['recipient_common_user_id'] ?? ''),
                (int)($values['recipient_agent_id'] ?? 0),
                (string)($values['project_key'] ?? '')
            ),
            'campaign_id' => $values['campaign_id'],
            'campaign_version_id' => $values['campaign_version_id'],
            'campaign_key' => $values['campaign_key'],
            'campaign_version_no' => $values['campaign_version_no'],
            'currency_code' => $values['currency_code'],
            'recipient_type' => $values['recipient_type'],
            'recipient_common_user_id' => $values['recipient_common_user_id'] ?? null,
            'recipient_agent_id' => $values['recipient_agent_id'] ?? null,
            'recipient_agent_code' => $values['recipient_agent_code'] ?? '',
            'points' => $values['points'],
            'trigger_event_type' => $values['trigger_event_type'],
            'trigger_event_id' => $values['trigger_event_id'],
            'source_system_key' => $values['source_system_key'],
            'project_key' => $values['project_key'],
            'wallet_delivery_status' => 'pending',
        ];
    }

    private function awardEventKey(
        string $campaignKey,
        int $versionNo,
        string $triggerEventType,
        string $triggerEventId,
        string $recipientType,
        string $recipientCommonUserId,
        int $recipientAgentId,
        string $projectKey
    ): string {
        return 'orly_' . hash('sha256', implode('|', [
            $campaignKey,
            (string)$versionNo,
            $triggerEventType,
            $triggerEventId,
            $recipientType,
            $recipientCommonUserId,
            (string)$recipientAgentId,
            $projectKey,
        ]));
    }

    /**
     * @param list<array<string, mixed>> $ancestors
     * @param ?array<string, mixed> $directReferrer
     * @return ?array<string, mixed>
     */
    private function findUpperDirector(array $ancestors, ?array $directReferrer): ?array
    {
        $directReferrerId = $directReferrer === null ? 0 : $this->intValue($directReferrer, 'id');

        foreach ($ancestors as $ancestor) {
            if (!$this->isAwardableAgent($ancestor)) {
                continue;
            }
            if ($this->intValue($ancestor, 'id') === $directReferrerId) {
                continue;
            }
            if ($this->isDirector($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $agent
     */
    private function isDirector(array $agent): bool
    {
        $position = strtolower(trim((string)($agent['position_type'] ?? $agent['type'] ?? $agent['role'] ?? '')));
        if ($position === 'director') {
            return true;
        }

        return $this->intValue($agent, 'level') === 2 || $this->intValue($agent, 'role_level') === 2;
    }

    /**
     * @param array<string, mixed> $agent
     */
    private function isAwardableAgent(array $agent): bool
    {
        if ($this->intValue($agent, 'id') <= 0) {
            return false;
        }

        $status = strtolower(trim((string)($agent['status'] ?? 'active')));
        if ($status === '') {
            return true;
        }

        return in_array($status, ['active', 'approved', 'published'], true);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        return trim((string)($values[$key] ?? $default));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function nullableStringValue(array $values, string $key): ?string
    {
        $value = $this->stringValue($values, $key);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
            return $default;
        }

        return (int)$values[$key];
    }
}

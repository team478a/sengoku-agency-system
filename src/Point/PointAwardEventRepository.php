<?php

declare(strict_types=1);

namespace SenNoKuni\Point;

use InvalidArgumentException;
use PDO;

final class PointAwardEventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<array<string, mixed>> $awards
     * @return list<array<string, mixed>>
     */
    public function savePlannedAwards(array $awards): array
    {
        $results = [];
        foreach ($awards as $award) {
            $results[] = $this->savePlannedAward($award);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $award
     * @return array<string, mixed>
     */
    public function savePlannedAward(array $award): array
    {
        $awardEventKey = $this->stringValue($award, 'award_event_key');
        if ($awardEventKey === '') {
            throw new InvalidArgumentException('award_event_key is required.');
        }

        $payloadHash = $this->payloadHash($award);
        $existing = $this->findByAwardEventKey($awardEventKey);
        if ($existing !== null) {
            if (!$this->matchesExisting($existing, $award, $payloadHash)) {
                return [
                    'ok' => false,
                    'status' => 'conflict',
                    'award_event_key' => $awardEventKey,
                    'id' => (int)$existing['id'],
                ];
            }

            return [
                'ok' => true,
                'status' => 'idempotent',
                'award_event_key' => $awardEventKey,
                'id' => (int)$existing['id'],
            ];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO point_award_events (
                award_event_key,
                campaign_id,
                campaign_version_id,
                point_code,
                recipient_common_user_id,
                recipient_agent_id,
                recipient_type,
                target_common_user_id,
                trigger_event_type,
                trigger_event_id,
                source_system_key,
                project_key,
                direct_referrer_agent_id,
                upper_director_agent_id,
                points,
                status,
                wallet_event_id,
                outbox_event_id,
                referral_snapshot_json,
                payload_hash,
                correlation_id,
                occurred_at
            ) VALUES (
                :award_event_key,
                :campaign_id,
                :campaign_version_id,
                :point_code,
                :recipient_common_user_id,
                :recipient_agent_id,
                :recipient_type,
                :target_common_user_id,
                :trigger_event_type,
                :trigger_event_id,
                :source_system_key,
                :project_key,
                :direct_referrer_agent_id,
                :upper_director_agent_id,
                :points,
                :status,
                :wallet_event_id,
                :outbox_event_id,
                :referral_snapshot_json,
                :payload_hash,
                :correlation_id,
                :occurred_at
            )
        ");
        $stmt->execute([
            ':award_event_key' => $awardEventKey,
            ':campaign_id' => $this->intValue($award, 'campaign_id'),
            ':campaign_version_id' => $this->nullableIntValue($award, 'campaign_version_id'),
            ':point_code' => $this->stringValue($award, 'point_code', $this->stringValue($award, 'currency_code', 'orly')),
            ':recipient_common_user_id' => $this->nullableStringValue($award, 'recipient_common_user_id'),
            ':recipient_agent_id' => $this->nullableIntValue($award, 'recipient_agent_id'),
            ':recipient_type' => $this->stringValue($award, 'recipient_type'),
            ':target_common_user_id' => $this->nullableStringValue($award, 'target_common_user_id'),
            ':trigger_event_type' => $this->stringValue($award, 'trigger_event_type'),
            ':trigger_event_id' => $this->nullableStringValue($award, 'trigger_event_id'),
            ':source_system_key' => $this->nullableStringValue($award, 'source_system_key'),
            ':project_key' => $this->nullableStringValue($award, 'project_key'),
            ':direct_referrer_agent_id' => $this->nullableIntValue($award, 'direct_referrer_agent_id'),
            ':upper_director_agent_id' => $this->nullableIntValue($award, 'upper_director_agent_id'),
            ':points' => $this->intValue($award, 'points'),
            ':status' => $this->stringValue($award, 'status', 'pending'),
            ':wallet_event_id' => $this->nullableStringValue($award, 'wallet_event_id'),
            ':outbox_event_id' => $this->nullableStringValue($award, 'outbox_event_id'),
            ':referral_snapshot_json' => $this->jsonValue($award['referral_snapshot_json'] ?? $award['referral_snapshot'] ?? null),
            ':payload_hash' => $payloadHash,
            ':correlation_id' => $this->nullableStringValue($award, 'correlation_id'),
            ':occurred_at' => $this->nullableStringValue($award, 'occurred_at'),
        ]);

        return [
            'ok' => true,
            'status' => 'created',
            'award_event_key' => $awardEventKey,
            'id' => (int)$this->pdo->lastInsertId(),
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function findByAwardEventKey(string $awardEventKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM point_award_events WHERE award_event_key=? LIMIT 1');
        $stmt->execute([$awardEventKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $award
     */
    private function matchesExisting(array $existing, array $award, string $payloadHash): bool
    {
        if ($this->stringValue($existing, 'payload_hash') !== '' && $this->stringValue($existing, 'payload_hash') !== $payloadHash) {
            return false;
        }

        return $this->intValue($existing, 'campaign_id') === $this->intValue($award, 'campaign_id')
            && $this->nullableIntValue($existing, 'campaign_version_id') === $this->nullableIntValue($award, 'campaign_version_id')
            && $this->stringValue($existing, 'point_code', 'orly') === $this->stringValue($award, 'point_code', $this->stringValue($award, 'currency_code', 'orly'))
            && $this->nullableStringValue($existing, 'recipient_common_user_id') === $this->nullableStringValue($award, 'recipient_common_user_id')
            && $this->nullableIntValue($existing, 'recipient_agent_id') === $this->nullableIntValue($award, 'recipient_agent_id')
            && $this->stringValue($existing, 'recipient_type') === $this->stringValue($award, 'recipient_type')
            && $this->nullableStringValue($existing, 'target_common_user_id') === $this->nullableStringValue($award, 'target_common_user_id')
            && $this->stringValue($existing, 'trigger_event_type') === $this->stringValue($award, 'trigger_event_type')
            && $this->nullableStringValue($existing, 'trigger_event_id') === $this->nullableStringValue($award, 'trigger_event_id')
            && $this->nullableStringValue($existing, 'source_system_key') === $this->nullableStringValue($award, 'source_system_key')
            && $this->nullableStringValue($existing, 'project_key') === $this->nullableStringValue($award, 'project_key')
            && $this->intValue($existing, 'points') === $this->intValue($award, 'points');
    }

    /**
     * @param array<string, mixed> $award
     */
    private function payloadHash(array $award): string
    {
        $payload = [
            'campaign_id' => $this->intValue($award, 'campaign_id'),
            'campaign_version_id' => $this->nullableIntValue($award, 'campaign_version_id'),
            'point_code' => $this->stringValue($award, 'point_code', $this->stringValue($award, 'currency_code', 'orly')),
            'recipient_common_user_id' => $this->nullableStringValue($award, 'recipient_common_user_id'),
            'recipient_agent_id' => $this->nullableIntValue($award, 'recipient_agent_id'),
            'recipient_type' => $this->stringValue($award, 'recipient_type'),
            'target_common_user_id' => $this->nullableStringValue($award, 'target_common_user_id'),
            'trigger_event_type' => $this->stringValue($award, 'trigger_event_type'),
            'trigger_event_id' => $this->nullableStringValue($award, 'trigger_event_id'),
            'source_system_key' => $this->nullableStringValue($award, 'source_system_key'),
            'project_key' => $this->nullableStringValue($award, 'project_key'),
            'points' => $this->intValue($award, 'points'),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', is_string($json) ? $json : '{}');
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

    /**
     * @param array<string, mixed> $values
     */
    private function nullableIntValue(array $values, string $key): ?int
    {
        if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
            return null;
        }

        return (int)$values[$key];
    }

    private function jsonValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }
}

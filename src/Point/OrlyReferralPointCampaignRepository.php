<?php

declare(strict_types=1);

namespace SenNoKuni\Point;

use PDO;

final class OrlyReferralPointCampaignRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function tablesReady(): bool
    {
        foreach (['point_campaigns', 'point_campaign_versions', 'point_award_events'] as $table) {
            if (!$this->tableExists($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return ?array{campaign: array<string, mixed>, campaign_version: array<string, mixed>}
     */
    public function loadActiveReferralSignupCampaign(): ?array
    {
        return $this->loadActiveCampaign('orly_referral_signup');
    }

    /**
     * @return ?array{campaign: array<string, mixed>, campaign_version: array<string, mixed>}
     */
    public function loadActiveSeminarAttendanceCampaign(): ?array
    {
        return $this->loadActiveCampaign('orly_seminar_attendance');
    }

    /**
     * @return ?array{campaign: array<string, mixed>, campaign_version: array<string, mixed>}
     */
    public function loadActiveCampaign(string $campaignKey): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                c.id,
                c.campaign_key,
                c.point_currency_code,
                c.name,
                c.status,
                v.id AS version_id,
                v.version_no,
                v.registrant_points,
                v.direct_referrer_points,
                v.upper_director_points,
                v.rule_json,
                v.status AS version_status,
                v.activated_at
            FROM point_campaigns c
            INNER JOIN point_campaign_versions v ON v.campaign_id = c.id
            WHERE c.campaign_key = ?
              AND c.status = 'active'
              AND v.status = 'active'
              AND (c.active_version_id IS NULL OR c.active_version_id = v.id)
              AND (c.starts_at IS NULL OR c.starts_at <= NOW())
              AND (c.ends_at IS NULL OR c.ends_at >= NOW())
            ORDER BY COALESCE(c.active_version_id, v.id) DESC, v.version_no DESC
            LIMIT 1
        ");
        $stmt->execute([$campaignKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'campaign' => [
                'id' => (int)$row['id'],
                'campaign_key' => (string)$row['campaign_key'],
                'currency_code' => (string)$row['point_currency_code'],
                'point_currency_code' => (string)$row['point_currency_code'],
                'name' => (string)$row['name'],
                'status' => (string)$row['status'],
            ],
            'campaign_version' => [
                'id' => (int)$row['version_id'],
                'version_no' => (int)$row['version_no'],
                'registrant_points' => (int)$row['registrant_points'],
                'direct_referrer_points' => (int)$row['direct_referrer_points'],
                'upper_director_points' => (int)$row['upper_director_points'],
                'rule_json' => $row['rule_json'] ?? null,
                'status' => (string)$row['version_status'],
                'activated_at' => $row['activated_at'] ?? null,
            ],
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return (bool)$stmt->fetchColumn();
    }
}

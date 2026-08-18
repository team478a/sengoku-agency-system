<?php

declare(strict_types=1);

namespace SenNoKuni\CommonIdentity;

final class AgencyCustomerRelationRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function loadByCommonUserId(string $commonUserId): array
    {
        if ($commonUserId === '' || empty(\tableColumns('agency_customer_relations'))) {
            return [];
        }

        $stmt = \getDB()->prepare("
            SELECT r.*, a.agent_code, a.agent_name, a.person_name, p.slug AS project_slug, p.name AS project_name
            FROM agency_customer_relations r
            LEFT JOIN agents a ON r.agent_id=a.id
            LEFT JOIN projects p ON r.project_id=p.id
            WHERE r.common_user_id=?
            ORDER BY r.updated_at DESC, r.id DESC
        ");
        $stmt->execute([$commonUserId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function save(array $data): array
    {
        if (!\commonIdTablesReady()) {
            throw new \RuntimeException('Common ID tables are not migrated.');
        }

        $commonUserId = \ensureCommonUser($data['common_user_id'] ?? null, $data);
        $agentId = !empty($data['agent_id']) ? (int)$data['agent_id'] : null;
        $projectId = !empty($data['project_id']) ? (int)$data['project_id'] : 0;
        $relationType = trim((string)($data['relation_type'] ?? 'referral')) ?: 'referral';
        $sourceServiceKey = trim((string)($data['source_service_key'] ?? '')) ?: null;
        $sourceServiceUserId = trim((string)($data['source_service_user_id'] ?? '')) ?: null;
        $referralTokenId = !empty($data['referral_token_id']) ? (int)$data['referral_token_id'] : null;
        $referralSource = trim((string)($data['referral_source'] ?? '')) ?: null;
        $locked = array_key_exists('locked', $data) ? ((int)!empty($data['locked'])) : 1;

        $db = \getDB();
        $stmt = $db->prepare("
            INSERT INTO agency_customer_relations
                (common_user_id, agent_id, project_id, relation_type, source_service_key, source_service_user_id, referral_token_id, referral_source, locked, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE
                agent_id = IF(locked=1 AND agent_id IS NOT NULL, agent_id, VALUES(agent_id)),
                project_id = VALUES(project_id),
                source_service_key = COALESCE(source_service_key, VALUES(source_service_key)),
                source_service_user_id = COALESCE(source_service_user_id, VALUES(source_service_user_id)),
                referral_token_id = COALESCE(referral_token_id, VALUES(referral_token_id)),
                referral_source = COALESCE(referral_source, VALUES(referral_source)),
                locked = GREATEST(locked, VALUES(locked)),
                status = 'active',
                updated_at = NOW()
        ");
        $stmt->execute([
            $commonUserId,
            $agentId,
            $projectId,
            $relationType,
            $sourceServiceKey,
            $sourceServiceUserId,
            $referralTokenId,
            $referralSource,
            $locked,
        ]);

        $load = $db->prepare("SELECT * FROM agency_customer_relations WHERE common_user_id=? AND relation_type=? AND project_id=? LIMIT 1");
        $load->execute([$commonUserId, $relationType, $projectId]);
        return $load->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
}

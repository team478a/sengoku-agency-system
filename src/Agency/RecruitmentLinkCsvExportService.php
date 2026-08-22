<?php

declare(strict_types=1);

namespace SenNoKuni\Agency;

use PDO;

final class RecruitmentLinkCsvExportService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<int|string, string> $levelLabels
     * @param callable(mixed, mixed): string $advisorLabelResolver
     * @return array<int, array<int, mixed>>
     */
    public function rows(int $ownerId, string $baseUrl, array $levelLabels, callable $advisorLabelResolver): array
    {
        $stmt = $this->db->prepare("
            SELECT rl.*,
                   COUNT(ap.id) AS applicant_count,
                   SUM(CASE WHEN ap.status='pending' THEN 1 ELSE 0 END) AS pending_count,
                   SUM(CASE WHEN ap.status='approved' THEN 1 ELSE 0 END) AS approved_count
            FROM recruitment_links rl
            LEFT JOIN applicants ap ON ap.recruitment_link_id = rl.id
            WHERE rl.agent_id=?
            GROUP BY rl.id
            ORDER BY rl.created_at DESC
        ");
        $stmt->execute([$ownerId]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
            $targetLevel = (int)($link['target_level'] ?? 0);
            $target = $targetLevel === 1
                ? $advisorLabelResolver($link['position_type'] ?? null, $link['position_label'] ?? null)
                : ($levelLabels[$targetLevel] ?? '');

            $rows[] = [
                $link['name'] ?? '',
                $target,
                rtrim($baseUrl, '/') . '/join/' . ($link['token'] ?? ''),
                (int)($link['click_count'] ?? 0),
                (int)($link['applicant_count'] ?? 0),
                (int)($link['pending_count'] ?? 0),
                (int)($link['approved_count'] ?? 0),
                $link['status'] ?? '',
                $link['expires_at'] ?? '',
                $link['created_at'] ?? '',
            ];
        }

        return $rows;
    }
}

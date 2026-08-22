<?php

declare(strict_types=1);

namespace SenNoKuni\Reporting;

use PDO;
use Throwable;

final class TemplateReportCsvExportService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function rows(?int $days): array
    {
        $accessColumns = $this->tableColumns('access_logs');
        $leadColumns = $this->tableColumns('leads');
        $accessTemplateExpr = !empty($accessColumns['template_id']) ? 'COALESCE(al.template_id, a.default_template_id)' : 'a.default_template_id';
        $leadTemplateExpr = !empty($leadColumns['template_id']) ? 'COALESCE(l.template_id, a.default_template_id)' : 'a.default_template_id';

        $dateSqlAccess = '';
        $dateSqlLead = '';
        $dateParamsAccess = [];
        $dateParamsLead = [];
        if ($days !== null) {
            $dateSqlAccess = ' AND al.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $dateSqlLead = ' AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $dateParamsAccess[] = $days;
            $dateParamsLead[] = $days;
        }

        $templates = $this->templates();
        $pvMap = $this->accessCountMap('pv', $accessTemplateExpr, $dateSqlAccess, $dateParamsAccess);
        $lineMap = $this->accessCountMap('line_click', $accessTemplateExpr, $dateSqlAccess, $dateParamsAccess);
        $leadMap = $this->leadCountMap($leadTemplateExpr, $dateSqlLead, $dateParamsLead);

        $rows = [];
        foreach ($templates as $template) {
            $templateId = (int)$template['id'];
            $pv = $pvMap[$templateId] ?? 0;
            $leads = (int)($leadMap[$templateId]['cnt'] ?? 0);
            $rows[] = [
                $template['name'] ?? '',
                $template['slug'] ?? '',
                $template['html_file'] ?? '',
                (int)($template['active_agent_count'] ?? 0),
                $pv,
                $lineMap[$templateId] ?? 0,
                $leads,
                (int)($leadMap[$templateId]['new_cnt'] ?? 0),
                (int)($leadMap[$templateId]['prospect_cnt'] ?? 0),
                (int)($leadMap[$templateId]['won_cnt'] ?? 0),
                $pv > 0 ? round(($leads / $pv) * 100, 2) . '%' : '',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return $this->db->query("
            SELECT t.*, COUNT(DISTINCT a.id) AS active_agent_count
            FROM lp_templates t
            LEFT JOIN agents a ON a.default_template_id=t.id AND a.status='active'
            GROUP BY t.id
            ORDER BY t.sort_order ASC, t.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, int> $params
     * @return array<int, int>
     */
    private function accessCountMap(string $type, string $templateExpr, string $dateSql, array $params): array
    {
        $stmt = $this->db->prepare("
            SELECT {$templateExpr} AS template_id, COUNT(*) AS cnt
            FROM access_logs al
            LEFT JOIN agents a ON a.id=al.agent_id
            WHERE al.type=? {$dateSql}
            GROUP BY {$templateExpr}
        ");
        $stmt->execute(array_merge([$type], $params));

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['template_id']] = (int)$row['cnt'];
        }

        return $map;
    }

    /**
     * @param array<int, int> $params
     * @return array<int, array<string, mixed>>
     */
    private function leadCountMap(string $templateExpr, string $dateSql, array $params): array
    {
        $stmt = $this->db->prepare("
            SELECT {$templateExpr} AS template_id,
                   COUNT(*) AS cnt,
                   SUM(CASE WHEN l.status='new' THEN 1 ELSE 0 END) AS new_cnt,
                   SUM(CASE WHEN l.status='prospect' THEN 1 ELSE 0 END) AS prospect_cnt,
                   SUM(CASE WHEN l.status='won' THEN 1 ELSE 0 END) AS won_cnt
            FROM leads l
            LEFT JOIN agents a ON a.id=l.agent_id
            WHERE 1=1 {$dateSql}
            GROUP BY {$templateExpr}
        ");
        $stmt->execute($params);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['template_id']] = $row;
        }

        return $map;
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
            error_log('Template report CSV column check failed: ' . $e->getMessage());
        }

        return $columns;
    }
}

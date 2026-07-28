<?php

declare(strict_types=1);

namespace SenNoKuni\LandingPage;

use PDO;
use Throwable;

final class LandingPageTemplateRepository
{
    /**
     * @param array<string,bool> $templateColumns
     */
    public function __construct(
        private readonly PDO $db,
        private readonly array $templateColumns = []
    ) {
    }

    public function hasProjectColumn(): bool
    {
        return !empty($this->templateColumns['project_id']);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function activeTemplates(): array
    {
        if ($this->hasProjectColumn()) {
            $stmt = $this->db->query("
                SELECT t.*, p.name AS project_name
                FROM lp_templates t
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.status = 'active'
                ORDER BY COALESCE(p.sort_order, 9999) ASC, t.sort_order ASC
            ");
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query("SELECT * FROM lp_templates WHERE status = 'active' ORDER BY sort_order ASC");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM lp_templates WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function activeProjectTemplate(int $templateId, int $projectId): ?array
    {
        if (!$this->hasProjectColumn()) {
            return $this->find($templateId);
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM lp_templates
            WHERE id = ? AND project_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$templateId, $projectId]);
        return $stmt->fetch() ?: null;
    }

    public function firstActiveForProject(int $projectId): ?array
    {
        if (!$this->hasProjectColumn()) {
            $stmt = $this->db->query("
                SELECT *
                FROM lp_templates
                WHERE status = 'active'
                ORDER BY sort_order ASC, id ASC
                LIMIT 1
            ");
            return $stmt->fetch() ?: null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM lp_templates
            WHERE project_id = ? AND status = 'active'
            ORDER BY sort_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetch() ?: null;
    }

    public function projectIdForTemplate(int $templateId): int
    {
        if ($templateId <= 0 || !$this->hasProjectColumn()) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare("SELECT project_id FROM lp_templates WHERE id = ? LIMIT 1");
            $stmt->execute([$templateId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    public function seoSource(int $templateId): array
    {
        if ($templateId <= 0) {
            return [];
        }

        try {
            if ($this->hasProjectColumn()) {
                $stmt = $this->db->prepare("
                    SELECT t.*, p.slug AS project_slug, p.name AS project_name, p.description AS project_description
                    FROM lp_templates t
                    LEFT JOIN projects p ON t.project_id = p.id
                    WHERE t.id = ?
                    LIMIT 1
                ");
                $stmt->execute([$templateId]);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }

            $stmt = $this->db->prepare("SELECT * FROM lp_templates WHERE id = ? LIMIT 1");
            $stmt->execute([$templateId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    public function fieldsReady(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM lp_template_fields LIMIT 1");
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function fields(int $templateId): array
    {
        if ($templateId <= 0 || !$this->fieldsReady()) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT * FROM lp_template_fields WHERE template_id = ?");
        $stmt->execute([$templateId]);

        $fields = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fields[(string)$row['field_key']] = $row;
        }
        return $fields;
    }

    public function upsertField(
        int $templateId,
        string $key,
        string $type,
        string $label,
        ?string $textValue,
        ?string $fileValue
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO lp_template_fields (template_id, field_key, field_type, label, value_text, value_file)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                field_type = VALUES(field_type),
                label = VALUES(label),
                value_text = VALUES(value_text),
                value_file = VALUES(value_file)
        ");
        $stmt->execute([$templateId, $key, $type, $label, $textValue, $fileValue]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function adminList(): array
    {
        if ($this->hasProjectColumn()) {
            $stmt = $this->db->query("
                SELECT t.*, p.name AS project_name
                FROM lp_templates t
                LEFT JOIN projects p ON t.project_id = p.id
                ORDER BY COALESCE(p.sort_order, 9999) ASC, t.sort_order ASC, t.id ASC
            ");
            return $stmt->fetchAll();
        }

        return $this->db
            ->query("SELECT * FROM lp_templates ORDER BY sort_order ASC, id ASC")
            ->fetchAll();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): void
    {
        if ($this->hasProjectColumn()) {
            $stmt = $this->db->prepare("
                INSERT INTO lp_templates (project_id, slug, name, description, html_file, thumbnail_url, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)($data['project_id'] ?? 0),
                (string)($data['slug'] ?? ''),
                (string)($data['name'] ?? ''),
                (string)($data['description'] ?? ''),
                (string)($data['html_file'] ?? ''),
                (string)($data['thumbnail_url'] ?? ''),
                (int)($data['sort_order'] ?? 0),
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO lp_templates (slug, name, description, html_file, thumbnail_url, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (string)($data['slug'] ?? ''),
            (string)($data['name'] ?? ''),
            (string)($data['description'] ?? ''),
            (string)($data['html_file'] ?? ''),
            (string)($data['thumbnail_url'] ?? ''),
            (int)($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        if ($this->hasProjectColumn()) {
            $stmt = $this->db->prepare("
                UPDATE lp_templates
                SET project_id = ?, slug = ?, name = ?, description = ?, html_file = ?, thumbnail_url = ?, sort_order = ?
                WHERE id = ?
            ");
            $stmt->execute([
                (int)($data['project_id'] ?? 0),
                (string)($data['slug'] ?? ''),
                (string)($data['name'] ?? ''),
                (string)($data['description'] ?? ''),
                (string)($data['html_file'] ?? ''),
                (string)($data['thumbnail_url'] ?? ''),
                (int)($data['sort_order'] ?? 0),
                $id,
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE lp_templates
            SET slug = ?, name = ?, description = ?, html_file = ?, thumbnail_url = ?, sort_order = ?
            WHERE id = ?
        ");
        $stmt->execute([
            (string)($data['slug'] ?? ''),
            (string)($data['name'] ?? ''),
            (string)($data['description'] ?? ''),
            (string)($data['html_file'] ?? ''),
            (string)($data['thumbnail_url'] ?? ''),
            (int)($data['sort_order'] ?? 0),
            $id,
        ]);
    }

    public function toggleStatus(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE lp_templates
            SET status = IF(status = 'active', 'inactive', 'active')
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM lp_templates WHERE id = ?");
        $stmt->execute([$id]);
    }
}

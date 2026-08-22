<?php

declare(strict_types=1);

namespace SenNoKuni\Activity;

use Closure;
use PDO;
use Throwable;

final class AccessLogRecorder
{
    private readonly Closure $lpTemplateColumnExists;

    private readonly Closure $errorLogger;

    /**
     * @param array<string, bool> $accessLogColumns
     * @param callable(string): bool $lpTemplateColumnExists
     * @param callable(string): void $errorLogger
     */
    public function __construct(
        private readonly PDO $db,
        private readonly array $accessLogColumns,
        callable $lpTemplateColumnExists,
        callable $errorLogger,
    ) {
        $this->lpTemplateColumnExists = Closure::fromCallable($lpTemplateColumnExists);
        $this->errorLogger = Closure::fromCallable($errorLogger);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $server
     */
    public function record(
        int $agentId,
        string $type = 'pv',
        ?int $templateId = null,
        array $context = [],
        array $server = []
    ): void {
        try {
            $ipHash = hash('sha256', (string)($server['REMOTE_ADDR'] ?? ''));
            $userAgent = substr((string)($server['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $projectId = $this->resolveProjectId($templateId);

            [$columns, $values] = $this->buildInsert($agentId, $type, $ipHash, $userAgent, $templateId, $projectId, $context);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));

            $stmt = $this->db->prepare('INSERT INTO access_logs (' . implode(',', $columns) . ") VALUES ($placeholders)");
            $stmt->execute($values);
        } catch (Throwable $e) {
            ($this->errorLogger)('Access log error: ' . $e->getMessage());
        }
    }

    private function resolveProjectId(?int $templateId): ?int
    {
        if (empty($this->accessLogColumns['project_id']) || !$templateId || !($this->lpTemplateColumnExists)('project_id')) {
            return null;
        }

        $projectStmt = $this->db->prepare('SELECT project_id FROM lp_templates WHERE id=?');
        $projectStmt->execute([$templateId]);

        return (int)$projectStmt->fetchColumn() ?: null;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function buildInsert(
        int $agentId,
        string $type,
        string $ipHash,
        string $userAgent,
        ?int $templateId,
        ?int $projectId,
        array $context
    ): array {
        $columns = ['agent_id', 'type', 'ip_hash', 'user_agent'];
        $values = [$agentId, $type, $ipHash, $userAgent];

        if (!empty($this->accessLogColumns['template_id'])) {
            $columns[] = 'template_id';
            $values[] = $templateId ?: null;
        }
        if (!empty($this->accessLogColumns['project_id'])) {
            $columns[] = 'project_id';
            $values[] = $projectId;
        }
        if (!empty($this->accessLogColumns['referral_token_id'])) {
            $columns[] = 'referral_token_id';
            $values[] = !empty($context['referral_token_id']) ? (int)$context['referral_token_id'] : null;
        }
        if (!empty($this->accessLogColumns['referral_session_key'])) {
            $columns[] = 'referral_session_key';
            $values[] = trim((string)($context['referral_session_key'] ?? '')) ?: null;
        }

        return [$columns, $values];
    }
}

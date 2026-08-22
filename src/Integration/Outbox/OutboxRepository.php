<?php

declare(strict_types=1);

namespace SenNoKuni\Integration\Outbox;

use PDO;

final class OutboxRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly OutboxClaimService $claimService,
        private readonly RetryPolicy $retryPolicy,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimDue(string $siteKey = '', int $limit = 10, bool $includeDlq = false, string $workerId = ''): array
    {
        if (!$this->claimService->supportsClaims()) {
            return [];
        }

        $this->claimService->recoverStaleClaims();
        $limit = min(50, max(1, $limit));
        $statuses = $includeDlq ? "'pending','failed','dlq'" : "'pending','failed'";
        $where = "status IN ($statuses) AND (next_attempt_at IS NULL OR next_attempt_at <= NOW() OR status='dlq')";
        $params = [];
        $siteKey = trim($siteKey);
        if ($siteKey !== '') {
            $where .= ' AND target_site_key=?';
            $params[] = $siteKey;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM integration_outbox_events
            WHERE $where
              AND (claim_token IS NULL OR claim_expires_at IS NULL OR claim_expires_at < NOW())
            ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'failed' THEN 1 ELSE 2 END,
                     COALESCE(next_attempt_at, created_at) ASC,
                     id ASC
            LIMIT $limit
        ");
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $claimed = [];
        $workerId = trim($workerId) !== '' ? trim($workerId) : ('cron-' . getmypid());
        foreach ($ids as $id) {
            $row = $this->claimService->claimById($id, $workerId);
            if ($row !== null) {
                $claimed[] = $row;
            }
        }

        return $claimed;
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $result
     */
    public function updateAfterAttempt(array $event, array $result): void
    {
        $id = (int)($event['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $hasClaim = $this->claimService->supportsClaims();
        $claimToken = trim((string)($event['_runtime_claim_token'] ?? $event['claim_token'] ?? ''));
        $claimWhere = ($hasClaim && $claimToken !== '') ? ' AND claim_token=?' : '';
        $claimParams = ($hasClaim && $claimToken !== '') ? [$claimToken] : [];
        $claimClearSql = $hasClaim ? ",
                claim_token=NULL,
                claimed_at=NULL,
                claim_expires_at=NULL,
                worker_id=NULL" : '';

        $attemptsAfter = (int)($event['attempt_count'] ?? 0) + 1;
        $maxAttempts = max(1, (int)($event['max_attempts'] ?? 8));
        $ok = !empty($result['ok']);
        $error = trim((string)($result['error'] ?? ''));
        if ($error === '' && !$ok) {
            $error = 'HTTP ' . (int)($result['status'] ?? 0);
        }

        if ($ok) {
            $stmt = $this->pdo->prepare("
                UPDATE integration_outbox_events
                SET status='succeeded',
                    attempt_count=?,
                    last_attempt_at=NOW(),
                    next_attempt_at=NULL,
                    last_error=NULL,
                    processed_at=NOW(),
                    updated_at=NOW()
                    $claimClearSql
                WHERE id=?$claimWhere
            ");
            $stmt->execute(array_merge([$attemptsAfter, $id], $claimParams));
            return;
        }

        if ($this->retryPolicy->shouldMoveToDeadLetter($attemptsAfter, $maxAttempts)) {
            $stmt = $this->pdo->prepare("
                UPDATE integration_outbox_events
                SET status='dlq',
                    attempt_count=?,
                    last_attempt_at=NOW(),
                    next_attempt_at=NULL,
                    last_error=?,
                    updated_at=NOW()
                    $claimClearSql
                WHERE id=?$claimWhere
            ");
            $stmt->execute(array_merge([$attemptsAfter, $error, $id], $claimParams));
            return;
        }

        $delayMinutes = $this->retryPolicy->nextDelayMinutes($attemptsAfter);
        $stmt = $this->pdo->prepare("
            UPDATE integration_outbox_events
            SET status='failed',
                attempt_count=?,
                last_attempt_at=NOW(),
                next_attempt_at=DATE_ADD(NOW(), INTERVAL {$delayMinutes} MINUTE),
                last_error=?,
                updated_at=NOW()
                $claimClearSql
            WHERE id=?$claimWhere
        ");
        $stmt->execute(array_merge([$attemptsAfter, $error, $id], $claimParams));
    }
}


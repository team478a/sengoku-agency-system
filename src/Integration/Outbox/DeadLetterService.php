<?php

declare(strict_types=1);

namespace SenNoKuni\Integration\Outbox;

use PDO;

final class DeadLetterService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly OutboxClaimService $claimService,
    ) {
    }

    public function resetForRetry(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $claimClearSql = $this->claimService->supportsClaims() ? "
            claim_token=NULL,
            claimed_at=NULL,
            claim_expires_at=NULL,
            worker_id=NULL," : '';
        $stmt = $this->pdo->prepare("
            UPDATE integration_outbox_events
            SET status='failed',
                next_attempt_at=NOW(),
                last_error=NULL,
                processed_at=NULL,
                $claimClearSql
                updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$id]);
    }

    public function moveToDeadLetter(int $id, string $reason = ''): void
    {
        if ($id <= 0) {
            return;
        }

        $claimClearSql = $this->claimService->supportsClaims() ? "
            claim_token=NULL,
            claimed_at=NULL,
            claim_expires_at=NULL,
            worker_id=NULL," : '';
        $stmt = $this->pdo->prepare("
            UPDATE integration_outbox_events
            SET status='dlq',
                next_attempt_at=NULL,
                $claimClearSql
                last_error=?,
                updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$reason !== '' ? $reason : 'Moved to DLQ manually.', $id]);
    }
}


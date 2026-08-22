<?php

declare(strict_types=1);

namespace SenNoKuni\Integration\Outbox;

use Closure;
use PDO;

final class OutboxClaimService
{
    /**
     * @param Closure(string, string): bool $columnChecker
     * @param Closure(string, string=): string $settingReader
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Closure $columnChecker,
        private readonly Closure $settingReader,
    ) {
    }

    public function supportsClaims(): bool
    {
        return ($this->columnChecker)('integration_outbox_events', 'claim_token')
            && ($this->columnChecker)('integration_outbox_events', 'claimed_at')
            && ($this->columnChecker)('integration_outbox_events', 'claim_expires_at')
            && ($this->columnChecker)('integration_outbox_events', 'worker_id');
    }

    public function timeoutSeconds(): int
    {
        $seconds = (int)($this->settingReader)('external_partner_outbox_claim_timeout_seconds', '300');
        return min(3600, max(60, $seconds));
    }

    public function recoverStaleClaims(): int
    {
        if (!$this->supportsClaims()) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            UPDATE integration_outbox_events
            SET status='failed',
                claim_token=NULL,
                claimed_at=NULL,
                claim_expires_at=NULL,
                worker_id=NULL,
                next_attempt_at=NOW(),
                last_error=CASE
                    WHEN last_error IS NULL OR last_error = '' THEN 'Outbox worker claim expired.'
                    ELSE CONCAT(last_error, '\nOutbox worker claim expired.')
                END,
                updated_at=NOW()
            WHERE status='processing'
              AND claim_expires_at IS NOT NULL
              AND claim_expires_at < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimById(int $id, string $workerId = ''): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if (!$this->supportsClaims()) {
            $stmt = $this->pdo->prepare('SELECT * FROM integration_outbox_events WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        }

        $this->recoverStaleClaims();
        $workerId = trim($workerId) !== '' ? trim($workerId) : ('manual-' . getmypid());
        $claimToken = 'clm_' . bin2hex(random_bytes(24));
        $timeout = $this->timeoutSeconds();

        $stmt = $this->pdo->prepare("
            UPDATE integration_outbox_events
            SET status='processing',
                claim_token=?,
                claimed_at=NOW(),
                claim_expires_at=DATE_ADD(NOW(), INTERVAL {$timeout} SECOND),
                worker_id=?,
                updated_at=NOW()
            WHERE id=?
              AND status IN ('pending','failed','dlq')
              AND (claim_token IS NULL OR claim_expires_at IS NULL OR claim_expires_at < NOW())
        ");
        $stmt->execute([$claimToken, $workerId, $id]);
        if ($stmt->rowCount() !== 1) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM integration_outbox_events WHERE id=? AND claim_token=? LIMIT 1');
        $stmt->execute([$id, $claimToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $row['_runtime_claim_token'] = $claimToken;
        return $row;
    }
}


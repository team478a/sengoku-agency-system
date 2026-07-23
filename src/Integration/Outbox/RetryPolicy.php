<?php

declare(strict_types=1);

namespace SenNoKuni\Integration\Outbox;

final class RetryPolicy
{
    public function nextDelayMinutes(int $attemptsAfterFailure): int
    {
        return min(1440, max(5, (int)(5 * (2 ** min(8, max(0, $attemptsAfterFailure - 1))))));
    }

    public function shouldMoveToDeadLetter(int $attemptsAfterFailure, int $maxAttempts): bool
    {
        return $attemptsAfterFailure >= max(1, $maxAttempts);
    }
}


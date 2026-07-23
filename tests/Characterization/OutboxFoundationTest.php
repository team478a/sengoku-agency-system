<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\Integration\Outbox\RetryPolicy;

final class OutboxFoundationTest extends TestCase
{
    public function testRetryPolicyUsesExistingBackoffContract(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(5, $policy->nextDelayMinutes(1));
        self::assertSame(10, $policy->nextDelayMinutes(2));
        self::assertSame(20, $policy->nextDelayMinutes(3));
        self::assertSame(1440, $policy->nextDelayMinutes(99));
    }

    public function testRetryPolicyMovesToDlqOnlyAfterMaxAttempts(): void
    {
        $policy = new RetryPolicy();

        self::assertFalse($policy->shouldMoveToDeadLetter(7, 8));
        self::assertTrue($policy->shouldMoveToDeadLetter(8, 8));
        self::assertTrue($policy->shouldMoveToDeadLetter(1, 0));
    }
}


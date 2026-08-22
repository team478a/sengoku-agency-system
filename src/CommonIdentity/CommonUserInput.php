<?php

declare(strict_types=1);

namespace SenNoKuni\CommonIdentity;

final class CommonUserInput
{
    /**
     * @param list<array{0: string, 1: string, 2: string}> $identityChecks
     */
    public function __construct(
        public readonly string $systemKey,
        public readonly string $externalUserId,
        public readonly string $commonUserId,
        public readonly array $identityChecks,
    ) {
    }
}


<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Auth;

final class ApiAuthorizationContext
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly int $partnerId,
        public readonly string $siteKey,
        public readonly array $scopes,
    ) {
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}


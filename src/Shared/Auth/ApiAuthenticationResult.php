<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Auth;

final class ApiAuthenticationResult
{
    /**
     * @param array<string, mixed>|null $partner
     */
    private function __construct(
        public readonly bool $authenticated,
        public readonly string $authType,
        public readonly ?array $partner,
        public readonly string $errorCode,
        public readonly string $message,
        public readonly int $statusCode,
    ) {
    }

    /**
     * @param array<string, mixed> $partner
     */
    public static function partner(array $partner): self
    {
        return new self(true, 'partner_inbound_key', $partner, '', '', 200);
    }

    public static function legacy(): self
    {
        return new self(true, 'legacy_external_api_token', null, '', '', 200);
    }

    public static function failure(string $errorCode, string $message, int $statusCode): self
    {
        return new self(false, '', null, $errorCode, $message, $statusCode);
    }
}


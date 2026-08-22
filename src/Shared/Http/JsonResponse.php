<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Http;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly int $statusCode = 200,
    ) {
    }

    public function body(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}


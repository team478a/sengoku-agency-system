<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Http;

final class JsonRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}


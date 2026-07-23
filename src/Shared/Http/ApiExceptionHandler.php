<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Http;

use Throwable;

final class ApiExceptionHandler
{
    /**
     * @return array{ok: false, error: array{code: string, message: string}}
     */
    public function payload(Throwable $exception, string $publicCode = 'SERVER_ERROR', string $publicMessage = 'Server error.'): array
    {
        error_log($exception->getMessage());

        return [
            'ok' => false,
            'error' => [
                'code' => $publicCode,
                'message' => $publicMessage,
            ],
        ];
    }
}


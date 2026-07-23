<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Log;

final class Logger
{
    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        error_log($this->format($message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function format(string $message, array $context): string
    {
        if ($context === []) {
            return $message;
        }

        return $message . ' ' . (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }
}


<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

use Closure;

final class JsonWebhookClient
{
    private readonly Closure $errorLogger;

    /**
     * @param callable(string): void $errorLogger
     */
    public function __construct(callable $errorLogger)
    {
        $this->errorLogger = Closure::fromCallable($errorLogger);
    }

    /**
     * @param list<string> $headers
     */
    public function postJson(string $url, string $body, array $headers = []): bool
    {
        $defaultHeaders = ['Content-Type: application/json'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($ch);
        $ok = ($result !== false);
        if (!$ok) {
            ($this->errorLogger)('postJson failed to ' . $url . ': ' . curl_error($ch));
        }
        curl_close($ch);

        return $ok;
    }
}

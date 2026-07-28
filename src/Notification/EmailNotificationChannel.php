<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

use Closure;

final class EmailNotificationChannel
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
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $lead
     * @param array<string, string> $server
     */
    public function send(array $agent, array $lead, string $message, array $server = []): bool
    {
        $to = (string)($agent['email'] ?? '');
        $subject = '[Sengoku] New lead - ' . (string)($lead['name'] ?? '');
        $headers = implode("\r\n", [
            'From: noreply@' . (string)($server['HTTP_HOST'] ?? 'sengoku.example.com'),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . PHP_VERSION,
        ]);
        $result = @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B'), $message, $headers);
        if (!$result) {
            ($this->errorLogger)('Email send failed to: ' . $to);
        }

        return $result;
    }
}

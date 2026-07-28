<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

final class LineNotificationChannel
{
    public function __construct(private readonly JsonWebhookClient $client)
    {
    }

    /**
     * @param array<string, mixed> $agent
     */
    public function send(array $agent, string $message): bool
    {
        $body = json_encode([
            'to' => (string)($agent['line_user_id'] ?? ''),
            'messages' => [[
                'type' => 'text',
                'text' => $message,
            ]],
        ]);

        return $this->client->postJson(
            'https://api.line.me/v2/bot/message/push',
            (string)$body,
            ['Authorization: Bearer ' . (string)($agent['line_messaging_token'] ?? '')]
        );
    }
}

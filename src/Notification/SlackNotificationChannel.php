<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

final class SlackNotificationChannel
{
    public function __construct(private readonly JsonWebhookClient $client)
    {
    }

    /**
     * @param array<string, mixed> $agent
     */
    public function send(array $agent, string $message): bool
    {
        return $this->client->postJson((string)($agent['slack_webhook'] ?? ''), (string)json_encode(['text' => $message]));
    }
}

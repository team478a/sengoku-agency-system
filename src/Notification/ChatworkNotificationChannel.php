<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

final class ChatworkNotificationChannel
{
    /**
     * @param array<string, mixed> $agent
     */
    public function send(array $agent, string $message): bool
    {
        $ch = curl_init((string)($agent['chatwork_webhook'] ?? ''));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'payload=' . urlencode($message),
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($ch);
        $ok = ($result !== false);
        curl_close($ch);

        return $ok;
    }
}

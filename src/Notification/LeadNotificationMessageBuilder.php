<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

final class LeadNotificationMessageBuilder
{
    /**
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $lead
     */
    public function build(array $agent, array $lead): string
    {
        $sourceName = $lead['source_agent_name'] ?? $agent['agent_name'];
        $sourceCode = $lead['source_agent_code'] ?? ($agent['agent_code'] ?? '');
        $lines = [
            '[Sengoku] New lead received',
            '------------------------------',
            'Customer',
            'Name: ' . (string)($lead['name'] ?? ''),
            'Email: ' . (string)($lead['email'] ?? ''),
            'Phone: ' . (($lead['phone'] ?? '') ?: 'N/A'),
            'Message',
            (string)($lead['message'] ?? ''),
            '------------------------------',
            'Received: ' . date('Y-m-d H:i'),
            'Source LP: ' . (string)$sourceName . ($sourceCode ? ' (/a/' . (string)$sourceCode . ')' : ''),
            'Notify to: ' . (string)($agent['agent_name'] ?? ''),
        ];

        return implode("\n", $lines);
    }
}

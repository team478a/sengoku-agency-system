<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

final class LeadNotifier
{
    public function __construct(
        private readonly LeadNotificationMessageBuilder $messageBuilder,
        private readonly EmailNotificationChannel $emailChannel,
        private readonly LineNotificationChannel $lineChannel,
        private readonly ChatworkNotificationChannel $chatworkChannel,
        private readonly SlackNotificationChannel $slackChannel,
    ) {
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $lead
     * @param array<string, string> $server
     * @return array<string, bool>
     */
    public function send(array $agent, array $lead, array $server = []): array
    {
        $results = [];
        $message = $this->messageBuilder->build($agent, $lead);

        if (!empty($agent['notify_email'])) {
            $results['email'] = $this->emailChannel->send($agent, $lead, $message, $server);
        }
        if (!empty($agent['notify_line']) && !empty($agent['line_messaging_token']) && !empty($agent['line_user_id'])) {
            $results['line'] = $this->lineChannel->send($agent, $message);
        }
        if (!empty($agent['notify_chatwork']) && !empty($agent['chatwork_webhook'])) {
            $results['chatwork'] = $this->chatworkChannel->send($agent, $message);
        }
        if (!empty($agent['notify_slack']) && !empty($agent['slack_webhook'])) {
            $results['slack'] = $this->slackChannel->send($agent, $message);
        }

        return $results;
    }
}

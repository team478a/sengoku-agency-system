<?php

declare(strict_types=1);

namespace SenNoKuni\CommonIdentity;

final class CommonUserInputNormalizer
{
    /**
     * @param array<string, mixed> $data
     */
    public function normalize(array $data, string $fallbackSystemKey = ''): CommonUserInput
    {
        $systemKey = trim((string)($data['system_key'] ?? $data['service_key'] ?? ''));
        if ($systemKey === '') {
            $systemKey = trim($fallbackSystemKey);
        }

        return new CommonUserInput(
            $systemKey,
            trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? '')),
            trim((string)($data['common_user_id'] ?? '')),
            [
                ['line', trim((string)($data['line_user_id'] ?? '')), ''],
                ['email', trim((string)($data['email'] ?? '')), ''],
                ['email', trim((string)($data['login_email'] ?? '')), 'login'],
                ['phone', trim((string)($data['phone'] ?? '')), ''],
                ['wallet', trim((string)($data['wallet_address'] ?? '')), ''],
            ],
        );
    }
}


<?php

declare(strict_types=1);

namespace SenNoKuni\Referral;

final class TouchpointFingerprint
{
    public function ipHash(string $ip): ?string
    {
        $ip = trim($ip);
        return $ip !== '' ? hash('sha256', 'ip:' . $ip) : null;
    }

    public function userAgentHash(string $userAgent): ?string
    {
        $userAgent = trim($userAgent);
        return $userAgent !== '' ? hash('sha256', 'ua:' . $userAgent) : null;
    }
}


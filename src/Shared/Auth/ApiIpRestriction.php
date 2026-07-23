<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Auth;

final class ApiIpRestriction
{
    public function isAllowed(string $remoteIp, string $allowlist): bool
    {
        $remoteIp = trim($remoteIp);
        if ($remoteIp === '') {
            return false;
        }

        $allowed = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $allowlist) ?: [])));
        return $allowed === [] || in_array($remoteIp, $allowed, true);
    }
}


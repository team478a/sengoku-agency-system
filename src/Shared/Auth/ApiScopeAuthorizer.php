<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Auth;

final class ApiScopeAuthorizer
{
    /**
     * @param array<string, mixed>|null $partner
     */
    public function isAllowed(?array $partner, string $requiredScope): bool
    {
        if ($partner === null) {
            return true;
        }

        $raw = trim((string)($partner['inbound_scopes'] ?? ''));
        if ($raw === '') {
            return true;
        }

        $scopes = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: [])));
        return in_array('*', $scopes, true) || in_array($requiredScope, $scopes, true);
    }
}


<?php

declare(strict_types=1);

namespace SenNoKuni\Referral;

use Closure;

final class ReferralTokenResolver
{
    /**
     * @param Closure(string): array<string, mixed> $validateToken
     * @param Closure(string, string): ?array<string, mixed> $findAlias
     */
    public function __construct(
        private readonly Closure $validateToken,
        private readonly Closure $findAlias,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $value, string $aliasType = ''): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['valid' => false, 'reason' => 'empty'];
        }

        $direct = ($this->validateToken)($value);
        if (!empty($direct['valid'])) {
            $direct['resolved_by'] = 'canonical_token';
            $direct['canonical_referral_token'] = $direct['token']['token'] ?? $value;
            return $direct;
        }

        $types = [];
        if (trim($aliasType) !== '') {
            $types[] = trim($aliasType);
        }
        foreach (['ref', 'referral_code', 'shopping_referral_code', 'wallet_invite_token', 'passport_ref'] as $type) {
            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        foreach ($types as $type) {
            $alias = ($this->findAlias)($type, $value);
            if (!$alias || empty($alias['canonical_token'])) {
                continue;
            }
            $validation = ($this->validateToken)((string)$alias['canonical_token']);
            if (!empty($validation['valid'])) {
                $validation['resolved_by'] = 'alias:' . $type;
                $validation['referral_alias'] = $alias;
                $validation['canonical_referral_token'] = $validation['token']['token'] ?? $alias['canonical_token'];
                return $validation;
            }
            return $validation + ['resolved_by' => 'alias:' . $type, 'referral_alias' => $alias];
        }

        return ['valid' => false, 'reason' => $direct['reason'] ?? 'not_found'];
    }
}


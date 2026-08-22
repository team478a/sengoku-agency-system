<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\Referral\ReferralTokenResolver;
use SenNoKuni\Referral\TouchpointFingerprint;

final class ReferralFoundationTest extends TestCase
{
    public function testFingerprintKeepsExistingHashPrefixes(): void
    {
        $fingerprint = new TouchpointFingerprint();

        self::assertSame(hash('sha256', 'ip:203.0.113.10'), $fingerprint->ipHash('203.0.113.10'));
        self::assertSame(hash('sha256', 'ua:ExampleBrowser'), $fingerprint->userAgentHash('ExampleBrowser'));
        self::assertNull($fingerprint->ipHash(''));
        self::assertNull($fingerprint->userAgentHash(''));
    }

    public function testReferralResolverReturnsCanonicalTokenWhenDirectTokenIsValid(): void
    {
        $resolver = new ReferralTokenResolver(
            static fn(string $token): array => $token === 'canonical'
                ? ['valid' => true, 'token' => ['token' => 'canonical']]
                : ['valid' => false, 'reason' => 'not_found'],
            static fn(string $type, string $value): ?array => null,
        );

        $result = $resolver->resolve('canonical');

        self::assertTrue($result['valid']);
        self::assertSame('canonical_token', $result['resolved_by']);
        self::assertSame('canonical', $result['canonical_referral_token']);
    }

    public function testReferralResolverUsesAliasesBeforeReturningNotFound(): void
    {
        $resolver = new ReferralTokenResolver(
            static fn(string $token): array => $token === 'canonical'
                ? ['valid' => true, 'token' => ['token' => 'canonical']]
                : ['valid' => false, 'reason' => 'not_found'],
            static fn(string $type, string $value): ?array => $type === 'shopping_referral_code' && $value === 'shop-code'
                ? ['alias_type' => $type, 'alias_value' => $value, 'canonical_token' => 'canonical']
                : null,
        );

        $result = $resolver->resolve('shop-code');

        self::assertTrue($result['valid']);
        self::assertSame('alias:shopping_referral_code', $result['resolved_by']);
        self::assertSame('canonical', $result['canonical_referral_token']);
    }
}

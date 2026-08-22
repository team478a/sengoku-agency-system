<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\CommonIdentity\CommonUserInputNormalizer;

final class CommonIdentityFoundationTest extends TestCase
{
    public function testNormalizerKeepsExistingSystemAndExternalIdAliases(): void
    {
        $normalizer = new CommonUserInputNormalizer();

        $input = $normalizer->normalize([
            'service_key' => ' passport ',
            'service_user_id' => ' user-123 ',
            'common_user_id' => ' common-456 ',
        ], 'fallback');

        self::assertSame('passport', $input->systemKey);
        self::assertSame('user-123', $input->externalUserId);
        self::assertSame('common-456', $input->commonUserId);
    }

    public function testNormalizerUsesAuthenticatedPartnerAsFallbackSystemKey(): void
    {
        $normalizer = new CommonUserInputNormalizer();

        $input = $normalizer->normalize([
            'external_user_id' => 'cart-001',
        ], 'shopping-cart');

        self::assertSame('shopping-cart', $input->systemKey);
        self::assertSame('cart-001', $input->externalUserId);
    }

    public function testNormalizerBuildsIdentityChecksForCurrentHubFields(): void
    {
        $normalizer = new CommonUserInputNormalizer();

        $input = $normalizer->normalize([
            'line_user_id' => 'line-1',
            'email' => 'contact@example.com',
            'login_email' => 'login@example.com',
            'phone' => '09000000000',
            'wallet_address' => '0xabc',
        ]);

        self::assertSame([
            ['line', 'line-1', ''],
            ['email', 'contact@example.com', ''],
            ['email', 'login@example.com', 'login'],
            ['phone', '09000000000', ''],
            ['wallet', '0xabc', ''],
        ], $input->identityChecks);
    }
}

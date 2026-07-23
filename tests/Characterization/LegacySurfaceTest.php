<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;

final class LegacySurfaceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertIsString($root);
        $this->root = $root;
    }

    /**
     * @dataProvider legacyEntrypointProvider
     */
    public function testLegacyEntrypointsRemainPresent(string $relativePath): void
    {
        self::assertFileExists($this->root . DIRECTORY_SEPARATOR . $relativePath);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function legacyEntrypointProvider(): iterable
    {
        yield 'admin login' => ['admin/login.php'];
        yield 'agent login' => ['agent/login.php'];
        yield 'agent initial setup' => ['agent/setup.php'];
        yield 'agent LP route' => ['lp.php'];
        yield 'contact form' => ['contact.php'];
        yield 'hierarchy API' => ['api/hierarchy.php'];
        yield 'agency sync API' => ['api/integrations/agencies/index.php'];
        yield 'common user resolve API' => ['api/common-users/resolve/index.php'];
        yield 'referral capture API' => ['api/referrals/capture/index.php'];
        yield 'referral confirm API' => ['api/referrals/confirm/index.php'];
        yield 'SSO JWKS API' => ['api/sso/jwks.php'];
        yield 'external retry cron' => ['cron/external_integration_retry.php'];
    }

    /**
     * @dataProvider legacyFunctionProvider
     */
    public function testLegacyFunctionNamesRemainPresent(string $relativePath, string $functionName): void
    {
        $content = file_get_contents($this->root . DIRECTORY_SEPARATOR . $relativePath);
        self::assertIsString($content);
        self::assertStringContainsString('function ' . $functionName, $content);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function legacyFunctionProvider(): iterable
    {
        yield 'API v2 auth' => ['api/v2/bootstrap.php', 'apiV2Authenticate'];
        yield 'API v2 scope' => ['api/v2/bootstrap.php', 'apiV2RequireScope'];
        yield 'hierarchy token' => ['api/hierarchy.php', 'apiTokenIsValid'];
        yield 'agency API key' => ['api/integrations/agencies/index.php', 'agencyApiKeyIsValid'];
        yield 'agency partner lookup' => ['api/integrations/agencies/index.php', 'agencyApiPartnerByKey'];
        yield 'agency scope' => ['api/integrations/agencies/index.php', 'agencyApiRequireScope'];
        yield 'outbox due retry' => ['includes/functions.php', 'retryDueIntegrationOutboxEvents'];
        yield 'outbox stale recovery' => ['includes/functions.php', 'recoverStaleIntegrationOutboxClaims'];
    }

    public function testVersionFileUsesSemanticVersion(): void
    {
        $version = trim((string) file_get_contents($this->root . DIRECTORY_SEPARATOR . 'VERSION'));
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }
}


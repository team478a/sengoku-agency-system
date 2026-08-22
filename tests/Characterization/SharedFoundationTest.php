<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\Shared\Auth\ApiIpRestriction;
use SenNoKuni\Shared\Auth\ApiScopeAuthorizer;
use SenNoKuni\Shared\Http\JsonRequest;
use SenNoKuni\Shared\Http\JsonResponse;

final class SharedFoundationTest extends TestCase
{
    public function testScopeAuthorizerAllowsWildcardAndExplicitScope(): void
    {
        $authorizer = new ApiScopeAuthorizer();

        self::assertTrue($authorizer->isAllowed(['inbound_scopes' => '*'], 'agencies:read'));
        self::assertTrue($authorizer->isAllowed(['inbound_scopes' => 'agencies:read agencies:write'], 'agencies:write'));
        self::assertFalse($authorizer->isAllowed(['inbound_scopes' => 'agencies:read'], 'agencies:write'));
    }

    public function testIpRestrictionParsesCommaAndNewlineAllowlist(): void
    {
        $restriction = new ApiIpRestriction();

        self::assertTrue($restriction->isAllowed('203.0.113.10', "198.51.100.1\n203.0.113.10"));
        self::assertTrue($restriction->isAllowed('203.0.113.10', '198.51.100.1,203.0.113.10'));
        self::assertFalse($restriction->isAllowed('203.0.113.20', '203.0.113.10'));
    }

    public function testJsonHelpersKeepArrayContracts(): void
    {
        self::assertSame(['ok' => true], JsonRequest::decode('{"ok":true}'));

        $response = new JsonResponse(['ok' => true], 201);
        self::assertSame(201, $response->statusCode);
        self::assertSame('{"ok":true}', $response->body());
    }
}


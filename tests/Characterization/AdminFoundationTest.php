<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\Admin\AdminBadgeRenderer;
use SenNoKuni\Admin\AdminDateFormatter;
use SenNoKuni\Admin\AdminTextFormatter;

final class AdminFoundationTest extends TestCase
{
    public function testDateFormatterKeepsMinuteContract(): void
    {
        $formatter = new AdminDateFormatter();

        self::assertSame('2026/07/24 01:23', $formatter->minute('2026-07-24 01:23:45'));
        self::assertSame('-', $formatter->minute(null));
        self::assertSame('-', $formatter->minute('not-a-date'));
    }

    public function testTextFormatterKeepsShortFallbackContract(): void
    {
        $formatter = new AdminTextFormatter();

        self::assertSame('-', $formatter->short(''));
        self::assertSame('abc', $formatter->short(' abc '));
        self::assertSame('abc...', $formatter->short('abcdef', 3));
    }

    public function testBadgeRendererKeepsOutboxStatusClasses(): void
    {
        $renderer = new AdminBadgeRenderer();

        self::assertStringContainsString('badge-new', $renderer->outboxStatus('pending', ['pending' => 'Pending']));
        self::assertStringContainsString('badge-contacted', $renderer->outboxStatus('processing', []));
        self::assertStringContainsString('badge-active', $renderer->outboxStatus('succeeded', []));
        self::assertStringContainsString('badge-inactive', $renderer->outboxStatus('dlq', []));
    }
}

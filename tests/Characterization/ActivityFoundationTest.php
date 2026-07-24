<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\Activity\ActivitySummaryCards;

final class ActivityFoundationTest extends TestCase
{
    public function testAdminSummaryCardsKeepLabelsAndValues(): void
    {
        $cards = (new ActivitySummaryCards())->adminCards([
            'agent_count' => '3',
            'pv_total' => 20,
            'line_total' => 4,
            'lead_total' => 2,
            'new_total' => 1,
        ]);

        self::assertSame(['代理店数', 'PV', 'LINE', '問い合わせ', '未対応'], array_column($cards, 'label'));
        self::assertSame([3, 20, 4, 2, 1], array_column($cards, 'value'));
        self::assertSame('warning', $cards[4]['tone']);
    }

    public function testDownlineSummaryCardsKeepLabelsAndValues(): void
    {
        $cards = (new ActivitySummaryCards())->downlineCards([
            'agent_count' => 2,
            'pv_total' => 10,
            'line_total' => 1,
            'lead_total' => 1,
            'new_total' => 0,
        ]);

        self::assertSame(['配下人数', 'PV', 'LINEクリック', '問い合わせ', '未対応'], array_column($cards, 'label'));
        self::assertSame([2, 10, 1, 1, 0], array_column($cards, 'value'));
        self::assertSame('warning', $cards[4]['tone']);
    }
}

<?php

declare(strict_types=1);

namespace SenNoKuni\Activity;

final class ActivitySummaryCards
{
    /**
     * @param array<string, mixed> $stats
     * @return list<array{label: string, value: int, tone: string}>
     */
    public function adminCards(array $stats): array
    {
        return [
            $this->card('代理店数', $stats['agent_count'] ?? 0),
            $this->card('PV', $stats['pv_total'] ?? 0),
            $this->card('LINE', $stats['line_total'] ?? 0),
            $this->card('問い合わせ', $stats['lead_total'] ?? 0),
            $this->card('未対応', $stats['new_total'] ?? 0, 'warning'),
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @return list<array{label: string, value: int, tone: string}>
     */
    public function downlineCards(array $stats): array
    {
        return [
            $this->card('配下人数', $stats['agent_count'] ?? 0),
            $this->card('PV', $stats['pv_total'] ?? 0),
            $this->card('LINEクリック', $stats['line_total'] ?? 0),
            $this->card('問い合わせ', $stats['lead_total'] ?? 0),
            $this->card('未対応', $stats['new_total'] ?? 0, 'warning'),
        ];
    }

    /**
     * @return array{label: string, value: int, tone: string}
     */
    private function card(string $label, mixed $value, string $tone = 'default'): array
    {
        return [
            'label' => $label,
            'value' => (int)$value,
            'tone' => $tone,
        ];
    }
}

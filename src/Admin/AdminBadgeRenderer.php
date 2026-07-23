<?php

declare(strict_types=1);

namespace SenNoKuni\Admin;

final class AdminBadgeRenderer
{
    /**
     * @param array<string, string> $labels
     */
    public function outboxStatus(string $status, array $labels): string
    {
        $label = $labels[$status] ?? $status;
        $class = match ($status) {
            'processing', 'failed' => 'badge-contacted',
            'succeeded' => 'badge-active',
            'dlq' => 'badge-inactive',
            default => 'badge-new',
        };

        return '<span class="badge ' . $this->escape($class) . '">' . $this->escape($label) . '</span>';
    }

    public function inlineStatus(string $label, string $type = 'ok'): string
    {
        $styles = [
            'ok' => 'background:rgba(44,143,99,.16);color:#2c8f63;',
            'warn' => 'background:rgba(201,168,76,.18);color:#8B6914;',
            'ng' => 'background:rgba(180,55,55,.16);color:#b43737;',
        ];

        return '<span style="display:inline-block;padding:.25rem .6rem;border-radius:999px;font-weight:700;font-size:.78rem;' . ($styles[$type] ?? $styles['ok']) . '">' . $this->escape($label) . '</span>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

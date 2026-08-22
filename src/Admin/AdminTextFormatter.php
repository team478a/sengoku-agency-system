<?php

declare(strict_types=1);

namespace SenNoKuni\Admin;

final class AdminTextFormatter
{
    public function short(?string $value, int $length = 120): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '-';
        }

        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
            return mb_substr($value, 0, $length, 'UTF-8') . '...';
        }

        return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
    }
}

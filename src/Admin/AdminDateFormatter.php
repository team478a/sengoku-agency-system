<?php

declare(strict_types=1);

namespace SenNoKuni\Admin;

final class AdminDateFormatter
{
    public function minute(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y/m/d H:i', $timestamp) : '-';
    }
}

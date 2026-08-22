<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Config;

use Closure;

final class SettingsRepository
{
    /**
     * @param Closure(string, mixed=): mixed $reader
     */
    public function __construct(private readonly Closure $reader)
    {
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = ($this->reader)($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = ($this->reader)($key, $default ? '1' : '0');
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}

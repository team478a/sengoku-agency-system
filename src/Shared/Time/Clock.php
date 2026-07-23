<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Time;

use DateTimeImmutable;

final class Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}


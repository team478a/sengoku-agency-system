<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Database;

use Closure;
use PDO;

final class DatabaseConnection
{
    /**
     * @param Closure(): PDO $factory
     */
    public function __construct(private readonly Closure $factory)
    {
    }

    public function pdo(): PDO
    {
        return ($this->factory)();
    }
}

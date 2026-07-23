<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Database;

use PDO;

final class DatabaseConnection
{
    /**
     * @param callable(): PDO $factory
     */
    public function __construct(private readonly mixed $factory)
    {
    }

    public function pdo(): PDO
    {
        return ($this->factory)();
    }
}


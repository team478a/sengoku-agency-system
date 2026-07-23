<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Database;

use PDO;
use Throwable;

final class SchemaVersionChecker
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}


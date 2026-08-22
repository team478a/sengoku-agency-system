<?php

declare(strict_types=1);

namespace SenNoKuni\Admin;

use PDO;
use Throwable;

final class OperationsQueryService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param callable(string): array<int, string> $tableColumns
     */
    public function tableReady(string $table, callable $tableColumns): bool
    {
        return !empty($tableColumns($table));
    }

    /**
     * @param list<mixed> $params
     */
    public function count(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}

<?php

declare(strict_types=1);

namespace Vault;

use PDO;
use PDOStatement;

final class Database
{
    private PDO $pdo;

    public function __construct(private readonly string $path)
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Database not found at {$path}");
        }

        $this->pdo = new PDO("sqlite:{$path}", options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Per-connection pragmas — must be set on every new connection.
        // See _index/schema.sql for rationale on each setting.
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('PRAGMA cache_size = -16000');
        $this->pdo->exec('PRAGMA temp_store = MEMORY');
        $this->pdo->exec('PRAGMA mmap_size = 134217728');
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->prepare($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->prepare($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        return $this->prepare($sql, $params)->fetchColumn();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->prepare($sql, $params)->rowCount();
    }

    public function executeRaw(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    private function prepare(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

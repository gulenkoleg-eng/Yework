<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** Тонка обгортка над PDO (MySQL 8 / MariaDB 10.6+). Лише підготовлені запити. */
final class Db
{
    public function __construct(public readonly PDO $pdo, public readonly string $prefix = 'yw_') {}

    /** @param array{host:string,port:int,database:string,user:string,password:string} $c */
    public static function connect(array $c, bool $withDb = true, ?string $prefix = null): self
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], (int) ($c['port'] ?? 3306));
        if ($withDb) $dsn .= ';dbname=' . $c['database'];
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return new self($pdo, $prefix ?? ($c['prefix'] ?? 'yw_'));
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare(str_replace('{p}', $this->prefix, $sql));
        foreach (array_values($params) as $i => $v) {
            $st->bindValue($i + 1, is_bool($v) ? (int) $v : $v, match (true) {
                is_int($v), is_bool($v) => PDO::PARAM_INT,
                $v === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $st->execute();
        return $st;
    }

    public function all(string $sql, array $params = []): array { return $this->run($sql, $params)->fetchAll(); }
    public function one(string $sql, array $params = []): ?array { return $this->run($sql, $params)->fetch() ?: null; }
    public function value(string $sql, array $params = []): mixed { $v = $this->run($sql, $params)->fetchColumn(); return $v === false ? null : $v; }

    public function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        $sql = 'INSERT INTO `' . $this->prefix . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->run($sql, array_values($row));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $set, string $where, array $params = []): int
    {
        $parts = array_map(static fn ($c) => "`$c` = ?", array_keys($set));
        return $this->run('UPDATE `' . $this->prefix . $table . '` SET ' . implode(', ', $parts) . ' WHERE ' . $where, [...array_values($set), ...$params])->rowCount();
    }

    public function tx(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $r = $fn($this);
            $this->pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Поточний час у UTC для запису в БД. */
    public static function now(int $plusSeconds = 0): string
    {
        return gmdate('Y-m-d H:i:s', time() + $plusSeconds);
    }
}

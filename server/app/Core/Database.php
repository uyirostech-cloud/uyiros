<?php
namespace App\Core;

use PDO;
use PDOException;

/** Thin PDO wrapper. Prepared statements only — no string interpolation of user input. */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $txDepth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $c = Config::get('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], (int) $c['port'], $c['database'], $c['charset']
        );
        try {
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
            self::$pdo->exec("SET SESSION time_zone='+00:00'");
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$txDepth = 0;
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function select(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function affected(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    /** Nested-safe transaction using savepoints. */
    public static function transaction(callable $fn): mixed
    {
        self::begin();
        try {
            $result = $fn();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    public static function begin(): void
    {
        if (self::$txDepth === 0) {
            self::pdo()->beginTransaction();
        } else {
            self::pdo()->exec('SAVEPOINT sp' . self::$txDepth);
        }
        self::$txDepth++;
    }

    public static function commit(): void
    {
        self::$txDepth--;
        if (self::$txDepth === 0) {
            self::pdo()->commit();
        } else {
            self::pdo()->exec('RELEASE SAVEPOINT sp' . self::$txDepth);
        }
    }

    public static function rollback(): void
    {
        self::$txDepth--;
        if (self::$txDepth <= 0) {
            self::$txDepth = 0;
            if (self::pdo()->inTransaction()) {
                self::pdo()->rollBack();
            }
        } else {
            self::pdo()->exec('ROLLBACK TO SAVEPOINT sp' . self::$txDepth);
        }
    }

    public static function inTransaction(): bool
    {
        return self::$txDepth > 0;
    }
}

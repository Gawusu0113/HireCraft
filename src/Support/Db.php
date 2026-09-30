<?php
declare(strict_types=1);

namespace HireCraft\Support;

use PDO;
use PDOException;

/**
 * Thin PDO wrapper. Every query goes through prepared statements (NFR-05:
 * no string-built SQL anywhere in the app), and the connection is a single
 * shared instance per request.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function init(array $cfg): void
    {
        if (self::$pdo !== null) {
            return;
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);
        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<h1>Database connection failed</h1><p>Check config/config.php or the HC_DB_* environment variables, and that schema.sql has been imported.</p>';
            if ($cfg['host'] ?? false) {
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            }
            exit;
        }
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int)self::$pdo->lastInsertId();
    }

    public static function transaction(callable $fn): mixed
    {
        self::$pdo->beginTransaction();
        try {
            $result = $fn();
            self::$pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            self::$pdo->rollBack();
            throw $e;
        }
    }
}

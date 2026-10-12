<?php
declare(strict_types=1);

/**
 * Тонкая обёртка над PDO. Драйвер mysql — для боевого хостинга,
 * sqlite — только для локальной разработки/тестов (одинаковый SQL-минимум).
 */
class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $driver = Config::get('db.driver', 'mysql');
            if ($driver === 'sqlite') {
                $path = Config::get('db.sqlite_path');
                is_dir(dirname($path)) || mkdir(dirname($path), 0775, true);
                self::$pdo = new PDO('sqlite:' . $path);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    Config::get('db.host', 'localhost'),
                    (int)Config::get('db.port', 3306),
                    Config::get('db.name', ''),
                    Config::get('db.charset', 'utf8mb4')
                );
                self::$pdo = new PDO($dsn, Config::get('db.user', ''), Config::get('db.pass', ''), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function queryAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ') VALUES ('
             . implode(',', array_map(fn($c) => ":$c", $cols)) . ')';
        self::run($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(',', array_map(fn($c) => "$c = :set_$c", array_keys($data)));
        $params = [];
        foreach ($data as $k => $v) { $params['set_' . $k] = $v; }
        $st = self::run("UPDATE {$table} SET {$set} WHERE {$where}", $params + $whereParams);
        return $st->rowCount();
    }

    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $res = $fn($pdo);
            $pdo->commit();
            return $res;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

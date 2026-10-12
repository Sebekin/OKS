<?php
declare(strict_types=1);

/** Загрузка и чтение конфигурации. */
class Config
{
    private static array $cfg = [];

    public static function load(string $path): void
    {
        if (is_file($path)) {
            self::$cfg = require $path;
        }
    }

    public static function get(string $key, $default = null)
    {
        $node = self::$cfg;
        foreach (explode('.', $key) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) return $default;
            $node = $node[$part];
        }
        return $node;
    }
}

/** Простые настройки из таблицы settings (кэшируются за запрос). */
function Settings_value(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::queryAll('SELECT k, v FROM settings') as $r) {
                $cache[$r['k']] = $r['v'];
            }
        } catch (Throwable $e) { /* до установки таблицы могут отсутствовать */ }
    }
    return $cache[$key] ?? $default;
}
